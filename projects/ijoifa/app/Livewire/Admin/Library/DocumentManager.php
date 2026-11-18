<?php

namespace App\Livewire\Admin\Library;

use App\Models\LibraryCategory;
use App\Models\LibraryDocument;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;

#[Layout('components.layouts.admin')]
class DocumentManager extends Component
{
    use WithFileUploads;

    public LibraryCategory $category;

    public ?int $editingId = null;

    public string $title = '';

    public string $language = 'pt-BR';

    public string $description = '';

    public string $content = '';

    public $audio = null;

    public ?string $currentAudioPath = null;

    public function mount(LibraryCategory $category): void
    {
        $this->category = $category;
    }

    public function edit(int $documentId): void
    {
        $doc = LibraryDocument::findOrFail($documentId);
        $this->editingId = $doc->id;
        $this->title = $doc->title;
        $this->language = $doc->language;
        $this->description = $doc->description ?? '';
        $this->content = $doc->content ?? '';
        $this->currentAudioPath = $doc->audio_path;
        $this->audio = null;
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'title', 'language', 'description', 'content', 'audio', 'currentAudioPath']);
        $this->language = 'pt-BR';
    }

    public function save(): void
    {
        $rules = [
            'title' => ['required', 'string', 'max:200'],
            'language' => ['required', 'in:pt-BR,en,yo'],
            'description' => ['nullable', 'string'],
            'content' => ['required', 'string'],
            'audio' => ['nullable', 'file', 'mimes:mp3,wav,ogg,oga,mpeg', 'max:20480'], // max 20MB audio
        ];

        $validated = $this->validate($rules);

        $audioPath = $this->currentAudioPath;

        if ($this->audio) {
            // Delete old audio if it exists
            if ($this->currentAudioPath) {
                Storage::disk('local')->delete($this->currentAudioPath);
            }
            $audioPath = $this->audio->store('library-audios', 'local');
        }

        if ($this->editingId) {
            $doc = LibraryDocument::findOrFail($this->editingId);
            $doc->update([
                'title' => $validated['title'],
                'language' => $validated['language'],
                'description' => $validated['description'],
                'content' => $validated['content'],
                'audio_path' => $audioPath,
            ]);
        } else {
            $this->category->documents()->create([
                'category_id' => $this->category->id,
                'title' => $validated['title'],
                'language' => $validated['language'],
                'description' => $validated['description'],
                'content' => $validated['content'],
                'audio_path' => $audioPath,
                'order' => $this->category->documents()->count(),
                'uploaded_by' => auth()->id(),
            ]);
        }

        $this->resetForm();
        $this->category->load('documents'); // reload relation
    }

    public function delete(int $documentId): void
    {
        $document = LibraryDocument::where('id', $documentId)->where('category_id', $this->category->id)->first();

        if (! $document) {
            return;
        }

        if ($document->file_path) {
            Storage::disk('local')->delete($document->file_path);
        }

        if ($document->audio_path) {
            Storage::disk('local')->delete($document->audio_path);
        }

        $document->delete();
        $this->category->load('documents');
    }

    public function deleteAudio(): void
    {
        if (! $this->editingId || ! $this->currentAudioPath) {
            return;
        }

        Storage::disk('local')->delete($this->currentAudioPath);
        
        $doc = LibraryDocument::findOrFail($this->editingId);
        $doc->update(['audio_path' => null]);
        $this->currentAudioPath = null;
        $this->category->load('documents');
    }

    public function render()
    {
        return view('livewire.admin.library.document-manager', [
            'documents' => $this->category->documents()->orderBy('order')->get(),
        ]);
    }
}
