<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NecropsyClassParticipantResource\Pages;
use App\Models\NecropsyClassParticipant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NecropsyClassParticipantResource extends Resource
{
    protected static ?string $model = NecropsyClassParticipant::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Alunos de Necropsia';

    protected static ?string $modelLabel = 'Aluno de Necropsia';

    protected static ?string $pluralModelLabel = 'Alunos de Necropsia';

    protected static ?string $navigationGroup = 'Orientação Acadêmica';

    protected static ?int $navigationSort = 41;

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->role === 'admin';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('rgm')
                    ->label('RGM')
                    ->maxLength(255),
                Forms\Components\TextInput::make('institution')
                    ->label('Instituição')
                    ->maxLength(255),
                Forms\Components\TextInput::make('phone')
                    ->label('Telefone')
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->label('E-mail')
                    ->email()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('sessions')->with('sessions'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('rgm')
                    ->label('RGM')
                    ->searchable(),
                Tables\Columns\TextColumn::make('institution')
                    ->label('Instituição')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sessions_count')
                    ->label('Tardes Presentes')
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('total_hours')
                    ->label('Carga Horária Total')
                    ->state(fn (NecropsyClassParticipant $record) => number_format(
                        $record->sessions->sum(fn ($s) => $s->durationHours()),
                        2,
                        ',',
                        '.'
                    ) . 'h'),
                Tables\Columns\TextColumn::make('email')
                    ->label('E-mail')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefone')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNecropsyClassParticipants::route('/'),
            'edit' => Pages\EditNecropsyClassParticipant::route('/{record}/edit'),
        ];
    }
}
