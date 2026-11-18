<?php

namespace App\Filament\Resources\SystematicReviewResource\RelationManagers;

use App\Models\SrMessage;
use App\Services\SystematicReviewService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/** Conversa registrada do projeto (professor + dupla). Nada é apagado nem editado. */
class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    protected static ?string $title = 'Conversa do projeto';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->description('Mensagens trocadas com a dupla pelo portal (Triagem Cega), a mais nova primeiro. Tudo fica registrado; os alunos recebem aviso por e-mail.')
            // A relação messages() vem em ordem cronológica (o portal usava assim): sem o reorder(),
            // ela passava na frente do defaultSort e a mensagem nova ficava no fim da lista.
            ->modifyQueryUsing(fn ($query) => $query->reorder())
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Quando')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('author_name')->label('Autor')
                    ->badge()->color(fn (SrMessage $m) => $m->author_role === SrMessage::ROLE_PROFESSOR ? 'success' : 'info')
                    ->description(fn (SrMessage $m) => $m->author_role === SrMessage::ROLE_STUDENT && !$m->seen_at ? '● nova' : null),
                Tables\Columns\TextColumn::make('body')->label('Mensagem')->wrap()->extraAttributes(['style' => 'white-space: pre-line']),
            ])
            ->headerActions([
                Tables\Actions\Action::make('todas')->label('Todas as conversas')->icon('heroicon-o-inbox-stack')->color('gray')
                    ->url(fn () => \App\Filament\Pages\CentralMetanalises::getUrl(['ver' => 'conversas'])),
                Tables\Actions\Action::make('vistas')->label('Marcar como vistas')->icon('heroicon-o-check')->color('gray')
                    ->visible(fn () => SrMessage::where('systematic_review_id', $this->getOwnerRecord()->id)
                        ->where('author_role', SrMessage::ROLE_STUDENT)->whereNull('seen_at')->exists())
                    ->action(function () {
                        SrMessage::where('systematic_review_id', $this->getOwnerRecord()->id)
                            ->where('author_role', SrMessage::ROLE_STUDENT)->whereNull('seen_at')->update(['seen_at' => now()]);
                        Notification::make()->title('Mensagens marcadas como vistas')->success()->send();
                    }),
                Tables\Actions\Action::make('escrever')->label('Escrever na conversa')->icon('heroicon-o-chat-bubble-left-ellipsis')->color('primary')
                    ->form([Forms\Components\Textarea::make('body')->label('Mensagem')->required()->rows(5)->maxLength(3000)])
                    ->action(function (array $data) {
                        try {
                            app(SystematicReviewService::class)->postMessage($this->getOwnerRecord(), auth()->user(), $data['body']);
                        } catch (\RuntimeException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }
                        Notification::make()->title('Mensagem enviada à dupla e registrada')->success()->send();
                    }),
            ])
            ->paginated([25, 50, 100]);
    }
}
