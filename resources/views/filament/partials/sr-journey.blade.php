{{-- Onde o projeto está na esteira (App\Support\ProgramJourney). Estilos inline/próprios: o portal não compila Tailwind novo.
     Recebe $journey (ProgramJourney::for) e, opcional, $you (o que o aluno faz agora). --}}
@once
    <style>
        .sj-bar { display: grid; grid-template-columns: repeat({{ $journey['total'] }}, 1fr); gap: 3px; margin-top: .45rem; }
        .sj-seg { height: .5rem; border-radius: 999px; background: rgba(148,163,184,.35); }
        .sj-seg.done { background: #16a34a; }
        .sj-seg.current { background: #2563eb; box-shadow: 0 0 0 2px rgba(37,99,235,.3); }
        .sj-labels { display: grid; grid-template-columns: repeat({{ $journey['total'] }}, 1fr); gap: 3px; margin-top: .25rem; font-size: .62rem; line-height: 1.2; opacity: .75; }
        .sj-labels span { overflow-wrap: anywhere; }
        .sj-labels .current { font-weight: 700; opacity: 1; color: #2563eb; }
        .sj-labels .done { color: #15803d; }
        .dark .sj-labels .done { color: #4ade80; }
        .dark .sj-labels .current { color: #60a5fa; }
        .sj-now { font-size: .82rem; margin-top: .45rem; line-height: 1.45; }
        .sj-search { display: inline-block; margin-top: .3rem; padding: .1rem .5rem; border-radius: 999px; font-size: .72rem; font-weight: 600; }
        .sj-search.ok, .sj-search.done { background: rgba(22,163,74,.12); color: #15803d; }
        .sj-search.wait { background: rgba(100,116,139,.15); color: #475569; }
        .sj-search.warn { background: #fef3c7; color: #92400e; }
        .sj-search.urgent, .sj-search.late { background: #fee2e2; color: #b91c1c; }
        @media (max-width: 640px) { .sj-labels { display: none; } }
    </style>
@endonce
@php $current = $journey['current'] ? $journey['steps'][$journey['current']] : null; @endphp
<div class="sj">
    <div style="font-size:.78rem;font-weight:700">
        @if ($current)
            Etapa {{ $journey['position'] }} de {{ $journey['total'] }}: <span style="color:#2563eb">{{ $current['label'] }}</span>
            @if (filled($current['window']))<span style="font-weight:500;opacity:.75">({{ $current['window'] }})</span>@endif
        @else
            ✅ Todas as etapas concluídas
        @endif
    </div>
    <div class="sj-bar" role="img" aria-label="Etapa {{ $journey['position'] }} de {{ $journey['total'] }}">
        @foreach ($journey['steps'] as $step)
            <div class="sj-seg {{ $step['state'] }}" title="{{ $step['label'] }}{{ filled($step['window']) ? ' · ' . $step['window'] : '' }}"></div>
        @endforeach
    </div>
    <div class="sj-labels">
        @foreach ($journey['steps'] as $step)
            <span class="{{ $step['state'] }}">{{ $step['state'] === 'done' ? '✓ ' : '' }}{{ $step['label'] }}</span>
        @endforeach
    </div>
    @unless ($compact ?? false)
    <div class="sj-now"><strong>Agora:</strong> {{ $journey['now'] }}
        {{-- "Vencida" é assunto do professor (desvio do protocolo): o aluno não vê. --}}
        @if (in_array($journey['current'], ['registro', 'busca'], true) && $journey['search'] && ($journey['search']['level'] !== 'late' || ($professor ?? false)))
            <br><span class="sj-search {{ $journey['search']['level'] }}">{{ $journey['search']['text'] }}</span>
        @endif
    </div>
    @if (filled($you ?? null))
        <div class="sj-now"><strong>Você:</strong> {{ $you }}</div>
    @endif
    @endunless
</div>
