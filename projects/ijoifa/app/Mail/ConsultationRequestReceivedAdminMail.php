<?php

namespace App\Mail;

use App\Models\ConsultationRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ConsultationRequestReceivedAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ConsultationRequest $consultationRequest,
        public User $user,
    ) {}

    public function build(): self
    {
        $adminUrl = route('admin.oracular.show', $this->consultationRequest);

        return $this->subject('Nova Solicitação de Consulta de '.$this->user->name)
            ->view('emails.consultation-request-received')
            ->text('emails.consultation-request-received-text')
            ->with([
                'userName' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
                'motherName' => $this->user->mother_maiden_name,
                'birthdate' => $this->user->birthdate ? $this->user->birthdate->format('d/m/Y') : 'Não informada',
                'country' => $this->user->country,
                'modality' => $this->consultationRequest->modality === 'online' ? 'Online' : 'Presencial',
                'description' => $this->consultationRequest->description,
                'adminUrl' => $adminUrl,
            ]);
    }
}
