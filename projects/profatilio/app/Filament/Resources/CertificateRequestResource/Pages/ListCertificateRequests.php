<?php

namespace App\Filament\Resources\CertificateRequestResource\Pages;

use App\Filament\Resources\CertificateRequestResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListCertificateRequests extends ListRecords
{
    protected static string $resource = CertificateRequestResource::class;

    public function getTabs(): array
    {
        return [
            'all'       => Tab::make('Todas'),
            'pending'   => Tab::make('Aguardando')->modifyQueryUsing(fn (Builder $q) => $q->where('status', 'pending')),
            'approved'  => Tab::make('Aprovadas')->modifyQueryUsing(fn (Builder $q) => $q->where('status', 'approved')),
            'available' => Tab::make('Disponíveis')->modifyQueryUsing(fn (Builder $q) => $q->where('status', 'available')),
        ];
    }
}
