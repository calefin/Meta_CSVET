<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Catalogs\RitualWorks;
use App\Models\Animal;
use App\Models\RitualWork;
use App\Models\Supply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogsTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create([
            'mother_maiden_name' => 'Teste',
            'role' => 'admin',
            'status' => 'active',
        ]);
    }

    public static function catalogRoutes(): array
    {
        return [
            ['admin.catalogs.odus'],
            ['admin.catalogs.ibi-ire'],
            ['admin.catalogs.ritual-works'],
            ['admin.catalogs.suppliers'],
            ['admin.catalogs.supplies'],
            ['admin.catalogs.animals'],
        ];
    }

    #[DataProvider('catalogRoutes')]
    public function test_admin_can_view_each_catalog_page(string $route): void
    {
        $this->actingAs($this->adminUser())
            ->get(route($route))
            ->assertOk();
    }

    public function test_consulente_cannot_view_catalog_pages(): void
    {
        $consulente = User::factory()->create([
            'mother_maiden_name' => 'Teste',
            'role' => 'consulente',
            'status' => 'active',
        ]);

        $this->actingAs($consulente)
            ->get(route('admin.catalogs.odus'))
            ->assertForbidden();
    }

    public function test_admin_can_search_ritual_works_by_name(): void
    {
        RitualWork::create(['name' => 'Ẹbọ Riru', 'active' => true]);
        RitualWork::create(['name' => 'Bori', 'active' => true]);

        Livewire::actingAs($this->adminUser())
            ->test(RitualWorks::class)
            ->set('search', 'Bori')
            ->assertSee('Bori')
            ->assertDontSee('Ẹbọ Riru');
    }

    public function test_admin_can_attach_supply_and_animal_via_search_when_creating_ritual_work(): void
    {
        $supply = Supply::create(['name' => 'Vela branca', 'active' => true]);
        $animal = Animal::create(['name' => 'Galo', 'active' => true]);

        Livewire::actingAs($this->adminUser())
            ->test(RitualWorks::class)
            ->call('create')
            ->set('name', 'Trabalho com insumo e animal')
            ->set('supplySearch', 'Vela')
            ->call('selectSupplyById', $supply->id)
            ->set('supplyRows.0.default_quantity', '2')
            ->set('animalSearch', 'Galo')
            ->call('selectAnimalById', $animal->id)
            ->set('animalRows.0.default_quantity', '1')
            ->call('save');

        $work = RitualWork::where('name', 'Trabalho com insumo e animal')->firstOrFail();

        $this->assertDatabaseHas('ritual_work_supplies', [
            'ritual_work_id' => $work->id,
            'supply_id' => $supply->id,
            'default_quantity' => 2,
        ]);

        $this->assertDatabaseHas('ritual_work_animals', [
            'ritual_work_id' => $work->id,
            'animal_id' => $animal->id,
            'default_quantity' => 1,
        ]);
    }

    public function test_admin_cannot_add_the_same_supply_twice_to_a_ritual_work(): void
    {
        $supply = Supply::create(['name' => 'Vela branca', 'active' => true]);

        Livewire::actingAs($this->adminUser())
            ->test(RitualWorks::class)
            ->call('create')
            ->call('selectSupplyById', $supply->id)
            ->call('selectSupplyById', $supply->id)
            ->assertSet('supplyRows', [
                ['supply_id' => $supply->id, 'default_quantity' => '1'],
            ])
            ->assertSet('duplicateItemWarning', 'Este insumo já foi adicionado à lista.');
    }

    public function test_admin_can_duplicate_ritual_work_without_altering_the_original(): void
    {
        $supply = Supply::create(['name' => 'Vela branca', 'active' => true]);
        $original = RitualWork::create(['name' => 'Ẹbọ Original', 'description' => 'Descrição original', 'active' => true]);
        $original->supplies()->attach($supply->id, ['default_quantity' => 3]);

        Livewire::actingAs($this->adminUser())
            ->test(RitualWorks::class)
            ->call('duplicate', $original->id)
            ->assertSet('editingId', null)
            ->assertSet('name', 'Ẹbọ Original (cópia)')
            ->call('save');

        $this->assertDatabaseCount('ritual_works', 2);

        $original->refresh();
        $this->assertSame('Ẹbọ Original', $original->name);
        $this->assertSame(1, $original->supplies()->count());

        $copy = RitualWork::where('name', 'Ẹbọ Original (cópia)')->firstOrFail();
        $this->assertDatabaseHas('ritual_work_supplies', [
            'ritual_work_id' => $copy->id,
            'supply_id' => $supply->id,
            'default_quantity' => 3,
        ]);
    }
}
