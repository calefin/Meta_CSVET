<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Termo de Assinatura Digital - Gov.br</title>
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
        .coat-of-arms {
            width: 70px;
            height: 70px;
            margin-bottom: 12px;
            display: inline-block;
        }
        .gov-title {
            font-size: 13px;
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
            <!-- Brasão da República Simulado via SVG nativo embutido para compatibilidade total com Dompdf -->
            <svg class="coat-of-arms" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:block; margin:0 auto 10px auto;">
                <circle cx="50" cy="50" r="45" stroke="#009c3b" stroke-width="2" fill="#e0f2fe" />
                <path d="M50 15 L56 35 L76 35 L60 47 L66 67 L50 55 L34 67 L40 47 L24 35 L44 35 Z" fill="#ffdf00" stroke="#002776" stroke-width="1.5" />
                <circle cx="50" cy="45" r="8" fill="#002776" />
                <line x1="42" y1="45" x2="58" y2="45" stroke="#ffffff" stroke-width="1" />
            </svg>
            <div class="gov-title">Ministério da Gestão e da Inovação em Serviços Públicos</div>
            <div class="gov-subtitle">Instituto Nacional de Tecnologia da Informação - ITI</div>
            <div class="main-title">TERMO DE ASSINATURA ELETRÔNICA E HOMOLOGAÇÃO DIGITAL</div>
            <div class="cert-id">Identificador do Termo: {{ hash('sha256', $submission->process_id . $sig['signed_at']) }}</div>
        </div>

        <div class="content">
            <p>
                O presente documento certifica, para todos os fins de direito e validade jurídica, que o arquivo acadêmico original abaixo identificado foi analisado e **assinado eletronicamente de forma avançada** utilizando a infraestrutura da Conta Digital única <strong>gov.br</strong>, em total conformidade com a <strong>Lei Federal nº 14.063 de 23 de setembro de 2020</strong> e com a Medida Provisória nº 2.200-2/2001.
            </p>
            <p>
                Através da presente homologação digital, o orientador atesta a aprovação, a validade e a integridade de integridade do documento enviado para a plataforma de orientação acadêmica profatilio.com.br.
            </p>

            <table class="metadata-table">
                <tr>
                    <th>Processo ID</th>
                    <td>{{ $submission->process_id }}</td>
                </tr>
                <tr>
                    <th>Tipo de Documento</th>
                    <td>{{ $submission->document_type }}</td>
                </tr>
                <tr>
                    <th>Título do Trabalho</th>
                    <td>{{ $submission->title }}</td>
                </tr>
                <tr>
                    <th>Estudante / Autor</th>
                    <td>{{ $student->user->name ?? 'N/A' }} (RGM: {{ $student->registration ?? 'N/A' }})</td>
                </tr>
                <tr>
                    <th>Signatário (Orientador)</th>
                    <td>{{ $sig['signer_name'] }} (CPF: {{ $sig['signer_cpf'] }})</td>
                </tr>
                <tr>
                    <th>Hash SHA-256 do Original</th>
                    <td style="word-break: break-all;">{{ $sig['original_pdf_hash'] }}</td>
                </tr>
                <tr>
                    <th>Data/Hora de Assinatura</th>
                    <td>{{ $sig['signed_at'] }}</td>
                </tr>
                <tr>
                    <th>Endereço IP / Origem</th>
                    <td>{{ $sig['ip'] }}</td>
                </tr>
                @if(isset($sig['is_mock']) && $sig['is_mock'])
                <tr>
                    <th>Ambiente de Assinatura</th>
                    <td style="color:#b45309; font-weight:bold;">HOMOLOGAÇÃO / SANDBOX SIMULADO</td>
                </tr>
                @endif
            </table>
        </div>

        <div class="stamp-container">
            <div class="stamp-left">
                <div class="legal-stamp">
                    🛡️ Assinado Eletronicamente via Gov.br
                </div>
                <div class="legal-text">
                    A validade jurídica deste termo e da assinatura vinculada pode ser verificada publicamente de forma gratuita a qualquer momento apontando a câmera do celular para o código QR ou acessando o validador da plataforma com a chave SHA-256 acima.<br>
                    <strong>Fundamentação Legal:</strong> Lei nº 14.063/2020.
                </div>
            </div>
            <div class="stamp-right">
                <img src="{{ $qrCodeUrl }}" alt="QR Code de Verificação" class="qr-code">
            </div>
        </div>
    </div>

</body>
</html>
