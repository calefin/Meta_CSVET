<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ResearchCallResource\Pages;
use App\Models\ResearchApplication;
use App\Models\ResearchCall;
use App\Models\SystematicReview;
use App\Services\ResearchRecruitmentService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ResearchCallResource extends Resource
{
    protected static ?string $model = ResearchCall::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationLabel = 'Seleção de Alunos';

    protected static ?string $modelLabel = 'Chamada de Seleção';

    protected static ?string $pluralModelLabel = 'Chamadas de Seleção de Alunos';

    protected static ?string $navigationGroup = 'Programa de Metanálises';

    protected static ?string $slug = 'selecao-alunos';

    protected static ?int $navigationSort = -37;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public static function form(Form $form): Form
    {
        $list = fn (string $name, string $label, ?string $help = null, int $rows = 2) => Forms\Components\Repeater::make($name)
            ->label($label)
            ->helperText($help)
            ->simple(Forms\Components\Textarea::make('text')->rows($rows)->required()->maxLength(1000))
            ->reorderable()
            ->addActionLabel('Adicionar item')
            ->defaultItems(0)
            ->columnSpanFull();

        return $form->schema([
            Forms\Components\Section::make('Chamada')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('title')->label('Título')->required()->maxLength(255)->columnSpanFull(),
                    Forms\Components\TextInput::make('slug')->label('Endereço da página')
                        ->prefix(url('/selecao') . '/')
                        ->helperText('Só letras minúsculas, números e hífen.')
                        ->required()->maxLength(80)
                        ->unique(ignoreRecord: true),
                    Forms\Components\Select::make('status')->label('Situação')
                        ->options(ResearchCall::STATUS_LABELS)->required()->native(false)
                        ->helperText('Em rascunho, só você vê a página (pré-visualização).'),
                    Forms\Components\DatePicker::make('closes_at')->label('Inscrições até (opcional)')
                        ->helperText('Depois desta data o formulário fecha sozinho.'),
                    Forms\Components\Textarea::make('headline')->label('Chamada curta (topo da página e WhatsApp)')->rows(2)->maxLength(500)->columnSpanFull(),
                    Forms\Components\Textarea::make('intro')->label('Sobre a seleção')->rows(5)->maxLength(5000)
                        ->helperText('Deixe uma linha em branco entre os parágrafos.')->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Requisitos conferidos automaticamente')
                ->description('O formulário recusa a inscrição de quem não cumpre estes requisitos e explica o motivo ao aluno.')
                ->columns(3)
                ->schema([
                    Forms\Components\CheckboxList::make('eligible_courses')->label('Cursos aceitos')
                        ->options(array_combine(ResearchCall::COURSES, ResearchCall::COURSES))
                        ->helperText('Nenhum marcado = qualquer curso.'),
                    Forms\Components\TextInput::make('min_semester')->label('Semestre mínimo')->numeric()->minValue(1)->maxValue(12)->required(),
                    Forms\Components\TextInput::make('min_weekly_hours')->label('Horas semanais mínimas')->numeric()->minValue(1)->maxValue(40)->required(),
                ]),

            Forms\Components\Section::make('Textos da página')
                ->collapsible()
                ->schema([
                    $list('benefits', 'O que o aluno ganha'),
                    $list('requirements', 'Pré-requisitos (texto exibido)', 'Escreva aqui também os requisitos conferidos automaticamente, para o aluno ler antes de se inscrever.'),
                    $list('commitments', 'Condições e compromissos', 'Cada item vira uma caixa obrigatória no formulário. O texto aceito fica guardado na inscrição.', 3),
                    Forms\Components\Repeater::make('steps')->label('Passo a passo (da inscrição ao artigo)')
                        ->helperText('Aparece na página e no acompanhamento do aluno. Mantenha a ordem: inscrição, análise, entrevista, resultado, login, termo… A etapa atual de cada aluno é marcada nessa ordem.')
                        ->schema([
                            Forms\Components\Select::make('phase')->label('Fase')->options(['Seleção' => 'Seleção', 'Projeto' => 'Projeto'])->required()->native(false),
                            Forms\Components\TextInput::make('title')->label('Etapa')->required()->maxLength(120),
                            Forms\Components\TextInput::make('when')->label('Quando')->maxLength(60)->placeholder('Semanas 3 a 5'),
                            Forms\Components\Textarea::make('text')->label('O que o aluno faz')->rows(2)->maxLength(1000)->columnSpanFull(),
                        ])
                        ->columns(3)
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state) => trim(($state['phase'] ?? '') . ' · ' . ($state['title'] ?? ''), ' ·'))
                        ->addActionLabel('Adicionar etapa')
                        ->defaultItems(0)
                        ->columnSpanFull(),
                    Forms\Components\Repeater::make('faq')->label('Perguntas frequentes')
                        ->schema([
                            Forms\Components\TextInput::make('q')->label('Pergunta')->required()->maxLength(255),
                            Forms\Components\Textarea::make('a')->label('Resposta')->rows(2)->required()->maxLength(2000),
                        ])
                        ->reorderable()->collapsible()
                        ->itemLabel(fn (array $state) => $state['q'] ?? null)
                        ->addActionLabel('Adicionar pergunta')
                        ->defaultItems(0)
                        ->columnSpanFull(),
                    $list('interview_questions', 'Roteiro da entrevista (só para você)', 'Viram caixas de marcar na ação "Registrar entrevista".'),
                ]),

            Forms\Components\Section::make('Eixos e projetos')
                ->description('Na página aparecem só o código e a área ("blindada"). Vincule o projeto a uma Revisão Sistemática para o aluno aprovado entrar direto como triador.')
                ->collapsible()
                ->schema([
                    Forms\Components\Repeater::make('axes')->label('Eixos')
                        ->schema([
                            Forms\Components\TextInput::make('name')->label('Nome do eixo')->required()->maxLength(120),
                            Forms\Components\Textarea::make('description')->label('Descrição')->rows(2)->maxLength(1000),
                        ])
                        ->reorderable()->collapsible()
                        ->itemLabel(fn (array $state) => $state['name'] ?? null)
                        ->live()
                        ->defaultItems(0)
                        ->columnSpanFull(),
                    Forms\Components\Repeater::make('projects')->label('Projetos')
                        ->schema([
                            Forms\Components\TextInput::make('code')->label('Código')->required()->maxLength(20)->placeholder('PET-01')->distinct(),
                            Forms\Components\Select::make('axis')->label('Eixo')->required()->native(false)
                                ->options(fn (Forms\Get $get) => collect($get('../../axes') ?? [])->pluck('name', 'name')->filter()->all()),
                            Forms\Components\TextInput::make('area')->label('Área exibida (sem o título real)')->required()->maxLength(255)->columnSpan(2),
                            Forms\Components\Toggle::make('closed')->label('Fechado (sai da lista)')
                                ->helperText('Sai sozinho quando a dupla fica completa; marque para tirar antes.')->columnSpan(2),
                            Forms\Components\Select::make('systematic_review_id')->label('Revisão Sistemática vinculada (opcional)')
                                ->options(fn () => SystematicReview::orderBy('title')->get()->mapWithKeys(fn ($r) => [$r->id => ($r->short_name ? $r->short_name . ' — ' : '') . \Illuminate\Support\Str::limit($r->title, 90)])->all())
                                ->searchable()
                                ->columnSpan(4),
                        ])
                        ->columns(4)
                        ->reorderable()->collapsible()->collapsed()
                        ->itemLabel(fn (array $state) => trim(($state['code'] ?? '') . ' — ' . ($state['area'] ?? ''), ' —'))
                        ->addActionLabel('Adicionar projeto')
                        ->defaultItems(0)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('Chamada')->wrap()
                    ->description(fn (ResearchCall $c) => '/selecao/' . $c->slug),
                Tables\Columns\TextColumn::make('status')->label('Situação')->badge()
                    ->formatStateUsing(fn (string $state, ResearchCall $c) => $state === ResearchCall::STATUS_OPEN && !$c->isOpen() ? 'Prazo vencido' : (ResearchCall::STATUS_LABELS[$state] ?? $state))
                    ->color(fn (string $state, ResearchCall $c) => $c->isOpen() ? 'success' : ($state === ResearchCall::STATUS_DRAFT ? 'warning' : 'gray')),
                Tables\Columns\TextColumn::make('closes_at')->label('Até')->date('d/m/Y')->placeholder('—'),
                Tables\Columns\TextColumn::make('applications_count')->label('Inscrições')->counts('applications')->badge(),
                Tables\Columns\TextColumn::make('approved')->label('Selecionados')
                    ->state(fn (ResearchCall $c) => $c->applications()->where('status', ResearchApplication::STATUS_APPROVED)->count() . '/' . count($c->projects ?? [])),
            ])
            ->actions([
                Tables\Actions\Action::make('pagina')->label('Ver página')->icon('heroicon-o-arrow-top-right-on-square')->color('gray')
                    ->url(fn (ResearchCall $c) => $c->publicUrl(), true),
                static::shareAction(Tables\Actions\Action::class),
                static::toggleOpenAction(Tables\Actions\Action::class),
                static::matrixAction(Tables\Actions\Action::class),
                Tables\Actions\Action::make('inscricoes')->label('Inscrições')->icon('heroicon-o-users')
                    ->url(fn (ResearchCall $c) => ResearchApplicationResource::getUrl('index', ['tableFilters' => ['research_call_id' => ['value' => $c->id]]])),
                Tables\Actions\EditAction::make(),
            ])
            ->emptyStateHeading('Nenhuma chamada ainda')
            ->emptyStateDescription('Use "Criar chamada das metanálises" no topo: ela já vem com requisitos, compromissos, passo a passo e os 20 projetos.');
    }

    /** Textos de divulgação prontos (e-mail e WhatsApp) com botão de copiar. */
    public static function shareAction(string $class)
    {
        return $class::make('divulgar')
            ->label('Divulgar')
            ->icon('heroicon-o-paper-airplane')
            ->color('success')
            ->modalHeading('Textos de divulgação')
            ->modalDescription('Copie e cole. Os títulos reais não aparecem: só as áreas e o link da página.')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->modalContent(function (ResearchCall $record) {
                $call = $record;
                $email = app(ResearchRecruitmentService::class)->announcementEmail($call);

                return view('filament.research-call.share', [
                    'call' => $call,
                    'emailSubject' => $email['subject'],
                    'emailBody' => $email['body'],
                    'whatsapp' => $call->shareText(),
                ]);
            });
    }

    /** Encerra (ou reabre) as inscrições em 1 clique; as inscrições já feitas seguem normalmente. */
    public static function toggleOpenAction(string $class, ?\Closure $resolveCall = null)
    {
        $call = fn ($record) => $resolveCall ? $resolveCall() : $record;

        return $class::make('encerrar')
            ->label(fn ($record = null) => $call($record)?->isOpen() ? 'Encerrar inscrições' : 'Reabrir inscrições')
            ->icon(fn ($record = null) => $call($record)?->isOpen() ? 'heroicon-o-lock-closed' : 'heroicon-o-lock-open')
            ->color(fn ($record = null) => $call($record)?->isOpen() ? 'danger' : 'success')
            ->visible(fn ($record = null) => (bool) $call($record))
            ->requiresConfirmation()
            ->modalHeading(fn ($record = null) => $call($record)?->isOpen() ? 'Encerrar as inscrições?' : 'Reabrir as inscrições?')
            ->modalDescription(fn ($record = null) => $call($record)?->isOpen()
                ? 'O formulário sai do ar e a página passa a mostrar "Inscrições encerradas". As inscrições já recebidas continuam aqui: você segue marcando reuniões, aprovando, reprovando e mudando de linha normalmente.'
                : 'O formulário volta a aceitar inscrições (se o prazo já tinha passado, ele é retirado; ajuste a data em Editar, se quiser).')
            ->action(function ($record = null) use ($call) {
                $c = $call($record);
                $open = !$c->isOpen();
                app(ResearchRecruitmentService::class)->setOpen($c, $open);
                \Filament\Notifications\Notification::make()->title($open ? 'Inscrições reabertas' : 'Inscrições encerradas')->success()->send();
            });
    }

    public static function matrixAction(string $class)
    {
        return $class::make('matriz')
            ->label('Matriz de duplas')
            ->icon('heroicon-o-table-cells')
            ->color('gray')
            ->modalHeading('Matriz de alocação (líder + revisor cego)')
            ->modalWidth('5xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->modalContent(fn (ResearchCall $record) => view('filament.research-call.matrix', [
                'rows' => app(ResearchRecruitmentService::class)->allocationMatrix($record),
            ]));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListResearchCalls::route('/'),
            'create' => Pages\CreateResearchCall::route('/create'),
            'edit' => Pages\EditResearchCall::route('/{record}/edit'),
        ];
    }
}
