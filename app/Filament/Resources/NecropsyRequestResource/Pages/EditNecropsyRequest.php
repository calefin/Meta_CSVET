<?php

namespace App\Filament\Resources\NecropsyRequestResource\Pages;

use App\Filament\Resources\NecropsyRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNecropsyRequest extends EditRecord
{
    protected static string $resource = NecropsyRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
