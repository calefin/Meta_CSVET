<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo de Auditoria Digital - {{ $submission->process_id }}</title>
    <link rel="icon" href="{{ asset('images/favicon.png') }}" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            line-height: 1.5;
            padding: 40px 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            max-width: 800px;
            width: 100%;
            background-color: #ffffff;
            border: 2px solid #0d9488;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
            padding: 40px;
            position: relative;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .logo-section h1 {
            font-size: 24px;
            color: #0d9488;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: -0.02em;
        }
        .logo-section p {
            font-size: 13px;
            color: #64748b;
        }
        .process-badge {
            background-color: #f0fdfa;
            color: #0d9488;
            border: 1px solid #ccfbf1;
            padding: 6px 12px;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 13px;
        }
        .section-title {
            font-size: 15px;
            color: #0d9488;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 12px;
            border-left: 3px solid #0d9488;
            padding-left: 8px;
        }
        .grid-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 25px;
            background-color: #f8fafc;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }
        .info-item label {
            display: block;
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 500;
            margin-bottom: 4px;
        }
        .info-item span {
            font-size: 14px;
            color: #1e293b;
            font-weight: 500;
        }
        .clauses-box {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
        }
        .clause-item {
            margin-bottom: 12px;
            font-size: 13px;
            display: flex;
            align-items: flex-start;
        }
        .clause-item:last-child {
            margin-bottom: 0;
        }
        .clause-checkbox {
            color: #0d9488;
            font-weight: bold;
            font-size: 16px;
            margin-right: 8px;
            line-height: 1;
        }
        .clause-text {
            color: #475569;
        }
        .forensic-box {
            background-color: #0f172a;
            color: #94a3b8;
            border-radius: 12px;
            padding: 25px;
            font-family: monospace;
            font-size: 12px;
            margin-bottom: 30px;
            border-left: 4px solid #0d9488;
        }
        .forensic-box h3 {
            color: #38bdf8;
            font-size: 13px;
            margin-bottom: 12px;
            font-family: 'Outfit', sans-serif;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .forensic-line {
            margin-bottom: 8px;
            word-break: break-all;
        }
        .forensic-line span {
            color: #38bdf8;
        }
        .hash-footer {
            border-top: 2px dashed #e2e8f0;
            padding-top: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .hash-details {
            flex-grow: 1;
            margin-right: 30px;
        }
        .hash-title {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        .hash-value {
            font-family: monospace;
            font-size: 13px;
            color: #0d9488;
            background-color: #f0fdfa;
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #ccfbf1;
            word-break: break-all;
            font-weight: bold;
        }
        .qr-code {
            width: 100px;
            height: 100px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 5px;
            background: white;
        }
        .actions-bar {
            margin-top: 30px;
            display: flex;
            justify-content: center;
            gap: 15px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 650;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease-in-out;
            border: none;
        }
        .btn-primary {
            background-color: #0d9488;
            color: white;
            box-shadow: 0 4px 6px -1px rgba(13, 148, 136, 0.2);
        }
        .btn-primary:hover {
            background-color: #0f766e;
            transform: translateY(-1px);
        }
        .btn-secondary {
            background-color: #e2e8f0;
            color: #475569;
        }
        .btn-secondary:hover {
            background-color: #cbd5e1;
        }
        @media print {
            body {
                background-color: #ffffff;
                padding: 0;
            }
            .container {
                box-shadow: none;
                border: 1px solid #0d9488;
                padding: 20px;
            }
            .actions-bar {
                display: none;
            }
        }
    </style>
</head>
<body>

    <div class="container">
        <!-- Cabeçalho do Recibo -->
        <div class="header">
            <div class="logo-section">
                <h1>profatilio.com.br</h1>
                <p>Laudo de Auditoria Digital de Iniciação Científica (IC)</p>
            </div>
            <div class="process-badge">
                {{ $submission->process_id }}
            </div>
        </div>

        <!-- Seção do Aluno -->
        <div class="section-title">Dados do Aluno / Orientando</div>
        <div class="grid-info">
            <div class="info-item">
                <label>Nome Completo</label>
                <span>{{ $user->name }}</span>
            </div>
            <div class="info-item">
                <label>Registro de Matrícula (RGM)</label>
                <span>{{ $student->registration }}</span>
            </div>
            <div class="info-item">
                <label>Instituição de Ensino</label>
                <span>{{ $student->institution }}</span>
            </div>
            <div class="info-item">
                <label>Campus</label>
                <span>{{ $student->campus }}</span>
            </div>
            <div class="info-item" style="grid-column: span 2;">
                <label>E-mail do Aluno</label>
                <span>{{ $user->email }}</span>
            </div>
        </div>

        <!-- Seção do Projeto -->
        <div class="section-title">Metadados do Projeto de Pesquisa</div>
        <div class="grid-info">
            <div class="info-item" style="grid-column: span 2;">
                <label>Título do Projeto de IC</label>
                <span>{{ $submission->title }}</span>
            </div>
            <div class="info-item">
                <label>Tipo de Bolsa</label>
                <span>{{ $metadata['ic_scholarship_type'] ?? 'Sem Bolsa' }}</span>
            </div>
            <div class="info-item">
                <label>Vigência do Projeto</label>
                <span>
                    {{ isset($metadata['ic_start_date']) ? \Carbon\Carbon::parse($metadata['ic_start_date'])->format('d/m/Y') : '' }}
                    até
                    {{ isset($metadata['ic_end_date']) ? \Carbon\Carbon::parse($metadata['ic_end_date'])->format('d/m/Y') : '' }}
                </span>
            </div>
        </div>

        <!-- Cláusulas de Compromisso -->
        <div class="section-title">Cláusulas de Declaração de Aceite (Padrão FAPESP)</div>
        <div class="clauses-box">
            <div class="clause-item">
                <span class="clause-checkbox">[✔]</span>
                <span class="clause-text"><strong>Cláusula de Relatórios Periódicos:</strong> Entrega de relatórios parciais de desempenho a cada 3 meses sob pena de encerramento imediato do vínculo de orientação de IC.</span>
            </div>
            <div class="clause-item">
                <span class="clause-checkbox">[✔]</span>
                <span class="clause-text"><strong>Cláusula de Entrega de Resultados (FAPESP):</strong> Entrega obrigatória de relatório conclusivo, artigo científico ou resumo expandido para congresso nacional/internacional. A inadimplência bloqueia a emissão de certificados acadêmicos.</span>
            </div>
            <div class="clause-item">
                <span class="clause-checkbox">[✔]</span>
                <span class="clause-text"><strong>Cláusula de Dedicação e Exclusividade de Dados:</strong> Propriedade exclusiva de todos os dados gerados na pesquisa pertencente ao laboratório de orientação do Prof. Atilio.</span>
            </div>
            <div class="clause-item">
                <span class="clause-checkbox">[✔]</span>
                <span class="clause-text"><strong>Cláusula de Boas Práticas Científicas:</strong> Compromisso formal de não cometer plágio, falsificação de dados ou qualquer outra infração ética, sob pena de desligamento imediato e sanções regimentais.</span>
            </div>
        </div>

        <!-- Log de Auditoria Forense -->
        <div class="section-title">Registro Forense da Transação</div>
        <div class="forensic-box">
            <h3>LOG DE SEGURANÇA IMUTÁVEL</h3>
            <div class="forensic-line"><span>TIMESTAMP:</span> {{ $metadata['forensic_timestamp'] ?? 'N/A' }}</div>
            <div class="forensic-line"><span>ENDEREÇO IP:</span> {{ $metadata['forensic_ip'] ?? 'N/A' }}</div>
            <div class="forensic-line"><span>USER-AGENT:</span> {{ $metadata['forensic_user_agent'] ?? 'N/A' }}</div>
            <div class="forensic-line"><span>MATRÍCULA ALUNO:</span> ID {{ $student->id }} / RGM {{ $student->registration }}</div>
            <div class="forensic-line"><span>PROPRIETÁRIO:</span> {{ $user->name }}</div>
        </div>

        <!-- Rodapé do Log / Assinatura Digital -->
        <div class="hash-footer">
            <div class="hash-details">
                <div class="hash-title">Assinatura Digital (Checksum SHA-256)</div>
                <div class="hash-value">{{ $hash }}</div>
            </div>
            <img class="qr-code" src="{{ $qrCodeUrl }}" alt="Validação QR Code">
        </div>

        <!-- Botões de Ação -->
        <div class="actions-bar">
            <a href="{{ request()->fullUrlWithQuery(['download' => 1]) }}" class="btn btn-primary">Baixar PDF do Recibo</a>
            <button onclick="window.print()" class="btn btn-secondary">Imprimir Recibo</button>
            <a href="{{ url('/admin') }}" class="btn btn-secondary">Voltar ao Painel</a>
        </div>
    </div>

</body>
</html>
