<?php

namespace Tests\Feature;

use App\Livewire\Admin\Oracular\WorksDashboard;
use App\Models\Budget;
use App\Models\IreIbiType;
use App\Models\Odu;
use App\Models\Payment;
use App\Models\QuickPayment;
use App\Models\RecommendedWork;
use App\Models\RitualWork;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WorksDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_recommended_works_and_quick_payments_together(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $clientA = User::factory()->create(['role' => 'consulente', 'status' => 'active', 'name' => 'Ana Consulente']);
        $clientB = User::factory()->create(['role' => 'consulente', 'status' => 'active', 'name' => 'Beto Consulente']);

        $odu = Odu::create(['name' => 'Ogbè Méjì', 'number' => 1, 'category' => 'meji']);
        $ireType = IreIbiType::create(['classification' => 'ire', 'name' => 'Ire Owó']);
        $ritualWork = RitualWork::create(['name' => 'Ẹbọ Riru', 'active' => true]);
        $request = $clientA->consultationRequests()->create(['modality' => 'online', 'description' => 'teste', 'status' => 'paid']);
        $consultation = $request->consultation()->create([
            'user_id' => $clientA->id, 'admin_id' => $admin->id, 'consulted_at' => now(),
            'modality' => 'online', 'odu_main_id' => $odu->id, 'ire_ibi' => 'ire',
            'ire_ibi_type_id' => $ireType->id, 'created_by' => $admin->id,
        ]);
        $work = $consultation->recommendedWorks()->create([
            'user_id' => $clientA->id, 'ritual_work_id' => $ritualWork->id, 'status' => 'budget_requested',
        ]);
        Budget::create([
            'recommended_work_id' => $work->id, 'user_id' => $clientA->id, 'admin_id' => $admin->id,
            'markup_percent' => 0, 'fee_percent' => 0, 'subtotal' => 120, 'total' => 120, 'status' => 'sent',
        ]);

        $qp = QuickPayment::create([
            'type' => 'trabalhos_gerais', 'user_id' => $clientB->id,
            'items' => [], 'markup_percent' => 0, 'fee_percent' => 0,
            'subtotal' => 80, 'total' => 80, 'status' => 'pending',
        ]);

        $component = Livewire::actingAs($admin)->test(WorksDashboard::class);

        $component->assertSee('Ana Consulente')
            ->assertSee('Beto Consulente')
            ->assertSee('R$ 120,00')
            ->assertSee('R$ 80,00');
    }

    public function test_mark_paid_action_works_for_both_sources(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $client = User::factory()->create(['role' => 'consulente', 'status' => 'active']);

        $qp = QuickPayment::create([
            'type' => 'trabalhos_gerais', 'user_id' => $client->id,
            'items' => [], 'markup_percent' => 0, 'fee_percent' => 0,
            'subtotal' => 80, 'total' => 80, 'status' => 'pending',
        ]);
        $payment = Payment::create([
            'user_id' => $client->id, 'payable_type' => QuickPayment::class,
            'payable_id' => $qp->id, 'amount' => 80, 'status' => 'pending',
        ]);

        Livewire::actingAs($admin)->test(WorksDashboard::class)
            ->call('markPaid', 'quick_payment', $qp->id);

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid']);
        $this->assertDatabaseHas('quick_payments', ['id' => $qp->id, 'status' => 'paid']);
    }

    public function test_cancel_action_cancels_quick_payment(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $client = User::factory()->create(['role' => 'consulente', 'status' => 'active']);

        $qp = QuickPayment::create([
            'type' => 'trabalhos_gerais', 'user_id' => $client->id,
            'items' => [], 'markup_percent' => 0, 'fee_percent' => 0,
            'subtotal' => 80, 'total' => 80, 'status' => 'pending',
        ]);

        Livewire::actingAs($admin)->test(WorksDashboard::class)
            ->call('cancel', 'quick_payment', $qp->id);

        $this->assertDatabaseHas('quick_payments', ['id' => $qp->id, 'status' => 'cancelled']);
    }
}
