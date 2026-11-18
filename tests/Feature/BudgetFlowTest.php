<?php

namespace Tests\Feature;

use App\Livewire\Admin\Oracular\BudgetBuilder;
use App\Livewire\Admin\Oracular\ConsultationDetail;
use App\Livewire\Consulente\Consultations;
use App\Models\Budget;
use App\Models\Consultation;
use App\Models\ConsultationRequest;
use App\Models\IreIbiType;
use App\Models\Odu;
use App\Models\Payment;
use App\Models\RecommendedWork;
use App\Models\Animal;
use App\Models\RitualWork;
use App\Models\Supply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BudgetFlowTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'admin', 'status' => 'active']);
    }

    private function consulente(): User
    {
        return User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'consulente', 'status' => 'active']);
    }

    private function makeRecommendedWork(User $consulente, User $admin): RecommendedWork
    {
        $odu = Odu::create(['name' => 'Ogbè Méjì', 'number' => 1, 'category' => 'meji']);
        $ireType = IreIbiType::create(['classification' => 'ire', 'name' => 'Ire Owó']);
        $work = RitualWork::create(['name' => 'Ẹbọ Riru', 'active' => true]);

        $request = $consulente->consultationRequests()->create([
            'modality' => 'online', 'description' => 'teste', 'status' => 'paid',
        ]);

        $consultation = $request->consultation()->create([
            'user_id' => $consulente->id, 'admin_id' => $admin->id, 'consulted_at' => now(),
            'modality' => 'online', 'odu_main_id' => $odu->id, 'ire_ibi' => 'ire',
            'ire_ibi_type_id' => $ireType->id, 'created_by' => $admin->id,
        ]);

        return $consultation->recommendedWorks()->create([
            'user_id' => $consulente->id, 'ritual_work_id' => $work->id, 'status' => 'recommended',
        ]);
    }

    public function test_full_budget_flow_from_request_to_payment_confirmation(): void
    {
        $admin = $this->admin();
        $consulente = $this->consulente();
        $work = $this->makeRecommendedWork($consulente, $admin);
        $supply = Supply::create(['name' => 'Vela branca', 'active' => true]);

        // Consulente requests the budget
        Livewire::actingAs($consulente)
            ->test(Consultations::class)
            ->call('requestBudget', $work->id);

        $work->refresh();
        $this->assertSame('budget_requested', $work->status);

        // Admin builds and sends the budget
        $component = Livewire::actingAs($admin)->test(BudgetBuilder::class, ['recommendedWork' => $work]);
        $component->call('addSupplyRow', $supply->id)
            ->set('optionItems.0.0.quantity', '2')
            ->set('optionItems.0.0.unit_price', '10')
            ->set('markup_percent.0', '20')
            ->set('fee_percent.0', '5')
            ->call('send');

        $budget = Budget::where('recommended_work_id', $work->id)->first();
        $this->assertNotNull($budget);
        $this->assertSame('sent', $budget->status);
        $this->assertEquals(20.00, $budget->subtotal);
        $this->assertEquals(25.26, $budget->total);

        // Consulente approves the budget and "requests the charge"
        Livewire::actingAs($consulente)
            ->test(Consultations::class)
            ->call('approveBudget', $budget->id);

        $budget->refresh();
        $this->assertSame('approved', $budget->status);
        $this->assertDatabaseHas('payments', [
            'payable_type' => RecommendedWork::class,
            'payable_id' => $work->id,
            'status' => 'pending',
            'amount' => 25.26,
        ]);

        // Admin confirms payment received (manual placeholder until Mercado Pago)
        \Illuminate\Support\Facades\Mail::fake();

        Livewire::actingAs($admin)
            ->test(ConsultationDetail::class, ['consultationRequest' => $work->consultation->request])
            ->call('markWorkPaid', $work->id);

        $work->refresh();
        $this->assertSame('approved', $work->status);
        $this->assertDatabaseHas('payments', [
            'payable_id' => $work->id,
            'status' => 'paid',
        ]);

        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\PaymentConfirmedAdminMail::class, function ($mail) use ($admin) {
            return $mail->hasTo($admin->email);
        });
    }

    public function test_pdf_is_accessible_to_owner_and_admin_but_not_other_consulentes(): void
    {
        $admin = $this->admin();
        $consulente = $this->consulente();
        $stranger = $this->consulente();
        $work = $this->makeRecommendedWork($consulente, $admin);

        $budget = Budget::create([
            'recommended_work_id' => $work->id,
            'user_id' => $consulente->id,
            'admin_id' => $admin->id,
            'markup_percent' => 0,
            'subtotal' => 0,
            'total' => 0,
            'status' => 'sent',
        ]);

        $this->actingAs($admin)->get(route('budgets.pdf', $budget))->assertOk();
        $this->actingAs($consulente)->get(route('budgets.pdf', $budget))->assertOk();
        $this->actingAs($stranger)->get(route('budgets.pdf', $budget))->assertForbidden();
    }

    public function test_admin_can_simulate_work_and_generate_budget_for_client(): void
    {
        $admin = $this->admin();
        $client = $this->consulente();

        $odu = Odu::create(['name' => 'Ogbè Méjì', 'number' => 1, 'category' => 'meji']);
        $ireType = IreIbiType::create(['classification' => 'ire', 'name' => 'Ire Owó']);
        $work = RitualWork::create(['name' => 'Ẹbọ Riru', 'active' => true]);
        $supply = Supply::create(['name' => 'Mel', 'active' => true]);

        $component = Livewire::actingAs($admin)->test(\App\Livewire\Admin\Oracular\WorkSimulator::class);

        $component->set('selectedWorkId', $work->id)
            ->call('addSupplyRow', $supply->id)
            ->set('items.0.quantity', '3')
            ->set('items.0.unit_price', '15')
            ->set('markupPercent', '10')
            ->set('feePercent', '5')
            ->set('selectedClientId', $client->id)
            ->set('notes', 'Simulado via painel')
            ->call('saveBudget');

        $this->assertDatabaseHas('consultations', [
            'user_id' => $client->id,
        ]);

        $this->assertDatabaseHas('recommended_works', [
            'user_id' => $client->id,
            'ritual_work_id' => $work->id,
        ]);

        $this->assertDatabaseHas('budgets', [
            'user_id' => $client->id,
            'markup_percent' => 10.00,
            'fee_percent' => 5.00,
            'notes' => 'Simulado via painel',
            'subtotal' => 45.00,
            'total' => 52.11,
        ]);
    }

    public function test_budget_builder_prefills_animals_from_ritual_work(): void
    {
        $admin = $this->admin();
        $consulente = $this->consulente();
        $work = $this->makeRecommendedWork($consulente, $admin);
        $animal = Animal::create(['name' => 'Cabra', 'active' => true]);
        $work->ritualWork->animals()->attach($animal->id, ['default_quantity' => 1]);

        $component = Livewire::actingAs($admin)
            ->test(BudgetBuilder::class, ['recommendedWork' => $work->fresh(['ritualWork'])]);

        $component->assertSet('optionItems.0.0.item_type', 'animal')
            ->assertSet('optionItems.0.0.item_id', $animal->id)
            ->assertSet('optionItems.0.0.description', 'Cabra');
    }

    public function test_budget_builder_supports_multiple_options_and_client_picks_one(): void
    {
        $admin = $this->admin();
        $consulente = $this->consulente();
        $work = $this->makeRecommendedWork($consulente, $admin);
        $supply = Supply::create(['name' => 'Vela branca', 'active' => true]);

        $component = Livewire::actingAs($admin)->test(BudgetBuilder::class, ['recommendedWork' => $work]);
        $component->call('addSupplyRow', $supply->id)
            ->set('optionItems.0.0.quantity', '2')
            ->set('optionItems.0.0.unit_price', '10')
            ->call('switchOption', 1)
            ->call('addCustomRow')
            ->set('optionItems.1.0.description', 'Trabalho completo com frete')
            ->set('optionItems.1.0.quantity', '1')
            ->set('optionItems.1.0.unit_price', '100')
            ->set('optionComments.1', 'Inclui frete e mão de obra')
            ->call('send');

        $budget = Budget::where('recommended_work_id', $work->id)->first();
        $this->assertNotNull($budget);
        $this->assertTrue($budget->hasMultipleOptions());
        $this->assertEquals(20.00, $budget->options[0]['total']);
        $this->assertEquals(100.00, $budget->options[1]['total']);
        $this->assertSame('Inclui frete e mão de obra', $budget->options[1]['comment']);
        $this->assertNull($budget->options[2]);

        // Consulente must choose an option before approving
        Livewire::actingAs($consulente)
            ->test(Consultations::class)
            ->call('approveBudget', $budget->id)
            ->assertHasErrors('approveBudget');

        $budget->refresh();
        $this->assertSame('sent', $budget->status);

        Livewire::actingAs($consulente)
            ->test(Consultations::class)
            ->call('selectBudgetOption', $budget->id, 1)
            ->call('approveBudget', $budget->id);

        $budget->refresh();
        $this->assertSame('approved', $budget->status);
        $this->assertSame(1, $budget->selected_option_index);
        $this->assertEquals(100.00, $budget->total);
        $this->assertDatabaseHas('payments', [
            'payable_type' => RecommendedWork::class,
            'payable_id' => $work->id,
            'status' => 'pending',
            'amount' => 100.00,
        ]);
    }

    public function test_work_simulator_supply_search_returns_matching_results(): void
    {
        $admin = $this->admin();
        $work = RitualWork::create(['name' => 'Ẹbọ Riru', 'active' => true]);
        Supply::create(['name' => 'Mel de abelha', 'active' => true]);
        Supply::create(['name' => 'Vela branca', 'active' => true]);

        Livewire::actingAs($admin)
            ->test(\App\Livewire\Admin\Oracular\WorkSimulator::class)
            ->set('selectedWorkId', $work->id)
            ->set('supplySearch', 'Mel')
            ->assertSee('Mel de abelha')
            ->assertDontSee('Vela branca');
    }
}
