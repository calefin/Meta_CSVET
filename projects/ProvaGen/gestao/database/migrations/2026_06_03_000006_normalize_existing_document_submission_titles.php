<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\DocumentSubmission;
use App\Models\AcademicTitle;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Normaliza todos os títulos de submissões existentes no banco
        $submissions = DocumentSubmission::all();
        foreach ($submissions as $submission) {
            if (!empty($submission->title)) {
                $normalized = AcademicTitle::normalizeTitle($submission->title);
                if ($submission->title !== $normalized) {
                    $submission->title = $normalized;
                    $submission->saveQuietly();
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
