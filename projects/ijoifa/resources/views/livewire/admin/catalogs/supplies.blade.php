<div>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-2xl text-ifa-green">Insumos</h1>
        <button wire:click="create" class="rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">
            Novo insumo
        </button>
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-3">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar por nome ou categoria..." class="rounded border border-orisa-earth/30 px-3 py-2 text-xs w-full sm:max-w-xs focus:border-ifa-green focus:ring-ifa-green">
    </div>

    <div class="mt-4 overflow-x-auto rounded border border-orisa-earth/20 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-sacred-sand">
                <tr>
                    <th class="px-4 py-2">Nome</th>
                    <th class="px-4 py-2">Categoria</th>
                    <th class="px-4 py-2">Unidade</th>
                    <th class="px-4 py-2">Fornecedor</th>
                    <th class="px-4 py-2">Preço Atual</th>
                    <th class="px-4 py-2">Ações</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($supplies as $supply)
                    <tr class="border-t border-orisa-earth/10" wire:key="supply-{{ $supply->id }}">
                        <td class="px-4 py-2" lang="yo">{{ $supply->name }}</td>
                        <td class="px-4 py-2">{{ $supply->category }}</td>
                        <td class="px-4 py-2">{{ $supply->unit }}</td>
                        <td class="px-4 py-2">{{ $supply->supplier?->name }}</td>
                        <td class="px-4 py-2">
                            @if ($latestPrice = $supply->prices->sortByDesc('purchase_date')->first())
                                R$ {{ number_format($latestPrice->unit_price, 2, ',', '.') }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            <button wire:click="edit({{ $supply->id }})" class="text-ifa-green hover:underline">Editar</button>
                            <button wire:click="showPriceForm({{ $supply->id }})" class="ml-2 text-orun-blue hover:underline">+ Preço</button>
                            <button wire:click="delete({{ $supply->id }})" wire:confirm="Remover este insumo?" class="ml-2 text-danger hover:underline">Remover</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $supplies->links() }}</div>

    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-lg bg-white p-6">
                <h2 class="font-display text-xl text-ifa-green">{{ $editingId ? 'Editar' : 'Novo' }} insumo</h2>

                <form wire:submit="save" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium">Nome</label>
                        <input type="text" wire:model="name" lang="yo" autocorrect="off" autocapitalize="none" spellcheck="false"
                            id="supply-name" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                        @error('name') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                        <x-yoruba-keyboard target="supply-name" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Categoria</label>
                        <input type="text" wire:model="category" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Unidade</label>
                        <input type="text" wire:model="unit" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Fornecedor</label>
                        <select wire:model="supplier_id" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                            <option value="">— Nenhum —</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" wire:model="active" id="supply-active">
                        <label for="supply-active" class="text-sm">Ativo</label>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showModal', false)" class="rounded px-4 py-2 text-ebony/70 hover:bg-sacred-sand">Cancelar</button>
                        <button type="submit" class="rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($priceFormFor)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-sm rounded-lg bg-white p-6">
                <h2 class="font-display text-xl text-ifa-green">Registrar preço</h2>

                <form wire:submit="addPrice" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium">Valor unitário (R$)</label>
                        <input type="number" step="0.01" wire:model="priceAmount" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                        @error('priceAmount') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Forma de Pagamento</label>
                        <select wire:model="pricePaymentMethod" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm bg-white">
                            <option value="">Padrão (Sem especificação)</option>
                            <option value="dinheiro">Dinheiro</option>
                            <option value="credito">Crédito</option>
                            <option value="debito">Débito</option>
                        </select>
                        @error('pricePaymentMethod') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Qtd Mínima (Desconto de Volume)</label>
                        <input type="number" step="0.001" wire:model="priceMinQuantity" placeholder="Ex: 10 (acima de 10 unidades)" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm">
                        @error('priceMinQuantity') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Data da compra</label>
                        <input type="date" wire:model="priceDate" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                        @error('priceDate') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('priceFormFor', null)" class="rounded px-4 py-2 text-ebony/70 hover:bg-sacred-sand">Cancelar</button>
                        <button type="submit" class="rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
