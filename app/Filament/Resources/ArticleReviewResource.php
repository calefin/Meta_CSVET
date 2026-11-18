<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArticleReviewResource\Pages;
use App\Models\ArticleReview;
use App\Models\ArticleReviewer;
use App\Services\ArticleReviewService;
use App\Services\DocxInspector;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class ArticleReviewResource extends Resource
{
    protected static ?string $model = ArticleReview::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-magnifying-glass';

    protected static ?string $navigationLabel = 'Revisão de Artigos/Livros';

    protected static ?string $modelLabel = 'Processo de Revisão';

    protected static ?string $pluralModelLabel = 'Processos de Revisão';

    protected static ?string $navigationGroup = 'Pesquisa & Publicações';

    protected static ?string $slug = 'revisao-de-artigos';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    /**
     * Badge do menu: processos que precisam de você agora — voltaram para
     * você, estão com prazo vencido ou a pessoa da vez não abriu o link em
     * 2 dias. (Os lembretes continuam manuais.)
     *
     * @return array{done: int, overdue: int, unopened: int}
     */
    public static function attentionCounts(): array
    {
        $active = ArticleReviewer::where('status', ArticleReviewer::STATUS_IN_REVIEW)
            ->whereHas('articleReview', fn ($q) => $q->where('status', ArticleReview::STATUS_IN_REVIEW));

        return [
            'done' => ArticleReview::where('status', ArticleReview::STATUS_REVIEWS_DONE)->count(),
            'overdue' => (clone $active)->where('due_at', '<', now())->count(),
            'unopened' => (clone $active)->whereNull('first_opened_at')->where('sent_at', '<', now()->subDays(2))
                ->where(fn ($q) => $q->whereNull('due_at')->orWhere('due_at', '>=', now()))->count(),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $total = array_sum(self::attentionCounts());

        return $total > 0 ? (string) $total : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return self::attentionCounts()['overdue'] > 0 ? 'danger' : 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        $c = self::attentionCounts();

        return collect([
            $c['done'] ? "{$c['done']} voltou(aram) para você" : null,
            $c['overdue'] ? "{$c['overdue']} com prazo vencido" : null,
            $c['unopened'] ? "{$c['unopened']} sem abrir o link há 2+ dias" : null,
        ])->filter()->implode(' · ') ?: null;
    }

    public static function pipelineHtml(ArticleReview $record): HtmlString
    {
        $def = \App\Support\ArticleTypes::get($record->article_type);
        $url = \App\Support\ArticleTypes::guidelineUrl($record->article_type);
        $m = $record->manuscriptMetrics();
        [$maxWords, $maxAbstract, $maxRefs, $maxFigs] = $def['limits'];

        $cell = function (?int $value, int $max, string $label) {
            if ($value === null) {
                return "<td>{$label}</td><td>—</td><td>{$max}</td><td style=\"opacity:.6\">não identificado</td>";
            }
            $ok = $max === 0 ? $value === 0 : $value <= $max;
            $near = $ok && $max > 0 && $value > 0.9 * $max;

            return "<td>{$label}</td><td style=\"font-weight:700\">≈ {$value}</td><td>{$max}</td><td>" . ($ok ? ($near ? '🟡 perto do limite' : '🟢 ok') : '🔴 acima') . '</td>';
        };

        $html = '<div style="font-size:.875rem;line-height:1.6">'
            . '<div><strong>Diretriz de relato:</strong> ' . e($def['guideline'])
            . ($url ? ' · <a href="' . e($url) . '" target="_blank" rel="noopener" style="color:#0d9488;font-weight:600">checklist na EQUATOR ↗</a>' : '')
            . ' · <strong>Resumo:</strong> ' . e($def['abstract']) . '</div>';

        if ($m) {
            $html .= '<table style="width:100%;margin-top:.5rem;font-size:.85rem"><tr style="text-align:left;opacity:.7"><th>Medida (v' . $m['version'] . ')</th><th>Atual</th><th>Limite</th><th></th></tr>'
                . '<tr>' . $cell($m['text_words'], $maxWords, 'Palavras do texto') . '</tr>'
                . '<tr>' . $cell($m['abstract_words'], $maxAbstract, 'Palavras do resumo') . '</tr>'
                . '<tr>' . $cell($m['references'], $maxRefs, 'Referências') . '</tr>'
                . '<tr>' . $cell($m['figures'] + $m['tables'], $maxFigs, 'Figuras + tabelas') . '</tr></table>'
                . '<div style="font-size:.75rem;opacity:.6">Estimativa a partir da versão atual (texto sem resumo, referências e legendas; depende dos títulos "Abstract" e "References").</div>';
        }

        $status = \App\Support\ArticleTypes::status($record);
        $pending = collect($status)->where('state', 'pending')->count();
        $html .= '<div style="margin-top:.75rem;font-weight:700">Checklist: ' . (count($status) - $pending) . '/' . count($status) . ' resolvidos'
            . ($pending ? ' — <span style="color:#b45309">' . $pending . ' pendente(s): o envio à revista fica bloqueado</span>' : ' ✅') . '</div>';
        foreach ($status as $item) {
            $icon = ['done' => '✅', 'auto' => '🤖', 'na' => '➖', 'pending' => '⬜'][$item['state']];
            $html .= '<div>' . $icon . ' ' . e($item['label']) . ($item['evidence'] && $item['state'] === 'auto' ? ' <span style="opacity:.6">(' . e($item['evidence']) . ')</span>' : '') . '</div>';
        }

        return new HtmlString($html . '</div>');
    }

    /**
     * Triadores (com termo assinado) viram participantes do processo do artigo.
     */
    public static function participantsFromSystematicReview(\App\Models\SystematicReview $sr): array
    {
        return $sr->activeScreeners()->get()
            ->mapWithKeys(fn ($s) => [(string) \Illuminate\Support\Str::uuid() => [
                'name' => $s->user?->getRawOriginal('name') ?: $s->name,
                'email' => $s->email,
                'phone' => $s->user?->student?->phone,
                'institution' => $s->user?->student?->institution,
            ]])
            ->all();
    }

    public static function participantFields(bool $withOrder = true): array
    {
        return array_values(array_filter([
            // Atalho: puxa nome, e-mail, telefone e instituição de um
            // orientando já cadastrado (e vincula a conta dele pelo e-mail).
            Forms\Components\Select::make('student_pick')
                ->label('Puxar de um orientando (opcional)')
                ->placeholder('Digite o nome, e-mail ou RGM do aluno…')
                ->searchable()
                ->dehydrated(false)
                ->live()
                ->getSearchResultsUsing(fn (string $search) => \App\Models\User::query()
                    ->whereHas('student')
                    ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('student', fn ($s) => $s->where('registration', 'like', "%{$search}%")))
                    ->orderBy('name')
                    ->limit(20)
                    ->get()
                    ->mapWithKeys(fn ($u) => [$u->id => $u->name . ' — ' . $u->email])
                    ->all())
                ->getOptionLabelUsing(fn ($value) => \App\Models\User::find($value)?->name)
                ->afterStateUpdated(function (Forms\Set $set, $state) {
                    $user = $state ? \App\Models\User::with('student')->find($state) : null;
                    if (!$user) {
                        return;
                    }
                    $set('name', $user->name);
                    $set('email', $user->email);
                    $set('phone', $user->student?->phone);
                    $set('institution', $user->student?->institution);
                })
                ->columnSpanFull(),
            Forms\Components\Hidden::make('institution'),
            Forms\Components\TextInput::make('name')
                ->label('Nome')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('email')
                ->label('E-mail')
                ->helperText('Opcional — sem e-mail, copie o link e envie por WhatsApp.')
                ->email()
                ->maxLength(255),
            Forms\Components\TextInput::make('phone')
                ->label('Telefone / WhatsApp')
                ->tel()
                ->maxLength(40),
            Forms\Components\TextInput::make('review_days')
                ->label('Prazo (dias)')
                ->placeholder('padrão do processo')
                ->numeric()
                ->integer()
                ->minValue(1)
                ->maxValue(90),
            $withOrder ? Forms\Components\TextInput::make('author_order')
                ->label('Ordem de autoria')
                ->helperText('Independe da ordem de revisão.')
                ->numeric()
                ->integer()
                ->minValue(1)
                ->maxValue(99) : null,
        ]));
    }

    public static function form(Form $form): Form
    {
        // Registro recém-criado ainda não tem "status" no objeto (o default vem do
        // banco): tratar vazio como rascunho, senão a lista de participantes
        // ficava "travada" e não era salva no cadastro.
        $lockedAfterStart = fn (?ArticleReview $record) => $record !== null
            && ($record->status ?: ArticleReview::STATUS_DRAFT) !== ArticleReview::STATUS_DRAFT;

        return $form->schema([
            Forms\Components\Section::make('Trabalho')
                ->columns(2)
                ->schema([
                    Forms\Components\Placeholder::make('how_it_works')
                        ->hiddenLabel()
                        ->columnSpanFull()
                        ->content(new HtmlString(
                            '<div style="font-size:.875rem;line-height:1.6;background:rgba(13,148,136,.07);border:1px solid rgba(13,148,136,.25);border-radius:.75rem;padding:.75rem 1rem">'
                            . '<strong>Como funciona:</strong> preencha só o <strong>título</strong>, envie o <strong>.docx</strong> e liste os <strong>participantes na ordem de revisão</strong> '
                            . '(só o nome já basta — e-mail e telefone podem vir depois pelo link de cadastro). O resto já vem preenchido. '
                            . 'Depois de salvar, confira e clique em <strong>"Enviar despacho"</strong>: o 1º recebe o arquivo; os demais recebem o link para a ficha. '
                            . 'Ao final o trabalho volta para você registrar o envio à revista.</div>'
                        )),
                    Forms\Components\Radio::make('development_model')
                        ->label('Modelo de desenvolvimento')
                        ->options([
                            'review_only' => 'Só revisão entre coautores (o manuscrito já existe)',
                            'complete' => 'Desenvolvimento completo — revisão sistemática / metanálise feita no sistema',
                        ])
                        ->default('review_only')
                        ->dehydrated(false)
                        ->live()
                        ->afterStateHydrated(function (Forms\Components\Radio $component, ?ArticleReview $record) {
                            if ($record?->systematic_review_id) {
                                $component->state('complete');
                            }
                        })
                        ->columnSpanFull(),
                    Forms\Components\Select::make('systematic_review_id')
                        ->label('Projeto de revisão sistemática')
                        ->relationship('systematicReview', 'title')
                        ->searchable()
                        ->preload()
                        ->visible(fn (Forms\Get $get) => $get('development_model') === 'complete')
                        ->required(fn (Forms\Get $get) => $get('development_model') === 'complete')
                        ->helperText('Os triadores do projeto entram como participantes (dá para ajustar), e o registro/PRISMA/kappa ficam à mão na página do processo.')
                        ->live()
                        ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                            $sr = $state ? \App\Models\SystematicReview::find($state) : null;
                            if (!$sr) {
                                return;
                            }
                            if (blank($get('article_type'))) {
                                $set('article_type', \App\Support\ArticleTypes::forSystematicReview($sr));
                            }
                            if (blank($get('title'))) {
                                $set('title', $sr->protocolValue('title_en') ?: $sr->title);
                                $set('short_name', self::suggestShortName($sr->protocolValue('title_en') ?: $sr->title));
                            }
                            $current = collect($get('reviewers') ?? [])->filter(fn ($p) => filled($p['name'] ?? null));
                            if ($current->isEmpty()) {
                                $set('reviewers', self::participantsFromSystematicReview($sr));
                            }
                        })
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('title')
                        ->label('Título do trabalho')
                        ->placeholder('Cole aqui o título completo, como está no manuscrito')
                        ->required()
                        ->maxLength(500)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, ?string $state) {
                            if (blank($get('short_name')) && filled($state)) {
                                $set('short_name', self::suggestShortName($state));
                            }
                        })
                        ->columnSpanFull(),
                    Forms\Components\Select::make('work_type')
                        ->label('Tipo')
                        ->options(ArticleReview::WORK_TYPES)
                        ->default('article')
                        ->required()
                        ->live()
                        ->native(false),
                    Forms\Components\Select::make('article_type')
                        ->label('Tipo de artigo (pipeline de submissão)')
                        ->options(\App\Support\ArticleTypes::options())
                        ->placeholder('Sem pipeline (opcional)')
                        ->helperText('Define a diretriz de relato (ARRIVE, STROBE-Vet, CARE, PRISMA…), os limites e o checklist que precisa estar completo para registrar o envio.')
                        ->visible(fn (Forms\Get $get) => in_array($get('work_type'), ['article', 'abstract', 'other'], true))
                        ->searchable()
                        ->native(false),
                    Forms\Components\TextInput::make('journal')
                        ->label('Revista / editora alvo')
                        ->placeholder('Opcional — pode definir só no envio final')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('short_name')
                        ->label('Nome curto (para o nome do arquivo)')
                        ->helperText('Gerado sozinho a partir do título; mude se quiser. Ex.: Imunologia_Repteis → Imunologia_Repteis_v2_rev-JS.docx')
                        ->maxLength(60),
                    Forms\Components\TextInput::make('default_review_days')
                        ->label('Prazo padrão por participante (dias)')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->maxValue(90)
                        ->default(7)
                        ->helperText('Cada pessoa tem esse prazo a partir de quando recebe o arquivo. Dá para mudar por pessoa e prorrogar depois.')
                        ->required(),
                    Forms\Components\FileUpload::make('original_file_path')
                        ->label('Arquivo do trabalho (.docx)')
                        ->required()
                        ->disk('local')
                        ->directory('article_reviews/originals')
                        ->visibility('private')
                        ->storeFileNamesIn('original_filename')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'application/vnd.oasis.opendocument.text',
                        ])
                        ->maxSize(ArticleReviewService::MAX_FILE_KB)
                        ->disabled($lockedAfterStart)
                        ->helperText(fn (?ArticleReview $record) => $lockedAfterStart($record)
                            ? 'O processo já começou: o original não pode mais ser trocado (use "Restaurar versão" se precisar).'
                            : 'Arquivos com macro são recusados.')
                        ->columnSpanFull(),
                    self::supplementaryUploadField('supplementary_files')
                        ->label('Documentos complementares (opcional)')
                        ->helperText('Figuras, tabelas, normas da revista, carta... Ficam disponíveis para quem estiver na vez. Dá para anexar mais depois, na página do processo.')
                        ->required(false)
                        ->visible(fn (?ArticleReview $record) => !$record?->isClosed())
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('instructions')
                        ->label('Instruções aos participantes (aparecem na página de cada um)')
                        ->helperText('Já vem com o texto padrão. Acrescente o que for específico deste trabalho (ex.: "foque na discussão", "confira a tabela 2").')
                        ->default(ArticleReview::DEFAULT_INSTRUCTIONS)
                        ->afterStateHydrated(function (Forms\Components\Textarea $component, ?string $state) {
                            if (blank($state)) {
                                $component->state(ArticleReview::DEFAULT_INSTRUCTIONS);
                            }
                        })
                        ->rows(7)
                        ->maxLength(3000)
                        ->columnSpanFull(),
                    Forms\Components\Toggle::make('notify_all')
                        ->label('Notificar o andamento a todos os participantes por e-mail')
                        ->default(true),
                    Forms\Components\Toggle::make('require_login')
                        ->label('Exigir senha (o link sozinho não basta)')
                        ->helperText('No 1º acesso a pessoa cria a senha; pode recuperar por e-mail.')
                        ->default(false),
                ]),

            Forms\Components\Section::make('Participantes (na ordem de REVISÃO)')
                ->description(fn (?ArticleReview $record) => $lockedAfterStart($record)
                    ? 'O processo já começou. Use as ações da página do processo para incluir, remover, reordenar ou trocar participantes.'
                    : 'O arquivo segue de um participante para o próximo; ao final volta para você. E-mail é opcional (dá para usar o link de cadastro).')
                ->schema([
                    Forms\Components\Repeater::make('reviewers')
                        ->helperText('Só o NOME é obrigatório. Arraste para mudar a ordem. Ordem de autoria é opcional (pode definir no fim).')
                        ->relationship()
                        ->orderColumn('position')
                        ->reorderable()
                        ->label('')
                        ->addActionLabel('Adicionar participante')
                        ->minItems(1)
                        ->defaultItems(1)
                        ->columns(5)
                        ->disabled($lockedAfterStart)
                        ->itemLabel(fn (array $state) => $state['name'] ?? null)
                        ->schema(self::participantFields()),
                ]),

            Forms\Components\Textarea::make('notes')
                ->label('Anotações privadas')
                ->rows(2)
                ->columnSpanFull(),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\TextEntry::make('turn_banner')
                ->hiddenLabel()
                ->columnSpanFull()
                ->state(fn (ArticleReview $record) => self::turnBanner($record))
                ->html(),

            Infolists\Components\Section::make('Trabalho')
                ->columns(4)
                ->collapsible()
                ->schema([
                    Infolists\Components\TextEntry::make('title')->label('Título')->columnSpanFull(),
                    Infolists\Components\TextEntry::make('work_type')->label('Tipo')->formatStateUsing(fn (ArticleReview $record) => $record->workTypeLabel()),
                    Infolists\Components\TextEntry::make('journal')->label('Revista/editora')->placeholder('—'),
                    Infolists\Components\TextEntry::make('systematicReview.title')
                        ->label('Revisão sistemática de origem')
                        ->state(fn (ArticleReview $record) => $record->systematicReview
                            ? $record->systematicReview->title . ($record->systematicReview->registrationLine() ? ' — ' . $record->systematicReview->registrationLine() : '')
                            : null)
                        ->url(fn (ArticleReview $record) => $record->systematic_review_id
                            ? \App\Filament\Resources\SystematicReviewResource::getUrl('view', ['record' => $record->systematic_review_id])
                            : null)
                        ->color('primary')
                        ->visible(fn (ArticleReview $record) => $record->systematic_review_id !== null)
                        ->columnSpanFull(),
                    Infolists\Components\TextEntry::make('status')
                        ->label('Situação')
                        ->badge()
                        ->formatStateUsing(fn (ArticleReview $record) => $record->statusLabel())
                        ->color(fn (string $state) => self::statusColor($state)),
                    Infolists\Components\TextEntry::make('flags')
                        ->label('Configurações')
                        ->state(fn (ArticleReview $record) => collect([
                            $record->require_login ? 'Exige senha' : 'Acesso por link',
                            $record->notify_all ? 'Andamento p/ todos' : 'Sem andamento p/ todos',
                            $record->signup_enabled ? 'Link de cadastro ATIVO' : null,
                        ])->filter()->implode(' · ')),
                    Infolists\Components\TextEntry::make('started_at')->label('Despacho em')->dateTime('d/m/Y H:i')->placeholder('—'),
                    Infolists\Components\TextEntry::make('reviews_completed_at')->label('Revisões concluídas em')->dateTime('d/m/Y H:i')->placeholder('—'),
                    Infolists\Components\TextEntry::make('submitted_at')->label('Enviado em')->date('d/m/Y')->placeholder('—'),
                    Infolists\Components\TextEntry::make('final_filename')
                        ->label('Versão final')
                        ->placeholder('—')
                        ->url(fn (ArticleReview $record) => $record->final_file_path
                            ? route('article-review.admin.download', ['articleReview' => $record, 'version' => 'final'])
                            : null)
                        ->color('primary'),
                    Infolists\Components\TextEntry::make('instructions')
                        ->label('Instruções aos participantes')
                        ->state(fn (ArticleReview $record) => new HtmlString(nl2br(e($record->instructions ?: ArticleReview::DEFAULT_INSTRUCTIONS))
                            . ($record->instructions ? '' : '<br><span style="font-size:.75rem;opacity:.6">(texto padrão — para personalizar, clique em Editar)</span>')))
                        ->html()
                        ->columnSpanFull(),
                    Infolists\Components\TextEntry::make('notes')->label('Anotações privadas')->placeholder('—')->columnSpanFull(),
                ]),

            Infolists\Components\Section::make('Pipeline de submissão')
                ->description(fn (ArticleReview $record) => (\App\Support\ArticleTypes::get($record->article_type)['label'] ?? '') . ' — limites de referência da NVAS; confira as normas da revista alvo.')
                ->visible(fn (ArticleReview $record) => \App\Support\ArticleTypes::get($record->article_type) !== null)
                ->collapsible()
                ->schema([
                    Infolists\Components\TextEntry::make('pipeline')
                        ->hiddenLabel()
                        ->state(fn (ArticleReview $record) => self::pipelineHtml($record))
                        ->html(),
                ]),

            Infolists\Components\Section::make('Participantes (ordem de revisão)')
                ->description('"Copiar link" serve para mandar por WhatsApp quando a pessoa não recebe o e-mail.')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('reviewers')
                        ->hiddenLabel()
                        ->contained(false)
                        ->schema([
                            Infolists\Components\ViewEntry::make('card')
                                ->hiddenLabel()
                                ->view('filament.article-review.participant-card'),
                            Infolists\Components\TextEntry::make('changes')
                                ->label('Alterações detectadas no arquivo')
                                ->state(fn (ArticleReviewer $record) => self::changesHtml($record))
                                ->html()
                                ->visible(fn (ArticleReviewer $record) => !empty($record->forensic['changes'])),
                            Infolists\Components\TextEntry::make('comments')
                                ->label('Comentários')
                                ->state(fn (ArticleReviewer $record) => new HtmlString(nl2br(e($record->comments))))
                                ->html()
                                ->visible(fn (ArticleReviewer $record) => filled($record->comments)),
                            Infolists\Components\TextEntry::make('suggestions')
                                ->label('Sugestões')
                                ->state(fn (ArticleReviewer $record) => new HtmlString(nl2br(e($record->suggestions))))
                                ->html()
                                ->visible(fn (ArticleReviewer $record) => filled($record->suggestions)),
                            Infolists\Components\TextEntry::make('participation')
                                ->hiddenLabel()
                                ->state(fn (ArticleReviewer $record) => $record->participation_accepted_at
                                    ? 'Declaração de participação aceita em ' . $record->participation_accepted_at->format('d/m/Y H:i')
                                        . ' · IP ' . ($record->forensic['ip'] ?? '—')
                                        . ' · SHA-256 ' . ($record->forensic['sha256'] ?? '—')
                                    : null)
                                ->size('xs')
                                ->color('gray')
                                ->visible(fn (ArticleReviewer $record) => $record->participation_accepted_at !== null),
                            Infolists\Components\Actions::make(self::participantActions())
                                ->extraAttributes(['style' => 'margin-top:-.5rem;padding-bottom:1.25rem;border-bottom:1px solid rgba(148,163,184,.3)']),
                        ]),
                ]),

            Infolists\Components\Section::make('Versões e documentos')
                ->description('Todas as versões ficam guardadas. Use "Restaurar versão" se alguém cometer um erro.')
                ->collapsible()
                ->schema([
                    Infolists\Components\TextEntry::make('versions_list')
                        ->label('Versões')
                        ->state(fn (ArticleReview $record) => self::versionsHtml($record))
                        ->html(),
                    Infolists\Components\TextEntry::make('files_list')
                        ->label('Documentos complementares')
                        ->state(fn (ArticleReview $record) => self::filesHtml($record))
                        ->html(),
                ]),

            Infolists\Components\Section::make('Registro de acompanhamento')
                ->description('Tudo o que aconteceu, do mais recente ao mais antigo. Itens com 👁 também aparecem para os participantes.')
                ->collapsible()
                ->schema([
                    Infolists\Components\TextEntry::make('history_log')
                        ->hiddenLabel()
                        ->state(fn (ArticleReview $record) => new HtmlString('<div style="font-size:.8125rem;line-height:1.7">' . (collect($record->history ?? [])
                            ->reverse()
                            ->map(fn (array $entry) => '<div><span style="opacity:.6">' . \Illuminate\Support\Carbon::parse($entry['at'])->format('d/m/Y H:i') . '</span> — '
                                . (!empty($entry['public']) ? '👁 ' : '') . '<strong>' . e($entry['event']) . '</strong>'
                                . (!empty($entry['details']) ? ' <span style="opacity:.75">(' . e($entry['details']) . ')</span>' : '')
                                . (!empty($entry['actor']) ? ' <span style="opacity:.6">· por ' . e($entry['actor']) . '</span>' : '') . '</div>')
                            ->implode('') ?: 'Nenhum evento registrado ainda.') . '</div>'))
                        ->html(),
                ]),
        ]);
    }

    /**
     * Ações por participante (dentro da lista do processo).
     */
    protected static function participantActions(): array
    {
        return [
            Infolists\Components\Actions\Action::make('lembrete')
                ->label(fn (ArticleReviewer $record) => $record->status === ArticleReviewer::STATUS_IN_REVIEW ? 'Enviar lembrete' : 'Enviar convite/aviso')
                ->icon('heroicon-o-bell-alert')
                ->color('warning')
                ->size('sm')
                ->visible(fn (ArticleReviewer $record) => self::canRemind($record) && filled($record->email))
                ->modalHeading(fn (ArticleReviewer $record) => 'Enviar lembrete a ' . $record->name)
                ->modalDescription(fn (ArticleReviewer $record) => $record->status === ArticleReviewer::STATUS_IN_REVIEW
                    ? 'Vai com o prazo, o link de acesso e a sua mensagem, se houver.'
                    : 'Ainda não é a vez dele(a): vai o link para preencher a ficha e acompanhar (sem download).')
                ->modalSubmitActionLabel('Enviar')
                ->form([
                    Forms\Components\Textarea::make('message')->label('Mensagem (opcional)')->rows(3)->maxLength(2000),
                ])
                ->action(function (ArticleReviewer $record, array $data) {
                    try {
                        $service = app(ArticleReviewService::class);
                        $record->status === ArticleReviewer::STATUS_IN_REVIEW
                            ? $service->sendReminder($record, $data['message'] ?? null)
                            : $service->sendHeadsUp($record, $data['message'] ?? null);
                    } catch (\RuntimeException $e) {
                        Notification::make()->title($e->getMessage())->warning()->send();
                        return;
                    }
                    Notification::make()->title('Enviado para ' . $record->name)->success()->send();
                }),

            Infolists\Components\Actions\Action::make('copiarLink')
                ->label('Copiar link')
                ->icon('heroicon-o-link')
                ->color('gray')
                ->size('sm')
                ->visible(fn (ArticleReviewer $record) => $record->isActive() && $record->articleReview->status !== ArticleReview::STATUS_CANCELLED)
                ->modalHeading(fn (ArticleReviewer $record) => 'Link pessoal de ' . $record->name)
                ->modalDescription('O link continua o mesmo que a pessoa já recebeu. Clique no texto para selecionar e copie (Ctrl+C).')
                ->fillForm(fn (ArticleReviewer $record) => [
                    'link' => $record->accessUrl(),
                    'message' => self::whatsappMessage($record),
                ])
                ->form(fn (ArticleReviewer $record) => [
                    Forms\Components\TextInput::make('link')
                        ->label('Link')
                        ->readOnly()
                        ->extraInputAttributes(['onclick' => 'this.select()']),
                    Forms\Components\Textarea::make('message')
                        ->label('Mensagem pronta (WhatsApp/e-mail)')
                        ->rows(7)
                        ->readOnly()
                        ->extraInputAttributes(['onclick' => 'this.select()'])
                        ->helperText($record->whatsappNumber()
                            ? new HtmlString('<a href="https://wa.me/' . $record->whatsappNumber() . '?text=' . rawurlencode(self::whatsappMessage($record)) . '" target="_blank" rel="noopener" style="color:#0d9488;font-weight:600">Abrir no WhatsApp de ' . e($record->phone) . ' →</a>')
                            : 'Sem telefone cadastrado.'),
                ])
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->action(fn () => null),

            Infolists\Components\Actions\Action::make('novoLink')
                ->label('Gerar link novo')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->size('sm')
                ->visible(fn (ArticleReviewer $record) => $record->isActive() && $record->articleReview->status !== ArticleReview::STATUS_CANCELLED)
                ->requiresConfirmation()
                ->modalHeading(fn (ArticleReviewer $record) => 'Gerar link novo para ' . $record->name . '?')
                ->modalDescription('Use se o link vazou ou foi encaminhado. O link antigo deixa de funcionar. Se a pessoa tiver e-mail, o novo link vai por e-mail.')
                ->action(function (ArticleReviewer $record) {
                    $url = $record->accessUrl(true);
                    $record->articleReview->logEvent("Link de {$record->name} trocado", 'O anterior foi desativado');
                    if ($record->email) {
                        $record->status === ArticleReviewer::STATUS_IN_REVIEW
                            ? app(ArticleReviewService::class)->sendInvite($record, true)
                            : app(ArticleReviewService::class)->sendHeadsUp($record, 'Este é o seu novo link de acesso (o anterior foi desativado).');
                    }
                    Notification::make()->title('Novo link gerado')->body($url)->success()->persistent()->send();
                }),

            Infolists\Components\Actions\Action::make('editarParticipante')
                ->label('Editar dados')
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->size('sm')
                ->visible(fn (ArticleReviewer $record) => $record->status !== ArticleReviewer::STATUS_REMOVED)
                ->fillForm(fn (ArticleReviewer $record) => $record->only(['name', 'email', 'phone', 'review_days', 'author_order']))
                ->form(self::participantFields())
                ->action(function (ArticleReviewer $record, array $data) {
                    if (filled($data['email']) && $record->articleReview->participants()
                        ->where('email', mb_strtolower(trim($data['email'])))->whereKeyNot($record->id)->exists()) {
                        Notification::make()->title('Outro participante deste processo já usa esse e-mail.')->danger()->send();
                        return;
                    }
                    app(ArticleReviewService::class)->updateParticipant($record, $data);
                    Notification::make()->title('Dados atualizados')->success()->send();
                }),

            Infolists\Components\Actions\Action::make('remover')
                ->label('Remover do processo')
                ->icon('heroicon-o-user-minus')
                ->color('danger')
                ->outlined()
                ->size('sm')
                ->visible(fn (ArticleReviewer $record) => $record->status === ArticleReviewer::STATUS_WAITING
                    && !$record->articleReview->isClosed())
                ->requiresConfirmation()
                ->modalDescription('A pessoa sai da fila e perde o acesso. Nada é apagado: ela continua no histórico como "Removido". (Quem já entregou nunca pode ser removido.)')
                ->form([
                    Forms\Components\TextInput::make('reason')->label('Motivo (opcional, fica no histórico)')->maxLength(250),
                ])
                ->action(function (ArticleReviewer $record, array $data) {
                    try {
                        app(ArticleReviewService::class)->removeParticipant($record, $data['reason'] ?? null);
                    } catch (\RuntimeException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                        return;
                    }
                    Notification::make()->title($record->name . ' removido(a) do processo')->success()->send();
                }),
        ];
    }

    /**
     * "Excluir processo" (página e lista): apaga tudo em qualquer situação,
     * para cadastros/envios errados. Exige digitar EXCLUIR.
     *
     * @template T of \Filament\Actions\Action|\Filament\Tables\Actions\Action
     * @param  T  $action
     * @return T
     */
    public static function deleteProcessAction($action)
    {
        return $action
            ->label('Excluir processo')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->modalHeading(fn (ArticleReview $record) => 'Excluir "' . \Illuminate\Support\Str::limit($record->title, 60) . '"?')
            ->modalDescription(function (ArticleReview $record) {
                $done = $record->reviewers()->where('status', ArticleReviewer::STATUS_DONE)->count();

                return 'Apaga DEFINITIVAMENTE o processo inteiro: participantes, fichas, todas as versões, documentos e o histórico. Os links param de funcionar. Não dá para desfazer.'
                    . ($done ? " Atenção: {$done} pessoa(s) já entregaram revisão — se quiser guardar a prova, baixe antes o \"Dossiê de auditoria (PDF)\"." : '');
            })
            ->modalSubmitActionLabel('Excluir tudo')
            ->form(fn (ArticleReview $record) => [
                Forms\Components\Checkbox::make('notify')
                    ->label('Avisar os participantes por e-mail que o processo foi cancelado')
                    ->default($record->status !== ArticleReview::STATUS_DRAFT)
                    ->visible($record->status !== ArticleReview::STATUS_DRAFT),
                Forms\Components\TextInput::make('reason')
                    ->label('Motivo (opcional, vai no aviso)')
                    ->maxLength(250)
                    ->visible($record->status !== ArticleReview::STATUS_DRAFT),
                Forms\Components\TextInput::make('confirm')
                    ->label('Para confirmar, digite EXCLUIR')
                    ->required()
                    ->in(['EXCLUIR', 'excluir'])
                    ->validationMessages(['in' => 'Digite EXCLUIR para confirmar.']),
            ])
            ->action(function (ArticleReview $record, array $data, $action) {
                app(ArticleReviewService::class)->deleteProcess($record, (bool) ($data['notify'] ?? false), $data['reason'] ?? null);
                Notification::make()->title('Processo excluído')->success()->send();
                $action->success();
            });
    }

    /**
     * Nome curto para arquivo: as primeiras palavras significativas do título.
     */
    public static function suggestShortName(string $title): string
    {
        $stop = ['a', 'o', 'as', 'os', 'de', 'da', 'do', 'das', 'dos', 'e', 'em', 'no', 'na', 'nos', 'nas', 'um', 'uma', 'para', 'por', 'com', 'the', 'of', 'and', 'in', 'on', 'for', 'to'];
        $words = collect(preg_split('/[^\p{L}\p{N}]+/u', \Illuminate\Support\Str::ascii($title), -1, PREG_SPLIT_NO_EMPTY))
            ->reject(fn ($w) => in_array(mb_strtolower($w), $stop, true))
            ->take(4)
            ->map(fn ($w) => ucfirst(mb_strtolower($w)));

        return \Illuminate\Support\Str::limit($words->implode('_'), 60, '') ?: 'Trabalho';
    }

    public static function whatsappMessage(ArticleReviewer $record): string
    {
        $review = $record->articleReview;
        $isTurn = $record->status === ArticleReviewer::STATUS_IN_REVIEW;

        return "Olá, {$record->name}! Segue o seu link pessoal do processo de revisão do {$review->workNoun()} \"{$review->title}\":\n\n"
            . $record->accessUrl() . "\n\n"
            . ($isTurn
                ? 'Agora é a sua vez: pelo link você confirma a sua ficha (nome, filiação, ORCID, tipo de participação), baixa o arquivo e devolve a revisão'
                    . ($record->due_at ? ' até ' . $record->due_at->format('d/m/Y') : '') . '.'
                : 'Pelo link você preenche/confere a sua ficha (nome, filiação, ORCID, telefone e tipo de participação) e acompanha com quem está o arquivo.')
            . "\n\nO link é pessoal — por favor, não encaminhe. Obrigado! Prof. Atilio";
    }

    public static function turnBanner(ArticleReview $record): HtmlString
    {
        $box = fn (string $bg, string $border, string $html) => new HtmlString(
            '<div style="border:2px solid ' . $border . ';background:' . $bg . ';border-radius:.9rem;padding:.9rem 1.1rem;font-size:1rem;color:#0f172a">' . $html . '</div>'
        );

        $participants = $record->reviewers->filter->isActive();
        $profiles = $participants->filter->isProfileComplete()->count();
        $fichas = " <span style=\"font-size:.85rem;opacity:.8\">· Fichas confirmadas: {$profiles}/{$participants->count()}</span>";

        return match ($record->status) {
            ArticleReview::STATUS_IN_REVIEW => ($active = $record->reviewers->firstWhere('status', ArticleReviewer::STATUS_IN_REVIEW))
                ? $box(
                    $active->isOverdue() ? '#fef2f2' : '#fffbeb',
                    $active->isOverdue() ? '#ef4444' : '#f59e0b',
                    '⏳ <strong>Na vez: ' . e($active->name) . '</strong> desde ' . $active->sent_at?->format('d/m/Y')
                        . ' (' . (int) $active->sent_at?->diffInDays(now()) . ' dia(s))'
                        . ' · prazo ' . $active->due_at?->format('d/m/Y') . ($active->isOverdue() ? ' — <strong style="color:#b91c1c">ATRASADO</strong>' : '')
                        . ($active->first_opened_at ? '' : ' · <em>ainda não abriu o link</em>')
                        . $fichas
                )
                : $box('#f8fafc', '#94a3b8', 'Em revisão, mas ninguém na vez agora.' . $fichas),
            ArticleReview::STATUS_REVIEWS_DONE => ($missing = $record->reviewers->where('status', ArticleReviewer::STATUS_WAITING))->isNotEmpty()
                ? $box('#eff6ff', '#3b82f6', '📥 <strong>Voltou para você.</strong> Você encerrou a rodada antes do fim — não revisaram: ' . e($missing->pluck('name')->implode(', ')) . '. Termine e registre o envio à revista.' . $fichas)
                : $box('#eff6ff', '#3b82f6', '📥 <strong>Voltou para você.</strong> Todas as revisões foram concluídas — consolide e registre o envio à revista.' . $fichas),
            ArticleReview::STATUS_SUBMITTED => $box('#ecfdf5', '#10b981', '✅ <strong>Processo fechado.</strong> Enviado' . ($record->journal ? ' à ' . e($record->journal) : '') . ' em ' . $record->submitted_at?->format('d/m/Y') . '.'),
            ArticleReview::STATUS_CANCELLED => $box('#f8fafc', '#94a3b8', 'Processo cancelado.'),
            default => $box('#f8fafc', '#94a3b8', '📝 <strong>Rascunho.</strong> Confira os participantes e clique em "Enviar despacho". Você já pode mandar o link de cadastro/ficha.' . $fichas),
        };
    }

    /**
     * Cartão compacto do participante: nome/contato, situação, datas e
     * versão numa linha só; ficha logo abaixo.
     */
    public static function participantCardHtml(ArticleReviewer $r): HtmlString
    {
        [$bg, $fg] = match (true) {
            $r->isOverdue() => ['#fee2e2', '#b91c1c'],
            $r->status === ArticleReviewer::STATUS_DONE => ['#dcfce7', '#15803d'],
            $r->status === ArticleReviewer::STATUS_IN_REVIEW => ['#fef3c7', '#b45309'],
            !$r->isActive() => ['#f1f5f9', '#64748b'],
            default => ['#e0f2fe', '#0369a1'],
        };
        $isTurn = $r->status === ArticleReviewer::STATUS_IN_REVIEW;

        $tags = collect([
            $r->author_order ? 'Autoria: ' . (int) $r->author_order . 'º' : null,
            $r->user_id ? '🔑 tem senha' : null,
            $r->telegramChatId() ? '📲 Telegram' : null,
            $r->self_registered ? 'cadastrou-se pelo link' : null,
            $r->last_reminder_at ? 'último aviso ' . $r->last_reminder_at->format('d/m H:i') : null,
        ])->filter()->map(fn ($t) => '<span style="display:inline-block;font-size:.7rem;background:rgba(100,116,139,.12);border-radius:999px;padding:0 .45rem;margin:.15rem .25rem 0 0">' . e($t) . '</span>')->implode('');

        $dates = collect([
            $r->sent_at ? 'Recebeu <strong>' . $r->sent_at->format('d/m/Y H:i') . '</strong>' : null,
            $r->first_opened_at ? 'Abriu ' . $r->first_opened_at->format('d/m H:i') : ($isTurn ? '<span style="color:#b45309">ainda não abriu</span>' : null),
            $r->due_at && $isTurn ? 'Prazo <strong' . ($r->isOverdue() ? ' style="color:#b91c1c"' : '') . '>' . $r->due_at->format('d/m/Y') . '</strong>' : null,
            $r->returned_at ? 'Devolveu <strong>' . $r->returned_at->format('d/m/Y H:i') . '</strong>' : null,
            $r->removed_at ? 'Removido ' . $r->removed_at->format('d/m/Y') . ($r->removed_reason ? ' — ' . e($r->removed_reason) : '') : null,
        ])->filter()->implode(' · ');

        $version = $r->returned_file_path
            ? '<a href="' . route('article-review.admin.download', ['articleReview' => $r->article_review_id, 'version' => $r->id]) . '" style="color:#0d9488;font-weight:600">⬇ ' . e($r->returned_filename) . '</a>'
            : '';

        return new HtmlString(
            '<div style="display:block;width:100%;box-sizing:border-box;border:1px solid ' . ($isTurn ? '#f59e0b' : 'rgba(148,163,184,.35)') . ';' . ($isTurn ? 'box-shadow:0 0 0 1px #f59e0b inset;' : '') . 'border-radius:.75rem;padding:.75rem 1rem;line-height:1.5">'
            . '<div style="display:flex;flex-wrap:wrap;gap:.35rem 1rem;align-items:center;justify-content:space-between">'
            . '<div style="min-width:220px"><strong style="font-size:.95rem">' . e($r->name) . '</strong>'
            . '<div style="font-size:.78rem;opacity:.75">' . e($r->email ?: 'sem e-mail') . ' · ' . e($r->phone ?: 'sem telefone') . '</div>'
            . ($tags ? '<div>' . $tags . '</div>' : '') . '</div>'
            . '<span style="font-size:.75rem;font-weight:700;background:' . $bg . ';color:' . $fg . ';border-radius:999px;padding:.15rem .6rem;white-space:nowrap">'
            . e($r->statusLabel() . ($r->isOverdue() ? ' (atrasado)' : '')) . '</span>'
            . '</div>'
            . ($dates ? '<div style="font-size:.8rem;margin-top:.4rem;opacity:.9">' . $dates . '</div>' : '')
            . ($version ? '<div style="font-size:.8rem;margin-top:.25rem">' . $version . '</div>' : '')
            . '<div style="margin-top:.5rem;padding-top:.5rem;border-top:1px dashed rgba(148,163,184,.4)">' . self::profileHtml($r)->toHtml() . '</div>'
            . '</div>'
        );
    }

    public static function profileHtml(ArticleReviewer $r): HtmlString
    {
        if (!$r->isProfileComplete()) {
            return new HtmlString('<span style="color:#b91c1c;font-weight:600">Ficha pendente</span>'
                . ($r->institution ? ' <span style="opacity:.7">(parcial)</span>' : ''));
        }

        $orcid = $r->orcid
            ? '<a href="https://orcid.org/' . e($r->orcid) . '" target="_blank" rel="noopener" style="color:#0d9488">' . e($r->orcid) . '</a>'
            : '<span style="color:#b45309">não possui</span>';

        return new HtmlString(
            '<div style="font-size:.85rem;line-height:1.6">'
            . '<span style="color:#047857;font-weight:600">✔ Confirmada em ' . $r->profile_confirmed_at->format('d/m/Y H:i') . '</span>'
            . ' (aceite de participação + ciência do conteúdo + checklist)<br>'
            . '<strong>Citação:</strong> ' . e($r->citation_name) . ' · <strong>ORCID:</strong> ' . $orcid . '<br>'
            . '<strong>Filiação:</strong> ' . e($r->affiliationLine()) . '<br>'
            . '<strong>CRediT:</strong> ' . e($r->rolesLabel())
            . ($r->profile_notes ? '<br><strong>Obs.:</strong> ' . nl2br(e($r->profile_notes)) : '')
            . '</div>'
        );
    }

    public static function changesHtml(ArticleReviewer $r): HtmlString
    {
        $c = $r->forensic['changes'] ?? null;
        if (!$c) {
            return new HtmlString('—');
        }

        $alerts = collect($c['alerts'] ?? [])
            ->map(fn ($a) => '<span style="display:inline-block;background:#fef3c7;color:#92400e;border-radius:999px;padding:0 .5rem;font-size:.75rem;margin:.1rem .2rem 0 0">⚠ ' . e(DocxInspector::ALERT_LABELS[$a] ?? $a) . '</span>')
            ->implode('');

        $items = collect($c['items'] ?? [])
            ->map(fn ($i) => '<div style="padding:.15rem .4rem;margin:.15rem 0;border-radius:.3rem;background:' . ($i['type'] === '+' ? '#ecfdf5' : '#fef2f2') . '"><strong>' . ($i['type'] === '+' ? '+ ' : '− ') . '</strong>' . e($i['text']) . '</div>')
            ->implode('');

        return new HtmlString(
            '<div style="font-size:.82rem">'
            . 'Comparado com v' . (int) ($c['compared_with_version'] ?? 0) . ': '
            . '<strong>+' . (int) $c['words_added'] . ' / −' . (int) $c['words_removed'] . ' palavras</strong> · '
            . (int) $c['tracked_insertions'] . ' inserções e ' . (int) $c['tracked_deletions'] . ' exclusões controladas · '
            . (int) $c['comments'] . ' comentário(s) · semelhança ' . round(($c['similarity'] ?? 1) * 100) . '%'
            . ($alerts ? '<div>' . $alerts . '</div>' : '')
            . ($items ? '<details style="margin-top:.35rem"><summary style="cursor:pointer;color:#0d9488">Ver trechos alterados (' . (int) $c['items_total'] . ')</summary>' . $items . '</details>' : '')
            . '</div>'
        );
    }

    public static function versionsHtml(ArticleReview $record): HtmlString
    {
        $current = $record->currentVersion()['version'];

        return new HtmlString('<ul style="margin:0;padding-left:1.1rem;font-size:.875rem;line-height:1.8">'
            . collect($record->allVersions())->map(fn ($v) => '<li><a style="color:#0d9488" href="'
                . route('article-review.admin.download', ['articleReview' => $record, 'version' => $v['key']]) . '">'
                . e($v['filename']) . '</a> — v' . $v['version'] . ' · ' . e($v['label'])
                . ($v['at'] ? ' · ' . $v['at']->format('d/m/Y H:i') : '')
                . ($v['version'] === $current ? ' <strong>(atual)</strong>' : '') . '</li>')->implode('')
            . '</ul>');
    }

    public static function filesHtml(ArticleReview $record): HtmlString
    {
        $files = $record->files()->get();
        if ($files->isEmpty()) {
            return new HtmlString('<span style="opacity:.7">Nenhum.</span>');
        }

        return new HtmlString('<ul style="margin:0;padding-left:1.1rem;font-size:.875rem;line-height:1.8">'
            . $files->map(fn ($f) => '<li><a style="color:#0d9488" href="'
                . route('article-review.admin.download', ['articleReview' => $record, 'version' => 'file-' . $f->id]) . '">'
                . e($f->filename) . '</a> (' . $f->size_kb . ' KB) · ' . e($f->uploaded_by ?: 'professor')
                . ' · ' . $f->created_at->format('d/m/Y H:i')
                . ($f->description ? ' — ' . e($f->description) : '') . '</li>')->implode('')
            . '</ul>');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('reviewers'))
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Trabalho')
                    ->searchable()
                    ->limit(60)
                    ->tooltip(fn (ArticleReview $record) => $record->title)
                    ->description(fn (ArticleReview $record) => $record->workTypeLabel() . ($record->journal ? ' · ' . $record->journal : '')),
                Tables\Columns\TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
                    ->formatStateUsing(fn (ArticleReview $record) => $record->statusLabel())
                    ->color(fn (string $state) => self::statusColor($state)),
                Tables\Columns\TextColumn::make('progress')
                    ->label('Progresso')
                    ->state(function (ArticleReview $record) {
                        $active = $record->reviewers->filter->isActive();
                        $done = $active->where('status', ArticleReviewer::STATUS_DONE)->count();

                        return "{$done}/{$active->count()}";
                    })
                    ->description(function (ArticleReview $record) {
                        $active = $record->reviewers->filter->isActive();

                        return 'Fichas ' . $active->filter->isProfileComplete()->count() . '/' . $active->count();
                    }),
                Tables\Columns\TextColumn::make('current_reviewer')
                    ->label('Com quem está')
                    ->weight('bold')
                    ->state(fn (ArticleReview $record) => $record->activeReviewer()?->name ?? ($record->status === ArticleReview::STATUS_REVIEWS_DONE ? 'Com você' : '—'))
                    ->description(fn (ArticleReview $record) => ($r = $record->activeReviewer()) && $r->due_at
                        ? 'Desde ' . $r->sent_at?->format('d/m') . ' · prazo ' . $r->due_at->format('d/m/Y') . ($r->isOverdue() ? ' — atrasado' : '')
                            . ($r->first_opened_at ? '' : ' · não abriu o link')
                        : null)
                    ->color(fn (ArticleReview $record) => $record->activeReviewer()?->isOverdue() ? 'danger' : null),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Atualizado')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Situação')
                    ->options(ArticleReview::STATUS_LABELS),
                Tables\Filters\SelectFilter::make('work_type')
                    ->label('Tipo')
                    ->options(ArticleReview::WORK_TYPES),
            ])
            ->actions([
                Tables\Actions\Action::make('baixarAtual')
                    ->label('Versão atual')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn (ArticleReview $record) => self::currentVersionUrl($record)),
                Tables\Actions\Action::make('aviso')
                    ->label('Avisar')
                    ->icon('heroicon-o-bell-alert')
                    ->color('warning')
                    ->visible(fn (ArticleReview $record) => $record->status === ArticleReview::STATUS_IN_REVIEW && $record->activeReviewer()?->email)
                    ->modalHeading(fn (ArticleReview $record) => 'Enviar aviso a ' . $record->activeReviewer()?->name)
                    ->modalDescription('Vai com o prazo, o link de acesso e a sua mensagem, se houver.')
                    ->form([
                        Forms\Components\Textarea::make('message')->label('Mensagem (opcional)')->rows(3)->maxLength(2000),
                    ])
                    ->action(function (ArticleReview $record, array $data) {
                        $reviewer = $record->activeReviewer();
                        if ($reviewer) {
                            app(ArticleReviewService::class)->sendReminder($reviewer, $data['message'] ?? null);
                            Notification::make()->title('Aviso enviado para ' . $reviewer->name)->success()->send();
                        }
                    }),
                Tables\Actions\ViewAction::make()->label('Abrir'),
                Tables\Actions\EditAction::make(),
                self::deleteProcessAction(Tables\Actions\Action::make('excluir'))->iconButton()->tooltip('Excluir processo'),
            ])
            ->recordUrl(fn (ArticleReview $record) => static::getUrl('view', ['record' => $record]));
    }

    /**
     * Campo de upload dos complementares (cadastro e "Anexar documentos").
     * Um arquivo por vez e sem pré-visualização: na hospedagem compartilhada
     * vários envios simultâneos + a prévia de imagem deixavam o envio girando.
     */
    public static function supplementaryUploadField(string $name = 'files'): Forms\Components\FileUpload
    {
        return Forms\Components\FileUpload::make($name)
            ->label('Arquivos')
            ->multiple()
            ->maxFiles(10)
            ->disk(ArticleReviewService::DISK)
            ->directory('article_reviews/tmp')
            ->visibility('private')
            ->storeFileNamesIn($name . '_names')
            ->maxSize(ArticleReviewService::MAX_SUPPLEMENTARY_KB)
            ->maxParallelUploads(1)
            ->previewable(false)
            ->required();
    }

    /**
     * Valida e anexa os complementares que vieram do campo acima.
     *
     * @return array{0: int, 1: array<int, string>}
     */
    public static function attachSupplementary(ArticleReview $record, array $data, string $name = 'files'): array
    {
        $disk = \Illuminate\Support\Facades\Storage::disk(ArticleReviewService::DISK);
        $names = (array) ($data[$name . '_names'] ?? []);
        $added = 0;
        $errors = [];

        foreach ((array) ($data[$name] ?? []) as $tmp) {
            $original = $names[$tmp] ?? basename($tmp);

            if (!$disk->exists($tmp)) {
                $errors[] = "{$original}: o envio não terminou — tente de novo.";
                continue;
            }

            $content = $disk->get($tmp);
            [$ok, $error] = ArticleReviewService::validateSupplementary($content, pathinfo($original, PATHINFO_EXTENSION));
            if ($ok) {
                app(ArticleReviewService::class)->addFile($record, null, $content, $original, $data['description'] ?? null, 'Prof. Atilio');
                $added++;
            } else {
                $errors[] = "{$original}: {$error}";
            }
            $disk->delete($tmp);
        }

        return [$added, $errors];
    }

    public static function notifyAttached(int $added, array $errors): void
    {
        if (!$added && !$errors) {
            return;
        }

        Notification::make()
            ->title("{$added} documento(s) complementar(es) anexado(s)")
            ->body($errors ? implode("\n", $errors) : null)
            ->{$errors ? 'warning' : 'success'}()
            ->persistent($errors !== [])
            ->send();
    }

    public static function canRemind(ArticleReviewer $reviewer): bool
    {
        return in_array($reviewer->status, [ArticleReviewer::STATUS_IN_REVIEW, ArticleReviewer::STATUS_WAITING], true)
            && $reviewer->articleReview?->status === ArticleReview::STATUS_IN_REVIEW;
    }

    public static function currentVersionUrl(ArticleReview $record): string
    {
        $versions = $record->allVersions();

        return route('article-review.admin.download', [
            'articleReview' => $record,
            'version' => end($versions)['key'],
        ]);
    }

    public static function statusColor(string $state): string
    {
        return match ($state) {
            ArticleReview::STATUS_IN_REVIEW => 'warning',
            ArticleReview::STATUS_REVIEWS_DONE => 'info',
            ArticleReview::STATUS_SUBMITTED => 'success',
            ArticleReview::STATUS_CANCELLED => 'danger',
            default => 'gray',
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArticleReviews::route('/'),
            'create' => Pages\CreateArticleReview::route('/create'),
            'view' => Pages\ViewArticleReview::route('/{record}'),
            'edit' => Pages\EditArticleReview::route('/{record}/edit'),
        ];
    }
}
