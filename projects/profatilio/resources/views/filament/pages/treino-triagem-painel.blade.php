<x-filament-panels::page>
    {{-- Tailwind não é compilado para páginas custom neste painel: todo o visual usa CSS próprio. --}}
    <style>
        .tt-card { background: #fff; border: 1px solid #e2e8f0; border-radius: .875rem; padding: 1rem 1.1rem; }
        .dark .tt-card { background: #1e293b; border-color: rgba(255,255,255,.08); color: #e2e8f0; }
        .tt-h { font-size: 1rem; font-weight: 700; margin: 1.25rem 0 .5rem; }
        .tt-muted { color: #64748b; font-size: .8rem; }
        .dark .tt-muted { color: #94a3b8; }
        .tt-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
        .tt-table th { text-align: left; font-size: .7rem; text-transform: uppercase; letter-spacing: .03em; color: #64748b; padding: .45rem .6rem; border-bottom: 1px solid #e2e8f0; white-space: nowrap; }
        .tt-table td { padding: .5rem .6rem; border-bottom: 1px solid rgba(148,163,184,.2); vertical-align: top; }
        .tt-wrap { overflow-x: auto; }
        .tt-badge { display: inline-block; padding: .1rem .5rem; border-radius: 999px; font-size: .72rem; font-weight: 700; background: rgba(148,163,184,.18); white-space: nowrap; }
        .tt-badge.open { background: rgba(37,99,235,.12); color: #1d4ed8; }
        .tt-badge.done { background: rgba(5,150,105,.14); color: #047857; }
        .tt-badge.prep { background: rgba(217,119,6,.15); color: #b45309; }
        .tt-btn { display: inline-flex; padding: .35rem .75rem; border-radius: .5rem; font-weight: 600; font-size: .8rem; border: 1px solid rgba(13,148,136,.55); color: #0d9488; background: transparent; cursor: pointer; white-space: nowrap; }
        .tt-btn.on { background: #0d9488; color: #fff; }
        .tt-pre { white-space: pre-line; font-size: .85rem; line-height: 1.5; }
    </style>

    <div class="tt-card">
        <p style="font-size:.9rem">Treino com 25 trabalhos fora dos projetos (selante de teto e mastite). Fica <strong>separado</strong> dos projetos reais: não entra na Central, nos alertas, no OSF nem nas exportações. No portal, os alunos o veem no menu próprio <strong>"Treino da triagem"</strong>, e a Triagem Cega continua só com o projeto real.</p>
        <p class="tt-muted" style="margin-top:.4rem">Passos: 1. Criar treinos (sem aviso) → 2. Liberar com prazo (envia e-mail) → fecha sozinho quando a dupla termina ou no prazo → alunos e o senhor veem os resultados → Arquivar quando não precisar mais.</p>
    </div>

    <div class="tt-h">Duplas</div>
    @if ($trainings->isEmpty())
        <div class="tt-card">Nenhum treino criado ainda. Use o botão <strong>1. Criar treinos</strong> no alto da página.@if ($archived) <span class="tt-muted">({{ $archived }} arquivados)</span>@endif</div>
    @else
        <div class="tt-card tt-wrap" style="padding:0">
            <table class="tt-table">
                <thead><tr><th>Treino</th><th>Situação</th><th>Fecha em</th><th>Progresso</th><th>κ da dupla</th><th></th></tr></thead>
                <tbody>
                    @foreach ($trainings as $t)
                        @php $r = $t['review']; $k = $t['kappa']; @endphp
                        <tr>
                            <td style="font-weight:700;white-space:nowrap">{{ $r->short_name }}</td>
                            <td>
                                @if ($r->status === \App\Models\SystematicReview::STATUS_PROTOCOL)<span class="tt-badge prep">não liberado</span>
                                @elseif ($r->status === \App\Models\SystematicReview::STATUS_SCREENING)<span class="tt-badge open">aberto</span>
                                @else<span class="tt-badge done">encerrado · resultados liberados</span>@endif
                            </td>
                            <td style="white-space:nowrap">{{ $r->training_closes_at?->format('d/m H:i') ?? '—' }}</td>
                            <td>
                                @foreach ($t['progress'] as $p)
                                    <div>{{ $p['name'] }}: <strong>{{ $p['done'] }}</strong>/{{ $r->records_count }}</div>
                                @endforeach
                            </td>
                            <td style="white-space:nowrap">
                                {{ $k && $k['kappa'] !== null ? number_format($k['kappa'], 2, ',', '') : '—' }}
                                @if ($k && $k['n'] > 0)
                                    {{-- O kappa só usa os trabalhos que os dois já triaram: com poucos, oscila muito (até negativo). --}}
                                    <div class="tt-muted">{{ $r->status === \App\Models\SystematicReview::STATUS_SCREENING ? 'parcial · ' : '' }}{{ $k['n'] }} em comum</div>
                                @endif
                            </td>
                            <td><button type="button" class="tt-btn {{ $opened?->id === $r->id ? 'on' : '' }}" wire:click="toggle({{ $r->id }})">{{ $opened?->id === $r->id ? 'Fechar' : 'Ver resultados' }}</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="tt-muted" style="margin-top:.35rem">O senhor vê os resultados a qualquer momento. Os alunos só veem os da própria dupla, depois que o treino fecha.</p>
    @endif

    @if ($opened && $results)
        <div class="tt-h">Resultados · {{ $opened->short_name }}</div>
        <div class="tt-card">
            @include('filament.partials.training-results', ['results' => $results, 'meId' => null])
        </div>
    @endif

    @if ($overview->isNotEmpty())
        <div class="tt-h">Para a aula: os trabalhos que mais confundiram (todas as duplas)</div>
        <div class="tt-card tt-wrap" style="padding:0">
            <table class="tt-table">
                <thead><tr><th>Trabalho</th><th>Gabarito</th><th>Incluir</th><th>Talvez</th><th>Excluir</th><th>Acertos</th></tr></thead>
                <tbody>
                    @foreach ($overview as $o)
                        <tr>
                            <td><div style="font-weight:600">{{ \Illuminate\Support\Str::limit($o['title'], 120) }}</div><div class="tt-muted">PMID {{ $o['pmid'] }}</div></td>
                            <td>{{ $o['key']['label'] ?? '—' }} {{ $o['key']['reason'] ?? '' }}<div class="tt-muted">{{ $o['key']['why'] ?? '' }}</div></td>
                            <td>{{ $o['counts']['include'] }}</td>
                            <td>{{ $o['counts']['maybe'] }}</td>
                            <td>{{ $o['counts']['exclude'] }}</td>
                            <td style="white-space:nowrap">{{ $o['rate'] === null ? 'discussão' : round($o['rate'] * 100) . '%' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($releaseText)
        <div class="tt-h">E-mail que os alunos recebem ao liberar (exemplo com o 1º aluno)</div>
        <div class="tt-card tt-pre">{{ $releaseText }}</div>
    @endif
</x-filament-panels::page>
