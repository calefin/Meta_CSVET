<?php

namespace App\Mail;

use App\Models\Payment;
use App\Models\QuickPayment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class QuickPaymentLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public QuickPayment $quickPayment,
        public User $user,
        public Payment $payment,
    ) {}

    public function build(): self
    {
        return $this->subject('Seu link de pagamento — '.config('app.name'))
            ->view('emails.quick-payment-link')
            ->text('emails.quick-payment-link-text')
            ->with([
                'userName' => $this->user->name,
                'amount' => number_format($this->payment->amount, 2, ',', '.'),
                'paymentUrl' => $this->payment->checkout_url ?? route('quick-checkout', ['uuid' => $this->quickPayment->uuid]),
            ]);
    }
}
