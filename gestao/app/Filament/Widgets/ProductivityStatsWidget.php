<?php

namespace App\Filament\Widgets;

use App\Models\DocumentSubmission;
use App\Models\Student;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProductivityStatsWidget extends BaseWidget
{
    protected static ?int $sort = -3;

    public static function canView(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    protected function getStats(): array
    {
        $activeStudents = Student::where('status', 'active')->count();

        $openProcesses = DocumentSubmission::where('status', 'not like', 'Processo Concluído%')
            ->whereNotIn('status', ['failed', 'approved', 'returned_signed'])
            ->count();

        $concludedThisMonth = DocumentSubmission::where('status', 'like', 'Processo Concluído%')
            ->whereMonth('updated_at', now()->month)
            ->whereYear('updated_at', now()->year)
            ->count();

        $submittedThisMonth = DocumentSubmission::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return [
            Stat::make('Alunos Ativos', $activeStudents)
                ->description('Orientandos com conta ativa')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('primary'),
            Stat::make('Processos em Aberto', $openProcesses)
                ->description('Aguardando ação ou revisão')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('warning'),
            Stat::make('Concluídos este Mês', $concludedThisMonth)
                ->description('Processos finalizados em ' . now()->locale('pt_BR')->isoFormat('MMMM'))
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),
            Stat::make('Submetidos este Mês', $submittedThisMonth)
                ->description('Novos documentos enviados em ' . now()->locale('pt_BR')->isoFormat('MMMM'))
                ->descriptionIcon('heroicon-m-arrow-up-tray')
                ->color('info'),
        ];
    }
}
