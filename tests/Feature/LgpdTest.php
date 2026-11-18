<?php

namespace Tests\Feature;

use App\Livewire\Admin\DeletionRequests;
use App\Livewire\Shared\MyData;
use App\Models\Consultation;
use App\Models\IreIbiType;
use App\Models\Odu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LgpdTest extends TestCase
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

    public function test_user_can_request_data_deletion_and_admin_can_resolve_it(): void
    {
        $consulente = $this->consulente();

        Livewire::actingAs($consulente)
            ->test(MyData::class)
            ->set('deletionNotes', 'Quero remover meus dados')
            ->call('requestDeletion');

        $this->assertDatabaseHas('deletion_requests', [
            'user_id' => $consulente->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'deletion_request.create']);

        $admin = $this->admin();
        $request = \App\Models\DeletionRequest::first();

        Livewire::actingAs($admin)
            ->test(DeletionRequests::class)
            ->call('resolve', $request->id, 'completed');

        $this->assertDatabaseHas('deletion_requests', ['id' => $request->id, 'status' => 'completed']);
    }

    public function test_user_can_export_own_data_as_json_and_pdf(): void
    {
        $consulente = $this->consulente();

        $this->actingAs($consulente)->get(route('my-data.export.json'))->assertOk()
            ->assertJsonStructure(['perfil', 'consultas', 'agendamentos']);

        $this->actingAs($consulente)->get(route('my-data.export.pdf'))->assertOk();
    }

    public function test_data_export_never_includes_life_areas(): void
    {
        $consulente = $this->consulente();
        $admin = $this->admin();
        $odu = Odu::create(['name' => 'Ogbè Méjì', 'number' => 1, 'category' => 'meji']);
        $ireType = IreIbiType::create(['classification' => 'ire', 'name' => 'Ire Owó']);

        $request = $consulente->consultationRequests()->create(['modality' => 'online', 'description' => 'x', 'status' => 'paid']);
        $consultation = $request->consultation()->create([
            'user_id' => $consulente->id, 'admin_id' => $admin->id, 'consulted_at' => now(),
            'modality' => 'online', 'odu_main_id' => $odu->id, 'ire_ibi' => 'ire',
            'ire_ibi_type_id' => $ireType->id, 'created_by' => $admin->id,
        ]);
        $consultation->lifeAreas()->create(['area' => 'saude', 'odu_1_id' => $odu->id, 'notes' => 'segredo interno']);

        $response = $this->actingAs($consulente)->get(route('my-data.export.json'));

        $response->assertOk();
        $response->assertDontSee('segredo interno');
        $response->assertDontSee('lifeAreas');
    }

    public function test_blocked_odu_edit_attempt_is_recorded_in_audit_log(): void
    {
        $admin = $this->admin();
        $consulente = $this->consulente();
        $odu1 = Odu::create(['name' => 'Ogbè Méjì', 'number' => 1, 'category' => 'meji']);
        $odu2 = Odu::create(['name' => 'Ọ̀yẹ̀kú Méjì', 'number' => 2, 'category' => 'meji']);
        $ireType = IreIbiType::create(['classification' => 'ire', 'name' => 'Ire Owó']);

        $consultation = Consultation::create([
            'user_id' => $consulente->id, 'admin_id' => $admin->id, 'consulted_at' => now(),
            'modality' => 'online', 'odu_main_id' => $odu1->id, 'ire_ibi' => 'ire',
            'ire_ibi_type_id' => $ireType->id, 'created_by' => $admin->id,
        ]);

        try {
            $consultation->update(['odu_main_id' => $odu2->id]);
        } catch (\RuntimeException $e) {
            // expected
        }

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'consultation.blocked_edit_attempt',
            'model_id' => $consultation->id,
        ]);
    }
}
