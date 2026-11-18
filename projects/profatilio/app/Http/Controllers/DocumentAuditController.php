<?php

namespace App\Http\Controllers;

use App\Models\DocumentSubmission;
use Illuminate\Http\Request;

class DocumentAuditController extends Controller
{
    /**
     * Exibe o recibo forense para impressão (requer autenticação).
     */
    public function receipt($id)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403, 'Acesso não autorizado.');
        }

        $submission = DocumentSubmission::with(['student.user'])->findOrFail($id);

        // Segurança: Apenas o orientador ou o próprio aluno dono do documento podem ver
        if ($user->role !== 'admin' && ($submission->student && $submission->student->user_id !== $user->id)) {
            abort(403, 'Você não tem permissão para visualizar este recibo.');
        }

        // Auto-correção (Self-healing) se forensic_hash estiver ausente
        if (!isset($submission->metadata['forensic_hash'])) {
            if (isset($submission->metadata['govbr_signature']['original_pdf_hash'])) {
                $metadata = $submission->metadata;
                $metadata['forensic_hash'] = $submission->metadata['govbr_signature']['original_pdf_hash'];
                $metadata['forensic_timestamp'] = $submission->metadata['govbr_signature']['signed_at'] ?? now()->timezone('America/Sao_Paulo')->format('d/m/Y - H:i:s');
                $metadata['forensic_ip'] = $submission->metadata['govbr_signature']['ip'] ?? '127.0.0.1';
                $metadata['forensic_user_agent'] = 'Gov.br Signature';
                $metadata['signer_name'] = $submission->metadata['govbr_signature']['signer_name'] ?? 'Dr. Atilio Sersun Calefi';
                $metadata['document_description'] = $submission->document_type;
                $submission->metadata = $metadata;
                $submission->save();
            } else {
                $returnedFile = $submission->google_drive_returned_file_id;
                // Normaliza: pega a primeira entrada se for array
                if (is_array($returnedFile)) { $returnedFile = $returnedFile[0] ?? null; }
                if ($returnedFile && is_string($returnedFile) && !str_contains($returnedFile, 'http')) {
                    $diskName = in_array(config('filesystems.default'), ['google', 'onedrive']) ? config('filesystems.default') : 'public';
                    $disk = \Illuminate\Support\Facades\Storage::disk($diskName);
                    
                    $targetDisk = null;
                    if ($disk->exists($returnedFile)) {
                        $targetDisk = $disk;
                    } elseif (\Illuminate\Support\Facades\Storage::disk('local')->exists($returnedFile)) {
                        $targetDisk = \Illuminate\Support\Facades\Storage::disk('local');
                    } elseif (\Illuminate\Support\Facades\Storage::disk('public')->exists($returnedFile)) {
                        $targetDisk = \Illuminate\Support\Facades\Storage::disk('public');
                    }

                    if ($targetDisk) {
                        try {
                            $content = $targetDisk->get($returnedFile);
                            $isSigned = (str_contains($content, '/Type/Sig') || str_contains($content, '/Type /Sig') || str_contains($content, '/ByteRange'));
                            if ($isSigned) {
                                $hash = hash('sha256', $content);
                                $metadata = $submission->metadata ?? [];
                                $metadata['forensic_hash'] = $hash;
                                $metadata['forensic_ip'] = '127.0.0.1';
                                $metadata['forensic_timestamp'] = now()->timezone('America/Sao_Paulo')->format('d/m/Y - H:i:s');
                                $metadata['forensic_user_agent'] = 'Self-Healed/System';
                                $extractedSigner = DocumentSubmission::extractSignerNameFromPdfContent($content);
                                $metadata['signer_name'] = $extractedSigner ?: 'Dr. Atilio Sersun Calefi';
                                $metadata['original_filename'] = basename($returnedFile);
                                $metadata['document_description'] = $submission->document_type;
                                $submission->metadata = $metadata;
                                $submission->save();
                            }
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error("Self-healing failed: " . $e->getMessage());
                        }
                    }
                }
            }
        }

        // Auto-correção (Self-healing) do tamanho do arquivo se for 0 ou nulo
        if (empty($submission->file_size_kb) || $submission->file_size_kb == 0) {
            $fileId = $submission->google_drive_returned_file_id ?: $submission->google_drive_file_id;
            // Normaliza: pega primeira entrada se for array
            if (is_array($fileId)) { $fileId = $fileId[0] ?? null; }
            if ($fileId && is_string($fileId) && !str_contains($fileId, 'http')) {
                $diskName = in_array(config('filesystems.default'), ['google', 'onedrive']) ? config('filesystems.default') : 'public';
                $disk = \Illuminate\Support\Facades\Storage::disk($diskName);
                
                $targetDisk = null;
                if ($disk->exists($fileId)) {
                    $targetDisk = $disk;
                } elseif (\Illuminate\Support\Facades\Storage::disk('local')->exists($fileId)) {
                    $targetDisk = \Illuminate\Support\Facades\Storage::disk('local');
                } elseif (\Illuminate\Support\Facades\Storage::disk('public')->exists($fileId)) {
                    $targetDisk = \Illuminate\Support\Facades\Storage::disk('public');
                }

                if ($targetDisk) {
                    try {
                        $submission->file_size_kb = round($targetDisk->size($fileId) / 1024, 2);
                        $submission->save();
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error("Failed to self-heal file size: " . $e->getMessage());
                    }
                }
            }
        }

        if (!isset($submission->metadata['forensic_hash'])) {
            abort(400, 'Este documento não possui assinatura digital forense.');
        }

        $hash = $submission->metadata['forensic_hash'];
        $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode(url("/documento/validar/{$hash}"));

        $hasGovBrSignature = isset($submission->metadata['govbr_signature']);
        if ($hasGovBrSignature) {
            $viewName = 'govbr_signature_certificate';
        } else {
            $viewName = ($submission->document_type !== 'Termo de Aceite de IC') ? 'local_signature_certificate' : 'document_receipt';
        }

        // Se o usuário solicitou o download do PDF
        if (request()->query('download') == 1) {
            $pdfPath = $submission->metadata['receipt_pdf_path'] ?? null;
            $diskName = in_array(config('filesystems.default'), ['google', 'onedrive']) ? config('filesystems.default') : 'public';
            $disk = \Illuminate\Support\Facades\Storage::disk($diskName);

            // Se o PDF físico já existe no storage, faz download dele
            if ($pdfPath) {
                $targetDisk = null;
                if ($disk->exists($pdfPath)) {
                    $targetDisk = $disk;
                } elseif (\Illuminate\Support\Facades\Storage::disk('local')->exists($pdfPath)) {
                    $targetDisk = \Illuminate\Support\Facades\Storage::disk('local');
                } elseif (\Illuminate\Support\Facades\Storage::disk('public')->exists($pdfPath)) {
                    $targetDisk = \Illuminate\Support\Facades\Storage::disk('public');
                }

                if ($targetDisk) {
                    return $targetDisk->download($pdfPath, "recibo_{$submission->process_id}.pdf");
                }
            }

            // Se não existe o PDF físico ainda no storage, tenta gerá-lo sob demanda
            if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
                $html = view($viewName, [
                    'submission' => $submission,
                    'student' => $submission->student,
                    'user' => $submission->student ? $submission->student->user : null,
                    'hash' => $hash,
                    'qrCodeUrl' => $qrCodeUrl,
                    'metadata' => $submission->metadata,
                    'sig' => $submission->metadata['govbr_signature'] ?? null,
                ])->render();

                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
                return $pdf->download("recibo_{$submission->process_id}.pdf");
            }
        }

        return view($viewName, [
            'submission' => $submission,
            'student' => $submission->student,
            'user' => $submission->student ? $submission->student->user : null,
            'hash' => $hash,
            'qrCodeUrl' => $qrCodeUrl,
            'metadata' => $submission->metadata,
            'sig' => $submission->metadata['govbr_signature'] ?? null,
        ]);
    }

    /**
     * Validação pública de assinatura digital (acesso aberto via QR Code).
     */
    public function validateSignature($hash)
    {
        // Encontra a submissão buscando pela chave do JSON de metadados
        $submission = DocumentSubmission::with(['student.user'])
            ->where('metadata->forensic_hash', $hash)
            ->first();

        return view('document_validate', [
            'submission' => $submission,
            'hash' => $hash,
        ]);
    }

    /**
     * Download seguro do arquivo enviado ou assinado.
     */
    public function downloadFile($id, $type)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403, 'Acesso não autorizado.');
        }

        $submission = DocumentSubmission::with(['student.user'])->findOrFail($id);

        // Segurança: Apenas o orientador (admin) ou o próprio aluno dono do documento podem baixar
        if ($user->role !== 'admin' && $submission->student->user_id !== $user->id) {
            abort(403, 'Você não tem permissão para baixar este arquivo.');
        }

        // Uma vez concluído com assinatura disponível, o aluno não pode mais baixar o
        // arquivo original (não assinado) que ele enviou, evitando confusão com a versão assinada.
        if ($type === 'original' && $user->role === 'student' && $submission->isConcluded() && !empty($submission->google_drive_returned_file_id)) {
            abort(403, 'Este documento já foi assinado e devolvido. Baixe a versão assinada disponível no processo.');
        }

        if ($type === 'signed_final') {
            // Versão assinada registrada pelo status "Documento Assinado - Aguardando
            // Correções do Aluno" (TCC Completo) — independe da devolução atual.
            $filePathOrUrl = $submission->signedFinalFile();
        } else {
            $fileField = $type === 'returned' ? 'google_drive_returned_file_id' : 'google_drive_file_id';
            $filePathOrUrl = $submission->$fileField;
        }

        if (!$filePathOrUrl) {
            abort(404, 'Arquivo não disponível.');
        }

        // Se for um array de caminhos (múltiplos arquivos)
        if (is_array($filePathOrUrl)) {
            if (empty($filePathOrUrl)) {
                abort(404, 'Nenhum arquivo disponível.');
            }

            if (count($filePathOrUrl) === 1) {
                $filePathOrUrl = $filePathOrUrl[0];
            } else {
                if (class_exists(\ZipArchive::class)) {
                    try {
                        $zip = new \ZipArchive();
                        $zipFileName = "Submissao_{$submission->process_id}_arquivos.zip";
                        $zipFilePath = storage_path("app/private/temp_zips/{$zipFileName}");

                        if (!file_exists(dirname($zipFilePath))) {
                            mkdir(dirname($zipFilePath), 0755, true);
                        }

                        if ($zip->open($zipFilePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                            $diskName = in_array(config('filesystems.default'), ['google', 'onedrive']) ? config('filesystems.default') : 'public';
                            $disk = \Illuminate\Support\Facades\Storage::disk($diskName);
                            $addedFilesCount = 0;

                            foreach ($filePathOrUrl as $index => $path) {
                                if (is_string($path)) {
                                    if (str_contains($path, 'http://') || str_contains($path, 'https://')) {
                                        try {
                                            $fileContent = file_get_contents($path);
                                            if ($fileContent !== false) {
                                                $zip->addFromString("arquivo_" . ($index + 1) . ".pdf", $fileContent);
                                                $addedFilesCount++;
                                            }
                                        } catch (\Exception $e) {}
                                    } else {
                                        $targetDisk = null;
                                        if ($disk->exists($path)) {
                                            $targetDisk = $disk;
                                        } elseif (\Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
                                            $targetDisk = \Illuminate\Support\Facades\Storage::disk('local');
                                        } elseif (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                                            $targetDisk = \Illuminate\Support\Facades\Storage::disk('public');
                                        }

                                        if ($targetDisk) {
                                            $zip->addFromString(basename($path), $targetDisk->get($path));
                                            $addedFilesCount++;
                                        }
                                    }
                                }
                            }

                            $zip->close();

                            if ($addedFilesCount > 0 && file_exists($zipFilePath)) {
                                return response()->download($zipFilePath)->deleteFileAfterSend(true);
                            }
                        }
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error("Erro ao zipar múltiplos arquivos para download: " . $e->getMessage());
                    }
                }

                // Fallback se ZipArchive falhar ou não existir: baixa o primeiro arquivo do array
                $filePathOrUrl = $filePathOrUrl[0];
            }
        }

        // Se for uma URL externa (ex: link direto externo)
        if (str_contains($filePathOrUrl, 'http://') || str_contains($filePathOrUrl, 'https://')) {
            return redirect($filePathOrUrl);
        }

        // Caso contrário, é um caminho relativo no storage
        $diskName = in_array(config('filesystems.default'), ['google', 'onedrive']) ? config('filesystems.default') : 'public';
        $disk = \Illuminate\Support\Facades\Storage::disk($diskName);

        // Tenta encontrar o arquivo sequencialmente em todos os discos possíveis (fallback)
        $targetDisk = null;
        if ($disk->exists($filePathOrUrl)) {
            $targetDisk = $disk;
        } elseif (\Illuminate\Support\Facades\Storage::disk('local')->exists($filePathOrUrl)) {
            $targetDisk = \Illuminate\Support\Facades\Storage::disk('local');
        } elseif (\Illuminate\Support\Facades\Storage::disk('public')->exists($filePathOrUrl)) {
            $targetDisk = \Illuminate\Support\Facades\Storage::disk('public');
        }

        // Devoluções gravadas com o caminho de antes da renomeação automática (save que
        // falhou na validação depois de já ter renomeado o arquivo no Drive): procura o
        // nome renomeado do próprio processo e corrige o registro.
        if (!$targetDisk && $type === 'returned') {
            $extension = pathinfo($filePathOrUrl, PATHINFO_EXTENSION) ?: 'pdf';
            $expectedPath = $submission->expectedReturnedFilePath($extension);
            if ($expectedPath !== $filePathOrUrl && $disk->exists($expectedPath)) {
                \Illuminate\Support\Facades\Log::warning("Devolução da submissão {$submission->process_id} apontava para caminho inexistente ({$filePathOrUrl}); corrigido para {$expectedPath}.");
                \Illuminate\Support\Facades\DB::table('document_submissions')
                    ->where('id', $submission->id)
                    ->update(['google_drive_returned_file_id' => $expectedPath]);
                $filePathOrUrl = $expectedPath;
                $targetDisk = $disk;
            }
        }

        if (!$targetDisk) {
            abort(404, 'Arquivo físico não encontrado no servidor ou nuvem.');
        }

        if (request()->query('preview') == 1) {
            $extension = strtolower(pathinfo($filePathOrUrl, PATHINFO_EXTENSION));
            $mimeType = match ($extension) {
                'pdf' => 'application/pdf',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'doc' => 'application/msword',
                'png' => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                default => 'application/octet-stream'
            };
            
            return response()->stream(function () use ($targetDisk, $filePathOrUrl) {
                fpassthru($targetDisk->readStream($filePathOrUrl));
            }, 200, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline; filename="' . basename($filePathOrUrl) . '"',
            ]);
        }

        return $targetDisk->download($filePathOrUrl);
    }

    /**
     * Redireciona para o Gov.br ou para a simulação de login para assinatura.
     */
    public function govbrRedirect($id)
    {
        $user = auth()->user();
        if (!$user || $user->role !== 'admin') {
            abort(403, 'Acesso reservado ao orientador.');
        }

        $submission = DocumentSubmission::with(['student.user'])->findOrFail($id);
        $fileId = $submission->google_drive_file_id;

        if (!$fileId) {
            return redirect('/admin/document-submissions')
                ->with('error', 'Nenhum arquivo enviado pelo aluno para assinar.');
        }

        // Determina o disco de armazenamento ativo
        $diskName = in_array(config('filesystems.default'), ['google', 'onedrive']) ? config('filesystems.default') : 'public';
        $disk = \Illuminate\Support\Facades\Storage::disk($diskName);

        if (!$disk->exists($fileId)) {
            return redirect('/admin/document-submissions')
                ->with('error', 'Arquivo original não encontrado no servidor de arquivos.');
        }

        try {
            // Calcula o hash SHA-256 do arquivo original
            $pdfContent = $disk->get($fileId);
            $pdfHash = hash('sha256', $pdfContent);

            // Grava os dados da submissão na sessão
            session([
                'govbr_sign_submission_id' => $id,
                'govbr_sign_hash' => $pdfHash,
            ]);

            $clientId = env('GOVBR_CLIENT_ID');
            $clientSecret = env('GOVBR_CLIENT_SECRET');

            // Se as credenciais oficiais não estiverem configuradas, ativa o modo de Simulação (Sandbox)
            if (empty($clientId) || empty($clientSecret)) {
                return view('govbr_mock_login', [
                    'submission' => $submission,
                    'pdfHash' => $pdfHash,
                ]);
            }

            // Caso contrário, redireciona para o fluxo real do Gov.br
            $callbackUrl = route('documento.govbr.callback');
            $casUrl = config('services.govbr.cas_url');
            $authUrl = "{$casUrl}/oauth2.0/authorize?response_type=code&client_id=" . urlencode($clientId) . "&redirect_uri=" . urlencode($callbackUrl) . "&scope=sign";

            return redirect($authUrl);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Erro no redirecionamento Gov.br: " . $e->getMessage());
            return redirect('/admin/document-submissions')
                ->with('error', 'Erro ao iniciar o processo de assinatura digital.');
        }
    }

    /**
     * Redireciona para o Gov.br ou para a simulação de login para assinatura em lote.
     */
    public function govbrBulkRedirect()
    {
        $user = auth()->user();
        if (!$user || $user->role !== 'admin') {
            abort(403, 'Acesso reservado ao orientador.');
        }

        $bulkIds = session('govbr_sign_bulk_ids');
        if (empty($bulkIds)) {
            return redirect('/admin/document-submissions')
                ->with('error', 'Nenhum documento na sessão para assinatura em lote.');
        }

        $submissions = DocumentSubmission::with(['student.user'])
            ->whereIn('id', $bulkIds)
            ->get();

        if ($submissions->isEmpty()) {
            return redirect('/admin/document-submissions')
                ->with('error', 'Documentos não encontrados.');
        }

        $diskName = in_array(config('filesystems.default'), ['google', 'onedrive']) ? config('filesystems.default') : 'public';
        $disk = \Illuminate\Support\Facades\Storage::disk($diskName);

        $hashes = [];
        $validSubmissions = [];

        foreach ($submissions as $sub) {
            $fileId = $sub->google_drive_file_id;
            if ($fileId && $disk->exists($fileId)) {
                try {
                    $pdfContent = $disk->get($fileId);
                    $pdfHash = hash('sha256', $pdfContent);
                    $hashes[$sub->id] = $pdfHash;
                    $validSubmissions[] = $sub;
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Erro ao ler arquivo da submissão {$sub->process_id} para assinatura em lote: " . $e->getMessage());
                }
            }
        }

        if (empty($hashes)) {
            return redirect('/admin/document-submissions')
                ->with('error', 'Os arquivos físicos dos documentos selecionados não foram encontrados.');
        }

        $validIds = array_keys($hashes);
        session([
            'govbr_sign_bulk_ids' => $validIds,
            'govbr_sign_bulk_hashes' => $hashes,
        ]);

        $clientId = env('GOVBR_CLIENT_ID');
        $clientSecret = env('GOVBR_CLIENT_SECRET');

        if (empty($clientId) || empty($clientSecret)) {
            return view('govbr_mock_login', [
                'submissions' => $validSubmissions,
                'hashes' => $hashes,
                'isBulk' => true,
            ]);
        }

        $callbackUrl = route('documento.govbr.callback');
        $casUrl = config('services.govbr.cas_url');
        $authUrl = "{$casUrl}/oauth2.0/authorize?response_type=code&client_id=" . urlencode($clientId) . "&redirect_uri=" . urlencode($callbackUrl) . "&scope=sign";

        return redirect($authUrl);
    }

    /**
     * Callback do Gov.br que executa a assinatura do hash e gera o recibo assinado.
     */
    public function govbrCallback(Request $request)
    {
        if ($request->has('signatureSessionId')) {
            return $this->handleRestPkiCallback($request);
        }

        $user = auth()->user();
        if (!$user || $user->role !== 'admin') {
            abort(403, 'Acesso reservado ao orientador.');
        }

        $code = $request->query('code');
        $bulkIds = session('govbr_sign_bulk_ids');
        $bulkHashes = session('govbr_sign_bulk_hashes');

        if (!empty($bulkIds) && !empty($bulkHashes)) {
            try {
                $clientId = env('GOVBR_CLIENT_ID');
                $clientSecret = env('GOVBR_CLIENT_SECRET');

                $isMock = true;
                $accessToken = null;

                if ($code !== 'mock_code_12345' && !empty($clientId) && !empty($clientSecret)) {
                    $callbackUrl = route('documento.govbr.callback');
                    $casUrl = config('services.govbr.cas_url');
                    
                    $tokenResponse = \Illuminate\Support\Facades\Http::asForm()->post("{$casUrl}/oauth2.0/token", [
                        'code' => $code,
                        'client_id' => $clientId,
                        'client_secret' => $clientSecret,
                        'grant_type' => 'authorization_code',
                        'redirect_uri' => $callbackUrl,
                    ]);

                    if (!$tokenResponse->successful()) {
                        throw new \Exception('Erro ao obter token do Gov.br: ' . $tokenResponse->body());
                    }

                    $accessToken = $tokenResponse->json()['access_token'] ?? null;
                    if (!$accessToken) {
                        throw new \Exception('Access Token não retornado no JSON do Gov.br.');
                    }
                    $isMock = false;
                }

                $signerName = 'Dr. Atilio Sersun Calefi';
                $signerCpf = '***.***.***-**';
                $signedCount = 0;

                $diskName = in_array(config('filesystems.default'), ['google', 'onedrive']) ? config('filesystems.default') : 'public';
                $disk = \Illuminate\Support\Facades\Storage::disk($diskName);

                foreach ($bulkIds as $subId) {
                    $pdfHash = $bulkHashes[$subId] ?? null;
                    if (!$pdfHash) continue;

                    $submission = DocumentSubmission::with(['student.user'])->find($subId);
                    if (!$submission) continue;

                    $pkcs7Signature = '';

                    if (!$isMock && $accessToken) {
                        $apiUrl = config('services.govbr.api_url');
                        $signResponse = \Illuminate\Support\Facades\Http::withToken($accessToken)
                            ->post("{$apiUrl}/externo/v2/assinarPKCS7", [
                                'hashBase64' => base64_encode(hex2bin($pdfHash)),
                            ]);

                        if ($signResponse->successful()) {
                            $pkcs7Signature = $signResponse->body();
                        } else {
                            \Illuminate\Support\Facades\Log::error("Erro na assinatura em lote do hash ITI/Gov.br para submissão {$submission->process_id}: " . $signResponse->body());
                            continue;
                        }
                    } else {
                        $pkcs7Signature = 'MOCK_PKCS7_SIGNATURE_DATA_' . hash('sha256', $pdfHash . now());
                    }

                    $sigMetadata = [
                        'signed_via' => 'Gov.br (ITI) [Lote]',
                        'signer_name' => $signerName,
                        'signer_cpf' => $signerCpf,
                        'signed_at' => now()->format('d/m/Y - H:i:s'),
                        'original_pdf_hash' => $pdfHash,
                        'ip' => $request->ip(),
                        'is_mock' => $isMock,
                        'pkcs7' => base64_encode($pkcs7Signature),
                    ];

                    $metadata = $submission->metadata ?? [];
                    $metadata['govbr_signature'] = $sigMetadata;
                    $metadata['forensic_hash'] = $pdfHash;
                    $metadata['forensic_timestamp'] = $sigMetadata['signed_at'];
                    $metadata['forensic_ip'] = $sigMetadata['ip'];
                    $metadata['forensic_user_agent'] = $request->userAgent() ?? 'Gov.br Signature';
                    $metadata['signer_name'] = $signerName;
                    $metadata['document_description'] = $submission->document_type;
                    $submission->metadata = $metadata;

                    $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode(url("/documento/validar/" . $pdfHash));
                    
                    $html = view('govbr_signature_certificate', [
                        'submission' => $submission,
                        'student' => $submission->student,
                        'user' => $submission->student->user,
                        'sig' => $sigMetadata,
                        'qrCodeUrl' => $qrCodeUrl,
                    ])->render();

                    $returnedPath = "returns/assinado_{$submission->process_id}.pdf";

                    if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
                        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
                        $pdfContent = $pdf->output();
                        $disk->put($returnedPath, $pdfContent);
                        $submission->google_drive_returned_file_id = $returnedPath;
                    }

                    $submission->status = $this->getConcludedStatus($submission->document_type);
                    $submission->save();

                    $signedCount++;
                }

                session()->forget(['govbr_sign_bulk_ids', 'govbr_sign_bulk_hashes']);

                if (class_exists(\Filament\Notifications\Notification::make())) {
                    \Filament\Notifications\Notification::make()
                        ->title("{$signedCount} Documentos Assinados com Sucesso!")
                        ->body("Os documentos selecionados foram homologados e assinados em lote via Gov.br.")
                        ->success()
                        ->send();
                }

                return redirect('/admin/document-submissions');
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Erro no callback de lote Gov.br: " . $e->getMessage());
                return redirect('/admin/document-submissions')
                    ->with('error', 'Erro ao finalizar assinatura digital em lote: ' . $e->getMessage());
            }
        }

        $submissionId = session('govbr_sign_submission_id');
        $pdfHash = session('govbr_sign_hash');

        if (!$submissionId || !$pdfHash) {
            if (session()->has('govbr_standalone_hash')) {
                return $this->handleStandaloneCallback($request);
            }

            return redirect('/admin/document-submissions')
                ->with('error', 'Sessão de assinatura expirada ou inválida.');
        }

        $submission = DocumentSubmission::with(['student.user'])->findOrFail($submissionId);

        try {
            $isMock = true;
            $pkcs7Signature = '';
            $signerName = 'Dr. Atilio Sersun Calefi';
            $signerCpf = '***.***.***-**';

            $clientId = env('GOVBR_CLIENT_ID');
            $clientSecret = env('GOVBR_CLIENT_SECRET');

            // Se for produção/staging real
            if ($code !== 'mock_code_12345' && !empty($clientId) && !empty($clientSecret)) {
                $callbackUrl = route('documento.govbr.callback');
                
                // 1. Troca o código pelo Token
                $casUrl = config('services.govbr.cas_url');
                $tokenResponse = \Illuminate\Support\Facades\Http::asForm()->post("{$casUrl}/oauth2.0/token", [
                    'code' => $code,
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $callbackUrl,
                ]);

                if (!$tokenResponse->successful()) {
                    throw new \Exception('Erro ao obter token do Gov.br: ' . $tokenResponse->body());
                }

                $accessToken = $tokenResponse->json()['access_token'] ?? null;
                if (!$accessToken) {
                    throw new \Exception('Access Token não retornado no JSON do Gov.br.');
                }

                // 2. Realiza a assinatura do Hash SHA-256 via API do ITI
                $apiUrl = config('services.govbr.api_url');
                $signResponse = \Illuminate\Support\Facades\Http::withToken($accessToken)
                    ->post("{$apiUrl}/externo/v2/assinarPKCS7", [
                        'hashBase64' => base64_encode(hex2bin($pdfHash)),
                    ]);

                if (!$signResponse->successful()) {
                    throw new \Exception('Erro na assinatura do hash ITI/Gov.br: ' . $signResponse->body());
                }

                $pkcs7Signature = $signResponse->body(); // A resposta binária é a assinatura PKCS#7
                $isMock = false;
                
                // Podemos extrair informações do certificado do usuário do token se necessário, ou usar dados padrão
            } else {
                // Modo simulado
                $pkcs7Signature = 'MOCK_PKCS7_SIGNATURE_DATA_' . hash('sha256', $pdfHash . now());
            }

            // 3. Salva os metadados de assinatura no banco de dados
            $sigMetadata = [
                'signed_via' => 'Gov.br (ITI)',
                'signer_name' => $signerName,
                'signer_cpf' => $signerCpf,
                'signed_at' => now()->format('d/m/Y - H:i:s'),
                'original_pdf_hash' => $pdfHash,
                'ip' => $request->ip(),
                'is_mock' => $isMock,
                'pkcs7' => base64_encode($pkcs7Signature),
            ];

            $metadata = $submission->metadata ?? [];
            $metadata['govbr_signature'] = $sigMetadata;
            $metadata['forensic_hash'] = $pdfHash;
            $metadata['forensic_timestamp'] = $sigMetadata['signed_at'];
            $metadata['forensic_ip'] = $sigMetadata['ip'];
            $metadata['forensic_user_agent'] = $request->userAgent() ?? 'Gov.br Signature';
            $metadata['signer_name'] = $signerName;
            $metadata['document_description'] = $submission->document_type;
            $submission->metadata = $metadata;

            // 4. Gera o Termo/Certificado de Homologação em PDF físico usando Dompdf
            $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode(url("/documento/validar/" . $pdfHash));
            
            $html = view('govbr_signature_certificate', [
                'submission' => $submission,
                'student' => $submission->student,
                'user' => $submission->student->user,
                'sig' => $sigMetadata,
                'qrCodeUrl' => $qrCodeUrl,
            ])->render();

            $returnedPath = "returns/assinado_{$submission->process_id}.pdf";
            
            if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
                $pdfContent = $pdf->output();

                $diskName = in_array(config('filesystems.default'), ['google', 'onedrive']) ? config('filesystems.default') : 'public';
                $disk = \Illuminate\Support\Facades\Storage::disk($diskName);
                $disk->put($returnedPath, $pdfContent);

                // Salva o caminho do arquivo final assinado
                $submission->google_drive_returned_file_id = $returnedPath;
            }

            // 5. Altera o status do processo de acordo com o tipo de documento
            $submission->status = $this->getConcludedStatus($submission->document_type);
            $submission->save();

            // Limpa as variáveis de sessão de assinatura
            session()->forget(['govbr_sign_submission_id', 'govbr_sign_hash']);

            // Envia Notificação de Sucesso para a UI do Filament
            if (class_exists(\Filament\Notifications\Notification::class)) {
                \Filament\Notifications\Notification::make()
                    ->title('Documento Assinado com Sucesso!')
                    ->body("O documento do processo {$submission->process_id} foi homologado e assinado via Gov.br.")
                    ->success()
                    ->send();
            }

            return redirect('/admin/document-submissions');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Erro no callback Gov.br para o processo {$submission->process_id}: " . $e->getMessage());
            return redirect('/admin/document-submissions')
                ->with('error', 'Erro ao finalizar a assinatura digital: ' . $e->getMessage());
        }
    }

    /**
     * Retorna o status de conclusão adequado com base no tipo do documento.
     */
    private function getConcludedStatus(string $type): string
    {
        return match ($type) {
            'TCC', 'Relatório de Estágio', 'Relatório de Aula', 'Documento Solicitado', 'Projeto de TCC (Assinatura)', 'TCC Final (Assinatura)' => 'Processo Concluído - Documento Assinado Disponível',
            'Iniciação Científica' => 'Processo Concluído - Projeto Arquivado',
            'Relatório', 'Artigo Científico', 'Relato de Caso', 'Trabalho de Congresso', 'Resumo para Evento', 'Banner para Apresentação' => 'Processo Concluído - Versão Final Definida',
            'Plano de Estágio', 'Termo de Aceite de Orientação' => 'Processo Concluído - Termo de Aceite Assinado',
            'Avaliações de Estágio' => 'Processo Concluído - Estágio Homologado com Sucesso',
            'Relatório de Necropsia' => 'Processo Concluído - Laudo de Necropsia Homologado',
            'Termo de Aceite de IC' => 'Processo Concluído - Orientação de IC Homologada e Ativa',
            'Concepção de TCC' => 'Processo Concluído - Tema Definido',
            'Objetivos de TCC' => 'Processo Concluído - Objetivos Aprovados',
            'Introdução de TCC' => 'Processo Concluído - Introdução Aprovada',
            'Materiais e Métodos de TCC' => 'Processo Concluído - Materiais e Métodos Aprovados',
            'Resultados de TCC' => 'Processo Concluído - Resultados Aprovados',
            'Discussão de TCC' => 'Processo Concluído - Discussão Aprovada',
            'Conclusão de TCC' => 'Processo Concluído - Conclusão Aprovada',
            // 'Protocolo CEUA/CEP' e 'Outro' usam o fluxo genérico legado
            // (pending/reviewing/approved/...), sem um status "Processo Concluído" dedicado.
            default => 'returned_signed',
        };
    }

    /**
     * Callback do Gov.br para assinaturas avulsas (suporta lote).
     */
    private function handleStandaloneCallback(Request $request)
    {
        $filePaths = session('govbr_standalone_files');
        $hashes = session('govbr_standalone_hashes');
        $filenames = session('govbr_standalone_filenames');
        $titles = session('govbr_standalone_titles', []);
        $descriptions = session('govbr_standalone_descriptions', []);
        $customNames = session('govbr_standalone_custom_names', []);

        $code = $request->query('code');

        if (empty($filePaths) || empty($hashes)) {
            return redirect('/admin/assinatura-avulsa')
                ->with('error', 'Sessão de assinatura avulsa expirada ou inválida.');
        }

        try {
            $clientId = env('GOVBR_CLIENT_ID');
            $clientSecret = env('GOVBR_CLIENT_SECRET');

            $isMock = true;
            $accessToken = null;

            if ($code !== 'mock_code_12345' && !empty($clientId) && !empty($clientSecret)) {
                $callbackUrl = route('documento.govbr.callback');
                
                // 1. Troca o código pelo Token
                $casUrl = config('services.govbr.cas_url');
                $tokenResponse = \Illuminate\Support\Facades\Http::asForm()->post("{$casUrl}/oauth2.0/token", [
                    'code' => $code,
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $callbackUrl,
                ]);

                if (!$tokenResponse->successful()) {
                    throw new \Exception('Erro ao obter token do Gov.br: ' . $tokenResponse->body());
                }

                $accessToken = $tokenResponse->json()['access_token'] ?? null;
                if (!$accessToken) {
                    throw new \Exception('Access Token não retornado no JSON do Gov.br.');
                }
                $isMock = false;
            }

            $signerName = 'Dr. Atilio Sersun Calefi';
            $signerCpf = '***.***.***-**';
            $downloadPaths = [];

            $diskName = in_array(config('filesystems.default'), ['google', 'onedrive']) ? config('filesystems.default') : 'public';
            $disk = \Illuminate\Support\Facades\Storage::disk($diskName);

            $index = 1;
            // Loop por cada arquivo para assinar
            foreach ($filePaths as $path) {
                $pdfHash = $hashes[$path] ?? null;
                $origFilename = $filenames[$path] ?? 'documento.pdf';
                if (!$pdfHash) continue;

                $docTitle = $titles[$path] ?? session('govbr_standalone_title') ?? 'Sem Título';
                $docDescription = $descriptions[$path] ?? session('govbr_standalone_description') ?? 'Documento';
                $customName = $customNames[$path] ?? null;

                $pkcs7Signature = '';

                if (!$isMock && $accessToken) {
                    // Assinatura do hash específico na API do ITI
                    $apiUrl = config('services.govbr.api_url');
                    $signResponse = \Illuminate\Support\Facades\Http::withToken($accessToken)
                        ->post("{$apiUrl}/externo/v2/assinarPKCS7", [
                            'hashBase64' => base64_encode(hex2bin($pdfHash)),
                        ]);

                    if ($signResponse->successful()) {
                        $pkcs7Signature = $signResponse->body();
                    } else {
                        \Illuminate\Support\Facades\Log::error("Erro ao assinar arquivo avulso {$origFilename}: " . $signResponse->body());
                        continue;
                    }
                } else {
                    $pkcs7Signature = 'MOCK_PKCS7_SIGNATURE_DATA_' . hash('sha256', $pdfHash . now());
                }

                // Salva metadados
                $sigMetadata = [
                    'signed_via' => 'Gov.br (ITI) [Avulso Lote]',
                    'signer_name' => $signerName,
                    'signer_cpf' => $signerCpf,
                    'signed_at' => now()->format('d/m/Y - H:i:s'),
                    'original_pdf_hash' => $pdfHash,
                    'ip' => $request->ip(),
                    'is_mock' => $isMock,
                    'pkcs7' => base64_encode($pkcs7Signature),
                ];

                // Cria modelo temporário para renderização do Termo
                $submission = new DocumentSubmission();
                $submission->process_id = 'AVULSO-' . now()->format('Y') . '-' . strtoupper(substr($pdfHash, 0, 8));
                $submission->document_type = $docDescription;
                
                // Mostra o nome do arquivo original no título do termo se múltiplos arquivos
                $submission->title = count($filePaths) > 1 ? "{$docTitle} ({$origFilename})" : $docTitle;

                $student = new \App\Models\Student();
                $student->registration = 'N/A';
                $student->user = new \App\Models\User();
                $student->user->name = 'N/A';

                $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode(url("/documento/validar/" . $pdfHash));

                $html = view('govbr_signature_certificate', [
                    'submission' => $submission,
                    'student' => $student,
                    'user' => $student->user,
                    'sig' => $sigMetadata,
                    'qrCodeUrl' => $qrCodeUrl,
                ])->render();

                $returnedPath = "returns/assinado_avulso_" . strtoupper(substr($pdfHash, 0, 8)) . ".pdf";

                if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
                    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
                    $pdfContent = $pdf->output();
                    $disk->put($returnedPath, $pdfContent);
                }

                // Determina o nome final do arquivo de download com sufixo '_assinado'
                $origBaseName = pathinfo($origFilename, PATHINFO_FILENAME);
                
                if (!empty($customName)) {
                    // Remove caracteres inválidos para nomes de arquivos
                    $safeCustomName = preg_replace('/[^a-zA-Z0-9_\-]/u', '', $customName);
                    $finalDownloadName = "{$safeCustomName}_assinado.pdf";
                } else {
                    $finalDownloadName = "{$origBaseName}_assinado.pdf";
                }

                $downloadPaths[] = [
                    'filename' => $finalDownloadName,
                    'path' => $returnedPath,
                ];

                $index++;
            }

            // Limpa as variáveis de sessão
            session()->forget([
                'govbr_standalone_file_path',
                'govbr_standalone_file_name',
                'govbr_standalone_files',
                'govbr_standalone_hashes',
                'govbr_standalone_filenames',
                'govbr_standalone_title',
                'govbr_standalone_description',
                'govbr_standalone_hash',
                'govbr_standalone_custom_name',
                'govbr_standalone_titles',
                'govbr_standalone_descriptions',
                'govbr_standalone_custom_names',
            ]);

            // Grava os caminhos dos downloads gerados
            session(['govbr_standalone_download_paths' => $downloadPaths]);

            if (class_exists(\Filament\Notifications\Notification::class)) {
                \Filament\Notifications\Notification::make()
                    ->title('Documentos Assinados com Sucesso!')
                    ->body(count($downloadPaths) . " termos de assinatura foram gerados.")
                    ->success()
                    ->send();
            }

            return redirect('/admin/assinatura-avulsa');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Erro no callback de assinatura avulsa em lote: " . $e->getMessage());
            return redirect('/admin/assinatura-avulsa')
                ->with('error', 'Erro ao assinar documentos avulsos: ' . $e->getMessage());
        }
    }

    /**
     * Callback do Lacuna Rest PKI Core para assinaturas com certificado digital A3/A1.
     */
    private function handleRestPkiCallback(Request $request)
    {
        $sessionId = $request->query('signatureSessionId');
        
        $filePaths = session('govbr_standalone_files');
        $filenames = session('govbr_standalone_filenames');
        $titles = session('govbr_standalone_titles', []);
        $descriptions = session('govbr_standalone_descriptions', []);
        $customNames = session('govbr_standalone_custom_names', []);

        if (empty($filePaths) || !$sessionId) {
            return redirect('/admin/assinatura-avulsa')
                ->with('error', 'Sessão de assinatura expirada ou inválida.');
        }

        try {
            $apiKey = env('RESTPKI_API_KEY');
            $endpoint = env('RESTPKI_ENDPOINT', 'https://core.pki.rest');

            // 1. Consulta os detalhes da sessão de assinatura no Lacuna Rest PKI Core
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'X-Api-Key' => $apiKey,
            ])->get(rtrim($endpoint, '/') . "/api/signature-sessions/{$sessionId}");

            if (!$response->successful()) {
                throw new \Exception('Erro ao consultar sessão no Rest PKI: ' . $response->body());
            }

            $sessionData = $response->json();
            $status = $sessionData['status'] ?? 'Unknown';

            if ($status !== 'Completed') {
                throw new \Exception("A assinatura não foi concluída. Status da sessão: {$status}");
            }

            $documents = $sessionData['documents'] ?? [];
            $downloadPaths = [];
            
            $diskName = in_array(config('filesystems.default'), ['google', 'onedrive']) ? config('filesystems.default') : 'public';
            $disk = \Illuminate\Support\Facades\Storage::disk($diskName);

            // Mapeia os caminhos dos documentos originais
            // Rest PKI Core retorna os documentos na mesma ordem que foram enviados
            foreach ($documents as $docIndex => $docData) {
                // Caminho correspondente do arquivo original enviado
                $path = $filePaths[$docIndex] ?? null;
                if (!$path) continue;

                $signedFileInfo = $docData['signedFile'] ?? null;
                if (!$signedFileInfo || empty($signedFileInfo['location'])) {
                    continue;
                }

                // Faz o download do arquivo assinado da API do Rest PKI
                $downloadUrl = $signedFileInfo['location'];
                $fileResponse = \Illuminate\Support\Facades\Http::withHeaders([
                    'X-Api-Key' => $apiKey,
                ])->get($downloadUrl);

                if (!$fileResponse->successful()) {
                    \Illuminate\Support\Facades\Log::error("Erro ao baixar arquivo assinado da Lacuna: " . $fileResponse->body());
                    continue;
                }

                $signedContent = $fileResponse->body();
                $pdfHash = hash('sha256', $signedContent);

                // Determina o nome final do arquivo de download com sufixo '_assinado'
                $origFilename = $filenames[$path] ?? 'documento.pdf';
                $origBaseName = pathinfo($origFilename, PATHINFO_FILENAME);
                $customName = $customNames[$path] ?? null;

                if (!empty($customName)) {
                    $safeCustomName = preg_replace('/[^a-zA-Z0-9_\-]/u', '', $customName);
                    $finalDownloadName = "{$safeCustomName}_assinado.pdf";
                } else {
                    $finalDownloadName = "{$origBaseName}_assinado.pdf";
                }

                $returnedPath = "returns/assinado_avulso_" . strtoupper(substr($pdfHash, 0, 8)) . ".pdf";
                $disk->put($returnedPath, $signedContent);

                $downloadPaths[] = [
                    'filename' => $finalDownloadName,
                    'path' => $returnedPath,
                ];
            }

            if (empty($downloadPaths)) {
                throw new \Exception('Nenhum documento assinado pôde ser processado.');
            }

            // Limpa as variáveis de sessão
            session()->forget([
                'govbr_standalone_file_path',
                'govbr_standalone_file_name',
                'govbr_standalone_files',
                'govbr_standalone_hashes',
                'govbr_standalone_filenames',
                'govbr_standalone_title',
                'govbr_standalone_description',
                'govbr_standalone_hash',
                'govbr_standalone_custom_name',
                'govbr_standalone_titles',
                'govbr_standalone_descriptions',
                'govbr_standalone_custom_names',
            ]);

            // Grava os caminhos dos downloads gerados
            session(['govbr_standalone_download_paths' => $downloadPaths]);

            if (class_exists(\Filament\Notifications\Notification::class)) {
                \Filament\Notifications\Notification::make()
                    ->title('Documentos Assinados via Certificado Digital!')
                    ->body(count($downloadPaths) . " termos de assinatura foram gerados.")
                    ->success()
                    ->send();
            }

            return redirect('/admin/assinatura-avulsa');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Erro no callback de assinatura Rest PKI: " . $e->getMessage());
            return redirect('/admin/assinatura-avulsa')
                ->with('error', 'Erro ao processar assinatura com certificado digital: ' . $e->getMessage());
        }
    }

    /**
     * Exibe a tela pública de verificação de assinaturas em PDFs.
     */
    public function showVerifier()
    {
        return view('document_verifier');
    }

    /**
     * Processa a verificação do arquivo PDF enviado.
     */
    public function verifyUpload(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'pdf_file' => 'required|file|mimes:pdf|max:51200', // 50MB
        ]);

        try {
            $file = $request->file('pdf_file');
            $content = file_get_contents($file->getRealPath());

            // 1. Verifica se o PDF contém assinatura digital (não repúdio)
            $isSigned = (str_contains($content, '/Type/Sig') || str_contains($content, '/Type /Sig') || str_contains($content, '/ByteRange'));
            
            // 2. Calcula hash SHA-256 do arquivo enviado
            $hash = hash('sha256', $content);

            // 3. Verifica se este hash já existe registrado no banco do site
            $submission = \App\Models\DocumentSubmission::with(['student.user'])
                ->where('metadata->forensic_hash', $hash)
                ->first();

            // 4. Se for assinado, tenta extrair os nomes dos signatários contidos no arquivo
            $extractedSigners = [];
            if ($isSigned) {
                // Procura por padrões CN= na cadeia de certificados embutidos no PKCS#7 do PDF
                preg_match_all('/CN\s*=\s*([^,\\\\]+)/i', $content, $matches);
                if (!empty($matches[1])) {
                    foreach ($matches[1] as $match) {
                        $name = trim($match);
                        // Limpa caracteres não imprimíveis comuns em DER ASN.1
                        $name = preg_replace('/[^\x20-\x7E\xC0-\xFF]/', '', $name);
                        
                        // Formatos de ICP-Brasil contêm o CPF associado: "NOME:CPF"
                        if (str_contains($name, ':')) {
                            $parts = explode(':', $name);
                            $name = trim($parts[0]);
                        }

                        // Remove nomes de Autoridades Certificadoras comuns para listar apenas pessoas
                        $isCA = false;
                        $caKeywords = ['AC ', 'ICP-Brasil', 'Receita Federal', 'Serasa', 'Certisign', 'Autoridade Certificadora', 'ROOT', 'Presidencia', 'PROD', 'CA ', 'Servico', 'Secr', 'Gov', 'Ministerio'];
                        foreach ($caKeywords as $keyword) {
                            if (stripos($name, $keyword) !== false) {
                                $isCA = true;
                                break;
                            }
                        }

                        if (!$isCA && strlen($name) > 3 && strlen($name) < 100 && !in_array($name, $extractedSigners) && preg_match('/^[a-zA-ZÀ-ÿ\s\.\-\/]+$/', $name)) {
                            $extractedSigners[] = $name;
                        }
                    }
                }
            }

            return view('document_verifier', [
                'checked' => true,
                'isSigned' => $isSigned,
                'hash' => $hash,
                'submission' => $submission,
                'signers' => $extractedSigners,
                'filename' => $file->getClientOriginalName(),
            ]);

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Falha ao processar arquivo: ' . $e->getMessage());
        }
    }

    /**
     * Download seguro do arquivo com base em seu caminho no storage.
     */
    public function downloadByPath(\Illuminate\Http\Request $request)
    {
        $path = $request->query('path');
        if (!$path) {
            abort(404, 'Caminho não fornecido.');
        }

        $user = auth()->user();
        if (!$user) {
            abort(403, 'Acesso não autorizado.');
        }

        // Busca por correspondência exata (campos de texto simples)
        $submission = \App\Models\DocumentSubmission::with(['student.user'])
            ->where('google_drive_file_id', $path)
            ->orWhere('google_drive_returned_file_id', $path)
            ->first();

        if (!$submission) {
            // Fallback: campo pode ser JSON array — busca com LIKE
            // Ex: google_drive_file_id = '["submissions/file1.pdf","submissions/file2.pdf"]'
            $escapedPath1 = str_replace(['%', '_'], ['\\%', '\\_'], $path);
            $escapedPath2 = str_replace(['%', '_', '/'], ['\\%', '\\_', '\\/'], $path);
            $escapedPath3 = trim(json_encode($path), '"');
            $escapedPath3 = str_replace(['%', '_'], ['\\%', '\\_'], $escapedPath3);

            // Se for MySQL, precisamos duplicar as barras invertidas para evitar que o MySQL as trate como escape na busca LIKE
            $isMysql = \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'mysql';
            if ($isMysql) {
                $escapedPath1 = str_replace('\\', '\\\\', $escapedPath1);
                $escapedPath2 = str_replace('\\', '\\\\', $escapedPath2);
                $escapedPath3 = str_replace('\\', '\\\\', $escapedPath3);
            }

            $submission = \App\Models\DocumentSubmission::with(['student.user'])
                ->where(function ($q) use ($escapedPath1, $escapedPath2, $escapedPath3) {
                    $q->where('google_drive_file_id', 'LIKE', '%' . $escapedPath1 . '%')
                      ->orWhere('google_drive_file_id', 'LIKE', '%' . $escapedPath2 . '%')
                      ->orWhere('google_drive_file_id', 'LIKE', '%' . $escapedPath3 . '%')
                      ->orWhere('google_drive_returned_file_id', 'LIKE', '%' . $escapedPath1 . '%')
                      ->orWhere('google_drive_returned_file_id', 'LIKE', '%' . $escapedPath2 . '%')
                      ->orWhere('google_drive_returned_file_id', 'LIKE', '%' . $escapedPath3 . '%');
                })
                ->first();
        }

        if (!$submission) {
            // Tenta buscar no metadata do recibo
            $submission = \App\Models\DocumentSubmission::with(['student.user'])
                ->where('metadata->receipt_pdf_path', $path)
                ->first();
        }

        if (!$submission) {
            abort(404, 'Arquivo não vinculado a nenhum processo registrado.');
        }

        // Apenas o orientador (admin) ou o próprio aluno dono do documento podem baixar
        if ($user->role !== 'admin' && ($submission->student && $submission->student->user_id !== $user->id)) {
            abort(403, 'Você não tem permissão para baixar este arquivo.');
        }

        $diskName = in_array(config('filesystems.default'), ['google', 'onedrive']) ? config('filesystems.default') : 'public';
        $disk = \Illuminate\Support\Facades\Storage::disk($diskName);

        // Tenta encontrar o arquivo sequencialmente em todos os discos possíveis (fallback)
        $targetDisk = null;
        if ($disk->exists($path)) {
            $targetDisk = $disk;
        } elseif (\Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
            $targetDisk = \Illuminate\Support\Facades\Storage::disk('local');
        } elseif (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
            $targetDisk = \Illuminate\Support\Facades\Storage::disk('public');
        }

        if (!$targetDisk) {
            abort(404, 'Arquivo físico não encontrado no servidor ou nuvem.');
        }

        if (request()->query('preview') == 1) {
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $mimeType = match ($extension) {
                'pdf' => 'application/pdf',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'doc' => 'application/msword',
                'png' => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                default => 'application/octet-stream'
            };
            
            return response()->stream(function () use ($targetDisk, $path) {
                fpassthru($targetDisk->readStream($path));
            }, 200, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
            ]);
        }

        return $targetDisk->download($path);
    }

    /**
     * Exibe a linha do tempo e histórico de um processo.
     */
    public function timeline($id)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403, 'Acesso não autorizado.');
        }

        $submission = \App\Models\DocumentSubmission::with(['student.user', 'academicTitle'])
            ->findOrFail($id);

        // Apenas o orientador (admin) ou o próprio aluno dono do documento podem visualizar a linha do tempo
        if ($user->role !== 'admin' && ($submission->student && $submission->student->user_id !== $user->id)) {
            abort(403, 'Você não tem permissão para visualizar a linha do tempo deste processo.');
        }

        // Se o histórico não existir ou estiver vazio, inicializa com o status atual
        $history = $submission->metadata['history'] ?? [];
        if (empty($history)) {
            $history[] = [
                'status' => $submission->status,
                'old_status' => null,
                'timestamp' => $submission->created_at ? $submission->created_at->format('d/m/Y - H:i:s') : now()->format('d/m/Y - H:i:s'),
                'actor' => $submission->student && $submission->student->user ? $submission->student->user->name : 'Estudante',
                'comments' => $submission->ai_executive_summary,
            ];
        }

        return view('document_timeline', [
            'submission' => $submission,
            'history' => $history,
        ]);
    }
}
