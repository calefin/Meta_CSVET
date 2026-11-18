<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Student;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Normaliza todos os registros existentes no banco de dados de produção
        $students = Student::all();

        foreach ($students as $student) {
            if (!empty($student->institution)) {
                $normalized = Student::normalizeInstitution($student->institution);
                if ($student->institution !== $normalized) {
                    // Evita disparar eventos adicionais desnecessários usando update ou saveQuietly
                    $student->institution = $normalized;
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
        // A reversão de normalização de dados históricos não é aplicável
    }
};
