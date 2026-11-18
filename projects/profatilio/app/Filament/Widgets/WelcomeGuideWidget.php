<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\ComoFunciona;
use Filament\Widgets\Widget;

class WelcomeGuideWidget extends Widget
{
    protected static string $view = 'filament.widgets.welcome-guide-widget';

    protected static ?int $sort = -4; // Acima de tudo, inclusive das Ações Rápidas

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();
        if (!$user || $user->role !== 'student') {
            return false;
        }

        $student = $user->student;

        return $student && !$student->guide_seen_at;
    }

    public function getGuideUrl(): string
    {
        return ComoFunciona::getUrl();
    }

    /**
     * Marca o guia como visto sem levar o aluno para a página completa,
     * e recarrega o dashboard para o banner sumir imediatamente.
     */
    public function dismiss(): void
    {
        $student = auth()->user()?->student;

        if ($student) {
            $student->update(['guide_seen_at' => now()]);
        }

        $this->redirect(request()->fullUrl());
    }
}
