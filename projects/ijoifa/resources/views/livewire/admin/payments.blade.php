<div>
    @if (session()->has('error'))
        <div class="mb-4 rounded-lg bg-danger/10 border border-danger/30 p-4 text-danger text-sm">
            {{ session('error') }}
        </div>
    @endif

    @if (session()->has('success'))
        <div class="mb-4 rounded-lg bg-success/10 border border-success/30 p-4 text-success text-sm">
            {{ session('success') }}
        </div>
    @endif

    @unless (\App\Services\MercadoPagoService::configured())
        <div class="mb-4 rounded border border-orisa-earth/30 bg-orisa-earth/10 p-3 text-sm text-ebony">
            Mercado Pago não está configurado (falta <code>MERCADO_PAGO_ACCESS_TOKEN</code> no .env). Sem isso, nenhum link de pagamento é gerado — as cobranças ficam pendentes só com confirmação manual.
        </div>
    @endunless

    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-2xl text-ifa-green">Cobranças</h1>
        <button wire:click="openChargeForm" class="rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">
            Nova cobrança
        </button>
    </div>

    <div class="mt-4 flex flex-wrap gap-3">
        <select wire:model.live="statusFilter" class="rounded border border-orisa-earth/30 px-3 py-2 text-sm">
            <option value="">Todos os status</option>
            <option value="pending">Pendente</option>
            <option value="paid">Pago</option>
            <option value="failed">Falhou</option>
            <option value="refunded">Reembolsado</option>
        </select>
        <input type="text" wire:model.live.debounce.400ms="search" placeholder="Buscar por nome ou e-mail"
            class="flex-1 rounded border border-orisa-earth/30 px-3 py-2 text-sm">
    </div>

    <div class="mt-4 overflow-x-auto rounded border border-orisa-earth/20 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-sacred-sand">
                <tr>
                    <th class="px-4 py-2">Pessoa</th>
                    <th class="px-4 py-2">Referente a</th>
                    <th class="px-4 py-2">Valor</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2">Data</th>
                    <th class="px-4 py-2">Ações</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payments as $payment)
                    <tr class="border-t border-orisa-earth/10" wire:key="payment-{{ $payment->id }}">
                        <td class="px-4 py-2" lang="yo">{{ $payment->user->name }}</td>
                        <td class="px-4 py-2">{{ $payment->description() }}</td>
                        <td class="px-4 py-2">R$ {{ number_format($payment->amount, 2, ',', '.') }}</td>
                        <td class="px-4 py-2">
                            <span class="rounded px-2 py-0.5 text-xs {{ match($payment->status) {
                                'paid' => 'bg-success/10 text-success',
                                'pending' => 'bg-orun-blue/10 text-orun-blue',
                                'failed' => 'bg-danger/10 text-danger',
                                default => 'bg-orisa-earth/10 text-orisa-earth',
                            } }}">
                                {{ match($payment->status) {
                                    'paid' => 'Pago',
                                    'pending' => 'Pendente',
                                    'failed' => 'Falhou',
                                    'refunded' => 'Reembolsado',
                                    default => $payment->status,
                                } }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-xs text-ebony/60">
                            {{ $payment->paid_at?->format('d/m/Y') ?? $payment->created_at->format('d/m/Y') }}
                        </td>
                        <td class="px-4 py-2">
                            @if ($payment->status === 'pending')
                                <button wire:click="markPaid({{ $payment->id }})" wire:confirm="Confirmar que este pagamento foi recebido?" class="text-xs text-success hover:underline">marcar pago</button>
                                @if ($payment->checkout_url)
                                    <a href="{{ $payment->checkout_url }}" target="_blank" class="ml-2 text-xs text-orun-blue hover:underline">link de pagamento</a>
                                @endif
                                <button wire:click="resend({{ $payment->id }})" class="ml-2 text-xs text-ifa-green hover:underline">reenviar e-mail</button>
                                <button wire:click="cancel({{ $payment->id }})" wire:confirm="Cancelar esta cobrança?" class="ml-2 text-xs text-danger hover:underline">cancelar</button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $payments->links() }}</div>

    @if ($showChargeForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-lg bg-white p-6">
                <h2 class="font-display text-xl text-ifa-green">Nova cobrança</h2>
                <p class="mt-1 text-xs text-ebony/50">Use para cobranças que não são de uma consulta, trabalho ou curso — ex.: uma taxa, um material, uma colaboração combinada.</p>

                <form wire:submit="createCharge" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium">Pessoa</label>
                        <select wire:model="chargeUserId" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                            <option value="">— Selecione —</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" lang="yo">{{ $user->name }} ({{ $user->role }})</option>
                            @endforeach
                        </select>
                        @error('chargeUserId') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                        <p class="mt-1 text-xs text-ebony/50">
                            Não encontrou a pessoa? Esta pessoa precisa ter um cadastro no site antes de receber uma cobrança.
                            Cadastre-a em <a href="{{ route('admin.users') }}" target="_blank" class="text-ifa-green hover:underline">Usuários &rarr; Novo usuário</a>, ou peça que ela se cadastre pelo site.
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Descrição</label>
                        <input type="text" wire:model="chargeDescription" placeholder="Ex: Taxa de ose" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                        @error('chargeDescription') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Valor (R$)</label>
                        <input type="number" step="0.01" wire:model="chargeAmount" class="mt-1 w-full max-w-[10rem] rounded border border-orisa-earth/30 px-3 py-2">
                        @error('chargeAmount') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showChargeForm', false)" class="rounded px-4 py-2 text-ebony/70 hover:bg-sacred-sand">Cancelar</button>
                        <button type="submit" class="rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">Criar e enviar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
