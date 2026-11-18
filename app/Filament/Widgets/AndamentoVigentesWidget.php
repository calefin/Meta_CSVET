<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\AcademicProjectResource;
use App\Models\AcademicTitle;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class AndamentoVigentesWidget extends BaseWidget
{
    protected static ?string $heading = '📊 Andamento de TCC e IC Vigentes';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = -3; // Antes de tudo, no topo do Dashboard

    public static function canView(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AcademicTitle::query()
                    ->whereIn('type', ['TCC', 'IC'])
                    ->vigente()
                    ->with(['student.user', 'submissions', 'deadlines'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('student.user.name')
                    ->label('Aluno')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->icon(fn (string $state) => $state === 'TCC' ? 'heroicon-o-document-text' : 'heroicon-o-beaker')
                    ->color(fn (string $state): string => $state === 'TCC' ? 'primary' : 'warning'),
                Tables\Columns\TextColumn::make('title')
                    ->label('Projeto / Tema')
                    ->limit(40)
                    ->wrap(),
                Tables\Columns\TextColumn::make('etapa')
                    ->label('Etapa')
                    ->html()
                    ->state(fn (AcademicTitle $record) => $record->type === 'TCC'
                        ? static::renderTccStepDots($record)
                        : static::renderIcProgressBar($record)),
                Tables\Columns\TextColumn::make('andamento')
                    ->label('Andamento')
                    ->badge()
                    ->icon(fn (AcademicTitle $record) => match (static::andamentoInfo($record)['color']) {
                        'danger' => 'heroicon-o-exclamation-triangle',
                        'warning' => 'heroicon-o-clock',
                        'success' => 'heroicon-o-check-circle',
                        default => 'heroicon-o-arrow-path',
                    })
                    ->state(fn (AcademicTitle $record) => static::andamentoInfo($record)['label'])
                    ->color(fn (AcademicTitle $record) => static::andamentoInfo($record)['color']),
                Tables\Columns\TextColumn::make('proxima_cobranca')
                    ->label('Próxima Cobrança/Prazo')
                    ->badge()
                    ->state(function (AcademicTitle $record) {
                        $deadline = $record->deadlines
                            ->where('status', 'pending')
                            ->sortBy('due_date')
                            ->first();

                        return $deadline ? $deadline->due_date->format('d/m/Y') : '—';
                    })
                    ->color(function (AcademicTitle $record) {
                        $deadline = $record->deadlines->where('status', 'pending')->sortBy('due_date')->first();
                        if (!$deadline) {
                            return 'gray';
                        }

                        return match (true) {
                            $deadline->due_date->isPast() => 'danger',
                            $deadline->due_date->diffInDays(now(), true) <= 7 => 'warning',
                            default => 'gray',
                        };
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'TCC' => 'TCC',
                        'IC' => 'Iniciação Científica',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('abrir')
                    ->label('Abrir Projeto')
                    ->icon('heroicon-o-folder-open')
                    ->url(fn (AcademicTitle $record) => AcademicProjectResource::getUrl('view', ['record' => $record])),
            ])
            ->paginated([10, 25, 50])
            ->defaultSort('updated_at', 'desc')
            ->emptyStateHeading('Nenhum projeto vigente')
            ->emptyStateDescription('Todos os TCCs e ICs em andamento aparecerão aqui, junto com a etapa/mês atual de cada um.');
    }

    /**
     * Para TCC, reaproveita getProgressInfo() (aguardando aluno/professor).
     * Para IC, getProgressInfo() usa o status do "Termo de Aceite de IC" —
     * que já nasce como "Processo Concluído - ... Ativa" assim que
     * homologado, o que mostraria "Concluído" para uma IC recém-começada e
     * ativa. Nesse caso a coluna "Andamento" passa a refletir o prazo de
     * cobrança mensal (em dia/alerta/atrasado), que é o que importa aqui.
     */
    protected static function andamentoInfo(AcademicTitle $record): array
    {
        if ($record->type !== 'IC') {
            return $record->getProgressInfo();
        }

        $deadline = $record->deadlines->where('type', 'cobranca')->where('status', 'pending')->sortBy('due_date')->first();

        if (!$deadline) {
            return ['label' => 'Ativa', 'color' => 'success'];
        }

        if ($deadline->due_date->isPast()) {
            return ['label' => 'Cobrança Atrasada', 'color' => 'danger'];
        }

        if ($deadline->due_date->diffInDays(now(), true) <= 7) {
            return ['label' => 'Cobrança Próxima', 'color' => 'warning'];
        }

        return ['label' => 'Em Dia', 'color' => 'success'];
    }

    /**
     * Trilha visual de 3 pontos (Concepção -> Objetivos -> Redação) em vez de
     * só o rótulo em texto — mostra de relance quantas etapas já foram
     * concluídas, sem precisar abrir o projeto.
     */
    protected static function renderTccStepDots(AcademicTitle $record): string
    {
        $steps = $record->getTccSteps();

        $dots = [];
        foreach ($steps as $step) {
            $color = match ($step['state']) {
                'done' => 'background-color:#14b8a6', // teal-500
                'current' => 'background-color:#f59e0b;box-shadow:0 0 0 3px rgba(245,158,11,0.25)', // amber-500 + halo
                default => 'background-color:#94a3b8', // slate-400 (mais visível que slate-600 no fundo claro)
            };
            $dots[] = '<span title="' . e($step['label']) . '" style="' . $color . '" class="inline-block w-3 h-3 rounded-full"></span>';
        }

        $line = '<span class="inline-block w-3 h-px bg-gray-400 dark:bg-gray-600 mx-0.5"></span>';
        $trail = implode($line, $dots);

        $current = collect($steps)->first(fn ($s) => $s['state'] === 'current')
            ?? collect($steps)->last(fn ($s) => $s['state'] === 'done');
        $label = $current ? $current['label'] : 'Não iniciado';

        return '<div class="flex items-center gap-1.5"><div class="flex items-center">' . $trail . '</div>'
            . '<span class="text-xs text-gray-500 dark:text-gray-400">' . e($label) . '</span></div>';
    }

    /**
     * Barra fina mostrando quantos % da vigência de 12 meses da IC já
     * passaram, com o mês atual escrito ao lado.
     */
    protected static function renderIcProgressBar(AcademicTitle $record): string
    {
        $progress = $record->getIcMonthProgress();

        if ($progress['percent'] === null) {
            return '<span class="text-xs text-gray-500 dark:text-gray-400">' . e($progress['label']) . '</span>';
        }

        $percent = max(0, min(100, $progress['percent']));
        $barColor = $percent >= 90 ? '#ef4444' : '#f59e0b'; // vermelho perto do fim, âmbar durante

        return '<div class="w-28">'
            . '<div class="h-1.5 w-full rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">'
            . '<div class="h-full rounded-full" style="width:' . $percent . '%;background-color:' . $barColor . '"></div>'
            . '</div>'
            . '<span class="text-xs text-gray-500 dark:text-gray-400">' . e($progress['label']) . '</span>'
            . '</div>';
    }
}
