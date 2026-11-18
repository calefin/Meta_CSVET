<?php

namespace App\Filament\Pages;

use App\Console\Commands\CleanupObsoleteDriveFiles;
use App\Jobs\DeleteObsoleteDriveFiles;
use App\Services\DriveCleanupService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;

class LimpezaDrive extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-trash';
    protected static string  $view            = 'filament.pages.limpeza-drive';
    protected static ?string $navigationLabel = 'Limpeza do Drive';
    protected static ?string $title           = 'Limpeza do Google Drive';
    protected static ?string $navigationGroup = 'Monitoramento e Logs';
    protected static ?int    $navigationSort  = 90;

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public function getScan(): ?array
    {
        return Cache::get(DriveCleanupService::SCAN_CACHE_KEY);
    }

    public function getProgress(): ?array
    {
        return Cache::get(DeleteObsoleteDriveFiles::PROGRESS_CACHE_KEY);
    }

    public static function human(int $bytes): string
    {
        return CleanupObsoleteDriveFiles::human($bytes);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('analisar')
                ->label('Analisar Drive agora')
                ->icon('heroicon-o-magnifying-glass')
                ->disabled(!DriveCleanupService::isAvailable())
                ->action(function () {
                    @set_time_limit(180);

                    try {
                        $scan = app(DriveCleanupService::class)->scan();
                    } catch (\Throwable $e) {
                        report($e);
                        Notification::make()->title('Falha ao analisar o Drive')->body($e->getMessage())->danger()->send();
                        return;
                    }

                    $bytes = array_sum(array_column($scan['totals'], 'bytes'));
                    Notification::make()
                        ->title('Análise concluída')
                        ->body(count($scan['candidates']) . ' arquivo(s) obsoleto(s), ' . self::human($bytes) . ' recuperáveis.')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function apagarAction(): Action
    {
        return Action::make('apagar')
            ->requiresConfirmation()
            ->color('danger')
            ->modalHeading(fn (array $arguments) => 'Apagar definitivamente: ' . (DriveCleanupService::CATEGORY_LABELS[$arguments['category']] ?? 'tudo'))
            ->modalDescription('Os arquivos são excluídos de vez do Google Drive (não vão para a lixeira) e não podem ser recuperados. Órfãos são reconferidos contra o banco antes de cada exclusão.')
            ->modalSubmitActionLabel('Sim, apagar')
            ->action(function (array $arguments) {
                $scan = $this->getScan();
                if (!$scan) {
                    Notification::make()->title('Análise expirada — clique em "Analisar Drive agora" de novo.')->warning()->send();
                    return;
                }

                $category = $arguments['category'] ?? null;
                $candidates = array_values(array_filter(
                    $scan['candidates'],
                    fn ($c) => $category === null || $c['category'] === $category
                ));

                if ($candidates === []) {
                    Notification::make()->title('Nada a apagar nessa categoria.')->send();
                    return;
                }

                Cache::put(DeleteObsoleteDriveFiles::PROGRESS_CACHE_KEY, [
                    'deleted' => 0, 'bytes' => 0, 'skipped' => 0, 'failed' => 0,
                    'remaining' => count($candidates), 'finished' => false,
                ], now()->addDay());

                DeleteObsoleteDriveFiles::dispatch($candidates);

                // Tira da análise em cache o que já foi mandado apagar.
                $ids = array_flip(array_column($candidates, 'id'));
                $scan['candidates'] = array_values(array_filter($scan['candidates'], fn ($c) => !isset($ids[$c['id']])));
                foreach ($scan['totals'] as $cat => $total) {
                    $items = array_filter($scan['candidates'], fn ($c) => $c['category'] === $cat);
                    $scan['totals'][$cat] = ['count' => count($items), 'bytes' => array_sum(array_column($items, 'size'))];
                }
                Cache::put(DriveCleanupService::SCAN_CACHE_KEY, $scan, now()->addHours(6));

                Notification::make()
                    ->title('Exclusão iniciada')
                    ->body(count($candidates) . ' arquivo(s) sendo apagados em segundo plano (lotes de ' . DeleteObsoleteDriveFiles::BATCH_SIZE . ' por minuto).')
                    ->success()
                    ->send();
            });
    }
}
