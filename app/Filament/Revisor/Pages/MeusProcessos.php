<?php

namespace App\Filament\Revisor\Pages;

use App\Models\ArticleReview;
use App\Models\ArticleReviewer;
use Filament\Facades\Filament;
use Filament\Pages\Page;

/**
 * Lista os processos de revisão (artigos/livros) da pessoa logada. Usada na
 * Área do Revisor e também no portal do aluno (orientando que participa de
 * um artigo vê o processo no próprio menu).
 */
class MeusProcessos extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-magnifying-glass';

    protected static ?string $title = 'Meus processos de revisão';

    protected static ?string $slug = 'processos-de-revisao';

    protected static string $view = 'filament.revisor.meus-processos';

    protected static ?int $navigationSort = -5;

    protected static function inPortal(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'portal';
    }

    public static function getNavigationLabel(): string
    {
        return static::inPortal() ? 'Revisão de Artigos' : 'Meus processos';
    }

    public static function getNavigationGroup(): ?string
    {
        return static::inPortal() ? 'Meus Trabalhos' : null;
    }

    protected static function myProcesses()
    {
        return ArticleReviewer::where('user_id', auth()->id())
            ->where('status', '!=', ArticleReviewer::STATUS_REMOVED)
            ->whereHas('articleReview', fn ($q) => $q->where('status', '!=', ArticleReview::STATUS_CANCELLED));
    }

    /** No portal, o item só aparece para quem participa de algum processo. */
    public static function shouldRegisterNavigation(): bool
    {
        return !static::inPortal() || (auth()->check() && static::myProcesses()->exists());
    }

    public static function getNavigationBadge(): ?string
    {
        if (!auth()->check()) {
            return null;
        }

        $myTurn = static::myProcesses()->where('status', ArticleReviewer::STATUS_IN_REVIEW)->count();

        return $myTurn > 0 ? 'Sua vez' : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    protected function getViewData(): array
    {
        return ['items' => static::myProcesses()->with(['articleReview.reviewers'])->latest('id')->get()];
    }
}
