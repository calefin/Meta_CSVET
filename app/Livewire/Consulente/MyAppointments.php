<?php

namespace App\Livewire\Consulente;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.consulente')]
class MyAppointments extends Component
{
    use WithPagination;

    public function render()
    {
        return view('livewire.consulente.my-appointments', [
            'appointments' => Auth::user()->appointments()->orderByDesc('scheduled_at')->paginate(25),
        ]);
    }
}
