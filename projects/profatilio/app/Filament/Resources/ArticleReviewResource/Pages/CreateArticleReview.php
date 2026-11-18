<?php

namespace App\Filament\Resources\ArticleReviewResource\Pages;

use App\Filament\Resources\ArticleReviewResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;

class CreateArticleReview extends CreateRecord
{
    protected static string $resource = ArticleReviewResource::class;

    /** Complementares escolhidos no cadastro (anexados depois de criar o processo). */
    protected array $supplementary = [];

    /**
     * Vindo de "Criar artigo" na revisão sistemática: já escolhe o modelo
     * completo, o projeto, o título e os triadores como participantes.
     */
    protected function afterFill(): void
    {
        $sr = \App\Models\SystematicReview::find(request()->integer('systematic_review'));
        if (!$sr) {
            return;
        }

        $title = $sr->protocolValue('title_en') ?: $sr->title;
        $this->form->fill([
            ...$this->form->getRawState(),
            'development_model' => 'complete',
            'systematic_review_id' => $sr->id,
            'article_type' => \App\Support\ArticleTypes::forSystematicReview($sr),
            'title' => $title,
            'short_name' => ArticleReviewResource::suggestShortName($title),
        ]);

        // O repeater com relationship() recarrega do banco no fill(); na criação
        // o estado dos triadores precisa ser posto direto.
        if ($participants = ArticleReviewResource::participantsFromSystematicReview($sr)) {
            $this->data['reviewers'] = array_map(fn (array $p) => $p + ['student_pick' => null, 'review_days' => null, 'author_order' => null], $participants);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->supplementary = Arr::only($data, ['supplementary_files', 'supplementary_files_names']);

        return Arr::except($data, ['supplementary_files', 'supplementary_files_names']);
    }

    protected function afterCreate(): void
    {
        ArticleReviewResource::notifyAttached(...ArticleReviewResource::attachSupplementary($this->record, $this->supplementary, 'supplementary_files'));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
