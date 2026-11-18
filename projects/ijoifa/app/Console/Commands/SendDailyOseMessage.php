<?php

namespace App\Console\Commands;

use App\Support\OseCalendar;
use App\Support\YorubaWeek;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SendDailyOseMessage extends Command
{
    protected $signature = 'ose:daily-message {--date= : Date to use (YYYY-MM-DD), defaults to today}';

    protected $description = 'Send the daily Ọ̀sẹ̀ report to the admin Telegram bot';

    public function handle(): int
    {
        $token = config('services.telegram.bot_token');
        $chatId = config('services.telegram.admin_chat_id');

        if (! $token || ! $chatId) {
            $this->error('TELEGRAM_BOT_TOKEN or TELEGRAM_ADMIN_CHAT_ID not set.');
            return self::FAILURE;
        }

        $date = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();

        $message = $this->buildMessage($date);

        $response = Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message,
        ]);

        if (! $response->successful()) {
            $this->error('Telegram API error: ' . $response->body());
            return self::FAILURE;
        }

        $this->info('Mensagem enviada para ' . $date->toDateString());
        return self::SUCCESS;
    }

    private function buildMessage(Carbon $date): string
    {
        $ose = OseCalendar::forDate($date);
        $yoruba = YorubaWeek::forDate($date);

        $gregorianDays = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
        $gregorianDay = $gregorianDays[$date->dayOfWeek];

        $dateStr = $date->translatedFormat('d \d\e F \d\e Y');

        $orisas = implode(', ', $ose['orisas']);

        // 17-day cycle position
        $cycleDay = OseCalendar::cycleDay($date);

        $specialLine = isset($ose['sub_name'])
            ? "*{$ose['sub_name']}*"
            : "Dia {$cycleDay} de 17 do ciclo";

        $lines = [
            "*Relatório Espiritual — {$dateStr}*",
            "{$gregorianDay}",
            '',
            '*Semana Yorùbá:*',
            "*{$yoruba['name']}*",
            "_{$yoruba['meaning']}_",
            '',
            '*Ọ̀sẹ̀ de hoje:*',
            "*{$ose['name']}*",
            "_{$orisas}_",
            '',
            '*Ciclo dos 17 dias:*',
            $specialLine,
        ];

        return implode("\n", $lines);
    }
}
