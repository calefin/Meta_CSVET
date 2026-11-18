<?php

namespace Tests\Feature;

use App\Filament\Pages\PosDoutorado;
use App\Models\PostdocProject;
use App\Models\SystematicReview;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PosDoutoradoTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_postdoc_panel_linked_to_p19_and_saves(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = User::factory()->create(['role' => 'admin']);
        SystematicReview::create(['title' => 'Meningioma grade and survival in dogs', 'protocol_code' => 'P19', 'pilot_size' => 0]);

        Livewire::actingAs($admin)->test(PosDoutorado::class)
            ->assertOk()
            ->assertSee('Revisão ligada ao pós-doc')
            ->assertSee('P19 · PAT-09')
            ->assertSee('10.17605/OSF.IO/PCGE7')
            ->assertSet('data.supervisor', 'Prof. Dr. Eduardo Fernandes Bondan')
            ->set('data.status', 'enviado')
            ->set('data.starts_at', '2026-12-01')
            ->call('save')
            ->assertHasNoErrors();

        $p = PostdocProject::sole();
        $this->assertSame('enviado', $p->status);
        $this->assertSame('2026-12-01', $p->starts_at->toDateString());
        $this->assertCount(12, $p->checklist);
    }

    public function test_students_cannot_open_the_postdoc_panel(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $this->actingAs($student);
        $this->assertFalse(PosDoutorado::canAccess());
    }
}
