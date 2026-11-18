<div>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-2xl text-ifa-green">Novo Trabalho</h1>
        <a href="{{ route('admin.oracular.works') }}" class="text-sm text-ifa-green hover:underline">&larr; Ver todos os trabalhos</a>
    </div>

    {{-- Cliente --}}
    <div class="mt-4 rounded-lg border border-orisa-earth/20 bg-white p-4">
        <label class="block text-sm font-medium">Cliente (opcional para link avulso, obrigatório para orçamento formal)</label>

        @if ($selectedClient)
            <div class="mt-2 flex items-center justify-between rounded bg-sacred-sand px-3 py-2">
                <span lang="yo">{{ $selectedClient->name }} — {{ $selectedClient->email }}</span>
                <button type="button" wire:click="$set('selectedClientId', null)" class="text-xs text-danger hover:underline">trocar</button>
            </div>
        @else
            <div class="relative mt-1">
                <input type="text" wire:model.live.debounce.300ms="clientSearch" placeholder="Buscar cliente por nome ou e-mail..." class="w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm">
                @if (!empty($clientResults))
                    <div class="absolute left-0 right-0 z-50 mt-1 max-h-60 overflow-y-auto rounded-md bg-white border border-orisa-earth/25 shadow-lg">
                        @foreach ($clientResults as $client)
                            <button type="button" wire:click="$set('selectedClientId', {{ $client->id }})" class="w-full text-left px-3 py-2 text-xs hover:bg-sacred-sand/40 border-b border-orisa-earth/10" lang="yo">
                                {{ $client->name }} <span class="text-ebony/40">{{ $client->email }}</span>
                            </button>
                        @endforeach
                    </div>
                @elseif(strlen(trim($clientSearch)) >= 2)
                    <div class="absolute left-0 right-0 z-50 mt-1 rounded-md bg-white border border-orisa-earth/25 p-3 text-center text-xs text-ebony/50 italic shadow-lg">
                        Nenhum cliente encontrado. A pessoa poderá se cadastrar ao clicar no link.
                    </div>
                @endif
            </div>
        @endif
        @error('selectedClientId') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
    </div>

    {{-- Destino --}}
    <div class="mt-4 rounded-lg border border-orisa-earth/20 bg-white p-4">
        <label class="block text-sm font-medium mb-2">O que você quer gerar?</label>
        <div class="flex gap-3">
            <label class="flex-1 flex items-center gap-2 rounded border border-orisa-earth/30 px-3 py-2 cursor-pointer {{ $destination === 'quick_link' ? 'border-ifa-green bg-sacred-sand/40' : '' }}">
                <input type="radio" wire:model.live="destination" value="quick_link">
                <span class="text-sm">Link rápido de pagamento</span>
            </label>
            <label class="flex-1 flex items-center gap-2 rounded border border-orisa-earth/30 px-3 py-2 cursor-pointer {{ $destination === 'formal' ? 'border-ifa-green bg-sacred-sand/40' : '' }}">
                <input type="radio" wire:model.live="destination" value="formal">
                <span class="text-sm">Orçamento formal (vinculado a uma consulta)</span>
            </label>
        </div>

        @if ($destination === 'quick_link')
            <div class="mt-3">
                <label class="block text-xs font-medium">Tipo</label>
                <select wire:model="quickType" class="mt-1 rounded border border-orisa-earth/30 px-3 py-2 text-sm">
                    <option value="trabalhos_gerais">Trabalhos gerais</option>
                    <option value="consulta">Consulta</option>
                    <option value="iniciacao">Iniciação</option>
                    <option value="aborisa">Aborisa</option>
                </select>
            </div>
        @endif

        @if ($destination === 'formal' && $selectedClient)
            @if ($openRecommendedWorks->isNotEmpty())
                <div class="mt-3">
                    <label class="block text-xs font-medium">Anexar a um trabalho já recomendado (opcional)</label>
                    <select wire:model="existingRecommendedWorkId" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm">
                        <option value="">— Criar novo —</option>
                        @foreach ($openRecommendedWorks as $work)
                            <option value="{{ $work->id }}">{{ $work->ritualWork->name }} ({{ $work->status }})</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-ebony/50">Se escolher um trabalho existente, você será levado para a tela de orçamento dele.</p>
                </div>
            @endif

            @if (!$existingRecommendedWorkId)
                <div class="mt-3">
                    <label class="block text-xs font-medium">Trabalho do catálogo</label>
                    <select wire:model="ritualWorkId" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm">
                        <option value="">— Selecione —</option>
                        @foreach ($ritualWorks as $work)
                            <option value="{{ $work->id }}">{{ $work->name }}</option>
                        @endforeach
                    </select>
                    @error('ritualWorkId') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    @if ($ritualWorkId)
                        <button type="button" wire:click="loadTemplate({{ $ritualWorkId }})" class="mt-2 text-xs text-ifa-green hover:underline">carregar itens deste trabalho na Opção {{ $activeOptionIndex + 1 }}</button>
                    @endif
                </div>
            @endif
        @endif
    </div>

    {{-- Modelos --}}
    <div class="mt-4 rounded-lg border border-orisa-earth/20 bg-white p-4">
        <label class="block text-sm font-medium mb-2">Modelos salvos</label>
        <div class="flex gap-2">
            <select wire:model.live="selectedTemplateId" class="flex-1 rounded border border-orisa-earth/30 px-3 py-2 text-sm">
                <option value="">— Selecione um modelo —</option>
                @foreach ($workTemplates as $template)
                    <option value="{{ $template->id }}">{{ $template->name }}</option>
                @endforeach
            </select>
            <button type="button" wire:click="applyTemplate" wire:confirm="Isso substitui os itens preenchidos nas 3 opções pelo modelo escolhido. Continuar?" class="rounded bg-ifa-green px-3 py-2 text-sm text-white hover:bg-ifa-green/90">Aplicar modelo</button>
        </div>
        <div class="mt-2 flex gap-2">
            <input type="text" wire:model="templateName" placeholder="Nome para salvar este rascunho como modelo (ex: Ebó Riru padrão)..." class="flex-1 rounded border border-orisa-earth/30 px-3 py-2 text-sm">
            <button type="button" wire:click="saveAsTemplate" class="rounded bg-orisa-earth px-3 py-2 text-sm text-white hover:bg-orisa-earth/90">Salvar como modelo</button>
        </div>
        <p class="mt-1 text-xs text-ebony/50">Um modelo salva os itens, comentarios, markup e taxa das 3 opcoes para reaplicar em qualquer trabalho futuro, para qualquer cliente.</p>
        @error('templateName') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
    </div>

    @if ($destination === 'formal' && !$selectedClient)
        <div class="mt-4 rounded border border-orisa-earth/30 bg-orisa-earth/10 p-3 text-sm text-ebony">
            Selecione um cliente acima para montar um orçamento formal.
        </div>
    @else
        {{-- Opções --}}
        <div class="mt-4 rounded-lg border border-orisa-earth/20 bg-white p-2 shadow-sm flex gap-2">
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
                <label for="linked-{{ $activeOptionIndex }}" class="text-sm">Esta opção soma com a Opção 1 (é um adicional — o cliente paga Opção 1 + esta opção, não uma alternativa a ela)</label>
            </div>
        @endif

        <div class="mt-4 rounded-lg border border-orisa-earth/20 bg-white p-6">
            @if ($destination === 'quick_link' && $quickType === 'consulta')
                <div>
                    <label class="block text-sm font-medium">Valor da consulta (R$) — Opção {{ $activeOptionIndex + 1 }}</label>
                    <input type="number" step="0.01" wire:key="consulta-value-{{ $activeOptionIndex }}" wire:model.live.debounce.500ms="consultaValues.{{ $activeOptionIndex }}" class="mt-1 w-40 rounded border border-orisa-earth/30 px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-ebony/50">Markup e taxa abaixo se aplicam sobre este valor, como em qualquer outra opção.</p>
                </div>
            @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-sacred-sand">
                        <tr>
                            <th class="px-3 py-2">Item</th>
                            <th class="px-3 py-2">Qtd.</th>
                            <th class="px-3 py-2">Preço unit. (R$)</th>
                            <th class="px-3 py-2">Subtotal</th>
                            <th class="px-3 py-2">Por conta do cliente</th>
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
            <p class="mt-1 text-xs text-ebony/50">Use "outro gasto" para mão de obra, frete ou itens consumidos que não são contados por unidade. Todo item salvo aqui fica disponível para reaproveitar depois. Use "pagamento pessoal" para lançar um valor seu nesta opção.</p>
            @endif

            <div class="mt-4">
                <label class="block text-sm font-medium">Comentário da Opção {{ $activeOptionIndex + 1 }} (visível para o cliente)</label>
                <textarea wire:key="option-comment-{{ $activeOptionIndex }}" wire:model="optionComments.{{ $activeOptionIndex }}" rows="2" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm focus:border-ifa-green focus:ring-ifa-green"></textarea>
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
                    <label class="block text-sm font-medium">Observações</label>
                    <textarea wire:model="notes" rows="2" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2"></textarea>
                </div>
            </div>

            <div class="mt-4 rounded bg-sacred-sand p-4 text-right space-y-1">
                @if ($activeOptionIndex > 0 && ($optionLinkedToFirst[$activeOptionIndex] ?? false))
                    <p class="text-xs text-ebony/50 italic">Inclui a Opção 1 (R$ {{ number_format($optionSummaries[0]['total'], 2, ',', '.') }}) + os itens desta opção</p>
                @endif
                <p class="text-sm">Subtotal Opção {{ $activeOptionIndex + 1 }}: R$ {{ number_format($subtotal, 2, ',', '.') }}</p>
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

            @error('save')
                <p class="mt-2 text-sm text-danger text-right">{{ $message }}</p>
            @enderror

            <div class="mt-4 flex justify-end gap-2">
                <button wire:click="save" wire:confirm="Confirmar geração deste trabalho?" class="rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">
                    {{ $destination === 'formal' ? 'Gerar orçamento' : 'Gerar link' }}
                </button>
            </div>
        </div>
    @endif
</div>
