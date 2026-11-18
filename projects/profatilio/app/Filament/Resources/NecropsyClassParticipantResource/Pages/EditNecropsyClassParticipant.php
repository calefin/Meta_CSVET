<?php

namespace App\Filament\Resources\NecropsyClassParticipantResource\Pages;

use App\Filament\Resources\NecropsyClassParticipantResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNecropsyClassParticipant extends EditRecord
{
    protected static string $resource = NecropsyClassParticipantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
