<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\DocumentSubmissionResource;
use App\Models\DocumentSubmission;
use App\Models\Student;
use Filament\Widgets\Widget;

class TccPipelineWidget extends Widget
{
    protected static string $view = 'filament.widgets.tcc-pipeline-widget';

    protected static ?int $sort = -2; // Depois do guia/ações rápidas, antes dos últimos envios

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();
        if (!$user || $user->role !== 'student') {
            return false;
        }

        $student = $user->student;
        if (!$student) {
            return false;
        }

        return DocumentSubmission::where('student_id', $student->id)
            ->where('document_type', 'Concepção de TCC')
            ->exists();
    }

    /**
     * Monta os 3 passos do pipeline de produção do TCC a partir das
     * submissões mais recentes do aluno de cada tipo, na ordem lógica de
     * produção: Concepção -> Objetivos -> TCC.
     */
    public function getSteps(): array
    {
        $studentId = Student::where('user_id', auth()->id())->value('id');

        return \App\Models\AcademicTitle::buildTccSteps($studentId);
    }

    /**
     * Checklist das 5 etapas de redação, que podem ser enviadas em qualquer
     * ordem assim que os Objetivos estiverem aprovados.
     */
    public function getWritingSegments(): array
    {
        $studentId = Student::where('user_id', auth()->id())->value('id');

        return \App\Models\AcademicTitle::buildTccWritingSegments($studentId);
    }

    public function getCreateUrl(): string
    {
        return DocumentSubmissionResource::getUrl('create');
    }
}
