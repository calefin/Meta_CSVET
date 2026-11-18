<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class PaymentMarkPaidService
{
    public static function markPaid(Payment $payment): void
    {
        $payment->update(['status' => 'paid', 'paid_at' => now()]);
        PaymentFulfillment::apply($payment);
    }

    public static function markPaidById(int $paymentId): ?Payment
    {
        return DB::transaction(function () use ($paymentId) {
            $payment = Payment::where('id', $paymentId)->where('status', 'pending')->lockForUpdate()->first();

            if (! $payment) {
                return null;
            }

            self::markPaid($payment);

            return $payment;
        });
    }

    public static function markPaidForPayable(string $payableType, int $payableId): ?Payment
    {
        return DB::transaction(function () use ($payableType, $payableId) {
            $payment = Payment::where('payable_type', $payableType)
                ->where('payable_id', $payableId)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                return null;
            }

            self::markPaid($payment);

            return $payment;
        });
    }
}
