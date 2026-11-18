<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BulkEmailAlunos extends Mailable
{
    use Queueable, SerializesModels;

    public string $htmlBody;
    public string $recipientName;

    public function __construct(
        string $subject,
        string $htmlBody,
        string $recipientName,
    ) {
        $this->subject = $subject;
        $this->htmlBody = $htmlBody;
        $this->recipientName = $recipientName;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.bulk-email',
            with: [
                'htmlBody'      => $this->htmlBody,
                'recipientName' => $this->recipientName,
                'botUsername'   => ltrim(config('services.telegram.bot_username', ''), '@'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }

    /**
     * Chamado automaticamente pelo Laravel quando o job enfileirado deste
     * e-mail falha definitivamente (ex.: erro de SMTP), já que o evento
     * MessageSent (usado no log de sucesso) nunca chega a disparar nesse caso.
     */
    public function failed(\Throwable $exception): void
    {
        foreach ($this->to ?? [] as $recipient) {
            \App\Models\EmailLog::create([
                'recipient' => $recipient['address'] ?? 'desconhecido',
                'subject' => $this->subject,
                'body' => $this->htmlBody,
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'sent_at' => now(),
            ]);
        }
    }
}
