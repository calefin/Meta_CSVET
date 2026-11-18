<?php

namespace Tests\Feature;

use App\Filament\Pages\ExtracaoRob;
use App\Filament\Pages\TriagemCega;
use App\Models\SrExtraction;
use App\Models\Student;
use App\Models\SystematicReview;
use App\Models\SystematicReviewRecord;
use App\Models\SystematicReviewScreener;
use App\Models\User;
use App\Services\ActiveLearningService;
use App\Services\SystematicReviewExtractionService;
use App\Services\SystematicReviewProtocolService;
use App\Services\SystematicReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Protocolo v1.1 (IA declarada): triagem de título/resumo priorizada por
 * aprendizado ativo com regra de parada e checagem aleatória; pré-extração por
 * modelo de linguagem só para o Revisor 1; isolamento entre alunos.
 * Fixture sintético: nenhum título real entra no repositório.
 */
class SystematicReviewDeclaredAiTest extends TestCase
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

    protected function sr(): SystematicReviewService
    {
        return app(SystematicReviewService::class);
    }

    protected function al(): ActiveLearningService
    {
        return app(ActiveLearningService::class);
    }

    protected function student(string $name, string $email): User
    {
        $user = User::factory()->create(['role' => 'student', 'name' => $name, 'email' => $email]);
        Student::create(['user_id' => $user->id, 'institution' => 'X', 'academic_level' => 'Graduação', 'research_topic' => 'Geral',
            'tcc_status' => 'none', 'phone' => '1', 'status' => 'active', 'category' => 'regular', 'last_semester_declared' => \App\Support\Semester::current()]);

        return $user;
    }

    protected function pipeline(string $id = 'P11', array $rule = []): string
    {
        return json_encode([
            'schema' => 'calefi-sr-pipeline/1.0', 'protocol_id' => $id, 'version' => '1.1', 'date' => '2026-10-21',
            'title' => "Synthetic declared-AI protocol {$id}", 'label_pt' => 'Teste', 'family' => 'prevalence', 'keywords' => ['ferret'],
            'eligibility' => ['pico' => [['element' => 'Population', 'definition' => 'Ferrets']], 'inclusion' => ['Ferret neoplasms'], 'exclusion' => ['Other species']],
            'search' => ['databases' => [['name' => 'PubMed/MEDLINE', 'interface' => 'PubMed', 'query' => 'ferret']],
                'validation_set' => [['key' => 'v1', 'pmid' => '9001', 'citation' => 'V1'], ['key' => 'v2', 'pmid' => '9002', 'citation' => 'V2']]],
            'screening' => ['stages' => [
                ['id' => 'title_abstract', 'decisions' => ['include', 'exclude', 'maybe'], 'maybe_rule' => 'maybe from either reviewer proceeds to full text',
                    'calibration' => ['sample' => 4, 'kappa_min' => 0.6],
                    'prioritisation' => ['tool' => 'active learning in the platform',
                        'stopping_rule' => array_merge(['all_validation_set_found' => true, 'min_fraction_screened' => 0.10, 'min_screened_to_relevant_ratio' => 2, 'consecutive_irrelevant' => 5], $rule),
                        'quality_check' => ['fraction' => 0.10, 'minimum' => 3]]],
                ['id' => 'full_text', 'decisions' => ['include', 'exclude'], 'exclusion_reasons' => [['code' => 'E1', 'label' => 'Wrong species']]],
            ]],
            'extraction' => [
                'fields' => [['name' => 'country', 'type' => 'text', 'description' => 'Country'], ['name' => 'n_total', 'type' => 'integer', 'description' => 'Animals']],
                'outcomes' => [['id' => 'O1', 'name' => 'Neoplasm frequency', 'type' => 'proportion', 'measure' => 'logit', 'timepoint' => 'any', 'priority' => 'primary']],
                'llm_assistance' => ['applies_to' => 'Reviewer 1 only'],
            ],
            'risk_of_bias' => ['tools' => [['code' => 'JBI', 'name' => 'JBI', 'domains' => ['Sample frame']]]],
            'synthesis' => ['model' => 'GLMM'],
        ]);
    }

    /** Projeto registrado, com 8 relevantes (2 do conjunto de validação) e 52 irrelevantes, e a dupla da seleção. */
    protected function startedReview(string $id = 'P11'): array
    {
        $protocol = app(SystematicReviewProtocolService::class);
        [$review] = $protocol->import($this->pipeline($id));
        $protocol->freeze($review);
        $protocol->register($review->fresh(), ['registration_id' => '10.17605/OSF.IO/AI' . substr($id, 1), 'registered_at' => '2026-10-21']);
        $review = $review->fresh();

        $records = [];
        for ($i = 1; $i <= 8; $i++) {
            $records[] = ['title' => "Spontaneous neoplasm survey in pet ferrets {$i}", 'abstract' => 'Ferret tumour lymphoma insulinoma adrenal neoplasia pathology survey.',
                'year' => '2020', 'doi' => "10.1000/rel.{$i}", 'pmid' => $i <= 2 ? (string) (9000 + $i) : null, 'sources' => ['PubMed/MEDLINE']];
        }
        for ($i = 1; $i <= 52; $i++) {
            $records[] = ['title' => "Dietary fibre and growth in dairy cattle {$i}", 'abstract' => 'Cattle nutrition rumen diet weight gain feedlot trial.',
                'year' => '2019', 'doi' => "10.1000/irr.{$i}", 'sources' => ['PubMed/MEDLINE']];
        }
        $this->sr()->import($review, json_encode(['schema' => 'profatilio-sr/1', 'search' => ['date' => '2026-11-10', 'searches' => [],
            'sources' => ['PubMed/MEDLINE' => 60], 'duplicates_removed' => 0], 'records' => $records]), 's.json');

        // Piloto fixo: 1 relevante (não da validação) + 3 irrelevantes.
        $review->records()->update(['random_key' => 999]);
        $review->records()->where('doi', '10.1000/rel.3')->update(['random_key' => 1]);
        $review->records()->whereIn('doi', ['10.1000/irr.1', '10.1000/irr.2', '10.1000/irr.3'])->update(['random_key' => 2]);

        $ana = $this->student('Ana Lider', "ana{$id}@x.com");
        $beto = $this->student('Beto Revisor', "beto{$id}@x.com");
        $lead = $this->sr()->addScreener($review, ['user_id' => $ana->id, 'label' => 'Líder (1º autor)', 'skip_invite' => true]);
        $rev = $this->sr()->addScreener($review, ['user_id' => $beto->id, 'label' => 'Revisor(a) cego(a)', 'skip_invite' => true]);
        $this->sr()->signTerms($lead, 'Ana Lider', '1.1.1.1', 'UA');
        $this->sr()->signTerms($rev, 'Beto Revisor', '1.1.1.2', 'UA');
        $this->sr()->start($review->fresh());

        return [$review->fresh(), $lead->fresh(), $rev->fresh(), $ana, $beto];
    }

    /** Oráculo: decide como um aluno atento decidiria. */
    protected function decideTruth(SystematicReviewScreener $s, int $recordId, ?string $force = null): void
    {
        $record = SystematicReviewRecord::find($recordId);
        $relevant = str_contains($record->title, 'ferrets');
        $decision = $force ?? ($relevant ? 'include' : 'exclude');
        $this->sr()->decide($s->fresh(), $record, $decision, $decision === 'exclude' ? SystematicReviewService::TA_OFF_TOPIC : null);
    }

    /** Tria na ordem do sistema até a regra de parada valer. Devolve quantos leu. */
    protected function screenUntilRule(SystematicReviewScreener $s): int
    {
        $n = 0;
        while (!$this->al()->stoppingStatus($s->fresh())['met']) {
            $decided = array_keys($this->al()->labels($s->fresh()));
            $next = collect($this->sr()->orderFor($s->fresh()))->first(fn ($id) => !in_array($id, $decided, true));
            $this->assertNotNull($next, 'Acabaram os registros antes da regra de parada.');
            $this->decideTruth($s, $next);
            $n++;
        }

        return $n;
    }

    public function test_v11_pipeline_turns_on_declared_ai(): void
    {
        [$review] = app(SystematicReviewProtocolService::class)->import($this->pipeline());
        $this->assertTrue($review->prioritisedScreening());
        $this->assertTrue($review->llmAssistedExtraction());
        $this->assertSame(5, $review->stoppingRule()['consecutive']);
        $this->assertSame(3, $review->stoppingRule()['qc_min']);
        $this->assertSame('1.1', $review->protocol_version);

        // Termo novo: a IA permitida é só a declarada no protocolo.
        $text = SystematicReviewScreener::termsText($review, 'Ana', 'a@x.com');
        $this->assertStringContainsString('previstos e declarados no protocolo registrado', $text);
        $this->assertStringContainsString('Revisor(a) 2, faço a extração sem nenhum pré-preenchimento', $text);
        $this->assertSame('2026.10.7', SystematicReviewScreener::TERMS_VERSION);
    }

    public function test_pilot_records_left_undecided_come_first_after_release(): void
    {
        [$review, $lead, $rev] = $this->startedReview('P15');
        $pilot = $review->screenableRecords()->reorder('random_key')->orderBy('id')->pluck('id')->all();
        $this->assertSame($pilot, $review->pilotSampleIds());

        // Revisor 2 deixa o último do piloto sem decidir; o professor libera assim mesmo.
        foreach ($pilot as $id) {
            $this->decideTruth($lead, $id);
        }
        $left = array_pop($pilot);
        foreach ($pilot as $id) {
            $this->decideTruth($rev, $id);
        }
        $this->sr()->releasePilot($review->fresh(), 'Concordância total nos registros comuns.');
        $this->assertTrue($this->sr()->prioritisedNow($review->fresh()));

        // Na fila dele, o que sobrou do piloto vem logo depois dos já decididos (antes do ranking).
        $order = $this->sr()->orderFor($rev->fresh());
        $this->assertSame($left, $order[count($pilot)]);
        $this->assertSame(count($order), count(array_unique($order)));
        $this->assertSame($review->records()->count(), count($order));

        // Para quem terminou o piloto nada muda: depois dos decididos vem o ranking.
        $leadOrder = $this->sr()->orderFor($lead->fresh());
        $this->assertSame($review->records()->count(), count(array_unique($leadOrder)));
    }

    public function test_prioritised_screening_ranks_stops_and_runs_quality_check(): void
    {
        [$review, $lead, $rev] = $this->startedReview();
        $this->assertTrue($review->inPilot());

        // Piloto de calibração (sem priorização): os dois triam a mesma amostra.
        foreach ([$lead, $rev] as $s) {
            foreach ($review->screenableRecords()->pluck('id') as $id) {
                $this->decideTruth($s, $id);
            }
        }
        $this->sr()->releasePilot($review->fresh());
        $review->refresh();
        $this->assertTrue($this->sr()->prioritisedNow($review));

        // Ordem do Revisor 1: depois dos já decididos, os mais prováveis primeiro.
        $decided = array_keys($this->al()->labels($lead));
        $pending = array_values(array_diff($this->sr()->orderFor($lead), $decided));
        $top = SystematicReviewRecord::whereIn('id', array_slice($pending, 0, 7))->pluck('title');
        $this->assertTrue($top->every(fn ($t) => str_contains($t, 'ferrets')), 'Os relevantes não vieram primeiro.');

        // Concluir antes da regra de parada é recusado.
        try {
            $this->sr()->finish($lead->fresh());
            $this->fail('Concluiu sem cumprir a regra de parada.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('regra de parada', $e->getMessage());
        }

        // Os dois triam até a regra e concluem: abre a checagem aleatória do Revisor 2.
        $readLead = $this->screenUntilRule($lead);
        $this->assertLessThan(56, $readLead, 'A priorização não poupou leitura.');
        $status = $this->al()->stoppingStatus($lead->fresh());
        $this->assertTrue($status['criteria']['a']['ok'], 'Estudos de validação não achados.');
        $this->sr()->finish($lead->fresh());
        $this->screenUntilRule($rev);
        $this->sr()->finish($rev->fresh());

        $review->refresh();
        $this->assertTrue($review->qcOpen());
        $rev->refresh();
        $this->assertTrue($this->al()->isQcScreener($rev));
        $this->assertFalse($this->al()->isQcScreener($lead->fresh()));
        $round1 = $this->al()->currentRound($review)['record_ids'];
        $unscreened = $this->al()->currentRound($review)['unscreened'];
        $this->assertSame(max(3, (int) ceil(0.1 * $unscreened)), count($round1));
        $this->assertSame(['done' => 0, 'total' => count($round1)], $this->sr()->progress($rev));

        // Na checagem, só a amostra: outro registro é recusado.
        $outside = $review->records()->whereNotIn('id', $round1)->whereDoesntHave('decisions')->value('id');
        if ($outside) {
            try {
                $this->decideTruth($rev, $outside);
                $this->fail('Decidiu fora da amostra da checagem.');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('sorteados', $e->getMessage());
            }
        }

        // Rodada 1 acha um "possivelmente elegível" → os dois voltam a triar.
        foreach ($round1 as $i => $id) {
            $this->decideTruth($rev, $id, $i === 0 ? 'maybe' : 'exclude');
        }
        $this->sr()->finish($rev->fresh());
        $review->refresh();
        $this->assertSame('resume', $review->ta_qc['status']);
        $this->assertFalse($lead->fresh()->hasFinished(SystematicReview::STAGE_TA));
        $this->assertFalse($rev->fresh()->hasFinished(SystematicReview::STAGE_TA));
        // O que o Revisor 2 viu na checagem não conta na sequência de irrelevantes dele.
        $this->assertFalse($this->al()->stoppingStatus($rev->fresh())['met'] && $this->al()->stoppingStatus($rev->fresh())['streak'] >= 5 + count($round1));

        // Rodada 2: voltam até a regra; a checagem não acha nada → etapa pronta para o professor.
        $this->screenUntilRule($lead);
        $this->sr()->finish($lead->fresh());
        $this->screenUntilRule($rev);
        $this->sr()->finish($rev->fresh());
        $review->refresh();
        if ($review->qcOpen()) {
            foreach ($this->al()->currentRound($review)['record_ids'] as $id) {
                $this->decideTruth($rev, $id, 'exclude');
            }
            $this->sr()->finish($rev->fresh());
            $review->refresh();
        }
        $this->assertSame('passed', $review->ta_qc['status']);
        $this->assertSame(SystematicReview::STATUS_CONFLICTS, $review->status);
        $this->assertSame(0, $this->sr()->conflictsQuery($review)->count());

        // Relevante para um revisor → texto completo; nenhum relevante perdido.
        $this->assertSame(8 + 1, $review->records()->where('ta_decision', 'include')->count());
        $stop = $review->records()->where('ta_reason', SystematicReviewService::STOP_RULE_REASON)->count();
        $prisma = $this->sr()->prisma($review);
        $this->assertSame($stop, $prisma['ta_stop_rule']);
        $this->assertSame(60 - $stop, $prisma['ta_screened_manually']);
        $this->assertGreaterThan(0, $stop, 'Nenhum registro ficou para a regra de parada.');
        $this->assertStringContainsString('active learning', $this->sr()->methodsText($review));
        $this->assertStringContainsString('stopping rule', $this->sr()->methodsText($review));
        $methods = collect(app(SystematicReviewProtocolService::class)->decisionRows($review, SystematicReview::STAGE_TA))->pluck(14)->unique()->all();
        $this->assertContains('regra de parada (não triado; aprendizado ativo + checagem aleatória)', $methods);

        // O professor encerra normalmente.
        $this->sr()->closeStage($review->fresh(), true);
        $this->assertSame(SystematicReview::STAGE_FT, $review->fresh()->stage);
    }

    public function test_triage_page_shows_stopping_panel_and_is_isolated_per_student(): void
    {
        [$review, $lead, $rev, $ana, $beto] = $this->startedReview();
        foreach ([$lead, $rev] as $s) {
            foreach ($review->screenableRecords()->pluck('id') as $id) {
                $this->decideTruth($s, $id);
            }
        }
        $this->sr()->releasePilot($review->fresh());

        $this->actingAs($ana)->get('/portal/triagem-cega')->assertOk();
        Livewire::actingAs($ana)->test(TriagemCega::class)
            ->assertSee('Regra de parada do protocolo')
            ->assertSee('irrelevantes seguidos')
            ->assertDontSee('Beto Revisor');

        // Outro aluno da plataforma, fora do projeto: não vê nem decide nada.
        $outsider = $this->student('Caio Fora', 'caio@x.com');
        Livewire::actingAs($outsider)->test(TriagemCega::class)
            ->assertSee('Nenhuma triagem atribuída')
            ->assertDontSee($review->title)
            ->call('choose', $lead->id)
            ->assertDontSee($review->title);

        // Aluno de outro projeto não alcança registros deste.
        [$other, $otherLead] = array_slice($this->startedReview('P12'), 0, 2);
        $foreign = $review->records()->first();
        try {
            $this->sr()->decide($otherLead->fresh(), $foreign, 'include');
            $this->fail('Decidiu registro de outro projeto.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('não está na sua lista', $e->getMessage());
        }

        // Exportações são só do professor.
        $this->actingAs($ana)->get(route('systematic-review.export', [$review, 'triagem-ta']))->assertForbidden();
        $this->actingAs($ana)->get(route('systematic-review.export', [$review, 'ia-extracao']))->assertForbidden();
    }

    public function test_ai_prefill_only_for_lead_and_corrections_are_tracked(): void
    {
        [$review, $lead, $rev, $ana, $beto] = $this->startedReview();
        // Triagem encerrada à mão: 2 incluídos.
        $review->records()->where('doi', 'like', '10.1000/rel.%')->take(2)->update(['ta_decision' => 'include', 'ft_decision' => 'include']);
        $review->records()->whereNull('ta_decision')->update(['ta_decision' => 'exclude']);
        $review->update(['status' => SystematicReview::STATUS_SCREENING_DONE, 'stage' => SystematicReview::STAGE_FT,
            'stage_closed_at' => ['title_abstract' => now()->subDay()->format('Y-m-d H:i:s'), 'full_text' => now()->format('Y-m-d H:i:s')]]);
        $ex = app(SystematicReviewExtractionService::class);
        $ex->open($review->fresh(), 0);
        $review->refresh();
        [$s1, $s2] = $review->activeStudies()->get()->all();

        $json = json_encode(['field_values' => ['country' => 'Japan', 'n_total' => '574', 'unknown_field' => 'x'], 'sources' => ['n_total' => 'p. 2'],
            'outcomes' => [['outcome_id' => 'O1', 'group_label' => 'total', 'timepoint' => '', 'n' => '574', 'events' => '120', 'source_location' => 'Tab. 1'],
                ['outcome_id' => 'O9', 'n' => '1']]]);

        // Sem modelo registrado, recusa.
        try {
            $ex->importAiPrefill($s1, $json, 'Prof');
            $this->fail('Importou sem modelo registrado.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('modelo', $e->getMessage());
        }
        $ex->setAiModel($review->fresh(), 'ModeloX', '2026-10', 'Prof');
        $this->assertStringContainsString('Do not assess risk of bias', $review->fresh()->ai_extraction['prompt']);
        $e1 = $ex->importAiPrefill($s1->fresh(), "```json\n{$json}\n```", 'Prof');

        // Só o Revisor 1 recebeu; campos e desfechos fora do protocolo foram descartados.
        $this->assertSame($lead->id, $e1->systematic_review_screener_id);
        $this->assertSame(['country' => 'Japan', 'n_total' => '574'], $e1->field_values);
        $this->assertCount(1, $e1->outcomes);
        $this->assertFalse(SrExtraction::where('systematic_review_screener_id', $rev->id)->whereNotNull('ai_prefill')->exists());

        // Portal: o líder vê o aviso; o revisor não vê nada da IA.
        Livewire::actingAs($ana)->test(ExtracaoRob::class)->call('open', $s1->id)->assertSee('Pré-preenchido por IA');
        Livewire::actingAs($beto)->test(ExtracaoRob::class)->call('open', $s1->id)->assertDontSee('Pré-preenchido por IA')->assertDontSee('Japan');

        // Enviar sem marcar a conferência é recusado.
        $data = ['field_values' => ['country' => 'Japan', 'n_total' => '570'], 'sources' => ['n_total' => 'p. 2'],
            'outcomes' => [['outcome_id' => 'O1', 'group_label' => 'total', 'timepoint' => '', 'n' => '570', 'source_location' => 'Tab. 1']],
            'rob' => ['JBI' => ['Sample frame' => ['judgement' => 'low', 'support' => 'p. 2'], '_overall' => 'low']]];
        try {
            $ex->save($lead->fresh(), $s1->fresh(), $data, true);
            $this->fail('Enviou sem conferir a IA.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('conferiu CADA valor', $e->getMessage());
        }
        $sent = $ex->save($lead->fresh(), $s1->fresh(), $data + ['ai_verified' => true], true);
        // Propostos: country, n_total, n, events = 4; mantido 1, alterados 2 (n_total, n), apagado 1 (events).
        $this->assertEquals(['proposed' => 4, 'kept' => 1, 'changed' => 2, 'removed' => 1, 'pct_corrected' => 75.0], $sent->fresh()->ai_changes);
        $this->assertNotNull($sent->fresh()->ai_verified_at);

        // Depois de enviado, não reimporta; líder que começou sem IA também não é sobrescrito.
        try {
            $ex->importAiPrefill($s1->fresh(), $json, 'Prof');
            $this->fail('Sobrescreveu estudo enviado.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('já enviou', $e->getMessage());
        }
        $ex->save($lead->fresh(), $s2->fresh(), ['field_values' => ['country' => 'Italy'], 'sources' => [], 'outcomes' => [], 'rob' => []]);
        try {
            $ex->importAiPrefill($s2->fresh(), $json, 'Prof');
            $this->fail('Sobrescreveu o que o líder digitou.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('sem IA', $e->getMessage());
        }

        // O Revisor 2 envia sem IA e sem conferência.
        $ex->save($rev->fresh(), $s1->fresh(), $data, true);
        $rows = $ex->aiRows($review->fresh());
        $this->assertSame('TOTAL', end($rows)[0]);
        $this->assertEquals(75.0, end($rows)[12]);

        $this->actingAs($this->admin)->get(route('systematic-review.export', [$review, 'ia-extracao']))->assertOk();
        $this->actingAs($this->admin)->get(route('systematic-review.export', [$review, 'ia-prompt']))->assertOk()->assertSee('Do not assess risk of bias', false);
    }
}
