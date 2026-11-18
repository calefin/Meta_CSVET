<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 14px; color: #1A1A1A; line-height: 1.5; }
        h1 { color: #2D6A4F; font-size: 24px; margin-bottom: 8px; border-bottom: 2px solid #2D6A4F; padding-bottom: 8px; }
        .details-box { background-color: #FAF7F0; border: 1px solid #7B4F2E/20; padding: 16px; border-radius: 6px; margin-top: 16px; }
        .totals { margin-top: 24px; font-size: 18px; color: #2D6A4F; font-weight: bold; }
        .payment-link { margin-top: 24px; padding: 12px; background-color: #E8F5E9; border: 1px solid #2D6A4F; border-radius: 4px; color: #2D6A4F; font-weight: bold; word-break: break-all; }
        .notes-box { margin-top: 16px; font-style: italic; color: #555; }
    </style>
</head>
<body>
    <h1>{{ config('app.name') }} — Orçamento</h1>
    
    <div class="details-box">
        <p><strong>Orçamento:</strong> #{{ $budget->id }}</p>
        <p><strong>Data:</strong> {{ $budget->created_at->format('d/m/Y') }}</p>
        <p><strong>Consulente:</strong> {{ $budget->user->name }}</p>
        <p><strong>Trabalho Espiritual:</strong> {{ $budget->recommendedWork->ritualWork->name }}</p>
    </div>

    @if ($budget->notes)
        <div class="notes-box">
            <p><strong>Observações:</strong> {{ $budget->notes }}</p>
        </div>
    @endif

    <div class="totals">
        Valor Final: R$ {{ number_format($budget->total, 2, ',', '.') }}
    </div>

    @php
        $payment = \App\Models\Payment::where('payable_type', \App\Models\RecommendedWork::class)
            ->where('payable_id', $budget->recommended_work_id)
            ->where('status', 'pending')
            ->latest()
            ->first();
    @endphp

    @if ($payment && $payment->checkout_url)
        <div class="payment-link">
            Link para Pagamento (Mercado Pago):<br>
            <a href="{{ $payment->checkout_url }}" style="color: #1B5E20; text-decoration: underline;">{{ $payment->checkout_url }}</a>
        </div>
    @endif
</body>
</html>
