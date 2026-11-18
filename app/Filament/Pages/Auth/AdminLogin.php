<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminLogin extends BaseLogin
{
    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return 'Entrar no Painel do Professor';
    }

    protected function getAuthenticateFormAction(): \Filament\Actions\Action
    {
        return parent::getAuthenticateFormAction()
            ->label('Entrar');
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            // O parent aplica o rate limit antes de qualquer checagem de senha.
            return parent::authenticate();
        } catch (ValidationException $e) {
            $data = $this->form->getState();
            $user = User::where('email', $data['email'])->first();

            // Aluno com senha correta tentando entrar pela área do professor: canAccessPanel('admin')
            // é falso e o Filament mostra a mensagem genérica "credenciais não correspondem",
            // fazendo o aluno achar que errou a senha (vários e-mails antigos apontavam para /admin).
            if ($user && $user->role === 'student' && Hash::check($data['password'], $user->password)) {
                throw ValidationException::withMessages([
                    'data.email' => 'Esta é a área exclusiva do professor. Alunos entram pelo Portal do Aluno: ' . url('/portal/login'),
                ]);
            }

            throw $e;
        }
    }
}
