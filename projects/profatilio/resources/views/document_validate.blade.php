<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Validador de Assinatura Digital - {{ request()->getHost() }}</title>
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
            background-color: #0f172a;
            color: #f8fafc;
            line-height: 1.5;
            padding: 40px 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            max-width: 600px;
            width: 100%;
            background-color: #1e293b;
            border: 1px solid #334155;
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
            padding: 40px;
            text-align: center;
        }
        .header h1 {
            font-size: 20px;
            color: #0d9488;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 25px;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 24px;
            border-radius: 9999px;
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 25px;
            gap: 8px;
        }
        .badge-success {
            background-color: #022c22;
            color: #4ade80;
            border: 1px solid #064e3b;
        }
        .badge-danger {
            background-color: #450a0a;
            color: #f87171;
            border: 1px solid #7f1d1d;
        }
        .status-icon {
            font-size: 18px;
        }
        .message {
            font-size: 15px;
            color: #94a3b8;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .cert-details {
            background-color: #0f172a;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 20px;
            text-align: left;
            margin-bottom: 30px;
        }
        .cert-item {
            margin-bottom: 12px;
            border-bottom: 1px solid #1e293b;
            padding-bottom: 12px;
        }
        .cert-item:last-child {
            margin-bottom: 0;
            border-bottom: none;
            padding-bottom: 0;
        }
        .cert-item label {
            display: block;
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .cert-item span {
            font-size: 14px;
            color: #f1f5f9;
            font-weight: 550;
            word-break: break-all;
        }
        .cert-item span.hash-highlight {
            color: #0d9488;
            font-family: monospace;
            font-size: 13px;
        }
        .footer-text {
            font-size: 12px;
            color: #64748b;
        }
        .btn-home {
            display: inline-flex;
            margin-top: 25px;
            padding: 10px 20px;
            background-color: #0d9488;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.2s ease-in-out;
        }
        .btn-home:hover {
            background-color: #0f766e;
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="header">
            <h1>{{ request()->getHost() }}</h1>
        </div>

        @if($submission)
            <!-- Assinatura Válida -->
            <div class="badge badge-success">
                <span class="status-icon">✔</span>
                Assinatura Digital Valida
            </div>

            <div class="message">
                Certificamos que o termo abaixo foi assinado digitalmente pelo orientando e auditado forensemente pelo sistema em conformidade com as diretrizes da plataforma.
            </div>

            @php
                // LGPD Masking helpers
                $student = $submission->student;
                $user = $student ? $student->user : null;
                
                // Mask Name: First and Last only
                $rawName = $user ? $user->name : 'Dr. Atilio Sersun Calefi (Professor/Orientador)';
                $nameParts = explode(' ', trim($rawName));
                $maskedName = count($nameParts) > 1 ? $nameParts[0] . ' ' . end($nameParts) : $rawName;
                
                // Mask RGM: show first 3 and mask the rest
                $rgm = $student ? ($student->registration ?? 'N/A') : 'N/A';
                $maskedRgm = strlen($rgm) > 3 ? substr($rgm, 0, 3) . '****' : '***';
                
                // Mask Email: a*****i@g***l.com
                $email = $user ? ($user->email ?? '') : '';
                if ($email && strpos($email, '@') !== false) {
                    list($first, $domain) = explode('@', $email);
                    $maskedFirst = substr($first, 0, 1) . str_repeat('*', max(1, strlen($first) - 2)) . substr($first, -1);
                    list($domainName, $domainExt) = explode('.', $domain, 2);
                    $maskedDomain = substr($domainName, 0, 1) . str_repeat('*', max(1, strlen($domainName) - 2)) . substr($domainName, -1);
                    $maskedEmail = $maskedFirst . '@' . $maskedDomain . '.' . $domainExt;
                } else {
                    $maskedEmail = 'Orientador';
                }

                // Mask IP: 192.168.***.***
                $ip = $submission->metadata['forensic_ip'] ?? '127.0.0.1';
                $ipParts = explode('.', $ip);
                if (count($ipParts) === 4) {
                    $maskedIp = $ipParts[0] . '.' . $ipParts[1] . '.***.***';
                } else {
                    // IPv6
                    $maskedIp = substr($ip, 0, 9) . '::****';
                }
            @endphp

            <div class="cert-details">
                <div class="cert-item">
                    <label>Código do Processo</label>
                    <span>{{ $submission->process_id }}</span>
                </div>
                <div class="cert-item">
                    <label>Título do Projeto</label>
                    <span>{{ $submission->title }}</span>
                </div>
                <div class="cert-item">
                    <label>Signatário / Orientador (LGPD Mask)</label>
                    <span>{{ $maskedName }} {{ $student ? "(RGM: {$maskedRgm})" : '' }}</span>
                </div>
                <div class="cert-item">
                    <label>Identificação / Perfil</label>
                    <span>{{ $maskedEmail }}</span>
                </div>
                <div class="cert-item">
                    <label>Instituição / Campus</label>
                    <span>{{ $student ? "{$student->institution} - {$student->campus}" : request()->getHost() }}</span>
                </div>
                <div class="cert-item">
                    <label>IP de Origem (LGPD Mask)</label>
                    <span>{{ $maskedIp }}</span>
                </div>
                <div class="cert-item">
                    <label>Timestamp Imutável</label>
                    <span>{{ $submission->metadata['forensic_timestamp'] ?? 'N/A' }} (Horário de Brasília)</span>
                </div>
                <div class="cert-item">
                    <label>Assinatura Digital (SHA-256)</label>
                    <span class="hash-highlight">{{ $hash }}</span>
                </div>
            </div>

            <div class="footer-text">
                Código de verificação permanente gerado no servidor. Registro de transação criptograficamente íntegro.
            </div>
        @else
            <!-- Assinatura Inválida / Adulterada -->
            <div class="badge badge-danger">
                <span class="status-icon">✖</span>
                Assinatura Invalida ou Inexistente
            </div>

            <div class="message" style="color: #f87171;">
                O código de assinatura digital fornecido não foi localizado no banco de dados oficial do sistema ou os registros correspondentes foram alterados.
            </div>

            <div class="cert-details" style="background-color: #450a0a/10; border-color: #7f1d1d;">
                <div class="cert-item" style="border-bottom: none;">
                    <label>Hash Informado</label>
                    <span style="font-family: monospace; color: #f87171; font-weight: bold;">{{ $hash }}</span>
                </div>
            </div>

            <div class="footer-text">
                Se você acredita que trata-se de um erro, certifique-se de escanear o QR Code oficial impresso no rodapé do documento de auditoria homologado.
            </div>
        @endif

        <div>
            <a href="{{ url('/') }}" class="btn-home">Ir para {{ request()->getHost() }}</a>
        </div>
    </div>

</body>
</html>
