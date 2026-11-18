<?php

namespace App\Livewire;

use App\Models\Article;
use Livewire\Component;

class PublicArticleShow extends Component
{
    public Article $article;

    public function mount(Article $article): void
    {
        abort_if($article->status !== 'published', 404);

        $this->article = $article;
    }

    public function render()
    {
        return view('livewire.public-article-show', [
            'article' => $this->article,
        ])->layout('components.layouts.guest', [
            'title' => $this->article->metaTitle(),
            'description' => $this->article->metaDescription(),
        ]);
    }
}
