<x-filament-panels::page>
    {{-- Tailwind não é compilado para páginas custom neste painel: todo o visual usa CSS próprio. --}}
    <style>
        .cm-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: .75rem; }
        .cm-stat { background: #fff; border: 1px solid #e2e8f0; border-radius: .875rem; padding: .875rem 1rem; }
        .dark .cm-stat { background: #1e293b; border-color: rgba(255,255,255,.08); }
        .cm-stat b { display: block; font-size: 1.5rem; color: #0f172a; line-height: 1.2; }
        .dark .cm-stat b { color: #f1f5f9; }
        .cm-stat span { font-size: .75rem; color: #64748b; }
        .cm-stat.warn { border-color: #f59e0b; background: #fffbeb; }
        .dark .cm-stat.warn { background: rgba(245,158,11,.12); }

        .cm-h { font-size: 1rem; font-weight: 700; color: #0f172a; margin: 1.25rem 0 .5rem; }
        .dark .cm-h { color: #f1f5f9; }
        .cm-wrap { overflow-x: auto; border: 1px solid #e2e8f0; border-radius: .875rem; background: #fff; }
        .dark .cm-wrap { border-color: rgba(255,255,255,.08); background: #1e293b; }
        .cm-table { width: 100%; border-collapse: collapse; font-size: .8125rem; }
        .cm-table th, .cm-table td { padding: .55rem .75rem; text-align: left; vertical-align: top; }
        .cm-table thead th { background: #f1f5f9; color: #475569; font-weight: 600; font-size: .7rem; text-transform: uppercase; letter-spacing: .03em; border-bottom: 1px solid #e2e8f0; white-space: nowrap; }
        .dark .cm-table thead th { background: #0f172a; color: #94a3b8; border-color: #334155; }
        .cm-table tbody tr { border-bottom: 1px solid #f1f5f9; }
        .dark .cm-table tbody tr { border-color: rgba(255,255,255,.06); }
        .dark .cm-table td { color: #e2e8f0; }
        .cm-table tr.awaiting { background: #fffbeb; }
        .dark .cm-table tr.awaiting { background: rgba(245,158,11,.10); }
        .cm-code { font-weight: 700; color: #0f766e; white-space: nowrap; }
        .dark .cm-code { color: #5eead4; }
        .cm-muted { color: #64748b; font-size: .75rem; }
        .dark .cm-muted { color: #94a3b8; }
        .cm-ok { color: #15803d; font-weight: 600; white-space: nowrap; }
        .cm-pend { color: #b45309; font-weight: 600; white-space: nowrap; }
        .dark .cm-ok { color: #4ade80; } .dark .cm-pend { color: #fbbf24; }
        .cm-badge { display: inline-block; padding: .1rem .5rem; border-radius: 9999px; font-size: .68rem; font-weight: 600; background: #e0f2fe; color: #0369a1; white-space: nowrap; }
        .cm-badge.prof { background: #dcfce7; color: #166534; }
        .cm-badge.warn { background: #fef3c7; color: #92400e; }
        .dark .cm-badge { background: rgba(2,132,199,.18); color: #7dd3fc; }
        .dark .cm-badge.prof { background: rgba(22,163,74,.18); color: #86efac; }
        .dark .cm-badge.warn { background: rgba(245,158,11,.18); color: #fcd34d; }
        .cm-link { color: #0f766e; font-weight: 600; text-decoration: underline; white-space: nowrap; }
        .dark .cm-link { color: #5eead4; }

        .cm-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; margin: 1.25rem 0 .75rem; }
        .cm-tab { border: 1px solid #cbd5e1; background: #f8fafc; color: #334155; font-weight: 600; font-size: .8125rem; padding: .4rem .9rem; border-radius: .5rem; cursor: pointer; }
        .cm-tab.on { background: #0f766e; border-color: #0f766e; color: #fff; }
        .dark .cm-tab { background: #0f172a; border-color: #334155; color: #e2e8f0; }
        .dark .cm-tab.on { background: #0d9488; border-color: #0d9488; color: #fff; }
        .cm-toolbar select, .cm-reply select, .cm-reply textarea { padding: .45rem .6rem; font-size: .8125rem; border: 1px solid #cbd5e1; border-radius: .5rem; background: #f8fafc; color: #0f172a; }
        .dark .cm-toolbar select, .dark .cm-reply select, .dark .cm-reply textarea { background: #0f172a; border-color: #334155; color: #f1f5f9; }
        .cm-toolbar label { font-size: .8125rem; color: #475569; display: inline-flex; align-items: center; gap: .35rem; }
        .dark .cm-toolbar label { color: #cbd5e1; }

        .cm-reply { background: #fff; border: 1px solid #e2e8f0; border-radius: .875rem; padding: .875rem 1rem; margin-bottom: .75rem; display: flex; flex-direction: column; gap: .5rem; }
        .dark .cm-reply { background: #1e293b; border-color: rgba(255,255,255,.08); }
        .cm-reply-row { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
        .cm-reply textarea { width: 100%; min-height: 90px; }
        .cm-btn { background: #0f766e; color: #fff; font-weight: 600; font-size: .8125rem; border: 0; padding: .5rem 1rem; border-radius: .5rem; cursor: pointer; }
        .cm-btn-sm { border: 1px solid #cbd5e1; background: #f8fafc; color: #0f766e; font-weight: 600; font-size: .72rem; padding: .2rem .55rem; border-radius: .45rem; cursor: pointer; }
        .dark .cm-btn-sm { background: #0f172a; border-color: #334155; color: #5eead4; }
        .cm-notice { font-size: .75rem; color: #92400e; background: #fffbeb; border: 1px solid #fde68a; border-radius: .5rem; padding: .4rem .6rem; }
        .dark .cm-notice { color: #fcd34d; background: rgba(245,158,11,.10); border-color: rgba(245,158,11,.3); }

        .cm-feed { display: flex; flex-direction: column; gap: .5rem; }
        .cm-item { background: #fff; border: 1px solid #e2e8f0; border-radius: .75rem; padding: .7rem .9rem; }
        .dark .cm-item { background: #1e293b; border-color: rgba(255,255,255,.08); }
        .cm-item-head { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; margin-bottom: .3rem; }
        .cm-body { font-size: .85rem; color: #1e293b; white-space: pre-line; overflow-wrap: anywhere; }
        .dark .cm-body { color: #e2e8f0; }
        /* Esteira do projeto (App\Support\ProgramJourney): verde = feito, azul = agora, cinza = depois. */
        .cm-steps { display: flex; gap: 2px; margin-bottom: .3rem; }
        .cm-steps span { flex: 1; height: .45rem; border-radius: 999px; background: #e2e8f0; }
        .cm-steps span.done { background: #16a34a; }
        .cm-steps span.current { background: #2563eb; }
        .dark .cm-steps span { background: #334155; }
        .dark .cm-steps span.done { background: #22c55e; }
        .dark .cm-steps span.current { background: #3b82f6; }
        .cm-search { display: inline-block; margin-top: .2rem; padding: .1rem .45rem; border-radius: .5rem; font-size: .68rem; font-weight: 600; line-height: 1.35; }
        .cm-search.ok, .cm-search.done { background: #dcfce7; color: #166534; }
        .cm-search.wait { background: #f1f5f9; color: #475569; }
        .cm-search.warn { background: #fef3c7; color: #92400e; }
        .cm-search.urgent, .cm-search.late { background: #fee2e2; color: #b91c1c; }
        .dark .cm-search.ok, .dark .cm-search.done { background: rgba(22,163,74,.18); color: #86efac; }
        .dark .cm-search.wait { background: #0f172a; color: #94a3b8; }
        .dark .cm-search.warn { background: rgba(245,158,11,.18); color: #fcd34d; }
        .dark .cm-search.urgent, .dark .cm-search.late { background: rgba(220,38,38,.2); color: #fca5a5; }
        .cm-empty { padding: 2rem 1rem; text-align: center; color: #64748b; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: .875rem; }
        .dark .cm-empty { color: #94a3b8; background: #0f172a; border-color: #334155; }
    </style>

    <div class="cm-stats">
        <div class="cm-stat {{ $stats['awaiting'] ? 'warn' : '' }}"><b>{{ $stats['awaiting'] }}</b><span>projeto(s) aguardando mensagem</span></div>
        <div class="cm-stat"><b>{{ $stats['signed'] }}/{{ $stats['people'] }}</b><span>termos assinados (líder + revisor)</span></div>
        <div class="cm-stat"><b>{{ $stats['week'] }}</b><span>mensagens nos últimos 7 dias</span></div>
        <div class="cm-stat"><b>{{ $stats['frozen'] }}/{{ $stats['projects'] }}</b><span>protocolos congelados</span></div>
        <div class="cm-stat"><b>{{ $stats['osf'] }}/{{ $stats['projects'] }}</b><span>com link do OSF</span></div>
        <div class="cm-stat"><b>{{ $stats['registered'] }}/{{ $stats['projects'] }}</b><span>registrados (DOI gravado)</span></div>
        <div class="cm-stat {{ $stats['searchUrgent'] ? 'warn' : '' }}"><b>{{ $stats['searchUrgent'] }}</b><span>busca(s) com prazo de 3 dias ou menos (ou vencida)</span></div>
    </div>

    <div class="cm-h">Projetos</div>
    @if ($rows->isEmpty())
        <div class="cm-empty">Nenhum projeto importado ainda. Use Revisões Sistemáticas › "Importar protocolos (pipeline JSON)".</div>
    @else
        <div class="cm-wrap">
            <table class="cm-table">
                <thead>
                    <tr>
                        <th>Projeto</th><th>Etapa</th><th>Líder (1º autor)</th><th>Revisor(a) cego(a)</th>
                        <th>Protocolo</th><th>Conversa</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="{{ $row['awaiting'] ? 'awaiting' : '' }}">
                            <td><span class="cm-code">{{ $row['code'] }}</span><br><span class="cm-muted">{{ $row['protocol'] }}</span></td>
                            <td style="min-width:11rem;max-width:16rem">
                                @php $j = $row['journey']; @endphp
                                <div class="cm-steps" title="{{ collect($j['steps'])->map(fn ($s) => ($s['state'] === 'done' ? '✓ ' : '') . $s['label'])->implode(' → ') }}">
                                    @foreach ($j['steps'] as $s)<span class="{{ $s['state'] }}"></span>@endforeach
                                </div>
                                <strong>{{ $j['current'] ? $j['position'] . '. ' . $j['steps'][$j['current']]['label'] : '✅ concluído' }}</strong>
                                @if ($j['search'] && (in_array($j['current'], ['registro', 'busca'], true) || ($j['search']['level'] === 'done' && $j['current'] === 'calibracao')))
                                    <br><span class="cm-search {{ $j['search']['level'] }}">{{ $j['search']['text'] }}</span>
                                @endif
                                @if ($row['records'])<br><span class="cm-muted">{{ number_format($row['records'], 0, ',', '.') }} registros</span>@endif
                            </td>
                            @foreach (['lead', 'second'] as $who)
                                <td>
                                    @if ($p = $row[$who])
                                        {{ $p['name'] }}<br>
                                        @if ($p['signed'])
                                            <span class="cm-ok">✓ termo {{ $p['signed']->format('d/m') }}</span>
                                        @else
                                            <span class="cm-pend">⏳ termo pendente</span>
                                        @endif
                                        @if ($row['records'])
                                            <span class="cm-muted"> · {{ $p['decisions'] }} decisões</span>
                                        @endif
                                    @else
                                        <span class="cm-muted">—</span>
                                    @endif
                                </td>
                            @endforeach
                            <td>
                                @if ($row['frozen'])<span class="cm-ok">✓ congelado</span>@else<span class="cm-muted">não congelado</span>@endif<br>
                                @if ($row['osf'])<a class="cm-link" href="{{ $row['osf'] }}" target="_blank" rel="noopener">OSF ↗</a>@else<span class="cm-muted">sem OSF</span>@endif
                            </td>
                            <td>
                                @if ($row['awaiting'])<span class="cm-badge warn">💬 aguardando mensagem</span>
                                    <button type="button" class="cm-btn-sm" wire:click="markSeen({{ $row['id'] }})" title="Tira o aviso sem responder">✓ vista</button><br>@endif
                                <span class="cm-muted">{{ $row['messages'] }} msg</span>
                                @if ($row['last'])
                                    <br><span class="cm-muted">última: {{ $row['last']->author_name }}, {{ $row['last']->created_at->format('d/m H:i') }}</span>
                                @endif
                            </td>
                            <td>
                                <a class="cm-link" href="{{ $row['url'] }}">Abrir</a><br>
                                @if ($row['lead'] || $row['second'])
                                    <button type="button" class="cm-btn-sm" wire:click="replyTo({{ $row['id'] }})">Escrever</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="cm-toolbar">
        <button type="button" class="cm-tab {{ $tab === 'conversas' ? 'on' : '' }}" wire:click="setTab('conversas')">Conversas</button>
        <button type="button" class="cm-tab {{ $tab === 'atividades' ? 'on' : '' }}" wire:click="setTab('atividades')">Atividades</button>
        <button type="button" class="cm-tab {{ $tab === 'enviadas' ? 'on' : '' }}" wire:click="setTab('enviadas')">Enviadas por mim</button>
        @if ($tab !== 'enviadas')
            <select wire:model.live="project" aria-label="Filtrar por projeto">
                <option value="">Todos os projetos</option>
                @foreach ($projectOptions as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </select>
        @else
            <label><input type="checkbox" wire:model.live="showAutomatic"> incluir os avisos automáticos do site</label>
        @endif
        @if ($tab === 'conversas')
            <label><input type="checkbox" wire:model.live="onlyStudents"> só mensagens de alunos</label>
            <label><input type="checkbox" wire:model.live="onlyAwaiting"> só aguardando resposta ({{ count($awaitingIds) }})</label>
        @endif
    </div>

    @if ($tab === 'conversas')
        <div class="cm-reply" id="cm-reply">
            <div class="cm-reply-row">
                <strong style="font-size:.85rem">Escrever na conversa do projeto</strong>
                <select wire:model="replyReview" aria-label="Projeto">
                    <option value="">Escolha o projeto…</option>
                    @foreach ($chatOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <textarea wire:model="replyBody" maxlength="3000" placeholder="A mensagem vai para a dupla do projeto escolhido (aviso por e-mail) e fica registrada."></textarea>
            <div class="cm-reply-row">
                <button type="button" class="cm-btn" wire:click="sendReply" wire:loading.attr="disabled">Enviar à dupla</button>
                <span class="cm-notice">🔒 Não revele a um aluno as decisões, estudos ou dados do outro antes de encerrar a etapa.</span>
            </div>
        </div>

        @if ($messages->isEmpty())
            <div class="cm-empty">Nenhuma mensagem {{ $project !== '' ? 'neste projeto' : 'ainda' }}.</div>
        @else
            <div class="cm-feed">
                @foreach ($messages as $m)
                    <div class="cm-item">
                        <div class="cm-item-head">
                            <span class="cm-code">{{ \App\Filament\Pages\CentralMetanalises::codeOf($m->systematicReview) }}</span>
                            <span class="cm-badge {{ $m->author_role === \App\Models\SrMessage::ROLE_PROFESSOR ? 'prof' : '' }}">{{ $m->author_name }}</span>
                            <span class="cm-muted">{{ $m->created_at->format('d/m/Y H:i') }}</span>
                            <a class="cm-btn-sm" href="{{ \App\Filament\Resources\SystematicReviewResource::getUrl('view', ['record' => $m->systematic_review_id]) }}?activeRelationManager=2">Abrir projeto</a>
                            @if ($m->author_role === \App\Models\SrMessage::ROLE_STUDENT)
                                <button type="button" class="cm-btn-sm" wire:click="replyTo({{ $m->systematic_review_id }})">Responder</button>
                                @if (! $m->seen_at && in_array($m->systematic_review_id, $awaitingIds, true))
                                    <button type="button" class="cm-btn-sm" wire:click="markSeen({{ $m->systematic_review_id }})">Marcar como vista</button>
                                @endif
                            @endif
                        </div>
                        <div class="cm-body">{{ $m->body }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    @elseif ($tab === 'enviadas')
        @if ($sent->isEmpty())
            <div class="cm-empty">Nenhuma mensagem enviada aos alunos do programa.</div>
        @else
            <div class="cm-feed">
                @foreach ($sent as $s)
                    <div class="cm-item">
                        <div class="cm-item-head">
                            <span class="cm-badge {{ $s['type'] === 'chat' ? 'prof' : '' }}">{{ $s['type'] === 'chat' ? 'conversa' : 'e-mail' }}</span>
                            @unless ($s['manual'])<span class="cm-badge warn">automático</span>@endunless
                            <strong style="font-size:.85rem">{{ $s['subject'] }}</strong>
                            <span class="cm-muted">{{ $s['at']?->format('d/m/Y H:i') }}</span>
                        </div>
                        <div class="cm-muted" style="margin-bottom:.35rem">
                            Para {{ count($s['to']) }}: {{ \Illuminate\Support\Str::limit(implode(', ', $s['to']), 400) }}
                        </div>
                        @if ($s['type'] === 'chat')
                            <div class="cm-body">{{ $s['body'] }}</div>
                        @else
                            <details>
                                <summary class="cm-link" style="cursor:pointer">Ver a mensagem como o aluno recebeu{{ count($s['to']) > 1 ? ' (exemplo do 1º destinatário)' : '' }}</summary>
                                <iframe sandbox srcdoc="{{ $s['html'] }}" title="E-mail enviado" loading="lazy"
                                    style="width:100%;height:480px;border:1px solid #e2e8f0;border-radius:.5rem;margin-top:.5rem;background:#fff"></iframe>
                            </details>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    @else
        @if ($activity->isEmpty())
            <div class="cm-empty">Nenhuma atividade registrada.</div>
        @else
            <div class="cm-wrap">
                <table class="cm-table">
                    <thead><tr><th>Quando</th><th>Projeto</th><th>Atividade</th><th>Por</th></tr></thead>
                    <tbody>
                        @foreach ($activity as $h)
                            <tr>
                                <td style="white-space:nowrap">{{ $h['at']?->format('d/m/Y H:i') }}</td>
                                <td><span class="cm-code">{{ $h['code'] }}</span></td>
                                <td>{{ $h['event'] }}@if ($h['details'])<br><span class="cm-muted">{{ $h['details'] }}</span>@endif</td>
                                <td class="cm-muted">{{ $h['actor'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif

    @script
    <script>
        $wire.on('central-reply-focus', () => {
            const box = document.getElementById('cm-reply');
            if (box) { box.scrollIntoView({ behavior: 'smooth', block: 'center' }); box.querySelector('textarea')?.focus(); }
        });
    </script>
    @endscript
</x-filament-panels::page>
