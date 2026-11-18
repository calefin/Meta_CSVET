<?php

namespace Tests\Feature;

use App\Livewire\Admin\Diary;
use App\Models\DiaryEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DiaryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'admin', 'status' => 'active']);
    }

    public function test_admin_can_create_a_diary_entry(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(Diary::class)
            ->call('openForm')
            ->set('entryDate', '2026-06-18')
            ->set('category', 'pessoal')
            ->set('title', 'Jogo para mim')
            ->set('content', 'Anotacoes privadas do jogo de hoje.')
            ->call('save');

        $this->assertDatabaseHas('diary_entries', [
            'title' => 'Jogo para mim',
            'category' => 'pessoal',
            'created_by' => $admin->id,
        ]);
    }

    public function test_admin_can_edit_and_delete_a_diary_entry(): void
    {
        $admin = $this->admin();
        $entry = DiaryEntry::create([
            'entry_date' => '2026-06-18',
            'category' => 'casa',
            'title' => 'Original',
            'content' => 'Texto original',
            'created_by' => $admin->id,
        ]);

        Livewire::actingAs($admin)
            ->test(Diary::class)
            ->call('edit', $entry->id)
            ->set('title', 'Atualizado')
            ->call('save');

        $this->assertDatabaseHas('diary_entries', ['id' => $entry->id, 'title' => 'Atualizado']);

        Livewire::actingAs($admin)
            ->test(Diary::class)
            ->call('delete', $entry->id);

        $this->assertDatabaseMissing('diary_entries', ['id' => $entry->id]);
    }

    public function test_non_admin_cannot_access_the_diary(): void
    {
        $consulente = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'consulente', 'status' => 'active']);

        $this->actingAs($consulente)
            ->get(route('admin.diary'))
            ->assertForbidden();
    }
}
