<div>
    <h1 class="font-display text-2xl text-ifa-green">Biblioteca</h1>
    <p class="text-sm text-ebony/60">Material de consulta liberado para você. O acesso é individual e pode ser ampliado pelo seu sacerdote conforme o estágio de formação.</p>

    @if ($categories->isEmpty())
        <p class="mt-6 text-sm text-ebony/50">Nenhum material foi liberado para você ainda.</p>
    @endif

    <div class="mt-6 space-y-6">
        @foreach ($categories as $category)
            <div class="rounded-lg border border-orisa-earth/20 bg-white p-5">
                <h2 class="font-display text-lg text-ifa-green">{{ $category->name }}</h2>
                @if ($category->description)
                    <p class="text-sm text-ebony/60">{{ $category->description }}</p>
                @endif

                <ul class="mt-3 space-y-1.5 text-sm">
                    @foreach ($category->documents as $document)
                        <li>
                            <a href="{{ route('library.show', $document) }}" class="text-orun-blue hover:underline">
                                {{ $document->title }}
                            </a>
                            <span class="text-xs text-ebony/40">({{ $document->language }})</span>
                        </li>
                    @endforeach
                    @if ($category->documents->isEmpty())
                        <li class="text-xs text-ebony/40">Nenhum documento nesta categoria ainda.</li>
                    @endif
                </ul>
            </div>
        @endforeach
    </div>
</div>
