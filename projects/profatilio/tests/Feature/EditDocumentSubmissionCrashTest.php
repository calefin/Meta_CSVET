<?php

namespace Tests\Feature;

use App\Filament\Resources\DocumentSubmissionResource\Pages\CreateDocumentSubmission;
use App\Filament\Resources\DocumentSubmissionResource\Pages\EditDocumentSubmission;
use App\Models\AcademicTitle;
use App\Models\DocumentSubmission;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EditDocumentSubmissionCrashTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'admin', 'name' => 'Prof Atilio']);
    }

    private function makeStudent(): Student
    {
        $user = User::factory()->create(['role' => 'student', 'name' => 'Aluno Teste']);

        return Student::create([
            'user_id' => $user->id,
            'institution' => 'Universidade Vet',
            'academic_level' => 'Graduação',
            'registration' => '99999',
            'research_topic' => 'Teste',
        ]);
    }

    public function test_admin_can_render_and_save_edit_page_for_materiais_e_metodos(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();

        $submission = DocumentSubmission::create([
            'student_id' => $student->id,
            'title' => 'MM Teste',
            'document_type' => 'Materiais e Métodos de TCC',
            'status' => 'Aguardando Correção de Materiais e Métodos',
            'google_drive_file_id' => 'arquivo.pdf',
            'metadata' => ['tcc_stage_text' => '<p>Texto enviado pelo aluno.</p>'],
        ]);

        $this->actingAs($admin);

        $component = Livewire::test(EditDocumentSubmission::class, ['record' => $submission->id]);
        $component->assertSuccessful();

        // O campo de texto fica desabilitado para o professor (só o aluno edita),
        // então o professor aprova a etapa sem alterá-lo.
        $component->set('data.status', 'Processo Concluído - Materiais e Métodos Aprovados');
        $component->set('data.ai_executive_summary', '<p>Corrija a secao 2.</p>');
        $component->call('publishDispatch');
        $component->assertHasNoErrors();

        $submission->refresh();
        $this->assertEquals('Processo Concluído - Materiais e Métodos Aprovados', $submission->status);
        $this->assertEquals('<p>Texto enviado pelo aluno.</p>', $submission->metadata['tcc_stage_text']);
    }

    public function test_admin_can_return_materiais_e_metodos_to_student_when_text_was_wiped(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();

        // Simula o registro já corrompido pelo bug antigo: professor está revisando,
        // mas o texto do aluno não está mais no metadata.
        $submission = DocumentSubmission::create([
            'student_id' => $student->id,
            'title' => 'MM Teste',
            'document_type' => 'Materiais e Métodos de TCC',
            'status' => 'Aguardando Correção de Materiais e Métodos',
            'google_drive_file_id' => 'arquivo.pdf',
        ]);

        $this->actingAs($admin);

        $component = Livewire::test(EditDocumentSubmission::class, ['record' => $submission->id]);
        $component->assertSuccessful();

        $component->set('data.status', 'Aguardando Materiais e Métodos do Aluno');
        $component->set('data.ai_executive_summary', '<p>Reenvie o texto, por favor.</p>');
        $component->call('publishDispatch');
        $component->assertHasNoErrors();

        $submission->refresh();
        $this->assertEquals('Aguardando Materiais e Métodos do Aluno', $submission->status);
    }

    public function test_admin_can_approve_materiais_e_metodos_directly_even_if_text_was_wiped(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();

        // Texto ausente (bug antigo já corrompeu o metadata), professor tenta
        // aprovar a etapa sem devolver ao aluno: não pode ser travado por um
        // campo que nem ele consegue editar.
        $submission = DocumentSubmission::create([
            'student_id' => $student->id,
            'title' => 'MM Teste',
            'document_type' => 'Materiais e Métodos de TCC',
            'status' => 'Aguardando Correção de Materiais e Métodos',
            'google_drive_file_id' => 'arquivo.pdf',
        ]);

        $this->actingAs($admin);

        $component = Livewire::test(EditDocumentSubmission::class, ['record' => $submission->id]);
        $component->assertSuccessful();

        $component->set('data.ai_executive_summary', '<p>Aprovado.</p>');
        $component->call('publishDispatch');
        $component->assertHasNoErrors();

        $submission->refresh();
        $this->assertEquals('Aguardando Correção de Materiais e Métodos', $submission->status);
    }

    public function test_habilitar_stage_written_by_student_as_text_only_goes_to_professor_dispatch(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();

        // Aluno escreveu o texto direto no editor (sem anexar arquivo) e enviou
        // para o professor habilitar. A bola já está com o professor: habilitar
        // não pode devolver para "Aguardando Materiais e Métodos do Aluno".
        $submission = DocumentSubmission::create([
            'student_id' => $student->id,
            'title' => 'MM Teste',
            'document_type' => 'Materiais e Métodos de TCC',
            'status' => 'Aguardando Triagem',
            'metadata' => ['tcc_stage_text' => '<p>Métodos redigidos pelo aluno.</p>'],
        ]);

        $this->actingAs($admin);

        $component = Livewire::test(EditDocumentSubmission::class, ['record' => $submission->id]);
        $component->assertSuccessful();

        $component->set('data.status', 'Habilitado');
        $component->call('publishDispatch');
        $component->assertHasNoErrors();

        $submission->refresh();
        $this->assertEquals('Aguardando Correção de Materiais e Métodos', $submission->status);
    }

    public function test_habilitar_objetivos_written_by_student_goes_to_professor_dispatch(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();

        // Objetivos de TCC não usa metadata.tcc_stage_text — usa
        // tcc_general_objective/tcc_specific_objectives (ver TCC_STAGE_CONTENT_KEYS).
        $submission = DocumentSubmission::create([
            'student_id' => $student->id,
            'title' => 'Objetivos Teste',
            'document_type' => 'Objetivos de TCC',
            'status' => 'Aguardando Triagem',
            'metadata' => [
                'tcc_general_objective' => 'Avaliar X em Y.',
                'tcc_specific_objectives' => '1. Fazer A. 2. Fazer B.',
            ],
        ]);

        $this->actingAs($admin);

        $component = Livewire::test(EditDocumentSubmission::class, ['record' => $submission->id]);
        $component->assertSuccessful();

        $component->set('data.status', 'Habilitado');
        $component->call('publishDispatch');
        $component->assertHasNoErrors();

        $submission->refresh();
        $this->assertEquals('Aguardando Correção dos Objetivos', $submission->status);
    }

    public function test_admin_can_render_and_save_edit_page_for_plain_tcc(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();

        $submission = DocumentSubmission::create([
            'student_id' => $student->id,
            'title' => 'TCC Teste',
            'document_type' => 'TCC',
            'status' => 'Aguardando Correção do Professor',
            'google_drive_file_id' => 'arquivo.pdf',
            'metadata' => ['tcc_stage' => 'Final', 'campus' => 'X', 'period' => 'Matutino'],
        ]);

        $this->actingAs($admin);

        $component = Livewire::test(EditDocumentSubmission::class, ['record' => $submission->id]);
        $component->assertSuccessful();

        $component->set('data.status', 'Processo Concluído - Documento Assinado Disponível');
        $component->call('publishDispatch');
        $component->assertHasNoErrors();
    }

    public function test_admin_can_render_and_save_edit_page_for_tcc_completo(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $title = AcademicTitle::create(['student_id' => $student->id, 'type' => 'TCC', 'title' => 'Titulo Teste']);

        $submission = DocumentSubmission::create([
            'student_id' => $student->id,
            'academic_title_id' => $title->id,
            'title' => 'TCC Completo Teste',
            'document_type' => 'TCC Completo (Envio Único)',
            'status' => 'Aguardando Correção do TCC Completo',
            'google_drive_file_id' => 'arquivo.pdf',
        ]);

        $this->actingAs($admin);

        $component = Livewire::test(EditDocumentSubmission::class, ['record' => $submission->id]);
        $component->assertSuccessful();

        $component->set('data.status', 'Processo Concluído - TCC Completo Aprovado');
        $component->call('publishDispatch');
        $component->assertHasNoErrors();
    }

    public function test_student_can_create_tcc_completo_with_text_only_no_file(): void
    {
        $student = $this->makeStudent();

        DocumentSubmission::create([
            'student_id' => $student->id,
            'title' => 'Objetivos Teste',
            'document_type' => 'Objetivos de TCC',
            'status' => 'Processo Concluído - Objetivos Aprovados',
        ]);

        $this->actingAs($student->user);

        $component = Livewire::test(CreateDocumentSubmission::class);
        $component->fillForm([
            'document_type' => 'TCC Completo (Envio Único)',
            'title' => 'Meu TCC Completo',
            'metadata.tcc_stage_text' => '<p>Texto completo do TCC digitado pelo aluno.</p>',
            'gatekeeper_abnt' => true,
            'gatekeeper_nomenclature' => true,
            'gatekeeper_file' => true,
        ]);
        $component->call('create');
        $component->assertHasNoFormErrors();

        $this->assertDatabaseHas('document_submissions', [
            'student_id' => $student->id,
            'document_type' => 'TCC Completo (Envio Único)',
            'title' => 'Meu TCC Completo',
        ]);
    }

    public function test_student_cannot_create_tcc_completo_without_file_or_text(): void
    {
        $student = $this->makeStudent();

        DocumentSubmission::create([
            'student_id' => $student->id,
            'title' => 'Objetivos Teste',
            'document_type' => 'Objetivos de TCC',
            'status' => 'Processo Concluído - Objetivos Aprovados',
        ]);

        $this->actingAs($student->user);

        $component = Livewire::test(CreateDocumentSubmission::class);
        $component->fillForm([
            'document_type' => 'TCC Completo (Envio Único)',
            'title' => 'Meu TCC Completo Vazio',
            'gatekeeper_abnt' => true,
            'gatekeeper_nomenclature' => true,
            'gatekeeper_file' => true,
        ]);
        $component->call('create');
        $component->assertHasFormErrors(['google_drive_file_id', 'metadata.tcc_stage_text']);

        $this->assertDatabaseMissing('document_submissions', [
            'student_id' => $student->id,
            'title' => 'Meu TCC Completo Vazio',
        ]);
    }
}
