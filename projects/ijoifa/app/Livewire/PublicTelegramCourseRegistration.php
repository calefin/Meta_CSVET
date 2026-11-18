<?php

namespace App\Livewire;

use App\Mail\TelegramCoursePaymentLinkMail;
use App\Models\Payment;
use App\Models\TelegramCourseSubscription;
use App\Models\User;
use App\Services\MercadoPagoService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Component;

class PublicTelegramCourseRegistration extends Component
{
    public string $full_name = '';

    public string $email = '';

    public ?string $phone = '';

    public string $telegram_username = '';

    public bool $accept_no_tefa_odu = false;

    public bool $accept_initiation_requirements = false;

    public bool $accept_aborisa_variable_effect = false;

    public bool $accept_format_and_frequency = false;

    public bool $accept_materials_responsibility = false;

    public bool $accept_presential_sacrifices = false;

    public ?string $checkoutUrl = null;

    public ?TelegramCourseSubscription $subscription = null;

    public bool $showRecoverForm = false;

    public string $recoverEmail = '';

    public function mount(): void
    {
        if (auth()->check()) {
            $user = auth()->user();
            $this->full_name = $user->name;
            $this->email = $user->email;
            $this->phone = $user->phone ?? '';

            $existingSub = TelegramCourseSubscription::where('user_id', $user->id)
                ->orWhere('email', $user->email)
                ->latest()
                ->first();

            if ($existingSub) {
                $this->subscription = $existingSub;
                $this->telegram_username = $existingSub->telegram_username;
            }
        }
    }

    protected function rules(): array
    {
        return [
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:30',
            'telegram_username' => 'required|string|max:100',
            'accept_no_tefa_odu' => 'accepted',
            'accept_initiation_requirements' => 'accepted',
            'accept_aborisa_variable_effect' => 'accepted',
            'accept_format_and_frequency' => 'accepted',
            'accept_materials_responsibility' => 'accepted',
            'accept_presential_sacrifices' => 'accepted',
        ];
    }

    protected function messages(): array
    {
        return [
            'full_name.required' => 'Por favor, informe seu nome completo.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.email' => 'Informe um e-mail válido.',
            'telegram_username.required' => 'Informe seu usuário ou telefone do Telegram.',
            'accept_no_tefa_odu.accepted' => 'Você precisa aceitar o termo sobre o escopo sem Tefa Odù.',
            'accept_initiation_requirements.accepted' => 'Você precisa declarar ciência sobre eventuais exigências de iniciação.',
            'accept_aborisa_variable_effect.accepted' => 'Você precisa aceitar a condição de variabilidade para Aborisa.',
            'accept_format_and_frequency.accepted' => 'Você precisa concordar com o formato de aulas e encontros.',
            'accept_materials_responsibility.accepted' => 'Você precisa aceitar a responsabilidade pelos materiais presenciais.',
            'accept_presential_sacrifices.accepted' => 'Você precisa aceitar que sacrifícios de ativação ocorrem presencialmente.',
        ];
    }

    public function register(): void
    {
        $this->validate();

        $telegramClean = trim($this->telegram_username);
        if (! Str::startsWith($telegramClean, '@') && ! preg_match('/^\+?\d+$/', $telegramClean)) {
            $telegramClean = '@'.ltrim($telegramClean, '@');
        }

        $user = auth()->user() ?? User::where('email', $this->email)->first();

        if (! $user) {
            $user = User::create([
                'name' => trim($this->full_name),
                'email' => strtolower(trim($this->email)),
                'phone' => trim($this->phone),
                'mother_maiden_name' => 'Não informada',
                'role' => 'consulente',
                'status' => 'active',
                'password' => Hash::make(Str::random(16)),
            ]);
        }

        // Seek or create active subscription
        $sub = TelegramCourseSubscription::updateOrCreate(
            ['email' => $this->email],
            [
                'user_id' => $user->id,
                'full_name' => trim($this->full_name),
                'phone' => trim($this->phone),
                'telegram_username' => $telegramClean,
                'monthly_price' => 180.00,
                'status' => 'pending_payment',
                'terms_accepted' => true,
                'terms_accepted_at' => now(),
                'terms_ip_address' => request()->ip(),
            ]
        );

        $this->subscription = $sub;

        $payment = Payment::findOrCreatePending(
            payableType: TelegramCourseSubscription::class,
            payableId: $sub->id,
            userId: $user->id,
            amount: 180.00,
            scopeToUser: false
        );

        $mpService = new MercadoPagoService;
        $url = $mpService->createPreference($payment, 'Curso Telegram — 1ª Mensalidade ('.$sub->full_name.')', maxInstallments: 1);

        $paymentUrl = $url ?? $payment->checkout_url ?? route('public-telegram-course-registration');
        $this->sendPaymentLinkEmail($sub, $paymentUrl);

        if ($url) {
            $this->redirect($url);

            return;
        }

        $this->checkoutUrl = $paymentUrl ?? '#';
        session()->flash('info', 'Inscrição registrada! Utilize o link de pagamento abaixo para concluir. Também enviamos esse link para o seu e-mail.');
    }

    public function toggleRecoverForm(): void
    {
        $this->showRecoverForm = ! $this->showRecoverForm;
        $this->recoverEmail = '';
        $this->resetErrorBag('recoverEmail');
    }

    public function resendPaymentLink(): void
    {
        $this->validate(['recoverEmail' => 'required|email']);

        $sub = TelegramCourseSubscription::where('email', $this->recoverEmail)
            ->where('status', 'pending_payment')
            ->latest()
            ->first();

        if ($sub && $sub->user_id) {
            $payment = Payment::findOrCreatePending(
                payableType: TelegramCourseSubscription::class,
                payableId: $sub->id,
                userId: $sub->user_id,
                amount: 180.00,
                scopeToUser: false
            );

            $mpService = new MercadoPagoService;
            $url = $mpService->createPreference($payment, 'Curso Telegram — 1ª Mensalidade ('.$sub->full_name.')', maxInstallments: 1);
            $paymentUrl = $url ?? $payment->checkout_url ?? route('public-telegram-course-registration');

            $this->sendPaymentLinkEmail($sub, $paymentUrl);
        }

        $this->showRecoverForm = false;
        session()->flash('info', 'Se encontrarmos uma inscrição pendente de pagamento com esse e-mail, reenviamos o link. Confira sua caixa de entrada (e o spam).');
    }

    private function sendPaymentLinkEmail(TelegramCourseSubscription $sub, string $paymentUrl): void
    {
        try {
            Mail::to($sub->email)->send(new TelegramCoursePaymentLinkMail($sub, $paymentUrl));
        } catch (\Throwable $e) {
            Log::error('Erro ao enviar e-mail de link de pagamento da inscrição do Curso Telegram: '.$e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.public-telegram-course-registration')
            ->layout('components.layouts.guest', [
                'title' => 'Inscrição no Curso Via Telegram — Ìjọ Ifá Èjìogbè',
                'description' => 'Inscreva-se no curso contínuo do Telegram com 2 aulas semanais e 1 encontro presencial prático por mês.',
            ]);
    }
}
