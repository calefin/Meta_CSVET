<?php

namespace App\Filament\Resources\ErrorReportResource\Pages;

use App\Filament\Resources\ErrorReportResource;
use Filament\Resources\Pages\CreateRecord;

class CreateErrorReport extends CreateRecord
{
    protected static string $resource = ErrorReportResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        return $data;
    }
}
