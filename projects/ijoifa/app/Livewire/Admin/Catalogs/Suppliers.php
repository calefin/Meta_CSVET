<?php

namespace App\Livewire\Admin\Catalogs;

use App\Models\Supplier;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
class Suppliers extends Component
{
    use WithPagination;

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $type = 'geral';

    public string $contact = '';

    public string $city = '';

    public string $state = '';

    public string $notes = '';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:animais,ervas,materiais,geral'],
            'contact' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:2'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function create(): void
    {
        $this->reset(['editingId', 'name', 'contact', 'city', 'state', 'notes']);
        $this->type = 'geral';
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $supplier = Supplier::findOrFail($id);
        $this->editingId = $supplier->id;
        $this->name = $supplier->name;
        $this->type = $supplier->type;
        $this->contact = (string) $supplier->contact;
        $this->city = (string) $supplier->city;
        $this->state = (string) $supplier->state;
        $this->notes = (string) $supplier->notes;
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate();

        Supplier::updateOrCreate(['id' => $this->editingId], $validated);

        $this->showModal = false;
    }

    public function delete(int $id): void
    {
        Supplier::destroy($id);
    }

    public function render()
    {
        return view('livewire.admin.catalogs.suppliers', [
            'suppliers' => Supplier::orderBy('name')->paginate(25),
        ]);
    }
}
