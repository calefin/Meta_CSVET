<?php

namespace App\Console\Commands;

use App\Models\DocumentSubmission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RepairBrokenReturnedFiles extends Command
{
    protected $signature = 'app:reparar-devolucoes
                            {--aplicar : Grava a correção no banco (sem isso, só simula)}';

    protected $description = 'Corrige devoluções (arquivo assinado/corrigido) que apontam para um caminho inexistente no Drive, usando o nome renomeado do próprio processo';

    public function handle(): int
    {
        $diskName = in_array(config('filesystems.default'), ['google', 'onedrive']) ? config('filesystems.default') : 'public';
        $disk = Storage::disk($diskName);
        $apply = (bool) $this->option('aplicar');

        $rows = [];
        $missing = 0;

        DocumentSubmission::with('student.user')
            ->whereNotNull('google_drive_returned_file_id')
            ->where('google_drive_returned_file_id', '!=', '')
            ->orderBy('id')
            ->each(function (DocumentSubmission $submission) use ($disk, $apply, &$rows, &$missing) {
                $path = $submission->google_drive_returned_file_id;

                // Devoluções múltiplas e links externos ficam de fora
                if (!is_string($path) || str_contains($path, 'http') || $disk->exists($path)) {
                    return;
                }

                $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'pdf';
                $expected = $submission->expectedReturnedFilePath($extension);

                if ($expected !== $path && $disk->exists($expected)) {
                    $rows[] = [$submission->id, $submission->process_id, $path, $expected];
                    if ($apply) {
                        DB::table('document_submissions')
                            ->where('id', $submission->id)
                            ->update(['google_drive_returned_file_id' => $expected]);
                    }
                } else {
                    $missing++;
                    $this->warn("#{$submission->id} {$submission->process_id}: '{$path}' não existe e o nome renomeado também não foi encontrado — reenviar a devolução manualmente.");
                }
            });

        if ($rows) {
            $this->table(['ID', 'Processo', 'Caminho gravado (inexistente)', 'Arquivo encontrado'], $rows);
        }

        $this->info(count($rows) . ' devolução(ões) ' . ($apply ? 'corrigida(s).' : 'corrigível(is) — rode com --aplicar para gravar.'));
        if ($missing) {
            $this->warn("{$missing} devolução(ões) sem arquivo localizável.");
        }

        return self::SUCCESS;
    }
}
