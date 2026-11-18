<?php

namespace App\Livewire\Admin\Oracular;

use App\Mail\BudgetSentMail;
use App\Models\Animal;
use App\Models\Budget;
use App\Models\ExpenseItem;
use App\Models\RecommendedWork;
use App\Models\Supply;
use App\Services\BudgetBuilderService;
use App\Services\ExpenseItemCatalogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.admin')]
class BudgetBuilder extends Component
{
    public RecommendedWork $recommendedWork;

    public ?int $budgetId = null;

    // One markup/fee pair per option, since each option can be sold independently.
    public array $markup_percent = ['0', '0', '0'];

    public array $fee_percent = ['0', '0', '0'];

    public string $notes = '';

    public string $supplySearch = '';

    public string $animalSearch = '';

    public bool $showSupplyCatalog = false;

    public bool $showAnimalCatalog = false;

    public string $expenseItemSearch = '';

    public bool $showExpenseItemCatalog = false;

    // Up to 3 independent options. Each slot is an array of item rows:
    // ['item_type' => string, 'item_id' => int|string|null, 'description' => string, 'quantity' => string, 'unit_price' => string, 'by_client' => bool]
    /** @var array<int, array<int, array{item_type: string, item_id: int|string|null, description: string, quantity: string, unit_price: string, by_client: bool}>> */
    public array $optionItems = [[], [], []];

    // Free-text comment shown to the client for each option, to help them tell the options apart.
    public array $optionComments = ['', '', ''];

    // When true for index 1/2, that option's price is Option 1's total PLUS
    // this option's own items — the client can do only Option 1, or Option 1
    // plus this add-on. Index 0 is never linked (it is the base itself).
    public array $optionLinkedToFirst = [false, false, false];

    // Which option (0, 1 or 2) is currently being edited
    public int $activeOptionIndex = 0;

    public function mount(RecommendedWork $recommendedWork): void
    {
        $this->recommendedWork = $recommendedWork->load('ritualWork.supplies', 'ritualWork.animals', 'budgets.items', 'user', 'consultation');

        $existing = $recommendedWork->budgets->whereNotIn('status', ['cancelled'])->last();

        if ($existing) {
            $this->budgetId = $existing->id;
            $this->notes = (string) $existing->notes;

            $this->optionItems[0] = $existing->items->map(fn ($item) => [
                'item_type' => $item->item_type,
                'item_id' => $item->item_id,
                'description' => $item->description,
                'quantity' => (string) $item->quantity,
                'unit_price' => (string) $item->unit_price,
                'by_client' => $item->by_client,
            ])->all();

            foreach ([0, 1, 2] as $i) {
                $option = $existing->options[$i] ?? null;
                $this->optionComments[$i] = $option['comment'] ?? '';
                // Older budgets saved before per-option markup/fee only have the
                // top-level scalar, which applied to every option at the time.
                $this->markup_percent[$i] = (string) ($option['markup_percent'] ?? $existing->markup_percent);
                $this->fee_percent[$i] = (string) ($option['fee_percent'] ?? $existing->fee_percent);
                $this->optionLinkedToFirst[$i] = (bool) ($option['linked_to_first'] ?? false);

                if ($i === 0 || ! $option) {
                    continue;
                }

                $this->optionItems[$i] = array_map(fn ($item) => [
                    'item_type' => $item['item_type'],
                    'item_id' => $item['item_id'],
                    'description' => $item['description'],
                    'quantity' => (string) $item['quantity'],
                    'unit_price' => (string) $item['unit_price'],
                    'by_client' => (bool) $item['by_client'],
                ], $option['items'] ?? []);
            }

            return;
        }

        foreach ($recommendedWork->ritualWork->supplies as $supply) {
            $latestPrice = $supply->prices()->latest('purchase_date')->first();

            $this->optionItems[0][] = [
                'item_type' => 'supply',
                'item_id' => $supply->id,
                'description' => $supply->name,
                'quantity' => (string) $supply->pivot->default_quantity,
                'unit_price' => $latestPrice ? (string) $latestPrice->unit_price : '0',
                'by_client' => false,
            ];
        }

        foreach ($recommendedWork->ritualWork->animals as $animal) {
            $latestPrice = $animal->prices()->latest('purchase_date')->first();

            $this->optionItems[0][] = [
                'item_type' => 'animal',
                'item_id' => $animal->id,
                'description' => $animal->name,
                'quantity' => (string) $animal->pivot->default_quantity,
                'unit_price' => $latestPrice ? (string) $latestPrice->unit_price : '0',
                'by_client' => false,
            ];
        }
    }

    protected function rules(): array
    {
        return [
            'markup_percent.*' => ['required', 'numeric', 'min:0'],
            'fee_percent.*' => ['required', 'numeric', 'min:0', 'max:99'],
            'notes' => ['nullable', 'string'],
            'optionItems.*.*.item_type' => ['required', 'in:supply,animal,custom,personal_payment'],
            'optionItems.*.*.item_id' => ['required_unless:optionItems.*.*.item_type,custom,personal_payment', 'nullable', 'integer'],
            'optionItems.*.*.description' => ['required', 'string', 'max:200'],
            'optionItems.*.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'optionItems.*.*.unit_price' => ['required', 'numeric', 'min:0'],
            'optionItems.*.*.by_client' => ['boolean'],
            'optionComments.*' => ['nullable', 'string', 'max:500'],
            'optionLinkedToFirst.*' => ['boolean'],
        ];
    }

    public function switchOption(int $index): void
    {
        $this->activeOptionIndex = max(0, min(2, $index));
    }

    public function addSupplyRow(int $supplyId): void
    {
        $supply = Supply::findOrFail($supplyId);
        $latestPrice = $supply->prices()->latest('purchase_date')->first();

        $this->optionItems[$this->activeOptionIndex][] = [
            'item_type' => 'supply',
            'item_id' => $supply->id,
            'description' => $supply->name,
            'quantity' => '1',
            'unit_price' => $latestPrice ? (string) $latestPrice->unit_price : '0',
            'by_client' => false,
        ];

        $this->supplySearch = '';
    }

    public function addAnimalRow(int $animalId): void
    {
        $animal = Animal::findOrFail($animalId);
        $latestPrice = $animal->prices()->latest('purchase_date')->first();

        $this->optionItems[$this->activeOptionIndex][] = [
            'item_type' => 'animal',
            'item_id' => $animal->id,
            'description' => $animal->name,
            'quantity' => '1',
            'unit_price' => $latestPrice ? (string) $latestPrice->unit_price : '0',
            'by_client' => false,
        ];

        $this->animalSearch = '';
    }

    public function addCustomRow(): void
    {
        $this->optionItems[$this->activeOptionIndex][] = [
            'item_type' => 'custom',
            'item_id' => null,
            'description' => '',
            'quantity' => '1',
            'unit_price' => '0',
            'by_client' => false,
        ];
    }

    public function addPersonalPaymentRow(): void
    {
        $this->optionItems[$this->activeOptionIndex][] = [
            'item_type' => 'personal_payment',
            'item_id' => null,
            'description' => 'Pagamento pessoal',
            'quantity' => '1',
            'unit_price' => '0',
            'by_client' => false,
        ];
    }

    public function addExpenseItemRow(int $expenseItemId): void
    {
        $expenseItem = ExpenseItem::findOrFail($expenseItemId);

        $this->optionItems[$this->activeOptionIndex][] = [
            'item_type' => 'custom',
            'item_id' => $expenseItem->id,
            'description' => $expenseItem->name,
            'quantity' => '1',
            'unit_price' => (string) $expenseItem->unit_price,
            'by_client' => false,
        ];

        $this->expenseItemSearch = '';
    }

    public function removeRow(int $index): void
    {
        unset($this->optionItems[$this->activeOptionIndex][$index]);
        $this->optionItems[$this->activeOptionIndex] = array_values($this->optionItems[$this->activeOptionIndex]);
    }

    public function isOptionFilled(int $index): bool
    {
        return ! empty($this->optionItems[$index]);
    }

    public function subtotalFor(int $index): float
    {
        $subtotal = 0;

        foreach ($this->optionItems[$index] ?? [] as $item) {
            if (! $item['by_client']) {
                $subtotal += (float) $item['quantity'] * (float) $item['unit_price'];
            }
        }

        return round($subtotal, 2);
    }

    public function totalFor(int $index): float
    {
        $totalAfterMarkup = $this->subtotalFor($index) * (1 + ((float) $this->markup_percent[$index] / 100));

        $feePercent = (float) $this->fee_percent[$index];
        if ($feePercent > 0 && $feePercent < 100) {
            $total = $totalAfterMarkup / (1 - ($feePercent / 100));
        } else {
            $total = $totalAfterMarkup;
        }

        $ownTotal = round($total, 2);

        if ($index > 0 && ($this->optionLinkedToFirst[$index] ?? false)) {
            return round($ownTotal + $this->totalFor(0), 2);
        }

        return $ownTotal;
    }

    /**
     * Own subtotal plus Option 1's, when this option is marked as an add-on
     * to Option 1 rather than a standalone alternative. For display/storage
     * only — the markup/fee calc in totalFor() always uses this option's
     * own subtotal alone.
     */
    public function combinedSubtotalFor(int $index): float
    {
        $subtotal = $this->subtotalFor($index);

        if ($index > 0 && ($this->optionLinkedToFirst[$index] ?? false)) {
            $subtotal += $this->subtotalFor(0);
        }

        return round($subtotal, 2);
    }

    private function persist(string $status): Budget
    {
        $validated = $this->validate();

        ExpenseItemCatalogService::registerFromOptions($this->optionItems);

        $options = [];
        foreach ([0, 1, 2] as $i) {
            $options[$i] = $this->isOptionFilled($i) ? [
                'items' => $this->optionItems[$i],
                'subtotal' => $this->combinedSubtotalFor($i),
                'total' => $this->totalFor($i),
                'comment' => trim($this->optionComments[$i]),
                'markup_percent' => (float) $this->markup_percent[$i],
                'fee_percent' => (float) $this->fee_percent[$i],
                'linked_to_first' => (bool) $this->optionLinkedToFirst[$i],
            ] : null;
        }

        $budget = BudgetBuilderService::save(
            $this->recommendedWork,
            $this->budgetId,
            $options,
            $this->subtotalFor(0),
            $this->totalFor(0),
            $validated['markup_percent'][0],
            $validated['fee_percent'][0],
            $validated['notes'],
            $status,
            Auth::id(),
        );

        $this->budgetId = $budget->id;

        return $budget;
    }

    public function saveDraft(): void
    {
        $this->persist('draft');
    }

    public function send(): void
    {
        if (! $this->isOptionFilled(0)) {
            $this->addError('send', 'A Opção 1 é obrigatória. Adicione ao menos um item antes de enviar.');
            return;
        }

        foreach ([0, 1, 2] as $i) {
            if ($this->isOptionFilled($i) && $this->totalFor($i) <= 0) {
                $this->addError('send', 'O valor final da Opção '.($i + 1).' não pode ser zero. Adicione itens ou ajuste os valores.');
                return;
            }
        }

        $budget = $this->persist('sent');

        try {
            Mail::to($budget->user->email)->send(new BudgetSentMail($budget, $budget->user));
        } catch (\Exception $e) {
            // Email driver issues should not block the budget from being sent.
        }
    }

    public function render()
    {
        $subtotal = $this->combinedSubtotalFor($this->activeOptionIndex);
        $ownSubtotal = $this->subtotalFor($this->activeOptionIndex);
        $total = $this->totalFor($this->activeOptionIndex);

        $supplyResults = [];
        if (strlen(trim($this->supplySearch)) >= 2) {
            $supplyResults = Supply::with('prices')
                ->where('name', 'like', '%'.trim($this->supplySearch).'%')
                ->orderBy('name')->take(30)->get();
        } elseif ($this->showSupplyCatalog) {
            $supplyResults = Supply::with('prices')
                ->where('active', true)
                ->orderBy('category')->orderBy('name')->get();
        }

        $animalResults = [];
        if (strlen(trim($this->animalSearch)) >= 2) {
            $animalResults = Animal::with('prices')
                ->where('name', 'like', '%'.trim($this->animalSearch).'%')
                ->orderBy('name')->take(30)->get();
        } elseif ($this->showAnimalCatalog) {
            $animalResults = Animal::with('prices')
                ->where('active', true)
                ->orderBy('name')->get();
        }

        $expenseItemResults = [];
        if (strlen(trim($this->expenseItemSearch)) >= 2) {
            $expenseItemResults = ExpenseItem::where('active', true)
                ->where('name', 'like', '%'.trim($this->expenseItemSearch).'%')
                ->orderBy('name')->take(30)->get();
        } elseif ($this->showExpenseItemCatalog) {
            $expenseItemResults = ExpenseItem::where('active', true)->orderBy('name')->get();
        }

        $optionSummaries = array_map(fn ($i) => [
            'index' => $i,
            'filled' => $this->isOptionFilled($i),
            'subtotal' => $this->combinedSubtotalFor($i),
            'total' => $this->totalFor($i),
        ], [0, 1, 2]);

        return view('livewire.admin.oracular.budget-builder', [
            'subtotal' => $subtotal,
            'ownSubtotal' => $ownSubtotal,
            'total' => $total,
            'supplyResults' => $supplyResults,
            'animalResults' => $animalResults,
            'expenseItemResults' => $expenseItemResults,
            'optionSummaries' => $optionSummaries,
        ]);
    }
}
