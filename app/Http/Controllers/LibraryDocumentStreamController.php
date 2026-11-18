<?php

namespace App\Http\Controllers;

use App\Models\LibraryDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class LibraryDocumentStreamController extends Controller
{
    /**
     * Serves raw PDF bytes for the client-side PDF.js viewer to fetch via
     * XHR (resources/js/library-viewer.js). Never linked to directly and
     * never sent with a download disposition - the document is only ever
     * meant to be rendered onto canvas inside the authenticated viewer,
     * not opened in the browser's native PDF chrome.
     */
    public function __invoke(Request $request, LibraryDocument $document): Response
    {
        $user = $request->user();

        $hasAccess = $user->isAdmin()
            || $user->libraryCategories()->where('library_categories.id', $document->category_id)->exists();

        abort_unless($hasAccess, 403);

        return Storage::disk('local')->response($document->file_path, null, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}
