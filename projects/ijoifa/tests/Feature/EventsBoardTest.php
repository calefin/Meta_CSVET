<?php

namespace Tests\Feature;

use App\Livewire\Shared\EventsBoard;
use App\Models\Event;
use App\Models\LiturgicalSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventsBoardTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_event_appears_on_the_board(): void
    {
        $admin = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'admin', 'status' => 'active']);
        $consulente = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'consulente', 'status' => 'active']);

        Event::create([
            'admin_id' => $admin->id,
            'title' => 'Festa de Ano Novo do Ifá',
            'starts_on' => now()->addDays(5),
            'target_group' => 'all',
        ]);

        Livewire::actingAs($consulente)
            ->test(EventsBoard::class)
            ->assertSee('Festa de Ano Novo do Ifá');
    }

    public function test_events_board_respects_target_group_visibility(): void
    {
        $admin = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'admin', 'status' => 'active']);
        $iniciado = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'iniciado', 'status' => 'active']);
        $consulente = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'consulente', 'status' => 'active']);

        Event::create([
            'admin_id' => $admin->id,
            'title' => 'Reunião só de iniciados',
            'starts_on' => now()->addDays(2),
            'target_group' => 'iniciados',
        ]);

        Livewire::actingAs($iniciado)->test(EventsBoard::class)->assertSee('Reunião só de iniciados');
        Livewire::actingAs($consulente)->test(EventsBoard::class)->assertDontSee('Reunião só de iniciados');
    }

    public function test_events_board_shows_computed_liturgical_occurrence_without_a_db_row(): void
    {
        $consulente = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'consulente', 'status' => 'active']);

        LiturgicalSetting::current()->update(['orun_anchor' => now()->addDays(3)->toDateString()]);
        LiturgicalSetting::forget();

        Livewire::actingAs($consulente)
            ->test(EventsBoard::class)
            ->assertSee('Òrún Awó');

        $this->assertSame(0, Event::count());
    }

    public function test_events_board_visible_on_all_three_dashboards(): void
    {
        $admin = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'admin', 'status' => 'active']);
        $iniciado = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'iniciado', 'status' => 'active']);
        $consulente = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'consulente', 'status' => 'active']);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertSeeLivewire('shared.events-board');
        $this->actingAs($iniciado)->get(route('iniciado.dashboard'))->assertSeeLivewire('shared.events-board');
        $this->actingAs($consulente)->get(route('consulente.dashboard'))->assertSeeLivewire('shared.events-board');
    }
}
