<?php

namespace App\Livewire\Admin\Library;

use App\Models\LibraryCategory;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.admin')]
class CategoryManager extends Component
{
    public string $name = '';

    public string $description = '';

    public ?int $editingId = null;

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
        ]);

        if ($this->editingId) {
            $category = LibraryCategory::findOrFail($this->editingId);
            $category->update($validated);
        } else {
            LibraryCategory::create([
                ...$validated,
                'slug' => $this->uniqueSlug($validated['name']),
                'order' => LibraryCategory::count(),
            ]);
        }

        $this->reset(['name', 'description', 'editingId']);
    }

    public function edit(int $id): void
    {
        $category = LibraryCategory::findOrFail($id);
        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->description = (string) $category->description;
    }

    public function cancelEdit(): void
    {
        $this->reset(['name', 'description', 'editingId']);
    }

    public function delete(int $id): void
    {
        LibraryCategory::where('id', $id)->delete();
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;

        while (LibraryCategory::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }

    public function render()
    {
        return view('livewire.admin.library.category-manager', [
            'categories' => LibraryCategory::withCount('documents')->orderBy('order')->get(),
        ]);
    }
}
