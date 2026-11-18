<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\AdminLogin;
use App\Filament\Pages\Auth\StudentLogin;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AdminLoginStudentRedirectTest extends TestCase
{
    use RefreshDatabase;

    private function makeExternalStudent(): User
    {
        $user = User::create([
            'name' => 'Aluna Externa',
            'email' => 'externa@example.com',
            'password' => Hash::make('senha12345'),
            'role' => 'student',
        ]);
        $user->student()->create([
            'institution' => 'Não informada',
            'academic_level' => 'Graduação',
            'tcc_status' => 'none',
            'research_topic' => 'Geral',
            'phone' => '11999999999',
            'status' => 'active',
            'category' => 'external',
        ]);

        return $user;
    }

    public function test_student_with_correct_password_on_admin_login_is_pointed_to_portal(): void
    {
        $this->makeExternalStudent();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(AdminLogin::class)
            ->set('data.email', 'externa@example.com')
            ->set('data.password', 'senha12345')
            ->call('authenticate')
            ->assertHasErrors(['data.email'])
            ->assertSee('Portal do Aluno');

        $this->assertGuest();
    }

    public function test_student_with_wrong_password_on_admin_login_gets_generic_error(): void
    {
        $this->makeExternalStudent();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(AdminLogin::class)
            ->set('data.email', 'externa@example.com')
            ->set('data.password', 'errada123')
            ->call('authenticate')
            ->assertHasErrors(['data.email'])
            ->assertDontSee('Portal do Aluno');
    }

    public function test_external_student_logs_in_through_portal(): void
    {
        $this->makeExternalStudent();
        Filament::setCurrentPanel(Filament::getPanel('portal'));

        Livewire::test(StudentLogin::class)
            ->set('data.email', 'externa@example.com')
            ->set('data.password', 'senha12345')
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticated();
    }
}
