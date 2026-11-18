<?php

namespace App\Services;

use App\Models\SystematicReview;
use App\Models\SystematicReviewDecision;
use App\Models\SystematicReviewRecord;
use App\Models\SystematicReviewScreener;
use Illuminate\Support\Facades\Cache;

/**
 * Triagem de título/resumo priorizada (protocolo v1.1, IA declarada).
 *
 * - Cada revisor tem o próprio modelo, treinado só com as decisões dele
 *   (cegamento preservado): TF-IDF de título + resumo + palavras-chave e
 *   Naive Bayes multinomial com prioris iguais (configuração Naive Bayes +
 *   TF-IDF avaliada no ASReview, van de Schoot et al. 2021).
 * - Retreino a cada 10 decisões; os mais prováveis aparecem primeiro.
 * - Regra de parada registrada (informada pelo SAFE, Boetje & van de Schoot 2024):
 *   (a) estudos de validação achados; (b) ≥ 10% triado; (c) triados ≥ 2× relevantes;
 *   (d) 100 irrelevantes seguidos. Depois, checagem aleatória do que ninguém leu.
 * Quem decide é sempre o aluno; o modelo só ordena.
 */
class ActiveLearningService
{
    public const RETRAIN_EVERY = 10;
    public const NB_ALPHA = 3.822;

    protected const STOPWORDS = ['the', 'and', 'for', 'with', 'from', 'that', 'this', 'were', 'was', 'are', 'been', 'have', 'has', 'had',
        'which', 'their', 'these', 'those', 'than', 'then', 'they', 'them', 'into', 'onto', 'over', 'under', 'between', 'among', 'during',
        'after', 'before', 'about', 'also', 'not', 'but', 'can', 'may', 'all', 'any', 'each', 'other', 'such', 'more', 'most', 'some',
        'only', 'both', 'when', 'where', 'while', 'who', 'whom', 'whose', 'what', 'how', 'its', 'our', 'out', 'one', 'two', 'three',
        'using', 'used', 'use', 'based', 'study', 'studies', 'results', 'conclusion', 'conclusions', 'methods', 'background', 'objective',
        'aim', 'aims', 'present', 'here', 'there', 'however', 'therefore', 'thus', 'within', 'without', 'per', 'via', 'our', 'his', 'her',
        'para', 'com', 'dos', 'das', 'uma', 'que', 'por', 'nos', 'nas', 'del', 'los', 'las', 'con', 'una', 'por', 'est'];

    /** @var array<int, array<int, array<string, float>>> vetores TF-IDF por revisão (memória da requisição) */
    protected array $vectors = [];

    /** @var array<string, array<int, int>> */
    protected array $rankMemo = [];

    // ------------------------------------------------------------------
    // Ranking
    // ------------------------------------------------------------------

    /** @return array<int, string> */
    public static function tokenize(?string $text): array
    {
        $text = mb_strtolower((string) $text);
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);
        $tokens = preg_split('/\s+/', trim($text)) ?: [];

        return array_values(array_filter($tokens, fn ($t) => mb_strlen($t) >= 3 && !ctype_digit($t) && !in_array($t, self::STOPWORDS, true)));
    }

    /** @return array<int, array<string, float>> id do registro => termo => peso TF-IDF */
    public function vectors(SystematicReview $review): array
    {
        if (isset($this->vectors[$review->id])) {
            return $this->vectors[$review->id];
        }
        $docs = [];
        $df = [];
        $review->stageRecords(SystematicReview::STAGE_TA)->select(['id', 'title', 'abstract', 'keywords'])->orderBy('id')
            ->chunk(500, function ($chunk) use (&$docs, &$df) {
                foreach ($chunk as $r) {
                    $kw = is_array($r->keywords) ? implode(' ', $r->keywords) : (string) $r->keywords;
                    $tf = array_count_values(self::tokenize($r->title . ' ' . $r->title . ' ' . $r->abstract . ' ' . $kw));
                    $docs[$r->id] = $tf;
                    foreach (array_keys($tf) as $t) {
                        $df[$t] = ($df[$t] ?? 0) + 1;
                    }
                }
            });
        $n = max(1, count($docs));
        foreach ($docs as $id => $tf) {
            $v = [];
            foreach ($tf as $t => $c) {
                $v[$t] = (1 + log($c)) * (log(($n + 1) / ($df[$t] + 1)) + 1);
            }
            $docs[$id] = $v;
        }

        return $this->vectors[$review->id] = $docs;
    }

    /** @return array<int, string> rótulos do triador: id => include|exclude|maybe, na ordem em que decidiu */
    public function labels(SystematicReviewScreener $screener): array
    {
        return SystematicReviewDecision::where('systematic_review_screener_id', $screener->id)
            ->where('stage', SystematicReview::STAGE_TA)
            ->orderBy('created_at')->orderBy('id')
            ->pluck('decision', 'systematic_review_record_id')->all();
    }

    /** Registro do conjunto de validação usado como relevante "a priori" quando a calibração não teve nenhum relevante. */
    public function priorValidationId(SystematicReviewScreener $screener, ?array $labels = null): ?int
    {
        $labels ??= $this->labels($screener);
        if (collect($labels)->contains(fn ($d) => in_array($d, ['include', 'maybe'], true))) {
            return null;
        }

        return $this->validationRecords($screener->systematicReview)->keys()->first();
    }

    /**
     * Registros ainda não decididos pelo triador, do mais ao menos provável.
     *
     * @return array<int, int>
     */
    public function ranking(SystematicReviewScreener $screener): array
    {
        $review = $screener->systematicReview;
        $labels = $this->labels($screener);
        $bucket = intdiv(count($labels), self::RETRAIN_EVERY);
        $memoKey = "{$screener->id}:{$bucket}";
        if (!isset($this->rankMemo[$memoKey])) {
            $cacheKey = "sr-al:{$review->id}:{$screener->id}:{$bucket}:" . $review->stageRecords(SystematicReview::STAGE_TA)->count();
            $this->rankMemo[$memoKey] = Cache::remember($cacheKey, now()->addDays(7), fn () => $this->computeRanking($screener, $labels));
        }
        $decided = array_flip(array_keys($labels));

        return array_values(array_filter($this->rankMemo[$memoKey], fn ($id) => !isset($decided[$id])));
    }

    /** @return array<int, int> */
    protected function computeRanking(SystematicReviewScreener $screener, array $labels): array
    {
        $review = $screener->systematicReview;
        $vectors = $this->vectors($review);
        $train = [];
        foreach ($labels as $id => $d) {
            if (isset($vectors[$id])) {
                $train[$id] = in_array($d, ['include', 'maybe'], true) ? 1 : 0;
            }
        }
        if (($prior = $this->priorValidationId($screener, $labels)) && isset($vectors[$prior])) {
            $train[$prior] = 1;
        }

        $hmac = fn ($id) => hash_hmac('sha256', "{$screener->id}:{$id}", (string) config('app.key'));
        $unlabeled = array_values(array_diff(array_keys($vectors), array_keys($labels)));

        // Sem exemplo das duas classes não há o que aprender: ordem aleatória própria.
        if (!in_array(1, $train, true) || !in_array(0, $train, true)) {
            usort($unlabeled, fn ($a, $b) => [$hmac($a), $a] <=> [$hmac($b), $b]);

            return $unlabeled;
        }

        $weights = [1 => [], 0 => []];
        $totals = [1 => 0.0, 0 => 0.0];
        foreach ($train as $id => $class) {
            foreach ($vectors[$id] as $t => $w) {
                $weights[$class][$t] = ($weights[$class][$t] ?? 0) + $w;
                $totals[$class] += $w;
            }
        }
        $vocab = count(array_unique(array_merge(array_keys($weights[1]), array_keys($weights[0])))) ?: 1;
        $den1 = $totals[1] + self::NB_ALPHA * $vocab;
        $den0 = $totals[0] + self::NB_ALPHA * $vocab;

        $scores = [];
        foreach ($unlabeled as $id) {
            $s = 0.0;
            foreach ($vectors[$id] as $t => $w) {
                $s += $w * (log((($weights[1][$t] ?? 0) + self::NB_ALPHA) / $den1) - log((($weights[0][$t] ?? 0) + self::NB_ALPHA) / $den0));
            }
            $scores[$id] = $s;
        }
        usort($unlabeled, fn ($a, $b) => [$scores[$b], $hmac($a)] <=> [$scores[$a], $hmac($b)]);

        return $unlabeled;
    }

    // ------------------------------------------------------------------
    // Regra de parada
    // ------------------------------------------------------------------

    /** @return \Illuminate\Support\Collection<int, string> id do registro => citação do estudo de validação */
    public function validationRecords(SystematicReview $review)
    {
        $set = collect($review->validationSet());
        $pmids = $set->pluck('pmid')->filter()->map(fn ($p) => (string) $p)->all();
        $dois = $set->pluck('doi')->filter()->map(fn ($d) => SystematicReviewRecord::normalizeDoi($d))->filter()->all();
        if (!$pmids && !$dois) {
            return collect();
        }

        return $review->stageRecords(SystematicReview::STAGE_TA)
            ->where(fn ($q) => $q->whereIn('pmid', $pmids ?: ['-'])->orWhereIn('doi', $dois ?: ['-']))
            ->orderBy('id')->get(['id', 'title', 'pmid'])
            ->mapWithKeys(fn ($r) => [$r->id => \Illuminate\Support\Str::limit((string) $r->title, 90)]);
    }

    /**
     * Situação do triador frente à regra de parada registrada.
     *
     * @return array{total: int, screened: int, relevant: int, streak: int, min_screened: int, criteria: array, met: bool, missing_validation: array, absent_validation: int}
     */
    public function stoppingStatus(SystematicReviewScreener $screener): array
    {
        $review = $screener->systematicReview;
        $rule = $review->stoppingRule();
        // O que o Revisor 2 triou na checagem aleatória não conta para a regra de parada dele.
        $qcIds = array_flip(collect($review->ta_qc['rounds'] ?? [])->where('screener_id', $screener->id)->pluck('record_ids')->flatten()->all());
        $labels = array_filter($this->labels($screener), fn ($id) => !isset($qcIds[$id]), ARRAY_FILTER_USE_KEY);
        $total = $review->stageRecords(SystematicReview::STAGE_TA)->count();
        $screened = count($labels);
        $relevant = count(array_filter($labels, fn ($d) => in_array($d, ['include', 'maybe'], true)));

        $streak = 0;
        foreach (array_reverse($labels, true) as $d) {
            if ($d !== 'exclude') {
                break;
            }
            $streak++;
        }

        $validation = $this->validationRecords($review);
        $prior = $this->priorValidationId($screener);
        $missing = [];
        foreach ($validation as $id => $title) {
            if ($id === $prior) {
                continue;
            }
            if (!in_array($labels[$id] ?? null, ['include', 'maybe'], true)) {
                $missing[$id] = $title;
            }
        }
        $absent = max(0, count($review->validationSet()) - $validation->count());

        $minScreened = (int) ceil($rule['min_fraction'] * $total);
        $criteria = [
            'a' => ['ok' => !$rule['validation'] || !$missing, 'text' => 'Todos os estudos de validação do protocolo apareceram e foram marcados como relevantes'
                . ($validation->isEmpty() ? ' (nenhum deles está entre os registros desta busca)' : '')],
            'b' => ['ok' => $screened >= $minScreened, 'text' => "Pelo menos {$minScreened} registros triados (" . (int) round($rule['min_fraction'] * 100) . "% de {$total})"],
            'c' => ['ok' => $screened >= $rule['ratio'] * $relevant, 'text' => 'Triados pelo menos ' . (int) $rule['ratio'] . "× o número de relevantes ({$relevant} relevantes)"],
            'd' => ['ok' => $streak >= $rule['consecutive'], 'text' => "{$rule['consecutive']} irrelevantes seguidos (agora: {$streak})"],
        ];

        return [
            'total' => $total,
            'screened' => $screened,
            'relevant' => $relevant,
            'streak' => $streak,
            'min_screened' => $minScreened,
            'criteria' => $criteria,
            'met' => collect($criteria)->every(fn ($c) => $c['ok']) || $screened >= $total,
            'missing_validation' => $missing,
            'absent_validation' => $absent,
        ];
    }

    // ------------------------------------------------------------------
    // Checagem aleatória do que ninguém leu
    // ------------------------------------------------------------------

    /** @return array<int, int> registros da etapa sem nenhuma decisão dos triadores */
    public function unscreenedIds(SystematicReview $review, array $screenerIds): array
    {
        $seen = SystematicReviewDecision::whereIn('systematic_review_screener_id', $screenerIds)
            ->where('stage', SystematicReview::STAGE_TA)->pluck('systematic_review_record_id')->unique()->all();

        return $review->stageRecords(SystematicReview::STAGE_TA)->whereNotIn('id', $seen ?: [0])->orderBy('id')->pluck('id')->all();
    }

    public function currentRound(SystematicReview $review): ?array
    {
        $rounds = $review->ta_qc['rounds'] ?? [];

        return $rounds ? end($rounds) : null;
    }

    /** O triador é quem faz a checagem aberta agora. */
    public function isQcScreener(SystematicReviewScreener $screener): bool
    {
        $review = $screener->systematicReview;

        return $review->qcOpen() && (int) ($this->currentRound($review)['screener_id'] ?? 0) === $screener->id;
    }

    /** Abre uma rodada de checagem para o Revisor 2. Devolve false se não há o que checar. */
    public function startQc(SystematicReview $review, array $screenerIds): bool
    {
        $unscreened = $this->unscreenedIds($review, $screenerIds);
        if (!$unscreened) {
            return false;
        }
        $rule = $review->stoppingRule();
        $size = min(count($unscreened), max($rule['qc_min'], (int) ceil($rule['qc_fraction'] * count($unscreened))));
        $sample = $review->records()->whereIn('id', $unscreened)->reorder('random_key')->orderBy('id')->limit($size)->pluck('id')->all();
        $qcScreener = $review->secondScreener();

        $qc = $review->ta_qc ?? [];
        $qc['status'] = 'open';
        $qc['rounds'][] = [
            'round' => count($qc['rounds'] ?? []) + 1,
            'screener_id' => $qcScreener?->id,
            'record_ids' => $sample,
            'unscreened' => count($unscreened),
            'started_at' => now()->format('Y-m-d H:i:s'),
        ];
        $review->forceFill(['ta_qc' => $qc])->save();

        if ($qcScreener) {
            $finished = $qcScreener->finished_at ?? [];
            unset($finished[SystematicReview::STAGE_TA]);
            $qcScreener->update(['finished_at' => $finished]);
        }
        $review->logEvent('Checagem aleatória aberta (rodada ' . count($qc['rounds']) . ')',
            count($sample) . ' de ' . count($unscreened) . ' registros que nenhum dos dois leu · ' . ($qcScreener?->name ?? 'sem Revisor 2'));

        return true;
    }

    /**
     * Fecha a rodada aberta. Achou algo possivelmente elegível → os dois voltam a
     * triar até a regra de parada valer de novo; senão a checagem passa.
     *
     * @return int quantos possivelmente elegíveis a checagem achou
     */
    public function finishQc(SystematicReview $review): int
    {
        $qc = $review->ta_qc ?? [];
        $i = count($qc['rounds'] ?? []) - 1;
        if ($i < 0) {
            return 0;
        }
        $round = $qc['rounds'][$i];
        $hits = SystematicReviewDecision::where('systematic_review_screener_id', $round['screener_id'])
            ->where('stage', SystematicReview::STAGE_TA)
            ->whereIn('systematic_review_record_id', $round['record_ids'])
            ->whereIn('decision', ['include', 'maybe'])->count();
        $qc['rounds'][$i]['finished_at'] = now()->format('Y-m-d H:i:s');
        $qc['rounds'][$i]['hits'] = $hits;
        $qc['status'] = $hits > 0 ? 'resume' : 'passed';
        $review->forceFill(['ta_qc' => $qc])->save();
        $review->logEvent('Checagem aleatória concluída (rodada ' . ($i + 1) . ')',
            $hits > 0 ? "{$hits} possivelmente elegível(is): os dois revisores voltam a triar" : 'nenhum elegível na amostra: regra de parada confirmada');

        return $hits;
    }
}
