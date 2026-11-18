<?php

namespace App\Notifications;

use Filament\Facades\Filament;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    // Propriedade dinâmica que o Filament define (RequestPasswordReset::sendPasswordResetLink())
    // ao resolver esta classe no lugar de Filament\Notifications\Auth\ResetPassword (ver
    // AppServiceProvider::register()). Não é usada por toMail() abaixo, mas precisa existir
    // para evitar o warning de propriedade dinâmica do PHP 8.2+.
    public string $url = '';

    public function toMail($notifiable): MailMessage
    {
        // A rota de redefinição do Filament é protegida por ValidateSignature: só aceita URLs
        // *assinadas*. O resetUrl() padrão do Laravel gera um link sem assinatura, o que fazia
        // a página abrir como "Acesso Negado" (403). getResetPasswordUrl() produz a URL assinada
        // do painel correto (admin/portal) conforme o papel do usuário.
        $panelId = ($notifiable->role ?? null) === 'admin' ? 'admin' : 'portal';
        $url = Filament::getPanel($panelId)->getResetPasswordUrl($this->token, $notifiable);

        return (new MailMessage)
            ->subject('Redefinição de Senha — Plataforma Prof. Atilio')
            ->view('emails.reset-password', [
                'resetUrl' => $url,
                'name'     => $notifiable->name,
            ]);
    }
}
