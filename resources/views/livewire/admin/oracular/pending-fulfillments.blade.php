<div>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-2xl text-ifa-green">Atendimentos Pendentes</h1>
        <a href="{{ route('admin.oracular.works') }}" class="text-sm text-ifa-green hover:underline">&larr; Ver todos os trabalhos</a>
    </div>
    <p class="mt-1 text-sm text-ebony/60">Já pagos, aguardando você realizar o atendimento.</p>

    <div class="mt-4 overflow-x-auto rounded border border-orisa-earth/20 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-sacred-sand">
                <tr>
                    <th class="px-4 py-2">Nome</th>
                    <th class="px-4 py-2">Telefone</th>
                    <th class="px-4 py-2">Solicitação</th>
                    <th class="px-4 py-2">Pago em</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr class="border-t border-orisa-earth/10" wire:key="pend-{{ $item['source'] }}-{{ $item['id'] }}">
                        <td class="px-4 py-2" lang="yo">{{ $item['name'] }}</td>
                        <td class="px-4 py-2">
                            @if ($item['phone'])
                                <a href="https://wa.me/{{ preg_replace('/\D/', '', $item['phone']) }}" target="_blank" class="text-orun-blue hover:underline">{{ $item['phone'] }}</a>
                            @else
                                <span class="text-ebony/40">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 max-w-sm">{{ $item['request'] ?: '—' }}</td>
                        <td class="px-4 py-2 text-xs text-ebony/60">{{ $item['paid_at']->format('d/m/Y') }}</td>
                        <td class="px-4 py-2">
                            @if ($item['source'] === 'consultation')
                                <a href="{{ route('admin.oracular.show', $item['id']) }}" class="text-xs text-ifa-green hover:underline">atender</a>
                            @elseif ($item['source'] === 'recommended_work')
                                <button wire:click="markRecommendedWorkDone({{ $item['id'] }})" wire:confirm="Marcar este trabalho como concluído?" class="text-xs text-success hover:underline">marcar como feito</button>
                            @else
                                <button wire:click="markQuickPaymentDone({{ $item['id'] }})" wire:confirm="Marcar este atendimento como concluído?" class="text-xs text-success hover:underline">marcar como feito</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-sm text-ebony/50">Nenhum atendimento pendente. Tudo em dia!</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
