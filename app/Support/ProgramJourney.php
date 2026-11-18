<?php

namespace App\Support;

use App\Models\SystematicReview;
use App\Models\SystematicReviewScreener;
use Illuminate\Support\Carbon;

/**
 * Onde cada projeto do Programa de Metanálises está na esteira, deduzido só do
 * estado real (termos, congelamento, DOI, registros importados, piloto, etapas
 * encerradas, esteira do POP). Alimenta a barra colorida do aluno (Painel e
 * Triagem Cega) e a coluna Etapa da Central, com o prazo da busca.
 *
 * Calendário: onda única 2026/27 (PIPELINE_DO_PROFESSOR.md › "Atenção ao calendário").
 */
class ProgramJourney
{
    /** O protocolo registra "Start date: November 2026": nenhuma busca formal antes disto. */
    public const SEARCH_NOT_BEFORE = '2026-11-01';

    /** O protocolo manda buscar em até 14 dias depois do registro no OSF. */
    public const SEARCH_DAYS_AFTER_REGISTRATION = 14;

    public const STEPS = [
        'preparacao' => ['label' => 'Preparação', 'window' => 'até 29/10',
            'now' => 'Termos, ORCID e OSF da dupla e congelamento do protocolo.'],
        'registro' => ['label' => 'Registro OSF', 'window' => '29/10 a 06/11',
            'now' => 'O professor registra o protocolo no OSF, com os nomes da dupla.'],
        'busca' => ['label' => 'Busca', 'window' => '03 a 20/11',
            'now' => 'O professor faz a busca nas bases, em novembro e em até 14 dias depois do registro.'],
        'calibracao' => ['label' => 'Calibração', 'window' => 'logo após a busca',
            'now' => 'Calibração: os dois triam os mesmos trabalhos do piloto e o professor confere a concordância (kappa).'],
        'triagem' => ['label' => 'Triagem', 'window' => 'até 22/12',
            'now' => 'Triagem de título e resumo, priorizada, até cumprir a regra de parada.'],
        'texto' => ['label' => 'Texto completo', 'window' => '04 a 31/01/2027',
            'now' => 'Leitura do texto completo dos trabalhos selecionados.'],
        'extracao' => ['label' => 'Extração', 'window' => '01/02 a 21/03/2027',
            'now' => 'Extração dos dados e risco de viés, em dupla.'],
        'analise' => ['label' => 'Análise', 'window' => '22/03 a 11/04/2027',
            'now' => 'Análise estatística pelo professor; o revisor reexecuta o script.'],
        'artigo' => ['label' => 'Artigo', 'window' => 'até 30/06/2027',
            'now' => 'Redação do artigo em inglês e submissão.'],
    ];

    /** Levas 2 e 3 (sem prazo fixo no protocolo): janelas relativas, combinadas com cada dupla. */
    public const FLEX_WINDOWS = [
        'registro' => 'antes da busca',
        'busca' => 'antes da triagem',
        'calibracao' => 'logo após a busca',
    ];

    public const FLEX_SEARCH_NOW = 'O professor faz a busca nas bases depois do registro e antes de abrir a triagem (o protocolo não fixa prazo).';

    /**
     * @return array{steps: array<string, array{label: string, window: string, state: string}>, current: ?string,
     *               position: int, total: int, now: string, search: ?array}
     */
    public static function for(SystematicReview $review): array
    {
        $done = static::doneFlags($review);
        // Datas do calendário só para quem registrou o prazo fixo (leva 1); levas 2 e 3 seguem o ritmo de cada dupla.
        $dated = static::hasRegisteredSearchDeadline($review);
        $current = null;
        $steps = [];
        foreach (static::STEPS as $key => $step) {
            // Sequencial: a primeira etapa não cumprida é a atual; as seguintes ainda não começaram.
            $state = $current === null && $done[$key] ? 'done' : ($current === null ? 'current' : 'todo');
            if ($state === 'current') {
                $current = $key;
            }
            $steps[$key] = ['label' => $step['label'], 'window' => $dated ? $step['window'] : (self::FLEX_WINDOWS[$key] ?? ''), 'state' => $state];
        }
        $position = $current ? array_search($current, array_keys(static::STEPS), true) + 1 : count(static::STEPS);

        return [
            'steps' => $steps,
            'current' => $current,
            'position' => $position,
            'total' => count(static::STEPS),
            'now' => $current ? (!$dated && $current === 'busca' ? self::FLEX_SEARCH_NOW : static::STEPS[$current]['now']) : 'Artigo submetido. Parabéns à dupla!',
            'search' => static::searchWindow($review),
        ];
    }

    /** @return array<string, bool> */
    protected static function doneFlags(SystematicReview $review): array
    {
        $screeners = $review->relationLoaded('screeners')
            ? $review->screeners->whereNull('removed_at')
            : $review->activeScreeners()->get();
        $records = $review->records_count ?? $review->records()->count();
        $screeningStarted = in_array($review->status, [SystematicReview::STATUS_SCREENING, SystematicReview::STATUS_CONFLICTS, SystematicReview::STATUS_SCREENING_DONE], true)
            || $review->stage === SystematicReview::STAGE_FT;

        $flags = [
            'preparacao' => $review->isFrozen() && $screeners->count() >= 2 && $screeners->every(fn ($s) => $s->hasSignedTerms()),
            'registro' => filled($review->registration_id),
            'busca' => $records > 0,
            'calibracao' => $screeningStarted && !$review->inPilot(SystematicReview::STAGE_TA),
            'triagem' => $review->isStageClosed(SystematicReview::STAGE_TA),
            'texto' => $review->isStageClosed(SystematicReview::STAGE_FT),
            'extracao' => $review->checklistDone('extraction'),
            'analise' => $review->checklistDone('analysis'),
            'artigo' => $review->checklistDone('submitted'),
        ];

        // Se uma etapa posterior já foi cumprida, as anteriores contam como feitas
        // (ex.: revisão fora do programa, triada sem congelar o protocolo). A busca
        // importada não conta: importar antes do registro não pode esconder a falta do DOI
        // (a triagem, sim, já é bloqueada sem DOI em startProblems).
        $later = false;
        foreach (array_reverse(array_keys($flags)) as $key) {
            $flags[$key] = $flags[$key] || $later;
            if ($key !== 'busca') {
                $later = $flags[$key];
            }
        }

        return $flags;
    }

    /**
     * Janela da busca formal: de max(registro, 01/11) até registro + 14 dias.
     * level: wait (ainda não pode), ok, warn (≤ 5 dias), urgent (≤ 3 dias), late (vencida), done (importada).
     *
     * Sem prazo (levas 2 e 3, ver hasRegisteredSearchDeadline): until e days_left nulos, level ok.
     *
     * @return array{from: Carbon, until: ?Carbon, days_left: ?int, level: string, text: string}|null
     */
    public static function searchWindow(SystematicReview $review, ?Carbon $today = null): ?array
    {
        if (!$review->registered_at || blank($review->registration_id)) {
            return null;
        }
        $today = ($today ?? now())->copy()->startOfDay();
        $registered = $review->registered_at->copy()->startOfDay();
        $from = $registered->copy()->max(Carbon::parse(self::SEARCH_NOT_BEFORE));
        $until = $registered->copy()->addDays(self::SEARCH_DAYS_AFTER_REGISTRATION);
        $daysLeft = (int) $today->diffInDays($until, false);
        $records = $review->records_count ?? $review->records()->count();

        if ($records > 0) {
            $when = $review->search_date ? ' em ' . $review->search_date->format('d/m') : '';

            return ['from' => $from, 'until' => $until, 'days_left' => $daysLeft, 'level' => 'done', 'text' => "Busca feita{$when}"];
        }
        if (!static::hasRegisteredSearchDeadline($review)) {
            // Levas 2 e 3: o protocolo registrado diz "depois do registro e antes de começar a triagem"
            // (duplas formadas aos poucos). Não há prazo, e não cabe alerta nem desvio.
            return ['from' => $registered, 'until' => null, 'days_left' => null, 'level' => 'ok',
                'text' => 'Busca antes de abrir a triagem (o protocolo não fixa prazo)'];
        }
        [$level, $text] = match (true) {
            // Registro antes de 18/10: os 14 dias acabam antes de novembro, e a janela fica vazia.
            $until->lt($from) => ['late', 'Janela impossível: registro em ' . $registered->format('d/m') . ' exige busca até ' . $until->format('d/m')
                . ', mas o protocolo diz novembro (registre um desvio)'],
            $daysLeft < 0 => ['late', 'Busca VENCIDA em ' . $until->format('d/m') . ' (registre um desvio do protocolo)'],
            $today->lt($from) => ['wait', 'Busca de ' . $from->format('d/m') . ' a ' . $until->format('d/m') . ' (ainda não pode começar)'],
            $daysLeft <= 3 => ['urgent', 'Busca até ' . $until->format('d/m') . ' · ' . static::daysText($daysLeft)],
            $daysLeft <= 5 => ['warn', 'Busca até ' . $until->format('d/m') . ' · ' . static::daysText($daysLeft)],
            default => ['ok', 'Busca até ' . $until->format('d/m') . ' · ' . static::daysText($daysLeft)],
        };

        return ['from' => $from, 'until' => $until, 'days_left' => $daysLeft, 'level' => $level, 'text' => $text];
    }

    /**
     * O protocolo registrado fixou "buscas em até duas semanas depois do registro" (leva 1)?
     * Sem pipeline (projeto antigo, cadastrado à mão), vale a regra dos 14 dias.
     */
    public static function hasRegisteredSearchDeadline(SystematicReview $review): bool
    {
        $start = (string) data_get($review->pipeline, 'osf_fields.376-14', '');

        return $start === '' || str_contains(mb_strtolower($start), 'within two weeks after registration');
    }

    protected static function daysText(int $days): string
    {
        return match ($days) {
            0 => 'é hoje',
            1 => 'falta 1 dia',
            default => "faltam {$days} dias",
        };
    }

    /** O que o aluno faz agora neste projeto, pelo papel e pelo que ele já fez. */
    public static function youText(SystematicReview $review, SystematicReviewScreener $screener, ?string $current): string
    {
        $lead = str_starts_with((string) $screener->label, 'Líder');
        if (!$screener->hasSignedTerms()) {
            return 'Leia e assine o termo de confidencialidade deste projeto.';
        }

        return match ($current) {
            'preparacao' => 'Envie o seu ORCID e o link do seu OSF pela conversa do projeto, se ainda não enviou. O resto é com o professor.',
            'registro' => 'Nada a fazer: o professor registra o protocolo com o seu nome. O OSF avisa você por e-mail.',
            'busca' => 'Nada a fazer agora: o professor faz a busca nas bases e carrega os trabalhos no site. Você recebe um e-mail quando a calibração abrir.',
            'calibracao' => static::screeningText($review, $screener, 'Trie os trabalhos do piloto (botão "Triar agora").', 'Piloto feito. Aguarde o professor conferir a concordância e liberar a triagem completa.'),
            'triagem' => static::screeningText($review, $screener, 'Trie até a regra de parada ficar toda verde' . (static::hasRegisteredSearchDeadline($review) ? ' (até 22/12).' : ', no prazo combinado com o professor.'), 'Você concluiu esta etapa. Aguarde a checagem aleatória e o fim da triagem.'),
            'texto' => static::screeningText($review, $screener, 'Leia o texto completo de cada trabalho e decida (até 31/01).', 'Você concluiu esta etapa. Aguarde o professor encerrá-la.'),
            'extracao' => 'Extraia os dados e avalie o risco de viés em "Extração e Risco de Viés" (até 21/03).',
            'analise' => $lead
                ? 'O professor roda a análise. Comece a escrever os Métodos do artigo.'
                : 'Você vai reexecutar o script da análise para confirmar os resultados.',
            'artigo' => $lead ? 'Escreva o artigo em inglês (até 30/06).' : 'Revise o texto do artigo com a sua dupla.',
            default => 'Nada pendente. Obrigado pela participação!',
        };
    }

    protected static function screeningText(SystematicReview $review, SystematicReviewScreener $screener, string $todo, string $finished): string
    {
        if (!$screener->participatesIn($review->stage)) {
            return 'Nada a fazer nesta etapa.';
        }
        if ($screener->hasFinished($review->stage)) {
            return $finished;
        }
        if ($review->inPilot()) {
            $p = app(\App\Services\SystematicReviewService::class)->progress($screener);
            if ($p['total'] > 0 && $p['done'] >= $p['total']) {
                return $finished;
            }
        }

        return $todo;
    }
}
