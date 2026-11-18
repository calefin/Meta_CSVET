<?php

namespace App\Http\Controllers;

use App\Mail\AppNotificationMail;
use App\Models\ExternalClassGroup;
use App\Models\ExternalDocumentSubmission;
use App\Models\Owner;
use App\Services\PdfSecurityValidator;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExternalDocumentController extends Controller
{
    public function index(Request $request, string $token)
    {
        $group = ExternalClassGroup::where('token', $token)->where('active', true)->firstOrFail();
        $participant = ExternalClassAccessController::resolveParticipant($request, $group);

        if (!$participant) {
            return redirect()->route('estagio.turma', ['token' => $token]);
        }

        $submissions = $participant->submissions()
            ->where('external_class_group_id', $group->id)
            ->latest()
            ->get();

        return view('estagio.documentos', [
            'group' => $group,
            'participant' => $participant,
            'submissions' => $submissions,
            'documentTypes' => ExternalDocumentSubmission::DOCUMENT_TYPE_OPTIONS,
        ]);
    }

    public function store(Request $request, string $token)
    {
        $group = ExternalClassGroup::where('token', $token)->where('active', true)->firstOrFail();
        $participant = ExternalClassAccessController::resolveParticipant($request, $group);

        if (!$participant) {
            return redirect()->route('estagio.turma', ['token' => $token]);
        }

        // Honeypot
        if ($request->filled('secondary_address')) {
            return redirect()->route('estagio.documentos', ['token' => $token]);
        }

        $validated = $request->validate([
            'document_type' => 'required|string|in:' . implode(',', array_keys(ExternalDocumentSubmission::DOCUMENT_TYPE_OPTIONS)),
            'reference_month_start' => 'required|integer|min:1|max:12',
            'reference_month_end' => 'nullable|integer|min:1|max:12',
            'reference_year' => 'required|integer|min:2020|max:2100',
            'document' => 'required|file|mimes:pdf|max:20480',
        ], [
            'document.required' => 'Selecione o arquivo PDF do documento.',
            'document.mimes' => 'O arquivo precisa ser um PDF.',
            'document.max' => 'O arquivo não pode passar de 20MB.',
        ]);

        $documentType = ExternalDocumentSubmission::DOCUMENT_TYPE_OPTIONS[$validated['document_type']];

        $monthStart = (int) $validated['reference_month_start'];
        $monthEnd = (int) ($validated['reference_month_end'] ?: $monthStart);

        if ($monthEnd < $monthStart || ($monthEnd - $monthStart) > 5) {
            return redirect()->route('estagio.documentos', ['token' => $token])
                ->withInput()
                ->withErrors(['reference_month_end' => 'O período de referência não pode ultrapassar um semestre (6 meses) dentro do mesmo ano.']);
        }

        $file = $request->file('document');
        $content = file_get_contents($file->getRealPath());

        [$isValid, $error] = PdfSecurityValidator::isValidPdf($content);
        if (!$isValid) {
            return redirect()->route('estagio.documentos', ['token' => $token])
                ->withInput()
                ->withErrors(['document' => $error]);
        }

        $disk = Storage::disk('local');
        $storedName = 'external_submissions/' . $group->id . '/' . $participant->id . '/' . Str::uuid() . '.pdf';
        $disk->put($storedName, $content);

        $submission = ExternalDocumentSubmission::create([
            'external_class_group_id' => $group->id,
            'external_class_participant_id' => $participant->id,
            'document_type' => $documentType,
            'reference_month_start' => $monthStart,
            'reference_month_end' => $monthEnd,
            'reference_year' => $validated['reference_year'],
            'file_path' => $storedName,
            'original_filename' => $file->getClientOriginalName(),
            'status' => ExternalDocumentSubmission::STATUS_PENDING,
            'metadata' => [
                'forensic_ip' => $request->ip() ?? '127.0.0.1',
                'forensic_user_agent' => $request->userAgent() ?? 'N/A',
                'forensic_timestamp' => now()->timezone('America/Sao_Paulo')->format('d/m/Y - H:i:s'),
                'sha256' => hash('sha256', $content),
            ],
        ]);

        $this->notifyProfessor($group, $participant, $submission);

        return redirect()->route('estagio.documentos', ['token' => $token])
            ->with('success', 'Documento enviado com sucesso! Você pode acompanhar o status abaixo.');
    }

    /**
     * Download do assinado a partir da tela do portal (aluno já reautenticado
     * por RGM+e-mail na sessão da turma) — sem prazo de expiração, porque a
     * sessão em si já reexige as credenciais a cada acesso.
     */
    public function downloadSignedViaPortal(Request $request, string $token, ExternalDocumentSubmission $submission)
    {
        $group = ExternalClassGroup::where('token', $token)->where('active', true)->firstOrFail();
        $participant = ExternalClassAccessController::resolveParticipant($request, $group);

        abort_unless($participant, 403);
        abort_unless($submission->external_class_participant_id === $participant->id, 403);
        abort_unless($submission->external_class_group_id === $group->id, 403);

        return $this->downloadSignedFile($submission);
    }

    /**
     * Download do assinado a partir do link enviado por e-mail — URL assinada
     * do Laravel (middleware `signed`), sem exigir sessão/login (LGPD: o aluno
     * externo nunca cadastra conta). A validade embutida na assinatura (padrão
     * 15 dias, ver `SubmissionsRelationManager::anexarAssinado`) já barra o
     * acesso automaticamente depois de vencida — não precisa checar aqui.
     */
    public function downloadSignedViaEmailLink(Request $request, ExternalDocumentSubmission $submission)
    {
        return $this->downloadSignedFile($submission);
    }

    protected function downloadSignedFile(ExternalDocumentSubmission $submission)
    {
        abort_unless(
            $submission->status === ExternalDocumentSubmission::STATUS_SIGNED && $submission->returned_file_path,
            404
        );

        return Storage::disk('local')->download(
            $submission->returned_file_path,
            Str::slug($submission->document_type) . '_assinado.pdf'
        );
    }

    protected function notifyProfessor(ExternalClassGroup $group, $participant, ExternalDocumentSubmission $submission): void
    {
        try {
            $body = "Olá,\n\nUm novo documento foi enviado pelo portal de assinatura externa.\n\n" .
                "Turma: {$group->group_name} ({$group->university_name})\n" .
                "Aluno: {$participant->name} (RGM {$participant->rgm})\n" .
                "Documento: {$submission->document_type}\n" .
                "Referência: {$submission->referencePeriodLabel()}";

            $admin = Owner::find($group->owner_user_id);

            if ($admin?->email) {
                Mail::to($admin->email)->queue(new AppNotificationMail(
                    title: 'Novo documento — Assinatura Externa',
                    messageContent: $body,
                    actionUrl: url('/admin/external-class-groups'),
                    actionText: 'Acessar Painel',
                    subject: 'Novo documento recebido — ' . $group->group_name,
                ));
            }

            if ($admin && !empty($admin->telegram_chat_id)) {
                $telegramService = app(TelegramService::class);
                $telegramService->sendMessage(
                    $admin->telegram_chat_id,
                    "<b>📄 Novo documento — Assinatura Externa</b>\n\n" .
                    "Turma: {$group->group_name}\n" .
                    "Aluno: {$participant->name} (RGM {$participant->rgm})\n" .
                    "Documento: {$submission->document_type}\n" .
                    "Referência: {$submission->referencePeriodLabel()}"
                );
            }
        } catch (\Exception $e) {
            Log::error('Erro ao notificar professor sobre novo documento externo: ' . $e->getMessage());
        }
    }
}
