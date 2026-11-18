{{-- Resultado de um treino da triagem: votos da dupla × gabarito comentado. $meId = triador logado (aluno) ou null (professor). --}}
@php
    $screeners = $results['screeners'];
    $me = $meId ? $screeners->firstWhere('id', $meId) : null;
    $order = $me ? collect([$me])->merge($screeners->where('id', '!=', $me->id)) : $screeners;
    $name = fn ($s) => $me && $s->id === $me->id ? 'Você' : ($me ? 'Dupla: ' . $s->name : $s->name);
    $dec = \App\Models\SystematicReviewRecord::DECISIONS;
    $pair = $results['pair'];
    $k = $pair['kappa'] ?? null;
@endphp
<style>
    .tr-grid { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr)); margin-bottom: 1rem; }
    .tr-box { border: 1px solid rgba(148,163,184,.35); border-radius: .8rem; padding: .7rem .9rem; }
    .tr-big { font-size: 1.5rem; font-weight: 700; line-height: 1.2; }
    .tr-small { font-size: .8rem; opacity: .75; }
    .tr-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
    .tr-table th { text-align: left; font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; opacity: .7; padding: .45rem .5rem; border-bottom: 1px solid rgba(148,163,184,.4); }
    .tr-table td { vertical-align: top; padding: .55rem .5rem; border-bottom: 1px solid rgba(148,163,184,.2); }
    .tr-table tr.split td { background: rgba(217,119,6,.07); }
    .tr-ok { color: #047857; font-weight: 700; }
    .tr-no { color: #b91c1c; font-weight: 700; }
    .tr-tag { display: inline-block; padding: .05rem .45rem; border-radius: 999px; font-size: .72rem; font-weight: 700; background: rgba(148,163,184,.18); }
    .tr-tag.include { background: rgba(5,150,105,.14); color: #047857; }
    .tr-tag.exclude { background: rgba(185,28,28,.12); color: #b91c1c; }
    .tr-tag.maybe { background: rgba(217,119,6,.15); color: #b45309; }
    .tr-wrap { overflow-x: auto; }
    @media (max-width: 640px) { .tr-table th:nth-child(1), .tr-table td:nth-child(1) { min-width: 12rem; } }
</style>

<div class="tr-grid">
    @foreach ($order as $s)
        @php $sc = $results['score'][$s->id]; @endphp
        <div class="tr-box">
            <div class="tr-small">{{ $name($s) }}</div>
            <div class="tr-big">{{ $sc['right'] }}/{{ $sc['scored'] }}</div>
            <div class="tr-small">acertos em relação ao gabarito · decidiu {{ $sc['done'] }} de {{ $sc['total'] }}</div>
        </div>
    @endforeach
    <div class="tr-box">
        <div class="tr-small">Concordância da dupla</div>
        @if ($k && $k['kappa'] !== null)
            <div class="tr-big">κ = {{ number_format($k['kappa'], 2, ',', '') }}</div>
            <div class="tr-small">{{ \App\Services\SystematicReviewService::kappaLabel($k['kappa']) }} · {{ number_format($k['agreement'] * 100, 0) }}% iguais · n = {{ $pair['n'] }} · meta do projeto: κ ≥ 0,60</div>
        @else
            <div class="tr-big">—</div>
            <div class="tr-small">Sem trabalhos decididos pelos dois.</div>
        @endif
    </div>
</div>
<p class="tr-small" style="margin:-.25rem 0 .75rem">Acerto pela regra da triagem: Talvez conta como "não excluir", porque o trabalho segue para o texto completo. Os casos de dúvida do gabarito não contam acerto nem erro: são para discutir. Linhas em destaque = a dupla discordou.</p>

<div class="tr-wrap">
    <table class="tr-table">
        <thead>
            <tr>
                <th>Trabalho</th>
                @foreach ($order as $s)<th>{{ $name($s) }}</th>@endforeach
                <th>Gabarito do professor</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($results['rows'] as $row)
                @php $r = $row['record']; $key = $row['key']; @endphp
                <tr class="{{ $row['split'] ? 'split' : '' }}">
                    <td>
                        <div style="font-weight:600">{{ $r->title }}</div>
                        <div class="tr-small">{{ $r->year }}@if ($r->link()) · <a href="{{ $r->link() }}" target="_blank" rel="noopener" style="color:#0d9488">PubMed {{ $r->pmid }}</a>@endif</div>
                    </td>
                    @foreach ($order as $s)
                        @php $v = $row['votes'][$s->id]; $ok = $row['right'][$s->id]; @endphp
                        <td>
                            @if ($v)
                                <span class="tr-tag {{ $v->decision }}">{{ $dec[$v->decision] ?? $v->decision }}</span>
                                @if ($ok === true)<span class="tr-ok" title="igual ao gabarito">✓</span>@elseif ($ok === false)<span class="tr-no" title="diferente do gabarito">✗</span>@endif
                                @if ($v->reason)<div class="tr-small">{{ $v->reason }}</div>@endif
                                @if ($v->note)<div class="tr-small" style="font-style:italic">“{{ $v->note }}”</div>@endif
                            @else
                                <span class="tr-small">não decidiu</span>
                            @endif
                        </td>
                    @endforeach
                    <td>
                        @if ($key)
                            <span class="tr-tag {{ $key['decision'] }}">{{ $key['label'] }}</span>
                            @if ($key['reason'])<strong style="font-size:.8rem">{{ $key['reason'] }}</strong>@endif
                            <div class="tr-small">{{ $key['why'] }}</div>
                        @else
                            <span class="tr-small">sem gabarito</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
