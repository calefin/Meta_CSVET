<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicketMessage extends Model
{
    use \App\Models\Concerns\BelongsToOwner;

    protected $fillable = [
        'support_ticket_id',
        'user_id',
        'message',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function ticket()
    {
        return $this->belongsTo(SupportTicket::class);
    }

    /**
     * A mensagem pode ser do professor (Owner) ou do aluno (AdviseeAccount) —
     * ver a migration 2026_08_24_100002.
     */
    public function user()
    {
        return $this->morphTo();
    }

    protected static function booted()
    {
        static::created(function (SupportTicketMessage $message) {
            $ticket = $message->ticket;
            if (!$ticket) {
                return;
            }

            $ticket->update(['last_message_at' => now()]);

            try {
                $sender = $message->user;
                $isFromStudent = $sender instanceof \App\Models\AdviseeAccount;

                if ($isFromStudent) {
                    $professor = \App\Models\Owner::find($ticket->owner_user_id);
                    $recipientEmail = $professor?->email;
                    $recipientChatId = $professor->telegram_chat_id ?? null;
                    $studentName = $ticket->student?->user?->name ?? 'Aluno';
                    $title = "Novo chamado/mensagem: {$ticket->subject}";
                    $content = "O aluno {$studentName} enviou uma mensagem no chamado \"{$ticket->subject}\":\n\n{$message->message}";
                    $actionUrl = url('/admin');
                } else {
                    $student = $ticket->student;
                    $recipientEmail = $student?->user?->email;
                    $recipientChatId = $student?->telegram_chat_id ?? $student?->user?->telegram_chat_id;
                    $title = "Nova resposta no seu chamado: {$ticket->subject}";
                    $content = "Você recebeu uma resposta no chamado \"{$ticket->subject}\":\n\n{$message->message}";
                    $actionUrl = url('/portal');
                }

                if (!empty($recipientEmail)) {
                    \Illuminate\Support\Facades\Mail::to($recipientEmail)->queue(
                        new \App\Mail\AppNotificationMail(
                            title: $title,
                            messageContent: $content,
                            actionUrl: $actionUrl,
                            actionText: 'Ver Chamado',
                            subject: $title,
                        )
                    );
                }

                if (!empty($recipientChatId)) {
                    $telegramService = app(\App\Services\TelegramService::class);
                    $telegramService->sendMessage(
                        $recipientChatId,
                        "<b>💬 " . e($title) . "</b>\n\n" . e($message->message)
                    );
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Erro ao notificar sobre mensagem do chamado {$ticket->id}: " . $e->getMessage());
            }
        });
    }
}
