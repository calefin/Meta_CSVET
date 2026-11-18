<?php

namespace Tests\Feature;

use App\Livewire\Admin\FinancialDashboard;
use App\Livewire\Admin\Oracular\RequestsQueue;
use App\Livewire\Admin\PriceMonitor;
use App\Models\Animal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FinancialDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'admin', 'status' => 'active']);
    }

    public function test_confirming_a_consultation_request_creates_a_payment_and_shows_up_as_revenue(): void
    {
        $admin = $this->admin();
        $consulente = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'consulente', 'status' => 'active']);

        $request = $consulente->consultationRequests()->create([
            'modality' => 'online', 'description' => 'teste', 'status' => 'pending',
        ]);

        Livewire::actingAs($admin)
            ->test(RequestsQueue::class)
            ->call('showConfirmForm', $request->id)
            ->set('confirmAmount', '200')
            ->call('confirm')
            ->call('markPaid', $request->id);

        $this->assertDatabaseHas('payments', [
            'payable_type' => \App\Models\ConsultationRequest::class,
            'payable_id' => $request->id,
            'status' => 'paid',
            'amount' => 200,
        ]);

        Livewire::actingAs($admin)
            ->test(FinancialDashboard::class)
            ->assertSee('200,00');
    }

    public function test_admin_can_register_an_expense_and_see_it_in_the_period(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(FinancialDashboard::class)
            ->call('openExpenseForm')
            ->set('expenseCategory', 'Insumos')
            ->set('expenseDescription', 'Velas e incenso')
            ->set('expenseAmount', '85.50')
            ->set('expenseDate', now()->format('Y-m-d'))
            ->call('saveExpense')
            ->assertSee('85,50');

        $this->assertDatabaseHas('expenses', ['category' => 'Insumos', 'amount' => 85.50]);
    }

    public function test_price_monitor_flags_large_variation(): void
    {
        $admin = $this->admin();
        $animal = Animal::create(['name' => 'Galo', 'active' => true]);
        $animal->prices()->create(['unit_price' => 50, 'purchase_date' => now()->subMonth()]);
        $animal->prices()->create(['unit_price' => 80, 'purchase_date' => now()]);

        Livewire::actingAs($admin)
            ->test(PriceMonitor::class)
            ->assertSee('Galo')
            ->assertSee('60');
    }

    public function test_price_monitor_can_import_json_price_table(): void
    {
        $admin = $this->admin();
        
        $jsonContent = json_encode([
            'supplier_name' => 'Fornecedor da Bahia',
            'purchase_date' => '2026-06-21',
            'prices' => [
                [
                    'type' => 'animal',
                    'name' => 'Frango Importado',
                    'price' => 45.50,
                    'payment_method' => 'dinheiro',
                    'min_quantity' => 2
                ],
                [
                    'type' => 'supply',
                    'name' => 'Incenso Importado',
                    'price' => 15.00,
                    'payment_method' => 'credito',
                    'min_quantity' => 10
                ]
            ]
        ]);

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('prices.json', $jsonContent);

        Livewire::actingAs($admin)
            ->test(PriceMonitor::class)
            ->set('jsonFile', $file)
            ->call('importJson')
            ->assertHasNoErrors('jsonFile');

        $this->assertDatabaseHas('suppliers', ['name' => 'Fornecedor da Bahia']);
        $this->assertDatabaseHas('animals', ['name' => 'Frango Importado']);
        $this->assertDatabaseHas('supplies', ['name' => 'Incenso Importado']);

        $this->assertDatabaseHas('animal_prices', [
            'unit_price' => 45.50,
            'payment_method' => 'dinheiro',
            'min_quantity' => 2,
            'purchase_date' => '2026-06-21 00:00:00'
        ]);

        $this->assertDatabaseHas('supply_prices', [
            'unit_price' => 15.00,
            'payment_method' => 'credito',
            'min_quantity' => 10,
            'purchase_date' => '2026-06-21 00:00:00'
        ]);
    }
}
