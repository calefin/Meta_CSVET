<?php

namespace App\Livewire\Auth;

use App\Support\Audit;
use App\Support\Recaptcha;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.guest', ['title' => 'Entrar', 'noindex' => true])]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public string $recaptchaToken = '';

    public function login(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::lower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $this->addError('email', 'Muitas tentativas. Tente novamente em alguns minutos.');

            return;
        }

        if (! Recaptcha::verify($this->recaptchaToken, 'login')) {
            $this->addError('recaptcha', 'Nao foi possivel validar que voce nao e um robo. Atualize a pagina e tente novamente.');

            return;
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($throttleKey, 60);
            $this->addError('email', __('auth.failed'));

            return;
        }

        RateLimiter::clear($throttleKey);

        $user = Auth::user();

        if ($user->status !== 'active') {
            Auth::logout();
            $this->addError(
                'email',
                $user->status === 'pending'
                    ? 'Seu cadastro ainda esta aguardando aprovacao do administrador.'
                    : 'Sua conta esta suspensa. Entre em contato com a administracao.'
            );

            return;
        }

        request()->session()->regenerate();

        Audit::record('login');

        $this->redirectRoute('dashboard', navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
