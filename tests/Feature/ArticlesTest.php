<?php

namespace Tests\Feature;

use App\Livewire\Admin\ArticlesManager;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ArticlesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'admin', 'status' => 'active']);
    }

    public function test_draft_article_does_not_appear_on_public_blog(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ArticlesManager::class)
            ->call('create')
            ->set('title', 'Rascunho de teste')
            ->set('content', '<p>Conteúdo</p>')
            ->set('status', 'draft')
            ->call('save');

        $this->get(route('blog.index'))->assertDontSee('Rascunho de teste');

        $article = Article::where('title', 'Rascunho de teste')->firstOrFail();
        $this->get(route('blog.show', $article))->assertNotFound();
    }

    public function test_published_article_appears_on_listing_and_show_page(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ArticlesManager::class)
            ->call('create')
            ->set('title', 'O que é o jogo de Ifá')
            ->set('category', 'ifa')
            ->set('excerpt', 'Uma introdução ao oráculo de Ifá.')
            ->set('content', '<p>O jogo de Ifá é...</p>')
            ->set('status', 'published')
            ->call('save');

        $article = Article::where('title', 'O que é o jogo de Ifá')->firstOrFail();

        $this->assertSame('o-que-e-o-jogo-de-ifa', $article->slug);
        $this->assertNotNull($article->published_at);

        $this->get(route('blog.index'))->assertOk()->assertSee('O que é o jogo de Ifá');
        $this->get(route('blog.show', $article))
            ->assertOk()
            ->assertSee('O que é o jogo de Ifá')
            ->assertSee('O jogo de Ifá é...', false);
    }

    public function test_duplicate_titles_get_a_unique_slug_suffix(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(ArticlesManager::class)
            ->call('create')->set('title', 'Consultas com Ifá')->set('content', '<p>A</p>')->call('save');

        Livewire::actingAs($admin)->test(ArticlesManager::class)
            ->call('create')->set('title', 'Consultas com Ifá')->set('content', '<p>B</p>')->call('save');

        $slugs = Article::where('title', 'Consultas com Ifá')->pluck('slug')->sort()->values();

        $this->assertSame(['consultas-com-ifa', 'consultas-com-ifa-2'], $slugs->all());
    }

    public function test_editing_title_does_not_change_the_existing_slug(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(ArticlesManager::class)
            ->call('create')->set('title', 'Título original')->set('content', '<p>A</p>')->call('save');

        $article = Article::where('title', 'Título original')->firstOrFail();
        $originalSlug = $article->slug;

        Livewire::actingAs($admin)->test(ArticlesManager::class)
            ->call('edit', $article->id)
            ->set('title', 'Título renomeado')
            ->call('save');

        $article->refresh();
        $this->assertSame('Título renomeado', $article->title);
        $this->assertSame($originalSlug, $article->slug);
    }

    public function test_sitemap_includes_published_article_and_excludes_draft(): void
    {
        Livewire::actingAs($this->admin())->test(ArticlesManager::class)
            ->call('create')->set('title', 'Artigo Publicado')->set('content', '<p>A</p>')->set('status', 'published')->call('save');

        Livewire::actingAs($this->admin())->test(ArticlesManager::class)
            ->call('create')->set('title', 'Artigo Rascunho')->set('content', '<p>B</p>')->set('status', 'draft')->call('save');

        $published = Article::where('title', 'Artigo Publicado')->firstOrFail();
        $draft = Article::where('title', 'Artigo Rascunho')->firstOrFail();

        $response = $this->get(route('sitemap'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml');
        $response->assertSee(route('blog.show', $published), false);
        $response->assertDontSee(route('blog.show', $draft), false);
    }
}
