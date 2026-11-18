<?php

namespace App\Filament\Resources\MeuCertificadoResource\Pages;

use App\Filament\Resources\MeuCertificadoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMeusCertificados extends ListRecords
{
    protected static string $resource = MeuCertificadoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Solicitar Certificado'),
        ];
    }
}
