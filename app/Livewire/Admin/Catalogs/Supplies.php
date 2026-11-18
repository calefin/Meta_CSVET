<?php

namespace App\Livewire\Admin\Catalogs;

use App\Models\Supplier;
use App\Models\Supply;
use App\Models\SupplyPrice;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
class Supplies extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $category = '';

    public string $unit = '';

    public ?int $supplier_id = null;

    public bool $active = true;

    public ?int $priceFormFor = null;

    public string $priceAmount = '';

    public string $pricePaymentMethod = '';

    public string $priceMinQuantity = '';

    public string $priceDate = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:50'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'active' => ['boolean'],
        ];
    }

    public function create(): void
    {
        $this->reset(['editingId', 'name', 'category', 'unit', 'supplier_id']);
        $this->active = true;
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $supply = Supply::findOrFail($id);
        $this->editingId = $supply->id;
        $this->name = $supply->name;
        $this->category = (string) $supply->category;
        $this->unit = (string) $supply->unit;
        $this->supplier_id = $supply->supplier_id;
        $this->active = $supply->active;
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate();

        Supply::updateOrCreate(['id' => $this->editingId], $validated);

        $this->showModal = false;
    }

    public function delete(int $id): void
    {
        Supply::destroy($id);
    }

    public function showPriceForm(int $supplyId): void
    {
        $this->priceFormFor = $supplyId;
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

        $supply = Supply::findOrFail($this->priceFormFor);

        SupplyPrice::create([
            'supply_id' => $supply->id,
            'supplier_id' => $supply->supplier_id,
            'unit_price' => $this->priceAmount,
            'payment_method' => $this->pricePaymentMethod ?: null,
            'min_quantity' => $this->priceMinQuantity !== '' ? (float) $this->priceMinQuantity : null,
            'purchase_date' => $this->priceDate,
        ]);

        $this->priceFormFor = null;
    }

    public function render()
    {
        $query = Supply::with(['supplier', 'prices']);

        if (trim($this->search) !== '') {
            $query->where(function($q) {
                $q->where('name', 'like', '%' . trim($this->search) . '%')
                  ->orWhere('category', 'like', '%' . trim($this->search) . '%');
            });
        }

        return view('livewire.admin.catalogs.supplies', [
            'supplies' => $query->orderBy('name')->paginate(25),
            'suppliers' => Supplier::orderBy('name')->get(),
        ]);
    }
}
