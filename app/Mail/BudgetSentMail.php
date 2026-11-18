<?php

namespace App\Mail;

use App\Models\Budget;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BudgetSentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Budget $budget,
        public User $user,
    ) {}

    public function build(): self
    {
        return $this->subject('Seu orçamento está pronto — '.config('app.name'))
            ->view('emails.budget-sent')
            ->text('emails.budget-sent-text')
            ->with([
                'userName' => $this->user->name,
                'workName' => $this->budget->recommendedWork?->ritualWork?->name ?? 'Trabalho recomendado',
                'hasMultipleOptions' => $this->budget->hasMultipleOptions(),
                'amount' => number_format((float) $this->budget->total, 2, ',', '.'),
                'portalUrl' => route('consulente.consultations'),
            ]);
    }
}
