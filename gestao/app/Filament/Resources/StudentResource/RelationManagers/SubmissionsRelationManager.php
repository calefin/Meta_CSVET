<?php

namespace App\Filament\Resources\StudentResource\RelationManagers;

use App\Filament\Resources\DocumentSubmissionResource;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SubmissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'submissions';

    protected static ?string $title = 'Documentos Recebidos (Todos os Processos)';

    protected static ?string $modelLabel = 'Documento';

    protected static ?string $pluralModelLabel = 'Documentos';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('process_id')
                    ->label('Processo')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('document_type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'TCC' => 'primary',
                        'Iniciação Científica', 'Termo de Aceite de IC' => 'warning',
                        'Relatório', 'Artigo Científico', 'Relato de Caso', 'Trabalho de Congresso' => 'info',
                        'Plano de Estágio', 'Termo de Aceite de Orientação', 'Avaliações de Estágio', 'Relatório de Necropsia', 'Relatório de Estágio' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->limit(35)
                    ->wrap(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->wrap(),
                Tables\Columns\TextColumn::make('aguardando')
                    ->label('Aguardando')
                    ->badge()
                    ->state(fn ($record) => $record->isConcluded()
                        ? 'Concluído'
                        : ($record->canStudentEdit() ? 'Ação do Aluno' : 'Ação do Professor'))
                    ->color(fn ($record) => $record->isConcluded()
                        ? 'success'
                        : ($record->canStudentEdit() ? 'warning' : 'info')),
                Tables\Columns\TextColumn::make('google_drive_file_id')
                    ->label('Arquivo do Aluno')
                    ->formatStateUsing(fn ($state) => $state ? 'Abrir' : null)
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('primary')
                    ->url(fn ($record) => $record->google_drive_file_id
                        ? (is_string($record->google_drive_file_id) && str_contains($record->google_drive_file_id, 'http')
                            ? $record->google_drive_file_id
                            : route('documento.download', ['id' => $record->id, 'type' => 'original']))
                        : null, true),
                Tables\Columns\TextColumn::make('google_drive_returned_file_id')
                    ->label('Arquivo do Professor')
                    ->formatStateUsing(fn ($state) => $state ? 'Abrir' : null)
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->url(fn ($record) => $record->google_drive_returned_file_id
                        ? (is_string($record->google_drive_returned_file_id) && str_contains($record->google_drive_returned_file_id, 'http')
                            ? $record->google_drive_returned_file_id
                            : route('documento.download', ['id' => $record->id, 'type' => 'returned']))
                        : null, true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Recebido em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('document_group')
                    ->label('Categoria')
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
                            $query->whereIn('document_type', ['Relatório', 'Artigo Científico', 'Relato de Caso', 'Trabalho de Congresso', 'Relatório de Necropsia', 'Relatório de Estágio', 'Relatório de Aula', 'Protocolo CEUA/CEP']);
                        } elseif ($data['value'] === 'termos') {
                            $query->whereIn('document_type', ['Termo de Aceite de Orientação', 'Termo de Aceite de IC', 'Plano de Estágio']);
                        } elseif ($data['value'] === 'avaliacoes') {
                            $query->where('document_type', 'Avaliações de Estágio');
                        }
                    }),
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
            ->recordUrl(fn ($record) => DocumentSubmissionResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Nenhum documento recebido ainda')
            ->emptyStateDescription('Todos os processos e documentos do aluno aparecerão aqui, organizados por data de recebimento.');
    }

    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->role === 'admin';
    }
}
