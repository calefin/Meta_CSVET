<?php

namespace App\Livewire\Admin\Catalogs;

use App\Models\Odu;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
class Odus extends Component
{
    use WithPagination;

    public bool $showModal = false;

    public bool $showViewModal = false;

    public ?Odu $viewingOdu = null;

    public ?int $editingId = null;

    public string $name = '';

    public string $number = '';

    public string $category = 'meji';

    public string $description = '';

    public string $search = '';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'number' => ['required', 'integer', 'min:1', 'max:999'],
            'category' => ['required', 'in:meji,ameji'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function create(): void
    {
        $this->reset(['editingId', 'name', 'number', 'category', 'description']);
        $this->category = 'meji';
        $this->showModal = true;
    }

    public function viewOdu(int $id): void
    {
        $this->viewingOdu = Odu::findOrFail($id);
        $this->showViewModal = true;
    }

    public function edit(int $id): void
    {
        $odu = Odu::findOrFail($id);
        $this->editingId = $odu->id;
        $this->name = $odu->name;
        $this->number = (string) $odu->number;
        $this->category = $odu->category;
        $this->description = (string) $odu->description;
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate();

        Odu::updateOrCreate(['id' => $this->editingId], $validated);

        $this->showModal = false;
    }

    public function delete(int $id): void
    {
        Odu::destroy($id);
    }

    public function render()
    {
        return view('livewire.admin.catalogs.odus', [
            'odus' => Odu::when($this->search, function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('alternative_names', 'like', "%{$this->search}%");
            })
                ->orderBy('number')
                ->paginate(25),
        ]);
    }
}
