<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PersonalActivityResource\Pages;
use App\Models\PersonalActivity;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PersonalActivityResource extends Resource
{
    protected static ?string $model = PersonalActivity::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Atividades & Timer';

    protected static ?string $modelLabel = 'Atividade';

    protected static ?string $pluralModelLabel = 'Atividades';

    protected static ?string $navigationGroup = 'Gestão Pessoal';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->check();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', auth()->id());
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('user_id')
                    ->default(fn () => auth()->id())
                    ->dehydrated(),
                Forms\Components\TextInput::make('title')
                    ->label('Atividade')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\Select::make('category_id')
                    ->label('Categoria')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome da Categoria')
                            ->required()
                            ->unique(ignoreRecord: true),
                        Forms\Components\ColorPicker::make('color')
                            ->label('Cor')
                            ->default('#0d9488'),
                    ]),
                Forms\Components\DatePicker::make('date')
                    ->label('Data')
                    ->native(false)
                    ->default(now())
                    ->required(),
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pendente',
                        'in_progress' => 'Em Andamento',
                        'done' => 'Concluída',
                    ])
                    ->default('pending')
                    ->required(),
                Forms\Components\Textarea::make('notes')
                    ->label('Notas')
                    ->helperText('Campo livre — ex: "Personagem Fulano, level 250" para lazer, ou detalhes da tarefa.')
                    ->rows(3)
                    ->columnSpanFull(),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->orderByRaw(
                "CASE status WHEN 'in_progress' THEN 0 WHEN 'pending' THEN 1 WHEN 'done' THEN 2 ELSE 3 END"
            )->orderByDesc('date'))
            ->recordClasses(fn (PersonalActivity $record) => $record->isRunning()
                ? 'ring-1 ring-inset ring-warning-400/40 bg-warning-50/40 dark:bg-warning-500/5'
                : null)
            ->columns([
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->icon(fn (string $state) => match ($state) {
                        'in_progress' => 'heroicon-o-play-circle',
                        'done' => 'heroicon-o-check-circle',
                        default => 'heroicon-o-clock',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'pending' => 'Pendente',
                        'in_progress' => 'Em Andamento',
                        'done' => 'Concluída',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'in_progress' => 'warning',
                        'done' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('title')
                    ->label('Atividade')
                    ->searchable()
                    ->wrap()
                    ->html()
                    ->state(fn (PersonalActivity $record) => e($record->title)
                        . ($record->category
                            ? '<div class="flex items-center gap-1.5 mt-1"><span class="inline-block w-2 h-2 rounded-full" style="background-color:' . e($record->category->color) . '"></span>'
                                . '<span class="text-xs text-gray-500 dark:text-gray-400">' . e($record->category->name) . '</span></div>'
                            : '')),
                Tables\Columns\TextColumn::make('tempo')
                    ->label('Tempo')
                    ->state(fn (PersonalActivity $record) => $record->formattedTime())
                    ->fontFamily('mono')
                    ->weight(fn (PersonalActivity $record) => $record->isRunning() ? 'bold' : 'normal')
                    ->color(fn (PersonalActivity $record) => $record->isRunning() ? 'warning' : null),
                Tables\Columns\TextColumn::make('date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Notas')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->poll('2s')
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Categoria')
                    ->relationship('category', 'name'),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pendente',
                        'in_progress' => 'Em Andamento',
                        'done' => 'Concluída',
                    ]),
                Tables\Filters\Filter::make('periodo')
                    ->label('Período')
                    ->form([
                        Forms\Components\Select::make('range')
                            ->label('Período')
                            ->options([
                                'hoje' => 'Hoje',
                                'semana' => 'Esta Semana',
                                'mes' => 'Este Mês',
                            ])
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['range'] ?? null) {
                            'hoje' => $query->whereDate('date', now()->toDateString()),
                            'semana' => $query->whereBetween('date', [now()->startOfWeek(), now()->endOfWeek()]),
                            'mes' => $query->whereBetween('date', [now()->startOfMonth(), now()->endOfMonth()]),
                            default => $query,
                        };
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('iniciar')
                    ->label('Iniciar')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn (PersonalActivity $record) => !$record->isRunning() && $record->status !== 'done')
                    ->action(fn (PersonalActivity $record) => $record->startTimer()),
                Tables\Actions\Action::make('pausar')
                    ->label('Pausar')
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->visible(fn (PersonalActivity $record) => $record->isRunning())
                    ->action(fn (PersonalActivity $record) => $record->stopTimer()),
                Tables\Actions\Action::make('concluir')
                    ->label('Concluir')
                    ->icon('heroicon-o-check-circle')
                    ->color('primary')
                    ->visible(fn (PersonalActivity $record) => $record->status !== 'done')
                    ->requiresConfirmation()
                    ->action(fn (PersonalActivity $record) => $record->markDone()),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('Nenhuma atividade cadastrada')
            ->emptyStateDescription('Cadastre sua primeira atividade do dia e ligue o timer quando começar a trabalhar nela.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPersonalActivities::route('/'),
        ];
    }
}
