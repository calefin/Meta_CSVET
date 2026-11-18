<?php

namespace App\Livewire;

use App\Models\Article;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.guest', [
    'title' => 'Blog — Ìjọ Ifá Èjìogbè',
    'description' => 'Artigos sobre Ifá, orixás, candomblé, umbanda e consultas oraculares da Ìjọ Ifá Èjìogbè.',
])]
class PublicArticlesList extends Component
{
    use WithPagination;

    #[Url]
    public string $categoria = '';

    public function render()
    {
        $query = Article::published()->latest('published_at');

        if ($this->categoria !== '' && array_key_exists($this->categoria, Article::CATEGORIES)) {
            $query->where('category', $this->categoria);
        }

        return view('livewire.public-articles-list', [
            'articles' => $query->paginate(12),
            'categories' => Article::CATEGORIES,
        ]);
    }
}
