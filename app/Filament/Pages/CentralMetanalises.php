<?php

namespace App\Filament\Pages;

use App\Filament\Resources\SystematicReviewResource;
use App\Models\SrMessage;
use App\Models\SystematicReview;
use App\Models\SystematicReviewDecision;
use App\Models\SystematicReviewScreener;
use App\Services\SystematicReviewService;
use App\Support\ProgramJourney;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Visão única do programa de metanálises: situação de cada projeto (termos,
 * protocolo, OSF, triagem), as conversas de todos os projetos e o histórico
 * de atividades — sem abrir revisão por revisão.
 */
class CentralMetanalises extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';

    protected static ?string $navigationGroup = 'Programa de Metanálises';

    protected static ?string $navigationLabel = 'Central das Metanálises';

    protected static ?string $title = 'Central das Metanálises';

    protected static ?string $slug = 'central-metanalises';

    protected static string $view = 'filament.pages.central-metanalises';

    protected static ?int $navigationSort = -40; // Grupo no topo do menu, na ordem do trabalho: Central → Revisões → Inscrições → Seleção.

    #[Url(as: 'projeto')]
    public string $project = '';

    #[Url(as: 'ver')]
    public string $tab = 'conversas';

    public bool $onlyStudents = false;

    /** Só as mensagens de aluno ainda sem resposta nem marcadas como vistas. */
    public bool $onlyAwaiting = false;

    public bool $showAutomatic = false;

    public string $replyReview = '';

    public string $replyBody = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    /** Projetos cuja última mensagem é de aluno e o professor ainda não respondeu nem marcou como vista. */
    public static function awaitingReplyIds(): array
    {
        // Só os projetos que aparecem na Central (fora treinos e arquivados): senão o aviso conta mensagem que não dá para abrir.
        $lastIds = SrMessage::query()->selectRaw('MAX(id) as id')
            ->whereIn('systematic_review_id', static::programQuery()->select('id'))
            ->groupBy('systematic_review_id')->pluck('id');

        return SrMessage::whereIn('id', $lastIds)->where('author_role', SrMessage::ROLE_STUDENT)
            ->whereNull('seen_at')
            ->pluck('systematic_review_id')->all();
    }

    /** Tira o aviso "aguardando mensagem" do projeto sem responder (as mensagens continuam na conversa). */
    public function markSeen(int $reviewId): void
    {
        SrMessage::where('systematic_review_id', $reviewId)
            ->where('author_role', SrMessage::ROLE_STUDENT)
            ->whereNull('seen_at')
            ->update(['seen_at' => now()]);
        Notification::make()->title('Mensagem marcada como vista')->success()->send();
    }

    public static function getNavigationBadge(): ?string
    {
        if (!static::canAccess()) {
            return null;
        }
        $n = count(static::awaitingReplyIds());

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Projetos aguardando mensagem (mensagem de aluno sem resposta nem marcada como vista)';
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['conversas', 'atividades', 'enviadas'], true) ? $tab : 'conversas';
    }

    /**
     * Tudo o que saiu do professor para os participantes do programa: os e-mails
     * (cópia em email_logs, agrupados por envio em massa) e as mensagens dele nas
     * conversas. Avisos automáticos do site só entram com $showAutomatic.
     */
    protected function sentItems(): Collection
    {
        $people = \App\Models\ResearchApplication::query()->get(['name', 'email'])
            ->concat(SystematicReviewScreener::query()->get(['name', 'email']))
            ->mapWithKeys(fn ($p) => [mb_strtolower(trim((string) $p->email)) => $p->name])
            ->filter(fn ($name, $email) => $email !== '');

        $mails = \App\Models\EmailLog::query()
            ->whereIn('recipient', $people->keys()->all())
            ->where('subject', 'not like', 'Nova mensagem no projeto%')
            ->orderByDesc('sent_at')->orderByDesc('id')
            ->limit(1500)
            ->get(['id', 'recipient', 'subject', 'body', 'sent_at', 'created_at']);

        $groups = [];
        foreach ($mails as $mail) {
            $last = $groups ? $groups[array_key_last($groups)] : null;
            $sentAt = $mail->sent_at ?? $mail->created_at;
            if ($last && $last['subject'] === $mail->subject && $last['at'] && $sentAt && $last['first']->diffInMinutes($sentAt, true) <= 30) {
                $groups[array_key_last($groups)]['to'][] = $people[mb_strtolower($mail->recipient)] ?? $mail->recipient;
                $groups[array_key_last($groups)]['first'] = $sentAt;

                continue;
            }
            $groups[] = [
                'type' => 'email',
                'at' => $sentAt,
                'first' => $sentAt,
                'subject' => $mail->subject,
                'to' => [$people[mb_strtolower($mail->recipient)] ?? $mail->recipient],
                'html' => $mail->body,
                'manual' => static::isWrittenMessage((string) $mail->subject),
            ];
        }

        $chat = SrMessage::query()->where('author_role', SrMessage::ROLE_PROFESSOR)
            ->with('systematicReview')->orderByDesc('id')->limit(300)->get()
            ->map(fn (SrMessage $m) => [
                'type' => 'chat',
                'at' => $m->created_at,
                'subject' => 'Conversa do projeto ' . ($m->systematicReview ? static::codeOf($m->systematicReview) : ''),
                'to' => $m->systematicReview ? $m->systematicReview->activeScreeners()->pluck('name')->all() : [],
                'body' => $m->body,
                'manual' => true,
            ]);

        return collect($groups)
            ->filter(fn ($g) => $this->showAutomatic || $g['manual'])
            ->concat($chat)
            ->sortByDesc(fn ($g) => $g['at']?->timestamp ?? 0)
            ->take(200)
            ->values();
    }

    /** E-mail escrito pelo professor (ações "Enviar mensagem"), e não aviso automático do site. */
    public static function isWrittenMessage(string $subject): bool
    {
        return str_ends_with($subject, ' — Prof. Atilio') && !str_starts_with($subject, 'Convite:');
    }

    public function replyTo(int $reviewId): void
    {
        $this->replyReview = (string) $reviewId;
        $this->dispatch('central-reply-focus');
    }

    public function sendReply(): void
    {
        $review = SystematicReview::find((int) $this->replyReview);
        if (!$review) {
            Notification::make()->title('Escolha o projeto.')->danger()->send();

            return;
        }
        try {
            app(SystematicReviewService::class)->postMessage($review, auth()->user(), $this->replyBody);
        } catch (\RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }
        $this->replyBody = '';
        Notification::make()->title('Mensagem enviada à dupla de ' . static::codeOf($review) . ' e registrada')->success()->send();
    }

    public static function codeOf(SystematicReview $review): string
    {
        return $review->selectionCode() ?? (string) ($review->protocol_code ?: '#' . $review->id);
    }

    /** Consulta das revisões do programa (com dupla ou código de protocolo), não arquivadas. */
    public static function programQuery()
    {
        return SystematicReview::query()
            ->where('status', '!=', SystematicReview::STATUS_ARCHIVED)
            ->where('is_training', false)
            ->where(fn ($q) => $q->whereNotNull('protocol_code')->orWhereHas('screeners'));
    }

    /**
     * Indicadores do programa para o Dashboard (mesmas regras da tabela da Central).
     * Só colunas leves: a linha da revisão carrega o pipeline JSON inteiro.
     *
     * @return array{projects: int, signed: int, people: int, awaiting: int, frozen: int, osf: int}
     */
    public static function summary(): array
    {
        $reviews = static::programQuery()
            ->with(['screeners' => fn ($q) => $q->whereNull('removed_at')->select(['id', 'systematic_review_id', 'terms_accepted_at'])])
            ->get(['id', 'protocol_frozen_at', 'osf_project_url']);
        $screeners = $reviews->flatMap->screeners;

        return [
            'projects' => $reviews->count(),
            'signed' => $screeners->whereNotNull('terms_accepted_at')->count(),
            'people' => $screeners->count(),
            'awaiting' => count(array_intersect(static::awaitingReplyIds(), $reviews->pluck('id')->all())),
            'frozen' => $reviews->whereNotNull('protocol_frozen_at')->count(),
            'osf' => $reviews->filter(fn ($r) => filled($r->osf_project_url))->count(),
        ];
    }

    /** Revisões do programa, em ordem PET → PAT. */
    protected function reviews(): Collection
    {
        return static::programQuery()
            ->with(['screeners' => fn ($q) => $q->whereNull('removed_at')])
            ->withCount(['records', 'messages'])
            ->get()
            ->sortBy(fn (SystematicReview $r) => static::codeOf($r))
            ->values();
    }

    protected function getViewData(): array
    {
        $reviews = $this->reviews();
        $awaiting = static::awaitingReplyIds();
        $ids = $reviews->pluck('id');

        $screenerIds = $reviews->flatMap(fn ($r) => $r->screeners->pluck('id'));
        $decisions = SystematicReviewDecision::query()
            ->whereIn('systematic_review_screener_id', $screenerIds)
            ->where('stage', SystematicReview::STAGE_TA)
            ->selectRaw('systematic_review_screener_id, COUNT(*) as n')
            ->groupBy('systematic_review_screener_id')
            ->pluck('n', 'systematic_review_screener_id');

        $lastMessages = SrMessage::whereIn('id', SrMessage::query()->whereIn('systematic_review_id', $ids)
            ->selectRaw('MAX(id) as id')->groupBy('systematic_review_id')->pluck('id'))
            ->get()->keyBy('systematic_review_id');

        $rows = $reviews->map(function (SystematicReview $r) use ($decisions, $lastMessages, $awaiting) {
            $lead = $r->screeners->first(fn ($s) => str_starts_with((string) $s->label, 'Líder')) ?? $r->screeners->first();
            $second = $r->screeners->first(fn ($s) => $s->id !== $lead?->id);
            $person = fn (?SystematicReviewScreener $s) => $s ? [
                'name' => $s->name,
                'email' => $s->email,
                'signed' => $s->terms_accepted_at,
                'decisions' => (int) ($decisions[$s->id] ?? 0),
            ] : null;

            return [
                'id' => $r->id,
                'code' => static::codeOf($r),
                'protocol' => $r->protocol_code,
                'status' => $r->statusLabel(),
                'frozen' => $r->protocol_frozen_at,
                'osf' => $r->osf_project_url,
                'records' => $r->records_count,
                'lead' => $person($lead),
                'second' => $person($second),
                'messages' => $r->messages_count,
                'last' => $lastMessages[$r->id] ?? null,
                'awaiting' => in_array($r->id, $awaiting, true),
                'url' => SystematicReviewResource::getUrl('view', ['record' => $r]),
                'journey' => ProgramJourney::for($r),
            ];
        });

        $filterIds = $this->project !== '' ? [(int) $this->project] : $ids->all();

        $messages = SrMessage::query()
            ->whereIn('systematic_review_id', $filterIds)
            ->when($this->onlyStudents, fn ($q) => $q->where('author_role', SrMessage::ROLE_STUDENT))
            ->when($this->onlyAwaiting, fn ($q) => $q->where('author_role', SrMessage::ROLE_STUDENT)->whereNull('seen_at')
                ->whereIn('systematic_review_id', $awaiting))
            ->with('systematicReview')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $activity = $reviews->whereIn('id', $filterIds)
            ->flatMap(fn (SystematicReview $r) => collect($r->history ?? [])->map(fn ($h) => [
                'at' => isset($h['at']) ? Carbon::parse($h['at']) : null,
                'code' => static::codeOf($r),
                'event' => $h['event'] ?? '',
                'details' => $h['details'] ?? null,
                'actor' => $h['actor'] ?? null,
            ]))
            ->sortByDesc(fn ($h) => $h['at']?->timestamp ?? 0)
            ->take(150)
            ->values();

        $people = $rows->flatMap(fn ($row) => array_filter([$row['lead'], $row['second']]));

        return [
            'rows' => $rows,
            'messages' => $messages,
            'awaitingIds' => $awaiting,
            'activity' => $activity,
            'sent' => $this->tab === 'enviadas' ? $this->sentItems() : collect(),
            'projectOptions' => $rows->mapWithKeys(fn ($row) => [$row['id'] => $row['code'] . ($row['protocol'] ? " ({$row['protocol']})" : '')]),
            'chatOptions' => $rows->filter(fn ($row) => $row['lead'] || $row['second'])
                ->mapWithKeys(fn ($row) => [$row['id'] => $row['code']]),
            'stats' => [
                'signed' => $people->filter(fn ($p) => $p['signed'])->count(),
                'people' => $people->count(),
                'awaiting' => $rows->where('awaiting', true)->count(),
                'week' => SrMessage::whereIn('systematic_review_id', $ids)->where('created_at', '>=', now()->subDays(7))->count(),
                'frozen' => $rows->filter(fn ($row) => $row['frozen'])->count(),
                'osf' => $rows->filter(fn ($row) => $row['osf'])->count(),
                'projects' => $rows->count(),
                'registered' => $rows->filter(fn ($row) => $row['journey']['search'] !== null)->count(),
                // Prazo do protocolo: busca em até 14 dias depois do registro.
                'searchUrgent' => $rows->filter(fn ($row) => in_array($row['journey']['search']['level'] ?? null, ['urgent', 'late'], true))->count(),
            ],
        ];
    }
}
