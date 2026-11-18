@php use App\Services\SystematicReviewService as S; use App\Models\SystematicReview; @endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
    body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5pt; color: #111; }
    h1 { font-size: 14pt; margin: 0 0 4px; }
    h2 { font-size: 11pt; margin: 16px 0 6px; border-bottom: 1px solid #999; padding-bottom: 2px; }
    table { width: 100%; border-collapse: collapse; margin-top: 4px; }
    th, td { border: 1px solid #bbb; padding: 3px 5px; vertical-align: top; text-align: left; }
    th { background: #eee; font-size: 8.5pt; }
    .small { font-size: 8pt; color: #444; }
    .flow td { text-align: center; border: 1px solid #555; padding: 6px; }
    .flow .arrow { border: none; font-size: 12pt; padding: 0; }
    .flow .side { text-align: left; font-size: 8.5pt; }
    .mono { font-family: "DejaVu Sans Mono", monospace; font-size: 7.5pt; word-break: break-all; }
</style>
</head>
<body>
    <h1>Relatório da triagem — revisão sistemática</h1>
    <div><strong>{{ $review->title }}</strong></div>
    <div class="small">
        {{ $review->typeLabel() }} · {{ $review->framework }} · Situação: {{ $review->statusLabel() }}<br>
        Registro: {{ $review->registrationLine() ?: 'não registrado' }}@if ($review->registered_at) em {{ $review->registered_at->format('d/m/Y') }}@endif
        @if ($review->embargo_until) · embargo até {{ $review->embargo_until->format('d/m/Y') }}@endif
        @if (!$review->registration_id && $review->registration_waiver)<br>Justificativa da ausência de registro: {{ $review->registration_waiver }}@endif
        <br>Diretriz de relato: {{ $review->reportingGuideline() }}
        <br>Triagem iniciada: {{ $review->screening_started_at?->format('d/m/Y H:i') ?? '—' }} · Gerado em {{ now()->format('d/m/Y H:i') }}
    </div>

    <h2>Fluxograma PRISMA 2020</h2>
    <table class="flow">
        <tr>
            <td style="width:55%"><strong>Identificação</strong><br>Registros identificados: n = {{ $prisma['identified'] ?: '—' }}<br>
                @foreach ($prisma['sources'] as $name => $n){{ $name }} (n = {{ $n }})@if (!$loop->last); @endif @endforeach</td>
            <td class="side">Duplicatas removidas: n = {{ $prisma['duplicates'] }}</td>
        </tr>
        <tr><td class="arrow">↓</td><td class="arrow"></td></tr>
        <tr>
            <td><strong>Triagem</strong><br>Registros triados: n = {{ $prisma['screened'] }}</td>
            <td class="side">Excluídos (título/resumo): n = {{ $prisma['ta_excluded'] }}<br>
                @foreach ($prisma['ta_reasons'] as $reason => $n){{ $reason ?: 'sem motivo' }}: {{ $n }}<br>@endforeach</td>
        </tr>
        <tr><td class="arrow">↓</td><td class="arrow"></td></tr>
        <tr>
            <td>Buscados para texto completo: n = {{ $prisma['sought'] }}</td>
            <td class="side">Não obtidos: n = {{ $prisma['not_retrieved'] }}</td>
        </tr>
        <tr><td class="arrow">↓</td><td class="arrow"></td></tr>
        <tr>
            <td>Avaliados para elegibilidade: n = {{ $prisma['assessed'] ?? '—' }}</td>
            <td class="side">Excluídos (texto completo): n = {{ $prisma['ft_excluded'] ?? '—' }}<br>
                @foreach ($prisma['ft_reasons'] as $reason => $n){{ $reason }}: {{ $n }}<br>@endforeach</td>
        </tr>
        <tr><td class="arrow">↓</td><td class="arrow"></td></tr>
        <tr><td><strong>Incluídos</strong><br>Estudos incluídos: n = {{ $prisma['included'] ?? '—' }}</td><td class="arrow"></td></tr>
    </table>

    @if ($searches)
        <h2>Buscas (PRISMA-S)</h2>
        <table>
            <tr><th>Base</th><th>Plataforma</th><th>Data</th><th>Resultados</th><th>Sintaxe</th></tr>
            @foreach ($searches as $s)
                <tr><td>{{ $s['database'] ?? '' }}</td><td>{{ $s['platform'] ?? '' }}</td><td>{{ $s['date'] ?? '' }}</td><td>{{ $s['results'] ?? '' }}</td><td class="mono">{{ $s['query'] ?? '' }}</td></tr>
            @endforeach
        </table>
    @endif

    @if (!empty($review->pilot_rounds))
        <h2>Calibração do piloto (Título e Resumo)</h2>
        <table>
            <tr><th>Rodada</th><th>Amostra</th><th>n avaliado</th><th>Concordância</th><th>κ de Cohen</th><th>κ binário</th><th>Situação</th></tr>
            @foreach ($review->pilot_rounds as $r)
                <tr>
                    <td>Rodada {{ $r['round'] }}</td>
                    <td>{{ $r['size'] ?? '—' }} registros</td>
                    <td>{{ $r['n'] ?? '—' }}</td>
                    <td>{{ isset($r['agreement']) ? S::fmt($r['agreement'] * 100, 1) . '%' : '—' }}</td>
                    <td>{{ S::fmt($r['kappa'] ?? null) }}</td>
                    <td>{{ S::fmt($r['kappa_binary'] ?? null) }}</td>
                    <td>{{ !empty($r['released']) ? 'Aprovada (liberou triagem)' : 'Alinhamento realizado' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>Confiabilidade entre triadores (acumulado)</h2>
    <table>
        <tr><th>Etapa</th><th>Par</th><th>n</th><th>Concordância</th><th>κ de Cohen (IC 95%)</th><th>Interpretação</th><th>κ binário</th></tr>
        @foreach ($reliability as $stage => $rel)
            @foreach ($rel['pairs'] as $p)
                <tr>
                    <td>{{ SystematicReview::STAGE_LABELS[$stage] }}</td>
                    <td>{{ $p['a'] }} × {{ $p['b'] }}</td>
                    <td>{{ $p['n'] }}</td>
                    <td>{{ $p['kappa']['agreement'] !== null ? S::fmt($p['kappa']['agreement'] * 100, 1) . '%' : '—' }}</td>
                    <td>{{ S::fmt($p['kappa']['kappa']) }}@if ($p['kappa']['ci_low'] !== null) ({{ S::fmt($p['kappa']['ci_low']) }} a {{ S::fmt($p['kappa']['ci_high']) }})@endif</td>
                    <td>{{ S::kappaLabel($p['kappa']['kappa']) }}</td>
                    <td>{{ S::fmt($p['kappa_binary']['kappa']) }}</td>
                </tr>
            @endforeach
        @endforeach
    </table>
    <div class="small">κ binário: "incluir" e "talvez" contam juntos contra "excluir". Interpretação de Landis & Koch (1977).</div>

    @if ($warnings)
        <h2>Exigências das revistas — pendências</h2>
        <ul>@foreach ($warnings as $w)<li>{{ $w }}</li>@endforeach</ul>
    @endif

    <h2>Auditoria (decisões do coordenador e desvios)</h2>
    @forelse ($audit as $e)
        <div class="small">{{ \Illuminate\Support\Carbon::parse($e['at'])->format('d/m/Y H:i') }} — <strong>{{ $e['event'] }}</strong>@if (!empty($e['details'])) — {{ $e['details'] }}@endif</div>
    @empty
        <div class="small">Nenhuma sobreposição de consenso, liberação de piloto ou alteração de protocolo registrada.</div>
    @endforelse

    <h2>Texto de Métodos (rascunho)</h2>
    <p style="line-height:1.6">{{ $methods }}</p>

    <h2>Triadores e termos assinados</h2>
    <table>
        <tr><th>Nome</th><th>E-mail</th><th>Termo (versão)</th><th>Assinado em</th><th>IP</th><th>SHA-256</th><th>Ritmo (mediana s/trabalho)</th><th>Situação</th></tr>
        @foreach ($screeners as $s)
            <tr>
                <td>{{ $s->name }}</td><td>{{ $s->email }}</td><td>{{ $s->terms_version ?? '—' }}</td>
                <td>{{ $s->terms_accepted_at?->format('d/m/Y H:i') ?? 'não assinado' }}</td><td>{{ $s->terms_ip ?? '—' }}</td>
                <td class="mono">{{ $s->terms_sha256 ? substr($s->terms_sha256, 0, 24) . '…' : '—' }}</td>
                <td>@foreach ($paces[$s->id] ?? [] as $stage => $p){{ SystematicReview::STAGE_LABELS[$stage] }}: {{ $p['median'] !== null ? S::fmt($p['median'], 0) . ' s' . ($p['fast'] ? ' ⚠' : '') : '—' }}<br>@endforeach</td>
                <td>{{ $s->removed_at ? 'removido em ' . $s->removed_at->format('d/m/Y') : 'ativo' }}</td>
            </tr>
        @endforeach
    </table>
</body>
</html>
