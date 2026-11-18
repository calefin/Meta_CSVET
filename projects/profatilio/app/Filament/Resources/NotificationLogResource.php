<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NotificationLogResource\Pages;
use App\Models\NotificationLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NotificationLogResource extends Resource
{
    protected static ?string $model = NotificationLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationLabel = 'Central de Cobranças e Avisos';

    protected static ?string $modelLabel = 'Aviso Enviado';

    protected static ?string $pluralModelLabel = 'Cobranças e Avisos Enviados';

    protected static ?string $navigationGroup = 'Monitoramento e Logs';

    protected static ?int $navigationSort = 91;

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->role === 'admin';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('student.user.name')
                    ->label('Aluno')
                    ->disabled(),
                Forms\Components\TextInput::make('academicTitle.title')
                    ->label('Projeto')
                    ->disabled(),
                Forms\Components\TextInput::make('category')
                    ->label('Categoria')
                    ->disabled(),
                Forms\Components\TextInput::make('channel')
                    ->label('Canal')
                    ->disabled(),
                Forms\Components\TextInput::make('title')
                    ->label('Título')
                    ->disabled(),
                Forms\Components\Textarea::make('message')
                    ->label('Mensagem')
                    ->disabled()
                    ->columnSpanFull(),
                Forms\Components\DateTimePicker::make('sent_at')
                    ->label('Enviado em')
                    ->disabled(),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['academicTitle', 'student.user']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('student.user.name')
                    ->label('Aluno')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('academicTitle.title')
                    ->label('Projeto')
                    ->limit(35)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('category')
                    ->label('Categoria')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'cobranca_ic' => 'Cobrança de IC',
                        'cobranca_tcc' => 'Cobrança de TCC',
                        'apresentacao_milestone' => 'Contagem Regressiva',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'apresentacao_milestone' => 'info',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('channel')
                    ->label('Canal')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'telegram' ? 'Telegram' : 'E-mail')
                    ->color(fn (string $state) => $state === 'telegram' ? 'info' : 'gray'),
                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->wrap()
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state) => $state === 'success' ? 'success' : 'danger')
                    ->formatStateUsing(fn (string $state) => $state === 'success' ? 'Enviado' : 'Falhou'),
                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Enviado em')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Categoria')
                    ->options([
                        'cobranca_ic' => 'Cobrança de IC',
                        'cobranca_tcc' => 'Cobrança de TCC',
                        'apresentacao_milestone' => 'Contagem Regressiva',
                    ]),
                Tables\Filters\SelectFilter::make('channel')
                    ->label('Canal')
                    ->options([
                        'email' => 'E-mail',
                        'telegram' => 'Telegram',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Visualizar'),
            ])
            ->bulkActions([])
            ->defaultSort('sent_at', 'desc')
            ->emptyStateHeading('Nenhuma cobrança ou aviso enviado ainda')
            ->emptyStateDescription('Assim que o sistema enviar cobranças de IC/TCC ou avisos de apresentação, eles aparecerão aqui.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotificationLogs::route('/'),
        ];
    }
}
