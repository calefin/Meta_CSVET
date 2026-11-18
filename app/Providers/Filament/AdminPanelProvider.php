<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
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

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(\App\Filament\Pages\Auth\AdminLogin::class)
            ->passwordReset()
            ->brandName('Prof. Atilio')
            ->brandLogo(asset('images/logo.png'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('images/favicon.png'))
            ->font('Outfit')
            ->sidebarCollapsibleOnDesktop()
            // Ordem do menu: o dia a dia primeiro; o que é raro começa recolhido.
            ->navigationGroups([
                NavigationGroup::make('Programa de Metanálises'),
                NavigationGroup::make('Orientação Acadêmica'),
                NavigationGroup::make('Gestão Acadêmica & Devolutivas'),
                NavigationGroup::make('Controle de Alunos'),
                NavigationGroup::make('Comunicações'),
                NavigationGroup::make('Certificados')->collapsed(),
                NavigationGroup::make('Pesquisa & Publicações')->collapsed(),
                NavigationGroup::make('Gestão Pessoal')->collapsed(),
                NavigationGroup::make('Site & Blog')->collapsed(),
                NavigationGroup::make('Atendimento Clínico')->collapsed(),
                NavigationGroup::make('Minha Conta')->collapsed(),
                NavigationGroup::make('Monitoramento e Logs')->collapsed(),
            ])
            ->colors([
                'primary' => Color::Teal,
                'gray' => Color::Slate,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                \App\Filament\Widgets\AndamentoVigentesWidget::class,
                \App\Filament\Widgets\UrgentActionsWidget::class,
                \App\Filament\Widgets\PendingSupportTicketsWidget::class,
                \App\Filament\Widgets\StalledProjectsWidget::class,
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
            ]);
    }

    public function boot(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn (): string => view('partials.upload-progress-indicator')->render(),
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::SIDEBAR_FOOTER,
            fn (): string => Blade::render('
                <div class="px-4 pb-4">
                    <a href="/instalar-app" target="_blank"
                       class="flex items-center gap-2 w-full px-3 py-2 rounded-lg text-xs font-semibold text-teal-400 hover:bg-teal-900/30 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 8.25h3" />
                        </svg>
                        Instalar App no Celular
                    </a>
                </div>
            '),
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => Blade::render('
                <link rel="manifest" href="/manifest-admin.json">
                <meta name="theme-color" content="#0d9488">
                <meta name="mobile-web-app-capable" content="yes">
                <meta name="apple-mobile-web-app-capable" content="yes">
                <meta name="apple-mobile-web-app-status-bar-style" content="default">
                <meta name="apple-mobile-web-app-title" content="Prof. Atilio">
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
                    /* Cabeçalho das páginas: com muitos botões (ex.: revisão sistemática), o bloco de ações do Filament
                       tem shrink-0 e sai da tela, e o título vira uma coluna estreita. Aqui o título ocupa a linha e os
                       botões quebram em várias linhas. */
                    .fi-header { flex-wrap: wrap; }
                    .fi-header > div:first-child { flex: 1 1 22rem; min-width: 0; }
                    .fi-header > div:last-child { flex-shrink: 1; min-width: 0; max-width: 100%; }
                    .fi-header > div:last-child .fi-ac { flex-wrap: wrap; justify-content: flex-start; row-gap: .5rem; }

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
                       alguns forms (ex.: edição de processo em DocumentSubmissionResource), esse
                       cálculo falha abaixo do breakpoint lg (1024px) — resolve 2 trilhas com uma
                       colapsada a 0px, fazendo os campos ficarem sobrepostos. Sobrescrever apenas
                       grid-template-columns não é suficiente (o valor calculado continua sendo
                       ignorado). A correção robusta é remover o grid inteiramente no mobile —
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
    }
}
