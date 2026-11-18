<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LoginLogResource\Pages;
use App\Models\LoginLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LoginLogResource extends Resource
{
    protected static ?string $model = LoginLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationLabel = 'Histórico de Logins';

    protected static ?string $modelLabel = 'Log de Login';

    protected static ?string $pluralModelLabel = 'Logs de Logins';

    protected static ?string $navigationGroup = 'Monitoramento e Logs';

    protected static ?int $navigationSort = 110;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('email')
                    ->label('E-mail Utilizado')
                    ->disabled(),
                Forms\Components\TextInput::make('ip_address')
                    ->label('Endereço IP')
                    ->disabled(),
                Forms\Components\TextInput::make('status')
                    ->label('Status')
                    ->disabled(),
                Forms\Components\DateTimePicker::make('login_at')
                    ->label('Data/Hora')
                    ->disabled(),
                Forms\Components\Textarea::make('user_agent')
                    ->label('User-Agent')
                    ->disabled()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuário')
                    ->placeholder('N/A / Usuário Não Identificado')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('E-mail Informado')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('Endereço IP')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Resultado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'success' => 'Sucesso',
                        'failed' => 'Falha',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('login_at')
                    ->label('Data/Hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Detalhes'),
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['user']);
    }

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->role === 'admin';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoginLogs::route('/'),
        ];
    }
}
