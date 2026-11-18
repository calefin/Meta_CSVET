<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Orientação — {{ $student->user->name ?? 'Aluno' }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', 'Helvetica', Arial, sans-serif;
            font-size: 11px;
            color: #334155;
            margin: 0;
            padding: 0;
        }
        .header {
            border-bottom: 3px solid #0d9488;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .header-title {
            font-size: 16px;
            font-weight: bold;
            color: #0f172a;
            margin: 0 0 4px 0;
        }
        .header-subtitle {
            font-size: 10px;
            color: #64748b;
            margin: 0;
        }
        .meta-right {
            float: right;
            text-align: right;
            font-size: 10px;
            color: #64748b;
        }
        .clear { clear: both; }
        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #0d9488;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
            margin: 18px 0 10px 0;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .info-grid td {
            padding: 4px 8px;
            vertical-align: top;
        }
        .info-grid td.label {
            font-weight: bold;
            color: #475569;
            width: 130px;
            white-space: nowrap;
        }
        .info-grid td.value {
            color: #1e293b;
        }
        table.submissions {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }
        table.submissions th {
            background: #0d9488;
            color: #fff;
            padding: 5px 6px;
            text-align: left;
            font-weight: bold;
        }
        table.submissions td {
            padding: 4px 6px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }
        table.submissions tr:nth-child(even) td {
            background: #f8fafc;
        }
        .badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
        }
        .badge-success { background: #dcfce7; color: #15803d; }
        .badge-warning { background: #fef9c3; color: #b45309; }
        .badge-info    { background: #dbeafe; color: #1d4ed8; }
        .badge-gray    { background: #f1f5f9; color: #64748b; }
        .footer {
            margin-top: 30px;
            border-top: 1px solid #e2e8f0;
            padding-top: 10px;
            font-size: 9px;
            color: #94a3b8;
            text-align: center;
        }
        .signature-block {
            margin-top: 40px;
            text-align: center;
        }
        .signature-line {
            display: inline-block;
            border-top: 1px solid #334155;
            width: 220px;
            margin-top: 40px;
            padding-top: 4px;
            font-size: 10px;
            color: #334155;
        }
    </style>
</head>
<body>

<div class="header">
    <div class="meta-right">
        Gerado em: {{ now()->format('d/m/Y H:i') }}<br>
        profatilio.com.br
    </div>
    <p class="header-title">Relatório de Orientação Acadêmica</p>
    <p class="header-subtitle">Prof. Dr. Atilio Sersun Calefi — Plataforma profatilio.com.br</p>
    <div class="clear"></div>
</div>

<!-- Dados do Aluno -->
<div class="section-title">Dados do Orientando</div>
<table class="info-grid">
    <tr>
        <td class="label">Nome:</td>
        <td class="value">{{ $student->user->name ?? '—' }}</td>
        <td class="label">E-mail:</td>
        <td class="value">{{ $student->user->email ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Matrícula / RGM:</td>
        <td class="value">{{ $student->registration ?? '—' }}</td>
        <td class="label">Telefone:</td>
        <td class="value">{{ $student->phone ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Instituição:</td>
        <td class="value">{{ $student->institution ?? '—' }}</td>
        <td class="label">Campus:</td>
        <td class="value">{{ $student->campus ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Nível Acadêmico:</td>
        <td class="value">{{ $student->academic_level ?? '—' }}</td>
        <td class="label">Status da Conta:</td>
        <td class="value">{{ match($student->status) {
            'active' => 'Ativo',
            'awaiting_advisor_approval' => 'Aguardando Aprovação',
            'awaiting_email_validation' => 'Validação de E-mail Pendente',
            default => $student->status,
        } }}</td>
    </tr>
    @if($student->research_topic)
    <tr>
        <td class="label">Tema de Pesquisa:</td>
        <td class="value" colspan="3">{{ $student->research_topic }}</td>
    </tr>
    @endif
    @if($student->terms_accepted_at)
    <tr>
        <td class="label">LGPD Assinado em:</td>
        <td class="value" colspan="3">{{ $student->terms_accepted_at->format('d/m/Y H:i') }}</td>
    </tr>
    @endif
</table>

<!-- Títulos Acadêmicos -->
@if($student->titles && $student->titles->count())
<div class="section-title">Títulos Vinculados (TCC / IC)</div>
<table class="info-grid">
    @foreach($student->titles as $title)
    <tr>
        <td class="label">{{ $title->type }}:</td>
        <td class="value" colspan="3">{{ $title->title }}</td>
    </tr>
    @endforeach
</table>
@endif

<!-- Histórico de Documentos -->
<div class="section-title">Histórico de Processos e Documentos</div>

@if($submissions->count() === 0)
    <p style="color:#64748b; font-style:italic;">Nenhum documento submetido até o momento.</p>
@else
<table class="submissions">
    <thead>
        <tr>
            <th>Processo</th>
            <th>Tipo de Documento</th>
            <th>Status Atual</th>
            <th>Submetido em</th>
            <th>Última Atualização</th>
            <th>Assinatura</th>
        </tr>
    </thead>
    <tbody>
        @foreach($submissions as $sub)
        @php
            $isConcluded = str_starts_with($sub->status, 'Processo Concluído');
            $hasSig      = !empty($sub->metadata['forensic_hash']);
            $statusClass = $isConcluded ? 'badge-success' : ($sub->status === 'pending' ? 'badge-gray' : 'badge-info');
        @endphp
        <tr>
            <td><strong>{{ $sub->process_id ?? '—' }}</strong></td>
            <td>{{ $sub->document_type }}</td>
            <td><span class="badge {{ $statusClass }}">{{ Str::limit($sub->status, 30) }}</span></td>
            <td>{{ $sub->created_at?->format('d/m/Y') }}</td>
            <td>{{ $sub->updated_at?->format('d/m/Y') }}</td>
            <td>{{ $hasSig ? 'Assinado' : '—' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<p style="margin-top:8px; font-size:10px; color:#64748b;">
    Total: <strong>{{ $submissions->count() }}</strong> processo(s) —
    <strong>{{ $submissions->filter(fn($s) => str_starts_with($s->status, 'Processo Concluído'))->count() }}</strong> concluído(s)
</p>
@endif

<!-- Assinatura do Orientador -->
<div class="signature-block">
    <div class="signature-line">
        Prof. Dr. Atilio Sersun Calefi<br>
        Orientador Acadêmico
    </div>
</div>

<div class="footer">
    Este relatório foi gerado automaticamente pela plataforma profatilio.com.br em {{ now()->format('d/m/Y \à\s H:i') }}.
    Documento de uso interno — sujeito à conformidade com a LGPD (Lei nº 13.709/2018).
</div>

</body>
</html>
