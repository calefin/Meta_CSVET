<?php

namespace Tests\Feature;

use App\Filament\Resources\ExternalClassGroupResource\Pages\EditExternalClassGroup;
use App\Filament\Resources\ExternalClassGroupResource\RelationManagers\SubmissionsRelationManager;
use App\Models\ExternalClassGroup;
use App\Models\ExternalClassParticipant;
use App\Models\ExternalDocumentSubmission;
use App\Models\Owner;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Ponta a ponta do fluxo "Turmas de Estágio" (Assinatura Externa): professor
 * cria a turma com roster, aluno se identifica só por RGM+e-mail (sem
 * cadastro), envia um documento, o professor devolve a versão assinada, e o
 * aluno baixa. Cobre exatamente o que foi pedido: importar turma → aluno
 * envia PDF para assinatura → professor devolve → aluno baixa.
 *
 * Nota: este fluxo é para os documentos de estágio (Plano de Estágio /
 * Termo de Compromisso — ver ExternalDocumentSubmission::DOCUMENT_TYPE_OPTIONS),
 * não para TCC. Envio de TCC passa pelo portal autenticado do aluno
 * (DocumentSubmissionResource), um fluxo diferente deste.
 */
class EstagioSignatureFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        // `users` é a tabela do ProvaGen (Node/Drizzle) — gestao nunca a migra de
        // verdade (ver 0001_01_01_000000_create_users_table.php, comentário
        // explícito). Pra testar qualquer fluxo que dependa de Owner/auth guard
        // 'admin', criamos uma versão mínima só para este SQLite de teste, sem
        // tocar nas migrations reais.
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('openId')->unique();
                $table->string('email')->nullable()->unique();
                $table->string('password_hash')->nullable();
                $table->string('name')->nullable();
                $table->string('loginMethod')->default('email');
                $table->string('role')->default('user');
                $table->string('plan')->default('free');
                $table->string('email_verified')->default('false');
                // Owner::CREATED_AT/UPDATED_AT usam camelCase (schema do ProvaGen/Drizzle),
                // não os snake_case padrão do Laravel — sem isso o Eloquent tenta gravar
                // em colunas que não existem.
                $table->timestamp('createdAt')->nullable();
                $table->timestamp('updatedAt')->nullable();
                $table->timestamp('lastSignedIn')->nullable();
            });
        }
    }

    protected function makeOwner(): Owner
    {
        $owner = new Owner();
        $owner->forceFill([
            'openId' => 'test:' . uniqid(),
            'email' => 'professor' . uniqid() . '@example.com',
            'name' => 'Professora Teste',
            'plan' => 'enterprise',
        ]);
        $owner->save();

        return $owner;
    }

    /** PDF sintético válido (passa em PdfSecurityValidator::isValidPdf), sem marcador de assinatura. */
    protected function plainPdfContent(): string
    {
        return "%PDF-1.4\n1 0 obj\n<</Type/Catalog/Pages 2 0 R>>\nendobj\n2 0 obj\n<</Type/Pages/Kids[3 0 R]/Count 1>>\nendobj\n3 0 obj\n<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]>>\nendobj\nxref\n0 4\ntrailer\n<</Size 4/Root 1 0 R>>\nstartxref\n150\n%%EOF";
    }

    /** PDF sintético com marcador /Type/Sig, pra passar em PdfSecurityValidator::isDigitallySigned. */
    protected function signedPdfContent(): string
    {
        return "%PDF-1.4\n1 0 obj\n<</Type/Catalog/Pages 2 0 R>>\nendobj\n2 0 obj\n<</Type/Pages/Kids[3 0 R]/Count 1>>\nendobj\n3 0 obj\n<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]/Type/Sig/ByteRange[0 1 2 3]>>\nendobj\nxref\n0 4\ntrailer\n<</Size 4/Root 1 0 R>>\nstartxref\n162\n%%EOF";
    }

    public function test_full_round_trip_import_upload_sign_download(): void
    {
        Mail::fake();

        $professor = $this->makeOwner();

        // 1. Turma criada com um aluno já no roster — equivalente ao resultado
        //    do wizard "Nova Turma + Importar Planilha" já confirmado.
        $group = ExternalClassGroup::create([
            'university_name' => 'Universidade Teste',
            'group_name' => 'Estágio Supervisionado A',
            'course' => 'Medicina Veterinária',
            'semester' => '8',
            'year' => 2026,
            'active' => true,
            'created_by' => $professor->id,
            'owner_user_id' => $professor->id,
        ]);

        $participant = ExternalClassParticipant::create([
            'external_class_group_id' => $group->id,
            'owner_user_id' => $professor->id,
            'name' => 'Aluna Teste',
            'rgm' => '20261234',
            'email' => 'aluna@example.com',
        ]);

        // 2. Aluno abre o link público da turma (sem estar logado).
        $this->get(route('estagio.turma', ['token' => $group->token]))
            ->assertSuccessful();

        // Credenciais erradas não entram (sem indicar qual campo falhou).
        $this->post(route('estagio.turma.verificar', ['token' => $group->token]), [
            'rgm' => $participant->rgm,
            'email' => 'errado@example.com',
        ])->assertSessionHasErrors('rgm');

        // 3. Aluno se identifica por RGM + e-mail — sem criar conta.
        $this->post(route('estagio.turma.verificar', ['token' => $group->token]), [
            'rgm' => $participant->rgm,
            'email' => $participant->email,
        ])->assertRedirect(route('estagio.documentos', ['token' => $group->token]));

        $this->get(route('estagio.documentos', ['token' => $group->token]))
            ->assertSuccessful();

        // 4. Aluno envia o documento (Plano de Estágio) para assinatura.
        $upload = UploadedFile::fake()->createWithContent('plano_estagio.pdf', $this->plainPdfContent());

        $this->post(route('estagio.documentos.store', ['token' => $group->token]), [
            'document_type' => 'plano_estagio',
            'reference_month_start' => 3,
            'reference_month_end' => 3,
            'reference_year' => 2026,
            'document' => $upload,
        ])->assertRedirect(route('estagio.documentos', ['token' => $group->token]));

        $submission = ExternalDocumentSubmission::firstOrFail();
        $this->assertEquals(ExternalDocumentSubmission::STATUS_PENDING, $submission->status);
        $this->assertEquals($participant->id, $submission->external_class_participant_id);
        $this->assertEquals($group->id, $submission->external_class_group_id);

        // 5. Professor loga (guard admin) e vê o envio na aba Documentos Enviados.
        $this->actingAs($professor, 'admin');

        // 5a. Tentar anexar um PDF SEM assinatura digital deve ser rejeitado.
        Livewire::test(SubmissionsRelationManager::class, [
            'ownerRecord' => $group,
            'pageClass' => EditExternalClassGroup::class,
        ])->callTableAction('anexarAssinado', $submission, data: [
            'signed_file' => [UploadedFile::fake()->createWithContent('sem_assinatura.pdf', $this->plainPdfContent())],
        ]);

        $submission->refresh();
        $this->assertNotEquals(ExternalDocumentSubmission::STATUS_SIGNED, $submission->status, 'PDF sem assinatura digital não deveria ser aceito.');

        // 5b. Anexar o PDF assinado de verdade — devolve pro aluno.
        Livewire::test(SubmissionsRelationManager::class, [
            'ownerRecord' => $group,
            'pageClass' => EditExternalClassGroup::class,
        ])->callTableAction('anexarAssinado', $submission, data: [
            'signed_file' => [UploadedFile::fake()->createWithContent('assinado.pdf', $this->signedPdfContent())],
        ]);

        $submission->refresh();
        $this->assertEquals(ExternalDocumentSubmission::STATUS_SIGNED, $submission->status);
        $this->assertNotNull($submission->returned_file_path);
        $this->assertNotNull($submission->signed_link_expires_at);

        // 6. Aluno volta (RGM+e-mail de novo — sessão nova) e baixa o assinado
        //    pelo portal.
        \Illuminate\Support\Facades\Auth::guard('admin')->logout();

        $this->post(route('estagio.turma.verificar', ['token' => $group->token]), [
            'rgm' => $participant->rgm,
            'email' => $participant->email,
        ]);

        $this->get(route('estagio.documentos.assinado.portal', ['token' => $group->token, 'submission' => $submission->id]))
            ->assertSuccessful()
            ->assertHeader('content-type', 'application/pdf');
    }
}
