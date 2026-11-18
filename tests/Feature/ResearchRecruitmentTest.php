<?php

namespace Tests\Feature;

use App\Filament\Resources\ResearchApplicationResource\Pages\ListResearchApplications;
use App\Filament\Resources\ResearchCallResource\Pages\EditResearchCall;
use App\Filament\Resources\ResearchCallResource\Pages\ListResearchCalls;
use App\Mail\AppNotificationMail;
use App\Models\InviteToken;
use App\Models\ResearchApplication;
use App\Models\ResearchCall;
use App\Models\Student;
use App\Models\SystematicReview;
use App\Models\User;
use App\Services\ResearchRecruitmentService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class ResearchRecruitmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->admin = User::factory()->create(['role' => 'admin', 'email' => 'prof@example.com']);
    }

    protected function openCall(array $attrs = []): ResearchCall
    {
        return ResearchCall::create(array_merge(ResearchCall::defaults(), ['status' => ResearchCall::STATUS_OPEN], $attrs));
    }

    protected function payload(ResearchCall $call, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Maria  da Silva',
            'name_confirmation' => 'Maria da Silva',
            'email' => 'Maria@Example.com',
            'email_confirmation' => 'maria@example.com',
            'adhesion' => '1',
            'phone' => '(11) 98765-4321',
            'course' => 'Medicina Veterinária',
            'institution' => 'Universidade X',
            'semester' => 6,
            'purpose' => 'career',
            'axes' => [$call->axisNames()[0]],
            'projects' => ['PET-01', 'PET-03'],
            'weekly_hours' => 5,
            'english_level' => 'translator',
            'motivation' => 'Quero aprender revisão sistemática e publicar na área de exóticos.',
            'commitments' => array_fill_keys(array_keys($call->texts('commitments')), '1'),
        ], $overrides);
    }

    public function test_public_page_shows_steps_requirements_and_only_blinded_projects(): void
    {
        $this->get('/selecao')->assertNotFound()->assertSee('Nenhuma seleção aberta no momento');

        $draft = ResearchCall::create(ResearchCall::defaults());
        $this->get('/selecao/' . $draft->slug)->assertNotFound()->assertSee('ainda não foi publicada');
        $this->get('/selecao/nao-existe')->assertNotFound()->assertSee('Seleção não encontrada');

        $this->actingAs($this->admin)->get('/selecao/' . $draft->slug)->assertOk()->assertSee('Pré-visualização');
        auth()->logout();

        $draft->update(['status' => ResearchCall::STATUS_OPEN]);
        $this->get('/selecao')->assertRedirect('/selecao/' . $draft->slug);

        $this->get('/selecao/' . $draft->slug)
            ->assertOk()
            ->assertSee('Passo a passo, da inscrição ao artigo')
            ->assertSee('Termo de confidencialidade')
            ->assertSee('Triagem de títulos e resumos')
            ->assertSee('PET-01')
            ->assertSee('Farmacologia clínica em lagomorfos')
            ->assertSee('a partir do 4º semestre', false)
            ->assertSee('Formulário de inscrição')
            ->assertSee('Participação sem nenhum custo para o aluno')
            ->assertSee('Orientador:')
            ->assertSee('Natureza da participação')
            ->assertSee('6. Termo de adesão')
            ->assertSee('Posso usar inteligência artificial na triagem?')
            ->assertSee('Tem algum custo para participar?')
            ->assertSee('podem levar ao meu desligamento do projeto')
            ->assertDontSee('Iniciação Científica')
            ->assertDontSee('Meloxicam')
            ->assertDontSee('Oryctolagus');

        // Menu do site e sitemap mostram a chamada aberta.
        $this->get('/na-midia')->assertSee('Seleção de Pesquisa');
        $this->get('/sitemap.xml')->assertSee('/selecao/' . $draft->slug);
    }

    public function test_eligible_student_applies_gets_email_and_can_track(): void
    {
        $call = $this->openCall();

        $this->post('/selecao/' . $call->slug, $this->payload($call))
            ->assertOk()
            ->assertSee('Inscrição enviada!');

        $app = ResearchApplication::sole();
        $this->assertSame('maria@example.com', $app->email);
        $this->assertSame('Maria da Silva', $app->name);
        $this->assertSame(['PET-01', 'PET-03'], $app->project_preferences);
        $this->assertCount(count($call->texts('commitments')), $app->declarations);
        $this->assertSame(ResearchApplication::STATUS_NEW, $app->status);
        $this->assertNotSame($app->access_token, $app->access_token_hash);

        Mail::assertSent(AppNotificationMail::class, fn ($m) => $m->hasTo('maria@example.com') && $m->title === 'Inscrição recebida — confirme o seu e-mail' && str_contains($m->messageContent, $app->adhesion_sha256));
        Mail::assertSent(AppNotificationMail::class, fn ($m) => $m->hasTo('calefi@csvet.com.br'));

        // Aceite gravado: texto exato com nome, IP e natureza da participação, conferível pelo SHA-256.
        $this->assertSame(hash('sha256', $app->adhesion_text), $app->adhesion_sha256);
        $this->assertNotNull($app->adhesion_accepted_at);
        $this->assertNotNull($app->ip);
        foreach (['Maria da Silva', 'maria@example.com', 'IP ' . $app->ip, 'não configura relação de emprego', 'Lei nº 13.709/2018', 'Orientador: Prof. Dr. Atilio Sersun Calefi', 'inteligência artificial'] as $needle) {
            $this->assertStringContainsString($needle, $app->adhesion_text);
        }
        $this->assertNull($app->email_verified_at);

        $this->get($app->trackingUrl())
            ->assertOk()
            ->assertSee('Comprovante do aceite do termo de adesão')
            ->assertSee('Olá, Maria!')
            ->assertSee('Você está aqui')
            ->assertSee('Análise dos requisitos');

        $this->assertNotNull($app->fresh()->email_verified_at, 'Abrir o link do e-mail confirma o endereço.');
        $this->get('/selecao/acompanhar/' . str_repeat('a', 48))->assertNotFound();

        // Mesmo e-mail de novo: não duplica, só reenvia o link.
        $this->post('/selecao/' . $call->slug, $this->payload($call, ['email' => 'maria@example.com']))
            ->assertOk()->assertSee('Você já está inscrito(a)');
        $this->assertSame(1, ResearchApplication::count());
    }

    public function test_requirements_block_ineligible_applications(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $call = $this->openCall();
        $this->post('/selecao/' . $call->slug, $this->payload($call, ['adhesion' => null]))
            ->assertSessionHasErrors('adhesion');
        $this->post('/selecao/' . $call->slug, $this->payload($call, ['email_confirmation' => 'maria@exemplo.com']))
            ->assertSessionHasErrors('email');

        $this->from('/selecao/' . $call->slug)
            ->post('/selecao/' . $call->slug, $this->payload($call, ['semester' => 2]))
            ->assertSessionHasErrors('semester');
        $this->post('/selecao/' . $call->slug, $this->payload($call, ['name' => 'Maria A. Silva', 'name_confirmation' => 'Maria A. Silva']))
            ->assertSessionHasErrors('name');
        $this->post('/selecao/' . $call->slug, $this->payload($call, ['name' => 'Maria', 'name_confirmation' => 'Maria']))
            ->assertSessionHasErrors('name');
        $this->post('/selecao/' . $call->slug, $this->payload($call, ['name_confirmation' => 'Maria de Souza']))
            ->assertSessionHasErrors('name');
        $this->post('/selecao/' . $call->slug, $this->payload($call, ['course' => 'Outro curso']))
            ->assertSessionHasErrors('course');
        $this->post('/selecao/' . $call->slug, $this->payload($call, ['weekly_hours' => 2]))
            ->assertSessionHasErrors('weekly_hours');
        $this->post('/selecao/' . $call->slug, $this->payload($call, ['commitments' => ['0' => '1']]))
            ->assertSessionHasErrors('commitments');
        $this->post('/selecao/' . $call->slug, $this->payload($call, ['projects' => ['PET-01', 'PET-02', 'PET-03', 'PET-04']]))
            ->assertSessionHasErrors('projects');

        // Honeypot: finge sucesso e não grava.
        $this->post('/selecao/' . $call->slug, $this->payload($call, ['website' => 'spam']))->assertOk();

        // Prazo vencido fecha o formulário.
        $call->update(['closes_at' => now()->subDay()]);
        $this->get('/selecao/' . $call->slug)->assertSee('As inscrições desta chamada estão encerradas');
        $this->post('/selecao/' . $call->slug, $this->payload($call))->assertSessionHasErrors('call');

        $this->assertSame(0, ResearchApplication::count());
    }

    public function test_approval_allocates_pair_invites_once_and_adds_screener(): void
    {
        $review = SystematicReview::create(['title' => 'Meloxicam in rabbits', 'review_type' => 'meta_analysis', 'framework' => 'PICO']);
        $review2 = SystematicReview::create(['title' => 'Uterine adenocarcinoma', 'review_type' => 'prevalence', 'framework' => 'CoCoPop']);
        $call = $this->openCall();
        $projects = $call->projects;
        $projects[0]['systematic_review_id'] = $review->id;   // PET-01
        $projects[1]['systematic_review_id'] = $review2->id;  // PET-02
        $call->update(['projects' => $projects]);

        $service = app(ResearchRecruitmentService::class);
        [$a] = $service->submit($call, $this->payload($call));
        [$b] = $service->submit($call, $this->payload($call, ['email' => 'joao@example.com', 'name' => 'João Souza']));

        $service->scheduleInterview($a, now()->addDays(2)->setTime(14, 0), 'Google Meet — link: https://meet.google.com/abc');
        $this->assertSame(ResearchApplication::STATUS_INTERVIEW, $a->fresh()->status);
        Mail::assertSent(AppNotificationMail::class, fn ($m) => $m->hasTo('maria@example.com') && $m->title === 'Reunião marcada');

        $invite = $service->approve($a->fresh(), 'PET-01', 'PET-02', 'Bem-vinda!');
        $this->assertNotNull($invite);
        $this->assertSame(1, InviteToken::where('email', 'maria@example.com')->whereNull('used_at')->count());
        $this->assertTrue(InviteToken::where('email', 'maria@example.com')->first()->auto_approve);
        $this->assertSame(1, $review->screeners()->where('email', 'maria@example.com')->count());
        $this->assertSame(1, $review2->screeners()->where('email', 'maria@example.com')->count());
        Mail::assertSent(AppNotificationMail::class, fn ($m) => $m->hasTo('maria@example.com') && $m->title === 'Você foi aprovado(a)!'
            && str_contains($m->messageContent, $invite) && !str_contains($m->messageContent, 'Meloxicam'));

        // Vaga ocupada não pode ser dada a outro aluno.
        $this->assertNotEmpty($service->allocationProblems($b, 'PET-01', 'PET-02'));
        $this->expectException(\RuntimeException::class);
        $service->approve($b, 'PET-01');
    }

    public function test_project_leaves_the_application_list_when_the_pair_is_complete(): void
    {
        $call = $this->openCall();
        $service = app(ResearchRecruitmentService::class);
        [$a] = $service->submit($call, $this->payload($call));
        [$b] = $service->submit($call, $this->payload($call, ['email' => 'joao@example.com', 'name' => 'João Souza']));
        $codes = fn () => collect($call->fresh()->projectsByAxis(onlyOpen: true))->flatMap(fn ($g) => collect($g['projects'])->pluck('code'))->all();
        $this->assertContains('PET-01', $codes());

        // Só o líder: continua na lista, com "1 vaga".
        $service->approve($a->fresh(), 'PET-01', 'PET-02');
        $this->assertContains('PET-01', $codes());
        $this->assertTrue(collect($call->fresh()->projectsByAxis(onlyOpen: true))->flatMap(fn ($g) => $g['projects'])->firstWhere('code', 'PET-01')['half']);

        // Dupla completa (líder + revisor): sai da lista e da validação do formulário.
        $service->approve($b->fresh(), 'PET-02', 'PET-01');
        $this->assertNotContains('PET-01', $codes());
        $this->assertNotContains('PET-02', $codes());
        $this->assertArrayNotHasKey('PET-01', $call->fresh()->openProjectsByCode());
        $this->get($call->publicUrl())->assertOk()->assertDontSee('PET-01 ', false);

        // Fechado à mão pelo professor também sai.
        $projects = $call->fresh()->projects;
        $projects[2]['closed'] = true; // PET-03
        $call->update(['projects' => $projects]);
        $this->assertNotContains('PET-03', $codes());
        $this->assertArrayHasKey('PET-03', $call->fresh()->projectsByCode(), 'Continua no cadastro e nos selects do admin.');
    }

    public function test_existing_student_is_linked_without_invite_and_track_shows_next_step(): void
    {
        $user = User::factory()->create(['role' => 'student', 'name' => 'Maria da Silva', 'email' => 'maria@example.com']);
        Student::create(['user_id' => $user->id, 'institution' => 'X', 'academic_level' => 'Graduação', 'research_topic' => 'Geral',
            'tcc_status' => 'none', 'phone' => '1', 'status' => 'active', 'category' => 'regular']);

        $call = $this->openCall();
        $service = app(ResearchRecruitmentService::class);
        [$app] = $service->submit($call, $this->payload($call));
        $this->assertSame($user->id, $app->user_id);

        $this->assertNull($service->approve($app, 'PET-03', 'PET-04'));
        $this->assertSame(0, InviteToken::count());
        $this->assertSame(5, $service->currentStep($app->fresh()));

        $this->get($app->fresh()->trackingUrl())->assertOk()
            ->assertSee('Aprovado(a)')
            ->assertSee('PET-03')
            ->assertSee('Abrir o Portal do Aluno');
    }

    public function test_admin_pages_create_default_call_share_and_select(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->admin);

        Livewire::test(ListResearchCalls::class)->callAction('modelo')->assertHasNoActionErrors();
        $call = ResearchCall::sole();
        $this->assertSame(ResearchCall::STATUS_DRAFT, $call->status);
        $this->assertCount(20, $call->projects);

        $this->get(\App\Filament\Resources\ResearchCallResource::getUrl('index'))->assertOk();
        $this->get(\App\Filament\Resources\ResearchCallResource::getUrl('edit', ['record' => $call]))->assertOk();

        Livewire::test(EditResearchCall::class, ['record' => $call->getRouteKey()])
            ->fillForm(['status' => ResearchCall::STATUS_OPEN, 'closes_at' => now()->addMonth()->toDateString()])
            ->call('save')
            ->assertHasNoFormErrors()
            ->mountAction('divulgar')
            ->assertSee('WhatsApp')
            ->assertSee($call->publicUrl());
        $this->assertTrue($call->fresh()->isOpen());

        $text = $call->fresh()->shareText();
        $this->assertStringContainsString($call->publicUrl(), $text);
        $email = app(ResearchRecruitmentService::class)->announcementEmail($call->fresh());
        $this->assertStringContainsString('PRÉ-REQUISITOS', $email['body']);
        $this->assertStringContainsString('SEM CUSTO PARA O ALUNO', $email['body']);
        $this->assertStringContainsString('Sem nenhum custo para o aluno', $text);
        $this->assertStringContainsString($call->publicUrl(), $email['body']);

        [$app] = app(ResearchRecruitmentService::class)->submit($call->fresh(), $this->payload($call->fresh()));

        $this->get(\App\Filament\Resources\ResearchApplicationResource::getUrl('index'))->assertOk()->assertSee('Maria da Silva');

        Livewire::test(ListResearchApplications::class)
            ->assertTableActionHidden('agendar', $app)
            ->assertTableActionHidden('aprovar', $app)
            ->assertTableActionVisible('confirmacao', $app);
        app(ResearchRecruitmentService::class)->confirmEmail($app);
        $app->refresh();

        Livewire::test(ListResearchApplications::class)
            ->callTableAction('agendar', $app, ['interview_at' => now()->addDay()->format('Y-m-d H:i'), 'interview_place' => 'Google Meet'])
            ->assertHasNoTableActionErrors()
            ->callTableAction('entrevista', $app, ['answers' => array_slice($call->texts('interview_questions'), 0, 4), 'notes' => 'Ok'])
            ->assertHasNoTableActionErrors()
            ->callTableAction('aprovar', $app, ['lead' => 'PET-05', 'reviewer' => 'PET-06'])
            ->assertHasNoTableActionErrors();

        $app->refresh();
        $this->assertSame(ResearchApplication::STATUS_APPROVED, $app->status);
        $this->assertSame('PET-05', $app->lead_project_code);
        $this->assertFalse($app->interview_answers[$call->texts('interview_questions')[4]]);

        Livewire::test(EditResearchCall::class, ['record' => $call->getRouteKey()])
            ->mountAction('matriz')
            ->assertSee('Maria da Silva');
    }

    public function test_removal_for_delay_revokes_access_and_notifies(): void
    {
        $review = SystematicReview::create(['title' => 'Meloxicam in rabbits', 'review_type' => 'meta_analysis', 'framework' => 'PICO']);
        $call = $this->openCall();
        $projects = $call->projects;
        $projects[0]['systematic_review_id'] = $review->id;
        $call->update(['projects' => $projects]);

        $service = app(ResearchRecruitmentService::class);
        [$app] = $service->submit($call, $this->payload($call));
        $service->approve($app, 'PET-01', 'PET-02');

        $service->remove($app->fresh(), 'A triagem venceu em 10/11 sem entrega nem resposta.');

        $app->refresh();
        $this->assertSame(ResearchApplication::STATUS_REMOVED, $app->status);
        $this->assertNotNull($review->screeners()->where('email', 'maria@example.com')->first()->removed_at);
        $this->assertEmpty($service->allocationProblems(
            app(ResearchRecruitmentService::class)->submit($call, $this->payload($call, ['email' => 'outro@example.com']))[0], 'PET-01', 'PET-02'));
        Mail::assertSent(AppNotificationMail::class, fn ($m) => $m->hasTo('maria@example.com') && $m->title === 'Desligamento do projeto'
            && str_contains($m->messageContent, 'A triagem venceu') && str_contains($m->messageContent, 'desligamento do projeto'));

        $this->get($app->trackingUrl())->assertOk()->assertSee('Você foi desligado(a) do projeto');

        // O termo assinado no portal prevê o desligamento por atraso.
        $terms = \App\Models\SystematicReviewScreener::termsText($review, 'Maria', 'maria@example.com');
        $this->assertStringContainsString('atraso na entrega de etapas', $terms);
        $this->assertStringContainsString('posso ser desligado(a) do projeto', $terms);
        foreach (['PARTICIPAÇÃO PESSOAL E INTRANSFERÍVEL', 'arts. 186 e 927 do Código Civil', 'Lei nº 9.610/1998', 'concepção, a delimitação dos temas', 'não configura relação de emprego', 'não há jornada, horário fixo', 'Lei nº 13.709/2018', 'art. 18', 'periódico científico internacional', 'objetivo da pesquisa apresentada', 'ferramentas de inteligência artificial'] as $needle) {
            $this->assertStringContainsString($needle, $terms);
        }
    }

    public function test_publish_command_creates_then_refreshes_keeping_links(): void
    {
        $this->artisan('app:publicar-selecao', ['--prazo' => '2026-11-15'])->assertSuccessful();
        $call = ResearchCall::sole();
        $this->assertTrue($call->isOpen() || $call->closes_at->isPast());
        $this->assertSame('2026-11-15', $call->closes_at->toDateString());

        $review = SystematicReview::create(['title' => 'X', 'review_type' => 'meta_analysis', 'framework' => 'PICO']);
        $projects = $call->projects;
        $projects[0]['systematic_review_id'] = $review->id;
        $projects[0]['area'] = 'texto antigo';
        $call->update(['projects' => $projects, 'title' => 'Título antigo']);

        $this->artisan('app:publicar-selecao')->assertSuccessful();
        $call->refresh();
        $this->assertSame(ResearchCall::defaults()['title'], $call->title);
        $this->assertSame('Farmacologia clínica em lagomorfos', $call->projects[0]['area']);
        $this->assertSame($review->id, $call->projects[0]['systematic_review_id']);
        $this->assertSame('2026-11-15', $call->closes_at->toDateString());
        $this->assertSame(1, ResearchCall::count());

        $this->artisan('app:publicar-selecao', ['--prazo' => '15/11/2026'])->assertFailed();
    }


    public function test_legacy_application_needs_email_and_adhesion_before_approval(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $call = $this->openCall();
        [$app] = app(ResearchRecruitmentService::class)->submit($call, $this->payload($call));
        // Inscrição feita antes do termo de adesão e da confirmação de e-mail existirem.
        $app->update(['adhesion_text' => null, 'adhesion_sha256' => null, 'adhesion_accepted_at' => null, 'email_verified_at' => null]);

        $this->actingAs($this->admin);
        Livewire::test(ListResearchApplications::class)
            ->assertTableActionHidden('agendar', $app)
            ->assertTableActionHidden('aprovar', $app)
            ->assertTableActionVisible('confirmacao', $app)
            ->callTableAction('confirmar_email', $app, ['note' => 'Confirmado pelo WhatsApp'])
            ->assertHasNoTableActionErrors();
        Livewire::test(ListResearchApplications::class)
            ->assertTableActionVisible('agendar', $app->fresh())
            ->assertTableActionHidden('aprovar', $app->fresh());

        try {
            app(ResearchRecruitmentService::class)->approve($app->fresh(), 'PET-01');
            $this->fail('Aprovou sem o aceite do termo de adesão.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('termo de adesão', $e->getMessage());
        }

        // O aluno aceita pela página de acompanhamento: texto, IP e SHA-256 gravados.
        auth()->logout();
        $token = $app->fresh()->access_token;
        $this->get(route('research-call.track', $token))->assertOk()->assertSee('Falta o seu aceite do termo de adesão');
        $this->post(route('research-call.adhesion', $token), [])->assertSessionHasErrors('adhesion');
        $this->post(route('research-call.adhesion', $token), ['adhesion' => '1'])->assertRedirect(route('research-call.track', $token));
        $app->refresh();
        $this->assertSame(hash('sha256', $app->adhesion_text), $app->adhesion_sha256);
        $this->assertStringContainsString('Maria da Silva', $app->adhesion_text);
        Mail::assertSent(AppNotificationMail::class, fn ($m) => $m->title === 'Comprovante do aceite do termo de adesão');

        $this->actingAs($this->admin);
        Livewire::test(ListResearchApplications::class)->assertTableActionVisible('aprovar', $app->fresh());
    }

    public function test_redirect_to_another_line_and_reallocate_approved_student(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $r1 = SystematicReview::create(['title' => 'R1', 'review_type' => 'meta_analysis', 'framework' => 'PICO']);
        $r3 = SystematicReview::create(['title' => 'R3', 'review_type' => 'meta_analysis', 'framework' => 'PICO']);
        $call = $this->openCall();
        $projects = collect($call->projects)->map(function ($p) use ($r1, $r3) {
            $p['systematic_review_id'] = ['PET-01' => $r1->id, 'PET-03' => $r3->id][$p['code']] ?? null;

            return $p;
        })->all();
        $call->update(['projects' => $projects]);
        $service = app(ResearchRecruitmentService::class);
        [$app] = $service->submit($call, $this->payload($call));
        $service->confirmEmail($app);

        $onco = $call->axisNames()[1];
        $this->actingAs($this->admin);
        Livewire::test(ListResearchApplications::class)
            ->callTableAction('linha', $app, ['axis' => $onco, 'projects' => ['PAT-01'], 'notify' => true, 'message' => 'Seu perfil combina mais.'])
            ->assertHasNoTableActionErrors();
        $app->refresh();
        $this->assertSame([$onco], $app->axis_preferences);
        $this->assertSame(['PAT-01'], $app->project_preferences);
        $this->assertTrue(collect($app->history)->contains(fn ($h) => $h['event'] === 'Redirecionado(a) para outra linha'));
        Mail::assertSent(AppNotificationMail::class, fn ($m) => $m->title === 'Sua inscrição foi direcionada para outra linha');

        $service->approve($app, 'PET-01', 'PET-02');
        Livewire::test(ListResearchApplications::class)
            ->set('activeTab', 'selecionados')
            ->callTableAction('realocar', $app->fresh(), ['lead' => 'PET-03', 'reviewer' => 'PET-02'])
            ->assertHasNoTableActionErrors();
        $app->refresh();
        $this->assertSame('PET-03', $app->lead_project_code);
        $this->assertNotNull($r1->screeners()->where('email', 'maria@example.com')->first()->removed_at);
        $this->assertNull($r3->screeners()->where('email', 'maria@example.com')->first()->removed_at);
        Mail::assertSent(AppNotificationMail::class, fn ($m) => $m->title === 'Seus projetos foram alterados');
    }

    public function test_close_and_reopen_registrations(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $call = $this->openCall();
        [$app] = app(ResearchRecruitmentService::class)->submit($call, $this->payload($call));

        $this->actingAs($this->admin);
        Livewire::test(ListResearchApplications::class)->callAction('encerrar')->assertHasNoActionErrors();
        $this->assertSame(ResearchCall::STATUS_CLOSED, $call->fresh()->status);
        auth()->logout();
        $this->get('/selecao/' . $call->slug)->assertOk()->assertSee('Inscrições encerradas');
        $this->post('/selecao/' . $call->slug, $this->payload($call, ['email' => 'novo@example.com', 'email_confirmation' => 'novo@example.com']))
            ->assertSessionHasErrors('call');
        $this->get('/na-midia')->assertDontSee('Seleção de Pesquisa');
        // Quem já se inscreveu continua acompanhando.
        $this->get($app->trackingUrl())->assertOk();

        $this->actingAs($this->admin);
        Livewire::test(\App\Filament\Resources\ResearchCallResource\Pages\EditResearchCall::class, ['record' => $call->getRouteKey()])
            ->callAction('encerrar')->assertHasNoActionErrors();
        $this->assertTrue($call->fresh()->isOpen());

        $this->artisan('app:publicar-selecao', ['--encerrar' => true])->assertSuccessful();
        $this->assertSame(ResearchCall::STATUS_CLOSED, $call->fresh()->status);
        // Atualizar os textos não reabre sem querer; só com --reabrir.
        $this->artisan('app:publicar-selecao')->assertSuccessful();
        $this->assertSame(ResearchCall::STATUS_CLOSED, $call->fresh()->status);
        $this->artisan('app:publicar-selecao', ['--reabrir' => true])->assertSuccessful();
        $this->assertSame(ResearchCall::STATUS_OPEN, $call->fresh()->status);
    }

    public function test_students_approved_before_import_become_screeners_when_protocol_is_imported(): void
    {
        $call = $this->openCall();
        $service = app(ResearchRecruitmentService::class);
        [$a] = $service->submit($call, $this->payload($call));
        [$b] = $service->submit($call, $this->payload($call, ['email' => 'joao@example.com', 'name' => 'João Souza']));
        $service->approve($a->fresh(), 'PET-01', 'PET-02');
        $service->approve($b->fresh(), 'PET-02', 'PET-01');
        $this->assertSame(0, \App\Models\SystematicReviewScreener::count());

        $pipeline = fn (string $id) => json_encode(['schema' => 'calefi-sr-pipeline/1.0', 'protocol_id' => $id, 'version' => '1.1', 'title' => "Synthetic {$id}",
            'label_pt' => 'Teste', 'family' => 'intervention', 'keywords' => ['x'],
            'eligibility' => ['pico' => [['element' => 'Population', 'definition' => 'Y']], 'inclusion' => ['A'], 'exclusion' => ['B']],
            'search' => ['databases' => [['name' => 'PubMed/MEDLINE', 'interface' => 'PubMed', 'query' => 'x']]],
            'screening' => ['stages' => [['id' => 'title_abstract', 'decisions' => ['include', 'exclude', 'maybe']], ['id' => 'full_text', 'decisions' => ['include', 'exclude']]]],
            'extraction' => ['fields' => [['name' => 'country', 'type' => 'text', 'description' => 'Country']], 'outcomes' => [['id' => 'O1', 'name' => 'Pain', 'type' => 'continuous', 'measure' => 'SMD', 'timepoint' => '6 h', 'priority' => 'primary']]],
            'risk_of_bias' => ['tools' => [['code' => 'SYRCLE', 'name' => 'SYRCLE', 'domains' => ['Sequence generation']]]],
            'synthesis' => ['model' => 'RE']]);
        $protocol = app(\App\Services\SystematicReviewProtocolService::class);
        [$p01] = $protocol->import($pipeline('P01'));
        [$p02] = $protocol->import($pipeline('P02'));

        $this->assertSame(['maria@example.com' => 'Líder (1º autor)', 'joao@example.com' => 'Revisor(a) cego(a)'],
            $p01->screeners()->pluck('label', 'email')->all());
        $this->assertSame(['maria@example.com' => 'Revisor(a) cego(a)', 'joao@example.com' => 'Líder (1º autor)'],
            $p02->screeners()->pluck('label', 'email')->all());
        $this->assertSame('maria@example.com', $p01->leadScreener()->email);
        $this->assertSame('joao@example.com', $p01->secondScreener()->email);

        // Reimportar não duplica.
        $protocol->import($pipeline('P01'));
        $this->assertSame(2, $p01->screeners()->count());
    }
}
