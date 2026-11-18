<?php

namespace Tests\Feature;

use App\Livewire\Admin\Oracular\PendingFulfillments;
use App\Models\ConsultationRequest;
use App\Models\QuickPayment;
use App\Models\RecommendedWork;
use App\Models\RitualWork;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PendingFulfillmentsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'admin', 'status' => 'active']);
    }

    public function test_lists_paid_recommended_works_quick_payments_and_consultations(): void
    {
        $admin = $this->admin();

        $client = User::factory()->create(['name' => 'Maria Teste', 'phone' => '11999990000', 'role' => 'consulente', 'status' => 'active']);
        $odu = \App\Models\Odu::create(['name' => 'Ogbè Méjì', 'number' => 1, 'category' => 'meji']);
        $ireIbiType = \App\Models\IreIbiType::create(['classification' => 'ire', 'name' => 'Ire Owó']);
        $consultation = $client->consultations()->create([
            'user_id' => $client->id, 'admin_id' => $admin->id, 'consulted_at' => now(),
            'modality' => 'online', 'odu_main_id' => $odu->id,
            'ire_ibi' => 'ire', 'ire_ibi_type_id' => $ireIbiType->id,
            'general_guidance' => 'teste', 'created_by' => $admin->id,
        ]);
        $ritualWork = RitualWork::create(['name' => 'Ebó Riru', 'active' => true]);
        $work = $consultation->recommendedWorks()->create([
            'user_id' => $client->id, 'ritual_work_id' => $ritualWork->id, 'notes' => 'Ajuste de caminhos', 'status' => 'approved',
        ]);

        $qpClient = User::factory()->create(['name' => 'João Teste', 'phone' => '11988880000', 'role' => 'consulente', 'status' => 'active']);
        $qp = QuickPayment::create([
            'type' => 'trabalhos_gerais', 'items' => [], 'options' => [null, null, null],
            'markup_percent' => 0, 'fee_percent' => 0, 'subtotal' => 150, 'total' => 150,
            'status' => 'paid', 'user_id' => $qpClient->id, 'notes' => 'Trabalho para prosperidade',
        ]);

        $consultRequest = $client->consultationRequests()->create([
            'modality' => 'online', 'description' => 'Quero saber sobre minha saúde', 'status' => 'paid',
        ]);

        Livewire::actingAs($admin)
            ->test(PendingFulfillments::class)
            ->assertSee('Maria Teste')
            ->assertSee('Ajuste de caminhos')
            ->assertSee('João Teste')
            ->assertSee('Trabalho para prosperidade')
            ->assertSee('11988880000')
            ->assertSee('Quero saber sobre minha saúde');
    }

    public function test_mark_recommended_work_done_moves_it_out_of_the_list(): void
    {
        $admin = $this->admin();
        $client = User::factory()->create(['role' => 'consulente', 'status' => 'active']);
        $odu = \App\Models\Odu::create(['name' => 'Ogbè Méjì', 'number' => 1, 'category' => 'meji']);
        $ireIbiType = \App\Models\IreIbiType::create(['classification' => 'ire', 'name' => 'Ire Owó']);
        $consultation = $client->consultations()->create([
            'user_id' => $client->id, 'admin_id' => $admin->id, 'consulted_at' => now(),
            'modality' => 'online', 'odu_main_id' => $odu->id,
            'ire_ibi' => 'ire', 'ire_ibi_type_id' => $ireIbiType->id,
            'general_guidance' => 'teste', 'created_by' => $admin->id,
        ]);
        $ritualWork = RitualWork::create(['name' => 'Ebó Riru', 'active' => true]);
        $work = $consultation->recommendedWorks()->create([
            'user_id' => $client->id, 'ritual_work_id' => $ritualWork->id, 'status' => 'approved',
        ]);

        Livewire::actingAs($admin)
            ->test(PendingFulfillments::class)
            ->call('markRecommendedWorkDone', $work->id);

        $this->assertDatabaseHas('recommended_works', ['id' => $work->id, 'status' => 'completed']);
    }

    public function test_mark_quick_payment_done_moves_it_out_of_the_list(): void
    {
        $admin = $this->admin();
        $client = User::factory()->create(['role' => 'consulente', 'status' => 'active']);
        $qp = QuickPayment::create([
            'type' => 'trabalhos_gerais', 'items' => [], 'options' => [null, null, null],
            'markup_percent' => 0, 'fee_percent' => 0, 'subtotal' => 150, 'total' => 150,
            'status' => 'paid', 'user_id' => $client->id,
        ]);

        Livewire::actingAs($admin)
            ->test(PendingFulfillments::class)
            ->call('markQuickPaymentDone', $qp->id);

        $this->assertDatabaseHas('quick_payments', ['id' => $qp->id, 'status' => 'completed']);
    }
}
