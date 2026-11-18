<?php

namespace App\Livewire\Admin;

use App\Models\DiaryEntry;
use App\Models\Odu;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
class Diary extends Component
{
    use WithPagination;

    public string $categoryFilter = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $entryDate = '';

    public string $category = 'pessoal';

    public ?int $subjectUserId = null;

    public ?int $oduId = null;

    public string $title = '';

    public string $content = '';

    public string $oduSearch = '';

    public ?Odu $searchedOdu = null;

    public bool $showOduSearchDrawer = false;

    public function selectSearchedOdu(int $id): void
    {
        $this->searchedOdu = Odu::find($id);
    }

    public function openForm(): void
    {
        $this->reset(['editingId', 'subjectUserId', 'oduId', 'title', 'content']);
        $this->entryDate = now()->toDateString();
        $this->category = 'pessoal';
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $entry = DiaryEntry::findOrFail($id);

        $this->editingId = $entry->id;
        $this->entryDate = $entry->entry_date->toDateString();
        $this->category = $entry->category;
        $this->subjectUserId = $entry->subject_user_id;
        $this->oduId = $entry->odu_id;
        $this->title = $entry->title;
        $this->content = $entry->content;
        $this->showForm = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'entryDate' => ['required', 'date'],
            'category' => ['required', 'in:pessoal,casa,familia,iniciado_externo'],
            'subjectUserId' => ['nullable', 'exists:users,id'],
            'oduId' => ['nullable', 'exists:odus,id'],
            'title' => ['required', 'string', 'max:200'],
            'content' => ['required', 'string'],
        ]);

        $data = [
            'entry_date' => $validated['entryDate'],
            'category' => $validated['category'],
            'subject_user_id' => $validated['subjectUserId'],
            'odu_id' => $validated['oduId'],
            'title' => $validated['title'],
            'content' => clean($validated['content'], 'lesson_content'),
        ];

        if ($this->editingId) {
            DiaryEntry::where('id', $this->editingId)->update($data);
        } else {
            DiaryEntry::create([...$data, 'created_by' => Auth::id()]);
        }

        $this->showForm = false;
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        DiaryEntry::where('id', $id)->delete();
    }

    public function render()
    {
        $entries = DiaryEntry::query()
            ->when($this->categoryFilter, fn ($q) => $q->where('category', $this->categoryFilter))
            ->with('subject', 'odu')
            ->latest('entry_date')
            ->paginate(20);

        $searchResults = [];
        if (strlen($this->oduSearch) >= 2) {
            $searchResults = Odu::where('name', 'like', "%{$this->oduSearch}%")
                ->orWhere('alternative_names', 'like', "%{$this->oduSearch}%")
                ->orWhere('number', 'like', "%{$this->oduSearch}%")
                ->orderBy('number')
                ->get();
        }

        return view('livewire.admin.diary', [
            'entries' => $entries,
            'subjects' => User::whereIn('role', ['iniciado', 'consulente'])->orderBy('name')->get(),
            'odus' => Odu::orderBy('number')->get(),
            'searchResults' => $searchResults,
        ]);
    }
}
