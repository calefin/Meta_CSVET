<?php

namespace App\Mail;

use App\Models\TelegramCourseSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TelegramCourseGroupInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public TelegramCourseSubscription $subscription,
        public string $inviteLink
    ) {}

    public function build(): self
    {
        return $this->subject('Pagamento confirmado — Acesse o grupo do Curso Telegram')
            ->view('emails.telegram-course-group-invite')
            ->text('emails.telegram-course-group-invite-text')
            ->with([
                'studentName' => $this->subscription->full_name,
                'expiresAt' => $this->subscription->expires_at ? $this->subscription->expires_at->format('d/m/Y') : null,
                'inviteLink' => $this->inviteLink,
            ]);
    }
}
