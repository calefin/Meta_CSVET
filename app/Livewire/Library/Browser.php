<?php

namespace App\Livewire\Library;

use App\Models\LibraryCategory;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Browser extends Component
{
    public function render()
    {
        $user = Auth::user();

        if ($user->isAdmin()) {
            $categories = LibraryCategory::with('documents')->orderBy('order')->get();
            $layout = 'components.layouts.admin';
        } else {
            $categories = $user->libraryCategories()->with('documents')->orderBy('order')->get();
            $layout = $user->role === 'iniciado' ? 'components.layouts.iniciado' : 'components.layouts.consulente';
        }

        return view('livewire.library.browser', ['categories' => $categories])
            ->layout($layout, ['title' => 'Biblioteca']);
    }
}
