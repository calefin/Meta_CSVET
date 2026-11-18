<?php

namespace App\Filament\Pages\Auth;

use Filament\Pages\Auth\Login as BaseLogin;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Illuminate\Validation\ValidationException;
use App\Models\AdviseeAccount;

class StudentLogin extends BaseLogin
{
    /**
     * LIMITAÇÃO CONHECIDA: email de advisee_accounts é único por
     * owner_user_id, não globalmente — dois professores diferentes podem
     * ter um aluno cadastrado com o mesmo e-mail. Este login (e o
     * Auth::attempt() padrão do Filament dentro de parent::authenticate())
     * não sabe de qual professor é o aluno antes de autenticar, então em
     * caso de colisão de e-mail entre dois professores, cai sempre no
     * primeiro cadastro encontrado. Resolve de vez com roteamento por
     * subdomínio por professor (Fase C do plano) — até lá, risco baixo
     * (colisão exige o mesmo aluno estar cadastrado em dois professores
     * diferentes com o mesmo e-mail).
     */
    public function authenticate(): ?LoginResponse
    {
        try {
            $data = $this->form->getState();

            // Check if user exists first and check their student status
            $user = AdviseeAccount::where('email', $data['email'])->first();
            
            if ($user && \Illuminate\Support\Facades\Hash::check($data['password'], $user->password)) {
                if ($user->role === 'student') {
                    if (!$user->student) {
                        // Senha correta, mas não há perfil de aluno vinculado — canAccessPanel()
                        // negaria o acesso e o Filament mostraria a mensagem genérica "credenciais
                        // não coincidem", confundindo o aluno que digitou tudo certo.
                        throw ValidationException::withMessages([
                            'email' => __('Sua conta não está vinculada a um perfil de aluno. Entre em contato com o Prof. Atilio para corrigir seu cadastro.'),
                        ]);
                    }

                    $status = $user->student->status;
                    if ($status === 'awaiting_email_validation') {
                        throw ValidationException::withMessages([
                            'email' => __('Sua conta ainda não foi confirmada por e-mail. Por favor, verifique sua caixa de entrada ou use o link de reenvio abaixo.'),
                        ]);
                    } elseif ($status === 'awaiting_advisor_approval') {
                        throw ValidationException::withMessages([
                            'email' => __('Sua conta está aguardando a aprovação manual do Prof. Atilio.'),
                        ]);
                    } elseif (!in_array($status, ['active', 'inactive'], true)) {
                        throw ValidationException::withMessages([
                            'email' => __('Sua conta está com um status de cadastro inválido. Entre em contato com o Prof. Atilio para corrigir seu cadastro.'),
                        ]);
                    }
                }
            }
            
            return parent::authenticate();
        } catch (ValidationException $e) {
            throw $e;
        }
    }
}
