<?php

namespace App\Http\Controllers;

use App\Models\TelegramCourseLesson;
use App\Models\TelegramCourseSubscription;
use App\Services\PdfWatermarkService;
use App\Support\Audit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class TelegramCourseLessonMaterialPdfController extends Controller
{
    public function __invoke(Request $request, TelegramCourseLesson $lesson): Response
    {
        abort_if(blank($lesson->content) && blank($lesson->document_path), 404);

        $user = $request->user();

        $subscription = TelegramCourseSubscription::where('user_id', $user->id)
            ->orWhere('email', $user->email)
            ->latest()
            ->first();

        abort_unless($user->isAdmin() || ($subscription && $subscription->isActive()), 403);

        Audit::record('telegram_course.lesson_material_download', $lesson, userId: $user->id);

        $filename = Str::slug($lesson->title).'-material.pdf';

        if ($lesson->document_path) {
            try {
                $binary = PdfWatermarkService::stamp(
                    Storage::disk('local')->path($lesson->document_path),
                    [
                        $user->name ?? $user->email,
                        $user->email,
                        'Reprodução proibida',
                    ]
                );
            } catch (\Throwable $e) {
                Log::error('telegram_course.lesson_material_watermark_failed', [
                    'lesson_id' => $lesson->id,
                    'error' => $e->getMessage(),
                ]);

                abort(500, 'Não foi possível gerar o PDF protegido no momento. Contate o suporte.');
            }

            return response($binary, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ]);
        }

        $pdf = Pdf::loadView('pdf.telegram-course-lesson-material', [
            'lesson' => $lesson,
            'user' => $user,
            'generatedAt' => now(),
        ]);

        return $pdf->stream($filename);
    }
}
