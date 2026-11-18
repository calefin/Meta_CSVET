<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PressMentionResource\Pages;
use App\Models\PressMention;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PressMentionResource extends Resource
{
    protected static ?string $model = PressMention::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationLabel = 'Na Mídia';

    protected static ?string $modelLabel = 'Menção na Mídia';

    protected static ?string $pluralModelLabel = 'Menções na Mídia';

    protected static ?string $navigationGroup = 'Site & Blog';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->role === 'admin';
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->role === 'admin';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label('Título da Matéria')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('url')
                    ->label('Link da Matéria')
                    ->url()
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('outlet')
                    ->label('Veículo')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Ex: Metrópoles'),
                Forms\Components\TextInput::make('section')
                    ->label('Coluna / Seção')
                    ->maxLength(255)
                    ->placeholder('Ex: É o Bicho'),
                Forms\Components\TextInput::make('author')
                    ->label('Autor(a) da Matéria')
                    ->maxLength(255),
                Forms\Components\DatePicker::make('published_at')
                    ->label('Data de Publicação')
                    ->native(false)
                    ->required(),
                Forms\Components\Textarea::make('excerpt')
                    ->label('Resumo')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->label('Título'),
                Tables\Columns\TextColumn::make('outlet')
                    ->badge()
                    ->label('Veículo'),
                Tables\Columns\TextColumn::make('section')
                    ->label('Coluna'),
                Tables\Columns\TextColumn::make('published_at')
                    ->date('d/m/Y')
                    ->sortable()
                    ->label('Data'),
            ])
            ->defaultSort('published_at', 'desc')
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

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPressMentions::route('/'),
            'create' => Pages\CreatePressMention::route('/create'),
            'edit' => Pages\EditPressMention::route('/{record}/edit'),
        ];
    }
}
