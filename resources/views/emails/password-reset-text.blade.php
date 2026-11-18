Olá, {{ $userName }}.

Recebemos uma solicitação para redefinir a senha da sua conta em {{ config('app.name') }}.
Acesse o link abaixo para escolher uma nova senha. Este link expira em 60 minutos.

{{ $resetUrl }}

Se você não solicitou a redefinição de senha, pode ignorar este e-mail com segurança.

--
{{ config('app.name') }}
São Paulo, SP — Brasil
WhatsApp: (11) 99211-4308
contato@ijoifa.com
