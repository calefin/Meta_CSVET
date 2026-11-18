<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class ComoFunciona extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-light-bulb';

    protected static string $view = 'filament.pages.como-funciona';

    protected static ?string $navigationLabel = 'Como Funciona a Plataforma';

    protected static ?string $title = 'Como Funciona a Plataforma';

    protected static ?int $navigationSort = 5;

    public static function getNavigationGroup(): ?string
    {
        return auth()->user()?->role === 'student' ? 'Minha Conta' : null;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'student';
    }

    public function mount(): void
    {
        $student = auth()->user()?->student;

        if ($student && !$student->guide_seen_at) {
            $student->update(['guide_seen_at' => now()]);
        }
    }
}
