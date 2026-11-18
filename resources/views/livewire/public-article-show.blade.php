@push('head')
    <script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'BlogPosting',
                'headline' => $article->title,
                'description' => $article->metaDescription(),
                'datePublished' => $article->published_at?->toAtomString(),
                'dateModified' => $article->updated_at->toAtomString(),
                'author' => ['@type' => 'Person', 'name' => $article->author->name],
                'image' => $article->cover_image_path ? Illuminate\Support\Facades\Storage::url($article->cover_image_path) : url('/icons/icon-512.png'),
                'mainEntityOfPage' => route('blog.show', $article),
            ],
            [
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Início', 'item' => url('/')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => route('blog.index')],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => $article->title, 'item' => route('blog.show', $article)],
                ],
            ],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
@endpush

<article class="mx-auto max-w-3xl px-4 py-10 xs:px-4 md:px-8">
    <nav class="text-xs text-ebony/50">
        <a href="{{ route('home') }}" class="hover:text-ifa-green">Início</a>
        <span class="mx-1">/</span>
        <a href="{{ route('blog.index') }}" class="hover:text-ifa-green">Blog</a>
        <span class="mx-1">/</span>
        <span class="text-ebony/70">{{ $article->title }}</span>
    </nav>

    <span class="mt-4 inline-block text-xs font-semibold uppercase tracking-wide text-orisa-earth">
        {{ \App\Models\Article::CATEGORIES[$article->category] ?? $article->category }}
    </span>
    <h1 class="mt-1 font-display text-3xl text-ifa-green">{{ $article->title }}</h1>
    <p class="mt-2 text-xs text-ebony/50">
        {{ $article->published_at?->translatedFormat('d \d\e F \d\e Y') }} — {{ $article->author->name }}
    </p>

    @if ($article->cover_image_path)
        <img src="{{ Illuminate\Support\Facades\Storage::url($article->cover_image_path) }}" alt="" class="mt-6 w-full rounded-lg object-cover" style="max-height: 24rem">
    @endif

    <div class="prose prose-ebony mt-8 max-w-none">
        {!! $article->content !!}
    </div>

    <div class="mt-10 border-t border-orisa-earth/10 pt-6">
        <a href="{{ route('blog.index') }}" class="text-sm text-ifa-green hover:underline">&larr; Voltar para o Blog</a>
    </div>
</article>
