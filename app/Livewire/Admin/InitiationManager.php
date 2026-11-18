<?php

namespace App\Livewire\Admin;

use App\Models\Initiation;
use App\Models\Odu;
use App\Models\OrisaRecord;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.admin')]
class InitiationManager extends Component
{
    public User $user;

    // Initiation core fields
    public string $odu_id = '';

    public string $odu_name = '';

    public string $spiritual_name = '';

    public string $initiation_date = '';

    public string $notes = '';

    // Day record form
    public string $dayLabel = '';

    public string $dayOduId = '';

    public string $dayOduName = '';

    public string $dayNotes = '';

    // Ewo form
    public string $newEwo = '';

    // Other Orisa record form
    public bool $showOrisaForm = false;

    public string $orisaName = '';

    public string $orisaSpiritualName = '';

    public string $orisaOduId = '';

    public string $orisaOduName = '';

    public string $orisaDate = '';

    public string $orisaNotes = '';

    public function mount(User $user): void
    {
        $this->user = $user;

        $initiation = $user->initiation;

        if ($initiation) {
            $this->odu_id = (string) $initiation->odu_id;
            $this->odu_name = $initiation->odu?->name ?? '';
            $this->spiritual_name = (string) $initiation->spiritual_name;
            $this->initiation_date = $initiation->initiation_date->format('Y-m-d');
            $this->notes = (string) $initiation->notes;
        }
    }

    private function getOrCreateOduByName(string $name): ?Odu
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $odu = Odu::where('name', 'like', $name)->first();
        if ($odu) {
            return $odu;
        }

        $nextNumber = Odu::max('number') ?? 0;
        $nextNumber++;

        $category = (str_contains(strtolower($name), 'meji') || str_contains(strtolower($name), 'méjì')) ? 'meji' : 'ameji';

        return Odu::create([
            'name' => $name,
            'number' => $nextNumber,
            'category' => $category,
        ]);
    }

    public function updatedOduName(string $value): void
    {
        $value = trim($value);
        if ($value === '') {
            $this->odu_id = '';
            return;
        }

        $odu = Odu::where('name', 'like', $value)->first();
        if ($odu) {
            $this->odu_id = (string) $odu->id;
        } else {
            $this->odu_id = '';
        }
    }

    public function updatedDayOduName(string $value): void
    {
        $value = trim($value);
        if ($value === '') {
            $this->dayOduId = '';
            return;
        }

        $odu = Odu::where('name', 'like', $value)->first();
        if ($odu) {
            $this->dayOduId = (string) $odu->id;
        } else {
            $this->dayOduId = '';
        }
    }

    public function updatedOrisaOduName(string $value): void
    {
        $value = trim($value);
        if ($value === '') {
            $this->orisaOduId = '';
            return;
        }

        $odu = Odu::where('name', 'like', $value)->first();
        if ($odu) {
            $this->orisaOduId = (string) $odu->id;
        } else {
            $this->orisaOduId = '';
        }
    }

    public function saveInitiation(): void
    {
        if ($this->odu_id !== '' && $this->odu_name === '') {
            $odu = Odu::find($this->odu_id);
            if ($odu) {
                $this->odu_name = $odu->name;
            }
        }

        if ($this->odu_name !== '') {
            $odu = $this->getOrCreateOduByName($this->odu_name);
            if ($odu) {
                $this->odu_id = (string) $odu->id;
            }
        } else {
            $this->odu_id = '';
        }

        $validated = $this->validate([
            'odu_id' => ['required', 'exists:odus,id'],
            'spiritual_name' => ['nullable', 'string', 'max:255'],
            'initiation_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ], [
            'odu_id.required' => 'O Odù da iniciação é obrigatório.',
            'odu_id.exists' => 'O Odù selecionado é inválido.',
        ]);

        Initiation::updateOrCreate(
            ['user_id' => $this->user->id],
            [
                'odu_id' => $validated['odu_id'],
                'spiritual_name' => $validated['spiritual_name'],
                'initiation_date' => $validated['initiation_date'],
                'notes' => $validated['notes'],
                'created_by' => Auth::id(),
            ]
        );

        $this->user->refresh();
    }

    public function addDayRecord(): void
    {
        if ($this->dayOduId !== '' && $this->dayOduName === '') {
            $odu = Odu::find($this->dayOduId);
            if ($odu) {
                $this->dayOduName = $odu->name;
            }
        }

        if ($this->dayOduName !== '') {
            $odu = $this->getOrCreateOduByName($this->dayOduName);
            if ($odu) {
                $this->dayOduId = (string) $odu->id;
            }
        } else {
            $this->dayOduId = '';
        }

        $this->validate([
            'dayLabel' => ['required', 'string', 'max:100'],
            'dayOduId' => ['required', 'exists:odus,id'],
            'dayNotes' => ['nullable', 'string'],
        ], [
            'dayOduId.required' => 'O Odù é obrigatório.',
            'dayOduId.exists' => 'O Odù selecionado é inválido.',
        ]);

        $initiation = $this->user->initiation;
        abort_unless($initiation, 404);

        $initiation->dayRecords()->create([
            'day_label' => $this->dayLabel,
            'odu_id' => $this->dayOduId,
            'notes' => $this->dayNotes,
            'order' => $initiation->dayRecords()->count(),
        ]);

        $this->reset(['dayLabel', 'dayOduId', 'dayOduName', 'dayNotes']);
    }

    public function deleteDayRecord(int $id): void
    {
        \App\Models\InitiationDayRecord::where('id', $id)
            ->where('initiation_id', $this->user->initiation?->id)
            ->delete();
    }

    public function addEwo(): void
    {
        $this->validate(['newEwo' => ['required', 'string', 'max:255']]);

        $initiation = $this->user->initiation;
        abort_unless($initiation, 404);

        $initiation->ewos()->create(['description' => $this->newEwo]);

        $this->newEwo = '';
    }

    public function deleteEwo(int $id): void
    {
        \App\Models\InitiationEwo::where('id', $id)
            ->where('initiation_id', $this->user->initiation?->id)
            ->delete();
    }

    public function openOrisaForm(): void
    {
        $this->reset(['orisaName', 'orisaSpiritualName', 'orisaOduId', 'orisaOduName', 'orisaNotes']);
        $this->orisaDate = now()->format('Y-m-d');
        $this->showOrisaForm = true;
    }

    public function saveOrisaRecord(): void
    {
        if ($this->orisaOduId !== '' && $this->orisaOduName === '') {
            $odu = Odu::find($this->orisaOduId);
            if ($odu) {
                $this->orisaOduName = $odu->name;
            }
        }

        if ($this->orisaOduName !== '') {
            $odu = $this->getOrCreateOduByName($this->orisaOduName);
            if ($odu) {
                $this->orisaOduId = (string) $odu->id;
            }
        } else {
            $this->orisaOduId = '';
        }

        $validated = $this->validate([
            'orisaName' => ['required', 'string', 'max:100'],
            'orisaSpiritualName' => ['nullable', 'string', 'max:255'],
            'orisaOduId' => ['nullable', 'exists:odus,id'],
            'orisaDate' => ['required', 'date'],
            'orisaNotes' => ['nullable', 'string'],
        ], [
            'orisaOduId.exists' => 'O Odù selecionado é inválido.',
        ]);

        OrisaRecord::create([
            'user_id' => $this->user->id,
            'orisa_name' => $validated['orisaName'],
            'spiritual_name' => $validated['orisaSpiritualName'],
            'odu_id' => $validated['orisaOduId'] ?: null,
            'performed_at' => $validated['orisaDate'],
            'notes' => $validated['orisaNotes'],
            'created_by' => Auth::id(),
        ]);

        $this->showOrisaForm = false;
        $this->reset(['orisaOduName']);
    }

    public function deleteOrisaRecord(int $id): void
    {
        OrisaRecord::where('id', $id)->where('user_id', $this->user->id)->delete();
    }

    public function render()
    {
        return view('livewire.admin.initiation-manager', [
            'odus' => Odu::orderBy('number')->get(),
            'initiation' => $this->user->initiation()->with('dayRecords.odu', 'ewos')->first(),
            'orisaRecords' => OrisaRecord::where('user_id', $this->user->id)->with('odu')->orderByDesc('performed_at')->get(),
        ]);
    }
}
