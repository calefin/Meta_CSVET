<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CourseCertificateController extends Controller
{
    public function __invoke(Request $request, Course $course): Response
    {
        $user = $request->user();

        $enrollment = $course->enrollments()->where('user_id', $user->id)->whereNotNull('completed_at')->first();

        abort_unless($enrollment, 403, 'Curso ainda nao concluido.');

        $pdf = Pdf::loadView('pdf.certificate', [
            'user' => $user,
            'course' => $course,
            'completedAt' => $enrollment->completed_at,
        ]);

        return $pdf->stream("certificado-{$course->id}.pdf");
    }
}
