<?php

namespace App\Filament\Resources\ArticleReviewResource\Pages;

use App\Filament\Resources\ArticleReviewResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Arr;

class EditArticleReview extends EditRecord
{
    protected static string $resource = ArticleReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            ArticleReviewResource::deleteProcessAction(Actions\Action::make('excluir'))
                ->successRedirectUrl(ArticleReviewResource::getUrl('index')),
        ];
    }

    /** Complementares incluídos na edição (anexados depois de salvar). */
    protected array $supplementary = [];

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->supplementary = Arr::only($data, ['supplementary_files', 'supplementary_files_names']);

        return Arr::except($data, ['supplementary_files', 'supplementary_files_names']);
    }

    protected function afterSave(): void
    {
        ArticleReviewResource::notifyAttached(...ArticleReviewResource::attachSupplementary($this->record, $this->supplementary, 'supplementary_files'));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
