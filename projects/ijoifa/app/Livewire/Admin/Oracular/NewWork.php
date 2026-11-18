<?php

namespace App\Livewire\Admin\Oracular;

use App\Livewire\Concerns\HasPricedOptions;
use App\Mail\BudgetSentMail;
use App\Mail\QuickPaymentLinkMail;
use App\Models\Animal;
use App\Models\ExpenseItem;
use App\Models\IreIbiType;
use App\Models\Odu;
use App\Models\Payment;
use App\Models\QuickPayment;
use App\Models\RecommendedWork;
use App\Models\RitualWork;
use App\Models\Supply;
use App\Models\User;
use App\Models\WorkTemplate;
use App\Services\BudgetBuilderService;
use App\Services\ExpenseItemCatalogService;
use App\Services\QuickPaymentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.admin')]
class NewWork extends Component
{
    use HasPricedOptions;

    public ?int $selectedClientId = null;

    public string $clientSearch = '';

    public string $destination = 'quick_link'; // quick_link | formal

    public ?int $ritualWorkId = null;

    public ?int $existingRecommendedWorkId = null;

    public string $quickType = 'trabalhos_gerais';

    public string $notes = '';

    // One markup/fee pair per option, since each option can be sold independently.
    public array $markup_percent = ['0', '0', '0'];

    public array $fee_percent = ['0', '0', '0'];

    public string $supplySearch = '';

    public string $animalSearch = '';

    public bool $showSupplyCatalog = false;

    public bool $showAnimalCatalog = false;

    public string $expenseItemSearch = '';

    public bool $showExpenseItemCatalog = false;

    // Up to 3 independent options, same row shape as BudgetBuilder
    // (item_type/item_id/description/quantity/unit_price/by_client).
    /** @var array<int, array<int, array{item_type: string, item_id: int|string|null, description: string, quantity: string, unit_price: string, by_client: bool}>> */
    public array $optionItems = [[], [], []];

    public array $optionComments = ['', '', ''];

    // When true for index 1/2, that option's price is Option 1's total PLUS
    // this option's own items — the client can do only Option 1, or Option 1
    // plus this add-on. Index 0 is never linked (it is the base itself).
    public array $optionLinkedToFirst = [false, false, false];

    public string $templateName = '';

    public ?int $selectedTemplateId = null;

    // Shortcut for quickType === 'consulta': a single price per option instead
    // of building it up from the items table (kept in sync with optionItems.0
    // under the hood so markup/fee/total math doesn't need a separate path).
    public array $consultaValues = ['', '', ''];

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

    public function mount(): void
    {
        $source = request()->query('duplicate_source');
        $id = request()->query('duplicate_id');

        if ($source === 'quick_payment' && $id) {
            $this->loadFromQuickPayment(QuickPayment::find($id));
        } elseif ($source === 'recommended_work' && $id) {
            $this->loadFromRecommendedWork(RecommendedWork::find($id));
        }

        foreach ([0, 1, 2] as $i) {
            $this->consultaValues[$i] = $this->optionItems[$i][0]['unit_price'] ?? '';
        }
    }

    public function updatedConsultaValues($value, $key): void
    {
        $index = (int) $key;

        $this->optionItems[$index] = $value === '' ? [] : [[
            'item_type' => 'custom',
            'item_id' => null,
            'description' => 'Consulta',
            'quantity' => '1',
            'unit_price' => $value,
            'by_client' => false,
        ]];
    }

    private function loadFromQuickPayment(?QuickPayment $quickPayment): void
    {
        if (! $quickPayment) {
            return;
        }

        $this->quickType = $quickPayment->type;
        $this->notes = (string) $quickPayment->notes;

        foreach ([0, 1, 2] as $i) {
            $option = $quickPayment->options[$i] ?? null;

            $this->markup_percent[$i] = (string) ($option['markup_percent'] ?? $quickPayment->markup_percent);
            $this->fee_percent[$i] = (string) ($option['fee_percent'] ?? $quickPayment->fee_percent);
            $this->optionLinkedToFirst[$i] = (bool) ($option['linked_to_first'] ?? false);
            $this->optionComments[$i] = $option['comment'] ?? '';

            if (! $option) {
                continue;
            }

            // Quick-payment items don't retain item_type/item_id (they were
            // flattened to name/price when the link was generated), so a
            // duplicated draft treats every item as a fresh "outro gasto"
            // row — prices and descriptions are preserved, the catalog link
            // isn't.
            $this->optionItems[$i] = array_map(fn ($item) => [
                'item_type' => 'custom',
                'item_id' => null,
                'description' => $item['name'],
                'quantity' => (string) $item['quantity'],
                'unit_price' => (string) $item['unit_price'],
                'by_client' => (bool) ($item['by_client'] ?? false),
            ], $option['items'] ?? []);
        }
    }

    private function loadFromRecommendedWork(?RecommendedWork $recommendedWork): void
    {
        if (! $recommendedWork) {
            return;
        }

        $budget = $recommendedWork->budgets()->whereNotIn('status', ['cancelled'])->latest()->first();

        if (! $budget) {
            return;
        }

        $this->notes = (string) $budget->notes;

        $this->optionItems[0] = $budget->items->map(fn ($item) => [
            'item_type' => $item->item_type,
            'item_id' => $item->item_id,
            'description' => $item->description,
            'quantity' => (string) $item->quantity,
            'unit_price' => (string) $item->unit_price,
            'by_client' => $item->by_client,
        ])->all();

        foreach ([0, 1, 2] as $i) {
            $option = $budget->options[$i] ?? null;

            $this->markup_percent[$i] = (string) ($option['markup_percent'] ?? $budget->markup_percent);
            $this->fee_percent[$i] = (string) ($option['fee_percent'] ?? $budget->fee_percent);
            $this->optionLinkedToFirst[$i] = (bool) ($option['linked_to_first'] ?? false);
            $this->optionComments[$i] = $option['comment'] ?? '';

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
    }

    public function updatedSelectedClientId(): void
    {
        $this->existingRecommendedWorkId = null;
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

    public function loadTemplate(int $ritualWorkId): void
    {
        $work = RitualWork::with(['supplies.prices', 'animals.prices'])->findOrFail($ritualWorkId);

        foreach ($work->supplies as $supply) {
            $latestPrice = $supply->prices->sortByDesc('purchase_date')->first();

            $this->optionItems[$this->activeOptionIndex][] = [
                'item_type' => 'supply',
                'item_id' => $supply->id,
                'description' => $supply->name,
                'quantity' => (string) $supply->pivot->default_quantity,
                'unit_price' => $latestPrice ? (string) $latestPrice->unit_price : '0',
                'by_client' => false,
            ];
        }

        foreach ($work->animals as $animal) {
            $latestPrice = $animal->prices->sortByDesc('purchase_date')->first();

            $this->optionItems[$this->activeOptionIndex][] = [
                'item_type' => 'animal',
                'item_id' => $animal->id,
                'description' => $animal->name,
                'quantity' => (string) $animal->pivot->default_quantity,
                'unit_price' => $latestPrice ? (string) $latestPrice->unit_price : '0',
                'by_client' => false,
            ];
        }
    }

    public function saveAsTemplate(): void
    {
        $name = trim($this->templateName);

        if ($name === '') {
            $this->addError('templateName', 'Dê um nome para o modelo antes de salvar.');

            return;
        }

        if (! $this->isOptionFilled(0)) {
            $this->addError('templateName', 'Adicione ao menos um item na Opção 1 antes de salvar como modelo.');

            return;
        }

        WorkTemplate::create([
            'name' => $name,
            'data' => [
                'optionItems' => $this->optionItems,
                'optionComments' => $this->optionComments,
                'markup_percent' => $this->markup_percent,
                'fee_percent' => $this->fee_percent,
                'optionLinkedToFirst' => $this->optionLinkedToFirst,
            ],
        ]);

        $this->templateName = '';
        session()->flash('success', 'Modelo salvo com sucesso.');
    }

    public function applyTemplate(): void
    {
        if (! $this->selectedTemplateId) {
            return;
        }

        $template = WorkTemplate::find($this->selectedTemplateId);

        if (! $template) {
            return;
        }

        $data = $template->data;

        $this->optionItems = $data['optionItems'] ?? [[], [], []];
        $this->optionComments = $data['optionComments'] ?? ['', '', ''];
        $this->markup_percent = $data['markup_percent'] ?? ['0', '0', '0'];
        $this->fee_percent = $data['fee_percent'] ?? ['0', '0', '0'];
        $this->optionLinkedToFirst = $data['optionLinkedToFirst'] ?? [false, false, false];
        $this->activeOptionIndex = 0;
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

    public function totalFor(int $index): float
    {
        $ownTotal = $this->applyMarkupAndFee($this->subtotalFor($index), (float) $this->markup_percent[$index], (float) $this->fee_percent[$index]);

        if ($index > 0 && ($this->optionLinkedToFirst[$index] ?? false)) {
            return round($ownTotal + $this->totalFor(0), 2);
        }

        return $ownTotal;
    }

    private function buildOptions(): array
    {
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

        return $options;
    }

    public function save(): void
    {
        if (! $this->isOptionFilled(0)) {
            $this->addError('save', 'A Opção 1 é obrigatória. Adicione ao menos um item antes de salvar.');

            return;
        }

        foreach ([0, 1, 2] as $i) {
            if ($this->isOptionFilled($i) && $this->totalFor($i) <= 0) {
                $this->addError('save', 'O valor final da Opção '.($i + 1).' não pode ser zero. Adicione itens ou ajuste os valores.');

                return;
            }
        }

        if ($this->destination === 'formal') {
            $this->saveFormal();
        } else {
            $this->saveQuickLink();
        }
    }

    private function saveFormal(): void
    {
        if (! $this->selectedClientId) {
            $this->addError('selectedClientId', 'Selecione um cliente para gerar um orçamento formal.');

            return;
        }

        if ($this->existingRecommendedWorkId) {
            $this->redirect(route('admin.oracular.budget', ['recommendedWork' => $this->existingRecommendedWorkId]));

            return;
        }

        if (! $this->ritualWorkId) {
            $this->addError('ritualWorkId', 'Selecione o trabalho do catálogo para o orçamento formal.');

            return;
        }

        $validated = $this->validate();

        $client = User::findOrFail($this->selectedClientId);

        $recommendedWork = DB::transaction(function () use ($client, $validated) {
            $consultation = $client->consultations()->latest('consulted_at')->first();

            if (! $consultation) {
                $consultation = $client->consultations()->create([
                    'user_id' => $client->id,
                    'admin_id' => Auth::id(),
                    'consulted_at' => now(),
                    'modality' => 'online',
                    'odu_main_id' => Odu::orderBy('number')->first()->id,
                    'ire_ibi' => 'ire',
                    'ire_ibi_type_id' => IreIbiType::where('classification', 'ire')->first()->id,
                    'general_guidance' => 'Consulta iniciada automaticamente para registro de orçamento avulso via Novo Trabalho.',
                    'created_by' => Auth::id(),
                ]);
            }

            return $consultation->recommendedWorks()->create([
                'user_id' => $client->id,
                'ritual_work_id' => $this->ritualWorkId,
                'notes' => $validated['notes'] ?: 'Recomendado via Novo Trabalho.',
                'status' => 'budget_requested',
            ]);
        });

        $budget = BudgetBuilderService::save(
            $recommendedWork,
            null,
            $this->buildOptions(),
            $this->subtotalFor(0),
            $this->totalFor(0),
            $validated['markup_percent'][0],
            $validated['fee_percent'][0],
            $validated['notes'],
            'sent',
            Auth::id(),
        );

        ExpenseItemCatalogService::registerFromOptions($this->optionItems);

        try {
            Mail::to($client->email)->send(new BudgetSentMail($budget, $client));
        } catch (\Exception $e) {
            // Email driver issues should not block the budget from being sent.
        }

        session()->flash('success', 'Orçamento criado e enviado para '.$client->name.'.');
        $this->redirect(route('admin.oracular.works'));
    }

    private function saveQuickLink(): void
    {
        $validated = $this->validate();

        $options = [];
        foreach ([0, 1, 2] as $i) {
            $options[$i] = $this->isOptionFilled($i) ? [
                'items' => array_map(fn ($item) => [
                    'name' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'supplier_id' => null,
                    'supplier_name' => null,
                    'by_client' => $item['by_client'],
                ], $this->optionItems[$i]),
                'subtotal' => $this->combinedSubtotalFor($i),
                'freight' => 0,
                'profit' => 0,
                'labor_cost' => 0,
                'total' => $this->totalFor($i),
                'comment' => trim($this->optionComments[$i]),
                'markup_percent' => (float) $this->markup_percent[$i],
                'fee_percent' => (float) $this->fee_percent[$i],
                'linked_to_first' => (bool) $this->optionLinkedToFirst[$i],
            ] : null;
        }

        $quickPayment = QuickPaymentService::generate(
            $this->quickType,
            $validated['notes'],
            $options,
            $validated['markup_percent'][0],
            $validated['fee_percent'][0],
            $this->selectedClientId,
            $this->existingRecommendedWorkId,
        );

        ExpenseItemCatalogService::registerFromOptions($this->optionItems);

        if ($this->selectedClientId) {
            $client = User::findOrFail($this->selectedClientId);

            $payment = Payment::findOrCreatePending(
                QuickPayment::class,
                $quickPayment->id,
                $client->id,
                (float) $quickPayment->total,
                false
            );

            try {
                Mail::to($client->email)->send(new QuickPaymentLinkMail($quickPayment, $client, $payment));
            } catch (\Exception $e) {
                // Email driver issues should not block link generation.
            }

            session()->flash('success', 'Link gerado e enviado para '.$client->name.'.');
        } else {
            session()->flash('success', 'Link de pagamento gerado com sucesso! Copie e envie para o cliente.');
        }

        $this->redirect(route('admin.oracular.works'));
    }

    public function render()
    {
        $subtotal = $this->combinedSubtotalFor($this->activeOptionIndex);
        $total = $this->totalFor($this->activeOptionIndex);

        $optionSummaries = array_map(fn ($i) => [
            'index' => $i,
            'filled' => $this->isOptionFilled($i),
            'subtotal' => $this->combinedSubtotalFor($i),
            'total' => $this->totalFor($i),
        ], [0, 1, 2]);

        $supplyResults = [];
        if (strlen(trim($this->supplySearch)) >= 2) {
            $supplyResults = Supply::with('prices')
                ->where('name', 'like', '%'.trim($this->supplySearch).'%')
                ->orderBy('name')->take(30)->get();
        } elseif ($this->showSupplyCatalog) {
            $supplyResults = Supply::with('prices')->where('active', true)->orderBy('category')->orderBy('name')->get();
        }

        $animalResults = [];
        if (strlen(trim($this->animalSearch)) >= 2) {
            $animalResults = Animal::with('prices')
                ->where('name', 'like', '%'.trim($this->animalSearch).'%')
                ->orderBy('name')->take(30)->get();
        } elseif ($this->showAnimalCatalog) {
            $animalResults = Animal::with('prices')->where('active', true)->orderBy('name')->get();
        }

        $expenseItemResults = [];
        if (strlen(trim($this->expenseItemSearch)) >= 2) {
            $expenseItemResults = ExpenseItem::where('active', true)
                ->where('name', 'like', '%'.trim($this->expenseItemSearch).'%')
                ->orderBy('name')->take(30)->get();
        } elseif ($this->showExpenseItemCatalog) {
            $expenseItemResults = ExpenseItem::where('active', true)->orderBy('name')->get();
        }

        $clientResults = [];
        if (strlen(trim($this->clientSearch)) >= 2) {
            $needle = trim($this->clientSearch);
            $clientResults = User::whereIn('role', ['consulente', 'iniciado'])
                ->where('status', 'active')
                ->where(fn ($q) => $q->where('name', 'like', "%{$needle}%")->orWhere('email', 'like', "%{$needle}%"))
                ->orderBy('name')->take(15)->get();
        }

        $selectedClient = $this->selectedClientId ? User::find($this->selectedClientId) : null;

        $openRecommendedWorks = $selectedClient
            ? RecommendedWork::where('user_id', $selectedClient->id)->whereNotIn('status', ['completed', 'cancelled'])->with('ritualWork')->get()
            : collect();

        return view('livewire.admin.oracular.new-work', [
            'subtotal' => $subtotal,
            'total' => $total,
            'optionSummaries' => $optionSummaries,
            'supplyResults' => $supplyResults,
            'animalResults' => $animalResults,
            'expenseItemResults' => $expenseItemResults,
            'clientResults' => $clientResults,
            'selectedClient' => $selectedClient,
            'openRecommendedWorks' => $openRecommendedWorks,
            'ritualWorks' => RitualWork::where('active', true)->orderBy('name')->get(),
            'workTemplates' => WorkTemplate::orderBy('name')->get(),
        ]);
    }
}
