<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Recibo de Pagamento — Mensalidade Curso Telegram</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #1e1e1e; font-size: 13px; margin: 0; padding: 20px; }
        .header { text-align: center; border-bottom: 2px solid #2D6A4F; padding-bottom: 15px; margin-bottom: 20px; }
        .title { font-size: 20px; font-weight: bold; color: #2D6A4F; margin: 0; }
        .subtitle { font-size: 12px; color: #666; margin-top: 5px; }
        .section { background: #fdfbf7; border: 1px solid #e5dfd5; border-radius: 6px; padding: 15px; margin-between: 15px; margin-bottom: 20px; }
        .field { margin-bottom: 8px; }
        .label { font-weight: bold; color: #444; }
        .amount-box { background: #e8f4ec; border: 1px solid #2D6A4F; border-radius: 6px; padding: 15px; text-align: center; margin-top: 20px; }
        .amount { font-size: 22px; font-weight: bold; color: #2D6A4F; }
        .footer { margin-top: 40px; text-align: center; font-size: 11px; color: #888; border-top: 1px solid #ddd; padding-top: 15px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">Ìjọ Ifá Èjìogbè</div>
        <div class="subtitle">Comprovante de Recibo — Mensalidade do Curso Telegram</div>
    </div>

    <div class="section">
        <div class="field"><span class="label">Nº do Pagamento:</span> #{{ $payment->id }}</div>
        <div class="field"><span class="label">Data de Confirmação:</span> {{ $payment->paid_at ? $payment->paid_at->format('d/m/Y H:i') : $payment->created_at->format('d/m/Y H:i') }}</div>
        <div class="field"><span class="label">Método:</span> {{ $payment->paymentMethodLabel() ?? 'Mercado Pago' }}</div>
        <div class="field"><span class="label">Status:</span> {{ strtoupper($payment->status) }}</div>
    </div>

    <div class="section">
        <div class="field"><span class="label">Aluno:</span> {{ $subscription->full_name }}</div>
        <div class="field"><span class="label">E-mail:</span> {{ $subscription->email }}</div>
        <div class="field"><span class="label">Telegram:</span> {{ $subscription->telegram_username }}</div>
        <div class="field"><span class="label">Novo Vencimento do Acesso:</span> {{ $subscription->expires_at ? $subscription->expires_at->format('d/m/Y') : 'Ativo' }}</div>
    </div>

    <div class="amount-box">
        <div style="font-size: 12px; color: #444; margin-bottom: 5px;">Valor Quitado:</div>
        <div class="amount">R$ {{ number_format($payment->amount, 2, ',', '.') }}</div>
    </div>

    <div class="footer">
        <p>Este recibo é emitido eletronicamente e comprova a quitação da mensalidade do Curso de Formação Contínua no Telegram.</p>
        <p>&copy; {{ date('Y') }} Ìjọ Ifá Èjìogbè</p>
    </div>
</body>
</html>
