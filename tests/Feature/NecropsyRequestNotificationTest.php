<?php

namespace Tests\Feature;

use App\Filament\Resources\NecropsyRequestResource;
use App\Filament\Resources\NecropsyRequestResource\Pages\ListNecropsyRequests;
use App\Mail\AppNotificationMail;
use App\Models\NecropsyRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class NecropsyRequestNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.telegram.bot_token' => 'TESTTOKEN']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        Mail::fake();

        $this->admin = User::factory()->create(['role' => 'admin', 'email' => 'prof@example.com']);
        $this->admin->forceFill(['telegram_chat_id' => '9000'])->save();
    }

    protected function quotePayload(array $overrides = []): array
    {
        return array_merge([
            'requester_name' => 'Maria de Oliveira',
            'requester_phone' => '(11) 98765-4321',
            'requester_email' => 'maria@example.com',
            'species' => 'Cão',
            'weight' => '12.5',
            'has_cremation_plan' => '0',
            'is_euthanasia' => '1',
            'requested_by_vet' => '0',
        ], $overrides);
    }

    public function test_quote_notifies_professor_by_email_and_telegram_immediately(): void
    {
        $response = $this->post(route('necropsy.orcamento.store'), $this->quotePayload());

        $quote = NecropsyRequest::firstOrFail();
        $response->assertRedirect(route('necropsy.autorizar', ['token' => $quote->token]));

        $this->assertSame('Maria de Oliveira', $quote->requester_name);
        $this->assertSame('quote_generated', $quote->status);

        // E-mail síncrono (send, não queue) para o professor, com reply-to do interessado.
        Mail::assertSent(AppNotificationMail::class, function (AppNotificationMail $mail) {
            return $mail->hasTo('contato@profatilio.com.br')
                && $mail->hasReplyTo('maria@example.com')
                && str_contains($mail->messageContent, '(11) 98765-4321')
                && str_contains($mail->messageContent, 'R$ 600,00');
        });
        Mail::assertNothingQueued();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.telegram.org')
            && $request['chat_id'] === '9000'
            && str_contains($request['text'], 'orçamento de NECROPSIA')
            && str_contains($request['text'], 'wa.me/5511987654321'));
    }

    public function test_contact_is_required_on_quote_step(): void
    {
        $this->post(route('necropsy.orcamento.store'), $this->quotePayload(['requester_name' => '', 'requester_phone' => '']))
            ->assertSessionHasErrors(['requester_name', 'requester_phone']);

        $this->assertSame(0, NecropsyRequest::count());
        Mail::assertNothingSent();
    }

    public function test_honeypot_blocks_bots_without_notifying(): void
    {
        $this->post(route('necropsy.orcamento.store'), $this->quotePayload(['website' => 'http://spam.example']))
            ->assertRedirect(route('necropsy.orcamento'));

        $this->assertSame(0, NecropsyRequest::count());
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_telegram_html_escapes_user_input(): void
    {
        $this->post(route('necropsy.orcamento.store'), $this->quotePayload(['requester_name' => '<b>Ana</b> & Cia']));

        Http::assertSent(fn ($request) => str_contains($request['text'], '&lt;b&gt;Ana&lt;/b&gt; &amp; Cia'));
    }

    public function test_awaiting_reply_badge_and_mark_contacted_action(): void
    {
        $this->post(route('necropsy.orcamento.store'), $this->quotePayload());
        $quote = NecropsyRequest::firstOrFail();

        // Pedido antigo, de antes do campo de contato: não entra na contagem.
        NecropsyRequest::create(array_merge(NecropsyRequest::calculatePricing(5, false), [
            'token' => 'old-token', 'species' => 'Gato', 'weight' => 5, 'status' => 'quote_generated',
        ]));

        $this->actingAs($this->admin);
        $this->assertSame('1', NecropsyRequestResource::getNavigationBadge());

        Livewire::test(ListNecropsyRequests::class)
            ->assertTableActionVisible('whatsapp', $quote)
            ->callTableAction('markContacted', $quote);

        $this->assertNotNull($quote->fresh()->contacted_at);
        $this->assertNull(NecropsyRequestResource::getNavigationBadge());
    }

    public function test_signed_authorization_notifies_professor_and_requester(): void
    {
        \Illuminate\Support\Facades\Storage::fake(config('filesystems.default'));

        $this->post(route('necropsy.orcamento.store'), $this->quotePayload());
        $quote = NecropsyRequest::firstOrFail();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->post(route('necropsy.autorizar.store', ['token' => $quote->token]), [
            'requester_name' => 'Maria de Oliveira', 'requester_email' => 'maria@example.com',
            'requester_phone' => '(11) 98765-4321', 'requester_cpf' => '123.456.789-00', 'requester_rg' => '12.345.678-9',
            'requester_address' => 'Rua A, 1', 'requester_neighborhood' => 'Centro', 'requester_city' => 'São Paulo',
            'requester_state' => 'SP', 'requester_zip' => '01000-000', 'animal_name' => 'Thor',
            'has_prosthesis' => '0', 'had_surgery' => '0', 'signature_name' => 'Maria de Oliveira',
            'signature_image' => 'data:image/png;base64,iVBORw0KGgo=', 'terms_accepted' => '1',
        ])->assertRedirect(route('necropsy.sucesso', ['token' => $quote->token]));

        $this->assertSame('authorized', $quote->fresh()->status);

        Mail::assertSent(AppNotificationMail::class, fn ($m) => $m->hasTo('contato@profatilio.com.br')
            && $m->title === 'Nova Autorização de Necropsia Recebida');
        Mail::assertSent(AppNotificationMail::class, fn ($m) => $m->hasTo('maria@example.com'));
        Mail::assertNothingQueued();
        Http::assertSent(fn ($request) => str_contains($request['text'], 'AUTORIZADA') && str_contains($request['text'], 'Thor'));
    }

    public function test_direct_link_marks_as_contacted_and_notifies_as_agreed(): void
    {
        $this->get('/autorizar-necropsia')
            ->assertOk()
            ->assertSee('Autorização de Necropsia')
            ->assertSee('noindex', false)
            ->assertSee('name="direct" value="1"', false);

        $this->post(route('necropsy.orcamento.store'), $this->quotePayload(['direct' => '1']));
        $quote = NecropsyRequest::firstOrFail();

        $this->assertNotNull($quote->contacted_at);
        Http::assertSent(fn ($request) => str_contains($request['text'], 'COMBINADA'));
        Mail::assertSent(AppNotificationMail::class, fn ($m) => str_starts_with($m->title, 'Necropsia combinada'));

        $this->actingAs($this->admin);
        $this->assertNull(NecropsyRequestResource::getNavigationBadge());
    }

    public function test_panel_generates_term_with_agreed_price_and_ready_message(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(ListNecropsyRequests::class)
            ->callAction('generateAgreedTerm', [
                'requester_name' => 'João Souza',
                'requester_phone' => '(11) 91234-5678',
                'species' => 'Gato',
                'weight' => '4',
                'final_price' => '350',
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('Link do termo gerado');

        $r = NecropsyRequest::firstOrFail();
        $this->assertSame('350.00', $r->final_price);
        $this->assertSame('400.00', $r->price);
        $this->assertSame('50.00', $r->discount);
        $this->assertNotNull($r->contacted_at);
        $this->assertStringContainsString($r->authorizationUrl(), $r->authorizationMessage());
        $this->assertStringContainsString('R$ 350,00', $r->authorizationMessage());

        // O link pessoal abre o termo com os dados de contato já preenchidos.
        $this->get($r->authorizationUrl())->assertOk()->assertSee('João Souza');

        Livewire::test(ListNecropsyRequests::class)
            ->callAction('directLink')
            ->assertNotified('Link direto da necropsia');
    }

    public function test_landing_page_is_indexable_and_never_mentions_laboratory(): void
    {
        $html = $this->get('/necropsia-veterinaria')
            ->assertOk()
            ->assertSee('Necropsia Veterinária com o Prof. Dr. Atilio Calefi')
            ->assertSee('R$ 400,00')
            ->assertSee('"@type": "FAQPage"', false)
            ->assertSee('https://profatilio.com.br/#person', false)
            ->getContent();

        $this->assertStringNotContainsStringIgnoringCase('laboratório', $html);
        $this->assertStringNotContainsStringIgnoringCase('laboratorio', $html);

        foreach (['necropsy.auth_form', 'necropsy.auth_pdf'] as $view) {
            $this->assertStringNotContainsStringIgnoringCase('laboratório', file_get_contents(resource_path('views/' . str_replace('.', '/', $view) . '.blade.php')));
        }

        $this->get('/sitemap.xml')->assertOk()->assertSee('https://profatilio.com.br/necropsia-veterinaria');
    }

    public function test_whatsapp_url_has_ready_message(): void
    {
        $r = new NecropsyRequest([
            'requester_name' => 'Maria de Oliveira', 'requester_phone' => '11 98765-4321',
            'species' => 'Cão', 'weight' => 12.5, 'final_price' => 600,
        ]);

        $url = urldecode($r->whatsappUrl());
        $this->assertStringStartsWith('https://wa.me/5511987654321?text=', $url);
        $this->assertStringContainsString('Olá, Maria! Aqui é o Prof. Atilio.', $url);
        $this->assertStringContainsString('R$ 600,00', $url);

        $this->assertNull((new NecropsyRequest(['requester_phone' => '123']))->whatsappUrl());
    }
}
