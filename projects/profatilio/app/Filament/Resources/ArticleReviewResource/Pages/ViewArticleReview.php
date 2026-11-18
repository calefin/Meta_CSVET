<?php

namespace App\Filament\Resources\ArticleReviewResource\Pages;

use App\Filament\Resources\ArticleReviewResource;
use App\Models\ArticleReview;
use App\Models\ArticleReviewer;
use App\Services\ArticleReviewService;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewArticleReview extends ViewRecord
{
    protected static string $resource = ArticleReviewResource::class;

    public function getHeading(): string
    {
        return 'Processo de revisão';
    }

    public function getSubheading(): ?string
    {
        return \Illuminate\Support\Str::limit($this->record->title, 140);
    }

    protected function service(): ArticleReviewService
    {
        return app(ArticleReviewService::class);
    }

    protected function active(): ?ArticleReviewer
    {
        return $this->record->reviewers()->where('status', ArticleReviewer::STATUS_IN_REVIEW)->first();
    }

    protected function refreshRecord(): void
    {
        $this->record->refresh();
        $this->record->unsetRelation('reviewers');
    }

    protected function getHeaderActions(): array
    {
        $status = fn () => $this->record->status;
        $open = fn () => in_array($status(), [ArticleReview::STATUS_DRAFT, ArticleReview::STATUS_IN_REVIEW, ArticleReview::STATUS_REVIEWS_DONE], true);
        $inReview = fn () => $status() === ArticleReview::STATUS_IN_REVIEW && $this->active() !== null;

        return [
            Actions\Action::make('iniciar')
                ->label('Enviar despacho')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->visible(fn () => $status() === ArticleReview::STATUS_DRAFT)
                ->requiresConfirmation()
                ->modalHeading('Enviar o despacho e iniciar a revisão?')
                ->modalDescription(function () {
                    $first = $this->record->participants()->first();

                    if (!$first) {
                        return 'Nenhum participante cadastrado ainda. Feche esta janela e use "Participantes → Incluir participante" (ou "Editar") antes de enviar o despacho.';
                    }

                    return 'O primeiro da fila (' . ($first?->name ?? '—') . ') recebe agora o link para baixar o arquivo'
                        . ($first && !$first->email ? ' — ele(a) NÃO tem e-mail: copie o link depois e envie por WhatsApp' : '')
                        . '. Os demais recebem o link para preencher a ficha e acompanhar.';
                })
                ->action(function () {
                    try {
                        $this->service()->start($this->record);
                    } catch (\RuntimeException $e) {
                        Notification::make()->title('Não foi possível iniciar')->body($e->getMessage())->danger()->send();
                        return;
                    }
                    $this->refreshRecord();
                    Notification::make()->title('Despacho enviado')->body('Agora está com ' . ($this->active()?->name ?? 'o primeiro participante') . '.')->success()->send();
                }),

            Actions\Action::make('aviso')
                ->label('Avisar a vez')
                ->icon('heroicon-o-bell-alert')
                ->color('warning')
                ->visible(fn () => $inReview() && $this->active()?->email)
                ->modalHeading(fn () => 'Enviar aviso a ' . $this->active()?->name)
                ->modalDescription('O e-mail sai com o prazo, o link de acesso e a sua mensagem, se houver.')
                ->form([
                    Forms\Components\Textarea::make('message')
                        ->label('Mensagem (opcional)')
                        ->placeholder('Ex.: Precisamos submeter até sexta, consegue devolver até quarta?')
                        ->rows(3)
                        ->maxLength(2000),
                ])
                ->action(function (array $data) {
                    $reviewer = $this->active();
                    if (!$reviewer) {
                        return;
                    }
                    $this->service()->sendReminder($reviewer, $data['message'] ?? null);
                    $this->refreshRecord();
                    Notification::make()->title('Aviso enviado para ' . $reviewer->name)->success()->send();
                }),

            Actions\Action::make('trazerDeVolta')
                ->label('Encerrar revisões e trazer para mim')
                ->icon('heroicon-o-arrow-uturn-down')
                ->color('danger')
                ->visible(fn () => $status() === ArticleReview::STATUS_IN_REVIEW)
                ->modalHeading('Encerrar a rodada e trazer o trabalho de volta?')
                ->modalDescription(function () {
                    $active = $this->active();
                    $waiting = $this->record->reviewers()->where('status', ArticleReviewer::STATUS_WAITING)->count();

                    return ($active ? "{$active->name} perde o acesso ao arquivo e é avisado(a). " : '')
                        . ($waiting ? "{$waiting} pessoa(s) da fila não vão mais receber o arquivo. " : '')
                        . 'Ninguém sai do processo (ficha e autoria ficam). A versão atual fica com você para terminar e registrar o envio à revista.';
                })
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label('Motivo / recado (opcional — vai no histórico e no aviso a quem estava na vez)')
                        ->rows(2)
                        ->maxLength(300),
                ])
                ->modalSubmitActionLabel('Encerrar e trazer para mim')
                ->action(function (array $data) {
                    try {
                        $this->service()->bringBack($this->record, $data['reason'] ?? null);
                    } catch (\RuntimeException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                        return;
                    }
                    $this->refreshRecord();
                    Notification::make()->title('Voltou para você')->body('Baixe a versão atual, termine e use "Registrar envio à revista e fechar".')->success()->send();
                }),

            Actions\ActionGroup::make([
                Actions\Action::make('adicionarParticipante')
                    ->label('Incluir participante')
                    ->icon('heroicon-o-user-plus')
                    ->modalDescription(fn () => $status() === ArticleReview::STATUS_REVIEWS_DONE
                        ? 'A rodada já terminou: a pessoa nova recebe a versão atual imediatamente.'
                        : 'Entra no fim da fila (depois dá para reordenar). Com e-mail, recebe o convite para preencher a ficha.')
                    ->form(ArticleReviewResource::participantFields())
                    ->action(function (array $data) {
                        if (filled($data['email']) && $this->record->participants()->where('email', mb_strtolower(trim($data['email'])))->exists()) {
                            Notification::make()->title('Esse e-mail já está no processo.')->danger()->send();
                            return;
                        }
                        $reviewer = $this->service()->addParticipant($this->record, $data);
                        $this->refreshRecord();
                        Notification::make()->title($reviewer->name . ' incluído(a) no fim da fila')->success()->send();
                    }),

                Actions\Action::make('reordenar')
                    ->label('Reordenar a fila')
                    ->icon('heroicon-o-arrows-up-down')
                    ->visible(fn () => $this->record->reviewers()->where('status', ArticleReviewer::STATUS_WAITING)->count() > 1)
                    ->modalDescription('Arraste para mudar a ordem de quem ainda está aguardando. Quem já entregou ou está na vez não muda de lugar. A ordem de autoria é separada (em "Editar dados").')
                    ->fillForm(fn () => [
                        'order' => $this->record->reviewers()->where('status', ArticleReviewer::STATUS_WAITING)->get()
                            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])->all(),
                    ])
                    ->form([
                        Forms\Components\Repeater::make('order')
                            ->label('Fila (de cima para baixo)')
                            ->schema([
                                Forms\Components\Hidden::make('id'),
                                Forms\Components\Hidden::make('name'),
                            ])
                            ->itemLabel(fn (array $state) => $state['name'] ?? '')
                            ->reorderable()
                            ->reorderableWithButtons()
                            ->addable(false)
                            ->deletable(false)
                            ->collapsible(false),
                    ])
                    ->action(function (array $data) {
                        $this->service()->reorderWaiting($this->record, collect($data['order'] ?? [])->pluck('id')->all());
                        $this->refreshRecord();
                        Notification::make()->title('Fila reordenada')->success()->send();
                    }),

                Actions\Action::make('trocarVez')
                    ->label('Trocar quem está na vez')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->visible($inReview)
                    ->modalHeading(fn () => 'Tirar a vez de ' . $this->active()?->name)
                    ->modalDescription('Use quando a pessoa está demorando. Tudo fica registrado no histórico.')
                    ->form([
                        Forms\Components\Select::make('next')
                            ->label('Quem assume a vez agora?')
                            ->options(fn () => $this->record->reviewers()->where('status', ArticleReviewer::STATUS_WAITING)->pluck('name', 'id')
                                ->mapWithKeys(fn ($name, $id) => [(string) $id => $name])
                                ->put('new', '➕ Uma pessoa nova'))
                            ->required()
                            ->live(),
                        Forms\Components\TextInput::make('name')->label('Nome')->required()->maxLength(255)->visible(fn (Get $get) => $get('next') === 'new'),
                        Forms\Components\TextInput::make('email')->label('E-mail')->email()->maxLength(255)->visible(fn (Get $get) => $get('next') === 'new'),
                        Forms\Components\TextInput::make('phone')->label('Telefone')->tel()->maxLength(40)->visible(fn (Get $get) => $get('next') === 'new'),
                        Forms\Components\Radio::make('fate')
                            ->label(fn () => 'E ' . $this->active()?->name . '?')
                            ->options([
                                'requeue' => 'Volta para o fim da fila (revisa depois)',
                                'remove' => 'Sai do processo (perde o acesso; fica no histórico)',
                            ])
                            ->default('requeue')
                            ->required(),
                        Forms\Components\TextInput::make('reason')->label('Motivo (opcional, fica no histórico)')->maxLength(250),
                    ])
                    ->action(function (array $data) {
                        try {
                            $next = $this->service()->swapCurrent(
                                $this->record,
                                $data['next'] === 'new' ? null : (int) $data['next'],
                                $data['next'] === 'new' ? ['name' => $data['name'], 'email' => $data['email'] ?? null, 'phone' => $data['phone'] ?? null] : null,
                                $data['fate'],
                                $data['reason'] ?? null
                            );
                        } catch (\RuntimeException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                            return;
                        }
                        $this->refreshRecord();
                        Notification::make()->title('Agora está com ' . $next->name)->success()->send();
                    }),

                Actions\Action::make('prorrogar')
                    ->label('Prorrogar prazo de quem está na vez')
                    ->icon('heroicon-o-calendar-days')
                    ->visible($inReview)
                    ->form([
                        Forms\Components\TextInput::make('days')
                            ->label('Mais quantos dias?')
                            ->numeric()->integer()->minValue(1)->maxValue(60)->default(3)->required(),
                    ])
                    ->action(function (array $data) {
                        $reviewer = $this->active();
                        if (!$reviewer) {
                            return;
                        }
                        $this->service()->extendDeadline($reviewer, (int) $data['days']);
                        Notification::make()->title('Novo prazo: ' . $reviewer->fresh()->due_at->format('d/m/Y'))->success()->send();
                    }),

                Actions\Action::make('pular')
                    ->label('Pular quem está na vez')
                    ->icon('heroicon-o-forward')
                    ->color('warning')
                    ->visible($inReview)
                    ->requiresConfirmation()
                    ->modalDescription(fn () => ($this->active()?->name ?? 'A pessoa') . ' perde o acesso e o arquivo segue (na versão atual) para o próximo da fila.')
                    ->action(function () {
                        $reviewer = $this->active();
                        if (!$reviewer) {
                            return;
                        }
                        $this->service()->skip($reviewer);
                        $this->refreshRecord();
                        Notification::make()->title($reviewer->name . ' foi pulado(a)')->success()->send();
                    }),

                Actions\Action::make('linkCadastro')
                    ->label('Link de cadastro do processo')
                    ->icon('heroicon-o-qr-code')
                    ->modalHeading('Link de cadastro do processo')
                    ->modalDescription('Compartilhe no particular. Quem abrir escolhe o próprio nome na lista (entre os cadastrados sem e-mail) ou entra como participante novo no fim da fila. Você é avisado a cada cadastro.')
                    ->fillForm(fn () => [
                        'enabled' => $this->record->signup_enabled,
                        'link' => $this->record->signupUrl(),
                        'rotate' => false,
                    ])
                    ->form([
                        Forms\Components\Toggle::make('enabled')->label('Link ativo'),
                        Forms\Components\TextInput::make('link')
                            ->label('Link')
                            ->readOnly()
                            ->extraInputAttributes(['onclick' => 'this.select()']),
                        Forms\Components\Checkbox::make('rotate')->label('Gerar link novo (o anterior para de funcionar)'),
                    ])
                    ->modalSubmitActionLabel('Salvar')
                    ->action(function (array $data) {
                        $this->record->update(['signup_enabled' => (bool) $data['enabled']]);
                        $url = $this->record->signupUrl((bool) ($data['rotate'] ?? false));
                        $this->record->logEvent('Link de cadastro ' . ($data['enabled'] ? 'ativado' : 'desativado') . (!empty($data['rotate']) ? ' (link novo gerado)' : ''));
                        $this->refreshRecord();
                        Notification::make()
                            ->title($data['enabled'] ? 'Link de cadastro ativo' : 'Link de cadastro desativado')
                            ->body($data['enabled'] ? $url : null)
                            ->success()
                            ->persistent()
                            ->send();
                    }),
            ])
                ->label('Participantes')
                ->icon('heroicon-o-users')
                ->button()
                ->color('gray')
                ->visible($open),

            Actions\ActionGroup::make([
                Actions\Action::make('restaurar')
                    ->label('Restaurar versão anterior')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->visible(fn () => count($this->record->allVersions()) > 1 && !$this->record->isClosed())
                    ->modalDescription('A versão escolhida vira a base atual (cópia com número novo). Nada é apagado. Quem estiver na vez é avisado para baixar de novo.')
                    ->form([
                        Forms\Components\Select::make('key')
                            ->label('Versão')
                            ->options(fn () => collect($this->record->allVersions())
                                ->slice(0, -1)
                                ->mapWithKeys(fn ($v) => [$v['key'] => 'v' . $v['version'] . ' — ' . $v['label'] . ' (' . $v['filename'] . ')']))
                            ->required(),
                        Forms\Components\TextInput::make('reason')->label('Motivo (fica no histórico)')->maxLength(300),
                    ])
                    ->action(function (array $data) {
                        try {
                            $restored = $this->service()->restoreVersion($this->record, $data['key'], $data['reason'] ?? null);
                        } catch (\RuntimeException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                            return;
                        }
                        $this->refreshRecord();
                        Notification::make()->title('Base atual: ' . $restored['filename'])->success()->send();
                    }),

                Actions\Action::make('complementares')
                    ->label('Anexar documentos complementares')
                    ->icon('heroicon-o-paper-clip')
                    ->visible(fn () => !$this->record->isClosed())
                    ->modalDescription('Figuras, tabelas, normas da revista, carta... Ficam disponíveis para quem estiver na vez.')
                    ->form([
                        ArticleReviewResource::supplementaryUploadField('files')
                            ->helperText('Até 10 arquivos de ' . (ArticleReviewService::MAX_SUPPLEMENTARY_KB / 1024) . ' MB cada. Os arquivos sobem um de cada vez — espere todos ficarem verdes antes de clicar em Enviar.'),
                        Forms\Components\TextInput::make('description')->label('Descrição (opcional)')->maxLength(250),
                    ])
                    ->action(function (array $data) {
                        [$added, $errors] = ArticleReviewResource::attachSupplementary($this->record, $data, 'files');
                        $this->refreshRecord();
                        ArticleReviewResource::notifyAttached($added, $errors);
                    }),

                Actions\Action::make('autores')
                    ->label('Bloco de autores p/ submissão')
                    ->icon('heroicon-o-identification')
                    ->modalHeading('Autores, filiações, ORCID e CRediT')
                    ->modalDescription('Ordenado pela ordem de AUTORIA (defina em "Editar dados" de cada participante). Clique para selecionar e copie.')
                    ->fillForm(fn () => ['text' => $this->service()->authorsBlock($this->record)])
                    ->form([
                        Forms\Components\Textarea::make('text')->hiddenLabel()->rows(18)->readOnly()
                            ->extraInputAttributes(['onclick' => 'this.select()', 'style' => 'font-family: ui-monospace, monospace; font-size: .8rem']),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->action(fn () => null),

                Actions\Action::make('dossie')
                    ->label('Dossiê de auditoria (PDF)')
                    ->icon('heroicon-o-document-text')
                    ->url(fn () => route('article-review.admin.dossier', ['articleReview' => $this->record]))
                    ->openUrlInNewTab(),
            ])
                ->label('Arquivos e relatórios')
                ->icon('heroicon-o-folder')
                ->button()
                ->color('gray'),

            Actions\Action::make('checklistSubmissao')
                ->label('Checklist de submissão')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('gray')
                ->visible(fn () => \App\Support\ArticleTypes::get($this->record->article_type) !== null)
                ->modalHeading(fn () => 'Checklist — ' . \App\Support\ArticleTypes::get($this->record->article_type)['label'])
                ->modalDescription('Itens com 🤖 já foram comprovados pelo sistema (revisão sistemática vinculada). O envio à revista só é registrado sem pendências.')
                ->fillForm(fn () => ['items' => collect(\App\Support\ArticleTypes::status($this->record))
                    ->map(fn ($i) => $i['state'] === 'auto' ? 'pending' : $i['state'])->all()])
                ->form(fn () => collect(\App\Support\ArticleTypes::status($this->record))->map(fn ($item, $key) => Forms\Components\ToggleButtons::make("items.{$key}")
                    ->label(($item['state'] === 'auto' ? '🤖 ' : '') . $item['label'])
                    ->helperText($item['state'] === 'auto' ? 'Comprovado pelo sistema: ' . $item['evidence'] : null)
                    ->options(['pending' => 'Pendente', 'done' => 'Feito', 'na' => 'Não se aplica'])
                    ->colors(['pending' => 'gray', 'done' => 'success', 'na' => 'info'])
                    ->inline()
                    ->grouped())->values()->all())
                ->modalWidth('5xl')
                ->action(function (array $data) {
                    $valid = array_keys(\App\Support\ArticleTypes::checklist($this->record->article_type));
                    $items = array_filter(
                        array_intersect_key($data['items'] ?? [], array_flip($valid)),
                        fn ($v) => in_array($v, ['done', 'na'], true)
                    );
                    $this->record->update(['submission_checklist' => $items]);
                    $this->record->logEvent('Checklist de submissão atualizado', count($items) . '/' . count($valid) . ' itens resolvidos');
                    $this->refreshRecord();
                    Notification::make()->title('Checklist salvo')->success()->send();
                }),

            Actions\Action::make('versaoFinal')
                ->label('Registrar envio à revista e fechar')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn () => in_array($status(), [ArticleReview::STATUS_REVIEWS_DONE, ArticleReview::STATUS_IN_REVIEW, ArticleReview::STATUS_SUBMITTED], true))
                ->modalDescription(fn () => $status() === ArticleReview::STATUS_IN_REVIEW
                    ? 'Atenção: ainda há revisão em andamento. Fechar agora encerra a vez de quem está com o arquivo.'
                    : 'Todos os participantes são avisados por e-mail do envio e do encerramento do processo.')
                ->fillForm(fn () => [
                    'journal' => $this->record->journal,
                    'submitted_at' => $this->record->submitted_at ?? now(),
                ])
                ->form([
                    Forms\Components\FileUpload::make('final_file')
                        ->label('Versão final consolidada (recomendado)')
                        ->helperText('Se não enviar, a última versão revisada fica como referência.')
                        ->disk('local')
                        ->directory('article_reviews/final_tmp')
                        ->visibility('private')
                        ->maxSize(ArticleReviewService::MAX_FILE_KB),
                    Forms\Components\TextInput::make('journal')->label('Revista/editora para a qual foi enviado')->required()->maxLength(255),
                    Forms\Components\DatePicker::make('submitted_at')->label('Data do envio')->required(),
                ])
                ->action(function (array $data) {
                    $tmp = is_array($data['final_file'] ?? null) ? reset($data['final_file']) : ($data['final_file'] ?? null);
                    try {
                        $this->service()->markSubmitted($this->record, $data, $tmp ?: null);
                    } catch (\RuntimeException $e) {
                        Notification::make()->title('Envio não registrado')->body($e->getMessage())->danger()->persistent()->send();

                        return;
                    }
                    $this->refreshRecord();
                    Notification::make()->title('Envio registrado — processo fechado')->success()->send();
                }),

            Actions\ActionGroup::make([
                Actions\EditAction::make()
                    ->visible(fn () => $status() !== ArticleReview::STATUS_CANCELLED),

                Actions\Action::make('cancelar')
                    ->label('Cancelar processo')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn () => in_array($status(), [ArticleReview::STATUS_IN_REVIEW, ArticleReview::STATUS_REVIEWS_DONE], true))
                    ->requiresConfirmation()
                    ->modalDescription('Todos os links deixam de funcionar. Os arquivos já devolvidos continuam aqui.')
                    ->action(function () {
                        $this->service()->cancel($this->record);
                        $this->refreshRecord();
                        Notification::make()->title('Processo cancelado')->success()->send();
                    }),

                ArticleReviewResource::deleteProcessAction(Actions\Action::make('excluir'))
                    ->successRedirectUrl(ArticleReviewResource::getUrl('index')),
            ])
                ->label('Mais')
                ->icon('heroicon-o-ellipsis-vertical')
                ->button()
                ->color('gray'),
        ];
    }
}
