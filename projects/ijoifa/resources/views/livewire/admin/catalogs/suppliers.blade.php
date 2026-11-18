<div>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-2xl text-ifa-green">Fornecedores</h1>
        <button wire:click="create" class="rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">
            Novo fornecedor
        </button>
    </div>

    <div class="mt-4 overflow-x-auto rounded border border-orisa-earth/20 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-sacred-sand">
                <tr>
                    <th class="px-4 py-2">Nome</th>
                    <th class="px-4 py-2">Tipo</th>
                    <th class="px-4 py-2">Cidade/UF</th>
                    <th class="px-4 py-2">Contato</th>
                    <th class="px-4 py-2">Ações</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($suppliers as $supplier)
                    <tr class="border-t border-orisa-earth/10" wire:key="supplier-{{ $supplier->id }}">
                        <td class="px-4 py-2" lang="yo">{{ $supplier->name }}</td>
                        <td class="px-4 py-2">{{ $supplier->type }}</td>
                        <td class="px-4 py-2">{{ $supplier->city }}{{ $supplier->state ? '/'.$supplier->state : '' }}</td>
                        <td class="px-4 py-2">{{ $supplier->contact }}</td>
                        <td class="px-4 py-2">
                            <button wire:click="edit({{ $supplier->id }})" class="text-ifa-green hover:underline">Editar</button>
                            <button wire:click="delete({{ $supplier->id }})" wire:confirm="Remover este fornecedor?" class="ml-2 text-danger hover:underline">Remover</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $suppliers->links() }}</div>

    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-lg bg-white p-6">
                <h2 class="font-display text-xl text-ifa-green">{{ $editingId ? 'Editar' : 'Novo' }} fornecedor</h2>

                <form wire:submit="save" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium">Nome</label>
                        <input type="text" wire:model="name" lang="yo" autocorrect="off" autocapitalize="none" spellcheck="false"
                            id="supplier-name" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                        @error('name') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                        <x-yoruba-keyboard target="supplier-name" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Tipo</label>
                        <select wire:model="type" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                            <option value="animais">Animais</option>
                            <option value="ervas">Ervas</option>
                            <option value="materiais">Materiais</option>
                            <option value="geral">Geral</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Contato</label>
                        <input type="text" wire:model="contact" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                    </div>

                    <div class="grid grid-cols-3 gap-2">
                        <div class="col-span-2">
                            <label class="block text-sm font-medium">Cidade</label>
                            <input type="text" wire:model="city" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium">UF</label>
                            <input type="text" wire:model="state" maxlength="2" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Observações</label>
                        <textarea wire:model="notes" rows="3" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2"></textarea>
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
