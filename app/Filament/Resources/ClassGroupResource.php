<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClassGroupResource\Pages;
use App\Models\ClassGroup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ClassGroupResource extends Resource
{
    protected static ?string $model = ClassGroup::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Turmas';

    protected static ?string $modelLabel = 'Turma';

    protected static ?string $pluralModelLabel = 'Turmas';

    protected static ?string $navigationGroup = 'Gestão Pessoal';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->role === 'admin';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nome da Turma / Disciplina')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('semester')
                    ->label('Semestre')
                    ->placeholder('Ex: 2026.2')
                    ->required(),
                Forms\Components\Select::make('shift')
                    ->label('Turno')
                    ->options([
                        'Matutino' => 'Matutino',
                        'Vespertino' => 'Vespertino',
                        'Noturno' => 'Noturno',
                    ])
                    ->native(false),
                Forms\Components\Toggle::make('active')
                    ->label('Turma Ativa')
                    ->default(true),
                Forms\Components\Textarea::make('notes')
                    ->label('Observações')
                    ->rows(3)
                    ->columnSpanFull(),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Turma')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('semester')
                    ->label('Semestre')
                    ->sortable(),
                Tables\Columns\TextColumn::make('shift')
                    ->label('Turno'),
                Tables\Columns\IconColumn::make('active')
                    ->label('Ativa')
                    ->boolean(),
                Tables\Columns\TextColumn::make('diary_entries_count')
                    ->label('Registros no Diário')
                    ->counts('diaryEntries'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('active')
                    ->label('Ativa'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('semester', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClassGroups::route('/'),
        ];
    }
}
