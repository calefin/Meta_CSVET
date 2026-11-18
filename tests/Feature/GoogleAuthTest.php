<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_active_user_is_logged_in_via_google(): void
    {
        $user = User::factory()->create([
            'mother_maiden_name' => 'Teste',
            'role' => 'consulente',
            'status' => 'active',
            'email' => 'maria@example.com',
        ]);

        $googleUser = (new SocialiteUser())->map(['id' => '123', 'name' => 'Maria', 'email' => 'maria@example.com']);
        Socialite::shouldReceive('driver->user')->andReturn($googleUser);

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_pending_user_is_not_logged_in_via_google(): void
    {
        User::factory()->create([
            'mother_maiden_name' => 'Teste',
            'role' => 'consulente',
            'status' => 'pending',
            'email' => 'pendente@example.com',
        ]);

        $googleUser = (new SocialiteUser())->map(['id' => '456', 'name' => 'Pendente', 'email' => 'pendente@example.com']);
        Socialite::shouldReceive('driver->user')->andReturn($googleUser);

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_new_google_user_is_sent_to_complete_registration(): void
    {
        $googleUser = (new SocialiteUser())->map(['id' => '789', 'name' => 'Novo Usuario', 'email' => 'novo@example.com']);
        Socialite::shouldReceive('driver->user')->andReturn($googleUser);

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('register.complete'));
        $this->assertGuest();

        $this->assertDatabaseMissing('users', ['email' => 'novo@example.com']);
    }

    public function test_completing_google_registration_creates_a_pending_user(): void
    {
        $this->withSession(['google_signup' => ['name' => 'Novo Usuario', 'email' => 'novo@example.com']]);

        \Livewire\Livewire::test(\App\Livewire\Auth\CompleteGoogleRegistration::class)
            ->set('mother_maiden_name', 'Mãe Teste')
            ->set('country', 'BR')
            ->set('cpf', '123.456.789-00')
            ->set('consent', true)
            ->call('complete')
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('users', [
            'email' => 'novo@example.com',
            'mother_maiden_name' => 'Mãe Teste',
            'status' => 'pending',
            'role' => 'consulente',
        ]);
    }
}
