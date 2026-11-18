<?php

namespace App\Filament\Pages;

use App\Models\SystematicReview;
use App\Services\ScreeningTrainingService;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;

/**
 * Treino da triagem, visão do professor: situação de cada dupla, botões para
 * criar, liberar com prazo e encerrar, e os resultados com o gabarito (por
 * dupla e por trabalho, para a aula). Separado da Central: nada aqui é projeto real.
 */
class TreinoTriagemPainel extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationGroup = 'Programa de Metanálises';

    protected static ?string $navigationLabel = 'Treino da triagem';

    protected static ?string $title = 'Treino da triagem';

    protected static ?string $slug = 'treino-da-triagem';

    protected static string $view = 'filament.pages.treino-triagem-painel';

    protected static ?int $navigationSort = -38; // Logo abaixo das Revisões.

    #[Url(as: 'dupla')]
    public ?int $open = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public static function getNavigationBadge(): ?string
    {
        $n = static::canAccess() ? ScreeningTrainingService::query()->where('status', SystematicReview::STATUS_SCREENING)->count() : 0;

        return $n ? $n . ' abertos' : null;
    }

    protected function service(): ScreeningTrainingService
    {
        return app(ScreeningTrainingService::class);
    }

    public function mount(): void
    {
        $this->service()->closeExpired();
    }

    public function toggle(int $id): void
    {
        $this->open = $this->open === $id ? null : $id;
    }

    protected function getHeaderActions(): array
    {
        $pending = fn () => ScreeningTrainingService::query()->where('status', SystematicReview::STATUS_PROTOCOL)->count();
        $running = fn () => ScreeningTrainingService::query()->where('status', SystematicReview::STATUS_SCREENING)->count();

        return [
            Action::make('criar')->label('1. Criar treinos')->icon('heroicon-o-plus')->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Cria um treino para cada dupla do programa, com os 25 trabalhos do PubMed. Ninguém é avisado nesta etapa.')
                ->action(function () {
                    try {
                        $n = $this->service()->create();
                        Notification::make()->title($n ? "{$n} treinos criados. Ninguém foi avisado ainda." : 'Todas as duplas já têm treino.')->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Não deu para criar os treinos')->body($e->getMessage())->danger()->send();
                    }
                }),
            Action::make('liberar')->label('2. Liberar aos alunos')->icon('heroicon-o-paper-airplane')
                ->visible(fn () => $pending() > 0)
                ->form([
                    DateTimePicker::make('until')->label('O treino fecha em')->seconds(false)->required()
                        ->default(now()->addDay()->setTime(23, 59))
                        ->helperText('Depois desse horário o treino fecha sozinho e os alunos veem os resultados e o gabarito.'),
                ])
                ->modalDescription('ENVIA e-mail (e Telegram, para quem tem) a cada aluno: "Treino da triagem liberado". O texto está no final desta página.')
                ->modalSubmitActionLabel('Liberar e avisar os alunos')
                ->action(function (array $data) {
                    $n = $this->service()->release(Carbon::parse($data['until']));
                    Notification::make()->title("{$n} treinos liberados até " . Carbon::parse($data['until'])->format('d/m H:i'))->success()->send();
                }),
            Action::make('encerrar')->label('Encerrar agora')->icon('heroicon-o-lock-closed')->color('warning')
                ->visible(fn () => $running() > 0)
                ->requiresConfirmation()
                ->modalDescription('Fecha agora todos os treinos abertos, mesmo antes do prazo, e ENVIA e-mail aos alunos avisando que os resultados estão liberados. O senhor recebe o kappa de cada dupla.')
                ->action(function () {
                    $n = $this->service()->closeAll();
                    Notification::make()->title("{$n} treinos encerrados; os alunos já veem os resultados.")->success()->send();
                }),
            Action::make('arquivar')->label('Arquivar')->icon('heroicon-o-archive-box')->color('gray')
                ->visible(fn () => ScreeningTrainingService::query()->where('status', SystematicReview::STATUS_SCREENING_DONE)->exists() && $running() === 0)
                ->requiresConfirmation()
                ->modalDescription('Tira os treinos do portal dos alunos (e os resultados deles somem de lá). As decisões continuam guardadas. Sem e-mail.')
                ->action(function () {
                    Notification::make()->title($this->service()->archive() . ' treinos arquivados.')->success()->send();
                }),
        ];
    }

    protected function getViewData(): array
    {
        $trainings = ScreeningTrainingService::query()->where('status', '!=', SystematicReview::STATUS_ARCHIVED)
            ->with('activeScreeners')->withCount('records')->orderBy('short_name')->get();
        $service = $this->service();
        $reviews = app(\App\Services\SystematicReviewService::class);

        return [
            'trainings' => $trainings->map(fn (SystematicReview $t) => [
                'review' => $t,
                'progress' => $t->activeScreeners->map(fn ($s) => ['name' => $s->name, 'done' => $reviews->progress($s, SystematicReview::STAGE_TA)['done'] ?? 0])->all(),
                'kappa' => $reviews->reliability($t, SystematicReview::STAGE_TA)['pairs'][0]['kappa'] ?? null,
            ]),
            'opened' => $this->open ? $trainings->firstWhere('id', $this->open) : null,
            'results' => ($t = $this->open ? $trainings->firstWhere('id', $this->open) : null) ? $service->results($t) : null,
            'overview' => $service->overview(),
            'archived' => ScreeningTrainingService::query()->where('status', SystematicReview::STATUS_ARCHIVED)->count(),
            // Prévia do e-mail com o primeiro aluno de um treino ainda não liberado (o prazo entra no envio).
            'releaseText' => ($s = $trainings->firstWhere('status', SystematicReview::STATUS_PROTOCOL)?->activeScreeners->first())
                ? $reviews->assignmentText($s) : null,
        ];
    }
}
