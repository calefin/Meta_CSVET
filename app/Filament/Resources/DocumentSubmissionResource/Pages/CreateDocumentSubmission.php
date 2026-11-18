<?php

namespace App\Filament\Resources\DocumentSubmissionResource\Pages;

use App\Filament\Resources\DocumentSubmissionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDocumentSubmission extends CreateRecord
{
    protected static string $resource = DocumentSubmissionResource::class;

    public function mount(): void
    {
        parent::mount();

        $type = request()->query('document_type');
        if ($type) {
            // andFillStateWithNull: false preserva os defaults já preenchidos
            // pelo mount() original (ex.: student_id), sobrescrevendo só o tipo.
            // A finalidade ("O que você precisa?") acompanha o tipo do link.
            $this->form->fill([
                'document_type' => $type,
                'submission_purpose' => \App\Models\DocumentSubmission::purposeForType($type),
            ], andFillStateWithNull: false);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (auth()->user()->role === 'student') {
            $data['student_id'] = auth()->user()->student?->id;
        }
        return $data;
    }

    protected function getFormActions(): array
    {
        $isStudent = auth()->user()?->role === 'student';

        return [
            $this->getCreateFormAction()
                ->label($isStudent ? 'Enviar Documento' : 'Criar Submissão')
                ->icon('heroicon-o-paper-airplane')
                ->color('success'),
            ...($isStudent ? [] : [$this->getCreateAnotherFormAction()->label('Criar e criar outro')]),
            $this->getCancelFormAction()->label('Cancelar'),
        ];
    }
}
