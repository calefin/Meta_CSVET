<?php

namespace App\Livewire\Admin;

use App\Models\AuditLog;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
class AuditLogViewer extends Component
{
    use WithPagination;

    public string $actionFilter = '';

    public function render()
    {
        return view('livewire.admin.audit-log-viewer', [
            'logs' => AuditLog::with('user')
                ->when($this->actionFilter, fn ($q) => $q->where('action', 'like', "%{$this->actionFilter}%"))
                ->latest()
                ->paginate(25),
        ]);
    }
}
