<?php

namespace App\Filament\Pages;

use App\Models\SystematicReview;

/**
 * Treino da triagem no portal do aluno: a mesma tela da Triagem Cega, mas só
 * com os treinos (dados fora dos projetos). Depois que o treino fecha, mostra
 * as decisões do aluno, as da dupla e o gabarito comentado.
 */
class TreinoTriagem extends TriagemCega
{
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $title = 'Treino da triagem';

    protected static ?string $slug = 'treino-triagem';

    protected static ?int $navigationSort = -7;

    protected static bool $training = true;

    public static function getNavigationBadge(): ?string
    {
        if (!auth()->check()) {
            return null;
        }
        // Treino aberto e ainda não terminado, ou resultado novo para ver.
        $open = static::myAssignments()->get()->filter(fn ($s) => $s->systematicReview->status === SystematicReview::STATUS_SCREENING
            && !$s->hasFinished($s->systematicReview->stage))->count();

        return $open > 0 ? (string) $open : null;
    }
}
