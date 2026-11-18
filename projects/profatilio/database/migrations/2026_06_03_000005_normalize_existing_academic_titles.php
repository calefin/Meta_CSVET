<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\AcademicTitle;
use App\Models\Student;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Normaliza todos os títulos acadêmicos existentes
        $titles = AcademicTitle::all();
        foreach ($titles as $title) {
            if (!empty($title->title)) {
                $normalized = AcademicTitle::normalizeTitle($title->title);
                if ($title->title !== $normalized) {
                    $title->title = $normalized;
                    $title->saveQuietly();
                }
            }
        }

        // Normaliza todos os temas de pesquisa dos estudantes existentes
        $students = Student::all();
        foreach ($students as $student) {
            if (!empty($student->research_topic)) {
                $normalized = AcademicTitle::normalizeTitle($student->research_topic);
                if ($student->research_topic !== $normalized) {
                    $student->research_topic = $normalized;
                    $student->saveQuietly();
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Não aplicável para dados históricos normalizados
    }
};
