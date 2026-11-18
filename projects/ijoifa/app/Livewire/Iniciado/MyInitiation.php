<?php

namespace App\Livewire\Iniciado;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.iniciado')]
class MyInitiation extends Component
{
    public function render()
    {
        $user = Auth::user();

        return view('livewire.iniciado.my-initiation', [
            'initiation' => $user->initiation()->with('odu', 'dayRecords.odu', 'ewos')->first(),
            'orisaRecords' => $user->orisaRecords()->with('odu')->orderByDesc('performed_at')->get(),
        ]);
    }
}
