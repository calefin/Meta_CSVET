<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Destaque no dashboard para chamados da Central de Dúvidas com mensagem do
 * aluno ainda não respondida — antes disso só havia o badge pequeno no menu
 * lateral, fácil de não notar, e um chamado podia ficar esquecido/pendente
 * mesmo com o e-mail/Telegram de aviso já enviado. Espelha o mesmo padrão do
 * UrgentActionsWidget (mais antigos primeiro, ação de um clique).
 */
class PendingSupportTicketsWidget extends BaseWidget
{
    protected static ?string $heading = '💬 Chamados Aguardando sua Resposta';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 1; // Logo abaixo de "Ações Urgentes"

    public static function canView(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    protected static function pendingQuery(): Builder
    {
        return SupportTicket::query()
            ->where('status', 'open')
            ->whereHas('messages', fn (Builder $q) => $q->whereNull('read_at')->whereHas('user', fn ($uq) => $uq->where('role', '!=', 'admin')))
            ->with(['student.user', 'messages']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(static::pendingQuery()->oldest('last_message_at'))
            ->columns([
                Tables\Columns\TextColumn::make('subject')
                    ->label('Assunto')
                    ->weight('bold')
                    ->searchable(),
                Tables\Columns\TextColumn::make('student.user.name')
                    ->label('Aluno')
                    ->searchable(),
                Tables\Columns\TextColumn::make('messages')
                    ->label('Última Mensagem')
                    ->state(fn (SupportTicket $record) => $record->messages->last()?->message)
                    ->html()
                    ->formatStateUsing(fn (?string $state) => $state ? \Illuminate\Support\Str::limit(strip_tags($state), 80) : '—')
                    ->wrap(),
                Tables\Columns\TextColumn::make('last_message_at')
                    ->label('Aguardando Desde')
                    ->since()
                    ->dateTimeTooltip()
                    ->color(fn ($record) => $record->last_message_at && $record->last_message_at->diffInDays(now()) >= 3 ? 'danger' : 'warning')
                    ->weight('bold'),
            ])
            ->actions([
                Tables\Actions\Action::make('responder')
                    ->label('Responder')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('danger')
                    ->button()
                    ->url(fn (SupportTicket $record) => SupportTicketResource::getUrl('view', ['record' => $record])),
            ])
            ->recordUrl(fn (SupportTicket $record) => SupportTicketResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Nenhum chamado pendente')
            ->emptyStateDescription('Todas as mensagens dos alunos na Central de Dúvidas já foram respondidas.');
    }
}
