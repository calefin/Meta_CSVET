<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicketMessage extends Model
{
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted()
    {
        static::created(function (SupportTicketMessage $message) {
            $ticket = $message->ticket;
            if (!$ticket) {
                return;
            }

            $ticket->update(['last_message_at' => now()]);

            $sender = $message->user;
            $isFromStudent = $sender && $sender->role === 'student';

            if ($isFromStudent) {
                $professor = User::where('role', 'admin')->first();
                $recipientEmail = 'contato@profatilio.com.br';
                $recipientChatId = $professor?->telegram_chat_id;
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

            // Envio síncrono (não ->queue()): notificação de chamado é algo que a outra
            // parte está esperando ver a qualquer momento, e depender do cron da fila
            // (queue:work a cada minuto) já causou entregas silenciosamente perdidas antes
            // (ver o bug do e-mail de "esqueci minha senha"). Email e Telegram em try/catch
            // separados para que falha em um não impeça o outro de ser tentado.
            if (!empty($recipientEmail)) {
                try {
                    \Illuminate\Support\Facades\Mail::to($recipientEmail)->send(
                        new \App\Mail\AppNotificationMail(
                            title: $title,
                            messageContent: $content,
                            actionUrl: $actionUrl,
                            actionText: 'Ver Chamado',
                            subject: $title,
                        )
                    );
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error("Erro ao enviar e-mail de notificação do chamado {$ticket->id}: " . $e->getMessage());
                }
            }

            if (!empty($recipientChatId)) {
                try {
                    $telegramService = app(\App\Services\TelegramService::class);
                    $telegramService->sendMessage(
                        $recipientChatId,
                        "<b>💬 " . e($title) . "</b>\n\n" . e($message->message)
                    );
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error("Erro ao enviar Telegram de notificação do chamado {$ticket->id}: " . $e->getMessage());
                }
            }
        });
    }
}
