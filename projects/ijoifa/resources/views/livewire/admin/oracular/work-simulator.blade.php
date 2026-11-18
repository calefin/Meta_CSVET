<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-display text-2xl text-ifa-green">Simulador de Orçamentos</h1>
            <p class="text-sm text-ebony/60">Selecione um trabalho para estimar preços de insumos e animais, edite itens e salve o orçamento para o cliente.</p>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <!-- Main Form & Items Table -->
        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-lg border border-orisa-earth/20 bg-white p-6 shadow-sm">
                <h2 class="font-display text-lg text-ifa-green mb-4">Escolha de Trabalho e Itens</h2>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-ebony">Trabalho (Modelo do catálogo)</label>
                        <select wire:model.live="selectedWorkId" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm focus:border-ifa-green focus:ring-ifa-green">
                            <option value="">— Selecione o trabalho —</option>
                            @foreach ($works as $w)
                                <option value="{{ $w->id }}">{{ $w->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @if ($selectedWorkId)
                    <div class="mt-6">
                        <h3 class="font-semibold text-ebony text-sm mb-3">Itens Calculados no Trabalho</h3>
                        
                        <div class="overflow-x-auto rounded border border-orisa-earth/10">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-sacred-sand text-xs font-semibold text-ebony/70">
                                    <tr>
                                        <th class="px-3 py-2">Tipo</th>
                                        <th class="px-3 py-2">Descrição</th>
                                        <th class="px-3 py-2 w-24">Qtd.</th>
                                        <th class="px-3 py-2 w-32">Preço Unit.</th>
                                        <th class="px-3 py-2 w-20 text-center">Do Cliente?</th>
                                        <th class="px-3 py-2 text-right">Subtotal</th>
                                        <th class="px-3 py-2">Ação</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-orisa-earth/10">
                                    @forelse ($items as $index => $item)
                                        <tr class="hover:bg-sacred-sand/10 transition-colors" wire:key="item-{{ $index }}">
                                            <td class="px-3 py-2 text-xs uppercase font-mono text-ebony/60">
                                                {{ $item['item_type'] === 'supply' ? 'Insumo' : 'Animal' }}
                                            </td>
                                            <td class="px-3 py-2 font-medium">
                                                <input type="text" wire:model.live="items.{{ $index }}.description" class="w-full rounded border border-orisa-earth/20 px-2 py-1 text-xs">
                                            </td>
                                            <td class="px-3 py-2">
                                                <input type="number" step="0.001" wire:model.live="items.{{ $index }}.quantity" class="w-full rounded border border-orisa-earth/20 px-2 py-1 text-xs">
                                            </td>
                                            <td class="px-3 py-2">
                                                <div class="flex items-center gap-1">
                                                    <span class="text-xs text-ebony/50">R$</span>
                                                    <input type="number" step="0.01" wire:model.live="items.{{ $index }}.unit_price" class="w-full rounded border border-orisa-earth/20 px-2 py-1 text-xs">
                                                </div>
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                <input type="checkbox" wire:model.live="items.{{ $index }}.by_client" class="rounded text-ifa-green focus:ring-ifa-green">
                                            </td>
                                            <td class="px-3 py-2 text-right font-medium">
                                                @if ($item['by_client'])
                                                    <span class="text-xs text-ebony/40 italic">Insumido</span>
                                                @else
                                                    R$ {{ number_format((float)$item['quantity'] * (float)$item['unit_price'], 2, ',', '.') }}
                                                @endif
                                            </td>
                                            <td class="px-3 py-2">
                                                <button type="button" wire:click="removeRow({{ $index }})" class="text-danger hover:underline text-xs">remover</button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="px-4 py-8 text-center text-ebony/40 italic">Nenhum item adicionado a este simulador. Adicione insumos ou animais abaixo.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Add Row Toolbars -->
                        <div class="mt-4 grid gap-4 md:grid-cols-2 border-t border-orisa-earth/10 pt-4">
                            <div class="relative space-y-1">
                                <label class="block text-xs font-semibold text-ebony/60">Adicionar Insumo do Catálogo</label>
                                <input type="text" wire:model.live.debounce.300ms="supplySearch" placeholder="Buscar insumos (ex: Vela, Erva, Atabaque)..." class="w-full rounded border border-orisa-earth/30 px-3 py-2 text-xs focus:border-ifa-green focus:ring-ifa-green">
                                @if (!empty($supplyResults))
                                    <div class="absolute left-0 right-0 z-50 mt-1 max-h-60 overflow-y-auto rounded-md bg-white border border-orisa-earth/25 shadow-lg">
                                        @foreach ($supplyResults as $s)
                                            <button type="button" wire:click="addSupplyRow({{ $s->id }})" class="w-full text-left px-3 py-2 text-xs hover:bg-sacred-sand/40 border-b border-orisa-earth/10 transition-colors flex justify-between items-center">
                                                <span class="font-medium text-ebony">{{ $s->name }}</span>
                                                @if($s->prices->isNotEmpty())
                                                    <span class="text-[10px] text-ifa-green font-mono">R$ {{ number_format($s->prices->sortByDesc('purchase_date')->first()->unit_price, 2, ',', '.') }}</span>
                                                @endif
                                            </button>
                                        @endforeach
                                    </div>
                                @elseif(strlen(trim($supplySearch)) >= 2)
                                    <div class="absolute left-0 right-0 z-50 mt-1 rounded-md bg-white border border-orisa-earth/25 p-3 text-center text-xs text-ebony/50 italic shadow-lg">
                                        Nenhum insumo encontrado para "{{ $supplySearch }}"
                                    </div>
                                @endif
                            </div>

                            <div class="relative space-y-1">
                                <label class="block text-xs font-semibold text-ebony/60">Adicionar Animal (Macedo/Outros)</label>
                                <input type="text" wire:model.live.debounce.300ms="animalSearch" placeholder="Buscar animais (ex: Galo, Cabra)..." class="w-full rounded border border-orisa-earth/30 px-3 py-2 text-xs focus:border-ifa-green focus:ring-ifa-green">
                                @if (!empty($animalResults))
                                    <div class="absolute left-0 right-0 z-50 mt-1 max-h-60 overflow-y-auto rounded-md bg-white border border-orisa-earth/25 shadow-lg">
                                        @foreach ($animalResults as $a)
                                            <button type="button" wire:click="addAnimalRow({{ $a->id }})" class="w-full text-left px-3 py-2 text-xs hover:bg-sacred-sand/40 border-b border-orisa-earth/10 transition-colors flex justify-between items-center">
                                                <span class="font-medium text-ebony">{{ $a->name }}</span>
                                                @if($a->prices->isNotEmpty())
                                                    <span class="text-[10px] text-ifa-green font-mono">R$ {{ number_format($a->prices->sortByDesc('purchase_date')->first()->unit_price, 2, ',', '.') }}</span>
                                                @endif
                                            </button>
                                        @endforeach
                                    </div>
                                @elseif(strlen(trim($animalSearch)) >= 2)
                                    <div class="absolute left-0 right-0 z-50 mt-1 rounded-md bg-white border border-orisa-earth/25 p-3 text-center text-xs text-ebony/50 italic shadow-lg">
                                        Nenhum animal encontrado para "{{ $animalSearch }}"
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Sidebar Actions & Totals -->
        <div class="space-y-6">
            <div class="rounded-lg border border-orisa-earth/20 bg-white p-6 shadow-sm space-y-4">
                <h3 class="font-display text-lg text-ifa-green mb-3 border-b border-orisa-earth/10 pb-2">Resumo & Persistência</h3>

                <div>
                    <label class="block text-sm font-medium text-ebony">Cliente (Consulente ou Iniciado)</label>
                    <select wire:model="selectedClientId" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm focus:border-ifa-green focus:ring-ifa-green">
                        <option value="">— Selecione o cliente —</option>
                        @foreach ($clients as $c)
                            <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->role === 'iniciado' ? 'Iniciado' : 'Consulente' }})</option>
                        @endforeach
                    </select>
                    @error('selectedClientId') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-sm font-medium text-ebony">Markup Geral (%)</label>
                        <input type="number" wire:model.live="markupPercent" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm focus:border-ifa-green focus:ring-ifa-green">
                        @error('markupPercent') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ebony">Taxa Sistema (%)</label>
                        <input type="number" wire:model.live="feePercent" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm focus:border-ifa-green focus:ring-ifa-green">
                        @error('feePercent') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-ebony">Observações do Orçamento</label>
                    <textarea wire:model="notes" rows="3" placeholder="Recomendações, forma de pagamento, etc..." class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm focus:border-ifa-green focus:ring-ifa-green"></textarea>
                    @error('notes') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                </div>

                <div class="border-t border-orisa-earth/10 pt-4 space-y-2">
                    <div class="flex justify-between text-sm text-ebony/70">
                        <span>Subtotal de itens:</span>
                        <span class="font-semibold">R$ {{ number_format($subtotal, 2, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between text-sm text-ebony/70">
                        <span>Markup ({{ $markupPercent }}%):</span>
                        @php $markupVal = $subtotal * ((float)$markupPercent / 100); @endphp
                        <span class="font-semibold">R$ {{ number_format($markupVal, 2, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between text-sm text-ebony/70">
                        <span>Taxa do Sistema ({{ $feePercent }}%):</span>
                        @php
                            $totalAfterMarkup = $subtotal + $markupVal;
                            $feePercentFloat = (float)$feePercent;
                            if ($feePercentFloat > 0 && $feePercentFloat < 100) {
                                $calculatedTotal = $totalAfterMarkup / (1 - ($feePercentFloat / 100));
                                $feeVal = $calculatedTotal - $totalAfterMarkup;
                            } else {
                                $feeVal = 0;
                            }
                        @endphp
                        <span class="font-semibold">R$ {{ number_format($feeVal, 2, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between border-t border-orisa-earth/10 pt-2 text-base font-bold text-ifa-green">
                        <span>Total Geral:</span>
                        <span>R$ {{ number_format($total, 2, ',', '.') }}</span>
                    </div>
                </div>

                <div class="pt-4">
                    @error('saveBudget')
                        <p class="mb-2 text-sm text-danger text-center">{{ $message }}</p>
                    @enderror
                    <button type="button" wire:click="saveBudget" class="w-full rounded bg-ifa-green py-2.5 text-center text-sm font-bold text-white hover:bg-ifa-green/90 shadow-sm transition-colors">
                        Gerar e Salvar Orçamento
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
