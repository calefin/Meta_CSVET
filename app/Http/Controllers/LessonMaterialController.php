<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class LessonMaterialController extends Controller
{
    public function __invoke(Request $request, Lesson $lesson): Response
    {
        $user = $request->user();
        $course = $lesson->module->course;

        abort_unless($course->userCanAccess($user) && $lesson->file_path, 403);

        return Storage::disk('local')->response($lesson->file_path);
    }
}
