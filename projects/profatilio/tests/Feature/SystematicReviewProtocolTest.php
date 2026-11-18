<?php

namespace Tests\Feature;

use App\Filament\Resources\SystematicReviewResource;
use App\Filament\Resources\SystematicReviewResource\Pages\EditSystematicReview;
use App\Filament\Resources\SystematicReviewResource\Pages\ViewSystematicReview;
use App\Mail\AppNotificationMail;
use App\Models\ResearchCall;
use App\Models\Student;
use App\Models\SystematicReview;
use App\Models\SystematicReviewDecision;
use App\Models\User;
use App\Services\SystematicReviewProtocolService;
use App\Services\SystematicReviewService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Protocolo registrado (pipeline JSON) → congelamento → registro OSF →
 * desvios/emendas → regras de triagem do protocolo → exportações e avisos.
 * O fixture é sintético: os títulos reais dos projetos nunca entram no repositório.
 */
class SystematicReviewProtocolTest extends TestCase
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

    protected function protocol(): SystematicReviewProtocolService
    {
        return app(SystematicReviewProtocolService::class);
    }

    protected function service(): SystematicReviewService
    {
        return app(SystematicReviewService::class);
    }

    protected function pipeline(array $overrides = []): string
    {
        return json_encode(array_replace_recursive([
            'schema' => 'calefi-sr-pipeline/1.0',
            'protocol_id' => 'P05',
            'version' => '1.0',
            'date' => '2026-10-15',
            'folder' => '05_Projeto_Teste',
            'label_pt' => 'Projeto de teste',
            'title' => 'Synthetic test protocol: a systematic review and meta-analysis',
            'short_title' => 'Synthetic test',
            'family' => 'prevalence',
            'keywords' => ['alpha', 'beta'],
            'registration' => ['registry' => 'OSF Registries', 'template' => 'Generalized Systematic Review Registration',
                'template_schema_id' => '69cbc587cd7e8b69fa11ab52', 'doi' => null,
                'metadata' => ['title' => 'Synthetic test protocol', 'description' => 'Synthetic description', 'tags' => ['alpha', 'beta']]],
            'questions' => ['primary' => ['What is the prevalence of X?'], 'secondary' => ['Does age matter?'], 'hypotheses' => ['H1: prevalence > 0']],
            'eligibility' => [
                'pico' => [
                    ['element' => 'Population', 'definition' => 'Animals of species Y'],
                    ['element' => 'Condition', 'definition' => 'Condition X'],
                    ['element' => 'Comparator', 'definition' => 'None'],
                    ['element' => 'Outcomes', 'definition' => 'Prevalence of X'],
                    ['element' => 'Study design', 'definition' => 'Cross-sectional and cohort'],
                ],
                'inclusion' => ['Reports prevalence of X in species Y.'],
                'exclusion' => ['Case reports.', 'Experimental infection.'],
                'language' => 'none', 'date_limits' => 'none',
            ],
            'search' => [
                'databases' => [
                    ['name' => 'PubMed/MEDLINE', 'interface' => 'PubMed', 'query' => '(x[tiab]) AND (y[tiab])'],
                    ['name' => 'Scopus', 'interface' => 'Scopus', 'query' => 'TITLE-ABS-KEY(x AND y)'],
                ],
                'validation_set' => [['key' => 'smith2020', 'pmid' => '123', 'citation' => 'Smith 2020']],
                'supplementary' => ['Reference lists'],
            ],
            'screening' => ['stages' => [
                ['id' => 'title_abstract', 'decisions' => ['include', 'exclude', 'maybe'], 'maybe_rule' => 'maybe from either reviewer proceeds to full text', 'calibration' => ['sample' => 50, 'kappa_min' => 0.6]],
                ['id' => 'full_text', 'decisions' => ['include', 'exclude'], 'calibration' => ['sample' => 10, 'kappa_min' => 0.6],
                    'exclusion_reasons' => [['code' => 'E1', 'label' => 'Case report'], ['code' => 'E2', 'label' => 'Experimental infection']]],
            ]],
            'extraction' => [
                'fields' => [['name' => 'country', 'type' => 'text', 'description' => 'Country'], ['name' => 'n_total', 'type' => 'integer', 'description' => 'Sample size']],
                'outcomes' => [['id' => 'O1', 'name' => 'Prevalence of X', 'type' => 'proportion', 'measure' => 'logit proportion', 'timepoint' => 'any', 'priority' => 'primary']],
            ],
            'risk_of_bias' => ['tools' => [['code' => 'JBI', 'name' => 'JBI prevalence checklist', 'domains' => ['Sample frame', 'Sampling']]], 'rule' => 'JBI for all'],
            'synthesis' => ['model' => 'Random-effects GLMM.', 'effect_measures' => ['Logit proportion'], 'min_studies_pooling' => 3,
                'min_studies_metaregression_per_covariate' => 10, 'min_studies_publication_bias' => 10, 'subgroups' => ['Region'], 'meta_regression' => ['Year'],
                'sensitivity' => ['Leave-one-out'], 'publication_bias' => 'Funnel when k ≥ 10.', 'certainty' => 'GRADE adapted.', 'narrative_synthesis' => 'SWiM'],
            'osf_fields' => ['376-8' => 'Prevalence review', '376-18' => 'Background text', '376-78' => 'Two reviewers screen independently.'],
        ], $overrides), JSON_UNESCAPED_UNICODE);
    }

    protected function student(string $name, string $email): User
    {
        $user = User::factory()->create(['role' => 'student', 'name' => $name, 'email' => $email]);
        Student::create(['user_id' => $user->id, 'institution' => 'X', 'academic_level' => 'Graduação', 'research_topic' => 'Geral',
            'tcc_status' => 'none', 'phone' => '1', 'status' => 'active', 'category' => 'regular']);

        return $user;
    }

    protected function searchJson(int $n = 6): string
    {
        $records = [];
        for ($i = 1; $i <= $n; $i++) {
            $records[] = ['title' => "Study {$i} on X in Y", 'abstract' => "Abstract {$i}", 'authors' => 'Doe, J', 'year' => '2020', 'doi' => "10.1000/p.{$i}", 'sources' => ['PubMed/MEDLINE']];
        }

        return json_encode(['schema' => 'profatilio-sr/1', 'search' => ['date' => '2026-11-10', 'query' => 'x AND y',
            'searches' => [['database' => 'PubMed/MEDLINE', 'platform' => 'NCBI', 'query' => '(x) AND (y)', 'date' => '2026-11-10', 'results' => $n]],
            'sources' => ['PubMed/MEDLINE' => $n], 'duplicates_removed' => 0], 'records' => $records]);
    }

    public function test_import_maps_protocol_and_links_selection_project(): void
    {
        $call = ResearchCall::create(ResearchCall::defaults());
        [$review, $created] = $this->protocol()->import($this->pipeline());

        $this->assertTrue($created);
        $this->assertSame('P05', $review->protocol_code);
        $this->assertSame('prevalence', $review->review_type);
        $this->assertSame('CoCoPop', $review->framework);
        $this->assertSame(50, $review->pilot_size);
        $this->assertSame(['E1 — Case report', 'E2 — Experimental infection'], $review->exclusion_reasons);
        $this->assertSame(['alpha', 'beta'], $review->highlight_terms['include']);
        $this->assertStringContainsString('E1: Case reports.', $review->protocol['exclusion_criteria']);
        $this->assertStringContainsString('Scopus — Scopus:', $review->protocol['search_string']);
        $this->assertSame('Animals of species Y', $review->protocol['population']);
        $this->assertSame('Condition X', $review->protocol['intervention']);
        $this->assertSame('None', $review->protocol['comparator']);
        $this->assertStringContainsString('Random-effects GLMM', $review->protocol['synthesis']);
        $this->assertSame('Background text', $review->protocol['background']);
        $this->assertTrue($review->maybeAdvancesToFullText());
        $this->assertSame(['include', 'exclude'], $review->allowedDecisions(SystematicReview::STAGE_FT));

        // P05 → PET-05 na seleção de alunos.
        $this->assertSame($review->id, $call->fresh()->projectsByCode()['PET-05']['systematic_review_id']);

        // Reimportar atualiza o mesmo projeto.
        [$again, $createdAgain] = $this->protocol()->import($this->pipeline(['title' => 'Synthetic test protocol v1.0 revised']));
        $this->assertFalse($createdAgain);
        $this->assertSame($review->id, $again->id);
        $this->assertSame(1, SystematicReview::count());

        $this->expectException(\RuntimeException::class);
        $this->protocol()->import(json_encode(['schema' => 'outro']));
    }

    public function test_freeze_register_and_amendment_gate_the_protocol(): void
    {
        [$review] = $this->protocol()->import($this->pipeline());

        // Não dá para registrar antes de congelar.
        $this->assertNotEmpty($this->protocol()->registrationProblems($review));
        $this->assertContains('Congele o protocolo (fase P1) antes de começar: critérios, códigos e campos ficam somente leitura e o SHA-256 é registrado.', $this->service()->startProblems($review));

        $this->protocol()->freeze($review, '%PDF-1.4 fake', 'p.pdf');
        $review->refresh();
        $this->assertTrue($review->isFrozen());
        $this->assertSame($review->computeProtocolHash(), $review->protocol_sha256);
        $this->assertSame(hash('sha256', '%PDF-1.4 fake'), $review->protocol_pdf_sha256);
        Storage::disk('local')->assertExists($review->protocol_pdf_path);
        Mail::assertSent(AppNotificationMail::class, fn ($m) => $m->title === 'Exportar dados para o OSF' && str_contains($m->messageContent, 'Registrar o protocolo no OSF'));

        // Congelado: reimportar é recusado e o formulário fica somente leitura.
        try {
            $this->protocol()->import($this->pipeline());
            $this->fail('Reimportou um protocolo congelado.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('congelado', $e->getMessage());
        }
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->admin);
        Livewire::test(EditSystematicReview::class, ['record' => $review->getRouteKey()])
            ->assertFormFieldIsDisabled('protocol.inclusion_criteria')
            ->assertFormFieldIsDisabled('exclusion_reasons')
            ->assertFormFieldIsDisabled('title');

        // DOI inválido é recusado; válido é normalizado.
        try {
            $this->protocol()->register($review, ['registration_id' => 'abc']);
            $this->fail('Aceitou DOI inválido.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('10.17605/OSF.IO', $e->getMessage());
        }
        $this->protocol()->register($review, ['registration_id' => 'https://doi.org/10.17605/osf.io/ab12c', 'osf_project_url' => 'https://osf.io/xyz12/',
            'registered_at' => '2026-10-20', 'embargo_until' => '2027-10-01']);
        $review->refresh();
        $this->assertSame('10.17605/OSF.IO/AB12C', $review->registration_id);
        $this->assertSame('2027-10-01', $review->embargo_until->toDateString());

        // Emenda aprovada: nova versão, protocolo volta a ser editável até novo congelamento.
        $d = $this->protocol()->addDeviation($review, ['type' => 'amendment', 'section' => 'Busca', 'description' => 'Inclui CAB Abstracts', 'justification' => 'Acesso obtido']);
        $review->refresh();
        $this->assertSame('1.1', $d->protocol_version_after);
        $this->assertSame('1.1', $review->protocol_version);
        $this->assertFalse($review->isFrozen());
        $this->assertContains('deviations', collect($this->protocol()->osfPending($review))->pluck('key')->all());
    }

    public function test_protocol_screening_rules_maybe_reasons_and_osf_pending_exports(): void
    {
        [$review] = $this->protocol()->import($this->pipeline());
        $this->protocol()->freeze($review);
        $this->protocol()->register($review->fresh(), ['registration_id' => '10.17605/OSF.IO/ABCDE', 'registered_at' => '2026-10-20']);
        $review = $review->fresh();
        $review->update(['pilot_size' => 0]);
        $this->service()->import($review, $this->searchJson(6), 'busca.json');
        $a = $this->service()->addScreener($review, ['user_id' => $this->student('Ana Lima', 'ana@x.com')->id]);
        $b = $this->service()->addScreener($review, ['user_id' => $this->student('Beto Reis', 'beto@x.com')->id]);
        $this->service()->signTerms($a, 'Ana Lima', '1.1.1.1', 'UA');
        $this->service()->signTerms($b, 'Beto Reis', '1.1.1.2', 'UA');
        $this->assertSame([], $this->service()->startProblems($review->fresh()));
        $this->service()->start($review->fresh());
        $review->refresh();

        // Motivos: "fora do tema" no título/resumo; códigos E… no texto completo.
        $taReasons = $this->service()->reasonsFor($review, SystematicReview::STAGE_TA);
        $this->assertSame(SystematicReviewService::TA_OFF_TOPIC, $taReasons[0]);
        $this->assertContains('E1 — Case report', $this->service()->reasonsFor($review, SystematicReview::STAGE_FT));

        // Busca importada e registro feito: o sistema pede para subir o log no componente 02.
        $this->assertContains('search', collect($this->protocol()->osfPending($review))->pluck('key')->all());

        $records = $review->records()->orderBy('seq')->get();
        // 1: incluir/incluir · 2: excluir/excluir · 3: talvez/excluir (regra → texto completo) · 4–6: incluir/incluir
        foreach ($records as $i => $r) {
            [$va, $vb] = match ($i) { 1 => ['exclude', 'exclude'], 2 => ['maybe', 'exclude'], default => ['include', 'include'] };
            $this->service()->decide($a->fresh(), $r, $va, $va === 'exclude' ? SystematicReviewService::TA_OFF_TOPIC : null);
            $this->service()->decide($b->fresh(), $r, $vb, $vb === 'exclude' ? SystematicReviewService::TA_OFF_TOPIC : null);
        }
        $this->service()->applyConsensus($review, SystematicReview::STAGE_TA, [$a->id, $b->id]);
        $maybeRecord = $records[2]->fresh();
        $this->assertSame('include', $maybeRecord->ta_decision);
        $this->assertSame(SystematicReviewService::MAYBE_RULE_NOTE, $maybeRecord->resolution_note);

        // Exportação parcial (etapa aberta) sai marcada e fora do pacote do OSF.
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->admin);
        $this->get(route('systematic-review.export', [$review, 'triagem-ta']))->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="P05_TRIAGEM_TA_decisoes_' . now()->format('Y-m-d') . '_PARCIAL.csv"');

        $this->service()->closeStage($review->fresh(), true);
        $review->refresh();
        Mail::assertSent(AppNotificationMail::class, fn ($m) => $m->title === 'Exportar dados para o OSF' && str_contains($m->messageContent, 'título/resumo encerrada'));
        $this->assertContains('ta', collect($this->protocol()->osfPending($review))->pluck('key')->all());

        // Texto completo: o protocolo não permite "talvez".
        try {
            $this->service()->decide($a->fresh(), $records[0], 'maybe');
            $this->fail('Aceitou "talvez" no texto completo.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('não permite', $e->getMessage());
        }

        // Decisões fechadas: método de resolução e códigos.
        $rows = collect($this->protocol()->decisionRows($review, SystematicReview::STAGE_TA));
        $this->assertSame('record_id', $rows[0][0]);
        $this->assertTrue($rows->contains(fn ($r) => ($r[14] ?? null) === 'regra do protocolo (talvez → texto completo)'));
        $this->assertTrue($rows->contains(fn ($r) => ($r[14] ?? null) === 'consenso'));

        // Todas as exportações respondem com o nome padrão do guia.
        foreach ([
            'osf-ficha' => 'OSF_Form_P05.txt', 'osf-json' => 'P05_OSF_registration_responses.json', 'pipeline' => 'pipeline_P05.json',
            'busca-log' => 'P05_BUSCA_log.csv', 'prisma-csv' => 'P05_PRISMA_contagens.csv', 'desvios' => 'P05_DESVIOS.csv',
            'extracao-modelo' => 'P05_EXTRACAO_modelo.csv', 'dicionario' => 'P05_dicionario_extracao.csv',
        ] as $type => $file) {
            $this->get(route('systematic-review.export', [$review, $type]))->assertOk()->assertHeader('Content-Disposition', "attachment; filename=\"{$file}\"");
        }
        $ta = $this->get(route('systematic-review.export', [$review, 'triagem-ta']));
        $this->assertStringNotContainsString('PARCIAL', $ta->headers->get('Content-Disposition'));

        $prisma = $this->protocol()->prismaRows($review);
        $this->assertTrue(collect($prisma)->contains(fn ($r) => $r[0] === 'screened_TA' && (int) $r[1] === 6));
        $this->assertTrue(collect($prisma)->contains(fn ($r) => $r[0] === 'kappa[title_abstract]'));
        $osf = json_decode($this->protocol()->osfResponsesJson($review), true);
        $this->assertSame('69cbc587cd7e8b69fa11ab52', $osf['registration_schema']);
        $this->assertSame('Background text', $osf['registration_responses']['376-18']);

        // Pacote: pastas dos componentes, só etapas fechadas, manifesto com SHA-256.
        $zipPath = $this->protocol()->osfPackage($review);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($zipPath) === true);
        $names = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i));
        $root = 'P05_OSF_' . now()->format('Y-m-d');
        foreach (['01_Protocolo_e_Registro/', '02_Busca/', '03_Triagem/', '04_Extracao_e_Risco_de_Vies/', '05_Analise/', '06_Manuscrito/'] as $dir) {
            $this->assertTrue($names->contains("{$root}/{$dir}"), "Falta a pasta {$dir}");
        }
        $this->assertTrue($names->contains(fn ($n) => str_contains($n, '03_Triagem/P05_TRIAGEM_TA_decisoes_')));
        $this->assertFalse($names->contains(fn ($n) => str_contains($n, 'TRIAGEM_FT')), 'Etapa aberta não pode ir para o OSF.');
        $manifest = $zip->getFromName("{$root}/LEIA-ME_e_SHA256.txt");
        $this->assertStringContainsString(hash('sha256', $zip->getFromName("{$root}/02_Busca/P05_BUSCA_log.csv")), $manifest);
        $zip->close();
        @unlink($zipPath);

        // "Marquei como enviado ao OSF" tira os itens da lista.
        $this->protocol()->markUploaded($review, ['search', 'ta']);
        $keys = collect($this->protocol()->osfPending($review->fresh()))->pluck('key')->all();
        $this->assertNotContains('search', $keys);
        $this->assertNotContains('ta', $keys);
    }

    public function test_admin_pages_show_osf_panel_and_actions(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->admin);
        [$review] = $this->protocol()->import($this->pipeline());

        $this->get(SystematicReviewResource::getUrl('index'))->assertOk()->assertSee('pendente');
        $this->get(SystematicReviewResource::getUrl('view', ['record' => $review]))->assertOk()
            ->assertSee('Protocolo e OSF')
            ->assertSee('Pendências no OSF')
            ->assertSee('Congelar o protocolo (P1)');

        Livewire::test(ViewSystematicReview::class, ['record' => $review->getRouteKey()])
            ->callAction('congelar', ['pdf' => null])
            ->assertHasNoActionErrors();
        $this->assertTrue($review->fresh()->isFrozen());

        Livewire::test(ViewSystematicReview::class, ['record' => $review->getRouteKey()])
            ->callAction('registroOsf', ['registration_id' => '10.17605/OSF.IO/QWERT', 'registered_at' => '2026-10-20'])
            ->assertHasNoActionErrors()
            ->callAction('desvio', ['type' => 'deviation', 'section' => 'RoB', 'description' => 'Ferramenta alternativa', 'justification' => 'Desenho do estudo'])
            ->assertHasNoActionErrors()
            ->callAction('marcarOsf', ['keys' => ['deviations']])
            ->assertHasNoActionErrors();

        $review->refresh();
        $this->assertSame('10.17605/OSF.IO/QWERT', $review->registration_id);
        $this->assertSame(1, $review->deviations()->count());
        $this->assertSame([], $this->protocol()->osfPending($review));
    }

    public function test_unfreeze_fixes_a_wrong_pdf_only_before_registration(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->admin);
        [$review] = $this->protocol()->import($this->pipeline());
        $this->protocol()->freeze($review, '%PDF wrong protocol');
        $wrongPath = $review->fresh()->protocol_pdf_path;
        Storage::disk('local')->assertExists($wrongPath);

        Livewire::test(ViewSystematicReview::class, ['record' => $review->getRouteKey()])
            ->callAction('descongelar', ['reason' => 'PDF do protocolo errado'])
            ->assertHasNoActionErrors();

        $review->refresh();
        $this->assertFalse($review->isFrozen());
        $this->assertNull($review->protocol_pdf_sha256);
        Storage::disk('local')->assertMissing($wrongPath);
        $this->assertStringContainsString('PDF do protocolo errado', collect($review->history)->last()['details']);

        $this->protocol()->freeze($review, '%PDF right protocol');
        $this->assertSame(hash('sha256', '%PDF right protocol'), $review->fresh()->protocol_pdf_sha256);

        $this->protocol()->register($review->fresh(), ['registration_id' => '10.17605/OSF.IO/ABCDE']);
        $this->assertNotEmpty($this->protocol()->unfreezeProblems($review->fresh()));
        $this->expectException(\RuntimeException::class);
        $this->protocol()->unfreeze($review->fresh(), 'tarde demais');
    }

    public function test_admin_list_shows_selection_code_terms_and_ready_to_freeze_filter(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->admin);
        [$ready] = $this->protocol()->import($this->pipeline());
        [$open] = $this->protocol()->import($this->pipeline(['protocol_id' => 'P15']));
        foreach ([$ready, $open] as $review) {
            $this->service()->addScreener($review, ['user_id' => $this->student('Ana Lima ' . $review->id, "ana{$review->id}@x.com")->id]);
            $this->service()->addScreener($review, ['user_id' => $this->student('Beto Reis ' . $review->id, "beto{$review->id}@x.com")->id]);
        }
        $ready->screeners()->update(['terms_accepted_at' => now()]);
        $open->screeners()->limit(1)->update(['terms_accepted_at' => now()]);

        $this->assertSame('P15', SystematicReviewResource::protocolCodeFromSearch('pat-05'));
        $this->assertSame('P05', SystematicReviewResource::protocolCodeFromSearch('PET-5'));

        $this->get(SystematicReviewResource::getUrl('index'))->assertOk()
            ->assertSee('PET-05')->assertSee('PAT-05')->assertSee('2/2')->assertSee('1/2')->assertSee('pronto para congelar');

        Livewire::test(\App\Filament\Resources\SystematicReviewResource\Pages\ListSystematicReviews::class)
            ->filterTable('ready_to_freeze')
            ->assertCanSeeTableRecords([$ready])
            ->assertCanNotSeeTableRecords([$open])
            ->removeTableFilters()
            ->searchTable('PAT-05')
            ->assertCanSeeTableRecords([$open])
            ->assertCanNotSeeTableRecords([$ready]);
    }

    public function test_stage_guides_for_student_and_professor(): void
    {
        [$review] = $this->protocol()->import($this->pipeline());
        $this->protocol()->freeze($review);
        $this->protocol()->register($review->fresh(), ['registration_id' => '10.17605/OSF.IO/ABCDE', 'registered_at' => '2026-10-20']);
        $review = $review->fresh();
        $review->update(['pilot_size' => 0]);
        $this->service()->import($review, $this->searchJson(4), 'busca.json');
        $ana = $this->student('Ana Lima', 'ana@x.com');
        $ana->student->update(['last_semester_declared' => \App\Support\Semester::current()]);
        $a = $this->service()->addScreener($review, ['user_id' => $ana->id]);
        $b = $this->service()->addScreener($review, ['user_id' => $this->student('Beto Reis', 'beto@x.com')->id]);

        // Professor: guia da fase com portão de qualidade.
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->admin)->get(SystematicReviewResource::getUrl('view', ['record' => $review]))->assertOk()
            ->assertSee('Guia do professor: o que fazer agora')
            ->assertSee('Pronto para liberar a calibração')
            ->assertSee('Portão para avançar');

        // Liberada a triagem, o aluno vê o projeto e o guia do termo antes de assinar.
        $this->service()->start($review->fresh());
        \Livewire\Livewire::actingAs($ana)->test(\App\Filament\Pages\TriagemCega::class)
            ->call('choose', $a->id)
            ->assertSee('Antes de começar: o termo de confidencialidade')
            ->assertSee('Por que isso importa');

        $this->service()->signTerms($a, 'Ana Lima', '1.1.1.1', 'UA');
        $this->service()->signTerms($b, 'Beto Reis', '1.1.1.2', 'UA');

        // Triagem: guia da etapa, faça/não faça, materiais com DOI e trilha do projeto.
        \Livewire\Livewire::actingAs($ana)->test(\App\Filament\Pages\TriagemCega::class)
            ->call('choose', $a->id)
            ->assertSee('Etapa atual: triagem por título e resumo')
            ->assertSee('Faça (reduz viés)')
            ->assertSee('Não faça')
            ->assertSee('https://doi.org/10.1136/bmj.n71')
            ->assertSee('Onde o projeto está')
            ->assertSee('Na dúvida, marque "Talvez".', false)
            ->assertSee('tc-actions');
    }


    public function test_wave3_pipeline_imports_checklist_portuguese_reasons_and_selection_code(): void
    {
        [$review] = $this->protocol()->import(file_get_contents(base_path('tests/fixtures/pipeline_leva3_P31.json')));
        $this->assertSame('P31', $review->protocol_code);
        $this->assertSame('BEM-01', $review->selectionCode());
        $this->assertSame('E6', $review->screening_checklist[0]['code'], 'Tipo de publicação é a 1ª pergunta.');
        $this->assertStringContainsString('ESTUDO ORIGINAL', $review->screening_checklist[0]['q']);
        $this->assertStringContainsString('Revisão', (string) $review->exclusionReasonPt('E6 — Review, editorial'));
        $this->assertNotNull($review->exclusionReasonPt('E1 — Population or host not eligible'));
        $this->assertSame(50, $review->pilot_size);
    }
}
