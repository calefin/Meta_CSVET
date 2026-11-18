<?php

namespace App\Livewire\Admin;

use App\Models\Article;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
class ArticlesManager extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $search = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $title = '';

    public string $category = 'geral';

    public string $excerpt = '';

    public string $content = '';

    public string $metaTitle = '';

    public string $metaDescription = '';

    public string $status = 'draft';

    public $coverImage = null;

    public ?string $currentCoverImagePath = null;

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'category' => ['required', 'in:'.implode(',', array_keys(Article::CATEGORIES))],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'string'],
            'metaTitle' => ['nullable', 'string', 'max:255'],
            'metaDescription' => ['nullable', 'string', 'max:300'],
            'status' => ['required', 'in:draft,published'],
            'coverImage' => ['nullable', 'image', 'max:4096'],
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->reset([
            'editingId', 'title', 'excerpt', 'content', 'metaTitle', 'metaDescription',
            'coverImage', 'currentCoverImagePath',
        ]);
        $this->category = 'geral';
        $this->status = 'draft';
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $article = Article::findOrFail($id);

        $this->editingId = $article->id;
        $this->title = $article->title;
        $this->category = $article->category;
        $this->excerpt = (string) $article->excerpt;
        $this->content = $article->content;
        $this->metaTitle = (string) $article->meta_title;
        $this->metaDescription = (string) $article->meta_description;
        $this->status = $article->status;
        $this->currentCoverImagePath = $article->cover_image_path;
        $this->coverImage = null;
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate();

        $existing = $this->editingId ? Article::find($this->editingId) : null;

        $coverImagePath = $this->currentCoverImagePath;
        if ($this->coverImage) {
            $coverImagePath = $this->coverImage->store('article-covers', 'public');
        }

        $becomingPublished = $validated['status'] === 'published' && $existing?->status !== 'published';

        // Slug is derived once at creation and then left alone on edits, so
        // renaming the title later doesn't silently break a URL that may
        // already be shared/indexed.
        $slug = $existing?->slug ?? Article::uniqueSlugFor($validated['title']);

        Article::updateOrCreate(['id' => $this->editingId], [
            'title' => $validated['title'],
            'slug' => $slug,
            'category' => $validated['category'],
            'excerpt' => $validated['excerpt'],
            'content' => clean($validated['content'], 'lesson_content'),
            'cover_image_path' => $coverImagePath,
            'meta_title' => $validated['metaTitle'],
            'meta_description' => $validated['metaDescription'],
            'status' => $validated['status'],
            'published_at' => $becomingPublished ? now() : $existing?->published_at,
            'author_id' => $existing?->author_id ?? Auth::id(),
        ]);

        $this->showModal = false;
    }

    public function delete(int $id): void
    {
        $article = Article::findOrFail($id);

        if ($article->cover_image_path) {
            Storage::disk('public')->delete($article->cover_image_path);
        }

        $article->delete();
    }

    public function render()
    {
        $query = Article::query();

        if (trim($this->search) !== '') {
            $query->where('title', 'like', '%'.trim($this->search).'%');
        }

        return view('livewire.admin.articles-manager', [
            'articles' => $query->latest()->paginate(20),
            'categories' => Article::CATEGORIES,
        ]);
    }
}
