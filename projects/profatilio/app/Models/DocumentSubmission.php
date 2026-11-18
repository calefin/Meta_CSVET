<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentSubmission extends Model
{
    protected $fillable = [
        'student_id',
        'academic_title_id',
        'process_id',
        'title',
        'document_type',
        'google_drive_file_id',
        'google_drive_returned_file_id',
        'file_size_kb',
        'status',
        'student_abnt_statement',
        'ai_abnt_feedback_json',
        'metadata',
        'ai_executive_summary',
        'student_notes',
        'abnt_verified_at',
    ];

    protected $casts = [
        'student_abnt_statement' => 'boolean',
        'ai_abnt_feedback_json' => 'array',
        'metadata' => 'array',
        'abnt_verified_at' => 'datetime',
    ];

    protected $attributes = [
        'file_size_kb' => 0,
    ];

    public $is_draft_save = false;

    /**
     * Tipos oferecidos quando o aluno marca "Este envio é apenas para assinatura".
     * O professor só assina e devolve — não há correção de conteúdo. Os dois de TCC
     * existem desde 2026-11-03: antes o aluno não achava Projeto de TCC / TCC final
     * nessa lista filtrada e acabava enviando como "Outro" (que entra no fluxo de
     * revisão, não de assinatura).
     */
    public const SIGNATURE_ONLY_TYPES = [
        'Projeto de TCC (Assinatura)' => 'Projeto de TCC (somente assinatura)',
        'TCC Final (Assinatura)' => 'TCC Final (somente assinatura)',
        'Termo de Aceite de Orientação' => 'Termo de Aceite de Orientação',
        'Plano de Estágio' => 'Plano de Estágio',
        'Termo de Aceite de IC' => 'Termo de Aceite de IC',
    ];

    /**
     * Tipos que o aluno só envia para ficar registrado (o professor confere e
     * homologa, sem ciclo de correção nem assinatura) — ex.: notas/avaliações do estágio.
     */
    public const RECORD_ONLY_TYPES = [
        'Avaliações de Estágio' => 'Avaliações / Notas de Estágio',
        'Trabalho de Congresso' => 'Certificado de Apresentação em Evento',
    ];

    /**
     * Botões "O que você precisa?" no topo do formulário de envio. Cada um filtra a
     * lista de "Tipo de Documento" (ver typesForPurpose()).
     */
    public const SUBMISSION_PURPOSES = [
        'assinar' => 'Para assinar',
        'corrigir' => 'Para corrigir',
        'registro' => 'Só para registro',
    ];

    /**
     * Filtra a lista completa de tipos ($all = valor => rótulo) pela finalidade.
     * Finalidade vazia devolve tudo. "corrigir" = tudo que não é de assinatura nem
     * de registro.
     */
    public static function typesForPurpose(?string $purpose, array $all): array
    {
        return match ($purpose) {
            'assinar' => self::SIGNATURE_ONLY_TYPES
                + array_intersect_key($all, ['Documento Solicitado' => true]),
            'registro' => self::RECORD_ONLY_TYPES,
            'corrigir' => array_diff_key($all, self::SIGNATURE_ONLY_TYPES, self::RECORD_ONLY_TYPES, ['Documento Solicitado' => true]),
            default => $all,
        };
    }

    public static function purposeForType(?string $type): ?string
    {
        if (blank($type)) {
            return null;
        }
        if (isset(self::SIGNATURE_ONLY_TYPES[$type]) || $type === 'Documento Solicitado') {
            return 'assinar';
        }
        if (isset(self::RECORD_ONLY_TYPES[$type])) {
            return 'registro';
        }

        return 'corrigir';
    }

    /**
     * TCC Completo: o professor já assinou o documento (fica registrado como versão
     * final assinada em metadata['signed_final_file']), mas ainda pede correções. O
     * processo NÃO conclui — a bola volta para o aluno e as devoluções continuam
     * normalmente; a cópia assinada nunca é sobrescrita pelas devoluções seguintes.
     */
    public const SIGNED_NEEDS_CORRECTIONS_STATUS = 'Documento Assinado - Aguardando Correções do Aluno';

    /**
     * Última versão assinada registrada pelo status acima (caminho no disco), ou null.
     */
    public function signedFinalFile(): ?string
    {
        return $this->metadata['signed_final_file'] ?? null;
    }

    /**
     * O estado do FileUpload de devolução traz um arquivo novo (upload temporário ou
     * caminho diferente da devolução atual)? O campo vem pré-preenchido com a
     * devolução anterior, então "não vazio" não basta.
     */
    public static function returnedFileStateHasNewFile($state, ?self $record): bool
    {
        $current = $record?->getAttributes()['google_drive_returned_file_id'] ?? null;

        foreach ((array) $state as $item) {
            if (blank($item)) {
                continue;
            }
            if (!is_string($item) || $item !== $current) {
                return true;
            }
        }

        return false;
    }

    public function isConcluded(): bool
    {
        $concludedStatuses = [
            'approved',
            'returned_signed',
            'failed',
            'Assinatura Realizada e Auditada',
            'Cancelado'
        ];

        return in_array($this->status, $concludedStatuses) || str_starts_with($this->status, 'Processo Concluído');
    }

    protected static function booted()
    {
        static::creating(function ($submission) {
            // Set student_abnt_statement if role is student
            if (auth()->check() && auth()->user()->role === 'student') {
                $submission->student_abnt_statement = true;
            }

            $submission->process_id = static::generateUniqueProcessId($submission->document_type);
            
            // Set initial status based on document type if not already set.
            // Quando é o próprio aluno criando o registro (via "Enviar Novo Documento"),
            // ele já preencheu o conteúdo obrigatório da etapa nesse mesmo formulário —
            // então o processo já nasce como pendência de correção do professor, não
            // como "aguardando o aluno" (isso ficava preso para sempre, já que só a
            // aresta de update() faz essa transição, e aqui é um create()). O status
            // "Aguardando X do Aluno" continua correto para o registro-placeholder vazio
            // criado pelo comando de backfill ou por outro fluxo automático/professor.
            $isStudentSubmittingContent = auth()->check() && auth()->user()->role === 'student';

            $submission->status = $submission->status ?: match ($submission->document_type) {
                'Assinatura Avulsa' => 'Assinatura Realizada e Auditada',
                'Concepção de TCC' => $isStudentSubmittingContent ? 'Aguardando Definição de Tema' : 'Aguardando Tema do Aluno',
                'Objetivos de TCC' => $isStudentSubmittingContent ? 'Aguardando Correção dos Objetivos' : 'Aguardando Objetivos do Aluno',
                'Introdução de TCC' => $isStudentSubmittingContent ? 'Aguardando Correção da Introdução' : 'Aguardando Introdução do Aluno',
                'Materiais e Métodos de TCC' => $isStudentSubmittingContent ? 'Aguardando Correção de Materiais e Métodos' : 'Aguardando Materiais e Métodos do Aluno',
                'Resultados de TCC' => $isStudentSubmittingContent ? 'Aguardando Correção dos Resultados' : 'Aguardando Resultados do Aluno',
                'Discussão de TCC' => $isStudentSubmittingContent ? 'Aguardando Correção da Discussão' : 'Aguardando Discussão do Aluno',
                'Conclusão de TCC' => $isStudentSubmittingContent ? 'Aguardando Correção da Conclusão' : 'Aguardando Conclusão do Aluno',
                'Documento Solicitado' => 'Aguardando Envio do Aluno',
                default => 'Aguardando Triagem',
            };

            // Forensic checks for Flow 8: Termo de Aceite de IC
            if ($submission->document_type === 'Termo de Aceite de IC') {
                $student = \App\Models\Student::find($submission->student_id);
                $studentName = $student && $student->user ? $student->user->name : 'N/A';
                $rgm = $student ? $student->registration : 'N/A';
                
                $ip = request()->ip() ?? '127.0.0.1';
                $timestamp = now()->format('d/m/Y - H:i:s');
                $userAgent = request()->userAgent() ?? 'Unknown';
                
                // Concatenar termos + dados do aluno + IP + Timestamp + UserAgent
                $termsText = "Declaro que aceito as cláusulas de relatórios periódicos, entrega de resultados (FAPESP), dedicação/exclusividade de dados e boas práticas científicas.";
                $dataToHash = $termsText . '|' . $studentName . '|' . $rgm . '|' . $ip . '|' . $timestamp . '|' . $userAgent;
                $hash = hash('sha256', $dataToHash);

                $metadata = $submission->metadata ?? [];
                $metadata['forensic_ip'] = $ip;
                $metadata['forensic_timestamp'] = $timestamp;
                $metadata['forensic_user_agent'] = $userAgent;
                $metadata['forensic_hash'] = $hash;
                $submission->metadata = $metadata;
            }

            // Renomeação automática do arquivo enviado pelo aluno (suporta arquivos únicos ou múltiplos)
            $files = $submission->google_drive_file_id;
            $isMultiple = is_array($files);
            
            // Se for string que representa JSON array, decodifica
            if (is_string($files) && (str_starts_with($files, '[') && str_ends_with($files, ']'))) {
                $files = json_decode($files, true);
                $isMultiple = true;
            }

            if (!empty($files)) {
                try {
                    if ($submission->document_type === 'Assinatura Avulsa') {
                        $simpleStudentName = 'Prof. Atilio';
                        $customName = $submission->metadata['custom_name'] ?? null;
                        $fileStage = $customName ?: 'Assinatura Avulsa';
                    } else {
                        $student = \App\Models\Student::find($submission->student_id);
                        $studentName = $student && $student->user ? $student->user->name : 'Aluno';
                        
                        // Simplificar o nome para Primeiro e Último Nome
                        $parts = explode(' ', trim($studentName));
                        $simpleStudentName = count($parts) > 1 ? $parts[0] . ' ' . end($parts) : $studentName;

                        // Determinar a etapa ou tipo do documento
                        $fileStage = $submission->document_type;
                        if ($submission->document_type === 'TCC') {
                            $fileStage = isset($submission->metadata['tcc_stage']) 
                                ? "TCC " . $submission->metadata['tcc_stage'] 
                                : 'TCC';
                        }
                    }

                    $diskName = in_array(config('filesystems.default'), ['google', 'onedrive']) ? config('filesystems.default') : 'public';
                    $disk = \Illuminate\Support\Facades\Storage::disk($diskName);

                    if ($isMultiple) {
                        $newFiles = [];
                        $totalSize = 0;
                        foreach ($files as $index => $oldPath) {
                            if (str_contains($oldPath, 'http')) {
                                $newFiles[] = $oldPath;
                                continue;
                            }
                            
                            $extension = pathinfo($oldPath, PATHINFO_EXTENSION);
                            if (!$extension) {
                                $extension = str_contains($oldPath, '.') ? last(explode('.', $oldPath)) : 'pdf';
                            }
                            $extension = str_replace([']', '"', '\''], '', $extension);

                            $suffix = " - " . ($index + 1);
                            $newFileName = "{$submission->process_id} - {$simpleStudentName} - {$fileStage}{$suffix}.{$extension}";
                            $newFileName = str_replace(['/', '\\', '?', '%', '*', ':', '|', '"', '<', '>'], '_', $newFileName);

                            $newPath = "submissions/{$newFileName}";
                            
                            if ($disk->exists($oldPath)) {
                                try {
                                    $totalSize += $disk->size($oldPath);
                                } catch (\Exception $e) {}
                                $disk->move($oldPath, $newPath);
                                $newFiles[] = $newPath;
                            } else {
                                $newFiles[] = $oldPath;
                                try {
                                    $totalSize += $disk->size($oldPath);
                                } catch (\Exception $e) {}
                            }
                        }
                        $submission->google_drive_file_id = $newFiles;
                        $submission->file_size_kb = round($totalSize / 1024, 2);
                    } else {
                        $oldPath = $files;
                        if (!str_contains($oldPath, 'http')) {
                            $extension = pathinfo($oldPath, PATHINFO_EXTENSION);
                            if (!$extension) {
                                $extension = str_contains($oldPath, '.') ? last(explode('.', $oldPath)) : 'pdf';
                            }

                            $newFileName = "{$submission->process_id} - {$simpleStudentName} - {$fileStage}.{$extension}";
                            $newFileName = str_replace(['/', '\\', '?', '%', '*', ':', '|', '"', '<', '>'], '_', $newFileName);

                            $newPath = "submissions/{$newFileName}";
                            
                            if ($disk->exists($oldPath)) {
                                try {
                                    $submission->file_size_kb = round($disk->size($oldPath) / 1024, 2);
                                } catch (\Exception $e) {}
                                $disk->move($oldPath, $newPath);
                                $submission->google_drive_file_id = $newPath;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Erro ao renomear arquivo da submissão {$submission->process_id}: " . $e->getMessage());
                }
            }

            // Geração Automática do Termo de Aceite de Orientação caso nenhum arquivo seja fornecido
            if ($submission->document_type === 'Termo de Aceite de Orientação' && empty($submission->google_drive_file_id)) {
                try {
                    $student = \App\Models\Student::with('user')->find($submission->student_id);
                    
                    $html = view('termo_aceite_orientacao', [
                        'submission' => $submission,
                        'student' => $student,
                        'metadata' => $submission->metadata ?? [],
                    ])->render();

                    if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
                        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
                        $pdfContent = $pdf->output();

                        $filename = "termo_original_{$submission->process_id}.pdf";
                        $diskName = in_array(config('filesystems.default'), ['google', 'onedrive']) ? config('filesystems.default') : 'public';
                        \Illuminate\Support\Facades\Storage::disk($diskName)->put("submissions/{$filename}", $pdfContent);

                        $submission->google_drive_file_id = "submissions/{$filename}";
                        \Illuminate\Support\Facades\Log::info("Termo de Aceite de Orientação gerado em PDF com sucesso: submissions/{$filename}");
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Erro ao gerar PDF do Termo de Aceite de Orientação: " . $e->getMessage());
                }
            }

            // Inicializa histórico de auditoria na criação
            $history = [];
            $history[] = [
                'status' => $submission->status,
                'old_status' => null,
                'timestamp' => now()->timezone('America/Sao_Paulo')->format('d/m/Y - H:i:s'),
                'actor' => auth()->user()?->name ?? 'Estudante',
                'comments' => $submission->student_notes,
            ];
            $metadata = $submission->metadata ?? [];
            $metadata['history'] = $history;
            $submission->metadata = $metadata;
        });

        static::created(function ($submission) {
            // Invalida cache de listagem do Google Drive
            try {
                \App\Filesystems\CachedGoogleDriveAdapter::clearAllGoogleDriveCache();
            } catch (\Exception $e) {
                // Previne falha do fluxo se o cache falhar
            }

            // Na primeira "Concepção de TCC" do aluno, explica o pipeline completo
            // de produção do TCC (Concepção -> Objetivos -> 5 etapas de redação em
            // paralelo -> TCC final), para que ele já entenda como funciona.
            if ($submission->document_type === 'Concepção de TCC') {
                try {
                    self::sendTccPipelineExplanationIfFirst($submission);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Erro ao enviar explicação do pipeline de TCC: " . $e->getMessage());
                }
            }

            // Write to system log
            $student = $submission->student;
            $studentName = $student && $student->user ? $student->user->name : 'N/A';
            $institution = $student ? $student->institution : 'N/A';
            $campus = $student ? $student->campus : 'N/A';
            $email = $student && $student->user ? $student->user->email : 'N/A';
            $timestamp = now()->format('d/m/Y - H:i:s');

            // Primeiro e último nome
            $simpleName = 'N/A';
            if ($studentName !== 'N/A') {
                $parts = explode(' ', trim($studentName));
                $simpleName = count($parts) > 1 ? $parts[0] . ' ' . end($parts) : $studentName;
            }

            // E-mail mascarado
            $maskedEmail = '***';
            if ($email !== 'N/A') {
                $emailParts = explode('@', $email);
                if (count($emailParts) === 2) {
                    $namePart = $emailParts[0];
                    $domainPart = $emailParts[1];
                    $len = strlen($namePart);
                    if ($len > 3) {
                        $maskedEmail = substr($namePart, 0, 3) . str_repeat('*', $len - 3) . '@' . $domainPart;
                    } else {
                        $maskedEmail = substr($namePart, 0, 1) . str_repeat('*', $len - 1) . '@' . $domainPart;
                    }
                }
            }

            // Log de Abertura no formato padrão atualizado
            $logHeader = "Processo [{$submission->process_id}] iniciado em [{$timestamp}]. Aluno: [{$simpleName}] | Instituição: [{$institution}] - Campus [{$campus}] | Contato: [{$maskedEmail}].";
            \Illuminate\Support\Facades\Log::info($logHeader);

            // Geração Automática do Recibo em PDF no Fluxo 8 (Termo de Aceite de IC)
            if ($submission->document_type === 'Termo de Aceite de IC') {
                try {
                    $hash = $submission->metadata['forensic_hash'] ?? '';
                    $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode(url("/documento/validar/{$hash}"));

                    // Renderiza o HTML do recibo
                    $html = view('document_receipt', [
                        'submission' => $submission,
                        'student' => $student,
                        'user' => $student->user,
                        'hash' => $hash,
                        'qrCodeUrl' => $qrCodeUrl,
                        'metadata' => $submission->metadata ?? [],
                    ])->render();

                    // Gera o PDF físico usando Dompdf
                    if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
                        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
                        $pdfContent = $pdf->output();

                        $filename = "recibo_{$submission->process_id}.pdf";
                        $diskName = in_array(config('filesystems.default'), ['google', 'onedrive']) ? config('filesystems.default') : 'public';
                        \Illuminate\Support\Facades\Storage::disk($diskName)->put("receipts/{$filename}", $pdfContent);

                        $metadata = $submission->metadata ?? [];
                        $metadata['receipt_pdf_path'] = "receipts/{$filename}";
                        $submission->metadata = $metadata;
                        $submission->saveQuietly();

                        \Illuminate\Support\Facades\Log::info("Recibo PDF gerado e salvo com sucesso no storage em: receipts/{$filename}");
                    } else {
                        \Illuminate\Support\Facades\Log::warning("Dompdf não está registrado no container do Laravel. O recibo PDF não pôde ser gerado fisicamente no hook created.");
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Erro ao gerar recibo PDF para o processo {$submission->process_id}: " . $e->getMessage());
                }
            }

            // Fluxo "Documento Solicitado": quem inicia é o professor, então o aluno
            // é avisado de que um documento foi pedido a ele (não o e-mail genérico de
            // "processo recebido", que não faz sentido aqui).
            if ($submission->document_type === 'Documento Solicitado' && $student && $student->user) {
                try {
                    $studentEmail = $student->user->email;
                    $instructionsHtml = $submission->ai_executive_summary ?: null;
                    $instructionsText = $instructionsHtml ? trim(strip_tags($instructionsHtml)) : null;

                    $bodyText = "Olá!\n\nO Prof. Atilio solicitou o envio do seguinte documento: \"{$submission->title}\"."
                        . ($instructionsText ? "\n\nInstruções do orientador:\n{$instructionsText}" : '')
                        . "\n\nAcesse o portal para enviar o arquivo solicitado.";

                    \Illuminate\Support\Facades\Mail::to($studentEmail)->queue(
                        new \App\Mail\AppNotificationMail(
                            title: "Documento Solicitado: {$submission->title}",
                            messageContent: $bodyText,
                            actionUrl: url('/portal'),
                            actionText: 'Acessar Portal',
                            parecerHtml: $instructionsHtml,
                            subject: "Documento Solicitado pelo Orientador: {$submission->title}"
                        )
                    );

                    $telegramChatId = $student->telegram_chat_id ?? ($student->user ? $student->user->telegram_chat_id : null);
                    if (!empty($telegramChatId)) {
                        try {
                            $telegramService = app(\App\Services\TelegramService::class);
                            $tgMessage = "<b>📄 Documento Solicitado pelo Orientador</b>\n\nOlá!\nO Prof. Atilio solicitou o envio do documento: <b>" . e($submission->title) . "</b>.";
                            if ($instructionsText) {
                                $tgMessage .= "\n\n💬 <b>Instruções:</b>\n" . e($instructionsText);
                            }
                            $tgMessage .= "\n\n<a href=\"" . url('/portal') . "\">👉 Acessar Portal e Enviar</a>";
                            $telegramService->sendMessage($telegramChatId, $tgMessage);
                        } catch (\Exception $tgEx) {
                            \Illuminate\Support\Facades\Log::error("Erro ao enviar Telegram de documento solicitado: " . $tgEx->getMessage());
                        }
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Erro ao enviar notificação de documento solicitado: " . $e->getMessage());
                }
            }

            // Trigger email and Telegram notifications
            try {
                if (in_array($submission->status, [
                    'Aguardando Objetivos do Aluno',
                    'Aguardando Tema do Aluno',
                    'Aguardando Envio do Aluno',
                    'Aguardando Introdução do Aluno',
                    'Aguardando Materiais e Métodos do Aluno',
                    'Aguardando Resultados do Aluno',
                    'Aguardando Discussão do Aluno',
                    'Aguardando Conclusão do Aluno',
                ])) {
                    return;
                }
                if ($student && $student->user) {
                    $studentEmail = $student->user->email;
                    $professorEmail = 'contato@profatilio.com.br';

                    \Illuminate\Support\Facades\Mail::to($studentEmail)->queue(
                        new \App\Mail\AppNotificationMail(
                            title: "Processo {$submission->process_id} Iniciado",
                            messageContent: "Olá!\n\nSeu envio foi recebido com sucesso no sistema. Veja abaixo os detalhes do processo:\n\n{$logHeader}",
                            actionUrl: url('/portal'),
                            actionText: 'Acessar Painel',
                            subject: "Processo {$submission->process_id} Iniciado"
                        )
                    );

                    \Illuminate\Support\Facades\Mail::to($professorEmail)->queue(
                        new \App\Mail\AppNotificationMail(
                            title: "Novo Processo {$submission->process_id} Iniciado",
                            messageContent: "Olá Prof. Atilio,\n\nUm novo processo de submissão foi iniciado por um de seus orientandos. Detalhes:\n\n{$logHeader}",
                            actionUrl: url('/admin'),
                            actionText: 'Acessar Painel de Controle',
                            subject: "Novo Processo {$submission->process_id} de Orientando"
                        )
                    );

                    // Send Telegram notification if linked
                    $telegramChatId = $student->telegram_chat_id ?? ($student->user ? $student->user->telegram_chat_id : null);
                    if (!empty($telegramChatId)) {
                        try {
                            $telegramService = app(\App\Services\TelegramService::class);
                            $tgMessage = "<b>🎓 Novo Processo Iniciado</b>\n\nOlá!\nSeu envio foi recebido com sucesso no sistema.\n\n<b>Detalhes:</b>\n" . $logHeader . "\n\n<a href=\"" . url('/portal') . "\">👉 Acessar Painel</a>";
                            $telegramService->sendMessage($telegramChatId, $tgMessage);
                        } catch (\Exception $tgEx) {
                            \Illuminate\Support\Facades\Log::error("Erro ao enviar Telegram de processo iniciado: " . $tgEx->getMessage());
                        }
                    }

                    // Send Telegram notification to Professor if linked
                    $professor = \App\Models\User::where('role', 'admin')->first();
                    if ($professor && !empty($professor->telegram_chat_id)) {
                        try {
                            $telegramService = app(\App\Services\TelegramService::class);
                                $tgMessage = "<b>🎓 Novo Processo de Orientando</b>\n\nOlá Prof. Atilio!\nUm novo processo de submissão foi iniciado por um de seus orientandos.\n\n<b>Detalhes:</b>\n" . $logHeader . "\n\n<a href=\"" . url('/admin') . "\">👉 Acessar Painel de Controle</a>";
                            $telegramService->sendMessage($professor->telegram_chat_id, $tgMessage);
                        } catch (\Exception $tgEx) {
                            \Illuminate\Support\Facades\Log::error("Erro ao enviar Telegram de processo iniciado para o professor: " . $tgEx->getMessage());
                        }
                    }
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Erro ao enviar e-mail de notificação de processo: " . $e->getMessage());
            }
        });

        static::updating(function ($submission) {
            // Se o aluno está atualizando (não rascunho) e o status atual é de ajuste do aluno,
            // transiciona automaticamente para o status de revisão do professor correspondente.
            if (auth()->check() && auth()->user()->role === 'student' && !$submission->is_draft_save && $submission->canStudentEdit()) {
                $submission->status = match ($submission->document_type) {
                    'TCC' => 'Aguardando Correção do Professor',
                    'Iniciação Científica' => 'Projeto em Análise Inicial pelo Orientador',
                    'Relatório', 'Artigo Científico', 'Relato de Caso', 'Trabalho de Congresso', 'Resumo para Evento', 'Banner para Apresentação', 'Relatório de Estágio', 'Relatório de Aula', 'Outro' => 'Aguardando Revisão do Professor',
                    'Protocolo CEUA/CEP' => 'Aguardando Revisão do Professor',
                    'Plano de Estágio', 'Projeto de TCC (Assinatura)', 'TCC Final (Assinatura)' => 'Aguardando Assinatura do Professor',
                    'Termo de Aceite de Orientação' => 'Aguardando Assinatura do Professor',
                    'Avaliações de Estágio' => 'Avaliações de Estágio Registradas e Recebidas',
                    'Relatório de Necropsia' => 'Relatório de Necropsia Recebido - Aguardando Análise do Professor',
                    'Termo de Aceite de IC' => 'Termos Aceitos e Auditados - Aguardando Assinatura do Orientador',
                    'Concepção de TCC' => 'Aguardando Definição de Tema',
                    'Objetivos de TCC' => 'Aguardando Correção dos Objetivos',
                    'Introdução de TCC' => 'Aguardando Correção da Introdução',
                    'Materiais e Métodos de TCC' => 'Aguardando Correção de Materiais e Métodos',
                    'Resultados de TCC' => 'Aguardando Correção dos Resultados',
                    'Discussão de TCC' => 'Aguardando Correção da Discussão',
                    'Conclusão de TCC' => 'Aguardando Correção da Conclusão',
                    'TCC Completo (Envio Único)' => 'Aguardando Correção do TCC Completo',
                    'Documento Solicitado' => 'Aguardando Assinatura do Professor',
                    default => 'pending',
                };
            }

            // Regenerar ID de processo se o tipo do documento for alterado pelo professor
            if ($submission->isDirty('document_type')) {
                $year = $submission->created_at ? $submission->created_at->format('Y') : now()->format('Y');
                $submission->process_id = static::generateUniqueProcessId($submission->document_type, $year);
                
                // Se não estava em triagem, redefine o status para o status inicial do novo fluxo
                if ($submission->getOriginal('status') !== 'Aguardando Triagem') {
                    $submission->status = match ($submission->document_type) {
                        'TCC' => 'Aguardando Correção do Professor',
                        'Iniciação Científica' => 'Projeto em Análise Inicial pelo Orientador',
                        'Relatório', 'Artigo Científico', 'Relato de Caso', 'Trabalho de Congresso', 'Resumo para Evento', 'Banner para Apresentação', 'Relatório de Estágio', 'Relatório de Aula' => 'Aguardando Revisão do Professor',
                        'Protocolo CEUA/CEP' => 'Aguardando Revisão do Professor',
                        'Plano de Estágio', 'Projeto de TCC (Assinatura)', 'TCC Final (Assinatura)' => 'Aguardando Assinatura do Professor',
                        'Termo de Aceite de Orientação' => 'Aguardando Assinatura do Professor',
                        'Avaliações de Estágio' => 'Avaliações de Estágio Registradas e Recebidas',
                        'Relatório de Necropsia' => 'Relatório de Necropsia Recebido - Aguardando Análise do Professor',
                        'Termo de Aceite de IC' => 'Termos Aceitos e Auditados - Aguardando Assinatura do Orientador',
                        'Concepção de TCC' => 'Aguardando Tema do Aluno',
                        'Objetivos de TCC' => 'Aguardando Objetivos do Aluno',
                        'Introdução de TCC' => 'Aguardando Introdução do Aluno',
                        'Materiais e Métodos de TCC' => 'Aguardando Materiais e Métodos do Aluno',
                        'Resultados de TCC' => 'Aguardando Resultados do Aluno',
                        'Discussão de TCC' => 'Aguardando Discussão do Aluno',
                        'Conclusão de TCC' => 'Aguardando Conclusão do Aluno',
                        'TCC Completo (Envio Único)' => 'Aguardando Correção do TCC Completo',
                        'Documento Solicitado' => 'Aguardando Envio do Aluno',
                        default => 'pending',
                    };
                }
            }

            // Intercepta Habilitado para fazer a transição para o status inicial correto
            if ($submission->isDirty('status') && $submission->status === 'Habilitado') {
                // Etapas do pipeline de TCC: se o aluno já anexou o arquivo OU já
                // preencheu o conteúdo da etapa nos campos de texto do metadata (cada
                // tipo usa chaves próprias — Objetivos usa tcc_general_objective/
                // tcc_specific_objectives, Concepção usa tcc_conception_*, as demais
                // etapas de redação usam tcc_stage_text — ver TCC_STAGE_CONTENT_KEYS),
                // ele mesmo montou a submissão pronta, em vez de esperar o professor
                // liberar uma etapa vazia — a bola já está com o professor, não com
                // o aluno. Ver TCC_STAGE_STATUS_PAIRS.
                $stagePair = self::TCC_STAGE_STATUS_PAIRS[$submission->document_type] ?? null;
                $stageAlreadyFilled = filled($submission->google_drive_file_id);
                if (!$stageAlreadyFilled) {
                    $metadata = $submission->metadata ?? [];
                    foreach (self::TCC_STAGE_CONTENT_KEYS[$submission->document_type] ?? [] as $key) {
                        if (filled($metadata[$key] ?? null)) {
                            $stageAlreadyFilled = true;
                            break;
                        }
                    }
                }
                if ($stagePair && $stageAlreadyFilled) {
                    $submission->status = $stagePair[1];
                } else {
                    $submission->status = match ($submission->document_type) {
                        'TCC' => 'Aguardando Correção do Professor',
                        'Iniciação Científica' => 'Projeto em Análise Inicial pelo Orientador',
                        'Relatório', 'Artigo Científico', 'Relato de Caso', 'Trabalho de Congresso', 'Resumo para Evento', 'Banner para Apresentação', 'Relatório de Estágio', 'Relatório de Aula', 'Outro' => 'Aguardando Revisão do Professor',
                        'Protocolo CEUA/CEP' => 'Aguardando Revisão do Professor',
                        'Plano de Estágio', 'Projeto de TCC (Assinatura)', 'TCC Final (Assinatura)' => 'Aguardando Assinatura do Professor',
                        'Termo de Aceite de Orientação' => 'Aguardando Assinatura do Professor',
                        'Avaliações de Estágio' => 'Avaliações de Estágio Registradas e Recebidas',
                        'Relatório de Necropsia' => 'Relatório de Necropsia Recebido - Aguardando Análise do Professor',
                        'Termo de Aceite de IC' => 'Termos Aceitos e Auditados - Aguardando Assinatura do Orientador',
                        'Concepção de TCC' => 'Aguardando Tema do Aluno',
                        'Objetivos de TCC' => 'Aguardando Objetivos do Aluno',
                        'Introdução de TCC' => 'Aguardando Introdução do Aluno',
                        'Materiais e Métodos de TCC' => 'Aguardando Materiais e Métodos do Aluno',
                        'Resultados de TCC' => 'Aguardando Resultados do Aluno',
                        'Discussão de TCC' => 'Aguardando Discussão do Aluno',
                        'Conclusão de TCC' => 'Aguardando Conclusão do Aluno',
                        'TCC Completo (Envio Único)' => 'Aguardando Correção do TCC Completo',
                        'Documento Solicitado' => 'Aguardando Envio do Aluno',
                        default => 'pending',
                    };
                }
                $submission->ai_executive_summary = $stagePair && $stageAlreadyFilled
                    ? "<p>O processo foi habilitado pelo orientador. Como você já enviou o material, ele está em análise — aguarde o parecer.</p>"
                    : "<p>O processo foi habilitado pelo orientador e iniciou a fase de análise.</p>";
            }

            // Intercepta Cancelado para gravar o motivo formatado no parecer
            if ($submission->isDirty('status') && $submission->status === 'Cancelado') {
                $reason = $submission->metadata['cancellation_reason'] ?? 'Não informado';
                $submission->ai_executive_summary = "<p><strong>Processo Cancelado pelo Orientador.</strong><br>Motivo: {$reason}<br><em>Sugere-se a abertura de um novo processo com as correções sugeridas.</em></p>";
            }
            // Invalida cache de listagem do Google Drive
            try {
                \App\Filesystems\CachedGoogleDriveAdapter::clearAllGoogleDriveCache();
            } catch (\Exception $e) {
                // Previne falha do fluxo se o cache falhar
            }

            // Se o arquivo original foi alterado ou se o tipo de documento mudou, calcula o tamanho do arquivo/renomeia os arquivos
            if (($submission->isDirty('google_drive_file_id') && $submission->google_drive_file_id !== $submission->getOriginal('google_drive_file_id')) || $submission->isDirty('document_type')) {
                try {
                    $files = $submission->google_drive_file_id;
                    $isMultiple = is_array($files);
                    if (is_string($files) && (str_starts_with($files, '[') && str_ends_with($files, ']'))) {
                        $files = json_decode($files, true);
                        $isMultiple = true;
                    }

                    if (!empty($files)) {
                        $student = \App\Models\Student::find($submission->student_id);
                        $studentName = $student && $student->user ? $student->user->name : 'Aluno';
                        $parts = explode(' ', trim($studentName));
                        $simpleStudentName = count($parts) > 1 ? $parts[0] . ' ' . end($parts) : $studentName;

                        $fileStage = $submission->document_type;
                        if ($submission->document_type === 'TCC') {
                            $fileStage = isset($submission->metadata['tcc_stage']) 
                                ? "TCC " . $submission->metadata['tcc_stage'] 
                                : 'TCC';
                        }

                        $diskName = in_array(config('filesystems.default'), ['google', 'onedrive']) ? config('filesystems.default') : 'public';
                        $disk = \Illuminate\Support\Facades\Storage::disk($diskName);
                        $totalSize = 0;

                        if ($isMultiple) {
                            $newFiles = [];
                            foreach ($files as $index => $oldPath) {
                                if (str_contains($oldPath, 'http')) {
                                    $newFiles[] = $oldPath;
                                    continue;
                                }
                                
                                if (str_contains($oldPath, "submissions/{$submission->process_id}")) {
                                    $newFiles[] = $oldPath;
                                    if ($disk->exists($oldPath)) {
                                        try {
                                            $totalSize += $disk->size($oldPath);
                                        } catch (\Exception $e) {}
                                    }
                                    continue;
                                }

                                $extension = pathinfo($oldPath, PATHINFO_EXTENSION);
                                if (!$extension) {
                                    $extension = str_contains($oldPath, '.') ? last(explode('.', $oldPath)) : 'pdf';
                                }
                                $extension = str_replace([']', '"', '\''], '', $extension);

                                $suffix = " - " . ($index + 1);
                                $newFileName = "{$submission->process_id} - {$simpleStudentName} - {$fileStage}{$suffix}.{$extension}";
                                $newFileName = str_replace(['/', '\\', '?', '%', '*', ':', '|', '"', '<', '>'], '_', $newFileName);

                                $newPath = "submissions/{$newFileName}";

                                if ($disk->exists($oldPath)) {
                                    try {
                                        $totalSize += $disk->size($oldPath);
                                    } catch (\Exception $e) {}
                                    $disk->move($oldPath, $newPath);
                                    $newFiles[] = $newPath;
                                } else {
                                    $newFiles[] = $oldPath;
                                    try {
                                        $totalSize += $disk->size($oldPath);
                                    } catch (\Exception $e) {}
                                }
                            }
                            $submission->google_drive_file_id = $newFiles;
                            $submission->file_size_kb = round($totalSize / 1024, 2);
                        } else {
                            $oldPath = $files;
                            if (!str_contains($oldPath, 'http')) {
                                if (str_contains($oldPath, "submissions/{$submission->process_id}")) {
                                    if ($disk->exists($oldPath)) {
                                        try {
                                            $submission->file_size_kb = round($disk->size($oldPath) / 1024, 2);
                                        } catch (\Exception $e) {}
                                    }
                                } else {
                                    $extension = pathinfo($oldPath, PATHINFO_EXTENSION);
                                    if (!$extension) {
                                        $extension = str_contains($oldPath, '.') ? last(explode('.', $oldPath)) : 'pdf';
                                    }

                                    $newFileName = "{$submission->process_id} - {$simpleStudentName} - {$fileStage}.{$extension}";
                                    $newFileName = str_replace(['/', '\\', '?', '%', '*', ':', '|', '"', '<', '>'], '_', $newFileName);

                                    $newPath = "submissions/{$newFileName}";

                                    if ($disk->exists($oldPath)) {
                                        try {
                                            $submission->file_size_kb = round($disk->size($oldPath) / 1024, 2);
                                        } catch (\Exception $e) {}
                                        $disk->move($oldPath, $newPath);
                                        $submission->google_drive_file_id = $newPath;
                                    }
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Erro ao atualizar tamanho do arquivo na submissão {$submission->process_id}: " . $e->getMessage());
                }
            }

            // Renomeação automática e Auditoria Criptográfica de Assinatura Local (A3/A1) (suporta arquivos únicos ou múltiplos)
            $returnedFiles = $submission->google_drive_returned_file_id;
            $isReturnedMultiple = is_array($returnedFiles);
            if (is_string($returnedFiles) && (str_starts_with($returnedFiles, '[') && str_ends_with($returnedFiles, ']'))) {
                $returnedFiles = json_decode($returnedFiles, true);
                $isReturnedMultiple = true;
            }

            $isSignedNeedsCorrections = $submission->status === self::SIGNED_NEEDS_CORRECTIONS_STATUS;
            $returnedFileChanged = $submission->isDirty('google_drive_returned_file_id')
                && $submission->google_drive_returned_file_id !== $submission->getOriginal('google_drive_returned_file_id')
                && !empty($returnedFiles);

            // "Documento assinado, mas precisa de correções" só faz sentido com um PDF
            // assinado novo anexado agora — senão o registro da versão assinada ficaria
            // apontando para uma devolução antiga (não assinada).
            if ($isSignedNeedsCorrections && $submission->isDirty('status') && !$returnedFileChanged) {
                throw new \InvalidArgumentException('Para usar o status "' . self::SIGNED_NEEDS_CORRECTIONS_STATUS . '", anexe o PDF assinado no campo de devolução ao aluno.');
            }

            if ($returnedFileChanged) {
                $hasLocalFiles = false;
                if ($isReturnedMultiple) {
                    foreach ($returnedFiles as $p) {
                        if (!str_contains($p, 'http')) {
                            $hasLocalFiles = true;
                            break;
                        }
                    }
                } else {
                    $hasLocalFiles = !str_contains($returnedFiles, 'http');
                }

                if ($hasLocalFiles) {
                    try {
                        $student = $submission->student;
                        $studentName = $student && $student->user ? $student->user->name : 'Aluno';
                        
                        $parts = explode(' ', trim($studentName));
                        $simpleStudentName = count($parts) > 1 ? $parts[0] . ' ' . end($parts) : $studentName;

                        $diskName = in_array(config('filesystems.default'), ['google', 'onedrive']) ? config('filesystems.default') : 'public';
                        $disk = \Illuminate\Support\Facades\Storage::disk($diskName);

                        if ($isReturnedMultiple) {
                            $newReturnedFiles = [];
                            foreach ($returnedFiles as $index => $oldPath) {
                                if (str_contains($oldPath, 'http')) {
                                    $newReturnedFiles[] = $oldPath;
                                    continue;
                                }
                                $extension = pathinfo($oldPath, PATHINFO_EXTENSION);
                                if (!$extension) {
                                    $extension = 'pdf';
                                }
                                $suffix = " - " . ($index + 1);
                                $newFileName = "{$submission->process_id} - {$simpleStudentName} - Assinado_Corrigido{$suffix}.{$extension}";
                                $newFileName = str_replace(['/', '\\', '?', '%', '*', ':', '|', '"', '<', '>'], '_', $newFileName);

                                $newPath = "returns/{$newFileName}";
                                if ($disk->exists($oldPath)) {
                                    $disk->move($oldPath, $newPath);
                                    $newReturnedFiles[] = $newPath;
                                } else {
                                    $newReturnedFiles[] = $oldPath;
                                }
                            }
                            $submission->google_drive_returned_file_id = $newReturnedFiles;
                        } else {
                            $oldPath = $returnedFiles;
                            $extension = pathinfo($oldPath, PATHINFO_EXTENSION);
                            if (!$extension) {
                                $extension = 'pdf';
                            }

                            // A versão assinada "com correções pendentes" ganha nome próprio (com
                            // data/hora) para nunca colidir com as devoluções seguintes, que
                            // continuam usando o nome "Assinado_Corrigido".
                            $newFileName = $isSignedNeedsCorrections
                                ? "{$submission->process_id} - {$simpleStudentName} - Assinado_Versao_Final_" . now()->format('Ymd-His') . ".{$extension}"
                                : "{$submission->process_id} - {$simpleStudentName} - Assinado_Corrigido.{$extension}";
                            $newFileName = str_replace(['/', '\\', '?', '%', '*', ':', '|', '"', '<', '>'], '_', $newFileName);

                            $newPath = "returns/{$newFileName}";

                            // A validação da assinatura acontece ANTES de renomear no Drive. Antes
                            // ela rodava depois do move(): se falhasse, o save era abortado mas o
                            // arquivo já tinha sido renomeado — no próximo salvamento o formulário
                            // ainda carregava o caminho antigo (inexistente), que ia para o banco,
                            // e o aluno recebia 404 ao abrir a devolução.
                            $isConcludedStatus = str_starts_with($submission->status, 'Processo Concluído')
                                || in_array($submission->status, ['approved', 'returned_signed', 'Assinatura Realizada e Auditada'])
                                || $isSignedNeedsCorrections;

                            $content = null;
                            $isSigned = false;
                            if ($isConcludedStatus && $disk->exists($oldPath)) {
                                $content = $disk->get($oldPath);
                                // Verifica se contém dicionário de assinatura digital
                                $isSigned = (str_contains($content, '/Type/Sig') || str_contains($content, '/Type /Sig') || str_contains($content, '/ByteRange'));
                                if (!$isSigned && self::statusRequiresSignature($submission->status)) {
                                    throw new \InvalidArgumentException("O arquivo retornado não contém uma assinatura digital ICP-Brasil válida. Assine o PDF localmente antes de enviá-lo.");
                                }
                            }

                            if ($disk->exists($oldPath)) {
                                $disk->move($oldPath, $newPath);
                                $submission->google_drive_returned_file_id = $newPath;
                            }

                            // Registro permanente da versão assinada (sobrevive às devoluções
                            // seguintes, que trocam google_drive_returned_file_id).
                            if ($isSignedNeedsCorrections) {
                                $signedPath = $disk->exists($newPath) ? $newPath : $oldPath;
                                $metadata = $submission->metadata ?? [];
                                $metadata['signed_final_file'] = $signedPath;
                                $metadata['signed_final_at'] = now()->format('d/m/Y H:i');
                                $metadata['signed_final_history'] = array_merge($metadata['signed_final_history'] ?? [], [[
                                    'path' => $signedPath,
                                    'at' => $metadata['signed_final_at'],
                                    'hash' => $content !== null ? hash('sha256', $content) : null,
                                ]]);
                                $submission->metadata = $metadata;
                            }

                            // Registra a auditoria forense quando o PDF devolvido está assinado
                            if ($isConcludedStatus && $isSigned) {
                                // Calcula hash SHA-256
                                $hash = hash('sha256', $content);
                                
                                // Registra metadados forenses
                                $metadata = $submission->metadata ?? [];
                                $metadata['forensic_hash'] = $hash;
                                $metadata['forensic_ip'] = request()->ip() ?? '127.0.0.1';
                                $metadata['forensic_timestamp'] = now()->timezone('America/Sao_Paulo')->format('d/m/Y - H:i:s');
                                $metadata['forensic_user_agent'] = request()->userAgent() ?? 'Unknown';
                                $extractedSigner = self::extractSignerNameFromPdfContent($content);
                                $metadata['signer_name'] = $extractedSigner ?: 'Dr. Atilio Sersun Calefi';
                                $metadata['original_filename'] = basename($oldPath);
                                $metadata['document_description'] = $submission->document_type;
                                
                                // Gera o recibo PDF (Termo de Homologação) com QR Code
                                $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode(url("/documento/validar/{$hash}"));
                                
                                $html = view('local_signature_certificate', [
                                    'submission' => $submission,
                                    'hash' => $hash,
                                    'qrCodeUrl' => $qrCodeUrl,
                                    'metadata' => $metadata,
                                ])->render();
                                
                                if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
                                    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
                                    $pdfContent = $pdf->output();
                                    
                                    $receiptFilename = "recibo_assinatura_{$submission->process_id}.pdf";
                                    $receiptPath = "receipts/{$receiptFilename}";
                                    
                                    $disk->put($receiptPath, $pdfContent);
                                    $metadata['receipt_pdf_path'] = $receiptPath;
                                }
                                
                                $submission->metadata = $metadata;
                            }
                        }
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error("Erro na validação forense da submissão {$submission->process_id}: " . $e->getMessage());
                        throw new \Exception("Erro na validação da assinatura local: " . $e->getMessage());
                    }
                }
            }

            // O ID do processo e status inicial do novo fluxo são tratados no início do hook updating

            // Auto transition status when signed/returned file is present/uploaded and we are publishing
            $isConcludedStatus = str_starts_with($submission->status, 'Processo Concluído') || in_array($submission->status, ['approved', 'returned_signed', 'failed', 'Assinatura Realizada e Auditada', 'Cancelado']);
            if (!$isConcludedStatus && !$submission->isDirty('status') && $submission->google_drive_returned_file_id !== null && !$submission->is_draft_save) {
                $submission->status = match ($submission->document_type) {
                    'Termo de Aceite de IC' => 'Processo Concluído - Orientação de IC Homologada e Ativa',
                    'TCC', 'Plano de Estágio', 'Protocolo CEUA/CEP', 'Relatório de Estágio', 'Relatório de Aula', 'Projeto de TCC (Assinatura)', 'TCC Final (Assinatura)' => 'Processo Concluído - Documento Assinado Disponível',
                    'Termo de Aceite de Orientação' => 'Processo Concluído - Termo de Aceite Assinado',
                    'Iniciação Científica' => 'Processo Concluído - Projeto Arquivado',
                    default => $submission->status,
                };
            }

            if ($submission->isDirty('status')) {
                $oldStatus = $submission->getOriginal('status');
                $newStatus = $submission->status;
                $timestamp = now()->timezone('America/Sao_Paulo')->format('d/m/Y - H:i:s');

                // Auto-preenche a data de avaliação — nunca deve ser manual
                $submission->abnt_verified_at = now();
                
                \Illuminate\Support\Facades\Log::info("Processo {$submission->process_id} alterado de '{$oldStatus}' para '{$newStatus}' em {$timestamp}.");

                $isStudentActionStatus = str_contains($newStatus, 'Ajustes') || 
                                         str_contains($newStatus, 'Correção') || 
                                         str_contains($newStatus, 'needs_corrections') ||
                                         $newStatus === self::SIGNED_NEEDS_CORRECTIONS_STATUS;

                $isConcludedStatus = str_starts_with($newStatus, 'Processo Concluído') ||
                                     in_array($newStatus, ['approved', 'returned_signed', 'failed', 'Assinatura Realizada e Auditada', 'Cancelado']);

                if ($isStudentActionStatus || $isConcludedStatus) {
                    $submission->is_draft_save = false;
                }

                if (!$submission->is_draft_save) {
                    // Registrar no histórico de metadados
                    $metadata = $submission->metadata ?? [];
                    $history = $metadata['history'] ?? [];
                    $isStudentActor = auth()->check() && auth()->user()->role === 'student';
                    $history[] = [
                        'status' => $newStatus,
                        'old_status' => $oldStatus,
                        'timestamp' => $timestamp,
                        'actor' => auth()->user()?->name ?? ($isStudentActor ? 'Estudante' : 'Sistema'),
                        'comments' => $isStudentActor ? $submission->student_notes : $submission->ai_executive_summary,
                    ];
                    $metadata['history'] = $history;
                    $submission->metadata = $metadata;

                    // Se for Concepção de TCC e o status mudou para "Processo Concluído - Tema Definido"
                    if ($submission->document_type === 'Concepção de TCC' && $newStatus === 'Processo Concluído - Tema Definido') {
                        $finalTitle = !empty($submission->metadata['advisor_suggested_title']) 
                            ? $submission->metadata['advisor_suggested_title'] 
                            : $submission->title;

                        // 1. Garante a criação do Título Acadêmico correspondente de TCC
                        $normalizedTitle = \App\Models\AcademicTitle::normalizeTitle($finalTitle);
                        $academicTitle = \App\Models\AcademicTitle::where('student_id', $submission->student_id)
                            ->where('type', 'TCC')
                            ->where('title', $normalizedTitle)
                            ->first();

                        if (!$academicTitle) {
                            $academicTitle = new \App\Models\AcademicTitle();
                            $academicTitle->student_id = $submission->student_id;
                            $academicTitle->type = 'TCC';
                            $academicTitle->title = $finalTitle;
                            $academicTitle->save();
                        }

                        // Atualiza a submissão de concepção com o ID do título acadêmico criado
                        $submission->academic_title_id = $academicTitle->id;
                        $submission->title = $finalTitle;

                        // 2. Garante a criação do processo de Objetivos de TCC
                        $exists = self::where('student_id', $submission->student_id)
                            ->where('academic_title_id', $academicTitle->id)
                            ->where('document_type', 'Objetivos de TCC')
                            ->exists();
                        
                        if (!$exists) {
                            $newSubmission = new self();
                            $newSubmission->student_id = $submission->student_id;
                            $newSubmission->academic_title_id = $academicTitle->id;
                            $newSubmission->title = $finalTitle;
                            $newSubmission->document_type = 'Objetivos de TCC';
                            $newSubmission->file_size_kb = 0;
                            $newSubmission->status = 'Aguardando Objetivos do Aluno';
                            $newSubmission->save();
                            
                            \Illuminate\Support\Facades\Log::info("Processo de Objetivos de TCC criado automaticamente para o estudante {$submission->student_id} após aprovação do tema de TCC.");
                        }
                    }

                    // Se for Iniciação Científica e o status mudou para "Processo Concluído - Projeto Arquivado"
                    if ($submission->document_type === 'Iniciação Científica' && $newStatus === 'Processo Concluído - Projeto Arquivado') {
                        $exists = self::where('student_id', $submission->student_id)
                            ->where('academic_title_id', $submission->academic_title_id)
                            ->where('document_type', 'Relatório')
                            ->where('status', 'Aguardando Relatório Parcial')
                            ->exists();
                        
                        if (!$exists) {
                            $newSubmission = new self();
                            $newSubmission->student_id = $submission->student_id;
                            $newSubmission->academic_title_id = $submission->academic_title_id;
                            $newSubmission->title = $submission->title;
                            $newSubmission->document_type = 'Relatório';
                            $newSubmission->file_size_kb = 0;
                            $newSubmission->status = 'Aguardando Relatório Parcial';
                            $newSubmission->save();
                            
                            \Illuminate\Support\Facades\Log::info("Processo de Relatório Parcial criado automaticamente para o estudante {$submission->student_id} após arquivamento do projeto de IC.");
                        }
                    }

                    // "TCC Completo (Envio Único)" é a opção para o aluno que já escreveu tudo
                    // em outro momento e não quer recortar/reenviar etapa por etapa. Ao aprovar
                    // esse envio único, replicamos a aprovação nas 5 submissões de etapa
                    // individuais (criando-as já aprovadas se ainda não existirem), para que o
                    // checklist do aluno (AcademicTitle::buildTccWritingSegments) e a trava que
                    // libera a versão final "TCC" continuem funcionando sem precisar saber sobre
                    // este atalho. Usa withoutEvents() para não disparar 5 notificações extras ao
                    // aluno — a notificação da aprovação do envio único (mais abaixo) já basta.
                    if ($submission->document_type === 'TCC Completo (Envio Único)' && $newStatus === 'Processo Concluído - TCC Completo Aprovado') {
                        $stageMap = [
                            'Introdução de TCC' => 'Processo Concluído - Introdução Aprovada',
                            'Materiais e Métodos de TCC' => 'Processo Concluído - Materiais e Métodos Aprovados',
                            'Resultados de TCC' => 'Processo Concluído - Resultados Aprovados',
                            'Discussão de TCC' => 'Processo Concluído - Discussão Aprovada',
                            'Conclusão de TCC' => 'Processo Concluído - Conclusão Aprovada',
                        ];

                        self::withoutEvents(function () use ($submission, $stageMap) {
                            foreach ($stageMap as $stageType => $approvedStatus) {
                                $stageSubmission = self::where('student_id', $submission->student_id)
                                    ->where('document_type', $stageType)
                                    ->latest('updated_at')
                                    ->first();

                                if ($stageSubmission && $stageSubmission->status !== 'Cancelado') {
                                    $stageSubmission->update([
                                        'status' => $approvedStatus,
                                        'google_drive_file_id' => $stageSubmission->google_drive_file_id ?: $submission->google_drive_file_id,
                                        'google_drive_returned_file_id' => $submission->google_drive_returned_file_id,
                                        'ai_executive_summary' => $submission->ai_executive_summary,
                                    ]);
                                } else {
                                    self::create([
                                        'student_id' => $submission->student_id,
                                        'academic_title_id' => $submission->academic_title_id,
                                        'title' => $submission->title,
                                        'document_type' => $stageType,
                                        'google_drive_file_id' => $submission->google_drive_file_id,
                                        'google_drive_returned_file_id' => $submission->google_drive_returned_file_id,
                                        'ai_executive_summary' => $submission->ai_executive_summary,
                                        'file_size_kb' => 0,
                                        'status' => $approvedStatus,
                                    ]);
                                }
                            }
                        });

                        \Illuminate\Support\Facades\Log::info("Etapas de redação do TCC marcadas como aprovadas automaticamente para o estudante {$submission->student_id} via envio único (TCC Completo).");
                    }

                    // Trigger dual notification email and Telegram on status change
                    try {
                        $student = $submission->student;
                        if ($student && $student->user) {
                            $studentEmail = $student->user->email;
                            $professorEmail = 'contato@profatilio.com.br';
                            $isHabilitated = ($oldStatus === 'Aguardando Triagem' && $newStatus !== 'Cancelado');
                            $isCancelled = ($newStatus === 'Cancelado');
                            $welcomeMessage = "";
                            if ($submission->document_type === 'Concepção de TCC' && $newStatus === 'Processo Concluído - Tema Definido') {
                                $welcomeMessage = "🎉 **Seu tema de TCC foi APROVADO pelo orientador!**\n\n" .
                                    "O tema oficial definido é: **\"" . \App\Models\AcademicTitle::normalizeTitle($submission->title) . "\"**.\n\n" .
                                    "**Orientações por onde começar:**\n" .
                                    "1. O seu próximo passo obrigatório é definir os **Objetivos** (Objetivo Geral e Objetivos Específicos).\n" .
                                    "2. Para ajudar a elaborar seus objetivos e entender as normas de escrita, consulte as orientações e o manual do aluno no site:\n" .
                                    "👉 " . url('/admin/manual-do-aluno') . " (ou a página pública de normas: " . url('/normas-abnt') . ").\n\n" .
                                    "Um novo processo de **'Objetivos de TCC'** foi aberto automaticamente para você. Acesse seu painel para preenchê-los e submetê-los para a correção do professor.\n\n";
                            } elseif ($isCancelled) {
                                $welcomeMessage = "Atenção: Seu processo foi CANCELADO pelo orientador. Por favor, verifique os detalhes abaixo para corrigir e submeter novamente.\n\n";
                            } elseif ($submission->document_type === 'Termo de Aceite de Orientação' && $newStatus === 'Processo Concluído - Termo de Aceite Assinado') {
                                $tgBotUser = ltrim((string) config('services.telegram.bot_username'), '@');
                                $welcomeMessage = "✅ **Seu Termo de Aceite de Orientação foi ASSINADO pelo orientador!**\n\n" .
                                    "O **termo assinado está em anexo** (link de download logo abaixo). Guarde uma cópia com você.\n\n" .
                                    "**Próximo passo:** siga normalmente com o seu processo pelo painel do aluno — as próximas etapas seguem o fluxo já iniciado.\n\n" .
                                    "Em caso de dúvidas, é só enviar uma mensagem pelo Telegram" . ($tgBotUser ? " (@{$tgBotUser})" : "") . ".\n\n";
                            } elseif ($newStatus === self::SIGNED_NEEDS_CORRECTIONS_STATUS) {
                                $welcomeMessage = "✍️ **Seu TCC foi ASSINADO pelo orientador, mas ainda precisa de correções.**\n\n" .
                                    "A versão assinada fica registrada no seu processo (link logo abaixo) — guarde uma cópia.\n\n" .
                                    "**Próximo passo:** faça as correções indicadas no parecer e reenvie o arquivo corrigido pelo mesmo processo, no painel do aluno.\n\n";
                            } elseif ($isHabilitated) {
                                $welcomeMessage = "Seu processo foi HABILITADO pelo orientador e está em fase de análise!\n\n";
                            }
                            $content = "{$welcomeMessage}Aviso: O status do processo {$submission->process_id} foi alterado para: {$newStatus}.\n\nData da alteração: {$timestamp}";
                            if (!empty($submission->google_drive_returned_file_id)) {
                                $downloadUrl = url("/documento/download/{$submission->id}/returned");
                                $content .= "\n\n📄 **Arquivo Retornado/Assinado Disponível para Download:**\n{$downloadUrl}";
                            }

                            $isStudentActor = auth()->check() && auth()->user()->role === 'student';
                            $parecerHtml  = $isStudentActor
                                ? (!empty($submission->student_notes) ? $submission->student_notes : null)
                                : (!empty($submission->ai_executive_summary) ? $submission->ai_executive_summary : null);
                            $parecerTexto = $parecerHtml ? trim(strip_tags($parecerHtml)) : null;

                            if ($parecerTexto) {
                                if ($isStudentActor) {
                                    $content .= "\n\n💬 **Observações/Comentários do Aluno:**\n{$parecerTexto}";
                                } else {
                                    $content .= "\n\n💬 **Parecer / Observações do Orientador:**\n{$parecerTexto}";
                                }
                            }

                            \Illuminate\Support\Facades\Mail::to($studentEmail)->send(
                                new \App\Mail\AppNotificationMail(
                                    title: "Atualização do Processo {$submission->process_id}",
                                    messageContent: "Olá!\n\nO status do seu processo foi atualizado. Veja os detalhes:\n\n{$content}",
                                    actionUrl: url('/portal'),
                                    actionText: 'Visualizar Processo',
                                    parecerHtml: $parecerHtml,
                                    subject: "Atualização do Processo {$submission->process_id}: {$newStatus}"
                                )
                            );

                            \Illuminate\Support\Facades\Mail::to($professorEmail)->send(
                                new \App\Mail\AppNotificationMail(
                                    title: "Alteração no Processo {$submission->process_id}",
                                    messageContent: "Olá Prof. Atilio,\n\nOcorreu uma atualização de status em um processo de orientação. Detalhes:\n\n{$content}",
                                    actionUrl: url('/admin'),
                                    actionText: 'Acessar Painel de Controle',
                                    parecerHtml: $parecerHtml,
                                    subject: "Alteração de Status no Processo {$submission->process_id}: {$newStatus}"
                                )
                            );

                            // Send Telegram notification if linked
                            $telegramChatId = $student->telegram_chat_id ?? ($student->user ? $student->user->telegram_chat_id : null);
                            if (!empty($telegramChatId)) {
                                try {
                                    $telegramService = app(\App\Services\TelegramService::class);
                                    $tgMessage = "<b>🔔 Atualização do Processo {$submission->process_id}</b>\n\nOlá!\nO status do seu processo foi atualizado.\n\n<b>Novo Status:</b> {$newStatus}\n<b>Data/Hora:</b> {$timestamp}\n";
                                    if ($submission->document_type === 'Concepção de TCC' && $newStatus === 'Processo Concluído - Tema Definido') {
                                         $tgMessage = "<b>🎉 Seu tema de TCC foi APROVADO!</b>\n\n" .
                                             "Tema oficial: <i>\"" . \App\Models\AcademicTitle::normalizeTitle($submission->title) . "\"</i>\n\n" .
                                             "<b>Próximo Passo:</b>\n" .
                                             "Definir os <b>Objetivos (Geral e Específicos)</b> no novo processo aberto em seu painel.\n\n" .
                                             "📖 <a href=\"" . url('/admin/manual-do-aluno') . "\">Ver Manual do Aluno</a>\n";
                                     } elseif ($submission->document_type === 'Termo de Aceite de Orientação' && $newStatus === 'Processo Concluído - Termo de Aceite Assinado') {
                                         $tgMessage = "<b>✅ Termo de Aceite de Orientação Assinado!</b>\n\n" .
                                             "Olá! O seu <b>termo de aceite foi assinado</b> pelo orientador e está <b>em anexo</b> para download.\n\n" .
                                             "<b>Próximo passo:</b> siga normalmente com o seu processo pelo painel do aluno.\n\n" .
                                             "Em caso de dúvidas, é só responder esta mensagem aqui no Telegram.\n";
                                         if (!empty($submission->google_drive_returned_file_id)) {
                                             $downloadUrl = url("/documento/download/{$submission->id}/returned");
                                             $tgMessage .= "\n📄 <b>Termo Assinado (em anexo):</b>\n<a href=\"{$downloadUrl}\">👉 Baixar Termo Assinado</a>\n";
                                         }
                                     } elseif ($newStatus === self::SIGNED_NEEDS_CORRECTIONS_STATUS) {
                                         $tgMessage = "<b>✍️ TCC assinado — ainda com correções</b>\n\n" .
                                             "Olá! O orientador <b>assinou</b> o seu TCC ({$submission->process_id}), mas ainda pede <b>correções</b>.\n\n" .
                                             "<b>Próximo passo:</b> corrija conforme o parecer e reenvie pelo mesmo processo no painel do aluno.\n";
                                         $downloadUrl = url("/documento/download/{$submission->id}/signed_final");
                                         $tgMessage .= "\n📄 <b>Versão Assinada:</b>\n<a href=\"{$downloadUrl}\">👉 Baixar Versão Assinada</a>\n";
                                     } elseif (!empty($submission->google_drive_returned_file_id)) {
                                         $downloadUrl = url("/documento/download/{$submission->id}/returned");
                                         $tgMessage .= "\n📄 <b>Arquivo Retornado/Assinado Disponível:</b>\n<a href=\"{$downloadUrl}\">👉 Baixar Arquivo Assinado</a>\n";
                                     }
                                     if ($parecerTexto) {
                                         if ($isStudentActor) {
                                             $tgMessage .= "\n💬 <b>Seus Comentários Enviados:</b>\n" . e($parecerTexto) . "\n";
                                         } else {
                                             $tgMessage .= "\n💬 <b>Parecer do Orientador:</b>\n" . e($parecerTexto) . "\n";
                                         }
                                     }
                                     $tgMessage .= "\n<a href=\"" . url('/portal') . "\">👉 Visualizar no Portal</a>";
                                    $telegramService->sendMessage($telegramChatId, $tgMessage);
                                } catch (\Exception $tgEx) {
                                    \Illuminate\Support\Facades\Log::error("Erro ao enviar Telegram de alteração de status: " . $tgEx->getMessage());
                                }
                            }

                            // Send Telegram notification to Professor if action was done by the student
                            if ($isStudentActor) {
                                $professor = \App\Models\User::where('role', 'admin')->first();
                                if ($professor && !empty($professor->telegram_chat_id)) {
                                    try {
                                        $telegramService = app(\App\Services\TelegramService::class);
                                        $tgMessage = "<b>🎓 Correções/Comentários Enviados pelo Aluno</b>\n\nOlá Prof. Atilio!\nO aluno realizou o envio de correções/comentários no processo <b>{$submission->process_id}</b>.\n\n<b>Novo Status:</b> {$newStatus}\n";
                                        if ($parecerTexto) {
                                            $tgMessage .= "\n💬 <b>Comentários do Aluno:</b>\n" . e($parecerTexto) . "\n";
                                        }
                                        $tgMessage .= "\n<a href=\"" . url('/admin') . "\">👉 Acessar Painel de Controle</a>";
                                        $telegramService->sendMessage($professor->telegram_chat_id, $tgMessage);
                                    } catch (\Exception $tgEx) {
                                        \Illuminate\Support\Facades\Log::error("Erro ao enviar Telegram de correção enviada para o professor: " . $tgEx->getMessage());
                                    }
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error("Erro ao enviar e-mail de notificação de mudança de status: " . $e->getMessage());
                    }

                    // Gatilho de Interrupção de Alertas:
                    // "Assim que a IA identificar que o aluno realizou a entrega do Relatório Parcial correspondente através do Fluxo 3, desative imediatamente os alertas regressivos (30, 15 e 7 dias) do ciclo trimestral vigente."
                    if ($submission->document_type === 'Relatório' && in_array($newStatus, ['Aguardando Revisão do Professor', 'Processo Concluído - Versão Final Definida'])) {
                        // Turn off IC alerts for the student!
                        if ($student) {
                            $activeIC = DocumentSubmission::where('student_id', $student->id)
                                ->where('document_type', 'Termo de Aceite de IC')
                                ->first();
                            if ($activeIC) {
                                $icMetadata = $activeIC->metadata ?? [];
                                $icMetadata['alerts_disabled'] = true;
                                $icMetadata['alerts_disabled_at'] = now()->format('d/m/Y - H:i:s');
                                $activeIC->update(['metadata' => $icMetadata]);
                                \Illuminate\Support\Facades\Log::info("Alertas regressivos de IC desativados para o estudante {$student->id} devido a entrega do Relatório.");
                            }
                        }
                    }
                }
            }
        });

        // Cumprimento automático de prazos manuais: qualquer submissão nova ou
        // atualizada do aluno para este projeto/aluno "resolve" as cobranças
        // pendentes em aberto (o professor ainda pode reabrir manualmente se
        // o envio não for suficiente).
        static::saved(function ($submission) {
            if (!$submission->student_id) {
                return;
            }

            try {
                $query = \App\Models\Deadline::where('student_id', $submission->student_id)
                    ->where('status', 'pending')
                    ->where('type', 'cobranca');

                if ($submission->academic_title_id) {
                    $query->where(function ($q) use ($submission) {
                        $q->whereNull('academic_title_id')
                            ->orWhere('academic_title_id', $submission->academic_title_id);
                    });
                }

                $query->update(['status' => 'fulfilled']);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Erro ao cumprir prazos automaticamente para a submissão {$submission->process_id}: " . $e->getMessage());
            }
        });
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function academicTitle()
    {
        return $this->belongsTo(AcademicTitle::class, 'academic_title_id');
    }

    /**
     * Tenta extrair o nome do signatário a partir de um conteúdo PDF assinado.
     * Retorna o nome do signatário ou null se não encontrar.
     */
    public static function extractSignerNameFromPdfContent(string $content): ?string
    {
        preg_match_all('/CN\s*=\s*([^,\\\\]+)/i', $content, $matches);
        $signers = [];
        if (!empty($matches[1])) {
            foreach ($matches[1] as $match) {
                $name = trim($match);
                // Limpa caracteres não imprimíveis comuns em DER ASN.1
                $name = preg_replace('/[^\x20-\x7E\xC0-\xFF]/u', '', $name);
                
                // Formatos de ICP-Brasil contêm o CPF associado: "NOME:CPF"
                if (str_contains($name, ':')) {
                    $parts = explode(':', $name);
                    $name = trim($parts[0]);
                }
                
                $isCA = false;
                $caKeywords = ['AC ', 'ICP-Brasil', 'Receita Federal', 'Serasa', 'Certisign', 'Autoridade Certificadora', 'ROOT', 'Presidencia', 'PROD', 'CA ', 'Servico', 'Secr', 'Gov', 'Ministerio', 'Serpro'];
                foreach ($caKeywords as $keyword) {
                    if (mb_strpos($name, $keyword) !== false) {
                        $isCA = true;
                        break;
                    }
                }
                
                if (!$isCA && !empty($name) && !in_array($name, $signers)) {
                    $signers[] = $name;
                }
            }
        }
        return empty($signers) ? null : implode(', ', $signers);
    }

    /**
     * Mutator para normalizar automaticamente o título ao salvar.
     */
    public function setTitleAttribute($value)
    {
        $this->attributes['title'] = \App\Models\AcademicTitle::normalizeTitle($value);
    }

    public function getGoogleDriveFileIdAttribute($value)
    {
        if ($value === null) {
            return null;
        }
        
        if (is_string($value) && str_starts_with($value, '[') && str_ends_with($value, ']')) {
            return json_decode($value, true) ?: $value;
        }
        
        return $value;
    }

    public function setGoogleDriveFileIdAttribute($value)
    {
        if (is_array($value)) {
            if ($this->document_type === 'Avaliações de Estágio') {
                $this->attributes['google_drive_file_id'] = json_encode(array_values($value));
            } else {
                $this->attributes['google_drive_file_id'] = reset($value) ?: null;
            }
        } else {
            $this->attributes['google_drive_file_id'] = $value;
        }
    }

    /**
     * Status de conclusão que significam "correção aprovada" (etapas de redação do TCC,
     * versão final de relatório/artigo etc.) devolvem um PDF corrigido, não assinado —
     * não faz sentido exigir assinatura ICP-Brasil neles.
     */
    public static function statusRequiresSignature(?string $status): bool
    {
        if (blank($status)) {
            return false;
        }

        $correctionOnly = ['Aprovad', 'Versão Final Definida', 'Tema Definido', 'Projeto Arquivado'];
        foreach ($correctionOnly as $fragment) {
            if (str_contains($status, $fragment)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Caminho com que o hook de salvamento renomeia a devolução de arquivo único.
     */
    public function expectedReturnedFilePath(string $extension = 'pdf'): string
    {
        $studentName = $this->student && $this->student->user ? $this->student->user->name : 'Aluno';
        $parts = explode(' ', trim($studentName));
        $simpleStudentName = count($parts) > 1 ? $parts[0] . ' ' . end($parts) : $studentName;

        $fileName = "{$this->process_id} - {$simpleStudentName} - Assinado_Corrigido.{$extension}";
        $fileName = str_replace(['/', '\\', '?', '%', '*', ':', '|', '"', '<', '>'], '_', $fileName);

        return "returns/{$fileName}";
    }

    public function getGoogleDriveReturnedFileIdAttribute($value)
    {
        if ($value === null) {
            return null;
        }
        
        if (is_string($value) && str_starts_with($value, '[') && str_ends_with($value, ']')) {
            return json_decode($value, true) ?: $value;
        }
        
        return $value;
    }

    public function setGoogleDriveReturnedFileIdAttribute($value)
    {
        if (is_array($value)) {
            $paths = array_values(array_filter($value, fn ($path) => filled($path)));
            // Empty array means no new file was uploaded — preserve the existing value
            // rather than wiping it. Only update when a real path is provided.
            if (!$paths) {
                return;
            }

            // O campo de devolução já vem pré-preenchido com o arquivo devolvido
            // anteriormente; quando o professor anexa um novo sem remover o antigo, o
            // estado chega como [antigo, novo]. Antes pegávamos o primeiro — o aluno
            // continuava vendo o arquivo velho e o novo (assinado) ficava órfão no Drive.
            // Prioriza o que é diferente do valor atual, e entre eles o último anexado.
            $current = $this->attributes['google_drive_returned_file_id'] ?? null;
            $newPaths = array_values(array_filter($paths, fn ($path) => $path !== $current));
            $this->attributes['google_drive_returned_file_id'] = $newPaths ? end($newPaths) : end($paths);
        } else {
            $this->attributes['google_drive_returned_file_id'] = $value;
        }
    }

    /**
     * Generates a unique, non-conflicting process_id for the given document type and year.
     * It looks at the maximum existing sequence number for that prefix/year and increments it.
     */
    public static function generateUniqueProcessId(string $documentType, ?string $year = null): string
    {
        $year = $year ?: now()->format('Y');
        
        $prefix = match ($documentType) {
            'Assinatura Avulsa' => 'AVULSO',
            'TCC' => 'TCC',
            'Iniciação Científica' => 'PROJ-IC',
            'Relatório' => 'REL-IC',
            'Relatório de Estágio' => 'REL-EST',
            'Relatório de Aula' => 'REL-AULA',
            'Artigo Científico' => 'ART',
            'Relato de Caso' => 'CASO',
            'Trabalho de Congresso' => 'CONG',
            'Resumo para Evento' => 'RESUMO',
            'Banner para Apresentação' => 'BANNER',
            'Plano de Estágio' => 'PLAN-EST',
            'Termo de Aceite de Orientação' => 'TERMO-ACEITE',
            'Avaliações de Estágio' => 'AVAL-EST',
            'Relatório de Necropsia' => 'NECRO',
            'Termo de Aceite de IC' => 'ACEITE-IC',
            'Protocolo CEUA/CEP' => 'CEUA-CEP',
            'Concepção de TCC' => 'CONC-TCC',
            'Objetivos de TCC' => 'OBJ-TCC',
            'Introdução de TCC' => 'INTRO-TCC',
            'Materiais e Métodos de TCC' => 'MM-TCC',
            'Resultados de TCC' => 'RESULT-TCC',
            'Discussão de TCC' => 'DISC-TCC',
            'Conclusão de TCC' => 'CONCL-TCC',
            'Documento Solicitado' => 'DOC-SOLIC',
            'Projeto de TCC (Assinatura)' => 'PROJ-TCC-ASS',
            'TCC Final (Assinatura)' => 'TCC-FINAL-ASS',
            'TCC Completo (Envio Único)' => 'TCC-COMP',
            default => 'DOC',
        };
        
        // Fetch all existing process_ids for this prefix and year
        $existingIds = static::where('process_id', 'like', "{$prefix}-{$year}-%")
            ->pluck('process_id')
            ->toArray();

        // Also fetch any previous_process_ids recorded in metadata from rerouted processes
        // to prevent reusing numbers that were once assigned in this series.
        $previousIds = static::whereNotNull('metadata')
            ->get(['metadata'])
            ->pluck('metadata')
            ->map(fn ($m) => is_array($m) ? ($m['previous_process_ids'] ?? []) : [])
            ->flatten()
            ->filter(fn ($id) => is_string($id) && str_starts_with($id, "{$prefix}-{$year}-"))
            ->toArray();

        $allIds = array_merge($existingIds, $previousIds);
            
        $maxSequence = 0;
        foreach ($allIds as $pid) {
            $parts = explode('-', $pid);
            $seqStr = end($parts);
            if (is_numeric($seqStr)) {
                $seqVal = (int) $seqStr;
                if ($seqVal > $maxSequence) {
                    $maxSequence = $seqVal;
                }
            }
        }
        
        $nextSequence = $maxSequence + 1;
        $sequence = str_pad($nextSequence, 4, '0', STR_PAD_LEFT);
        
        return "{$prefix}-{$year}-{$sequence}";
    }

    /**
     * As 7 etapas do pipeline de TCC que têm duas origens possíveis:
     * (a) o professor "libera" uma etapa vazia para o aluno preencher (bola
     *     com o aluno), ou
     * (b) o aluno já monta a submissão com o material pronto por conta
     *     própria (ex.: "Nova Submissão" no portal, com arquivo anexado) e
     *     ela entra em "Aguardando Triagem" como qualquer outro documento.
     * O interceptor de "Habilitado" (abaixo) usava sempre o status (a), então
     * um aluno que já mandou o material pronto (fluxo b) caía de volta em
     * "aguardando o aluno" ao ser habilitado — o processo nunca aparecia como
     * pendente para o professor avaliar. Corrigido em 2026-09-14: se a
     * submissão já tem arquivo anexado (google_drive_file_id preenchido),
     * usamos o status (b) — bola com o professor.
     */
    public const TCC_STAGE_STATUS_PAIRS = [
        'Concepção de TCC' => ['Aguardando Tema do Aluno', 'Aguardando Definição de Tema'],
        'Objetivos de TCC' => ['Aguardando Objetivos do Aluno', 'Aguardando Correção dos Objetivos'],
        'Introdução de TCC' => ['Aguardando Introdução do Aluno', 'Aguardando Correção da Introdução'],
        'Materiais e Métodos de TCC' => ['Aguardando Materiais e Métodos do Aluno', 'Aguardando Correção de Materiais e Métodos'],
        'Resultados de TCC' => ['Aguardando Resultados do Aluno', 'Aguardando Correção dos Resultados'],
        'Discussão de TCC' => ['Aguardando Discussão do Aluno', 'Aguardando Correção da Discussão'],
        'Conclusão de TCC' => ['Aguardando Conclusão do Aluno', 'Aguardando Correção da Conclusão'],
    ];

    /**
     * Chaves de metadata que indicam conteúdo real preenchido pelo aluno em cada
     * etapa do pipeline de TCC, usadas pelo interceptor de "Habilitado" acima e
     * pelo comando de correção retroativa (app:fix-stuck-awaiting-student-statuses)
     * — mantenha as duas em sincronia.
     */
    public const TCC_STAGE_CONTENT_KEYS = [
        'Concepção de TCC' => ['tcc_conception_area', 'tcc_conception_species', 'tcc_conception_type', 'tcc_conception_ideas'],
        'Objetivos de TCC' => ['tcc_general_objective', 'tcc_specific_objectives'],
        'Introdução de TCC' => ['tcc_stage_text'],
        'Materiais e Métodos de TCC' => ['tcc_stage_text'],
        'Resultados de TCC' => ['tcc_stage_text'],
        'Discussão de TCC' => ['tcc_stage_text'],
        'Conclusão de TCC' => ['tcc_stage_text'],
    ];

    /**
     * Status em que a submissão está "com a bola" com o aluno (ele precisa
     * agir para o processo avançar). Usado tanto para permitir edição
     * quanto para identificar orientandos parados/atrasados em prazos.
     */
    public const STUDENT_ACTIONABLE_STATUSES = [
        'needs_corrections',
        'Aguardando Ajustes do Aluno',
        'Aguardando Ajustes no Laudo pelo Aluno',
        'pending',
        'Aguardando Definição de Tema',
        'Aguardando Tema do Aluno',
        'Aguardando Objetivos do Aluno',
        'Aguardando Relatório Parcial',
        'Aguardando Envio do Aluno',
        'Aguardando Introdução do Aluno',
        'Aguardando Materiais e Métodos do Aluno',
        'Aguardando Resultados do Aluno',
        'Aguardando Discussão do Aluno',
        'Aguardando Conclusão do Aluno',
        self::SIGNED_NEEDS_CORRECTIONS_STATUS,
    ];

    /**
     * Determines if the student can edit/resubmit files/comments for this submission.
     */
    public function canStudentEdit(): bool
    {
        if ($this->isConcluded()) {
            return false;
        }

        return in_array($this->status, self::STUDENT_ACTIONABLE_STATUSES);
    }

    /**
     * Envia (uma única vez, na primeira "Concepção de TCC" do aluno) uma
     * mensagem explicando o pipeline completo de produção do TCC: Concepção e
     * Objetivos liberam em sequência, mas as 5 etapas de redação (Introdução a
     * Conclusão) liberam juntas e podem ser enviadas em qualquer ordem.
     */
    protected static function sendTccPipelineExplanationIfFirst(self $submission): void
    {
        $isFirst = self::where('student_id', $submission->student_id)
            ->where('document_type', 'Concepção de TCC')
            ->where('id', '!=', $submission->id)
            ->doesntExist();

        if (!$isFirst) {
            return;
        }

        $student = $submission->student;
        if (!$student || !$student->user) {
            return;
        }

        $studentName = $student->user->name;
        $portalUrl = url('/portal');

        $steps = [
            '1. Concepção de TCC' => 'Você propõe uma área/tema de pesquisa. O orientador aprova ou sugere ajustes no tema.',
            '2. Objetivos de TCC' => 'Assim que o tema é aprovado, este processo é aberto automaticamente. Você define o Objetivo Geral e os Objetivos Específicos para aprovação do orientador.',
            '3. Redação (Introdução, Materiais e Métodos, Resultados, Discussão, Conclusão)' => 'Com os objetivos aprovados, essas 5 etapas ficam liberadas ao mesmo tempo — envie na ordem que preferir, o orientador corrige cada uma independentemente. Resultados é opcional (dispensável em revisões de literatura).',
            '4. TCC (versão final)' => 'Assim que todas as etapas obrigatórias de redação estiverem aprovadas, você poderá solicitar a montagem da versão final para revisão e assinatura.',
        ];

        $htmlBody = "Olá, <strong>{$studentName}</strong>!<br><br>"
            . "Você acabou de iniciar a produção do seu TCC pelo sistema. Para você já entender como tudo funciona, veja a ordem geral de produção:<br><br>"
            . "<ul>";
        foreach ($steps as $stepTitle => $stepDesc) {
            $htmlBody .= "<li><strong>{$stepTitle}:</strong> {$stepDesc}</li>";
        }
        $htmlBody .= "</ul><br>"
            . "Você pode acompanhar em qual etapa está a qualquer momento no seu Dashboard, na seção \"Pipeline do TCC\", "
            . "ou pelo guia completo em <a href='{$portalUrl}'>{$portalUrl}</a> (menu Minha Conta &rarr; Como Funciona a Plataforma).<br><br>"
            . "Atenciosamente,<br><strong>Prof. Dr. Atilio Sersun Calefi</strong>";

        \Illuminate\Support\Facades\Mail::to($student->user->email)->queue(
            new \App\Mail\AppNotificationMail(
                title: 'Como funciona a produção do seu TCC',
                messageContent: $htmlBody,
                actionUrl: $portalUrl,
                actionText: 'Acessar Painel',
                subject: 'Como funciona a produção do seu TCC neste sistema'
            )
        );

        $telegramChatId = $student->telegram_chat_id ?? $student->user->telegram_chat_id;
        if (!empty($telegramChatId)) {
            $tgMessage = "🎓 <b>Como funciona a produção do seu TCC</b>\n\n"
                . "Olá, {$studentName}! Veja a ordem geral de produção:\n\n"
                . "1️⃣ <b>Concepção de TCC</b> — tema proposto e aprovado pelo orientador.\n"
                . "2️⃣ <b>Objetivos de TCC</b> — abre sozinho após o tema aprovado; você define Objetivo Geral e Específicos.\n"
                . "3️⃣ <b>Redação</b> — com os objetivos aprovados, Introdução, Materiais e Métodos, Resultados, Discussão e Conclusão ficam liberados ao mesmo tempo; envie na ordem que preferir (Resultados é opcional).\n"
                . "4️⃣ <b>TCC (versão final)</b> — liberado quando todas as etapas obrigatórias de redação estiverem aprovadas.\n\n"
                . "Acompanhe o andamento a qualquer momento no seu Dashboard.\n"
                . "<a href=\"{$portalUrl}\">👉 Acessar Painel</a>";

            try {
                app(\App\Services\TelegramService::class)->sendMessage((string) $telegramChatId, $tgMessage);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Erro ao enviar Telegram de explicação do pipeline de TCC: " . $e->getMessage());
            }
        }
    }

    /**
     * Retorna os status aplicáveis a determinado tipo de documento.
     */
    public static function getStatusesForType(string $type): array
    {
        return match ($type) {
            'Concepção de TCC' => [
                'Aguardando Tema do Aluno' => 'Aguardando Tema do Aluno',
                'Aguardando Definição de Tema' => 'Aguardando Definição de Tema',
                'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                'Processo Concluído - Tema Definido' => 'Processo Concluído - Tema Definido',
            ],
            'Objetivos de TCC' => [
                'Aguardando Objetivos do Aluno' => 'Aguardando Objetivos do Aluno',
                'Aguardando Correção dos Objetivos' => 'Aguardando Correção dos Objetivos',
                'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                'Processo Concluído - Objetivos Aprovados' => 'Processo Concluído - Objetivos Aprovados',
            ],
            'Introdução de TCC' => [
                'Aguardando Introdução do Aluno' => 'Aguardando Introdução do Aluno',
                'Aguardando Correção da Introdução' => 'Aguardando Correção da Introdução',
                'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                'Processo Concluído - Introdução Aprovada' => 'Processo Concluído - Introdução Aprovada',
            ],
            'Materiais e Métodos de TCC' => [
                'Aguardando Materiais e Métodos do Aluno' => 'Aguardando Materiais e Métodos do Aluno',
                'Aguardando Correção de Materiais e Métodos' => 'Aguardando Correção de Materiais e Métodos',
                'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                'Processo Concluído - Materiais e Métodos Aprovados' => 'Processo Concluído - Materiais e Métodos Aprovados',
            ],
            'Resultados de TCC' => [
                'Aguardando Resultados do Aluno' => 'Aguardando Resultados do Aluno',
                'Aguardando Correção dos Resultados' => 'Aguardando Correção dos Resultados',
                'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                'Processo Concluído - Resultados Aprovados' => 'Processo Concluído - Resultados Aprovados',
            ],
            'Discussão de TCC' => [
                'Aguardando Discussão do Aluno' => 'Aguardando Discussão do Aluno',
                'Aguardando Correção da Discussão' => 'Aguardando Correção da Discussão',
                'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                'Processo Concluído - Discussão Aprovada' => 'Processo Concluído - Discussão Aprovada',
            ],
            'Conclusão de TCC' => [
                'Aguardando Conclusão do Aluno' => 'Aguardando Conclusão do Aluno',
                'Aguardando Correção da Conclusão' => 'Aguardando Correção da Conclusão',
                'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                'Processo Concluído - Conclusão Aprovada' => 'Processo Concluído - Conclusão Aprovada',
            ],
            'TCC' => [
                'Aguardando Correção do Professor' => 'Aguardando Correção do Professor',
                'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                'Em análise final para assinatura institucional' => 'Em análise final para assinatura institucional',
                'Processo Concluído - Documento Assinado Disponível' => 'Processo Concluído - Documento Assinado Disponível',
            ],
            'TCC Completo (Envio Único)' => [
                'Aguardando Correção do TCC Completo' => 'Aguardando Correção do TCC Completo',
                'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                self::SIGNED_NEEDS_CORRECTIONS_STATUS => 'Documento Assinado — mas precisa de correções',
                'Processo Concluído - TCC Completo Aprovado' => 'Processo Concluído - TCC Completo Aprovado',
            ],
            'Iniciação Científica' => [
                'Projeto em Análise Inicial pelo Orientador' => 'Projeto em Análise Inicial pelo Orientador',
                'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                'Processo Concluído - Projeto Arquivado' => 'Processo Concluído - Projeto Arquivado',
            ],
            'Relatório', 'Artigo Científico', 'Relato de Caso', 'Trabalho de Congresso', 'Resumo para Evento', 'Banner para Apresentação', 'Relatório de Estágio', 'Relatório de Aula', 'Outro' => [
                'Aguardando Revisão do Professor' => 'Aguardando Revisão do Professor',
                'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                'Aguardando Relatório Parcial' => 'Aguardando Relatório Parcial',
                'Processo Concluído - Versão Final Definida' => 'Processo Concluído - Versão Final Definida',
                'Processo Concluído - Documento Assinado Disponível' => 'Processo Concluído - Documento Assinado Disponível',
            ],
            'Plano de Estágio', 'Projeto de TCC (Assinatura)', 'TCC Final (Assinatura)' => [
                'Aguardando Assinatura do Professor' => 'Aguardando Assinatura do Professor',
                'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                'Processo Concluído - Documento Assinado Disponível' => 'Processo Concluído - Documento Assinado Disponível',
            ],
            'Termo de Aceite de Orientação' => [
                'Aguardando Assinatura do Professor' => 'Aguardando Assinatura do Professor',
                'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                'Processo Concluído - Termo de Aceite Assinado' => 'Processo Concluído - Termo de Aceite Assinado',
            ],
            'Avaliações de Estágio' => [
                'Avaliações de Estágio Registradas e Recebidas' => 'Avaliações de Estágio Registradas e Recebidas',
                'Processo Concluído - Estágio Homologado com Sucesso' => 'Processo Concluído - Estágio Homologado com Sucesso',
            ],
            'Relatório de Necropsia' => [
                'Relatório de Necropsia Recebido - Aguardando Análise do Professor' => 'Relatório de Necropsia Recebido - Aguardando Análise do Professor',
                'Aguardando Ajustes no Laudo pelo Aluno' => 'Aguardando Ajustes no Laudo pelo Aluno',
                'Processo Concluído - Laudo de Necropsia Homologado' => 'Processo Concluído - Laudo de Necropsia Homologado',
            ],
            'Termo de Aceite de IC' => [
                'Termos Aceitos e Auditados - Aguardando Assinatura do Orientador' => 'Termos Aceitos e Auditados - Aguardando Assinatura do Orientador',
                'Processo Concluído - Orientação de IC Homologada e Ativa' => 'Processo Concluído - Orientação de IC Homologada e Ativa',
            ],
            'Documento Solicitado' => [
                'Aguardando Envio do Aluno' => 'Aguardando Envio do Aluno',
                'Aguardando Assinatura do Professor' => 'Aguardando Assinatura do Professor',
                'Aguardando Ajustes do Aluno' => 'Aguardando Ajustes do Aluno',
                'Processo Concluído - Documento Assinado Disponível' => 'Processo Concluído - Documento Assinado Disponível',
            ],
            default => [
                'pending' => 'Pendente',
                'reviewing' => 'Em Análise',
                'approved' => 'Aprovado',
                'needs_corrections' => 'Devolvido com Correções',
                'returned_signed' => 'Devolvido Assinado',
            ],
        };
    }

    /**
     * Retorna o status padrão para determinado tipo de documento.
     */
    public static function getDefaultStatusForType(string $type, bool $isConcluded = false): string
    {
        $statuses = static::getStatusesForType($type);
        if ($isConcluded) {
            foreach ($statuses as $k => $v) {
                if (str_starts_with($k, 'Processo Concluído') || in_array($k, ['approved', 'returned_signed', 'Assinatura Realizada e Auditada'])) {
                    return $k;
                }
            }
        }

        return array_key_first($statuses) ?: 'pending';
    }

    /**
     * Readequa a rota do processo para o tipo correto, gerando novo process_id,
     * renomeando arquivos no storage sem conflito e registrando justificativa na auditoria.
     */
    public function readequarRota(string $novoTipo, ?string $novoStatus, string $justificativa, bool $notificarAluno = true): string
    {
        $oldProcessId = $this->process_id;
        $oldType = $this->document_type;
        $oldStatus = $this->status;

        $year = $this->created_at ? $this->created_at->format('Y') : now()->format('Y');
        $newProcessId = static::generateUniqueProcessId($novoTipo, $year);

        // Se novo status não foi explicitado, calcula o padrão condizente
        if (blank($novoStatus)) {
            $isConcluded = $this->isConcluded();
            $novoStatus = static::getDefaultStatusForType($novoTipo, $isConcluded);
        }

        // Renomear arquivos físicos no storage
        $diskName = in_array(config('filesystems.default'), ['google', 'onedrive']) ? config('filesystems.default') : 'public';
        $disk = \Illuminate\Support\Facades\Storage::disk($diskName);

        $student = $this->student;
        $studentName = $student && $student->user ? $student->user->name : 'Aluno';
        $parts = explode(' ', trim($studentName));
        $simpleStudentName = count($parts) > 1 ? $parts[0] . ' ' . end($parts) : $studentName;

        // 1. Arquivo original do aluno
        $files = $this->google_drive_file_id;
        if (!empty($files)) {
            if (is_array($files)) {
                $newFiles = [];
                foreach ($files as $index => $oldPath) {
                    if (str_contains($oldPath, 'http')) {
                        $newFiles[] = $oldPath;
                        continue;
                    }
                    $ext = pathinfo($oldPath, PATHINFO_EXTENSION) ?: 'pdf';
                    $suffix = ' - ' . ($index + 1);
                    $newFileName = "{$newProcessId} - {$simpleStudentName} - {$novoTipo}{$suffix}.{$ext}";
                    $newFileName = str_replace(['/', '\\', '?', '%', '*', ':', '|', '"', '<', '>'], '_', $newFileName);
                    $newPath = "submissions/{$newFileName}";

                    if ($disk->exists($oldPath)) {
                        $disk->move($oldPath, $newPath);
                        $newFiles[] = $newPath;
                    } else {
                        $newFiles[] = $oldPath;
                    }
                }
                $this->google_drive_file_id = $newFiles;
            } else {
                $oldPath = $files;
                if (!str_contains($oldPath, 'http')) {
                    $ext = pathinfo($oldPath, PATHINFO_EXTENSION) ?: 'pdf';
                    $newFileName = "{$newProcessId} - {$simpleStudentName} - {$novoTipo}.{$ext}";
                    $newFileName = str_replace(['/', '\\', '?', '%', '*', ':', '|', '"', '<', '>'], '_', $newFileName);
                    $newPath = "submissions/{$newFileName}";

                    if ($disk->exists($oldPath)) {
                        $disk->move($oldPath, $newPath);
                        $this->google_drive_file_id = $newPath;
                    }
                }
            }
        }

        // 2. Arquivo retornado / assinado pelo professor
        $returnedFiles = $this->google_drive_returned_file_id;
        if (!empty($returnedFiles)) {
            if (is_array($returnedFiles)) {
                $newReturned = [];
                foreach ($returnedFiles as $index => $oldReturnPath) {
                    if (str_contains($oldReturnPath, 'http')) {
                        $newReturned[] = $oldReturnPath;
                        continue;
                    }
                    $ext = pathinfo($oldReturnPath, PATHINFO_EXTENSION) ?: 'pdf';
                    $suffix = ' - ' . ($index + 1);
                    $newReturnFileName = "{$newProcessId} - {$simpleStudentName} - Assinado_Corrigido{$suffix}.{$ext}";
                    $newReturnFileName = str_replace(['/', '\\', '?', '%', '*', ':', '|', '"', '<', '>'], '_', $newReturnFileName);
                    $newReturnPath = "returns/{$newReturnFileName}";

                    if ($disk->exists($oldReturnPath)) {
                        $disk->move($oldReturnPath, $newReturnPath);
                        $newReturned[] = $newReturnPath;
                    } else {
                        $newReturned[] = $oldReturnPath;
                    }
                }
                $this->google_drive_returned_file_id = $newReturned;
            } else {
                $oldReturnPath = $returnedFiles;
                if (!str_contains($oldReturnPath, 'http')) {
                    $ext = pathinfo($oldReturnPath, PATHINFO_EXTENSION) ?: 'pdf';
                    $newReturnFileName = "{$newProcessId} - {$simpleStudentName} - Assinado_Corrigido.{$ext}";
                    $newReturnFileName = str_replace(['/', '\\', '?', '%', '*', ':', '|', '"', '<', '>'], '_', $newReturnFileName);
                    $newReturnPath = "returns/{$newReturnFileName}";

                    if ($disk->exists($oldReturnPath)) {
                        $disk->move($oldReturnPath, $newReturnPath);
                        $this->google_drive_returned_file_id = $newReturnPath;
                    }
                }
            }
        }

        // 3. Atualizar metadata, histórico de auditoria e parecer
        $metadata = $this->metadata ?? [];
        $metadata['previous_process_ids'] = array_merge($metadata['previous_process_ids'] ?? [], [$oldProcessId]);
        $metadata['readequacoes_rota'] = array_merge($metadata['readequacoes_rota'] ?? [], [
            [
                'timestamp' => now()->timezone('America/Sao_Paulo')->format('d/m/Y - H:i:s'),
                'actor' => auth()->user()?->name ?? 'Prof. Atilio',
                'from_type' => $oldType,
                'to_type' => $novoTipo,
                'from_process_id' => $oldProcessId,
                'to_process_id' => $newProcessId,
                'from_status' => $oldStatus,
                'to_status' => $novoStatus,
                'justificativa' => $justificativa,
            ]
        ]);

        $metadata['history'] = array_merge($metadata['history'] ?? [], [
            [
                'status' => $novoStatus,
                'old_status' => $oldStatus,
                'timestamp' => now()->timezone('America/Sao_Paulo')->format('d/m/Y - H:i:s'),
                'actor' => auth()->user()?->name ?? 'Prof. Atilio',
                'action' => 'Readequação de Rota',
                'comments' => "Rota alterada de [{$oldType}] ({$oldProcessId}) para [{$novoTipo}] ({$newProcessId}). Justificativa: {$justificativa}",
            ]
        ]);

        // Nota visual no parecer
        $readequacaoNote = "<div style=\"padding:10px 14px; margin-bottom:12px; border-left:4px solid #f59e0b; background-color:rgba(245,158,11,0.08); border-radius:6px;\">"
            . "<strong style=\"color:#d97706;\">🔄 Rota do Processo Readequada pelo Orientador</strong><br>"
            . "<strong>De:</strong> " . e($oldType) . " (ID anterior: <code>" . e($oldProcessId) . "</code>)<br>"
            . "<strong>Para:</strong> " . e($novoTipo) . " (Novo ID: <code>" . e($newProcessId) . "</code>)<br>"
            . "<strong>Justificativa do orientador:</strong> " . e($justificativa)
            . "</div>";

        $this->ai_executive_summary = $readequacaoNote . ($this->ai_executive_summary ?? '');

        // Atualizar atributos e salvar
        $this->process_id = $newProcessId;
        $this->document_type = $novoTipo;
        $this->status = $novoStatus;
        $this->metadata = $metadata;
        $this->saveQuietly();

        // Limpar cache do Drive
        try {
            \App\Filesystems\CachedGoogleDriveAdapter::clearAllGoogleDriveCache();
        } catch (\Exception $e) {}

        // Notificar aluno (e-mail + Telegram)
        if ($notificarAluno && $student && $student->user) {
            try {
                $email = $student->user->email;
                $msgText = "Olá!\n\nO Prof. Atilio readequou a rota do seu processo.\n\n"
                    . "• De: {$oldType} ({$oldProcessId})\n"
                    . "• Para: {$novoTipo} ({$newProcessId})\n"
                    . "• Novo Status: {$novoStatus}\n\n"
                    . "Justificativa do orientador:\n{$justificativa}\n\n"
                    . "Acesse o portal para acompanhar ou baixar seu documento assinado.";

                \Illuminate\Support\Facades\Mail::to($email)->queue(
                    new \App\Mail\AppNotificationMail(
                        title: "Rota do Processo Readequada: {$newProcessId}",
                        messageContent: $msgText,
                        actionUrl: url('/portal'),
                        actionText: 'Acessar Portal do Aluno',
                        subject: "Processo Readequado: {$newProcessId} ({$novoTipo})"
                    )
                );

                $telegramChatId = $student->telegram_chat_id ?? $student->user->telegram_chat_id;
                if (!empty($telegramChatId)) {
                    $telegramService = app(\App\Services\TelegramService::class);
                    $tgMessage = "<b>🔄 Rota do Processo Readequada</b>\n\n"
                        . "Olá!\nO orientador readequou seu processo para a rota correta:\n\n"
                        . "• <b>De:</b> " . e($oldType) . " (<code>" . e($oldProcessId) . "</code>)\n"
                        . "• <b>Para:</b> " . e($novoTipo) . " (<code>" . e($newProcessId) . "</code>)\n"
                        . "• <b>Novo Status:</b> " . e($novoStatus) . "\n\n"
                        . "💬 <b>Justificativa:</b>\n" . e($justificativa) . "\n\n"
                        . "<a href=\"" . url('/portal') . "\">👉 Acessar Portal do Aluno</a>";
                    $telegramService->sendMessage($telegramChatId, $tgMessage);
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Erro ao notificar aluno sobre readequação de rota: " . $e->getMessage());
            }
        }

        return $newProcessId;
    }
}
