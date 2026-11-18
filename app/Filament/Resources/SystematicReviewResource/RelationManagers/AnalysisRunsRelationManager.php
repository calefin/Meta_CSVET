<?php

namespace App\Filament\Resources\SystematicReviewResource\RelationManagers;

use App\Models\SrAnalysisRun;
use App\Services\SystematicReviewExtractionService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** Análises sobre a base travada, com reexecução independente obrigatória (P8). */
class AnalysisRunsRelationManager extends RelationManager
{
    protected static string $relationship = 'analysisRuns';

    protected static ?string $title = 'Análises (R)';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->datasetLocks()->exists();
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->description('Rode o script sobre a base travada e registre aqui o script, as saídas e o sessionInfo. Outra pessoa reexecuta e confirma o resultado idêntico.')
            ->columns([
                Tables\Columns\TextColumn::make('script_name')->label('Script')->description(fn (SrAnalysisRun $r) => 'base v' . ($r->lock?->version ?? '?') . ' · ' . $r->created_at->format('d/m/Y') . ' · ' . $r->run_by . ($r->post_hoc ? ' · POST HOC' : '')),
                Tables\Columns\TextColumn::make('outputs')->label('Saídas')->state(fn (SrAnalysisRun $r) => count($r->outputs ?? []) . ' arquivo(s)'),
                Tables\Columns\TextColumn::make('rerun')->label('Reexecução')
                    ->state(fn (SrAnalysisRun $r) => $r->rerun_at ? ($r->rerun_matches ? '✔ idêntico' : '✖ diferente') . " · {$r->rerun_by}" : 'pendente')
                    ->badge()->color(fn (SrAnalysisRun $r) => $r->rerun_at ? ($r->rerun_matches ? 'success' : 'danger') : 'warning'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('registrar')->label('Registrar análise')->icon('heroicon-o-arrow-up-tray')
                    ->modalDescription(fn () => 'A análise fica ligada à base travada mais recente (v' . $this->getOwnerRecord()->latestLock()?->version . '). Guarde o script, as saídas (forest, funnel, tabelas) e o sessionInfo.')
                    ->form([
                        Forms\Components\FileUpload::make('script')->label('Script R')->disk('local')->directory('systematic_reviews/tmp')->visibility('private')
                            ->storeFileNamesIn('script_name')->maxSize(5120)->required(),
                        Forms\Components\FileUpload::make('outputs')->label('Saídas (PDF, PNG, CSV, TXT)')->multiple()->maxFiles(30)->disk('local')->directory('systematic_reviews/tmp')
                            ->visibility('private')->storeFileNamesIn('output_names')->maxSize(20480),
                        Forms\Components\Textarea::make('session_info')->label('sessionInfo() (cole a saída)')->rows(4),
                        Forms\Components\Toggle::make('post_hoc')->label('Análise NÃO prevista no protocolo (post hoc) — vira desvio'),
                        Forms\Components\Textarea::make('notes')->label('Notas')->rows(2),
                    ])
                    ->action(function (array $data) {
                        $disk = Storage::disk('local');
                        try {
                            $outputs = collect((array) ($data['outputs'] ?? []))->map(fn ($p) => ['name' => $data['output_names'][$p] ?? basename($p), 'contents' => $disk->get($p)])->all();
                            app(SystematicReviewExtractionService::class)->registerAnalysis($this->getOwnerRecord(), $data['script_name'] ?? basename($data['script']),
                                $disk->get($data['script']), $outputs, $data['session_info'] ?? null, auth()->user()?->name ?? 'Professor', $data['notes'] ?? null, (bool) ($data['post_hoc'] ?? false));
                        } catch (\RuntimeException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        } finally {
                            $disk->delete(array_merge([$data['script']], (array) ($data['outputs'] ?? [])));
                        }
                        Notification::make()->title('Análise registrada')->body('Peça a outra pessoa para reexecutar e confirmar.')->success()->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('reexecucao')->label('Confirmar reexecução')->icon('heroicon-o-check-badge')->color('success')
                    ->visible(fn (SrAnalysisRun $r) => !$r->rerun_at)
                    ->form([
                        Forms\Components\TextInput::make('rerun_by')->label('Quem reexecutou (outra pessoa)')->required()->maxLength(255),
                        Forms\Components\Toggle::make('matches')->label('Resultado idêntico')->default(true),
                        Forms\Components\Textarea::make('notes')->label('Notas (o que foi comparado)')->rows(2),
                    ])
                    ->action(function (SrAnalysisRun $r, array $data) {
                        try {
                            app(SystematicReviewExtractionService::class)->confirmRerun($r, $data['rerun_by'], (bool) $data['matches'], $data['notes'] ?? null);
                        } catch (\RuntimeException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }
                        Notification::make()->title('Reexecução registrada')->success()->send();
                    }),
                Tables\Actions\Action::make('arquivos')->label('Arquivos')->icon('heroicon-o-paper-clip')->color('gray')
                    ->modalSubmitAction(false)->modalCancelActionLabel('Fechar')
                    ->modalContent(fn (SrAnalysisRun $r) => new \Illuminate\Support\HtmlString(
                        '<ul style="font-size:.875rem;list-style:disc;padding-left:1.2rem">'
                        . '<li><a style="color:#0d9488" href="' . e(route('systematic-review.analysis-file', [$r, 'script'])) . '">' . e($r->script_name) . '</a> <code style="font-size:.7rem">' . e(substr((string) $r->script_sha256, 0, 16)) . '…</code></li>'
                        . collect($r->outputs ?? [])->map(fn ($o, $i) => '<li><a style="color:#0d9488" href="' . e(route('systematic-review.analysis-file', [$r, $i])) . '">' . e($o['name']) . '</a> <code style="font-size:.7rem">' . e(substr($o['sha256'], 0, 16)) . '…</code></li>')->implode('')
                        . '</ul>' . ($r->session_info ? '<pre style="font-size:.7rem;white-space:pre-wrap;margin-top:.6rem;max-height:14rem;overflow:auto">' . e($r->session_info) . '</pre>' : ''))),
            ]);
    }
}
