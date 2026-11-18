<?php

namespace App\Filament\Resources\SupportTicketResource\Pages;

use App\Filament\Resources\SupportTicketResource;
use App\Mail\AppNotificationMail;
use App\Services\TelegramService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ViewSupportTicket extends ViewRecord
{
    protected static string $resource = SupportTicketResource::class;

    public function mount($record): void
    {
        parent::mount($record);

        $this->record->messages()
            ->whereNull('read_at')
            ->where('user_id', '!=', auth()->id())
            ->update(['read_at' => now()]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('encerrar')
                ->label('Encerrar Chamado')
                ->icon('heroicon-o-check-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('O aluno não poderá mais enviar mensagens neste chamado. Tem certeza que o assunto foi esgotado?')
                ->visible(fn () => auth()->user()?->role === 'admin' && $this->record->isOpen())
                ->action(function () {
                    abort_unless(auth()->user()?->role === 'admin', 403);

                    if ($this->record->isClosed()) {
                        return;
                    }

                    $this->record->update([
                        'status' => 'closed',
                        'closed_at' => now(),
                    ]);

                    try {
                        $student = $this->record->student;
                        $email = $student?->user?->email;
                        $chatId = $student?->telegram_chat_id ?? $student?->user?->telegram_chat_id;
                        $title = "Chamado encerrado: {$this->record->subject}";
                        $content = "Seu chamado \"{$this->record->subject}\" foi encerrado pelo Prof. Atilio. Se precisar, abra um novo chamado.";

                        if (!empty($email)) {
                            Mail::to($email)->queue(
                                new AppNotificationMail(
                                    title: $title,
                                    messageContent: $content,
                                    actionUrl: url('/portal'),
                                    actionText: 'Ver Chamado',
                                    subject: $title,
                                )
                            );
                        }

                        if (!empty($chatId)) {
                            app(TelegramService::class)->sendMessage($chatId, "<b>✅ " . e($title) . "</b>\n\n" . e($content));
                        }
                    } catch (\Exception $e) {
                        Log::error("Erro ao notificar encerramento do chamado {$this->record->id}: " . $e->getMessage());
                    }

                    Notification::make()
                        ->title('Chamado encerrado')
                        ->success()
                        ->send();
                }),
        ];
    }
}
