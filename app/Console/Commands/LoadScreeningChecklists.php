<?php

namespace App\Console\Commands;

use App\Models\SystematicReview;
use Illuminate\Console\Command;

/**
 * Carrega o checklist de triagem (perguntas PICO em português) de cada projeto
 * a partir de database/data/screening_checklists.json, pelo código do protocolo (P01…).
 */
class LoadScreeningChecklists extends Command
{
    protected $signature = 'metanalises:checklists {--file= : outro arquivo JSON} {--dry-run : só mostra o que faria}';

    protected $description = 'Carrega o checklist de apoio à triagem dos projetos do programa de metanálises';

    public function handle(): int
    {
        $file = $this->option('file') ?: database_path('data/screening_checklists.json');
        $data = json_decode((string) @file_get_contents($file), true);
        if (!is_array($data)) {
            $this->error("Arquivo inválido ou não encontrado: {$file}");

            return self::FAILURE;
        }

        $rows = [];
        foreach ($data as $code => $items) {
            if (str_starts_with((string) $code, '_') || !is_array($items)) {
                continue;
            }
            $reviews = SystematicReview::where('protocol_code', $code)->where('is_training', false)->get();
            if ($reviews->isEmpty()) {
                $rows[] = [$code, '—', count($items), 'projeto não encontrado'];
                continue;
            }
            foreach ($reviews as $review) {
                $resolved = collect($review->exclusionReasons());
                $missing = collect($items)->pluck('code')->filter()
                    ->reject(fn ($c) => $resolved->contains(fn ($r) => preg_match('/^' . preg_quote($c, '/') . '\s+—/u', $r) === 1))
                    ->values()->all();
                if (!$this->option('dry-run')) {
                    $review->update(['screening_checklist' => array_values($items)]);
                }
                $rows[] = [$code, $review->id, count($items), $missing ? 'código sem motivo: ' . implode(', ', $missing) : 'ok'];
            }
        }
        $this->table(['Projeto', 'ID', 'Perguntas', 'Situação'], $rows);
        $this->info($this->option('dry-run') ? 'Nada gravado (dry-run).' : 'Checklists gravados.');

        return self::SUCCESS;
    }
}
