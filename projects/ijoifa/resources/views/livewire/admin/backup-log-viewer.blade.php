<div>
    <h1 class="font-display text-2xl text-ifa-green">Log de backups</h1>

    <div class="mt-4 overflow-x-auto rounded border border-orisa-earth/20 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-sacred-sand">
                <tr>
                    <th class="px-4 py-2">Data/hora</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2">Arquivo</th>
                    <th class="px-4 py-2">Tamanho</th>
                    <th class="px-4 py-2">Mensagem</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($logs as $log)
                    <tr class="border-t border-orisa-earth/10">
                        <td class="px-4 py-2 font-mono text-xs">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                        <td class="px-4 py-2">
                            <span class="{{ $log->status === 'success' ? 'text-success' : 'text-danger' }}">
                                {{ $log->status === 'success' ? 'Sucesso' : 'Falha' }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-xs">{{ $log->filename }}</td>
                        <td class="px-4 py-2 text-xs">{{ $log->size_bytes ? number_format($log->size_bytes / 1024 / 1024, 2).' MB' : '—' }}</td>
                        <td class="px-4 py-2 text-xs text-danger">{{ $log->message }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
</div>
