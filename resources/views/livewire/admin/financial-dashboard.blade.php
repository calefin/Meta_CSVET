<div>
    <h1 class="font-display text-2xl text-ifa-green">Financeiro</h1>

    <div class="mt-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-sm font-medium">Visão</label>
            <div class="mt-1 flex rounded border border-orisa-earth/30 text-sm">
                <button type="button" wire:click="$set('periodView', 'monthly')" class="px-3 py-2 rounded-l {{ $periodView === 'monthly' ? 'bg-ifa-green text-white' : 'bg-white text-ebony/70 hover:bg-sacred-sand' }}">Mensal</button>
                <button type="button" wire:click="$set('periodView', 'yearly')" class="px-3 py-2 rounded-r {{ $periodView === 'yearly' ? 'bg-ifa-green text-white' : 'bg-white text-ebony/70 hover:bg-sacred-sand' }}">Anual</button>
            </div>
        </div>

        @if ($periodView === 'monthly')
            <div class="flex items-end gap-1">
                <button type="button" wire:click="previousMonth" class="rounded border border-orisa-earth/30 px-2 py-2 text-sm hover:bg-sacred-sand">&larr;</button>
                <div>
                    <label class="block text-sm font-medium">Mês</label>
                    <select wire:model.live="selectedMonth" class="mt-1 rounded border border-orisa-earth/30 px-3 py-2 text-sm">
                        @for ($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}">{{ \Illuminate\Support\Carbon::create()->setMonth($m)->translatedFormat('F') }}</option>
                        @endfor
                    </select>
                </div>
                <button type="button" wire:click="nextMonth" class="rounded border border-orisa-earth/30 px-2 py-2 text-sm hover:bg-sacred-sand">&rarr;</button>
            </div>
        @endif

        <div>
            <label class="block text-sm font-medium">Ano</label>
            <select wire:model.live="selectedYear" class="mt-1 rounded border border-orisa-earth/30 px-3 py-2 text-sm">
                @for ($y = now()->year; $y >= now()->year - 5; $y--)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endfor
            </select>
        </div>

        <details class="text-sm">
            <summary class="cursor-pointer select-none text-xs text-ifa-green hover:underline">período personalizado</summary>
            <div class="mt-2 flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs font-medium">De</label>
                    <input type="date" wire:model.live="startDate" class="mt-1 rounded border border-orisa-earth/30 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium">Até</label>
                    <input type="date" wire:model.live="endDate" class="mt-1 rounded border border-orisa-earth/30 px-3 py-2 text-sm">
                </div>
            </div>
        </details>

        <button wire:click="openExpenseForm" class="rounded bg-ifa-green px-4 py-2 text-sm text-white hover:bg-ifa-green/90">
            Nova despesa
        </button>
        <a href="{{ route('admin.finance.prices') }}" class="rounded border border-orisa-earth px-4 py-2 text-sm text-orisa-earth hover:bg-sacred-sand">
            Monitoramento de precos
        </a>
    </div>

    <div class="mt-4 grid gap-4 md:grid-cols-4">
        <div class="rounded-lg border border-success/30 bg-success/5 p-4">
            <p class="text-xs uppercase text-ebony/50">Receitas</p>
            <p class="font-display text-xl text-success">R$ {{ number_format($totalRevenue, 2, ',', '.') }}</p>
        </div>
        <div class="rounded-lg border border-danger/30 bg-danger/5 p-4">
            <p class="text-xs uppercase text-ebony/50">Despesas</p>
            <p class="font-display text-xl text-danger">R$ {{ number_format($totalExpenses, 2, ',', '.') }}</p>
        </div>
        <div class="rounded-lg border border-orisa-earth/20 bg-white p-4">
            <p class="text-xs uppercase text-ebony/50">Taxas Mercado Pago</p>
            <p class="font-display text-xl text-ebony/70">R$ {{ number_format($totalMpFees, 2, ',', '.') }}</p>
        </div>
        <div class="rounded-lg border border-orisa-earth/20 bg-white p-4">
            <p class="text-xs uppercase text-ebony/50">Saldo</p>
            <p class="font-display text-xl {{ $net >= 0 ? 'text-success' : 'text-danger' }}">R$ {{ number_format($net, 2, ',', '.') }}</p>
        </div>
    </div>

    <div
        wire:key="chart-{{ implode('-', $chartRevenue) }}-{{ implode('-', $chartExpenses) }}"
        wire:ignore
        x-data
        x-init="window.renderFinanceChart($refs.financeChart, @js($chartLabels), @js($chartRevenue), @js($chartExpenses))"
        class="mt-6 rounded-lg border border-orisa-earth/20 bg-white p-4"
    >
        <h2 class="mb-3 font-medium">Receitas x Despesas (últimos 6 meses)</h2>
        <canvas x-ref="financeChart" height="90"></canvas>
    </div>

    <div class="mt-6 grid gap-4 md:grid-cols-2">
        <div class="rounded-lg border border-orisa-earth/20 bg-white p-4">
            <h2 class="font-medium">Receitas no periodo</h2>
            <ul class="mt-2 max-h-72 space-y-1 overflow-y-auto text-sm">
                @forelse ($revenues as $payment)
                    <li class="border-b border-orisa-earth/10 py-1">
                        <div class="flex items-center justify-between">
                            <span lang="yo">{{ $payment->user->name }} — {{ class_basename($payment->payable_type) }}</span>
                            <span class="text-success">R$ {{ number_format($payment->amount, 2, ',', '.') }}</span>
                        </div>
                        @if ($payment->mp_payment_type)
                            <div class="mt-0.5 flex items-center justify-between text-xs text-ebony/50">
                                <span>{{ $payment->paymentMethodLabel() }}</span>
                                @if ($payment->mp_fee_amount)
                                    <span>taxa MP: R$ {{ number_format($payment->mp_fee_amount, 2, ',', '.') }}</span>
                                @endif
                            </div>
                        @endif
                    </li>
                @empty
                    <p class="text-ebony/50">Nenhuma receita no periodo.</p>
                @endforelse
            </ul>
        </div>

        <div class="rounded-lg border border-orisa-earth/20 bg-white p-4">
            <h2 class="font-medium">Despesas no periodo</h2>

            @if ($expensesByCategory->isNotEmpty())
                <ul class="mt-2 space-y-1 text-xs border-b border-orisa-earth/10 pb-2 mb-2">
                    @foreach ($expensesByCategory as $category => $amount)
                        <li class="flex items-center justify-between text-ebony/70">
                            <span>{{ $category }}</span>
                            <span class="font-mono">R$ {{ number_format($amount, 2, ',', '.') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif

            <ul class="max-h-72 space-y-1 overflow-y-auto text-sm">
                @forelse ($expenses as $expense)
                    <li class="flex items-center justify-between border-b border-orisa-earth/10 py-1">
                        <span>{{ $expense->category }} — {{ $expense->description }}</span>
                        <span class="flex items-center gap-2">
                            <span class="text-danger">R$ {{ number_format($expense->amount, 2, ',', '.') }}</span>
                            <button wire:click="deleteExpense({{ $expense->id }})" class="text-xs text-ebony/40 hover:text-danger">remover</button>
                        </span>
                    </li>
                @empty
                    <p class="text-ebony/50">Nenhuma despesa no periodo.</p>
                @endforelse
            </ul>
        </div>
    </div>

    @if ($showExpenseForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-lg bg-white p-6">
                <h2 class="font-display text-xl text-ifa-green">Nova despesa</h2>

                <form wire:submit="saveExpense" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium">Categoria</label>
                        <input type="text" wire:model="expenseCategory" list="expense-categories" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                        <datalist id="expense-categories">
                            <option value="Animais">
                            <option value="Insumos">
                            <option value="Aluguel">
                            <option value="Manutencao">
                            <option value="Materiais">
                            <option value="Outros">
                        </datalist>
                        @error('expenseCategory') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Descrição</label>
                        <input type="text" wire:model="expenseDescription" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                        @error('expenseDescription') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-sm font-medium">Valor (R$)</label>
                            <input type="number" step="0.01" wire:model="expenseAmount" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                            @error('expenseAmount') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium">Data</label>
                            <input type="date" wire:model="expenseDate" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showExpenseForm', false)" class="rounded px-4 py-2 text-ebony/70 hover:bg-sacred-sand">Cancelar</button>
                        <button type="submit" class="rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
