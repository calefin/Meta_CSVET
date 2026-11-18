<?php

namespace Tests\Feature;

use App\Livewire\PublicQuickCheckout;
use App\Models\QuickPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class QuickPaymentPreselectedClientTest extends TestCase
{
    use RefreshDatabase;

    private function makeQuickPayment(?int $userId): QuickPayment
    {
        return QuickPayment::create([
            'uuid' => 'preselected-uuid',
            'type' => 'trabalhos_gerais',
            'user_id' => $userId,
            'items' => [],
            'markup_percent' => 0,
            'fee_percent' => 0,
            'subtotal' => 100,
            'total' => 100,
            'status' => 'pending',
        ]);
    }

    public function test_checkout_jumps_straight_to_login_when_client_preselected(): void
    {
        $client = User::factory()->create(['role' => 'consulente', 'status' => 'active', 'password' => Hash::make('senha1234')]);
        $qp = $this->makeQuickPayment($client->id);

        Livewire::test(PublicQuickCheckout::class, ['uuid' => $qp->uuid])
            ->assertSet('activeTab', 'login')
            ->assertSet('loginEmail', $client->email);
    }

    public function test_checkout_keeps_dual_tabs_when_no_client_preselected(): void
    {
        $qp = $this->makeQuickPayment(null);

        Livewire::test(PublicQuickCheckout::class, ['uuid' => $qp->uuid])
            ->assertSet('activeTab', 'register')
            ->assertSet('loginEmail', '');
    }

    public function test_preselected_client_can_login_and_pay(): void
    {
        $client = User::factory()->create(['role' => 'consulente', 'status' => 'active', 'password' => Hash::make('senha1234')]);
        $qp = $this->makeQuickPayment($client->id);

        Livewire::test(PublicQuickCheckout::class, ['uuid' => $qp->uuid])
            ->set('loginEmail', $client->email)
            ->set('loginPassword', 'senha1234')
            ->call('loginAndPay')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($client);
    }

    public function test_a_different_account_cannot_hijack_a_preassigned_link(): void
    {
        $client = User::factory()->create(['role' => 'consulente', 'status' => 'active']);
        $stranger = User::factory()->create(['role' => 'consulente', 'status' => 'active', 'password' => Hash::make('senha1234')]);
        $qp = $this->makeQuickPayment($client->id);

        Livewire::test(PublicQuickCheckout::class, ['uuid' => $qp->uuid])
            ->set('loginEmail', $stranger->email)
            ->set('loginPassword', 'senha1234')
            ->call('loginAndPay')
            ->assertHasErrors('loginEmail');

        $this->assertGuest();
        $this->assertDatabaseHas('quick_payments', ['id' => $qp->id, 'user_id' => $client->id]);
    }
}
