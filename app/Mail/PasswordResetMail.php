<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent synchronously (not queued) since this is a single-recipient,
 * time-critical auth email - we don't want it silently stuck if the
 * queue worker cron isn't running, unlike the bulk admin broadcast.
 */
class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $token,
    ) {}

    public function build(): self
    {
        $resetUrl = url(route('password.reset', ['token' => $this->token, 'email' => $this->user->email], false));

        return $this->subject('Redefinir senha — '.config('app.name'))
            ->view('emails.password-reset')
            ->text('emails.password-reset-text')
            ->with([
                'userName' => $this->user->name,
                'resetUrl' => $resetUrl,
            ]);
    }
}
