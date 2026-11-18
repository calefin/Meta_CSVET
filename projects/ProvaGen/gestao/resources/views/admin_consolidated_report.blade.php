<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório Consolidado de Orientações Acadêmicas</title>
    <style>
        body {
            font-family: 'Helvetica Neue', 'Helvetica', Arial, sans-serif;
            font-size: 11px;
            color: #334155;
            margin: 0;
            padding: 0;
        }
        .header {
            border-bottom: 2px solid #0d9488;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .header-title {
            font-size: 18px;
            font-weight: bold;
            color: #0f172a;
            margin: 0;
        }
        .header-subtitle {
            font-size: 11px;
            color: #64748b;
            margin: 4px 0 0 0;
        }
        .meta-info {
            float: right;
            text-align: right;
            font-size: 10px;
            color: #64748b;
        }
        .clear {
            clear: both;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: bold;
            text-align: left;
            padding: 8px 10px;
            border-bottom: 1px solid #cbd5e1;
            text-transform: uppercase;
            font-size: 9px;
            letter-spacing: 0.5px;
        }
        td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
        }
        .badge-success {
            background-color: #dcfce7;
            color: #15803d;
        }
        .badge-warning {
            background-color: #fef9c3;
            color: #a16207;
        }
        .badge-danger {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        .badge-gray {
            background-color: #f1f5f9;
            color: #475569;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
            font-size: 9px;
            color: #94a3b8;
            text-align: center;
        }
        .signature-section {
            margin-top: 40px;
            text-align: right;
        }
        .signature-line {
            display: inline-block;
            width: 250px;
            border-top: 1px solid #94a3b8;
            margin-top: 40px;
            text-align: center;
            font-size: 10px;
            color: #475569;
        }
    </style>
</head>
<body>

    <div class="header">
        <div class="meta-info">
            <strong>Gerado em:</strong> {{ $generated_at }}<br>
            Plataforma profatilio.com.br
        </div>
        <h1 class="header-title">Relatório Consolidado de Orientações</h1>
        <p class="header-subtitle">Status de prazos e relatórios trimestrais de Iniciação Científica (IC) e TCC dos orientandos</p>
        <div class="clear"></div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 20%;">Orientando</th>
                <th style="width: 10%;">Matrícula/RGM</th>
                <th style="width: 15%;">Instituição</th>
                <th style="width: 10%;">Nível</th>
                <th style="width: 25%;">Tema da Pesquisa / Título</th>
                <th style="width: 10%;">Prazo Trimestre</th>
                <th style="width: 10%;">Próximo Prazo</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data as $row)
                <tr>
                    <td style="font-weight: bold; color: #0f172a;">{{ $row['name'] }}</td>
                    <td>{{ $row['registration'] }}</td>
                    <td>{{ $row['institution'] }}</td>
                    <td>{{ $row['academic_level'] }}</td>
                    <td style="font-style: italic; color: #475569;">{{ $row['research_topic'] }}</td>
                    <td>
                        @if($row['has_ic'])
                            @php
                                $badgeClass = match($row['status']) {
                                    'submitted' => 'badge-success',
                                    'on_time' => 'badge-success',
                                    'warning' => 'badge-warning',
                                    'critical', 'overdue' => 'badge-danger',
                                    default => 'badge-gray'
                                };
                            @endphp
                            <span class="badge {{ $badgeClass }}">
                                {{ $row['quarter'] }}: {{ $row['label'] }}
                            </span>
                        @else
                            <span class="badge badge-gray">-</span>
                        @endif
                    </td>
                    <td style="font-weight: 500;">
                        {{ $row['due_date'] }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center; color: #94a3b8; padding: 20px;">
                        Nenhum aluno ou orientação ativa cadastrada no sistema.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="signature-section">
        <div class="signature-line">
            <strong>Prof. Dr. Atilio Sersun Calefi</strong><br>
            Orientador / Administrador
        </div>
    </div>

    <div class="footer">
        Este documento é gerado automaticamente pelo portal profatilio.com.br. Validação forense e integridade garantidas criptograficamente.
    </div>

</body>
</html>
