<div>
    <h1 class="font-display text-2xl text-ifa-green">Biblioteca — categorias</h1>
    <p class="text-sm text-ebony/60">Material de consulta restrito (rezas, técnicas, encantamentos, mitologia). O acesso a cada categoria é liberado individualmente em <a href="{{ route('admin.users') }}" class="text-ifa-green hover:underline">Usuários</a>.</p>

    <div class="mt-4 overflow-x-auto rounded border border-orisa-earth/20 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-sacred-sand">
                <tr>
                    <th class="px-4 py-2">Nome</th>
                    <th class="px-4 py-2">Documentos</th>
                    <th class="px-4 py-2">Ações</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($categories as $category)
                    <tr class="border-t border-orisa-earth/10" wire:key="cat-{{ $category->id }}">
                        <td class="px-4 py-2">{{ $category->name }}</td>
                        <td class="px-4 py-2">{{ $category->documents_count }}</td>
                        <td class="px-4 py-2 space-x-2">
                            <a href="{{ route('admin.library.documents', $category) }}" class="text-orun-blue hover:underline">documentos</a>
                            <button wire:click="edit({{ $category->id }})" class="text-ifa-green hover:underline">editar</button>
                            <button wire:click="delete({{ $category->id }})" wire:confirm="Remover esta categoria e seus documentos?" class="text-danger hover:underline">remover</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-6 max-w-md rounded-lg border border-orisa-earth/20 bg-white p-6">
        <h2 class="font-display text-lg text-ifa-green">{{ $editingId ? 'Editar categoria' : 'Nova categoria' }}</h2>

        <form wire:submit="save" class="mt-4 space-y-3">
            <div>
                <label class="block text-sm font-medium">Nome</label>
                <input type="text" wire:model="name" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm">
                @error('name') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium">Descrição</label>
                <textarea wire:model="description" rows="2" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm"></textarea>
            </div>

            <div class="flex justify-end gap-2">
                @if ($editingId)
                    <button type="button" wire:click="cancelEdit" class="rounded px-4 py-2 text-sm text-ebony/70 hover:bg-sacred-sand">cancelar</button>
                @endif
                <button type="submit" class="rounded bg-ifa-green px-4 py-2 text-sm text-white hover:bg-ifa-green/90">salvar</button>
            </div>
        </form>
    </div>
</div>
