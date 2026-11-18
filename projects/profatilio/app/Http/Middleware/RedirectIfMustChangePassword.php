<?php

namespace App\Http\Middleware;

use App\Filament\Pages\TrocarSenhaObrigatoria;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfMustChangePassword
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (
            $user
            && $user->must_change_password
            && $request->route()?->getName() !== TrocarSenhaObrigatoria::getRouteName()
            && $request->route()?->getName() !== 'filament.portal.auth.logout'
        ) {
            return redirect(TrocarSenhaObrigatoria::getUrl());
        }

        return $next($request);
    }
}
