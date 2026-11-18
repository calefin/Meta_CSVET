<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Administração' }} — {{ config('app.name') }}</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/svg+xml" href="/logo.svg">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <meta name="theme-color" content="#2D6A4F">
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/admin.js', 'resources/js/editor.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-sacred-sand font-sans text-ebony" x-data="{ drawerOpen: false }">
    <div class="flex min-h-screen">
        <aside
            class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full bg-ifa-green text-white transition-transform md:static md:translate-x-0"
            :class="{ '!translate-x-0': drawerOpen }"
        >
            <div class="flex items-center justify-between px-4 py-4">
                <span class="flex items-center gap-2 font-display text-lg"><img src="/logo.svg" alt="" class="h-7 w-7" aria-hidden="true">{{ config('app.name') }}</span>
                <button type="button" class="md:hidden" x-on:click="drawerOpen = false" aria-label="Fechar menu">&times;</button>
            </div>
            <nav class="space-y-1 overflow-y-auto px-2 pb-6 text-sm" style="max-height: calc(100vh - 4.5rem)">
                <a href="{{ route('admin.dashboard') }}" class="block rounded px-3 py-2 hover:bg-white/10 {{ request()->routeIs('admin.dashboard') ? 'bg-white/15' : '' }}">Painel</a>
                <a href="{{ route('profile') }}" class="block rounded px-3 py-2 hover:bg-white/10 {{ request()->routeIs('profile') ? 'bg-white/15' : '' }}">Meu perfil</a>
                <a href="{{ route('admin.users') }}" class="block rounded px-3 py-2 hover:bg-white/10 {{ request()->routeIs('admin.users') ? 'bg-white/15' : '' }}">Usuários</a>
                <a href="{{ route('admin.approvals') }}" class="block rounded px-3 py-2 hover:bg-white/10 {{ request()->routeIs('admin.approvals') ? 'bg-white/15' : '' }}">Aprovação de cadastros</a>
                <a href="{{ route('admin.diary') }}" class="block rounded px-3 py-2 hover:bg-white/10 {{ request()->routeIs('admin.diary') ? 'bg-white/15' : '' }}">Diário do sacerdote</a>

                @php
                    $navGroups = [
                        'oracular' => ['label' => 'Atendimentos', 'active' => request()->routeIs('admin.oracular.*') || request()->routeIs('admin.agenda') || request()->routeIs('ose-calendar') || request()->routeIs('admin.clients.*'), 'links' => [
                            ['admin.oracular.queue', 'Solicitações de consulta'],
                            ['admin.agenda', 'Agenda'],
                            ['ose-calendar', 'Calendário Ọ̀sẹ̀'],
                            ['admin.oracular.works', 'Trabalhos'],
                            ['admin.oracular.new-work', 'Novo Trabalho'],
                        ]],
                        'catalogs' => ['label' => 'Catálogos', 'active' => request()->routeIs('admin.catalogs.*'), 'links' => [
                            ['admin.catalogs.odus', 'Odù'],
                            ['admin.catalogs.ibi-ire', 'Ibi / Ire'],
                            ['admin.catalogs.ritual-works', 'Trabalhos'],
                            ['admin.catalogs.suppliers', 'Fornecedores'],
                            ['admin.catalogs.supplies', 'Insumos'],
                            ['admin.catalogs.animals', 'Animais'],
                        ]],
                        'comms' => ['label' => 'Comunicação', 'active' => request()->routeIs('admin.messages') || request()->routeIs('admin.announcements') || request()->routeIs('admin.events') || request()->routeIs('admin.notes*') || request()->routeIs('admin.email-broadcast'), 'links' => [
                            ['admin.messages', 'Mensagens'],
                            ['admin.announcements', 'Avisos'],
                            ['admin.events', 'Eventos'],
                            ['admin.notes', 'Anotações privadas'],
                            ['admin.email-broadcast', 'Enviar e-mail'],
                        ]],
                        'courses' => ['label' => 'Cursos', 'active' => request()->routeIs('admin.courses.*'), 'links' => [
                            ['admin.courses.index', 'Cursos'],
                            ['admin.courses.telegram', 'Curso Telegram (Mensalidades)'],
                            ['admin.courses.quizzes', 'Correção de quizzes'],
                        ]],
                        'library' => ['label' => 'Biblioteca', 'active' => request()->routeIs('admin.library.*') || request()->routeIs('library.*'), 'links' => [
                            ['admin.library.categories', 'Categorias e documentos'],
                            ['library.index', 'Visualizar biblioteca'],
                        ]],
                        'content' => ['label' => 'Conteúdo Público', 'active' => request()->routeIs('admin.articles'), 'links' => [
                            ['admin.articles', 'Artigos (Blog)'],
                        ]],
                        'finance' => ['label' => 'Financeiro', 'active' => request()->routeIs('admin.finance.*'), 'links' => [
                            ['admin.finance.index', 'Fluxo de caixa'],
                            ['admin.finance.payments', 'Cobranças'],
                            ['admin.finance.prices', 'Monitoramento de preços'],
                        ]],
                        'system' => ['label' => 'Sistema e LGPD', 'active' => request()->routeIs('admin.deletion-requests') || request()->routeIs('admin.audit-log') || request()->routeIs('admin.backups'), 'links' => [
                            ['admin.deletion-requests', 'Exclusões (LGPD)'],
                            ['admin.audit-log', 'Log de auditoria'],
                            ['admin.backups', 'Backups'],
                        ]],
                    ];
                @endphp

                @foreach ($navGroups as $key => $group)
                    <div x-data="{ open: {{ $group['active'] ? 'true' : 'false' }} }">
                        <button
                            type="button"
                            x-on:click="open = !open"
                            class="flex w-full items-center justify-between rounded px-3 py-2 text-left text-white/90 hover:bg-white/10"
                        >
                            <span>{{ $group['label'] }}</span>
                            <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 transition-transform" :class="{ 'rotate-180': open }">
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 10.94l3.71-3.71a.75.75 0 1 1 1.06 1.06l-4.24 4.25a.75.75 0 0 1-1.06 0L5.21 8.29a.75.75 0 0 1 .02-1.08z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <div x-show="open" x-transition class="ml-3 space-y-1 border-l border-white/10 pl-2">
                            @foreach ($group['links'] as [$routeName, $label])
                                <a href="{{ route($routeName) }}" class="block rounded px-3 py-1.5 text-white/85 hover:bg-white/10 {{ request()->routeIs($routeName) ? 'bg-white/15' : '' }}">{{ $label }}</a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </nav>
        </aside>

        <div class="flex-1">
            <header class="flex items-center justify-between border-b border-orisa-earth/20 bg-white px-4 py-3">
                <button type="button" class="md:hidden" x-on:click="drawerOpen = true" aria-label="Abrir menu">&#9776;</button>
                <div class="flex items-center gap-4 text-sm">
                    <span>{{ auth()->user()?->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-ifa-green hover:underline">Sair</button>
                    </form>
                </div>
            </header>

            <x-pwa-install-banner />

            <main class="p-4 xs:p-4 md:p-8">
                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScripts
    @stack('scripts')
</body>
</html>
