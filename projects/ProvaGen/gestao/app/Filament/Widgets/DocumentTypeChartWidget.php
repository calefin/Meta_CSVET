<?php

namespace App\Filament\Widgets;

use App\Models\DocumentSubmission;
use Filament\Widgets\ChartWidget;

class DocumentTypeChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Distribuição por Tipo de Documento';

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    protected function getData(): array
    {
        $types = DocumentSubmission::selectRaw('document_type, count(*) as total')
            ->groupBy('document_type')
            ->orderByDesc('total')
            ->get();

        $colors = [
            'rgba(13, 148, 136, 0.7)',
            'rgba(79, 70, 229, 0.7)',
            'rgba(124, 58, 237, 0.7)',
            'rgba(239, 68, 68, 0.7)',
            'rgba(245, 158, 11, 0.7)',
            'rgba(34, 197, 94, 0.7)',
            'rgba(59, 130, 246, 0.7)',
            'rgba(236, 72, 153, 0.7)',
        ];

        return [
            'datasets' => [
                [
                    'data'            => $types->pluck('total')->values()->toArray(),
                    'backgroundColor' => collect($colors)->take($types->count())->values()->toArray(),
                ],
            ],
            'labels' => $types->pluck('document_type')->values()->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
