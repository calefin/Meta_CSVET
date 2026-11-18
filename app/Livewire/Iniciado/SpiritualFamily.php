<?php

namespace App\Livewire\Iniciado;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.iniciado')]
class SpiritualFamily extends Component
{
    public function render()
    {
        $user = Auth::user();

        // Fetch all active initiates, ordering by seniority (initiation date ascending)
        $initiates = User::query()
            ->where('users.role', 'iniciado')
            ->where('users.status', 'active')
            ->leftJoin('initiations', 'users.id', '=', 'initiations.user_id')
            ->select('users.id', 'users.name', 'users.email', 'initiations.initiation_date', 'initiations.spiritual_name')
            ->with(['orisaRecords' => fn ($q) => $q->whereNotNull('spiritual_name')])
            ->orderByRaw('CASE WHEN initiations.initiation_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('initiations.initiation_date', 'asc')
            ->orderBy('users.name', 'asc')
            ->get();

        return view('livewire.iniciado.spiritual-family', [
            'initiates' => $initiates,
        ]);
    }
}
