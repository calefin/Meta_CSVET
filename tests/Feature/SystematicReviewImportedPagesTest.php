<?php

namespace Tests\Feature;

use App\Filament\Pages\CentralMetanalises;
use App\Filament\Pages\TriagemCega;
use App\Filament\Resources\SystematicReviewResource;
use App\Filament\Resources\SystematicReviewResource\Pages\ViewSystematicReview;
use App\Models\ResearchCall;
use App\Models\Student;
use App\Models\SystematicReview;
use App\Models\User;
use App\Services\ResearchRecruitmentService;
use App\Services\SystematicReviewProtocolService;
use App\Services\SystematicReviewService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regressão do erro 500 de 23/10/2026: a página de cada revisão importada do
 * pipeline JSON v1.1 quebrava no MySQL (GROUP BY do PRISMA com o ORDER BY seq
 * herdado de records()). O fixture tem a estrutura real do pipeline_P01.json
 * v1.1, com os textos trocados. O guarda de GROUP BY do TestCase reprova
 * qualquer consulta que o MySQL recusaria.
 */
class SystematicReviewImportedPagesTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
        $this->admin = User::factory()->create(['role' => 'admin', 'email' => 'prof@example.com']);
    }

    protected function student(string $name, string $email): User
    {
        $user = User::factory()->create(['role' => 'student', 'name' => $name, 'email' => $email]);
        Student::create(['user_id' => $user->id, 'institution' => 'X', 'academic_level' => 'Graduação', 'research_topic' => 'Geral',
            'tcc_status' => 'none', 'phone' => '1', 'status' => 'active', 'category' => 'external']);

        return $user;
    }

    /** Como em produção: dupla aprovada na seleção ANTES de importar o JSON. */
    protected function importedWithPair(): array
    {
        $call = ResearchCall::create(array_merge(ResearchCall::defaults(), ['status' => ResearchCall::STATUS_OPEN]));
        $svc = app(ResearchRecruitmentService::class);
        $users = [];
        foreach (['ana' => ['PET-01', 'PET-02'], 'beto' => ['PET-02', 'PET-01']] as $key => [$lead, $reviewer]) {
            $users[$key] = $this->student(ucfirst($key) . ' Teste', "{$key}@x.com");
            [$app] = $svc->submit($call, [
                'name' => $users[$key]->name, 'name_confirmation' => $users[$key]->name, 'email' => "{$key}@x.com", 'email_confirmation' => "{$key}@x.com",
                'adhesion' => '1', 'phone' => '(11) 98765-4321', 'course' => 'Medicina Veterinária', 'institution' => 'Universidade X', 'semester' => 6,
                'purpose' => 'career', 'axes' => [$call->axisNames()[0]], 'projects' => ['PET-01', 'PET-02'], 'weekly_hours' => 5,
                'english_level' => 'translator', 'motivation' => 'Quero aprender revisão sistemática e publicar na área de exóticos.',
                'commitments' => array_fill_keys(array_keys($call->texts('commitments')), '1'),
            ]);
            $svc->approve($app->fresh(), $lead, $reviewer);
        }
        // Cada aluno lidera um projeto e revisa o outro, como nas 9 duplas reais.
        $json = json_decode(file_get_contents(base_path('tests/fixtures/pipeline_v11_estrutura_real.json')), true);
        $protocol = app(SystematicReviewProtocolService::class);
        [$review] = $protocol->import(json_encode($json));
        $protocol->import(json_encode(array_merge($json, ['protocol_id' => 'P02', 'title' => 'Second synthetic project'])));

        return [$review->fresh(), $users['ana'], $users['beto']];
    }

    protected function assertAdminPagesOpen(SystematicReview $review): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->admin);
        $this->get(SystematicReviewResource::getUrl('index'))->assertOk();
        $this->get(SystematicReviewResource::getUrl('view', ['record' => $review]))->assertOk()->assertSee('Fluxograma PRISMA 2020');
        $this->get(SystematicReviewResource::getUrl('edit', ['record' => $review]))->assertOk();
        $this->get('/admin/central-metanalises')->assertOk();
        foreach (SystematicReviewResource::getRelations() as $manager) {
            Livewire::test($manager, ['ownerRecord' => $review, 'pageClass' => ViewSystematicReview::class])->assertOk();
        }
        // Downloads que também montam o PRISMA.
        foreach (['prisma-csv', 'relatorio', 'pacote-osf', 'protocolo', 'osf-ficha'] as $type) {
            $this->get(route('systematic-review.export', [$review, $type]))->assertOk();
        }
    }

    public function test_admin_opens_imported_v11_review_before_and_after_the_pair_signs(): void
    {
        [$review, $ana, $beto] = $this->importedWithPair();
        $this->assertTrue($review->llmAssistedExtraction());
        $this->assertCount(2, $review->screeners);
        $this->assertAdminPagesOpen($review);

        $sr = app(SystematicReviewService::class);
        foreach ([[$ana, 'Ana Teste'], [$beto, 'Beto Teste']] as [$user, $name]) {
            $sr->signTerms($review->screeners()->where('user_id', $user->id)->first(), $name, '127.0.0.1', 'test');
        }
        $sr->postMessage($review, $ana, 'Professor, li o protocolo.');
        $this->assertAdminPagesOpen($review->fresh());

        Livewire::actingAs($this->admin)->test(CentralMetanalises::class)
            ->assertSee('PET-01')
            ->call('setTab', 'conversas')->assertSee('li o protocolo');

        // Dashboard do professor: resumo do programa (2 projetos, 4 vagas na dupla, 2 termos, 1 conversa esperando).
        $this->assertSame(['projects' => 2, 'signed' => 2, 'people' => 4, 'awaiting' => 1, 'frozen' => 0, 'osf' => 0], CentralMetanalises::summary());
        $this->assertTrue(\App\Filament\Widgets\MetanalisesAdminWidget::canView());
        Livewire::actingAs($this->admin)->test(\App\Filament\Widgets\MetanalisesAdminWidget::class)
            ->assertSee('Aguardando mensagem')
            ->assertSee('2/4')
            ->assertSee('0/2');
        $this->assertFalse(\App\Filament\Widgets\MetanalisesPortalWidget::canView());
    }

    public function test_student_of_the_pair_signs_terms_and_uses_the_project_but_not_the_admin(): void
    {
        [$review, $ana] = $this->importedWithPair();
        $lead = $review->screeners()->where('user_id', $ana->id)->first();
        Filament::setCurrentPanel(Filament::getPanel('portal'));

        $this->actingAs($ana)->get('/portal/triagem-cega')->assertOk();
        Livewire::actingAs($ana)->test(TriagemCega::class)
            ->assertSee('PET-01')
            ->assertSee('PET-02')
            ->assertSee('Ler e assinar o termo')
            ->assertDontSee($review->title)
            ->call('choose', $lead->id)
            ->set('termsRead', true)
            ->set('signature', 'Ana Teste')
            ->call('sign')
            ->assertHasNoErrors()
            ->assertSee($review->title)
            ->assertSee('Líder (1º autor)')
            ->set('chatBody', 'Combinado, professor.')
            ->call('postChat')
            ->assertHasNoErrors()
            ->assertSee('Combinado, professor.');
        $this->assertTrue($lead->fresh()->hasSignedTerms());

        // Em preparação: guia da fase certa (ORCID/OSF pela conversa), não o de "aguardar desempate",
        // e a trilha marca o protocolo como etapa atual (ainda não registrado no OSF).
        Livewire::actingAs($ana)->test(TriagemCega::class)->call('choose', $lead->id)
            ->assertSee('Etapa atual: preparação')
            ->assertSee('ORCID')
            ->assertDontSee('resolver as discordâncias');
        $this->assertSame(['now', 'next'], array_column(array_slice(\App\Support\StageGuides::roadmap($review->fresh()), 0, 2), 'state'));

        // Painel do aluno: os dois projetos no topo, com o termo e a próxima ação.
        $this->assertTrue(\App\Filament\Widgets\MetanalisesPortalWidget::canView());
        Livewire::actingAs($ana)->test(\App\Filament\Widgets\MetanalisesPortalWidget::class)
            ->assertSee('PET-01 · Líder (1º autor)')
            ->assertSee('✓ termo assinado')
            ->assertSee('Envie o seu ORCID')
            ->assertSee('Etapa 1 de 9')
            ->assertSee('PET-02 · Revisor(a) cego(a)')
            ->assertSee('Ler e assinar o termo')
            ->assertDontSee($review->title);

        // Aluno não entra no painel do professor.
        $this->actingAs($ana)->get(SystematicReviewResource::getUrl('view', ['record' => $review], panel: 'admin'))->assertForbidden();
    }

    public function test_professor_freezes_imported_protocol_with_pdf_and_only_signed_student_downloads_it(): void
    {
        [$review, $ana, $beto] = $this->importedWithPair();
        $sr = app(SystematicReviewService::class);
        $lead = $review->screeners()->where('user_id', $ana->id)->first();
        $reviewer = $review->screeners()->where('user_id', $beto->id)->first();
        $sr->signTerms($lead, 'Ana Teste', '127.0.0.1', 'test');

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->admin);
        $this->assertSame([], app(SystematicReviewProtocolService::class)->freezeProblems($review));
        Livewire::test(ViewSystematicReview::class, ['record' => $review->getRouteKey()])
            ->callAction('congelar', data: ['pdf' => \Illuminate\Http\UploadedFile::fake()->createWithContent('Protocol_P01_v1.1.pdf', '%PDF-1.4 protocolo P01')])
            ->assertHasNoActionErrors();

        $review->refresh();
        $this->assertTrue($review->isFrozen());
        $this->assertSame(hash('sha256', '%PDF-1.4 protocolo P01'), $review->protocol_pdf_sha256);
        Storage::disk('local')->assertExists($review->protocol_pdf_path);
        $this->get(SystematicReviewResource::getUrl('view', ['record' => $review]))->assertOk()->assertSee('Protocolo congelado');

        // Aluno que assinou vê e baixa o PDF; quem ainda não assinou, não.
        Livewire::actingAs($ana)->test(TriagemCega::class)->call('choose', $lead->id)->assertSee('Protocolo do projeto (PDF)');
        $this->actingAs($ana)->get(route('systematic-review.my-protocol', $lead))->assertOk();
        $this->actingAs($beto)->get(route('systematic-review.my-protocol', $reviewer))->assertForbidden();
    }

    public function test_guard_flags_group_by_with_an_inherited_order_by(): void
    {
        $this->assertSame(['seq'], self::onlyFullGroupByViolations('select ta_reason, count(*) as n from "r" where "a" = ? group by "ta_reason" order by "seq" asc, "n" desc'));
        $this->assertSame([], self::onlyFullGroupByViolations('select ta_reason, count(*) as n from "r" group by "ta_reason" order by "n" desc'));
        $this->assertSame([], self::onlyFullGroupByViolations('select max(id) as id from "m" group by "systematic_review_id"'));
        $this->assertSame([], self::onlyFullGroupByViolations('select * from "r" order by "seq" asc'));
    }
}
