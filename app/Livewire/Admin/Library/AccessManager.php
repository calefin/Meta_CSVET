<?php

namespace App\Livewire\Admin\Library;

use App\Models\LibraryCategory;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.admin')]
class AccessManager extends Component
{
    public User $user;

    public function mount(User $user): void
    {
        $this->user = $user;
    }

    public function grant(int $categoryId): void
    {
        $this->user->libraryCategories()->syncWithoutDetaching([
            $categoryId => ['granted_by' => auth()->id(), 'granted_at' => now()],
        ]);
    }

    public function revoke(int $categoryId): void
    {
        $this->user->libraryCategories()->detach($categoryId);
    }

    public function render()
    {
        return view('livewire.admin.library.access-manager', [
            'categories' => LibraryCategory::orderBy('order')->get(),
            'grantedCategoryIds' => $this->user->libraryCategories()->pluck('library_categories.id')->all(),
        ]);
    }
}
