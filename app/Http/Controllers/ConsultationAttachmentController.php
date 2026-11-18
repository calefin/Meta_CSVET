<?php

namespace App\Http\Controllers;

use App\Models\ConsultationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ConsultationAttachmentController extends Controller
{
    public function __invoke(Request $request, ConsultationRequest $consultationRequest): Response
    {
        $user = $request->user();

        abort_unless($user->isAdmin() || $consultationRequest->user_id === $user->id, 403);
        abort_unless($consultationRequest->attachment_path, 404);

        return Storage::disk('local')->response($consultationRequest->attachment_path);
    }
}
