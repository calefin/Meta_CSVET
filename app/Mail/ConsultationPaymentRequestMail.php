<?php

namespace App\Mail;

use App\Models\ConsultationRequest;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ConsultationPaymentRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ConsultationRequest $consultationRequest,
        public User $user,
        public Payment $payment,
    ) {}

    public function build(): self
    {
        return $this->subject('Link de Pagamento da sua Consulta — '.config('app.name'))
            ->view('emails.consultation-payment-request')
            ->text('emails.consultation-payment-request-text')
            ->with([
                'userName' => $this->user->name,
                'amount' => number_format($this->payment->amount, 2, ',', '.'),
                'paymentUrl' => $this->payment->checkout_url ?? url('/'),
            ]);
    }
}
