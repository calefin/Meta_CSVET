<div class="mx-auto max-w-5xl px-4 py-10 xs:px-4 md:px-8">
    <div class="text-center">
        <h1 class="font-display text-3xl text-ifa-green">Blog</h1>
        <p class="mx-auto mt-2 max-w-2xl text-sm text-ebony/70">Textos sobre Ifá, orixás, candomblé, umbanda e consultas oraculares da Ìjọ Ifá Èjìogbè.</p>
    </div>

    <div class="mt-6 flex flex-wrap justify-center gap-2">
        <button type="button" wire:click="$set('categoria', '')" class="rounded-full px-3 py-1 text-xs font-semibold {{ $categoria === '' ? 'bg-ifa-green text-white' : 'bg-sacred-sand text-ebony/70 hover:bg-sacred-sand/70' }}">
            Todos
        </button>
        @foreach ($categories as $value => $label)
            <button type="button" wire:click="$set('categoria', '{{ $value }}')" class="rounded-full px-3 py-1 text-xs font-semibold {{ $categoria === $value ? 'bg-ifa-green text-white' : 'bg-sacred-sand text-ebony/70 hover:bg-sacred-sand/70' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($articles as $article)
            <a href="{{ route('blog.show', $article) }}" class="group flex flex-col overflow-hidden rounded-lg border border-orisa-earth/20 bg-white shadow-sm transition hover:shadow-md">
                @if ($article->cover_image_path)
                    <img src="{{ Illuminate\Support\Facades\Storage::url($article->cover_image_path) }}" alt="" class="h-40 w-full object-cover">
                @endif
                <div class="flex flex-1 flex-col p-5">
                    <span class="text-xs font-semibold uppercase tracking-wide text-orisa-earth">{{ $categories[$article->category] ?? $article->category }}</span>
                    <h2 class="mt-1 font-display text-lg text-ifa-green group-hover:underline">{{ $article->title }}</h2>
                    @if ($article->excerpt)
                        <p class="mt-2 flex-1 text-sm text-ebony/70">{{ \Illuminate\Support\Str::limit($article->excerpt, 140) }}</p>
                    @endif
                    <span class="mt-3 text-xs text-ebony/50">{{ $article->published_at?->translatedFormat('d \d\e F \d\e Y') }}</span>
                </div>
            </a>
        @empty
            <p class="col-span-full text-center text-sm text-ebony/50 italic">Nenhum artigo publicado ainda.</p>
        @endforelse
    </div>

    <div class="mt-8">{{ $articles->links() }}</div>
</div>
