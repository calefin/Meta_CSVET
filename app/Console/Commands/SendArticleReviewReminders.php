<?php

namespace App\Console\Commands;

use App\Models\ArticleReview;
use App\Models\ArticleReviewer;
use App\Services\ArticleReviewService;
use Illuminate\Console\Command;

class SendArticleReviewReminders extends Command
{
    protected $signature = 'app:lembrar-revisoes-artigo {--dry-run : Só lista quem receberia lembrete}';

    protected $description = 'Lembra o revisor da vez 1 dia antes do prazo e a cada 3 dias após o vencimento; avisa o professor no 1º dia de atraso.';

    public function handle(ArticleReviewService $service): int
    {
        $reviewers = ArticleReviewer::with('articleReview')
            ->where('status', ArticleReviewer::STATUS_IN_REVIEW)
            ->whereNotNull('due_at')
            ->whereNotNull('email')
            ->whereHas('articleReview', fn ($q) => $q->where('status', ArticleReview::STATUS_IN_REVIEW))
            ->get();

        $sent = 0;

        foreach ($reviewers as $reviewer) {
            $hoursToDue = now()->diffInHours($reviewer->due_at, false);
            $lastReminder = $reviewer->last_reminder_at;

            $dueSoon = $hoursToDue > 0 && $hoursToDue <= 36 && (!$lastReminder || $lastReminder->lt(now()->subHours(30)));
            $overdue = $hoursToDue <= 0 && (!$lastReminder || $lastReminder->lt(now()->subDays(3)->addHours(2)));

            if (!$dueSoon && !$overdue) {
                continue;
            }

            $this->line(($overdue ? '[ATRASADO] ' : '[LEMBRETE] ') . "{$reviewer->name} — {$reviewer->articleReview->title}");

            if ($this->option('dry-run')) {
                continue;
            }

            $service->sendReminder($reviewer, null, true);
            $sent++;

            if ($overdue && !$reviewer->overdue_notified) {
                $service->notifyProfessor(
                    '⏰ Revisão de artigo em atraso',
                    "{$reviewer->name} ({$reviewer->email}) está com a revisão de \"{$reviewer->articleReview->title}\" vencida desde "
                        . $reviewer->due_at->format('d/m/Y') . '. Um lembrete automático foi enviado; você pode prorrogar, enviar um aviso ou pular o revisor no painel.',
                    $reviewer->articleReview
                );
                $reviewer->forceFill(['overdue_notified' => true])->save();
            }
        }

        $this->info("Lembretes enviados: {$sent}");

        return self::SUCCESS;
    }
}
