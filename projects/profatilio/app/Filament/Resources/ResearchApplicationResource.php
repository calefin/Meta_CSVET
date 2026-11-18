<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ResearchApplicationResource\Pages;
use App\Models\ResearchApplication;
use App\Models\ResearchCall;
use App\Services\ResearchRecruitmentService;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ResearchApplicationResource extends Resource
{
    protected static ?string $model = ResearchApplication::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Inscrições na Seleção';

    protected static ?string $modelLabel = 'Inscrição';

    protected static ?string $pluralModelLabel = 'Inscrições na Seleção de Alunos';

    protected static ?string $navigationGroup = 'Programa de Metanálises';

    protected static ?string $slug = 'selecao-inscricoes';

    protected static ?int $navigationSort = -38;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $n = ResearchApplication::where('status', ResearchApplication::STATUS_NEW)->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Inscrições novas esperando análise';
    }

    protected static function service(): ResearchRecruitmentService
    {
        return app(ResearchRecruitmentService::class);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Candidato(a)')->columns(3)->schema([
                Infolists\Components\TextEntry::make('name')->label('Nome'),
                Infolists\Components\TextEntry::make('email')->label('E-mail')->copyable(),
                Infolists\Components\TextEntry::make('phone')->label('WhatsApp')->copyable(),
                Infolists\Components\TextEntry::make('course')->label('Curso'),
                Infolists\Components\TextEntry::make('semester')->label('Semestre')->suffix('º'),
                Infolists\Components\TextEntry::make('institution')->label('Instituição'),
                Infolists\Components\TextEntry::make('purpose')->label('Objetivo')->formatStateUsing(fn ($state) => ResearchApplication::PURPOSES[$state] ?? $state),
                Infolists\Components\TextEntry::make('weekly_hours')->label('Horas/semana'),
                Infolists\Components\TextEntry::make('english_level')->label('Inglês')->formatStateUsing(fn ($state) => ResearchApplication::ENGLISH_LEVELS[$state] ?? $state),
                Infolists\Components\TextEntry::make('lattes_url')->label('Lattes')->url(fn ($state) => $state, true)->placeholder('—'),
                Infolists\Components\TextEntry::make('orcid')->label('ORCID')->placeholder('—'),
                Infolists\Components\TextEntry::make('user_id')->label('Conta no portal')
                    ->state(fn (ResearchApplication $r) => $r->user_id || \App\Models\User::where('email', mb_strtolower(trim((string) $r->email)))->exists()
                        ? 'Já tem conta'
                        : ($r->status === ResearchApplication::STATUS_APPROVED ? 'Sem conta ainda (convite enviado na aprovação)' : 'Sem conta (recebe convite ao ser aprovado)')),
            ]),
            Infolists\Components\Section::make('Interesse')->columns(2)->schema([
                Infolists\Components\TextEntry::make('axis_preferences')->label('Eixos')->listWithLineBreaks()->placeholder('—'),
                Infolists\Components\TextEntry::make('project_preferences')->label('Projetos preferidos')
                    ->state(fn (ResearchApplication $r) => collect($r->project_preferences ?? [])->map(fn ($c) => $c . ' — ' . ($r->researchCall->projectsByCode()[$c]['area'] ?? ''))->all())
                    ->listWithLineBreaks()->placeholder('—'),
                Infolists\Components\TextEntry::make('motivation')->label('Motivação')->columnSpanFull(),
                Infolists\Components\TextEntry::make('experience')->label('Experiência com pesquisa')->placeholder('—')->columnSpanFull(),
            ]),
            Infolists\Components\Section::make('Seleção')->columns(3)->schema([
                Infolists\Components\TextEntry::make('status')->label('Situação')->badge()
                    ->formatStateUsing(fn ($state) => ResearchApplication::STATUS_LABELS[$state] ?? $state)
                    ->color(fn ($state) => ResearchApplication::STATUS_COLORS[$state] ?? 'gray'),
                Infolists\Components\TextEntry::make('interview_at')->label('Entrevista')->dateTime('d/m/Y H:i')->placeholder('—'),
                Infolists\Components\TextEntry::make('interview_place')->label('Local/link')->placeholder('—'),
                Infolists\Components\TextEntry::make('interview_answers')->label('Roteiro da entrevista')
                    ->state(fn (ResearchApplication $r) => collect($r->interview_answers ?? [])->map(fn ($ok, $q) => ($ok ? '✅ ' : '❌ ') . $q)->values()->all())
                    ->listWithLineBreaks()->placeholder('—')->columnSpan(2),
                Infolists\Components\TextEntry::make('interview_notes')->label('Anotações')->placeholder('—'),
                Infolists\Components\TextEntry::make('lead_project_code')->label('Líder em')->placeholder('—'),
                Infolists\Components\TextEntry::make('reviewer_project_code')->label('Revisor cego em')->placeholder('—'),
                Infolists\Components\TextEntry::make('decided_at')->label('Decisão em')->dateTime('d/m/Y H:i')->placeholder('—'),
            ]),
            Infolists\Components\Section::make('Aceite e validação')->columns(3)->schema([
                Infolists\Components\TextEntry::make('email_verified_at')->label('E-mail confirmado em')->dateTime('d/m/Y H:i')->placeholder('⚠️ ainda não confirmado'),
                Infolists\Components\TextEntry::make('adhesion_accepted_at')->label('Termo de adesão aceito em')->dateTime('d/m/Y H:i:s')->placeholder('—'),
                Infolists\Components\TextEntry::make('ip')->label('IP do aceite')->placeholder('—')->copyable(),
                Infolists\Components\TextEntry::make('adhesion_sha256')->label('SHA-256 do texto aceito')->fontFamily('mono')->copyable()->placeholder('—')->columnSpanFull(),
                Infolists\Components\TextEntry::make('user_agent')->label('Navegador')->placeholder('—')->columnSpanFull(),
                Infolists\Components\TextEntry::make('adhesion_text')->label('Texto exato aceito')->placeholder('—')
                    ->formatStateUsing(fn ($state) => new \Illuminate\Support\HtmlString(nl2br(e($state))))->columnSpanFull(),
            ]),
            Infolists\Components\Section::make('Compromissos aceitos e histórico')->collapsed()->schema([
                Infolists\Components\TextEntry::make('declarations')->label('Compromissos aceitos')
                    ->state(fn (ResearchApplication $r) => collect($r->declarations ?? [])->map(fn ($d) => '✔ ' . $d['text'])->all())
                    ->listWithLineBreaks(),
                Infolists\Components\TextEntry::make('history')->label('Histórico')
                    ->state(fn (ResearchApplication $r) => collect($r->history ?? [])->map(fn ($h) => \Carbon\Carbon::parse($h['at'])->format('d/m/Y H:i') . ' — ' . $h['event'] . ($h['detail'] ? ' (' . $h['detail'] . ')' : ''))->reverse()->values()->all())
                    ->listWithLineBreaks(),
                Infolists\Components\TextEntry::make('ip')->label('IP / navegador da inscrição')
                    ->state(fn (ResearchApplication $r) => ($r->ip ?: '—') . ' · ' . ($r->user_agent ?: '—')),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nome')->searchable()->wrap()
                    ->description(fn (ResearchApplication $r) => $r->email . ($r->email_verified_at ? ' · ✅ e-mail confirmado' : ' · ⚠️ e-mail NÃO confirmado') . ($r->adhesion_sha256 ? '' : ' · 📝 aceite do termo pendente')),
                Tables\Columns\TextColumn::make('course')->label('Formação')->wrap()
                    ->formatStateUsing(fn (ResearchApplication $r) => "{$r->course} · {$r->semester}º sem.")
                    ->description(fn (ResearchApplication $r) => $r->institution),
                Tables\Columns\TextColumn::make('purpose')->label('Objetivo')
                    ->formatStateUsing(fn ($state) => ResearchApplication::PURPOSES[$state] ?? $state)
                    ->description(fn (ResearchApplication $r) => $r->weekly_hours . ' h/sem'),
                Tables\Columns\TextColumn::make('project_preferences')->label('Preferências')
                    ->state(fn (ResearchApplication $r) => implode(', ', $r->project_preferences ?? []) ?: '—')
                    ->description(fn (ResearchApplication $r) => collect($r->axis_preferences ?? [])->map(fn ($a) => \Illuminate\Support\Str::before($a, ' —'))->implode(', ')),
                Tables\Columns\TextColumn::make('status')->label('Situação')->badge()
                    ->formatStateUsing(fn ($state) => ResearchApplication::STATUS_LABELS[$state] ?? $state)
                    ->color(fn ($state) => ResearchApplication::STATUS_COLORS[$state] ?? 'gray')
                    ->description(fn (ResearchApplication $r) => match ($r->status) {
                        ResearchApplication::STATUS_INTERVIEW => $r->interview_at?->format('d/m H:i'),
                        ResearchApplication::STATUS_APPROVED => 'Líder ' . $r->lead_project_code . ($r->reviewer_project_code ? ' · Rev. ' . $r->reviewer_project_code : ''),
                        default => null,
                    }),
                Tables\Columns\TextColumn::make('created_at')->label('Inscrição')->since()->sortable()
                    ->tooltip(fn (ResearchApplication $r) => $r->created_at->format('d/m/Y H:i')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('research_call_id')->label('Chamada')
                    ->options(fn () => ResearchCall::orderByDesc('id')->pluck('title', 'id')->all()),
                Tables\Filters\SelectFilter::make('purpose')->label('Objetivo')->options(ResearchApplication::PURPOSES),
            ])
            ->actions([
                // As três decisões do dia a dia ficam à vista; o resto vai em "Mais".
                Tables\Actions\ViewAction::make()->label('Ver')->modalHeading(fn (ResearchApplication $r) => $r->name),
                static::scheduleAction(),
                static::approveAction(),
                Tables\Actions\Action::make('confirmacao')->label(fn (ResearchApplication $r) => $r->adhesion_sha256 ? 'Reenviar confirmação' : 'Pedir confirmação e aceite')->icon('heroicon-o-envelope')->color('warning')
                    ->visible(fn (ResearchApplication $r) => (!$r->email_verified_at || !$r->adhesion_sha256) && !$r->isClosed())
                    ->tooltip('Falta o aluno confirmar o e-mail e/ou aceitar o termo de adesão. Marcar reunião exige e-mail confirmado; Aprovar exige também o aceite.')
                    ->requiresConfirmation()
                    ->modalDescription('Reenvia o e-mail com o botão que confirma o endereço.')
                    ->action(function (ResearchApplication $r) {
                        static::service()->resendConfirmation($r);
                        Notification::make()->title("E-mail de confirmação reenviado para {$r->email}")->success()->send();
                    }),
                Tables\Actions\Action::make('recusar')->label('Reprovar')->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(fn (ResearchApplication $r) => !$r->isClosed())
                    ->modalHeading(fn (ResearchApplication $r) => "Reprovar {$r->name}")
                    ->modalDescription('O aluno recebe um e-mail educado dizendo que não foi selecionado(a) desta vez.')
                    ->form([Forms\Components\Textarea::make('message')->label('Recado (opcional, vai no e-mail)')->rows(3)->maxLength(1500)
                        ->placeholder('Ex.: Faltou cumprir o requisito de semestre mínimo. Inscreva-se na próxima chamada!')])
                    ->action(function (ResearchApplication $r, array $data) {
                        static::service()->reject($r, $data['message'] ?? null);
                        Notification::make()->title("Resultado enviado para {$r->firstName()}")->success()->send();
                    }),
                Tables\Actions\ActionGroup::make([
                    static::redirectAction(),
                    static::reallocateAction(),
                    Tables\Actions\Action::make('confirmar_email')->label('Confirmar e-mail manualmente')->icon('heroicon-o-check-circle')->color('gray')
                        ->visible(fn (ResearchApplication $r) => !$r->email_verified_at)
                        ->modalDescription('Use quando você confirmou com o aluno (ex.: pelo WhatsApp) que o e-mail está certo. Fica no histórico com a sua observação. O aceite do termo de adesão continua sendo feito pelo próprio aluno.')
                        ->form([Forms\Components\TextInput::make('note')->label('Como foi confirmado')->required()->maxLength(250)->placeholder('Ex.: confirmado pelo WhatsApp em 16/10')])
                        ->action(function (ResearchApplication $r, array $data) {
                            static::service()->confirmEmailManually($r, $data['note']);
                            Notification::make()->title('E-mail marcado como confirmado')->success()->send();
                        }),
                    Tables\Actions\Action::make('whatsapp')->label('WhatsApp')->icon('heroicon-o-chat-bubble-left-right')->color('success')
                        ->url(fn (ResearchApplication $r) => static::whatsappUrl($r), true),
                    static::interviewAction(),
                    Tables\Actions\Action::make('espera')->label('Lista de espera')->icon('heroicon-o-clock')->color('gray')
                        ->visible(fn (ResearchApplication $r) => !$r->isClosed() && $r->status !== ResearchApplication::STATUS_WAITLIST)
                        ->form([Forms\Components\Textarea::make('message')->label('Recado (opcional, vai no e-mail)')->rows(3)->maxLength(1500)])
                        ->action(function (ResearchApplication $r, array $data) {
                            static::service()->waitlist($r, $data['message'] ?? null);
                            Notification::make()->title("{$r->firstName()} foi para a lista de espera (e-mail enviado)")->success()->send();
                        }),
                    Tables\Actions\Action::make('desligar')->label('Desligar do projeto')->icon('heroicon-o-no-symbol')->color('danger')
                        ->visible(fn (ResearchApplication $r) => $r->status === ResearchApplication::STATUS_APPROVED)
                        ->requiresConfirmation()
                        ->modalHeading(fn (ResearchApplication $r) => "Desligar {$r->name}")
                        ->modalDescription('O aluno perde o acesso às triagens dos projetos vinculados, a vaga é liberada na matriz e ele recebe um e-mail com o motivo e o compromisso de prazos que aceitou na inscrição (o termo assinado no portal também prevê o desligamento por atraso). O motivo fica no histórico.')
                        ->form([Forms\Components\Textarea::make('reason')->label('Motivo (vai no e-mail e fica no histórico)')->required()->rows(3)->maxLength(1500)
                            ->placeholder('Ex.: A etapa de triagem venceu em 10/11 e não houve entrega nem resposta aos contatos de 11/11 e 13/11.')])
                        ->action(function (ResearchApplication $r, array $data) {
                            static::service()->remove($r, $data['reason']);
                            Notification::make()->title("{$r->firstName()} foi desligado(a) e avisado(a) por e-mail")->success()->send();
                        }),
                    Tables\Actions\Action::make('desistiu')->label('Registrar desistência')->icon('heroicon-o-arrow-right-start-on-rectangle')->color('gray')
                        ->visible(fn (ResearchApplication $r) => !in_array($r->status, [ResearchApplication::STATUS_WITHDRAWN, ResearchApplication::STATUS_REMOVED], true))
                        ->requiresConfirmation()
                        ->modalDescription('Libera a vaga na matriz de duplas. Se ele(a) já estava como triador em algum projeto, remova também na Revisão Sistemática.')
                        ->form([Forms\Components\TextInput::make('reason')->label('Motivo (só no histórico)')->maxLength(250)])
                        ->action(fn (ResearchApplication $r, array $data) => static::service()->withdraw($r, $data['reason'] ?? null)),
                    Tables\Actions\DeleteAction::make()->label('Excluir dados (pedido LGPD)')
                        ->modalHeading(fn (ResearchApplication $r) => "Excluir a inscrição de {$r->name}")
                        ->modalDescription('Use só quando o aluno pedir a exclusão dos dados (art. 18 da LGPD) ou para limpar inscrições antigas de quem não foi selecionado. Apaga a inscrição, o aceite e o histórico. Não dá para desfazer. Para alunos aprovados, o termo assinado no portal continua guardado na Revisão Sistemática.'),
                    Tables\Actions\Action::make('acompanhamento')->label('Link de acompanhamento')->icon('heroicon-o-link')->color('gray')
                        ->action(fn (ResearchApplication $r) => Notification::make()->title('Link pessoal do aluno')->body($r->trackingUrl())->persistent()->send()),
                    Tables\Actions\Action::make('mensagem')->label('Enviar mensagem')->icon('heroicon-o-envelope-open')->color('primary')
                        ->modalHeading(fn (ResearchApplication $r) => "Mensagem para {$r->name}")
                        ->modalDescription('Só para este aluno, por e-mail (e Telegram, se ligado). Fica no histórico da inscrição.')
                        ->form(static::messageFields())
                        ->action(function (ResearchApplication $r, array $data) {
                            static::service()->sendMessage($r, $data['title'], $data['body']);
                            Notification::make()->title("Mensagem enviada para {$r->firstName()}")->success()->send();
                        }),
                ])->label('Mais')->icon('heroicon-m-ellipsis-vertical')->color('gray'),
            ])
            ->bulkActions([
                // Marque os alunos (ex.: todos da aba Selecionados) e envie a mesma mensagem a cada um.
                Tables\Actions\BulkAction::make('mensagemGrupo')->label('Enviar mensagem aos marcados')->icon('heroicon-o-chat-bubble-left-right')->color('primary')
                    ->modalDescription('Cada aluno marcado recebe a mensagem individualmente (ninguém vê o e-mail dos outros). Use {nome} para o primeiro nome.')
                    ->form(static::messageFields())
                    ->deselectRecordsAfterCompletion()
                    ->action(function (\Illuminate\Support\Collection $records, array $data) {
                        foreach ($records as $r) {
                            static::service()->sendMessage($r, $data['title'], $data['body']);
                        }
                        Notification::make()->title('Mensagem enviada para ' . $records->count() . ' aluno(s)')->success()->send();
                    }),
            ])
            ->emptyStateHeading('Nenhuma inscrição ainda')
            ->emptyStateDescription('Divulgue a página da chamada em "Seleção de Alunos" → Divulgar.');
    }

    /** Campos da mensagem livre do professor (individual, dupla ou grupo). */
    public static function messageFields(): array
    {
        return [
            Forms\Components\TextInput::make('title')->label('Assunto')->required()->maxLength(120),
            Forms\Components\Textarea::make('body')->label('Mensagem')->required()->rows(8)->maxLength(5000)
                ->helperText('{nome} vira o primeiro nome do aluno. Não inclua títulos reais de projetos de quem ainda não assinou o termo.'),
        ];
    }

    protected static function scheduleAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('agendar')->label(fn (ResearchApplication $r) => $r->interview_at ? 'Remarcar reunião' : 'Marcar reunião')->icon('heroicon-o-calendar-days')->color('info')
            ->modalHeading(fn (ResearchApplication $r) => "Reunião com {$r->name}")
            ->modalDescription('O aluno recebe um e-mail com a data, o horário e o link, e vê tudo na página de acompanhamento.')
            ->visible(fn (ResearchApplication $r) => $r->email_verified_at && in_array($r->status, [ResearchApplication::STATUS_NEW, ResearchApplication::STATUS_INTERVIEW, ResearchApplication::STATUS_WAITLIST], true))
            ->fillForm(fn (ResearchApplication $r) => [
                'interview_at' => $r->interview_at,
                'interview_place' => $r->interview_place ?: 'Google Meet — link: ',
            ])
            ->form([
                Forms\Components\DateTimePicker::make('interview_at')->label('Data e hora')->seconds(false)->required()->minDate(now()->startOfDay()),
                Forms\Components\TextInput::make('interview_place')->label('Onde (link da chamada ou local)')->required()->maxLength(500),
                Forms\Components\Textarea::make('message')->label('Recado (opcional)')->rows(2)->maxLength(1000),
            ])
            ->action(function (ResearchApplication $r, array $data) {
                static::service()->scheduleInterview($r, \Carbon\Carbon::parse($data['interview_at']), $data['interview_place'], $data['message'] ?? null);
                Notification::make()->title("Reunião marcada. {$r->firstName()} recebeu o e-mail.")->success()->send();
            });
    }

    protected static function interviewAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('entrevista')->label('Anotar a reunião')->icon('heroicon-o-clipboard-document-check')->color('primary')
            ->visible(fn (ResearchApplication $r) => in_array($r->status, [ResearchApplication::STATUS_NEW, ResearchApplication::STATUS_INTERVIEW, ResearchApplication::STATUS_INTERVIEWED], true))
            ->fillForm(fn (ResearchApplication $r) => [
                'answers' => array_keys(array_filter($r->interview_answers ?? [])),
                'notes' => $r->interview_notes,
            ])
            ->form(fn (ResearchApplication $r) => [
                Forms\Components\CheckboxList::make('answers')->label('Roteiro (marque o que ficou OK)')
                    ->options(array_combine($r->researchCall->texts('interview_questions'), $r->researchCall->texts('interview_questions')) ?: []),
                Forms\Components\Textarea::make('notes')->label('Anotações')->rows(3)->maxLength(3000),
            ])
            ->action(function (ResearchApplication $r, array $data) {
                $answers = collect($r->researchCall->texts('interview_questions'))
                    ->mapWithKeys(fn ($q) => [$q => in_array($q, $data['answers'] ?? [], true)])->all();
                static::service()->recordInterview($r, $answers, $data['notes'] ?? null);
                Notification::make()->title('Entrevista registrada')->success()->send();
            });
    }

    protected static function approveAction(): Tables\Actions\Action
    {
        $options = function (ResearchApplication $r, string $field) {
            $taken = $r->researchCall->applications()
                ->where('id', '!=', $r->id)->where('status', ResearchApplication::STATUS_APPROVED)
                ->pluck('name', $field)->filter()->all();

            return collect($r->researchCall->projectOptions())
                ->map(fn ($label, $code) => isset($taken[$code]) ? "{$label}  (ocupado: {$taken[$code]})" : $label)
                ->all();
        };

        return Tables\Actions\Action::make('aprovar')->label('Aprovar')->icon('heroicon-o-check-badge')->color('success')
            ->modalHeading(fn (ResearchApplication $r) => "Aprovar {$r->name} e escolher os projetos")
            ->visible(fn (ResearchApplication $r) => $r->email_verified_at && $r->adhesion_sha256 && !in_array($r->status, [ResearchApplication::STATUS_APPROVED, ResearchApplication::STATUS_WITHDRAWN, ResearchApplication::STATUS_REMOVED], true))
            ->modalDescription(fn (ResearchApplication $r) => 'Preferências do aluno: ' . (implode(', ', $r->project_preferences ?? []) ?: 'nenhum projeto marcado')
                . '. Ele(a) recebe um e-mail com os códigos, o convite para criar o login (se ainda não tem conta) e os próximos passos.')
            ->form(fn (ResearchApplication $r) => [
                Forms\Components\Select::make('lead')->label('Líder (1º autor) do projeto')->required()->native(false)->searchable()
                    ->options($options($r, 'lead_project_code'))
                    ->disableOptionWhen(fn (string $label) => str_contains($label, '(ocupado:')),
                Forms\Components\Select::make('reviewer')->label('Revisor(a) cego(a) (coautor) do projeto')->native(false)->searchable()
                    ->options($options($r, 'reviewer_project_code'))
                    ->disableOptionWhen(fn (string $label) => str_contains($label, '(ocupado:'))
                    ->different('lead')
                    ->helperText('Na dupla cruzada, o líder do projeto B costuma ser o revisor cego do projeto A.'),
                Forms\Components\Textarea::make('message')->label('Recado (opcional, vai no e-mail)')->rows(2)->maxLength(1500),
            ])
            ->action(function (ResearchApplication $r, array $data) {
                try {
                    $invite = static::service()->approve($r, $data['lead'], $data['reviewer'] ?? null, $data['message'] ?? null);
                } catch (\RuntimeException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }
                Notification::make()
                    ->title("{$r->firstName()} aprovado(a) e avisado(a) por e-mail")
                    ->body($invite ? "Convite para criar o login: {$invite}" : 'Já tinha conta no portal.')
                    ->success()->persistent()->send();
            });
    }

    protected static function redirectAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('linha')->label('Mudar de linha')->icon('heroicon-o-arrows-right-left')->color('info')
            ->visible(fn (ResearchApplication $r) => !$r->isClosed())
            ->modalHeading(fn (ResearchApplication $r) => "Direcionar {$r->name} para outra linha")
            ->modalDescription(fn (ResearchApplication $r) => 'Hoje: ' . (implode('; ', $r->axis_preferences ?? []) ?: '—')
                . (($r->project_preferences ?? []) ? ' · projetos ' . implode(', ', $r->project_preferences) : '')
                . '. A mudança fica no histórico. Para aprovar direto em um projeto de outra linha, você também pode usar "Aprovar".')
            ->fillForm(fn () => ['notify' => true])
            ->form(fn (ResearchApplication $r) => [
                Forms\Components\Select::make('axis')->label('Nova linha (eixo)')->required()->native(false)->live()
                    ->options(array_combine($r->researchCall->axisNames(), $r->researchCall->axisNames())),
                Forms\Components\CheckboxList::make('projects')->label('Projetos de preferência nessa linha (opcional, até 3)')
                    ->options(fn (Forms\Get $get) => collect($r->researchCall->projects ?? [])->where('axis', $get('axis'))
                        ->mapWithKeys(fn ($p) => [$p['code'] => $p['code'] . ' — ' . $p['area']])->all())
                    ->maxItems(3)
                    ->visible(fn (Forms\Get $get) => filled($get('axis'))),
                Forms\Components\Toggle::make('notify')->label('Avisar o aluno por e-mail'),
                Forms\Components\Textarea::make('message')->label('Recado (opcional, vai no e-mail)')->rows(2)->maxLength(1000),
            ])
            ->action(function (ResearchApplication $r, array $data) {
                static::service()->redirect($r, $data['axis'], $data['projects'] ?? [], $data['message'] ?? null, (bool) ($data['notify'] ?? false));
                Notification::make()->title("{$r->firstName()} direcionado(a) para {$data['axis']}")->success()->send();
            });
    }

    protected static function reallocateAction(): Tables\Actions\Action
    {
        $options = fn (ResearchApplication $r, string $field) => collect($r->researchCall->projectOptions())
            ->map(function ($label, $code) use ($r, $field) {
                $taken = $r->researchCall->applications()->where('id', '!=', $r->id)
                    ->where('status', ResearchApplication::STATUS_APPROVED)->where($field, $code)->value('name');

                return $taken ? "{$label}  (ocupado: {$taken})" : $label;
            })->all();

        return Tables\Actions\Action::make('realocar')->label('Realocar projetos')->icon('heroicon-o-arrows-right-left')->color('info')
            ->visible(fn (ResearchApplication $r) => $r->status === ResearchApplication::STATUS_APPROVED)
            ->modalHeading(fn (ResearchApplication $r) => "Realocar {$r->name}")
            ->modalDescription('Troca o projeto que o aluno lidera e/ou o de revisor cego. O acesso de triador é movido entre as Revisões Sistemáticas vinculadas, e o aluno recebe um e-mail com os novos códigos.')
            ->fillForm(fn (ResearchApplication $r) => ['lead' => $r->lead_project_code, 'reviewer' => $r->reviewer_project_code])
            ->form(fn (ResearchApplication $r) => [
                Forms\Components\Select::make('lead')->label('Líder (1º autor) do projeto')->required()->native(false)->searchable()
                    ->options($options($r, 'lead_project_code'))
                    ->disableOptionWhen(fn (string $label) => str_contains($label, '(ocupado:')),
                Forms\Components\Select::make('reviewer')->label('Revisor(a) cego(a) (coautor) do projeto')->native(false)->searchable()
                    ->options($options($r, 'reviewer_project_code'))
                    ->disableOptionWhen(fn (string $label) => str_contains($label, '(ocupado:'))
                    ->different('lead'),
                Forms\Components\Textarea::make('message')->label('Recado (opcional, vai no e-mail)')->rows(2)->maxLength(1000),
            ])
            ->action(function (ResearchApplication $r, array $data) {
                try {
                    static::service()->reallocate($r, $data['lead'], $data['reviewer'] ?? null, $data['message'] ?? null);
                } catch (\RuntimeException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }
                Notification::make()->title("{$r->firstName()} realocado(a) e avisado(a) por e-mail")->success()->send();
            });
    }

    public static function whatsappUrl(ResearchApplication $r): string
    {
        $digits = preg_replace('/\D/', '', $r->phone);
        if (strlen($digits) <= 11) {
            $digits = '55' . $digits;
        }
        $text = "Olá, {$r->firstName()}! Aqui é o Prof. Atilio, sobre a sua inscrição na seleção \"{$r->researchCall->title}\".";

        return 'https://wa.me/' . $digits . '?text=' . rawurlencode($text);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListResearchApplications::route('/'),
        ];
    }
}
