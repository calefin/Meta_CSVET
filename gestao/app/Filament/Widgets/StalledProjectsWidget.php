<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\AcademicProjectResource;
use App\Mail\BulkEmailAlunos;
use App\Models\AcademicTitle;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Mail;

class StalledProjectsWidget extends BaseWidget
{
    protected static ?string $heading = '🚨 Orientandos Sem Progresso';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 1; // Logo abaixo do UrgentActionsWidget

    public static function canView(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AcademicTitle::query()
                    ->with(['student.user', 'submissions', 'deadlines'])
                    ->where(function ($q) {
                        $q->whereHas('submissions', function ($sq) {
                            $sq->whereIn('status', \App\Models\DocumentSubmission::STUDENT_ACTIONABLE_STATUSES)
                                ->where('updated_at', '<', now()->subDays(15));
                        })->orWhereHas('deadlines', function ($dq) {
                            $dq->where('status', 'pending')->where('type', 'cobranca')->where('due_date', '<', now());
                        });
                    })
            )
            ->columns([
                Tables\Columns\TextColumn::make('student.user.name')
                    ->label('Aluno'),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->label('Tipo'),
                Tables\Columns\TextColumn::make('title')
                    ->limit(35)
                    ->label('Projeto'),
                Tables\Columns\TextColumn::make('motivo')
                    ->label('Motivo')
                    ->state(fn (AcademicTitle $record) => $record->getProgressInfo()['stall_reason'])
                    ->badge()
                    ->color('danger'),
            ])
            ->defaultSort('updated_at', 'asc')
            ->actions([
                Tables\Actions\Action::make('cobrarAgora')
                    ->label('Enviar Cobrança Agora')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Enviar cobrança avulsa')
                    ->modalDescription('Um e-mail de cobrança será enviado agora ao aluno lembrando do andamento pendente.')
                    ->action(function (AcademicTitle $record) {
                        $email = $record->student?->user?->email;
                        $name = $record->student?->user?->name ?? 'Aluno';

                        if (!$email) {
                            Notification::make()->title('Aluno sem e-mail cadastrado.')->warning()->send();
                            return;
                        }

                        $info = $record->getProgressInfo();

                        Mail::to($email)->send(new BulkEmailAlunos(
                            subject: "Lembrete: andamento pendente — {$record->title}",
                            htmlBody: "Olá, <strong>{$name}</strong>!<br><br>"
                                . "Este é um lembrete de que o seu projeto <strong>\"{$record->title}\"</strong> ({$record->type}) "
                                . "está aguardando uma ação sua há {$info['days_inactive']} dias.<br><br>"
                                . "Por favor, acesse o portal e regularize a pendência assim que possível.<br><br>"
                                . "Atenciosamente,<br><strong>Prof. Dr. Atilio Sersun Calefi</strong>",
                            recipientName: $name,
                        ));

                        Notification::make()->title("Cobrança enviada para {$name}.")->success()->send();
                    }),
                Tables\Actions\Action::make('abrir')
                    ->label('Abrir Projeto')
                    ->icon('heroicon-o-folder-open')
                    ->url(fn (AcademicTitle $record) => AcademicProjectResource::getUrl('view', ['record' => $record])),
            ])
            ->paginated([10])
            ->emptyStateHeading('Nenhum orientando parado')
            ->emptyStateDescription('Todos os projetos ativos estão com prazos em dia. Bom trabalho!');
    }
}
