<!DOCTYPE html>
<html lang="pt-BR" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Na Mídia — Prof. Dr. Atilio Sersun Calefi</title>
    <meta name="description" content="Reportagens e colunas em que o Prof. Dr. Atilio Sersun Calefi apareceu como fonte especializada em veterinária, imunologia e animais não convencionais.">
    <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
    <meta name="author" content="Prof. Dr. Atilio Sersun Calefi">
    <link rel="canonical" href="https://profatilio.com.br/na-midia">
    <link class="favicon" rel="icon" type="image/png" sizes="96x96" href="{{ asset('images/logo-mark-96.png') }}">
    <meta name="theme-color" content="#030712">

    <meta property="og:type" content="website">
    <meta property="og:url" content="https://profatilio.com.br/na-midia">
    <meta property="og:title" content="Na Mídia — Prof. Dr. Atilio Sersun Calefi">
    <meta property="og:description" content="Reportagens e colunas em que o Prof. Dr. Atilio Sersun Calefi apareceu como fonte especializada.">
    <meta property="og:image" content="{{ asset('images/profile.png') }}">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:site_name" content="profatilio.com.br">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    @vite('resources/css/site.css')

    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @include('partials.adsense')
</head>
<body class="bg-dark-950 text-slate-100 font-sans antialiased overflow-x-hidden" x-data="{ mobileMenu: false }">

    <div class="absolute inset-0 bg-[linear-gradient(to_right,#1f2937_1px,transparent_1px),linear-gradient(to_bottom,#1f2937_1px,transparent_1px)] bg-[size:4rem_4rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_0%,#000_70%,transparent_100%)] opacity-[0.15] pointer-events-none"></div>

    @include('partials.site-nav')

    <main class="relative z-10 pt-28 sm:pt-36 pb-24">
        <div class="max-w-4xl mx-auto px-6">
            <div class="text-center max-w-2xl mx-auto mb-16">
                <span class="text-xs font-bold text-brand-teal uppercase tracking-widest font-outfit">Imprensa</span>
                <h1 class="font-outfit font-extrabold text-3xl sm:text-4xl text-white mt-1">Na Mídia</h1>
                <p class="text-slate-400 mt-3 font-light text-sm">
                    Reportagens e colunas em que participei como fonte especializada em veterinária, imunologia e animais não convencionais.
                </p>
                <div class="w-16 h-1 bg-brand-teal mx-auto mt-6 rounded-full"></div>
            </div>

            @if($mentions->isEmpty())
                <div class="glass-card p-10 rounded-3xl border border-white/5 text-center text-slate-400">
                    Nenhuma matéria cadastrada ainda.
                </div>
            @else
                <div class="flex flex-col gap-5">
                    @foreach($mentions as $mention)
                        <a href="{{ $mention->url }}" target="_blank" rel="noopener" class="glass-card p-6 rounded-2xl border border-white/5 flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-6 shadow-lg shadow-black/30">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 text-xs text-slate-400 mb-1">
                                    <span class="font-bold text-brand-teal">{{ $mention->outlet }}</span>
                                    @if($mention->section)
                                        <span>· {{ $mention->section }}</span>
                                    @endif
                                    <span>· {{ $mention->published_at?->format('d/m/Y') }}</span>
                                </div>
                                <h2 class="font-outfit font-bold text-white text-base leading-snug">{{ $mention->title }}</h2>
                                @if($mention->excerpt)
                                    <p class="text-sm text-slate-400 font-light mt-1">{{ $mention->excerpt }}</p>
                                @endif
                            </div>
                            <span class="text-brand-teal text-sm font-semibold whitespace-nowrap">Ler matéria →</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </main>

    @include('partials.site-footer')

</body>
</html>
