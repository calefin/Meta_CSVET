<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\TelegramCourseSubscription;
use App\Services\TelegramBotService;
use App\Support\OseCalendar;
use App\Support\YorubaWeek;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

class TelegramWebhookController extends Controller
{
    private string $token;

    private string $adminChatId;

    private string $webhookSecret;

    public function __construct()
    {
        $this->token = (string) config('services.telegram.bot_token', '');
        $this->adminChatId = (string) config('services.telegram.admin_chat_id', '');
        $this->webhookSecret = (string) config('services.telegram.webhook_secret', '');
    }

    public function __invoke(Request $request): Response
    {
        if ($this->webhookSecret) {
            $header = $request->header('X-Telegram-Bot-Api-Secret-Token', '');
            if (! hash_equals($this->webhookSecret, $header)) {
                return response('', 403);
            }
        }

        $update = $request->json()->all();
        $message = $update['message'] ?? $update['edited_message'] ?? null;

        if ($message && isset($message['text'])) {
            $this->handle($message);
        }

        return response('', 200);
    }

    private function handle(array $message): void
    {
        $text = trim($message['text']);
        $chatId = (string) $message['chat']['id'];
        $chatType = $message['chat']['type'] ?? 'private';

        $this->captureTelegramUserId($message);

        if (! str_starts_with($text, '/')) {
            if ($chatType === 'private') {
                $this->forwardToAdmin($chatId, $message);
            }

            return;
        }

        $parts = explode(' ', $text, 2);
        $command = strtolower(explode('@', $parts[0])[0]);
        $arg = $parts[1] ?? null;

        match ($command) {
            '/start', '/ajuda' => $this->sendHelp($chatId),
            '/hoje' => $this->sendToday($chatId),
            '/ciclo' => $this->sendCycle($chatId),
            '/agenda' => $this->sendAgenda($chatId),
            '/consulta' => $this->sendConsulta($chatId),
            '/proximomes' => $this->sendNextMonthOse($chatId),
            '/curso', '/mensalidade' => $this->sendCourseInfo($chatId, $message),
            '/statuscurso' => $chatId === $this->adminChatId
                                    ? $this->sendAdminCourseReport($chatId)
                                    : null,
            '/ose' => $chatId === $this->adminChatId
                                    ? $this->sendOseDate($chatId, $arg)
                                    : null,
            default => null,
        };
    }

    private function sendToday(string $chatId): void
    {
        $date = Carbon::today();
        $ose = OseCalendar::forDate($date);
        $yoruba = YorubaWeek::forDate($date);
        $orisas = implode(', ', $ose['orisas']);

        $cycleDay = OseCalendar::cycleDay($date);

        $cycleLine = isset($ose['sub_name'])
            ? "🌙 <b>{$ose['sub_name']}</b>"
            : "Dia {$cycleDay} de 17 do ciclo";

        $text = "📅 <b>{$date->translatedFormat('d \d\e F \d\e Y')}</b>\n\n"
            ."<b>Semana Yorùbá:</b>\n"
            ."<b>{$yoruba['name']}</b>\n"
            ."<i>{$yoruba['meaning']}</i>\n\n"
            ."<b>Ọ̀sẹ̀:</b>\n"
            ."<b>{$ose['name']}</b>\n"
            ."<i>{$orisas}</i>\n\n"
            ."<b>Ciclo de 17 dias:</b>\n"
            .$cycleLine;

        $this->send($chatId, $text);
    }

    private function sendCycle(string $chatId): void
    {
        $date = Carbon::today();
        $cycleDay = OseCalendar::cycleDay($date);

        $ose = OseCalendar::forDate($date);
        $labelPart = isset($ose['sub_name']) ? " — <b>{$ose['sub_name']}</b>" : '';

        $daysToIsan = OseCalendar::daysUntil('isan', $date);
        $daysToItadogun = OseCalendar::daysUntil('itadogun', $date);
        $daysToOrun = OseCalendar::daysUntil('orun', $date) ?: 16;

        $text = "🌙 <b>Ciclo dos 17 dias</b>\n\n"
            ."Hoje é o dia <b>{$cycleDay}</b> de 17{$labelPart}\n\n";

        if ($daysToIsan > 0) {
            $text .= "📌 Ìsán Awó em {$daysToIsan} ".($daysToIsan === 1 ? 'dia' : 'dias')."\n";
        }
        if ($daysToItadogun > 0) {
            $text .= "📌 Ìtádógún em {$daysToItadogun} ".($daysToItadogun === 1 ? 'dia' : 'dias')."\n";
        }
        $text .= "📌 Próximo Òrún Awó em {$daysToOrun} ".($daysToOrun === 1 ? 'dia' : 'dias');

        $this->send($chatId, $text);
    }

    private function sendAgenda(string $chatId): void
    {
        $events = Event::query()
            ->where('starts_on', '>=', Carbon::today())
            ->orderBy('starts_on')
            ->limit(5)
            ->get();

        if ($events->isEmpty()) {
            $this->send($chatId, '📅 Nenhum evento próximo cadastrado.');

            return;
        }

        $lines = ["📅 <b>Próximos eventos:</b>\n"];
        foreach ($events as $event) {
            $date = Carbon::parse($event->starts_on)->translatedFormat('d \d\e F');
            $lines[] = "• <b>{$event->title}</b> — {$date}";
        }

        $this->send($chatId, implode("\n", $lines));
    }

    private function sendConsulta(string $chatId): void
    {
        $url = config('app.url').'/solicitar-consulta';
        $text = "🔮 <b>Solicitar Consulta Oracular</b>\n\n"
            ."Acesse o link abaixo para solicitar uma consulta com o Babalaô:\n\n"
            ."<a href=\"{$url}\">{$url}</a>";

        $this->send($chatId, $text);
    }

    private function sendNextMonthOse(string $chatId): void
    {
        $firstDay = Carbon::today()->startOfMonth()->addMonthNoOverflow();
        $lastDay = $firstDay->copy()->endOfMonth();

        $occurrences = OseCalendar::specialOccurrencesBetween($firstDay, $lastDay);

        $lines = ["📅 <b>Calendário Ọ̀sẹ̀ — {$firstDay->translatedFormat('F \d\e Y')}</b>\n"];

        $lines[] = '🌙 <b>Marcos do ciclo de 17 dias:</b>';
        if ($occurrences === []) {
            $lines[] = 'Nenhum marco cai neste mês.';
        } else {
            foreach ($occurrences as $occurrence) {
                $lines[] = "📌 <b>{$occurrence['label']}</b> — {$occurrence['date']->format('d/m')}";
            }
        }

        $lines[] = "\n<b>Dias do mês:</b>";

        $cursor = $firstDay->copy();
        while ($cursor->lte($lastDay)) {
            $ose = OseCalendar::forDate($cursor);
            $orisas = implode(', ', $ose['orisas']);
            $marker = isset($ose['sub_name']) ? " — 🌙 {$ose['sub_name']}" : '';

            $lines[] = "{$cursor->format('d/m')} — {$ose['name']} ({$orisas}){$marker}";

            $cursor->addDay();
        }

        $this->send($chatId, implode("\n", $lines));
    }

    private function sendOseDate(string $chatId, ?string $arg): void
    {
        if (! $arg) {
            $this->send($chatId, 'Uso: /ose YYYY-MM-DD');

            return;
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', trim($arg));
            if (! $date) {
                throw new \InvalidArgumentException;
            }
        } catch (\Throwable) {
            $this->send($chatId, 'Data inválida. Use o formato YYYY-MM-DD.');

            return;
        }

        $ose = OseCalendar::forDate($date);
        $yoruba = YorubaWeek::forDate($date);
        $orisas = implode(', ', $ose['orisas']);

        $cycleDay = OseCalendar::cycleDay($date);

        $cycleLine = isset($ose['sub_name'])
            ? "🌙 <b>{$ose['sub_name']}</b>"
            : "Dia {$cycleDay} de 17 do ciclo";

        $text = "📅 <b>{$date->translatedFormat('d \d\e F \d\e Y')}</b>\n\n"
            ."<b>{$yoruba['name']}</b> — <i>{$yoruba['meaning']}</i>\n\n"
            ."<b>Ọ̀sẹ̀:</b> {$ose['name']}\n"
            ."<i>{$orisas}</i>\n\n"
            ."<b>Ciclo de 17:</b>\n"
            .$cycleLine;

        $this->send($chatId, $text);
    }

    private function sendHelp(string $chatId): void
    {
        $text = "Olá! Sou o assistente do <b>Ìjọ Ifá Èjìogbè</b>. 🌿\n\n"
            ."<b>Comandos disponíveis:</b>\n\n"
            ."/hoje — Semana Yorùbá e Ọ̀sẹ̀ de hoje\n"
            ."/ciclo — Posição no ciclo de 17 dias\n"
            ."/proximomes — Òrún/Ìsán/Ìtádógún e o calendário Ọ̀sẹ̀ completo do próximo mês\n"
            ."/agenda — Próximos eventos da casa\n"
            ."/consulta — Link para solicitar consulta oracular\n"
            ."/curso — Informações e status da mensalidade do curso\n"
            ."/ajuda — Esta mensagem\n\n"
            .'Ou envie uma mensagem livre — o Babalaô irá ler e responder pessoalmente.';

        $this->send($chatId, $text);
    }

    private function sendCourseInfo(string $chatId, array $message): void
    {
        $fromUsername = $message['from']['username'] ?? null;
        $url = route('public-telegram-course-registration');

        if ($fromUsername) {
            $sub = TelegramCourseSubscription::where('telegram_username', '@'.$fromUsername)
                ->orWhere('telegram_username', $fromUsername)
                ->latest()
                ->first();

            if ($sub) {
                if ($sub->isActive()) {
                    $expiryStr = $sub->expires_at ? $sub->expires_at->format('d/m/Y') : 'Ativo';
                    $text = "✈️ <b>Inscrição no Curso Telegram — ATIVA</b>\n\n"
                        ."Aluno: <b>{$sub->full_name}</b>\n"
                        ."Status: <b>Ativo / Adimplente</b>\n"
                        ."Vencimento da mensalidade: <b>{$expiryStr}</b>\n\n"
                        .'Obrigado por caminhar conosco nos estudos tradicionais! 🙏';
                    $this->send($chatId, $text);

                    return;
                } else {
                    $text = "⚠️ <b>Inscrição no Curso Telegram — PENDENTE / ATRASADA</b>\n\n"
                        ."Aluno: <b>{$sub->full_name}</b>\n"
                        ."Sua mensalidade de R$ 180,00 precisa ser renovada.\n\n"
                        ."Para regularizar e manter seu acesso ao grupo, acesse:\n"
                        ."<a href=\"{$url}\">{$url}</a>";
                    $this->send($chatId, $text);

                    return;
                }
            }
        }

        $text = "🌿 <b>Curso de Formação Contínua no Telegram</b>\n\n"
            ."• 2 aulas por semana no Telegram\n"
            ."• 1 encontro presencial por mês para práticas\n"
            ."• Investimento: R$ 180,00 / mês\n\n"
            ."Para se inscrever e aceitar os termos de participação, acesse:\n"
            ."<a href=\"{$url}\">{$url}</a>";

        $this->send($chatId, $text);
    }

    private function sendAdminCourseReport(string $chatId): void
    {
        $report = TelegramBotService::generateCourseStatusReport();
        $this->send($chatId, $report);
    }

    /**
     * The Bot API's banChatMember only accepts a numeric user_id, never a
     *
     * @username - but registration only ever collects the username. This
     * opportunistically records it the first time we see a message from
     * that Telegram account (DM or group), so "remover do grupo" in the
     * admin panel has something to actually call.
     */
    private function captureTelegramUserId(array $message): void
    {
        $username = $message['from']['username'] ?? null;
        $userId = $message['from']['id'] ?? null;

        if (! $username || ! $userId) {
            return;
        }

        $normalized = strtolower(ltrim($username, '@'));

        TelegramCourseSubscription::whereNull('telegram_user_id')
            ->whereNotNull('telegram_username')
            ->get()
            ->first(fn ($sub) => strtolower(ltrim($sub->telegram_username, '@')) === $normalized)
            ?->update(['telegram_user_id' => (string) $userId]);
    }

    private function forwardToAdmin(string $fromChatId, array $message): void
    {
        if ($fromChatId === $this->adminChatId) {
            return;
        }

        $from = $message['from'] ?? [];
        $name = trim(($from['first_name'] ?? '').' '.($from['last_name'] ?? ''));
        $username = isset($from['username']) ? " (@{$from['username']})" : '';
        $text = $message['text'];

        $forward = "💬 <b>Nova mensagem recebida</b>\n"
            ."De: <b>{$name}</b>{$username}\n"
            ."Chat ID: <code>{$fromChatId}</code>\n\n"
            .htmlspecialchars($text, ENT_QUOTES);

        $this->send($this->adminChatId, $forward);

        $this->send($fromChatId, 'Mensagem recebida. O Babalaô irá responder em breve. 🙏');
    }

    private function send(string $chatId, string $text): void
    {
        if (! $this->token || ! $chatId) {
            return;
        }

        Http::post("https://api.telegram.org/bot{$this->token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ]);
    }
}
