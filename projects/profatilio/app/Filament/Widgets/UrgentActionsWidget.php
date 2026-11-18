<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\DocumentSubmissionResource;
use App\Filament\Resources\DocumentSubmissionResource\Pages\ListDocumentSubmissions;
use App\Models\DocumentSubmission;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UrgentActionsWidget extends BaseWidget
{
    protected static ?string $heading = '⚠️ Ações Urgentes — Aguardando seu Parecer / Triagem';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 0; // Exibe no topo do painel

    /**
     * Limiares de SLA (em dias parado no mesmo status) usados para escalar visualmente
     * os itens mais antigos, em vez de tratar "chegou ontem" igual a "esquecido há semanas".
     * Públicos porque o mesmo limiar é reaproveitado na coluna "Parado Há" da listagem
     * completa de processos (DocumentSubmissionResource), para não duplicar o valor.
     */
    public const SLA_WARNING_DAYS = 3;
    public const SLA_DANGER_DAYS = 7;

    public static function canView(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                DocumentSubmission::query()
                    // Reaproveita a mesma lista canônica de "status com a bola no professor"
                    // usada em ListDocumentSubmissions, para não duplicar/desatualizar em dois
                    // lugares (já causou bugs de status sumindo desta listagem no passado).
                    ->whereIn('status', array_merge(
                        ['Aguardando Triagem', 'pending'],
                        ListDocumentSubmissions::PROFESSOR_STATUSES
                    ))
                    ->oldest('updated_at') // Os que esperam há mais tempo aparecem primeiro
            )
            ->columns([
                Tables\Columns\TextColumn::make('process_id')
                    ->label('ID do Processo')
                    ->copyable()
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('student.user.name')
                    ->label('Aluno')
                    ->searchable(),
                Tables\Columns\TextColumn::make('document_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'TCC', 'Concepção de TCC', 'Objetivos de TCC' => 'primary',
                        'Iniciação Científica', 'Termo de Aceite de IC' => 'warning',
                        'Relatório', 'Artigo Científico', 'Relato de Caso', 'Trabalho de Congresso', 'Resumo para Evento', 'Banner para Apresentação' => 'info',
                        'Plano de Estágio', 'Termo de Aceite de Orientação', 'Projeto de TCC (Assinatura)', 'TCC Final (Assinatura)', 'Avaliações de Estágio', 'Relatório de Necropsia' => 'success',
                        default => 'gray',
                    })
                    ->label('Tipo'),
                Tables\Columns\TextColumn::make('title')
                    ->limit(35)
                    ->label('Título / Descrição'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color('danger') // Vermelho destacado para pendências
                    ->label('Etapa Pendente'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Aguardando Desde')
                    ->since()
                    ->dateTimeTooltip()
                    ->color(fn ($record) => match (true) {
                        $record->updated_at->diffInDays(now()) >= self::SLA_DANGER_DAYS => 'danger',
                        $record->updated_at->diffInDays(now()) >= self::SLA_WARNING_DAYS => 'warning',
                        default => 'gray',
                    })
                    ->weight(fn ($record) => $record->updated_at->diffInDays(now()) >= self::SLA_DANGER_DAYS ? 'bold' : null)
                    ->icon(fn ($record) => $record->updated_at->diffInDays(now()) >= self::SLA_DANGER_DAYS ? 'heroicon-o-fire' : null)
                    ->iconColor('danger'),
            ])
            ->actions([
                Tables\Actions\Action::make('habilitarRapido')
                    ->label('Habilitar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->button()
                    ->requiresConfirmation()
                    ->modalHeading('Habilitar Processo')
                    ->modalDescription('O processo entrará em análise/andamento e o aluno será notificado por e-mail/Telegram.')
                    ->modalSubmitActionLabel('Habilitar')
                    ->visible(fn ($record) => $record->status === 'Aguardando Triagem')
                    ->action(function ($record) {
                        $record->update(['status' => 'Habilitado']);

                        \Filament\Notifications\Notification::make()
                            ->title('Processo Habilitado')
                            ->success()
                            ->body("O processo {$record->process_id} foi habilitado e o aluno foi notificado.")
                            ->send();
                    }),
                Tables\Actions\Action::make('evaluate')
                    ->label('Avaliar')
                    ->icon('heroicon-o-pencil-square')
                    ->color('danger')
                    ->button()
                    ->url(fn ($record) => DocumentSubmissionResource::getUrl('edit', ['record' => $record])),
            ])
            ->recordUrl(fn ($record) => DocumentSubmissionResource::getUrl('edit', ['record' => $record]))
            ->emptyStateHeading('Nenhuma ação urgente pendente')
            ->emptyStateDescription('Todos os processos enviados pelos alunos já foram respondidos ou estão aguardando correções por parte deles. Bom trabalho!');
    }
}
