<?php

namespace App\Filament\Resources\MeuCertificadoResource\Pages;

use App\Filament\Resources\MeuCertificadoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMeuCertificado extends CreateRecord
{
    protected static string $resource = MeuCertificadoResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['requested_at'] = now();
        return $data;
    }
}
