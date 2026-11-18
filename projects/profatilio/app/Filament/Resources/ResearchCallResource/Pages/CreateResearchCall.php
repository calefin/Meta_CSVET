<?php

namespace App\Filament\Resources\ResearchCallResource\Pages;

use App\Filament\Resources\ResearchCallResource;
use Filament\Resources\Pages\CreateRecord;

class CreateResearchCall extends CreateRecord
{
    protected static string $resource = ResearchCallResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data + ['status' => 'draft'];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
