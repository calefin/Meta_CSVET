<?php

namespace App\Mail;

use App\Models\ConsultationRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ConsultationRequestConfirmationClientMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ConsultationRequest $consultationRequest,
        public User $user,
    ) {}

    public function build(): self
    {
        return $this->subject('Solicitação de Consulta Recebida — '.config('app.name'))
            ->view('emails.consultation-request-confirmation')
            ->text('emails.consultation-request-confirmation-text')
            ->with([
                'userName' => $this->user->name,
                'phone' => $this->user->phone,
                'email' => $this->user->email,
                'modality' => $this->consultationRequest->modality === 'online' ? 'Online' : 'Presencial',
            ]);
    }
}
