<?php

namespace App\Http\Middleware;

use App\Filament\Pages\PesquisaSemestral;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfSemesterSurveyPending
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (
            $user
            && $user->role === 'student'
            && $user->student?->needsSemesterSurvey()
            && $request->route()?->getName() !== PesquisaSemestral::getRouteName()
            && $request->route()?->getName() !== 'filament.portal.auth.logout'
        ) {
            return redirect(PesquisaSemestral::getUrl());
        }

        return $next($request);
    }
}
