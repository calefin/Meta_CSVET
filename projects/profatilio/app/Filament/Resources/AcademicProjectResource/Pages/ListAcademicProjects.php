<?php

namespace App\Filament\Resources\AcademicProjectResource\Pages;

use App\Filament\Resources\AcademicProjectResource;
use Filament\Resources\Pages\ListRecords;

class ListAcademicProjects extends ListRecords
{
    protected static string $resource = AcademicProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
