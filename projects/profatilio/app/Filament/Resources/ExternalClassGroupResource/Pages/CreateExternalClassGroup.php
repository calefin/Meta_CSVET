<?php

namespace App\Filament\Resources\ExternalClassGroupResource\Pages;

use App\Filament\Resources\ExternalClassGroupResource;
use Filament\Resources\Pages\CreateRecord;

class CreateExternalClassGroup extends CreateRecord
{
    protected static string $resource = ExternalClassGroupResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
