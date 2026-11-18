<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Lembrete de Renovação — Curso Telegram</title>
</head>
<body style="font-family: sans-serif; background-color: #F8F5F0; padding: 20px; color: #1E1E1E;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 12px; border: 1px solid #e0d8cd;">
        <h2 style="color: #2D6A4F; font-size: 20px; margin-top: 0;">Lembrete de Renovação do Curso Telegram</h2>
        <p>Olá, <strong>{{ $studentName }}</strong>!</p>
        <p>Escrevemos para lembrar que a mensalidade do seu <strong>Curso de Formação Contínua no Telegram</strong> precisa ser renovada para manter seu acesso ativo no grupo e garantir sua participação nos encontros presenciais.</p>

        <div style="background-color: #f4efe6; border-left: 4px solid #2D6A4F; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; font-size: 14px;"><strong>Usuário Telegram:</strong> {{ $telegramHandle }}</p>
            <p style="margin: 5px 0 0 0; font-size: 14px;"><strong>Valor da Mensalidade:</strong> R$ {{ $amount }}</p>
            <p style="margin: 5px 0 0 0; font-size: 14px;"><strong>Vencimento:</strong> {{ $expiresAt }}</p>
        </div>

        <p style="text-align: center; margin: 30px 0;">
            <a href="{{ $paymentUrl }}" style="background-color: #2D6A4F; color: #ffffff; text-decoration: none; padding: 12px 24px; font-weight: bold; border-radius: 8px; display: inline-block;">Pagar Mensalidade via Mercado Pago</a>
        </p>

        <p style="font-size: 12px; color: #666666;">Se você já efetuou o pagamento presencialmente ou via Pix direto, desconsidere esta mensagem.</p>
        <hr style="border: none; border-top: 1px solid #eeeeee; margin: 20px 0;">
        <p style="font-size: 12px; color: #888888; text-align: center;">Ìjọ Ifá Èjìogbè — Tradição e Aprendizado</p>
    </div>
</body>
</html>
