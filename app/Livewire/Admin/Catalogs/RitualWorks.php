<?php

namespace App\Livewire\Admin\Catalogs;

use App\Models\Animal;
use App\Models\RitualWork;
use App\Models\Supply;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
class RitualWorks extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public string $estimated_days = '';

    public bool $active = true;

    /** @var array<int, array{supply_id: int|string, default_quantity: string}> */
    public array $supplyRows = [];

    /** @var array<int, array{animal_id: int|string, default_quantity: string}> */
    public array $animalRows = [];

    public string $supplySearch = '';

    public string $animalSearch = '';

    public string $duplicateItemWarning = '';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'estimated_days' => ['nullable', 'integer', 'min:0'],
            'active' => ['boolean'],
            'supplyRows.*.supply_id' => ['required', 'exists:supplies,id'],
            'supplyRows.*.default_quantity' => ['required', 'numeric', 'min:0.001'],
            'animalRows.*.animal_id' => ['required', 'exists:animals,id'],
            'animalRows.*.default_quantity' => ['required', 'numeric', 'min:0.001'],
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->reset(['editingId', 'name', 'description', 'estimated_days', 'supplyRows', 'animalRows', 'supplySearch', 'animalSearch', 'duplicateItemWarning']);
        $this->active = true;
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $work = RitualWork::with(['supplies', 'animals'])->findOrFail($id);
        $this->editingId = $work->id;
        $this->name = $work->name;
        $this->description = (string) $work->description;
        $this->estimated_days = (string) $work->estimated_days;
        $this->active = $work->active;
        $this->supplyRows = $work->supplies->map(fn ($supply) => [
            'supply_id' => $supply->id,
            'default_quantity' => (string) $supply->pivot->default_quantity,
        ])->all();
        $this->animalRows = $work->animals->map(fn ($animal) => [
            'animal_id' => $animal->id,
            'default_quantity' => (string) $animal->pivot->default_quantity,
        ])->all();
        $this->supplySearch = '';
        $this->animalSearch = '';
        $this->duplicateItemWarning = '';
        $this->showModal = true;
    }

    public function duplicate(int $id): void
    {
        $work = RitualWork::with(['supplies', 'animals'])->findOrFail($id);

        $baseName = preg_replace('/\s*\(cópia\)$/u', '', $work->name);

        $this->editingId = null;
        $this->name = $baseName.' (cópia)';
        $this->description = (string) $work->description;
        $this->estimated_days = (string) $work->estimated_days;
        $this->active = true;
        $this->supplyRows = $work->supplies->map(fn ($supply) => [
            'supply_id' => $supply->id,
            'default_quantity' => (string) $supply->pivot->default_quantity,
        ])->all();
        $this->animalRows = $work->animals->map(fn ($animal) => [
            'animal_id' => $animal->id,
            'default_quantity' => (string) $animal->pivot->default_quantity,
        ])->all();
        $this->supplySearch = '';
        $this->animalSearch = '';
        $this->duplicateItemWarning = '';
        $this->showModal = true;
    }

    public function selectSupplyById(int $id): void
    {
        $this->supplySearch = '';
        $this->duplicateItemWarning = '';

        if (collect($this->supplyRows)->contains('supply_id', $id)) {
            $this->duplicateItemWarning = 'Este insumo já foi adicionado à lista.';
            return;
        }

        $this->supplyRows[] = ['supply_id' => $id, 'default_quantity' => '1'];
    }

    public function selectAnimalById(int $id): void
    {
        $this->animalSearch = '';
        $this->duplicateItemWarning = '';

        if (collect($this->animalRows)->contains('animal_id', $id)) {
            $this->duplicateItemWarning = 'Este animal já foi adicionado à lista.';
            return;
        }

        $this->animalRows[] = ['animal_id' => $id, 'default_quantity' => '1'];
    }

    public function removeSupplyRow(int $index): void
    {
        unset($this->supplyRows[$index]);
        $this->supplyRows = array_values($this->supplyRows);
    }

    public function removeAnimalRow(int $index): void
    {
        unset($this->animalRows[$index]);
        $this->animalRows = array_values($this->animalRows);
    }

    public function save(): void
    {
        $validated = $this->validate();

        $work = RitualWork::updateOrCreate(['id' => $this->editingId], [
            'name' => $validated['name'],
            'description' => $validated['description'],
            'estimated_days' => $validated['estimated_days'] ?: null,
            'active' => $validated['active'],
        ]);

        $work->supplies()->sync(
            collect($validated['supplyRows'] ?? [])
                ->mapWithKeys(fn ($row) => [$row['supply_id'] => ['default_quantity' => $row['default_quantity']]])
                ->all()
        );

        $work->animals()->sync(
            collect($validated['animalRows'] ?? [])
                ->mapWithKeys(fn ($row) => [$row['animal_id'] => ['default_quantity' => $row['default_quantity']]])
                ->all()
        );

        $this->showModal = false;
    }

    public function delete(int $id): void
    {
        RitualWork::destroy($id);
    }

    public function render()
    {
        $query = RitualWork::withCount(['supplies', 'animals']);

        if (trim($this->search) !== '') {
            $query->where('name', 'like', '%'.trim($this->search).'%');
        }

        $supplyResults = [];
        if (strlen(trim($this->supplySearch)) >= 2) {
            $supplyResults = Supply::where('name', 'like', '%'.trim($this->supplySearch).'%')
                ->orderBy('name')->take(30)->get();
        }

        $animalResults = [];
        if (strlen(trim($this->animalSearch)) >= 2) {
            $animalResults = Animal::where('name', 'like', '%'.trim($this->animalSearch).'%')
                ->orderBy('name')->take(30)->get();
        }

        $supplyIds = collect($this->supplyRows)->pluck('supply_id')->filter()->all();
        $animalIds = collect($this->animalRows)->pluck('animal_id')->filter()->all();

        return view('livewire.admin.catalogs.ritual-works', [
            'works' => $query->orderBy('name')->paginate(25),
            'supplyResults' => $supplyResults,
            'animalResults' => $animalResults,
            'supplyNames' => Supply::whereIn('id', $supplyIds)->pluck('name', 'id'),
            'animalNames' => Animal::whereIn('id', $animalIds)->pluck('name', 'id'),
            'supplyUnits' => Supply::whereIn('id', $supplyIds)->pluck('unit', 'id'),
            'animalUnits' => Animal::whereIn('id', $animalIds)->pluck('unit', 'id'),
        ]);
    }
}
