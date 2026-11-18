@php
    /** @var array{rows: array, importCount: int, updateCount: int, errorCount: int} $analysis */
@endphp

<div class="space-y-3">
    <div class="flex flex-wrap gap-3 text-sm font-medium">
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400">
            {{ $analysis['importCount'] }} novo(s)
        </span>
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-info-50 text-info-700 dark:bg-info-500/10 dark:text-info-400">
            {{ $analysis['updateCount'] }} será(ão) atualizado(s)
        </span>
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-danger-50 text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">
            {{ $analysis['errorCount'] }} com erro (não será(ão) importado(s))
        </span>
    </div>

    @if(empty($analysis['rows']))
        <p class="text-sm text-gray-500 dark:text-gray-400">Nenhuma linha reconhecida no arquivo. Confira se as colunas estão na ordem Nome, RGM, E-mail.</p>
    @else
        <div class="overflow-x-auto border rounded-lg border-gray-200 dark:border-white/10 max-h-72 overflow-y-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-gray-50 dark:bg-white/5 sticky top-0">
                    <tr>
                        <th class="px-3 py-2 font-semibold">Linha</th>
                        <th class="px-3 py-2 font-semibold">Nome</th>
                        <th class="px-3 py-2 font-semibold">RGM</th>
                        <th class="px-3 py-2 font-semibold">E-mail</th>
                        <th class="px-3 py-2 font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($analysis['rows'] as $row)
                        <tr class="border-t border-gray-100 dark:border-white/5">
                            <td class="px-3 py-1.5 text-gray-400">{{ $row['line'] }}</td>
                            <td class="px-3 py-1.5">{{ $row['name'] ?: '—' }}</td>
                            <td class="px-3 py-1.5">{{ $row['rgm'] ?: '—' }}</td>
                            <td class="px-3 py-1.5">{{ $row['email'] ?: '—' }}</td>
                            <td class="px-3 py-1.5">
                                @if($row['status'] === 'new')
                                    <span class="text-success-600 dark:text-success-400 font-medium">Novo</span>
                                @elseif($row['status'] === 'update')
                                    <span class="text-info-600 dark:text-info-400 font-medium">Atualiza</span>
                                @else
                                    <span class="text-danger-600 dark:text-danger-400 font-medium">{{ $row['reason'] }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
