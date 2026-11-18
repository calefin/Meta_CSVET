<?php

namespace App\Filament\Resources\StudentResource\RelationManagers;

use App\Filament\Resources\AcademicProjectResource;
use App\Models\AcademicTitle;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TitlesRelationManager extends RelationManager
{
    protected static string $relationship = 'titles';

    protected static ?string $title = 'Processos / Projetos (TCC & IC)';

    protected static ?string $modelLabel = 'Projeto';

    protected static ?string $pluralModelLabel = 'Projetos';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'TCC' => 'primary',
                        'IC' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('title')
                    ->label('Título do Projeto / Tema')
                    ->wrap(),
                Tables\Columns\TextColumn::make('andamento')
                    ->label('Andamento')
                    ->badge()
                    ->state(fn (AcademicTitle $record) => $record->getProgressInfo()['label'])
                    ->color(fn (AcademicTitle $record) => $record->getProgressInfo()['color']),
                Tables\Columns\TextColumn::make('validity')
                    ->label('Vigência (IC)')
                    ->state(fn (AcademicTitle $record) => $record->type === 'IC' ? $record->validity : '—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('abrirProjeto')
                    ->label('Abrir Projeto')
                    ->icon('heroicon-o-folder-open')
                    ->color('primary')
                    ->url(fn (AcademicTitle $record) => AcademicProjectResource::getUrl('view', ['record' => $record])),
            ])
            ->bulkActions([])
            ->recordUrl(fn (AcademicTitle $record) => AcademicProjectResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Nenhum projeto de TCC/IC ainda')
            ->emptyStateDescription('Projetos aparecem aqui assim que um tema de TCC é definido ou um projeto de IC é cadastrado.');
    }

    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->role === 'admin';
    }
}
