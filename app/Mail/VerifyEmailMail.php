<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class VerifyEmailMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
    ) {}

    public function build(): self
    {
        $verifyUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHours(24),
            ['id' => $this->user->id, 'hash' => sha1($this->user->email)]
        );

        return $this->subject('Confirme seu e-mail — '.config('app.name'))
            ->view('emails.verify-email')
            ->text('emails.verify-email-text')
            ->with([
                'userName' => $this->user->name,
                'verifyUrl' => $verifyUrl,
            ]);
    }
}
