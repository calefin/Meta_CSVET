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

    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-2xl text-ifa-green">Trabalhos</h1>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.oracular.pending-fulfillments') }}" class="text-sm text-orisa-earth hover:underline">Atendimentos pendentes &rarr;</a>
            <a href="{{ route('admin.oracular.new-work') }}" class="rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">
                Novo trabalho
            </a>
        </div>
    </div>

    <div class="mt-4 flex flex-wrap gap-3">
        <select wire:model.live="statusFilter" class="rounded border border-orisa-earth/30 px-3 py-2 text-sm">
            <option value="">Todos os status</option>
            <option value="pending">Pendente</option>
            <option value="paid">Pago</option>
            <option value="cancelled">Cancelado</option>
        </select>
        <input type="text" wire:model.live.debounce.400ms="search" placeholder="Buscar por nome do cliente"
            class="flex-1 rounded border border-orisa-earth/30 px-3 py-2 text-sm">
    </div>

    <div class="mt-4 overflow-x-auto rounded border border-orisa-earth/20 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-sacred-sand">
                <tr>
                    <th class="px-4 py-2">Cliente</th>
                    <th class="px-4 py-2">Tipo</th>
                    <th class="px-4 py-2">Valor</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2">Data</th>
                    <th class="px-4 py-2">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr class="border-t border-orisa-earth/10" wire:key="work-{{ $item['source'] }}-{{ $item['id'] }}">
                        <td class="px-4 py-2" lang="yo">
                            @if ($item['client'])
                                <a href="{{ route('admin.clients.show', $item['client']) }}" class="text-ifa-green hover:underline">{{ $item['client']->name }}</a>
                            @else
                                <span class="text-ebony/40">sem cadastro ainda</span>
                            @endif
                        </td>
                        <td class="px-4 py-2">{{ $item['tipo'] }}</td>
                        <td class="px-4 py-2">{{ $item['valor'] !== null ? 'R$ '.number_format($item['valor'], 2, ',', '.') : '—' }}</td>
                        <td class="px-4 py-2">
                            <span class="rounded px-2 py-0.5 text-xs {{ match($item['status_group']) {
                                'paid' => 'bg-success/10 text-success',
                                'pending' => 'bg-orun-blue/10 text-orun-blue',
                                'cancelled' => 'bg-danger/10 text-danger',
                                default => 'bg-orisa-earth/10 text-orisa-earth',
                            } }}">
                                {{ $item['status_label'] }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-xs text-ebony/60">{{ $item['data']->format('d/m/Y') }}</td>
                        <td class="px-4 py-2">
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($item['link'])
                                    <a href="{{ $item['link'] }}" class="text-xs text-orun-blue hover:underline">abrir</a>
                                @endif
                                @if ($item['checkout_url'])
                                    <button type="button" onclick="navigator.clipboard.writeText('{{ $item['checkout_url'] }}'); alert('Link copiado!')" class="text-xs text-orun-blue hover:underline">copiar link</button>
                                @endif
                                <a href="{{ route('admin.oracular.new-work', ['duplicate_source' => $item['source'], 'duplicate_id' => $item['id']]) }}" class="text-xs text-orisa-earth hover:underline">duplicar</a>
                                @if ($item['status_group'] === 'pending')
                                    <button wire:click="markPaid('{{ $item['source'] }}', {{ $item['id'] }})" wire:confirm="Confirmar que este pagamento foi recebido?" class="text-xs text-success hover:underline">marcar pago</button>
                                    <button wire:click="resend('{{ $item['source'] }}', {{ $item['id'] }})" class="text-xs text-ifa-green hover:underline">reenviar</button>
                                    <button wire:click="cancel('{{ $item['source'] }}', {{ $item['id'] }})" wire:confirm="Cancelar este trabalho?" class="text-xs text-danger hover:underline">cancelar</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-sm text-ebony/50">Nenhum trabalho encontrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $items->links() }}</div>
</div>
