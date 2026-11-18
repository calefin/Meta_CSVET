<?php

namespace Tests\Feature;

use App\Filament\Resources\DocumentSubmissionResource\Pages\CreateDocumentSubmission;
use App\Models\DocumentSubmission;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Este envio é apenas para assinatura": Projeto de TCC e TCC Final precisam aparecer
 * como opção e seguir o fluxo de assinatura (não cair em 'pending' nem no fluxo de
 * revisão de "Outro").
 */
class SignatureOnlyTccTypesTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(): array
    {
        $user = User::factory()->create(['role' => 'student', 'name' => 'Bia Aluna']);
        $student = Student::create([
            'user_id' => $user->id,
            'institution' => 'Universidade Vet',
            'academic_level' => 'Graduação',
            'registration' => '11111',
            'research_topic' => 'Não informado',
        ]);

        return [$user, $student];
    }

    public function test_signature_only_list_includes_tcc_project_and_final(): void
    {
        $types = array_keys(DocumentSubmission::SIGNATURE_ONLY_TYPES);

        $this->assertContains('Projeto de TCC (Assinatura)', $types);
        $this->assertContains('TCC Final (Assinatura)', $types);
        $this->assertNotContains('Outro', $types);
    }

    public function test_purpose_buttons_filter_type_options(): void
    {
        $all = ['TCC' => 'TCC', 'Outro' => 'Outro', 'Avaliações de Estágio' => 'Aval', 'Plano de Estágio' => 'Plano', 'Trabalho de Congresso' => 'Cong'];

        $sign = DocumentSubmission::typesForPurpose('assinar', $all);
        $this->assertArrayHasKey('TCC Final (Assinatura)', $sign);
        $this->assertArrayHasKey('Projeto de TCC (Assinatura)', $sign);
        $this->assertArrayNotHasKey('Outro', $sign);

        $fix = DocumentSubmission::typesForPurpose('corrigir', $all);
        $this->assertSame(['TCC', 'Outro'], array_keys($fix));

        $record = DocumentSubmission::typesForPurpose('registro', $all);
        $this->assertSame(['Avaliações de Estágio', 'Trabalho de Congresso'], array_keys($record));

        $this->assertSame('assinar', DocumentSubmission::purposeForType('TCC Final (Assinatura)'));
        $this->assertSame('registro', DocumentSubmission::purposeForType('Avaliações de Estágio'));
        $this->assertSame('corrigir', DocumentSubmission::purposeForType('TCC'));
    }

    public function test_student_picks_purpose_then_type_in_the_form(): void
    {
        [$user] = $this->makeStudent();
        $this->actingAs($user);

        $component = Livewire::test(CreateDocumentSubmission::class)
            ->assertFormFieldIsHidden('document_type')
            ->set('data.submission_purpose', 'assinar')
            ->assertFormFieldIsVisible('document_type');

        $this->assertNull($component->get('data.document_type'));

        $component->set('data.document_type', 'TCC Final (Assinatura)')
            ->set('data.submission_purpose', 'corrigir');

        // Trocar a finalidade limpa um tipo de outro grupo
        $this->assertNull($component->get('data.document_type'));
    }

    public function test_link_with_document_type_preselects_purpose(): void
    {
        [$user] = $this->makeStudent();
        $this->actingAs($user);

        Livewire::withQueryParams(['document_type' => 'Avaliações de Estágio'])
            ->test(CreateDocumentSubmission::class)
            ->assertSet('data.submission_purpose', 'registro')
            ->assertSet('data.document_type', 'Avaliações de Estágio');
    }

    public function test_tcc_signature_types_follow_signature_flow(): void
    {
        [, $student] = $this->makeStudent();

        foreach (['Projeto de TCC (Assinatura)', 'TCC Final (Assinatura)'] as $type) {
            $submission = DocumentSubmission::create([
                'student_id' => $student->id,
                'title' => "Meu {$type}",
                'document_type' => $type,
            ]);

            $this->assertSame('Aguardando Triagem', $submission->status);
            $this->assertStringNotContainsString('DOC-', $submission->process_id);

            $submission->status = 'Habilitado';
            $submission->save();

            $this->assertSame('Aguardando Assinatura do Professor', $submission->fresh()->status);
        }
    }
}
