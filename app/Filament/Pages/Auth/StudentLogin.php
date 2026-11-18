<?php

namespace App\Filament\Pages\Auth;

use Filament\Pages\Auth\Login as BaseLogin;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Illuminate\Validation\ValidationException;
use App\Models\User;

class StudentLogin extends BaseLogin
{
    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return 'Entrar no Portal do Aluno';
    }

    protected function getAuthenticateFormAction(): \Filament\Actions\Action
    {
        return parent::getAuthenticateFormAction()
            ->label('Entrar');
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $data = $this->form->getState();
            
            // Check if user exists first and check their student status
            $user = User::where('email', $data['email'])->first();
            
            if ($user && \Illuminate\Support\Facades\Hash::check($data['password'], $user->password)) {
                if ($user->role === 'student') {
                    if (!$user->student) {
                        // Senha correta, mas não há perfil de aluno vinculado — canAccessPanel()
                        // negaria o acesso e o Filament mostraria a mensagem genérica "credenciais
                        // não coincidem", confundindo o aluno que digitou tudo certo.
                        throw ValidationException::withMessages([
                            'data.email' => __('Sua conta não está vinculada a um perfil de aluno. Entre em contato com o Prof. Atilio para corrigir seu cadastro.'),
                        ]);
                    }

                    $status = $user->student->status;
                    if ($status === 'awaiting_email_validation') {
                        throw ValidationException::withMessages([
                            'data.email' => __('Sua conta ainda não foi confirmada por e-mail. Por favor, verifique sua caixa de entrada ou use o link de reenvio abaixo.'),
                        ]);
                    } elseif ($status === 'awaiting_advisor_approval') {
                        throw ValidationException::withMessages([
                            'data.email' => __('Sua conta está aguardando a aprovação manual do Prof. Atilio.'),
                        ]);
                    } elseif (!in_array($status, ['active', 'inactive'], true)) {
                        throw ValidationException::withMessages([
                            'data.email' => __('Sua conta está com um status de cadastro inválido. Entre em contato com o Prof. Atilio para corrigir seu cadastro.'),
                        ]);
                    }
                }

                if ($user->role === 'reviewer') {
                    throw ValidationException::withMessages([
                        'data.email' => __('Sua conta é de revisor(a) de artigos/livros. Acesse pela Área do Revisor: ') . url('/revisor'),
                    ]);
                }
            }

            return parent::authenticate();
        } catch (ValidationException $e) {
            throw $e;
        }
    }
}
