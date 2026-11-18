<?php

namespace App\Filament\Resources\SupportTicketResource\Pages;

use App\Filament\Resources\SupportTicketResource;
use App\Models\Student;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateSupportTicket extends CreateRecord
{
    protected static string $resource = SupportTicketResource::class;

    protected string $firstMessage = '';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->firstMessage = $data['first_message'] ?? '';
        unset($data['first_message']);

        $student = Student::where('user_id', auth()->id())->first();

        if (!$student) {
            Notification::make()
                ->title('Não foi possível abrir o chamado')
                ->body('Seu cadastro de aluno não foi encontrado. Entre em contato com o suporte.')
                ->danger()
                ->send();

            $this->halt();
        }

        $data['student_id'] = $student->id;
        $data['status'] = 'open';
        $data['last_message_at'] = now();

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->messages()->create([
            'user_id' => auth()->id(),
            'message' => $this->firstMessage,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
