<?php

namespace App\Filament\Resources\ExternalClassGroupResource\Pages;

use App\Filament\Resources\ExternalClassGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExternalClassGroup extends EditRecord
{
    protected static string $resource = ExternalClassGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
