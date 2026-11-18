<?php

namespace App\Filament\Pages;

use App\Models\DocumentSubmission;
use Filament\Pages\Page;

class TransparenciaLgpd extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static string $view = 'filament.pages.transparencia-lgpd';

    protected static ?string $navigationLabel = 'Transparência LGPD';

    protected static ?string $title = 'Painel de Transparência LGPD';

    protected static ?int $navigationSort = 100;

    public static function getNavigationGroup(): ?string
    {
        return auth()->user()?->role === 'student' ? 'Minha Conta' : null;
    }

    public $student;
    public $submissions;

    public function mount(): void
    {
        $user = auth()->user();
        if ($user && $user->role === 'student') {
            $this->student = $user->student;
            $this->submissions = DocumentSubmission::where('student_id', $this->student?->id)
                ->where('document_type', 'Termo de Aceite de IC')
                ->whereNotNull('metadata')
                ->get();
        } else {
            $this->student = null;
            $this->submissions = [];
        }
    }
}
