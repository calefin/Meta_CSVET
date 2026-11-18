<?php

namespace App\Console\Commands;

use App\Mail\TelegramCoursePaymentReminderMail;
use App\Models\Payment;
use App\Models\TelegramCourseSubscription;
use App\Services\MercadoPagoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendSubscriptionReminders extends Command
{
    protected $signature = 'subscriptions:send-reminders';

    protected $description = 'Dispara lembretes automáticos de renovação de mensalidade para alunos do Curso Telegram prestes a vencer';

    public function handle(): int
    {
        $this->info('Verificando mensalidades do Curso Telegram prestes a vencer...');

        $expiringSubscriptions = TelegramCourseSubscription::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(3))
            ->get();

        $count = 0;

        foreach ($expiringSubscriptions as $sub) {
            try {
                $payment = Payment::findOrCreatePending(
                    payableType: TelegramCourseSubscription::class,
                    payableId: $sub->id,
                    userId: $sub->user_id ?? 1,
                    amount: 180.00,
                    scopeToUser: false
                );

                $mpService = new MercadoPagoService;
                $url = $mpService->createPreference($payment, 'Mensalidade Curso Telegram — '.$sub->full_name, maxInstallments: 1);
                $paymentUrl = $url ?? $payment->checkout_url ?? route('public-telegram-course-registration');

                Mail::to($sub->email)->send(new TelegramCoursePaymentReminderMail($sub, $paymentUrl));
                $count++;
            } catch (\Throwable $e) {
                Log::error("Erro ao enviar lembrete automático de mensalidade para {$sub->email}: ".$e->getMessage());
            }
        }

        $this->info("Concluído! {$count} lembrete(s) enviado(s).");

        return Command::SUCCESS;
    }
}
