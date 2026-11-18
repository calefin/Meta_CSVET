<?php

namespace App\Livewire\Admin;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
class Announcements extends Component
{
    use WithPagination;

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $title = '';

    public string $body = '';

    public string $target_group = 'all';

    public string $expires_at = '';

    public ?int $viewingReadsFor = null;

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string'],
            'target_group' => ['required', 'in:all,iniciados,consulentes'],
            'expires_at' => ['nullable', 'date'],
        ];
    }

    public function create(): void
    {
        $this->reset(['editingId', 'title', 'body', 'expires_at']);
        $this->target_group = 'all';
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate();
        $validated['expires_at'] = $validated['expires_at'] ?: null;

        Announcement::updateOrCreate(['id' => $this->editingId], [
            ...$validated,
            'admin_id' => Auth::id(),
        ]);

        $this->showModal = false;
    }

    public function archive(int $id): void
    {
        Announcement::where('id', $id)->update(['archived_at' => now()]);
    }

    public function showReads(int $id): void
    {
        $this->viewingReadsFor = $id;
    }

    public function render()
    {
        $announcement = $this->viewingReadsFor ? Announcement::find($this->viewingReadsFor) : null;
        $readsData = null;

        if ($announcement) {
            $targetUsers = match ($announcement->target_group) {
                'iniciados' => User::where('role', 'iniciado')->get(),
                'consulentes' => User::where('role', 'consulente')->get(),
                default => User::whereIn('role', ['iniciado', 'consulente'])->get(),
            };

            $readUserIds = $announcement->reads->pluck('user_id');

            $readsData = $targetUsers->map(fn ($u) => [
                'name' => $u->name,
                'read' => $readUserIds->contains($u->id),
            ]);
        }

        return view('livewire.admin.announcements', [
            'announcements' => Announcement::withCount('reads')->whereNull('archived_at')->latest()->paginate(25),
            'viewingAnnouncement' => $announcement,
            'readsData' => $readsData,
        ]);
    }
}
