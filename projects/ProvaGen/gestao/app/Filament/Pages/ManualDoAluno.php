<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class ManualDoAluno extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static string $view = 'filament.pages.manual-do-aluno';

    protected static ?string $navigationLabel = 'Manual & Normas';

    protected static ?string $title = 'Manual do Aluno & Syllabus';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return auth()->user()?->role === 'student' ? 'Minha Conta' : null;
    }

    public static function canAccess(): bool
    {
        // Alunos e Administradores podem acessar
        return auth()->check();
    }
}
