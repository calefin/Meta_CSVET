<?php

namespace Tests\Feature;

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\ResetPassword;
use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_requesting_a_reset_link_sends_the_branded_email(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'mother_maiden_name' => 'Teste',
            'role' => 'consulente',
            'status' => 'active',
            'email' => 'maria@example.com',
        ]);

        Livewire::test(ForgotPassword::class)
            ->set('email', 'maria@example.com')
            ->call('send')
            ->assertSet('submitted', true);

        Mail::assertSent(PasswordResetMail::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_requesting_a_reset_link_for_unknown_email_shows_same_message(): void
    {
        Mail::fake();

        Livewire::test(ForgotPassword::class)
            ->set('email', 'nao-existe@example.com')
            ->call('send')
            ->assertSet('submitted', true);

        Mail::assertNothingSent();
    }

    public function test_user_can_reset_password_with_a_valid_token(): void
    {
        $user = User::factory()->create([
            'mother_maiden_name' => 'Teste',
            'role' => 'consulente',
            'status' => 'active',
            'email' => 'maria@example.com',
            'password' => Hash::make('old-password'),
        ]);

        $token = Password::createToken($user);

        Livewire::test(ResetPassword::class, ['token' => $token])
            ->set('email', $user->email)
            ->set('password', 'new-password-123')
            ->set('password_confirmation', 'new-password-123')
            ->call('resetPassword')
            ->assertSet('completed', true);

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_reset_fails_with_an_invalid_token(): void
    {
        $user = User::factory()->create([
            'mother_maiden_name' => 'Teste',
            'role' => 'consulente',
            'status' => 'active',
            'email' => 'maria@example.com',
        ]);

        Livewire::test(ResetPassword::class, ['token' => 'invalid-token'])
            ->set('email', $user->email)
            ->set('password', 'new-password-123')
            ->set('password_confirmation', 'new-password-123')
            ->call('resetPassword')
            ->assertHasErrors('email')
            ->assertSet('completed', false);
    }
}
