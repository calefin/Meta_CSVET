<?php

namespace App\Filament\Pages;

use App\Models\DocumentSubmission;
use App\Models\Student;
use App\Support\Semester;
use Filament\Pages\Page;

/**
 * Resumo do estágio para o aluno: só locais e carga horária homologada, por
 * semestre. A nota é vista apenas pelo professor (Relatório de Estágios).
 */
class MeuEstagio extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static string $view = 'filament.pages.meu-estagio';

    protected static ?string $navigationLabel = 'Meu Estágio';

    protected static ?string $title = 'Meu Estágio — Carga Horária Homologada';

    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): ?string
    {
        return 'Meus Trabalhos';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'student';
    }

    public function getSemesters(): array
    {
        $student = Student::where('user_id', auth()->id())->first();
        if (!$student) {
            return [];
        }

        $filled = fn ($value) => $value !== null && $value !== '';

        return DocumentSubmission::where('student_id', $student->id)
            ->where('document_type', 'Avaliações de Estágio')
            ->where('status', RelatorioEstagios::HOMOLOGATED_STATUS)
            ->orderBy('created_at')
            ->get()
            ->groupBy(fn ($submission) => Semester::forDate($submission->created_at))
            ->sortKeysDesc()
            ->map(fn ($group) => [
                'locations' => (int) $group->pluck('metadata.locations_count')->filter($filled)->sum(),
                'hours' => (float) $group->pluck('metadata.total_hours')->filter($filled)->sum(),
                'records' => $group->map(fn ($submission) => [
                    'process_id' => $submission->process_id,
                    'internship_stage' => $submission->metadata['internship_stage'] ?? '—',
                    'locations' => $submission->metadata['locations_count'] ?? null,
                    'hours' => $filled($submission->metadata['total_hours'] ?? null) ? (float) $submission->metadata['total_hours'] : null,
                    'sent_at' => $submission->created_at?->format('d/m/Y'),
                ])->values()->all(),
            ])
            ->all();
    }
}
