<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\DocumentSubmissionResource;
use App\Models\DocumentSubmission;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UrgentActionsWidget extends BaseWidget
{
    protected static ?string $heading = '⚠️ Ações Urgentes — Aguardando seu Parecer / Triagem';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 0; // Exibe no topo do painel

    public static function canView(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                DocumentSubmission::query()
                    ->whereIn('status', [
                        'Aguardando Triagem',
                        'pending',
                        'Aguardando Correção do Professor',
                        'Projeto em Análise Inicial pelo Orientador',
                        'Aguardando Revisão do Professor',
                        'Aguardando Assinatura do Professor',
                        'Avaliações de Estágio Registradas e Recebidas',
                        'Relatório de Necropsia Recebido - Aguardando Análise do Professor',
                        'Termos Aceitos e Auditados - Aguardando Assinatura do Orientador',
                        'Aguardando Definição de Tema',
                        'Aguardando Correção dos Objetivos'
                    ])
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
                        'Relatório', 'Artigo Científico', 'Relato de Caso', 'Trabalho de Congresso' => 'info',
                        'Plano de Estágio', 'Termo de Aceite de Orientação', 'Avaliações de Estágio', 'Relatório de Necropsia' => 'success',
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
                    ->color('warning'),
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
