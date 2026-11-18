<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Iniciado' }} — {{ config('app.name') }}</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/svg+xml" href="/logo.svg">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <meta name="theme-color" content="#2D6A4F">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-sacred-sand font-sans text-ebony" x-data="{ drawerOpen: false }">
    <div class="flex min-h-screen">
        <aside
            class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full bg-orisa-earth text-white transition-transform md:static md:translate-x-0"
            :class="{ '!translate-x-0': drawerOpen }"
        >
            <div class="flex items-center justify-between px-4 py-4">
                <span class="flex items-center gap-2 font-display text-lg"><img src="/logo.svg" alt="" class="h-7 w-7" aria-hidden="true">{{ config('app.name') }}</span>
                <button type="button" class="md:hidden" x-on:click="drawerOpen = false" aria-label="Fechar menu">&times;</button>
            </div>
            <nav class="space-y-1 px-2 text-sm">
                <a href="{{ route('iniciado.dashboard') }}" class="block rounded px-3 py-2 hover:bg-white/10 {{ request()->routeIs('iniciado.dashboard') ? 'bg-white/15' : '' }}">Painel</a>
                <a href="{{ route('iniciado.initiation') }}" class="block rounded px-3 py-2 hover:bg-white/10 {{ request()->routeIs('iniciado.initiation') ? 'bg-white/15' : '' }}">Minha iniciação</a>
                <a href="{{ route('iniciado.family') }}" class="block rounded px-3 py-2 hover:bg-white/10 {{ request()->routeIs('iniciado.family') ? 'bg-white/15' : '' }}">Família Espiritual (Ẹgbẹ́)</a>
                <a href="{{ route('ose-calendar') }}" class="block rounded px-3 py-2 hover:bg-white/10 {{ request()->routeIs('ose-calendar') ? 'bg-white/15' : '' }}">Calendário Ọ̀sẹ̀</a>
                <a href="{{ route('library.index') }}" class="block rounded px-3 py-2 hover:bg-white/10 {{ request()->routeIs('library.index') ? 'bg-white/15' : '' }}">Biblioteca</a>
                <a href="{{ route('student.courses.index') }}" class="block rounded px-3 py-2 hover:bg-white/10 {{ request()->routeIs('student.courses.index') ? 'bg-white/15' : '' }}">Cursos</a>
                <a href="{{ route('iniciado.messages') }}" class="block rounded px-3 py-2 hover:bg-white/10 {{ request()->routeIs('iniciado.messages') ? 'bg-white/15' : '' }}">Mensagens</a>
                <a href="{{ route('profile') }}" class="block rounded px-3 py-2 hover:bg-white/10 {{ request()->routeIs('profile') ? 'bg-white/15' : '' }}">Meu perfil</a>
                <a href="{{ route('my-data') }}" class="block rounded px-3 py-2 hover:bg-white/10 {{ request()->routeIs('my-data') ? 'bg-white/15' : '' }}">Meus dados</a>
            </nav>
            <div class="px-4 py-4 border-t border-white/10">
                <a
                    href="https://t.me/ijoifa_bot"
                    target="_blank"
                    rel="noopener"
                    class="flex items-center gap-2 rounded px-3 py-2 text-sm text-white/80 hover:bg-white/10"
                >
                    <svg viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4 shrink-0" aria-hidden="true">
                        <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.96 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                    </svg>
                    Fale pelo Telegram
                </a>
            </div>
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
