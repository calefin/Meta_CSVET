<?php

namespace App\Filament\Resources\NecropsyClassSessionResource\Pages;

use App\Filament\Resources\NecropsyClassSessionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNecropsyClassSession extends CreateRecord
{
    protected static string $resource = NecropsyClassSessionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        if (!empty($data['is_cancelled'])) {
            $data['cancelled_at'] = now();
        }
        unset($data['is_cancelled']);

        return $data;
    }
}
