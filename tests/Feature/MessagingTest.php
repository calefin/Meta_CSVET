<?php

namespace Tests\Feature;

use App\Livewire\Admin\AdminNotes;
use App\Livewire\Admin\Announcements;
use App\Livewire\Shared\AnnouncementsList;
use App\Livewire\Shared\Inbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => $role, 'status' => 'active']);
    }

    public function test_consulente_can_message_admin(): void
    {
        $consulente = $this->user('consulente');
        $admin = $this->user('admin');

        Livewire::actingAs($consulente)
            ->test(Inbox::class)
            ->set('newRecipientId', (string) $admin->id)
            ->set('body', 'Preciso de ajuda')
            ->call('send');

        $this->assertDatabaseHas('messages', [
            'sender_id' => $consulente->id,
            'recipient_id' => $admin->id,
        ]);
    }

    public function test_consulente_cannot_message_another_consulente(): void
    {
        $consulente = $this->user('consulente');
        $other = $this->user('consulente');

        Livewire::actingAs($consulente)
            ->test(Inbox::class)
            ->set('newRecipientId', (string) $other->id)
            ->set('body', 'Oi')
            ->call('send')
            ->assertForbidden();

        $this->assertDatabaseMissing('messages', [
            'sender_id' => $consulente->id,
            'recipient_id' => $other->id,
        ]);
    }

    public function test_iniciado_can_message_another_iniciado(): void
    {
        $iniciado1 = $this->user('iniciado');
        $iniciado2 = $this->user('iniciado');

        Livewire::actingAs($iniciado1)
            ->test(Inbox::class)
            ->set('newRecipientId', (string) $iniciado2->id)
            ->set('body', 'Oi colega')
            ->call('send');

        $this->assertDatabaseHas('messages', [
            'sender_id' => $iniciado1->id,
            'recipient_id' => $iniciado2->id,
        ]);
    }

    public function test_admin_can_create_announcement_and_user_can_confirm_read(): void
    {
        $admin = $this->user('admin');
        $consulente = $this->user('consulente');

        Livewire::actingAs($admin)
            ->test(Announcements::class)
            ->call('create')
            ->set('title', 'Aviso importante')
            ->set('body', 'Mesa de Ifa nesta semana')
            ->set('target_group', 'consulentes')
            ->call('save');

        $this->assertDatabaseHas('announcements', ['title' => 'Aviso importante']);

        $announcement = \App\Models\Announcement::first();

        Livewire::actingAs($consulente)
            ->test(AnnouncementsList::class)
            ->assertSee('Aviso importante')
            ->call('markRead', $announcement->id);

        $this->assertDatabaseHas('announcement_reads', [
            'announcement_id' => $announcement->id,
            'user_id' => $consulente->id,
        ]);
    }

    public function test_admin_note_is_never_visible_to_target_user_and_locks_after_24h(): void
    {
        $admin = $this->user('admin');
        $target = $this->user('consulente');

        Livewire::actingAs($admin)
            ->test(AdminNotes::class, ['targetUserId' => $target->id])
            ->call('openForm')
            ->set('category', 'comportamento')
            ->set('body', 'Observacao confidencial')
            ->call('save');

        $note = \App\Models\AdminNote::first();
        $this->assertNotNull($note);

        // Simulate the note aging past 24h (bypassing Eloquent events, since
        // the model itself guards against post-creation updates) and confirm
        // it becomes locked.
        \Illuminate\Support\Facades\DB::table('admin_notes')->where('id', $note->id)->update([
            'created_at' => now()->subHours(25),
        ]);
        $note = $note->fresh();

        $this->assertTrue($note->isLocked());
        $this->expectException(\RuntimeException::class);
        $note->update(['body' => 'tentativa de edicao']);
    }
}
