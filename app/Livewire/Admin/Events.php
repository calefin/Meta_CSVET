<?php

namespace App\Livewire\Admin;

use App\Models\Event;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
class Events extends Component
{
    use WithPagination;

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $title = '';

    public string $description = '';

    public string $starts_on = '';

    public string $ends_on = '';

    public string $target_group = 'all';

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'target_group' => ['required', 'in:all,iniciados,consulentes'],
        ];
    }

    public function create(): void
    {
        $this->reset(['editingId', 'title', 'description', 'starts_on', 'ends_on']);
        $this->target_group = 'all';
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $event = Event::findOrFail($id);

        $this->editingId = $event->id;
        $this->title = $event->title;
        $this->description = (string) $event->description;
        $this->starts_on = $event->starts_on->toDateString();
        $this->ends_on = $event->ends_on?->toDateString() ?? '';
        $this->target_group = $event->target_group;
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate();
        $validated['ends_on'] = $validated['ends_on'] ?: null;

        Event::updateOrCreate(['id' => $this->editingId], [
            ...$validated,
            'admin_id' => Auth::id(),
        ]);

        $this->showModal = false;
    }

    public function delete(int $id): void
    {
        Event::destroy($id);
    }

    public function render()
    {
        return view('livewire.admin.events', [
            'events' => Event::orderByDesc('starts_on')->paginate(25),
        ]);
    }
}
