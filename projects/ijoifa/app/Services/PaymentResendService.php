<?php

namespace App\Services;

use App\Mail\AdHocChargeMail;
use App\Mail\BudgetSentMail;
use App\Mail\ConsultationPaymentRequestMail;
use App\Mail\QuickPaymentLinkMail;
use App\Models\AdHocCharge;
use App\Models\ConsultationRequest;
use App\Models\Payment;
use App\Models\QuickPayment;
use App\Models\RecommendedWork;
use Illuminate\Support\Facades\Mail;

class PaymentResendService
{
    public static function resend(Payment $payment): bool
    {
        if ($payment->status !== 'pending' || ! $payment->user) {
            return false;
        }

        return match ($payment->payable_type) {
            ConsultationRequest::class => self::resendConsultationRequest($payment),
            AdHocCharge::class => self::resendAdHocCharge($payment),
            QuickPayment::class => self::resendQuickPayment($payment),
            RecommendedWork::class => self::resendRecommendedWork($payment),
            default => false,
        };
    }

    private static function resendConsultationRequest(Payment $payment): bool
    {
        $request = $payment->payable;

        if (! $request) {
            return false;
        }

        return self::send($payment, new ConsultationPaymentRequestMail($request, $payment->user, $payment));
    }

    private static function resendAdHocCharge(Payment $payment): bool
    {
        $charge = $payment->payable;

        if (! $charge) {
            return false;
        }

        return self::send($payment, new AdHocChargeMail($charge, $payment->user, $payment));
    }

    private static function resendQuickPayment(Payment $payment): bool
    {
        $quickPayment = $payment->payable;

        if (! $quickPayment) {
            return false;
        }

        return self::send($payment, new QuickPaymentLinkMail($quickPayment, $payment->user, $payment));
    }

    private static function resendRecommendedWork(Payment $payment): bool
    {
        $work = $payment->payable;
        $budget = $work?->budgets()->whereNotIn('status', ['cancelled'])->latest()->first();

        if (! $budget) {
            return false;
        }

        return self::send($payment, new BudgetSentMail($budget, $payment->user));
    }

    private static function send(Payment $payment, \Illuminate\Mail\Mailable $mailable): bool
    {
        try {
            Mail::to($payment->user->email)->send($mailable);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
