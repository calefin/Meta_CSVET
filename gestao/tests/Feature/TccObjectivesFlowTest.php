<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Student;
use App\Models\DocumentSubmission;
use App\Models\AcademicTitle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TccObjectivesFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_concepcao_de_tcc_approval_automatically_creates_title_and_objectives(): void
    {
        // 1. Create student user and details
        $user = User::factory()->create([
            'role' => 'student',
            'name' => 'Ana Student',
            'email' => 'ana@example.com',
        ]);

        $student = Student::create([
            'user_id' => $user->id,
            'institution' => 'Universidade Vet',
            'academic_level' => 'Graduação',
            'registration' => '54321',
            'research_topic' => 'Não informado',
        ]);

        // 2. Create a "Concepção de TCC" submission
        $submission = DocumentSubmission::create([
            'student_id' => $student->id,
            'title' => 'Uso de Células-Tronco em Lesões de Tendão de Equinos',
            'document_type' => 'Concepção de TCC',
            'status' => 'Aguardando Definição de Tema',
        ]);

        $this->assertDatabaseHas('document_submissions', [
            'id' => $submission->id,
            'status' => 'Aguardando Definição de Tema',
        ]);

        // 3. Approve the theme (change status to Processo Concluído - Tema Definido)
        $submission->status = 'Processo Concluído - Tema Definido';
        $submission->save();

        // 4. Assert AcademicTitle of type TCC was created and title is normalized
        $this->assertDatabaseHas('academic_titles', [
            'student_id' => $student->id,
            'type' => 'TCC',
            'title' => 'Uso de Células-Tronco em Lesões de Tendão de Equinos',
        ]);

        $academicTitle = AcademicTitle::where('student_id', $student->id)->where('type', 'TCC')->first();
        $this->assertNotNull($academicTitle);

        // 5. Assert "Objetivos de TCC" submission was created automatically with the correct initial status
        $this->assertDatabaseHas('document_submissions', [
            'student_id' => $student->id,
            'academic_title_id' => $academicTitle->id,
            'document_type' => 'Objetivos de TCC',
            'status' => 'Aguardando Objetivos do Aluno',
        ]);
    }
}
