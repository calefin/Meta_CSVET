<div>
    <h1 class="font-display text-2xl text-ifa-green">Log de auditoria</h1>

    <input type="text" wire:model.live.debounce.300ms="actionFilter" placeholder="Filtrar por ação (ex: login, consultation)..."
        class="mt-4 w-full max-w-sm rounded border border-orisa-earth/30 px-3 py-2 text-sm">

    <div class="mt-4 overflow-x-auto rounded border border-orisa-earth/20 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-sacred-sand">
                <tr>
                    <th class="px-4 py-2">Data/hora</th>
                    <th class="px-4 py-2">Usuário</th>
                    <th class="px-4 py-2">Ação</th>
                    <th class="px-4 py-2">Modelo</th>
                    <th class="px-4 py-2">IP</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($logs as $log)
                    <tr class="border-t border-orisa-earth/10" wire:key="log-{{ $log->id }}">
                        <td class="px-4 py-2 font-mono text-xs">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                        <td class="px-4 py-2" lang="yo">{{ $log->user?->name ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $log->action }}</td>
                        <td class="px-4 py-2 text-xs text-ebony/60">{{ $log->model ? class_basename($log->model).' #'.$log->model_id : '' }}</td>
                        <td class="px-4 py-2 font-mono text-xs">{{ $log->ip_address }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
</div>
