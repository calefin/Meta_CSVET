<?php

namespace App\Livewire\Admin\Courses;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\Payment;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.admin')]
class CourseBuilder extends Component
{
    use WithFileUploads;
    public Course $course;

    public string $newModuleTitle = '';

    public ?int $addingLessonToModule = null;

    public ?int $editingLessonId = null;

    public string $lessonTitle = '';

    public string $lessonType = 'text';

    public string $lessonContent = '';

    public string $lessonExternalUrl = '';

    public $lessonFile = null;

    public $lessonAudio = null;

    public ?string $currentLessonAudioPath = null;

    public ?int $grantUserId = null;

    public function mount(Course $course): void
    {
        $this->course = $course;
    }

    public function addModule(): void
    {
        $this->validate(['newModuleTitle' => ['required', 'string', 'max:200']]);

        $this->course->modules()->create([
            'title' => $this->newModuleTitle,
            'order' => $this->course->modules()->count(),
        ]);

        $this->newModuleTitle = '';
    }

    public function deleteModule(int $moduleId): void
    {
        CourseModule::where('id', $moduleId)->where('course_id', $this->course->id)->delete();
    }

    public function showLessonForm(int $moduleId): void
    {
        $this->addingLessonToModule = $moduleId;
        $this->editingLessonId = null;
        $this->reset(['lessonTitle', 'lessonContent', 'lessonExternalUrl', 'lessonFile', 'lessonAudio', 'currentLessonAudioPath']);
        $this->lessonType = 'text';
    }

    public function editLesson(int $lessonId): void
    {
        $lesson = Lesson::where('id', $lessonId)->whereHas('module', fn ($q) => $q->where('course_id', $this->course->id))->firstOrFail();

        $this->editingLessonId = $lesson->id;
        $this->addingLessonToModule = $lesson->module_id;
        $this->lessonTitle = $lesson->title;
        $this->lessonType = $lesson->type;
        $this->lessonContent = (string) $lesson->content;
        $this->lessonExternalUrl = (string) $lesson->external_url;
        $this->currentLessonAudioPath = $lesson->audio_path;
        $this->lessonFile = null;
        $this->lessonAudio = null;
    }

    public function saveLesson(): void
    {
        $validated = $this->validate([
            'lessonTitle' => ['required', 'string', 'max:200'],
            'lessonType' => ['required', 'in:video,pdf,text,external'],
            'lessonContent' => ['nullable', 'string'],
            'lessonExternalUrl' => ['nullable', 'string', 'max:255'],
            'lessonFile' => match ($this->lessonType) {
                'pdf' => ['nullable', 'file', 'max:51200', 'mimes:pdf'],
                'video' => ['nullable', 'file', 'max:51200', 'mimes:mp4,webm,mov,avi,mkv,quicktime'], // looser mime validation for videos
                default => ['nullable', 'file', 'max:51200'],
            },
            'lessonAudio' => ['nullable', 'file', 'mimes:mp3,wav,ogg,oga,mpeg', 'max:20480'],
        ]);

        if ($validated['lessonType'] === 'text') {
            $validated['lessonContent'] = clean($validated['lessonContent'] ?? '', 'lesson_content');
        }

        $module = CourseModule::where('id', $this->addingLessonToModule)->where('course_id', $this->course->id)->firstOrFail();

        $audioPath = $this->currentLessonAudioPath;

        if ($this->lessonAudio) {
            if ($this->currentLessonAudioPath) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($this->currentLessonAudioPath);
            }
            $audioPath = $this->lessonAudio->store('lesson-audios', 'local');
        }

        if ($this->editingLessonId) {
            $lesson = Lesson::where('id', $this->editingLessonId)->where('module_id', $module->id)->firstOrFail();

            $filePath = $this->lessonFile?->store('course-materials', 'local') ?? $lesson->file_path;

            $lesson->update([
                'title' => $validated['lessonTitle'],
                'type' => $validated['lessonType'],
                'content' => $validated['lessonContent'],
                'external_url' => $validated['lessonExternalUrl'],
                'file_path' => $filePath,
                'audio_path' => $audioPath,
            ]);
        } else {
            $filePath = $this->lessonFile?->store('course-materials', 'local');

            $module->lessons()->create([
                'title' => $validated['lessonTitle'],
                'type' => $validated['lessonType'],
                'content' => $validated['lessonContent'],
                'external_url' => $validated['lessonExternalUrl'],
                'file_path' => $filePath,
                'audio_path' => $audioPath,
                'order' => $module->lessons()->count(),
            ]);
        }

        $this->addingLessonToModule = null;
        $this->editingLessonId = null;
        $this->reset(['lessonTitle', 'lessonContent', 'lessonExternalUrl', 'lessonFile', 'lessonAudio', 'currentLessonAudioPath']);
    }

    public function deleteLesson(int $lessonId): void
    {
        $lesson = Lesson::find($lessonId);
        if ($lesson) {
            if ($lesson->file_path) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($lesson->file_path);
            }
            if ($lesson->audio_path) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($lesson->audio_path);
            }
            $lesson->delete();
        }
    }

    public function deleteLessonAudio(): void
    {
        if (! $this->editingLessonId || ! $this->currentLessonAudioPath) {
            return;
        }

        \Illuminate\Support\Facades\Storage::disk('local')->delete($this->currentLessonAudioPath);
        
        $lesson = Lesson::findOrFail($this->editingLessonId);
        $lesson->update(['audio_path' => null]);
        $this->currentLessonAudioPath = null;
    }

    public function grantAccess(): void
    {
        $this->validate(['grantUserId' => ['required', 'exists:users,id']]);

        $this->course->enrollments()->firstOrCreate(
            ['user_id' => $this->grantUserId],
            ['granted_by' => auth()->id(), 'source' => 'manual', 'enrolled_at' => now()]
        );

        $this->grantUserId = null;
    }

    public function revokeAccess(int $userId): void
    {
        $this->course->enrollments()->where('user_id', $userId)->delete();
    }

    /**
     * Manual payment confirmation placeholder for paid courses, same
     * pattern used elsewhere until Mercado Pago credentials are available.
     */
    public function confirmPayment(int $paymentId): void
    {
        $payment = Payment::where('id', $paymentId)
            ->where('payable_type', Course::class)
            ->where('payable_id', $this->course->id)
            ->where('status', 'pending')
            ->first();

        if (! $payment) {
            return;
        }

        $payment->update(['status' => 'paid', 'paid_at' => now()]);
        \App\Services\PaymentFulfillment::apply($payment);
    }

    public function render()
    {
        return view('livewire.admin.courses.course-builder', [
            'modules' => $this->course->modules()->with('lessons')->get(),
            'enrollments' => $this->course->enrollments()->with('user')->get(),
            'pendingPayments' => Payment::where('payable_type', Course::class)
                ->where('payable_id', $this->course->id)
                ->where('status', 'pending')
                ->with('user')
                ->get(),
            'grantableUsers' => User::where('role', 'iniciado')
                ->whereNotIn('id', $this->course->enrollments()->pluck('user_id'))
                ->orderBy('name')->get(),
        ]);
    }
}
