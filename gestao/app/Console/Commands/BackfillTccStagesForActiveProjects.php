<?php

namespace App\Console\Commands;

use App\Models\DocumentSubmission;
use Illuminate\Console\Command;

class BackfillTccStagesForActiveProjects extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:backfill-tcc-stages';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cria as etapas de escrita do TCC (Introdução, Materiais e Métodos, Resultados, Discussão, Conclusão) para alunos que já têm um TCC em andamento, forçando a nova sequência. Idempotente — não duplica etapas já existentes.';

    private const STAGES = [
        'Introdução de TCC',
        'Materiais e Métodos de TCC',
        'Resultados de TCC',
        'Discussão de TCC',
        'Conclusão de TCC',
    ];

    public function handle(): int
    {
        $activeTccs = DocumentSubmission::where('document_type', 'TCC')
            ->get()
            ->filter(fn (DocumentSubmission $submission) => !$submission->isConcluded());

        if ($activeTccs->isEmpty()) {
            $this->info('Nenhum TCC em andamento encontrado. Nada a fazer.');
            return self::SUCCESS;
        }

        $studentsAffected = 0;
        $stagesCreated = 0;

        foreach ($activeTccs as $tcc) {
            $studentId = $tcc->student_id;
            if (!$studentId) {
                continue;
            }

            $createdForThisStudent = 0;

            foreach (self::STAGES as $stageType) {
                $alreadyExists = DocumentSubmission::where('student_id', $studentId)
                    ->where('document_type', $stageType)
                    ->exists();

                if ($alreadyExists) {
                    continue;
                }

                DocumentSubmission::create([
                    'student_id' => $studentId,
                    'academic_title_id' => $tcc->academic_title_id,
                    'title' => $tcc->title,
                    'document_type' => $stageType,
                ]);

                $createdForThisStudent++;
                $stagesCreated++;
            }

            if ($createdForThisStudent > 0) {
                $studentsAffected++;
                $this->info("Etapas criadas para {$tcc->student?->user?->name} (\"{$tcc->title}\"): {$createdForThisStudent} etapa(s).");
                $this->notifyStudent($tcc);
            }
        }

        $this->info("Concluído. {$stagesCreated} etapa(s) criada(s) para {$studentsAffected} aluno(s) com TCC em andamento.");

        return self::SUCCESS;
    }

    private function notifyStudent(DocumentSubmission $tcc): void
    {
        $student = $tcc->student;
        if (!$student || !$student->user) {
            return;
        }

        try {
            \Illuminate\Support\Facades\Mail::to($student->user->email)->queue(
                new \App\Mail\AppNotificationMail(
                    title: 'Nova etapa de acompanhamento do seu TCC',
                    messageContent: "Olá!\n\nA partir de agora, o TCC \"{$tcc->title}\" passa a ser acompanhado por etapas: Introdução, Materiais e Métodos, Resultados, Discussão e Conclusão. Cada etapa precisa ser enviada e aprovada pelo orientador, em ordem, antes que o TCC final possa ser concluído.\n\nAcesse o portal e envie a Introdução para começar.",
                    actionUrl: url('/portal'),
                    actionText: 'Acessar Portal',
                    subject: 'Nova etapa de acompanhamento do seu TCC'
                )
            );

            $telegramChatId = $student->telegram_chat_id ?? ($student->user->telegram_chat_id ?? null);
            if (!empty($telegramChatId)) {
                $telegramService = app(\App\Services\TelegramService::class);
                $tgMessage = "<b>📚 Nova etapa de acompanhamento do seu TCC</b>\n\nOlá!\nA partir de agora, o TCC \"" . e($tcc->title) . "\" passa a ser acompanhado por etapas: Introdução, Materiais e Métodos, Resultados, Discussão e Conclusão, em ordem.\n\n<a href=\"" . url('/portal') . "\">👉 Acessar Portal e Enviar a Introdução</a>";
                $telegramService->sendMessage($telegramChatId, $tgMessage);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Erro ao notificar aluno sobre novas etapas de TCC (student_id={$student->id}): " . $e->getMessage());
        }
    }
}
