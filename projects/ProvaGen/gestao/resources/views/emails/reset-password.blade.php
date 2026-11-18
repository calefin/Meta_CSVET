<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Redefinição de Senha — Prof-Aid</title>
    <style>
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        body { margin: 0; padding: 0; background-color: #f1f5f9; width: 100% !important; }
        .wrapper { width: 100%; background-color: #f1f5f9; padding: 40px 16px; box-sizing: border-box; }
        .container { max-width: 580px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 40px -10px rgba(0,0,0,0.10); border: 1px solid #e2e8f0; }
        .header { background: linear-gradient(135deg, #0f766e 0%, #0e7490 100%); padding: 36px 40px 28px; text-align: center; }
        .header h1 { color: #ffffff; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 20px; font-weight: 700; margin: 0 0 4px; letter-spacing: -0.3px; }
        .header p { color: #99f6e4; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 12px; margin: 0; font-weight: 600; letter-spacing: 1.5px; text-transform: uppercase; }
        .body { padding: 36px 40px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .body p { font-size: 15px; line-height: 1.7; color: #475569; margin: 0 0 18px; }
        .button-wrap { text-align: center; margin: 32px 0 28px; }
        .button {
            display: inline-block;
            background-color: #0f766e;
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 40px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 15px;
            font-weight: 700;
            border-radius: 8px;
            box-shadow: 0 4px 14px rgba(15,118,110,0.25);
        }
        .url-fallback { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; margin: 0 0 18px; word-break: break-all; }
        .url-fallback p { font-size: 11px; color: #94a3b8; margin: 0 0 4px; }
        .url-fallback a { font-size: 11px; color: #0f766e; word-break: break-all; }
        .warning { background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 12px 16px; margin: 18px 0 0; }
        .warning p { font-size: 13px; color: #92400e; margin: 0; line-height: 1.5; }
        .footer { background: #f8fafc; padding: 24px 40px; text-align: center; border-top: 1px solid #f1f5f9; }
        .footer p { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 11px; color: #94a3b8; margin: 0 0 5px; line-height: 1.6; }
        .footer a { color: #0f766e; text-decoration: none; font-weight: 600; }
        @media only screen and (max-width: 580px) {
            .container { border-radius: 0 !important; }
            .header, .body, .footer { padding-left: 24px !important; padding-right: 24px !important; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="container">

        <div class="header">
            <p>Prof-Aid — Gestão de Alunos</p>
            <h1>🔐 Redefinição de Senha</h1>
        </div>

        <div class="body">
            <p>Olá, <strong>{{ $name }}</strong>!</p>

            <p>Recebemos uma solicitação para redefinir a senha da sua conta na plataforma <strong>prof-aid.com</strong>. Clique no botão abaixo para criar uma nova senha:</p>

            <div class="button-wrap">
                <a href="{{ $resetUrl }}" class="button" target="_blank">
                    Redefinir Minha Senha
                </a>
            </div>

            <div class="url-fallback">
                <p>Se o botão não funcionar, copie e cole este link no navegador:</p>
                <a href="{{ $resetUrl }}">{{ $resetUrl }}</a>
            </div>

            <div class="warning">
                <p>
                    ⏱ Este link expira em <strong>24 horas</strong> e só pode ser usado <strong>uma única vez</strong>.<br>
                    Se você não solicitou a redefinição, ignore este e-mail — sua senha permanece inalterada.
                </p>
            </div>
        </div>

        <div class="footer">
            <p>Este e-mail foi enviado pela plataforma <a href="https://prof-aid.com">prof-aid.com</a>.</p>
            <p>&copy; {{ date('Y') }} Prof-Aid. Todos os direitos reservados.</p>
        </div>

    </div>
</div>
</body>
</html>
