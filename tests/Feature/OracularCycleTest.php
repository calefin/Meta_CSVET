<?php

namespace Tests\Feature;

use App\Models\IreIbiType;
use App\Models\Odu;
use App\Models\RitualWork;
use App\Models\User;
use App\Livewire\Admin\Oracular\ConsultationDetail;
use App\Livewire\Consulente\Consultations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OracularCycleTest extends TestCase
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

    public function test_consulente_can_request_a_consultation(): void
    {
        $consulente = $this->consulente();

        Livewire::actingAs($consulente)
            ->test(Consultations::class)
            ->call('openRequestForm')
            ->set('modality', 'online')
            ->set('description', 'Preciso de orientacao sobre um momento dificil.')
            ->call('submitRequest');

        $this->assertDatabaseHas('consultation_requests', [
            'user_id' => $consulente->id,
            'modality' => 'online',
            'status' => 'pending',
        ]);
    }

    public function test_full_cycle_from_request_to_registered_consultation_with_recommended_work(): void
    {
        $admin = $this->admin();
        $consulente = $this->consulente();

        $oduMain = Odu::create(['name' => 'Ogbè Méjì', 'number' => 1, 'category' => 'meji']);
        $ireType = IreIbiType::create(['classification' => 'ire', 'name' => 'Ire Owó']);
        $work = RitualWork::create(['name' => 'Ẹbọ Riru', 'active' => true]);

        Livewire::actingAs($consulente)
            ->test(Consultations::class)
            ->call('openRequestForm')
            ->set('modality', 'presential')
            ->set('description', 'Solicitacao de teste')
            ->call('submitRequest');

        $request = $consulente->consultationRequests()->first();
        $this->assertNotNull($request);

        // Admin confirms and marks paid (manual placeholder until Mercado Pago)
        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Livewire\Admin\Oracular\RequestsQueue::class)
            ->call('showConfirmForm', $request->id)
            ->set('confirmAmount', '150')
            ->call('confirm')
            ->call('markPaid', $request->id);

        $request->refresh();
        $this->assertSame('paid', $request->status);

        // Admin registers the consultation
        $component = Livewire::actingAs($admin)->test(ConsultationDetail::class, ['consultationRequest' => $request]);

        $component->set('odu_main_id', $oduMain->id)
            ->set('ire_ibi', 'ire')
            ->set('ire_ibi_type_id', $ireType->id)
            ->set('general_guidance', 'Mantenha-se firme.');

        foreach (['espiritual', 'trabalho', 'relacionamento', 'saude', 'dinheiro'] as $area) {
            $component->set("lifeAreas.{$area}.odu_1_id", $oduMain->id);
        }

        $component->call('registerConsultation');

        $request->refresh();
        $this->assertSame('attended', $request->status);
        $this->assertDatabaseHas('consultations', [
            'request_id' => $request->id,
            'odu_main_id' => $oduMain->id,
            'ire_ibi' => 'ire',
        ]);
        $this->assertDatabaseCount('consultation_life_areas', 5);

        // Recommend a work
        $component->set('newWorkId', $work->id)
            ->set('newWorkNotes', 'Realizar em ate 7 dias')
            ->call('addRecommendedWork');

        $this->assertDatabaseHas('recommended_works', [
            'ritual_work_id' => $work->id,
            'user_id' => $consulente->id,
            'status' => 'recommended',
        ]);

        // Consulente sees the 3-Odu summary but the underlying life areas stay admin-only
        // (life_areas relation is never loaded/rendered on the consulente view).
        Livewire::actingAs($consulente)
            ->test(Consultations::class)
            ->assertSee('Ogbè Méjì')
            ->assertSee('Ẹbọ Riru');
    }

    public function test_odu_fields_are_immutable_after_registration_even_via_addendum_flow(): void
    {
        $admin = $this->admin();
        $consulente = $this->consulente();
        $odu1 = Odu::create(['name' => 'Ogbè Méjì', 'number' => 1, 'category' => 'meji']);
        $odu2 = Odu::create(['name' => 'Ọ̀yẹ̀kú Méjì', 'number' => 2, 'category' => 'meji']);
        $ireType = IreIbiType::create(['classification' => 'ire', 'name' => 'Ire Owó']);

        $request = $consulente->consultationRequests()->create([
            'modality' => 'online',
            'description' => 'teste',
            'status' => 'paid',
        ]);

        $consultation = $request->consultation()->create([
            'user_id' => $consulente->id,
            'admin_id' => $admin->id,
            'consulted_at' => now(),
            'modality' => 'online',
            'odu_main_id' => $odu1->id,
            'ire_ibi' => 'ire',
            'ire_ibi_type_id' => $ireType->id,
            'created_by' => $admin->id,
        ]);

        $this->expectException(\RuntimeException::class);
        $consultation->update(['odu_main_id' => $odu2->id]);
    }
}
