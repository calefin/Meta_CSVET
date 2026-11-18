<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\DocumentSubmission;
use App\Filament\Pages\AssinaturaAvulsa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AssinaturaAvulsaTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_access_assinatura_avulsa_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);

        // Guest redirect
        $this->get(AssinaturaAvulsa::getUrl())->assertRedirect('/admin/login');

        // Student forbidden
        $this->actingAs($student);
        $this->get(AssinaturaAvulsa::getUrl())->assertStatus(403);

        // Admin success
        $this->actingAs($admin);
        $this->get(AssinaturaAvulsa::getUrl())->assertSuccessful();
    }

    public function test_admin_can_upload_and_homologate_signed_pdf_batch(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $diskName = in_array(config('filesystems.default'), ['google', 'onedrive']) ? config('filesystems.default') : 'public';
        $disk = Storage::disk($diskName);
        
        // PDF contendo o marcador /Type/Sig para passar na validação de assinatura local
        $signedPdfMockContent = "%PDF-1.4\n1 0 obj\n<<\/Type\/Catalog\/Pages 2 0 R>>\nendobj\n2 0 obj\n<<\/Type\/Pages\/Kids[3 0 R]\/Count 1>>\nendobj\n3 0 obj\n<<\/Type\/Page\/Parent 2 0 R\/MediaBox[0 0 595 842]\/Type/Sig>>\nendobj\nxref\n0 4\ntrailer\n<<\/Size 4\/Root 1 0 R>>\nstartxref\n162\n%%EOF";
        
        $tempPath = 'temp_signatures/signed_test_doc.pdf';
        $disk->put($tempPath, $signedPdfMockContent);

        // Simula upload do lote e submissão
        Livewire::test(AssinaturaAvulsa::class)
            ->fillForm([
                'pdf_items' => [
                    [
                        'pdf_file' => [$tempPath],
                        'document_title' => 'Relatorio de Teste Assinado',
                        'document_description' => 'Testes de Homologacao A3',
                        'original_filename' => 'signed_test_doc.pdf',
                    ]
                ]
            ])
            ->call('submit')
            ->assertHasNoFormErrors();

        // Verifica se a submissão foi registrada e marcada como concluída/auditada
        $this->assertDatabaseHas('document_submissions', [
            'title' => 'Relatorio de Teste Assinado',
            'document_type' => 'Assinatura Avulsa',
            'status' => 'Assinatura Realizada e Auditada',
        ]);

        $submission = DocumentSubmission::where('title', 'Relatorio de Teste Assinado')->first();
        $this->assertNotNull($submission);
        $this->assertEquals($admin->name, $submission->metadata['signer_name']);

        // Verifica se o recibo foi salvo e a sessão de download foi preenchida
        $receiptPath = $submission->metadata['receipt_pdf_path'];
        $this->assertNotNull($receiptPath);
        $this->assertTrue($disk->exists($receiptPath));
        
        $sessionDownloads = session('govbr_standalone_download_paths');
        $this->assertNotEmpty($sessionDownloads);
        $this->assertEquals('signed_test_doc.pdf', $sessionDownloads[0]['filename']);
    }
}
