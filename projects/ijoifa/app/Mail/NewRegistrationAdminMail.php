<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewRegistrationAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
    ) {}

    public function build(): self
    {
        $adminUrl = route('admin.users');

        return $this->subject('Novo cadastro recebido: '.$this->user->name)
            ->view('emails.new-registration-admin')
            ->text('emails.new-registration-admin-text')
            ->with([
                'userName' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone ?: 'Não informado',
                'country' => $this->user->country,
                'birthdate' => $this->user->birthdate ? $this->user->birthdate->format('d/m/Y') : 'Não informada',
                'adminUrl' => $adminUrl,
            ]);
    }
}
