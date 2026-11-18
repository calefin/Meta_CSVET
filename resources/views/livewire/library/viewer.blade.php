<div>
    <a href="{{ route('library.index') }}" class="text-sm text-ifa-green hover:underline">&larr; Voltar para a biblioteca</a>

    <h1 class="mt-2 font-display text-2xl text-ifa-green">{{ $document->title }}</h1>
    @if ($document->description)
        <p class="text-sm text-ebony/60">{{ $document->description }}</p>
    @endif
    <p class="mt-1 text-xs text-ebony/40">Material restrito — apenas para consulta dentro do sistema.</p>

    @if ($document->audio_path)
        <div class="mt-4 rounded-xl border border-orisa-earth/15 bg-sacred-sand/30 p-4 shadow-sm">
            <p class="text-xs font-semibold text-ebony/60 mb-2 flex items-center gap-1">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-ifa-green" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 0 1 0 12.728M16.463 8.288a5.25 5.25 0 0 1 0 7.424M6.75 8.25l4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.009 9.009 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75Z" />
                </svg>
                Ouvir Áudio Explicativo:
            </p>
            <audio controls src="{{ route('library.audio', $document) }}" class="w-full focus:outline-none" controlsList="nodownload"></audio>
        </div>
    @endif

    <div
        x-data
        x-init="
            $el.addEventListener('contextmenu', (e) => e.preventDefault());
            $el.addEventListener('dragstart', (e) => e.preventDefault());
            $el.addEventListener('copy', (e) => e.preventDefault());
            $el.addEventListener('selectstart', (e) => e.preventDefault());
        "
        class="mt-4 select-none rounded-lg border border-orisa-earth/20 bg-white p-6 relative overflow-hidden"
        oncontextmenu="return false"
    >
        <!-- Dynamic CSS Watermark overlay -->
        <div class="pointer-events-none absolute inset-0 z-0 flex flex-wrap items-center justify-center opacity-[0.03] select-none" style="transform: rotate(-30deg);">
            @for ($i = 0; $i < 20; $i++)
                <span class="m-8 text-sm font-sans font-semibold tracking-wider text-black">{{ auth()->user()->name }} — {{ auth()->user()->email }}</span>
            @endfor
        </div>

        <div class="prose prose-sm sm:prose-base prose-headings:font-display prose-headings:text-ifa-green max-w-none text-ebony/80 relative z-10 select-none">
            {!! $document->content !!}
        </div>
    </div>
</div>
