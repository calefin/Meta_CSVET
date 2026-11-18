<?php

namespace App\Filament\Resources\NecropsyClassSessionResource\Pages;

use App\Filament\Resources\NecropsyClassSessionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNecropsyClassSessions extends ListRecords
{
    protected static string $resource = NecropsyClassSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nova Aula de Necropsia'),
        ];
    }
}
