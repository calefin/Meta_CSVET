<?php

namespace App\Mail;

use App\Models\TelegramCourseSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TelegramCoursePaymentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public TelegramCourseSubscription $subscription,
        public string $paymentUrl
    ) {}

    public function build(): self
    {
        return $this->subject('Lembrete de Renovação — Curso de Formação Contínua no Telegram')
            ->view('emails.telegram-course-reminder')
            ->text('emails.telegram-course-reminder-text')
            ->with([
                'studentName' => $this->subscription->full_name,
                'telegramHandle' => $this->subscription->telegram_username,
                'amount' => number_format($this->subscription->monthly_price, 2, ',', '.'),
                'expiresAt' => $this->subscription->expires_at ? $this->subscription->expires_at->format('d/m/Y') : 'Vencido',
                'paymentUrl' => $this->paymentUrl,
            ]);
    }
}
