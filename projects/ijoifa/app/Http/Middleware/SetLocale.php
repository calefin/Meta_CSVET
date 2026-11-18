<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin and iniciado panels are internal-only and stay in pt-BR regardless
 * of the visitor's chosen locale (see plano de fundacao, decisao de i18n).
 * Public site and consulente areas honor the session/user locale.
 */
class SetLocale
{
    private const AVAILABLE_LOCALES = ['pt-BR', 'en', 'es'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('admin/*') || $request->is('admin') || $request->is('iniciado/*') || $request->is('iniciado')) {
            app()->setLocale('pt-BR');

            return $next($request);
        }

        // URL-prefixed locale pages (/en, /es) are deterministic regardless
        // of session state - a crawler with no session must always get the
        // language the URL promises, which is also what makes hreflang
        // alternates meaningful for SEO in the first place.
        if ($request->is('en') || $request->is('en/*')) {
            app()->setLocale('en');

            return $next($request);
        }

        if ($request->is('es') || $request->is('es/*')) {
            app()->setLocale('es');

            return $next($request);
        }

        $locale = session('locale', $request->user()?->locale ?? config('app.locale'));

        if (! in_array($locale, self::AVAILABLE_LOCALES, true)) {
            $locale = config('app.locale');
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
