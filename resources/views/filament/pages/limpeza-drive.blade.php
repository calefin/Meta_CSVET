<x-filament-panels::page>
    @php
        $scan = $this->getScan();
        $progress = $this->getProgress();
        $running = $progress && !($progress['finished'] ?? true);
        $labels = \App\Services\DriveCleanupService::CATEGORY_LABELS;
        $hints = [
            'trash'     => 'Versões anteriores que o sistema já descartou ao receber uma correção nova. Continuam ocupando a cota até a lixeira ser esvaziada.',
            'duplicate' => 'Arquivos com o mesmo nome na mesma pasta. Fica só o mais recente.',
            'orphan'    => 'Nenhum processo, orçamento ou registro do sistema aponta para eles (correções substituídas, envios abandonados, temporários).',
        ];
    @endphp

    <style>
        .ld-wrap { display: grid; gap: 1.25rem; }
        .ld-card { background: #fff; border: 1px solid #e5e7eb; border-radius: .9rem; padding: 1.1rem 1.25rem; }
        .dark .ld-card { background: #18181b; border-color: #3f3f46; }
        .ld-muted { color: #6b7280; font-size: .85rem; }
        .dark .ld-muted { color: #a1a1aa; }
        .ld-title { font-weight: 600; font-size: 1rem; margin-bottom: .35rem; }
        .ld-bar { height: .75rem; border-radius: 999px; background: #e5e7eb; overflow: hidden; display: flex; margin: .6rem 0 .4rem; }
        .dark .ld-bar { background: #3f3f46; }
        .ld-bar span { display: block; height: 100%; }
        .ld-bar .used { background: #2563eb; }
        .ld-bar .trash { background: #f59e0b; }
        .ld-grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); }
        .ld-big { font-size: 1.6rem; font-weight: 700; line-height: 1.2; }
        .ld-cat { display: flex; flex-direction: column; gap: .5rem; }
        .ld-cat .ld-actions { margin-top: auto; }
        .ld-table-wrap { overflow-x: auto; }
        .ld-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
        .ld-table th, .ld-table td { text-align: left; padding: .45rem .6rem; border-bottom: 1px solid #f1f5f9; white-space: nowrap; }
        .dark .ld-table th, .dark .ld-table td { border-color: #27272a; }
        .ld-table td.name { white-space: normal; word-break: break-word; min-width: 16rem; }
        .ld-pill { display: inline-block; padding: .1rem .5rem; border-radius: 999px; font-size: .72rem; font-weight: 600; }
        .ld-pill.trash { background: #fef3c7; color: #92400e; }
        .ld-pill.duplicate { background: #dbeafe; color: #1e40af; }
        .ld-pill.orphan { background: #fee2e2; color: #991b1b; }
        .ld-progress { border-left: 4px solid #2563eb; }
        .ld-empty { text-align: center; padding: 2.5rem 1rem; }
    </style>

    <div class="ld-wrap" @if ($running) wire:poll.10s @endif>
        @unless (\App\Services\DriveCleanupService::isAvailable())
            <div class="ld-card">O disco padrão não é o Google Drive — nada a limpar aqui.</div>
        @endunless

        @if ($progress)
            <div class="ld-card ld-progress">
                <div class="ld-title">{{ $running ? 'Exclusão em andamento…' : 'Última exclusão concluída' }}</div>
                <div class="ld-muted">
                    {{ $progress['deleted'] }} apagado(s) · {{ $this->human($progress['bytes']) }} liberados
                    @if ($progress['skipped']) · {{ $progress['skipped'] }} pulado(s) (vinculados ou já inexistentes) @endif
                    @if ($progress['failed']) · <strong>{{ $progress['failed'] }} falha(s)</strong> (ver log) @endif
                    @if ($running) · faltam {{ $progress['remaining'] }} — atualiza sozinho @endif
                </div>
            </div>
        @endif

        @if (!$scan)
            <div class="ld-card ld-empty">
                <div class="ld-title">Nenhuma análise recente</div>
                <div class="ld-muted">Clique em <strong>Analisar Drive agora</strong> para listar o que pode ser apagado. Nada é excluído sem confirmação.</div>
            </div>
        @else
            @if ($q = $scan['quota'])
                <div class="ld-card">
                    <div class="ld-title">Espaço do Google Drive</div>
                    @if ($q['limit'])
                        @php
                            $usedPct = min(100, ($q['usage'] - $q['usage_in_trash']) / $q['limit'] * 100);
                            $trashPct = min(100 - $usedPct, $q['usage_in_trash'] / $q['limit'] * 100);
                        @endphp
                        <div class="ld-bar"><span class="used" style="width: {{ $usedPct }}%"></span><span class="trash" style="width: {{ $trashPct }}%"></span></div>
                        <div class="ld-muted">{{ $this->human($q['usage']) }} de {{ $this->human($q['limit']) }} ({{ number_format($q['usage'] / $q['limit'] * 100, 0) }}%) · lixeira: {{ $this->human($q['usage_in_trash']) }} · a cota inclui Gmail e Fotos da conta</div>
                    @else
                        <div class="ld-muted">{{ $this->human($q['usage']) }} usados (cota ilimitada)</div>
                    @endif
                </div>
            @endif

            <div class="ld-grid">
                @foreach ($labels as $category => $label)
                    @php $total = $scan['totals'][$category] ?? ['count' => 0, 'bytes' => 0]; @endphp
                    <div class="ld-card ld-cat">
                        <span class="ld-pill {{ $category }}" style="align-self: flex-start">{{ $label }}</span>
                        <div class="ld-big">{{ $this->human($total['bytes']) }}</div>
                        <div class="ld-muted">{{ $total['count'] }} arquivo(s). {{ $hints[$category] }}</div>
                        <div class="ld-actions">
                            @if ($total['count'] > 0 && !$running)
                                {{ ($this->apagarAction)(['category' => $category])->label('Apagar ' . $total['count'] . ' arquivo(s)') }}
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="ld-card">
                <div class="ld-title">Arquivos encontrados</div>
                <div class="ld-muted" style="margin-bottom: .6rem">
                    Análise de {{ $scan['scanned_at'] }} · duplicatas e órfãos só entram com mais de {{ $scan['min_age_days'] }} dias ·
                    pastas: {{ implode(', ', \App\Services\DriveCleanupService::CLEANABLE_FOLDERS) }} (certificados e recibos nunca são tocados, exceto o que já está na lixeira)
                </div>
                @if (empty($scan['candidates']))
                    <div class="ld-empty ld-muted">Nada obsoleto encontrado. 🎉</div>
                @else
                    <div class="ld-table-wrap">
                        <table class="ld-table">
                            <thead><tr><th>Categoria</th><th>Arquivo</th><th>Tamanho</th><th>Data</th></tr></thead>
                            <tbody>
                                @foreach (collect($scan['candidates'])->sortByDesc('size')->take(300) as $c)
                                    <tr>
                                        <td><span class="ld-pill {{ $c['category'] }}">{{ $labels[$c['category']] }}</span></td>
                                        <td class="name" title="{{ $c['reason'] }}">{{ $c['path'] }}</td>
                                        <td>{{ $this->human($c['size']) }}</td>
                                        <td>{{ $c['modified'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if (count($scan['candidates']) > 300)
                        <div class="ld-muted" style="margin-top: .5rem">Mostrando os 300 maiores de {{ count($scan['candidates']) }}.</div>
                    @endif
                @endif
            </div>
        @endif
    </div>

</x-filament-panels::page>
