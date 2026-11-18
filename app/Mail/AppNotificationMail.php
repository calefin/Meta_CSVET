<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $title,
        public readonly string $messageContent,
        public readonly ?string $actionUrl = null,
        public readonly ?string $actionText = 'Acessar Painel',
        public readonly ?string $parecerHtml = null,
        $subject = null
    ) {
        if ($subject) {
            $this->subject = $subject;
        } else {
            $this->subject = $title;
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notification',
            with: [
                'title' => $this->title,
                'messageContent' => $this->messageContent,
                'actionUrl' => $this->actionUrl,
                'actionText' => $this->actionText,
                'parecerHtml' => $this->parecerHtml,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
