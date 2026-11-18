<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Acesse o grupo do Curso Telegram</title>
</head>
<body style="font-family: sans-serif; background-color: #F8F5F0; padding: 20px; color: #1E1E1E;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 12px; border: 1px solid #e0d8cd;">
        <h2 style="color: #2D6A4F; font-size: 20px; margin-top: 0;">Pagamento confirmado!</h2>
        <p>Olá, <strong>{{ $studentName }}</strong>!</p>
        <p>Recebemos a confirmação do seu pagamento. Use o link abaixo para entrar no grupo exclusivo do <strong>Curso de Formação Contínua no Telegram</strong>.</p>

        <p style="text-align: center; margin: 30px 0;">
            <a href="{{ $inviteLink }}" style="background-color: #2D6A4F; color: #ffffff; text-decoration: none; padding: 12px 24px; font-weight: bold; border-radius: 8px; display: inline-block;">Entrar no Grupo do Telegram</a>
        </p>

        <p style="font-size: 12px; color: #666666;">Este link é de uso único e expira em 24 horas — se já for membro do grupo, pode ignorá-lo.</p>
        @if ($expiresAt)
            <p style="font-size: 12px; color: #666666;">Sua mensalidade está válida até <strong>{{ $expiresAt }}</strong>.</p>
        @endif
        <hr style="border: none; border-top: 1px solid #eeeeee; margin: 20px 0;">
        <p style="font-size: 12px; color: #888888; text-align: center;">Ìjọ Ifá Èjìogbè — Tradição e Aprendizado</p>
    </div>
</body>
</html>
