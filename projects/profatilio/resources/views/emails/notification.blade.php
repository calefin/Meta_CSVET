<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Notificação - Prof. Atilio' }}</title>
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
        .content p {
            margin: 0 0 20px 0;
            color: #475569;
        }
        .content p:last-child {
            margin-bottom: 0;
        }
        .content .highlight-box {
            background-color: #f0fdfa;
            border-left: 4px solid #0d9488;
            padding: 20px;
            border-radius: 8px;
            margin: 25px 0;
            font-size: 14px;
            color: #0f766e;
            line-height: 1.6;
        }
        .button-container {
            text-align: center;
            margin: 35px 0 10px 0;
        }
        .button {
            background-color: #0f766e;
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 35px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 8px;
            display: inline-block;
            box-shadow: 0 4px 10px rgba(15, 118, 110, 0.2);
            border: 1px solid #0d9488;
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
        .footer p:last-child {
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <div class="header">
                <h1>{{ $title }}</h1>
                <p>Plataforma profatilio.com.br</p>
            </div>
            <div class="content">
                @foreach (explode("\n", $messageContent) as $paragraph)
                    @if (trim($paragraph) !== '')
                        @if (str_starts_with(trim($paragraph), 'Processo [') || str_starts_with(trim($paragraph), 'Aviso:'))
                            <div class="highlight-box">
                                {!! nl2br(\App\Support\SimpleMarkdown::toHtml(trim($paragraph))) !!}
                            </div>
                        @else
                            <p>{!! nl2br(\App\Support\SimpleMarkdown::toHtml(trim($paragraph))) !!}</p>
                        @endif
                    @endif
                @endforeach

                @if (!empty($parecerHtml))
                    <div style="margin:24px 0 0;padding:16px 20px;background:#f0fdfa;border-left:4px solid #0d9488;border-radius:0 8px 8px 0;">
                        <p style="margin:0 0 10px;font-size:12px;font-weight:700;color:#0f766e;text-transform:uppercase;letter-spacing:1px;">💬 Parecer do Orientador</p>
                        <div style="font-size:14px;color:#0f766e;line-height:1.7;">
                            {!! $parecerHtml !!}
                        </div>
                    </div>
                @endif

                @if (isset($actionUrl))
                    <div class="button-container">
                        <a href="{{ $actionUrl }}" class="button" target="_blank">{{ $actionText ?? 'Acessar Painel' }}</a>
                    </div>
                @endif
            </div>
            <div class="footer">
                <p>Este é um e-mail automático enviado pela Plataforma <a href="https://profatilio.com.br">profatilio.com.br</a>.</p>
                <p>&copy; {{ date('Y') }} Prof. Atilio Calefi. Todos os direitos reservados.</p>
            </div>
        </div>
    </div>
</body>
</html>
