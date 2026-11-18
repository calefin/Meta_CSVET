<?php

namespace App\Livewire\Library;

use App\Models\LibraryDocument;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Viewer extends Component
{
    public LibraryDocument $document;

    public function mount(LibraryDocument $document): void
    {
        $user = Auth::user();

        $hasAccess = $user->isAdmin()
            || $user->libraryCategories()->where('library_categories.id', $document->category_id)->exists();

        abort_unless($hasAccess, 403);

        $this->document = $document;
    }

    public function render()
    {
        $user = Auth::user();
        if ($user->role === 'admin') {
            $layout = 'components.layouts.admin';
        } else {
            $layout = $user->role === 'iniciado' ? 'components.layouts.iniciado' : 'components.layouts.consulente';
        }

        return view('livewire.library.viewer')
            ->layout($layout, ['title' => $this->document->title]);
    }
}
