<?php

namespace App\Filament\Resources\ErrorReportResource\Pages;

use App\Filament\Resources\ErrorReportResource;
use Filament\Resources\Pages\EditRecord;

class EditErrorReport extends EditRecord
{
    protected static string $resource = ErrorReportResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
