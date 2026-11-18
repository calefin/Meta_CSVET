<?php

namespace App\Livewire;

use App\Mail\ConsultationPaymentRequestMail;
use App\Models\ConsentRecord;
use App\Models\ConsultationPriceSetting;
use App\Models\ConsultationRequest;
use App\Models\User;
use App\Rules\YorubaText;
use App\Services\ConsultationChargeService;
use App\Support\Audit;
use App\Support\Recaptcha;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

class PublicConsultationRequest extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $mother_maiden_name = '';

    public string $birthdate = '';

    public string $country = 'BR';

    public string $cpf = '';

    public string $email = '';

    public string $phone = '';

    public string $modality = 'online';

    public string $description = '';

    public $attachment = null;

    public bool $consent = false;

    public bool $submitted = false;

    public string $recaptchaToken = '';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', new YorubaText],
            'mother_maiden_name' => ['required', 'string', 'max:255', new YorubaText],
            'birthdate' => ['nullable', 'date', 'before:today'],
            'country' => ['required', 'string', 'size:2'],
            'cpf' => [Rule::requiredIf($this->country === 'BR'), 'nullable', 'string', 'max:14'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'modality' => ['required', 'in:online'],
            'description' => ['required', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
            'consent' => ['accepted'],
        ];
    }

    public function submit(): void
    {
        if (\App\Support\Recaptcha::enabled() && ! Recaptcha::verify($this->recaptchaToken, 'public_consultation')) {
            $this->addError('recaptcha', 'Não foi possível validar que você não é um robô. Atualize a pagina e tente novamente.');
            return;
        }

        if ($this->birthdate) {
            $this->birthdate = $this->parseDate($this->birthdate);
        }

        $validated = $this->validate();

        // 1. Find or create the user
        $user = User::where('email', $this->email)->first();

        if (! $user) {
            $user = User::create([
                'name' => $validated['name'],
                'mother_maiden_name' => $validated['mother_maiden_name'],
                'birthdate' => $validated['birthdate'] ?: null,
                'country' => $validated['country'],
                'cpf' => $validated['cpf'] ?: null,
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'role' => 'consulente',
                'status' => 'active', // Active immediately so they can log in if they want to.
                'locale' => app()->getLocale(),
                'password' => Hash::make(Str::random(16)),
            ]);

            // Save LGPD consent for new user
            ConsentRecord::create([
                'user_id' => $user->id,
                'type' => 'lgpd_cadastro',
                'accepted_at' => now(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            Audit::record('register.public_consultation', $user, userId: $user->id);
        } else {
            // Optionally update phone if it was empty/null
            if (empty($user->phone)) {
                $user->update(['phone' => $validated['phone']]);
            }
            // Optionally update birthdate if it was empty/null in existing user
            if (empty($user->birthdate) && ! empty($validated['birthdate'])) {
                $user->update(['birthdate' => $validated['birthdate']]);
            }
        }

        // 2. Upload attachment if present
        $path = $this->attachment?->store('consultation-attachments', 'local');

        // 3. Create Consultation Request
        $request = ConsultationRequest::create([
            'user_id' => $user->id,
            'modality' => $validated['modality'],
            'description' => $validated['description'],
            'attachment_path' => $path,
            'status' => 'pending',
        ]);

        Audit::record('consultation_request.create_public', $request, userId: $user->id);

        // Send confirmation HTML email to the client
        try {
            \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\ConsultationRequestConfirmationClientMail($request, $user));
        } catch (\Exception $e) {
            // Silently capture or ignore mail driver issues in tests/dev environments
        }

        // Charge automatically at the configured price — no admin approval
        // step required. If this fails (Mercado Pago unreachable, etc.) the
        // request stays 'pending' and shows up in the admin's Fila Oracular
        // for a manual retry via RequestsQueue::confirm().
        $payment = ConsultationChargeService::charge($request, ConsultationPriceSetting::current()->chargeAmount());

        if ($payment) {
            try {
                $payment->refresh();
                \Illuminate\Support\Facades\Mail::to($user->email)->send(new ConsultationPaymentRequestMail($request, $user, $payment));
            } catch (\Exception $e) {
                // Silently capture or ignore mail driver issues in tests/dev environments
            }
        }

        // Send notification HTML email to all active admins
        try {
            $admins = User::activeAdmins();
            if ($admins->isNotEmpty()) {
                \Illuminate\Support\Facades\Mail::to($admins)->send(new \App\Mail\ConsultationRequestReceivedAdminMail($request, $user));
            }
        } catch (\Exception $e) {
            // Silently capture or ignore
        }

        $this->reset(['name', 'mother_maiden_name', 'birthdate', 'country', 'cpf', 'email', 'phone', 'modality', 'description', 'attachment', 'consent']);
        $this->submitted = true;
    }

    public function render()
    {
        $locale = app()->getLocale();
        
        $titles = [
            'pt-BR' => 'Solicitar Consulta Oracular — Jogo de Ifá',
            'en' => 'Request Oracular Consultation — Ifá Reading',
            'es' => 'Solicitar Consulta Oracular — Lectura de Ifá',
        ];
        
        $descriptions = [
            'pt-BR' => 'Solicite sua consulta oracular de Ifá com o Babalawo. Atendimento online para todo o Brasil. Rápido, prático e seguro.',
            'en' => 'Request your Ifá oracular consultation with the Babalawo. Online sessions available. Simple, practical, and secure.',
            'es' => 'Solicite su consulta oracular de Ifá con el Babalawo. Sesiones en línea disponibles. Rápido, práctico y seguro.',
        ];

        $title = $titles[$locale] ?? $titles['pt-BR'];
        $description = $descriptions[$locale] ?? $descriptions['pt-BR'];

        return view('livewire.public-consultation-request', [
            'countries' => [
                'BR' => 'Brasil',
                'PT' => 'Portugal',
                'US' => 'Estados Unidos',
                'ES' => 'Espanha',
                'AR' => 'Argentina',
                'CU' => 'Cuba',
                'MX' => 'México',
                'NG' => 'Nigéria',
                'CO' => 'Colômbia',
                'UY' => 'Uruguai',
            ],
        ])
        ->layout('components.layouts.guest', [
            'title' => $title,
            'description' => $description,
            'hreflangs' => [
                'pt-BR' => route('public-consultation-request'),
                'en' => route('public-consultation-request.en'),
                'es' => route('public-consultation-request.es'),
                'x-default' => route('public-consultation-request'),
            ]
        ]);
    }

    private function parseDate(string $value): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $matches)) {
            return "{$matches[3]}-{$matches[2]}-{$matches[1]}";
        }

        return $value;
    }
}
