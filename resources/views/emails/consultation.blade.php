<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Solicitação de Consultoria/Parceria</title>
    <style>
        /* Base Reset */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8fafc;
            color: #334155;
            margin: 0;
            padding: 0;
            width: 100% !important;
        }
        .wrapper {
            width: 100%;
            background-color: #f8fafc;
            padding: 40px 10px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -4px rgba(0, 0, 0, 0.05);
            border: 1px solid #f1f5f9;
        }
        .header {
            background: linear-gradient(135deg, #0f766e 0%, #115e59 100%);
            padding: 35px 40px;
            text-align: center;
            border-bottom: 4px solid #0d9488;
        }
        .header h1 {
            color: #ffffff;
            font-size: 22px;
            font-weight: 700;
            margin: 0;
            letter-spacing: -0.5px;
            line-height: 1.3;
        }
        .header p {
            color: #ccfbf1;
            font-size: 12px;
            margin: 6px 0 0 0;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 600;
        }
        .content {
            padding: 40px;
            line-height: 1.7;
            font-size: 15px;
        }
        .field-group {
            margin-bottom: 20px;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 15px;
        }
        .field-group:last-child {
            margin-bottom: 0;
            border-bottom: none;
            padding-bottom: 0;
        }
        .label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #64748b;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .value {
            font-size: 15px;
            color: #1e293b;
            font-weight: 500;
        }
        .message-box {
            background-color: #f0fdfa;
            border-left: 4px solid #0d9488;
            padding: 20px;
            border-radius: 8px;
            font-style: italic;
            color: #0f766e;
            white-space: pre-line;
            font-size: 14px;
            line-height: 1.6;
        }
        .footer {
            background-color: #f8fafc;
            padding: 30px 40px;
            text-align: center;
            font-size: 12px;
            color: #64748b;
            border-top: 1px solid #f1f5f9;
        }
        .footer p {
            margin: 0 0 8px 0;
            line-height: 1.5;
        }
        .footer a {
            color: #0f766e;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <div class="header">
                <h1>Nova Solicitação de Contato</h1>
                <p>Formulário de Consultoria / Parceria</p>
            </div>
            <div class="content">
                <div class="field-group">
                    <div class="label">Nome / Razão Social</div>
                    <div class="value">{{ $requestData->name }}</div>
                </div>
                
                <div class="field-group">
                    <div class="label">E-mail para Retorno</div>
                    <div class="value">
                        <a href="mailto:{{ $requestData->email }}" style="color: #0f766e; text-decoration: none; font-weight: 600;">{{ $requestData->email }}</a>
                    </div>
                </div>
                
                <div class="field-group">
                    <div class="label">Telefone / WhatsApp</div>
                    <div class="value">{{ $requestData->phone ?? 'Não informado' }}</div>
                </div>
                
                <div class="field-group">
                    <div class="label">Tipo de Solicitação</div>
                    <div class="value">{{ $requestData->request_type }}</div>
                </div>
                
                <div class="field-group">
                    <div class="label">Descrição da Proposta / Detalhes</div>
                    <div class="message-box">{{ $requestData->message }}</div>
                </div>
            </div>
            <div class="footer">
                <p>Este e-mail foi enviado automaticamente a partir do formulário de contato de <a href="https://profatilio.com.br">profatilio.com.br</a>.</p>
                <p>&copy; {{ date('Y') }} Prof. Atilio Calefi. Todos os direitos reservados.</p>
            </div>
        </div>
    </div>
</body>
</html>
