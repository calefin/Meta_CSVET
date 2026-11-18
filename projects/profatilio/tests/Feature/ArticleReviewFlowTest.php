<?php

namespace Tests\Feature;

use App\Filament\Resources\ArticleReviewResource\Pages\ViewArticleReview;
use App\Models\ArticleReview;
use App\Models\ArticleReviewer;
use App\Models\User;
use App\Services\ArticleReviewService;
use App\Services\DocxInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ArticleReviewFlowTest extends TestCase
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

    public static function docx(array $paragraphs, int $insertions = 0): string
    {
        $body = '';
        foreach ($paragraphs as $p) {
            $body .= '<w:p><w:r><w:t>' . htmlspecialchars($p, ENT_XML1) . '</w:t></w:r></w:p>';
        }
        for ($i = 0; $i < $insertions; $i++) {
            $body .= '<w:p><w:ins w:id="' . $i . '" w:author="X"><w:r><w:t>novo ' . $i . '</w:t></w:r></w:ins></w:p>';
        }

        $tmp = tempnam(sys_get_temp_dir(), 'tdx');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="w"><w:body>' . $body . '</w:body></w:document>');
        $zip->close();
        $content = file_get_contents($tmp);
        @unlink($tmp);

        return $content;
    }

    protected function baseText(): array
    {
        return array_map(fn ($i) => "Parágrafo número {$i} do manuscrito sobre imunologia de répteis com várias palavras para comparação de texto e semelhança entre versões.", range(1, 20));
    }

    protected function makeReview(array $people, array $attrs = []): ArticleReview
    {
        Storage::disk('local')->put('article_reviews/originals/orig.docx', self::docx($this->baseText()));

        $review = ArticleReview::create($attrs + [
            'title' => 'Imunologia de Répteis em Cativeiro',
            'work_type' => 'article',
            'original_file_path' => 'article_reviews/originals/orig.docx',
            'original_filename' => 'orig.docx',
            'default_review_days' => 7,
            'status' => ArticleReview::STATUS_DRAFT,
        ]);

        foreach ($people as $i => $p) {
            $review->reviewers()->create($p + ['position' => $i + 1, 'status' => ArticleReviewer::STATUS_WAITING]);
        }

        return $review->fresh();
    }

    protected function profilePayload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Ana Maria Souza',
            'citation_name' => 'SOUZA, A. M.',
            'email' => 'ana@example.com',
            'phone' => '(11) 98765-4321',
            'institution' => 'Universidade X',
            'department' => 'Depto. de Clínica',
            'city' => 'São Paulo',
            'state' => 'SP',
            'country' => 'Brasil',
            'orcid' => 'https://orcid.org/0000-0002-1825-0097',
            'roles' => ['investigation', 'writing_review'],
            'checklist' => array_keys(ArticleReviewer::CHECKLIST),
            'accept_participation' => '1',
            'aware_content' => '1',
        ];
    }

    public function test_orcid_checksum_validation(): void
    {
        $this->assertSame('0000-0002-1825-0097', ArticleReviewer::normalizeOrcid('https://orcid.org/0000-0002-1825-0097'));
        $this->assertSame('0000-0001-5109-3700', ArticleReviewer::normalizeOrcid('0000000151093700'));
        $this->assertNull(ArticleReviewer::normalizeOrcid('0000-0002-1825-0098'));
        $this->assertNull(ArticleReviewer::normalizeOrcid('123'));
    }

    public function test_full_sequential_flow_with_profile_changes_and_closing(): void
    {
        $review = $this->makeReview([
            ['name' => 'Ana Maria Souza', 'email' => 'ana@example.com'],
            ['name' => 'Bruno Lima', 'email' => null, 'phone' => '11999998888'],
            ['name' => 'Carla Dias', 'email' => 'carla@example.com'],
        ]);
        $service = app(ArticleReviewService::class);

        $service->start($review);
        [$ana, $bruno, $carla] = $review->reviewers()->get()->all();
        $this->assertSame(ArticleReviewer::STATUS_IN_REVIEW, $ana->status);
        $this->assertNotNull($ana->token_hash);
        app(\Illuminate\Support\Defer\DeferredCallbackCollection::class)->invoke();
        Mail::assertSent(\App\Mail\AppNotificationMail::class, fn ($m) => $m->hasTo('carla@example.com') && $m->title === 'Você está no processo de revisão'
            && str_contains($m->messageContent, 'Agora está com: Ana Maria Souza'));
        // Professor recebe o despacho, na caixa de sempre, com a fila.
        Mail::assertSent(\App\Mail\AppNotificationMail::class, fn ($m) => $m->hasTo(ArticleReviewService::PROFESSOR_EMAIL)
            && str_contains($m->title, 'Despacho enviado: agora está com Ana Maria Souza')
            && str_contains($m->messageContent, 'Ordem de revisão:'));

        // Mesmo link pode ser copiado de novo sem invalidar.
        $url = $ana->accessUrl();
        $this->assertSame($url, $ana->fresh()->accessUrl());

        // Página: vez da pessoa, aviso de mudanças, fila.
        $this->get($url)->assertOk()
            ->assertSee('É a sua vez!')
            ->assertSee('pessoas que hoje não aparecem na lista podem ser incluídas')
            ->assertSee('Bruno Lima');

        // Carla (na fila) vê com quem está e não consegue baixar.
        $carlaUrl = $carla->fresh()->accessUrl();
        $this->get($carlaUrl)->assertOk()->assertSee('Agora está com:')->assertDontSee('Baixar arquivo');
        $this->get($carlaUrl . '/arquivo')->assertForbidden();

        // Envio bloqueado sem ficha.
        $file = UploadedFile::fake()->createWithContent('rev.docx', self::docx(array_merge($this->baseText(), ['Parágrafo novo inserido pela Ana.']), 2));
        $this->post($url, ['document' => $file, 'accept_participation' => '1'])->assertRedirect();
        $this->assertSame(ArticleReviewer::STATUS_IN_REVIEW, $ana->fresh()->status);

        // ORCID inválido.
        $this->post($url . '/ficha', $this->profilePayload(['orcid' => '0000-0002-1825-0098']))->assertSessionHasErrors('orcid');
        // Checklist incompleto.
        $this->post($url . '/ficha', $this->profilePayload(['checklist' => ['name']]))->assertSessionHasErrors('checklist');
        // Ficha válida.
        $this->post($url . '/ficha', $this->profilePayload())->assertSessionHasNoErrors();
        $ana->refresh();
        $this->assertTrue($ana->isProfileComplete());
        $this->assertSame('0000-0002-1825-0097', $ana->orcid);
        $this->assertSame('Investigação; Escrita – revisão e edição', $ana->rolesLabel());

        // Download e envio com documento complementar.
        $this->get($url . '/arquivo')->assertOk();
        $extra = UploadedFile::fake()->createWithContent('figura.png', "\x89PNG\r\n\x1a\n" . str_repeat('x', 50));
        $this->post($url, [
            'document' => UploadedFile::fake()->createWithContent('rev.docx', self::docx(array_merge($this->baseText(), ['Parágrafo novo inserido pela Ana.']), 2)),
            'extras' => [$extra],
            'comments' => 'Ajustei a discussão.',
            'accept_participation' => '1',
        ])->assertRedirect()->assertSessionMissing('needs_confirm')->assertSessionHasNoErrors();

        $ana->refresh();
        $this->assertSame(ArticleReviewer::STATUS_DONE, $ana->status);
        $this->assertSame(2, $ana->version_number);
        $this->assertStringStartsWith('Imunologia_de_Repteis_em_Cativeiro_v2_rev-AS', $ana->returned_filename);
        $changes = $ana->forensic['changes'];
        $this->assertGreaterThan(0, $changes['paragraphs_added']);
        $this->assertSame(2, $changes['tracked_insertions']);
        $this->assertNotContains('untracked', $changes['alerts']);
        $this->assertSame(1, $review->files()->count());

        // Andamento para os demais sai depois da resposta (defer), mas sai.
        Mail::assertSent(\App\Mail\AppNotificationMail::class, fn ($m) => $m->hasTo('carla@example.com') && $m->title === 'Andamento da revisão'
            && str_contains($m->messageContent, 'Agora está com: Bruno Lima'));
        Mail::assertSent(\App\Mail\AppNotificationMail::class, fn ($m) => $m->hasTo(ArticleReviewService::PROFESSOR_EMAIL)
            && $m->title === 'Revisão devolvida por Ana Maria Souza — agora com Bruno Lima'
            && str_contains($m->messageContent, 'NÃO tem e-mail'));
        // Quem acabou de devolver não recebe o "andamento" (já recebeu o comprovante).
        Mail::assertNotSent(\App\Mail\AppNotificationMail::class, fn ($m) => $m->hasTo('ana@example.com') && $m->title === 'Andamento da revisão');

        // Bruno (sem e-mail) assumiu a vez.
        $bruno->refresh();
        $this->assertSame(ArticleReviewer::STATUS_IN_REVIEW, $bruno->status);

        // Quem já entregou não pode ser removido.
        $this->expectRuntime(fn () => $service->removeParticipant($ana->fresh()));

        // Bruno demorou: a vez passa para alguém novo; Bruno volta para o fim.
        $next = $service->swapCurrent($review->fresh(), null, ['name' => 'diego ramos', 'email' => 'diego@example.com'], 'requeue', 'Demorando');
        $this->assertSame('Diego Ramos', $next->name);
        $this->assertSame(ArticleReviewer::STATUS_IN_REVIEW, $next->fresh()->status);
        $order = $review->fresh()->reviewers()->where('status', ArticleReviewer::STATUS_WAITING)->pluck('name')->all();
        $this->assertSame(['Carla Dias', 'Bruno Lima'], $order);

        // Reordenar: Bruno antes de Carla.
        $service->reorderWaiting($review->fresh(), [$bruno->id, $carla->id]);
        $this->assertSame(['Bruno Lima', 'Carla Dias'], $review->fresh()->reviewers()->where('status', ArticleReviewer::STATUS_WAITING)->pluck('name')->all());

        // Remover Carla (ainda aguardando) — fica no histórico.
        $service->removeParticipant($carla->fresh(), 'Desistiu');
        $this->assertSame(ArticleReviewer::STATUS_REMOVED, $carla->fresh()->status);
        $this->get($carlaUrl)->assertNotFound();

        // Diego envia arquivo idêntico: precisa confirmar.
        $diego = $next->fresh();
        $diegoUrl = $diego->accessUrl();
        $this->post($diegoUrl . '/ficha', $this->profilePayload(['name' => 'Diego Ramos', 'email' => 'diego@example.com', 'orcid' => '', 'no_orcid' => '1']))->assertSessionHasNoErrors();
        $current = Storage::disk('local')->get($review->fresh()->currentVersion()['path']);
        $this->post($diegoUrl, ['document' => UploadedFile::fake()->createWithContent('d.docx', $current), 'accept_participation' => '1'])
            ->assertSessionHas('needs_confirm');
        $this->assertSame(ArticleReviewer::STATUS_IN_REVIEW, $diego->fresh()->status);
        $this->post($diegoUrl, ['document' => UploadedFile::fake()->createWithContent('d.docx', $current), 'accept_participation' => '1', 'confirm_file' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertContains('identical', $diego->fresh()->forensic['changes']['alerts']);

        // Restaurar a v1 como base.
        $restored = $service->restoreVersion($review->fresh(), 'original', 'Erro na v3');
        $this->assertSame(4, $restored['version']);
        $this->assertSame(4, $review->fresh()->currentVersion()['version']);

        // Bruno é o último: completa o fluxo.
        $bruno->refresh();
        $this->assertSame(ArticleReviewer::STATUS_IN_REVIEW, $bruno->status);
        $service->skip($bruno);
        $review->refresh();
        $this->assertSame(ArticleReview::STATUS_REVIEWS_DONE, $review->status);

        // Bloco de autores respeita a ordem de autoria.
        $service->updateParticipant($diego->fresh(), ['author_order' => 1]);
        $service->updateParticipant($ana->fresh(), ['author_order' => 2]);
        $block = $service->authorsBlock($review->fresh());
        $this->assertTrue(strpos($block, 'Diego Ramos') < strpos($block, 'Ana Maria Souza'));
        $this->assertStringContainsString('https://orcid.org/0000-0002-1825-0097', $block);
        $this->assertStringContainsString('CONTRIBUIÇÕES (CRediT)', $block);

        // Fechamento.
        $service->markSubmitted($review->fresh(), ['journal' => 'Vet Immunology', 'submitted_at' => '2026-10-08']);
        $review->refresh();
        $this->assertSame(ArticleReview::STATUS_SUBMITTED, $review->status);
        $this->get($url)->assertOk()->assertSee('Processo encerrado');

        // Histórico público não expõe IP.
        $public = collect($review->history)->where('public', true);
        $this->assertNotEmpty($public);
        $this->get($url)->assertDontSee('IP ' . '127.0.0.1');

        // Dossiê PDF e downloads do admin.
        $this->actingAs($this->admin);
        $this->get(route('article-review.admin.dossier', ['articleReview' => $review]))->assertOk();
        $this->get(route('article-review.admin.download', ['articleReview' => $review, 'version' => 'restore-0']))->assertOk();
        $this->get(route('article-review.admin.download', ['articleReview' => $review, 'version' => 'file-' . $review->files()->first()->id]))->assertOk();
    }

    public function test_signup_link_claims_name_or_adds_new_participant(): void
    {
        $review = $this->makeReview([
            ['name' => 'Ana Maria Souza', 'email' => 'ana@example.com'],
            ['name' => 'Bruno Lima', 'email' => null],
        ]);

        $url = $review->signupUrl();
        $this->get($url)->assertNotFound();

        $review->update(['signup_enabled' => true]);
        $this->get($url)->assertOk()->assertSee('Bruno Lima')->assertDontSee('Ana Maria Souza</label>', false);

        $bruno = $review->reviewers()->where('name', 'Bruno Lima')->first();
        $this->post($url, ['claim_id' => $bruno->id, 'email' => 'bruno@example.com', 'phone' => '11988887777', 'agree' => '1'])
            ->assertRedirect();
        $this->assertSame('bruno@example.com', $bruno->fresh()->email);

        // E-mail repetido é recusado.
        $this->post($url, ['name' => 'Outro', 'email' => 'ana@example.com', 'phone' => '11988887777', 'agree' => '1'])
            ->assertSessionHasErrors('email');

        // Pessoa nova entra no fim da fila.
        $this->post($url, ['name' => 'eva costa', 'email' => 'eva@example.com', 'phone' => '11988887777', 'agree' => '1'])
            ->assertRedirect();
        $eva = $review->reviewers()->where('email', 'eva@example.com')->first();
        $this->assertTrue($eva->self_registered);
        $this->assertSame('Eva Costa', $eva->name);
        $this->assertSame($eva->id, $review->reviewers()->get()->last()->id);
    }

    public function test_require_login_gate_account_and_reviewer_area(): void
    {
        $review = $this->makeReview([['name' => 'Ana Maria Souza', 'email' => 'ana@example.com']], ['require_login' => true]);
        app(ArticleReviewService::class)->start($review);
        $ana = $review->reviewers()->first();
        $url = $ana->accessUrl();

        $this->get($url)->assertOk()->assertSee('Acesso protegido por senha')->assertDontSee('Sua ficha de participante');
        $this->get($url . '/arquivo')->assertForbidden();

        $this->post($url . '/acesso', ['mode' => 'create', 'password' => 'segredo123', 'password_confirmation' => 'segredo123'])
            ->assertSessionHasNoErrors();
        $user = User::where('email', 'ana@example.com')->first();
        $this->assertSame('reviewer', $user->role);
        $this->assertSame($user->id, $ana->fresh()->user_id);
        $this->assertAuthenticatedAs($user);

        $this->get($url)->assertOk()->assertSee('Sua ficha de participante');

        // Área do Revisor + rota por conta.
        $this->get('/revisor/processos-de-revisao')->assertOk()->assertSee('Imunologia de Répteis');
        $this->get(route('article-review.account.show', ['articleReviewer' => $ana->id]))->assertOk()->assertSee('É a sua vez!');

        // Reviewer não entra no admin nem no portal.
        $this->get('/admin')->assertForbidden();

        // Outra pessoa não vê o processo da Ana.
        $other = User::factory()->create(['role' => 'reviewer']);
        $this->actingAs($other)->get(route('article-review.account.show', ['articleReviewer' => $ana->id]))->assertNotFound();

        // Sem login, rota por conta manda para o login do revisor.
        auth()->logout();
        $this->get(route('article-review.account.show', ['articleReviewer' => $ana->id]))->assertRedirect('/revisor/login');

        // Login pela página do link.
        $this->post($url . '/acesso', ['mode' => 'login', 'password' => 'errada'])->assertSessionHasErrors('password');
        $this->post($url . '/acesso', ['mode' => 'login', 'password' => 'segredo123'])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_untracked_and_different_document_detection(): void
    {
        $base = $this->baseText();
        $old = DocxInspector::analyze(self::docx($base), 'docx');

        $edited = $base;
        $edited[3] = 'Texto alterado sem controle de alterações nenhum aqui.';
        $c = DocxInspector::compare($old, DocxInspector::analyze(self::docx($edited), 'docx'));
        $this->assertContains('untracked', $c['alerts']);
        $this->assertSame(1, $c['paragraphs_added']);
        $this->assertSame(1, $c['paragraphs_removed']);

        $other = array_map(fn ($i) => "Capítulo {$i}: anatomia comparada de aves silvestres e manejo hospitalar.", range(1, 30));
        $c2 = DocxInspector::compare($old, DocxInspector::analyze(self::docx($other), 'docx'));
        $this->assertContains('different_document', $c2['alerts']);
    }

    public function test_admin_view_page_renders_and_starts(): void
    {
        $review = $this->makeReview([
            ['name' => 'Ana Maria Souza', 'email' => 'ana@example.com', 'phone' => '11987654321'],
            ['name' => 'Bruno Lima'],
        ]);
        $this->actingAs($this->admin);

        $this->get(\App\Filament\Resources\ArticleReviewResource::getUrl('view', ['record' => $review]))->assertOk()->assertSee('Rascunho');

        Livewire::test(ViewArticleReview::class, ['record' => $review->getRouteKey()])
            ->callAction('iniciar')
            ->assertHasNoActionErrors();

        $this->assertSame(ArticleReview::STATUS_IN_REVIEW, $review->fresh()->status);

        $this->get(\App\Filament\Resources\ArticleReviewResource::getUrl('view', ['record' => $review]))
            ->assertOk()->assertSee('Na vez: Ana Maria Souza');

        Livewire::test(ViewArticleReview::class, ['record' => $review->getRouteKey()])
            ->callAction('trocarVez', ['next' => (string) $review->reviewers()->where('name', 'Bruno Lima')->value('id'), 'fate' => 'requeue'])
            ->assertHasNoActionErrors();
        $this->assertSame(ArticleReviewer::STATUS_IN_REVIEW, $review->reviewers()->where('name', 'Bruno Lima')->first()->status);

        Livewire::test(ViewArticleReview::class, ['record' => $review->getRouteKey()])
            ->callAction('autores')
            ->assertHasNoActionErrors();

        Livewire::test(ViewArticleReview::class, ['record' => $review->getRouteKey()])
            ->callAction('linkCadastro', ['enabled' => true, 'rotate' => false])
            ->assertHasNoActionErrors();
        $this->assertTrue($review->fresh()->signup_enabled);

        // Ações por participante dentro da lista (infolist).
        $ana = $review->reviewers()->where('name', 'Ana Maria Souza')->first();
        $i = $review->reviewers()->pluck('id')->search($ana->id);
        Livewire::test(ViewArticleReview::class, ['record' => $review->getRouteKey()])
            ->mountInfolistAction("reviewers.{$i}.copiarLinkAction", 'copiarLink')
            ->assertInfolistActionDataSet(['link' => $ana->fresh()->accessUrl()]);

        Livewire::test(ViewArticleReview::class, ['record' => $review->getRouteKey()])
            ->callInfolistAction("reviewers.{$i}.editarParticipanteAction", 'editarParticipante', [
                'name' => 'Ana Maria Souza', 'email' => 'ana@example.com', 'phone' => '11911112222', 'author_order' => 3,
            ])
            ->assertHasNoInfolistActionErrors();
        $this->assertSame('11911112222', $ana->fresh()->phone);
        $this->assertSame(3, (int) $ana->fresh()->author_order);

        // Ana voltou para a fila (aguardando): pode ser removida.
        Livewire::test(ViewArticleReview::class, ['record' => $review->getRouteKey()])
            ->callInfolistAction("reviewers.{$i}.removerAction", 'remover', ['reason' => 'teste'])
            ->assertHasNoInfolistActionErrors();
        $this->assertSame(ArticleReviewer::STATUS_REMOVED, $ana->fresh()->status);
    }

    public function test_returning_review_notifies_next_in_queue(): void
    {
        $review = $this->makeReview([
            ['name' => 'Ana Maria Souza', 'email' => 'ana@example.com'],
            ['name' => 'Carla Dias', 'email' => 'carla@example.com'],
        ]);
        $service = app(ArticleReviewService::class);
        $service->start($review);
        $ana = $review->reviewers()->where('name', 'Ana Maria Souza')->first();
        $url = $ana->accessUrl();
        $this->post($url . '/ficha', $this->profilePayload())->assertSessionHasNoErrors();

        $this->post($url, [
            'document' => UploadedFile::fake()->createWithContent('rev.docx', self::docx(array_merge($this->baseText(), ['Novo.']), 1)),
            'accept_participation' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame(ArticleReviewer::STATUS_DONE, $ana->fresh()->status);
        $this->assertSame(ArticleReviewer::STATUS_IN_REVIEW, $review->reviewers()->where('name', 'Carla Dias')->value('status'));
        // A próxima recebe o "Sua vez" com o link, na hora (não depende do defer).
        Mail::assertSent(\App\Mail\AppNotificationMail::class, fn ($m) => $m->hasTo('carla@example.com')
            && $m->title === 'Sua vez: revisão de artigo'
            && str_contains($m->messageContent, 'Chegou a sua vez'));
        Mail::assertSent(\App\Mail\AppNotificationMail::class, fn ($m) => $m->hasTo('ana@example.com') && $m->title === 'Revisão recebida — obrigado!');
    }

    public function test_bring_back_ends_round_and_returns_to_professor(): void
    {
        $review = $this->makeReview([
            ['name' => 'Ana Maria Souza', 'email' => 'ana@example.com'],
            ['name' => 'Carla Dias', 'email' => 'carla@example.com'],
        ]);
        app(ArticleReviewService::class)->start($review);
        $this->actingAs($this->admin);

        Livewire::test(ViewArticleReview::class, ['record' => $review->getRouteKey()])
            ->callAction('trazerDeVolta', ['reason' => 'Prazo da revista'])
            ->assertHasNoActionErrors();
        app(\Illuminate\Support\Defer\DeferredCallbackCollection::class)->invoke();

        $review->refresh();
        $this->assertSame(ArticleReview::STATUS_REVIEWS_DONE, $review->status);
        $this->assertNull($review->activeReviewer());
        // Ninguém sai do processo.
        $this->assertSame(2, $review->participants()->count());
        Mail::assertSent(\App\Mail\AppNotificationMail::class, fn ($m) => $m->hasTo('ana@example.com') && $m->title === 'Sua vez foi encerrada'
            && str_contains($m->messageContent, 'Prazo da revista'));
        Mail::assertSent(\App\Mail\AppNotificationMail::class, fn ($m) => $m->hasTo('carla@example.com') && str_contains($m->messageContent, 'voltou para o Prof. Atilio'));

        // Ana não baixa mais o arquivo.
        $this->get($review->reviewers()->where('name', 'Ana Maria Souza')->first()->accessUrl() . '/arquivo')->assertForbidden();

        $this->get(\App\Filament\Resources\ArticleReviewResource::getUrl('view', ['record' => $review]))
            ->assertOk()->assertSee('não revisaram: Ana Maria Souza, Carla Dias');

        // Dá para fechar normalmente depois.
        Livewire::test(ViewArticleReview::class, ['record' => $review->getRouteKey()])
            ->callAction('versaoFinal', ['journal' => 'Revista X', 'submitted_at' => now()->toDateString()])
            ->assertHasNoActionErrors();
        $this->assertSame(ArticleReview::STATUS_SUBMITTED, $review->fresh()->status);
    }

    public function test_supplementary_files_from_modal_and_create_form(): void
    {
        $this->actingAs($this->admin);
        $review = $this->makeReview([['name' => 'Ana Maria Souza']]);

        Livewire::test(ViewArticleReview::class, ['record' => $review->getRouteKey()])
            ->callAction('complementares', [
                'files' => [
                    UploadedFile::fake()->createWithContent('Normas da revista.pdf', '%PDF-1.4 teste'),
                    UploadedFile::fake()->createWithContent('falso.pdf', 'nao e pdf'),
                ],
                'description' => 'Normas',
            ])
            ->assertHasNoActionErrors();
        $this->assertSame(['Normas_da_revista.pdf'], $review->files()->pluck('filename')->all());

        $lw = Livewire::test(\App\Filament\Resources\ArticleReviewResource\Pages\CreateArticleReview::class);
        $key = array_key_first($lw->get('data.reviewers'));
        $lw->fillForm([
            'title' => 'Outro trabalho',
            'work_type' => 'article',
            'default_review_days' => 7,
            'original_file_path' => UploadedFile::fake()->createWithContent('a.docx', self::docx(['texto um', 'texto dois'])),
            'supplementary_files' => [UploadedFile::fake()->createWithContent('figura 1.png', "\x89PNG\r\n\x1a\n" . str_repeat('x', 20))],
        ])
            ->set("data.reviewers.{$key}.name", 'Maria Silva')
            ->call('create')
            ->assertHasNoFormErrors();

        $created = ArticleReview::where('title', 'Outro trabalho')->first();
        $this->assertSame(['figura_1.png'], $created->files()->pluck('filename')->all());
    }

    public function test_create_form_saves_participants(): void
    {
        $this->actingAs($this->admin);
        $file = UploadedFile::fake()->createWithContent('a.docx', self::docx(['texto um', 'texto dois']));
        $lw = Livewire::test(\App\Filament\Resources\ArticleReviewResource\Pages\CreateArticleReview::class);
        $key = array_key_first($lw->get('data.reviewers'));
        $this->assertSame(ArticleReview::DEFAULT_INSTRUCTIONS, $lw->get('data.instructions'));
        $lw->set('data.title', 'Imunologia de Répteis em Cativeiro: revisão');
        $this->assertSame('Imunologia_Repteis_Cativeiro_Revisao', $lw->get('data.short_name'));

        $lw->fillForm(['work_type' => 'book', 'default_review_days' => 7, 'original_file_path' => $file])
            ->set("data.reviewers.{$key}.name", 'Maria Silva')
            ->set("data.reviewers.{$key}.phone", '11987654321')
            ->call('create')
            ->assertHasNoFormErrors();

        $review = ArticleReview::first();
        $this->assertSame(ArticleReview::STATUS_DRAFT, $review->status);
        $this->assertSame(1, $review->participants()->count());
        $this->assertSame('Maria Silva', $review->participants()->first()->name);
    }

    public function test_delete_whole_process_requires_typing_and_removes_files(): void
    {
        $review = $this->makeReview([['name' => 'Ana Maria Souza', 'email' => 'ana@example.com']]);
        app(ArticleReviewService::class)->start($review);
        $url = $review->reviewers()->first()->accessUrl();
        $this->actingAs($this->admin);

        Livewire::test(ViewArticleReview::class, ['record' => $review->getRouteKey()])
            ->callAction('excluir', ['confirm' => 'errado'])
            ->assertHasActionErrors(['confirm']);
        $this->assertNotNull($review->fresh());

        Livewire::test(ViewArticleReview::class, ['record' => $review->getRouteKey()])
            ->callAction('excluir', ['confirm' => 'EXCLUIR', 'notify' => true, 'reason' => 'Arquivo errado'])
            ->assertHasNoActionErrors()
            ->assertRedirect(\App\Filament\Resources\ArticleReviewResource::getUrl('index'));

        $this->assertNull($review->fresh());
        $this->assertSame(0, ArticleReviewer::count());
        Storage::disk('local')->assertMissing('article_reviews/originals/orig.docx');
        Mail::assertSent(\App\Mail\AppNotificationMail::class, fn ($m) => $m->title === 'Processo de revisão cancelado');
        auth()->logout();
        $this->get($url)->assertNotFound();
    }

    public function test_pick_student_fills_participant_and_links_account(): void
    {
        $user = User::factory()->create(['role' => 'student', 'name' => 'Joana Orientanda', 'email' => 'joana@aluno.com']);
        \App\Models\Student::create([
            'user_id' => $user->id, 'institution' => 'Universidade Y', 'academic_level' => 'graduacao',
            'research_topic' => 'x', 'phone' => '11977776666', 'status' => 'active',
        ]);
        $review = $this->makeReview([['name' => 'Ana Maria Souza', 'email' => 'ana@example.com']]);
        $this->actingAs($this->admin);

        Livewire::test(ViewArticleReview::class, ['record' => $review->getRouteKey()])
            ->mountAction('adicionarParticipante')
            ->setActionData(['student_pick' => $user->id])
            ->assertActionDataSet(['name' => 'Joana Orientanda', 'email' => 'joana@aluno.com', 'phone' => '11977776666', 'institution' => 'Universidade Y'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $joana = $review->reviewers()->where('email', 'joana@aluno.com')->first();
        $this->assertNotNull($joana);
        $this->assertSame($user->id, $joana->user_id);
        $this->assertSame('Universidade Y', $joana->institution);

        // Ela vê o processo na Área do Revisor com a senha que já usa...
        $this->actingAs($user)->get('/revisor/processos-de-revisao')->assertOk()->assertSee('Imunologia de Répteis');

    }

    public function test_telegram_notifications_for_professor_students_and_external_participants(): void
    {
        config(['services.telegram.bot_token' => 'TESTTOKEN', 'services.telegram.bot_username' => '@ProfBot']);
        \Illuminate\Support\Facades\Http::fake(['api.telegram.org/*' => \Illuminate\Support\Facades\Http::response(['ok' => true])]);
        $this->admin->forceFill(['telegram_chat_id' => '9000'])->save();

        // Orientanda já vinculada ao Telegram no portal.
        $user = User::factory()->create(['role' => 'student', 'email' => 'joana@aluno.com']);
        \App\Models\Student::create(['user_id' => $user->id, 'institution' => 'U', 'academic_level' => 'g', 'research_topic' => 'x', 'status' => 'active', 'telegram_chat_id' => '7001']);

        $review = $this->makeReview([
            ['name' => 'Joana Orientanda', 'email' => 'joana@aluno.com'],
            ['name' => 'Externo Sem Email', 'email' => null],
        ]);
        $externo = $review->reviewers()->where('name', 'Externo Sem Email')->first();

        // Externo abre a página e vê o botão do bot; vincula pelo /start.
        $page = $this->get($externo->accessUrl())->assertOk()->assertSee('Receber avisos no Telegram')->getContent();
        $this->assertStringContainsString('https://t.me/ProfBot?start=rev_', $page);
        $token = $externo->fresh()->telegram_link_token;
        $this->postJson('/telegram/webhook?token=TESTTOKEN', ['message' => ['chat' => ['id' => 7002], 'text' => "/start rev_{$token}"]])->assertOk();
        $this->assertSame('7002', $externo->fresh()->telegram_chat_id);
        $this->assertTrue($externo->fresh()->isReachable());

        app(ArticleReviewService::class)->start($review->fresh());
        app(\Illuminate\Support\Defer\DeferredCallbackCollection::class)->invoke();

        $sentTo = fn (string $chat, string $needle) => \Illuminate\Support\Facades\Http::assertSent(fn ($req) => str_contains($req->url(), 'api.telegram.org')
            && (string) $req['chat_id'] === $chat && str_contains($req['text'], $needle));

        $sentTo('7001', 'Sua vez');                 // Joana (orientanda) — convite da vez
        $sentTo('7002', 'Agora está com: Joana');   // Externo (só Telegram) — fila
        $sentTo('9000', 'Despacho enviado');        // Professor

        // Externo sem e-mail recebe a vez pelo Telegram.
        $joana = $review->reviewers()->where('name', 'Joana Orientanda')->first();
        app(ArticleReviewService::class)->skip($joana);
        $sentTo('7002', 'Sua vez');
        $sentTo('9000', 'agora está com Externo Sem Email');
    }

    public function test_linked_student_sees_review_in_portal_menu(): void
    {
        $user = User::factory()->create(['role' => 'student', 'email' => 'joana@aluno.com']);
        \App\Models\Student::create(['user_id' => $user->id, 'institution' => 'U', 'academic_level' => 'g', 'research_topic' => 'x',
            'status' => 'active', 'last_semester_declared' => \App\Support\Semester::current()]);
        $review = $this->makeReview([['name' => 'Joana', 'email' => 'joana@aluno.com']]);
        app(ArticleReviewService::class)->start($review);

        $html = $this->actingAs($user)->get('/portal')->assertOk()->getContent();
        $this->assertStringContainsString('processos-de-revisao', $html);
        $this->assertStringContainsString('Sua vez', $html);
        $this->get('/portal/processos-de-revisao')->assertOk()->assertSee('Imunologia de Répteis')->assertSee('Abrir processo');
    }

    public function test_student_without_review_does_not_see_menu_item(): void
    {
        $other = User::factory()->create(['role' => 'student']);
        \App\Models\Student::create(['user_id' => $other->id, 'institution' => 'U', 'academic_level' => 'g', 'research_topic' => 'x',
            'status' => 'active', 'last_semester_declared' => \App\Support\Semester::current()]);
        $html = $this->actingAs($other)->get('/portal')->assertOk()->getContent();
        $this->assertStringNotContainsString('processos-de-revisao', $html);
    }

    protected function expectRuntime(callable $fn): void
    {
        try {
            $fn();
            $this->fail('Esperava RuntimeException');
        } catch (\RuntimeException) {
            $this->addToAssertionCount(1);
        }
    }
}
