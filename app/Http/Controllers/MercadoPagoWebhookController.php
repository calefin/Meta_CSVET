<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\MercadoPagoService;
use App\Services\PaymentFulfillment;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MercadoPagoWebhookController extends Controller
{
    /**
     * How old a signed webhook's "ts" is allowed to be before it's rejected
     * as a replay of a previously captured (and otherwise still valid)
     * signature. Mercado Pago recommends a tolerance window rather than
     * exact matching, since delivery is never instantaneous.
     */
    private const SIGNATURE_MAX_AGE_SECONDS = 300;

    public function __invoke(Request $request, MercadoPagoService $mercadoPago): JsonResponse
    {
        $type = $request->input('type') ?? $request->input('topic');
        $mpPaymentId = $request->input('data.id') ?? $request->input('id');

        if ($type !== 'payment' || ! $mpPaymentId || ! MercadoPagoService::configured()) {
            return response()->json(['ignored' => true]);
        }

        if (! $this->hasValidSignature($request, (string) $mpPaymentId)) {
            abort(403, 'Invalid webhook signature.');
        }

        // Even with a valid signature, we never trust the request body for
        // the actual status/amount - always re-fetch from the Mercado Pago
        // API itself, so a forged or replayed payload can't fake approval.
        $mpPayment = $mercadoPago->fetchPayment($mpPaymentId);

        $payment = Payment::find($mpPayment->external_reference);

        if (! $payment) {
            return response()->json(['error' => 'payment not found'], 404);
        }

        // Locks the row for the duration of the transaction so two webhook
        // deliveries for the same payment (Mercado Pago retries on timeout,
        // or a manual resend from their dashboard) can't both read
        // status = 'pending' and both apply fulfillment.
        DB::transaction(function () use ($payment, $mpPayment, $mpPaymentId) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->first();

            $feeAmount = collect($mpPayment->fee_details ?? [])->sum(fn ($fee) => is_object($fee) ? ($fee->amount ?? 0) : ($fee['amount'] ?? 0));

            $payment->update([
                'mp_payment_id' => (string) $mpPaymentId,
                'mp_fee_amount' => $feeAmount ?: null,
                'mp_net_amount' => $mpPayment->transaction_details?->net_received_amount ?? null,
                'mp_payment_type' => $mpPayment->payment_type_id ?? null,
                'mp_payment_method' => $mpPayment->payment_method_id ?? null,
            ]);

            if ($mpPayment->status === 'approved' && $payment->status !== 'paid') {
                $payment->update(['status' => 'paid', 'paid_at' => now()]);
                PaymentFulfillment::apply($payment);
            } elseif (in_array($mpPayment->status, ['rejected', 'cancelled'], true)) {
                $payment->update(['status' => 'failed']);
            }
        });

        Audit::record('mercadopago.webhook', $payment);

        return response()->json(['ok' => true]);
    }

    /**
     * Verifies Mercado Pago's x-signature header (HMAC-SHA256 over
     * "id:{id};request-id:{request-id};ts:{ts};") when a webhook secret
     * is configured. Skipped gracefully when it isn't, since this is an
     * additional layer on top of the API re-fetch above (which is the
     * real source of truth), not the only line of defense.
     */
    private function hasValidSignature(Request $request, string $mpPaymentId): bool
    {
        $secret = config('services.mercadopago.webhook_secret');

        if (! $secret) {
            return true;
        }

        $signatureHeader = $request->header('x-signature');
        $requestId = $request->header('x-request-id');

        if (! $signatureHeader || ! $requestId) {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $piece) {
            [$key, $value] = array_pad(explode('=', $piece, 2), 2, null);
            $parts[trim((string) $key)] = trim((string) $value);
        }

        if (empty($parts['ts']) || empty($parts['v1'])) {
            return false;
        }

        if (! ctype_digit((string) $parts['ts']) || abs(time() - (int) $parts['ts']) > self::SIGNATURE_MAX_AGE_SECONDS) {
            return false;
        }

        $manifest = "id:{$mpPaymentId};request-id:{$requestId};ts:{$parts['ts']};";
        $expected = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($expected, $parts['v1']);
    }
}
