<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Termo de Homologação e Assinatura Digital ICP-Brasil</title>
    <link rel="icon" href="{{ asset('images/favicon.png') }}" type="image/png">
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #1e293b;
            line-height: 1.5;
            margin: 0;
            padding: 20px;
            background-color: #ffffff;
        }
        .certificate-border {
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
        .prof-title {
            font-size: 24px;
            font-weight: bold;
            color: #0d9488;
            margin: 0 0 4px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .prof-subtitle {
            font-size: 11px;
            color: #64748b;
            margin: 0 0 15px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .main-title {
            font-size: 18px;
            font-weight: bold;
            color: #1e293b;
            margin: 10px 0 5px 0;
            letter-spacing: -0.5px;
        }
        .cert-id {
            font-size: 11px;
            color: #94a3b8;
            font-family: monospace;
        }
        .content {
            margin-bottom: 30px;
            font-size: 13.5px;
        }
        .content p {
            margin-bottom: 15px;
            text-align: justify;
        }
        .metadata-table {
            width: 100%;
            border-collapse: collapse;
            margin: 25px 0;
            font-size: 12px;
        }
        .metadata-table th, .metadata-table td {
            border: 1px solid #e2e8f0;
            padding: 10px 12px;
            text-align: left;
        }
        .metadata-table th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: bold;
            width: 30%;
        }
        .metadata-table td {
            color: #1e293b;
            font-family: 'Courier New', Courier, monospace;
        }
        .stamp-container {
            display: table;
            width: 100%;
            margin-top: 30px;
            border-top: 1px solid #f1f5f9;
            padding-top: 25px;
        }
        .stamp-left {
            display: table-cell;
            width: 70%;
            vertical-align: middle;
        }
        .stamp-right {
            display: table-cell;
            width: 30%;
            text-align: right;
            vertical-align: middle;
        }
        .qr-code {
            width: 110px;
            height: 110px;
            border: 1px solid #cbd5e1;
            padding: 4px;
            border-radius: 4px;
        }
        .legal-stamp {
            border: 2px solid #16a34a;
            border-radius: 6px;
            padding: 10px 15px;
            color: #15803d;
            display: inline-block;
            background-color: #f0fdf4;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }
        .legal-text {
            font-size: 10px;
            color: #64748b;
            line-height: 1.4;
        }
    </style>
</head>
<body>

    <div class="certificate-border">
        <div class="header">
            <div class="prof-title">profatilio.com.br</div>
            <div class="prof-subtitle">Sistema de Auditoria Acadêmica e Homologação Digital</div>
            <div class="main-title">TERMO DE AUDITORIA E HOMOLOGAÇÃO DE ASSINATURA DIGITAL</div>
            <div class="cert-id">Identificador do Termo: {{ hash('sha256', $submission->process_id . $metadata['forensic_timestamp']) }}</div>
        </div>

        <div class="content">
            <p>
                O presente documento certifica, para todos os fins de direito e validade jurídica, que o arquivo digital abaixo identificado foi submetido à plataforma profatilio.com.br contendo uma <strong>Assinatura Digital ICP-Brasil (A3/A1) ativa e íntegra</strong>, estando em total conformidade com a <strong>Medida Provisória nº 2.200-2/2001 (ICP-Brasil)</strong> e a <strong>Lei Federal nº 14.063/2020</strong>.
            </p>
            <p>
                O orientador Prof. Dr. Atilio Sersun Calefi atesta que o arquivo foi assinado criptograficamente antes de sua homologação no sistema, garantindo sua integridade (não adulteração) e o não repúdio da autoria do documento.
            </p>

            <table class="metadata-table">
                <tr>
                    <th>Código de Processo</th>
                    <td>{{ $submission->process_id }}</td>
                </tr>
                <tr>
                    <th>Tipo de Documento</th>
                    <td>{{ $metadata['document_description'] ?? 'Assinatura Avulsa' }}</td>
                </tr>
                <tr>
                    <th>Título de Referência</th>
                    <td>{{ $submission->title }}</td>
                </tr>
                <tr>
                    <th>Signatário Homologado</th>
                    <td>{{ $metadata['signer_name'] }}</td>
                </tr>
                @if($submission->student)
                <tr>
                    <th>Estudante / Autor</th>
                    <td>{{ $submission->student->user->name ?? 'N/A' }} (RGM: {{ $submission->student->registration ?? 'N/A' }})</td>
                </tr>
                @endif
                <tr>
                    <th>Nome do Arquivo Físico</th>
                    <td>{{ $metadata['original_filename'] }}</td>
                </tr>
                <tr>
                    <th>Tamanho do Arquivo</th>
                    <td>{{ $submission->file_size_kb }} KB</td>
                </tr>
                <tr>
                    <th>Checksum Hash (SHA-256)</th>
                    <td style="word-break: break-all; font-weight: bold;">{{ $hash }}</td>
                </tr>
                <tr>
                    <th>Data/Hora de Upload e Auditoria</th>
                    <td>{{ $metadata['forensic_timestamp'] }}</td>
                </tr>
                <tr>
                    <th>Endereço IP de Origem</th>
                    <td>{{ $metadata['forensic_ip'] }}</td>
                </tr>
            </table>
        </div>

        <div class="stamp-container">
            <div class="stamp-left">
                <div class="legal-stamp">
                    🛡️ Assinatura ICP-Brasil Auditada e Válida
                </div>
                <div class="legal-text">
                    A validade jurídica deste termo e da assinatura vinculada pode ser verificada de forma pública e gratuita a qualquer momento apontando a câmera do celular para o código QR ou acessando o validador da plataforma com a chave SHA-256 acima.<br>
                    <strong>Fundamentação Legal:</strong> MP nº 2.200-2/2001 e Lei nº 14.063/2020.
                </div>
            </div>
            <div class="stamp-right">
                <img src="{{ $qrCodeUrl }}" alt="QR Code de Verificação" class="qr-code">
            </div>
        </div>
    </div>

</body>
</html>
