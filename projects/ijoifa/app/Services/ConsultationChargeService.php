<?php

namespace App\Services;

use App\Models\ConsultationRequest;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Shared by the automatic charge fired right when a client submits a
 * consultation request and by the admin's manual "confirm" retry in
 * RequestsQueue (used when the automatic attempt failed, e.g. a transient
 * Mercado Pago API error) — one place for the status transition + payment
 * creation so the two paths can't drift apart.
 */
class ConsultationChargeService
{
    /**
     * Catches \Throwable, not just \RuntimeException: this runs inline in
     * the public submission flow (PublicConsultationRequest::submit()),
     * and an uncaught exception here (e.g. a schema mismatch from a pending
     * migration) would abort that method before it reaches the admin
     * notification email — silently breaking both the payment link and the
     * admin alert at once. Logging it here is what makes that failure
     * visible instead of just "the button didn't do anything".
     */
    public static function charge(ConsultationRequest $request, float $amount): ?Payment
    {
        try {
            return DB::transaction(function () use ($request, $amount) {
                $request->update(['status' => 'awaiting_payment']);

                $payment = Payment::findOrCreatePending(
                    ConsultationRequest::class,
                    $request->id,
                    $request->user_id,
                    $amount
                );

                if (MercadoPagoService::configured() && ! $payment->checkout_url) {
                    $url = (new MercadoPagoService())->createPreference($payment, 'Consulta oracular — '.config('app.name'));

                    if ($url === null) {
                        throw new \RuntimeException('mercadopago_preference_failed');
                    }
                }

                return $payment;
            });
        } catch (\Throwable $e) {
            Log::error('Consultation charge failed', [
                'consultation_request_id' => $request->id,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
