<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DocumentSubmission;

class FixOrientationLevelMetadata extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fix-orientation-level-metadata {--apply : Grava as correções no banco. Sem essa flag, apenas lista o que seria alterado.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Corrige submissões de Termo de Aceite de Orientação de alunos de Graduação (TCC) que foram salvas incorretamente como "Iniciação Científica" no campo Nível da Orientação.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $apply = $this->option('apply');

        $submissions = DocumentSubmission::where('document_type', 'Termo de Aceite de Orientação')
            ->with('student')
            ->get()
            ->filter(function (DocumentSubmission $submission) {
                return ($submission->metadata['orientation_level'] ?? null) === 'Iniciação Científica'
                    && $submission->student
                    && $submission->student->academic_level === 'Graduação';
            });

        if ($submissions->isEmpty()) {
            $this->info('Nenhuma submissão para corrigir.');
            return self::SUCCESS;
        }

        $this->table(
            ['Processo', 'Aluno', 'Nível do Aluno', 'orientation_level atual'],
            $submissions->map(fn (DocumentSubmission $s) => [
                $s->process_id,
                $s->student->user->name ?? 'N/A',
                $s->student->academic_level,
                $s->metadata['orientation_level'] ?? '—',
            ])
        );

        if (!$apply) {
            $this->warn("{$submissions->count()} submissão(ões) seriam corrigidas para 'Graduação'. Rode novamente com --apply para gravar.");
            return self::SUCCESS;
        }

        foreach ($submissions as $submission) {
            $metadata = $submission->metadata ?? [];
            $metadata['orientation_level'] = 'Graduação';
            $submission->updateQuietly(['metadata' => $metadata]);
        }

        $this->info("{$submissions->count()} submissão(ões) corrigida(s) com sucesso.");
        return self::SUCCESS;
    }
}
