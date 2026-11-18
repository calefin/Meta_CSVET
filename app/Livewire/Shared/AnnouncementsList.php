<?php

namespace App\Livewire\Shared;

use App\Models\Announcement;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class AnnouncementsList extends Component
{
    public function markRead(int $announcementId): void
    {
        Announcement::findOrFail($announcementId)
            ->reads()
            ->firstOrCreate(['user_id' => Auth::id()], ['read_at' => now()]);
    }

    public function render()
    {
        $user = Auth::user();

        $announcements = Announcement::whereNull('archived_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->where(function ($q) use ($user) {
                $q->where('target_group', 'all')
                    ->orWhere('target_group', $user->role === 'iniciado' ? 'iniciados' : 'consulentes');
            })
            ->with(['reads' => fn ($q) => $q->where('user_id', $user->id)])
            ->latest()
            ->get();

        return view('livewire.shared.announcements-list', ['announcements' => $announcements]);
    }
}
