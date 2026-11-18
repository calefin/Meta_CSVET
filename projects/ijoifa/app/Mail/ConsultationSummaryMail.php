<?php

namespace App\Mail;

use App\Models\Consultation;
use App\Models\User;
// Note: We import standard classes for Mailables
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ConsultationSummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Consultation $consultation,
        public User $user,
    ) {}

    public function build(): self
    {
        return $this->subject('Resumo da sua Consulta de Ifá — ' . config('app.name'))
            ->view('emails.consultation-summary')
            ->text('emails.consultation-summary-text')
            ->with([
                'userName' => $this->user->name,
                'consultedAt' => $this->consultation->consulted_at->format('d/m/Y H:i'),
                'oduMain' => $this->consultation->oduMain ? $this->consultation->oduMain->name : 'N/A',
                'classification' => $this->consultation->ire_ibi === 'ire' ? 'Ire' : 'Ibi',
                'generalGuidance' => $this->consultation->general_guidance ?? 'Não informada',
            ]);
    }
}
