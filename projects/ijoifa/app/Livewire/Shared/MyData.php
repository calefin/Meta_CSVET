<?php

namespace App\Livewire\Shared;

use App\Models\DeletionRequest;
use App\Support\Audit;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MyData extends Component
{
    public string $deletionNotes = '';

    public function requestDeletion(): void
    {
        $request = DeletionRequest::create([
            'user_id' => Auth::id(),
            'status' => 'pending',
            'notes' => $this->deletionNotes,
        ]);

        Audit::record('deletion_request.create', $request);

        $this->deletionNotes = '';
    }

    public function render()
    {
        $user = Auth::user();
        $layout = match ($user->role) {
            'iniciado' => 'components.layouts.iniciado',
            'admin' => 'components.layouts.admin',
            default => 'components.layouts.consulente',
        };

        return view('livewire.shared.my-data', [
            'pendingRequest' => $user->deletionRequests()->where('status', 'pending')->latest()->first(),
            'history' => $user->deletionRequests()->latest()->get(),
        ])->layout($layout, ['title' => 'Meus dados']);
    }
}
