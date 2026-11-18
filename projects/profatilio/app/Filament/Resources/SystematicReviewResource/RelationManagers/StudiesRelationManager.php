<?php

namespace App\Filament\Resources\SystematicReviewResource\RelationManagers;

use App\Models\SrExtraction;
use App\Models\SrStudy;
use App\Services\SystematicReviewExtractionService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Estudos incluídos: extração em duplicata, divergências e reconciliação (P7). */
class StudiesRelationManager extends RelationManager
{
    protected static string $relationship = 'studies';

    protected static ?string $title = 'Estudos (extração e RoB)';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->extractionOpen();
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    protected function svc(): SystematicReviewExtractionService
    {
        return app(SystematicReviewExtractionService::class);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->where('status', 'active')->with('extractions.screener'))
            ->description('Cada estudo recebe duas extrações independentes. Reconcilie as divergências olhando o artigo; depois trave a base em "Extração e análise".')
            ->columns([
                Tables\Columns\TextColumn::make('label')->label('Estudo')->wrap()
                    ->description(fn (SrStudy $s) => $s->code() . ($s->pilot ? ' · piloto' : '') . ' · ' . count($s->record_ids ?? []) . ' relato(s)'),
                Tables\Columns\TextColumn::make('submitted')->label('Extrações')
                    ->state(fn (SrStudy $s) => $s->submittedCount() . '/2 enviadas')
                    ->badge()->color(fn (SrStudy $s) => $s->submittedCount() >= 2 ? 'success' : 'warning')
                    ->description(fn (SrStudy $s) => $s->extractions->map(fn ($e) => $e->screener?->name . ': ' . ($e->isSubmitted() ? 'enviado' : 'rascunho'))->implode(' · ') ?: null),
                Tables\Columns\TextColumn::make('divergences')->label('Divergências')
                    ->state(fn (SrStudy $s) => $s->submittedCount() >= 2 ? count($this->svc()->divergences($s)) : '—'),
                Tables\Columns\TextColumn::make('reconciled_at')->label('Reconciliado')
                    ->state(fn (SrStudy $s) => $s->reconciled_at ? '✔ ' . $s->reconciled_at->format('d/m/Y') : 'pendente')
                    ->badge()->color(fn (SrStudy $s) => $s->reconciled_at ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('ai')->label('IA (Revisor 1)')
                    ->visible(fn () => $this->getOwnerRecord()->llmAssistedExtraction())
                    ->state(function (SrStudy $s) {
                        $e = $s->extractions->first(fn ($x) => $x->aiPrefilled());
                        if (!$e) {
                            return '—';
                        }
                        $c = $e->ai_changes;

                        return $c ? "conferido · {$c['pct_corrected']}% corrigido" : 'pré-preenchido · aguardando conferência';
                    }),
            ])
            ->headerActions([
                // Protocolo v1.1: pré-extração por IA declarada, só para o Revisor 1.
                Tables\Actions\Action::make('modeloIa')->label('Modelo de IA e prompt')->icon('heroicon-o-cpu-chip')->color('gray')
                    ->visible(fn () => $this->getOwnerRecord()->llmAssistedExtraction())
                    ->modalHeading('Pré-extração por IA (declarada no protocolo)')
                    ->modalDescription('Use um plano que não treine com os dados enviados. Copie o prompt fixo, envie com o PDF do estudo e cole a resposta JSON em "Pré-extração IA" na linha do estudo. Só o Revisor 1 recebe o pré-preenchimento; o Revisor 2 extrai sem IA.')
                    ->fillForm(fn () => [
                        'model' => $this->getOwnerRecord()->ai_extraction['model'] ?? null,
                        'version' => $this->getOwnerRecord()->ai_extraction['version'] ?? null,
                        'prompt' => $this->svc()->aiPrompt($this->getOwnerRecord()),
                    ])
                    ->form([
                        Forms\Components\TextInput::make('model')->label('Modelo (ex.: Claude, GPT)')->required()->maxLength(80),
                        Forms\Components\TextInput::make('version')->label('Versão exata do modelo')->required()->maxLength(80),
                        Forms\Components\Textarea::make('prompt')->label('Prompt fixo (gerado do dicionário de extração; copie daqui)')->rows(14)->readOnly(),
                    ])
                    ->action(function (array $data) {
                        $this->svc()->setAiModel($this->getOwnerRecord(), $data['model'], $data['version'], auth()->user()?->name ?? 'Professor');
                        Notification::make()->title('Modelo e prompt registrados')->success()->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('preIa')->label('Pré-extração IA')->icon('heroicon-o-sparkles')->color('gray')
                    ->visible(fn (SrStudy $s) => $this->getOwnerRecord()->llmAssistedExtraction()
                        && !$s->extractions->contains(fn ($e) => $e->systematic_review_screener_id === $this->getOwnerRecord()->leadScreener()?->id && $e->isSubmitted()))
                    ->modalHeading(fn (SrStudy $s) => "Pré-extração por IA — {$s->code()} — {$s->label}")
                    ->modalDescription(fn () => 'Vai para a extração do Revisor 1 (' . ($this->getOwnerRecord()->leadScreener()?->name ?? '—') . '), que confere cada valor no PDF. O Revisor 2 não vê.')
                    ->form([
                        Forms\Components\Textarea::make('json')->label('Resposta JSON do modelo')->required()->rows(14),
                    ])
                    ->action(function (SrStudy $s, array $data) {
                        try {
                            $this->svc()->importAiPrefill($s, $data['json'], auth()->user()?->name ?? 'Professor');
                        } catch (\RuntimeException $e) {
                            Notification::make()->title('Não importado')->body($e->getMessage())->danger()->persistent()->send();

                            return;
                        }
                        Notification::make()->title("Pré-extração importada em {$s->code()}")->success()->send();
                    }),
                Tables\Actions\Action::make('reconciliar')->label('Reconciliar')->icon('heroicon-o-scale')->color('primary')
                    ->visible(fn (SrStudy $s) => $s->submittedCount() >= 2)
                    ->modalHeading(fn (SrStudy $s) => "Reconciliar {$s->code()} — {$s->label}")
                    ->modalDescription(function (SrStudy $s) {
                        [$a, $b] = $this->svc()->pair($s);
                        $n = count($this->svc()->divergences($s));

                        return $n ? "Para cada divergência, confira no artigo e escolha a versão correta. A = {$a?->screener?->name}; B = {$b?->screener?->name}." : 'As duas extrações são idênticas: confirme para gravar a versão reconciliada.';
                    })
                    ->form(function (SrStudy $s) {
                        [$a, $b] = $this->svc()->pair($s);
                        $fields = collect($this->svc()->divergences($s))->values()->map(fn ($d, $i) => Forms\Components\Radio::make("choice.d{$i}")
                            ->label($d['label'])
                            ->options(['a' => 'A (' . ($a?->screener?->name ?? 'A') . '): ' . (is_scalar($d['a']) ? (string) $d['a'] : '—'),
                                'b' => 'B (' . ($b?->screener?->name ?? 'B') . '): ' . (is_scalar($d['b']) ? (string) $d['b'] : '—')])
                            ->required())->all();

                        return array_merge($fields, [Forms\Components\Textarea::make('notes')->label('Notas da reconciliação (opcional)')->rows(2)]);
                    })
                    ->action(function (SrStudy $s, array $data) {
                        $divs = collect($this->svc()->divergences($s))->values();
                        $choices = $divs->mapWithKeys(fn ($d, $i) => [$d['key'] => $data['choice']["d{$i}"] ?? null])->all();
                        try {
                            $this->svc()->reconcile($s, $choices, $data['notes'] ?? null, auth()->user()?->name ?? 'Professor');
                        } catch (\RuntimeException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }
                        Notification::make()->title("{$s->code()} reconciliado")->success()->send();
                    }),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('reabrir')->label('Reabrir extração de um revisor')->icon('heroicon-o-arrow-uturn-left')
                        ->visible(fn (SrStudy $s) => $s->extractions->contains(fn ($e) => $e->isSubmitted()))
                        ->form(fn (SrStudy $s) => [
                            Forms\Components\Select::make('extraction')->label('Revisor')->required()->native(false)
                                ->options($s->extractions->filter->isSubmitted()->mapWithKeys(fn ($e) => [$e->id => $e->screener?->name])->all()),
                            Forms\Components\TextInput::make('reason')->label('Motivo (vai para o aluno)')->maxLength(300),
                        ])
                        ->action(function (SrStudy $s, array $data) {
                            $this->svc()->reopen(SrExtraction::findOrFail($data['extraction']), $data['reason'] ?? null);
                            Notification::make()->title('Extração reaberta')->success()->send();
                        }),
                    Tables\Actions\Action::make('mesclar')->label('Mesclar outros relatos neste estudo')->icon('heroicon-o-square-2-stack')
                        ->modalDescription('Use quando dois ou mais registros são relatos do mesmo estudo (mesmos animais, mesmo período). Os relatos mesclados saem da lista.')
                        ->form(fn (SrStudy $s) => [
                            Forms\Components\CheckboxList::make('sources')->label('Relatos (estudos) a mesclar neste')->required()
                                ->options($this->getOwnerRecord()->activeStudies()->where('id', '!=', $s->id)->get()->mapWithKeys(fn ($o) => [$o->id => $o->code() . ' — ' . $o->label])->all()),
                        ])
                        ->action(function (SrStudy $s, array $data) {
                            try {
                                $this->svc()->merge($s, $data['sources']);
                            } catch (\RuntimeException $e) {
                                Notification::make()->title($e->getMessage())->danger()->send();

                                return;
                            }
                            Notification::make()->title('Relatos mesclados')->success()->send();
                        }),
                    Tables\Actions\Action::make('renomear')->label('Renomear (ex.: Silva 2019a)')->icon('heroicon-o-pencil')
                        ->fillForm(fn (SrStudy $s) => ['label' => $s->label])
                        ->form([Forms\Components\TextInput::make('label')->label('Rótulo do estudo')->required()->maxLength(115)])
                        ->action(fn (SrStudy $s, array $data) => $s->update(['label' => $data['label']])),
                ])->label('Mais'),
            ])
            ->paginated([25, 50, 100]);
    }
}
