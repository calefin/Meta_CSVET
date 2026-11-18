<?php

namespace App\Console\Commands;

use App\Models\DocumentSubmission;
use Illuminate\Console\Command;

class FixStuckAwaitingStudentStatuses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fix-stuck-awaiting-student-statuses {--dry-run : Apenas lista o que seria corrigido, sem salvar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Corrige processos de Concepção/Objetivos/etapas de redação de TCC que ficaram presos em "Aguardando X do Aluno" mesmo já tendo o conteúdo do aluno preenchido (bug do status inicial em criações diretas pelo aluno). Move para o status de correção do professor correspondente. Idempotente.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $fixedCount = 0;

        foreach (DocumentSubmission::TCC_STAGE_STATUS_PAIRS as $documentType => [$awaiting, $correction]) {
            $contentKeys = DocumentSubmission::TCC_STAGE_CONTENT_KEYS[$documentType] ?? [];

            $submissions = DocumentSubmission::where('document_type', $documentType)
                ->where('status', $awaiting)
                ->get();

            foreach ($submissions as $submission) {
                if (!$this->hasRealContent($submission, $contentKeys)) {
                    continue;
                }

                $this->info(($dryRun ? '[DRY-RUN] ' : '') . "{$submission->process_id} ({$submission->student?->user?->name}): "
                    . "'{$awaiting}' -> '{$correction}'");

                if (!$dryRun) {
                    $submission->status = $correction;
                    $submission->save();
                }

                $fixedCount++;
            }
        }

        $this->info($fixedCount > 0
            ? "Concluído. {$fixedCount} processo(s) " . ($dryRun ? 'seriam corrigidos' : 'corrigido(s)') . '.'
            : 'Nenhum processo preso encontrado. Nada a fazer.');

        return self::SUCCESS;
    }

    private function hasRealContent(DocumentSubmission $submission, array $contentKeys): bool
    {
        if (!empty($submission->google_drive_file_id)) {
            return true;
        }

        $metadata = $submission->metadata ?? [];
        foreach ($contentKeys as $key) {
            if (!empty($metadata[$key])) {
                return true;
            }
        }

        return false;
    }
}
