<?php

namespace App\Filament\Resources\PressMentionResource\Pages;

use App\Filament\Resources\PressMentionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPressMention extends EditRecord
{
    protected static string $resource = PressMentionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
