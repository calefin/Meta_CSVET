<?php

namespace Tests\Feature;

use App\Models\User;
use App\Filament\Pages\PerfilProfessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProfessorProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_access_professor_profile_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);

        // Test guest cannot access
        $this->get(PerfilProfessor::getUrl())->assertRedirect('/admin/login');

        // Test student cannot access
        $this->actingAs($student);
        $this->get(PerfilProfessor::getUrl())->assertStatus(403);

        // Test admin can access
        $this->actingAs($admin);
        $this->get(PerfilProfessor::getUrl())->assertSuccessful();
    }

    public function test_professor_profile_form_can_be_filled_and_saved(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Dr. Atilio Calefi',
            'email' => 'atilio@example.com',
            'metadata' => [
                'title' => 'Prof. Dr.',
                'whatsapp_number' => '5511977998476',
            ]
        ]);

        $this->actingAs($admin);

        Livewire::test(PerfilProfessor::class)
            ->assertFormSet([
                'name' => 'Dr. Atilio Calefi',
                'email' => 'atilio@example.com',
                'title' => 'Prof. Dr.',
                'whatsapp_number' => '5511977998476',
            ])
            ->fillForm([
                'name' => 'Prof. Dr. Atilio Calefi',
                'email' => 'atiliocalefi@example.com',
                'title' => 'Prof. Dr. (Livre-Docente)',
                'whatsapp_number' => '11977998476',
                'storage_provider' => 'onedrive',
                'storage_folder' => 'profatilio_docs',
            ])
            ->call('submit')
            ->assertHasNoFormErrors();

        $admin->refresh();

        $this->assertEquals('Prof. Dr. Atilio Calefi', $admin->name); // Name auto-normalized
        $this->assertEquals('atiliocalefi@example.com', $admin->email);
        $this->assertEquals('Prof. Dr. (Livre-Docente)', $admin->metadata['title']);
        $this->assertEquals('11977998476', $admin->metadata['whatsapp_number']);
        $this->assertEquals('onedrive', $admin->metadata['storage_provider']);
        $this->assertEquals('profatilio_docs', $admin->metadata['storage_folder']);
    }
}
