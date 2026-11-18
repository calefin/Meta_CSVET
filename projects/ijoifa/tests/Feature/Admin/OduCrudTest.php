<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Catalogs\Odus;
use App\Models\Odu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OduCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_an_odu(): void
    {
        $admin = User::factory()->create([
            'mother_maiden_name' => 'Teste',
            'role' => 'admin',
            'status' => 'active',
        ]);

        Livewire::actingAs($admin)
            ->test(Odus::class)
            ->call('create')
            ->set('name', 'Ọ̀sá Méjì')
            ->set('number', 17)
            ->set('category', 'meji')
            ->call('save')
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('odus', [
            'name' => 'Ọ̀sá Méjì',
            'number' => 17,
            'category' => 'meji',
        ]);
    }

    public function test_admin_can_edit_an_odu(): void
    {
        $admin = User::factory()->create([
            'mother_maiden_name' => 'Teste',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $odu = Odu::create(['name' => 'Original', 'number' => 1, 'category' => 'meji']);

        Livewire::actingAs($admin)
            ->test(Odus::class)
            ->call('edit', $odu->id)
            ->set('name', 'Atualizado')
            ->call('save');

        $this->assertDatabaseHas('odus', ['id' => $odu->id, 'name' => 'Atualizado']);
    }
}
