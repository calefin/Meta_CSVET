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
            ->recordUrl(fn ($record) => $record->canStudentEdit()
                ? DocumentSubmissionResource::getUrl('edit', ['record' => $record])
                : DocumentSubmissionResource::getUrl('view', ['record' => $record]))
            ->paginated(false)
            ->emptyStateHeading('Você ainda não enviou nenhum documento')
            ->emptyStateDescription('Use o botão "Enviar Novo Documento" acima para começar.');
    }
}
