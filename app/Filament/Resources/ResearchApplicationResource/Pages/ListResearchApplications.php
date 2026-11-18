<?php

namespace App\Filament\Resources\ResearchApplicationResource\Pages;

use App\Filament\Resources\ResearchApplicationResource;
use App\Filament\Resources\ResearchCallResource;
use App\Models\ResearchApplication;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListResearchApplications extends ListRecords
{
    protected static string $resource = ResearchApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Chamada mais recente que está aberta ou foi encerrada (a de rascunho não conta).
            ResearchCallResource::toggleOpenAction(Actions\Action::class, fn () => \App\Models\ResearchCall::whereIn('status', ['open', 'closed'])->latest('id')->first()),
            Actions\Action::make('mensagemDupla')->label('Mensagem a uma dupla')->icon('heroicon-o-chat-bubble-left-right')->color('primary')
                ->visible(fn () => (bool) $this->currentCall())
                ->modalDescription('Vai por e-mail (e Telegram, se o aluno ligou) só para o líder e o revisor cego do projeto escolhido. Use {nome} para o primeiro nome.')
                ->form(fn () => array_merge([
                    \Filament\Forms\Components\Select::make('code')->label('Projeto (dupla)')->required()->native(false)
                        ->options(app(\App\Services\ResearchRecruitmentService::class)->projectsWithPairs($this->currentCall())),
                ], ResearchApplicationResource::messageFields()))
                ->action(function (array $data) {
                    $svc = app(\App\Services\ResearchRecruitmentService::class);
                    $pair = $svc->projectPair($this->currentCall(), $data['code']);
                    foreach ($pair as $app) {
                        $svc->sendMessage($app, $data['title'], $data['body']);
                    }
                    \Filament\Notifications\Notification::make()->title('Mensagem enviada para ' . $pair->map->firstName()->implode(' e '))->success()->send();
                }),
            Actions\Action::make('contatos')->label('Contatos das duplas (.csv)')->icon('heroicon-o-identification')->color('gray')
                ->visible(fn () => (bool) $this->currentCall())
                ->action(function () {
                    $csv = \App\Services\SystematicReviewProtocolService::csvString(app(\App\Services\ResearchRecruitmentService::class)->contactRows($this->currentCall()));

                    return response()->streamDownload(fn () => print($csv), 'contatos_duplas_' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
                }),
            Actions\Action::make('chamadas')->label('Chamadas e divulgação')->icon('heroicon-o-megaphone')->color('gray')
                ->url(ResearchCallResource::getUrl('index')),
        ];
    }

    protected function currentCall(): ?\App\Models\ResearchCall
    {
        return \App\Models\ResearchCall::whereIn('status', ['open', 'closed'])->latest('id')->first();
    }

    public function getTabs(): array
    {
        $tab = fn (string $label, array $statuses) => Tab::make($label)
            ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', $statuses))
            ->badge(fn () => ResearchApplication::whereIn('status', $statuses)->count() ?: null);

        return [
            'pendentes' => $tab('A fazer', [ResearchApplication::STATUS_NEW, ResearchApplication::STATUS_INTERVIEW, ResearchApplication::STATUS_INTERVIEWED])
                ->badgeColor('warning'),
            'selecionados' => $tab('Selecionados', [ResearchApplication::STATUS_APPROVED])->badgeColor('success'),
            'espera' => $tab('Lista de espera', [ResearchApplication::STATUS_WAITLIST]),
            'encerradas' => $tab('Não selecionados / desligados / desistências', [ResearchApplication::STATUS_REJECTED, ResearchApplication::STATUS_WITHDRAWN, ResearchApplication::STATUS_REMOVED]),
            'todas' => Tab::make('Todas'),
        ];
    }
}
