<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Filament\Notifications\Auth\ResetPassword implementa ShouldQueue: o e-mail de
        // "Esqueci minha senha" (fluxo de auto-serviço, resolvido via app() em
        // RequestPasswordReset::sendPasswordResetLink()) só sai depois que um worker de fila
        // processar o job. Nesta hospedagem isso depende do cron do queue:work, e falhas/atrasos
        // nele faziam o e-mail nunca chegar. Trocamos pela nossa notificação (não enfileirada),
        // que já é a mesma usada pelas ações manuais do admin em StudentResource — assim o envio
        // é sempre síncrono e imediato, independente da fila.
        $this->app->bind(
            \Filament\Notifications\Auth\ResetPassword::class,
            \App\Notifications\ResetPasswordNotification::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Atrás do Nginx Proxy Manager (HTTPS por fora, HTTP puro entre o proxy
        // e o container) o Laravel não detecta a request como segura sozinho —
        // sem isto, asset()/url() geram links http:// que o navegador bloqueia
        // silenciosamente numa página https:// (mixed content), deixando o
        // painel Filament completamente sem CSS/JS, sem nenhum erro visível.
        if ($this->app->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        \Illuminate\Support\Facades\Storage::extend('onedrive', function ($app, $config) {
            $adapter = new \App\Filesystems\OneDriveAdapter(
                $config['clientId'],
                $config['clientSecret'],
                $config['refreshToken'],
                $config['rootFolder'] ?? '',
                $config['tenant'] ?? 'common'
            );

            $driver = new \League\Flysystem\Filesystem($adapter);

            return new \Illuminate\Filesystem\FilesystemAdapter($driver, $adapter, $config);
        });

        \Illuminate\Support\Facades\Storage::extend('google', function ($app, $config) {
            $options = [];

            if (! empty($config['teamDriveId'] ?? null)) {
                $options['teamDriveId'] = $config['teamDriveId'];
            }

            $client = new \Google\Client;
            $client->setClientId($config['clientId']);
            $client->setClientSecret($config['clientSecret']);
            $client->setHttpClient(new \GuzzleHttp\Client([
                'timeout'         => 15,
                'connect_timeout' => 10,
            ]));

            if (isset($config['accessToken'])) {
                $client->setAccessToken($config['accessToken']);
            } elseif (!empty($config['refreshToken'])) {
                $client->setAccessToken([
                    'refresh_token' => $config['refreshToken'],
                    'created' => time() - 3600,
                    'expires_in' => 0,
                    'access_token' => 'dummy-token-to-satisfy-check',
                ]);
            }

            $service = new \Google\Service\Drive($client);
            $adapter = new \Masbug\Flysystem\GoogleDriveAdapter($service, $config['folder'] ?? '/', $options);
            
            $cachedAdapter = new \App\Filesystems\CachedGoogleDriveAdapter($adapter);
            $driver = new \League\Flysystem\Filesystem($cachedAdapter);

            return new \Illuminate\Filesystem\FilesystemAdapter($driver, $cachedAdapter, $config);
        });


        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Auth\Events\Authenticated::class,
            function ($event) {
                $user = $event->user;
                if ($user instanceof \App\Models\AdviseeAccount && $user->role === 'student') {
                    \App\Models\Student::firstOrCreate(
                        ['user_id' => $user->id],
                        [
                            'owner_user_id' => $user->owner_user_id,
                            'institution' => 'Não informada',
                            'academic_level' => 'Graduação',
                            'research_topic' => 'Não informado',
                        ]
                    );
                }
            }
        );

        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Mail\Events\MessageSent::class,
            function (\Illuminate\Mail\Events\MessageSent $event) {
                $message = $event->message;
                
                $recipients = [];
                if ($message->getTo()) {
                    foreach ($message->getTo() as $address) {
                        $recipients[] = $address->getAddress();
                    }
                }
                
                \App\Models\EmailLog::create([
                    'recipient' => implode(', ', $recipients),
                    'subject' => $message->getSubject() ?? '(Sem Assunto)',
                    'body' => $message->getHtmlBody() ?? $message->getTextBody() ?? '',
                    'status' => 'success',
                    'sent_at' => now(),
                ]);
            }
        );

        // LoginLog só registra login de orientando (guard 'portal') — login de
        // professor é sempre via SSO do prof-aid, cujo próprio audit trail já
        // vive no lado do ProvaGen; misturar os dois aqui exigiria uma coluna
        // que identificasse de qual tabela (users vs advisee_accounts) o id
        // veio, o que não vale a complexidade para um log secundário.
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Auth\Events\Login::class,
            function (\Illuminate\Auth\Events\Login $event) {
                if ($event->guard !== 'portal') {
                    return;
                }

                \App\Models\LoginLog::create([
                    'owner_user_id' => $event->user->owner_user_id,
                    'user_id' => $event->user->id,
                    'email' => $event->user->email,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'status' => 'success',
                    'login_at' => now(),
                ]);
            }
        );

        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Auth\Events\Failed::class,
            function (\Illuminate\Auth\Events\Failed $event) {
                if ($event->guard !== 'portal') {
                    return;
                }

                $ownerUserId = $event->user instanceof \App\Models\AdviseeAccount
                    ? $event->user->owner_user_id
                    : \App\Models\AdviseeAccount::where('email', $event->credentials['email'] ?? null)->value('owner_user_id');

                \App\Models\LoginLog::create([
                    'owner_user_id' => $ownerUserId,
                    'user_id' => $event->user ? $event->user->id : null,
                    'email' => $event->credentials['email'] ?? 'desconhecido',
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'status' => 'failed',
                    'login_at' => now(),
                ]);
            }
        );

        // queue:work é um processo de longa duração: sem isso, o owner_user_id
        // resolvido para um job vazaria para o próximo job processado pelo
        // mesmo worker. Cada Job que precisa de contexto de owner deve
        // guardar o owner_user_id no construtor e chamar OwnerContext::set()
        // no início do seu handle(); isso aqui só garante que nunca sobra sujo.
        \Illuminate\Support\Facades\Queue::after(function () {
            \App\Support\OwnerContext::clear();
        });
        \Illuminate\Support\Facades\Queue::failing(function () {
            \App\Support\OwnerContext::clear();
        });
    }
}
