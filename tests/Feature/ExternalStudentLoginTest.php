<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\StudentLogin;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExternalStudentLoginTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(string $status, string $category = 'external'): User
    {
        $user = User::create([
            'name' => 'Aluno Externo',
            'email' => 'externo@test.com',
            'password' => bcrypt('senha12345'),
            'role' => 'student',
        ]);

        $user->student()->create([
            'institution' => 'Não informada',
            'academic_level' => 'Graduação',
            'tcc_status' => 'none',
            'research_topic' => 'Geral',
            'phone' => '11999999999',
            'terms_accepted' => true,
            'terms_accepted_at' => now(),
            'status' => $status,
            'category' => $category,
        ]);

        return $user;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('portal'));
    }

    public function test_active_external_student_reaches_portal_without_semester_survey(): void
    {
        $user = $this->makeStudent('active');

        Livewire::test(StudentLogin::class)
            ->fillForm(['email' => 'externo@test.com', 'password' => 'senha12345'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($user);
        $this->actingAs($user)->get('/portal')->assertOk();
    }

    public function test_regular_student_still_gets_semester_survey(): void
    {
        $user = $this->makeStudent('active', 'regular');

        $this->actingAs($user)->get('/portal')->assertRedirect('/portal/pesquisa-semestral');
    }

    public function test_pending_status_message_is_shown_on_the_email_field(): void
    {
        $this->makeStudent('awaiting_advisor_approval');

        Livewire::test(StudentLogin::class)
            ->fillForm(['email' => 'externo@test.com', 'password' => 'senha12345'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }
}
