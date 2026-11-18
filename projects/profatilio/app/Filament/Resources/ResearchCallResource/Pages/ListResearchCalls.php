<?php

namespace App\Filament\Resources\ResearchCallResource\Pages;

use App\Filament\Resources\ResearchCallResource;
use App\Models\ResearchCall;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListResearchCalls extends ListRecords
{
    protected static string $resource = ResearchCallResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('modelo')
                ->label('Criar chamada das metanálises')
                ->icon('heroicon-o-sparkles')
                ->color('success')
                ->visible(fn () => !ResearchCall::where('slug', ResearchCall::defaults()['slug'])->exists())
                ->requiresConfirmation()
                ->modalDescription('Cria a chamada em rascunho com os textos do guia de captação: benefícios, pré-requisitos, compromissos, passo a passo, perguntas frequentes, roteiro de entrevista e os 20 projetos (só código e área). Revise e mude para "Inscrições abertas" quando quiser publicar.')
                ->action(function () {
                    $call = ResearchCall::create(ResearchCall::defaults());
                    Notification::make()->title('Chamada criada em rascunho')->body('Revise os textos e publique.')->success()->send();
                    $this->redirect(ResearchCallResource::getUrl('edit', ['record' => $call]));
                }),
            Actions\CreateAction::make()->label('Nova chamada em branco'),
        ];
    }
}
