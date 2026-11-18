<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentConfirmedAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Payment $payment
    ) {}

    public function build(): self
    {
        $payableName = $this->payment->description();

        $adminUrl = route('admin.dashboard');

        return $this->subject('Pagamento Confirmado: ' . $payableName)
            ->view('emails.payment-confirmed-admin')
            ->text('emails.payment-confirmed-admin-text')
            ->with([
                'clientName' => $this->payment->user->name,
                'clientEmail' => $this->payment->user->email,
                'payableName' => $payableName,
                'amount' => number_format($this->payment->amount, 2, ',', '.'),
                'paidAt' => $this->payment->paid_at ? $this->payment->paid_at->format('d/m/Y H:i') : now()->format('d/m/Y H:i'),
                'adminUrl' => $adminUrl,
            ]);
    }
}
