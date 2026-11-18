<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Events;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_edit_and_delete_an_event(): void
    {
        $admin = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'admin', 'status' => 'active']);

        Livewire::actingAs($admin)
            ->test(Events::class)
            ->call('create')
            ->set('title', 'Festa de Iyawo')
            ->set('starts_on', '2026-08-01')
            ->set('target_group', 'iniciados')
            ->call('save')
            ->assertHasNoErrors();

        $event = Event::firstWhere('title', 'Festa de Iyawo');
        $this->assertNotNull($event);
        $this->assertSame('iniciados', $event->target_group);

        Livewire::actingAs($admin)
            ->test(Events::class)
            ->call('edit', $event->id)
            ->set('title', 'Festa de Iyawo (atualizada)')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Festa de Iyawo (atualizada)', $event->refresh()->title);

        Livewire::actingAs($admin)
            ->test(Events::class)
            ->call('delete', $event->id);

        $this->assertNull(Event::find($event->id));
    }

    public function test_non_admin_cannot_access_the_events_admin_route(): void
    {
        $iniciado = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'iniciado', 'status' => 'active']);
        $consulente = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'consulente', 'status' => 'active']);

        $this->actingAs($iniciado)->get(route('admin.events'))->assertForbidden();
        $this->actingAs($consulente)->get(route('admin.events'))->assertForbidden();
    }
}
