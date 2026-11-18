<?php

namespace App\Filament\Pages;

use App\Models\SrExtraction;
use App\Models\SrStudy;
use App\Models\SystematicReview;
use App\Models\SystematicReviewScreener;
use App\Services\SystematicReviewExtractionService;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Portal do aluno: extração de dados e risco de viés em duplicata (cega).
 * O formulário é gerado do protocolo registrado (pipeline JSON).
 */
class ExtracaoRob extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationLabel = 'Extração e Risco de Viés';

    protected static ?string $title = 'Extração e Risco de Viés';

    protected static ?string $slug = 'extracao';

    protected static string $view = 'filament.pages.extracao-rob';

    protected static ?int $navigationSort = -5;

    public static function getNavigationGroup(): ?string
    {
        return Filament::getCurrentPanel()?->getId() === 'portal' ? 'Programa de Metanálises' : null;
    }

    public ?int $screenerId = null;

    public ?int $studyId = null;

    public ?array $data = [];

    protected static function myAssignments()
    {
        return SystematicReviewScreener::where('user_id', auth()->id())
            ->whereNull('removed_at')
            ->whereNotNull('terms_accepted_at')
            ->whereHas('systematicReview', fn ($q) => $q->whereNotNull('extraction_opened_at'));
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && static::myAssignments()->exists();
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    protected function service(): SystematicReviewExtractionService
    {
        return app(SystematicReviewExtractionService::class);
    }

    public function mount(): void
    {
        $only = static::myAssignments()->pluck('id');
        if ($only->count() === 1) {
            $this->screenerId = $only->first();
        }
        $this->form->fill([]);
        // Link direto para um estudo (?estudo=ID): volta exatamente onde parou.
        if ($this->screenerId && ($estudo = (int) request()->query('estudo'))) {
            $this->open($estudo);
        }
    }

    protected function screener(): ?SystematicReviewScreener
    {
        return $this->screenerId ? static::myAssignments()->with('systematicReview')->find($this->screenerId) : null;
    }

    protected function study(): ?SrStudy
    {
        $screener = $this->screener();

        return $screener && $this->studyId ? $this->service()->availableStudies($screener)->find($this->studyId) : null;
    }

    public function choose(int $id): void
    {
        $this->screenerId = $id;
        $this->studyId = null;
    }

    public function back(): void
    {
        if ($this->studyId) {
            $this->studyId = null;

            return;
        }
        $this->screenerId = null;
    }

    public function open(int $studyId): void
    {
        $this->studyId = $studyId;
        $study = $this->study();
        if (!$study) {
            $this->studyId = null;

            return;
        }
        // O esquema depende do estudo/protocolo: reconstrói o formulário em cache.
        $this->cacheForms();
        $e = $this->service()->extractionFor($this->screener(), $study);
        $outcomes = collect($e->outcomes ?? [])->values()->all();
        $this->form->fill([
            'field_values' => $e->field_values ?? [],
            'sources' => $e->sources ?? [],
            'outcomes' => $outcomes,
            'rob' => $e->rob ?? [],
            'notes' => $e->notes,
        ]);
    }

    public function form(Form $form): Form
    {
        $screener = $this->screener();
        $review = $screener?->systematicReview;
        if (!$review || !$this->studyId) {
            return $form->schema([])->statePath('data');
        }
        $svc = $this->service();
        $mine = $this->service()->extractionFor($screener, $this->study() ?? new SrStudy());
        $readonly = (bool) $mine->isSubmitted();
        // Só existe pré-preenchimento na extração do próprio Revisor 1 (o Revisor 2 nunca vê).
        $aiPrefilled = $mine->aiPrefilled();

        $fieldRows = collect($svc->fields($review))->map(function ($f) use ($readonly) {
            $input = match ($f['type']) {
                'category' => Forms\Components\Select::make("field_values.{$f['name']}")->options(array_combine($f['options'], $f['options']))->native(true),
                'integer', 'number' => Forms\Components\TextInput::make("field_values.{$f['name']}")->inputMode('decimal'),
                default => Forms\Components\TextInput::make("field_values.{$f['name']}"),
            };

            return Forms\Components\Grid::make(['default' => 1, 'md' => 3])->schema([
                $input->label($f['name'])->helperText($f['description'] ?: null)->disabled($readonly)->columnSpan(['default' => 1, 'md' => 2]),
                Forms\Components\TextInput::make("sources.{$f['name']}")->label('Onde está (pág./tabela/figura)')->placeholder('p. 4, Tab. 2')->disabled($readonly),
            ]);
        })->all();

        $defs = $svc->outcomeDefs($review);
        $robSections = collect($svc->robTools($review))->map(fn ($t) => Forms\Components\Section::make("Risco de viés — {$t['name']}")
            ->description('Para cada domínio: escolha o julgamento e copie o trecho (com a página) que o justifica.')
            ->collapsible()
            ->schema(array_merge(
                collect($t['domains'])->map(fn ($d) => Forms\Components\Grid::make(['default' => 1, 'md' => 3])->schema([
                    Forms\Components\Select::make("rob.{$t['code']}.{$d}.judgement")->label($d)->options(SrExtraction::ROB_JUDGEMENTS)->native(true)->disabled($readonly),
                    Forms\Components\Textarea::make("rob.{$t['code']}.{$d}.support")->label('Justificativa (trecho e página)')->rows(2)->disabled($readonly)->columnSpan(['default' => 1, 'md' => 2]),
                ]))->all(),
                [Forms\Components\Select::make("rob.{$t['code']}._overall")->label('Julgamento geral')->options(SrExtraction::ROB_JUDGEMENTS)->native(true)->disabled($readonly)]
            )))->all();

        $ai = $mine->ai_prefill ?? [];
        $aiNotice = $aiPrefilled ? [Forms\Components\Placeholder::make('ai_notice')->hiddenLabel()->content(new \Illuminate\Support\HtmlString(
            '<div style="padding:.75rem 1rem;border-radius:.75rem;background:#eff6ff;border:1px solid #93c5fd;color:#1e3a8a;font-size:.9rem;line-height:1.5">'
            . '🤖 <strong>Pré-preenchido por IA</strong> (' . e(trim(($ai['model'] ?? '') . ' ' . ($ai['version'] ?? ''))) . '), como prevê o protocolo registrado. '
            . 'Confira <strong>cada valor</strong> no PDF e anote onde está. Corrija o que estiver errado e <strong>apague o que não estiver no artigo</strong>. '
            . 'O risco de viés é só seu: a IA não avalia. O sistema registra quantos valores você manteve, corrigiu ou apagou.</div>'))] : [];

        return $form->statePath('data')->schema(array_merge($aiNotice, [
            Forms\Components\Section::make('Dados do estudo')
                ->description('Valores brutos, como aparecem no artigo (não calcule nada aqui). Todo número precisa da página, tabela ou figura de onde saiu.')
                ->collapsible()
                ->schema($fieldRows ?: [Forms\Components\Placeholder::make('nofields')->hiddenLabel()->content('O protocolo não define campos descritivos.')]),
            Forms\Components\Section::make('Resultados por desfecho')
                ->description('Uma linha por desfecho × grupo × momento. Use proporção entre 0 e 1 (12% = 0,12). Marque "lido de gráfico" quando o número vier de figura.')
                ->collapsible()
                ->schema([
                    Forms\Components\Repeater::make('outcomes')->hiddenLabel()
                        ->schema([
                            Forms\Components\Select::make('outcome_id')->label('Desfecho')->required()->native(true)
                                ->options(collect($defs)->mapWithKeys(fn ($o, $id) => [$id => "{$id} — " . \Illuminate\Support\Str::limit($o['name'] ?? '', 70)])->all())
                                ->columnSpanFull(),
                            Forms\Components\TextInput::make('group_label')->label('Grupo / braço')->placeholder('ex.: tratado, controle, total'),
                            Forms\Components\TextInput::make('timepoint')->label('Momento')->placeholder('ex.: 6 h'),
                            Forms\Components\TextInput::make('n')->label('n')->inputMode('numeric'),
                            Forms\Components\TextInput::make('events')->label('Eventos')->inputMode('numeric'),
                            Forms\Components\TextInput::make('mean')->label('Média')->inputMode('decimal'),
                            Forms\Components\TextInput::make('sd')->label('DP')->inputMode('decimal'),
                            Forms\Components\TextInput::make('proportion')->label('Proporção (0–1)')->inputMode('decimal'),
                            Forms\Components\TextInput::make('effect')->label('Efeito relatado')->inputMode('decimal'),
                            Forms\Components\TextInput::make('ci_low')->label('IC 95% inferior')->inputMode('decimal'),
                            Forms\Components\TextInput::make('ci_high')->label('IC 95% superior')->inputMode('decimal'),
                            Forms\Components\TextInput::make('source_location')->label('Onde está (pág./tabela/figura)')->columnSpanFull(),
                            Forms\Components\Toggle::make('digitised')->label('Lido de gráfico (WebPlotDigitizer)'),
                        ])
                        ->columns(['default' => 2, 'md' => 4])
                        ->addActionLabel('Adicionar resultado')
                        ->collapsible()
                        ->itemLabel(fn (array $state) => trim(($state['outcome_id'] ?? '') . ' ' . ($state['group_label'] ?? '') . ' ' . ($state['timepoint'] ?? '')) ?: null)
                        ->defaultItems(0)
                        ->disabled($readonly),
                ]),
        ], $robSections, [
            Forms\Components\Textarea::make('notes')->label('Dúvidas e observações para a reunião (opcional)')->rows(2)->disabled($readonly),
        ], $aiPrefilled && !$readonly ? [
            Forms\Components\Checkbox::make('ai_verified')
                ->label('Conferi no PDF cada valor pré-preenchido pela IA, anotei onde está e apaguei o que não está no artigo.')
                ->helperText('Obrigatório para enviar este estudo.'),
        ] : []));
    }

    public function saveDraft(): void
    {
        $this->persist(false);
    }

    public function submit(): void
    {
        $this->persist(true);
    }

    protected function persist(bool $submit): void
    {
        $screener = $this->screener();
        $study = $this->study();
        if (!$screener || !$study) {
            return;
        }
        try {
            $this->service()->save($screener, $study, $this->form->getState(), $submit);
        } catch (\RuntimeException $e) {
            Notification::make()->title($submit ? 'Não enviado: confira' : 'Não salvo')->body($e->getMessage())->danger()->persistent()->send();

            return;
        }
        Notification::make()->title($submit ? 'Estudo enviado' : 'Rascunho salvo')->success()->send();
        if ($submit) {
            $this->studyId = null;
        }
    }

    protected function getViewData(): array
    {
        $screener = $this->screener();
        if (!$screener) {
            return ['mode' => 'list', 'assignments' => static::myAssignments()->with('systematicReview')->get()];
        }
        $review = $screener->systematicReview;
        $studies = $this->service()->availableStudies($screener)->get();
        $mine = SrExtraction::where('systematic_review_screener_id', $screener->id)->whereIn('sr_study_id', $studies->pluck('id'))->get()->keyBy('sr_study_id');
        $study = $this->study();

        return [
            'mode' => $study ? 'form' : 'studies',
            'screener' => $screener,
            'review' => $review,
            'studies' => $studies,
            'mine' => $mine,
            'study' => $study,
            'submitted' => $study ? (bool) ($mine[$study->id] ?? null)?->isSubmitted() : false,
            'records' => $study ? $study->recordsQuery()->get() : collect(),
            'done' => $mine->filter->isSubmitted()->count(),
        ];
    }
}
