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
            $this->form->fill(['document_type' => $type], andFillStateWithNull: false);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (auth()->user()->role === 'student') {
            $data['student_id'] = auth()->user()->student?->id;
        }
        return $data;
    }
}
