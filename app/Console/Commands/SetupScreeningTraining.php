<?php

namespace App\Console\Commands;

use App\Models\SystematicReview;
use App\Services\ScreeningTrainingService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Treino da triagem: uma revisão TREINO por dupla, com os mesmos 25 trabalhos
 * de um tema fora dos projetos (selante de teto e mastite bovina). Os alunos
 * conhecem a plataforma sem ver nada do próprio projeto antes da busca oficial.
 */
class SetupScreeningTraining extends Command
{
    protected $signature = 'app:treino-triagem
                            {--liberar : Libera os treinos já criados (avisa os alunos por e-mail/Telegram)}
                            {--ate= : Com --liberar: quando o treino fecha (padrão: amanhã às 23h59)}
                            {--encerrar : Fecha agora os treinos abertos e mostra os resultados aos alunos}
                            {--vencidos : Fecha só os treinos com o prazo vencido (agendado)}
                            {--situacao : Mostra a situação de cada treino}
                            {--arquivar : Arquiva os treinos (somem do portal; as decisões ficam guardadas)}';

    protected $description = 'Cria o treino da triagem (25 trabalhos fora dos projetos) para cada dupla do programa';

    /** Escolhidos à mão na busca da aula (PubMed, 28/10/2026): incluídos, dúvidas e armadilhas de exclusão. */
    public const PMIDS = [
        '12416803', '17235153', '20598329', '24351664', '35078119', '42353384', '35379464', '7440342', '6378997',
        '24580114', '34316189', '35633519', '40105284', '40185671', '35931474', '19528591', '32081124', '30070621',
        '32475664', '34593233', '17183089', '32278556', '36288172', '32984415', '38908700',
    ];

    public const TITLE = 'TREINO · Selante de teto e mastite em vacas (simulação)';

    public function handle(ScreeningTrainingService $service): int
    {
        if ($this->option('situacao')) {
            $rows = ScreeningTrainingService::query()->withCount(['records', 'screeners'])->orderBy('short_name')->get()
                ->map(fn (SystematicReview $t) => [$t->short_name, $t->statusLabel(), $t->records_count, $t->screeners_count, $t->training_closes_at?->format('d/m/Y H:i') ?? '—']);
            $rows->isEmpty()
                ? $this->warn('Nenhum treino criado ainda. Rode: php artisan app:treino-triagem')
                : $this->table(['Treino', 'Situação', 'Trabalhos', 'Alunos', 'Fecha em'], $rows->all());

            return self::SUCCESS;
        }

        if ($this->option('arquivar')) {
            $this->info($service->archive() . ' treinos arquivados.');

            return self::SUCCESS;
        }

        if ($this->option('encerrar')) {
            $this->info($service->closeAll() . ' treinos encerrados; os alunos já veem os resultados.');

            return self::SUCCESS;
        }

        if ($this->option('vencidos')) {
            $n = $service->closeExpired();
            if ($n) {
                $this->info("{$n} treinos encerrados pelo prazo.");
            }

            return self::SUCCESS;
        }

        if ($this->option('liberar')) {
            $until = $this->option('ate') ? Carbon::parse($this->option('ate')) : now()->addDay()->setTime(23, 59);
            $released = $service->release($until);
            $this->info("{$released} treinos liberados até " . $until->format('d/m/Y H:i') . '; os alunos foram avisados.');

            return self::SUCCESS;
        }

        $created = $service->create(fn (string $line) => $this->line($line));
        $this->info("{$created} treinos criados. Ninguém foi avisado ainda: confira no painel e rode com --liberar.");

        return self::SUCCESS;
    }

    public static function attributes(string $code, SystematicReview $original): array
    {
        return [
            'title' => self::TITLE,
            'short_name' => "TREINO {$code}",
            'is_training' => true,
            'pilot_size' => count(self::PMIDS),
            'registration_waiver' => 'Treino da plataforma com dados fora dos projetos: não é pesquisa e não tem registro.',
            'notes' => "Treino da dupla de {$code} (revisão #{$original->id}). Não conta para o projeto.",
            'protocol' => [
                'question' => 'Em vacas leiteiras na secagem, o selante interno de teto (sozinho ou com antibiótico), comparado a não usar selante, reduz novas infecções intramamárias ou mastite clínica?',
                'population' => 'Vacas leiteiras tratadas na secagem (fim da lactação).',
                'intervention' => 'Selante interno de teto (subnitrato de bismuto), sozinho ou junto com antibiótico intramamário.',
                'comparator' => 'Grupo sem selante: sem tratamento ou só com antibiótico.',
                'outcomes' => 'Nova infecção intramamária ou mastite clínica no período seco ou no início da lactação.',
                'study_designs' => 'Estudos primários com grupo controle (ensaios randomizados ou não).',
                'inclusion_criteria' => "1. Vacas leiteiras tratadas na secagem.\n2. Selante interno de teto, com ou sem antibiótico.\n3. Grupo de comparação sem selante.\n4. Desfecho: nova infecção intramamária ou mastite clínica.\n5. Estudo primário com grupo controle.",
                'exclusion_criteria' => "E1. Não é estudo primário (revisão, metanálise, protocolo, guia, modelo de simulação).\nE2. População errada (novilhas antes do primeiro parto, vacas em lactação, outra espécie).\nE3. Intervenção errada (sem selante interno; selante externo aplicado como película no teto).\nE4. Sem grupo de comparação sem selante (por exemplo, dois selantes comparados entre si).\nE5. Desfecho errado (sem nova infecção intramamária nem mastite clínica).\nE6. Estudo in vitro ou de laboratório.",
            ],
            'exclusion_reasons' => [
                'E1 — Não é estudo primário',
                'E2 — População errada',
                'E3 — Intervenção errada',
                'E4 — Sem grupo de comparação sem selante',
                'E5 — Desfecho errado',
                'E6 — Estudo in vitro',
            ],
            'highlight_terms' => [
                'include' => ['teat seal', 'sealant', 'dry-off', 'drying off', 'dry period', 'intramammary infection', 'clinical mastitis'],
                'exclude' => ['heifer', 'in vitro', 'review', 'meta-analysis', 'microbiome', 'model'],
            ],
            'screener_instructions' => 'TREINO: tema fora dos nossos projetos, só para conhecer a plataforma. Decida sozinho(a) cada trabalho pelo título e resumo, com os critérios acima. Na dúvida, marque Talvez e escreva o porquê na nota. Quando o treino fechar, você vê aqui as suas decisões, as da dupla e o gabarito comentado.',
        ];
    }
}
