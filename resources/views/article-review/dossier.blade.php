@php use App\Models\ArticleReviewer; use App\Services\DocxInspector; @endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
    body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5pt; color: #111; }
    h1 { font-size: 14pt; margin: 0 0 4px; }
    h2 { font-size: 11pt; margin: 16px 0 6px; border-bottom: 1px solid #999; padding-bottom: 2px; }
    h3 { font-size: 10pt; margin: 10px 0 4px; }
    table { width: 100%; border-collapse: collapse; margin-top: 4px; }
    th, td { border: 1px solid #bbb; padding: 3px 5px; vertical-align: top; text-align: left; }
    th { background: #eee; font-size: 8.5pt; }
    .small { font-size: 8pt; color: #444; }
    pre { white-space: pre-wrap; font-family: "DejaVu Sans", sans-serif; font-size: 8.5pt; margin: 0; }
    .person { page-break-inside: avoid; border: 1px solid #ccc; padding: 6px 8px; margin-bottom: 8px; }
    .mono { font-family: "DejaVu Sans Mono", monospace; font-size: 7.5pt; word-break: break-all; }
</style>
</head>
<body>
    <h1>Dossiê do processo de revisão</h1>
    <div><strong>{{ $review->title }}</strong></div>
    <div class="small">
        {{ $review->workTypeLabel() }} · Situação: {{ $review->statusLabel() }}
        @if ($review->journal) · Revista: {{ $review->journal }} @endif
        · Iniciado: {{ $review->started_at?->format('d/m/Y H:i') ?? '—' }}
        · Revisões concluídas: {{ $review->reviews_completed_at?->format('d/m/Y H:i') ?? '—' }}
        · Enviado: {{ $review->submitted_at?->format('d/m/Y') ?? '—' }}
    </div>
    <div class="small">Gerado em {{ now()->format('d/m/Y H:i') }} — profatilio.com.br</div>

    <h2>Autores, filiações e contribuições</h2>
    <pre>{{ $authors }}</pre>

    <h2>Participantes (ordem de revisão)</h2>
    @foreach ($reviewers as $i => $r)
        <div class="person">
            <h3 style="margin-top:0">{{ $i + 1 }}. {{ $r->name }} — {{ $r->statusLabel() }}</h3>
            <table>
                <tr><th style="width:22%">E-mail / telefone</th><td>{{ $r->email ?: '—' }} / {{ $r->phone ?: '—' }}</td></tr>
                <tr><th>Nome para citação</th><td>{{ $r->citation_name ?: '—' }}</td></tr>
                <tr><th>Filiação</th><td>{{ $r->affiliationLine() ?: '—' }}</td></tr>
                <tr><th>ORCID</th><td>{{ $r->orcid ?: ($r->no_orcid ? 'Declarou não possuir' : '—') }}</td></tr>
                <tr><th>Participação (CRediT)</th><td>{{ $r->rolesLabel() ?: '—' }}</td></tr>
                <tr><th>Ficha confirmada</th><td>{{ $r->profile_confirmed_at?->format('d/m/Y H:i') ?? 'Pendente' }}@if (!empty($r->consent['ip'])) · IP {{ $r->consent['ip'] }}@endif — aceite em participar, ciência do conteúdo e lista de verificação</td></tr>
                <tr><th>Recebeu / devolveu</th><td>{{ $r->sent_at?->format('d/m/Y H:i') ?? '—' }} / {{ $r->returned_at?->format('d/m/Y H:i') ?? '—' }}</td></tr>
                @if ($r->returned_filename)
                    <tr><th>Versão devolvida</th><td>{{ $r->returned_filename }} (recebeu v{{ $r->forensic['received_version'] ?? '?' }})<br><span class="mono">SHA-256 {{ $r->forensic['sha256'] ?? '—' }}</span><br>IP {{ $r->forensic['ip'] ?? '—' }}</td></tr>
                @endif
                @if (!empty($r->forensic['changes']))
                    <tr><th>Alterações detectadas</th><td>{{ DocxInspector::summaryLine($r->forensic['changes']) }}</td></tr>
                @endif
                @if ($r->participation_statement)
                    <tr><th>Declaração</th><td>{{ $r->participation_statement }} — aceita em {{ $r->participation_accepted_at?->format('d/m/Y H:i') }}</td></tr>
                @endif
                @if ($r->removed_at)
                    <tr><th>Removido em</th><td>{{ $r->removed_at->format('d/m/Y H:i') }} {{ $r->removed_reason ? '— ' . $r->removed_reason : '' }}</td></tr>
                @endif
                @if ($r->profile_notes)
                    <tr><th>Observações</th><td>{{ $r->profile_notes }}</td></tr>
                @endif
            </table>
        </div>
    @endforeach

    <h2>Versões do arquivo</h2>
    <table>
        <tr><th>Versão</th><th>Arquivo</th><th>Origem</th><th>Data</th></tr>
        @foreach ($versions as $v)
            <tr><td>v{{ $v['version'] }}</td><td>{{ $v['filename'] }}</td><td>{{ $v['label'] }}</td><td>{{ $v['at']?->format('d/m/Y H:i') ?? '—' }}</td></tr>
        @endforeach
        @if ($review->final_filename)
            <tr><td>FINAL</td><td>{{ $review->final_filename }}</td><td>Versão final (professor)</td><td>{{ $review->submitted_at?->format('d/m/Y') }}</td></tr>
        @endif
    </table>

    <h2>Registro de acompanhamento</h2>
    <table>
        <tr><th style="width:16%">Data</th><th>Evento</th></tr>
        @foreach ($review->history ?? [] as $e)
            <tr>
                <td>{{ \Illuminate\Support\Carbon::parse($e['at'])->format('d/m/Y H:i') }}</td>
                <td>{{ $e['event'] }}@if (!empty($e['details'])) <span class="small">({{ $e['details'] }})</span>@endif @if (!empty($e['actor'])) <span class="small">· por {{ $e['actor'] }}</span>@endif</td>
            </tr>
        @endforeach
    </table>
</body>
</html>
