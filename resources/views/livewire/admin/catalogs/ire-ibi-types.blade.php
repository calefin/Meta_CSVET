<div>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-2xl text-ifa-green">Catálogo de Ire / Ibi</h1>
        <button wire:click="create" class="rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">
            Novo tipo
        </button>
    </div>

    <div class="mt-4 overflow-x-auto rounded border border-orisa-earth/20 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-sacred-sand">
                <tr>
                    <th class="px-4 py-2">Classificação</th>
                    <th class="px-4 py-2">Nome</th>
                    <th class="px-4 py-2">Ativo</th>
                    <th class="px-4 py-2">Ações</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($types as $type)
                    <tr class="border-t border-orisa-earth/10" wire:key="type-{{ $type->id }}">
                        <td class="px-4 py-2">{{ $type->classification === 'ire' ? 'Ire' : 'Ibi' }}</td>
                        <td class="px-4 py-2" lang="yo">{{ $type->name }}</td>
                        <td class="px-4 py-2">{{ $type->active ? 'Sim' : 'Não' }}</td>
                        <td class="px-4 py-2">
                            <button wire:click="edit({{ $type->id }})" class="text-ifa-green hover:underline">Editar</button>
                            <button wire:click="delete({{ $type->id }})" wire:confirm="Remover este tipo?" class="ml-2 text-danger hover:underline">Remover</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $types->links() }}</div>

    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-lg bg-white p-6">
                <h2 class="font-display text-xl text-ifa-green">{{ $editingId ? 'Editar' : 'Novo' }} tipo</h2>

                <form wire:submit="save" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium">Classificacao</label>
                        <select wire:model="classification" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                            <option value="ire">Ire</option>
                            <option value="ibi">Ibi</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Nome</label>
                        <input type="text" wire:model="name" lang="yo" autocorrect="off" autocapitalize="none" spellcheck="false"
                            id="type-name" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                        @error('name') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                        <x-yoruba-keyboard target="type-name" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Descrição</label>
                        <textarea wire:model="description" rows="3" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2"></textarea>
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" wire:model="active" id="type-active">
                        <label for="type-active" class="text-sm">Ativo</label>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showModal', false)" class="rounded px-4 py-2 text-ebony/70 hover:bg-sacred-sand">Cancelar</button>
                        <button type="submit" class="rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
