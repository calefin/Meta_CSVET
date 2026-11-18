<?php

namespace Tests\Feature;

use App\Models\ConsultationRequest;
use App\Models\Payment;
use App\Models\QuickPayment;
use App\Models\User;
use App\Services\MercadoPagoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MercadoPagoWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_ignores_non_payment_notifications(): void
    {
        $response = $this->postJson(route('webhooks.mercadopago'), ['type' => 'merchant_order', 'data' => ['id' => '123']]);

        $response->assertOk()->assertJson(['ignored' => true]);
    }

    public function test_webhook_ignores_when_mercadopago_is_not_configured(): void
    {
        config(['services.mercadopago.access_token' => null]);

        $response = $this->postJson(route('webhooks.mercadopago'), ['type' => 'payment', 'data' => ['id' => '999']]);

        $response->assertOk()->assertJson(['ignored' => true]);
    }

    public function test_webhook_marks_payment_paid_and_applies_fulfillment(): void
    {
        config(['services.mercadopago.access_token' => 'fake-token']);

        $consulente = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'consulente', 'status' => 'active']);
        $request = $consulente->consultationRequests()->create([
            'modality' => 'online', 'description' => 'teste', 'status' => 'awaiting_payment',
        ]);

        $payment = Payment::create([
            'user_id' => $consulente->id,
            'payable_type' => ConsultationRequest::class,
            'payable_id' => $request->id,
            'amount' => 100,
            'status' => 'pending',
        ]);

        $fakeMpPayment = (object) ['status' => 'approved', 'external_reference' => (string) $payment->id];

        $this->mock(MercadoPagoService::class, function ($mock) use ($fakeMpPayment) {
            $mock->shouldReceive('fetchPayment')->once()->andReturn($fakeMpPayment);
        });

        $response = $this->postJson(route('webhooks.mercadopago'), ['type' => 'payment', 'data' => ['id' => '555']]);

        $response->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid', 'mp_payment_id' => '555']);
        $this->assertDatabaseHas('consultation_requests', ['id' => $request->id, 'status' => 'paid']);
    }

    public function test_webhook_stores_mp_fee_and_payment_method_details(): void
    {
        config(['services.mercadopago.access_token' => 'fake-token']);

        $consulente = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'consulente', 'status' => 'active']);
        $request = $consulente->consultationRequests()->create([
            'modality' => 'online', 'description' => 'teste', 'status' => 'awaiting_payment',
        ]);

        $payment = Payment::create([
            'user_id' => $consulente->id,
            'payable_type' => ConsultationRequest::class,
            'payable_id' => $request->id,
            'amount' => 100,
            'status' => 'pending',
        ]);

        $fakeMpPayment = (object) [
            'status' => 'approved',
            'external_reference' => (string) $payment->id,
            'payment_type_id' => 'bank_transfer',
            'payment_method_id' => 'pix',
            'fee_details' => [(object) ['type' => 'mercadopago_fee', 'fee_payer' => 'collector', 'amount' => 3.45]],
            'transaction_details' => (object) ['net_received_amount' => 96.55],
        ];

        $this->mock(MercadoPagoService::class, function ($mock) use ($fakeMpPayment) {
            $mock->shouldReceive('fetchPayment')->once()->andReturn($fakeMpPayment);
        });

        $this->postJson(route('webhooks.mercadopago'), ['type' => 'payment', 'data' => ['id' => '556']])
            ->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'mp_fee_amount' => 3.45,
            'mp_net_amount' => 96.55,
            'mp_payment_type' => 'bank_transfer',
            'mp_payment_method' => 'pix',
        ]);
    }

    public function test_webhook_marks_payment_failed_when_rejected(): void
    {
        config(['services.mercadopago.access_token' => 'fake-token']);

        $consulente = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'consulente', 'status' => 'active']);
        $request = $consulente->consultationRequests()->create([
            'modality' => 'online', 'description' => 'teste', 'status' => 'awaiting_payment',
        ]);

        $payment = Payment::create([
            'user_id' => $consulente->id,
            'payable_type' => ConsultationRequest::class,
            'payable_id' => $request->id,
            'amount' => 100,
            'status' => 'pending',
        ]);

        $fakeMpPayment = (object) ['status' => 'rejected', 'external_reference' => (string) $payment->id];

        $this->mock(MercadoPagoService::class, function ($mock) use ($fakeMpPayment) {
            $mock->shouldReceive('fetchPayment')->once()->andReturn($fakeMpPayment);
        });

        $this->postJson(route('webhooks.mercadopago'), ['type' => 'payment', 'data' => ['id' => '556']])
            ->assertOk();

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'failed']);
    }

    public function test_webhook_delivered_twice_for_the_same_quick_payment_only_fulfills_once(): void
    {
        config(['services.mercadopago.access_token' => 'fake-token']);

        $user = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'consulente', 'status' => 'active']);

        $qp = QuickPayment::create([
            'uuid' => 'idempotency-uuid',
            'type' => 'consulta',
            'items' => [],
            'markup_percent' => 0,
            'fee_percent' => 0,
            'subtotal' => 100,
            'total' => 100,
            'status' => 'pending',
        ]);

        $payment = Payment::create([
            'user_id' => $user->id,
            'payable_type' => QuickPayment::class,
            'payable_id' => $qp->id,
            'amount' => 100,
            'status' => 'pending',
        ]);

        $fakeMpPayment = (object) ['status' => 'approved', 'external_reference' => (string) $payment->id];

        $this->mock(MercadoPagoService::class, function ($mock) use ($fakeMpPayment) {
            $mock->shouldReceive('fetchPayment')->twice()->andReturn($fakeMpPayment);
        });

        // Simulates Mercado Pago retrying the same notification (e.g. after
        // a slow response) - the second delivery must not create a second
        // ConsultationRequest for this link.
        $this->postJson(route('webhooks.mercadopago'), ['type' => 'payment', 'data' => ['id' => '999']])->assertOk();
        $this->postJson(route('webhooks.mercadopago'), ['type' => 'payment', 'data' => ['id' => '999']])->assertOk();

        $this->assertSame(1, ConsultationRequest::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('quick_payments', ['id' => $qp->id, 'status' => 'paid']);
    }
}
