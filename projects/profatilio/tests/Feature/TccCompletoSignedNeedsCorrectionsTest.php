<?php

namespace Tests\Feature;

use App\Models\DocumentSubmission;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * TCC Completo: "Documento Assinado — mas precisa de correções". A versão assinada
 * fica registrada e as devoluções continuam sem apagá-la.
 */
class TccCompletoSignedNeedsCorrectionsTest extends TestCase
{
    use RefreshDatabase;

    private const SIGNED_PDF = "%PDF-1.7\n1 0 obj << /Type /Sig /ByteRange [0 10 20 30] >> endobj\n%%EOF";

    private User $admin;
    private User $studentUser;
    private DocumentSubmission $submission;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake('public');
        Storage::fake('local');
        Storage::fake('google');
        config(['filesystems.default' => 'public']);

        $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'Prof Atilio']);
        $this->studentUser = User::factory()->create(['role' => 'student', 'name' => 'Carla Souza Lima']);
        $student = Student::create([
            'user_id' => $this->studentUser->id,
            'institution' => 'Universidade Vet',
            'academic_level' => 'Graduação',
            'registration' => '22222',
            'research_topic' => 'Não informado',
        ]);

        $this->submission = DocumentSubmission::create([
            'student_id' => $student->id,
            'title' => 'TCC da Carla',
            'document_type' => 'TCC Completo (Envio Único)',
            'status' => 'Aguardando Correção do TCC Completo',
        ]);
    }

    private function signAndRequestCorrections(): DocumentSubmission
    {
        $this->actingAs($this->admin);
        Storage::disk('public')->put('returns/upload-assinado.pdf', self::SIGNED_PDF);

        $this->submission->update([
            'status' => DocumentSubmission::SIGNED_NEEDS_CORRECTIONS_STATUS,
            'google_drive_returned_file_id' => ['returns/upload-assinado.pdf'],
            'ai_executive_summary' => '<p>Assinado, mas corrija a formatação das referências.</p>',
        ]);

        return $this->submission->fresh();
    }

    public function test_signed_version_is_registered_and_process_goes_back_to_student(): void
    {
        $submission = $this->signAndRequestCorrections();

        $this->assertSame(DocumentSubmission::SIGNED_NEEDS_CORRECTIONS_STATUS, $submission->status);
        $this->assertFalse($submission->isConcluded());
        $this->assertTrue($submission->canStudentEdit());

        $signed = $submission->signedFinalFile();
        $this->assertNotNull($signed);
        $this->assertStringContainsString('Assinado_Versao_Final', $signed);
        Storage::disk('public')->assertExists($signed);
        $this->assertCount(1, $submission->metadata['signed_final_history']);
    }

    public function test_corrections_continue_without_losing_signed_version(): void
    {
        $signed = $this->signAndRequestCorrections()->signedFinalFile();

        // Aluno reenvia corrigido → volta para o professor
        $this->actingAs($this->studentUser);
        $submission = DocumentSubmission::find($this->submission->id);
        $submission->update(['google_drive_file_id' => ['submissions/tcc-corrigido.pdf']]);
        $this->assertSame('Aguardando Correção do TCC Completo', $submission->fresh()->status);

        // Professor devolve nova correção (sem assinar)
        $this->actingAs($this->admin);
        Storage::disk('public')->put('returns/correcao-2.pdf', '%PDF-1.7 sem assinatura');
        $submission = DocumentSubmission::find($this->submission->id);
        $submission->update([
            'status' => 'Aguardando Ajustes do Aluno',
            'google_drive_returned_file_id' => ['returns/correcao-2.pdf'],
        ]);

        $submission = $submission->fresh();
        $this->assertSame($signed, $submission->signedFinalFile());
        $this->assertNotSame($signed, $submission->google_drive_returned_file_id);
        Storage::disk('public')->assertExists($signed);

        // Aluno ainda baixa a versão assinada
        $this->actingAs($this->studentUser);
        $response = $this->get(route('documento.download', ['id' => $submission->id, 'type' => 'signed_final']));
        $response->assertOk();
        $this->assertSame(self::SIGNED_PDF, $response->streamedContent());
    }

    public function test_status_requires_a_new_signed_pdf(): void
    {
        $this->actingAs($this->admin);

        $this->expectException(\InvalidArgumentException::class);
        $this->submission->update(['status' => DocumentSubmission::SIGNED_NEEDS_CORRECTIONS_STATUS]);
    }

    public function test_unsigned_pdf_is_rejected(): void
    {
        $this->actingAs($this->admin);
        Storage::disk('public')->put('returns/nao-assinado.pdf', '%PDF-1.7 sem assinatura');

        $this->expectException(\Exception::class);
        $this->submission->update([
            'status' => DocumentSubmission::SIGNED_NEEDS_CORRECTIONS_STATUS,
            'google_drive_returned_file_id' => ['returns/nao-assinado.pdf'],
        ]);
    }

    public function test_edit_page_shows_signed_version_link_and_validates_status(): void
    {
        $submission = $this->signAndRequestCorrections();

        \Livewire\Livewire::test(\App\Filament\Resources\DocumentSubmissionResource\Pages\EditDocumentSubmission::class, ['record' => $submission->getRouteKey()])
            ->assertOk()
            ->assertSee('Baixar versão assinada')
            // Arquivo do aluno sempre antes (esquerda) da devolução do professor (direita)
            ->assertSeeInOrder(['Documento do Aluno', 'Devolução do Professor']);

        // Outro processo: escolher o status sem anexar PDF novo dá erro de validação
        $other = DocumentSubmission::create([
            'student_id' => $submission->student_id,
            'title' => 'Outro TCC',
            'document_type' => 'TCC Completo (Envio Único)',
            'status' => 'Aguardando Correção do TCC Completo',
        ]);

        \Livewire\Livewire::test(\App\Filament\Resources\DocumentSubmissionResource\Pages\EditDocumentSubmission::class, ['record' => $other->getRouteKey()])
            ->set('data.status', DocumentSubmission::SIGNED_NEEDS_CORRECTIONS_STATUS)
            ->call('save')
            ->assertHasFormErrors(['status']);

        $this->assertSame('Aguardando Correção do TCC Completo', $other->fresh()->status);
    }

    public function test_helper_detects_new_upload_vs_prefilled_previous_return(): void
    {
        $submission = $this->signAndRequestCorrections();
        $current = $submission->getAttributes()['google_drive_returned_file_id'];

        $this->assertFalse(DocumentSubmission::returnedFileStateHasNewFile([$current], $submission));
        $this->assertFalse(DocumentSubmission::returnedFileStateHasNewFile([], $submission));
        $this->assertTrue(DocumentSubmission::returnedFileStateHasNewFile([$current, 'returns/novo.pdf'], $submission));
    }
}
