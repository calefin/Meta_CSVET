<?php

namespace App\Livewire\Admin\Courses;

use App\Models\QuizSubmission;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
class QuizReview extends Component
{
    use WithPagination;

    public ?int $reviewingId = null;

    public string $feedback = '';

    public function openReview(int $id): void
    {
        $this->reviewingId = $id;
        $this->feedback = (string) QuizSubmission::find($id)->feedback;
    }

    public function saveFeedback(): void
    {
        $this->validate(['feedback' => ['required', 'string']]);

        QuizSubmission::where('id', $this->reviewingId)->update([
            'feedback' => $this->feedback,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        $this->reviewingId = null;
    }

    public function render()
    {
        return view('livewire.admin.courses.quiz-review', [
            'submissions' => QuizSubmission::with('user', 'lesson.module.course')
                ->orderByRaw('reviewed_at IS NOT NULL, created_at desc')
                ->paginate(25),
        ]);
    }
}
