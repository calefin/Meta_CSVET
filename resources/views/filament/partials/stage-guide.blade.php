{{-- Guia da etapa para o aluno + trilha do projeto. Estilo próprio (página custom do Filament). --}}
@php
    use App\Support\StageGuides;
    $g = StageGuides::student($key, $review);
    $refs = array_values(array_unique(array_merge($g['refs'], $extraRefs ?? [])));
    $roadmap = ($showRoadmap ?? true) ? StageGuides::roadmap($review) : [];
    $projectRefs = collect(data_get($review->pipeline, 'references', []))->pluck('citation')->filter()->values();
@endphp
<style>
    .sg { font-size: .875rem; line-height: 1.55; }
    .sg h3 { font-size: 1rem; font-weight: 700; margin: 0 0 .5rem; }
    .sg h4 { font-size: .8rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; margin: .9rem 0 .3rem; opacity: .8; }
    .sg ul { margin: 0; padding-left: 1.15rem; list-style: disc; }
    .sg li { margin: .15rem 0; }
    .sg-grid { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr)); margin-top: .5rem; }
    .sg-do { border: 1px solid rgba(5,150,105,.35); background: rgba(5,150,105,.07); border-radius: .6rem; padding: .6rem .8rem; }
    .sg-dont { border: 1px solid rgba(220,38,38,.35); background: rgba(220,38,38,.06); border-radius: .6rem; padding: .6rem .8rem; }
    .sg-why { border-left: 3px solid #0d9488; background: rgba(13,148,136,.06); padding: .5rem .8rem; border-radius: .4rem; }
    .sg a { color: #0d9488; text-decoration: underline; }
    .sg-road { list-style: none !important; padding: 0 !important; }
    .sg-road li { display: flex; gap: .6rem; align-items: flex-start; margin: .4rem 0; }
    .sg-dot { flex: 0 0 auto; width: 1.4rem; height: 1.4rem; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; font-size: .7rem; font-weight: 700; border: 1px solid rgba(148,163,184,.6); }
    .sg-dot.done { background: #0d9488; color: #fff; border-color: #0d9488; }
    .sg-dot.now { background: #f59e0b; color: #fff; border-color: #f59e0b; }
    .sg-muted { opacity: .65; }
</style>
<div class="sg">
    <h3>{{ $g['title'] }}</h3>

    <h4>O que você vai fazer</h4>
    <ul>@foreach ($g['what'] as $item)<li>{{ $item }}</li>@endforeach</ul>

    <h4>Por que isso importa</h4>
    <div class="sg-why">{{ $g['why'] }}</div>

    <div class="sg-grid">
        <div class="sg-do">
            <strong>✔ Faça (reduz viés)</strong>
            <ul style="margin-top:.3rem">@foreach ($g['do'] as $item)<li>{{ $item }}</li>@endforeach</ul>
        </div>
        <div class="sg-dont">
            <strong>✖ Não faça</strong>
            <ul style="margin-top:.3rem">@foreach ($g['dont'] as $item)<li>{{ $item }}</li>@endforeach</ul>
        </div>
    </div>

    <h4>Para estudar</h4>
    <ul>
        @foreach ($refs as $ref)
            @if (isset(StageGuides::REFS[$ref]))
                <li>{{ StageGuides::REFS[$ref][0] }} <a href="{{ StageGuides::REFS[$ref][1] }}" target="_blank" rel="noopener">abrir ↗</a></li>
            @endif
        @endforeach
    </ul>

    @if ($projectRefs->isNotEmpty() && ($showProjectRefs ?? false))
        <details style="margin-top:.6rem">
            <summary>Referências do protocolo deste projeto ({{ $projectRefs->count() }})</summary>
            <ol style="margin:.4rem 0 0 1.2rem;font-size:.8rem;list-style:decimal">
                @foreach ($projectRefs as $citation)<li>{{ $citation }}</li>@endforeach
            </ol>
        </details>
    @endif

    @if ($roadmap)
        <h4>Onde o projeto está</h4>
        <ol class="sg-road">
            @foreach ($roadmap as $i => $step)
                <li>
                    <span @class(['sg-dot', $step['state']])>{{ $step['state'] === 'done' ? '✓' : $i + 1 }}</span>
                    <span @class(['sg-muted' => $step['state'] === 'next'])>
                        <strong>{{ $step['title'] }}</strong>@if ($step['state'] === 'now') <em>(você está aqui)</em>@endif<br>
                        <span style="font-size:.8rem">{{ $step['why'] }}</span>
                    </span>
                </li>
            @endforeach
        </ol>
    @endif
</div>
