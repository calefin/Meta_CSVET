<?php

namespace App\Filament\Resources\AcademicProjectResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DeadlinesRelationManager extends RelationManager
{
    protected static string $relationship = 'deadlines';

    protected static ?string $title = 'Prazos e Cobranças';

    protected static ?string $modelLabel = 'Prazo';

    protected static ?string $pluralModelLabel = 'Prazos';

    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->label('Tipo de Prazo')
                    ->options([
                        'cobranca' => 'Cobrança de Entrega (cobra o aluno até ele agir)',
                        'apresentacao' => 'Data de Apresentação do TCC (só avisa a contagem regressiva)',
                    ])
                    ->default('cobranca')
                    ->live()
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('title')
                    ->label(fn (Forms\Get $get) => $get('type') === 'apresentacao' ? 'Nome da Apresentação/Defesa' : 'O que está sendo cobrado')
                    ->placeholder(fn (Forms\Get $get) => $get('type') === 'apresentacao' ? 'Ex: Defesa Final de TCC' : 'Ex: Entrega do Capítulo 2 do TCC')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\DatePicker::make('due_date')
                    ->label(fn (Forms\Get $get) => $get('type') === 'apresentacao' ? 'Data da Apresentação' : 'Data Limite')
                    ->required()
                    ->native(false),
                Forms\Components\Select::make('recurrence_days')
                    ->label('Cobrança Recorrente')
                    ->options([
                        '' => 'Uma vez (não repetir)',
                        7 => 'A cada 7 dias, até cumprir',
                        15 => 'A cada 15 dias, até cumprir',
                    ])
                    ->native(false)
                    ->visible(fn (Forms\Get $get) => $get('type') !== 'apresentacao')
                    ->helperText('A contagem regressiva de apresentação avisa sozinha em 30/15/7/3/1/0 dias, sem precisar configurar recorrência.'),
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pendente',
                        'fulfilled' => 'Cumprido',
                        'cancelled' => 'Cancelado',
                    ])
                    ->required()
                    ->default('pending'),
                Forms\Components\Textarea::make('notes')
                    ->label('Observações (opcional)')
                    ->rows(3)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'apresentacao' ? 'Apresentação' : 'Cobrança')
                    ->color(fn (string $state) => $state === 'apresentacao' ? 'info' : 'warning'),
                Tables\Columns\TextColumn::make('title')
                    ->label('Descrição')
                    ->wrap()
                    ->searchable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Data Limite')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('recurrence_days')
                    ->label('Recorrência')
                    ->formatStateUsing(fn ($state) => $state ? "A cada {$state} dias" : 'Única'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'pending' => 'Pendente',
                        'fulfilled' => 'Cumprido',
                        'cancelled' => 'Cancelado',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'warning',
                        'fulfilled' => 'success',
                        'cancelled' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('last_charged_at')
                    ->label('Última Cobrança')
                    ->since()
                    ->dateTimeTooltip()
                    ->placeholder('Ainda não cobrado'),
            ])
            ->defaultSort('due_date', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('marcarCumprido')
                    ->label('Marcar como Cumprido')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->update(['status' => 'fulfilled'])),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([])
            ->emptyStateHeading('Nenhum prazo cadastrado')
            ->emptyStateDescription('Cadastre uma cobrança de entrega ou a data de apresentação do TCC para o sistema avisar automaticamente por e-mail e Telegram.');
    }
}
