<?php

namespace App\Livewire\Admin\Oracular;

use App\Models\Animal;
use App\Models\QuickPayment;
use App\Models\Supply;
use App\Models\Supplier;
use App\Models\User;
use App\Models\RitualWork;
use App\Services\QuickPaymentService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.admin')]
class QuickPaymentBuilder extends Component
{
    public string $type = '';
    public string $notes = '';
    public string $markup_percent = '0';
    public string $fee_percent = '0';

    // For loading a RitualWork template:
    public string $templateSearch = '';

    // For saving current items as a template:
    public bool $showSaveTemplateModal = false;
    public string $newTemplateName = '';
    public string $newTemplateDescription = '';

    // Up to 3 independent options. Each slot is an array of item rows:
    // ['name' => string, 'quantity' => float, 'unit_price' => float, 'supplier_id' => ?int, 'supplier_name' => ?string]
    public array $optionItems = [[], [], []];

    // Direct value charged for an option when it has no items (no markup applied)
    public array $optionBaseValue = ['0', '0', '0'];

    // Shipping cost, added to the cost basis (before profit %) for each option
    public array $optionFreight = ['0', '0', '0'];

    // Fixed labor/handling fee, added after profit is calculated, for each option
    public array $optionLaborCost = ['0', '0', '0'];

    // Free-text comment shown to the client for each option
    public array $optionComments = ['', '', ''];

    // Which option (0, 1 or 2) is currently being edited
    public int $activeOptionIndex = 0;

    // For selecting / searching supplies from catalog
    public string $supplySearch = '';

    // For selecting / searching animals from catalog
    public string $animalSearch = '';

    public bool $showSupplyCatalog = false;

    public bool $showAnimalCatalog = false;

    // For listing supplier prices when clicking/selecting an item:
    public ?int $activePriceViewSupplyId = null;
    public string $activePriceViewSupplyName = '';
    public array $activeSupplierPrices = [];

    // For editing a specific row (within the active option):
    public ?int $editingRowIndex = null;

    // Generated link display
    public ?string $generatedLink = null;

    protected function rules(): array
    {
        return [
            'type' => ['required', 'in:consulta,trabalhos_gerais,iniciacao,aborisa'],
            'notes' => ['nullable', 'string'],
            'markup_percent' => ['required', 'numeric', 'min:0'],
            'fee_percent' => ['required', 'numeric', 'min:0', 'max:99'],
            'optionItems.*.*.name' => ['required', 'string', 'max:150'],
            'optionItems.*.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'optionItems.*.*.unit_price' => ['required', 'numeric', 'min:0'],
            'optionItems.*.*.supplier_id' => ['nullable', 'exists:suppliers,id'],
            'optionItems.*.*.by_client' => ['boolean'],
            'optionFreight.*' => ['nullable', 'numeric', 'min:0'],
            'optionLaborCost.*' => ['nullable', 'numeric', 'min:0'],
            'optionComments.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function switchOption(int $index): void
    {
        $this->activeOptionIndex = max(0, min(2, $index));
        $this->closeSupplierPrices();
    }

    public function addItemRow(): void
    {
        $this->optionItems[$this->activeOptionIndex][] = [
            'name' => '',
            'quantity' => 1,
            'unit_price' => 0.00,
            'supplier_id' => null,
            'supplier_name' => null,
            'by_client' => false,
        ];
    }

    public function removeRow(int $index): void
    {
        unset($this->optionItems[$this->activeOptionIndex][$index]);
        $this->optionItems[$this->activeOptionIndex] = array_values($this->optionItems[$this->activeOptionIndex]);
        if ($this->editingRowIndex === $index) {
            $this->editingRowIndex = null;
        }
    }

    public function viewSupplierPrices(int $index): void
    {
        $item = $this->optionItems[$this->activeOptionIndex][$index] ?? null;
        if (!$item) {
            return;
        }

        $this->editingRowIndex = $index;

        if (!empty($item['animal_id'])) {
            $animal = Animal::find($item['animal_id']);

            if ($animal) {
                $this->activePriceViewSupplyId = $animal->id;
                $this->activePriceViewSupplyName = $animal->name;

                $prices = $animal->prices()->with('supplier')->orderBy('unit_price', 'asc')->get();
                $this->activeSupplierPrices = $prices->map(fn ($p) => [
                    'supplier_id' => $p->supplier_id,
                    'supplier_name' => $p->supplier ? $p->supplier->name : 'N/A',
                    'price' => (float) $p->unit_price,
                    'payment_method' => $p->payment_method ?? 'N/A',
                    'date' => $p->purchase_date ? $p->purchase_date->format('d/m/Y') : 'N/A',
                ])->all();

                return;
            }
        }

        // Find if this is associated with a supply in our DB (by exact name or direct link)
        $supply = null;
        if (!empty($item['supply_id'])) {
            $supply = Supply::find($item['supply_id']);
        } else {
            $supply = Supply::where('name', $item['name'])->first();
        }

        if ($supply) {
            $this->activePriceViewSupplyId = $supply->id;
            $this->activePriceViewSupplyName = $supply->name;

            $prices = $supply->prices()->with('supplier')->orderBy('unit_price', 'asc')->get();
            $this->activeSupplierPrices = $prices->map(fn($p) => [
                'supplier_id' => $p->supplier_id,
                'supplier_name' => $p->supplier ? $p->supplier->name : 'N/A',
                'price' => (float)$p->unit_price,
                'payment_method' => $p->payment_method ?? 'N/A',
                'date' => $p->purchase_date ? $p->purchase_date->format('d/m/Y') : 'N/A',
            ])->all();
        } else {
            // Check if there's a partial match to be helpful
            $closest = Supply::where('name', 'like', "%{$item['name']}%")->first();
            if ($closest) {
                $this->activePriceViewSupplyId = $closest->id;
                $this->activePriceViewSupplyName = $closest->name;
                $prices = $closest->prices()->with('supplier')->orderBy('unit_price', 'asc')->get();
                $this->activeSupplierPrices = $prices->map(fn($p) => [
                    'supplier_id' => $p->supplier_id,
                    'supplier_name' => $p->supplier ? $p->supplier->name : 'N/A',
                    'price' => (float)$p->unit_price,
                    'payment_method' => $p->payment_method ?? 'N/A',
                    'date' => $p->purchase_date ? $p->purchase_date->format('d/m/Y') : 'N/A',
                ])->all();
            } else {
                $this->activePriceViewSupplyId = null;
                $this->activePriceViewSupplyName = $item['name'];
                $this->activeSupplierPrices = [];
            }
        }
    }

    public function selectSupplierPrice(int $supplierId, string $supplierName, float $price): void
    {
        if ($this->editingRowIndex === null || !isset($this->optionItems[$this->activeOptionIndex][$this->editingRowIndex])) {
            return;
        }

        $this->optionItems[$this->activeOptionIndex][$this->editingRowIndex]['unit_price'] = $price;
        $this->optionItems[$this->activeOptionIndex][$this->editingRowIndex]['supplier_id'] = $supplierId;
        $this->optionItems[$this->activeOptionIndex][$this->editingRowIndex]['supplier_name'] = $supplierName;

        // If the item name was empty or generic, we can overwrite it with the catalog item name
        if ($this->activePriceViewSupplyName) {
            $this->optionItems[$this->activeOptionIndex][$this->editingRowIndex]['name'] = $this->activePriceViewSupplyName;
        }

        $this->closeSupplierPrices();
    }

    public function closeSupplierPrices(): void
    {
        $this->editingRowIndex = null;
        $this->activePriceViewSupplyId = null;
        $this->activePriceViewSupplyName = '';
        $this->activeSupplierPrices = [];
    }

    public function subtotalFor(int $index): float
    {
        $items = $this->optionItems[$index] ?? [];

        if (empty($items)) {
            return round((float) ($this->optionBaseValue[$index] ?? 0), 2);
        }

        $sub = 0;
        foreach ($items as $item) {
            $sub += (float)($item['quantity'] ?? 0) * (float)($item['unit_price'] ?? 0);
        }
        return round($sub, 2);
    }

    public function freightFor(int $index): float
    {
        return round((float) ($this->optionFreight[$index] ?? 0), 2);
    }

    public function laborCostFor(int $index): float
    {
        return round((float) ($this->optionLaborCost[$index] ?? 0), 2);
    }

    // Profit (%) applies over the real cost (items + freight). A direct base
    // value is already the final price the admin wants to charge, so it has
    // no profit margin applied on top of it.
    public function profitFor(int $index): float
    {
        if (empty($this->optionItems[$index] ?? [])) {
            return 0.0;
        }

        $costBasis = $this->subtotalFor($index) + $this->freightFor($index);

        return round($costBasis * ((float) $this->markup_percent / 100), 2);
    }

    public function totalFor(int $index): float
    {
        $items = $this->optionItems[$index] ?? [];
        $subtotal = $this->subtotalFor($index);
        $freight = $this->freightFor($index);
        $labor = $this->laborCostFor($index);
        $fee = (float) $this->fee_percent;

        $totalAfterMarkup = empty($items)
            ? $subtotal + $freight + $labor
            : $subtotal + $freight + $this->profitFor($index) + $labor;

        if ($fee > 0 && $fee < 100) {
            $total = $totalAfterMarkup / (1 - ($fee / 100));
        } else {
            $total = $totalAfterMarkup;
        }

        return round($total, 2);
    }

    public function isOptionFilled(int $index): bool
    {
        return !empty($this->optionItems[$index]) || (float) ($this->optionBaseValue[$index] ?? 0) > 0;
    }

    public function getSubtotalProperty(): float
    {
        return $this->subtotalFor($this->activeOptionIndex);
    }

    public function getTotalProperty(): float
    {
        return $this->totalFor($this->activeOptionIndex);
    }

    public function generateLink(): void
    {
        $this->validate();

        if (!$this->isOptionFilled(0)) {
            $this->addError('optionBaseValue.0', 'Informe pelo menos a Opção 1 (itens ou valor) antes de gerar o link.');
            return;
        }

        foreach ([0, 1, 2] as $i) {
            if ($this->isOptionFilled($i) && $this->totalFor($i) <= 0) {
                $this->addError('generateLink', 'O valor final da Opção '.($i + 1).' não pode ser zero. Ajuste o preço unitário dos itens ou o valor base.');
                return;
            }
        }

        $options = [];
        foreach ([0, 1, 2] as $i) {
            $options[$i] = $this->isOptionFilled($i) ? [
                'items' => $this->optionItems[$i],
                'subtotal' => $this->subtotalFor($i),
                'freight' => $this->freightFor($i),
                'profit' => $this->profitFor($i),
                'labor_cost' => $this->laborCostFor($i),
                'total' => $this->totalFor($i),
                'comment' => trim($this->optionComments[$i]),
            ] : null;
        }

        $quickPayment = QuickPaymentService::generate(
            $this->type,
            $this->notes,
            $options,
            $this->markup_percent,
            $this->fee_percent,
        );

        $this->generatedLink = route('quick-checkout', ['uuid' => $quickPayment->uuid]);

        session()->flash('success', 'Link de pagamento gerado com sucesso!');
    }

    public function cancelLink(int $id): void
    {
        $qp = QuickPayment::findOrFail($id);
        $qp->update(['status' => 'cancelled']);
        session()->flash('success', 'Link cancelado.');
    }

    public function selectCatalogItemById(int $id): void
    {
        $supply = Supply::find($id);
        if (!$supply) {
            return;
        }

        // Find latest price
        $latestPrice = $supply->prices()->latest('purchase_date')->first();
        $supplierId = $latestPrice ? $latestPrice->supplier_id : null;
        $supplierName = $latestPrice && $latestPrice->supplier ? $latestPrice->supplier->name : null;

        $this->optionItems[$this->activeOptionIndex][] = [
            'name' => $supply->name,
            'quantity' => 1,
            'unit_price' => $latestPrice ? (float)$latestPrice->unit_price : 0.00,
            'supplier_id' => $supplierId,
            'supplier_name' => $supplierName,
            'supply_id' => $supply->id,
            'by_client' => false,
        ];

        $this->supplySearch = '';
    }

    public function selectAnimalItemById(int $id): void
    {
        $animal = Animal::find($id);
        if (!$animal) {
            return;
        }

        $latestPrice = $animal->prices()->latest('purchase_date')->first();
        $supplierId = $latestPrice ? $latestPrice->supplier_id : null;
        $supplierName = $latestPrice && $latestPrice->supplier ? $latestPrice->supplier->name : null;

        $this->optionItems[$this->activeOptionIndex][] = [
            'name' => $animal->name,
            'quantity' => 1,
            'unit_price' => $latestPrice ? (float) $latestPrice->unit_price : 0.00,
            'supplier_id' => $supplierId,
            'supplier_name' => $supplierName,
            'animal_id' => $animal->id,
            'by_client' => false,
        ];

        $this->animalSearch = '';
    }

    public function loadTemplate(int $ritualWorkId): void
    {
        $ritualWork = RitualWork::with(['supplies.prices', 'animals.prices'])->findOrFail($ritualWorkId);
        foreach ($ritualWork->supplies as $supply) {
            $latestPrice = $supply->prices()->latest('purchase_date')->first();
            $supplierId = $latestPrice ? $latestPrice->supplier_id : null;
            $supplierName = $latestPrice && $latestPrice->supplier ? $latestPrice->supplier->name : null;

            $this->optionItems[$this->activeOptionIndex][] = [
                'name' => $supply->name,
                'quantity' => (float)($supply->pivot->default_quantity ?: 1.0),
                'unit_price' => $latestPrice ? (float)$latestPrice->unit_price : 0.00,
                'supplier_id' => $supplierId,
                'supplier_name' => $supplierName,
                'supply_id' => $supply->id,
                'by_client' => false,
            ];
        }

        foreach ($ritualWork->animals as $animal) {
            $latestPrice = $animal->prices()->latest('purchase_date')->first();
            $supplierId = $latestPrice ? $latestPrice->supplier_id : null;
            $supplierName = $latestPrice && $latestPrice->supplier ? $latestPrice->supplier->name : null;

            $this->optionItems[$this->activeOptionIndex][] = [
                'name' => $animal->name,
                'quantity' => (float)($animal->pivot->default_quantity ?: 1.0),
                'unit_price' => $latestPrice ? (float)$latestPrice->unit_price : 0.00,
                'supplier_id' => $supplierId,
                'supplier_name' => $supplierName,
                'animal_id' => $animal->id,
                'by_client' => false,
            ];
        }

        $this->templateSearch = '';
        session()->flash('success', "Modelo de trabalho '{$ritualWork->name}' carregado com sucesso!");
    }

    public function openSaveTemplateModal(): void
    {
        if (empty($this->newTemplateName) && !empty($this->notes)) {
            $this->newTemplateName = $this->notes;
        }
        $this->showSaveTemplateModal = true;
    }

    public function saveAsTemplate(): void
    {
        $items = $this->optionItems[$this->activeOptionIndex];

        if (empty($items)) {
            $this->addError('newTemplateName', 'Adicione pelo menos um item à opção atual antes de salvar como modelo.');
            return;
        }

        $this->validate([
            'newTemplateName' => ['required', 'string', 'max:150'],
            'newTemplateDescription' => ['nullable', 'string', 'max:500'],
        ], [
            'newTemplateName.required' => 'O nome do modelo de trabalho é obrigatório.'
        ]);

        $ritualWork = RitualWork::create([
            'name' => trim($this->newTemplateName),
            'description' => trim($this->newTemplateDescription) ?: 'Modelo criado via gerador de link de orçamento.',
            'estimated_days' => 1,
            'active' => true,
        ]);

        foreach ($items as $item) {
            $supplyName = trim($item['name']);
            if (!$supplyName) {
                continue;
            }

            // Find or create supply
            $supply = null;
            if (!empty($item['supply_id'])) {
                $supply = Supply::find($item['supply_id']);
            }
            if (!$supply) {
                $supply = Supply::whereRaw('LOWER(name) = ?', [strtolower($supplyName)])->first();
            }
            if (!$supply) {
                $supply = Supply::create([
                    'name' => $supplyName,
                    'category' => 'Artigos Religiosos',
                    'unit' => 'unidade',
                    'active' => true,
                ]);
            }

            $ritualWork->supplies()->attach($supply->id, [
                'default_quantity' => (float)($item['quantity'] ?? 1.0)
            ]);
        }

        $this->newTemplateName = '';
        $this->newTemplateDescription = '';
        $this->showSaveTemplateModal = false;

        session()->flash('success', 'Modelo de trabalho salvo com sucesso para reuso!');
    }

    public function render()
    {
        $searchResults = [];
        if (strlen(trim($this->supplySearch)) >= 2) {
            $searchResults = Supply::with('prices')
                ->where('name', 'like', '%' . trim($this->supplySearch) . '%')
                ->orderBy('name')
                ->take(30)
                ->get();
        } elseif ($this->showSupplyCatalog) {
            $searchResults = Supply::with('prices')
                ->where('active', true)
                ->orderBy('category')->orderBy('name')
                ->get();
        }

        $animalResults = [];
        if (strlen(trim($this->animalSearch)) >= 2) {
            $animalResults = Animal::with('prices')
                ->where('name', 'like', '%' . trim($this->animalSearch) . '%')
                ->orderBy('name')
                ->take(30)
                ->get();
        } elseif ($this->showAnimalCatalog) {
            $animalResults = Animal::with('prices')
                ->where('active', true)
                ->orderBy('name')
                ->get();
        }

        $templateResults = [];
        if (strlen(trim($this->templateSearch)) >= 2) {
            $templateResults = RitualWork::where('name', 'like', '%' . trim($this->templateSearch) . '%')
                ->orderBy('name')
                ->take(10)
                ->get();
        }

        $quickPayments = QuickPayment::with('user')->latest()->take(10)->get();

        $optionSummaries = array_map(fn ($i) => [
            'index' => $i,
            'filled' => $this->isOptionFilled($i),
            'subtotal' => $this->subtotalFor($i),
            'total' => $this->totalFor($i),
        ], [0, 1, 2]);

        return view('livewire.admin.oracular.quick-payment-builder', [
            'searchResults' => $searchResults,
            'animalResults' => $animalResults,
            'templateResults' => $templateResults,
            'quickPayments' => $quickPayments,
            'subtotal' => $this->getSubtotalProperty(),
            'freight' => $this->freightFor($this->activeOptionIndex),
            'profit' => $this->profitFor($this->activeOptionIndex),
            'laborCost' => $this->laborCostFor($this->activeOptionIndex),
            'total' => $this->getTotalProperty(),
            'optionSummaries' => $optionSummaries,
        ]);
    }
}
