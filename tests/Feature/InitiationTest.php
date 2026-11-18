<?php

namespace Tests\Feature;

use App\Livewire\Admin\InitiationManager;
use App\Livewire\Iniciado\MyInitiation;
use App\Models\Odu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InitiationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'admin', 'status' => 'active']);
    }

    private function iniciado(): User
    {
        return User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'iniciado', 'status' => 'active']);
    }

    public function test_admin_can_register_initiation_with_day_records_and_ewos(): void
    {
        $admin = $this->admin();
        $iniciado = $this->iniciado();
        $odu = Odu::create(['name' => 'Ogbè Méjì', 'number' => 1, 'category' => 'meji']);
        $oduDay = Odu::create(['name' => 'Ọ̀yẹ̀kú Méjì', 'number' => 2, 'category' => 'meji']);

        $component = Livewire::actingAs($admin)->test(InitiationManager::class, ['user' => $iniciado]);

        $component->set('odu_id', $odu->id)
            ->set('initiation_date', '2024-05-10')
            ->set('notes', 'Iniciação realizada na sede')
            ->call('saveInitiation');

        $this->assertDatabaseHas('initiations', [
            'user_id' => $iniciado->id,
            'odu_id' => $odu->id,
        ]);

        $component->set('dayLabel', 'Ebó')
            ->set('dayOduId', $oduDay->id)
            ->call('addDayRecord');

        $this->assertDatabaseHas('initiation_day_records', [
            'day_label' => 'Ebó',
            'odu_id' => $oduDay->id,
        ]);

        $component->set('newEwo', 'Não comer quiabo')
            ->call('addEwo');

        $this->assertDatabaseHas('initiation_ewos', ['description' => 'Não comer quiabo']);
    }

    public function test_admin_can_record_other_orisa_made(): void
    {
        $admin = $this->admin();
        $iniciado = $this->iniciado();

        Livewire::actingAs($admin)
            ->test(InitiationManager::class, ['user' => $iniciado])
            ->call('openOrisaForm')
            ->set('orisaName', 'Exu')
            ->set('orisaDate', '2023-01-15')
            ->call('saveOrisaRecord');

        $this->assertDatabaseHas('orisa_records', [
            'user_id' => $iniciado->id,
            'orisa_name' => 'Exu',
        ]);
    }

    public function test_iniciado_sees_only_their_own_initiation(): void
    {
        $admin = $this->admin();
        $iniciado = $this->iniciado();
        $other = $this->iniciado();
        $odu = Odu::create(['name' => 'Ogbè Méjì', 'number' => 1, 'category' => 'meji']);

        $iniciado->initiation()->create([
            'odu_id' => $odu->id,
            'initiation_date' => '2024-01-01',
            'created_by' => $admin->id,
        ]);

        Livewire::actingAs($iniciado)
            ->test(MyInitiation::class)
            ->assertSee('Ogbè Méjì');

        Livewire::actingAs($other)
            ->test(MyInitiation::class)
            ->assertDontSee('Ogbè Méjì');
    }

    public function test_consulente_cannot_access_initiation_page(): void
    {
        $consulente = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'consulente', 'status' => 'active']);

        $this->actingAs($consulente)
            ->get(route('iniciado.initiation'))
            ->assertForbidden();
    }

    public function test_admin_can_save_spiritual_name(): void
    {
        $admin = $this->admin();
        $iniciado = $this->iniciado();
        $odu = Odu::create(['name' => 'Ogbè Méjì', 'number' => 1, 'category' => 'meji']);

        Livewire::actingAs($admin)
            ->test(InitiationManager::class, ['user' => $iniciado])
            ->set('odu_id', $odu->id)
            ->set('initiation_date', '2024-05-10')
            ->set('spiritual_name', 'Sọlá Ifátóògùn')
            ->call('saveInitiation');

        $this->assertDatabaseHas('initiations', [
            'user_id' => $iniciado->id,
            'odu_id' => $odu->id,
            'spiritual_name' => 'Sọlá Ifátóògùn',
        ]);
    }

    public function test_iniciado_can_see_spiritual_family_by_seniority(): void
    {
        $admin = $this->admin();
        $odu = Odu::create(['name' => 'Ogbè Méjì', 'number' => 1, 'category' => 'meji']);

        $sibling1 = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'iniciado', 'status' => 'active', 'name' => 'Ana']);
        $sibling2 = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'iniciado', 'status' => 'active', 'name' => 'Bruno']);

        $sibling1->initiation()->create([
            'odu_id' => $odu->id,
            'initiation_date' => '2024-02-01',
            'spiritual_name' => 'Ana de Ifá',
            'created_by' => $admin->id,
        ]);

        $sibling2->initiation()->create([
            'odu_id' => $odu->id,
            'initiation_date' => '2024-01-01',
            'spiritual_name' => 'Bruno de Ifá',
            'created_by' => $admin->id,
        ]);

        $iniciado = $this->iniciado();

        Livewire::actingAs($iniciado)
            ->test(\App\Livewire\Iniciado\SpiritualFamily::class)
            ->assertSee('Ana de Ifá')
            ->assertSee('Bruno de Ifá');
    }

    public function test_consulente_cannot_access_spiritual_family_page(): void
    {
        $consulente = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'consulente', 'status' => 'active']);

        $this->actingAs($consulente)
            ->get(route('iniciado.family'))
            ->assertForbidden();
    }
}
