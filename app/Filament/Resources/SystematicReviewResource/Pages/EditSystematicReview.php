<?php

namespace App\Filament\Resources\SystematicReviewResource\Pages;

use App\Filament\Resources\SystematicReviewResource;
use App\Models\SystematicReview;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSystematicReview extends EditRecord
{
    protected static string $resource = SystematicReviewResource::class;

    protected function afterSave(): void
    {
        // Mudanças no protocolo depois da triagem começar precisam ser relatadas
        // como desvio do protocolo registrado (PRISMA 2020, item 24c).
        if ($this->record->status !== SystematicReview::STATUS_PROTOCOL && $this->record->wasChanged('protocol')) {
            $this->record->logEvent('Protocolo alterado após o início da triagem', 'Relate como desvio do protocolo registrado (PRISMA 2020, item 24c).');
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()->label('Abrir'),
            Actions\DeleteAction::make()
                ->label('Excluir projeto')
                ->modalDescription('Apaga o projeto, os registros, os votos e os termos assinados. Não dá para desfazer.'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
