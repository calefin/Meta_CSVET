<?php

namespace App\Mail;

use App\Models\AdHocCharge;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdHocChargeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AdHocCharge $charge,
        public User $user,
        public Payment $payment,
    ) {}

    public function build(): self
    {
        return $this->subject('Cobrança — '.config('app.name'))
            ->view('emails.ad-hoc-charge')
            ->text('emails.ad-hoc-charge-text')
            ->with([
                'userName' => $this->user->name,
                'description' => $this->charge->description,
                'amount' => number_format($this->payment->amount, 2, ',', '.'),
                'paymentUrl' => $this->payment->checkout_url ?? url('/'),
            ]);
    }
}
