<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Termo de Aceite de Orientação Acadêmica</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #1e293b;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #ffffff;
        }
        .term-border {
            border: 4px double #0d9488;
            padding: 40px;
            border-radius: 8px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 20px;
        }
        .gov-title {
            font-size: 14px;
            font-weight: bold;
            color: #0f766e;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0 0 4px 0;
        }
        .gov-subtitle {
            font-size: 11px;
            color: #64748b;
            margin: 0 0 15px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .main-title {
            font-size: 20px;
            font-weight: bold;
            color: #1e293b;
            margin: 10px 0 5px 0;
            letter-spacing: -0.5px;
        }
        .process-id {
            font-size: 11px;
            color: #94a3b8;
            font-family: monospace;
        }
        .content {
            margin-bottom: 35px;
            font-size: 14px;
        }
        .content p {
            margin-bottom: 20px;
            text-align: justify;
            text-indent: 30px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin: 25px 0;
            font-size: 13px;
        }
        .info-table th, .info-table td {
            border: 1px solid #e2e8f0;
            padding: 10px 14px;
            text-align: left;
        }
        .info-table th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: bold;
            width: 30%;
        }
        .info-table td {
            color: #1e293b;
        }
        .signatures {
            margin-top: 50px;
            width: 100%;
            display: table;
        }
        .sig-block {
            display: table-cell;
            width: 50%;
            text-align: center;
            font-size: 12px;
        }
        .sig-line {
            width: 80%;
            margin: 0 auto 8px auto;
            border-top: 1px solid #cbd5e1;
        }
    </style>
</head>
<body>

    <div class="term-border">
        <div class="header">
            <div class="gov-title">Plataforma Acadêmica de Orientação</div>
            <div class="gov-subtitle">Prof. Dr. Atilio Sersun Calefi</div>
            <div class="main-title">TERMO DE ACEITE DE ORIENTAÇÃO ACADÊMICA</div>
            <div class="process-id">Processo Acadêmico nº {{ $submission->process_id }}</div>
        </div>

        <div class="content">
            <p>
                Por meio deste termo, o docente orientador abaixo qualificado declara formalmente o **aceite de orientação acadêmica** do estudante indicado, comprometendo-se a supervisionar o desenvolvimento de suas atividades de pesquisa, elaboração de relatórios e cumprimento dos requisitos institucionais vigentes para a respectiva modalidade.
            </p>

            <table class="info-table">
                <tr>
                    <th>Docente Orientador</th>
                    <td><strong>Prof. Dr. Atilio Sersun Calefi</strong></td>
                </tr>
                <tr>
                    <th>Estudante / Orientando</th>
                    <td>{{ $student->user->name ?? 'N/A' }} (Matrícula/RGM: {{ $student->registration ?? 'N/A' }})</td>
                </tr>
                <tr>
                    <th>Nível da Orientação</th>
                    <td>{{ $metadata['orientation_level'] ?? 'Graduação' }}</td>
                </tr>
                <tr>
                    <th>Tema / Título do Trabalho</th>
                    <td>{{ $metadata['orientation_topic'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Instituição de Vínculo</th>
                    <td>{{ $metadata['orientation_institution'] ?? ($student->institution ?? 'N/A') }}</td>
                </tr>
                <tr>
                    <th>Campus</th>
                    <td>{{ $metadata['orientation_campus'] ?? ($student->campus ?? 'N/A') }}</td>
                </tr>
                <tr>
                    <th>Vigência da Orientação</th>
                    <td>
                        De: <strong>{{ isset($metadata['orientation_start_date']) ? date('d/m/Y', strtotime($metadata['orientation_start_date'])) : date('d/m/Y') }}</strong> 
                        Até: <strong>{{ isset($metadata['orientation_end_date']) ? date('d/m/Y', strtotime($metadata['orientation_end_date'])) : date('d/m/Y', strtotime('+1 year')) }}</strong>
                    </td>
                </tr>
            </table>

            <p>
                O orientando declara estar ciente de suas obrigações acadêmicas, incluindo a observância de prazos, ética em pesquisa e conformidade com as diretrizes da instituição de ensino.
            </p>
        </div>

        <div class="signatures">
            <div class="sig-block">
                <div class="sig-line"></div>
                <strong>Prof. Dr. Atilio Sersun Calefi</strong><br>
                Docente Orientador
            </div>
            <div class="sig-block">
                <div class="sig-line"></div>
                <strong>{{ $student->user->name ?? 'Estudante' }}</strong><br>
                Orientando(a)
            </div>
        </div>
    </div>

</body>
</html>
