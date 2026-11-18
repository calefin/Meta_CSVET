<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_locked_out_after_too_many_failed_attempts(): void
    {
        $user = User::factory()->create([
            'mother_maiden_name' => 'Teste',
            'role' => 'consulente',
            'status' => 'active',
            'email' => 'teste@example.com',
        ]);

        for ($i = 0; $i < 5; $i++) {
            Livewire::test(Login::class)
                ->set('email', $user->email)
                ->set('password', 'wrong-password')
                ->call('login');
        }

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('teste@example.com|127.0.0.1');
        parent::tearDown();
    }
}
