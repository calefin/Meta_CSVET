<?php

namespace App\Services;

use App\Mail\PaymentConfirmedAdminMail;
use App\Mail\TelegramCourseGroupInviteMail;
use App\Models\ConsultationRequest;
use App\Models\Course;
use App\Models\Payment;
use App\Models\QuickPayment;
use App\Models\RecommendedWork;
use App\Models\TelegramCourseSubscription;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Single place that applies the side effect of a payment being confirmed,
 * regardless of whether confirmation came from the Mercado Pago webhook or
 * a manual admin action (cash/PIX paid outside the platform). Keeps both
 * paths consistent instead of duplicating the status transitions.
 */
class PaymentFulfillment
{
    public static function apply(Payment $payment): void
    {
        match ($payment->payable_type) {
            ConsultationRequest::class => ConsultationRequest::where('id', $payment->payable_id)->update(['status' => 'paid']),
            RecommendedWork::class => RecommendedWork::where('id', $payment->payable_id)->update(['status' => 'approved']),
            Course::class => Course::find($payment->payable_id)?->enrollments()->firstOrCreate(
                ['user_id' => $payment->user_id],
                ['enrolled_at' => now(), 'source' => 'purchase']
            ),
            QuickPayment::class => self::fulfillQuickPayment($payment),
            TelegramCourseSubscription::class => self::fulfillTelegramCourseSubscription($payment),
            default => null,
        };

        Audit::record('payment.fulfilled', $payment);

        // Send notification email to active admins. Wrapped so an SMTP
        // failure never bubbles up past this point - the business side
        // effects above are already committed, and this call happens
        // synchronously inside the Mercado Pago webhook request: an
        // uncaught exception here would make the webhook respond with a
        // non-2xx status, causing Mercado Pago to retry - but the retry
        // would be a no-op (status is already 'paid'), so the email would
        // be lost silently either way. Catching it here at least lets the
        // webhook still return 200 as expected.
        try {
            $admins = User::activeAdmins();
            if ($admins->isNotEmpty()) {
                Mail::to($admins)->send(new PaymentConfirmedAdminMail($payment));
            }
        } catch (\Exception $e) {
            // Silently ignore mail driver issues, same pattern used elsewhere.
        }
    }

    private static function fulfillQuickPayment(Payment $payment): void
    {
        $qp = QuickPayment::find($payment->payable_id);

        // Guards against duplicate fulfillment (e.g. two concurrent webhook
        // deliveries for the same payment slipping past the caller's own
        // status check) creating a second ConsultationRequest for the
        // same link.
        if (! $qp || $qp->status === 'paid') {
            return;
        }

        $qp->update(['status' => 'paid', 'user_id' => $payment->user_id]);
        if ($qp->type === 'consulta') {
            ConsultationRequest::create([
                'user_id' => $payment->user_id,
                'modality' => 'online',
                'description' => $qp->notes ?? 'Consulta via link rápido',
                'status' => 'paid',
            ]);
        }
    }

    private static function fulfillTelegramCourseSubscription(Payment $payment): void
    {
        $sub = TelegramCourseSubscription::find($payment->payable_id);
        if (! $sub) {
            return;
        }

        $newExpiry = TelegramCourseSubscription::nextDueDate($sub->expires_at);

        $sub->update([
            'status' => 'active',
            'expires_at' => $newExpiry,
            'user_id' => $sub->user_id ?? $payment->user_id,
        ]);

        TelegramBotService::sendSubscriptionConfirmedNotification($sub, (float) $payment->amount);

        self::sendGroupInviteEmail($sub);
    }

    // Wrapped in try/catch, same as the admin notification below: the
    // subscription is already active at this point (committed above), so a
    // Telegram API hiccup or SMTP failure here must never bubble up and
    // break the Mercado Pago webhook response.
    private static function sendGroupInviteEmail(TelegramCourseSubscription $sub): void
    {
        $groupChatId = config('services.telegram.course_group_chat_id');
        if (! $groupChatId) {
            Log::error("Não foi possível enviar convite do grupo do Telegram para {$sub->email}: TELEGRAM_COURSE_GROUP_CHAT_ID não configurado.");

            return;
        }

        try {
            $inviteLink = TelegramBotService::createChatInviteLink($groupChatId, 86400, 1);

            if (! $inviteLink) {
                Log::error("Não foi possível gerar o link de convite do grupo do Telegram para {$sub->email}.");

                return;
            }

            Mail::to($sub->email)->send(new TelegramCourseGroupInviteMail($sub, $inviteLink));
        } catch (\Throwable $e) {
            Log::error("Erro ao enviar convite do grupo do Telegram para {$sub->email}: ".$e->getMessage());
        }
    }
}
