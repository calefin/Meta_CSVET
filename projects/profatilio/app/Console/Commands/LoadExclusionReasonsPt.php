<?php

namespace App\Console\Commands;

use App\Models\SystematicReview;
use Illuminate\Console\Command;

/**
 * Grava os rótulos em português dos motivos de exclusão (o que o aluno lê no botão da triagem)
 * a partir de database/data/exclusion_reasons_pt.json, pelo código do protocolo (P01…).
 * Só preenche códigos que existem no protocolo; não sobrescreve rótulo já editado no admin, salvo com --force.
 */
class LoadExclusionReasonsPt extends Command
{
    protected $signature = 'metanalises:motivos-pt {--file= : outro arquivo JSON} {--force : sobrescreve rótulos já preenchidos} {--dry-run : só mostra o que faria}';

    protected $description = 'Carrega os rótulos em português dos motivos de exclusão da triagem';

    public function handle(): int
    {
        $file = $this->option('file') ?: database_path('data/exclusion_reasons_pt.json');
        $data = json_decode((string) @file_get_contents($file), true);
        if (!is_array($data)) {
            $this->error("Arquivo inválido ou não encontrado: {$file}");

            return self::FAILURE;
        }
        $rows = [];
        foreach ($data as $code => $labels) {
            $reviews = SystematicReview::where('protocol_code', $code)->where('is_training', false)->get();
            if ($reviews->isEmpty()) {
                $rows[] = [$code, '—', 0, 'projeto não encontrado'];
                continue;
            }
            foreach ($reviews as $review) {
                $valid = collect($review->exclusionReasons())->map(fn ($r) => preg_match('/^(E\d+)(?!\d)/u', trim($r), $m) ? $m[1] : null)->filter()->all();
                $current = $review->exclusion_reasons_pt ?? [];
                $new = $current;
                foreach ((array) $labels as $c => $pt) {
                    if (in_array($c, $valid, true) && filled($pt) && ($this->option('force') || blank($current[$c] ?? null))) {
                        $new[$c] = trim((string) $pt);
                    }
                }
                if (!$this->option('dry-run') && $new !== $current) {
                    $review->update(['exclusion_reasons_pt' => $new]);
                }
                $rows[] = [$code, $review->id, count($new) - count($current) + ($this->option('force') ? 0 : 0), 'ok (' . count($new) . ' códigos)'];
            }
        }
        $this->table(['Projeto', 'ID', 'Novos', 'Situação'], $rows);
        $this->info($this->option('dry-run') ? 'Nada gravado (dry-run).' : 'Rótulos gravados.');

        return self::SUCCESS;
    }
}
