<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EmailAttachmentController extends Controller
{
    /**
     * Download do anexo de um e-mail em massa (ex: PDF de edital).
     * Rota protegida por assinatura (signed URL) em vez de login, já que o
     * aluno acessa direto pelo link do e-mail sem necessariamente estar autenticado.
     */
    public function download(Request $request)
    {
        $path = $request->query('path');

        if (!$path || !str_starts_with($path, 'email-attachments/') || !Storage::disk('local')->exists($path)) {
            abort(404, 'Arquivo não encontrado ou link expirado.');
        }

        return Storage::disk('local')->download($path, basename($path));
    }
}
