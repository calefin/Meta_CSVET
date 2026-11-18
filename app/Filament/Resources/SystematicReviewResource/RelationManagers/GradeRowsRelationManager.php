<?php

namespace App\Filament\Resources\SystematicReviewResource\RelationManagers;

use App\Models\SrGradeRow;
use App\Services\SystematicReviewExtractionService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Tabela de resumo dos achados com certeza GRADE por desfecho (P9). */
class GradeRowsRelationManager extends RelationManager
{
    protected static string $relationship = 'gradeRows';

    protected static ?string $title = 'GRADE (resumo dos achados)';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->datasetLocks()->exists();
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    protected function schema(): array
    {
        return [
            Forms\Components\TextInput::make('outcome_id')->label('Desfecho (código)')->required()->maxLength(20),
            Forms\Components\TextInput::make('outcome_name')->label('Desfecho')->required()->maxLength(500)->columnSpan(2),
            Forms\Components\TextInput::make('k')->label('k (estudos)')->numeric(),
            Forms\Components\TextInput::make('n')->label('n (animais)')->numeric(),
            Forms\Components\TextInput::make('effect')->label('Efeito combinado')->maxLength(120),
            Forms\Components\TextInput::make('ci')->label('IC 95%')->maxLength(120),
            Forms\Components\TextInput::make('pi')->label('Intervalo de predição')->maxLength(120),
            Forms\Components\Select::make('certainty')->label('Certeza')->options(SrGradeRow::CERTAINTY)->native(false),
            Forms\Components\Fieldset::make('Razões de rebaixamento (deixe em branco se não rebaixou)')->columns(['default' => 1, 'md' => 2])->schema(
                collect(SrGradeRow::DOMAINS)->map(fn ($label, $key) => Forms\Components\TextInput::make("reasons.{$key}")->label($label)->maxLength(500))->values()->all()
            )->columnSpanFull(),
            Forms\Components\Textarea::make('notes')->label('Notas')->rows(2)->columnSpanFull(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->description('Uma linha por desfecho. Certeza começa alta (ou baixa, conforme o desenho dos estudos) e é rebaixada por risco de viés, inconsistência, evidência indireta, imprecisão e viés de publicação.')
            ->columns([
                Tables\Columns\TextColumn::make('outcome_id')->label('Cód.'),
                Tables\Columns\TextColumn::make('outcome_name')->label('Desfecho')->wrap()->limit(80),
                Tables\Columns\TextColumn::make('effect')->label('Efeito (IC 95%)')->state(fn (SrGradeRow $g) => trim(($g->effect ?? '') . ' ' . ($g->ci ? "({$g->ci})" : '')) ?: '—'),
                Tables\Columns\TextColumn::make('k')->label('k / n')->state(fn (SrGradeRow $g) => ($g->k ?? '—') . ' / ' . ($g->n ?? '—')),
                Tables\Columns\TextColumn::make('certainty')->label('Certeza')->badge()
                    ->formatStateUsing(fn ($state) => SrGradeRow::CERTAINTY[$state] ?? 'pendente')
                    ->color(fn ($state) => match ($state) { 'high' => 'success', 'moderate' => 'info', 'low' => 'warning', 'very_low' => 'danger', default => 'gray' }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('semear')->label('Criar linhas dos desfechos do protocolo')->icon('heroicon-o-sparkles')
                    ->action(function () {
                        $n = app(SystematicReviewExtractionService::class)->seedGrade($this->getOwnerRecord());
                        Notification::make()->title("{$n} linha(s) criada(s)")->success()->send();
                    }),
                Tables\Actions\CreateAction::make()->label('Nova linha')->form($this->schema()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Preencher')->form($this->schema()),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
