<?php

namespace App\Console\Commands;

use App\Filament\Pages\CentralMetanalises;
use App\Mail\AppNotificationMail;
use App\Models\SystematicReview;
use App\Models\User;
use App\Services\TelegramService;
use App\Support\ProgramJourney;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Prazo do protocolo: a busca formal sai em até 14 dias depois do registro no OSF.
 * Com 20 projetos correndo juntos, um atraso viraria desvio no artigo sem ninguém
 * perceber. Avisa o professor (e-mail + Telegram) quando faltam 3 dias ou menos,
 * ou quando venceu. Sem nada a avisar, não manda nada.
 */
class AlertSearchDeadlines extends Command
{
    protected $signature = 'app:alertar-prazo-busca {--dry-run : Só mostra o que seria avisado}';

    protected $description = 'Avisa o professor das buscas com prazo de 3 dias ou menos (ou vencidas) após o registro no OSF.';

    public function handle(): int
    {
        $lines = CentralMetanalises::programQuery()->withCount('records')->get()
            ->map(fn (SystematicReview $r) => [$r, ProgramJourney::searchWindow($r)])
            ->filter(fn ($pair) => in_array($pair[1]['level'] ?? null, ['urgent', 'late'], true))
            ->sortBy(fn ($pair) => $pair[1]['days_left'])
            ->map(fn ($pair) => '• ' . CentralMetanalises::codeOf($pair[0]) . ': ' . $pair[1]['text'])
            ->values();

        if ($lines->isEmpty()) {
            $this->info('Nenhuma busca perto do prazo.');

            return self::SUCCESS;
        }

        $title = 'Prazo da busca: ' . $lines->count() . ' projeto(s)';
        $body = "O protocolo manda buscar em até 14 dias depois do registro no OSF (e não antes de novembro).\n\n"
            . $lines->implode("\n")
            . "\n\nDepois da busca, importe os registros no projeto: o prazo some da Central.";
        $this->line($title . "\n" . $body);
        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $url = CentralMetanalises::getUrl(panel: 'admin');
        try {
            Mail::to((string) config('services.research.professor_email'))->send(new AppNotificationMail(
                title: $title, messageContent: $body, actionUrl: $url, actionText: 'Abrir a Central', subject: $title,
            ));
        } catch (\Throwable $e) {
            Log::error('Prazo da busca: falha no e-mail: ' . $e->getMessage());
        }
        $admin = User::where('role', 'admin')->whereNotNull('telegram_chat_id')->orderBy('id')->first();
        if ($admin) {
            try {
                app(TelegramService::class)->sendMessage((string) $admin->telegram_chat_id, '<b>' . e($title) . "</b>\n\n" . e($body) . "\n\n👉 <a href=\"" . e($url) . '">Abrir a Central</a>');
            } catch (\Throwable $e) {
                Log::error('Prazo da busca: falha no Telegram: ' . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
