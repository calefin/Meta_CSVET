<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Student;
use App\Models\DocumentSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentNotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_notes_are_saved_and_logged_on_creation(): void
    {
        // 1. Create a student user
        $user = User::factory()->create([
            'role' => 'student',
            'name' => 'João Student',
            'email' => 'joao@example.com',
        ]);

        $student = Student::create([
            'user_id' => $user->id,
            'institution' => 'Universidade Vet',
            'academic_level' => 'Graduação',
            'registration' => '12345',
            'research_topic' => 'Não informado',
        ]);

        $this->actingAs($user);

        // 2. Create a document submission
        $submission = DocumentSubmission::create([
            'student_id' => $student->id,
            'title' => 'Trabalho de TCC Final',
            'document_type' => 'TCC',
            'student_notes' => 'Olá professor, aqui está meu TCC inicial.',
            'status' => 'Aguardando Triagem',
        ]);

        // 3. Assert database field is populated
        $this->assertDatabaseHas('document_submissions', [
            'id' => $submission->id,
            'student_notes' => 'Olá professor, aqui está meu TCC inicial.',
        ]);

        // 4. Assert history contains the student notes
        $submission = $submission->fresh();
        $history = $submission->metadata['history'] ?? [];
        
        $this->assertCount(1, $history);
        $this->assertEquals('Aguardando Triagem', $history[0]['status']);
        $this->assertEquals('João Student', $history[0]['actor']);
        $this->assertEquals('Olá professor, aqui está meu TCC inicial.', $history[0]['comments']);
    }

    public function test_student_notes_are_logged_and_status_transitions_on_resubmission(): void
    {
        // 1. Create student and submission
        $user = User::factory()->create([
            'role' => 'student',
            'name' => 'João Student',
            'email' => 'joao@example.com',
        ]);

        $student = Student::create([
            'user_id' => $user->id,
            'institution' => 'Universidade Vet',
            'academic_level' => 'Graduação',
            'registration' => '12345',
            'research_topic' => 'Não informado',
        ]);

        // Create submission (starts as Aguardando Triagem)
        $submission = DocumentSubmission::create([
            'student_id' => $student->id,
            'title' => 'Trabalho de TCC Final',
            'document_type' => 'TCC',
            'student_notes' => 'Primeira versão.',
        ]);

        // Simulate Professor returning the document to student for adjustments
        $submission->status = 'Aguardando Ajustes do Aluno';
        $submission->save();

        $this->actingAs($user);

        // 2. Perform resubmission (is_draft_save = false)
        $submission->student_notes = 'Segunda versão com correções.';
        $submission->is_draft_save = false;
        $submission->save();

        $submission = $submission->fresh();

        // 3. Assert status auto-transitioned
        $this->assertEquals('Aguardando Correção do Professor', $submission->status);

        // 4. Assert history timeline logged the resubmission details
        $history = $submission->metadata['history'] ?? [];
        
        // Let's assert the history entries
        // Entry 0: Creation (Aguardando Triagem)
        $this->assertEquals('Aguardando Triagem', $history[0]['status']);
        
        // Entry 1: Returned to Student (Aguardando Ajustes do Aluno)
        $this->assertEquals('Aguardando Ajustes do Aluno', $history[1]['status']);

        // Entry 2: Resubmission by student (Aguardando Correção do Professor)
        $this->assertEquals('Aguardando Correção do Professor', $history[2]['status']);
        $this->assertEquals('João Student', $history[2]['actor']);
        $this->assertEquals('Segunda versão com correções.', $history[2]['comments']);
    }

    public function test_signature_validation_runs_only_on_concluded_status(): void
    {
        // 1. Create a student user and student record
        $user = User::factory()->create([
            'role' => 'admin',
            'name' => 'Professor Atilio',
        ]);

        $student = Student::create([
            'user_id' => User::factory()->create(['role' => 'student'])->id,
            'institution' => 'Universidade Vet',
            'academic_level' => 'Graduação',
            'registration' => '12345',
            'research_topic' => 'Não informado',
        ]);

        // Create submission
        $submission = DocumentSubmission::create([
            'student_id' => $student->id,
            'title' => 'Trabalho de TCC Final',
            'document_type' => 'TCC',
            'status' => 'Aguardando Triagem',
        ]);

        // Setup a mock/dummy file in the storage for the returned file
        $diskName = in_array(config('filesystems.default'), ['google', 'onedrive']) ? config('filesystems.default') : 'public';
        $disk = \Illuminate\Support\Facades\Storage::disk($diskName);
        $dummyPath = 'returns/dummy_corrections.pdf';
        $disk->put($dummyPath, 'Unsigned dummy PDF content');

        $this->actingAs($user);

        // 2. Set status to a non-concluded state (e.g. 'Aguardando Ajustes do Aluno')
        // and upload the unsigned returned file.
        // It should NOT throw any signature validation exception!
        $submission->status = 'Aguardando Ajustes do Aluno';
        $submission->google_drive_returned_file_id = $dummyPath;
        
        $submission->save(); // Should save successfully without exception!

        $studentName = $student->user->name;
        $parts = explode(' ', trim($studentName));
        $simpleStudentName = count($parts) > 1 ? $parts[0] . ' ' . end($parts) : $studentName;

        $this->assertDatabaseHas('document_submissions', [
            'id' => $submission->id,
            'status' => 'Aguardando Ajustes do Aluno',
            'google_drive_returned_file_id' => 'returns/' . $submission->process_id . ' - ' . $simpleStudentName . ' - Assinado_Corrigido.pdf',
        ]);

        // 3. Now try to change the status to a concluded/signed status
        // (e.g. 'Processo Concluído - Documento Assinado Disponível')
        // It should THROW an InvalidArgumentException because the file is not signed!
        $submission = $submission->fresh();
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Erro na validação da assinatura local: O arquivo retornado não contém uma assinatura digital ICP-Brasil válida. Assine o PDF localmente antes de enviá-lo.');
        
        $submission->status = 'Processo Concluído - Documento Assinado Disponível';
        // Force the file to be considered dirty by updating the path to trigger validation
        $submission->google_drive_returned_file_id = 'returns/dummy_corrections.pdf';
        $disk->put('returns/dummy_corrections.pdf', 'Unsigned dummy PDF content');
        
        $submission->save();
    }
}
