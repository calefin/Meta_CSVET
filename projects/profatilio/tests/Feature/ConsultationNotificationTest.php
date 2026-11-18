<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ConsultationRequest;
use App\Mail\ConsultationSubmitted;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ConsultationNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_consultation_form_sends_email_and_telegram_notifications(): void
    {
        Mail::fake();

        // 1. Mock TelegramService
        $mockTelegram = $this->createMock(TelegramService::class);
        $mockTelegram->expects($this->once())
            ->method('sendMessage')
            ->with(
                $this->equalTo('123456789'),
                $this->callback(function ($message) {
                    return str_contains($message, 'Nova Solicitação de Atendimento') && str_contains($message, 'John Doe');
                })
            )
            ->willReturn(true);
        $this->app->instance(TelegramService::class, $mockTelegram);

        // 2. Create an admin user with a telegram chat ID
        User::factory()->create([
            'role' => 'admin',
            'telegram_chat_id' => '123456789',
        ]);

        // 3. Post to submit-consultation route
        $response = $this->postJson(route('submit.consultation'), [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '(11) 99999-9999',
            'request_type' => 'Orçamento de Consultoria Acadêmica',
            'message' => 'Preciso de ajuda com meu TCC.',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'message' => 'Sua solicitação foi enviada com sucesso! Entraremos em contato em breve.',
        ]);

        // 4. Assert ConsultationRequest exists in database
        $this->assertDatabaseHas('consultation_requests', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '(11) 99999-9999',
            'request_type' => 'Orçamento de Consultoria Acadêmica',
            'message' => 'Preciso de ajuda com meu TCC.',
        ]);

        // 5. Assert Mail was queued
        Mail::assertQueued(ConsultationSubmitted::class, function ($mail) {
            return $mail->hasTo('contato@profatilio.com.br') &&
                   $mail->requestData->name === 'John Doe';
        });
    }
}
