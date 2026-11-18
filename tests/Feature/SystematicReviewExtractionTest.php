<?php

namespace Tests\Feature;

use App\Filament\Pages\ExtracaoRob;
use App\Filament\Resources\SystematicReviewResource;
use App\Filament\Resources\SystematicReviewResource\Pages\ViewSystematicReview;
use App\Filament\Resources\SystematicReviewResource\RelationManagers\StudiesRelationManager;
use App\Mail\AppNotificationMail;
use App\Models\SrExtraction;
use App\Models\Student;
use App\Models\SystematicReview;
use App\Models\User;
use App\Services\SystematicReviewExtractionService;
use App\Services\SystematicReviewProtocolService;
use App\Services\SystematicReviewService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Fases P7–P14: extração e RoB em duplicata, reconciliação, trava, análise, GRADE e submissão (fixture sintético). */
class SystematicReviewExtractionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
        $this->admin = User::factory()->create(['role' => 'admin', 'email' => 'prof@example.com', 'name' => 'Prof Teste']);
    }

    protected function ex(): SystematicReviewExtractionService
    {
        return app(SystematicReviewExtractionService::class);
    }

    protected function student(string $name, string $email): User
    {
        $user = User::factory()->create(['role' => 'student', 'name' => $name, 'email' => $email]);
        Student::create(['user_id' => $user->id, 'institution' => 'X', 'academic_level' => 'Graduação', 'research_topic' => 'Geral',
            'tcc_status' => 'none', 'phone' => '1', 'status' => 'active', 'category' => 'regular', 'last_semester_declared' => \App\Support\Semester::current()]);

        return $user;
    }

    /** Revisão com triagem encerrada: 3 incluídos e 1 excluído; dois revisores com termo assinado. */
    protected function screenedReview(): array
    {
        $pipeline = json_encode([
            'schema' => 'calefi-sr-pipeline/1.0', 'protocol_id' => 'P09', 'version' => '1.0', 'title' => 'Synthetic extraction protocol', 'label_pt' => 'Teste',
            'family' => 'intervention', 'keywords' => ['x'],
            'eligibility' => ['pico' => [['element' => 'Population', 'definition' => 'Y']], 'inclusion' => ['A'], 'exclusion' => ['B']],
            'search' => ['databases' => [['name' => 'PubMed/MEDLINE', 'interface' => 'PubMed', 'query' => 'x']]],
            'screening' => ['stages' => [['id' => 'title_abstract', 'decisions' => ['include', 'exclude', 'maybe']], ['id' => 'full_text', 'decisions' => ['include', 'exclude'], 'exclusion_reasons' => [['code' => 'E1', 'label' => 'Wrong']]]]],
            'extraction' => [
                'fields' => [['name' => 'country', 'type' => 'text', 'description' => 'Country'], ['name' => 'n_total', 'type' => 'integer', 'description' => 'Total animals'],
                    ['name' => 'setting', 'type' => 'category: laboratory|client-owned', 'description' => 'Setting']],
                'outcomes' => [['id' => 'O1', 'name' => 'Pain score', 'type' => 'continuous', 'measure' => 'SMD', 'timepoint' => '6 h', 'priority' => 'primary'],
                    ['id' => 'O2', 'name' => 'Rescue analgesia', 'type' => 'binary', 'measure' => 'RR', 'timepoint' => '24 h', 'priority' => 'secondary']],
            ],
            'risk_of_bias' => ['tools' => [['code' => 'SYRCLE', 'name' => 'SYRCLE', 'domains' => ['Sequence generation', 'Blinding of outcome assessor']]]],
            'synthesis' => ['model' => 'RE'],
        ]);
        $protocol = app(SystematicReviewProtocolService::class);
        $sr = app(SystematicReviewService::class);
        [$review] = $protocol->import($pipeline);
        $protocol->freeze($review);
        $protocol->register($review->fresh(), ['registration_id' => '10.17605/OSF.IO/EXTRA', 'registered_at' => '2026-10-20']);
        $review = $review->fresh();
        $records = [];
        foreach (['Silva, A', 'Souza, B', 'Lima, C', 'Reis, D'] as $i => $author) {
            $records[] = ['title' => "Study {$i}", 'authors' => $author, 'year' => '202' . $i, 'doi' => "10.1000/x.{$i}", 'sources' => ['PubMed/MEDLINE']];
        }
        $sr->import($review, json_encode(['schema' => 'profatilio-sr/1', 'search' => ['date' => '2026-11-10', 'searches' => [], 'sources' => ['PubMed/MEDLINE' => 4], 'duplicates_removed' => 0], 'records' => $records]), 'b.json');
        $ana = $this->student('Ana Lima', 'ana@x.com');
        $beto = $this->student('Beto Reis', 'beto@x.com');
        $a = $sr->addScreener($review, ['user_id' => $ana->id]);
        $b = $sr->addScreener($review, ['user_id' => $beto->id]);
        $sr->signTerms($a, 'Ana Lima', '1.1.1.1', 'UA');
        $sr->signTerms($b, 'Beto Reis', '1.1.1.2', 'UA');
        $review->records()->orderBy('seq')->take(3)->update(['ta_decision' => 'include', 'ft_decision' => 'include']);
        $review->records()->orderByDesc('seq')->take(1)->update(['ta_decision' => 'exclude']);
        $review->update(['status' => SystematicReview::STATUS_SCREENING_DONE, 'stage' => SystematicReview::STAGE_FT,
            'stage_closed_at' => ['title_abstract' => now()->subDay()->format('Y-m-d H:i:s'), 'full_text' => now()->format('Y-m-d H:i:s')]]);

        return [$review->fresh(), $a->fresh(), $b->fresh(), $ana, $beto];
    }

    protected function data(int $nTotal = 20, int $n = 10): array
    {
        return [
            'field_values' => ['country' => 'Brazil', 'n_total' => (string) $nTotal, 'setting' => 'laboratory'],
            'sources' => ['n_total' => 'p. 3'],
            'outcomes' => [['outcome_id' => 'O1', 'group_label' => 'treated', 'timepoint' => '6 h', 'n' => (string) $n, 'mean' => '2.1', 'sd' => '0.5', 'source_location' => 'Tab. 2']],
            'rob' => ['SYRCLE' => ['Sequence generation' => ['judgement' => 'unclear', 'support' => 'Not reported, p. 2'],
                'Blinding of outcome assessor' => ['judgement' => 'low', 'support' => 'Blinded observer, p. 3'], '_overall' => 'some']],
        ];
    }

    public function test_full_pipeline_from_extraction_to_publication(): void
    {
        [$review, $a, $b] = $this->screenedReview();
        $ex = $this->ex();
        $this->assertSame('P7', $ex->phase($review)['code']);
        $this->assertSame([], $ex->openProblems($review));

        $ex->open($review, 1);
        $review->refresh();
        $this->assertSame(3, $review->activeStudies()->count());
        $this->assertSame('P7a', $ex->phase($review)['code']);
        Mail::assertSent(AppNotificationMail::class, fn ($m) => $m->title === 'Nova etapa: extração de dados e risco de viés');
        [$pilot, $s2, $s3] = $review->activeStudies()->get()->all();
        $this->assertTrue($pilot->pilot);
        $this->assertSame('Silva 2020', $pilot->label);

        // Fora do piloto ainda não está disponível.
        try {
            $ex->save($a, $s2, $this->data());
            $this->fail('Extraiu estudo fora do piloto.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('não está disponível', $e->getMessage());
        }

        // Validações automáticas do pipeline.
        $bad = $this->data();
        $bad['outcomes'][0] = array_merge($bad['outcomes'][0], ['events' => '12', 'sd' => '0', 'proportion' => '1.2']);
        $bad['sources'] = [];
        $bad['rob']['SYRCLE']['Sequence generation']['support'] = '';
        $errors = $ex->validate($review, $bad, true);
        $joined = implode(' | ', $errors);
        foreach (['n_total: informe de onde saiu', 'eventos não podem ser maiores que n', 'desvio-padrão deve ser maior que zero', 'proporção deve estar entre 0 e 1', 'justifique com o trecho'] as $needle) {
            $this->assertStringContainsString($needle, $joined);
        }
        $this->assertNotEmpty($ex->validate($review, array_merge($this->data(), ['field_values' => ['setting' => 'zoo']]), false));

        // Piloto: os dois enviam; uma divergência (n do desfecho).
        $ex->save($a, $pilot, $this->data(20, 10), true);
        $ex->save($b, $pilot, $this->data(20, 11), true);
        Mail::assertSent(AppNotificationMail::class, fn ($m) => $m->title === 'Piloto de extração concluído');
        $divs = $ex->divergences($pilot->fresh());
        $this->assertCount(1, $divs);
        $this->assertStringContainsString('— n', $divs[0]['label']);

        // Já enviado: não pode editar.
        try {
            $ex->save($a, $pilot, $this->data());
            $this->fail('Editou extração enviada.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('já enviou', $e->getMessage());
        }

        $ex->reconcile($pilot->fresh(), [$divs[0]['key'] => 'b'], 'Conferido na Tab. 2', 'Prof Teste');
        $this->assertSame('11', $pilot->fresh()->reconciled_outcomes[0]['n']);

        $ex->releasePilot($review->fresh(), 'Definição de "grupo" esclarecida');
        $review->refresh();
        $this->assertSame('P7b–c', $ex->phase($review)['code']);
        foreach ([$s2, $s3] as $s) {
            $ex->save($a, $s, $this->data(), true);
            $ex->save($b, $s, $this->data(), true);
            $this->assertSame([], $ex->divergences($s->fresh()));
        }
        Mail::assertSent(AppNotificationMail::class, fn ($m) => $m->title === 'Extração concluída: reconciliar');

        // Não trava com estudo sem reconciliar.
        $this->assertNotEmpty($ex->lockProblems($review));
        foreach ([$s2, $s3] as $s) {
            $ex->reconcile($s->fresh(), [], null, 'Prof Teste');
        }
        $this->assertSame([], $ex->lockProblems($review->fresh()));

        $lock = $ex->lock($review->fresh(), 'Prof Teste');
        $this->assertSame(1, $lock->version);
        $this->assertSame(hash('sha256', $lock->extraction_csv), $lock->extraction_sha256);
        $this->assertStringContainsString('STU-', $lock->extraction_csv);
        $this->assertStringContainsString('Sequence generation', $lock->rob_csv);
        $this->assertContains('lock_v1', collect(app(SystematicReviewProtocolService::class)->osfPending($review->fresh()))->pluck('key')->all());
        $this->assertSame('P8', $ex->phase($review->fresh())['code']);

        // Nova versão: só com mudança e justificativa.
        $this->assertContains('Nada mudou desde a versão travada v1.', $ex->lockProblems($review->fresh()));
        $this->travel(1)->minutes();
        $ex->reconcile($s3->fresh(), [], 'Correção de rótulo', 'Prof Teste');
        try {
            $ex->lock($review->fresh(), 'Prof Teste');
            $this->fail('Travou v2 sem justificativa.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('justificativa', $e->getMessage());
        }
        $this->assertSame(2, $ex->lock($review->fresh(), 'Prof Teste', 'Corrigido rótulo do STU-3')->version);

        // Análise e reexecução independente.
        $run = $ex->registerAnalysis($review->fresh(), 'script_metanalise.R', "library(meta)\n", [['name' => 'forest.pdf', 'contents' => '%PDF-forest']], "R 4.4.1", 'Prof Teste');
        $this->assertSame(hash('sha256', "library(meta)\n"), $run->script_sha256);
        Storage::disk('local')->assertExists($run->outputs[0]['path']);
        try {
            $ex->confirmRerun($run, 'prof teste', true);
            $this->fail('Reexecução pela mesma pessoa.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('outra pessoa', $e->getMessage());
        }
        $ex->confirmRerun($run, 'Beto Reis', true, 'Mesmos IC e I²');
        $this->assertSame('P9', $ex->phase($review->fresh())['code']);
        $this->assertTrue(collect(app(SystematicReviewProtocolService::class)->osfPending($review->fresh()))->contains(fn ($s) => str_starts_with($s['key'], 'analysis_')));

        $ex->registerAnalysis($review->fresh(), 'extra.R', 'x', [], null, 'Prof Teste', 'Subgrupo não previsto', true);
        $this->assertSame(1, $review->deviations()->where('section', 'Análise estatística (post hoc)')->count());

        // GRADE → redação → submissão → publicação.
        $this->assertSame(2, $ex->seedGrade($review->fresh()));
        $review->gradeRows()->update(['certainty' => 'moderate']);
        $this->assertSame('P10–P11', $ex->phase($review->fresh())['code']);
        $ex->updateManuscript($review->fresh(), ['checks' => array_keys(SystematicReviewExtractionService::MANUSCRIPT_CHECKS), 'journal' => 'J Test', 'submitted_at' => '2027-10-01']);
        $this->assertSame('P12–P13', $ex->phase($review->fresh())['code']);
        $this->assertContains('deposit', collect(app(SystematicReviewProtocolService::class)->osfPending($review->fresh()))->pluck('key')->all());
        $ex->updateManuscript($review->fresh(), ['article_doi' => '10.1000/article.1']);
        $this->assertSame('P14', $ex->phase($review->fresh())['code']);

        // Exportações e pacote do OSF com 04 e 05.
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->admin);
        foreach (['extracao-travada' => 'P09_EXTRACAO_travada_v2.csv', 'rob-travado' => 'P09_RoB_v2.csv', 'contatos-autores' => 'P09_CONTATOS_autores.csv', 'grade' => 'P09_GRADE_SoF.csv'] as $type => $file) {
            $this->get(route('systematic-review.export', [$review, $type]))->assertOk()->assertHeader('Content-Disposition', "attachment; filename=\"{$file}\"");
        }
        $this->get(route('systematic-review.export', [$review, 'extracao-travada']) . '?v=1')->assertHeader('Content-Disposition', 'attachment; filename="P09_EXTRACAO_travada_v1.csv"');
        $this->get(route('systematic-review.export', [$review, 'extracao-duplicata']))->assertOk();
        $this->get(route('systematic-review.analysis-file', [$run, 0]))->assertOk();
        $zipPath = app(SystematicReviewProtocolService::class)->osfPackage($review->fresh());
        $zip = new \ZipArchive();
        $zip->open($zipPath);
        $names = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i));
        $this->assertTrue($names->contains(fn ($n) => str_ends_with($n, '04_Extracao_e_Risco_de_Vies/P09_EXTRACAO_travada_v2.csv')));
        $this->assertTrue($names->contains(fn ($n) => str_ends_with($n, '05_Analise/resultados/forest.pdf')));
        $this->assertTrue($names->contains(fn ($n) => str_ends_with($n, '05_Analise/sessionInfo.txt')));
        $zip->close();
        @unlink($zipPath);

        $this->get(SystematicReviewResource::getUrl('view', ['record' => $review]))->assertOk()->assertSee('Extração e análise');
        $this->get(SystematicReviewResource::getUrl('index'))->assertOk()->assertSee('P14');
    }

    public function test_student_extracts_on_portal_and_professor_reconciles_in_panel(): void
    {
        [$review, $a, $b, $ana] = $this->screenedReview();
        $this->ex()->open($review, 0);
        $study = $review->activeStudies()->first();

        // Aluno: portal mostra o guia e salva/enviará pela página.
        $this->actingAs($ana)->get('/portal/extracao')->assertOk()->assertSee('Etapa atual: extração de dados e risco de viés');
        Livewire::actingAs($ana)->test(ExtracaoRob::class)
            ->call('open', $study->id)
            ->set('data', $this->data(20, 10))
            ->call('submit')
            ->assertHasNoErrors();
        $this->assertTrue(SrExtraction::where('systematic_review_screener_id', $a->id)->where('sr_study_id', $study->id)->first()->isSubmitted());

        $this->ex()->save($b, $study, $this->data(20, 12), true);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->admin);
        Livewire::test(StudiesRelationManager::class, ['ownerRecord' => $review, 'pageClass' => ViewSystematicReview::class])
            ->assertSee($study->label)
            ->callTableAction('reconciliar', $study, ['choice' => ['d0' => 'a']])
            ->assertHasNoTableActionErrors();
        $this->assertSame('10', $study->fresh()->reconciled_outcomes[0]['n']);

        // Mesclar relato com extração enviada é bloqueado.
        $other = $review->activeStudies()->where('id', '!=', $study->id)->first();
        $this->expectException(\RuntimeException::class);
        $this->ex()->merge($other, [$study->id]);
    }
}
