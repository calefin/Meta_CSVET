<?php

namespace App\Filament\Resources\ArticleReviewResource\Pages;

use App\Filament\Resources\ArticleReviewResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListArticleReviews extends ListRecords
{
    protected static string $resource = ArticleReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Novo artigo para revisão'),
        ];
    }
}
