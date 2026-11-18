<?php

namespace Tests\Feature;

use App\Filament\Pages\TriagemCega;
use App\Filament\Resources\ArticleReviewResource\Pages\CreateArticleReview;
use App\Filament\Resources\SystematicReviewResource\Pages\ViewSystematicReview;
use App\Filament\Resources\SystematicReviewResource\RelationManagers\RecordsRelationManager;
use App\Filament\Resources\SystematicReviewResource\RelationManagers\ScreenersRelationManager;
use App\Models\InviteToken;
use App\Models\Student;
use App\Models\SystematicReview;
use App\Models\SystematicReviewRecord;
use App\Models\SystematicReviewScreener;
use App\Models\User;
use App\Services\ReferenceImporter;
use App\Services\SystematicReviewService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SystematicReviewFlowTest extends TestCase
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

    protected function service(): SystematicReviewService
    {
        return app(SystematicReviewService::class);
    }

    protected function student(string $name, string $email): User
    {
        $user = User::factory()->create(['role' => 'student', 'name' => $name, 'email' => $email]);
        Student::create(['user_id' => $user->id, 'institution' => 'X', 'academic_level' => 'Graduação', 'research_topic' => 'Geral',
            'tcc_status' => 'none', 'phone' => '1', 'status' => 'active', 'category' => 'regular']);

        return $user->fresh();
    }

    protected function searchJson(int $n = 10): string
    {
        $records = [];
        for ($i = 1; $i <= $n; $i++) {
            $records[] = ['title' => "Meloxicam study number {$i} in rabbits", 'abstract' => "Abstract {$i} about pain in rabbits.",
                'authors' => 'Silva, A; Souza, B', 'year' => '2020', 'journal' => 'J Exot Pet Med', 'doi' => "10.1000/test.{$i}",
                'volume' => '3', 'sources' => ['PubMed/MEDLINE']];
        }

        return json_encode(['schema' => 'profatilio-sr/1', 'search' => [
            'date' => '2026-10-15', 'query' => '(meloxicam) AND (rabbit)',
            'searches' => [['database' => 'PubMed/MEDLINE', 'platform' => 'NCBI', 'query' => '(meloxicam) AND (rabbit)', 'date' => '2026-10-15', 'results' => $n + 2]],
            'sources' => ['PubMed/MEDLINE' => $n + 2], 'duplicates_removed' => 2,
        ], 'records' => $records]);
    }

    protected function project(array $attrs = []): SystematicReview
    {
        return SystematicReview::create(array_merge([
            'title' => 'Meloxicam em coelhos',
            'protocol' => ['inclusion_criteria' => 'Rabbits, meloxicam', 'exclusion_criteria' => 'Humans'],
            'registration_platform' => 'OSF',
            'registration_id' => '10.17605/OSF.IO/ABCDE',
            'pilot_size' => 0,
        ], $attrs));
    }

    /** @return array{0: SystematicReview, 1: SystematicReviewScreener, 2: SystematicReviewScreener} */
    protected function startedWithTwo(int $records = 10, int $pilot = 0): array
    {
        $review = $this->project(['pilot_size' => $pilot]);
        $this->service()->import($review, $this->searchJson($records), 'busca.json');
        $a = $this->service()->addScreener($review, ['user_id' => $this->student('Ana Lima', 'ana@x.com')->id]);
        $b = $this->service()->addScreener($review, ['user_id' => $this->student('Beto Reis', 'beto@x.com')->id]);
        $this->service()->signTerms($a, 'Ana Lima', '1.1.1.1', 'UA');
        $this->service()->signTerms($b, 'beto reis', '1.1.1.2', 'UA');
        $this->service()->start($review->fresh());

        return [$review->fresh(), $a->fresh(), $b->fresh()];
    }

    public function test_parsers_read_ris_medline_and_scopus_csv(): void
    {
        $ris = "TY  - JOUR\nTI  - First title\nAU  - Doe, J\nPY  - 2019\nDO  - https://doi.org/10.1/ABC\nVL  - 4\nER  - \nTY  - JOUR\nTI  - Second\nER  - \n";
        $parsed = ReferenceImporter::parse($ris, 'scopus.ris', 'Scopus');
        $this->assertCount(2, $parsed['records']);
        $this->assertSame('10.1/abc', $parsed['records'][0]['doi']);
        $this->assertSame('4', $parsed['records'][0]['extra']['volume']);
        $this->assertSame(['Scopus' => 2], $parsed['meta']['sources']);

        $nbib = "PMID- 123456\nTI  - A long\n      title.\nAB  - Text.\nFAU - Doe, John\nDP  - 2021 Jan\nLID - 10.5/xyz [doi]\n\nPMID- 999999\nTI  - Other.\nDP  - 2020\n";
        $parsed = ReferenceImporter::parse($nbib, 'pubmed.nbib');
        $this->assertSame('A long title', $parsed['records'][0]['title']);
        $this->assertSame('10.5/xyz', $parsed['records'][0]['doi']);
        $this->assertSame('123456', $parsed['records'][0]['pmid']);

        $csv = "Authors,Title,Year,Source title,DOI,Abstract\n\"Doe J.; Roe K.\",\"Scopus paper\",2018,Vet J,10.9/q,\"Some abstract\"\n";
        $parsed = ReferenceImporter::parse($csv, 'scopus.csv');
        $this->assertSame('Scopus', array_key_first($parsed['meta']['sources']));
        $this->assertSame('Doe J.; Roe K.', $parsed['records'][0]['authors']);

        // CSV-modelo do kit é aceito com as bases e os campos extras.
        $model = file_get_contents(base_path('scripts/revisao_sistematica/modelo_importacao.csv'));
        $parsed = ReferenceImporter::parse($model, 'modelo_importacao.csv');
        $this->assertSame(['PubMed/MEDLINE', 'Scopus'], $parsed['records'][0]['sources']);
        $this->assertSame('1557-5063', $parsed['records'][0]['extra']['issn']);
        $this->assertNotNull(json_decode(file_get_contents(base_path('scripts/revisao_sistematica/modelo_importacao.json')), true));
    }

    public function test_import_dedupes_and_keeps_prisma_counts(): void
    {
        $review = $this->project();
        $r1 = $this->service()->import($review, $this->searchJson(5), 'busca.json');
        $this->assertSame(5, $r1['new']);

        // Mesmo DOI em outra base + mesmo título sem DOI = duplicatas.
        $ris = "TY  - JOUR\nTI  - Meloxicam study number 1 in rabbits\nDO  - 10.1000/TEST.1\nER  - \n"
            . "TY  - JOUR\nTI  - Meloxicam study number 2 in rabbits.\nPY  - 2020\nER  - \n"
            . "TY  - JOUR\nTI  - Brand new\nPY  - 2020\nER  - \n";
        $r2 = $this->service()->import($review->fresh(), $ris, 'scopus.ris', 'Scopus');
        $this->assertSame(1, $r2['new']);

        $review->refresh();
        $this->assertSame(6, $review->records()->count());
        $this->assertStringContainsString('Scopus', $review->records()->where('doi', '10.1000/test.1')->value('sources'));
        $p = $this->service()->prisma($review);
        $this->assertSame(['PubMed/MEDLINE' => 7, 'Scopus' => 3], $p['sources']);
        $this->assertSame(2 + 2, $p['duplicates']);
        $this->assertCount(1, $review->search_counts['searches']);
        $this->assertSame('(meloxicam) AND (rabbit)', $review->protocol['search_string']);
    }

    public function test_complete_abstracts_fills_only_blank_ones_and_keeps_prisma_counts(): void
    {
        $review = $this->project();
        $ris = "TY  - JOUR\nTI  - Sem resumo pelo DOI\nPY  - 2019\nDO  - 10.9/AAA\nER  - \n"
            . "TY  - JOUR\nTI  - Sem resumo e sem DOI, casa pelo titulo\nPY  - 1975\nER  - \n"
            . "TY  - JOUR\nTI  - Ja tem resumo\nPY  - 2020\nDO  - 10.9/bbb\nAB  - Original.\nER  - \n";
        $this->service()->import($review, $ris, 'wos.ris', 'Web of Science');
        $before = $review->fresh()->search_counts;

        $json = json_encode(['records' => [
            ['title' => 'x', 'doi' => 'https://doi.org/10.9/aaa', 'abstract' => 'Novo resumo A.', 'abstract_source' => 'Semantic Scholar'],
            ['title' => 'SEM RESUMO E SEM DOI, CASA PELO TITULO.', 'year' => '1975', 'abstract' => 'Novo resumo B.', 'abstract_source' => 'OpenAlex (título)'],
            ['title' => 'Ja tem resumo', 'doi' => '10.9/bbb', 'abstract' => 'Não deve trocar.'],
            ['title' => 'Trabalho que não está no projeto', 'doi' => '10.9/zzz', 'abstract' => 'Ignorar.'],
        ]]);
        $s = $this->service()->completeAbstracts($review->fresh(), $json);

        $this->assertSame([4, 2, 1, 1], [$s['read'], $s['filled'], $s['not_found'], $s['already']]);
        $this->assertSame(3, $review->records()->count());
        $this->assertSame('Novo resumo A.', $review->records()->where('doi', '10.9/aaa')->value('abstract'));
        $this->assertSame('Semantic Scholar', $review->records()->where('doi', '10.9/aaa')->first()->extra['abstract_source']);
        $this->assertSame('Novo resumo B.', $review->records()->where('year', '1975')->value('abstract'));
        $this->assertSame('Original.', $review->records()->where('doi', '10.9/bbb')->value('abstract'));
        $this->assertSame($before, $review->fresh()->search_counts);
        $this->assertSame('Web of Science', $review->records()->where('doi', '10.9/aaa')->value('sources'));

        // Depois de liberar: só preenche registro sem voto de ninguém.
        $ris2 = "TY  - JOUR\nTI  - Votado sem resumo\nPY  - 2018\nDO  - 10.9/ccc\nER  - \n"
            . "TY  - JOUR\nTI  - Nao votado sem resumo\nPY  - 2018\nDO  - 10.9/ddd\nER  - \n";
        $this->service()->import($review->fresh(), $ris2, 'wos2.ris', 'Web of Science');
        $review->update(['status' => SystematicReview::STATUS_SCREENING]);
        $voted = $review->records()->where('doi', '10.9/ccc')->first();
        $screener = \App\Models\SystematicReviewScreener::create(['systematic_review_id' => $review->id, 'name' => 'Aluno', 'email' => 'aluno@x.com']);
        $voted->decisions()->create(['systematic_review_screener_id' => $screener->id, 'stage' => SystematicReview::STAGE_TA, 'decision' => 'include']);
        $s2 = $this->service()->completeAbstracts($review->fresh(), json_encode(['records' => [
            ['title' => 'Votado sem resumo', 'doi' => '10.9/ccc', 'abstract' => 'Não pode entrar.'],
            ['title' => 'Nao votado sem resumo', 'doi' => '10.9/ddd', 'abstract' => 'Pode entrar.'],
        ]]));
        $this->assertSame([1, 1], [$s2['filled'], $s2['voted']]);
        $this->assertNull($voted->fresh()->abstract);
        $this->assertSame('Pode entrar.', $review->records()->where('doi', '10.9/ddd')->value('abstract'));

        // Depois da triagem de título/resumo, não mexe mais.
        $review->update(['status' => SystematicReview::STATUS_SCREENING_DONE]);
        $this->expectException(\RuntimeException::class);
        $this->service()->completeAbstracts($review->fresh(), $json);
    }

    public function test_import_dedupes_by_pmid_and_by_title_with_adjacent_year(): void
    {
        $review = $this->project();
        $medline = "PMID- 39737798\nDP  - 2025 Feb\nTI  - Sequence-based multi ancestry association study of Varroa resistance in honeybees.\nLID - 10.1111/mec.17637 [doi]\n\n"
            . "PMID- 11111111\nDP  - 2024\nTI  - Erratum.\n";
        $this->assertSame(2, $this->service()->import($review, $medline, 'pubmed.txt', 'PubMed/MEDLINE')['new']);

        // Scopus sem DOI: casa pelo PMID; WoS sem DOI e com o ano da versão online: casa pelo título com ano vizinho.
        // Título curto ("Erratum") de outro ano não é juntado.
        $ris = "TY  - JOUR\nTI  - Outro título qualquer no Scopus\nPY  - 2024\nC2  - PMID 39737798\nER  - \n";
        $this->assertSame(0, $this->service()->import($review->fresh(), $ris, 'scopus.ris', 'Scopus')['new']);
        $ris = "TY  - JOUR\nTI  - SEQUENCE-BASED MULTI ANCESTRY ASSOCIATION STUDY OF VARROA RESISTANCE IN HONEYBEES\nPY  - 2024\nER  - \n"
            . "TY  - JOUR\nTI  - Erratum\nPY  - 2023\nER  - \n";
        $this->assertSame(1, $this->service()->import($review->fresh(), $ris, 'wos.ris', 'Web of Science')['new']);

        $this->assertSame(3, $review->records()->count());
        $this->assertSame('PubMed/MEDLINE, Scopus, Web of Science', $review->records()->where('pmid', '39737798')->value('sources'));
    }

    public function test_training_review_per_pair_without_terms_and_outside_the_central(): void
    {
        [$review, $a] = $this->startedWithTwo(4);
        $medline = collect(\App\Console\Commands\SetupScreeningTraining::PMIDS)
            ->map(fn ($id) => "PMID- {$id}\nTI  - Internal teat sealant trial {$id}\nAB  - Dairy cows at dry-off.\nDP  - 2020\n")
            ->implode("\n");
        \Illuminate\Support\Facades\Http::fake(['eutils.ncbi.nlm.nih.gov/*' => \Illuminate\Support\Facades\Http::response($medline)]);

        $this->artisan('app:treino-triagem')->assertSuccessful();
        $this->artisan('app:treino-triagem')->assertSuccessful(); // idempotente
        $training = SystematicReview::where('is_training', true)->sole();
        $this->assertSame(25, $training->records()->count());
        $this->assertSame(2, $training->activeScreeners()->count());
        $this->assertSame([$review->id], \App\Filament\Pages\CentralMetanalises::programQuery()->pluck('id')->all());
        $this->assertSame([], $this->service()->startProblems($training));
        Mail::assertNotSent(\App\Mail\AppNotificationMail::class, fn ($m) => $m->title === 'Treino da triagem liberado');

        $this->artisan('app:treino-triagem --liberar')->assertSuccessful();
        Mail::assertSent(\App\Mail\AppNotificationMail::class, fn ($m) => $m->title === 'Treino da triagem liberado'
            && str_contains($m->messageContent, 'não há termo para assinar'));

        $screener = $training->screeners()->where('user_id', $a->user_id)->first();
        $this->assertTrue($screener->hasSignedTerms());
        $this->assertSame(25, $training->fresh()->screenableRecords()->count());

        // Os dois decidem os 25: o treino fecha sozinho e todos são avisados.
        foreach ($training->activeScreeners()->get() as $s) {
            foreach ($training->fresh()->screenableRecords()->get() as $r) {
                $this->service()->decide($s, $r, 'include');
            }
        }
        $this->assertSame(SystematicReview::STATUS_SCREENING_DONE, $training->fresh()->status);
        Mail::assertSent(\App\Mail\AppNotificationMail::class, fn ($m) => $m->title === 'Treino da triagem concluído');
        Mail::assertSent(\App\Mail\AppNotificationMail::class, fn ($m) => $m->title === 'Treino concluído: veja os resultados');
        Mail::assertNotSent(\App\Mail\AppNotificationMail::class, fn ($m) => $m->title === 'Piloto da triagem concluído');

        // A triagem real da dupla é liberada: o treino sai do portal.
        $review->update(['status' => SystematicReview::STATUS_PROTOCOL]);
        $this->service()->start($review->fresh());
        $this->assertSame(SystematicReview::STATUS_ARCHIVED, $training->fresh()->status);

        $this->artisan('app:treino-triagem --arquivar')->assertSuccessful();
    }

    protected function trainingWithTwo(): array
    {
        [$review, $a, $b] = $this->startedWithTwo(4);
        $review->update(['status' => SystematicReview::STATUS_PROTOCOL]); // projeto real ainda em preparação, como agora
        $medline = collect(\App\Console\Commands\SetupScreeningTraining::PMIDS)
            ->map(fn ($id) => "PMID- {$id}
TI  - Internal teat sealant trial {$id}
AB  - Dairy cows at dry-off.
DP  - 2020
")
            ->implode("
");
        \Illuminate\Support\Facades\Http::fake(['eutils.ncbi.nlm.nih.gov/*' => \Illuminate\Support\Facades\Http::response($medline)]);
        $this->artisan('app:treino-triagem')->assertSuccessful();

        return [$review, $a, $b, SystematicReview::where('is_training', true)->sole()];
    }

    public function test_training_has_its_own_menu_and_never_mixes_with_the_real_project(): void
    {
        // Relógio fixo antes do prazo: com a data real, o prazo de 30/10 já teria passado.
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-29 12:00'));
        [$review, $a, , $training] = $this->trainingWithTwo();
        $this->artisan('app:treino-triagem --liberar --ate="2026-10-30 23:59"')->assertSuccessful();
        $this->assertSame('30/10 23:59', $training->fresh()->training_closes_at->format('d/m H:i'));
        Mail::assertSent(\App\Mail\AppNotificationMail::class, fn ($m) => $m->title === 'Treino da triagem liberado'
            && str_contains($m->messageContent, 'aberto até 30/10 às 23h59') && str_contains($m->actionUrl, '/portal/treino-triagem'));

        $this->actingAs($a->user);
        $this->assertSame([$a->id], \App\Filament\Pages\TriagemCega::myAssignments()->pluck('id')->all());
        $mine = \App\Filament\Pages\TreinoTriagem::myAssignments()->sole();
        $this->assertSame($training->id, $mine->systematic_review_id);
        $this->assertTrue(\App\Filament\Pages\TreinoTriagem::shouldRegisterNavigation());
        Livewire::test(TriagemCega::class)->assertDontSee('TREINO');
        Livewire::test(\App\Filament\Pages\TreinoTriagem::class)->assertSee('Analisados: 0 de 25')->assertSee('trabalho 1 de 25', false)->assertDontSee('Gabarito do professor');
        $this->assertSame([$review->id], \App\Filament\Pages\CentralMetanalises::programQuery()->pluck('id')->all());

        $this->actingAs($this->admin);
        Livewire::test(\App\Filament\Pages\TreinoTriagemPainel::class)->assertSee('TREINO')->assertSee('aberto')->assertSee('30/10 23:59');
    }

    public function test_training_closes_at_the_deadline_and_both_see_the_results_with_the_key(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-29 12:00'));
        [, $a, $b, $training] = $this->trainingWithTwo();
        $this->artisan('app:treino-triagem --liberar --ate="2026-10-30 23:59"')->assertSuccessful();
        $training->refresh();
        $records = $training->screenableRecords()->get()->keyBy('pmid');
        // Ana acerta um incluído e erra a armadilha (dois selantes); Beto discorda nos dois.
        $ana = $training->screeners()->where('user_id', $a->user_id)->first();
        $bb = $training->screeners()->where('user_id', $b->user_id)->first();
        $this->service()->decide($ana, $records['12416803'], 'include');
        $this->service()->decide($ana->fresh(), $records['35379464'], 'include');
        $this->service()->decide($bb, $records['12416803'], 'exclude', 'E5 — Desfecho errado');
        $this->service()->decide($bb, $records['35379464'], 'exclude', 'E4 — Sem grupo de comparação sem selante');

        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-31 00:05'));
        $this->assertTrue($training->fresh()->trainingExpired());
        try {
            $this->service()->decide($bb->fresh(), $records['24351664'], 'include');
            $this->fail('Decidiu depois do prazo.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('prazo do treino terminou', $e->getMessage());
        }
        $this->assertSame(SystematicReview::STATUS_SCREENING_DONE, $training->fresh()->status);
        Mail::assertSent(\App\Mail\AppNotificationMail::class, fn ($m) => $m->title === 'Treino concluído: veja os resultados');
        $this->artisan('app:treino-triagem --vencidos')->assertSuccessful(); // nada mais a fechar

        $res = app(\App\Services\ScreeningTrainingService::class)->results($training->fresh());
        $this->assertSame(['done' => 2, 'total' => 25, 'right' => 1, 'scored' => 2], $res['score'][$ana->id]);
        $this->assertSame(['done' => 2, 'total' => 25, 'right' => 1, 'scored' => 2], $res['score'][$bb->id]);
        $this->assertTrue($res['rows'][0]['split']); // divergências primeiro
        $this->assertCount(25, \App\Support\TrainingKey::KEY);
        $this->assertSame([], array_diff(\App\Console\Commands\SetupScreeningTraining::PMIDS, array_keys(\App\Support\TrainingKey::KEY)));

        $this->actingAs($a->user);
        Livewire::withQueryParams(['projeto' => $ana->id])->test(\App\Filament\Pages\TreinoTriagem::class)
            ->assertSee('Gabarito do professor')->assertSee('Dupla: Beto Reis')->assertSee('Compara dois selantes');

        $this->actingAs($this->admin);
        Livewire::test(\App\Filament\Pages\TreinoTriagemPainel::class)->call('toggle', $training->id)
            ->assertSee('Gabarito do professor')->assertSee('Ana Lima')->assertSee('mais confundiram');

        $this->artisan('app:treino-triagem --arquivar')->assertSuccessful();
        $this->actingAs($a->user);
        $this->assertFalse(\App\Filament\Pages\TreinoTriagem::myAssignments()->exists());
    }

    public function test_start_is_blocked_without_registration_criteria_records_or_screeners(): void
    {
        $review = SystematicReview::create(['title' => 'X']);
        $this->assertCount(4, $this->service()->startProblems($review));
        $this->expectException(\RuntimeException::class);
        $this->service()->start($review);
    }

    public function test_external_screener_must_create_student_login_and_is_auto_approved(): void
    {
        $review = $this->project();
        $s = $this->service()->addScreener($review, ['name' => 'Carla Externa', 'email' => 'Carla@Fora.com']);
        $this->assertNull($s->user_id);
        $invite = InviteToken::where('email', 'carla@fora.com')->firstOrFail();
        $this->assertTrue($invite->auto_approve);
        Mail::assertSent(\App\Mail\AppNotificationMail::class, fn ($m) => $m->hasTo('carla@fora.com') && str_contains($m->actionUrl, $invite->token));

        $this->get('/invite?token=' . $invite->token)->assertOk()->assertSee('carla@fora.com');

        $form = ['token' => $invite->token, 'name' => 'Carla Externa', 'password' => 'segredo123', 'password_confirmation' => 'segredo123',
            'cpf' => '123', 'academic_level' => 'Graduação', 'phone' => '999', 'terms_accepted' => '1'];
        // E-mail diferente do convite é recusado.
        $this->post('/invite/register', $form + ['email' => 'outra@fora.com'])->assertSessionHasErrors('email');
        $this->post('/invite/register', $form + ['email' => 'carla@fora.com'])->assertOk();

        $user = User::where('email', 'carla@fora.com')->firstOrFail();
        // Só vincula depois de comprovar o e-mail.
        $this->assertNull($s->fresh()->user_id);
        $this->assertSame('awaiting_email_validation', $user->student->status);

        $this->get('/activate?token=' . $user->student->activation_token)->assertOk()->assertSee('Conta Ativada');
        $this->assertSame($user->id, $s->fresh()->user_id);
        $this->assertSame('active', $user->student->fresh()->status);
        $this->assertTrue($user->fresh()->canAccessPanel(Filament::getPanel('portal')));
    }

    public function test_terms_signature_must_match_name_and_blocks_screening(): void
    {
        $review = $this->project();
        $this->service()->import($review, $this->searchJson(3), 'b.json');
        $s = $this->service()->addScreener($review, ['user_id' => $this->student('Ana Lima', 'ana@x.com')->id]);
        $this->service()->start($review->fresh());

        try {
            $this->service()->decide($s->fresh(), $review->records()->first(), 'include');
            $this->fail('Deveria exigir o termo.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('termo', $e->getMessage());
        }

        $this->expectExceptionMessage('nome completo');
        $this->service()->signTerms($s, 'Outra Pessoa', '1.1.1.1', 'UA');
    }

    public function test_blind_dual_screening_kappa_conflicts_and_full_text(): void
    {
        [$review, $a, $b] = $this->startedWithTwo(10);
        $records = $review->records()->get();

        // Exclusão sem motivo é recusada.
        try {
            $this->service()->decide($a, $records[0], 'exclude');
            $this->fail();
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('motivo', $e->getMessage());
        }

        $votesA = ['include', 'include', 'include', 'exclude', 'exclude', 'exclude', 'exclude', 'exclude', 'maybe', 'include'];
        $votesB = ['include', 'include', 'exclude', 'exclude', 'exclude', 'exclude', 'exclude', 'exclude', 'include', 'include'];
        foreach ($records as $i => $r) {
            $this->service()->decide($a, $r, $votesA[$i], $votesA[$i] === 'exclude' ? 'População errada' : null);
            $this->service()->decide($b, $r, $votesB[$i], $votesB[$i] === 'exclude' ? 'População errada' : null);
        }

        $this->service()->finish($a);
        $this->assertSame(SystematicReview::STATUS_SCREENING, $review->fresh()->status);
        $this->service()->finish($b->fresh());

        $review->refresh();
        $this->assertSame(SystematicReview::STATUS_CONFLICTS, $review->status);
        $this->assertSame(2, $this->service()->conflictsQuery($review)->count()); // #3 (incl×excl) e #9 (talvez)

        $pair = $this->service()->reliability($review, SystematicReview::STAGE_TA)['pairs'][0];
        $this->assertSame(10, $pair['n']);
        $this->assertEqualsWithDelta(0.8, $pair['kappa']['agreement'], 1e-9);
        // po = .8; A: inc 4, exc 5, maybe 1; B: inc 4, exc 6 → pe = .16+.30 = .46 → κ = .34/.54
        $this->assertEqualsWithDelta(0.34 / 0.54, $pair['kappa']['kappa'], 1e-9);

        try {
            $this->service()->closeStage($review);
            $this->fail();
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('2 trabalho', $e->getMessage());
        }

        $this->service()->resolve($records[2], SystematicReview::STAGE_TA, 'exclude', 'População errada');
        $this->service()->resolve($records[8], SystematicReview::STAGE_TA, 'include');
        $this->service()->closeStage($review->fresh(), true);

        $review->refresh();
        $this->assertSame(SystematicReview::STAGE_FT, $review->stage);
        $this->assertSame(SystematicReview::STATUS_SCREENING, $review->status);
        $this->assertSame(4, $review->stageRecords()->count());
        $this->assertTrue($review->checklistDone('ta_done'));

        // Texto completo: "não obtido" vira caixa própria do PRISMA.
        $ft = $review->stageRecords()->get();
        foreach ($ft as $i => $r) {
            [$d, $why] = match ($i) {
                0 => ['exclude', SystematicReviewService::NOT_RETRIEVED],
                1 => ['exclude', 'Sem dados quantitativos'],
                default => ['include', null],
            };
            $this->service()->decide($a->fresh(), $r, $d, $why);
            $this->service()->decide($b->fresh(), $r, $d, $why);
        }
        $this->service()->finish($a->fresh());
        $this->service()->finish($b->fresh());
        $this->service()->closeStage($review->fresh());

        $review->refresh();
        $this->assertSame(SystematicReview::STATUS_SCREENING_DONE, $review->status);
        $p = $this->service()->prisma($review);
        $this->assertSame(10, $p['screened']);
        $this->assertSame(6, $p['ta_excluded']);
        $this->assertSame(4, $p['sought']);
        $this->assertSame(1, $p['not_retrieved']);
        $this->assertSame(3, $p['assessed']);
        $this->assertSame(['Sem dados quantitativos' => 1], $p['ft_reasons']);
        $this->assertSame(2, $p['included']);

        $methods = $this->service()->methodsText($review);
        $this->assertStringContainsString('10.17605/OSF.IO/ABCDE', $methods);
        $this->assertStringContainsString("Cohen's κ", $methods);
        $this->assertStringContainsString('2 studies were included', $methods);
    }

    public function test_pilot_shows_only_first_records_until_released(): void
    {
        [$review, $a, $b] = $this->startedWithTwo(10, 3);
        $this->assertTrue($review->inPilot());
        $this->assertSame(3, $review->screenableRecords()->count());

        // O piloto é sorteado: pega um registro que ficou fora dele.
        $outside = $review->records()->whereNotIn('id', $review->screenableRecords()->pluck('id'))->first();
        $this->expectExceptionMessage('não está na sua lista');
        $this->service()->decide($a, $outside, 'include');
    }

    public function test_pilot_completion_notifies_and_release_opens_all(): void
    {
        [$review, $a, $b] = $this->startedWithTwo(6, 2);
        foreach ($review->screenableRecords()->get() as $r) {
            $this->service()->decide($a, $r, 'include');
            $this->service()->decide($b, $r, 'include');
        }
        $this->assertTrue(collect($review->fresh()->history)->contains(fn ($e) => $e['event'] === 'Piloto concluído pelos triadores'));
        Mail::assertSent(\App\Mail\AppNotificationMail::class, fn ($m) => $m->title === 'Piloto da triagem concluído');

        try {
            $this->service()->finish($a);
            $this->fail();
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('piloto', $e->getMessage());
        }

        $this->service()->releasePilot($review->fresh());
        $this->assertSame(6, $review->fresh()->screenableRecords()->count());
        $this->assertSame(['done' => 2, 'total' => 6], $this->service()->progress($a->fresh()));
    }

    public function test_cohen_kappa_textbook_value(): void
    {
        // Exemplo clássico: 50 itens, 20 sim/sim, 15 não/não, 5 sim/não, 10 não/sim → κ = 0,40
        $a = array_merge(array_fill(0, 20, 'y'), array_fill(0, 15, 'n'), array_fill(0, 5, 'y'), array_fill(0, 10, 'n'));
        $b = array_merge(array_fill(0, 20, 'y'), array_fill(0, 15, 'n'), array_fill(0, 5, 'n'), array_fill(0, 10, 'y'));
        $k = SystematicReviewService::cohenKappa($a, $b);
        $this->assertEqualsWithDelta(0.40, $k['kappa'], 1e-9);
        $this->assertSame('razoável', SystematicReviewService::kappaLabel($k['kappa']));
    }

    public function test_red_terms_match_only_at_word_start(): void
    {
        $html = TriagemCega::highlight('Pirate rats rate the review; mice', ['mice'], ['rat', 'review']);
        $this->assertStringContainsString('Pirate', $html);
        $this->assertStringNotContainsString('Pi<mark', $html);
        $this->assertStringContainsString('>rat</mark>s', $html);
        $this->assertStringContainsString('>rat</mark>e', $html);
        $this->assertStringContainsString('>review</mark>', $html);
        $this->assertStringContainsString('#bbf7d0', $html); // verde continua
    }

    public function test_screening_aids_command_keeps_green_terms_and_shows_tips_in_portal(): void
    {
        [$review, $a] = $this->startedWithTwo(3);
        $review->update(['protocol_code' => 'P05', 'highlight_terms' => ['include' => ['rabbit'], 'exclude' => ['old']]]);
        $file = tempnam(sys_get_temp_dir(), 'aids');
        file_put_contents($file, json_encode(['_common_tips' => 'Comum a todos.', 'P05' => ['exclude_terms' => ['in vitro', 'OLD'], 'tips' => 'Relato de caso é E10.']]));
        $this->artisan('metanalises:ajudas', ['--file' => $file])->assertSuccessful();
        $review = $review->fresh();
        $this->assertSame(['rabbit'], $review->highlight_terms['include']);
        $this->assertSame(['old', 'in vitro'], $review->highlight_terms['exclude']);
        $this->assertSame("Relato de caso é E10.
Comum a todos.", $review->screening_tips);

        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $this->actingAs($a->user);
        Livewire::test(TriagemCega::class)->assertSee('Atenção neste projeto')->assertSee('Relato de caso é E10.')->assertSee('Quem decide é você.');
        @unlink($file);
    }

    public function test_portal_page_is_blind_and_screens_with_server_validation(): void
    {
        [$review, $a, $b] = $this->startedWithTwo(3);
        $first = $review->records()->find($this->service()->orderFor($a)[0]);
        $this->service()->decide($b, $first, 'exclude', 'Somente in vitro', 'nota secreta do B');

        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $this->actingAs($a->user);

        Livewire::test(TriagemCega::class)
            ->assertSee($first->title)
            ->assertDontSee('nota secreta do B')
            ->assertDontSee('Sua decisão')
            ->call('decide', 'include')
            ->assertHasNoErrors();
        $this->assertSame('include', $a->decisions()->where('systematic_review_record_id', $first->id)->value('decision'));

        // Outro aluno não consegue abrir o projeto de A.
        $intruder = $this->student('Zé Intruso', 'ze@x.com');
        $this->actingAs($intruder);
        Livewire::withQueryParams(['projeto' => $a->id])->test(TriagemCega::class)->assertDontSee($first->title);
        $this->assertFalse(TriagemCega::shouldRegisterNavigation());
    }

    public function test_student_in_two_projects_sees_both_and_what_is_left_in_each(): void
    {
        [$pet, $a, $b] = $this->startedWithTwo(3);
        $pet->update(['protocol_code' => 'P03']);
        // A mesma dupla no segundo projeto (PAT), ainda em preparação e já com o termo assinado.
        $pat = $this->project(['title' => 'Projeto de patologia', 'protocol_code' => 'P15']);
        $this->service()->import($pat, $this->searchJson(2), 'busca.json');
        $a2 = $this->service()->addScreener($pat, ['user_id' => $a->user_id]);
        $this->service()->signTerms($a2, 'Ana Lima', '1.1.1.1', 'UA');
        $this->service()->addScreener($pat, ['user_id' => $b->user_id]);

        // E-mail da liberação: diz que são 2 projetos diferentes e que precisa fazer os dois.
        $text = $this->service()->assignmentText($a->fresh());
        $this->assertStringContainsString('PET-03, "Meloxicam em coelhos"', $text);
        $this->assertStringContainsString('2 projetos diferentes (PET-03, PAT-05)', $text);
        $this->assertStringContainsString('TODOS', $text);

        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $this->actingAs($a->user);
        Livewire::test(TriagemCega::class)
            ->assertSee('Você participa de 2 projetos diferentes')
            ->assertSee('Projeto 1 de 2 · PET-03')
            ->assertSee('faltam 3')
            ->assertSee('Projeto 2 de 2 · PAT-05')
            ->assertSee('em preparação');

        // Dentro do PAT (em preparação), aparece o aviso de que o PET ainda tem trabalho a fazer.
        Livewire::withQueryParams(['projeto' => $a2->id])->test(TriagemCega::class)
            ->assertSee('Você também tem outro projeto para fazer')
            ->assertSee('faltam 3 de 3');
    }

    public function test_pilot_is_shown_in_two_goals_without_changing_its_size(): void
    {
        $this->assertSame(['n' => 1, 'done' => 0, 'size' => 25], TriagemCega::pilotGoal(0, 50));
        $this->assertSame(['n' => 1, 'done' => 24, 'size' => 25], TriagemCega::pilotGoal(24, 50));
        $this->assertSame(['n' => 2, 'done' => 0, 'size' => 25], TriagemCega::pilotGoal(25, 50));
        $this->assertNull(TriagemCega::pilotGoal(50, 50));

        [$review, $a] = $this->startedWithTwo(10, 6);
        $this->assertSame(6, $this->service()->progress($a)['total']);
        foreach (array_slice($this->service()->orderFor($a), 0, 2) as $id) {
            $this->service()->decide($a, $review->records()->find($id), 'include');
        }
        // A mesma aluna num 2º projeto, para o quadro aparecer.
        $other = $this->project(['title' => 'Outro', 'protocol_code' => 'P12']);
        $this->service()->import($other, $this->searchJson(2), 'busca.json');
        $this->service()->signTerms($this->service()->addScreener($other, ['user_id' => $a->user_id]), 'Ana Lima', '1', 'UA');

        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $this->actingAs($a->user);
        Livewire::test(TriagemCega::class)->assertSee('meta 1: 2 de 3 · faltam 1')->assertSee('(2 de 6 no piloto)');
        Livewire::withQueryParams(['projeto' => $a->id])->test(TriagemCega::class)->assertSee('Meta 1 de 2 do piloto:');
    }

    public function test_card_counter_shows_how_many_were_analysed_not_the_shuffled_position(): void
    {
        $this->assertSame(['n' => 2, 'done' => 1, 'size' => 25, 'meta' => 1, 'decided' => false], TriagemCega::counter(1, 50, false, true));
        $this->assertSame(['n' => 1, 'done' => 0, 'size' => 25, 'meta' => 2, 'decided' => false], TriagemCega::counter(25, 50, false, true));
        $this->assertSame(['n' => 4, 'done' => 3, 'size' => 10, 'meta' => null, 'decided' => true], TriagemCega::counter(3, 10, true, false));

        [$review, $a] = $this->startedWithTwo(10, 6);
        $order = $this->service()->orderFor($a);
        // Vota o 1º e pula o 2º: antes o cartão saltava de "Trabalho 2" para "Trabalho 4" e parecia ordem errada.
        foreach ([$order[0], $order[2], $order[3]] as $id) {
            $this->service()->decide($a, $review->records()->find($id), 'include');
        }

        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $this->actingAs($a->user);
        Livewire::withQueryParams(['projeto' => $a->id])->test(TriagemCega::class)
            ->assertSee('Analisados: 0 de 3')->assertSee('meta 2 de 2 do piloto')->assertSee('trabalho 1 de 3', false)
            ->assertSee('0 de 3 analisados · faltam 3')
            ->call('jump', $order[0])->assertSee('Este trabalho já tem o seu voto');
    }

    public function test_double_tap_decides_only_the_record_on_screen_and_keeps_the_note(): void
    {
        [$review, $a] = $this->startedWithTwo(4);
        $order = $this->service()->orderFor($a);
        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $this->actingAs($a->user);

        $page = Livewire::test(TriagemCega::class)
            ->assertSet('recordId', $order[0])
            ->set('note', 'Dúvida sobre a dose')
            ->call('decide', 'include', null, $order[0])
            ->assertSet('recordId', $order[1])
            // Segundo toque do mesmo clique (celular): chega com o id do trabalho anterior.
            ->call('decide', 'include', null, $order[0])
            ->assertSet('recordId', $order[1]);
        $this->assertSame(1, $a->decisions()->count(), 'o toque duplo decidiu o trabalho seguinte sem o aluno ler');
        $this->assertSame('Dúvida sobre a dose', $a->decisions()->where('systematic_review_record_id', $order[0])->value('note'));

        // Excluir grava o motivo escolhido; a observação não vaza para o trabalho seguinte.
        $page->assertSet('note', '')
            ->call('decide', 'exclude', 'Somente in vitro', $order[1]);
        $vote = $a->decisions()->where('systematic_review_record_id', $order[1])->first();
        $this->assertSame(['exclude', 'Somente in vitro', null], [$vote->decision, $vote->reason, $vote->note]);

        // "Anterior" volta ao que acabou de decidir mesmo com "só pendentes" (para corrigir um clique errado).
        $page->assertSet('onlyPending', true)->assertSet('recordId', $order[2])
            ->call('go', -1)->assertSet('recordId', $order[1])->assertSee('Sua decisão');

        // Voltar ao primeiro mostra a observação gravada; trocar a decisão não a apaga.
        $page->call('jump', $order[0])->assertSet('note', 'Dúvida sobre a dose')
            ->call('decide', 'maybe', null, $order[0]);
        $first = $a->decisions()->where('systematic_review_record_id', $order[0])->first();
        $this->assertSame(['maybe', 'Dúvida sobre a dose'], [$first->decision, $first->note]);
        $this->service()->decide($a->fresh(), $review->records()->find($order[0]), 'include');
        $this->assertSame('Dúvida sobre a dose', $first->fresh()->note);
    }

    public function test_portal_terms_screen_and_signature(): void
    {
        $review = $this->project();
        $this->service()->import($review, $this->searchJson(2), 'b.json');
        $user = $this->student('Ana Lima', 'ana@x.com');
        $s = $this->service()->addScreener($review, ['user_id' => $user->id]);
        $this->service()->start($review->fresh());

        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $this->actingAs($user);
        Livewire::test(TriagemCega::class)
            ->assertSee('TERMO DE CONFIDENCIALIDADE')
            ->set('signature', 'Ana Lima')
            ->call('sign')
            ->assertSee('TERMO DE CONFIDENCIALIDADE') // faltou marcar "li e concordo"
            ->set('termsRead', true)
            ->call('sign')
            ->assertSee('Meloxicam study number');

        $s->refresh();
        $this->assertTrue($s->hasSignedTerms());
        $this->assertSame(hash('sha256', $s->terms_text), $s->terms_sha256);
        $this->get(route('systematic-review.my-terms', $s))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_admin_pages_exports_and_article_prefill(): void
    {
        [$review, $a, $b] = $this->startedWithTwo(3);
        foreach ($review->records()->get() as $i => $r) {
            $this->service()->decide($a, $r, 'include');
            $this->service()->decide($b, $r, $i === 0 ? 'maybe' : 'include');
        }
        $this->service()->finish($a);
        $this->service()->finish($b->fresh());
        $review->refresh();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->admin);

        $this->get(\App\Filament\Resources\SystematicReviewResource::getUrl('index'))->assertOk();
        $this->get(\App\Filament\Resources\SystematicReviewResource::getUrl('view', ['record' => $review]))->assertOk()->assertSee('Fluxograma PRISMA 2020');
        $this->get(\App\Filament\Resources\SystematicReviewResource::getUrl('edit', ['record' => $review]))->assertOk();
        Livewire::test(RecordsRelationManager::class, ['ownerRecord' => $review, 'pageClass' => ViewSystematicReview::class])
            ->assertCanSeeTableRecords($review->records()->whereNull('ta_decision')->get())
            ->assertCanNotSeeTableRecords($review->records()->whereNotNull('ta_decision')->get())
            ->callTableAction('excluir', $review->records()->first(), ['reason' => 'População errada'])
            ->assertHasNoTableActionErrors();
        $this->assertSame('exclude', $review->records()->first()->ta_decision);
        Livewire::test(ScreenersRelationManager::class, ['ownerRecord' => $review, 'pageClass' => ViewSystematicReview::class])
            ->assertSee('Ana Lima');

        foreach (['protocolo', 'triagem', 'incluidos-csv', 'incluidos-ris', 'relatorio'] as $type) {
            $this->get(route('systematic-review.export', [$review, $type]))->assertOk();
        }
        $this->get(route('systematic-review.terms', $a))->assertOk();
        $this->get(route('systematic-review.kit'))->assertOk()->assertDownload('kit_revisao_sistematica.zip');

        // Aluno não baixa nada do professor.
        $this->actingAs($a->user);
        $this->get(route('systematic-review.export', [$review, 'triagem']))->assertForbidden();
        $this->get(route('systematic-review.terms', $b))->assertForbidden();
        $this->get(route('systematic-review.my-terms', $b))->assertForbidden();

        // "Criar artigo": modelo completo com o projeto e os triadores como participantes.
        $this->actingAs($this->admin);
        $this->withoutExceptionHandling();
        $component = Livewire::withQueryParams(['systematic_review' => $review->id])->test(CreateArticleReview::class)
            ->assertSet('data.development_model', 'complete')
            ->assertSet('data.systematic_review_id', $review->id)
            ->assertSet('data.title', 'Meloxicam em coelhos');
        $names = collect($component->get('data.reviewers'))->pluck('name')->all();
        $this->assertSame(['Ana Lima', 'Beto Reis'], $names);

        $component->fillForm([
            'work_type' => 'article',
            'default_review_days' => 7,
            'original_file_path' => \Illuminate\Http\UploadedFile::fake()->createWithContent('a.docx', ArticleReviewFlowTest::docx(['texto um', 'texto dois'])),
        ])->call('create')->assertHasNoFormErrors();

        $article = \App\Models\ArticleReview::firstOrFail();
        $this->assertSame($review->id, $article->systematic_review_id);
        $this->assertSame(['ana@x.com', 'beto@x.com'], $article->reviewers()->pluck('email')->all());
        $this->assertSame($a->user_id, $article->reviewers()->first()->user_id);
    }

    public function test_professor_cannot_decide_during_blind_screening_and_override_needs_justification(): void
    {
        [$review, $a, $b] = $this->startedWithTwo(3);
        $records = $review->records()->get();
        try {
            $this->service()->resolve($records[0], SystematicReview::STAGE_TA, 'include');
            $this->fail('Professor não pode decidir antes dos alunos terminarem.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('depois que todos', $e->getMessage());
        }

        foreach ($records as $r) {
            $this->service()->decide($a, $r, 'exclude', 'População errada');
            $this->service()->decide($b, $r, 'exclude', 'População errada');
        }
        $this->service()->finish($a);
        $this->service()->finish($b->fresh());
        $review->refresh();
        $this->assertSame('exclude', $records[0]->fresh()->ta_consensus);

        try {
            $this->service()->resolve($records[0]->fresh(), SystematicReview::STAGE_TA, 'include');
            $this->fail('Sobrepor consenso exige justificativa.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('justificativa', $e->getMessage());
        }
        $this->service()->resolve($records[0]->fresh(), SystematicReview::STAGE_TA, 'include', null, 'Estudo em coelhos de laboratório, critério esclarecido');
        $this->assertTrue(collect($review->fresh()->history)->contains(fn ($e) => str_starts_with($e['event'], 'Consenso dos triadores sobreposto')));
    }

    public function test_pilot_is_random_sample_and_order_differs_per_screener(): void
    {
        [$review, $a, $b] = $this->startedWithTwo(40, 10);
        $pilotSeqs = $review->screenableRecords()->pluck('seq')->sort()->values()->all();
        $this->assertCount(10, $pilotSeqs);
        $this->assertNotSame(range(1, 10), $pilotSeqs, 'Piloto não pode ser simplesmente os 10 primeiros importados.');

        $orderA = $this->service()->orderFor($a);
        $orderB = $this->service()->orderFor($b);
        $this->assertEqualsCanonicalizing($orderA, $orderB);
        $this->assertNotSame($orderA, $orderB);
        $this->assertSame($orderA, $this->service()->orderFor($a->fresh()), 'A ordem de cada triador é estável.');
    }

    public function test_low_pilot_kappa_requires_justification(): void
    {
        [$review, $a, $b] = $this->startedWithTwo(6, 4);
        foreach ($review->screenableRecords()->get() as $i => $r) {
            $this->service()->decide($a, $r, $i % 2 ? 'include' : 'exclude', $i % 2 ? null : 'População errada');
            $this->service()->decide($b, $r, $i % 2 ? 'exclude' : 'include', $i % 2 ? 'População errada' : null);
        }
        $this->expectExceptionMessage('0,60');
        $this->service()->releasePilot($review->fresh());
    }

    public function test_new_calibration_round_adds_a_new_random_sample_and_kappa_counts_only_it(): void
    {
        [$review, $a, $b] = $this->startedWithTwo(12, 4);
        $first = $review->screenableRecords()->pluck('id')->all();
        // Rodada 1 em total discordância.
        foreach ($review->screenableRecords()->get() as $i => $r) {
            $this->service()->decide($a, $r, $i % 2 ? 'include' : 'exclude', $i % 2 ? null : 'População errada');
            $this->service()->decide($b, $r, $i % 2 ? 'exclude' : 'include', $i % 2 ? 'População errada' : null);
        }
        $this->assertLessThan(0.60, $this->service()->pilotKappa($review->fresh()));

        $added = $this->service()->newPilotRound($review->fresh(), 10);
        $review = $review->fresh();
        $this->assertSame(8, $added); // só sobravam 8 fora do piloto
        $this->assertSame(2, $review->pilotRound());
        $this->assertSame(12, $review->pilot_size);
        $this->assertSame(4, $review->pilotRoundStart());
        $this->assertCount(1, $review->pilot_rounds);
        $this->assertSame(4, $review->pilot_rounds[0]['n']);
        $new = $review->currentPilotRecordIds();
        $this->assertCount(8, $new);
        $this->assertEmpty(array_intersect($first, $new));
        $this->assertTrue($review->inPilot());
        Mail::assertSent(\App\Mail\AppNotificationMail::class, fn ($m) => $m->title === 'Nova rodada de calibração do piloto');

        // Rodada 2 em total concordância: o kappa do piloto passa a ser só o da amostra nova.
        foreach ($review->records()->whereIn('id', $new)->get() as $i => $r) {
            $d = $i % 2 ? 'include' : 'exclude';
            $this->service()->decide($a->fresh(), $r, $d, $d === 'exclude' ? 'População errada' : null);
            $this->service()->decide($b->fresh(), $r, $d, $d === 'exclude' ? 'População errada' : null);
        }
        $review = $review->fresh();
        $this->assertSame(1.0, $this->service()->pilotKappa($review));
        // O quadro da página mostra o mesmo kappa do botão "Liberar" (amostra nova, n = 8), e não o acumulado.
        $card = (string) \App\Filament\Resources\SystematicReviewResource::reliabilityHtml($review);
        $this->assertStringContainsString('rodada 2 de calibração', $card);
        $this->assertStringContainsString('n = 8', $card);
        $this->assertStringContainsString('κ (3 categorias): 1,00', $card);
        $this->assertStringContainsString('Rodada 1 (já discutida)', $card);
        $this->assertTrue(collect($review->history)->contains(fn ($e) => $e['event'] === 'Piloto concluído pelos triadores (rodada 2)'));
        $this->assertStringContainsString('successive random samples', $this->service()->methodsText($review));

        // Libera sem justificativa (a rodada atual passou de 0,60); as decisões da rodada 1 continuam.
        $this->service()->releasePilot($review);
        $this->assertSame(12, $review->fresh()->screenableRecords()->count());
        $this->assertSame(['done' => 12, 'total' => 12], $this->service()->progress($a->fresh()));
        $this->assertCount(2, $review->fresh()->pilot_rounds);
        $this->assertEquals(1.0, $review->fresh()->pilot_rounds[1]['kappa']);

        $cardAfter = (string) \App\Filament\Resources\SystematicReviewResource::reliabilityHtml($review->fresh());
        $this->assertStringContainsString('Calibração do Piloto (concluída)', $cardAfter);
        $this->assertStringContainsString('Rodada 1', $cardAfter);
        $this->assertStringContainsString('Rodada 2', $cardAfter);
        $this->assertStringContainsString('Aprovada para liberação', $cardAfter);
    }

    public function test_new_calibration_round_waits_for_both_screeners(): void
    {
        [$review, $a] = $this->startedWithTwo(8, 3);
        $this->service()->decide($a, $review->screenableRecords()->first(), 'include');
        $this->expectExceptionMessage('terminar a rodada atual');
        $this->service()->newPilotRound($review->fresh(), 10);
    }

    public function test_prevalence_paradox_rule_releases_with_high_agreement_and_pabak(): void
    {
        // 20 no piloto: 19 excluídos pelos dois e 1 divergência. κ binário = 0 (paradoxo), concordância 95%, PABAK 0,90.
        [$review, $a, $b] = $this->startedWithTwo(20, 20);
        foreach ($review->screenableRecords()->get() as $i => $r) {
            $this->service()->decide($a, $r, $i === 0 ? 'include' : 'exclude', $i === 0 ? null : 'População errada');
            $this->service()->decide($b, $r, 'exclude', 'População errada');
        }
        $review = $review->fresh();
        $this->assertLessThan(0.60, (float) $this->service()->pilotEffectiveKappa($review));
        $rule = $this->service()->pilotPrevalenceRule($review);
        $this->assertTrue($rule['applies']);
        $this->assertTrue($rule['passes']);
        $this->assertEqualsWithDelta(0.90, $rule['pabak'], 1e-9);
        $this->assertStringContainsString('PABAK = 0,90', $this->service()->pilotReliability($review)['summary']);

        $this->service()->releasePilot($review); // sem justificativa
        $this->assertNotNull($review->fresh()->pilot_released_at);
        $this->assertTrue(collect($review->fresh()->history)->contains(fn ($e) => str_contains((string) ($e['details'] ?? ''), 'prevalência baixa')));
    }

    public function test_prevalence_rule_does_not_rescue_real_disagreement(): void
    {
        // 20 no piloto, 6 divergências em 20: concordância 70%, PABAK 0,40 → precisa de justificativa.
        [$review, $a, $b] = $this->startedWithTwo(20, 20);
        foreach ($review->screenableRecords()->get() as $i => $r) {
            $this->service()->decide($a, $r, $i < 6 ? 'maybe' : 'exclude', $i < 6 ? null : 'População errada');
            $this->service()->decide($b, $r, 'exclude', 'População errada');
        }
        $this->assertFalse($this->service()->pilotPassesGate($review->fresh()));
        $this->expectExceptionMessage('0,60');
        $this->service()->releasePilot($review->fresh());
    }

    public function test_calibration_stops_after_three_rounds(): void
    {
        [$review, $a, $b] = $this->startedWithTwo(40, 10);
        $disagree = function () use (&$review, $a, $b) {
            foreach ($review->records()->whereIn('id', $review->currentPilotRecordIds())->get() as $i => $r) {
                $this->service()->decide($a->fresh(), $r, $i % 2 ? 'include' : 'exclude', $i % 2 ? null : 'População errada');
                $this->service()->decide($b->fresh(), $r, $i % 2 ? 'exclude' : 'include', $i % 2 ? 'População errada' : null);
            }
        };
        $disagree();
        $this->service()->newPilotRound($review->fresh(), 10);
        $review = $review->fresh();
        $disagree();
        $this->service()->newPilotRound($review->fresh(), 10);
        $review = $review->fresh();
        $this->assertSame(3, $review->pilotRound());
        $disagree();
        $this->expectExceptionMessage('no máximo 3 rodadas');
        $this->service()->newPilotRound($review->fresh(), 10);
    }

    public function test_small_project_gets_proportional_pilot(): void
    {
        [$review] = $this->startedWithTwo(100, 50);
        $this->assertSame(20, $review->pilot_size); // 20% de 100
    }

    public function test_tiny_project_pilot_is_never_larger_than_the_records(): void
    {
        [$tiny] = $this->startedWithTwo(12, 50);
        $this->assertSame(12, $tiny->pilot_size);
    }

    public function test_selection_codes_cover_the_four_waves(): void
    {
        $this->assertSame('AQU-01', SystematicReview::selectionCodeFor(21));
        $this->assertSame('BEM-10', SystematicReview::selectionCodeFor(40));
        $this->assertSame('P23', SystematicReview::protocolCodeFor('AQU-03'));
        $this->assertSame('P15', SystematicReview::protocolCodeFor('pat-5'));
        $this->assertNull(SystematicReview::protocolCodeFor('AQU-11'));
        $this->assertLessThan(SystematicReview::selectionSortKey('AQU-01'), SystematicReview::selectionSortKey('PAT-10'));

        $review = $this->project(['protocol_code' => 'P31', 'exclusion_reasons' => ['E1 — Population or host not eligible'], 'exclusion_reasons_pt' => ['E1' => 'Não é psitacídeo de cativeiro']]);
        $this->assertSame('BEM-01', $review->selectionCode());
        $this->assertSame('Não é psitacídeo de cativeiro', $review->exclusionReasonPt('E1 — Population or host not eligible'));
        $this->assertNull($review->exclusionReasonPt('E10 — Study design'));
    }

    public function test_project_message_goes_whole_in_the_email(): void
    {
        [$review] = $this->startedWithTwo(4, 0);
        $body = str_repeat('Critério explicado. ', 120) . 'FIM DA MENSAGEM';
        $this->service()->postMessage($review, $this->admin, $body);
        Mail::assertSent(\App\Mail\AppNotificationMail::class, fn ($m) => str_contains($m->messageContent, 'FIM DA MENSAGEM'));
    }

    public function test_generic_invite_cannot_hijack_screener_before_email_validation(): void
    {
        $review = $this->project();
        $s = $this->service()->addScreener($review, ['name' => 'Vitima', 'email' => 'vitima@x.com']);
        $generic = InviteToken::create(['category' => 'external', 'expires_at' => now()->addDay()]);
        $this->post('/invite/register', ['token' => $generic->token, 'name' => 'Atacante', 'email' => 'vitima@x.com', 'password' => 'segredo123',
            'password_confirmation' => 'segredo123', 'cpf' => '1', 'academic_level' => 'Graduação', 'phone' => '1', 'terms_accepted' => '1']);
        $this->assertNull($s->fresh()->user_id);
        // Sem convite nominal: não é liberado direto, vai para a aprovação do professor.
        $this->assertEmpty(User::where('email', 'vitima@x.com')->first()->metadata['auto_approve'] ?? null);
    }

    public function test_pace_flags_too_fast_screening_and_type_pipelines(): void
    {
        [$review, $a] = $this->startedWithTwo(15);
        $t = now();
        foreach ($review->records()->get() as $i => $r) {
            \Illuminate\Support\Carbon::setTestNow($t->copy()->addSeconds($i * 2));
            $this->service()->decide($a, $r, 'include');
        }
        \Illuminate\Support\Carbon::setTestNow();
        $pace = $this->service()->pace($a);
        $this->assertSame(2.0, $pace['median']);
        $this->assertTrue($pace['fast']);

        $review->update(['review_type' => 'scoping']);
        $this->assertArrayNotHasKey('rob', $review->pipeline());
        $this->assertSame('PRISMA-ScR', $review->reportingGuideline());
        $this->assertStringContainsString('PRISMA-ScR', $this->service()->methodsText($review));
        $review->update(['review_type' => 'diagnostic']);
        $this->assertStringContainsString('QUADAS-2', $review->pipeline()['rob']);
        $this->assertNotEmpty(collect($review->journalWarnings())->first(fn ($w) => str_contains($w, 'mínimo 2')));
    }

    public function test_screening_checklist_suggests_from_the_students_own_answers_and_is_kept_with_the_vote(): void
    {
        [$review, $a] = $this->startedWithTwo(4, 0);
        $review->update([
            'protocol_code' => 'P01',
            'exclusion_reasons' => ['E1 — Experimental pain model', 'E10 — No eligible outcome', 'E7 — Population not eligible'],
        ]);

        // O comando carrega pelo código do protocolo e avisa código sem motivo correspondente.
        $file = tempnam(sys_get_temp_dir(), 'ck');
        file_put_contents($file, json_encode(['_info' => 'x', 'P01' => [
            ['q' => 'São coelhos?', 'h' => 'Qualquer raça.', 'code' => 'E7'],
            ['q' => 'Passaram por cirurgia?', 'code' => 'E1'],
            ['q' => 'Mede dor?', 'code' => 'E99'],
        ]]));
        $this->artisan('metanalises:checklists', ['--file' => $file])->expectsOutputToContain('código sem motivo: E99')->assertSuccessful();
        $review->refresh();
        $items = $review->screeningChecklistFor($this->service()->reasonsFor($review, SystematicReview::STAGE_TA));
        $this->assertSame('E7 — Population not eligible', $items[0]['reason']);
        $this->assertSame('E1 — Experimental pain model', $items[1]['reason'], 'E1 não pode casar com E10');
        $this->assertNull($items[2]['reason']);
        // O arquivo real do programa cobre os 40 projetos (levas 1 a 3).
        $real = json_decode(file_get_contents(database_path('data/screening_checklists.json')), true);
        $this->assertCount(40, array_filter(array_keys($real), fn ($k) => !str_starts_with($k, '_')));

        $order = $this->service()->orderFor($a);
        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $this->actingAs($a->user);
        $page = Livewire::test(TriagemCega::class)
            ->assertSee('Checklist do protocolo')->assertSee('São coelhos?')->assertSee('Não dá para saber')
            ->assertSee('Ver em português')
            // Respostas de outro trabalho (toque duplo) não são gravadas neste.
            ->set('checklist', ['0' => 'nao'])->set('checklistRecordId', $order[1])
            ->call('decide', 'include', null, $order[0]);
        $this->assertNull($a->decisions()->where('systematic_review_record_id', $order[0])->value('checklist'));

        // Resposta inválida e pergunta inexistente são descartadas; o motivo continua sendo escolha do aluno.
        $page->set('checklist', ['0' => 'sim', '1' => 'nao', '5' => 'sim', '2' => 'talvez'])->set('checklistRecordId', $order[1])
            ->call('decide', 'exclude', 'E1 — Experimental pain model', $order[1])
            ->assertSet('checklist', [])->assertSet('checklistRecordId', null);
        $vote = $a->decisions()->where('systematic_review_record_id', $order[1])->first();
        $this->assertSame(['0' => 'sim', '1' => 'nao'], $vote->checklist);

        // Trocar só a decisão mantém o checklist gravado.
        $this->service()->decide($a->fresh(), $review->records()->find($order[1]), 'maybe');
        $this->assertSame(['0' => 'sim', '1' => 'nao'], $vote->fresh()->checklist);

        // E-mail da triagem real deixa claro que vale.
        $this->assertStringContainsString('ESTA É A TRIAGEM OFICIAL, NÃO É TREINO', $this->service()->assignmentText($a->fresh()));
    }
}
