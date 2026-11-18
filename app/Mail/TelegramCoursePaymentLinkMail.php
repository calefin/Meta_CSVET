<?php

namespace App\Mail;

use App\Models\TelegramCourseSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TelegramCoursePaymentLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public TelegramCourseSubscription $subscription,
        public string $paymentUrl
    ) {}

    public function build(): self
    {
        return $this->subject('Conclua sua inscrição — Curso de Formação Contínua no Telegram')
            ->view('emails.telegram-course-payment-link')
            ->text('emails.telegram-course-payment-link-text')
            ->with([
                'studentName' => $this->subscription->full_name,
                'telegramHandle' => $this->subscription->telegram_username,
                'amount' => number_format($this->subscription->monthly_price, 2, ',', '.'),
                'paymentUrl' => $this->paymentUrl,
            ]);
    }
}
