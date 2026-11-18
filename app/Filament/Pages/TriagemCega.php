<?php

namespace App\Filament\Pages;

use App\Models\SystematicReview;
use App\Models\SystematicReviewRecord;
use App\Models\SystematicReviewScreener;
use App\Services\SystematicReviewService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

/**
 * Modo triagem do aluno: um trabalho por vez, botões de decisão e atalhos
 * de teclado. Cega: o aluno só enxerga os próprios votos. Tudo é validado
 * de novo no servidor (SystematicReviewService::decide).
 */
class TriagemCega extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-funnel';

    protected static ?string $title = 'Triagem Cega';

    protected static ?string $slug = 'triagem-cega';

    protected static string $view = 'filament.pages.triagem-cega';

    protected static ?int $navigationSort = -6;

    /** false = só os projetos reais; a página TreinoTriagem mostra só os treinos. Os dois nunca se misturam. */
    protected static bool $training = false;

    #[Url(as: 'projeto')]
    public ?int $screenerId = null;

    #[Url(as: 'n')]
    public ?int $recordId = null;

    public bool $onlyPending = true;

    public string $signature = '';

    public bool $termsRead = false;

    public string $note = '';

    /** Trabalho a que $note pertence: ao mudar de trabalho, carrega a observação já gravada dele. */
    public ?int $noteRecordId = null;

    /** Respostas do checklist PICO do trabalho na tela (índice da pergunta => sim/nao/incerto), enviadas com a decisão. */
    public array $checklist = [];

    /** Trabalho a que $checklist pertence: num toque duplo, as respostas não vão para o trabalho seguinte. */
    public ?int $checklistRecordId = null;

    public string $chatBody = '';

    /** Escreve na conversa registrada do projeto (só a dupla e o professor). */
    public function postChat(): void
    {
        $screener = $this->screener();
        if (!$screener || $screener->systematicReview->is_training) {
            return;
        }
        try {
            $this->service()->postMessage($screener->systematicReview, auth()->user(), $this->chatBody);
        } catch (\RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }
        $this->chatBody = '';
        $screener->markChatRead();
        Notification::make()->title('Mensagem enviada e registrada')->success()->send();
    }

    /** O aluno abriu a conversa: zera o "N novas" do Painel e do menu. */
    public function markChatRead(): void
    {
        $screener = $this->screener();
        if ($screener && $screener->hasSignedTerms()) {
            $screener->markChatRead();
        }
    }

    /** Últimas mensagens da conversa do projeto, se o aluno pode ler. */
    protected function chatFor(SystematicReviewScreener $screener)
    {
        $review = $screener->systematicReview;
        if ($review->is_training) {
            return null;
        }

        return $this->service()->canChat($review, auth()->user())
            // A mais nova primeiro. reorder(): a relação já vem em ordem cronológica, e sem isso o limit pegava as 60 MAIS ANTIGAS.
            ? $review->messages()->reorder()->latest('id')->limit(60)->get()
            : null;
    }

    public static function getNavigationGroup(): ?string
    {
        return Filament::getCurrentPanel()?->getId() === 'portal' ? 'Programa de Metanálises' : null;
    }

    public static function myAssignments()
    {
        return SystematicReviewScreener::where('user_id', auth()->id())
            ->whereNull('removed_at')
            // Em preparação (protocolo) também aparece: o aluno assina o termo online antes da triagem.
            ->whereHas('systematicReview', fn ($q) => $q->where('is_training', static::$training)->whereIn('status', [
                SystematicReview::STATUS_PROTOCOL,
                SystematicReview::STATUS_SCREENING,
                SystematicReview::STATUS_CONFLICTS,
                SystematicReview::STATUS_SCREENING_DONE,
            ]));
    }

    /** A outra pessoa da dupla neste projeto (nome e e-mail para combinarem prazos). */
    public static function partnerOf(SystematicReviewScreener $screener): ?SystematicReviewScreener
    {
        return SystematicReviewScreener::where('systematic_review_id', $screener->systematic_review_id)
            ->whereNull('removed_at')->where('id', '!=', $screener->id)->orderBy('id')->first();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && static::myAssignments()->exists();
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public static function getNavigationBadge(): ?string
    {
        if (!auth()->check()) {
            return null;
        }
        // Projeto com algo a fazer: termo, triagem aberta ou mensagem nova na conversa.
        $open = static::myAssignments()->get()->filter(function (SystematicReviewScreener $s) {
            $r = $s->systematicReview;

            return !$s->hasSignedTerms()
                || ($r->status === SystematicReview::STATUS_SCREENING && $s->participatesIn($r->stage) && !$s->hasFinished($r->stage))
                || $s->unreadMessagesCount() > 0;
        })->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    protected function service(): SystematicReviewService
    {
        return app(SystematicReviewService::class);
    }

    public function mount(): void
    {
        if (static::$training) {
            // Sem depender do agendador: o treino com prazo vencido fecha quando alguém abre a página.
            app(\App\Services\ScreeningTrainingService::class)->closeExpired();
        }
        if (!$this->screenerId) {
            $only = static::myAssignments()->pluck('id');
            if ($only->count() === 1) {
                $this->screenerId = $only->first();
            }
        }
    }

    /** Recarrega sempre do banco, amarrado ao usuário logado. */
    protected function screener(): ?SystematicReviewScreener
    {
        if (!$this->screenerId) {
            return null;
        }

        return static::myAssignments()->with('systematicReview')->find($this->screenerId);
    }

    public function choose(int $id): void
    {
        $this->screenerId = $id;
        $this->recordId = null;
    }

    public function back(): void
    {
        $this->screenerId = null;
        $this->recordId = null;
    }

    public function sign(): void
    {
        $screener = $this->screener();
        if (!$screener) {
            return;
        }
        if (!$this->termsRead) {
            Notification::make()->title('Marque que leu e concorda com o termo.')->warning()->send();

            return;
        }
        try {
            $this->service()->signTerms($screener, $this->signature, request()->ip(), request()->userAgent());
        } catch (\RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }
        Notification::make()->title('Termo assinado. Bom trabalho!')->success()->send();
    }

    /**
     * $recordId = o trabalho que estava na tela quando o aluno clicou. Num toque duplo
     * (comum no celular) o segundo clique chega depois que a tela já avançou: sem o id,
     * ele decidiria o PRÓXIMO trabalho sem o aluno ter lido.
     */
    public function decide(string $decision, ?string $reason = null, ?int $recordId = null): void
    {
        $screener = $this->screener();
        $targetId = $recordId ?? $this->recordId;
        $record = $screener ? $screener->systematicReview->screenableRecords()->find($targetId) : null;
        if (!$screener || !$record) {
            return;
        }
        // A observação digitada só vale para o trabalho em que foi escrita; nos outros casos mantém a gravada.
        $note = $this->noteRecordId === $record->id ? $this->note : null;
        $checklist = $this->checklistRecordId === $record->id && $this->checklist ? $this->checklist : null;
        try {
            $this->service()->decide($screener, $record, $decision, $reason, $note, $checklist);
        } catch (\RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }
        $this->checklist = [];
        $this->checklistRecordId = null;
        if ((int) $this->recordId === $record->id) {
            $this->recordId = $this->nextPendingId($screener, $record->id) ?? $record->id;
        }
    }

    public function go(int $direction): void
    {
        $screener = $this->screener();
        if (!$screener) {
            return;
        }
        $all = $this->service()->orderFor($screener);
        // "Anterior" anda pela ordem completa: volta ao trabalho que o aluno acabou de decidir (para corrigir um
        // clique errado) mesmo com "só pendentes" marcado. "Próximo" continua pulando os já decididos.
        $order = $direction > 0 ? $this->visibleOrder($screener) : $all;
        $current = $this->currentRecord($screener);
        $pos = $current ? array_search($current->id, $all, true) : -1;
        $candidates = $direction > 0
            ? array_values(array_filter($order, fn ($id) => array_search($id, $all, true) > $pos))
            : array_reverse(array_values(array_filter($order, fn ($id) => array_search($id, $all, true) < $pos)));
        if ($id = $candidates[0] ?? null) {
            $this->recordId = $id; // A observação dele é carregada no render (getViewData).
        }
    }

    public function jump(int $id): void
    {
        $screener = $this->screener();
        if ($screener && $screener->systematicReview->screenableRecords()->whereKey($id)->exists()) {
            $this->recordId = $id;
        }
    }

    public function updatedOnlyPending(): void
    {
        $this->recordId = null;
    }

    public function finish(): void
    {
        $screener = $this->screener();
        if (!$screener) {
            return;
        }
        try {
            $this->service()->finish($screener);
        } catch (\RuntimeException $e) {
            Notification::make()->title($e->getMessage())->warning()->send();

            return;
        }
        Notification::make()->title('Triagem concluída e enviada ao professor. Obrigado!')->success()->send();
    }

    /** @return array<int, int> ids já decididos pelo triador na etapa atual */
    protected function decidedIds(SystematicReviewScreener $screener): array
    {
        return $screener->decisions()->where('stage', $screener->systematicReview->stage)->pluck('systematic_review_record_id')->all();
    }

    /** Ordem própria do triador, filtrada por "só pendentes". */
    protected function visibleOrder(SystematicReviewScreener $screener): array
    {
        $order = $this->service()->orderFor($screener);
        if ($this->onlyPending) {
            $order = array_values(array_diff($order, $this->decidedIds($screener)));
        }

        return $order;
    }

    protected function nextPendingId(SystematicReviewScreener $screener, int $afterId): ?int
    {
        $all = $this->service()->orderFor($screener);
        $pending = array_values(array_diff($all, $this->decidedIds($screener)));
        $pos = array_search($afterId, $all, true);
        foreach ($pending as $id) {
            if (array_search($id, $all, true) > $pos) {
                return $id;
            }
        }

        return $pending[0] ?? null;
    }

    protected function currentRecord(SystematicReviewScreener $screener): ?SystematicReviewRecord
    {
        $review = $screener->systematicReview;
        $record = $this->recordId ? $review->screenableRecords()->find($this->recordId) : null;

        if (!$record) {
            $id = $this->visibleOrder($screener)[0] ?? ($this->service()->orderFor($screener)[0] ?? null);
            $record = $id ? $review->records()->find($id) : null;
            $this->recordId = $record?->id;
        }

        return $record;
    }

    /** Tradução de apoio dos motivos genéricos do protocolo (o motivo gravado continua o original). */
    public static function reasonPt(string $label): ?string
    {
        $map = [
            'Laboratory colonies, experimentally induced or transplanted' => 'Colônias de laboratório, tumores induzidos/transplantados ou estudos de carcinogenicidade.',
            'Zoo-only collections of non-companion species' => 'Coleções exclusivas de zoológico ou fauna silvestre (animais que não são pets de companhia).',
            'Narrative reviews, editorials, letters and textbook chapters' => 'Revisão narrativa, editorial, carta ou capítulo de livro, sem dados originais.',
            'Conference abstracts and other reports without sufficient' => 'Resumo de congresso ou relato sem detalhes e dados suficientes.',
            'Duplicate reports of the same animals' => 'Publicação duplicada dos mesmos animais.',
            'Population not eligible' => 'Animais/população fora do critério.',
            'Intervention, exposure or index test not eligible' => 'Intervenção, exposição ou teste fora do critério.',
            'Comparator not eligible' => 'Sem grupo de comparação adequado.',
            'No eligible outcome or data not extractable' => 'Não mede o desfecho do protocolo ou não tem dados aproveitáveis.',
            'Study design not eligible' => 'Tipo de estudo fora do critério (ex.: relato de caso, número pequeno de animais).',
            // Códigos padronizados das levas 2 e 3 (P21–P40).
            'Population or host not eligible' => 'População ou hospedeiro fora do critério (espécie, origem, fase de vida).',
            'Intervention, exposure or condition not eligible' => 'Intervenção, exposição ou condição fora do critério.',
            'Comparator or reference group missing' => 'Sem grupo controle ou de comparação adequado.',
            'No eligible outcome, or outcome data not extractable' => 'Não mede o desfecho do protocolo, ou não dá para extrair os dados.',
            'Study design not eligible (including below the minimum' => 'Desenho fora do critério ou abaixo do número mínimo de animais.',
            'Review, editorial, letter, commentary or book chapter' => 'Revisão, editorial, carta, comentário ou capítulo, sem dados próprios.',
            'Conference abstract or report without sufficient' => 'Resumo de congresso ou relato sem métodos e dados suficientes.',
            'Duplicate or overlapping report' => 'Publicação duplicada ou sobreposta dos mesmos animais.',
            'Full text not obtainable' => 'Texto completo não obtido após as etapas previstas (só no texto completo).',
        ];
        foreach ($map as $en => $pt) {
            if (str_starts_with($label, $en)) {
                return $pt;
            }
        }

        return null;
    }

    public static function highlight(?string $text, array $green, array $red): string
    {
        $html = e((string) $text);
        $clean = fn (array $terms) => array_values(array_filter(array_map('trim', $terms), fn ($t) => mb_strlen($t) >= 2));
        $green = $clean($green);
        $red = $clean($red);
        if (!$green && !$red) {
            return $html;
        }
        // Uma passada só: o HTML inserido nunca é processado de novo.
        $all = array_merge($red, $green);
        usort($all, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        // Só no início de palavra: "rat" não marca "rate", "mice" não marca "accomplice".
        $pattern = '/(?<![\p{L}\p{N}])(' . implode('|', array_map(fn ($t) => preg_quote(e($t), '/'), $all)) . ')/iu';
        $redLower = array_map(fn ($t) => mb_strtolower(e($t)), $red);

        return preg_replace_callback($pattern, function ($m) use ($redLower) {
            [$bg, $fg] = in_array(mb_strtolower($m[1]), $redLower, true) ? ['#fecaca', '#7f1d1d'] : ['#bbf7d0', '#14532d'];

            return '<mark style="background:' . $bg . ';color:' . $fg . ';border-radius:3px;padding:0 2px">' . $m[1] . '</mark>';
        }, $html);
    }

    /**
     * Piloto em duas metas (metade cada, ex. 25 + 25): o tamanho do piloto do protocolo não muda,
     * só a forma de mostrar o andamento ao aluno, com um prazo por meta.
     *
     * @return array{n: int, done: int, size: int}|null null quando o piloto está completo
     */
    public static function pilotGoal(int $done, int $total, int $offset = 0): ?array
    {
        // Rodada 2+ de calibração: as metas contam só a amostra nova.
        $done = max(0, $done - $offset);
        $total = max(0, $total - $offset);
        if ($total <= 0 || $done >= $total) {
            return null;
        }
        $half = intdiv($total + 1, 2);

        return $done < $half
            ? ['n' => 1, 'done' => $done, 'size' => $half]
            : ['n' => 2, 'done' => $done - $half, 'size' => $total - $half];
    }

    /**
     * Os projetos REAIS do aluno, na ordem do código (PET antes de PAT), com o que falta em cada um.
     * Cada aluno está em mais de um projeto e precisa triar TODOS: sem este quadro, parte achou
     * que o segundo era a mesma tarefa repetida.
     *
     * @return \Illuminate\Support\Collection<int, array{screener: SystematicReviewScreener, review: SystematicReview, code: string, signed: bool, open: bool, done: int, total: int, finished: bool, pilot: bool, todo: bool}>
     */
    protected function realProjects()
    {
        if (static::$training) {
            return collect();
        }

        return static::myAssignments()->with('systematicReview')->get()
            ->map(function (SystematicReviewScreener $s) {
                $r = $s->systematicReview;
                $open = $r->status === SystematicReview::STATUS_SCREENING && $s->participatesIn($r->stage);
                $signed = $s->hasSignedTerms();
                $p = $open ? $this->service()->progress($s) : ['done' => 0, 'total' => 0];
                $finished = $open && ($s->hasFinished($r->stage) || ($r->inPilot() && $p['total'] > 0 && $p['done'] >= $p['total']));

                return [
                    'screener' => $s, 'review' => $r, 'code' => $r->displayCode() ?? $r->typeLabel(),
                    'signed' => $signed, 'open' => $open, 'done' => $p['done'], 'total' => $p['total'],
                    'finished' => $finished, 'pilot' => $open && $r->inPilot(),
                    'goal' => $open && $r->inPilot() ? static::pilotGoal($p['done'], $p['total'], $r->pilotRoundStart()) : null,
                    'todo' => !$signed || ($open && !$finished),
                ];
            })
            ->sortBy(fn ($x) => SystematicReview::selectionSortKey((string) $x['code']))
            ->values();
    }

    protected function getViewData(): array
    {
        $screener = $this->screener();
        if (!$screener) {
            return [
                'mode' => 'list',
                'assignments' => static::myAssignments()->with('systematicReview')->latest('id')->get(),
                'projects' => $this->realProjects(),
            ];
        }

        $review = $screener->systematicReview;

        if (!$screener->hasSignedTerms()) {
            return [
                'mode' => 'terms',
                'screener' => $screener,
                'review' => $review,
                'terms' => SystematicReviewScreener::termsText($review, $screener->name, $screener->email),
            ];
        }

        if ($review->is_training && $review->status === SystematicReview::STATUS_SCREENING_DONE) {
            return [
                'mode' => 'training_results',
                'screener' => $screener,
                'review' => $review,
                'results' => app(\App\Services\ScreeningTrainingService::class)->results($review),
            ];
        }

        $stage = $review->stage;
        $open = $review->status === SystematicReview::STATUS_SCREENING && $screener->participatesIn($stage);
        if (!$open || $screener->hasFinished($stage)) {
            // Aqui a conversa já aparece aberta: conta as novas para destacar e marca como lidas.
            $unread = $screener->unreadMessagesCount();
            $readBefore = $screener->chat_read_at;
            if ($unread > 0) {
                $screener->markChatRead();
            }

            $journey = \App\Support\ProgramJourney::for($review);

            return [
                'others' => $review->is_training ? collect() : $this->realProjects()->filter(fn ($x) => $x['screener']->id !== $screener->id && $x['todo'])->values(),
                'journey' => $journey,
                'you' => \App\Support\ProgramJourney::youText($review, $screener, $journey['current']),
                'unread' => $unread,
                'readBefore' => $readBefore,
                'mode' => 'waiting',
                'screener' => $screener,
                'review' => $review,
                'finished' => $screener->hasFinished($stage),
                'preparing' => $review->status === SystematicReview::STATUS_PROTOCOL,
                'partner' => static::partnerOf($screener),
                'chat' => $this->chatFor($screener),
            ];
        }

        $record = $this->currentRecord($screener);
        $mine = $record ? $screener->decisions()->where('stage', $stage)->where('systematic_review_record_id', $record->id)->first() : null;
        if ($this->noteRecordId !== $record?->id) {
            // Mudou de trabalho (próximo, anterior, salto ou depois de decidir): mostra a observação já gravada dele.
            $this->note = (string) ($mine?->note ?? '');
            $this->noteRecordId = $record?->id;
        }
        $progress = $this->service()->progress($screener);
        $prioritised = $this->service()->prioritisedNow($review, $stage);
        $al = app(\App\Services\ActiveLearningService::class);
        $qc = $prioritised && $al->isQcScreener($screener);

        return [
            'others' => $review->is_training ? collect() : $this->realProjects()->filter(fn ($x) => $x['screener']->id !== $screener->id && $x['todo'])->values(),
            'journey' => \App\Support\ProgramJourney::for($review),
            'chat' => $this->chatFor($screener),
            'unread' => $screener->unreadMessagesCount(),
            'readBefore' => $screener->chat_read_at,
            'prioritised' => $prioritised,
            'qc' => $qc,
            'stop' => $prioritised && !$qc ? $al->stoppingStatus($screener) : null,
            'mode' => 'screen',
            'screener' => $screener,
            'review' => $review,
            'record' => $record,
            'mine' => $mine,
            'progress' => $progress,
            'reasons' => $this->service()->reasonsFor($review, $stage),
            'allowed' => $review->allowedDecisions($stage),
            'maybeRule' => $stage === SystematicReview::STAGE_TA && $review->maybeAdvancesToFullText(),
            'green' => $review->highlight_terms['include'] ?? [],
            'red' => $review->highlight_terms['exclude'] ?? [],
            'checkItems' => $review->screeningChecklistFor($this->service()->reasonsFor($review, $stage)),
            'counter' => static::counter($progress['done'], $progress['total'], (bool) $mine, !$review->is_training && $review->inPilot(), $review->pilotRoundStart()),
        ];
    }

    /**
     * Contador do cartão do trabalho, pelo que o aluno JÁ ANALISOU (não pela posição na ordem
     * embaralhada): ao pular os já votados a posição saltava (2 → 17) e parecia ordem errada.
     * No piloto conta dentro da meta atual (25 + 25).
     *
     * @return array{n: int, done: int, size: int, meta: ?int, decided: bool}
     */
    public static function counter(int $done, int $total, bool $decided, bool $pilot, int $offset = 0): array
    {
        $goal = $pilot ? static::pilotGoal($done, $total, $offset) : null;
        $size = $goal['size'] ?? $total;
        $inMeta = $goal['done'] ?? $done;

        return [
            'n' => min($size, $inMeta + 1),
            'done' => $inMeta,
            'size' => $size,
            'meta' => $goal['n'] ?? null,
            'decided' => $decided,
        ];
    }
}
