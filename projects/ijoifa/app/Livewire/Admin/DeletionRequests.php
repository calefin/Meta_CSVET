<?php

namespace App\Livewire\Admin;

use App\Models\DeletionRequest;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
class DeletionRequests extends Component
{
    use WithPagination;

    public function resolve(int $id, string $status): void
    {
        DeletionRequest::where('id', $id)->update([
            'status' => $status,
            'resolved_by' => Auth::id(),
            'resolved_at' => now(),
        ]);
    }

    public function render()
    {
        return view('livewire.admin.deletion-requests', [
            'requests' => DeletionRequest::with('user')->latest()->paginate(25),
        ]);
    }
}
