<?php

namespace App\Filament\Widgets;

use App\Models\AcademicTitle;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ICDeadlinesOverviewWidget extends BaseWidget
{
    protected static ?int $sort = -2; // Exibir no topo superior do Dashboard

    public static function canView(): bool
    {
        // Visível apenas para o Prof. Atilio (admin)
        return auth()->user()?->role === 'admin';
    }

    /**
     * Classifica cada projeto de IC vigente pelo seu prazo de cobrança
     * mensal (Deadline tipo 'cobranca'), em vez de pelo aluno — dois
     * projetos de IC do mesmo aluno (um terminando, um começando) contam
     * separadamente, sem se misturar.
     */
    protected function getStats(): array
    {
        $titles = AcademicTitle::where('type', 'IC')->vigente()->with('deadlines')->get();

        $onTime = 0;
        $warning = 0;
        $critical = 0;
        $overdue = 0;
        $submitted = 0;

        foreach ($titles as $title) {
            $deadline = $title->deadlines
                ->where('type', 'cobranca')
                ->sortByDesc('due_date')
                ->first();

            if (!$deadline) {
                continue;
            }

            if ($deadline->status === 'fulfilled') {
                $submitted++;
                continue;
            }

            $daysRemaining = (int) now()->startOfDay()->diffInDays($deadline->due_date->copy()->startOfDay(), false);

            if ($daysRemaining < 0) {
                $overdue++;
            } elseif ($daysRemaining < 15) {
                $critical++;
            } elseif ($daysRemaining <= 30) {
                $warning++;
            } else {
                $onTime++;
            }
        }

        $criticalWarningCount = $warning + $critical + $overdue;
        $alertColor = 'warning';
        if ($overdue > 0 || $critical > 0) {
            $alertColor = 'danger';
        }

        return [
            Stat::make('Relatórios de IC Entregues', $submitted)
                ->description('Projetos com evolução entregue no ciclo atual')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
            Stat::make('Prazos em Dia / Seguros', $onTime)
                ->description('Projetos com mais de 30 dias restantes')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('success'),
            Stat::make('Prazos em Alerta ou Críticos', $criticalWarningCount)
                ->description("{$warning} em alerta, {$critical} críticos, {$overdue} atrasados")
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($alertColor),
        ];
    }
}
