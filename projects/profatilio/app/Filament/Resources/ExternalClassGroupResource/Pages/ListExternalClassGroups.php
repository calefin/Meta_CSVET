<?php

namespace App\Filament\Resources\ExternalClassGroupResource\Pages;

use App\Filament\Resources\ExternalClassGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExternalClassGroups extends ListRecords
{
    protected static string $resource = ExternalClassGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nova Turma (sem planilha)'),
        ];
    }
}
