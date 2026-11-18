<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->status !== 'active') {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with(
                'status_message',
                $user->status === 'pending'
                    ? 'Seu cadastro ainda esta aguardando aprovacao do administrador.'
                    : 'Sua conta esta suspensa. Entre em contato com a administracao.'
            );
        }

        return $next($request);
    }
}
