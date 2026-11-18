<?php

namespace Tests\Feature;

use App\Filament\Pages\TriagemCega;
use App\Filament\Resources\ResearchApplicationResource\Pages\ListResearchApplications;
use App\Filament\Resources\SystematicReviewResource\Pages\ViewSystematicReview;
use App\Filament\Resources\SystematicReviewResource\RelationManagers\MessagesRelationManager;
use App\Filament\Resources\SystematicReviewResource\RelationManagers\ScreenersRelationManager;
use App\Mail\AppNotificationMail;
use App\Models\ResearchApplication;
use App\Models\ResearchCall;
use App\Models\SrMessage;
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
 * Termo assinado online antes da triagem, papel e contato da dupla, protocolo
 * em PDF só para quem assinou, conversa registrada do projeto e mensagens do
 * professor (dupla, individual e grupo). Isolamento entre alunos.
 */
class SystematicReviewStudentAccessTest extends TestCase
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

    protected function student(string $name, string $email): User
    {
        $user = User::factory()->create(['role' => 'student', 'name' => $name, 'email' => $email]);
        Student::create(['user_id' => $user->id, 'institution' => 'X', 'academic_level' => 'Graduação', 'research_topic' => 'Geral',
            'tcc_status' => 'none', 'phone' => '1', 'status' => 'active', 'category' => 'regular', 'last_semester_declared' => \App\Support\Semester::current()]);

        return $user;
    }

    protected function apply(ResearchCall $call, string $name, string $email): ResearchApplication
    {
        [$app] = app(ResearchRecruitmentService::class)->submit($call, [
            'name' => $name, 'name_confirmation' => $name, 'email' => $email, 'email_confirmation' => $email, 'adhesion' => '1',
            'phone' => '(11) 98765-4321', 'course' => 'Medicina Veterinária', 'institution' => 'Universidade X', 'semester' => 6,
            'purpose' => 'career', 'axes' => [$call->axisNames()[0]], 'projects' => ['PET-01', 'PET-02'], 'weekly_hours' => 5,
            'english_level' => 'translator', 'motivation' => 'Quero aprender revisão sistemática e publicar na área de exóticos.',
            'commitments' => array_fill_keys(array_keys($call->texts('commitments')), '1'),
        ]);

        return $app;
    }

    protected function pipeline(string $id): string
    {
        return json_encode(['schema' => 'calefi-sr-pipeline/1.0', 'protocol_id' => $id, 'version' => '1.1', 'title' => "Secret real title {$id}",
            'label_pt' => 'Teste', 'family' => 'intervention', 'keywords' => ['x'],
            'eligibility' => ['pico' => [['element' => 'Population', 'definition' => 'Y']], 'inclusion' => ['A'], 'exclusion' => ['B']],
            'search' => ['databases' => [['name' => 'PubMed/MEDLINE', 'interface' => 'PubMed', 'query' => 'x']]],
            'screening' => ['stages' => [['id' => 'title_abstract', 'decisions' => ['include', 'exclude', 'maybe']], ['id' => 'full_text', 'decisions' => ['include', 'exclude']]]],
            'extraction' => ['fields' => [['name' => 'country', 'type' => 'text', 'description' => 'Country']], 'outcomes' => [['id' => 'O1', 'name' => 'Pain', 'type' => 'continuous', 'measure' => 'SMD', 'timepoint' => '6 h', 'priority' => 'primary']]],
            'risk_of_bias' => ['tools' => [['code' => 'SYRCLE', 'name' => 'SYRCLE', 'domains' => ['Sequence generation']]]],
            'synthesis' => ['model' => 'RE']]);
    }

    /** Dupla aprovada (Ana lidera PET-01, Beto revisa) e protocolo P01 importado e congelado com PDF. */
    protected function setupPair(): array
    {
        $call = ResearchCall::create(array_merge(ResearchCall::defaults(), ['status' => ResearchCall::STATUS_OPEN]));
        $ana = $this->student('Ana Lider', 'ana@x.com');
        $beto = $this->student('Beto Revisor', 'beto@x.com');
        $svc = app(ResearchRecruitmentService::class);
        $a = $this->apply($call, 'Ana Lider', 'ana@x.com');
        $b = $this->apply($call, 'Beto Revisor', 'beto@x.com');
        $svc->approve($a->fresh(), 'PET-01', 'PET-02');
        $svc->approve($b->fresh(), 'PET-02', 'PET-01');
        $protocol = app(SystematicReviewProtocolService::class);
        [$review] = $protocol->import($this->pipeline('P01'));
        $protocol->freeze($review, '%PDF-1.4 protocolo', 'p.pdf');

        return [$call->fresh(), $review->fresh(), $ana, $beto, $a->fresh(), $b->fresh()];
    }

    public function test_student_signs_terms_online_before_screening_and_sees_role_partner_and_protocol(): void
    {
        [, $review, $ana, $beto] = $this->setupPair();
        $this->assertSame(SystematicReview::STATUS_PROTOCOL, $review->status);
        $lead = $review->screeners()->where('email', 'ana@x.com')->first();

        // Antes de assinar: o projeto aparece no menu, sem o título real.
        $this->actingAs($ana)->get('/portal/triagem-cega')->assertOk();
        $this->assertTrue(TriagemCega::shouldRegisterNavigation());
        Livewire::actingAs($ana)->test(TriagemCega::class)
            ->set('screenerId', null)
            ->call('back')
            ->assertSee('PET-01 · P01')
            ->assertSee('Ler e assinar o termo')
            ->assertDontSee('Secret real title P01');

        // Assina online e passa a ver papel, dupla, protocolo e conversa.
        Livewire::actingAs($ana)->test(TriagemCega::class)
            ->call('choose', $lead->id)
            ->assertSee('Termo de confidencialidade')
            ->set('termsRead', true)
            ->set('signature', 'Ana Lider')
            ->call('sign')
            ->assertSee('em preparação')
            ->assertSee('(PET-01 · P01)')
            ->assertSee('Líder (1º autor)')
            ->assertSee('beto@x.com')
            ->assertSee('Protocolo do projeto (PDF)')
            ->assertSee('Revisão cega: não exponha nada');
        $this->assertTrue($lead->fresh()->hasSignedTerms());

        // PDF do protocolo: só quem assinou.
        $this->actingAs($ana)->get(route('systematic-review.my-protocol', $lead))->assertOk();
        $rev = $review->screeners()->where('email', 'beto@x.com')->first();
        $this->actingAs($beto)->get(route('systematic-review.my-protocol', $rev))->assertForbidden();
        $this->actingAs($beto)->get(route('systematic-review.my-protocol', $lead))->assertForbidden();
        $outsider = $this->student('Caio Fora', 'caio@x.com');
        $this->actingAs($outsider)->get(route('systematic-review.my-protocol', $lead))->assertForbidden();
        Livewire::actingAs($outsider)->test(TriagemCega::class)->assertSee('Nenhuma triagem atribuída')->call('choose', $lead->id)->assertDontSee('Secret real title');
    }

    public function test_project_chat_is_registered_and_only_for_the_pair_and_professor(): void
    {
        [, $review, $ana, $beto] = $this->setupPair();
        $sr = app(SystematicReviewService::class);
        $lead = $review->screeners()->where('email', 'ana@x.com')->first();
        $rev = $review->screeners()->where('email', 'beto@x.com')->first();
        $sr->signTerms($lead, 'Ana Lider', '1.1.1.1', 'UA');

        // Sem termo assinado não participa; quem é de fora também não.
        $this->assertFalse($sr->canChat($review, $beto));
        $outsider = $this->student('Caio Fora', 'caio@x.com');
        try {
            $sr->postMessage($review, $outsider, 'oi');
            $this->fail('Aluno de fora escreveu na conversa.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('não participa', $e->getMessage());
        }

        $sr->signTerms($rev, 'Beto Revisor', '1.1.1.2', 'UA');
        Livewire::actingAs($ana)->test(TriagemCega::class)->call('choose', $lead->id)
            ->set('chatBody', 'Podemos marcar a leitura do protocolo na quinta?')->call('postChat')->assertHasNoErrors();
        $this->assertSame(1, SrMessage::where('systematic_review_id', $review->id)->count());
        Mail::assertSent(AppNotificationMail::class, fn ($m) => $m->hasTo('beto@x.com') && str_contains($m->messageContent, 'Podemos marcar')
            && str_contains($m->messageContent, 'Revisão cega'));
        Mail::assertSent(AppNotificationMail::class, fn ($m) => $m->hasTo('calefi@csvet.com.br') && str_contains($m->title, 'Nova mensagem'));
        Livewire::actingAs($beto)->test(TriagemCega::class)->call('choose', $rev->id)->assertSee('Podemos marcar a leitura');

        // Professor responde pelo painel; fica na conversa.
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->admin);
        Livewire::test(MessagesRelationManager::class, ['ownerRecord' => $review, 'pageClass' => ViewSystematicReview::class])
            ->assertSee('Podemos marcar')
            ->callTableAction('escrever', data: ['body' => 'Quinta às 19h, combinado.'])
            ->assertHasNoTableActionErrors();
        $this->assertSame(SrMessage::ROLE_PROFESSOR, SrMessage::latest('id')->first()->author_role);
        Mail::assertSent(AppNotificationMail::class, fn ($m) => $m->hasTo('ana@x.com') && str_contains($m->messageContent, 'Quinta às 19h'));

        // Mensagem à dupla (aba Triadores) também entra na conversa; individual não.
        Livewire::test(ScreenersRelationManager::class, ['ownerRecord' => $review, 'pageClass' => ViewSystematicReview::class])
            ->callTableAction('mensagemDupla', data: ['title' => 'Reunião de abertura', 'body' => 'Olá, {nome}! Reunião sexta.'])
            ->assertHasNoTableActionErrors()
            ->callTableAction('mensagem', $rev, data: ['title' => 'Só para você', 'body' => 'Olá, {nome}.'])
            ->assertHasNoTableActionErrors();
        Mail::assertSent(AppNotificationMail::class, fn ($m) => $m->hasTo('ana@x.com') && str_contains($m->messageContent, 'Olá, Ana! Reunião sexta.'));
        Mail::assertSent(AppNotificationMail::class, fn ($m) => $m->hasTo('beto@x.com') && $m->title === 'Só para você');
        Mail::assertNotSent(AppNotificationMail::class, fn ($m) => $m->hasTo('ana@x.com') && $m->title === 'Só para você');
        $this->assertSame(3, SrMessage::where('systematic_review_id', $review->id)->count());

        // A mensagem mais nova aparece primeiro, no painel e no portal (pedido do professor em 13/11/2026).
        Livewire::test(MessagesRelationManager::class, ['ownerRecord' => $review, 'pageClass' => ViewSystematicReview::class])
            ->assertSeeInOrder(['sexta.', '19h, combinado', 'Podemos marcar']);
        Livewire::actingAs($beto)->test(TriagemCega::class)->call('choose', $rev->id)
            ->assertSeeInOrder(['sexta.', '19h, combinado', 'Podemos marcar']);
    }

    public function test_student_finds_the_project_chat_from_email_panel_and_menu(): void
    {
        [, $review, $ana, $beto] = $this->setupPair();
        $sr = app(SystematicReviewService::class);
        $lead = $review->screeners()->where('email', 'ana@x.com')->first();
        $rev = $review->screeners()->where('email', 'beto@x.com')->first();
        $sr->signTerms($lead, 'Ana Lider', '1.1.1.1', 'UA');
        $sr->signTerms($rev, 'Beto Revisor', '1.1.1.2', 'UA');

        // O e-mail leva direto à conversa daquele projeto.
        $sr->postMessage($review, $this->admin, 'Mandem o ORCID e o OSF aqui.');
        Mail::assertSent(AppNotificationMail::class, fn ($m) => $m->hasTo('ana@x.com')
            && str_ends_with((string) $m->actionUrl, '/portal/triagem-cega?projeto=' . $lead->id . '#conversa')
            && $m->actionText === 'Abrir a conversa do projeto'
            && str_contains($m->messageContent, 'Responder a este e-mail não chega à conversa'));
        $this->assertSame(1, $lead->fresh()->unreadMessagesCount());

        // Painel e menu mostram que há mensagem nova e o atalho para ela.
        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $this->actingAs($ana);
        $this->assertSame('1', TriagemCega::getNavigationBadge());
        Livewire::test(\App\Filament\Widgets\MetanalisesPortalWidget::class)
            ->assertSee('💬 Conversa')->assertSee('1 nova')->assertSee('Prof. Atilio')
            ->assertSeeHtml('?projeto=' . $lead->id . '#conversa');

        // Abrir o projeto (com um projeto só, a Triagem Cega já abre nele) mostra a conversa aberta,
        // destaca a nova e marca como lida.
        $page = Livewire::test(TriagemCega::class)
            ->assertSeeHtml('id="conversa"')->assertSee('Mandem o ORCID')->assertSee('nova');
        $this->assertSame(0, $lead->fresh()->unreadMessagesCount());
        $this->assertNull(TriagemCega::getNavigationBadge());
        // Na lista de projetos, cada um tem o atalho para a conversa.
        $page->call('back')->assertSee('💬 Conversa')->assertSeeHtml('?projeto=' . $lead->id . '#conversa')->assertDontSee('1 nova');

        // A própria mensagem não conta como nova; a do colega conta para ele.
        $sr->postMessage($review, $ana, 'ORCID: https://orcid.org/0009-0000-0000-0001');
        $this->assertSame(0, $lead->fresh()->unreadMessagesCount());
        $this->assertSame(2, $rev->fresh()->unreadMessagesCount());
        Livewire::actingAs($beto)->test(TriagemCega::class)->set('screenerId', $rev->id)->call('markChatRead');
        $this->assertSame(0, $rev->fresh()->unreadMessagesCount());

        // Sem termo, nada de conversa (nem contador).
        $rev->update(['terms_version' => 'antiga']);
        $sr->postMessage($review, $this->admin, 'Outra.');
        $this->assertSame(0, $rev->fresh()->unreadMessagesCount());
    }

    public function test_journey_bar_shows_the_step_what_to_do_and_the_search_deadline(): void
    {
        [, $review, $ana, $beto] = $this->setupPair();
        $sr = app(SystematicReviewService::class);
        $lead = $review->screeners()->where('email', 'ana@x.com')->first();
        $rev = $review->screeners()->where('email', 'beto@x.com')->first();

        $j = \App\Support\ProgramJourney::for($review->fresh());
        $this->assertSame('preparacao', $j['current']);
        $this->assertSame('Leia e assine o termo de confidencialidade deste projeto.', \App\Support\ProgramJourney::youText($review, $lead, $j['current']));

        $sr->signTerms($lead, 'Ana Lider', '1.1.1.1', 'UA');
        $sr->signTerms($rev, 'Beto Revisor', '1.1.1.2', 'UA');
        $this->assertSame('registro', \App\Support\ProgramJourney::for($review->fresh())['current']);

        // Registrado em 29/10: a busca não pode antes de 01/11 e vai até 12/11 (14 dias).
        $review->update(['registration_id' => '10.17605/OSF.IO/ABCDE', 'registered_at' => '2026-10-29']);
        $review = $review->fresh();
        $j = \App\Support\ProgramJourney::for($review);
        $this->assertSame(['busca', 3], [$j['current'], $j['position']]);
        $this->assertSame(['done', 'done', 'current', 'todo'], array_slice(array_column($j['steps'], 'state'), 0, 4));
        $at = fn (string $d) => \App\Support\ProgramJourney::searchWindow($review, \Illuminate\Support\Carbon::parse($d));
        $this->assertSame('wait', $at('2026-10-30')['level']);
        $this->assertSame('Busca de 01/11 a 12/11 (ainda não pode começar)', $at('2026-10-30')['text']);
        $this->assertSame(['ok', 'warn', 'urgent', 'urgent', 'late'], [$at('2026-11-02')['level'], $at('2026-11-07')['level'], $at('2026-11-09')['level'], $at('2026-11-12')['level'], $at('2026-11-13')['level']]);
        $this->assertSame('Busca até 12/11 · faltam 3 dias', $at('2026-11-09')['text']);
        $this->assertStringContainsString('o professor faz a busca', \App\Support\ProgramJourney::youText($review, $lead->fresh(), 'busca'));
        $this->assertStringContainsString('Nada a fazer agora', \App\Support\ProgramJourney::youText($review, $rev->fresh(), 'busca'));

        // Aluno: barra no Painel e no projeto, sem o aviso de "vencida".
        \Illuminate\Support\Carbon::setTestNow('2026-11-09 10:00');
        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $this->actingAs($ana);
        Livewire::test(\App\Filament\Widgets\MetanalisesPortalWidget::class)
            ->assertSee('Etapa 3 de 9')->assertSee('Busca')->assertSee('o professor faz a busca')->assertSee('faltam 3 dias');
        Livewire::test(TriagemCega::class)->assertSee('Etapa 3 de 9')->assertSee('Agora:')
            ->assertSee('O que você pode')->assertSee('Usar IA para triar')->assertSee('Você é o(a) 1º autor(a)')->assertDontSee('Você é o(a) revisor(a) cego(a)');
        Livewire::actingAs($beto)->test(TriagemCega::class)->assertSee('Você é o(a) revisor(a) cego(a)')->assertSee('checagem aleatória');
        $this->actingAs($ana);

        // Professor: Central com a barra, o prazo e o card de alerta; aviso diário só quando perto do prazo.
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->admin);
        Livewire::test(\App\Filament\Pages\CentralMetanalises::class)
            ->assertSee('3. Busca')->assertSee('Busca até 12/11 · faltam 3 dias')->assertSee('busca(s) com prazo de 3 dias ou menos');
        $this->artisan('app:alertar-prazo-busca')->expectsOutputToContain('PET-01: Busca até 12/11')->assertSuccessful();
        Mail::assertSent(AppNotificationMail::class, fn ($m) => $m->hasTo('calefi@csvet.com.br') && str_contains($m->messageContent, 'PET-01'));

        \Illuminate\Support\Carbon::setTestNow('2026-11-02 10:00');
        $this->artisan('app:alertar-prazo-busca')->expectsOutputToContain('Nenhuma busca perto do prazo')->assertSuccessful();
        \Illuminate\Support\Carbon::setTestNow();
    }

    public function test_application_approved_before_the_account_is_linked_when_the_email_is_validated(): void
    {
        $call = ResearchCall::create(array_merge(ResearchCall::defaults(), ['status' => ResearchCall::STATUS_OPEN]));
        $app = $this->apply($call, 'Julia Nova', 'Julia.Nova@x.com');
        app(ResearchRecruitmentService::class)->approve($app->fresh(), 'PET-05', 'PET-07');
        $this->assertNull($app->fresh()->user_id); // ainda não tinha conta: recebe o convite

        $user = $this->student('Julia Nova', 'julia.nova@x.com');
        ResearchApplication::linkVerifiedUser($user);
        $this->assertSame($user->id, $app->fresh()->user_id);

        // Conta de outro tipo não toma a inscrição.
        $other = $this->apply($call, 'Prof Externo', 'ext@x.com');
        ResearchApplication::linkVerifiedUser(User::factory()->create(['role' => 'professor', 'email' => 'ext@x.com']));
        $this->assertNull($other->fresh()->user_id);
    }

    public function test_professor_messages_pair_individual_group_and_downloads_contacts(): void
    {
        [$call, , , , $a, $b] = $this->setupPair();
        $c = $this->apply($call, 'Carla Terceira', 'carla@x.com');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->admin);

        $page = Livewire::test(ListResearchApplications::class);
        $page->callAction('mensagemDupla', data: ['code' => 'PET-01', 'title' => 'Aviso da dupla', 'body' => 'Oi {nome}'])->assertHasNoActionErrors();
        Mail::assertSent(AppNotificationMail::class, fn ($m) => $m->hasTo('ana@x.com') && $m->title === 'Aviso da dupla' && $m->messageContent === 'Oi Ana');
        Mail::assertSent(AppNotificationMail::class, fn ($m) => $m->hasTo('beto@x.com') && $m->title === 'Aviso da dupla');
        Mail::assertNotSent(AppNotificationMail::class, fn ($m) => $m->hasTo('carla@x.com') && $m->title === 'Aviso da dupla');

        $page->callTableAction('mensagem', $c, data: ['title' => 'Individual', 'body' => 'Só você'])->assertHasNoTableActionErrors();
        Mail::assertSent(AppNotificationMail::class, fn ($m) => $m->hasTo('carla@x.com') && $m->title === 'Individual');

        $page->set('activeTab', 'selecionados')->callTableBulkAction('mensagemGrupo', [$a, $b], data: ['title' => 'Grupo', 'body' => 'Para os marcados'])->assertHasNoTableBulkActionErrors();
        Mail::assertSent(AppNotificationMail::class, fn ($m) => $m->hasTo('beto@x.com') && $m->title === 'Grupo');
        Mail::assertNotSent(AppNotificationMail::class, fn ($m) => $m->hasTo('carla@x.com') && $m->title === 'Grupo');
        $this->assertTrue(collect($a->fresh()->history ?? [])->contains(fn ($e) => $e['event'] === 'Mensagem do professor enviada'));

        $rows = app(ResearchRecruitmentService::class)->contactRows($call);
        $this->assertSame(['dupla', 'nome', 'email', 'lider_1o_autor', 'revisor_cego'], $rows[0]);
        $this->assertCount(3, $rows); // só aprovados
        $this->assertSame('PET-01 + PET-02', $rows[1][0]);
        $page->callAction('contatos')->assertFileDownloaded('contatos_duplas_' . now()->format('Y-m-d') . '.csv');
    }

    public function test_central_shows_all_projects_conversations_activity_and_sent_messages_to_admin_only(): void
    {
        [, $review, $ana, $beto] = $this->setupPair();
        $lead = $review->screeners()->where('email', 'ana@x.com')->first();
        $svc = app(SystematicReviewService::class);
        $svc->signTerms($lead, 'Ana Lider', '127.0.0.1', 'test');
        $svc->postMessage($review, $ana, 'Professor, quando é a reunião?');
        \App\Models\EmailLog::create(['recipient' => 'ana@x.com', 'subject' => 'Próximos passos — Prof. Atilio', 'body' => '<p>Olá Ana, corpo do aviso</p>', 'status' => 'success', 'sent_at' => now()]);
        \App\Models\EmailLog::create(['recipient' => 'beto@x.com', 'subject' => 'Próximos passos — Prof. Atilio', 'body' => '<p>Olá Beto</p>', 'status' => 'success', 'sent_at' => now()]);
        \App\Models\EmailLog::create(['recipient' => 'ana@x.com', 'subject' => 'Aprovado(a) — Seleção de pesquisa Prof. Atilio', 'body' => '<p>auto</p>', 'status' => 'success', 'sent_at' => now()->subDay()]);
        \App\Models\EmailLog::create(['recipient' => 'fora@x.com', 'subject' => 'Outro — Prof. Atilio', 'body' => '<p>x</p>', 'status' => 'success', 'sent_at' => now()]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->admin);
        $this->assertSame('1', \App\Filament\Pages\CentralMetanalises::getNavigationBadge()); // aguardando resposta

        $page = Livewire::actingAs($this->admin)->test(\App\Filament\Pages\CentralMetanalises::class)
            ->assertSee('PET-01')
            ->assertSee('Ana Lider')
            ->assertSee('termo pendente') // Beto ainda não assinou
            ->assertSee('💬 aguardando mensagem')
            ->assertSee('quando é a reunião?')
            ->call('setTab', 'atividades')
            ->assertSee('Termo assinado por Ana Lider')
            ->call('setTab', 'enviadas')
            ->assertSee('Próximos passos — Prof. Atilio')
            ->assertSee('Para 2:')
            ->assertDontSee('Outro — Prof. Atilio')
            ->assertDontSee('Aprovado(a) — Seleção')
            ->set('showAutomatic', true)
            ->assertSee('Aprovado(a) — Seleção')
            ->call('setTab', 'conversas')
            ->call('replyTo', $review->id)
            ->set('replyBody', 'Na semana que vem.')
            ->call('sendReply')
            ->assertSet('replyBody', '');

        $this->assertSame(SrMessage::ROLE_PROFESSOR, SrMessage::where("systematic_review_id", $review->id)->orderByDesc("id")->first()->author_role);
        $this->assertNull(\App\Filament\Pages\CentralMetanalises::getNavigationBadge());
        $page->call('setTab', 'enviadas')->assertSee('Conversa do projeto PET-01')->assertSee('Na semana que vem.');

        // Nova mensagem do aluno: "✓ vista" tira o aviso sem responder; a mensagem continua na conversa.
        $svc->postMessage($review, $ana, 'Obrigada!');
        $this->assertSame('1', \App\Filament\Pages\CentralMetanalises::getNavigationBadge());
        $page->call('setTab', 'conversas')->assertSee('Marcar como vista')->call('markSeen', $review->id)
            ->assertDontSee('💬 aguardando mensagem')->assertSee('Obrigada!');
        $this->assertNull(\App\Filament\Pages\CentralMetanalises::getNavigationBadge());

        // Só o professor acessa.
        $this->actingAs($ana)->get('/admin/central-metanalises')->assertForbidden();
        $this->actingAs($this->admin)->get('/admin/central-metanalises')->assertOk();
    }
}
