<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * O restante do código (Filament Resources, Mailables, event listeners, etc.
 * herdados do profatilio original) chama auth()->user()/Auth::check() sem
 * especificar guard, esperando que "o usuário logado" resolva sozinho.
 * Agora que professor (guard admin, App\Models\Owner) e aluno (guard portal,
 * App\Models\AdviseeAccount) são dois guards/modelos separados, isso só
 * continua funcionando sem reescrever todos esses call sites se o guard
 * padrão da request for trocado cedo, por painel. Usar como
 * `SetDefaultAuthGuard:admin` / `SetDefaultAuthGuard:portal` no middleware
 * de cada Filament Panel.
 */
class SetDefaultAuthGuard
{
    public function handle(Request $request, Closure $next, string $guard): Response
    {
        Auth::shouldUse($guard);

        return $next($request);
    }
}
