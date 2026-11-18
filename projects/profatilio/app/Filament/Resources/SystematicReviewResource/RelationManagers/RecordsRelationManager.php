<?php

namespace App\Filament\Resources\SystematicReviewResource\RelationManagers;

use App\Models\SystematicReview;
use App\Models\SystematicReviewRecord;
use App\Services\SystematicReviewService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class RecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'records';

    protected static ?string $title = 'Registros';

    protected static ?string $recordTitleAttribute = 'title';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        /** @var SystematicReview $ownerRecord */
        if ($ownerRecord->status !== SystematicReview::STATUS_CONFLICTS) {
            return (string) $ownerRecord->records()->count();
        }

        return app(SystematicReviewService::class)->conflictsQuery($ownerRecord)->count() . ' conflito(s)';
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    protected function review(): SystematicReview
    {
        return $this->getOwnerRecord();
    }

    /** Desempate só depois que todos os triadores concluíram (triagem cega). */
    protected function stageOpen(): bool
    {
        return app(SystematicReviewService::class)->professorCanResolve($this->review());
    }

    public function table(Table $table): Table
    {
        $review = $this->review();
        $stage = $review->stage;
        $column = SystematicReview::decisionColumn($stage);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['decisions' => fn ($d) => $d->where('stage', $stage)->with('screener')]))
            ->defaultSort('seq')
            ->paginated([25, 50, 100])
            ->columns([
                Tables\Columns\TextColumn::make('seq')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->label('Trabalho')
                    ->searchable(['title', 'authors', 'doi', 'pmid', 'abstract'])
                    ->wrap()
                    ->description(fn (SystematicReviewRecord $r) => $r->citation())
                    ->url(fn (SystematicReviewRecord $r) => $r->link(), true),
                Tables\Columns\TextColumn::make('votes')
                    ->label('Votos (' . $review->stageLabel() . ')')
                    ->state(fn (SystematicReviewRecord $r) => new HtmlString($r->decisions->map(fn ($d) => '<div style="white-space:nowrap">'
                        . self::icon($d->decision) . ' ' . e(\Illuminate\Support\Str::of($d->screener?->name)->before(' ')) . ($d->reason ? ' <span style="opacity:.65">· ' . e($d->reason) . '</span>' : '') . '</div>')->implode('') ?: '<span style="opacity:.5">—</span>')),
                Tables\Columns\TextColumn::make('ta_decision')
                    ->label('Final T/R')
                    ->formatStateUsing(fn (?string $state, SystematicReviewRecord $r) => $state ? SystematicReviewRecord::DECISIONS[$state] . ($r->ta_resolved_by_professor ? ' (prof.)' : '') : '—')
                    ->badge()
                    ->color(fn (?string $state) => $state === 'include' ? 'success' : ($state === 'exclude' ? 'danger' : 'gray'))
                    ->placeholder('pendente'),
                Tables\Columns\TextColumn::make('ft_decision')
                    ->label('Final texto completo')
                    ->formatStateUsing(fn (?string $state, SystematicReviewRecord $r) => $state ? SystematicReviewRecord::DECISIONS[$state] . ($r->ft_resolved_by_professor ? ' (prof.)' : '') : '—')
                    ->badge()
                    ->color(fn (?string $state) => $state === 'include' ? 'success' : ($state === 'exclude' ? 'danger' : 'gray'))
                    ->placeholder('—')
                    ->visible($stage === SystematicReview::STAGE_FT || $review->isStageClosed(SystematicReview::STAGE_FT)),
                Tables\Columns\TextColumn::make('sources')->label('Bases')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('situacao')
                    ->label('Mostrar')
                    ->options([
                        'conflicts' => 'Conflitos / pendentes da etapa atual',
                        'stage' => 'Todos da etapa atual',
                        'included' => 'Incluídos (etapa atual)',
                        'excluded' => 'Excluídos (etapa atual)',
                    ])
                    ->default($review->status === SystematicReview::STATUS_CONFLICTS ? 'conflicts' : null)
                    ->query(function (Builder $query, array $data) use ($stage, $column) {
                        $value = $data['value'] ?? null;
                        if (!$value) {
                            return $query;
                        }
                        if ($stage === SystematicReview::STAGE_FT) {
                            $query->where('ta_decision', 'include');
                        }

                        return match ($value) {
                            'conflicts' => $query->whereNull($column),
                            'included' => $query->where($column, 'include'),
                            'excluded' => $query->where($column, 'exclude'),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('resumo')
                    ->label('Resumo')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (SystematicReviewRecord $r) => $r->title)
                    ->modalContent(fn (SystematicReviewRecord $r) => new HtmlString(
                        '<div style="font-size:.875rem;line-height:1.6"><p style="opacity:.75">' . e($r->citation()) . '</p>'
                        . '<p style="margin-top:.75rem;white-space:pre-line">' . e($r->abstract ?: 'Sem resumo.') . '</p>'
                        . ($r->keywords ? '<p style="margin-top:.75rem;opacity:.75"><strong>Palavras-chave:</strong> ' . e($r->keywords) . '</p>' : '')
                        . ($r->decisions->contains(fn ($d) => filled($d->note)) ? '<p style="margin-top:.75rem"><strong>Notas dos triadores:</strong><br>' . $r->decisions->filter(fn ($d) => filled($d->note))->map(fn ($d) => e($d->screener?->name) . ': ' . e($d->note))->implode('<br>') . '</p>' : '')
                        . '</div>'
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar'),
                Tables\Actions\Action::make('incluir')
                    ->label('Incluir')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn () => $this->stageOpen())
                    ->form(fn (SystematicReviewRecord $r) => $r->{($stage === SystematicReview::STAGE_FT ? 'ft' : 'ta') . '_consensus'} === 'exclude' ? [
                        Forms\Components\Textarea::make('note')->label('Os triadores concordaram em EXCLUIR. Justifique a mudança (vai para o relatório)')->required()->maxLength(500),
                    ] : [])
                    ->action(function (SystematicReviewRecord $r, array $data) use ($stage) {
                        try {
                            app(SystematicReviewService::class)->resolve($r, $stage, 'include', null, $data['note'] ?? null);
                        } catch (\RuntimeException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }
                        Notification::make()->title('#' . $r->seq . ' incluído')->success()->send();
                    }),
                Tables\Actions\Action::make('excluir')
                    ->label('Excluir')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn () => $this->stageOpen())
                    ->form([
                        Forms\Components\Select::make('reason')
                            ->label('Motivo')
                            ->options(fn () => array_combine($r = app(SystematicReviewService::class)->reasonsFor($this->review(), $this->review()->stage), $r))
                            ->required(),
                        Forms\Components\TextInput::make('note')->label('Nota (obrigatória se for contra o consenso dos triadores)')->maxLength(500),
                    ])
                    ->action(function (SystematicReviewRecord $r, array $data) use ($stage) {
                        try {
                            app(SystematicReviewService::class)->resolve($r, $stage, 'exclude', $data['reason'], $data['note'] ?? null);
                        } catch (\RuntimeException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }
                        Notification::make()->title('#' . $r->seq . ' excluído')->success()->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('excluirLote')
                    ->label('Excluir selecionados')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn () => $this->stageOpen())
                    ->form([
                        Forms\Components\Select::make('reason')
                            ->label('Motivo')
                            ->options(fn () => array_combine($r = app(SystematicReviewService::class)->reasonsFor($this->review(), $this->review()->stage), $r))
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data) use ($stage) {
                        $done = 0;
                        foreach ($records as $r) {
                            try {
                                app(SystematicReviewService::class)->resolve($r, $stage, 'exclude', $data['reason']);
                                $done++;
                            } catch (\RuntimeException) {
                                // contra o consenso: precisa de justificativa individual
                            }
                        }
                        Notification::make()->title("{$done} excluídos")->body($done < $records->count() ? ($records->count() - $done) . ' ficaram de fora por contrariar o consenso dos triadores (use a ação individual com justificativa).' : null)->success()->send();
                    }),
                Tables\Actions\BulkAction::make('incluirLote')
                    ->label('Incluir selecionados')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn () => $this->stageOpen())
                    ->requiresConfirmation()
                    ->action(function (Collection $records) use ($stage) {
                        $done = 0;
                        foreach ($records as $r) {
                            try {
                                app(SystematicReviewService::class)->resolve($r, $stage, 'include');
                                $done++;
                            } catch (\RuntimeException) {
                            }
                        }
                        Notification::make()->title("{$done} incluídos")->body($done < $records->count() ? ($records->count() - $done) . ' ficaram de fora por contrariar o consenso dos triadores (use a ação individual com justificativa).' : null)->success()->send();
                    }),
            ]);
    }

    protected static function icon(string $decision): string
    {
        return match ($decision) {
            'include' => '<span style="color:#16a34a;font-weight:700">✔ Incl.</span>',
            'exclude' => '<span style="color:#dc2626;font-weight:700">✖ Excl.</span>',
            default => '<span style="color:#d97706;font-weight:700">? Talvez</span>',
        };
    }
}
