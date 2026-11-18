<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }}</title>
</head>
<body style="margin:0; padding:0; background-color:#FAF7F0; font-family: Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#FAF7F0; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 480px; background-color:#FFFFFF; border-radius: 8px; overflow: hidden;">
                    <tr>
                        <td style="background-color:#2D6A4F; padding: 24px; text-align: center;">
                            <img src="{{ url('/icons/icon-192.png') }}" alt="{{ config('app.name') }}" width="56" height="56" style="display:block; margin: 0 auto 8px;">
                            <span style="color:#FFFFFF; font-size: 20px; font-weight: bold;">{{ config('app.name') }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 32px 28px; color:#1A1A1A; font-size: 15px; line-height: 1.6;">
                            {{ $slot }}
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#FAF7F0; padding: 20px 28px; text-align: center; color:#7B4F2E; font-size: 12px; line-height: 1.6;">
                            <p style="margin: 0 0 6px;">São Paulo, SP — Brasil</p>
                            <p style="margin: 0 0 6px;">
                                WhatsApp: <a href="https://wa.me/5511992114308" style="color:#2D6A4F; text-decoration: none;">(11) 99211-4308</a>
                                &nbsp;•&nbsp;
                                <a href="mailto:contato@ijoifa.com" style="color:#2D6A4F; text-decoration: none;">contato@ijoifa.com</a>
                            </p>
                            <p style="margin: 0;">&copy; {{ now()->year }} {{ config('app.name') }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
