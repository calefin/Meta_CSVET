<?php

namespace Tests\Feature;

use App\Livewire\Admin\Oracular\QuickPaymentBuilder;
use App\Livewire\PublicQuickCheckout;
use App\Livewire\Admin\Oracular\ConsultationDetail;
use App\Models\QuickPayment;
use App\Models\ConsultationRequest;
use App\Models\User;
use App\Models\Payment;
use App\Mail\QuickPaymentOptionSelectedAdminMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class QuickPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_generate_quick_payment_link(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin);

        Livewire::test(QuickPaymentBuilder::class)
            ->set('type', 'consulta')
            ->set('notes', 'Consulta com sacerdote')
            ->set('optionItems.0', [
                [
                    'name' => 'Abano Africano',
                    'quantity' => 1,
                    'unit_price' => 100.00,
                    'supplier_id' => null,
                ]
            ])
            ->set('markup_percent', '10') // 100 + 10% = 110
            ->set('fee_percent', '5')     // 110 / (1 - 0.05) = 115.79
            ->call('generateLink')
            ->assertHasNoErrors();

        $qp = QuickPayment::first();
        $this->assertNotNull($qp);
        $this->assertSame('consulta', $qp->type);
        $this->assertEquals(100.00, $qp->subtotal);
        $this->assertEquals(115.79, $qp->total);
        $this->assertSame('pending', $qp->status);
    }

    public function test_admin_can_generate_quick_payment_link_with_freight_and_labor_cost(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin);

        Livewire::test(QuickPaymentBuilder::class)
            ->set('type', 'trabalhos_gerais')
            ->set('optionItems.0', [
                [
                    'name' => 'Ebó completo',
                    'quantity' => 1,
                    'unit_price' => 300.00,
                    'supplier_id' => null,
                ]
            ])
            ->set('optionFreight.0', '50')
            ->set('optionLaborCost.0', '20')
            ->set('markup_percent', '10') // 10% sobre (300 + 50) = 35
            ->set('fee_percent', '0')
            ->call('generateLink')
            ->assertHasNoErrors();

        $qp = QuickPayment::first();
        $this->assertNotNull($qp);
        $this->assertEquals(300.00, $qp->subtotal);
        // (300 + 50 frete) + 35 lucro (10%) + 20 mão de obra = 405
        $this->assertEquals(405.00, $qp->total);
        $this->assertEquals(50.00, $qp->options[0]['freight']);
        $this->assertEquals(35.00, $qp->options[0]['profit']);
        $this->assertEquals(20.00, $qp->options[0]['labor_cost']);
    }

    public function test_guest_can_register_and_checkout_from_link(): void
    {
        $qp = QuickPayment::create([
            'uuid' => 'test-uuid-123',
            'type' => 'consulta',
            'notes' => 'Consulta rápida',
            'items' => [],
            'markup_percent' => 0,
            'fee_percent' => 5,
            'subtotal' => 100.00,
            'total' => 105.26,
            'status' => 'pending',
        ]);

        Livewire::test(PublicQuickCheckout::class, ['uuid' => 'test-uuid-123'])
            ->set('registerName', 'Consulente Teste')
            ->set('registerMotherName', 'Mãe do Consulente')
            ->set('registerBirthdate', '25/12/1988')
            ->set('registerCountry', 'BR')
            ->set('registerCpf', '123.456.789-01')
            ->set('registerEmail', 'teste@example.com')
            ->set('registerPhone', '(11) 98888-7777')
            ->set('registerPassword', 'password123')
            ->set('registerPasswordConfirmation', 'password123')
            ->set('registerConsent', true)
            ->call('registerAndPay')
            ->assertHasNoErrors();

        // Check user was created
        $user = User::where('email', 'teste@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('Consulente Teste', $user->name);
        $this->assertSame('1988-12-25', $user->birthdate->format('Y-m-d'));

        // Check payment record was created
        $payment = Payment::where('user_id', $user->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals(105.26, $payment->amount);
        $this->assertSame(QuickPayment::class, $payment->payable_type);
        $this->assertEquals($qp->id, $payment->payable_id);
    }

    public function test_admin_can_generate_link_with_base_value_and_no_items(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin);

        Livewire::test(QuickPaymentBuilder::class)
            ->set('type', 'trabalhos_gerais')
            ->set('optionBaseValue.0', '250.00')
            ->call('generateLink')
            ->assertHasNoErrors();

        $qp = QuickPayment::first();
        $this->assertNotNull($qp);
        $this->assertEquals(250.00, $qp->subtotal);
        $this->assertEquals(250.00, $qp->total);
    }

    public function test_admin_cannot_generate_link_with_zero_value(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin);

        Livewire::test(QuickPaymentBuilder::class)
            ->set('type', 'trabalhos_gerais')
            ->set('optionBaseValue.0', '0')
            ->call('generateLink')
            ->assertHasErrors(['optionBaseValue.0']);

        $this->assertNull(QuickPayment::first());
    }

    public function test_admin_cannot_generate_link_when_option_with_items_totals_zero(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin);

        Livewire::test(QuickPaymentBuilder::class)
            ->set('type', 'trabalhos_gerais')
            ->set('optionItems.0', [
                ['name' => 'Item sem preço', 'quantity' => 1, 'unit_price' => 0, 'supplier_id' => null],
            ])
            ->call('generateLink')
            ->assertHasErrors(['generateLink']);

        $this->assertNull(QuickPayment::first());
    }

    public function test_admin_can_add_animal_from_catalog(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin);

        $animal = \App\Models\Animal::create([
            'name' => 'Galo',
            'unit' => 'unidade',
            'active' => true,
        ]);
        $supplier = \App\Models\Supplier::create(['name' => 'Fornecedor Teste', 'type' => 'animais']);
        \App\Models\AnimalPrice::create([
            'animal_id' => $animal->id,
            'supplier_id' => $supplier->id,
            'unit_price' => 80.00,
            'purchase_date' => now(),
        ]);

        Livewire::test(QuickPaymentBuilder::class)
            ->call('selectAnimalItemById', $animal->id)
            ->assertSet('optionItems.0.0.name', 'Galo')
            ->assertSet('optionItems.0.0.unit_price', 80.00)
            ->assertSet('optionItems.0.0.animal_id', $animal->id);
    }

    public function test_guest_cannot_pay_an_already_paid_link(): void
    {
        $qp = QuickPayment::create([
            'uuid' => 'already-paid-uuid',
            'type' => 'consulta',
            'items' => [],
            'markup_percent' => 0,
            'fee_percent' => 0,
            'subtotal' => 100.00,
            'total' => 100.00,
            'status' => 'paid',
        ]);

        Livewire::test(PublicQuickCheckout::class, ['uuid' => 'already-paid-uuid'])
            ->set('registerName', 'Outra Pessoa')
            ->set('registerMotherName', 'Mãe')
            ->set('registerBirthdate', '25/12/1988')
            ->set('registerCountry', 'BR')
            ->set('registerCpf', '123.456.789-01')
            ->set('registerEmail', 'outra@example.com')
            ->set('registerPhone', '(11) 98888-7777')
            ->set('registerPassword', 'password123')
            ->set('registerPasswordConfirmation', 'password123')
            ->set('registerConsent', true)
            ->call('registerAndPay');

        $this->assertNull(User::where('email', 'outra@example.com')->first());
        $this->assertSame(0, Payment::where('payable_id', $qp->id)->count());
    }

    public function test_consultation_detail_auto_saves_draft_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $client = User::factory()->create(['role' => 'consulente', 'status' => 'active']);
        
        $request = ConsultationRequest::create([
            'user_id' => $client->id,
            'modality' => 'online',
            'status' => 'paid',
        ]);

        $this->actingAs($admin);

        Livewire::test(ConsultationDetail::class, ['consultationRequest' => $request])
            ->set('general_guidance', 'Test general guidance')
            ->assertHasNoErrors();

        $request->refresh();
        $this->assertNotNull($request->draft_data);
        $this->assertSame('Test general guidance', $request->draft_data['general_guidance']);
    }

    public function test_admin_can_save_items_as_ritual_work_template(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin);

        Livewire::test(QuickPaymentBuilder::class)
            ->set('optionItems.0', [
                [
                    'name' => 'Vela de 7 Dias',
                    'quantity' => 2.0,
                    'unit_price' => 15.0,
                    'supplier_id' => null,
                    'supplier_name' => null,
                ]
            ])
            ->set('newTemplateName', 'Modelo Vela Teste')
            ->set('newTemplateDescription', 'Descrição de teste')
            ->call('saveAsTemplate')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('ritual_works', [
            'name' => 'Modelo Vela Teste',
            'description' => 'Descrição de teste',
        ]);

        $ritualWork = \App\Models\RitualWork::where('name', 'Modelo Vela Teste')->first();
        $this->assertNotNull($ritualWork);
        $this->assertCount(1, $ritualWork->supplies);
        $this->assertSame('Vela de 7 Dias', $ritualWork->supplies->first()->name);
        $this->assertEquals(2.0, $ritualWork->supplies->first()->pivot->default_quantity);
    }

    public function test_admin_can_load_items_from_ritual_work_template(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin);

        $supply = \App\Models\Supply::create([
            'name' => 'Insumo Teste',
            'category' => 'Artigos Religiosos',
            'unit' => 'unidade',
            'active' => true,
        ]);

        $ritualWork = \App\Models\RitualWork::create([
            'name' => 'Modelo Teste Carregar',
            'description' => 'Descrição',
            'estimated_days' => 1,
            'active' => true,
        ]);
        $ritualWork->supplies()->attach($supply->id, ['default_quantity' => 5.0]);

        Livewire::test(QuickPaymentBuilder::class)
            ->call('loadTemplate', $ritualWork->id)
            ->assertHasNoErrors()
            ->assertSet('optionItems.0.0.name', 'Insumo Teste')
            ->assertSet('optionItems.0.0.quantity', 5.0);
    }

    public function test_admin_opens_save_template_modal_prefills_name_with_notes(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin);

        Livewire::test(QuickPaymentBuilder::class)
            ->set('notes', 'Ebó para caminhos abertos')
            ->call('openSaveTemplateModal')
            ->assertSet('newTemplateName', 'Ebó para caminhos abertos')
            ->assertSet('showSaveTemplateModal', true);
    }

    public function test_admin_can_generate_link_with_multiple_options(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin);

        Livewire::test(QuickPaymentBuilder::class)
            ->set('type', 'trabalhos_gerais')
            ->set('optionBaseValue.0', '100.00')
            ->set('optionBaseValue.1', '200.00')
            ->set('optionComments.1', 'Versão completa com ervas importadas')
            ->call('generateLink')
            ->assertHasNoErrors();

        $qp = QuickPayment::first();
        $this->assertNotNull($qp);
        $this->assertCount(2, $qp->filledOptions());
        $this->assertNull($qp->options[2]);
        $this->assertEquals(100.00, $qp->options[0]['total']);
        $this->assertEquals(200.00, $qp->options[1]['total']);
        $this->assertSame('Versão completa com ervas importadas', $qp->options[1]['comment']);
        // Subtotal/total on the record itself always mirror option 1 until the client picks one
        $this->assertEquals(100.00, $qp->total);
    }

    public function test_guest_choosing_an_option_notifies_admins_and_locks_it_in(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $qp = QuickPayment::create([
            'uuid' => 'multi-option-uuid',
            'type' => 'trabalhos_gerais',
            'items' => [],
            'options' => [
                ['items' => [], 'subtotal' => 100.00, 'total' => 100.00, 'comment' => 'Básica'],
                ['items' => [], 'subtotal' => 200.00, 'total' => 200.00, 'comment' => 'Completa'],
                null,
            ],
            'markup_percent' => 0,
            'fee_percent' => 0,
            'subtotal' => 100.00,
            'total' => 100.00,
            'status' => 'pending',
        ]);

        Livewire::test(PublicQuickCheckout::class, ['uuid' => 'multi-option-uuid'])
            ->assertSet('selectedOptionIndex', null)
            ->call('selectOption', 1)
            ->assertSet('selectedOptionIndex', 1)
            ->set('registerName', 'Consulente Teste')
            ->set('registerMotherName', 'Mãe do Consulente')
            ->set('registerBirthdate', '25/12/1988')
            ->set('registerCountry', 'BR')
            ->set('registerCpf', '123.456.789-01')
            ->set('registerEmail', 'opcoes@example.com')
            ->set('registerPhone', '(11) 98888-7777')
            ->set('registerPassword', 'password123')
            ->set('registerPasswordConfirmation', 'password123')
            ->set('registerConsent', true)
            ->call('registerAndPay')
            ->assertHasNoErrors();

        $qp->refresh();
        $this->assertSame(1, $qp->selected_option_index);
        $this->assertEquals(200.00, $qp->total);

        Mail::assertSent(QuickPaymentOptionSelectedAdminMail::class, function ($mail) use ($admin) {
            return $mail->hasTo($admin->email) && $mail->optionIndex === 1;
        });

        $payment = Payment::where('payable_id', $qp->id)->first();
        $this->assertEquals(200.00, $payment->amount);
    }

    public function test_single_option_link_skips_the_choice_screen(): void
    {
        $qp = QuickPayment::create([
            'uuid' => 'single-option-uuid',
            'type' => 'trabalhos_gerais',
            'items' => [],
            'options' => [
                ['items' => [], 'subtotal' => 100.00, 'total' => 100.00, 'comment' => null],
                null,
                null,
            ],
            'markup_percent' => 0,
            'fee_percent' => 0,
            'subtotal' => 100.00,
            'total' => 100.00,
            'status' => 'pending',
        ]);

        Livewire::test(PublicQuickCheckout::class, ['uuid' => 'single-option-uuid'])
            ->assertSet('selectedOptionIndex', 0);
    }

    public function test_admin_can_mark_item_as_by_client_and_it_persists_on_the_link(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin);

        Livewire::test(QuickPaymentBuilder::class)
            ->set('type', 'trabalhos_gerais')
            ->set('optionItems.0', [
                [
                    'name' => 'Tecido branco',
                    'quantity' => 1,
                    'unit_price' => 30.00,
                    'supplier_id' => null,
                    'by_client' => true,
                ],
            ])
            ->call('generateLink')
            ->assertHasNoErrors();

        $qp = QuickPayment::first();
        $this->assertNotNull($qp);
        $this->assertTrue($qp->options[0]['items'][0]['by_client']);
    }

    public function test_load_template_also_brings_animals_from_ritual_work(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin);

        $animal = \App\Models\Animal::create(['name' => 'Cabra', 'active' => true]);
        $ritualWork = \App\Models\RitualWork::create(['name' => 'Modelo com animal', 'active' => true]);
        $ritualWork->animals()->attach($animal->id, ['default_quantity' => 1]);

        Livewire::test(QuickPaymentBuilder::class)
            ->call('loadTemplate', $ritualWork->id)
            ->assertHasNoErrors()
            ->assertSet('optionItems.0.0.name', 'Cabra')
            ->assertSet('optionItems.0.0.animal_id', $animal->id);
    }

    public function test_checkout_requires_materials_confirmation_when_item_is_by_client(): void
    {
        $qp = QuickPayment::create([
            'uuid' => 'materials-uuid',
            'type' => 'trabalhos_gerais',
            'items' => [
                ['name' => 'Tecido branco', 'quantity' => 1, 'unit_price' => 30.00, 'by_client' => true],
            ],
            'options' => [
                ['items' => [
                    ['name' => 'Tecido branco', 'quantity' => 1, 'unit_price' => 30.00, 'by_client' => true],
                ], 'subtotal' => 30.00, 'total' => 30.00, 'comment' => null],
                null,
                null,
            ],
            'markup_percent' => 0,
            'fee_percent' => 0,
            'subtotal' => 30.00,
            'total' => 30.00,
            'status' => 'pending',
        ]);

        $component = Livewire::test(PublicQuickCheckout::class, ['uuid' => 'materials-uuid'])
            ->assertSet('selectedOptionIndex', 0)
            ->set('registerName', 'Consulente Teste')
            ->set('registerMotherName', 'Mãe do Consulente')
            ->set('registerBirthdate', '25/12/1988')
            ->set('registerCountry', 'BR')
            ->set('registerCpf', '123.456.789-01')
            ->set('registerEmail', 'materiais@example.com')
            ->set('registerPhone', '(11) 98888-7777')
            ->set('registerPassword', 'password123')
            ->set('registerPasswordConfirmation', 'password123')
            ->set('registerConsent', true)
            ->call('registerAndPay')
            ->assertHasErrors(['materialsConfirmed']);

        $this->assertSame(0, Payment::where('payable_id', $qp->id)->count());

        $component->set('materialsConfirmed', true)
            ->call('processPayment')
            ->assertHasNoErrors();

        $this->assertSame(1, Payment::where('payable_id', $qp->id)->count());
    }

    public function test_checkout_reuses_the_pending_payment_even_if_a_different_account_completes_it(): void
    {
        $qp = QuickPayment::create([
            'uuid' => 'diff-email-uuid',
            'type' => 'trabalhos_gerais',
            'items' => [],
            'markup_percent' => 0,
            'fee_percent' => 0,
            'subtotal' => 100,
            'total' => 100,
            'status' => 'pending',
        ]);

        // The link is sent to one client, but a different account (a
        // relative, a typo'd email leading to a fresh signup, etc.) starts
        // the checkout first.
        $firstUser = User::factory()->create(['role' => 'consulente', 'status' => 'active']);
        Livewire::actingAs($firstUser)
            ->test(PublicQuickCheckout::class, ['uuid' => 'diff-email-uuid'])
            ->call('processPayment');

        $this->assertSame(1, Payment::where('payable_type', QuickPayment::class)->where('payable_id', $qp->id)->count());

        // A second, different account actually completes the payment for
        // the very same link.
        $secondUser = User::factory()->create(['role' => 'consulente', 'status' => 'active']);
        Livewire::actingAs($secondUser)
            ->test(PublicQuickCheckout::class, ['uuid' => 'diff-email-uuid'])
            ->call('processPayment');

        // Still a single payment row for this link - reassigned to whoever
        // actually completed it, not duplicated per account.
        $this->assertSame(1, Payment::where('payable_type', QuickPayment::class)->where('payable_id', $qp->id)->count());
        $this->assertDatabaseHas('payments', [
            'payable_type' => QuickPayment::class,
            'payable_id' => $qp->id,
            'user_id' => $secondUser->id,
        ]);
    }

    public function test_admin_cannot_view_consultation_registered_by_another_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $otherAdmin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $client = User::factory()->create(['role' => 'consulente', 'status' => 'active']);

        $request = ConsultationRequest::create([
            'user_id' => $client->id,
            'modality' => 'online',
            'status' => 'paid',
        ]);

        $odu = \App\Models\Odu::create(['name' => 'Ogbè Méjì', 'number' => 1, 'category' => 'meji']);
        $ireType = \App\Models\IreIbiType::create(['classification' => 'ire', 'name' => 'Ire Owó']);

        \App\Models\Consultation::create([
            'request_id' => $request->id,
            'user_id' => $client->id,
            'admin_id' => $otherAdmin->id,
            'consulted_at' => now(),
            'modality' => 'online',
            'odu_main_id' => $odu->id,
            'ire_ibi' => 'ire',
            'ire_ibi_type_id' => $ireType->id,
            'created_by' => $otherAdmin->id,
        ]);

        $this->actingAs($admin);

        Livewire::test(ConsultationDetail::class, ['consultationRequest' => $request])
            ->assertForbidden();
    }
}
