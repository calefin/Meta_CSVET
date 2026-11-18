<?php

namespace Tests\Feature;

use App\Livewire\Admin\UserManagement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_edit_a_user(): void
    {
        $admin = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'admin', 'status' => 'active']);
        $target = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'consulente', 'status' => 'pending']);

        Livewire::actingAs($admin)
            ->test(UserManagement::class)
            ->call('edit', $target->id)
            ->set('role', 'iniciado')
            ->set('status', 'active')
            ->call('save');

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'role' => 'iniciado',
            'status' => 'active',
        ]);
    }

    public function test_consulente_cannot_access_user_management(): void
    {
        $consulente = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'consulente', 'status' => 'active']);

        $this->actingAs($consulente)->get(route('admin.users'))->assertForbidden();
    }
}
