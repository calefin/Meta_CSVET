<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
class PendingApprovals extends Component
{
    use WithPagination;

    public function approve(int $userId): void
    {
        User::where('id', $userId)->update(['status' => 'active']);
    }

    public function reject(int $userId): void
    {
        User::where('id', $userId)->update(['status' => 'suspended']);
    }

    public function render()
    {
        return view('livewire.admin.pending-approvals', [
            'pendingUsers' => User::where('status', 'pending')
                ->orderBy('created_at')
                ->paginate(25),
        ]);
    }
}
