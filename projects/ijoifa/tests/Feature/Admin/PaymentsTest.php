<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Payments;
use App\Mail\AdHocChargeMail;
use App\Models\AdHocCharge;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'admin', 'status' => 'active']);
    }

    private function iniciado(): User
    {
        return User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'iniciado', 'status' => 'active']);
    }

    public function test_admin_can_create_an_ad_hoc_charge_and_email_is_sent(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $iniciado = $this->iniciado();

        Livewire::actingAs($admin)
            ->test(Payments::class)
            ->call('openChargeForm')
            ->set('chargeUserId', $iniciado->id)
            ->set('chargeDescription', 'Taxa de ose')
            ->set('chargeAmount', '50')
            ->call('createCharge');

        $this->assertDatabaseHas('ad_hoc_charges', [
            'user_id' => $iniciado->id,
            'description' => 'Taxa de ose',
        ]);

        $this->assertDatabaseHas('payments', [
            'user_id' => $iniciado->id,
            'payable_type' => AdHocCharge::class,
            'amount' => 50,
            'status' => 'pending',
        ]);

        Mail::assertSent(AdHocChargeMail::class);
    }

    public function test_admin_can_mark_a_pending_payment_as_paid(): void
    {
        $admin = $this->admin();
        $iniciado = $this->iniciado();
        $charge = AdHocCharge::create(['user_id' => $iniciado->id, 'description' => 'Taxa', 'created_by' => $admin->id]);
        $payment = Payment::create([
            'user_id' => $iniciado->id,
            'payable_type' => AdHocCharge::class,
            'payable_id' => $charge->id,
            'amount' => 30,
            'status' => 'pending',
        ]);

        Livewire::actingAs($admin)
            ->test(Payments::class)
            ->call('markPaid', $payment->id);

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid']);
    }

    public function test_admin_can_cancel_a_pending_payment(): void
    {
        $admin = $this->admin();
        $iniciado = $this->iniciado();
        $charge = AdHocCharge::create(['user_id' => $iniciado->id, 'description' => 'Taxa', 'created_by' => $admin->id]);
        $payment = Payment::create([
            'user_id' => $iniciado->id,
            'payable_type' => AdHocCharge::class,
            'payable_id' => $charge->id,
            'amount' => 30,
            'status' => 'pending',
        ]);

        Livewire::actingAs($admin)
            ->test(Payments::class)
            ->call('cancel', $payment->id);

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'failed']);
    }

    public function test_consulente_cannot_access_payments_panel(): void
    {
        $consulente = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'consulente', 'status' => 'active']);

        $this->actingAs($consulente)->get(route('admin.finance.payments'))->assertForbidden();
    }
}
