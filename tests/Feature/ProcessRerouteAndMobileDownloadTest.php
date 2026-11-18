<?php

namespace Tests\Feature;

use App\Filament\Resources\DocumentSubmissionResource\Pages\EditDocumentSubmission;
use App\Filament\Resources\DocumentSubmissionResource\Pages\ViewDocumentSubmission;
use App\Models\DocumentSubmission;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProcessRerouteAndMobileDownloadTest extends TestCase
{
    use RefreshDatabase;

    private const SIGNED_PDF = "%PDF-1.7\n1 0 obj << /Type /Sig /ByteRange [0 10 20 30] >> endobj\n%%EOF";

    private function makeStudent(string $name = 'Mariana Silva'): array
    {
        $user = User::factory()->create(['role' => 'student', 'name' => $name]);
        $student = Student::create([
            'user_id' => $user->id,
            'institution' => 'Faculdade de Medicina Veterinária',
            'campus' => 'Paulista',
            'academic_level' => 'Graduação',
            'registration' => '998877',
            'research_topic' => 'Clínica de Silvestres',
        ]);

        return [$user, $student];
    }

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'admin', 'name' => 'Prof. Atilio']);
    }

    public function test_pt_br_translations_override_english_buttons(): void
    {
        $this->assertSame('Entrar', __('Sign in'));
        $this->assertSame('Entrar', __('Sign In'));
        $this->assertSame('Criar', __('Create'));
        $this->assertSame('Cancelar', __('Cancel'));
        $this->assertSame('Salvar alterações', __('Save changes'));
    }

    public function test_readequar_rota_changes_document_type_process_id_and_renames_files(): void
    {
        Storage::fake('public');

        [$user, $student] = $this->makeStudent('Mariana Silva');
        $admin = $this->makeAdmin();

        $this->actingAs($user);

        // Criar processo inicialmente como "Termo de Aceite de Orientação"
        $originalFileName = "fake_termo.pdf";
        Storage::disk('public')->put("submissions/{$originalFileName}", 'conteudo termo');

        $submission = DocumentSubmission::create([
            'student_id' => $student->id,
            'title' => 'Termo de Aceite Mariana',
            'document_type' => 'Termo de Aceite de Orientação',
            'status' => 'Aguardando Assinatura do Professor',
            'google_drive_file_id' => "submissions/{$originalFileName}",
        ]);

        $oldProcessId = $submission->process_id;
        $this->assertStringStartsWith('TERMO-ACEITE-', $oldProcessId);

        // Professor assina e devolve
        $returnedFileName = "fake_assinado.pdf";
        Storage::disk('public')->put("returns/{$returnedFileName}", self::SIGNED_PDF);
        $submission->update([
            'status' => 'Processo Concluído - Termo de Aceite Assinado',
            'google_drive_returned_file_id' => "returns/{$returnedFileName}",
        ]);

        $this->actingAs($admin);

        // Professor readequa a rota para "TCC Final (Assinatura)"
        $newProcessId = $submission->readequarRota(
            novoTipo: 'TCC Final (Assinatura)',
            novoStatus: 'Processo Concluído - Documento Assinado Disponível',
            justificativa: 'Aluna enviou TCC selecionando Termo de Aceite. Readequado para TCC Final.',
            notificarAluno: false
        );

        $submission->refresh();

        // 1. Process ID mudou para o prefixo correto de TCC Final
        $this->assertStringStartsWith('TCC-FINAL-ASS-', $newProcessId);
        $this->assertSame($newProcessId, $submission->process_id);
        $this->assertSame('TCC Final (Assinatura)', $submission->document_type);
        $this->assertSame('Processo Concluído - Documento Assinado Disponível', $submission->status);

        // 2. Histórico e auditoria foram gravados
        $history = $submission->metadata['history'] ?? [];
        $lastHistory = end($history);
        $this->assertSame('Readequação de Rota', $lastHistory['action']);
        $this->assertStringContainsString('Readequado para TCC Final', $lastHistory['comments']);

        $this->assertContains($oldProcessId, $submission->metadata['previous_process_ids']);

        // 3. Arquivo retornado foi renomeado no storage
        $this->assertStringContainsString($newProcessId, $submission->google_drive_returned_file_id);
        Storage::disk('public')->assertExists($submission->google_drive_returned_file_id);

        // 4. Arquivo original do aluno foi renomeado no storage
        $this->assertStringContainsString($newProcessId, $submission->google_drive_file_id);
        Storage::disk('public')->assertExists($submission->google_drive_file_id);

        // 5. Garantir que um novo processo de Termo de Aceite pode ser aberto sem conflito
        $this->actingAs($user);
        $newTermo = DocumentSubmission::create([
            'student_id' => $student->id,
            'title' => 'Agora sim Termo de Aceite Real',
            'document_type' => 'Termo de Aceite de Orientação',
            'status' => 'Aguardando Assinatura do Professor',
        ]);

        $this->assertStringStartsWith('TERMO-ACEITE-', $newTermo->process_id);
        $this->assertNotEquals($oldProcessId, $newTermo->process_id);
    }

    public function test_download_assinado_action_is_visible_on_view_and_edit_for_signed_documents(): void
    {
        Storage::fake('public');

        [$user, $student] = $this->makeStudent('Lucas Aluno');

        $submission = DocumentSubmission::create([
            'student_id' => $student->id,
            'title' => 'TCC Lucas',
            'document_type' => 'TCC Final (Assinatura)',
            'status' => 'Processo Concluído - Documento Assinado Disponível',
            'google_drive_returned_file_id' => 'returns/assinado.pdf',
        ]);

        $this->actingAs($user);

        // No ViewRecord deve ter o botão de download do documento assinado
        Livewire::test(ViewDocumentSubmission::class, ['record' => $submission->id])
            ->assertActionExists('download_assinado');

        // No EditRecord também deve ter
        Livewire::test(EditDocumentSubmission::class, ['record' => $submission->id])
            ->assertActionExists('download_assinado');
    }
}
