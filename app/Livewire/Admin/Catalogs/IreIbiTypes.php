<?php

namespace App\Livewire\Admin\Catalogs;

use App\Models\IreIbiType;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
class IreIbiTypes extends Component
{
    use WithPagination;

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $classification = 'ire';

    public string $name = '';

    public string $description = '';

    public bool $active = true;

    protected function rules(): array
    {
        return [
            'classification' => ['required', 'in:ire,ibi'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'active' => ['boolean'],
        ];
    }

    public function create(): void
    {
        $this->reset(['editingId', 'name', 'description']);
        $this->classification = 'ire';
        $this->active = true;
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $type = IreIbiType::findOrFail($id);
        $this->editingId = $type->id;
        $this->classification = $type->classification;
        $this->name = $type->name;
        $this->description = (string) $type->description;
        $this->active = $type->active;
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate();

        IreIbiType::updateOrCreate(['id' => $this->editingId], $validated);

        $this->showModal = false;
    }

    public function delete(int $id): void
    {
        IreIbiType::destroy($id);
    }

    public function render()
    {
        return view('livewire.admin.catalogs.ire-ibi-types', [
            'types' => IreIbiType::orderBy('classification')->orderBy('name')->paginate(25),
        ]);
    }
}
