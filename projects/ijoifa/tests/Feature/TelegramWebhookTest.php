<?php

namespace Tests\Feature;

use App\Models\LiturgicalSetting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function fakeMessage(string $text): array
    {
        return [
            'message' => [
                'text' => $text,
                'chat' => ['id' => '111'],
                'from' => ['first_name' => 'Teste'],
            ],
        ];
    }

    public function test_proximomes_lists_next_month_ose_calendar_and_cycle_markers(): void
    {
        config(['services.telegram.bot_token' => 'fake-token']);
        LiturgicalSetting::current()->update(['orun_anchor' => '2026-06-22']);
        LiturgicalSetting::forget();

        Carbon::setTestNow(Carbon::parse('2026-06-15'));
        Http::fake();

        $this->postJson(route('webhooks.telegram'), $this->fakeMessage('/proximomes'))
            ->assertOk();

        Http::assertSent(function ($request) {
            $text = $request['text'] ?? '';

            return str_contains($text, 'Calendário Ọ̀sẹ̀')
                && str_contains($text, 'julho de 2026')
                && str_contains($text, 'Marcos do ciclo de 17 dias')
                && str_contains($text, 'Ìtádógún Ifá')
                && str_contains($text, 'Òrún Awó')
                && str_contains($text, 'Ìsán Awó')
                && str_contains($text, '01/07')
                && str_contains($text, '31/07');
        });

        Carbon::setTestNow();
    }
}
