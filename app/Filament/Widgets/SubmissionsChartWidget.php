<?php

namespace App\Filament\Widgets;

use App\Models\DocumentSubmission;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class SubmissionsChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Documentos Submetidos por Mês';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    protected function getData(): array
    {
        $months = collect(range(5, 0))->map(
            fn ($i) => now()->subMonths($i)->startOfMonth()
        );

        $submitted = $months->map(
            fn (Carbon $month) => DocumentSubmission::whereBetween('created_at', [
                $month->copy()->startOfMonth(),
                $month->copy()->endOfMonth(),
            ])->count()
        );

        $concluded = $months->map(
            fn (Carbon $month) => DocumentSubmission::where('status', 'like', 'Processo Concluído%')
                ->whereBetween('updated_at', [
                    $month->copy()->startOfMonth(),
                    $month->copy()->endOfMonth(),
                ])->count()
        );

        $labels = $months->map(
            fn (Carbon $m) => ucfirst($m->locale('pt_BR')->isoFormat('MMM/YY'))
        );

        return [
            'datasets' => [
                [
                    'label'           => 'Submetidos',
                    'data'            => $submitted->values()->toArray(),
                    'backgroundColor' => 'rgba(13, 148, 136, 0.6)',
                    'borderColor'     => 'rgba(13, 148, 136, 1)',
                    'borderWidth'     => 1,
                ],
                [
                    'label'           => 'Concluídos',
                    'data'            => $concluded->values()->toArray(),
                    'backgroundColor' => 'rgba(34, 197, 94, 0.6)',
                    'borderColor'     => 'rgba(34, 197, 94, 1)',
                    'borderWidth'     => 1,
                ],
            ],
            'labels' => $labels->values()->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
