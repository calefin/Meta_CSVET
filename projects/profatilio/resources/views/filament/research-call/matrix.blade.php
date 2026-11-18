{{-- Matriz de alocação. Estilo próprio (modal do Filament). --}}
<style>
    .rc-matrix { width: 100%; border-collapse: collapse; font-size: .8rem; }
    .rc-matrix th { text-align: left; font-size: .7rem; text-transform: uppercase; letter-spacing: .04em; opacity: .7; padding: .4rem .5rem; border-bottom: 1px solid rgba(148,163,184,.35); }
    .rc-matrix td { padding: .45rem .5rem; border-bottom: 1px solid rgba(148,163,184,.18); vertical-align: top; }
    .rc-code { font-family: ui-monospace, monospace; font-weight: 700; color: #0d9488; white-space: nowrap; }
    .rc-empty { opacity: .45; font-style: italic; }
    .rc-axis td { font-weight: 700; padding-top: .9rem; opacity: .8; }
    .rc-legend { font-size: .75rem; opacity: .75; margin-bottom: .6rem; }
</style>
@php
    $leads = collect($rows)->whereNotNull('lead')->count();
    $reviewers = collect($rows)->whereNotNull('reviewer')->count();
    $axis = null;
@endphp
<p class="rc-legend">{{ $leads }}/{{ count($rows) }} projetos com líder · {{ $reviewers }}/{{ count($rows) }} com revisor cego. "Interessados" = candidatos que marcaram o projeto como preferência. 🔗 = vinculado a uma Revisão Sistemática.</p>
<div style="overflow-x:auto">
<table class="rc-matrix">
    <thead><tr><th>Projeto</th><th>Área</th><th>Líder (1º autor)</th><th>Revisor cego</th><th>Interessados</th></tr></thead>
    <tbody>
    @foreach($rows as $r)
        @if($r['axis'] !== $axis)
            @php $axis = $r['axis']; @endphp
            <tr class="rc-axis"><td colspan="5">{{ $axis }}</td></tr>
        @endif
        <tr>
            <td class="rc-code">{{ $r['code'] }}{{ $r['linked'] ? ' 🔗' : '' }}</td>
            <td>{{ $r['area'] }}</td>
            <td>{!! $r['lead'] ? e($r['lead']) : '<span class="rc-empty">vago</span>' !!}</td>
            <td>{!! $r['reviewer'] ? e($r['reviewer']) : '<span class="rc-empty">vago</span>' !!}</td>
            <td>{{ $r['wanted'] ?: '—' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
