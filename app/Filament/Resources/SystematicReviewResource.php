<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SystematicReviewResource\Pages;
use App\Filament\Resources\SystematicReviewResource\RelationManagers;
use App\Models\SystematicReview;
use App\Services\SystematicReviewService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class SystematicReviewResource extends Resource
{
    protected static ?string $model = SystematicReview::class;

    protected static ?string $navigationIcon = 'heroicon-o-funnel';

    protected static ?string $navigationLabel = 'Revisões Sistemáticas';

    protected static ?string $modelLabel = 'Revisão Sistemática';

    protected static ?string $pluralModelLabel = 'Revisões Sistemáticas / Metanálises';

    protected static ?string $navigationGroup = 'Programa de Metanálises';

    protected static ?string $slug = 'revisoes-sistematicas';

    protected static ?int $navigationSort = -39;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    /** Badge: projetos com conflitos esperando o professor. */
    public static function getNavigationBadge(): ?string
    {
        $n = SystematicReview::where('status', SystematicReview::STATUS_CONFLICTS)->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Triagens concluídas pelos alunos esperando seu desempate';
    }

    public static function form(Form $form): Form
    {
        // Protocolo congelado: somente leitura (campos desabilitados não são salvos). Mudança só por emenda.
        $frozen = fn (?SystematicReview $record) => (bool) $record?->isFrozen();
        $protocolField = fn (string $key, int $rows = 3, ?string $help = null) => Forms\Components\Textarea::make("protocol.{$key}")
            ->label(SystematicReview::PROTOCOL_FIELDS[$key])
            ->rows($rows)
            ->helperText($help)
            ->disabled($frozen)
            ->maxLength(10000);

        return $form->schema([
            Forms\Components\Section::make('Projeto')
                ->columns(2)
                ->schema([
                    Forms\Components\Placeholder::make('how')
                        ->hiddenLabel()
                        ->columnSpanFull()
                        ->content(new HtmlString(
                            '<div style="font-size:.875rem;line-height:1.6;background:rgba(13,148,136,.07);border:1px solid rgba(13,148,136,.25);border-radius:.75rem;padding:.75rem 1rem">'
                            . '<strong>Fluxo:</strong> protocolo → registro no OSF → <strong>importar</strong> o arquivo do script (ou .ris) → incluir os <strong>triadores</strong> '
                            . '→ <strong>Liberar triagem</strong>. Os alunos assinam o termo e triam no portal (cegos). Quando terminam, você recebe o kappa e os conflitos, '
                            . 'desempata e encerra a etapa (pode seguir para o texto completo). No fim, crie o processo de redação do artigo a partir daqui.</div>'
                        )),
                    Forms\Components\Placeholder::make('frozen_notice')
                        ->hiddenLabel()
                        ->columnSpanFull()
                        ->visible($frozen)
                        ->content(fn (?SystematicReview $record) => new HtmlString(
                            '<div style="font-size:.875rem;background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.4);border-radius:.75rem;padding:.75rem 1rem">'
                            . '🔒 <strong>Protocolo congelado</strong> em ' . $record?->protocol_frozen_at?->format('d/m/Y H:i') . ' (versão ' . e($record?->protocol_version) . ').'
                            . ' Título, tipo, critérios, códigos de exclusão e campos do protocolo estão somente leitura. Para mudar, registre uma <strong>emenda</strong> em "Protocolo e OSF".</div>')),
                    Forms\Components\TextInput::make('title')
                        ->label('Título do projeto')
                        ->disabled($frozen)
                        ->required()
                        ->maxLength(500)
                        ->columnSpanFull(),
                    Forms\Components\Select::make('review_type')
                        ->label('Tipo')
                        ->disabled($frozen)
                        ->options(SystematicReview::REVIEW_TYPES)
                        ->default('meta_analysis')
                        ->required()
                        ->native(false),
                    Forms\Components\Select::make('framework')
                        ->label('Estrutura da pergunta')
                        ->disabled($frozen)
                        ->options(SystematicReview::FRAMEWORKS)
                        ->default('PICO')
                        ->required()
                        ->native(false),
                    Forms\Components\TextInput::make('short_name')
                        ->label('Nome curto / pasta')
                        ->placeholder('ex.: 01_Meloxicam_Lagomorfos')
                        ->maxLength(80),
                ]),

            Forms\Components\Section::make('Protocolo (PRISMA-P)')
                ->description('Em inglês, como vai para o OSF. Critérios de inclusão e exclusão são obrigatórios para liberar a triagem — os alunos veem esta parte.')
                ->collapsible()
                ->columns(2)
                ->schema([
                    $protocolField('title_en', 2)->columnSpanFull(),
                    $protocolField('background', 3)->columnSpanFull(),
                    $protocolField('question', 2)->columnSpanFull(),
                    $protocolField('population'),
                    $protocolField('intervention'),
                    $protocolField('comparator'),
                    $protocolField('outcomes'),
                    $protocolField('study_designs')->columnSpanFull(),
                    $protocolField('inclusion_criteria', 4),
                    $protocolField('exclusion_criteria', 4),
                    $protocolField('databases', 2, 'Ex.: PubMed/MEDLINE, Web of Science Core Collection, Scopus, CAB Abstracts'),
                    $protocolField('limits', 2),
                    $protocolField('search_string', 4, 'Sintaxe booleana completa (é preenchida sozinha na importação do script, se estiver vazia).')->columnSpanFull(),
                    $protocolField('screening_method', 2)->default('Two independent reviewers will screen titles/abstracts and full texts in blinded mode; disagreements will be resolved by a senior reviewer. Agreement will be reported as Cohen\'s kappa.')->columnSpanFull(),
                    $protocolField('data_items', 3)->columnSpanFull(),
                    $protocolField('rob_tool', 2),
                    $protocolField('synthesis', 3),
                    $protocolField('heterogeneity', 2),
                    $protocolField('certainty', 2),
                ]),

            Forms\Components\Section::make('Registro do protocolo')
                ->description('Registre ANTES da triagem. Sem o registro (ou uma justificativa), a triagem não é liberada.')
                ->collapsible()
                ->columns(4)
                ->schema([
                    Forms\Components\Select::make('registration_platform')
                        ->label('Plataforma')
                        ->options(SystematicReview::REGISTRATION_PLATFORMS)
                        ->default('OSF')
                        ->native(false),
                    Forms\Components\TextInput::make('registration_id')
                        ->label('DOI / ID do registro')
                        ->disabled(fn (?SystematicReview $record) => (bool) $record?->protocol_code)
                        ->helperText(fn (?SystematicReview $record) => $record?->protocol_code ? 'Projeto com protocolo importado: grave o registro em "Protocolo e OSF → Registro no OSF".' : null)
                        ->placeholder('10.17605/OSF.IO/XXXXX')
                        ->maxLength(255),
                    Forms\Components\DatePicker::make('registered_at')->label('Registrado em'),
                    Forms\Components\DatePicker::make('embargo_until')->label('Embargo até')->helperText('Se registrou sob embargo.'),
                    Forms\Components\Textarea::make('registration_waiver')
                        ->label('Sem registro? Justifique (aparece no relatório)')
                        ->rows(2)
                        ->maxLength(1000)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Triagem')
                ->collapsible()
                ->columns(2)
                ->schema([
                    Forms\Components\Textarea::make('screener_instructions')
                        ->label('Instruções aos triadores')
                        ->default(SystematicReview::DEFAULT_INSTRUCTIONS)
                        ->afterStateHydrated(function (Forms\Components\Textarea $component, ?string $state) {
                            if (blank($state)) {
                                $component->state(SystematicReview::DEFAULT_INSTRUCTIONS);
                            }
                        })
                        ->rows(7)
                        ->maxLength(5000)
                        ->columnSpanFull(),
                    Forms\Components\TagsInput::make('exclusion_reasons')
                        ->label('Motivos de exclusão (os alunos escolhem um)')
                        ->disabled($frozen)
                        ->default(SystematicReview::DEFAULT_EXCLUSION_REASONS)
                        ->afterStateHydrated(function (Forms\Components\TagsInput $component, $state) {
                            if (empty($state)) {
                                $component->state(SystematicReview::DEFAULT_EXCLUSION_REASONS);
                            }
                        })
                        ->columnSpanFull(),
                    // Não mexe no protocolo (o código e o texto em inglês continuam os mesmos): só o que o aluno lê no botão.
                    Forms\Components\KeyValue::make('exclusion_reasons_pt')
                        ->label('Motivos de exclusão em português (o que o aluno lê no botão)')
                        ->keyLabel('Código')->valueLabel('Texto curto em português')
                        ->helperText('Ex.: E1 → "Classificação modificada que não converte em Kenney-Doig (não é fora do tema)". O voto continua gravando o código do protocolo.')
                        ->columnSpanFull(),
                    Forms\Components\TagsInput::make('highlight_terms.include')
                        ->label('Termos destacados em VERDE')
                        ->helperText('Ex.: rabbit, meloxicam, pain — ajudam a ler rápido.'),
                    Forms\Components\TagsInput::make('highlight_terms.exclude')
                        ->label('Termos destacados em VERMELHO')
                        ->helperText('Ex.: human, in vitro, review.'),
                    Forms\Components\Textarea::make('screening_tips')
                        ->label('Atenção neste projeto (quadro na tela da triagem)')
                        ->helperText('Erros mais comuns e lembretes. Não pode conter decisões de artigos do piloto nem dizer quem marcou o quê.')
                        ->rows(6)
                        ->maxLength(4000)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('pilot_size')
                        ->label('Piloto de calibração (nº de registros)')
                        ->helperText('Os triadores fazem só os N primeiros; você confere o kappa e libera o resto. 0 = sem piloto. Sugestão: 50.')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->maxValue(1000)
                        ->default(50),
                ]),

            Forms\Components\Textarea::make('notes')
                ->label('Anotações privadas')
                ->rows(2)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['screeners' => fn ($q) => $q->whereNull('removed_at')]))
            ->defaultSort('protocol_code')
            ->columns([
                // Código da seleção (PET-01…, PAT-01…): é por ele que o professor e os alunos se referem ao projeto.
                Tables\Columns\TextColumn::make('protocol_code')->label('Código')->badge()->color('gray')
                    ->formatStateUsing(fn (SystematicReview $r) => $r->selectionCode() ?? $r->protocol_code)
                    ->description(fn (SystematicReview $r) => $r->selectionCode() ? $r->protocol_code : null)
                    ->placeholder('—')
                    ->sortable()
                    ->searchable(query: fn ($query, string $search) => $query->where('protocol_code', self::protocolCodeFromSearch($search))),
                Tables\Columns\TextColumn::make('terms')->label('Termos')
                    ->state(function (SystematicReview $r) {
                        $people = $r->screeners;

                        return $people->isEmpty() ? '—' : $people->whereNotNull('terms_accepted_at')->count() . '/' . $people->count();
                    })
                    ->badge()
                    ->color(fn (string $state) => $state === '2/2' ? 'success' : ($state === '—' ? 'gray' : 'warning'))
                    ->description(fn (SystematicReview $r) => $r->isFrozen() ? '🔒 congelado' : (self::readyToFreeze($r) ? 'pronto para congelar' : null)),
                Tables\Columns\TextColumn::make('title')->label('Projeto')->searchable()->limit(80)->wrap()
                    ->description(fn (SystematicReview $r) => $r->typeLabel()),
                Tables\Columns\TextColumn::make('status')->label('Situação')->badge()
                    ->formatStateUsing(fn (SystematicReview $r) => $r->statusLabel() . ($r->status === SystematicReview::STATUS_SCREENING ? ' · ' . $r->stageLabel() : ''))
                    ->color(fn (string $state) => self::statusColor($state)),
                Tables\Columns\TextColumn::make('records_count')->counts('records')->label('Registros'),
                Tables\Columns\TextColumn::make('screeners_count')->counts('screeners')->label('Triadores'),
                Tables\Columns\TextColumn::make('registration_id')->label('Registro')->placeholder('— sem registro')->limit(30)
                    ->description(fn (SystematicReview $r) => $r->protocol_code ? $r->protocol_code . ($r->isFrozen() ? ' · 🔒 congelado' : ' · não congelado') : null),
                Tables\Columns\TextColumn::make('phase')->label('Fase')
                    ->state(fn (SystematicReview $r) => app(\App\Services\SystematicReviewExtractionService::class)->phase($r)['code'])
                    ->description(fn (SystematicReview $r) => app(\App\Services\SystematicReviewExtractionService::class)->phase($r)['label'])
                    ->badge()->color('primary'),
                Tables\Columns\TextColumn::make('osf_pending')->label('OSF')
                    ->state(function (SystematicReview $r) {
                        $n = count(app(\App\Services\SystematicReviewProtocolService::class)->osfPending($r));

                        return $n ? "📤 {$n} pendente(s)" : '✓ em dia';
                    })
                    ->badge()
                    ->color(fn (string $state) => str_starts_with($state, '📤') ? 'warning' : 'success')
                    ->tooltip(fn (SystematicReview $r) => collect(app(\App\Services\SystematicReviewProtocolService::class)->osfPending($r))->pluck('title')->implode(' · ') ?: null),
                Tables\Columns\TextColumn::make('updated_at')->label('Atualizado')->since()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('Situação')->options(SystematicReview::STATUS_LABELS),
                // Os treinos da triagem ficam fora da lista por padrão (menu próprio "Treino da triagem").
                Tables\Filters\TernaryFilter::make('is_training')->label('Treinos da triagem')
                    ->placeholder('Todos')->trueLabel('Só os treinos')->falseLabel('Só os projetos reais')->default(false),
                Tables\Filters\Filter::make('ready_to_freeze')->label('Pronto para congelar (dupla com os 2 termos)')
                    ->query(fn ($query) => $query->whereNull('protocol_frozen_at')
                        ->whereHas('screeners', fn ($q) => $q->whereNull('removed_at')->whereNotNull('terms_accepted_at'), '>=', 2)),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Abrir'),
                Tables\Actions\EditAction::make(),
            ]);
    }

    /** "PET-01" → "P01", "PAT-05" → "P15"; qualquer outra busca vale como o próprio código de protocolo. */
    public static function protocolCodeFromSearch(string $search): string
    {
        $search = strtoupper(trim($search));
        if ($code = SystematicReview::protocolCodeFor($search)) {
            return $code;
        }

        return $search;
    }

    /** Dupla completa, os dois termos assinados e o protocolo ainda aberto. */
    public static function readyToFreeze(SystematicReview $r): bool
    {
        $people = $r->screeners;

        return ! $r->isFrozen() && $people->count() >= 2 && $people->every(fn ($s) => $s->terms_accepted_at !== null);
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            SystematicReview::STATUS_SCREENING => 'info',
            SystematicReview::STATUS_CONFLICTS => 'warning',
            SystematicReview::STATUS_SCREENING_DONE => 'success',
            SystematicReview::STATUS_ARCHIVED => 'gray',
            default => 'primary',
        };
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            // ViewEntry, não TextEntry->html(): este limita a largura ao conteúdo e as caixas ficavam pela metade.
            Infolists\Components\ViewEntry::make('banner')
                ->hiddenLabel()
                ->columnSpanFull()
                ->view('filament.infolists.html-block')
                ->state(fn (SystematicReview $record) => '<div style="font-size:1.05rem;font-weight:600;line-height:1.45;margin-bottom:.75rem">' . e($record->title) . '</div>'
                    . self::banner($record)->toHtml()),

            Infolists\Components\Section::make('Guia do professor: o que fazer agora')
                ->icon('heroicon-o-light-bulb')
                ->collapsible()
                ->schema([
                    Infolists\Components\TextEntry::make('professor_guide')
                        ->hiddenLabel()
                        ->state(fn (SystematicReview $record) => self::professorGuideHtml($record))
                        ->html(),
                ]),

            Infolists\Components\Section::make('Protocolo e OSF')
                ->description('O protocolo registrado manda. O sistema avisa (aqui, por e-mail e no Telegram) sempre que houver algo para exportar e subir no OSF.')
                ->collapsible()
                ->schema([
                    Infolists\Components\TextEntry::make('osf')
                        ->hiddenLabel()
                        ->state(fn (SystematicReview $record) => self::osfHtml($record))
                        ->html(),
                ]),

            Infolists\Components\Grid::make(2)->schema([
                Infolists\Components\Section::make('Fluxograma PRISMA 2020')
                    ->columnSpan(1)
                    ->schema([
                        Infolists\Components\TextEntry::make('prisma')
                            ->hiddenLabel()
                            ->state(fn (SystematicReview $record) => self::prismaHtml($record))
                            ->html(),
                    ]),
                Infolists\Components\Section::make('Confiabilidade entre triadores')
                    ->columnSpan(1)
                    ->schema([
                        Infolists\Components\TextEntry::make('reliability')
                            ->hiddenLabel()
                            ->state(fn (SystematicReview $record) => self::reliabilityHtml($record))
                            ->html(),
                    ]),
            ]),

            Infolists\Components\Section::make('Esteira do projeto (POP)')
                ->description('Marcado sozinho quando o sistema sabe; o resto você marca em "Atualizar esteira".')
                ->collapsible()
                ->schema([
                    Infolists\Components\TextEntry::make('pipeline')
                        ->hiddenLabel()
                        ->state(fn (SystematicReview $record) => self::pipelineHtml($record))
                        ->html(),
                ]),

            Infolists\Components\Section::make('Texto de Métodos (para o manuscrito)')
                ->description('Gerado com os números reais da triagem. Clique no texto para selecionar.')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Infolists\Components\TextEntry::make('methods')
                        ->hiddenLabel()
                        ->state(fn (SystematicReview $record) => new HtmlString('<div onclick="window.getSelection().selectAllChildren(this)" style="font-family:Georgia,serif;line-height:1.7;cursor:text">'
                            . e(app(SystematicReviewService::class)->methodsText($record)) . '</div>'))
                        ->html(),
                ]),

            Infolists\Components\Section::make('Protocolo')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Infolists\Components\TextEntry::make('protocol_view')
                        ->hiddenLabel()
                        ->state(fn (SystematicReview $record) => new HtmlString('<pre style="white-space:pre-wrap;font-size:.8125rem;line-height:1.6">'
                            . e(app(SystematicReviewService::class)->protocolMarkdown($record)) . '</pre>'))
                        ->html(),
                ]),

            Infolists\Components\Section::make('Registro de acompanhamento')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Infolists\Components\TextEntry::make('history_log')
                        ->hiddenLabel()
                        ->state(fn (SystematicReview $record) => new HtmlString('<div style="font-size:.8125rem;line-height:1.7">' . (collect($record->history ?? [])
                            ->reverse()
                            ->map(fn (array $e) => '<div><span style="opacity:.6">' . \Illuminate\Support\Carbon::parse($e['at'])->format('d/m/Y H:i') . '</span> — <strong>' . e($e['event']) . '</strong>'
                                . (!empty($e['details']) ? ' <span style="opacity:.75">(' . nl2br(e($e['details'])) . ')</span>' : '')
                                . (!empty($e['actor']) ? ' <span style="opacity:.6">· por ' . e($e['actor']) . '</span>' : '') . '</div>')
                            ->implode('') ?: 'Nada ainda.') . '</div>'))
                        ->html(),
                ]),
        ]);
    }

    protected static function box(string $html, string $color = '13,148,136'): string
    {
        return "<div style=\"font-size:.9rem;line-height:1.6;background:rgba({$color},.08);border:1px solid rgba({$color},.35);border-radius:.75rem;padding:.75rem 1rem\">{$html}</div>";
    }

    public static function banner(SystematicReview $record): HtmlString
    {
        $warnings = $record->journalWarnings();
        $html = self::bannerMain($record)->toHtml();
        if ($warnings) {
            $html .= '<div style="margin-top:.5rem">' . self::box('<strong>Exigências das revistas:</strong><ul style="margin:.25rem 0 0 1.25rem;list-style:disc">'
                . collect($warnings)->map(fn ($w) => '<li>' . e($w) . '</li>')->implode('') . '</ul>', '217,119,6') . '</div>';
        }

        return new HtmlString($html);
    }

    protected static function bannerMain(SystematicReview $record): HtmlString
    {
        $service = app(SystematicReviewService::class);

        if ($record->status === SystematicReview::STATUS_PROTOCOL) {
            $problems = $service->startProblems($record);

            return new HtmlString(self::box($problems
                ? '<strong>Para liberar a triagem falta:</strong><ul style="margin:.25rem 0 0 1.25rem;list-style:disc">' . collect($problems)->map(fn ($p) => '<li>' . e($p) . '</li>')->implode('') . '</ul>'
                : '<strong>Tudo pronto.</strong> Clique em "Liberar triagem" — os triadores são avisados por e-mail/Telegram.', $problems ? '217,119,6' : '13,148,136'));
        }

        if ($record->status === SystematicReview::STATUS_SCREENING) {
            $lines = $record->activeScreeners()->get()->filter(fn ($s) => $s->participatesIn($record->stage))->map(function ($s) use ($service, $record) {
                $p = $service->progress($s);
                $pct = $p['total'] ? round(100 * $p['done'] / $p['total']) : 0;
                $state = !$s->user_id ? '⏳ falta criar a conta' : (!$s->hasSignedTerms() ? '✍ falta assinar o termo' : ($s->hasFinished($record->stage) ? '✅ concluiu' : "{$p['done']}/{$p['total']} ({$pct}%)"));
                $pace = $service->pace($s);
                $paceText = $pace['median'] !== null ? ' · ritmo ' . SystematicReviewService::fmt($pace['median'], 0) . ' s/trabalho' . ($pace['fast'] ? ' ⚠ rápido demais — confira se está lendo' : '') : '';

                return '<li><strong>' . e($s->name) . '</strong> — ' . e($state . $paceText) . '</li>';
            })->implode('');

            return new HtmlString(self::box('<strong>Em triagem — ' . e($record->stageLabel()) . ($record->inPilot() ? " (PILOTO: {$record->pilot_size} registros)" : '') . '</strong>'
                . '<ul style="margin:.25rem 0 0 1.25rem;list-style:disc">' . $lines . '</ul>'
                . ($record->inPilot() ? '<div style="margin-top:.4rem">Quando os dois terminarem o piloto, confira o kappa e clique em <strong>"Liberar triagem completa"</strong>.</div>' : ''), '37,99,235'));
        }

        if ($record->status === SystematicReview::STATUS_CONFLICTS) {
            $n = $service->conflictsQuery($record)->count();

            return new HtmlString(self::box("<strong>Sua vez:</strong> {$n} conflito(s) de " . e($record->stageLabel())
                . ' para desempatar na aba <strong>Registros → Conflitos</strong>. Depois clique em <strong>"Encerrar etapa"</strong>.', '217,119,6'));
        }

        if ($record->status === SystematicReview::STATUS_SCREENING_DONE) {
            return new HtmlString(self::box('<strong>Triagem concluída.</strong> Baixe a lista de incluídos e o relatório, siga para extração/RoB e crie o processo de redação do artigo ("Criar artigo").'));
        }

        return new HtmlString(self::box('Projeto arquivado.', '100,116,139'));
    }

    public static function prismaHtml(SystematicReview $record): HtmlString
    {
        $p = app(SystematicReviewService::class)->prisma($record);
        $row = fn (string $label, $value, bool $indent = false) => '<tr><td style="padding:.15rem .5rem ' . ($indent ? '.15rem 1.25rem' : '.15rem 0') . '">' . $label . '</td><td style="text-align:right;font-weight:600">' . ($value ?? '—') . '</td></tr>';

        $html = '<table style="width:100%;font-size:.85rem">';
        $html .= '<tr><td colspan="2" style="font-weight:700;padding-top:.25rem">Identificação</td></tr>';
        foreach ($p['sources'] as $name => $n) {
            $html .= $row(e($name), $n, true);
        }
        $html .= $row('Registros identificados', $p['identified'] ?: '—');
        $html .= $row('Duplicatas removidas', $p['duplicates']);
        $html .= '<tr><td colspan="2" style="font-weight:700;padding-top:.5rem">Triagem</td></tr>';
        $html .= $row('Registros triados (título/resumo)', $p['screened']);
        $html .= $row('Excluídos na triagem', $p['ta_excluded']);
        $html .= $row('Buscados para texto completo', $p['sought']);
        $html .= $row('Texto completo não obtido', $p['not_retrieved']);
        $html .= $row('Avaliados para elegibilidade', $p['assessed']);
        foreach ($p['ft_reasons'] as $reason => $n) {
            $html .= $row('Excluído: ' . e($reason), $n, true);
        }
        $html .= '<tr><td colspan="2" style="font-weight:700;padding-top:.5rem">Incluídos</td></tr>';
        $html .= $row('Estudos incluídos na revisão', $p['included']);

        return new HtmlString($html . '</table>');
    }

    public static function reliabilityHtml(SystematicReview $record): HtmlString
    {
        $service = app(SystematicReviewService::class);
        $service->ensurePilotRoundsConsistent($record);
        $html = '';

        foreach (SystematicReview::STAGE_LABELS as $stage => $label) {
            if ($stage === SystematicReview::STAGE_FT && $record->stage !== $stage && !$record->isStageClosed($stage)) {
                continue;
            }

            $inPilot = $stage === SystematicReview::STAGE_TA && !$record->is_training && $record->inPilot();
            $hasClosedPilot = $stage === SystematicReview::STAGE_TA && !$record->is_training && $record->pilot_released_at && !empty($record->pilot_rounds);

            // Bloco de rodadas fechadas após liberação da triagem completa
            if ($hasClosedPilot) {
                $html .= '<div style="margin-bottom:.6rem;padding:.5rem .75rem;border-radius:.5rem;background:#f8fafc;border:1px solid #cbd5e1">'
                    . '<div style="font-weight:700;font-size:.85rem;color:#1e293b;margin-bottom:.25rem">Calibração do Piloto (concluída)</div>';
                foreach ($record->pilot_rounds as $r) {
                    $badge = !empty($r['released'])
                        ? ' <span style="background:#059669;color:#fff;padding:.05rem .3rem;border-radius:.2rem;font-size:.7rem;font-weight:700">Aprovada para liberação</span>'
                        : ' <span style="background:#e2e8f0;color:#475569;padding:.05rem .3rem;border-radius:.2rem;font-size:.7rem">Alinhamento realizado</span>';
                    $html .= '<div style="font-size:.8rem;color:#334155;margin:.2rem 0">'
                        . '<strong>Rodada ' . (int) $r['round'] . '</strong> (' . (int) ($r['size'] ?? 0) . ' registros, n = ' . (int) ($r['n'] ?? 0) . '): '
                        . 'κ (3 cat.) = <strong>' . SystematicReviewService::fmt($r['kappa'] ?? null) . '</strong> · '
                        . 'κ binário = <strong>' . SystematicReviewService::fmt($r['kappa_binary'] ?? null) . '</strong> · '
                        . 'concordância = ' . (isset($r['agreement']) ? SystematicReviewService::fmt($r['agreement'] * 100, 1) . '%' : '—')
                        . $badge . '</div>';
                }
                $html .= '</div>';
            }

            // Rodada 2+ de calibração EM ANDAMENTO: mostra só a amostra nova
            $roundView = $inPilot && $record->pilotRound() > 1;
            $rel = $inPilot ? $service->pilotReliability($record) : $service->reliability($record, $stage);

            $stageTitle = e($label);
            if ($inPilot) {
                $stageTitle .= $roundView
                    ? ' — rodada ' . $record->pilotRound() . ' de calibração (só a amostra nova)'
                    : ' — piloto de calibração';
            } elseif ($hasClosedPilot) {
                $stageTitle .= ' — triagem completa (acumulado)';
            }

            $html .= '<div style="font-weight:700;margin-top:.25rem">' . $stageTitle . '</div>';

            if ($roundView) {
                foreach ($record->pilot_rounds ?? [] as $r) {
                    $html .= '<div style="font-size:.8rem;opacity:.75">Rodada ' . (int) $r['round'] . ' (já discutida): κ = '
                        . SystematicReviewService::fmt($r['kappa'] ?? null) . ' · κ binário ' . SystematicReviewService::fmt($r['kappa_binary'] ?? null)
                        . ' · n = ' . (int) ($r['n'] ?? 0) . '</div>';
                }
            }
            if (!$rel['pairs']) {
                $html .= '<div style="opacity:.7;font-size:.85rem">Precisa de 2 triadores para calcular.</div>';
            }
            foreach ($rel['pairs'] as $pair) {
                $k = $pair['kappa'];
                $kb = $pair['kappa_binary'];
                $kEff = max($k['kappa'] ?? -1.0, $kb['kappa'] ?? -1.0);
                $color = $kEff < 0 ? '100,116,139' : ($kEff >= 0.61 ? '22,163,74' : ($kEff >= 0.41 ? '217,119,6' : '220,38,38'));
                $osfBadge = ($kb['kappa'] !== null && $kb['kappa'] >= 0.60)
                    ? ' <span style="background:#059669;color:#fff;padding:.1rem .35rem;border-radius:.25rem;font-size:.72rem;font-weight:700">OSF Apto (κ binário ≥ 0,60)</span>'
                    : '';

                $html .= '<div style="font-size:.85rem;margin:.25rem 0;padding:.5rem .75rem;border-radius:.5rem;background:rgba(' . $color . ',.1);border-left:3px solid rgb(' . $color . ')">'
                    . '<strong>' . e($pair['a']) . ' × ' . e($pair['b']) . '</strong> · n = ' . (int) $pair['n'] . $osfBadge . '<br>'
                    . '• <strong>κ (3 categorias): ' . SystematicReviewService::fmt($k['kappa']) . '</strong>'
                    . ($k['ci_low'] !== null ? ' (IC 95% ' . SystematicReviewService::fmt($k['ci_low']) . ' a ' . SystematicReviewService::fmt($k['ci_high']) . ')' : '')
                    . ' — ' . SystematicReviewService::kappaLabel($k['kappa']) . '<br>'
                    . '• <strong>κ binário (OSF: incluir+talvez × excluir): ' . SystematicReviewService::fmt($kb['kappa']) . '</strong>'
                    . ($kb['ci_low'] !== null ? ' (IC 95% ' . SystematicReviewService::fmt($kb['ci_low']) . ' a ' . SystematicReviewService::fmt($kb['ci_high']) . ')' : '')
                    . ' — ' . SystematicReviewService::kappaLabel($kb['kappa']) . '<br>'
                    . '• Concordância bruta: ' . ($k['agreement'] !== null ? SystematicReviewService::fmt($k['agreement'] * 100, 1) . '%' : '—')
                    . '</div>';
            }
        }

        return new HtmlString($html . '<div style="font-size:.75rem;opacity:.7;margin-top:.4rem">Interpretação (Landis & Koch): ≥0,61 substancial; ≥0,81 quase perfeita. No OSF (item 11b), votos "talvez" avançam para texto completo junto com "incluir", portanto o κ binário reflete a real concordância de elegibilidade preliminar.</div>');
    }

    public static function pipelineHtml(SystematicReview $record): HtmlString
    {
        return new HtmlString('<div style="font-size:.8rem;opacity:.75;margin-bottom:.25rem">Diretriz de relato: <strong>' . e($record->reportingGuideline()) . '</strong></div>'
            . '<div style="font-size:.875rem;line-height:1.9">' . collect($record->pipeline())->map(function ($label, $key) use ($record) {
            $done = $record->checklist[$key] ?? null;

            // Caixas desenhadas em CSS: o emoji ⬜ saía lilás em algumas fontes.
            $box = $done
                ? '<span style="display:inline-flex;align-items:center;justify-content:center;width:1.05em;height:1.05em;border-radius:.25rem;background:#059669;color:#fff;font-size:.75em;font-weight:700;vertical-align:-.15em">✓</span>'
                : '<span style="display:inline-block;width:1.05em;height:1.05em;border-radius:.25rem;border:1.5px solid #94a3b8;vertical-align:-.15em"></span>';

            return '<div>' . $box . ' ' . e($label)
                . ($done ? ' <span style="opacity:.6">(' . \Illuminate\Support\Carbon::parse($done)->format('d/m/Y') . ')</span>' : '') . '</div>';
        })->implode('') . '</div>');
    }

    /** Guia do professor para a fase atual (pipeline, seções 4–8). */
    public static function professorGuideHtml(SystematicReview $record): string
    {
        $g = \App\Support\StageGuides::professor($record);
        $list = fn (array $items) => '<ul style="margin:.25rem 0 0 1.15rem;list-style:disc">' . collect($items)->map(fn ($i) => '<li style="margin:.15rem 0">' . e($i) . '</li>')->implode('') . '</ul>';

        return '<div style="font-size:.875rem;line-height:1.55">'
            . '<div style="font-weight:700;font-size:.95rem">' . e($g['phase']) . '</div>'
            . '<div style="margin-top:.5rem;font-weight:600">O que fazer agora</div>' . $list($g['todo'])
            . ($g['gate'] ? '<div style="margin-top:.6rem;padding:.5rem .75rem;border-radius:.5rem;background:rgba(13,148,136,.07);border:1px solid rgba(13,148,136,.3)"><strong>Portão para avançar:</strong> ' . e($g['gate']) . '</div>' : '')
            . ($g['checks'] ? '<div style="margin-top:.6rem;font-weight:600">Para conferir</div>' . $list($g['checks']) : '')
            . '<div style="margin-top:.6rem;opacity:.7">Regras de viés que valem sempre: só você desempata, e só depois que os dois concluírem; não mude critérios ou desfechos depois de ver dados (use desvio/emenda); não mostre aos alunos o conjunto de validação nem os votos da dupla.</div>'
            . '</div>';
    }

    /** Painel do protocolo/OSF: situação (P0–P2), pendências de exportação e desvios. */
    public static function osfHtml(SystematicReview $record): string
    {
        $protocol = app(\App\Services\SystematicReviewProtocolService::class);
        $row = fn (string $label, string $value) => '<tr><td style="padding:.2rem .6rem .2rem 0;opacity:.7;white-space:nowrap;vertical-align:top">' . e($label) . '</td><td style="padding:.2rem 0">' . $value . '</td></tr>';
        $ok = fn (string $t) => '<span style="color:#059669;font-weight:600">✔ ' . e($t) . '</span>';
        $no = fn (string $t) => '<span style="color:#b45309;font-weight:600">● ' . e($t) . '</span>';

        $html = '<table style="font-size:.875rem;border-collapse:collapse">'
            . $row('Protocolo', $record->pipeline
                ? e(($record->protocol_code ?: '') . ' · versão ' . ($record->protocol_version ?: '1.0') . ' · família ' . ($record->family ?: '—') . ($record->folder ? ' · pasta ' . $record->folder : ''))
                : '<span style="opacity:.7">sem pipeline JSON importado (Protocolo e OSF → Importar protocolo)</span>')
            . $row('Congelado (P1)', $record->isFrozen()
                ? $ok($record->protocol_frozen_at->format('d/m/Y H:i')) . '<br><code style="font-size:.75rem">SHA-256 ' . e($record->protocol_sha256) . '</code>'
                    . ($record->protocol_pdf_sha256 ? '<br><code style="font-size:.75rem">PDF ' . e($record->protocol_pdf_sha256) . '</code>' : '')
                : $no('não congelado'))
            . $row('Registro OSF (P2)', filled($record->registration_id)
                ? $ok($record->registration_id) . ($record->osf_project_url ? ' · <a href="' . e($record->osf_project_url) . '" target="_blank" rel="noopener" style="color:#0d9488">projeto no OSF ↗</a>' : '')
                    . ($record->registered_at ? ' · ' . $record->registered_at->format('d/m/Y') : '')
                    . ' · ' . ($record->embargo_until ? 'embargo até ' . $record->embargo_until->format('d/m/Y') : 'público')
                : $no('sem DOI'))
            . $row('Conjunto de validação', count($record->validationSet()) ? e(count($record->validationSet()) . ' estudo(s) que as buscas devem recuperar') : '—')
            . '</table>';

        $steps = $protocol->osfPending($record);
        $html .= '<div style="margin-top:.8rem;font-weight:600">' . ($steps ? '📤 Pendências no OSF' : '✅ Nada pendente no OSF agora') . '</div>';
        if ($steps) {
            $html .= '<ol style="margin:.3rem 0 0 1.2rem;font-size:.875rem;list-style:decimal">';
            foreach ($steps as $s) {
                $html .= '<li style="margin-bottom:.35rem"><strong>' . e($s['title']) . '</strong>'
                    . ($s['component'] !== '—' ? ' → componente <code>' . e($s['component']) . '</code>' : '')
                    . ($s['files'] ? '<br><span style="opacity:.75">Arquivos: ' . e(implode(', ', $s['files'])) . '</span>' : '')
                    . '<br><span style="opacity:.75">Onde: ' . e($s['action']) . '</span></li>';
            }
            $html .= '</ol>';
        }

        $deviations = $record->deviations()->get();
        if ($deviations->isNotEmpty()) {
            $html .= '<div style="margin-top:.8rem;font-weight:600">Desvios e emendas (' . $deviations->count() . ')</div><ul style="margin:.3rem 0 0 1.2rem;font-size:.85rem;list-style:disc">';
            foreach ($deviations as $d) {
                $html .= '<li><strong>' . e($d->typeLabel()) . '</strong> · ' . e($d->section) . ' · ' . $d->created_at->format('d/m/Y')
                    . ($d->protocol_version_after ? ' · nova versão ' . e($d->protocol_version_after) : '') . '<br><span style="opacity:.8">' . e($d->description) . '</span></li>';
            }
            $html .= '</ul>';
        }

        return $html;
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\RecordsRelationManager::class,
            RelationManagers\ScreenersRelationManager::class,
            RelationManagers\MessagesRelationManager::class,
            RelationManagers\StudiesRelationManager::class,
            RelationManagers\AuthorContactsRelationManager::class,
            RelationManagers\AnalysisRunsRelationManager::class,
            RelationManagers\GradeRowsRelationManager::class,
            RelationManagers\MeetingsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSystematicReviews::route('/'),
            'create' => Pages\CreateSystematicReview::route('/create'),
            'view' => Pages\ViewSystematicReview::route('/{record}'),
            'edit' => Pages\EditSystematicReview::route('/{record}/edit'),
        ];
    }
}
