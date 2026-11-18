<div>
    @unless (\App\Services\MercadoPagoService::configured())
        <div class="mb-4 rounded border border-orisa-earth/30 bg-orisa-earth/10 p-3 text-sm text-ebony">
            Mercado Pago não está configurado (falta <code>MERCADO_PAGO_ACCESS_TOKEN</code> no .env). O cliente ainda pode se cadastrar pelo link, mas o pagamento fica pendente até confirmação manual em Cobranças.
        </div>
    @endunless

    <h1 class="font-display text-2xl text-ifa-green">
        Gerador de Link de Cadastro e Pagamento
    </h1>
    <p class="text-sm text-ebony/60">Gere orçamentos e links rápidos para novos consulentes (via WhatsApp) ou cadastrados.</p>

    @if (session()->has('success'))
        <div class="mt-4 rounded-lg bg-ifa-green/10 border border-ifa-green/30 p-4 text-ifa-green text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <!-- Budget Form Column -->
        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-lg border border-orisa-earth/20 bg-white p-6 shadow-sm">
                <h2 class="font-display text-lg text-ifa-green mb-4">1. Detalhes da Cobrança</h2>
                
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="qp-type" class="block text-sm font-medium">Tipo do Serviço</label>
                        <select id="qp-type" wire:model.live="type" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm focus:border-ifa-green focus:ring-ifa-green">
                            <option value="" disabled>Selecione o tipo...</option>
                            <option value="consulta">Consulta</option>
                            <option value="trabalhos_gerais">Trabalhos gerais</option>
                            <option value="iniciacao">Iniciação</option>
                            <option value="aborisa">Aborisa</option>
                        </select>
                        @error('type') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="qp-notes" class="block text-sm font-medium">O que será feito (Descrição para o cliente)</label>
                        <input id="qp-notes" type="text" wire:model="notes" placeholder="Ex: Ebó para caminhos abertos, Consulta oracular, etc." class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm focus:border-ifa-green focus:ring-ifa-green">
                    </div>
                </div>
            </div>

            <!-- Options Tabs -->
            <div class="rounded-lg border border-orisa-earth/20 bg-white p-2 shadow-sm flex gap-2">
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

            <!-- Items Section -->
            <div class="rounded-lg border border-orisa-earth/20 bg-white p-6 shadow-sm">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="font-display text-lg text-ifa-green">2. Itens da Opção {{ $activeOptionIndex + 1 }} (Opcional)</h2>
                    <div class="flex gap-2">
                        @if(count($optionItems[$activeOptionIndex]) > 0)
                            <button type="button" wire:click="openSaveTemplateModal" class="text-xs bg-ifa-green hover:bg-ifa-green/90 text-white px-3 py-1.5 rounded-lg transition-colors font-medium flex items-center gap-1 shadow-sm">
                                💾 Salvar como Modelo
                            </button>
                        @endif
                        <button type="button" wire:click="addItemRow" class="text-xs bg-sacred-sand hover:bg-sacred-sand/80 text-ifa-green border border-orisa-earth/20 px-3 py-1.5 rounded-lg transition-colors font-medium">
                            + Item Personalizado
                        </button>
                    </div>
                </div>

                <!-- Catalog Add / Load Template Section -->
                <div class="grid gap-4 md:grid-cols-3 mb-4">
                    <!-- Add from Catalog -->
                    <div class="relative">
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-semibold text-ifa-green">Buscar Insumo no Catálogo</label>
                            <button type="button" wire:click="$toggle('showSupplyCatalog')" class="text-[11px] text-ifa-green hover:underline">
                                {{ $showSupplyCatalog ? 'Ocultar' : 'Ver tudo' }}
                            </button>
                        </div>
                        <div class="relative">
                            <input type="text" wire:model.live.debounce.300ms="supplySearch" placeholder="Buscar insumos (ex: Vela, Erva, Atabaque)..." class="w-full rounded border border-orisa-earth/30 px-3 py-2 text-xs focus:border-ifa-green focus:ring-ifa-green">
                            @if (!empty($searchResults))
                                <div class="absolute left-0 right-0 z-50 mt-1 max-h-60 overflow-y-auto rounded-md bg-white border border-orisa-earth/25 shadow-lg">
                                    @foreach ($searchResults as $supply)
                                        <button type="button" wire:click="selectCatalogItemById({{ $supply->id }})" class="w-full text-left px-3 py-2 text-xs hover:bg-sacred-sand/40 border-b border-orisa-earth/10 transition-colors flex justify-between items-center">
                                            <span class="font-medium text-ebony">{{ $supply->name }}<span class="ml-1 text-[10px] text-ebony/40">{{ $supply->category }}</span></span>
                                            @if($supply->prices->isNotEmpty())
                                                <span class="text-[10px] text-ifa-green font-mono">
                                                    R$ {{ number_format($supply->prices->sortByDesc('purchase_date')->first()->unit_price, 2, ',', '.') }}
                                                </span>
                                            @else
                                                <span class="text-[10px] text-ebony/40 italic">Sem preço</span>
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
                    </div>

                    <!-- Add Animal from Catalog -->
                    <div class="relative">
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-semibold text-ifa-green">Buscar Animal no Catálogo</label>
                            <button type="button" wire:click="$toggle('showAnimalCatalog')" class="text-[11px] text-ifa-green hover:underline">
                                {{ $showAnimalCatalog ? 'Ocultar' : 'Ver tudo' }}
                            </button>
                        </div>
                        <div class="relative">
                            <input type="text" wire:model.live.debounce.300ms="animalSearch" placeholder="Buscar animais (ex: Galo, Cabra)..." class="w-full rounded border border-orisa-earth/30 px-3 py-2 text-xs focus:border-ifa-green focus:ring-ifa-green">
                            @if (!empty($animalResults))
                                <div class="absolute left-0 right-0 z-50 mt-1 max-h-60 overflow-y-auto rounded-md bg-white border border-orisa-earth/25 shadow-lg">
                                    @foreach ($animalResults as $animal)
                                        <button type="button" wire:click="selectAnimalItemById({{ $animal->id }})" class="w-full text-left px-3 py-2 text-xs hover:bg-sacred-sand/40 border-b border-orisa-earth/10 transition-colors flex justify-between items-center">
                                            <span class="font-medium text-ebony">{{ $animal->name }}</span>
                                            @if($animal->prices->isNotEmpty())
                                                <span class="text-[10px] text-ifa-green font-mono">
                                                    R$ {{ number_format($animal->prices->sortByDesc('purchase_date')->first()->unit_price, 2, ',', '.') }}
                                                </span>
                                            @else
                                                <span class="text-[10px] text-ebony/40 italic">Sem preço</span>
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

                    <!-- Load Template -->
                    <div class="relative">
                        <label class="block text-xs font-semibold text-ifa-green mb-1">Carregar Modelo de Trabalho</label>
                        <div class="relative">
                            <input type="text" wire:model.live.debounce.300ms="templateSearch" placeholder="Buscar modelo (ex: Ebó, Iniciação)..." class="w-full rounded border border-orisa-earth/30 px-3 py-2 text-xs focus:border-ifa-green focus:ring-ifa-green">
                            @if (!empty($templateResults))
                                <div class="absolute left-0 right-0 z-50 mt-1 max-h-60 overflow-y-auto rounded-md bg-white border border-orisa-earth/25 shadow-lg">
                                    @foreach ($templateResults as $template)
                                        <button type="button" wire:click="loadTemplate({{ $template->id }})" class="w-full text-left px-3 py-2 text-xs hover:bg-sacred-sand/40 border-b border-orisa-earth/10 transition-colors flex justify-between items-center">
                                            <span class="font-medium text-ebony">{{ $template->name }}</span>
                                            <span class="text-[10px] text-ebony/40 font-mono">{{ $template->supplies_count ?? $template->supplies()->count() }} itens</span>
                                        </button>
                                    @endforeach
                                </div>
                            @elseif(strlen(trim($templateSearch)) >= 2)
                                <div class="absolute left-0 right-0 z-50 mt-1 rounded-md bg-white border border-orisa-earth/25 p-3 text-center text-xs text-ebony/50 italic shadow-lg">
                                    Nenhum modelo encontrado para "{{ $templateSearch }}"
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                @if(count($optionItems[$activeOptionIndex]) > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-sacred-sand text-ifa-green font-display">
                                <tr>
                                    <th class="px-3 py-2">Item</th>
                                    <th class="px-3 py-2 w-20">Qtd.</th>
                                    <th class="px-3 py-2 w-32">Preço Unit. (R$)</th>
                                    <th class="px-3 py-2">Fornecedor</th>
                                    <th class="px-3 py-2 w-20 text-center">Por conta do consulente</th>
                                    <th class="px-3 py-2 text-right">Subtotal</th>
                                    <th class="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($optionItems[$activeOptionIndex] as $index => $item)
                                    <tr class="border-t border-orisa-earth/10 align-middle" wire:key="opt-{{ $activeOptionIndex }}-item-{{ $index }}">
                                        <td class="px-3 py-2">
                                            <input type="text" wire:model.live="optionItems.{{ $activeOptionIndex }}.{{ $index }}.name" class="w-full rounded border border-orisa-earth/30 px-2 py-1 text-xs">
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="number" step="0.001" wire:model.live="optionItems.{{ $activeOptionIndex }}.{{ $index }}.quantity" class="w-20 rounded border border-orisa-earth/30 px-2 py-1 text-xs">
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="flex items-center gap-1">
                                                <input type="number" step="0.01" wire:model.live="optionItems.{{ $activeOptionIndex }}.{{ $index }}.unit_price" class="w-24 rounded border border-orisa-earth/30 px-2 py-1 text-xs">
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 text-xs">
                                            @if(!empty($item['supplier_name']))
                                                <span class="text-ifa-green font-medium">{{ $item['supplier_name'] }}</span>
                                            @else
                                                <span class="text-ebony/40 italic">Estimado (Sem Fornecedor)</span>
                                            @endif
                                            <button type="button" wire:click="viewSupplierPrices({{ $index }})" class="ml-1 text-ifa-green hover:underline text-[10px] font-bold block">
                                                🔍 Preços / Comparar
                                            </button>
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <input type="checkbox" wire:model.live="optionItems.{{ $activeOptionIndex }}.{{ $index }}.by_client" class="rounded text-ifa-green focus:ring-ifa-green">
                                        </td>
                                        <td class="px-3 py-2 text-right font-mono text-xs">
                                            {{ number_format(($item['quantity'] ?: 0) * ($item['unit_price'] ?: 0), 2, ',', '.') }}
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <button type="button" wire:click="removeRow({{ $index }})" class="text-danger hover:underline text-xs">Remover</button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-ebony/40 text-center py-6">Nenhum item adicionado. O link cobrará o valor base diretamente.</p>
                @endif

                <div class="mt-4">
                    <label class="block text-sm font-medium">Comentário desta opção (visível para o cliente)</label>
                    <textarea wire:key="option-comment-{{ $activeOptionIndex }}" wire:model="optionComments.{{ $activeOptionIndex }}" rows="2" placeholder="Ex: Opção completa com ervas importadas" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm focus:border-ifa-green focus:ring-ifa-green"></textarea>
                    <p class="mt-1 text-[11px] text-ebony/45">Dica: para uma opção "os dois trabalhos", carregue os dois modelos nesta mesma opção (busque e clique em "Carregar Modelo" duas vezes, uma para cada trabalho) ou monte manualmente os itens de ambos aqui — o cliente escolhe só uma opção no link, então se ele quiser "os dois" ele escolhe a opção que já inclui os dois combinados. Para opções independentes, basta preencher opções diferentes com conteúdos separados.</p>
                </div>
            </div>

            <!-- Pricing supplier list card -->
            @if ($activePriceViewSupplyName)
                <div class="rounded-lg border border-orun-blue/30 bg-orun-blue/5 p-6 shadow-sm">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-display text-md text-ifa-green">Comparativo de Preços: {{ $activePriceViewSupplyName }}</h3>
                        <button type="button" wire:click="closeSupplierPrices" class="text-ebony/60 hover:text-danger">&times; Fechar</button>
                    </div>

                    @if(count($activeSupplierPrices) > 0)
                        <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-3">
                            @foreach ($activeSupplierPrices as $ap)
                                <div class="p-3 bg-white rounded-lg border border-orisa-earth/10 hover:border-ifa-green/40 transition-colors flex flex-col justify-between">
                                    <div>
                                        <span class="text-xs text-ebony/60 block">{{ $ap['supplier_name'] }}</span>
                                        <strong class="text-lg text-ifa-green">R$ {{ number_format($ap['price'], 2, ',', '.') }}</strong>
                                        <span class="text-[10px] text-ebony/40 block">Pagto: {{ $ap['payment_method'] }}</span>
                                    </div>
                                    <button type="button" wire:click="selectSupplierPrice({{ $ap['supplier_id'] }}, '{{ $ap['supplier_name'] }}', {{ $ap['price'] }})" class="mt-3 w-full bg-ifa-green text-white text-xs py-1 px-2 rounded hover:bg-ifa-green/90 transition-colors">
                                        Usar este Preço
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-ebony/55 italic">Nenhum preço ou fornecedor cadastrado para este item. Você pode definir o valor estimado diretamente na tabela.</p>
                    @endif
                </div>
            @endif
        </div>

        <!-- Checkout / Total Calculation Column -->
        <div class="space-y-6">
            <div class="rounded-lg border border-orisa-earth/20 bg-white p-6 shadow-sm sticky top-6">
                <h2 class="font-display text-lg text-ifa-green mb-4">3. Ajustes de Taxas e Valores</h2>
                
                <div class="space-y-4">
                    @if(count($optionItems[$activeOptionIndex]) === 0)
                        <div wire:key="option-base-value-wrapper-{{ $activeOptionIndex }}">
                            <label class="block text-sm font-medium">Valor Base da Opção {{ $activeOptionIndex + 1 }} (R$)</label>
                            <input type="number" step="0.01" wire:key="option-base-value-{{ $activeOptionIndex }}" wire:model.live.debounce.300ms="optionBaseValue.{{ $activeOptionIndex }}" placeholder="Digite o valor a cobrar..." class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm focus:border-ifa-green focus:ring-ifa-green">
                            @error('optionBaseValue.' . $activeOptionIndex) <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                        </div>
                    @else
                        <div>
                            <label class="block text-sm font-medium">Lucro (%) sobre o custo</label>
                            <input type="number" step="0.01" wire:model.live="markup_percent" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm focus:border-ifa-green focus:ring-ifa-green">
                            <span class="text-[10px] text-ebony/40">Margem aplicada sobre itens + frete (aplicado às 3 opções)</span>
                        </div>
                    @endif

                    <div wire:key="option-freight-wrapper-{{ $activeOptionIndex }}">
                        <label class="block text-sm font-medium">Frete (R$)</label>
                        <input type="number" step="0.01" min="0" wire:key="option-freight-{{ $activeOptionIndex }}" wire:model.live.debounce.300ms="optionFreight.{{ $activeOptionIndex }}" placeholder="0,00" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm focus:border-ifa-green focus:ring-ifa-green">
                        <span class="text-[10px] text-ebony/40">Custo de envio/transporte da Opção {{ $activeOptionIndex + 1 }}, somado antes do lucro</span>
                    </div>

                    <div wire:key="option-labor-wrapper-{{ $activeOptionIndex }}">
                        <label class="block text-sm font-medium">Mão de obra (R$)</label>
                        <input type="number" step="0.01" min="0" wire:key="option-labor-{{ $activeOptionIndex }}" wire:model.live.debounce.300ms="optionLaborCost.{{ $activeOptionIndex }}" placeholder="0,00" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm focus:border-ifa-green focus:ring-ifa-green">
                        <span class="text-[10px] text-ebony/40">Taxa mínima fixa de trabalho da Opção {{ $activeOptionIndex + 1 }}, somada após o lucro</span>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Taxa da Operadora de Cartão (%)</label>
                        <input type="number" step="0.01" wire:model.live="fee_percent" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm focus:border-ifa-green focus:ring-ifa-green">
                        <span class="text-[10px] text-ebony/40">Custo financeiro de processamento do cartão (Ex: 4.99%, aplicado às 3 opções)</span>
                    </div>

                    <div class="border-t border-orisa-earth/10 pt-4 space-y-2">
                        <div class="flex justify-between text-sm text-ebony/60">
                            <span>Subtotal (Opção {{ $activeOptionIndex + 1 }}):</span>
                            <span>R$ {{ number_format($subtotal, 2, ',', '.') }}</span>
                        </div>
                        @if($freight > 0)
                            <div class="flex justify-between text-sm text-ebony/60">
                                <span>Frete:</span>
                                <span>R$ {{ number_format($freight, 2, ',', '.') }}</span>
                            </div>
                        @endif
                        @if((float)$markup_percent > 0 && count($optionItems[$activeOptionIndex]) > 0)
                            <div class="flex justify-between text-sm text-ebony/60">
                                <span>Lucro ({{ $markup_percent }}%):</span>
                                <span>R$ {{ number_format($profit, 2, ',', '.') }}</span>
                            </div>
                        @endif
                        @if($laborCost > 0)
                            <div class="flex justify-between text-sm text-ebony/60">
                                <span>Mão de obra:</span>
                                <span>R$ {{ number_format($laborCost, 2, ',', '.') }}</span>
                            </div>
                        @endif
                        @if((float)$fee_percent > 0)
                            @php
                                $base = $subtotal + $freight + $profit + $laborCost;
                                $final = $fee_percent < 100 ? ($base / (1 - ((float)$fee_percent / 100))) : $base;
                                $feeVal = $final - $base;
                            @endphp
                            <div class="flex justify-between text-sm text-ebony/60">
                                <span>Custos do cartão ({{ $fee_percent }}%):</span>
                                <span>R$ {{ number_format($feeVal, 2, ',', '.') }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between font-display text-lg text-ifa-green font-bold border-t border-orisa-earth/10 pt-2">
                            <span>Preço Final (Opção {{ $activeOptionIndex + 1 }}):</span>
                            <span>R$ {{ number_format($total, 2, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="border-t border-orisa-earth/10 pt-4 space-y-1">
                        <p class="text-xs font-semibold text-ifa-green">Resumo das opções</p>
                        @foreach ($optionSummaries as $summary)
                            <div class="flex justify-between text-xs {{ $summary['filled'] ? 'text-ebony/70' : 'text-ebony/30 italic' }}">
                                <span>Opção {{ $summary['index'] + 1 }}{{ $summary['filled'] ? '' : ($summary['index'] === 0 ? ' (obrigatória)' : ' (vazia)') }}</span>
                                <span>{{ $summary['filled'] ? 'R$ ' . number_format($summary['total'], 2, ',', '.') : '—' }}</span>
                            </div>
                        @endforeach
                    </div>

                    @error('generateLink')
                        <p class="mt-2 text-xs text-danger text-center">{{ $message }}</p>
                    @enderror
                    <button type="button" wire:click="generateLink" class="mt-6 w-full rounded bg-ifa-green py-3 text-center text-sm font-semibold text-white hover:bg-ifa-green/90 transition-colors shadow-sm">
                        Gerar Link de Pagamento
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Generated Link Output -->
    @if ($generatedLink)
        <div class="mt-8 rounded-lg border-2 border-dashed border-ifa-green/30 bg-ifa-green/5 p-6 text-center">
            <h3 class="font-display text-lg text-ifa-green font-bold mb-2">Link Gerado com Sucesso!</h3>
            <p class="text-xs text-ebony/70 mb-4">Envie este link no WhatsApp do cliente. Ao clicar, se não tiver cadastro ele irá se cadastrar e efetuar o pagamento. Se já tiver cadastro ele fará login e pagará.</p>
            
            <div class="flex max-w-xl mx-auto gap-2 items-center">
                <input type="text" readonly id="link-field" value="{{ $generatedLink }}" class="flex-1 bg-white rounded border border-orisa-earth/30 px-3 py-2 text-sm select-all">
                <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('link-field').value); alert('Link copiado!')" class="bg-ifa-green text-white px-4 py-2 text-sm rounded font-medium hover:bg-ifa-green/90 transition-colors">
                    Copiar
                </button>
            </div>

            @if(count($optionItems[$activeOptionIndex]) > 0)
                <div class="mt-4 flex justify-center gap-3">
                    <button type="button" wire:click="openSaveTemplateModal" class="text-xs bg-white text-ifa-green border border-ifa-green/35 hover:bg-sacred-sand/40 px-3 py-1.5 rounded-lg transition-colors font-medium flex items-center gap-1 shadow-sm">
                        💾 Salvar esses itens como Modelo de Trabalho para reuso
                    </button>
                </div>
            @endif
        </div>
    @endif

    <!-- Recent Links List -->
    <div class="mt-12 rounded-lg border border-orisa-earth/20 bg-white p-6 shadow-sm">
        <h2 class="font-display text-lg text-ifa-green mb-4">Últimos Links Gerados</h2>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-sacred-sand text-ifa-green font-display">
                    <tr>
                        <th class="px-4 py-2">Data</th>
                        <th class="px-4 py-2">Serviço</th>
                        <th class="px-4 py-2">Descrição</th>
                        <th class="px-4 py-2 text-right">Valor Final</th>
                        <th class="px-4 py-2">Cliente</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($quickPayments as $qp)
                        <tr class="border-t border-orisa-earth/10">
                            <td class="px-4 py-3 text-xs text-ebony/60">
                                {{ $qp->created_at->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-4 py-3 text-xs font-bold text-ifa-green uppercase">
                                {{ str_replace('_', ' ', $qp->type) }}
                            </td>
                            <td class="px-4 py-3 text-xs text-ebony/70">
                                {{ $qp->notes }}
                            </td>
                            <td class="px-4 py-3 text-xs font-bold text-right font-mono text-ebony">
                                R$ {{ number_format($qp->total, 2, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-xs">
                                @if ($qp->user)
                                    <span class="font-medium text-ebony">{{ $qp->user->name }}</span>
                                    <span class="block text-[10px] text-ebony/50">{{ $qp->user->email }}</span>
                                @else
                                    <span class="text-ebony/40 italic">Aguardando cadastro</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs">
                                @if($qp->status === 'paid')
                                    <span class="inline-block px-2 py-1 rounded-full bg-ifa-green/10 text-ifa-green font-semibold text-[10px]">Pago</span>
                                @elseif($qp->status === 'cancelled')
                                    <span class="inline-block px-2 py-1 rounded-full bg-danger/10 text-danger font-semibold text-[10px]">Cancelado</span>
                                @else
                                    <span class="inline-block px-2 py-1 rounded-full bg-warning/10 text-warning font-semibold text-[10px]">Pendente</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button type="button" onclick="navigator.clipboard.writeText('{{ route('quick-checkout', ['uuid' => $qp->uuid]) }}'); alert('Link copiado!')" class="text-xs text-ifa-green hover:underline">
                                        Copiar Link
                                    </button>
                                    @if($qp->status === 'pending')
                                        <span class="text-ebony/20">|</span>
                                        <button type="button" wire:click="cancelLink({{ $qp->id }})" wire:confirm="Deseja realmente cancelar este link?" class="text-xs text-danger hover:underline">
                                            Cancelar
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Save Template Modal -->
    @if ($showSaveTemplateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-lg border border-orisa-earth/10">
                <h2 class="font-display text-xl text-ifa-green font-bold mb-2">💾 Salvar como Modelo de Trabalho</h2>
                <p class="text-xs text-ebony/60 mb-4">Transforme a lista de itens atual em um modelo de trabalho reutilizável no futuro.</p>

                <form wire:submit="saveAsTemplate" class="space-y-4">
                    <div>
                        <label for="template-name" class="block text-xs font-semibold text-ebony">Nome do Modelo (ex: Ebó Ipadá, Sacrifício, etc.)</label>
                        <input type="text" id="template-name" wire:model="newTemplateName" placeholder="Digite o nome..." class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-xs focus:border-ifa-green focus:ring-ifa-green">
                        @error('newTemplateName') <p class="mt-1 text-xs text-danger font-medium">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="template-desc" class="block text-xs font-semibold text-ebony">Descrição / Notas do Trabalho (Opcional)</label>
                        <textarea id="template-desc" wire:model="newTemplateDescription" rows="3" placeholder="Digite uma breve descrição..." class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-xs focus:border-ifa-green focus:ring-ifa-green"></textarea>
                        @error('newTemplateDescription') <p class="mt-1 text-xs text-danger font-medium">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="$set('showSaveTemplateModal', false)" class="rounded px-4 py-2 text-xs font-medium text-ebony/70 hover:bg-sacred-sand">
                            Cancelar
                        </button>
                        <button type="submit" class="rounded bg-ifa-green px-4 py-2 text-xs font-semibold text-white hover:bg-ifa-green/90 transition-colors shadow-sm">
                            Salvar Modelo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
