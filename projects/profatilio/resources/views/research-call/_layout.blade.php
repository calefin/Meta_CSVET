{{-- Moldura das páginas da seleção (mesmo visual do site: site.css + nav + rodapé). --}}
<!DOCTYPE html>
<html lang="pt-BR" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle }} — Prof. Dr. Atilio Sersun Calefi</title>
    <meta name="description" content="{{ $pageDescription ?? '' }}">
    <meta name="robots" content="{{ ($indexable ?? false) ? 'index, follow' : 'noindex, nofollow' }}">
    @if(!empty($canonical))
        <link rel="canonical" href="{{ $canonical }}">
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ $canonical }}">
        <meta property="og:title" content="{{ $pageTitle }}">
        <meta property="og:description" content="{{ $pageDescription ?? '' }}">
        <meta property="og:image" content="{{ asset('images/profile.png') }}">
        <meta property="og:locale" content="pt_BR">
    @endif
    <link class="favicon" rel="icon" type="image/png" sizes="96x96" href="{{ asset('images/logo-mark-96.png') }}">
    <meta name="theme-color" content="#030712">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite('resources/css/site.css')
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-dark-950 text-slate-100 font-sans antialiased overflow-x-hidden" x-data="{ mobileMenu: false }">
    <div class="absolute inset-0 bg-[linear-gradient(to_right,#1f2937_1px,transparent_1px),linear-gradient(to_bottom,#1f2937_1px,transparent_1px)] bg-[size:4rem_4rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_0%,#000_70%,transparent_100%)] opacity-[0.15] pointer-events-none"></div>

    @include('partials.site-nav')

    <main class="relative z-10 pt-28 sm:pt-36 pb-24">
        @yield('content')
    </main>

    @include('partials.site-footer')
</body>
</html>
