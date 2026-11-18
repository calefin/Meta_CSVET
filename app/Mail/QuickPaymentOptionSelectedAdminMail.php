<?php

namespace App\Mail;

use App\Models\QuickPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class QuickPaymentOptionSelectedAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public QuickPayment $quickPayment,
        public array $option,
        public int $optionIndex,
    ) {}

    public function build(): self
    {
        return $this->subject('Opção escolhida (aguardando pagamento) — ' . ($this->quickPayment->notes ?: 'Link rápido'))
            ->view('emails.quick-payment-option-selected-admin')
            ->text('emails.quick-payment-option-selected-admin-text')
            ->with([
                'clientName' => $this->quickPayment->user?->name ?? 'Cliente',
                'clientEmail' => $this->quickPayment->user?->email ?? '—',
                'optionLabel' => 'Opção ' . ($this->optionIndex + 1),
                'comment' => $this->option['comment'] ?? null,
                'total' => number_format($this->option['total'], 2, ',', '.'),
                'adminUrl' => route('admin.oracular.quick-payment'),
            ]);
    }
}
