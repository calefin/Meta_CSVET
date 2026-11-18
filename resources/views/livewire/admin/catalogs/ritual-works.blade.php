<div>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-2xl text-ifa-green">Trabalhos</h1>
        <button wire:click="create" class="rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">
            Novo trabalho
        </button>
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-3">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar por nome..." class="w-full rounded border border-orisa-earth/30 px-3 py-2 text-xs sm:max-w-xs focus:border-ifa-green focus:ring-ifa-green">
    </div>

    <div class="mt-4 overflow-x-auto rounded border border-orisa-earth/20 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-sacred-sand">
                <tr>
                    <th class="px-4 py-2">Nome</th>
                    <th class="px-4 py-2">Prazo estimado</th>
                    <th class="px-4 py-2">Insumos vinculados</th>
                    <th class="px-4 py-2">Animais vinculados</th>
                    <th class="px-4 py-2">Ações</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($works as $work)
                    <tr class="border-t border-orisa-earth/10" wire:key="work-{{ $work->id }}">
                        <td class="px-4 py-2" lang="yo">{{ $work->name }}</td>
                        <td class="px-4 py-2">{{ $work->estimated_days ? $work->estimated_days.' dias' : '—' }}</td>
                        <td class="px-4 py-2">{{ $work->supplies_count }}</td>
                        <td class="px-4 py-2">{{ $work->animals_count }}</td>
                        <td class="px-4 py-2">
                            <button wire:click="edit({{ $work->id }})" class="text-ifa-green hover:underline">Editar</button>
                            <button wire:click="duplicate({{ $work->id }})" class="ml-2 text-orisa-earth hover:underline">Duplicar</button>
                            <button wire:click="delete({{ $work->id }})" wire:confirm="Remover este trabalho?" class="ml-2 text-danger hover:underline">Remover</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $works->links() }}</div>

    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/50 p-4">
            <div class="w-full max-w-2xl rounded-lg bg-white p-6">
                <h2 class="font-display text-xl text-ifa-green">{{ $editingId ? 'Editar' : 'Novo' }} trabalho</h2>

                <form wire:submit="save" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium">Nome</label>
                        <input type="text" wire:model="name" lang="yo" autocorrect="off" autocapitalize="none" spellcheck="false"
                            id="work-name" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                        @error('name') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                        <x-yoruba-keyboard target="work-name" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Descrição</label>
                        <textarea wire:model="description" rows="2" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Prazo estimado (dias)</label>
                        <input type="number" wire:model="estimated_days" class="mt-1 w-full max-w-[10rem] rounded border border-orisa-earth/30 px-3 py-2">
                    </div>

                    @if ($duplicateItemWarning)
                        <p class="text-xs text-warning font-medium">{{ $duplicateItemWarning }}</p>
                    @endif

                    <div>
                        <label class="block text-sm font-medium">Insumos padrão</label>

                        <div class="relative mt-2">
                            <input type="text" wire:model.live.debounce.300ms="supplySearch" placeholder="Buscar insumos (ex: Vela, Erva, Atabaque)..." class="w-full rounded border border-orisa-earth/30 px-3 py-2 text-xs focus:border-ifa-green focus:ring-ifa-green">
                            @if (!empty($supplyResults))
                                <div class="absolute left-0 right-0 z-50 mt-1 max-h-60 overflow-y-auto rounded-md bg-white border border-orisa-earth/25 shadow-lg">
                                    @foreach ($supplyResults as $supply)
                                        <button type="button" wire:click="selectSupplyById({{ $supply->id }})" class="w-full text-left px-3 py-2 text-xs hover:bg-sacred-sand/40 border-b border-orisa-earth/10 transition-colors">
                                            {{ $supply->name }}
                                        </button>
                                    @endforeach
                                </div>
                            @elseif(strlen(trim($supplySearch)) >= 2)
                                <div class="absolute left-0 right-0 z-50 mt-1 rounded-md bg-white border border-orisa-earth/25 p-3 text-center text-xs text-ebony/50 italic shadow-lg">
                                    Nenhum insumo encontrado para "{{ $supplySearch }}"
                                </div>
                            @endif
                        </div>

                        <div class="mt-2 space-y-2">
                            @foreach ($supplyRows as $index => $row)
                                <div class="flex items-center gap-2" wire:key="supply-row-{{ $index }}">
                                    <span class="flex-1 rounded border border-orisa-earth/30 bg-sacred-sand/30 px-2 py-1.5 text-sm">
                                        {{ $supplyNames[$row['supply_id']] ?? '—' }}
                                    </span>
                                    <input type="number" step="0.001" wire:model="supplyRows.{{ $index }}.default_quantity"
                                        class="w-24 rounded border border-orisa-earth/30 px-2 py-1.5 text-sm" placeholder="qtd.">
                                    <span class="w-14 text-xs text-ebony/50">{{ $supplyUnits[$row['supply_id']] ?? '' }}</span>
                                    <button type="button" wire:click="removeSupplyRow({{ $index }})" class="text-danger hover:underline">remover</button>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Animais padrão</label>

                        <div class="relative mt-2">
                            <input type="text" wire:model.live.debounce.300ms="animalSearch" placeholder="Buscar animais (ex: Galo, Cabra)..." class="w-full rounded border border-orisa-earth/30 px-3 py-2 text-xs focus:border-ifa-green focus:ring-ifa-green">
                            @if (!empty($animalResults))
                                <div class="absolute left-0 right-0 z-50 mt-1 max-h-60 overflow-y-auto rounded-md bg-white border border-orisa-earth/25 shadow-lg">
                                    @foreach ($animalResults as $animal)
                                        <button type="button" wire:click="selectAnimalById({{ $animal->id }})" class="w-full text-left px-3 py-2 text-xs hover:bg-sacred-sand/40 border-b border-orisa-earth/10 transition-colors">
                                            {{ $animal->name }}
                                        </button>
                                    @endforeach
                                </div>
                            @elseif(strlen(trim($animalSearch)) >= 2)
                                <div class="absolute left-0 right-0 z-50 mt-1 rounded-md bg-white border border-orisa-earth/25 p-3 text-center text-xs text-ebony/50 italic shadow-lg">
                                    Nenhum animal encontrado para "{{ $animalSearch }}"
                                </div>
                            @endif
                        </div>

                        <div class="mt-2 space-y-2">
                            @foreach ($animalRows as $index => $row)
                                <div class="flex items-center gap-2" wire:key="animal-row-{{ $index }}">
                                    <span class="flex-1 rounded border border-orisa-earth/30 bg-sacred-sand/30 px-2 py-1.5 text-sm">
                                        {{ $animalNames[$row['animal_id']] ?? '—' }}
                                    </span>
                                    <input type="number" step="0.001" wire:model="animalRows.{{ $index }}.default_quantity"
                                        class="w-24 rounded border border-orisa-earth/30 px-2 py-1.5 text-sm" placeholder="qtd.">
                                    <span class="w-14 text-xs text-ebony/50">{{ $animalUnits[$row['animal_id']] ?? '' }}</span>
                                    <button type="button" wire:click="removeAnimalRow({{ $index }})" class="text-danger hover:underline">remover</button>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" wire:model="active" id="work-active">
                        <label for="work-active" class="text-sm">Ativo</label>
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
