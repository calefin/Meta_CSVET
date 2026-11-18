<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Services\TelegramService;

class TelegramAlertWidget extends Widget
{
    protected static string $view = 'filament.widgets.telegram-alert-widget';

    protected static ?int $sort = 0; // Exibe no topo do Dashboard

    protected int | string | array $columnSpan = 'full';

    /**
     * Define a visibilidade do widget na dashboard.
     * Visível apenas para estudantes que não possuem o chat_id cadastrado.
     */
    public static function canView(): bool
    {
        $user = auth()->user();
        if (!$user || $user->role !== 'student') {
            return false;
        }

        $student = $user->student;
        return $student && empty($student->telegram_chat_id);
    }

    /**
     * Obtém o link seguro de vinculação.
     */
    public function getInviteLink(): string
    {
        $student = auth()->user()->student;
        if (!$student) {
            return '#';
        }
        return app(TelegramService::class)->getInviteLink($student->id);
    }
}
