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
        // Força idioma padrão pt_BR para toda a aplicação e painéis Filament
        app()->setLocale('pt_BR');

        // Datas relativas (->since(), diffForHumans(): "há 2 semanas") sempre em
        // português, independente do APP_LOCALE do servidor — em produção
        // apareciam em inglês ("2 weeks ago") na coluna "Parado Há".
        \Carbon\Carbon::setLocale('pt_BR');
        \Carbon\CarbonImmutable::setLocale('pt_BR');

        // Revisão de artigos: o "throttle:N,1" padrão conta todas as rotas do
        // mesmo IP juntas — abrir a página, baixar e salvar a ficha esgotava o
        // limite do envio (429). Aqui cada rota tem o seu contador.
        foreach (['article-review-read' => 60, 'article-review-download' => 20, 'article-review-write' => 10, 'article-review-auth' => 6] as $name => $perMinute) {
            \Illuminate\Support\Facades\RateLimiter::for($name, fn (\Illuminate\Http\Request $request) => \Illuminate\Cache\RateLimiting\Limit::perMinute($perMinute)
                ->by($name . '|' . ($request->route()?->getName() ?? '') . '|' . $request->ip()));
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

            // Cada requisição HTTP cria um Google\Client novo. Sem cache, isso forçava um
            // round-trip de refresh_token -> access_token na accounts.google.com em TODA
            // operação de Drive (upload, download, exists, list), mesmo que o token anterior
            // ainda fosse válido — essa era a maior causa da lentidão percebida em
            // upload/download de arquivos. Agora reaproveitamos o access_token em cache
            // (mesmo backend de cache já usado pelo CachedGoogleDriveAdapter) enquanto ele
            // não expirar, e só pagamos o refresh quando realmente necessário.
            $tokenCacheKey = 'gdrive:access_token:' . md5($config['clientId'] . '|' . ($config['refreshToken'] ?? ''));
            $cachedToken = \Illuminate\Support\Facades\Cache::get($tokenCacheKey);

            if ($cachedToken) {
                $client->setAccessToken($cachedToken);
            } elseif (isset($config['accessToken'])) {
                $client->setAccessToken($config['accessToken']);
            } elseif (!empty($config['refreshToken'])) {
                $client->setAccessToken([
                    'refresh_token' => $config['refreshToken'],
                    'created' => time() - 3600,
                    'expires_in' => 0,
                    'access_token' => 'dummy-token-to-satisfy-check',
                ]);
            }

            if ($client->isAccessTokenExpired() && !empty($config['refreshToken'])) {
                try {
                    $newToken = $client->fetchAccessTokenWithRefreshToken($config['refreshToken']);
                    if (isset($newToken['access_token'])) {
                        $client->setAccessToken($newToken);
                        $ttl = max(60, ($newToken['expires_in'] ?? 3600) - 120);
                        \Illuminate\Support\Facades\Cache::put($tokenCacheKey, $newToken, $ttl);
                    }
                } catch (\Exception $e) {
                    // Se o refresh eager falhar, o Google\Client ainda tenta renovar
                    // automaticamente por conta própria na primeira chamada à API do Drive.
                    \Illuminate\Support\Facades\Log::warning('Falha ao renovar access_token do Google Drive antecipadamente: ' . $e->getMessage());
                }
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
                if ($user && $user->role === 'student') {
                    \App\Models\Student::firstOrCreate(
                        ['user_id' => $user->id],
                        [
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

        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Auth\Events\Login::class,
            function (\Illuminate\Auth\Events\Login $event) {
                \App\Models\LoginLog::create([
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
                \App\Models\LoginLog::create([
                    'user_id' => $event->user ? $event->user->id : null,
                    'email' => $event->credentials['email'] ?? 'desconhecido',
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'status' => 'failed',
                    'login_at' => now(),
                ]);
            }
        );

    }
}
