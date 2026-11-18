<?php

namespace App\Livewire\Auth;

use App\Support\Audit;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.guest', ['title' => 'Redefinir senha', 'noindex' => true])]
class ResetPassword extends Component
{
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $completed = false;

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = request()->query('email', '');
    }

    public function resetPassword(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            [
                'token' => $this->token,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
            ],
            function ($user) {
                $user->forceFill(['password' => Hash::make($this->password)])->save();
                Audit::record('password.reset', $user, userId: $user->id);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            $this->addError('email', 'Este link de redefinição é inválido ou expirou. Solicite um novo.');

            return;
        }

        $this->completed = true;
    }

    public function render()
    {
        return view('livewire.auth.reset-password');
    }
}
