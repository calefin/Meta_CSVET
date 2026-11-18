<?php

namespace App\Livewire\Admin;

use App\Models\Animal;
use App\Models\Supply;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.admin')]
class PriceMonitor extends Component
{
    use WithFileUploads;

    public string $alertThreshold = '20';

    public string $selectedType = '';

    public string $selectedItemId = '';

    public $jsonFile;

    private function variationRows($items, string $priceRelation): array
    {
        return $items->map(function ($item) use ($priceRelation) {
            $prices = $item->{$priceRelation}()->orderByDesc('purchase_date')->limit(2)->get();

            if ($prices->count() < 2) {
                return null;
            }

            [$latest, $previous] = $prices;
            $change = $previous->unit_price > 0
                ? (($latest->unit_price - $previous->unit_price) / $previous->unit_price) * 100
                : 0;

            return [
                'name' => $item->name,
                'latest_price' => $latest->unit_price,
                'previous_price' => $previous->unit_price,
                'change_percent' => round($change, 1),
                'latest_date' => $latest->purchase_date,
            ];
        })->filter()->sortByDesc('change_percent')->values()->all();
    }

    public function updatedSelectedType(): void
    {
        $this->selectedItemId = '';
    }

    public function getItemsProperty()
    {
        if ($this->selectedType === 'animal') {
            return Animal::orderBy('name')->get();
        }
        if ($this->selectedType === 'supply') {
            return Supply::orderBy('name')->get();
        }
        return collect();
    }

    public function getHistoryProperty()
    {
        if (!$this->selectedType || !$this->selectedItemId) {
            return null;
        }

        if ($this->selectedType === 'animal') {
            $prices = \App\Models\AnimalPrice::with('supplier')
                ->where('animal_id', (int) $this->selectedItemId)
                ->orderBy('purchase_date', 'asc')
                ->get();
        } else {
            $prices = \App\Models\SupplyPrice::with('supplier')
                ->where('supply_id', (int) $this->selectedItemId)
                ->orderBy('purchase_date', 'asc')
                ->get();
        }

        $historyRows = [];
        $prevPrice = null;
        foreach ($prices as $p) {
            $change = 0;
            if ($prevPrice !== null && $prevPrice > 0) {
                $change = (($p->unit_price - $prevPrice) / $prevPrice) * 100;
            }
            $historyRows[] = [
                'date' => $p->purchase_date,
                'price' => $p->unit_price,
                'supplier' => $p->supplier?->name ?? '—',
                'change_percent' => $prevPrice !== null ? round($change, 1) : null,
            ];
            $prevPrice = $p->unit_price;
        }

        // Sort descending by date for visual listing
        usort($historyRows, fn($a, $b) => strcmp($b['date']->toDateString(), $a['date']->toDateString()));

        $avg = $prices->avg('unit_price') ?? 0;
        $max = $prices->max('unit_price') ?? 0;
        $min = $prices->min('unit_price') ?? 0;

        $quarters = [
            'Q1' => [],
            'Q2' => [],
            'Q3' => [],
            'Q4' => [],
        ];

        foreach ($prices as $p) {
            $month = $p->purchase_date->month;
            if ($month >= 1 && $month <= 3) {
                $quarters['Q1'][] = $p->unit_price;
            } elseif ($month >= 4 && $month <= 6) {
                $quarters['Q2'][] = $p->unit_price;
            } elseif ($month >= 7 && $month <= 9) {
                $quarters['Q3'][] = $p->unit_price;
            } else {
                $quarters['Q4'][] = $p->unit_price;
            }
        }

        $quarterAverages = [];
        foreach ($quarters as $q => $vals) {
            $quarterAverages[$q] = count($vals) > 0 ? array_sum($vals) / count($vals) : null;
        }

        return [
            'rows' => $historyRows,
            'avg' => $avg,
            'max' => $max,
            'min' => $min,
            'quarters' => $quarterAverages,
        ];
    }

    public function importJson(): void
    {
        $this->validate([
            'jsonFile' => ['required', 'file', 'mimes:json,txt', 'max:1024'],
        ]);

        $content = file_get_contents($this->jsonFile->getRealPath());
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->addError('jsonFile', 'O arquivo enviado não é um JSON válido. Verifique a formatação.');
            return;
        }

        if (empty($data['supplier_name'])) {
            $this->addError('jsonFile', 'O campo "supplier_name" é obrigatório no JSON.');
            return;
        }

        if (!isset($data['prices']) || !is_array($data['prices'])) {
            $this->addError('jsonFile', 'O campo "prices" é obrigatório e deve ser uma lista de preços.');
            return;
        }

        $supplierName = trim($data['supplier_name']);
        $purchaseDate = !empty($data['purchase_date']) ? trim($data['purchase_date']) : now()->toDateString();

        $validatedPrices = [];
        foreach ($data['prices'] as $idx => $row) {
            $rowNum = $idx + 1;
            if (empty($row['type']) || !in_array($row['type'], ['animal', 'supply'])) {
                $this->addError('jsonFile', "Item #{$rowNum}: O campo 'type' deve ser 'animal' ou 'supply'.");
                return;
            }
            if (empty($row['name'])) {
                $this->addError('jsonFile', "Item #{$rowNum}: O campo 'name' é obrigatório.");
                return;
            }
            if (!isset($row['price']) || !is_numeric($row['price']) || $row['price'] < 0) {
                $this->addError('jsonFile', "Item #{$rowNum}: O campo 'price' é obrigatório e deve ser um número positivo.");
                return;
            }

            $paymentMethod = !empty($row['payment_method']) ? strtolower(trim($row['payment_method'])) : null;
            if ($paymentMethod && !in_array($paymentMethod, ['dinheiro', 'credito', 'debito', 'crédito', 'débito'])) {
                $this->addError('jsonFile', "Item #{$rowNum}: O campo 'payment_method' deve ser 'dinheiro', 'credito' ou 'debito'.");
                return;
            }

            if ($paymentMethod === 'crédito') {
                $paymentMethod = 'credito';
            }
            if ($paymentMethod === 'débito') {
                $paymentMethod = 'debito';
            }

            $minQty = isset($row['min_quantity']) && is_numeric($row['min_quantity']) ? (float)$row['min_quantity'] : null;

            $validatedPrices[] = [
                'type' => $row['type'],
                'name' => trim($row['name']),
                'price' => (float)$row['price'],
                'payment_method' => $paymentMethod,
                'min_quantity' => $minQty,
            ];
        }

        $supplier = \App\Models\Supplier::firstOrCreate(
            ['name' => $supplierName],
            ['type' => 'geral', 'active' => true]
        );

        $animalsCount = 0;
        $suppliesCount = 0;

        foreach ($validatedPrices as $item) {
            if ($item['type'] === 'animal') {
                $animal = Animal::firstOrCreate(
                    ['name' => $item['name']],
                    ['supplier_id' => $supplier->id, 'unit' => 'unidade', 'active' => true]
                );

                \App\Models\AnimalPrice::create([
                    'animal_id' => $animal->id,
                    'supplier_id' => $supplier->id,
                    'unit_price' => $item['price'],
                    'payment_method' => $item['payment_method'],
                    'min_quantity' => $item['min_quantity'],
                    'purchase_date' => $purchaseDate,
                ]);
                $animalsCount++;
            } else {
                $supply = Supply::firstOrCreate(
                    ['name' => $item['name']],
                    ['supplier_id' => $supplier->id, 'unit' => 'unidade', 'active' => true]
                );

                \App\Models\SupplyPrice::create([
                    'supply_id' => $supply->id,
                    'supplier_id' => $supplier->id,
                    'unit_price' => $item['price'],
                    'payment_method' => $item['payment_method'],
                    'min_quantity' => $item['min_quantity'],
                    'purchase_date' => $purchaseDate,
                ]);
                $suppliesCount++;
            }
        }

        session()->flash('import_status', "Importação concluída com sucesso! Cadastrados: {$animalsCount} preços de animais e {$suppliesCount} preços de insumos para o fornecedor '{$supplier->name}'.");
        
        $this->reset('jsonFile');
    }

    public function render()
    {
        $supplyRows = $this->variationRows(Supply::all(), 'prices');
        $animalRows = $this->variationRows(Animal::all(), 'prices');

        return view('livewire.admin.price-monitor', [
            'supplyRows' => $supplyRows,
            'animalRows' => $animalRows,
            'items' => $this->items,
            'history' => $this->history,
        ]);
    }
}
