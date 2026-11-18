<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmailLogResource\Pages;
use App\Models\EmailLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EmailLogResource extends Resource
{
    protected static ?string $model = EmailLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationLabel = 'Mural de E-mails';

    protected static ?string $modelLabel = 'Log de E-mail';

    protected static ?string $pluralModelLabel = 'Log de E-mails';

    protected static ?string $navigationGroup = 'Monitoramento e Logs';

    protected static ?int $navigationSort = 90;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('recipient')
                    ->label('Destinatário')
                    ->disabled(),
                Forms\Components\TextInput::make('subject')
                    ->label('Assunto')
                    ->disabled(),
                Forms\Components\DateTimePicker::make('sent_at')
                    ->label('Enviado em')
                    ->disabled(),
                Forms\Components\Select::make('status')
                    ->options([
                        'success' => 'Sucesso',
                        'failed' => 'Falha',
                    ])
                    ->label('Status')
                    ->disabled(),
                Forms\Components\RichEditor::make('body')
                    ->label('Corpo do E-mail')
                    ->disabled()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('recipient')
                    ->label('Destinatário')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subject')
                    ->label('Assunto')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Enviado em')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Visualizar'),
            ])
            ->bulkActions([
                //
            ])
            ->defaultSort('sent_at', 'desc');
    }

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->role === 'admin';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailLogs::route('/'),
        ];
    }
}
