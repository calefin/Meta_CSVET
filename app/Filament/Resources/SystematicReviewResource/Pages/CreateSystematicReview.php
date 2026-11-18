<?php

namespace App\Filament\Resources\SystematicReviewResource\Pages;

use App\Filament\Resources\SystematicReviewResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSystematicReview extends CreateRecord
{
    protected static string $resource = SystematicReviewResource::class;

    protected function afterCreate(): void
    {
        $this->record->logEvent('Projeto criado');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
