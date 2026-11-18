<?php

namespace App\Services;

use App\Console\Commands\SetupScreeningTraining;
use App\Filament\Pages\CentralMetanalises;
use App\Models\SystematicReview;
use App\Models\SystematicReviewDecision;
use App\Models\SystematicReviewScreener;
use App\Support\TrainingKey;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Treino da triagem: cria, libera com prazo, fecha (pela dupla ou pelo prazo)
 * e monta os resultados com o gabarito, para o professor e para os alunos.
 * Fica separado das revisões reais (is_training): nada daqui entra na Central.
 */
class ScreeningTrainingService
{
    public function __construct(protected SystematicReviewService $reviews) {}

    public static function query()
    {
        return SystematicReview::where('is_training', true);
    }

    /** Um treino por dupla do programa (os já existentes são pulados). Ninguém é avisado. */
    public function create(?callable $log = null): int
    {
        $log ??= fn () => null;
        $medline = null;
        $created = 0;
        foreach (CentralMetanalises::programQuery()->with('activeScreeners')->get() as $review) {
            $code = CentralMetanalises::codeOf($review);
            $screeners = $review->activeScreeners->filter(fn ($s) => $s->user_id);
            if ($screeners->isEmpty()) {
                $log("{$code}: sem alunos com login, pulado.");

                continue;
            }
            if (static::query()->where('short_name', "TREINO {$code}")->exists()) {
                $log("{$code}: treino já existe.");

                continue;
            }

            $medline ??= $this->fetchRecords();
            $training = SystematicReview::create(SetupScreeningTraining::attributes($code, $review));
            $this->reviews->import($training, $medline, 'treino_selante_de_teto.nbib', 'PubMed/MEDLINE');
            foreach ($screeners as $s) {
                $this->reviews->addScreener($training, ['user_id' => $s->user_id, 'label' => $s->label, 'stages' => $s->stages, 'skip_invite' => true]);
            }
            $created++;
            $log("{$code}: treino criado com " . $training->records()->count() . ' trabalhos e ' . $screeners->count() . ' alunos.');
        }

        return $created;
    }

    /** Libera os treinos em preparação (e-mail/Telegram aos alunos) e grava o prazo de fechamento. */
    public function release(Carbon $until): int
    {
        $released = 0;
        foreach (static::query()->where('status', SystematicReview::STATUS_PROTOCOL)->get() as $training) {
            $training->update(['training_closes_at' => $until]);
            $this->reviews->start($training);
            $released++;
        }

        return $released;
    }

    /** Fecha um treino aberto: calcula o kappa, avisa o professor e libera os resultados aos alunos. */
    public function close(SystematicReview $training, string $why): void
    {
        if (!$training->is_training || $training->status !== SystematicReview::STATUS_SCREENING) {
            return;
        }

        $rel = $this->reviews->reliability($training, SystematicReview::STAGE_TA);
        $training->update(['status' => SystematicReview::STATUS_SCREENING_DONE]);
        $training->logEvent('Treino encerrado (' . $why . ')', $rel['summary']);

        $screeners = $training->activeScreeners()->get();
        $progress = $screeners->map(fn ($s) => $s->name . ': ' . $this->decisions($s)->count() . ' de ' . $training->records()->count())->implode("\n");
        $this->reviews->notifyProfessor(
            'Treino da triagem concluído',
            "O treino \"{$training->short_name}\" foi encerrado ({$why}).\n\n{$progress}\n\n{$rel['summary']}\n\n"
                . 'Os resultados, com o gabarito, estão no painel em Programa de Metanálises › Treino da triagem. Os alunos já podem vê-los no portal.',
            $training,
        );
        foreach ($screeners as $screener) {
            if ($screener->user_id) {
                $this->reviews->notifyScreener($screener, 'Treino concluído: veja os resultados',
                    "Olá, {$screener->name}!\n\nO treino da triagem foi encerrado ({$why}).\n\n"
                    . 'Os resultados já estão no portal, em "Treino da triagem": as suas decisões, as da sua dupla e o gabarito comentado de cada trabalho. '
                    . "Veja onde vocês discordaram e por quê. O professor vai discutir os casos com vocês.\n\n"
                    . 'Nada do treino conta para o seu projeto. Quando a triagem do seu projeto for liberada, você recebe outro e-mail.',
                    'project');
            }
        }
    }

    /** Fecha os treinos com o prazo vencido (agendado e também ao abrir as páginas do treino). */
    public function closeExpired(): int
    {
        $n = 0;
        foreach (static::query()->where('status', SystematicReview::STATUS_SCREENING)->whereNotNull('training_closes_at')
            ->where('training_closes_at', '<=', now())->get() as $training) {
            $this->close($training, 'prazo encerrado');
            $n++;
        }

        return $n;
    }

    /** Fecha agora todos os treinos abertos, mesmo antes do prazo. */
    public function closeAll(): int
    {
        $open = static::query()->where('status', SystematicReview::STATUS_SCREENING)->get();
        $open->each(fn ($t) => $this->close($t, 'encerrado pelo professor'));

        return $open->count();
    }

    public function archive(): int
    {
        return static::query()->where('status', '!=', SystematicReview::STATUS_ARCHIVED)
            ->update(['status' => SystematicReview::STATUS_ARCHIVED]);
    }

    protected function decisions(SystematicReviewScreener $screener): Collection
    {
        return SystematicReviewDecision::where('systematic_review_screener_id', $screener->id)
            ->where('stage', SystematicReview::STAGE_TA)->get()->keyBy('systematic_review_record_id');
    }

    /**
     * Resultado de um treino: cada trabalho com o voto de cada aluno e o gabarito,
     * os acertos de cada um e o kappa da dupla. Divergências primeiro (é o que se discute).
     *
     * @return array{screeners: Collection, rows: Collection, score: array, pair: ?array}
     */
    public function results(SystematicReview $training): array
    {
        $screeners = $training->activeScreeners()->orderBy('id')->get();
        $votes = $screeners->mapWithKeys(fn ($s) => [$s->id => $this->decisions($s)]);

        $rows = $training->records()->get()->map(function ($record) use ($screeners, $votes) {
            $key = TrainingKey::for($record->pmid);
            $cast = $screeners->mapWithKeys(fn ($s) => [$s->id => $votes[$s->id]->get($record->id)]);
            $given = $cast->filter()->map(fn ($d) => $d->decision);
            $right = $cast->map(fn ($d) => $d && $key ? TrainingKey::isRight($key, $d->decision) : null);

            return [
                'record' => $record,
                'key' => $key,
                'votes' => $cast,
                'right' => $right,
                'split' => $given->count() >= 2 && $given->unique()->count() > 1,
                'missed' => $right->contains(false),
            ];
        })->sortBy(fn ($r) => [$r['split'] ? 0 : ($r['missed'] ? 1 : 2), ['include' => 0, 'maybe' => 1, 'exclude' => 2][$r['key']['decision'] ?? 'maybe']])->values();

        $score = $screeners->mapWithKeys(function ($s) use ($rows, $training) {
            $marks = $rows->map(fn ($r) => $r['right'][$s->id]);

            return [$s->id => [
                'done' => $rows->filter(fn ($r) => $r['votes'][$s->id])->count(),
                'total' => $training->records()->count(),
                'right' => $marks->filter(fn ($m) => $m === true)->count(),
                'scored' => $marks->filter(fn ($m) => $m !== null)->count(),
            ]];
        })->all();

        $pair = $this->reviews->reliability($training, SystematicReview::STAGE_TA)['pairs'][0] ?? null;

        return ['screeners' => $screeners, 'rows' => $rows, 'score' => $score, 'pair' => $pair];
    }

    /** Para a aula: em cada trabalho, quantos alunos de todas as duplas acertaram (só treinos encerrados). */
    public function overview(): Collection
    {
        $done = static::query()->whereIn('status', [SystematicReview::STATUS_SCREENING_DONE, SystematicReview::STATUS_SCREENING])->pluck('id');
        $decisions = SystematicReviewDecision::query()
            ->join('systematic_review_records as r', 'r.id', '=', 'systematic_review_decisions.systematic_review_record_id')
            ->whereIn('r.systematic_review_id', $done)
            ->where('systematic_review_decisions.stage', SystematicReview::STAGE_TA)
            ->get(['r.pmid', 'r.title', 'systematic_review_decisions.decision', 'systematic_review_decisions.reason']);

        return $decisions->groupBy('pmid')->map(function ($group, $pmid) {
            $key = TrainingKey::for($pmid);
            $marks = $group->map(fn ($d) => $key ? TrainingKey::isRight($key, $d->decision) : null)->filter(fn ($m) => $m !== null);

            return [
                'pmid' => $pmid,
                'title' => $group->first()->title,
                'key' => $key,
                'n' => $group->count(),
                'counts' => collect(['include', 'maybe', 'exclude'])->mapWithKeys(fn ($d) => [$d => $group->where('decision', $d)->count()])->all(),
                'rate' => $marks->isEmpty() ? null : $marks->filter()->count() / $marks->count(),
            ];
        })->sortBy(fn ($r) => $r['rate'] ?? 2)->values();
    }

    protected function fetchRecords(): string
    {
        $response = Http::timeout(90)->retry(3, 2000)->get('https://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi', [
            'db' => 'pubmed', 'id' => implode(',', SetupScreeningTraining::PMIDS), 'rettype' => 'medline', 'retmode' => 'text',
        ]);
        $text = $response->throw()->body();
        if (substr_count($text, 'PMID-') !== count(SetupScreeningTraining::PMIDS)) {
            throw new \RuntimeException('O PubMed não devolveu os 25 trabalhos do treino. Tente de novo em alguns minutos.');
        }

        return $text;
    }
}
