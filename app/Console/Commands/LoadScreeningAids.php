<?php

namespace App\Console\Commands;

use App\Models\SystematicReview;
use Illuminate\Console\Command;

/**
 * Carrega as ajudas de leitura da triagem (termos em vermelho e quadro "Atenção neste projeto")
 * a partir de database/data/screening_aids.json, pelo código do protocolo (P01…).
 * Não há sugestão por artigo: as ajudas são iguais para todos os registros e para os dois triadores.
 */
class LoadScreeningAids extends Command
{
    protected $signature = 'metanalises:ajudas {--file= : outro arquivo JSON} {--dry-run : só mostra o que faria}';

    protected $description = 'Carrega os termos em vermelho e o quadro de atenção da triagem dos projetos do programa de metanálises';

    public function handle(): int
    {
        $file = $this->option('file') ?: database_path('data/screening_aids.json');
        $data = json_decode((string) @file_get_contents($file), true);
        if (!is_array($data)) {
            $this->error("Arquivo inválido ou não encontrado: {$file}");

            return self::FAILURE;
        }
        $common = trim((string) ($data['_common_tips'] ?? ''));

        $rows = [];
        foreach ($data as $code => $aid) {
            if (str_starts_with((string) $code, '_') || !is_array($aid)) {
                continue;
            }
            $reviews = SystematicReview::where('protocol_code', $code)->where('is_training', false)->get();
            if ($reviews->isEmpty()) {
                $rows[] = [$code, '—', '—', 'projeto não encontrado'];
                continue;
            }
            foreach ($reviews as $review) {
                $terms = $review->highlight_terms ?? [];
                $red = collect($terms['exclude'] ?? [])->merge($aid['exclude_terms'] ?? [])
                    ->map(fn ($t) => trim((string) $t))->filter()->unique(fn ($t) => mb_strtolower($t))->values()->all();
                $tips = trim(implode("\n", array_filter([trim((string) ($aid['tips'] ?? '')), $common])));
                if (!$this->option('dry-run')) {
                    $review->update([
                        'highlight_terms' => ['include' => array_values($terms['include'] ?? []), 'exclude' => $red],
                        'screening_tips' => $tips ?: null,
                    ]);
                }
                $rows[] = [$code, $review->id, count($red), 'ok'];
            }
        }
        $this->table(['Projeto', 'ID', 'Termos em vermelho', 'Situação'], $rows);
        $this->info($this->option('dry-run') ? 'Nada gravado (dry-run).' : 'Ajudas gravadas.');

        return self::SUCCESS;
    }
}
