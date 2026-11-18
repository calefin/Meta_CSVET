<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\TelegramCourseSubscription;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TelegramSubscriptionReceiptPdfController extends Controller
{
    public function __invoke(Request $request, Payment $payment): Response
    {
        abort_unless(
            $payment->payable_type === TelegramCourseSubscription::class,
            404
        );

        $subscription = TelegramCourseSubscription::findOrFail($payment->payable_id);

        abort_unless(
            $request->user()->isAdmin() || $subscription->user_id === $request->user()->id || $payment->user_id === $request->user()->id,
            403
        );

        $pdf = Pdf::loadView('pdf.telegram-subscription-receipt', [
            'payment' => $payment,
            'subscription' => $subscription,
        ]);

        return $pdf->stream("recibo-mensalidade-{$payment->id}.pdf");
    }
}
