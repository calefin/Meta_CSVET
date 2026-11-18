<?php

namespace App\Livewire\Auth;

use App\Mail\NewRegistrationAdminMail;
use App\Mail\VerifyEmailMail;
use App\Models\ConsentRecord;
use App\Models\User;
use App\Rules\YorubaText;
use App\Support\Audit;
use App\Support\Recaptcha;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.guest', ['title' => 'Criar cadastro', 'noindex' => true])]
class Register extends Component
{
    public string $name = '';

    public string $mother_maiden_name = '';

    public string $birthdate = '';

    public string $country = 'BR';

    public string $cpf = '';

    public string $email = '';

    public string $phone = '';

    public string $password = '';

    public string $password_confirmation = '';

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
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'consent' => ['accepted'],
        ];
    }

    public function register()
    {
        if (! Recaptcha::verify($this->recaptchaToken, 'register')) {
            $this->addError('recaptcha', 'Nao foi possivel validar que voce nao e um robo. Atualize a pagina e tente novamente.');

            return;
        }

        if ($this->birthdate) {
            $this->birthdate = $this->parseDate($this->birthdate);
        }

        $validated = $this->validate();

        $user = User::create([
            'name' => $validated['name'],
            'mother_maiden_name' => $validated['mother_maiden_name'],
            'birthdate' => $validated['birthdate'] ?: null,
            'country' => $validated['country'],
            'cpf' => $validated['cpf'] ?: null,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?: null,
            'role' => 'consulente',
            'status' => 'pending',
            'locale' => app()->getLocale(),
            'password' => Hash::make($validated['password']),
        ]);

        ConsentRecord::create([
            'user_id' => $user->id,
            'type' => 'lgpd_cadastro',
            'accepted_at' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        Audit::record('register', $user, userId: $user->id);

        Mail::to($user->email)->send(new VerifyEmailMail($user));

        try {
            $admins = User::activeAdmins();
            if ($admins->isNotEmpty()) {
                Mail::to($admins)->send(new NewRegistrationAdminMail($user));
            }
        } catch (\Exception $e) {
            // Não bloqueia o cadastro do usuário se a notificação ao admin falhar
        }

        return redirect()->route('login')->with('status_message', 'Cadastro realizado com sucesso! Enviamos um e-mail de confirmação para o endereço cadastrado. Por favor, verifique sua caixa de entrada e também a pasta de spam. Após confirmar o e-mail, seu cadastro precisará ser aprovado pelo administrador.');
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

    public function render()
    {
        return view('livewire.auth.register', [
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
        ]);
    }
}
