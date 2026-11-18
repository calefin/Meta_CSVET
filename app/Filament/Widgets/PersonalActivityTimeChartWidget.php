<?php

namespace App\Filament\Widgets;

use App\Models\PersonalActivityCategory;
use Filament\Widgets\ChartWidget;

class PersonalActivityTimeChartWidget extends ChartWidget
{
    protected static ?string $heading = '⏱️ Tempo Gasto por Categoria';

    protected static ?int $sort = 50; // Depois do bloco de TCC/IC, no fim do Dashboard

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    protected function getFilters(): ?array
    {
        return [
            'hoje' => 'Hoje',
            'semana' => 'Esta Semana',
            'mes' => 'Este Mês',
            'tudo' => 'Tudo',
        ];
    }

    protected function getData(): array
    {
        $range = $this->filter ?? 'mes';

        $query = PersonalActivityCategory::query()->with(['activities' => function ($q) use ($range) {
            match ($range) {
                'hoje' => $q->whereDate('date', now()->toDateString()),
                'semana' => $q->whereBetween('date', [now()->startOfWeek(), now()->endOfWeek()]),
                'tudo' => $q,
                default => $q->whereBetween('date', [now()->startOfMonth(), now()->endOfMonth()]),
            };
        }]);

        $categories = $query->get()->filter(fn (PersonalActivityCategory $c) => $c->activities->isNotEmpty());

        $hours = $categories->map(
            fn (PersonalActivityCategory $c) => round($c->activities->sum(fn ($a) => $a->currentSeconds()) / 3600, 2)
        );

        return [
            'datasets' => [
                [
                    'label' => 'Horas',
                    'data' => $hours->values()->toArray(),
                    'backgroundColor' => $categories->pluck('color')->values()->toArray(),
                ],
            ],
            'labels' => $categories->pluck('name')->values()->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
