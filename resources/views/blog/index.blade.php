<!DOCTYPE html>
<html lang="pt-BR" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Blog — Prof. Dr. Atilio Sersun Calefi | Imunologia Veterinária e Animais Não Convencionais</title>
    <meta name="description" content="Artigos sobre imunologia veterinária, clínica de animais não convencionais, patologia e toxicologia animal, escritos pelo Prof. Dr. Atilio Sersun Calefi.">
    <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
    <meta name="author" content="Prof. Dr. Atilio Sersun Calefi">
    <link rel="canonical" href="https://profatilio.com.br/blog">
    <link class="favicon" rel="icon" type="image/png" sizes="96x96" href="{{ asset('images/logo-mark-96.png') }}">
    <meta name="theme-color" content="#030712">

    <meta property="og:type" content="website">
    <meta property="og:url" content="https://profatilio.com.br/blog">
    <meta property="og:title" content="Blog — Prof. Dr. Atilio Sersun Calefi">
    <meta property="og:description" content="Artigos sobre imunologia veterinária, clínica de animais não convencionais, patologia e toxicologia animal.">
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
        <div class="max-w-5xl mx-auto px-6">
            <div class="text-center max-w-2xl mx-auto mb-16">
                <span class="text-xs font-bold text-brand-teal uppercase tracking-widest font-outfit">Blog</span>
                <h1 class="font-outfit font-extrabold text-3xl sm:text-4xl text-white mt-1">Artigos e Novidades</h1>
                <p class="text-slate-400 mt-3 font-light text-sm">
                    Conteúdo sobre imunologia veterinária, clínica de animais não convencionais, patologia e toxicologia animal.
                </p>
                <div class="w-16 h-1 bg-brand-teal mx-auto mt-6 rounded-full"></div>
            </div>

            @if($posts->isEmpty())
                <div class="glass-card p-10 rounded-3xl border border-white/5 text-center text-slate-400">
                    Nenhum post publicado ainda. Volte em breve!
                </div>
            @else
                <div class="grid sm:grid-cols-2 gap-8">
                    @foreach($posts as $post)
                        <a href="{{ route('blog.show', $post->slug) }}" class="glass-card rounded-3xl border border-white/5 overflow-hidden flex flex-col shadow-xl shadow-black/40">
                            @if($post->cover_image)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($post->cover_image) }}" alt="{{ $post->title }}" class="w-full h-48 object-cover">
                            @endif
                            <div class="p-6 flex flex-col gap-3">
                                <span class="text-xs text-slate-400">{{ $post->published_at?->format('d/m/Y') }}</span>
                                <h2 class="font-outfit font-bold text-lg text-white leading-snug">{{ $post->title }}</h2>
                                @if($post->excerpt)
                                    <p class="text-sm text-slate-400 font-light leading-relaxed">{{ \Illuminate\Support\Str::limit($post->excerpt, 140) }}</p>
                                @endif
                                <span class="text-brand-teal text-sm font-semibold mt-2">Ler artigo →</span>
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="mt-12 flex justify-center">
                    {{ $posts->links() }}
                </div>
            @endif
        </div>
    </main>

    @include('partials.site-footer')

</body>
</html>
