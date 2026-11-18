<?php

namespace App\Http\Controllers;

use App\Models\LibraryDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class LibraryDocumentAudioStreamController extends Controller
{
    public function __invoke(Request $request, LibraryDocument $document): Response
    {
        $user = $request->user();

        $hasAccess = $user->isAdmin()
            || $user->libraryCategories()->where('library_categories.id', $document->category_id)->exists();

        abort_unless($hasAccess, 403);
        abort_unless($document->audio_path, 404);

        return Storage::disk('local')->response($document->audio_path);
    }
}
