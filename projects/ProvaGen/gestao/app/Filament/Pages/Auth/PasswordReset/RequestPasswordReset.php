<?php

namespace App\Filament\Pages\Auth\PasswordReset;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Exception;
use Filament\Facades\Filament;
use Filament\Notifications\Auth\ResetPassword as ResetPasswordNotification;
use Filament\Notifications\Notification;
use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Filament\Pages\Auth\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Throwable;

class RequestPasswordReset extends BaseRequestPasswordReset
{
    /**
     * O padrão do Filament chama canAccessPanel() aqui e, se for falso, deixa de enviar o
     * e-mail — mas ainda reporta "e-mail enviado com sucesso" para quem pediu, porque o
     * retorno antecipado não conta como falha para o broker. Isso fazia a aluna com cadastro
     * pendente de aprovação/validação de e-mail (canAccessPanel = false) ver a mensagem de
     * sucesso sem nunca receber nada. Aqui o e-mail é sempre enviado quando o usuário existe;
     * o acesso ao portal continua sendo bloqueado normalmente (página de "Acesso Negado").
     */
    public function request(): void
    {
        try {
            $this->rateLimit(2);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return;
        }

        $data = $this->form->getState();

        // O envio agora é síncrono (ver AppServiceProvider::register()), então uma falha real
        // de SMTP aconteceria aqui dentro, no meio da requisição. Sem isso, ela subiria como
        // exceção não tratada e a aluna veria uma tela de erro genérica em vez de uma mensagem.
        $sendFailed = false;

        $status = Password::broker(Filament::getAuthPasswordBroker())->sendResetLink(
            $this->getCredentialsFromFormData($data),
            function (CanResetPassword $user, string $token) use (&$sendFailed): void {
                if (! method_exists($user, 'notify')) {
                    $userClass = $user::class;

                    throw new Exception("Model [{$userClass}] does not have a [notify()] method.");
                }

                $notification = app(ResetPasswordNotification::class, ['token' => $token]);
                $notification->url = Filament::getResetPasswordUrl($token, $user);

                try {
                    $user->notify($notification);
                } catch (Throwable $exception) {
                    Log::error('Falha ao enviar e-mail de redefinição de senha: '.$exception->getMessage());
                    $sendFailed = true;

                    return;
                }

                if (class_exists(PasswordResetLinkSent::class)) {
                    event(new PasswordResetLinkSent($user));
                }
            },
        );

        if ($sendFailed) {
            Notification::make()
                ->title('Não foi possível enviar o e-mail agora')
                ->body('Tente novamente em alguns minutos ou peça ao Prof. Atilio um link de redefinição direto.')
                ->danger()
                ->send();

            return;
        }

        if ($status !== Password::RESET_LINK_SENT) {
            $this->getFailureNotification($status)?->send();

            return;
        }

        $this->getSentNotification($status)?->send();

        $this->form->fill();
    }
}
