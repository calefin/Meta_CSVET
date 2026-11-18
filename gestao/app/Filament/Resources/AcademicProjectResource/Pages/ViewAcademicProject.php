<?php

namespace App\Filament\Resources\AcademicProjectResource\Pages;

use App\Filament\Resources\AcademicProjectResource;
use Filament\Resources\Pages\ViewRecord;

class ViewAcademicProject extends ViewRecord
{
    protected static string $resource = AcademicProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
