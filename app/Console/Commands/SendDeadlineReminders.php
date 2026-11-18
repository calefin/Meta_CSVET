<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Student;
use App\Services\TelegramService;
use App\Mail\BulkEmailAlunos;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;

class SendDeadlineReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-deadline-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envia lembretes automáticos de prazos de relatórios trimestrais de IC para os alunos via Telegram e E-mail.';

    /**
     * Execute the console command.
     */
    public function handle(TelegramService $telegramService)
    {
        $students = Student::with('user')->where('status', 'active')->get();
        $notifiedCount = 0;

        foreach ($students as $student) {
            $deadline = $student->getICDeadlineInfo();

            // Só processa se o aluno tiver IC ativo e não tiver entregue o relatório deste trimestre
            if (!$deadline['has_ic'] || $deadline['status'] === 'submitted') {
                continue;
            }

            $days = $deadline['days_remaining'];

            // Avisar se faltar 15 dias ou menos (ou se já estiver atrasado)
            if ($days <= 15) {
                $studentName = $student->user ? $student->user->name : 'Aluno';
                $dueDateStr = $deadline['due_date'] ? Carbon::parse($deadline['due_date'])->format('d/m/Y') : '-';
                $quarter = $deadline['quarter'] ?? '';
                
                $daysText = $days < 0 
                    ? "está atrasado há " . abs($days) . " dia(s)" 
                    : "vence em {$days} dia(s)";

                // 1. Enviar e-mail
                if ($student->user && $student->user->email) {
                    $subject = "⚠️ Lembrete de Prazo: Relatório de Iniciação Científica ({$quarter})";
                    
                    $htmlBody = "Olá, <strong>{$studentName}</strong>!<br><br>"
                              . "Este é um lembrete automático de que o prazo de entrega do seu relatório de Iniciação Científica "
                              . "referente ao <strong>{$quarter}</strong> {$daysText} (limite em <strong>{$dueDateStr}</strong>).<br><br>"
                              . "Por favor, acesse o portal <a href='https://profatilio.com.br/portal'>profatilio.com.br/portal</a> "
                              . "e realize a submissão do seu relatório o quanto antes para regularizar sua situação.<br><br>"
                              . "Caso já tenha submetido o documento ou combinado uma nova data diretamente com o orientador, favor desconsiderar este alerta.<br><br>"
                              . "Atenciosamente,<br>"
                              . "<strong>Prof. Dr. Atilio Sersun Calefi</strong>";

                    Mail::to($student->user->email)->queue(new BulkEmailAlunos($subject, $htmlBody, $studentName));
                }

                // 2. Enviar Telegram
                $telegramChatId = $student->telegram_chat_id ?? ($student->user ? $student->user->telegram_chat_id : null);
                if (!empty($telegramChatId)) {
                    $tgMessage = "⚠️ <b>Lembrete de Prazo - IC</b>\n\n"
                               . "Olá, <b>{$studentName}</b>!\n"
                               . "O prazo de entrega do seu relatório de Iniciação Científica (<b>{$quarter}</b>) {$daysText} (limite em <b>{$dueDateStr}</b>).\n\n"
                               . "Acesse o portal para enviar: <a href='https://profatilio.com.br/portal'>profatilio.com.br/portal</a>\n\n"
                               . "Atenciosamente,\n"
                               . "<b>Prof. Dr. Atilio Calefi</b>";

                    try {
                        $telegramService->sendMessage((string)$telegramChatId, $tgMessage);
                    } catch (\Exception $e) {
                        $this->error("Erro ao enviar Telegram para {$studentName}: " . $e->getMessage());
                    }
                }

                $notifiedCount++;
                $this->info("Notificado: {$studentName} ({$daysText})");
            }
        }

        $this->info("Processamento concluído. Alunos notificados: {$notifiedCount}");

        return self::SUCCESS;
    }
}
