<?php

namespace App\Filament\Resources\AcademicProjectResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class SubmissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'submissions';

    protected static ?string $title = 'Documentos e Submissões Associados';

    protected static ?string $modelLabel = 'Submissão';

    protected static ?string $pluralModelLabel = 'Submissões';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('process_id')
                    ->label('ID do Processo')
                    ->disabled(),
                Forms\Components\TextInput::make('document_type')
                    ->label('Tipo de Documento')
                    ->disabled(),
                Forms\Components\TextInput::make('title')
                    ->label('Título')
                    ->disabled(),
                Forms\Components\TextInput::make('status')
                    ->label('Status')
                    ->disabled(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('process_id')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->label('ID do Processo'),
                Tables\Columns\TextColumn::make('document_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'TCC' => 'primary',
                        'Iniciação Científica', 'Termo de Aceite de IC' => 'warning',
                        'Relatório', 'Artigo Científico', 'Relato de Caso', 'Trabalho de Congresso', 'Resumo para Evento', 'Banner para Apresentação' => 'info',
                        'Plano de Estágio', 'Termo de Aceite de Orientação', 'Projeto de TCC (Assinatura)', 'TCC Final (Assinatura)', 'Avaliações de Estágio', 'Relatório de Necropsia', 'Relatório de Estágio' => 'success',
                        default => 'gray',
                    })
                    ->label('Tipo'),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->limit(40)
                    ->label('Título'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        // Mantido em sincronia com o mapeamento canônico em
                        // DocumentSubmissionResource::table() — mesma lista de status.
                        'approved', 'returned_signed',
                        'Processo Concluído - Documento Assinado Disponível',
                        'Processo Concluído - Projeto Arquivado',
                        'Processo Concluído - Versão Final Definida',
                        'Processo Concluído - Termo de Aceite Assinado',
                        'Processo Concluído - Estágio Homologado com Sucesso',
                        'Processo Concluído - Laudo de Necropsia Homologado',
                        'Processo Concluído - Orientação de IC Homologada e Ativa',
                        'Processo Concluído - Tema Definido',
                        'Processo Concluído - Objetivos Aprovados',
                        'Processo Concluído - Introdução Aprovada',
                        'Processo Concluído - Materiais e Métodos Aprovados',
                        'Processo Concluído - Resultados Aprovados',
                        'Processo Concluído - Discussão Aprovada',
                        'Processo Concluído - Conclusão Aprovada' => 'success',

                        'pending',
                        'Aguardando Correção do Professor',
                        'Projeto em Análise Inicial pelo Orientador',
                        'Aguardando Revisão do Professor',
                        'Aguardando Assinatura do Professor',
                        'Avaliações de Estágio Registradas e Recebidas',
                        'Relatório de Necropsia Recebido - Aguardando Análise do Professor',
                        'Termos Aceitos e Auditados - Aguardando Assinatura do Orientador',
                        'Aguardando Relatório Parcial',
                        'Aguardando Envio do Aluno',
                        'reviewed' => 'warning',

                        'reviewing',
                        'auditing',
                        'Em análise final para assinatura institucional' => 'info',

                        'needs_corrections',
                        'Aguardando Ajustes do Aluno',
                        'Documento Assinado - Aguardando Correções do Aluno',
                        'Aguardando Ajustes no Laudo pelo Aluno',
                        'Aguardando Definição de Tema',
                        'Aguardando Tema do Aluno',
                        'Aguardando Objetivos do Aluno',
                        'Aguardando Correção dos Objetivos',
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

                        'failed' => 'gray',
                        default => 'gray',
                    })
                    ->label('Status'),
                Tables\Columns\TextColumn::make('google_drive_file_id')
                    ->label('Arquivo Enviado')
                    ->formatStateUsing(function ($state, $record) {
                        if (!$state) {
                            return null;
                        }
                        if (auth()->user()->role === 'student' && $record->isConcluded() && !empty($record->google_drive_returned_file_id)) {
                            return null;
                        }
                        return 'Download / Abrir';
                    })
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('primary')
                    ->url(function ($record) {
                        if (!$record->google_drive_file_id) {
                            return null;
                        }
                        if (auth()->user()->role === 'student' && $record->isConcluded() && !empty($record->google_drive_returned_file_id)) {
                            return null;
                        }
                        return is_string($record->google_drive_file_id) && str_contains($record->google_drive_file_id, 'http')
                            ? $record->google_drive_file_id
                            : route('documento.download', ['id' => $record->id, 'type' => 'original']);
                    }, true),
                Tables\Columns\TextColumn::make('google_drive_returned_file_id')
                    ->label(fn ($record) => $record && $record->isConcluded() && !empty($record->google_drive_returned_file_id) ? '✅ Documento Assinado' : 'Arquivo Retornado')
                    ->formatStateUsing(fn ($state) => $state ? 'Baixar Assinado' : null)
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->weight(fn ($record) => $record && $record->isConcluded() && !empty($record->google_drive_returned_file_id) ? 'bold' : null)
                    ->url(fn ($record) => $record->google_drive_returned_file_id
                        ? (is_string($record->google_drive_returned_file_id) && str_contains($record->google_drive_returned_file_id, 'http')
                            ? $record->google_drive_returned_file_id
                            : route('documento.download', ['id' => $record->id, 'type' => 'returned']))
                        : null, true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i')
                    ->label('Enviado em'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('document_group')
                    ->options([
                        'projetos' => 'Projetos & TCC',
                        'relatorios' => 'Relatórios & Laudos',
                        'termos' => 'Termos & Aceites',
                        'avaliacoes' => 'Estágios & Avaliações',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if ($data['value'] === 'projetos') {
                            $query->whereIn('document_type', ['TCC', 'Iniciação Científica']);
                        } elseif ($data['value'] === 'relatorios') {
                            $query->whereIn('document_type', ['Relatório', 'Artigo Científico', 'Relato de Caso', 'Trabalho de Congresso', 'Resumo para Evento', 'Banner para Apresentação', 'Relatório de Necropsia', 'Relatório de Estágio', 'Relatório de Aula', 'Protocolo CEUA/CEP']);
                        } elseif ($data['value'] === 'termos') {
                            $query->whereIn('document_type', ['Termo de Aceite de Orientação', 'Termo de Aceite de IC', 'Plano de Estágio', 'Projeto de TCC (Assinatura)', 'TCC Final (Assinatura)']);
                        } elseif ($data['value'] === 'avaliacoes') {
                            $query->where('document_type', 'Avaliações de Estágio');
                        }
                    })
                    ->label('Categoria de Documento')
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('recibo')
                    ->label('Recibo PDF')
                    ->icon('heroicon-o-document-check')
                    ->color('success')
                    ->url(fn ($record) => url("/documento/recibo/{$record->id}"))
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => $record->document_type === 'Termo de Aceite de IC' && isset($record->metadata['forensic_hash'])),
            ])
            ->bulkActions([])
            ->recordUrl(function ($record) {
                if (auth()->user()->role === 'admin') {
                    return \App\Filament\Resources\DocumentSubmissionResource::getUrl('edit', ['record' => $record]);
                }
                if ($record->canStudentEdit()) {
                    return \App\Filament\Resources\DocumentSubmissionResource::getUrl('edit', ['record' => $record]);
                }
                return \App\Filament\Resources\DocumentSubmissionResource::getUrl('view', ['record' => $record]);
            });
    }
}
