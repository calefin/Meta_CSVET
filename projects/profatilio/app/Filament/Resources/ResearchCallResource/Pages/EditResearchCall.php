<?php

namespace App\Filament\Resources\ResearchCallResource\Pages;

use App\Filament\Resources\ResearchApplicationResource;
use App\Filament\Resources\ResearchCallResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditResearchCall extends EditRecord
{
    protected static string $resource = ResearchCallResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('pagina')->label('Ver página')->icon('heroicon-o-arrow-top-right-on-square')->color('gray')
                ->url(fn () => $this->record->publicUrl(), true),
            ResearchCallResource::shareAction(Actions\Action::class),
            ResearchCallResource::toggleOpenAction(Actions\Action::class),
            ResearchCallResource::matrixAction(Actions\Action::class),
            Actions\Action::make('inscricoes')->label('Inscrições')->icon('heroicon-o-users')
                ->url(fn () => ResearchApplicationResource::getUrl('index', ['tableFilters' => ['research_call_id' => ['value' => $this->record->id]]])),
            Actions\DeleteAction::make()
                ->label('Excluir chamada')
                ->modalDescription('Apaga a chamada e TODAS as inscrições dela. Não dá para desfazer. Para só parar de receber inscrições, mude a situação para "Inscrições encerradas".'),
        ];
    }
}
