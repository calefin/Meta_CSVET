<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class CentralAjuda extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static string $view = 'filament.pages.central-ajuda';

    protected static ?string $navigationLabel = 'Central de Ajuda';

    protected static ?string $title = 'Central de Ajuda';

    protected static ?int $navigationSort = 20;

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->role === 'admin';
    }
}
