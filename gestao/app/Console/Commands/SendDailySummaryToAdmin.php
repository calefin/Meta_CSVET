<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Owner;
use App\Models\OwnerSettings;
use App\Models\Student;
use App\Models\DocumentSubmission;
use App\Services\TelegramService;
use App\Support\OwnerContext;
use Illuminate\Support\Carbon;

class SendDailySummaryToAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-daily-summary';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envia um resumo diário das solicitações da plataforma para o Telegram de cada professor com conta vinculada.';

    /**
     * Execute the console command.
     */
    public function handle(TelegramService $telegramService)
    {
        $settingsWithTelegram = OwnerSettings::whereNotNull('telegram_chat_id')
            ->where('telegram_chat_id', '!=', '')
            ->get();

        if ($settingsWithTelegram->isEmpty()) {
            $this->warn('Nenhum professor com Telegram vinculado encontrado.');
            return self::SUCCESS;
        }

        foreach ($settingsWithTelegram as $settings) {
            $owner = Owner::find($settings->owner_user_id);
            if (!$owner) {
                continue;
            }

            // Escopa as queries abaixo (Student/DocumentSubmission usam
            // BelongsToOwner) só pros dados deste professor.
            OwnerContext::set($owner->id);

            $this->sendSummaryFor($owner, $settings, $telegramService);
        }

        OwnerContext::clear();

        return self::SUCCESS;
    }

    private function sendSummaryFor(Owner $owner, OwnerSettings $settings, TelegramService $telegramService): void
    {
        // 1. Pending Approvals
        $pendingStudents = Student::with('user')
            ->where('status', 'awaiting_advisor_approval')
            ->get();

        // 2. New Submissions (last 24 hours)
        $yesterday = Carbon::now()->subDay();
        $newSubmissions = DocumentSubmission::with('student.user')
            ->where('created_at', '>=', $yesterday)
            ->get();

        // 3. Pending Professor Actions
        $pendingStatuses = [
            'Aguardando Correção do Professor',
            'Projeto em Análise Inicial pelo Orientador',
            'Aguardando Revisão do Professor',
            'Avaliações de Estágio Registradas e Recebidas',
            'Relatório de Necropsia Recebido - Aguardando Análise do Professor',
            'Termos Aceitos e Auditados - Aguardando Assinatura do Orientador',
            'Em análise final para assinatura institucional'
        ];

        $pendingSubmissions = DocumentSubmission::with('student.user')
            ->whereIn('status', $pendingStatuses)
            ->orderBy('created_at', 'asc')
            ->get();

        if ($pendingStudents->isEmpty() && $newSubmissions->isEmpty() && $pendingSubmissions->isEmpty()) {
            // Sem novidade nenhuma pra esse professor hoje — não manda mensagem vazia.
            return;
        }

        // Build the report
        $todayStr = Carbon::now()->format('d/m/Y');

        $msg = "📅 <b>Resumo Diário</b>\n";
        $msg .= "<i>Relatório de {$todayStr}</i>\n\n";

        // Approvals section
        $msg .= "👤 <b>Novos Cadastros Pendentes:</b> " . $pendingStudents->count() . "\n";
        if ($pendingStudents->isNotEmpty()) {
            foreach ($pendingStudents as $student) {
                $name = $student->user ? $student->user->name : 'N/A';
                $reg = $student->registration ?? 'Sem Reg.';
                $msg .= "• {$name} (Matrícula: {$reg})\n";
            }
        } else {
            $msg .= "• Nenhum cadastro aguardando aprovação.\n";
        }
        $msg .= "\n";

        // New submissions section
        $msg .= "📥 <b>Novos Envios (últimas 24h):</b> " . $newSubmissions->count() . "\n";
        if ($newSubmissions->isNotEmpty()) {
            foreach ($newSubmissions as $sub) {
                $studentName = $sub->student && $sub->student->user ? $sub->student->user->name : 'Estudante';
                $msg .= "• <b>[{$sub->document_type}]</b> {$studentName} - <i>{$sub->title}</i>\n";
            }
        } else {
            $msg .= "• Nenhuma nova submissão registrada.\n";
        }
        $msg .= "\n";

        // Pending corrections section
        $msg .= "⏳ <b>Aguardando Sua Correção/Assinatura:</b> " . $pendingSubmissions->count() . "\n";
        if ($pendingSubmissions->isNotEmpty()) {
            $limit = 10;
            $itemsToShow = $pendingSubmissions->take($limit);
            foreach ($itemsToShow as $sub) {
                $studentName = $sub->student && $sub->student->user ? $sub->student->user->name : 'Estudante';
                $days = Carbon::parse($sub->created_at)->diffInDays();
                $dayStr = $days === 0 ? "hoje" : "há {$days} dia(s)";
                $msg .= "• <b>[{$sub->document_type}]</b> {$studentName} ({$dayStr})\n";
            }
            if ($pendingSubmissions->count() > $limit) {
                $msg .= "• <i>... e mais " . ($pendingSubmissions->count() - $limit) . " item(ns) na fila.</i>\n";
            }
        } else {
            $msg .= "• Fila limpa! Nenhuma pendência sob sua responsabilidade.\n";
        }

        $telegramService->sendMessage((string) $settings->telegram_chat_id, $msg);
        $this->info("Resumo enviado para: {$owner->name} (Chat ID: {$settings->telegram_chat_id})");
    }
}
