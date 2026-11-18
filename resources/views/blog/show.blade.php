<!DOCTYPE html>
<html lang="pt-BR" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    @php
        $metaDescription = $post->seo_description ?: \Illuminate\Support\Str::limit(strip_tags($post->excerpt ?: $post->content), 160);
        $coverUrl = $post->cover_image ? \Illuminate\Support\Facades\Storage::disk('public')->url($post->cover_image) : asset('images/profile.png');
    @endphp

    <title>{{ $post->title }} — Blog Prof. Atilio Calefi</title>
    <meta name="description" content="{{ $metaDescription }}">
    <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
    <meta name="author" content="Prof. Dr. Atilio Sersun Calefi">
    <link rel="canonical" href="{{ route('blog.show', $post->slug) }}">
    <link class="favicon" rel="icon" type="image/png" sizes="96x96" href="{{ asset('images/logo-mark-96.png') }}">
    <meta name="theme-color" content="#030712">

    <meta property="og:type" content="article">
    <meta property="og:url" content="{{ route('blog.show', $post->slug) }}">
    <meta property="og:title" content="{{ $post->title }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:image" content="{{ $coverUrl }}">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:site_name" content="profatilio.com.br">
    <meta name="twitter:card" content="summary_large_image">

    <script type="application/ld+json">
    {
      "@@context": "https://schema.org",
      "@@type": "Article",
      "headline": @json($post->title),
      "description": @json($metaDescription),
      "image": @json($coverUrl),
      "datePublished": @json($post->published_at?->toIso8601String()),
      "dateModified": @json($post->updated_at?->toIso8601String()),
      "author": { "@@type": "Person", "name": "Atilio Sersun Calefi", "url": "https://profatilio.com.br" },
      "publisher": { "@@type": "Person", "name": "Atilio Sersun Calefi" }
    }
    </script>

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
        <article class="max-w-3xl mx-auto px-6">
            <a href="{{ route('blog.index') }}" class="text-sm text-brand-teal hover:underline">← Voltar ao Blog</a>

            <h1 class="font-outfit font-extrabold text-3xl sm:text-4xl text-white mt-4 leading-tight">{{ $post->title }}</h1>
            <p class="text-slate-400 text-sm mt-3">Publicado em {{ $post->published_at?->format('d/m/Y') }} — Prof. Dr. Atilio Sersun Calefi</p>

            @if($post->cover_image)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($post->cover_image) }}" alt="{{ $post->title }}" class="w-full h-auto rounded-3xl border border-white/5 shadow-2xl mt-8">
            @endif

            <div class="post-content mt-10">
                {!! $post->content !!}
            </div>
        </article>
    </main>

    @include('partials.site-footer')

</body>
</html>
