<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\DocumentSubmissionResource;
use App\Models\DocumentSubmission;
use App\Models\Student;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class MyLastSubmissionsWidget extends BaseWidget
{
    protected static ?string $heading = '📋 Meus Últimos Envios';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = -1; // Logo abaixo das ações rápidas

    public static function canView(): bool
    {
        return auth()->user()?->role === 'student';
    }

    public function table(Table $table): Table
    {
        $studentId = Student::where('user_id', auth()->id())->value('id');

        return $table
            ->query(
                DocumentSubmission::query()
                    ->where('student_id', $studentId)
                    ->latest('created_at')
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('process_id')
                    ->label('Processo')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('document_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'TCC', 'TCC Completo (Envio Único)', 'Concepção de TCC', 'Objetivos de TCC', 'Introdução de TCC', 'Materiais e Métodos de TCC', 'Resultados de TCC', 'Discussão de TCC', 'Conclusão de TCC' => 'primary',
                        'Iniciação Científica', 'Termo de Aceite de IC', 'Protocolo CEUA/CEP' => 'warning',
                        'Relatório', 'Artigo Científico', 'Relato de Caso', 'Trabalho de Congresso', 'Resumo para Evento', 'Banner para Apresentação' => 'info',
                        'Plano de Estágio', 'Termo de Aceite de Orientação', 'Projeto de TCC (Assinatura)', 'TCC Final (Assinatura)', 'Avaliações de Estágio', 'Relatório de Necropsia', 'Relatório de Estágio', 'Documento Solicitado' => 'success',
                        default => 'gray',
                    })
                    ->label('Tipo'),
                Tables\Columns\TextColumn::make('title')
                    ->limit(35)
                    ->label('Título'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        in_array($state, ['approved', 'returned_signed', 'Assinatura Realizada e Auditada']) || str_starts_with($state, 'Processo Concluído') => 'success',
                        in_array($state, DocumentSubmission::STUDENT_ACTIONABLE_STATUSES) => 'danger',
                        default => 'warning',
                    })
                    ->label('Status'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Enviado')
                    ->since()
                    ->dateTimeTooltip(),
            ])
            ->actions([
                Tables\Actions\Action::make('baixar')
                    ->label('Baixar Assinado')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->button()
                    ->size('xs')
                    ->url(fn ($record) => route('documento.download', ['id' => $record->id, 'type' => 'returned']))
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => !empty($record->google_drive_returned_file_id)),
            ])
            ->recordUrl(fn ($record) => $record->canStudentEdit()
                ? DocumentSubmissionResource::getUrl('edit', ['record' => $record])
                : DocumentSubmissionResource::getUrl('view', ['record' => $record]))
            ->paginated(false)
            ->emptyStateHeading('Você ainda não enviou nenhum documento')
            ->emptyStateDescription('Use o botão "Enviar Novo Documento" acima para começar.');
    }
}
