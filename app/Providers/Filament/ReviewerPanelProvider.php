<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Área do Revisor (/revisor): coautores/revisores externos de artigos e
 * livros que criaram senha. Só mostra os processos de revisão da pessoa —
 * não dá acesso ao portal de orientandos nem ao painel admin.
 */
class ReviewerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('revisor')
            ->path('revisor')
            ->login()
            ->passwordReset(
                requestAction: \App\Filament\Pages\Auth\PasswordReset\RequestPasswordReset::class,
                resetAction: \App\Filament\Pages\Auth\PasswordReset\ResetPassword::class,
            )
            ->profile(isSimple: false)
            ->brandName('Área do Revisor')
            ->brandLogo(asset('images/logo.png'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('images/favicon.png'))
            ->font('Outfit')
            ->colors([
                'primary' => Color::Teal,
                'gray' => Color::Slate,
            ])
            ->pages([
                \App\Filament\Revisor\Pages\MeusProcessos::class,
            ])
            ->homeUrl(fn () => \App\Filament\Revisor\Pages\MeusProcessos::getUrl(panel: 'revisor'))
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
