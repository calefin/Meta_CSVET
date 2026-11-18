<?php

namespace App\Filament\Resources\PressMentionResource\Pages;

use App\Filament\Resources\PressMentionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPressMentions extends ListRecords
{
    protected static string $resource = PressMentionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
