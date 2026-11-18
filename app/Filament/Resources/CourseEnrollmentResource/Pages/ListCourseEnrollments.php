<?php

namespace App\Filament\Resources\CourseEnrollmentResource\Pages;

use App\Filament\Resources\CourseEnrollmentResource;
use App\Models\CourseEnrollment;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListCourseEnrollments extends ListRecords
{
    protected static string $resource = CourseEnrollmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Matricular Aluno'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'solicitados' => Tab::make('Solicitações Pendentes')
                ->icon('heroicon-o-inbox-arrow-down')
                ->badge(CourseEnrollment::where('status', 'solicitado')->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'solicitado')),
            'em_andamento' => Tab::make('Em Andamento')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'em_andamento')),
            'concluido' => Tab::make('Concluídos')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'concluido')),
            'all' => Tab::make('Todas'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return CourseEnrollment::where('status', 'solicitado')->exists() ? 'solicitados' : 'em_andamento';
    }
}
