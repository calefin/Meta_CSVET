<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Agenda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AgendaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'admin', 'status' => 'active']);
    }

    public function test_admin_can_view_agenda_page(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.agenda'))
            ->assertOk();
    }

    public function test_admin_can_create_an_appointment(): void
    {
        $admin = $this->admin();
        $consulente = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'consulente', 'status' => 'active']);

        Livewire::actingAs($admin)
            ->test(Agenda::class)
            ->call('create')
            ->set('user_id', $consulente->id)
            ->set('scheduled_at', now()->addDay()->format('Y-m-d\TH:i'))
            ->set('modality', 'online')
            ->set('status', 'pending')
            ->call('save');

        $this->assertDatabaseHas('appointments', [
            'user_id' => $consulente->id,
            'admin_id' => $admin->id,
            'modality' => 'online',
            'status' => 'pending',
        ]);
    }

    public function test_consulente_only_sees_own_appointments(): void
    {
        $admin = $this->admin();
        $consulente = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'consulente', 'status' => 'active']);
        $other = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'consulente', 'status' => 'active']);

        $consulente->appointments()->create([
            'admin_id' => $admin->id, 'scheduled_at' => now()->addDay(), 'modality' => 'online', 'status' => 'confirmed',
        ]);
        $other->appointments()->create([
            'admin_id' => $admin->id, 'scheduled_at' => now()->addDay(), 'modality' => 'online', 'status' => 'confirmed',
        ]);

        $response = $this->actingAs($consulente)->get(route('consulente.appointments'));

        $response->assertOk();
        $this->assertCount(1, $consulente->appointments);
    }
}
