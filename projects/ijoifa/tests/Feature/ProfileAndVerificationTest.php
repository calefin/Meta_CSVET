<?php

namespace Tests\Feature;

use App\Livewire\Shared\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileAndVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_change_their_own_password(): void
    {
        $user = User::factory()->create([
            'mother_maiden_name' => 'Teste',
            'role' => 'consulente',
            'status' => 'active',
            'password' => Hash::make('old-password'),
        ]);

        Livewire::actingAs($user)
            ->test(Profile::class)
            ->set('current_password', 'old-password')
            ->set('password', 'brand-new-password')
            ->set('password_confirmation', 'brand-new-password')
            ->call('changePassword')
            ->assertSet('changed', true);

        $this->assertTrue(Hash::check('brand-new-password', $user->fresh()->password));
    }

    public function test_changing_password_requires_correct_current_password(): void
    {
        $user = User::factory()->create([
            'mother_maiden_name' => 'Teste',
            'role' => 'consulente',
            'status' => 'active',
            'password' => Hash::make('old-password'),
        ]);

        Livewire::actingAs($user)
            ->test(Profile::class)
            ->set('current_password', 'wrong-password')
            ->set('password', 'brand-new-password')
            ->set('password_confirmation', 'brand-new-password')
            ->call('changePassword')
            ->assertHasErrors('current_password');

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_valid_signed_link_verifies_email_without_requiring_login(): void
    {
        $user = User::factory()->create([
            'mother_maiden_name' => 'Teste',
            'role' => 'consulente',
            'status' => 'pending',
            'email' => 'pendente@example.com',
            'email_verified_at' => null,
        ]);

        $url = URL::temporarySignedRoute('verification.verify', now()->addHours(24), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->get($url)->assertRedirect(route('login'));

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_tampered_verification_link_is_rejected(): void
    {
        $user = User::factory()->create([
            'mother_maiden_name' => 'Teste',
            'role' => 'consulente',
            'status' => 'pending',
            'email' => 'pendente@example.com',
            'email_verified_at' => null,
        ]);

        $url = URL::temporarySignedRoute('verification.verify', now()->addHours(24), [
            'id' => $user->id,
            'hash' => 'wrong-hash',
        ]);

        $this->get($url)->assertForbidden();

        $this->assertNull($user->fresh()->email_verified_at);
    }
}
