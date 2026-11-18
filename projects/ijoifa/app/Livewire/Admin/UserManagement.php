<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
class UserManagement extends Component
{
    use WithPagination;

    public string $search = '';

    public string $roleFilter = '';

    public string $statusFilter = '';

    public bool $showCreateForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $mother_maiden_name = '';

    public string $email = '';

    public string $role = 'consulente';

    public string $status = 'active';

    public string $phone = '';

    public function create(): void
    {
        $this->reset(['name', 'mother_maiden_name', 'email', 'phone']);
        $this->role = 'consulente';
        $this->status = 'active';
        $this->showCreateForm = true;
    }

    public function store(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'mother_maiden_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'role' => ['required', 'in:admin,iniciado,consulente'],
            'status' => ['required', 'in:pending,active,suspended'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $user = User::create([
            ...$validated,
            'locale' => app()->getLocale(),
            'password' => Hash::make(Str::random(32)),
        ]);

        Audit::record('admin_create_user', $user);

        Password::sendResetLink(['email' => $user->email]);

        $this->showCreateForm = false;
    }

    public function edit(int $id): void
    {
        $user = User::findOrFail($id);
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role;
        $this->status = $user->status;
        $this->phone = (string) $user->phone;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($this->editingId)],
            'role' => ['required', 'in:admin,iniciado,consulente'],
            'status' => ['required', 'in:pending,active,suspended'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        User::where('id', $this->editingId)->update($validated);

        $this->editingId = null;
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
    }

    public function cancelCreate(): void
    {
        $this->showCreateForm = false;
    }

    public function render()
    {
        return view('livewire.admin.user-management', [
            'users' => User::query()
                ->when($this->search, fn ($q) => $q->where(fn ($q2) => $q2
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")))
                ->when($this->roleFilter, fn ($q) => $q->where('role', $this->roleFilter))
                ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
                ->orderBy('name')
                ->paginate(25),
        ]);
    }
}
