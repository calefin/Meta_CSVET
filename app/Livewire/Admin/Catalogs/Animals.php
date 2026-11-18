<?php

namespace App\Livewire\Admin\Catalogs;

use App\Models\Animal;
use App\Models\AnimalPrice;
use App\Models\Supplier;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
class Animals extends Component
{
    use WithPagination;

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public ?int $supplier_id = null;

    public string $unit = '';

    public string $notes = '';

    public bool $active = true;

    public ?int $priceFormFor = null;

    public string $priceAmount = '';

    public string $pricePaymentMethod = '';

    public string $priceMinQuantity = '';

    public string $priceDate = '';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'unit' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'active' => ['boolean'],
        ];
    }

    public function create(): void
    {
        $this->reset(['editingId', 'name', 'supplier_id', 'unit', 'notes']);
        $this->active = true;
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $animal = Animal::findOrFail($id);
        $this->editingId = $animal->id;
        $this->name = $animal->name;
        $this->supplier_id = $animal->supplier_id;
        $this->unit = (string) $animal->unit;
        $this->notes = (string) $animal->notes;
        $this->active = $animal->active;
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate();

        Animal::updateOrCreate(['id' => $this->editingId], $validated);

        $this->showModal = false;
    }

    public function delete(int $id): void
    {
        Animal::destroy($id);
    }

    public function showPriceForm(int $animalId): void
    {
        $this->priceFormFor = $animalId;
        $this->priceAmount = '';
        $this->pricePaymentMethod = '';
        $this->priceMinQuantity = '';
        $this->priceDate = now()->format('Y-m-d');
    }

    public function addPrice(): void
    {
        $this->validate([
            'priceAmount' => ['required', 'numeric', 'min:0'],
            'pricePaymentMethod' => ['nullable', 'string', 'in:dinheiro,credito,debito'],
            'priceMinQuantity' => ['nullable', 'numeric', 'min:0'],
            'priceDate' => ['required', 'date'],
        ]);

        $animal = Animal::findOrFail($this->priceFormFor);

        AnimalPrice::create([
            'animal_id' => $animal->id,
            'supplier_id' => $animal->supplier_id,
            'unit_price' => $this->priceAmount,
            'payment_method' => $this->pricePaymentMethod ?: null,
            'min_quantity' => $this->priceMinQuantity !== '' ? (float) $this->priceMinQuantity : null,
            'purchase_date' => $this->priceDate,
        ]);

        $this->priceFormFor = null;
    }

    public function render()
    {
        return view('livewire.admin.catalogs.animals', [
            'animals' => Animal::with(['supplier', 'prices'])->orderBy('name')->paginate(25),
            'suppliers' => Supplier::orderBy('name')->get(),
        ]);
    }
}
