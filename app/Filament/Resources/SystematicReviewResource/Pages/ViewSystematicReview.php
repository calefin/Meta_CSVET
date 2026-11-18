<?php

namespace App\Filament\Resources\SystematicReviewResource\Pages;

use App\Filament\Resources\ArticleReviewResource;
use App\Filament\Resources\SystematicReviewResource;
use App\Models\SystematicReview;
use App\Services\SystematicReviewProtocolService;
use App\Services\SystematicReviewService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Storage;

class ViewSystematicReview extends ViewRecord
{
    protected static string $resource = SystematicReviewResource::class;

    /** Título curto: o título completo (em inglês, longo) espremia os botões do cabeçalho; ele vai no topo do conteúdo. */
    public function getHeading(): string
    {
        $code = $this->record->selectionCode();

        return 'Revisão sistemática' . ($code ? " · {$code}" : '') . ($this->record->protocol_code ? " ({$this->record->protocol_code})" : '');
    }

    protected function service(): SystematicReviewService
    {
        return app(SystematicReviewService::class);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('importar')
                ->label('Importar registros')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->visible(fn () => in_array($this->record->status, [SystematicReview::STATUS_PROTOCOL], true))
                ->modalDescription('Envie o .json gerado pelo script (recomendado) ou exportações .ris / .nbib / .csv. Duplicatas são removidas sozinhas (DOI, PMID e título). Pode importar vários arquivos, um de cada vez.')
                ->form([
                    Forms\Components\FileUpload::make('file')
                        ->label('Arquivo')
                        ->disk('local')
                        ->directory('systematic_reviews/tmp')
                        ->visibility('private')
                        ->storeFileNamesIn('file_name')
                        ->maxSize(40960)
                        ->required(),
                    Forms\Components\TextInput::make('source')
                        ->label('Base de origem (só para .ris/.csv)')
                        ->placeholder('ex.: Scopus, Web of Science, CAB Abstracts')
                        ->maxLength(60),
                ])
                ->action(function (array $data) {
                    $disk = Storage::disk('local');
                    $path = $data['file'];
                    try {
                        $result = $this->service()->import($this->record, $disk->get($path), $data['file_name'] ?? basename($path), $data['source'] ?? null);
                    } catch (\RuntimeException $e) {
                        Notification::make()->title('Não importado')->body($e->getMessage())->danger()->persistent()->send();

                        return;
                    } finally {
                        $disk->delete($path);
                    }
                    Notification::make()
                        ->title("{$result['new']} registros novos importados")
                        ->body("{$result['parsed']} lidos ({$result['format']}) · {$result['duplicates']} duplicatas removidas")
                        ->success()
                        ->send();
                }),

            Actions\Action::make('completarResumos')
                ->label('Completar resumos')
                ->icon('heroicon-o-document-plus')
                ->color('gray')
                ->visible(fn () => in_array($this->record->status, [SystematicReview::STATUS_PROTOCOL, SystematicReview::STATUS_SCREENING], true) && $this->record->records()->exists())
                ->modalDescription('Envie o .json de resumos completados (gerado pelo script). Só preenche o resumo que está vazio em registros já importados: não cria registro e não muda as contagens do PRISMA. Depois de liberar a triagem, só preenche registro que nenhum aluno votou (a dupla continua julgando o mesmo texto).')
                ->form([
                    Forms\Components\FileUpload::make('file')
                        ->label('Arquivo .json')
                        ->disk('local')
                        ->directory('systematic_reviews/tmp')
                        ->visibility('private')
                        ->maxSize(40960)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $disk = Storage::disk('local');
                    try {
                        $s = $this->service()->completeAbstracts($this->record, $disk->get($data['file']));
                    } catch (\RuntimeException $e) {
                        Notification::make()->title('Nada alterado')->body($e->getMessage())->danger()->persistent()->send();

                        return;
                    } finally {
                        $disk->delete($data['file']);
                    }
                    Notification::make()
                        ->title("{$s['filled']} resumos preenchidos")
                        ->body("{$s['read']} lidos · {$s['not_found']} não localizados · {$s['already']} já tinham resumo · {$s['voted']} já votados (sem mudança)")
                        ->success()
                        ->persistent()
                        ->send();
                }),

            Actions\Action::make('liberar')
                ->label('Liberar triagem')
                ->icon('heroicon-o-play')
                ->color('primary')
                ->visible(fn () => $this->record->status === SystematicReview::STATUS_PROTOCOL)
                ->requiresConfirmation()
                ->modalDescription(fn () => ($p = $this->service()->startProblems($this->record))
                    ? 'Ainda falta: ' . implode(' ', $p)
                    : 'Os triadores são avisados. Depois de liberada, o protocolo não deve mudar (mudanças ficam registradas como desvio).')
                ->action(function () {
                    try {
                        $this->service()->start($this->record);
                    } catch (\RuntimeException $e) {
                        Notification::make()->title('Não liberada')->body($e->getMessage())->danger()->persistent()->send();

                        return;
                    }
                    Notification::make()->title('Triagem liberada e triadores avisados')->success()->send();
                }),

            Actions\Action::make('liberarPiloto')
                ->label('Liberar triagem completa')
                ->icon('heroicon-o-forward')
                ->color('primary')
                ->visible(fn () => $this->record->status === SystematicReview::STATUS_SCREENING && $this->record->inPilot() && !$this->record->is_training)
                ->modalDescription(function () {
                    $rel = $this->service()->pilotReliability($this->record);
                    $k3 = $this->service()->pilotKappa($this->record);
                    $kb = $this->service()->pilotKappaBinary($this->record);
                    $kEff = $this->service()->pilotEffectiveKappa($this->record);
                    $prev = $this->service()->pilotPrevalenceRule($this->record);
                    $status = ($kEff !== null && $kEff >= 0.60)
                        ? "✔ Apto para liberação pelo protocolo OSF (κ efetivo = " . SystematicReviewService::fmt($kEff) . " ≥ 0,60)."
                        : ($prev['passes']
                            ? '✔ Apto pela regra da prevalência baixa (paradoxo do kappa): ' . $prev['advanced'] . '/' . $prev['n'] . ' avançados, concordância binária '
                                . SystematicReviewService::fmt($prev['agreement'] * 100, 1) . '% e PABAK ' . SystematicReviewService::fmt($prev['pabak']) . '.'
                            : "⚠ Ambos os kappas estão abaixo de 0,60: realize a reunião de alinhamento com a dupla e preencha a justificativa auditável abaixo para liberar.");

                    return "Confiabilidade da rodada:\n" . $rel['summary']
                        . "\n\n{$status}\n\nNota OSF (item 11b): No título/resumo, votos 'talvez' e 'incluir' avançam ambos para texto completo. O κ binário reflete a real concordância de elegibilidade preliminar.\nAs decisões do piloto continuam valendo.";
                })
                ->form(fn () => $this->service()->pilotPassesGate($this->record) ? [] : [
                    Forms\Components\Textarea::make('justification')
                        ->label('Ambos os kappas estão abaixo de 0,60. O que foi feito antes de liberar?')
                        ->placeholder('Ex.: reunião de calibração e adjudicação em 09/11; critérios esclarecidos e desacordos dirimidos…')
                        ->required()
                        ->maxLength(1000),
                ])
                ->action(function (array $data) {
                    try {
                        $this->service()->releasePilot($this->record, $data['justification'] ?? null);
                    } catch (\RuntimeException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();

                        return;
                    }
                    Notification::make()->title('Triagem completa liberada')->success()->send();
                }),

            // Protocolo registrado: kappa < 0,60 → discutir os critérios e triar NOVA amostra-piloto.
            Actions\Action::make('novaRodadaPiloto')
                ->label(fn () => 'Nova rodada de calibração (' . ($this->record->pilotRound() + 1) . 'ª)')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn () => $this->record->status === SystematicReview::STATUS_SCREENING && $this->record->inPilot() && !$this->record->is_training)
                ->modalHeading('Nova rodada de calibração')
                ->modalDescription(fn () => "Rodada atual:\n" . $this->service()->pilotReliability($this->record)['summary']
                    . "\n\nA dupla recebe por e-mail uma NOVA amostra aleatória, com trabalhos diferentes dos anteriores. As decisões já dadas continuam valendo. Dê a devolutiva na conversa do projeto antes de abrir a nova rodada.")
                ->form([
                    Forms\Components\TextInput::make('size')->label('Registros na nova amostra')->numeric()->minValue(10)->maxValue(200)
                        ->default(fn () => (int) (data_get($this->record->pipeline, 'screening.stages.0.calibration.sample') ?: 50))->required(),
                ])
                ->action(function (array $data) {
                    try {
                        $added = $this->service()->newPilotRound($this->record, (int) $data['size']);
                    } catch (\RuntimeException $e) {
                        Notification::make()->title($e->getMessage())->danger()->persistent()->send();

                        return;
                    }
                    Notification::make()->title("Nova rodada aberta: {$added} registros. A dupla foi avisada.")->success()->send();
                }),

            Actions\Action::make('encerrar')
                ->label('Encerrar etapa')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn () => $this->service()->professorCanResolve($this->record))
                ->modalHeading(fn () => 'Encerrar a etapa "' . $this->record->stageLabel() . '"')
                ->modalDescription(fn () => 'Todos os trabalhos precisam ter decisão final (consenso dos triadores ou o seu desempate). Pendentes agora: '
                    . $this->service()->conflictsQuery($this->record)->count() . '.')
                ->form(fn () => $this->record->stage === SystematicReview::STAGE_TA ? [
                    Forms\Components\Radio::make('next')
                        ->label('Depois desta etapa')
                        ->options([
                            'full_text' => 'Seguir para a leitura do TEXTO COMPLETO com os mesmos triadores (recomendado — PRISMA)',
                            'done' => 'Encerrar a triagem aqui (eu faço a elegibilidade por fora)',
                        ])
                        ->default('full_text')
                        ->required(),
                ] : [])
                ->action(function (array $data) {
                    try {
                        $this->service()->closeStage($this->record, ($data['next'] ?? 'done') === 'full_text');
                    } catch (\RuntimeException $e) {
                        Notification::make()->title($e->getMessage())->warning()->send();

                        return;
                    }
                    Notification::make()->title('Etapa encerrada')->success()->send();
                }),

            Actions\ActionGroup::make([
                Actions\Action::make('esteira')
                    ->label('Atualizar esteira (fases)')
                    ->icon('heroicon-o-queue-list')
                    ->fillForm(fn () => ['done' => array_keys(array_filter($this->record->checklist ?? []))])
                    ->form([
                        Forms\Components\CheckboxList::make('done')
                            ->label('Fases concluídas')
                            ->options(fn () => $this->record->pipeline()),
                    ])
                    ->action(function (array $data) {
                        $old = $this->record->checklist ?? [];
                        $new = [];
                        foreach ($data['done'] ?? [] as $key) {
                            $new[$key] = $old[$key] ?? now()->format('Y-m-d');
                        }
                        $this->record->update(['checklist' => $new]);
                        Notification::make()->title('Esteira atualizada')->success()->send();
                    }),
                Actions\Action::make('criarArtigo')
                    ->label('Criar artigo (processo de revisão)')
                    ->icon('heroicon-o-document-plus')
                    ->url(fn () => ArticleReviewResource::getUrl('create', ['systematic_review' => $this->record->id])),
                Actions\Action::make('arquivar')
                    ->label(fn () => $this->record->status === SystematicReview::STATUS_ARCHIVED ? 'Desarquivar' : 'Arquivar')
                    ->icon('heroicon-o-archive-box')
                    ->requiresConfirmation()
                    ->visible(fn () => in_array($this->record->status, [SystematicReview::STATUS_SCREENING_DONE, SystematicReview::STATUS_ARCHIVED, SystematicReview::STATUS_PROTOCOL], true))
                    ->action(function () {
                        $archived = $this->record->status === SystematicReview::STATUS_ARCHIVED;
                        $this->record->update(['status' => $archived
                            ? ($this->record->screening_started_at ? SystematicReview::STATUS_SCREENING_DONE : SystematicReview::STATUS_PROTOCOL)
                            : SystematicReview::STATUS_ARCHIVED]);
                        $this->record->logEvent($archived ? 'Projeto desarquivado' : 'Projeto arquivado');
                    }),
            ])->label('Gerenciar')->icon('heroicon-o-cog-6-tooth')->button()->color('gray'),

            $this->osfActions(),

            $this->extractionActions(),

            Actions\ActionGroup::make([
                Actions\Action::make('dlPacoteOsf')->label('Pacote para o OSF (.zip, por componente, com SHA-256)')->icon('heroicon-o-archive-box-arrow-down')
                    ->url(fn () => route('systematic-review.export', [$this->record, 'pacote-osf'])),
                Actions\Action::make('dlFichaOsf')->label('Ficha OSF para copiar e colar (.txt)')->icon('heroicon-o-clipboard-document-list')
                    ->visible(fn () => filled($this->record->pipeline))
                    ->url(fn () => route('systematic-review.export', [$this->record, 'osf-ficha'])),
                Actions\Action::make('dlOsfJson')->label('Respostas do registro OSF (.json, API)')->icon('heroicon-o-code-bracket')
                    ->visible(fn () => filled($this->record->pipeline))
                    ->url(fn () => route('systematic-review.export', [$this->record, 'osf-json'])),
                Actions\Action::make('dlPipeline')->label('Protocolo importado (pipeline .json)')->icon('heroicon-o-code-bracket')
                    ->visible(fn () => filled($this->record->pipeline))
                    ->url(fn () => route('systematic-review.export', [$this->record, 'pipeline'])),
                Actions\Action::make('dlBusca')->label('Log das buscas (PNN_BUSCA_log.csv)')->icon('heroicon-o-magnifying-glass')
                    ->url(fn () => route('systematic-review.export', [$this->record, 'busca-log'])),
                Actions\Action::make('dlTriagemTa')->label(fn () => 'Decisões título/resumo (PNN_TRIAGEM_TA' . ($this->record->isStageClosed(SystematicReview::STAGE_TA) ? '' : ' — PARCIAL, não subir no OSF') . ')')->icon('heroicon-o-table-cells')
                    ->url(fn () => route('systematic-review.export', [$this->record, 'triagem-ta'])),
                Actions\Action::make('dlTriagemFt')->label(fn () => 'Decisões texto completo (PNN_TRIAGEM_FT' . ($this->record->isStageClosed(SystematicReview::STAGE_FT) ? '' : ' — PARCIAL, não subir no OSF') . ')')->icon('heroicon-o-table-cells')
                    ->url(fn () => route('systematic-review.export', [$this->record, 'triagem-ft'])),
                Actions\Action::make('dlPrismaCsv')->label('Contagens PRISMA + kappa (PNN_PRISMA_contagens.csv)')->icon('heroicon-o-chart-bar')
                    ->url(fn () => route('systematic-review.export', [$this->record, 'prisma-csv'])),
                Actions\Action::make('dlDesvios')->label('Desvios e emendas (PNN_DESVIOS.csv)')->icon('heroicon-o-exclamation-triangle')
                    ->url(fn () => route('systematic-review.export', [$this->record, 'desvios'])),
                Actions\Action::make('dlExtracao')->label('Modelo de extração (PNN_EXTRACAO_modelo.csv)')->icon('heroicon-o-table-cells')
                    ->visible(fn () => filled($this->record->pipeline))
                    ->url(fn () => route('systematic-review.export', [$this->record, 'extracao-modelo'])),
                Actions\Action::make('dlDicionario')->label('Dicionário de extração e RoB (.csv)')->icon('heroicon-o-book-open')
                    ->visible(fn () => filled($this->record->pipeline))
                    ->url(fn () => route('systematic-review.export', [$this->record, 'dicionario'])),
                Actions\Action::make('dlTravada')->label(fn () => 'Extração travada (PNN_EXTRACAO_travada_v' . $this->record->latestLock()?->version . ')')->icon('heroicon-o-lock-closed')
                    ->visible(fn () => (bool) $this->record->latestLock())
                    ->url(fn () => route('systematic-review.export', [$this->record, 'extracao-travada'])),
                Actions\Action::make('dlRob')->label(fn () => 'Risco de viés travado (PNN_RoB_v' . $this->record->latestLock()?->version . ')')->icon('heroicon-o-shield-exclamation')
                    ->visible(fn () => (bool) $this->record->latestLock())
                    ->url(fn () => route('systematic-review.export', [$this->record, 'rob-travado'])),
                Actions\Action::make('dlDuplicata')->label('Extrações em duplicata (auditoria, .csv)')->icon('heroicon-o-document-duplicate')
                    ->visible(fn () => $this->record->extractionOpen())
                    ->url(fn () => route('systematic-review.export', [$this->record, 'extracao-duplicata'])),
                Actions\Action::make('dlIa')->label('IA na extração: proposto × conferido (.csv)')->icon('heroicon-o-cpu-chip')
                    ->visible(fn () => $this->record->extractionOpen() && $this->record->llmAssistedExtraction())
                    ->url(fn () => route('systematic-review.export', [$this->record, 'ia-extracao'])),
                Actions\Action::make('dlIaPrompt')->label('IA na extração: prompt fixo (.txt)')->icon('heroicon-o-document-text')
                    ->visible(fn () => $this->record->llmAssistedExtraction())
                    ->url(fn () => route('systematic-review.export', [$this->record, 'ia-prompt'])),
                Actions\Action::make('dlContatos')->label('Contatos com autores (.csv)')->icon('heroicon-o-envelope')
                    ->visible(fn () => $this->record->extractionOpen())
                    ->url(fn () => route('systematic-review.export', [$this->record, 'contatos-autores'])),
                Actions\Action::make('dlGrade')->label('GRADE / resumo dos achados (.csv)')->icon('heroicon-o-chart-bar-square')
                    ->visible(fn () => $this->record->gradeRows()->exists())
                    ->url(fn () => route('systematic-review.export', [$this->record, 'grade'])),
                Actions\Action::make('dlAtas')->label('Atas de reunião (.csv)')->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->visible(fn () => $this->record->meetings()->exists())
                    ->url(fn () => route('systematic-review.export', [$this->record, 'atas'])),
                Actions\Action::make('dlProtocolo')->label('Protocolo PRISMA-P (.md, para o OSF)')->icon('heroicon-o-document-text')
                    ->url(fn () => route('systematic-review.export', [$this->record, 'protocolo'])),
                Actions\Action::make('dlRelatorio')->label('Relatório: PRISMA + kappa + métodos + termos')->icon('heroicon-o-printer')
                    ->url(fn () => route('systematic-review.export', [$this->record, 'relatorio']), true),
                Actions\Action::make('dlLog')->label('Planilha da triagem — todos os votos (.csv)')->icon('heroicon-o-table-cells')
                    ->url(fn () => route('systematic-review.export', [$this->record, 'triagem'])),
                Actions\Action::make('dlIncluidos')->label('Estudos incluídos (.ris → Zotero)')->icon('heroicon-o-check-circle')
                    ->url(fn () => route('systematic-review.export', [$this->record, 'incluidos-ris'])),
                Actions\Action::make('dlIncluidosCsv')->label('Estudos incluídos (.csv → planilha de extração)')->icon('heroicon-o-table-cells')
                    ->url(fn () => route('systematic-review.export', [$this->record, 'incluidos-csv'])),
                Actions\Action::make('dlKit')->label('Kit de importação (script + modelos)')->icon('heroicon-o-arrow-down-tray')
                    ->url(fn () => route('systematic-review.kit')),
            ])->label('Baixar')->icon('heroicon-o-arrow-down-tray')->button()->color('gray'),

            Actions\EditAction::make()->label('Editar protocolo'),
        ];
    }

    protected function protocolService(): SystematicReviewProtocolService
    {
        return app(SystematicReviewProtocolService::class);
    }

    /** Fases P7–P14: extração em duplicata, trava da base, manuscrito e submissão. */
    protected function extractionActions(): Actions\ActionGroup
    {
        $ex = app(\App\Services\SystematicReviewExtractionService::class);
        $problems = fn (array $p) => $p ? new \Illuminate\Support\HtmlString('<div style="color:#b91c1c;font-size:.85rem;white-space:pre-line">Ainda não dá:' . "\n• " . e(implode("\n• ", array_slice($p, 0, 12))) . '</div>') : null;

        return Actions\ActionGroup::make([
            Actions\Action::make('abrirExtracao')->label('Abrir extração (P7)')->icon('heroicon-o-table-cells')
                ->visible(fn () => !$this->record->extractionOpen())
                ->modalDescription(fn () => $problems($ex->openProblems($this->record))
                    ?? 'Cria um estudo por registro incluído (mescle depois os relatos do mesmo estudo) e libera a extração e o risco de viés em duplicata no portal dos alunos, começando pelo piloto.')
                ->form([Forms\Components\TextInput::make('pilot')->label('Estudos no piloto de extração')->numeric()->minValue(0)->maxValue(20)->default(3)->required()])
                ->action(function (array $data) use ($ex) {
                    try {
                        $ex->open($this->record, (int) $data['pilot']);
                    } catch (\RuntimeException $e) {
                        Notification::make()->title('Não aberta')->body($e->getMessage())->danger()->persistent()->send();

                        return;
                    }
                    Notification::make()->title('Extração aberta')->body('Os alunos foram avisados.')->success()->send();
                }),
            Actions\Action::make('liberarExtracao')->label('Liberar extração completa (fim do piloto)')->icon('heroicon-o-play')->color('success')
                ->visible(fn () => $this->record->inExtractionPilot())
                ->modalDescription('Confirme que as divergências do piloto foram discutidas e que só definições/instruções foram ajustadas (campos registrados não mudam; isso seria emenda).')
                ->form([Forms\Components\Textarea::make('note')->label('O que foi ajustado/combinado (vai para os alunos e para o histórico)')->rows(3)])
                ->action(function (array $data) use ($ex) {
                    $ex->releasePilot($this->record, $data['note'] ?? null);
                    Notification::make()->title('Extração completa liberada')->success()->send();
                }),
            Actions\Action::make('travarBase')->label(fn () => $this->record->latestLock() ? 'Travar nova versão da base' : 'Travar base (P7c)')->icon('heroicon-o-lock-closed')->color('warning')
                ->visible(fn () => $this->record->extractionOpen())
                ->modalDescription(fn () => $problems($ex->lockProblems($this->record))
                    ?? 'Gera a extração reconciliada e o risco de viés em CSV, calcula o SHA-256 e guarda a versão. Ela nunca é alterada; correções criam nova versão com justificativa.')
                ->form(fn () => $this->record->latestLock() ? [Forms\Components\Textarea::make('justification')->label('Justificativa da nova versão')->required()->rows(2)] : [])
                ->action(function (array $data) use ($ex) {
                    try {
                        $lock = $ex->lock($this->record, auth()->user()?->name ?? 'Professor', $data['justification'] ?? null);
                    } catch (\RuntimeException $e) {
                        Notification::make()->title('Base não travada')->body($e->getMessage())->danger()->persistent()->send();

                        return;
                    }
                    Notification::make()->title("Base travada v{$lock->version}")->body('SHA-256 da extração: ' . $lock->extraction_sha256)->success()->persistent()->send();
                }),
            Actions\Action::make('manuscrito')->label('Manuscrito e submissão (P10–P14)')->icon('heroicon-o-document-check')
                ->visible(fn () => (bool) $this->record->latestLock())
                ->fillForm(fn () => $this->record->manuscript ?? [])
                ->form([
                    Forms\Components\CheckboxList::make('checks')->label('Checklist antes de submeter (guia, seção 11)')->options(\App\Services\SystematicReviewExtractionService::MANUSCRIPT_CHECKS)->columns(1),
                    Forms\Components\TextInput::make('journal')->label('Revista')->maxLength(255),
                    Forms\Components\DatePicker::make('submitted_at')->label('Submetido em'),
                    Forms\Components\TextInput::make('decision')->label('Decisão / situação')->maxLength(255)->placeholder('ex.: revisão maior em 10/02/2028'),
                    Forms\Components\TextInput::make('article_doi')->label('DOI do artigo publicado')->maxLength(255),
                    Forms\Components\DatePicker::make('published_at')->label('Publicado em'),
                    Forms\Components\Textarea::make('notes')->label('Notas')->rows(2),
                ])
                ->action(function (array $data) use ($ex) {
                    $ex->updateManuscript($this->record, $data);
                    Notification::make()->title('Manuscrito/submissão atualizado')->success()->send();
                }),
        ])->label('Extração e análise')->icon('heroicon-o-beaker')->button()->color('gray');
    }

    /** Fases P0–P2 do pipeline: importar, congelar, registrar no OSF; desvios e emendas. */
    protected function osfActions(): Actions\ActionGroup
    {
        $problemsHtml = fn (array $problems) => $problems
            ? new \Illuminate\Support\HtmlString('<div style="color:#b91c1c;font-size:.875rem">Ainda não dá: ' . e(implode(' ', $problems)) . '</div>')
            : null;

        return Actions\ActionGroup::make([
            Actions\Action::make('importarPipeline')
                ->label(fn () => $this->record->pipeline ? 'Atualizar protocolo (pipeline JSON)' : 'Importar protocolo (pipeline JSON)')
                ->icon('heroicon-o-document-arrow-up')
                ->visible(fn () => !$this->record->isFrozen())
                ->modalDescription('Envie o pipeline_PXX.json deste projeto (schema calefi-sr-pipeline/1.0). Critérios, PICO, buscas, códigos de exclusão, campos de extração, risco de viés, plano de análise e a ficha do OSF são preenchidos a partir dele.')
                ->form([
                    Forms\Components\FileUpload::make('file')->label('pipeline_PXX.json')
                        ->disk('local')->directory('systematic_reviews/tmp')->visibility('private')
                        ->acceptedFileTypes(['application/json', 'text/plain', 'text/json'])->maxSize(5120)->required(),
                ])
                ->action(function (array $data) {
                    $disk = Storage::disk('local');
                    try {
                        $json = $disk->get($data['file']);
                        $code = $this->protocolService()->parse($json)['protocol_id'];
                        if ($this->record->protocol_code && $this->record->protocol_code !== $code) {
                            throw new \RuntimeException("Este projeto é o {$this->record->protocol_code}, mas o arquivo é do {$code}.");
                        }
                        if (!$this->record->protocol_code) {
                            $this->record->update(['protocol_code' => $code]);
                        }
                        [$review] = $this->protocolService()->import($json);
                    } catch (\RuntimeException $e) {
                        Notification::make()->title('Não importado')->body($e->getMessage())->danger()->persistent()->send();

                        return;
                    } finally {
                        $disk->delete($data['file']);
                    }
                    Notification::make()->title("Protocolo {$review->protocol_code} importado")->body('Confira os campos e congele o protocolo antes de registrar no OSF.')->success()->send();
                }),

            Actions\Action::make('congelar')
                ->label('Congelar protocolo (P1)')
                ->icon('heroicon-o-lock-closed')
                ->color('warning')
                ->visible(fn () => !$this->record->isFrozen())
                ->modalHeading('Congelar o protocolo')
                ->modalDescription(fn () => $problemsHtml($this->protocolService()->freezeProblems($this->record))
                    ?? 'Grava o SHA-256 do protocolo (e do PDF, se enviar) e deixa critérios, códigos de exclusão e campos somente leitura. Depois disso, mudanças só por emenda. Faça isto antes de registrar no OSF e antes de qualquer triagem.')
                ->form([
                    Forms\Components\FileUpload::make('pdf')->label('PDF do protocolo (o mesmo que vai para o OSF) — opcional, recomendado')
                        ->disk('local')->directory('systematic_reviews/tmp')->visibility('private')
                        ->acceptedFileTypes(['application/pdf'])->maxSize(20480),
                ])
                ->action(function (array $data) {
                    $disk = Storage::disk('local');
                    try {
                        $pdf = filled($data['pdf'] ?? null) ? $disk->get($data['pdf']) : null;
                        $this->protocolService()->freeze($this->record, $pdf);
                    } catch (\RuntimeException $e) {
                        Notification::make()->title('Não congelado')->body($e->getMessage())->danger()->persistent()->send();

                        return;
                    } finally {
                        if (filled($data['pdf'] ?? null)) {
                            $disk->delete($data['pdf']);
                        }
                    }
                    Notification::make()->title('Protocolo congelado')->body('SHA-256: ' . $this->record->protocol_sha256)->success()->persistent()->send();
                }),

            Actions\Action::make('descongelar')
                ->label('Desfazer congelamento')
                ->icon('heroicon-o-lock-open')
                ->color('danger')
                ->visible(fn () => $this->record->isFrozen() && !$this->record->registration_id)
                ->modalHeading('Desfazer o congelamento')
                ->modalDescription(fn () => $problemsHtml($this->protocolService()->unfreezeProblems($this->record))
                    ?? 'Use só para corrigir um engano antes do registro no OSF (por exemplo, o PDF de outro protocolo). O PDF anexado é removido, o motivo fica no histórico do projeto e você congela de novo em seguida.')
                ->form([
                    Forms\Components\TextInput::make('reason')->label('Motivo')->required()->maxLength(255)
                        ->placeholder('PDF do protocolo errado anexado no congelamento'),
                ])
                ->action(function (array $data) {
                    try {
                        $this->protocolService()->unfreeze($this->record, $data['reason']);
                    } catch (\RuntimeException $e) {
                        Notification::make()->title('Congelamento mantido')->body($e->getMessage())->danger()->persistent()->send();

                        return;
                    }
                    Notification::make()->title('Congelamento desfeito')->body('Congele de novo com o PDF correto.')->success()->send();
                }),

            Actions\Action::make('registroOsf')
                ->label(fn () => $this->record->registration_id ? 'Registro no OSF: ' . $this->record->registration_id : 'Registro no OSF (P2)')
                ->icon('heroicon-o-identification')
                ->color('success')
                ->modalHeading('Registro no OSF')
                ->modalDescription(fn () => $problemsHtml($this->protocolService()->registrationProblems($this->record))
                    ?? 'Registre no osf.io (template Generalized Systematic Review Registration) usando a "Ficha OSF" do menu Baixar e grave aqui o DOI. O registro tem de existir antes de qualquer triagem.')
                ->fillForm(fn () => [
                    'registration_id' => $this->record->registration_id,
                    'osf_project_url' => $this->record->osf_project_url,
                    'registered_at' => $this->record->registered_at ?? now(),
                    'embargo_until' => $this->record->embargo_until,
                ])
                ->form([
                    Forms\Components\TextInput::make('registration_id')->label('DOI do registro')->placeholder('10.17605/OSF.IO/XXXXX')->required()->maxLength(255),
                    Forms\Components\TextInput::make('osf_project_url')->label('Link do projeto no OSF')->url()->placeholder('https://osf.io/xxxxx/')->maxLength(255),
                    Forms\Components\DatePicker::make('registered_at')->label('Data do registro')->required(),
                    Forms\Components\DatePicker::make('embargo_until')->label('Embargo até (vazio = público)'),
                ])
                ->action(function (array $data) {
                    try {
                        $this->protocolService()->register($this->record, $data);
                    } catch (\RuntimeException $e) {
                        Notification::make()->title('Registro não gravado')->body($e->getMessage())->danger()->persistent()->send();

                        return;
                    }
                    Notification::make()->title('Registro do OSF gravado')->body($this->record->registration_id)->success()->send();
                }),

            Actions\Action::make('marcarOsf')
                ->label('Marquei como enviado ao OSF')
                ->icon('heroicon-o-cloud-arrow-up')
                ->color('success')
                ->visible(fn () => collect($this->protocolService()->osfPending($this->record))->reject(fn ($s) => in_array($s['key'], ['freeze', 'register'], true))->isNotEmpty())
                ->modalDescription('Marque o que você já subiu no componente correspondente do OSF. Sai da lista de pendências e fica no histórico.')
                ->form(fn () => [
                    Forms\Components\CheckboxList::make('keys')->label('Enviado ao OSF')->required()
                        ->options(collect($this->protocolService()->osfPending($this->record))->reject(fn ($s) => in_array($s['key'], ['freeze', 'register'], true))
                            ->mapWithKeys(fn ($s) => [$s['key'] => $s['title'] . ' (' . $s['component'] . ')'])->all()),
                ])
                ->action(function (array $data) {
                    $this->protocolService()->markUploaded($this->record, $data['keys']);
                    Notification::make()->title('Envio ao OSF registrado')->success()->send();
                }),

            Actions\Action::make('desvio')
                ->label('Registrar desvio ou emenda')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('gray')
                ->modalHeading('Desvio ou emenda do protocolo')
                ->modalDescription('Desvio: decisão pontual diferente do protocolo (entra na seção "Deviations from protocol"). Emenda: mudança de método ANTES de a etapa afetada começar; cria nova versão do protocolo, que precisa ser atualizada no OSF e congelada de novo. Nunca altere critérios ou desfechos depois de ver resultados.')
                ->form([
                    Forms\Components\Select::make('type')->label('Tipo')->options(\App\Models\SystematicReviewDeviation::TYPES)->required()->native(false),
                    Forms\Components\TextInput::make('section')->label('Seção do protocolo afetada')->required()->maxLength(120)->placeholder('Ex.: Busca — CAB Abstracts'),
                    Forms\Components\Textarea::make('description')->label('O que mudou')->required()->rows(3)->maxLength(3000),
                    Forms\Components\Textarea::make('justification')->label('Justificativa')->required()->rows(3)->maxLength(3000),
                    Forms\Components\TextInput::make('requested_by')->label('Quem pediu')->maxLength(255),
                    Forms\Components\TextInput::make('osf_update_ref')->label('Referência da atualização no OSF (se houver)')->maxLength(255),
                ])
                ->action(function (array $data) {
                    $d = $this->protocolService()->addDeviation($this->record, $data);
                    Notification::make()->title($d->typeLabel() . ' registrada')
                        ->body($d->protocol_version_after ? "Nova versão {$d->protocol_version_after}: importe o pipeline atualizado (se houver), atualize no OSF e congele de novo." : null)
                        ->success()->persistent()->send();
                }),
        ])->label('Protocolo e OSF')->icon('heroicon-o-shield-check')->button()->color('primary');
    }
}
