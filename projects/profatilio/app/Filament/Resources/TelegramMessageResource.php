<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TelegramMessageResource\Pages;
use App\Models\TelegramMessage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TelegramMessageResource extends Resource
{
    protected static ?string $model = TelegramMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Mural do Telegram';

    protected static ?string $modelLabel = 'Mensagem do Telegram';

    protected static ?string $pluralModelLabel = 'Mensagens do Telegram';

    protected static ?string $navigationGroup = 'Monitoramento e Logs';

    protected static ?int $navigationSort = 95;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('student_name')
                    ->label('Aluno')
                    ->disabled()
                    ->default(fn ($record) => $record?->student?->user?->name ?? 'Não Vinculado'),
                Forms\Components\TextInput::make('chat_id')
                    ->label('Telegram Chat ID')
                    ->disabled(),
                Forms\Components\DateTimePicker::make('created_at')
                    ->label('Recebida em')
                    ->disabled(),
                Forms\Components\Toggle::make('is_read')
                    ->label('Lida / Respondida')
                    ->disabled(fn () => auth()->user()->role !== 'admin'),
                Forms\Components\Textarea::make('message_text')
                    ->label('Conteúdo da Mensagem')
                    ->disabled()
                    ->columnSpanFull()
                    ->rows(5),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['student.user']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('student.user.name')
                    ->label('Aluno')
                    ->placeholder('Não Vinculado')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('message_text')
                    ->label('Mensagem')
                    ->limit(60)
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_read')
                    ->label('Lida')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Recebida em')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('unread')
                    ->label('Apenas Não Lidas')
                    ->query(fn ($query) => $query->where('is_read', false)),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Visualizar'),
                Tables\Actions\Action::make('reply')
                    ->label('Responder')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('primary')
                    ->form([
                        Forms\Components\Textarea::make('reply_text')
                            ->label('Mensagem de Resposta')
                            ->required()
                            ->rows(5)
                            ->placeholder('Digite aqui a resposta que será enviada para o Telegram do usuário...'),
                    ])
                    ->action(function ($record, array $data) {
                        $telegramService = app(\App\Services\TelegramService::class);
                        
                        $responseMsg = "<b>Mensagem do Prof. Atilio:</b>\n\n" . $data['reply_text'];
                        
                        $success = $telegramService->sendMessage($record->chat_id, $responseMsg);
                        
                        if ($success) {
                            $record->update(['is_read' => true]);
                            // Marcar outras mensagens pendentes do mesmo chat_id como lidas
                            \App\Models\TelegramMessage::where('chat_id', $record->chat_id)
                                ->where('is_read', false)
                                ->update(['is_read' => true]);

                            \Filament\Notifications\Notification::make()
                                ->title('Resposta enviada com sucesso!')
                                ->success()
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title('Erro ao enviar mensagem via Telegram')
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('markAsRead')
                    ->label('Marcar como Lida')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->action(fn ($record) => $record->update(['is_read' => true]))
                    ->visible(fn ($record) => !$record->is_read),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('markAllAsRead')
                        ->label('Marcar como Lidas')
                        ->icon('heroicon-o-check')
                        ->action(fn ($records) => $records->each->update(['is_read' => true])),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Somente o orientador (admin) pode acessar o mural de mensagens do Telegram.
     */
    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->role === 'admin';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTelegramMessages::route('/'),
        ];
    }
}
