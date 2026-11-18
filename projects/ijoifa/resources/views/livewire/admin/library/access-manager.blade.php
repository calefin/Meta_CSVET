<div>
    <a href="{{ route('admin.users') }}" class="text-sm text-ifa-green hover:underline">&larr; Voltar para usuários</a>

    <h1 class="mt-2 font-display text-2xl text-ifa-green">Acesso à biblioteca</h1>
    <p class="text-sm text-ebony/60" lang="yo">{{ $user->name }}</p>
    <p class="text-xs text-ebony/50">Marque apenas as categorias que esta pessoa já pode consultar. Nenhuma categoria é liberada automaticamente.</p>

    <ul class="mt-4 max-w-md space-y-2">
        @foreach ($categories as $category)
            <li class="flex items-center justify-between rounded border border-orisa-earth/20 bg-white px-4 py-2.5">
                <span class="text-sm">{{ $category->name }}</span>
                @if (in_array($category->id, $grantedCategoryIds))
                    <button wire:click="revoke({{ $category->id }})" class="text-xs text-danger hover:underline">revogar</button>
                @else
                    <button wire:click="grant({{ $category->id }})" class="text-xs text-ifa-green hover:underline">liberar</button>
                @endif
            </li>
        @endforeach
    </ul>

    @if ($categories->isEmpty())
        <p class="mt-4 text-sm text-ebony/50">Nenhuma categoria cadastrada ainda.</p>
    @endif
</div>
