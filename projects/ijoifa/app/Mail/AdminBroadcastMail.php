<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminBroadcastMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $broadcastSubject,
        public string $broadcastBody,
    ) {}

    public function build(): self
    {
        return $this->subject($this->broadcastSubject)
            ->view('emails.admin-broadcast')
            ->text('emails.admin-broadcast-text')
            ->with([
                'recipientName' => $this->recipientName,
                'broadcastBody' => $this->broadcastBody,
            ]);
    }
}
