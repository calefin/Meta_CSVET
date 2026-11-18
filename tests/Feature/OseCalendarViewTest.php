<?php

namespace Tests\Feature;

use App\Livewire\Shared\OseCalendarView;
use App\Models\LiturgicalSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OseCalendarViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_adjust_the_orun_anchor_to_a_valid_ifa_phase_date(): void
    {
        $admin = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'admin', 'status' => 'active']);

        Livewire::actingAs($admin)
            ->test(OseCalendarView::class)
            ->assertSee('Ajustar data de Òrún Awó')
            ->call('editAnchor')
            ->set('orunAnchor', '2026-06-26')
            ->call('saveAnchor')
            ->assertHasNoErrors();

        $this->assertSame('2026-06-26', LiturgicalSetting::current()->orun_anchor->toDateString());
    }

    public function test_admin_cannot_set_an_anchor_outside_the_ifa_phase(): void
    {
        $admin = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'admin', 'status' => 'active']);

        Livewire::actingAs($admin)
            ->test(OseCalendarView::class)
            ->call('editAnchor')
            ->set('orunAnchor', '2026-06-23')
            ->call('saveAnchor')
            ->assertHasErrors(['orunAnchor']);

        $this->assertSame('2026-08-13', LiturgicalSetting::current()->orun_anchor->toDateString());
    }

    public function test_non_admin_does_not_see_the_anchor_button(): void
    {
        $consulente = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'consulente', 'status' => 'active']);

        Livewire::actingAs($consulente)
            ->test(OseCalendarView::class)
            ->assertDontSee('Ajustar data de Òrún Awó');
    }

    public function test_month_grid_renders_yoruba_week_subtitles_and_meanings(): void
    {
        $consulente = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'consulente', 'status' => 'active']);

        Livewire::actingAs($consulente)
            ->test(OseCalendarView::class)
            ->assertSee('Ọjọ́ Àìkú')
            ->assertSee('Calendário Yoruba de 7 dias');
    }
}
