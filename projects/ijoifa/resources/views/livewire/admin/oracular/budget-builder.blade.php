<div>
    <a href="{{ route('admin.oracular.show', $recommendedWork->consultation->request_id) }}" class="text-sm text-ifa-green hover:underline">&larr; Voltar para a consulta</a>

    <h1 class="mt-2 font-display text-2xl text-ifa-green">
        Orçamento — <span lang="yo">{{ $recommendedWork->ritualWork->name }}</span>
    </h1>
    <p class="text-sm text-ebony/60">Consulente: <span lang="yo">{{ $recommendedWork->user->name }}</span></p>

    <div class="mt-6 rounded-lg border border-orisa-earth/20 bg-white p-2 shadow-sm flex gap-2">
        @foreach ([0, 1, 2] as $i)
            @php $summary = $optionSummaries[$i]; @endphp
            <button type="button" wire:click="switchOption({{ $i }})"
                class="flex-1 rounded px-3 py-2 text-sm font-medium transition-colors {{ $activeOptionIndex === $i ? 'bg-ifa-green text-white' : 'bg-sacred-sand text-ifa-green hover:bg-sacred-sand/70' }}">
                Opção {{ $i + 1 }}
                @if ($summary['filled'])
                    <span class="block text-[10px] {{ $activeOptionIndex === $i ? 'text-white/80' : 'text-ebony/50' }}">R$ {{ number_format($summary['total'], 2, ',', '.') }}</span>
                @else
                    <span class="block text-[10px] {{ $activeOptionIndex === $i ? 'text-white/60' : 'text-ebony/30' }} italic">{{ $i === 0 ? 'obrigatória' : 'vazia' }}</span>
                @endif
            </button>
        @endforeach
    </div>

    @if ($activeOptionIndex > 0)
        <div class="mt-3 flex items-center gap-2 rounded-lg border border-orisa-earth/20 bg-sacred-sand/40 px-4 py-3">
            <input type="checkbox" id="linked-{{ $activeOptionIndex }}" wire:model.live="optionLinkedToFirst.{{ $activeOptionIndex }}">
            <label for="linked-{{ $activeOptionIndex }}" class="text-sm">Esta opção soma com a Opção 1 (é um adicional — o consulente paga Opção 1 + esta opção, não uma alternativa a ela)</label>
        </div>
    @endif

    <div class="mt-4 rounded-lg border border-orisa-earth/20 bg-white p-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-sacred-sand">
                    <tr>
                        <th class="px-3 py-2">Item</th>
                        <th class="px-3 py-2">Qtd.</th>
                        <th class="px-3 py-2">Preço unit. (R$)</th>
                        <th class="px-3 py-2">Subtotal</th>
                        <th class="px-3 py-2">Por conta do consulente</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (($optionItems[$activeOptionIndex] ?? []) as $index => $item)
                        <tr class="border-t border-orisa-earth/10" wire:key="opt-{{ $activeOptionIndex }}-item-{{ $index }}">
                            <td class="px-3 py-2">
                                <input type="text" wire:model.live.debounce.500ms="optionItems.{{ $activeOptionIndex }}.{{ $index }}.description" class="w-full rounded border border-orisa-earth/30 px-2 py-1">
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" step="0.001" wire:model.live.debounce.500ms="optionItems.{{ $activeOptionIndex }}.{{ $index }}.quantity" class="w-20 rounded border border-orisa-earth/30 px-2 py-1">
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" step="0.01" wire:model.live.debounce.500ms="optionItems.{{ $activeOptionIndex }}.{{ $index }}.unit_price" class="w-24 rounded border border-orisa-earth/30 px-2 py-1">
                            </td>
                            <td class="px-3 py-2 font-mono">
                                {{ number_format(($item['quantity'] ?: 0) * ($item['unit_price'] ?: 0), 2, ',', '.') }}
                            </td>
                            <td class="px-3 py-2 text-center">
                                <input type="checkbox" wire:model="optionItems.{{ $activeOptionIndex }}.{{ $index }}.by_client">
                            </td>
                            <td class="px-3 py-2">
                                <button type="button" wire:click="removeRow({{ $index }})" class="text-danger hover:underline">remover</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="relative">
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-xs font-semibold text-ifa-green">Buscar Insumo no Catálogo</label>
                    <button type="button" wire:click="$toggle('showSupplyCatalog')" class="text-[11px] text-ifa-green hover:underline">
                        {{ $showSupplyCatalog ? 'Ocultar catálogo' : 'Ver catálogo completo' }}
                    </button>
                </div>
                <input type="text" wire:model.live.debounce.300ms="supplySearch" placeholder="Buscar insumos (ex: Vela, Erva, Atabaque)..." class="w-full rounded border border-orisa-earth/30 px-3 py-2 text-xs focus:border-ifa-green focus:ring-ifa-green">
                @if (!empty($supplyResults))
                    <div class="absolute left-0 right-0 z-50 mt-1 max-h-60 overflow-y-auto rounded-md bg-white border border-orisa-earth/25 shadow-lg">
                        @foreach ($supplyResults as $supply)
                            <button type="button" wire:click="addSupplyRow({{ $supply->id }})" class="w-full text-left px-3 py-2 text-xs hover:bg-sacred-sand/40 border-b border-orisa-earth/10 transition-colors flex justify-between items-center">
                                <span class="font-medium text-ebony">{{ $supply->name }}<span class="ml-1 text-[10px] text-ebony/40">{{ $supply->category }}</span></span>
                                @if($supply->prices->isNotEmpty())
                                    <span class="text-[10px] text-ifa-green font-mono">R$ {{ number_format($supply->prices->sortByDesc('purchase_date')->first()->unit_price, 2, ',', '.') }}</span>
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

            <div class="relative">
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-xs font-semibold text-ifa-green">Buscar Animal no Catálogo</label>
                    <button type="button" wire:click="$toggle('showAnimalCatalog')" class="text-[11px] text-ifa-green hover:underline">
                        {{ $showAnimalCatalog ? 'Ocultar catálogo' : 'Ver catálogo completo' }}
                    </button>
                </div>
                <input type="text" wire:model.live.debounce.300ms="animalSearch" placeholder="Buscar animais (ex: Galo, Cabra)..." class="w-full rounded border border-orisa-earth/30 px-3 py-2 text-xs focus:border-ifa-green focus:ring-ifa-green">
                @if (!empty($animalResults))
                    <div class="absolute left-0 right-0 z-50 mt-1 max-h-60 overflow-y-auto rounded-md bg-white border border-orisa-earth/25 shadow-lg">
                        @foreach ($animalResults as $animal)
                            <button type="button" wire:click="addAnimalRow({{ $animal->id }})" class="w-full text-left px-3 py-2 text-xs hover:bg-sacred-sand/40 border-b border-orisa-earth/10 transition-colors flex justify-between items-center">
                                <span class="font-medium text-ebony">{{ $animal->name }}</span>
                                @if($animal->prices->isNotEmpty())
                                    <span class="text-[10px] text-ifa-green font-mono">R$ {{ number_format($animal->prices->sortByDesc('purchase_date')->first()->unit_price, 2, ',', '.') }}</span>
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

        <div class="relative mt-3">
            <div class="flex items-center justify-between mb-1">
                <label class="block text-xs font-semibold text-ifa-green">Reaproveitar "outro gasto" já usado</label>
                <button type="button" wire:click="$toggle('showExpenseItemCatalog')" class="text-[11px] text-ifa-green hover:underline">
                    {{ $showExpenseItemCatalog ? 'Ocultar catálogo' : 'Ver catálogo completo' }}
                </button>
            </div>
            <input type="text" wire:model.live.debounce.300ms="expenseItemSearch" placeholder="Buscar gasto já usado (ex: Frete, Mão de obra)..." class="w-full rounded border border-orisa-earth/30 px-3 py-2 text-xs focus:border-ifa-green focus:ring-ifa-green">
            @if (!empty($expenseItemResults))
                <div class="absolute left-0 right-0 z-50 mt-1 max-h-60 overflow-y-auto rounded-md bg-white border border-orisa-earth/25 shadow-lg">
                    @foreach ($expenseItemResults as $expenseItem)
                        <button type="button" wire:click="addExpenseItemRow({{ $expenseItem->id }})" class="w-full text-left px-3 py-2 text-xs hover:bg-sacred-sand/40 border-b border-orisa-earth/10 transition-colors flex justify-between items-center">
                            <span class="font-medium text-ebony">{{ $expenseItem->name }}</span>
                            <span class="ml-1 text-[10px] text-ifa-green font-mono">R$ {{ number_format($expenseItem->unit_price, 2, ',', '.') }}</span>
                        </button>
                    @endforeach
                </div>
            @elseif(strlen(trim($expenseItemSearch)) >= 2)
                <div class="absolute left-0 right-0 z-50 mt-1 rounded-md bg-white border border-orisa-earth/25 p-3 text-center text-xs text-ebony/50 italic shadow-lg">
                    Nenhum gasto encontrado para "{{ $expenseItemSearch }}". Use "+ outro gasto" abaixo para criar um novo.
                </div>
            @endif
        </div>

        <div class="mt-3 flex gap-2">
            <button type="button" wire:click="addCustomRow" class="rounded bg-ifa-green px-3 py-1.5 text-sm text-white hover:bg-ifa-green/90">+ outro gasto</button>
            <button type="button" wire:click="addPersonalPaymentRow" class="rounded bg-orisa-earth px-3 py-1.5 text-sm text-white hover:bg-orisa-earth/90">+ pagamento pessoal</button>
        </div>
        <p class="mt-1 text-xs text-ebony/50">Use "outro gasto" para mão de obra, frete ou itens consumidos que não são contados por unidade — preencha a descrição e informe o valor total em "Preço unit.". Todo item salvo aqui fica disponível para reaproveitar depois. Use "pagamento pessoal" para lançar um valor seu nesta opção.</p>

        <div class="mt-4">
            <label class="block text-sm font-medium">Comentário da Opção {{ $activeOptionIndex + 1 }} (visível para o consulente)</label>
            <textarea wire:key="option-comment-{{ $activeOptionIndex }}" wire:model="optionComments.{{ $activeOptionIndex }}" rows="2" placeholder="Ex: opção completa com ervas importadas" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm focus:border-ifa-green focus:ring-ifa-green"></textarea>
            <p class="mt-1 text-[11px] text-ebony/45">O consulente vê o nome do trabalho, o valor e este comentário de cada opção preenchida — os itens da composição não aparecem para ele.</p>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-2">
            <div class="flex gap-4">
                <div>
                    <label class="block text-sm font-medium">Markup da Opção {{ $activeOptionIndex + 1 }} (%)</label>
                    <input wire:key="markup-{{ $activeOptionIndex }}" type="number" step="0.01" wire:model.live="markup_percent.{{ $activeOptionIndex }}" class="mt-1 w-32 rounded border border-orisa-earth/30 px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium">Taxa do sistema da Opção {{ $activeOptionIndex + 1 }} (%)</label>
                    <input wire:key="fee-{{ $activeOptionIndex }}" type="number" step="0.01" wire:model.live="fee_percent.{{ $activeOptionIndex }}" class="mt-1 w-32 rounded border border-orisa-earth/30 px-3 py-2">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium">Observações (o que o consulente deve providenciar)</label>
                <textarea wire:model="notes" rows="2" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2"></textarea>
            </div>
        </div>

        <div class="mt-4 rounded bg-sacred-sand p-4 text-right space-y-1">
            @if ($activeOptionIndex > 0 && ($optionLinkedToFirst[$activeOptionIndex] ?? false))
                <p class="text-xs text-ebony/50 italic">Inclui a Opção 1 (R$ {{ number_format($optionSummaries[0]['total'], 2, ',', '.') }}) + os itens desta opção</p>
            @endif
            <p class="text-sm">Subtotal Opção {{ $activeOptionIndex + 1 }} (itens da casa): R$ {{ number_format($subtotal, 2, ',', '.') }}</p>
            @if ((float) $fee_percent[$activeOptionIndex] > 0)
                @php
                    $totalAfterMarkup = $ownSubtotal * (1 + ((float) $markup_percent[$activeOptionIndex] / 100));
                    $feePercentFloat = (float) $fee_percent[$activeOptionIndex];
                    if ($feePercentFloat > 0 && $feePercentFloat < 100) {
                        $calculatedTotal = $totalAfterMarkup / (1 - ($feePercentFloat / 100));
                        $feeAmount = $calculatedTotal - $totalAfterMarkup;
                    } else {
                        $feeAmount = 0;
                    }
                @endphp
                <p class="text-sm text-ebony/70">Taxa do sistema desta opção ({{ $fee_percent[$activeOptionIndex] }}%): R$ {{ number_format($feeAmount, 2, ',', '.') }}</p>
            @endif
            <p class="font-display text-lg text-ifa-green font-bold">Total Opção {{ $activeOptionIndex + 1 }}: R$ {{ number_format($total, 2, ',', '.') }}</p>
        </div>

        <div class="mt-4 border-t border-orisa-earth/10 pt-4 space-y-1">
            <p class="text-xs font-semibold text-ifa-green">Resumo das opções</p>
            @foreach ($optionSummaries as $summary)
                <div class="flex justify-between text-xs {{ $summary['filled'] ? 'text-ebony/70' : 'text-ebony/30 italic' }}">
                    <span>Opção {{ $summary['index'] + 1 }}{{ $summary['filled'] ? '' : ($summary['index'] === 0 ? ' (obrigatória)' : ' (vazia)') }}</span>
                    <span>{{ $summary['filled'] ? 'R$ ' . number_format($summary['total'], 2, ',', '.') : '—' }}</span>
                </div>
            @endforeach
        </div>

        @error('send')
            <p class="mt-2 text-sm text-danger text-right">{{ $message }}</p>
        @enderror

        <div class="mt-4 flex justify-end gap-2">
            @if ($budgetId)
                <a href="{{ route('budgets.pdf', $budgetId) }}" target="_blank" class="rounded border border-ifa-green px-4 py-2 text-ifa-green hover:bg-sacred-sand">Ver PDF</a>
            @endif
            <button wire:click="saveDraft" class="rounded px-4 py-2 text-ebony/70 hover:bg-sacred-sand">Salvar rascunho</button>
            <button wire:click="send" wire:confirm="Enviar este orçamento para o consulente?" class="rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">Enviar orçamento</button>
        </div>
    </div>
</div>
