<?php

namespace App\Livewire\Student;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\QuizSubmission;
use App\Services\CourseAccessResolver;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CourseShow extends Component
{
    public Course $course;

    public ?int $activeLessonId = null;

    public string $quizAnswer = '';

    public function mount(Course $course): void
    {
        abort_unless($course->userCanAccess(Auth::user()), 403);

        $this->course = $course->load('modules.lessons');

        CourseAccessResolver::grantIfEligible($this->course, Auth::user());

        $lessonIds = $this->course->modules->flatMap(fn ($m) => $m->lessons->pluck('id'));
        
        $queryLessonId = request('lesson') ?: request('aula');
        if ($queryLessonId && $lessonIds->contains((int)$queryLessonId)) {
            $this->activeLessonId = (int)$queryLessonId;
        } else {
            $viewedIds = LessonProgress::where('user_id', Auth::id())->whereIn('lesson_id', $lessonIds)->pluck('lesson_id');
            $this->activeLessonId = $lessonIds->first(fn ($id) => ! $viewedIds->contains($id)) ?? $lessonIds->first();
        }

        if ($this->activeLessonId) {
            $this->openLesson($this->activeLessonId);
        }
    }

    public function openLesson(int $lessonId): void
    {
        $this->activeLessonId = $lessonId;

        LessonProgress::firstOrCreate(
            ['lesson_id' => $lessonId, 'user_id' => Auth::id()],
            ['viewed_at' => now()]
        );

        $this->checkCompletion();

        $existingAnswer = QuizSubmission::where('lesson_id', $lessonId)->where('user_id', Auth::id())->latest()->first();
        $this->quizAnswer = $existingAnswer?->answer ?? '';
    }

    private function checkCompletion(): void
    {
        $lessonIds = $this->course->modules->flatMap(fn ($m) => $m->lessons->pluck('id'));
        $viewedCount = LessonProgress::where('user_id', Auth::id())->whereIn('lesson_id', $lessonIds)->count();

        if ($lessonIds->isNotEmpty() && $viewedCount >= $lessonIds->count()) {
            $this->course->enrollments()->where('user_id', Auth::id())->whereNull('completed_at')->update(['completed_at' => now()]);
        }
    }

    public function submitQuiz(): void
    {
        $this->validate(['quizAnswer' => ['required', 'string']]);

        QuizSubmission::create([
            'lesson_id' => $this->activeLessonId,
            'user_id' => Auth::id(),
            'answer' => $this->quizAnswer,
        ]);
    }

    public function render()
    {
        $user = Auth::user();
        $lessonIds = $this->course->modules->flatMap(fn ($m) => $m->lessons->pluck('id'));
        $viewedIds = LessonProgress::where('user_id', $user->id)->whereIn('lesson_id', $lessonIds)->pluck('lesson_id');

        $activeLesson = $this->activeLessonId ? Lesson::find($this->activeLessonId) : null;
        $mySubmission = $activeLesson
            ? QuizSubmission::where('lesson_id', $activeLesson->id)->where('user_id', $user->id)->latest()->first()
            : null;

        $layout = $user->role === 'iniciado' ? 'components.layouts.iniciado' : 'components.layouts.consulente';

        return view('livewire.student.course-show', [
            'viewedIds' => $viewedIds,
            'activeLesson' => $activeLesson,
            'mySubmission' => $mySubmission,
        ])->layout($layout, ['title' => $this->course->title]);
    }
}
