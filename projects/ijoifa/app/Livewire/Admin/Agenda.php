<?php

namespace App\Livewire\Admin;

use App\Models\Appointment;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.admin')]
class Agenda extends Component
{
    public bool $showModal = false;

    public ?int $editingId = null;

    public ?int $user_id = null;

    public string $scheduled_at = '';

    public string $modality = 'online';

    public string $type = '';

    public string $video_link = '';

    public string $status = 'pending';

    public string $notes = '';

    protected function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'scheduled_at' => ['required', 'date'],
            'modality' => ['required', 'in:online,presential'],
            'type' => ['nullable', 'string', 'max:100'],
            'video_link' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:pending,confirmed,completed,cancelled'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function create(): void
    {
        $this->reset(['editingId', 'user_id', 'type', 'video_link', 'notes']);
        $this->scheduled_at = now()->addDay()->format('Y-m-d\TH:i');
        $this->modality = 'online';
        $this->status = 'pending';
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $appointment = Appointment::findOrFail($id);
        $this->editingId = $appointment->id;
        $this->user_id = $appointment->user_id;
        $this->scheduled_at = $appointment->scheduled_at->format('Y-m-d\TH:i');
        $this->modality = $appointment->modality;
        $this->type = (string) $appointment->type;
        $this->video_link = (string) $appointment->video_link;
        $this->status = $appointment->status;
        $this->notes = (string) $appointment->notes;
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate();

        Appointment::updateOrCreate(['id' => $this->editingId], [
            ...$validated,
            'admin_id' => auth()->id(),
        ]);

        $this->showModal = false;
    }

    public function delete(int $id): void
    {
        Appointment::destroy($id);
    }

    public function render()
    {
        $appointments = Appointment::with('user')
            ->whereBetween('scheduled_at', [now()->subMonths(2), now()->addMonths(6)])
            ->get();

        $colors = [
            'pending' => '#7B4F2E',
            'confirmed' => '#4A7DAA',
            'completed' => '#27AE60',
            'cancelled' => '#C0392B',
        ];

        return view('livewire.admin.agenda', [
            'consulentes' => User::where('role', 'consulente')->orderBy('name')->get(),
            'events' => $appointments->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->user->name.' — '.($a->type ?: ($a->modality === 'online' ? 'Online' : 'Presencial')),
                'start' => $a->scheduled_at->toIso8601String(),
                'color' => $colors[$a->status] ?? '#7B4F2E',
            ])->values(),
        ]);
    }
}
