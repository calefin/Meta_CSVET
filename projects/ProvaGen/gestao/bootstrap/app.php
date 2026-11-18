<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'telegram/webhook',
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\ResolveOwnerMiddleware::class,
        ]);

        // Atrás do Nginx Proxy Manager (container-to-container, sem IP
        // confiável fixo) — confia em qualquer proxy pra reconhecer
        // corretamente esquema (https) e IP real do visitante (request()->ip(),
        // usado nos logs forenses de submissão/login). Sem isso, request()->ip()
        // e Request::isSecure() enxergam a rede interna do Docker, não o
        // visitante de verdade.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
