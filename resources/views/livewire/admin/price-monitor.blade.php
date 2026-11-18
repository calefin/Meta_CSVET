<div>
    <a href="{{ route('admin.finance.index') }}" class="text-sm text-ifa-green hover:underline">&larr; Voltar ao financeiro</a>

    <h1 class="mt-2 font-display text-2xl text-ifa-green">Monitoramento de Preços</h1>

    <!-- Importação de Tabelas de Preços via JSON -->
    <div class="mt-6 rounded-lg border border-orisa-earth/20 bg-white p-5">
        <h2 class="font-display text-lg text-ifa-green flex items-center gap-1.5 mb-3">
            <span>📥</span> Importar Tabela de Preços (JSON)
        </h2>
        <p class="text-xs text-ebony/60 mb-4">
            Carregue rapidamente uma listagem de preços em formato JSON fornecida pelo parceiro/fornecedor. O sistema validará a estrutura e os dados do arquivo antes de salvar.
        </p>

        <div class="grid gap-6 md:grid-cols-2">
            <!-- Form de Importacao -->
            <div class="space-y-4">
                <form wire:submit.prevent="importJson" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-ebony/70 mb-2">Selecione o Arquivo JSON</label>
                        <div class="flex items-center justify-center w-full">
                            <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-orisa-earth/30 rounded-lg cursor-pointer bg-sacred-sand/20 hover:bg-sacred-sand/40 transition-colors">
                                <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                    <span class="text-2xl mb-1">📄</span>
                                    <p class="text-xs text-ebony/70 font-semibold">Clique para selecionar</p>
                                    <p class="text-[10px] text-ebony/50">JSON (Max. 1MB)</p>
                                </div>
                                <input type="file" wire:model="jsonFile" accept=".json,application/json" class="hidden" />
                            </label>
                        </div>
                        @if ($jsonFile)
                            <div class="mt-2 flex items-center gap-1.5 text-xs text-ifa-green font-medium">
                                <span>✓</span> Arquivo selecionado: {{ $jsonFile->getClientOriginalName() }}
                            </div>
                        @endif
                        @error('jsonFile')
                            <div class="mt-2 rounded-md bg-danger/10 border border-danger/30 p-2.5 text-xs text-danger font-medium flex items-start gap-1.5">
                                <span>⚠️</span>
                                <div>{{ $message }}</div>
                            </div>
                        @enderror
                    </div>

                    @if (session()->has('import_status'))
                        <div class="rounded-md bg-success/10 border border-success/30 p-3 text-xs text-success font-medium flex items-start gap-1.5">
                            <span>✓</span>
                            <div>{{ session('import_status') }}</div>
                        </div>
                    @endif

                    <div>
                        <button type="submit" wire:loading.attr="disabled" class="w-full sm:w-auto rounded bg-ifa-green px-4 py-2.5 text-xs font-semibold text-white hover:bg-ifa-green/90 transition-all disabled:opacity-50 flex items-center justify-center gap-2">
                            <span wire:loading.remove wire:target="jsonFile, importJson">Importar Dados</span>
                            <span wire:loading wire:target="jsonFile, importJson" class="flex items-center gap-1.5">
                                <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                Processando...
                            </span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Template Exemplo -->
            <div class="bg-sacred-sand/30 rounded-lg border border-orisa-earth/10 p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="block text-xs font-bold text-orisa-earth uppercase tracking-wider">Template JSON de Exemplo</span>
                    <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('json-template').innerText); alert('Template copiado para a área de transferência!')" class="text-[10px] text-ifa-green font-semibold hover:underline flex items-center gap-1">
                        <span>📋</span> Copiar
                    </button>
                </div>
                <pre id="json-template" class="p-3 bg-ebony text-sacred-sand rounded border border-orisa-earth/20 text-[10px] font-mono overflow-y-auto leading-relaxed select-all" style="max-height: 180px;">{
  "supplier_name": "Fornecedor da Bahia",
  "purchase_date": "2026-06-21",
  "prices": [
    {
      "type": "animal",
      "name": "Frango branco",
      "price": 33.00,
      "payment_method": "dinheiro",
      "min_quantity": 1
    },
    {
      "type": "supply",
      "name": "Dendê",
      "price": 12.50,
      "payment_method": "credito",
      "min_quantity": 5
    }
  ]
}</pre>
                <div class="mt-3 space-y-1">
                    <span class="block text-[10px] font-bold text-ebony/60 uppercase">Campos Suportados:</span>
                    <ul class="text-[10px] text-ebony/75 list-disc list-inside space-y-0.5">
                        <li><strong>supplier_name</strong> (obrigatório): Nome do fornecedor.</li>
                        <li><strong>purchase_date</strong> (opcional): Data da cotação no formato YYYY-MM-DD.</li>
                        <li><strong>type</strong> (obrigatório): <code>"animal"</code> ou <code>"supply"</code>.</li>
                        <li><strong>name</strong> (obrigatório): Nome exato do item a ser cadastrado/atualizado.</li>
                        <li><strong>price</strong> (obrigatório): Preço unitário numérico decimal.</li>
                        <li><strong>payment_method</strong> (opcional): <code>"dinheiro"</code>, <code>"credito"</code> ou <code>"debito"</code>.</li>
                        <li><strong>min_quantity</strong> (opcional): Quantidade mínima para este valor (desconto por atacado).</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Analise de Oscilacao Temporal -->
    <div class="mt-6 rounded-lg border border-orisa-earth/20 bg-white p-5">
        <h2 class="font-display text-lg text-ifa-green flex items-center gap-1.5 mb-3">
            <span>📊</span> Histórico de Oscilação Temporal
        </h2>
        <p class="text-xs text-ebony/60 mb-4">Escolha um item para analisar as variações e obter estimativas de custo ao longo das épocas do ano.</p>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-xs font-semibold text-ebony/70 mb-1">1. Tipo de Item</label>
                <select wire:model.live="selectedType" class="w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-ifa-green">
                    <option value="">— Selecione o tipo —</option>
                    <option value="animal">Animal</option>
                    <option value="supply">Insumo (Materiais/Ervas)</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-ebony/70 mb-1">2. Selecione o Item</label>
                <select wire:model.live="selectedItemId" @disabled(!$selectedType) class="w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-ifa-green disabled:bg-sacred-sand">
                    <option value="">— Selecione o item —</option>
                    @foreach ($items as $item)
                        <option value="{{ $item->id }}">{{ $item->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if ($history)
            <div class="mt-6 grid gap-6 md:grid-cols-3 border-t border-orisa-earth/10 pt-6">
                <!-- Estatisticas basicas -->
                <div class="md:col-span-1 space-y-4">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-orisa-earth">Estatísticas do Período</h3>
                    
                    <div class="rounded-xl border border-orisa-earth/10 bg-sacred-sand/30 p-4 space-y-3">
                        <div>
                            <span class="text-[10px] text-ebony/50 block font-semibold uppercase">Preço Médio</span>
                            <span class="text-lg font-bold text-ifa-green">R$ {{ number_format($history['avg'], 2, ',', '.') }}</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 pt-1 border-t border-orisa-earth/5">
                            <div>
                                <span class="text-[9px] text-ebony/50 block font-semibold uppercase">Menor Valor</span>
                                <span class="text-sm font-bold text-ebony/80">R$ {{ number_format($history['min'], 2, ',', '.') }}</span>
                            </div>
                            <div>
                                <span class="text-[9px] text-ebony/50 block font-semibold uppercase">Maior Valor</span>
                                <span class="text-sm font-bold text-ebony/80">R$ {{ number_format($history['max'], 2, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>

                    <h3 class="text-xs font-bold uppercase tracking-wider text-orisa-earth pt-2">Estimativa por Época (Média)</h3>
                    
                    <div class="rounded-xl border border-orisa-earth/10 bg-sacred-sand/30 p-4 space-y-2.5">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-medium text-ebony/70">🌅 Q1 (Jan - Mar / Verão)</span>
                            <span class="font-bold text-ebony">
                                {{ $history['quarters']['Q1'] ? 'R$ '.number_format($history['quarters']['Q1'], 2, ',', '.') : '—' }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-xs border-t border-orisa-earth/5 pt-2">
                            <span class="font-medium text-ebony/70">🍂 Q2 (Abr - Jun / Outono)</span>
                            <span class="font-bold text-ebony">
                                {{ $history['quarters']['Q2'] ? 'R$ '.number_format($history['quarters']['Q2'], 2, ',', '.') : '—' }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-xs border-t border-orisa-earth/5 pt-2">
                            <span class="font-medium text-ebony/70">❄️ Q3 (Jul - Set / Inverno)</span>
                            <span class="font-bold text-ebony">
                                {{ $history['quarters']['Q3'] ? 'R$ '.number_format($history['quarters']['Q3'], 2, ',', '.') : '—' }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-xs border-t border-orisa-earth/5 pt-2">
                            <span class="font-medium text-ebony/70">🌺 Q4 (Out - Dez / Primavera)</span>
                            <span class="font-bold text-ebony">
                                {{ $history['quarters']['Q4'] ? 'R$ '.number_format($history['quarters']['Q4'], 2, ',', '.') : '—' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Tabela de precos historicos -->
                <div class="md:col-span-2">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-orisa-earth mb-3">Linha do Tempo de Preços</h3>
                    
                    <div class="overflow-x-auto rounded-xl border border-orisa-earth/15 bg-white">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-sacred-sand">
                                <tr>
                                    <th class="px-3 py-2.5">Data Compra</th>
                                    <th class="px-3 py-2.5">Fornecedor</th>
                                    <th class="px-3 py-2.5 text-right">Valor Unitário</th>
                                    <th class="px-3 py-2.5 text-right">Oscilação</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-orisa-earth/10">
                                @forelse ($history['rows'] as $row)
                                    <tr>
                                        <td class="px-3 py-2">{{ $row['date']->format('d/m/Y') }}</td>
                                        <td class="px-3 py-2">{{ $row['supplier'] }}</td>
                                        <td class="px-3 py-2 text-right font-medium">R$ {{ number_format($row['price'], 2, ',', '.') }}</td>
                                        <td class="px-3 py-2 text-right">
                                            @if ($row['change_percent'] === null)
                                                <span class="text-ebony/40">—</span>
                                            @elseif ($row['change_percent'] > 0)
                                                <span class="text-danger font-semibold">+{{ $row['change_percent'] }}% 📈</span>
                                            @elseif ($row['change_percent'] < 0)
                                                <span class="text-success font-semibold">{{ $row['change_percent'] }}% 📉</span>
                                            @else
                                                <span class="text-ebony/50">Estável</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-3 py-6 text-center text-ebony/50">Nenhum registro de preço encontrado para o item.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <!-- Alertas de Variacao -->
    <div class="mt-8 rounded-lg border border-orisa-earth/20 bg-white p-4 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h3 class="text-sm font-semibold text-ifa-green">Monitoramento Automático de Alertas</h3>
            <p class="text-xs text-ebony/60">Destaca itens em vermelho que sofreram reajustes elevados na última compra.</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-xs font-semibold text-ebony/70">Alertar variação acima de:</span>
            <input type="number" wire:model.live="alertThreshold" class="w-16 rounded border border-orisa-earth/30 px-2 py-1 text-xs">
            <span class="text-xs font-bold">%</span>
        </div>
    </div>

    <!-- Tabelas comparativas gerais -->
    <div class="mt-6 grid gap-6 md:grid-cols-2">
        <div>
            <h2 class="font-display text-lg text-ifa-green">Variação Recente: Insumos</h2>
            <div class="mt-2 overflow-x-auto rounded border border-orisa-earth/20 bg-white">
                <table class="w-full text-left text-sm">
                    <thead class="bg-sacred-sand">
                        <tr>
                            <th class="px-3 py-2">Item</th>
                            <th class="px-3 py-2">Anterior</th>
                            <th class="px-3 py-2">Atual</th>
                            <th class="px-3 py-2">Variação</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($supplyRows as $row)
                            <tr class="border-t border-orisa-earth/10 {{ abs($row['change_percent']) >= $alertThreshold ? 'bg-danger/10' : '' }}">
                                <td class="px-3 py-2">{{ $row['name'] }}</td>
                                <td class="px-3 py-2">R$ {{ number_format($row['previous_price'], 2, ',', '.') }}</td>
                                <td class="px-3 py-2">R$ {{ number_format($row['latest_price'], 2, ',', '.') }}</td>
                                <td class="px-3 py-2 {{ $row['change_percent'] > 0 ? 'text-danger' : 'text-success' }}">
                                    {{ $row['change_percent'] > 0 ? '+' : '' }}{{ $row['change_percent'] }}%
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-3 py-4 text-center text-xs text-ebony/50">Não há dados históricos suficientes para calcular variação.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <h2 class="font-display text-lg text-ifa-green">Variação Recente: Animais</h2>
            <div class="mt-2 overflow-x-auto rounded border border-orisa-earth/20 bg-white">
                <table class="w-full text-left text-sm">
                    <thead class="bg-sacred-sand">
                        <tr>
                            <th class="px-3 py-2">Item</th>
                            <th class="px-3 py-2">Anterior</th>
                            <th class="px-3 py-2">Atual</th>
                            <th class="px-3 py-2">Variação</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($animalRows as $row)
                            <tr class="border-t border-orisa-earth/10 {{ abs($row['change_percent']) >= $alertThreshold ? 'bg-danger/10' : '' }}">
                                <td class="px-3 py-2">{{ $row['name'] }}</td>
                                <td class="px-3 py-2">R$ {{ number_format($row['previous_price'], 2, ',', '.') }}</td>
                                <td class="px-3 py-2">R$ {{ number_format($row['latest_price'], 2, ',', '.') }}</td>
                                <td class="px-3 py-2 {{ $row['change_percent'] > 0 ? 'text-danger' : 'text-success' }}">
                                    {{ $row['change_percent'] > 0 ? '+' : '' }}{{ $row['change_percent'] }}%
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-3 py-4 text-center text-xs text-ebony/50">Não há dados históricos suficientes para calcular variação.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
