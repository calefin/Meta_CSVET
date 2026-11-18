<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClassDiaryEntryResource\Pages;
use App\Models\ClassDiaryEntry;
use App\Models\ClassGroup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ClassDiaryEntryResource extends Resource
{
    protected static ?string $model = ClassDiaryEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Diário de Classe';

    protected static ?string $modelLabel = 'Registro de Diário';

    protected static ?string $pluralModelLabel = 'Diário de Classe';

    protected static ?string $navigationGroup = 'Gestão Pessoal';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->role === 'admin';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('class_group_id')
                    ->label('Turma')
                    ->options(fn () => ClassGroup::where('active', true)->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\DatePicker::make('date')
                    ->label('Data da Aula')
                    ->native(false)
                    ->default(now())
                    ->required(),
                Forms\Components\Textarea::make('content')
                    ->label('Conteúdo Ministrado')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('progress_notes')
                    ->label('Até Onde Parou / Observações')
                    ->rows(3)
                    ->columnSpanFull(),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('classGroup.name')
                    ->label('Turma')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('content')
                    ->label('Conteúdo Ministrado')
                    ->limit(60)
                    ->wrap(),
                Tables\Columns\TextColumn::make('progress_notes')
                    ->label('Até Onde Parou')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultGroup('classGroup.name')
            ->filters([
                Tables\Filters\SelectFilter::make('class_group_id')
                    ->label('Turma')
                    ->relationship('classGroup', 'name'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('date', 'desc')
            ->emptyStateHeading('Nenhum registro de diário ainda')
            ->emptyStateDescription('Cadastre uma turma primeiro, depois registre aqui o conteúdo de cada aula.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClassDiaryEntries::route('/'),
        ];
    }
}
