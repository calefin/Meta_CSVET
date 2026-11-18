<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\DocumentSubmissionResource;
use App\Models\DocumentSubmission;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class ActiveSubmissionsWidget extends BaseWidget
{
    protected static ?string $heading = 'Processos Acadêmicos em Andamento';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                DocumentSubmission::query()
                    // Só colunas leves: com "select *" o MySQL ordenava a linha
                    // inteira (metadata com o texto das etapas de TCC) e estourava
                    // o sort buffer da HostGator ("1038 Out of sort memory").
                    ->select(['id', 'process_id', 'student_id', 'document_type', 'title', 'status', 'created_at', 'updated_at'])
                    ->where('status', 'not like', 'Processo Concluído%')
                    ->whereNotIn('status', ['failed', 'approved', 'returned_signed', 'Assinatura Realizada e Auditada', 'Cancelado'])
                    ->latest('updated_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('process_id')
                    ->label('ID do Processo')
                    ->copyable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('student.user.name')
                    ->label('Aluno')
                    ->formatStateUsing(function ($state) {
                        if (auth()->user()->role === 'student' && $state) {
                            $parts = explode(' ', trim($state));
                            return count($parts) > 1 ? $parts[0] . ' ' . end($parts) : $state;
                        }
                        return $state;
                    })
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
                Tables\Columns\TextColumn::make('student.id')
                    ->label('Prazo Trimestral IC')
                    ->badge()
                    ->color(function ($record): string {
                        $student = $record->student;
                        if (!$student) return 'gray';
                        $info = $student->getICDeadlineInfo();
                        if (!$info['has_ic']) return 'gray';
                        return $info['color'];
                    })
                    ->formatStateUsing(function ($record): string {
                        $student = $record->student;
                        if (!$student) return '-';
                        $info = $student->getICDeadlineInfo();
                        if (!$info['has_ic']) return '-';
                        return $info['quarter'] . ': ' . $info['label'];
                    }),

                Tables\Columns\TextColumn::make('title')
                    ->limit(40)
                    ->label('Título do Trabalho'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending', 
                        'Aguardando Correção do Professor', 
                        'Projeto em Análise Inicial pelo Orientador', 
                        'Aguardando Revisão do Professor', 
                        'Aguardando Assinatura do Professor', 
                        'Avaliações de Estágio Registradas e Recebidas', 
                        'Relatório de Necropsia Recebido - Aguardando Análise do Professor', 
                        'Termos Aceitos e Auditados - Aguardando Assinatura do Orientador',
                        'Aguardando Definição de Tema',
                        'Aguardando Correção dos Objetivos',
                        'reviewed' => 'warning',

                        'reviewing',
                        'auditing',
                        'Em análise final para assinatura institucional' => 'info',
                        
                        'needs_corrections',
                        'Aguardando Ajustes do Aluno',
                        'Documento Assinado - Aguardando Correções do Aluno',
                        'Aguardando Ajustes no Laudo pelo Aluno',
                        'Aguardando Objetivos do Aluno',
                        'Aguardando Tema do Aluno',
                        'Aguardando Introdução do Aluno',
                        'Aguardando Correção da Introdução',
                        'Aguardando Materiais e Métodos do Aluno',
                        'Aguardando Correção de Materiais e Métodos',
                        'Aguardando Resultados do Aluno',
                        'Aguardando Correção dos Resultados',
                        'Aguardando Discussão do Aluno',
                        'Aguardando Correção da Discussão',
                        'Aguardando Conclusão do Aluno',
                        'Aguardando Correção da Conclusão' => 'danger',
                        
                        default => 'gray',
                    })
                    ->label('Etapa Atual'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime('d/m/Y H:i')
                    ->label('Última Atividade'),
            ])
            ->actions([
                Tables\Actions\Action::make('view_process')
                    ->label('Ir para Processo')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('primary')
                    ->url(fn ($record) => DocumentSubmissionResource::getUrl('edit', ['record' => $record])),
            ])
            ->recordUrl(function ($record) {
                if (auth()->user()->role === 'admin') {
                    return DocumentSubmissionResource::getUrl('edit', ['record' => $record]);
                }
                if ($record->canStudentEdit()) {
                    return DocumentSubmissionResource::getUrl('edit', ['record' => $record]);
                }
                return DocumentSubmissionResource::getUrl('view', ['record' => $record]);
            })
            ->headerActions([
                Tables\Actions\Action::make('relatorioConsolidado')
                    ->label('Exportar PDF Relatório Consolidado')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->url(route('admin.relatorio-consolidado'))
                    ->openUrlInNewTab()
                    ->visible(fn () => auth()->user()->role === 'admin'),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                if (auth()->user()->role === 'student') {
                    $query->whereHas('student', function ($q) {
                        $q->where('user_id', auth()->id());
                    });
                }
                return $query;
            });
    }

}
