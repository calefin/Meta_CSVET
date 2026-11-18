<!DOCTYPE html>
<html lang="{{ str_starts_with(app()->getLocale(), 'pt') ? 'pt-BR' : app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? config('app.name') }}</title>
    <meta name="description" content="{{ $description ?? __('app.home.hero_subtitle') }}">
    <link rel="canonical" href="{{ url()->current() }}">
    @if ($noindex ?? false)
        <meta name="robots" content="noindex, nofollow">
    @endif

    @isset($hreflangs)
        @foreach ($hreflangs as $hreflang => $href)
            <link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $href }}">
        @endforeach
    @endisset

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:title" content="{{ $title ?? config('app.name') }}">
    <meta property="og:description" content="{{ $description ?? __('app.home.hero_subtitle') }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ url('/icons/icon-512.png') }}">
    <meta property="og:locale" content="{{ str_replace('-', '_', app()->getLocale()) }}">
    @isset($hreflangs)
        @foreach (array_keys($hreflangs) as $hreflang)
            @continue($hreflang === 'x-default' || str_replace('-', '_', $hreflang) === str_replace('-', '_', app()->getLocale()))
            <meta property="og:locale:alternate" content="{{ str_replace('-', '_', $hreflang) }}">
        @endforeach
    @endisset
    <meta name="twitter:card" content="summary">

    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/svg+xml" href="/logo.svg">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <meta name="theme-color" content="#2D6A4F">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @if (\App\Support\Recaptcha::enabled())
        <script src="https://www.google.com/recaptcha/api.js?render={{ config('services.recaptcha.site_key') }}"></script>
    @endif
    {{ $head ?? '' }}
    @stack('head')
</head>
<body class="min-h-screen bg-sacred-sand font-sans text-ebony">
    <header class="border-b border-orisa-earth/20 bg-white px-3 py-3 xs:px-3 md:px-8" x-data="{ open: false }">
        <div class="flex items-center justify-between gap-2">
            <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-2 font-display text-lg text-ifa-green">
                <img src="/logo.svg" alt="" class="h-8 w-8 flex-shrink-0" aria-hidden="true">
                <span class="truncate">{{ config('app.name') }}</span>
            </a>
            <button type="button" class="flex-shrink-0 p-1 text-ifa-green md:hidden" x-on:click="open = !open" :aria-expanded="open" aria-label="Abrir menu">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-7 w-7">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
                </svg>
            </button>
            <nav class="hidden items-center gap-4 text-sm md:flex">
                <a href="{{ route('blog.index') }}" class="hover:text-ifa-green">{{ __('app.nav.blog') }}</a>
                <a href="{{ route('login') }}" class="hover:text-ifa-green">{{ __('app.nav.login') }}</a>
                <a href="{{ route('register') }}" class="rounded bg-ifa-green px-3 py-1.5 text-white hover:bg-ifa-green/90">{{ __('app.nav.register') }}</a>
                <livewire:shared.language-switcher />
            </nav>
        </div>
        <nav class="mt-3 flex flex-col items-start gap-3 text-sm md:hidden" x-show="open" x-cloak>
            <a href="{{ route('blog.index') }}" class="hover:text-ifa-green" x-on:click="open = false">{{ __('app.nav.blog') }}</a>
            <a href="{{ route('login') }}" class="hover:text-ifa-green" x-on:click="open = false">{{ __('app.nav.login') }}</a>
            <a href="{{ route('register') }}" class="rounded bg-ifa-green px-3 py-1.5 text-white hover:bg-ifa-green/90" x-on:click="open = false">{{ __('app.nav.register') }}</a>
            <livewire:shared.language-switcher />
        </nav>
    </header>

    <x-pwa-install-banner />

    <main>
        {{ $slot }}
    </main>

    <footer class="border-t border-orisa-earth/20 bg-white px-4 py-8 text-center text-sm text-ebony/70 xs:px-4 md:px-8">
        <p class="font-display text-base text-ifa-green">{{ config('app.name') }}</p>
        <p class="mt-1">{{ __('app.footer.location') }}</p>
        <p class="mt-1">
            <a href="https://wa.me/5511992114308" target="_blank" rel="noopener" class="hover:text-ifa-green">
                {{ __('app.footer.whatsapp') }}: (11) 99211-4308
            </a>
        </p>
        <p class="mt-3">
            <a href="{{ route('privacy') }}" class="hover:text-ifa-green">{{ __('app.footer.privacy_policy') }}</a>
        </p>
        <p class="mt-2">&copy; {{ now()->year }} {{ config('app.name') }}. {{ __('app.footer.rights') }}</p>
    </footer>

    <a
        href="https://wa.me/5511992114308"
        target="_blank"
        rel="noopener"
        aria-label="WhatsApp"
        class="fixed bottom-24 right-5 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-success text-white shadow-lg hover:bg-success/90"
    >
        <svg viewBox="0 0 24 24" fill="currentColor" class="h-7 w-7" aria-hidden="true">
            <path d="M17.47 14.38c-.29-.15-1.71-.84-1.97-.94-.27-.1-.46-.15-.66.15-.2.29-.76.94-.93 1.14-.17.2-.34.22-.63.07-.29-.15-1.22-.45-2.32-1.43-.86-.77-1.44-1.71-1.61-2-.17-.29-.02-.45.13-.6.15-.15.34-.4.5-.6.17-.2.22-.34.34-.57.12-.22.06-.42-.04-.57-.1-.15-.6-1.45-.82-1.99-.22-.52-.44-.45-.61-.46h-.52c-.17 0-.46.07-.7.34-.24.27-.93.92-.93 2.23 0 1.31.96 2.58 1.1 2.77.14.2 1.85 2.83 4.48 3.86 2.63 1.03 2.63.69 3.1.65.47-.05 1.71-.7 1.95-1.38.24-.67.24-1.25.17-1.38-.07-.12-.27-.2-.56-.34z" />
            <path d="M12 2C6.48 2 2 6.48 2 12c0 1.93.56 3.73 1.52 5.27L2 22l4.86-1.47A9.94 9.94 0 0 0 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2zm0 18.13c-1.7 0-3.29-.5-4.63-1.36l-.33-.21-3.04.92.93-2.96-.22-.34A8.12 8.12 0 0 1 3.88 12c0-4.48 3.64-8.12 8.12-8.12S20.12 7.52 20.12 12 16.48 20.13 12 20.13z" />
        </svg>
    </a>

    @livewireScripts
    @stack('scripts')
</body>
</html>
