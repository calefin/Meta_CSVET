<?php

namespace App\Livewire\Admin\Oracular;

use App\Models\Animal;
use App\Models\Budget;
use App\Models\RecommendedWork;
use App\Models\RitualWork;
use App\Models\Supply;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.admin')]
class WorkSimulator extends Component
{
    public ?int $selectedWorkId = null;

    public ?int $selectedClientId = null;

    public string $markupPercent = '0';

    public string $feePercent = '0';

    public string $notes = '';

    public string $supplySearch = '';

    public string $animalSearch = '';

    /** @var array<int, array{item_type: string, item_id: int|string, description: string, quantity: string, unit_price: string, by_client: bool}> */
    public array $items = [];

    public function updatedSelectedWorkId($value): void
    {
        $this->items = [];

        if (!$value) {
            return;
        }

        $work = RitualWork::with(['supplies', 'animals'])->findOrFail($value);

        foreach ($work->supplies as $supply) {
            $latestPrice = $supply->prices()->latest('purchase_date')->first();

            $this->items[] = [
                'item_type' => 'supply',
                'item_id' => $supply->id,
                'description' => $supply->name,
                'quantity' => (string) $supply->pivot->default_quantity,
                'unit_price' => $latestPrice ? (string) $latestPrice->unit_price : '0',
                'by_client' => false,
            ];
        }

        foreach ($work->animals as $animal) {
            $latestPrice = $animal->prices()->latest('purchase_date')->first();

            $this->items[] = [
                'item_type' => 'animal',
                'item_id' => $animal->id,
                'description' => $animal->name,
                'quantity' => (string) $animal->pivot->default_quantity,
                'unit_price' => $latestPrice ? (string) $latestPrice->unit_price : '0',
                'by_client' => false,
            ];
        }
    }

    public function addSupplyRow(int $supplyId): void
    {
        $supply = Supply::findOrFail($supplyId);
        $latestPrice = $supply->prices()->latest('purchase_date')->first();

        $this->items[] = [
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

        $this->items[] = [
            'item_type' => 'animal',
            'item_id' => $animal->id,
            'description' => $animal->name,
            'quantity' => '1',
            'unit_price' => $latestPrice ? (string) $latestPrice->unit_price : '0',
            'by_client' => false,
        ];

        $this->animalSearch = '';
    }

    public function removeRow(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function totals(): array
    {
        $subtotal = 0;

        foreach ($this->items as $item) {
            if (!$item['by_client']) {
                $subtotal += (float) $item['quantity'] * (float) $item['unit_price'];
            }
        }

        $totalAfterMarkup = $subtotal * (1 + ((float) $this->markupPercent / 100));
        
        $feePercent = (float) $this->feePercent;
        if ($feePercent > 0 && $feePercent < 100) {
            $total = $totalAfterMarkup / (1 - ($feePercent / 100));
        } else {
            $total = $totalAfterMarkup;
        }

        return [round($subtotal, 2), round($total, 2)];
    }

    public function saveBudget(): void
    {
        $this->validate([
            'selectedClientId' => ['required', 'exists:users,id'],
            'selectedWorkId' => ['required', 'exists:ritual_works,id'],
            'markupPercent' => ['required', 'numeric', 'min:0'],
            'feePercent' => ['required', 'numeric', 'min:0', 'max:99'],
            'notes' => ['nullable', 'string'],
            'items.*.item_type' => ['required', 'in:supply,animal'],
            'items.*.item_id' => ['required', 'integer'],
            'items.*.description' => ['required', 'string', 'max:200'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.by_client' => ['boolean'],
        ]);

        [, $total] = $this->totals();

        if ($total <= 0) {
            $this->addError('saveBudget', 'O orçamento não pode ser salvo com valor final zero. Adicione itens ou ajuste os valores.');
            return;
        }

        $client = User::findOrFail($this->selectedClientId);

        // Find or create a consultation
        $consultation = $client->consultations()->latest('consulted_at')->first();

        if (!$consultation) {
            $consultation = $client->consultations()->create([
                'user_id' => $client->id,
                'admin_id' => Auth::id(),
                'consulted_at' => now(),
                'modality' => 'online',
                'odu_main_id' => \App\Models\Odu::orderBy('number')->first()->id,
                'ire_ibi' => 'ire',
                'ire_ibi_type_id' => \App\Models\IreIbiType::where('classification', 'ire')->first()->id,
                'general_guidance' => 'Consulta iniciada automaticamente para registro de orçamento avulso via simulador.',
                'created_by' => Auth::id(),
            ]);
        }

        // Create recommended work
        $recommendedWork = $consultation->recommendedWorks()->create([
            'user_id' => $client->id,
            'ritual_work_id' => $this->selectedWorkId,
            'notes' => $this->notes ?: 'Recomendado via simulador.',
            'status' => 'budget_requested',
        ]);

        [$subtotal, $total] = $this->totals();

        // Create budget
        $budget = $recommendedWork->budgets()->create([
            'user_id' => $client->id,
            'admin_id' => Auth::id(),
            'markup_percent' => $this->markupPercent,
            'fee_percent' => $this->feePercent,
            'subtotal' => $subtotal,
            'total' => $total,
            'status' => 'sent',
            'notes' => $this->notes,
        ]);

        // Create items
        foreach ($this->items as $item) {
            $budget->items()->create([
                'item_type' => $item['item_type'],
                'item_id' => $item['item_id'],
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'subtotal' => round($item['quantity'] * $item['unit_price'], 2),
                'by_client' => $item['by_client'],
            ]);
        }

        session()->flash('success', 'Orçamento gerado e salvo com sucesso para ' . $client->name);
        $this->redirect(route('admin.oracular.queue'));
    }

    public function render()
    {
        [$subtotal, $total] = $this->totals();

        $supplyResults = [];
        if (strlen(trim($this->supplySearch)) >= 2) {
            $supplyResults = Supply::with('prices')
                ->where('name', 'like', '%'.trim($this->supplySearch).'%')
                ->orderBy('name')->take(30)->get();
        }

        $animalResults = [];
        if (strlen(trim($this->animalSearch)) >= 2) {
            $animalResults = Animal::with('prices')
                ->where('name', 'like', '%'.trim($this->animalSearch).'%')
                ->orderBy('name')->take(30)->get();
        }

        return view('livewire.admin.oracular.work-simulator', [
            'subtotal' => $subtotal,
            'total' => $total,
            'works' => RitualWork::where('active', true)->orderBy('name')->get(),
            'clients' => User::whereIn('role', ['consulente', 'iniciado'])->where('status', 'active')->orderBy('name')->get(),
            'supplyResults' => $supplyResults,
            'animalResults' => $animalResults,
        ]);
    }
}
