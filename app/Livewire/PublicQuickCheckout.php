<?php

namespace App\Livewire;

use App\Mail\QuickPaymentOptionSelectedAdminMail;
use App\Models\ConsentRecord;
use App\Models\QuickPayment;
use App\Models\Payment;
use App\Models\User;
use App\Services\MercadoPagoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.guest', ['noindex' => true, 'title' => 'Pagamento'])]
class PublicQuickCheckout extends Component
{
    public string $uuid;
    public ?QuickPayment $quickPayment = null;
    public ?int $selectedOptionIndex = null;
    public bool $materialsConfirmed = false;

    // Login fields
    public string $loginEmail = '';
    public string $loginPassword = '';

    // Registration fields (Rule #2 accessibility compliant)
    public string $registerName = '';
    public string $registerMotherName = '';
    public string $registerBirthdate = ''; // DD/MM/AAAA
    public string $registerCountry = 'BR';
    public string $registerCpf = '';
    public string $registerEmail = '';
    public string $registerPhone = '';
    public string $registerPassword = '';
    public string $registerPasswordConfirmation = '';
    public bool $registerConsent = false;

    // View state
    public string $activeTab = 'register'; // 'register' or 'login'

    public function mount(string $uuid): void
    {
        $this->uuid = $uuid;
        $this->quickPayment = QuickPayment::where('uuid', $uuid)->first();

        if (!$this->quickPayment) {
            abort(404, 'Link de pagamento não encontrado ou expirado.');
        }

        // Single-option links (including legacy links created before the
        // multi-option feature, which have no "options" at all) skip the
        // choice screen entirely and behave exactly as before.
        $filled = $this->quickPayment->filledOptions();
        if (count($filled) === 1) {
            $this->selectedOptionIndex = array_key_first($filled);
        }

        // Links generated with a client already selected by the admin skip
        // straight to login, pre-filled to that account, instead of making
        // the person choose between registering and logging in.
        if ($this->quickPayment->user_id !== null) {
            $this->activeTab = 'login';
            $this->loginEmail = $this->quickPayment->user->email;
        }
    }

    public function selectOption(int $index): void
    {
        if ($this->quickPayment->status !== 'pending' || empty($this->quickPayment->options[$index] ?? null)) {
            return;
        }

        $this->selectedOptionIndex = $index;
    }

    public function changeOption(): void
    {
        if ($this->quickPayment->status === 'pending' && $this->quickPayment->selected_option_index === null) {
            $this->selectedOptionIndex = null;
        }
    }

    public function getRequiredMaterialsProperty(): array
    {
        if (!$this->quickPayment) {
            return [];
        }

        $items = $this->selectedOptionIndex !== null
            ? ($this->quickPayment->options[$this->selectedOptionIndex]['items'] ?? [])
            : ($this->quickPayment->items ?? []);

        return collect($items)
            ->filter(fn ($item) => !empty($item['by_client']))
            ->pluck('name')
            ->values()
            ->all();
    }

    /**
     * Parse date from DD/MM/AAAA to YYYY-MM-DD (Rule #2 requirement)
     */
    protected function parseDate(string $date): ?string
    {
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $matches)) {
            return "{$matches[3]}-{$matches[2]}-{$matches[1]}";
        }
        return null;
    }

    public function registerAndPay()
    {
        if ($this->quickPayment->status !== 'pending') {
            return;
        }

        // This e-mail already has an account — don't fail with a raw
        // "already taken" error, send the person to the login tab instead,
        // since that's what they actually need to do to continue.
        if (trim($this->registerEmail) !== '' && User::where('email', trim($this->registerEmail))->exists()) {
            $this->activeTab = 'login';
            $this->loginEmail = trim($this->registerEmail);
            $this->addError('loginEmail', 'Este e-mail já tem cadastro. Faça login com sua senha para continuar.');

            return;
        }

        // Parse the birthdate first to validate it correctly
        $parsedBirthdate = $this->parseDate($this->registerBirthdate);

        $this->validate([
            'registerName' => ['required', 'string', 'max:255'],
            'registerMotherName' => ['required', 'string', 'max:255'],
            'registerBirthdate' => [
                'required',
                'string',
                function ($attribute, $value, $fail) use ($parsedBirthdate) {
                    if (!$parsedBirthdate) {
                        $fail('A data de nascimento deve estar no formato DD/MM/AAAA.');
                    } else {
                        $timestamp = strtotime($parsedBirthdate);
                        if (!$timestamp || $timestamp > time()) {
                            $fail('A data de nascimento deve ser uma data válida no passado.');
                        }
                    }
                }
            ],
            'registerCountry' => ['required', 'string', 'max:3'],
            'registerCpf' => [
                'required_if:registerCountry,BR',
                'nullable',
                'string',
                'regex:/^\d{3}\.?\d{3}\.?\d{3}-?\d{2}$/'
            ],
            'registerEmail' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'registerPhone' => ['required', 'string', 'max:30'],
            'registerPassword' => ['required', 'string', 'min:8', 'same:registerPasswordConfirmation'],
            'registerConsent' => ['accepted'],
        ], [
            'registerConsent.accepted' => 'Você precisa aceitar os termos de consentimento.',
            'registerCpf.regex' => 'Formato de CPF inválido.',
            'registerPassword.same' => 'As senhas não coincidem.',
        ]);

        // Create the user
        $user = User::create([
            'name' => $this->registerName,
            'mother_maiden_name' => $this->registerMotherName,
            'birthdate' => $parsedBirthdate,
            'country' => $this->registerCountry,
            'cpf' => $this->registerCpf ? preg_replace('/\D/', '', $this->registerCpf) : null,
            'email' => $this->registerEmail,
            'phone' => $this->registerPhone,
            'role' => 'consulente',
            'status' => 'active', // Approved directly to checkout
            'password' => Hash::make($this->registerPassword),
        ]);

        ConsentRecord::create([
            'user_id' => $user->id,
            'type' => 'lgpd_cadastro',
            'accepted_at' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        Auth::login($user);

        // Associate user to quick payment
        $this->quickPayment->update(['user_id' => $user->id]);

        return $this->processPayment();
    }

    public function loginAndPay()
    {
        if ($this->quickPayment->status !== 'pending') {
            return;
        }

        $credentials = $this->validate([
            'loginEmail' => ['required', 'email'],
            'loginPassword' => ['required', 'string'],
        ]);

        if (Auth::attempt(['email' => $credentials['loginEmail'], 'password' => $credentials['loginPassword']])) {
            // Links pre-assigned to a specific client by the admin are
            // exclusive to that account — a different login must not be
            // able to hijack and pay someone else's link.
            if ($this->quickPayment->user_id !== null && Auth::id() !== $this->quickPayment->user_id) {
                Auth::logout();
                $this->addError('loginEmail', 'Este link de pagamento é exclusivo para outra pessoa. Entre em contato com o suporte.');
                return;
            }

            // Associate user to quick payment
            $this->quickPayment->update(['user_id' => Auth::id()]);
            return $this->processPayment();
        }

        $this->addError('loginEmail', 'E-mail ou senha incorretos.');
    }

    public function processPayment()
    {
        if (!Auth::check() || !$this->quickPayment || $this->quickPayment->status !== 'pending') {
            return;
        }

        $this->resetErrorBag(['materialsConfirmed', 'checkout']);
        if (!empty($this->getRequiredMaterialsProperty()) && !$this->materialsConfirmed) {
            $this->addError('materialsConfirmed', 'Confirme que você vai fornecer os itens listados antes de continuar.');
            return;
        }

        $user = Auth::user();

        // Persist which option the client chose (only once) and notify all
        // active admins that the quote is awaiting payment, before creating
        // the actual Payment/preference below.
        if ($this->selectedOptionIndex !== null && $this->quickPayment->selected_option_index === null) {
            $option = $this->quickPayment->options[$this->selectedOptionIndex] ?? null;

            if ($option) {
                $isMultiOption = $this->quickPayment->hasMultipleOptions();

                $this->quickPayment->update([
                    'selected_option_index' => $this->selectedOptionIndex,
                    'subtotal' => $option['subtotal'],
                    'total' => $option['total'],
                ]);

                if ($isMultiOption) {
                    $admins = User::activeAdmins();
                    if ($admins->isNotEmpty()) {
                        try {
                            Mail::to($admins)->send(new QuickPaymentOptionSelectedAdminMail($this->quickPayment, $option, $this->selectedOptionIndex));
                        } catch (\Exception $e) {
                            // mesmo padrão silencioso usado nos outros envios de e-mail
                        }
                    }
                }
            }
        }

        // Reuse a pending payment for this same link instead of spawning a
        // new one on every double-click / reload / open tab. Not scoped to
        // the user: whoever actually completes checkout might register or
        // log in with a different email than whoever the link was sent
        // to, but it must still resolve to the one payment tied to this
        // link, not a duplicate.
        $payment = Payment::findOrCreatePending(
            QuickPayment::class,
            $this->quickPayment->id,
            $user->id,
            (float) $this->quickPayment->total,
            scopeToUser: false
        );

        $description = 'Pagamento — ' . match ($this->quickPayment->type) {
            'consulta' => 'Consulta oracular',
            'trabalhos_gerais' => 'Trabalhos gerais',
            'iniciacao' => 'Iniciação',
            'aborisa' => 'Aborisa',
            default => 'Orçamento'
        };

        $selectedOption = $this->quickPayment->selectedOption();
        if ($selectedOption && !empty($selectedOption['comment'])) {
            $description .= ' — ' . $selectedOption['comment'];
        }

        if (MercadoPagoService::configured()) {
            if ($payment->checkout_url) {
                return redirect($payment->checkout_url);
            }

            $checkoutUrl = (new MercadoPagoService())->createPreference($payment, $description);

            if ($checkoutUrl === null) {
                $this->addError('checkout', 'Não foi possível gerar o pagamento agora. Tente novamente em instantes ou fale com o suporte.');
                return;
            }

            return redirect($checkoutUrl);
        }

        // Mercado Pago not configured: payment stays pending for manual
        // confirmation by the admin (e.g. PIX/cash) via the Cobranças panel.
        $payment->update([
            'checkout_url' => route('dashboard'),
        ]);

        session()->flash('success', 'Recebemos sua solicitação. Nossa equipe vai confirmar o pagamento e entrar em contato em breve.');
        return redirect()->route('dashboard');
    }

    public function render()
    {
        return view('livewire.public-quick-checkout', [
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
