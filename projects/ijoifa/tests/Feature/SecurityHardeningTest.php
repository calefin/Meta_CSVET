<?php

namespace Tests\Feature;

use App\Models\ConsultationRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_consultation_attachment_rejects_disallowed_file_types(): void
    {
        $consulente = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'consulente', 'status' => 'active']);

        Livewire::actingAs($consulente)
            ->test(\App\Livewire\Consulente\Consultations::class)
            ->call('openRequestForm')
            ->set('modality', 'online')
            ->set('description', 'teste')
            ->set('attachment', UploadedFile::fake()->create('malicious.php', 10, 'application/x-php'))
            ->call('submitRequest')
            ->assertHasErrors('attachment');
    }

    public function test_only_owner_or_admin_can_download_consultation_attachment(): void
    {
        $consulente = $this->makeConsulente();
        $stranger = $this->makeConsulente();
        $admin = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'admin', 'status' => 'active']);

        $request = $consulente->consultationRequests()->create([
            'modality' => 'online',
            'description' => 'teste',
            'status' => 'pending',
            'attachment_path' => 'consultation-attachments/fake.jpg',
        ]);

        \Illuminate\Support\Facades\Storage::disk('local')->put('consultation-attachments/fake.jpg', 'fake-image-content');

        $this->actingAs($consulente)->get(route('consultation-requests.attachment', $request))->assertOk();
        $this->actingAs($admin)->get(route('consultation-requests.attachment', $request))->assertOk();
        $this->actingAs($stranger)->get(route('consultation-requests.attachment', $request))->assertForbidden();
    }

    private function makeConsulente(): User
    {
        return User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'consulente', 'status' => 'active']);
    }

    public function test_forgot_password_is_rate_limited_per_ip(): void
    {
        RateLimiter::clear('forgot-password|127.0.0.1');

        for ($i = 0; $i < 5; $i++) {
            Livewire::test(\App\Livewire\Auth\ForgotPassword::class)
                ->set('email', "user{$i}@example.com")
                ->call('send');
        }

        \Illuminate\Support\Facades\Mail::fake();

        Livewire::test(\App\Livewire\Auth\ForgotPassword::class)
            ->set('email', 'one-more@example.com')
            ->call('send')
            ->assertSet('submitted', true);

        \Illuminate\Support\Facades\Mail::assertNothingSent();

        RateLimiter::clear('forgot-password|127.0.0.1');
    }

    public function test_webhook_rejects_invalid_signature_when_secret_is_configured(): void
    {
        config([
            'services.mercadopago.access_token' => 'fake-token',
            'services.mercadopago.webhook_secret' => 'top-secret',
        ]);

        $response = $this->postJson(
            route('webhooks.mercadopago'),
            ['type' => 'payment', 'data' => ['id' => '123']],
            ['x-signature' => 'ts=1700000000,v1=invalidhash', 'x-request-id' => 'abc-123']
        );

        $response->assertForbidden();
    }

    public function test_webhook_accepts_valid_signature_when_secret_is_configured(): void
    {
        $secret = 'top-secret';
        config([
            'services.mercadopago.access_token' => 'fake-token',
            'services.mercadopago.webhook_secret' => $secret,
        ]);

        $consulente = $this->makeConsulente();
        $request = $consulente->consultationRequests()->create([
            'modality' => 'online', 'description' => 'teste', 'status' => 'awaiting_payment',
        ]);
        $payment = \App\Models\Payment::create([
            'user_id' => $consulente->id,
            'payable_type' => ConsultationRequest::class,
            'payable_id' => $request->id,
            'amount' => 100,
            'status' => 'pending',
        ]);

        $ts = time();
        $requestId = 'req-123';
        $manifest = "id:777;request-id:{$requestId};ts:{$ts};";
        $hash = hash_hmac('sha256', $manifest, $secret);

        $fakeMpPayment = (object) ['status' => 'approved', 'external_reference' => (string) $payment->id];
        $this->mock(\App\Services\MercadoPagoService::class, function ($mock) use ($fakeMpPayment) {
            $mock->shouldReceive('fetchPayment')->once()->andReturn($fakeMpPayment);
        });

        $response = $this->postJson(
            route('webhooks.mercadopago'),
            ['type' => 'payment', 'data' => ['id' => '777']],
            ['x-signature' => "ts={$ts},v1={$hash}", 'x-request-id' => $requestId]
        );

        $response->assertOk();
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid']);
    }
}
