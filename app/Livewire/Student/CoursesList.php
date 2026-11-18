<?php

namespace App\Livewire\Student;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Payment;
use App\Services\MercadoPagoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class CoursesList extends Component
{
    public function buy(int $courseId): void
    {
        $course = Course::findOrFail($courseId);

        abort_if(Auth::user()->isConsulente(), 403);

        try {
            DB::transaction(function () use ($course) {
                $payment = Payment::findOrCreatePending(
                    Course::class,
                    $course->id,
                    Auth::id(),
                    (float) $course->price
                );

                if (MercadoPagoService::configured() && ! $payment->checkout_url) {
                    $url = (new MercadoPagoService())->createPreference($payment, "Curso — {$course->title}");

                    if ($url === null) {
                        throw new \RuntimeException('mercadopago_preference_failed');
                    }
                }
            });
        } catch (\RuntimeException $e) {
            session()->flash('error', 'Não foi possível gerar a cobrança agora. Tente novamente em instantes ou fale com o suporte.');
        }
    }

    public function render()
    {
        $user = Auth::user();
        $enrolledCourseIds = $user->courseEnrollments()->pluck('course_id');

        $courses = Course::where('active', true)
            ->where(function ($q) use ($user, $enrolledCourseIds) {
                $q->where('target', $user->role)
                    ->orWhere('target', 'both')
                    ->orWhereIn('id', $enrolledCourseIds);
            })
            ->with('modules.lessons')
            ->get()
            ->map(function (Course $course) use ($user) {
                $enrollment = $user->courseEnrollments()->where('course_id', $course->id)->first();
                $lessonIds = $course->modules->flatMap(fn ($m) => $m->lessons->pluck('id'));
                $viewedCount = LessonProgress::where('user_id', $user->id)->whereIn('lesson_id', $lessonIds)->count();

                $pendingPayment = Payment::where('payable_type', Course::class)
                    ->where('payable_id', $course->id)
                    ->where('user_id', $user->id)
                    ->where('status', 'pending')
                    ->latest()
                    ->first();

                $course->locked_for_consulente = $user->isConsulente();
                $course->unlocked_by_criteria = ! $enrollment && ! $course->locked_for_consulente
                    && $course->price > 0 && $course->userMeetsInitiationCriteria($user);
                $course->needs_purchase = ! $course->locked_for_consulente && ! $course->unlocked_by_criteria
                    && $course->price > 0 && ! $enrollment;
                $course->has_pending_payment = (bool) $pendingPayment;
                $course->checkout_url = $pendingPayment?->checkout_url;
                $course->progress_percent = $lessonIds->isNotEmpty() ? (int) round(($viewedCount / $lessonIds->count()) * 100) : 0;
                $course->is_completed = (bool) $enrollment?->completed_at;

                return $course;
            });

        // Quick Access to daily resources (Reza, Obi, Orogbo, etc.) for initiated users
        $quickAccessLessons = collect();
        if ($user->role === 'iniciado') {
            $traditionalCourse = $courses->first(fn ($c) => str_contains($c->title, 'Formação Tradicional'));
            if ($traditionalCourse && !$traditionalCourse->locked_for_consulente) {
                $traditionalLessonIds = $traditionalCourse->modules->flatMap(fn ($m) => $m->lessons->pluck('id'));
                $quickAccessLessons = Lesson::whereIn('id', $traditionalLessonIds)
                    ->where(function ($q) {
                        $q->where('title', 'like', '%Iwure%')
                          ->orWhere('title', 'like', '%Obi%')
                          ->orWhere('title', 'like', '%Orogbo%')
                          ->orWhere('title', 'like', '%Dicionário%')
                          ->orWhere('title', 'like', '%Termos%');
                    })
                    ->get()
                    ->map(function ($lesson) use ($traditionalCourse) {
                        $lesson->course_id = $traditionalCourse->id;
                        if (str_contains(strtolower($lesson->title), 'iwure')) {
                            $lesson->display_title = 'Iwure (Reza da Manhã)';
                            $lesson->icon = '🌅';
                            $lesson->description = 'A reza e saudação matinal diária';
                            $lesson->color_bg = 'bg-amber-500/10 hover:bg-amber-500/15 border-amber-500/20';
                            $lesson->color_text = 'text-amber-800';
                        } elseif (str_contains(strtolower($lesson->title), 'obi')) {
                            $lesson->display_title = 'Consultar com Obi';
                            $lesson->icon = '🥥';
                            $lesson->description = 'Guia prático e caídas do Obi';
                            $lesson->color_bg = 'bg-emerald-500/10 hover:bg-emerald-500/15 border-emerald-500/20';
                            $lesson->color_text = 'text-emerald-800';
                        } elseif (str_contains(strtolower($lesson->title), 'orogbo')) {
                            $lesson->display_title = 'Consultar com Orogbo';
                            $lesson->icon = '🌰';
                            $lesson->description = 'Guia prático do Orogbo';
                            $lesson->color_bg = 'bg-orange-500/10 hover:bg-orange-500/15 border-orange-500/20';
                            $lesson->color_text = 'text-orange-800';
                        } else {
                            $lesson->display_title = 'Dicionário de Termos';
                            $lesson->icon = '📖';
                            $lesson->description = 'Glossário rápido de termos Ifá';
                            $lesson->color_bg = 'bg-blue-500/10 hover:bg-blue-500/15 border-blue-500/20';
                            $lesson->color_text = 'text-blue-800';
                        }
                        return $lesson;
                    });
            }
        }

        $telegramSub = \App\Models\TelegramCourseSubscription::where('user_id', $user->id)
            ->orWhere('email', $user->email)
            ->latest()
            ->first();

        $telegramLessons = \App\Models\TelegramCourseLesson::orderByDesc('published_at')->get();
        $telegramLastPayment = $telegramSub
            ? Payment::where('payable_type', \App\Models\TelegramCourseSubscription::class)
                ->where('payable_id', $telegramSub->id)
                ->where('status', 'paid')
                ->latest()
                ->first()
            : null;

        $layout = $user->role === 'iniciado' ? 'components.layouts.iniciado' : 'components.layouts.consulente';

        return view('livewire.student.courses-list', [
            'courses' => $courses,
            'quickAccessLessons' => $quickAccessLessons,
            'telegramSub' => $telegramSub,
            'telegramLessons' => $telegramLessons,
            'telegramLastPayment' => $telegramLastPayment,
        ])
            ->layout($layout, ['title' => 'Cursos']);
    }
}

