<?php

namespace App\Livewire\Admin;

use App\Models\AdminNote;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
class AdminNotes extends Component
{
    use WithPagination;

    public ?int $targetUserId = null;

    public bool $showForm = false;

    public string $category = 'outro';

    public string $body = '';

    public string $search = '';

    public function mount(?int $targetUserId = null): void
    {
        $this->targetUserId = $targetUserId;
    }

    public function openForm(): void
    {
        $this->reset(['category', 'body']);
        $this->category = 'outro';
        $this->showForm = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'category' => ['required', 'in:espiritual,financeiro,saude,comportamento,outro'],
            'body' => ['required', 'string'],
        ]);

        $note = AdminNote::create([
            ...$validated,
            'admin_id' => Auth::id(),
            'target_user_id' => $this->targetUserId,
        ]);

        Audit::record('admin_note.create', $note);

        $this->showForm = false;
    }

    public function render()
    {
        $targetUser = $this->targetUserId ? User::findOrFail($this->targetUserId) : null;

        $notes = AdminNote::query()
            ->when($this->targetUserId, fn ($q) => $q->where('target_user_id', $this->targetUserId))
            ->when($this->search, fn ($q) => $q->where('body', 'like', "%{$this->search}%"))
            ->with('admin', 'targetUser')
            ->latest()
            ->paginate(25);

        return view('livewire.admin.admin-notes', [
            'notes' => $notes,
            'targetUser' => $targetUser,
            'targetUsers' => $this->targetUserId ? collect() : User::whereIn('role', ['iniciado', 'consulente'])->orderBy('name')->get(),
        ]);
    }
}
