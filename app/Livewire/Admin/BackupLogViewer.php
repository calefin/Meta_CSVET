<?php

namespace App\Livewire\Admin;

use App\Models\BackupLog;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
class BackupLogViewer extends Component
{
    use WithPagination;

    public function render()
    {
        return view('livewire.admin.backup-log-viewer', [
            'logs' => BackupLog::latest()->paginate(25),
        ]);
    }
}
