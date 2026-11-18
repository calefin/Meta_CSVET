<?php

namespace App\Filament\Pages;

use App\Models\PersonalActivity;
use Filament\Pages\Page;

class Pomodoro extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-fire';

    protected static string $view = 'filament.pages.pomodoro';

    protected static ?string $navigationLabel = 'Pomodoro';

    protected static ?string $title = '🍅 Pomodoro';

    protected static ?string $navigationGroup = 'Gestão Pessoal';

    protected static ?int $navigationSort = 0;

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public function getActivities()
    {
        return PersonalActivity::with('category')
            ->where('user_id', auth()->id())
            ->where('status', '!=', 'done')
            ->orderByDesc('date')
            ->get();
    }

    /**
     * Chamado pelo Alpine (via $wire) sempre que um ciclo de foco termina
     * (ou é interrompido) para persistir o tempo focado na atividade
     * escolhida — mesmo campo total_seconds usado pelo timer simples, então
     * entra automaticamente nas estatísticas por categoria.
     */
    public function addFocusSeconds(?int $activityId, int $seconds): ?string
    {
        if (!$activityId || $seconds <= 0) {
            return null;
        }

        $activity = PersonalActivity::where('user_id', auth()->id())->find($activityId);
        if (!$activity) {
            return null;
        }

        $activity->increment('total_seconds', $seconds);

        if ($activity->status === 'pending') {
            $activity->update(['status' => 'in_progress']);
        }

        return $activity->fresh()->formattedTime();
    }
}
