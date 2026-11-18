<?php

namespace App\Livewire\Shared;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class Profile extends Component
{
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $changed = false;

    public function changePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        Auth::user()->update(['password' => Hash::make($this->password)]);

        \App\Support\Audit::record('password.change');

        $this->reset(['current_password', 'password', 'password_confirmation']);
        $this->changed = true;
    }

    public function render()
    {
        $user = Auth::user();

        $layout = match ($user->role) {
            'admin' => 'components.layouts.admin',
            'iniciado' => 'components.layouts.iniciado',
            default => 'components.layouts.consulente',
        };

        return view('livewire.shared.profile', ['user' => $user])
            ->layout($layout, ['title' => 'Meu perfil']);
    }
}
