<?php

namespace App\Console\Commands;

use App\Mail\BulkEmailAlunos;
use App\Models\Deadline;
use App\Models\NotificationLog;
use App\Models\Student;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendProjectDeadlineReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-project-deadline-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envia cobranças recorrentes de prazos de entrega vencidos e avisos de contagem regressiva para datas de apresentação de TCC (e-mail + Telegram).';

    protected TelegramService $telegramService;

    /**
     * Execute the console command.
     */
    public function handle(TelegramService $telegramService)
    {
        $this->telegramService = $telegramService;

        $deadlines = Deadline::with(['student.user', 'academicTitle'])
            ->where('status', 'pending')
            ->get();

        $chargedCount = 0;
        $milestoneCount = 0;

        foreach ($deadlines as $deadline) {
            $student = $deadline->student;
            if (!$student || !$student->user) {
                continue;
            }

            if ($deadline->isApresentacao()) {
                $milestoneCount += $this->sendPresentationMilestones($deadline, $student);
            } elseif ($deadline->isDueForCharge()) {
                $this->sendCharge($deadline, $student);
                $chargedCount++;
            }
        }

        $this->info("Processamento concluído. Cobranças enviadas: {$chargedCount}. Avisos de apresentação enviados: {$milestoneCount}.");

        return self::SUCCESS;
    }

    protected function sendCharge(Deadline $deadline, Student $student): void
    {
        $studentName = $student->user->name;
        $dueDateStr = $deadline->due_date->format('d/m/Y');
        $daysLate = (int) now()->diffInDays($deadline->due_date, true);
        $projectRef = $deadline->academicTitle ? " (\"{$deadline->academicTitle->title}\")" : '';
        $category = $deadline->academicTitle?->type === 'TCC' ? 'cobranca_tcc' : 'cobranca_ic';

        if ($student->user->email) {
            $subject = "⚠️ Cobrança de Prazo: {$deadline->title}";

            $htmlBody = "Olá, <strong>{$studentName}</strong>!<br><br>"
                . "Este é um lembrete automático de que o prazo para <strong>\"{$deadline->title}\"</strong>{$projectRef} "
                . "venceu em <strong>{$dueDateStr}</strong> (há {$daysLate} dia(s)) e ainda não foi regularizado.<br><br>"
                . "Por favor, acesse o portal <a href='https://profatilio.com.br/portal'>profatilio.com.br/portal</a> "
                . "e realize o envio o quanto antes para regularizar sua situação.<br><br>"
                . "Caso já tenha resolvido isso diretamente com o orientador, favor desconsiderar este alerta.<br><br>"
                . "Atenciosamente,<br><strong>Prof. Dr. Atilio Sersun Calefi</strong>";

            Mail::to($student->user->email)->queue(new BulkEmailAlunos($subject, $htmlBody, $studentName));

            NotificationLog::create([
                'student_id' => $student->id,
                'academic_title_id' => $deadline->academic_title_id,
                'channel' => 'email',
                'category' => $category,
                'title' => $subject,
                'message' => strip_tags($htmlBody),
                'status' => 'success',
                'sent_at' => now(),
            ]);
        }

        $telegramChatId = $student->telegram_chat_id ?? $student->user->telegram_chat_id;
        if (!empty($telegramChatId)) {
            $tgMessage = "⚠️ <b>Cobrança de Prazo</b>\n\n"
                . "Olá, <b>{$studentName}</b>!\n"
                . "O prazo para <b>\"{$deadline->title}\"</b>{$projectRef} venceu em <b>{$dueDateStr}</b> (há {$daysLate} dia(s)) e ainda não foi regularizado.\n\n"
                . "Acesse o portal para resolver: <a href='https://profatilio.com.br/portal'>profatilio.com.br/portal</a>\n\n"
                . "Atenciosamente,\n<b>Prof. Dr. Atilio Calefi</b>";

            try {
                $this->telegramService->sendMessage((string) $telegramChatId, $tgMessage);
                NotificationLog::create([
                    'student_id' => $student->id,
                    'academic_title_id' => $deadline->academic_title_id,
                    'channel' => 'telegram',
                    'category' => $category,
                    'title' => "Cobrança de Prazo: {$deadline->title}",
                    'message' => strip_tags($tgMessage),
                    'status' => 'success',
                    'sent_at' => now(),
                ]);
            } catch (\Exception $e) {
                $this->error("Erro ao enviar Telegram para {$studentName}: " . $e->getMessage());
                NotificationLog::create([
                    'student_id' => $student->id,
                    'academic_title_id' => $deadline->academic_title_id,
                    'channel' => 'telegram',
                    'category' => $category,
                    'title' => "Cobrança de Prazo: {$deadline->title}",
                    'message' => $e->getMessage(),
                    'status' => 'failed',
                    'sent_at' => now(),
                ]);
            }
        }

        $deadline->update(['last_charged_at' => now()]);
        $this->info("Cobrança enviada: {$studentName} — {$deadline->title} ({$daysLate} dia(s) de atraso)");
    }

    /**
     * Dispara, em sequência, todos os avisos de contagem regressiva ainda
     * pendentes para uma data de apresentação (cobre o caso de o prazo ter
     * sido cadastrado quando já faltavam poucos dias, sem "pular" avisos).
     * Marca o prazo como cumprido assim que a data de apresentação passa.
     */
    protected function sendPresentationMilestones(Deadline $deadline, Student $student): int
    {
        $sent = 0;

        while (($milestone = $deadline->getNextMilestone()) !== null) {
            $daysRemaining = (int) now()->startOfDay()->diffInDays($deadline->due_date->copy()->startOfDay(), false);
            $this->sendPresentationAlert($deadline, $student, max($daysRemaining, 0));

            $notified = $deadline->notified_milestones ?? [];
            $notified[] = $milestone;
            $deadline->update(['notified_milestones' => $notified]);

            $sent++;
        }

        if ($deadline->due_date && $deadline->due_date->isPast()) {
            $deadline->update(['status' => 'fulfilled']);
        }

        return $sent;
    }

    protected function sendPresentationAlert(Deadline $deadline, Student $student, int $daysRemaining): void
    {
        $studentName = $student->user->name;
        $dueDateStr = $deadline->due_date->format('d/m/Y');
        $daysText = $daysRemaining === 0 ? 'HOJE' : "em {$daysRemaining} dia(s)";

        if ($student->user->email) {
            $subject = "🎓 Faltam {$daysRemaining} dia(s) para: {$deadline->title}";

            $htmlBody = "Olá, <strong>{$studentName}</strong>!<br><br>"
                . "Estamos a <strong>{$daysText}</strong> da sua <strong>\"{$deadline->title}\"</strong>, "
                . "marcada para <strong>{$dueDateStr}</strong>.<br><br>"
                . "Aproveite para revisar seus materiais e alinhar os últimos detalhes com o orientador.<br><br>"
                . "Atenciosamente,<br><strong>Prof. Dr. Atilio Sersun Calefi</strong>";

            Mail::to($student->user->email)->queue(new BulkEmailAlunos($subject, $htmlBody, $studentName));

            NotificationLog::create([
                'student_id' => $student->id,
                'academic_title_id' => $deadline->academic_title_id,
                'channel' => 'email',
                'category' => 'apresentacao_milestone',
                'title' => $subject,
                'message' => strip_tags($htmlBody),
                'status' => 'success',
                'sent_at' => now(),
            ]);
        }

        $telegramChatId = $student->telegram_chat_id ?? $student->user->telegram_chat_id;
        if (!empty($telegramChatId)) {
            $tgMessage = "🎓 <b>Contagem Regressiva</b>\n\n"
                . "Olá, <b>{$studentName}</b>!\n"
                . "Estamos a <b>{$daysText}</b> da sua <b>\"{$deadline->title}\"</b>, marcada para <b>{$dueDateStr}</b>.\n\n"
                . "Atenciosamente,\n<b>Prof. Dr. Atilio Calefi</b>";

            try {
                $this->telegramService->sendMessage((string) $telegramChatId, $tgMessage);
                NotificationLog::create([
                    'student_id' => $student->id,
                    'academic_title_id' => $deadline->academic_title_id,
                    'channel' => 'telegram',
                    'category' => 'apresentacao_milestone',
                    'title' => "Contagem Regressiva: {$deadline->title}",
                    'message' => strip_tags($tgMessage),
                    'status' => 'success',
                    'sent_at' => now(),
                ]);
            } catch (\Exception $e) {
                $this->error("Erro ao enviar Telegram para {$studentName}: " . $e->getMessage());
                NotificationLog::create([
                    'student_id' => $student->id,
                    'academic_title_id' => $deadline->academic_title_id,
                    'channel' => 'telegram',
                    'category' => 'apresentacao_milestone',
                    'title' => "Contagem Regressiva: {$deadline->title}",
                    'message' => $e->getMessage(),
                    'status' => 'failed',
                    'sent_at' => now(),
                ]);
            }
        }

        $this->info("Aviso de apresentação enviado: {$studentName} — {$deadline->title} (faltam {$daysRemaining} dia(s))");
    }
}
