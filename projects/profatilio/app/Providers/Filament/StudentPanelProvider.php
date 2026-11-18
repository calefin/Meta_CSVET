<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\View\PanelsRenderHook;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Facades\Blade;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use App\Filament\Resources\DocumentSubmissionResource;

class StudentPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('portal')
            ->path('portal')
            ->login(\App\Filament\Pages\Auth\StudentLogin::class)
            ->passwordReset(
                requestAction: \App\Filament\Pages\Auth\PasswordReset\RequestPasswordReset::class,
                resetAction: \App\Filament\Pages\Auth\PasswordReset\ResetPassword::class,
            )
            ->brandName('Portal do Aluno')
            ->brandLogo(asset('images/logo.png'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('images/favicon.png'))
            ->font('Outfit')
            ->sidebarCollapsibleOnDesktop()
            ->colors([
                'primary' => Color::Teal,
                'gray' => Color::Slate,
            ])
            // Metanálise separada dos trabalhos de TCC/IC/estágio (só aparece para quem está numa dupla).
            ->navigationGroups([
                NavigationGroup::make('Programa de Metanálises'),
                NavigationGroup::make('Meus Trabalhos'),
                NavigationGroup::make('Cursos & Certificados'),
                NavigationGroup::make('Gestão Pessoal'),
                NavigationGroup::make('Minha Conta'),
            ])
            ->pages([
                Pages\Dashboard::class,
                \App\Filament\Pages\ComoFunciona::class,
                \App\Filament\Pages\ManualDoAluno::class,
                \App\Filament\Pages\MeuHistorico::class,
                \App\Filament\Pages\MeuEstagio::class,
                \App\Filament\Pages\TransparenciaLgpd::class,
                \App\Filament\Pages\TrocarSenhaObrigatoria::class,
                \App\Filament\Pages\PesquisaSemestral::class,
                \App\Filament\Pages\MeusCursos::class,
                \App\Filament\Pages\Pomodoro::class,
                // Orientando que participa de revisão de artigo/livro vê no próprio menu.
                \App\Filament\Revisor\Pages\MeusProcessos::class,
                // Aluno triador de revisão sistemática (só aparece para quem foi incluído).
                \App\Filament\Pages\TriagemCega::class,
                \App\Filament\Pages\TreinoTriagem::class,
                \App\Filament\Pages\ExtracaoRob::class,
            ])
            ->resources([
                \App\Filament\Resources\DocumentSubmissionResource::class,
                \App\Filament\Resources\AcademicProjectResource::class,
                \App\Filament\Resources\StudentResource::class,
                \App\Filament\Resources\ErrorReportResource::class,
                \App\Filament\Resources\MeuCertificadoResource::class,
                \App\Filament\Resources\SupportTicketResource::class,
                \App\Filament\Resources\PersonalActivityResource::class,
            ])
            ->navigationItems([
                // Atalho direto no menu para o envio de documento, para o aluno não
                // precisar entrar em "Meus Trabalhos" e só então achar o botão de criar.
                NavigationItem::make('Enviar Novo Documento')
                    ->icon('heroicon-o-document-plus')
                    ->group('Meus Trabalhos')
                    ->sort(-11)
                    ->url(fn () => DocumentSubmissionResource::getUrl('create'))
                    ->visible(fn () => auth()->user()?->role === 'student'),
            ])
            ->widgets([
                \App\Filament\Widgets\MetanalisesPortalWidget::class,
                Widgets\AccountWidget::class,
                \App\Filament\Widgets\WelcomeGuideWidget::class,
                \App\Filament\Widgets\QuickActionsWidget::class,
                \App\Filament\Widgets\TccPipelineWidget::class,
                \App\Filament\Widgets\MyLastSubmissionsWidget::class,
                \App\Filament\Widgets\TelegramAlertWidget::class,
            ])
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
                \App\Http\Middleware\RedirectIfMustChangePassword::class,
                \App\Http\Middleware\RedirectIfSemesterSurveyPending::class,
            ]);
    }

    public function boot(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn (): string => view('partials.upload-progress-indicator')->render(),
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => Blade::render('
                <link rel="manifest" href="/manifest-portal.json">
                <meta name="theme-color" content="#0d9488">
                <meta name="mobile-web-app-capable" content="yes">
                <meta name="apple-mobile-web-app-capable" content="yes">
                <meta name="apple-mobile-web-app-status-bar-style" content="default">
                <meta name="apple-mobile-web-app-title" content="Portal do Aluno">
                <link rel="apple-touch-icon" href="/images/pwa-icon-192.png">
                <script>
                    if ("serviceWorker" in navigator) {
                        window.addEventListener("load", function () {
                            navigator.serviceWorker.register("/sw.js").catch(function () {});
                        });
                    }
                </script>
            '),
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => Blade::render('
                <style>
                    /* Remover anúncios/propaganda do FilamentInfoWidget */
                    .fi-wi-filament-info {
                        display: none !important;
                    }
                    
                    /* Design Premium, Moderno e Elegante */
                    body {
                        font-family: "Outfit", sans-serif !important;
                        letter-spacing: -0.015em;
                        -webkit-font-smoothing: antialiased;
                    }

                    /* Efeito de Vidro (Glassmorphism) e Bordas Suaves nos Cards */
                    .fi-card {
                        backdrop-filter: blur(12px) !important;
                        background-color: rgba(255, 255, 255, 0.85) !important;
                        border: 1px solid rgba(13, 148, 136, 0.1) !important;
                        border-radius: 1rem !important;
                        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -4px rgba(0, 0, 0, 0.05) !important;
                        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
                    }
                    
                    .dark .fi-card {
                        background-color: rgba(15, 23, 42, 0.75) !important;
                        border: 1px solid rgba(255, 255, 255, 0.05) !important;
                    }

                    /* Scrollbars Customizados Elegantes */
                    ::-webkit-scrollbar {
                        width: 6px;
                        height: 6px;
                    }
                    ::-webkit-scrollbar-track {
                        background: transparent;
                    }
                    ::-webkit-scrollbar-thumb {
                        background: rgba(13, 148, 136, 0.25);
                        border-radius: 8px;
                    }
                    ::-webkit-scrollbar-thumb:hover {
                        background: rgba(13, 148, 136, 0.50);
                    }

                    /* Correção de layout mobile: os grids de formulário do Filament calculam
                       grid-template-columns via variável CSS (--cols-default/--cols-lg). Em
                       alguns forms (ex.: envio de Concepção de TCC/TCC em DocumentSubmissionResource),
                       esse cálculo falha abaixo do breakpoint lg (1024px) — resolve 2 trilhas com
                       uma colapsada a 0px, fazendo os campos ficarem sobrepostos. Sobrescrever
                       apenas grid-template-columns não é suficiente (o valor calculado continua
                       sendo ignorado). A correção robusta é remover o grid inteiramente no mobile —
                       nenhum form deste app usa mais de 1 coluna abaixo do lg, então os campos só
                       precisam empilhar em fluxo normal de bloco. */
                    @media (max-width: 1023.98px) {
                        .fi-fo-component-ctn {
                            display: block !important;
                        }
                        .fi-fo-component-ctn > * {
                            margin-top: 1.5rem;
                        }
                        .fi-fo-component-ctn > *:first-child {
                            margin-top: 0;
                        }
                    }
                </style>
            '),
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
            fn (): string => \Filament\Facades\Filament::getCurrentPanel()?->getId() === 'portal' ? Blade::render('
                <div class="text-center mt-4 text-sm">
                    <p class="text-gray-600 dark:text-gray-400">
                        Não recebeu o e-mail de confirmação? 
                        <a href="/reenviar-confirmacao" class="text-teal-600 dark:text-teal-400 hover:underline font-semibold">
                            Pedir reenvio
                        </a>
                    </p>
                </div>
            ') : '',
        );
    }
}
