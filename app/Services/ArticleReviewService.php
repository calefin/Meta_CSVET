<?php

namespace App\Services;

use App\Mail\AppNotificationMail;
use App\Models\ArticleReview;
use App\Models\ArticleReviewer;
use App\Models\ArticleReviewFile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fluxo sequencial de revisão de artigo/livro por coautores/revisores: cada
 * participante tem um link pessoal (e, opcionalmente, conta com senha),
 * preenche a ficha (filiação, ORCID, CRediT, aceites); só quem está na vez
 * baixa a versão atual e devolve o arquivo revisado; o processo passa ao
 * próximo e, no fim, volta ao professor. Tudo vai para o registro de
 * acompanhamento.
 */
class ArticleReviewService
{
    public const DISK = 'local';

    public const PROFESSOR_EMAIL = 'contato@profatilio.com.br';

    public const MAX_FILE_KB = 30720;

    public const MAX_SUPPLEMENTARY_KB = 20480;

    public const ALLOWED_EXTENSIONS = ['docx', 'odt'];

    public const SUPPLEMENTARY_EXTENSIONS = [
        'pdf', 'docx', 'xlsx', 'pptx', 'odt', 'ods', 'odp', 'csv', 'txt', 'png', 'jpg', 'jpeg', 'tif', 'tiff',
    ];

    public static function disclaimer(): string
    {
        return 'A composição e a ordem de revisão deste processo podem mudar durante o andamento, por determinação do '
            . 'Prof. Atilio: pessoas que hoje não aparecem na lista podem ser incluídas e a ordem de revisão pode ser '
            . 'alterada. A ordem de revisão não corresponde necessariamente à ordem de autoria.';
    }

    public static function participationStatement(ArticleReview $review, ArticleReviewer $reviewer): string
    {
        return "Eu, {$reviewer->name}" . ($reviewer->email ? " ({$reviewer->email})" : '') . ', declaro que li e estou ciente do conteúdo do '
            . "{$review->workNoun()} \"{$review->title}\", que participei da sua revisão, que o arquivo e os comentários enviados nesta etapa "
            . 'são de minha autoria/revisão, e autorizo o registro do meu nome, e-mail, data/hora, endereço IP e da impressão digital '
            . '(SHA-256) do arquivo como comprovação desta participação.';
    }

    /**
     * Valida um .docx/.odt sem executar nem extrair nada: confere a assinatura
     * ZIP, a estrutura mínima esperada e recusa documentos com macro (VBA).
     *
     * @return array{0: bool, 1: ?string}
     */
    public static function validateDocument(string $content, string $extension): array
    {
        $extension = strtolower($extension);

        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return [false, 'Envie o arquivo em formato .docx (Word) ou .odt.'];
        }

        if (strlen($content) < 100 || !str_starts_with($content, "PK\x03\x04")) {
            return [false, 'O arquivo não parece ser um documento do Word válido (.docx).'];
        }

        [$ok, $error, $names, $contentTypes] = self::inspectZip($content);
        if (!$ok) {
            return [false, $error];
        }
        if ($names === null) {
            return [true, null];
        }

        if ($extension === 'docx' && (!in_array('word/document.xml', $names, true) || $contentTypes === '')) {
            return [false, 'O arquivo não parece ser um documento do Word válido (.docx).'];
        }

        if ($extension === 'odt' && !in_array('content.xml', $names, true)) {
            return [false, 'O arquivo não parece ser um documento .odt válido.'];
        }

        return [true, null];
    }

    /**
     * Documento complementar: só formatos comuns, conferindo a assinatura do
     * arquivo (não só a extensão) e recusando macros.
     *
     * @return array{0: bool, 1: ?string}
     */
    public static function validateSupplementary(string $content, string $extension): array
    {
        $extension = strtolower($extension);

        if (!in_array($extension, self::SUPPLEMENTARY_EXTENSIONS, true)) {
            return [false, 'Formato não aceito. Use PDF, Word, Excel, PowerPoint, LibreOffice, CSV, TXT ou imagem (PNG/JPG/TIF).'];
        }

        if (strlen($content) === 0) {
            return [false, 'Arquivo vazio.'];
        }

        $valid = match ($extension) {
            'pdf' => str_starts_with($content, '%PDF'),
            'png' => str_starts_with($content, "\x89PNG"),
            'jpg', 'jpeg' => str_starts_with($content, "\xFF\xD8\xFF"),
            'tif', 'tiff' => str_starts_with($content, "II*\x00") || str_starts_with($content, "MM\x00*"),
            'csv', 'txt' => !str_contains(substr($content, 0, 65536), "\x00"),
            default => str_starts_with($content, "PK\x03\x04"),
        };

        if (!$valid) {
            return [false, 'O conteúdo do arquivo não corresponde à extensão .' . $extension . '.'];
        }

        if (str_starts_with($content, "PK\x03\x04")) {
            [$ok, $error] = self::inspectZip($content);
            if (!$ok) {
                return [false, $error];
            }
        }

        return [true, null];
    }

    /**
     * @return array{0: bool, 1: ?string, 2: ?array, 3: string}
     */
    protected static function inspectZip(string $content): array
    {
        if (!class_exists(\ZipArchive::class)) {
            return [true, null, null, ''];
        }

        $tmp = tempnam(sys_get_temp_dir(), 'arv');
        file_put_contents($tmp, $content);

        try {
            $zip = new \ZipArchive();
            if ($zip->open($tmp, \ZipArchive::RDONLY) !== true) {
                return [false, 'Não foi possível abrir o documento. Salve-o novamente e tente outra vez.', null, ''];
            }

            if ($zip->numFiles > 5000) {
                $zip->close();
                return [false, 'Documento com estrutura inválida.', null, ''];
            }

            $names = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $names[] = strtolower((string) $zip->getNameIndex($i));
            }
            $contentTypes = (string) $zip->getFromName('[Content_Types].xml');
            $zip->close();

            $macro = 'Documentos com macros não são aceitos. Salve no formato padrão (ex.: "Documento do Word (.docx)") e envie novamente.';
            foreach ($names as $name) {
                if (str_contains($name, 'vbaproject') || str_starts_with($name, 'basic/') || str_contains($name, 'activex')) {
                    return [false, $macro, $names, $contentTypes];
                }
            }

            if (stripos($contentTypes, 'macroEnabled') !== false) {
                return [false, $macro, $names, $contentTypes];
            }

            return [true, null, $names, $contentTypes];
        } finally {
            @unlink($tmp);
        }
    }

    // ------------------------------------------------------------------
    // Andamento da fila
    // ------------------------------------------------------------------

    public function start(ArticleReview $review): void
    {
        $review->unsetRelation('reviewers');
        $participants = $review->participants()->get();

        if ($participants->isEmpty()) {
            throw new \RuntimeException('Cadastre ao menos um participante antes de enviar o despacho.');
        }

        if (!Storage::disk(self::DISK)->exists($review->original_file_path)) {
            throw new \RuntimeException('O arquivo original não foi encontrado. Envie o .docx novamente.');
        }

        [$ok, $error] = self::validateDocument(
            Storage::disk(self::DISK)->get($review->original_file_path),
            $review->originalExtension()
        );
        if (!$ok) {
            throw new \RuntimeException($error);
        }

        $review->update([
            'status' => ArticleReview::STATUS_IN_REVIEW,
            'started_at' => $review->started_at ?? now(),
            'reviews_completed_at' => null,
        ]);

        $review->logEvent('Despacho enviado — processo iniciado', $participants->count() . ' participante(s) na fila', true);

        $first = $this->activateNext($review, false, [], false);

        $this->notifyProfessor(
            'Despacho enviado: ' . ($first ? "agora está com {$first->name}" : 'revisão iniciada'),
            "\"{$review->title}\"\n\n" . $this->queueSummary($review)
                . ($first && !$first->isReachable() ? "\n\n⚠ {$first->name} NÃO tem e-mail nem Telegram: abra o painel, clique em \"Copiar link\" e envie por WhatsApp." : '')
                . "\n\nOs demais participantes com e-mail receberam o link para preencher a ficha e acompanhar.",
            $review
        );

        // Quem está na fila já recebe o link para preencher a ficha (depois
        // da resposta, para o "Enviar despacho" não ficar esperando).
        $queueIds = $participants->pluck('id')->reject(fn ($id) => $first && $id === $first->id)->all();
        $this->afterResponse(function () use ($queueIds) {
            foreach (ArticleReviewer::whereKey($queueIds)->get() as $p) {
                if ($p->status === ArticleReviewer::STATUS_WAITING && $p->isReachable()) {
                    $this->sendHeadsUp($p, null, true);
                }
            }
        });
    }

    /**
     * Passa o processo para o próximo participante da fila, ou encerra a
     * rodada de revisões se não houver mais ninguém.
     */
    public function activateNext(ArticleReview $review, bool $broadcast = true, array $exceptIds = [], bool $notifyProfessor = true): ?ArticleReviewer
    {
        $review->unsetRelation('reviewers');

        if ($review->reviewers()->where('status', ArticleReviewer::STATUS_IN_REVIEW)->exists()) {
            return null;
        }

        $next = $review->reviewers()->where('status', ArticleReviewer::STATUS_WAITING)->first();

        if (!$next) {
            $this->complete($review);
            return null;
        }

        $days = max(1, (int) ($next->review_days ?: $review->default_review_days ?: 7));

        $next->update([
            'status' => ArticleReviewer::STATUS_IN_REVIEW,
            'sent_at' => now(),
            'due_at' => now()->addDays($days)->endOfDay(),
            'overdue_notified' => false,
            'last_reminder_at' => null,
        ]);

        $review->logEvent(
            "Agora está com {$next->name}",
            'Prazo: ' . $next->due_at->format('d/m/Y') . ($next->email ? " · {$next->email}" : ' · sem e-mail cadastrado'),
            true
        );

        if ($next->isReachable()) {
            $this->sendInvite($next);
        } else {
            $review->logEvent("{$next->name} não tem e-mail cadastrado", 'Copie o link no painel e envie por WhatsApp');
        }

        if ($notifyProfessor) {
            $this->notifyProfessor(
                "Revisão: agora está com {$next->name}",
                "\"{$review->title}\" passou para {$next->name} (prazo " . $next->due_at->format('d/m/Y') . ").\n"
                    . ($next->isReachable() ? '' : "\n⚠ {$next->name} NÃO tem e-mail nem Telegram: abra o painel, clique em \"Copiar link\" e envie por WhatsApp.\n")
                    . "\n" . $this->queueSummary($review),
                $review
            );
        }

        if ($broadcast) {
            $this->broadcastProgress(
                $review,
                "O {$review->workNoun()} \"{$review->title}\" passou para {$next->name}.",
                array_merge([$next->id], $exceptIds)
            );
        }

        return $next;
    }

    public function queueOrder(ArticleReviewer $reviewer): array
    {
        $ids = $reviewer->articleReview->participants()->pluck('id')->all();
        $index = array_search($reviewer->id, $ids, true);

        return [$index === false ? null : $index + 1, count($ids)];
    }

    /**
     * "Com quem está" + a fila inteira, para os e-mails de andamento.
     */
    public function queueSummary(ArticleReview $review): string
    {
        $people = $review->participants()->get();
        $active = $people->firstWhere('status', ArticleReviewer::STATUS_IN_REVIEW);

        $lines = $people->values()->map(fn (ArticleReviewer $p, $i) => ($i + 1) . '. ' . $p->name . ' — ' . match ($p->status) {
            ArticleReviewer::STATUS_DONE => 'entregou em ' . $p->returned_at?->format('d/m'),
            ArticleReviewer::STATUS_IN_REVIEW => 'COM O ARQUIVO AGORA (desde ' . $p->sent_at?->format('d/m') . ', prazo ' . $p->due_at?->format('d/m') . ')',
            default => 'aguardando a vez',
        });

        return ($active ? "Agora está com: {$active->name}\n\n" : '')
            . "Ordem de revisão:\n" . $lines->implode("\n");
    }

    public function sendInvite(ArticleReviewer $reviewer, bool $isResend = false, bool $rotate = false): void
    {
        $review = $reviewer->articleReview;
        $url = $reviewer->accessUrl($rotate);
        [$order, $total] = $this->queueOrder($reviewer);

        if (!$reviewer->isReachable()) {
            return;
        }

        $body = "Olá, {$reviewer->name}!\n\n"
            . ($isResend ? ($rotate ? "Segue o seu novo link de acesso (o anterior foi desativado).\n\n" : "Segue novamente o seu link de acesso.\n\n") : '')
            . "Chegou a sua vez no processo de revisão do {$review->workNoun()}:\n"
            . "\"{$review->title}\"\n\n"
            . ($order ? "Você é o(a) participante {$order} de {$total} na ordem de revisão.\n" : '')
            . 'Prazo para devolução: ' . $reviewer->due_at?->format('d/m/Y') . "\n\n"
            . "Pelo link abaixo você confere/preenche a sua ficha (nome, filiação, ORCID, tipo de participação), baixa a versão atual, faz suas correções com \"Controlar Alterações\" ligado e devolve o arquivo revisado com seus comentários.\n\n"
            . self::disclaimer() . "\n\n"
            . 'Este link é pessoal e intransferível — por favor, não o encaminhe.';

        $this->notifyParticipant($reviewer, new AppNotificationMail(
            title: 'Sua vez: revisão de ' . $review->workNoun(),
            messageContent: $body,
            actionUrl: $url,
            actionText: 'Abrir revisão',
            subject: 'Sua vez de revisar — ' . Str::limit($review->title, 70),
        ));

        if ($isResend) {
            $review->logEvent("Link reenviado a {$reviewer->name}", $rotate ? 'Link novo — o anterior foi desativado' : 'Mesmo link');
        }
    }

    /**
     * Grava a devolução do participante da vez.
     *
     * @param  array<int, array{content: string, filename: string}>  $extraFiles
     */
    public function submitReview(
        ArticleReviewer $reviewer,
        string $content,
        string $extension,
        ?string $comments,
        ?string $suggestions,
        string $ip,
        string $userAgent,
        ?array $changes = null,
        array $extraFiles = []
    ): ArticleReviewer {
        $reviewer = DB::transaction(function () use ($reviewer, $content, $extension, $comments, $suggestions, $ip, $userAgent, $changes) {
            /** @var ArticleReviewer $locked */
            $locked = ArticleReviewer::whereKey($reviewer->id)->lockForUpdate()->firstOrFail();
            $review = ArticleReview::whereKey($locked->article_review_id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== ArticleReviewer::STATUS_IN_REVIEW || $review->status !== ArticleReview::STATUS_IN_REVIEW) {
                throw new \RuntimeException('Esta etapa de revisão não está mais aberta para envio.');
            }

            if (!$locked->isProfileComplete()) {
                throw new \RuntimeException('Confirme a sua ficha de participante antes de enviar o arquivo.');
            }

            $current = $review->currentVersion();
            $version = $current['version'] + 1;

            $initials = $review->reviewers()
                ->where('status', ArticleReviewer::STATUS_DONE)
                ->reorder('version_number')
                ->get()
                ->map(fn (ArticleReviewer $r) => $r->initials())
                ->push($locked->initials())
                ->implode('-');

            $filename = sprintf('%s_v%d_rev-%s.%s', $review->baseFileStem(), $version, $initials, strtolower($extension));
            $storedPath = 'article_reviews/' . $review->id . '/' . Str::uuid() . '.' . strtolower($extension);
            Storage::disk(self::DISK)->put($storedPath, $content);

            $locked->update([
                'status' => ArticleReviewer::STATUS_DONE,
                'returned_at' => now(),
                'version_number' => $version,
                'returned_file_path' => $storedPath,
                'returned_filename' => $filename,
                'comments' => filled($comments) ? Str::limit(trim($comments), 20000, '') : null,
                'suggestions' => filled($suggestions) ? Str::limit(trim($suggestions), 20000, '') : null,
                'participation_accepted_at' => now(),
                'participation_statement' => self::participationStatement($review, $locked),
                'forensic' => [
                    'ip' => $ip,
                    'user_agent' => Str::limit($userAgent, 500, ''),
                    'sha256' => hash('sha256', $content),
                    'size_kb' => (int) ceil(strlen($content) / 1024),
                    'received_version' => $current['version'],
                    'timestamp' => now()->format('d/m/Y H:i:s'),
                    'changes' => $changes,
                ],
            ]);

            return $locked;
        });

        $review = $reviewer->articleReview;

        foreach ($extraFiles as $file) {
            $this->addFile($review, $reviewer, $file['content'], $file['filename'], 'Enviado junto com a revisão', $reviewer->name);
        }

        $review->logEvent(
            "{$reviewer->name} devolveu a revisão (v{$reviewer->version_number})",
            "{$reviewer->returned_filename} · participação declarada · IP {$ip}"
                . ($changes ? ' · ' . DocxInspector::summaryLine($changes) : ''),
            true
        );

        if ($reviewer->isReachable()) {
            $this->notifyParticipant($reviewer, new AppNotificationMail(
                title: 'Revisão recebida — obrigado!',
                messageContent: "Olá, {$reviewer->name}!\n\n"
                    . "Recebemos a sua revisão de \"{$review->title}\" em " . $reviewer->returned_at->format('d/m/Y \à\s H:i') . ".\n"
                    . "Arquivo registrado: {$reviewer->returned_filename}\n"
                    . 'Código de verificação (SHA-256): ' . $reviewer->forensic['sha256'] . "\n\n"
                    . 'Sua participação foi registrada. Você continuará recebendo o andamento do processo por e-mail. Muito obrigado!',
                actionUrl: $reviewer->accessUrl(),
                actionText: 'Ver andamento',
                subject: 'Revisão recebida — ' . Str::limit($review->title, 70),
            ));
        }

        $next = $this->activateNext($review, true, [$reviewer->id], false);
        $review->refresh();

        $this->notifyProfessor(
            "Revisão devolvida por {$reviewer->name}" . ($next ? " — agora com {$next->name}" : ''),
            "{$reviewer->name} devolveu a revisão de \"{$review->title}\".\n"
                . "Versão gerada: {$reviewer->returned_filename}"
                . ($changes ? "\nAlterações: " . DocxInspector::summaryLine($changes) : '')
                . (filled($reviewer->comments) ? "\n\nComentários:\n" . Str::limit($reviewer->comments, 1500) : '')
                . (filled($reviewer->suggestions) ? "\n\nSugestões:\n" . Str::limit($reviewer->suggestions, 1500) : '')
                . ($next ? "\n\n" . $this->queueSummary($review) : '')
                . ($next && !$next->isReachable() ? "\n\n⚠ {$next->name} NÃO tem e-mail nem Telegram: abra o painel, clique em \"Copiar link\" e envie por WhatsApp." : ''),
            $review
        );

        return $reviewer;
    }

    /**
     * Compara o arquivo enviado com a versão atual (antes de gravar), para
     * acusar envio errado e registrar as modificações.
     */
    public function inspectUpload(ArticleReview $review, string $content, string $extension): ?array
    {
        try {
            $current = $review->currentVersion();
            $disk = Storage::disk(self::DISK);
            if (!$current['path'] || !$disk->exists($current['path'])) {
                return null;
            }

            $oldContent = $disk->get($current['path']);
            $oldExt = strtolower(pathinfo($current['filename'], PATHINFO_EXTENSION)) ?: 'docx';

            return DocxInspector::compare(
                DocxInspector::analyze($oldContent, $oldExt),
                DocxInspector::analyze($content, $extension),
                hash('sha256', $oldContent) === hash('sha256', $content)
            ) + ['compared_with_version' => $current['version']];
        } catch (\Throwable $e) {
            Log::warning('Revisão de artigo: falha ao comparar versões: ' . $e->getMessage());

            return null;
        }
    }

    public function skip(ArticleReviewer $reviewer): void
    {
        $reviewer->update(['status' => ArticleReviewer::STATUS_SKIPPED]);
        $reviewer->revokeToken();

        $review = $reviewer->articleReview;
        $review->logEvent("{$reviewer->name} foi pulado(a)", 'Acesso revogado', true);

        if ($review->status === ArticleReview::STATUS_IN_REVIEW) {
            $this->activateNext($review);
        }
    }

    /**
     * Encerra a rodada antes do fim (alguém não revisou) e devolve o trabalho
     * ao professor. Quem estava na vez perde o acesso ao arquivo, mas todos
     * continuam no processo (ficha, autoria e acompanhamento).
     */
    public function bringBack(ArticleReview $review, ?string $reason = null): void
    {
        if ($review->status !== ArticleReview::STATUS_IN_REVIEW) {
            throw new \RuntimeException('O processo não está em revisão.');
        }

        $active = $review->reviewers()->where('status', ArticleReviewer::STATUS_IN_REVIEW)->first();
        $pending = $review->reviewers()->where('status', ArticleReviewer::STATUS_WAITING)->pluck('name');

        $active?->update(['status' => ArticleReviewer::STATUS_WAITING]);

        $review->update([
            'status' => ArticleReview::STATUS_REVIEWS_DONE,
            'reviews_completed_at' => now(),
        ]);

        $notReviewed = collect([$active?->name])->filter()->merge($pending);
        $current = $review->currentVersion();

        $review->logEvent(
            'Rodada encerrada pelo Prof. Atilio — voltou para ele',
            ($notReviewed->isNotEmpty() ? 'Não revisaram: ' . $notReviewed->implode(', ') . '. ' : '')
                . 'Versão atual: ' . $current['filename']
                . (filled($reason) ? ' · Motivo: ' . Str::limit(trim($reason), 300, '') : ''),
            true
        );

        if ($active && $active->isReachable()) {
            $this->notifyParticipant($active, new AppNotificationMail(
                title: 'Sua vez foi encerrada',
                messageContent: "Olá, {$active->name}!\n\n"
                    . "O Prof. Atilio encerrou a rodada de revisão de \"{$review->title}\" e o arquivo voltou para ele. "
                    . "Não é mais preciso enviar a sua revisão.\n\n"
                    . (filled($reason) ? 'Mensagem: ' . trim($reason) . "\n\n" : '')
                    . 'Você continua acompanhando o andamento pelo link abaixo.',
                actionUrl: $active->accessUrl(),
                actionText: 'Ver andamento',
                subject: 'Revisão encerrada — ' . Str::limit($review->title, 70),
            ));
        }

        $this->broadcastProgress(
            $review,
            "A rodada de revisão de \"{$review->title}\" foi encerrada e o {$review->workNoun()} voltou para o Prof. Atilio para a consolidação e o envio à revista.",
            array_filter([$active?->id])
        );
    }

    public function extendDeadline(ArticleReviewer $reviewer, int $days): void
    {
        $base = $reviewer->due_at && $reviewer->due_at->isFuture() ? $reviewer->due_at : now();

        $reviewer->update([
            'due_at' => $base->copy()->addDays(max(1, $days))->endOfDay(),
            'overdue_notified' => false,
            'last_reminder_at' => null,
        ]);

        $reviewer->articleReview->logEvent(
            "Prazo de {$reviewer->name} prorrogado",
            'Novo prazo: ' . $reviewer->due_at->format('d/m/Y')
        );
    }

    public function cancel(ArticleReview $review): void
    {
        $review->reviewers()->update(['token_hash' => null, 'token_encrypted' => null]);
        $review->reviewers()->where('status', ArticleReviewer::STATUS_IN_REVIEW)->update(['status' => ArticleReviewer::STATUS_WAITING]);
        $review->update(['status' => ArticleReview::STATUS_CANCELLED, 'signup_enabled' => false]);
        $review->logEvent('Processo cancelado', 'Todos os links desativados');
    }

    /**
     * Exclui o processo inteiro (cadastro errado etc.): participantes, fichas,
     * versões, documentos e histórico. Os arquivos saem do disco pelo hook
     * `deleting` do model. Opcionalmente avisa quem já tinha recebido algo.
     */
    public function deleteProcess(ArticleReview $review, bool $notify = false, ?string $reason = null): void
    {
        if ($notify && $review->status !== ArticleReview::STATUS_DRAFT) {
            foreach ($review->participants()->get()->filter->isReachable() as $p) {
                $this->notifyParticipant($p, new AppNotificationMail(
                    title: 'Processo de revisão cancelado',
                    messageContent: "Olá, {$p->name}!\n\n"
                        . "O processo de revisão de \"{$review->title}\" foi cancelado e excluído pelo Prof. Atilio. "
                        . 'O link que você recebeu não funciona mais. Se você estiver com o arquivo, pode descartá-lo.'
                        . (filled($reason) ? "\n\nMotivo: " . trim($reason) : '')
                        . "\n\nEm caso de dúvida, responda a este e-mail.",
                    subject: 'Revisão cancelada — ' . Str::limit($review->title, 60),
                ));
            }
        }

        Log::info('Revisão de artigo: processo excluído', [
            'id' => $review->id,
            'title' => $review->title,
            'status' => $review->status,
            'by' => auth()->user()?->email,
            'reason' => $reason,
        ]);

        $review->delete();
    }

    /**
     * Aviso/lembrete ao participante da vez, com o link de acesso.
     */
    public function sendReminder(ArticleReviewer $reviewer, ?string $customMessage = null, bool $automatic = false): void
    {
        $review = $reviewer->articleReview;

        if (!$reviewer->isReachable()) {
            throw new \RuntimeException("{$reviewer->name} não tem e-mail nem Telegram. Use \"Copiar link\" e envie por WhatsApp.");
        }

        $overdue = $reviewer->isOverdue();
        $custom = filled($customMessage) ? "Mensagem do Prof. Atilio:\n" . trim($customMessage) . "\n\n" : '';

        $this->notifyParticipant($reviewer, new AppNotificationMail(
            title: $overdue ? 'Revisão em atraso' : 'Lembrete de revisão',
            messageContent: "Olá, {$reviewer->name}!\n\n"
                . $custom
                . ($overdue
                    ? 'O prazo para a sua revisão de "' . $review->title . '" venceu em ' . $reviewer->due_at->format('d/m/Y') . ". Os demais participantes aguardam a sua devolução para seguir com o trabalho.\n\n"
                    : 'Lembrete: o prazo para a sua revisão de "' . $review->title . '" vence em ' . $reviewer->due_at?->format('d/m/Y') . ".\n\n")
                . "Use o link abaixo para baixar a versão atual e devolver o arquivo revisado.\n\n"
                . 'Se não puder participar desta vez, basta responder a este e-mail avisando.',
            actionUrl: $reviewer->accessUrl(),
            actionText: 'Abrir revisão',
            subject: ($overdue ? 'Revisão em atraso — ' : 'Lembrete de revisão — ') . Str::limit($review->title, 60),
        ));

        $reviewer->update(['last_reminder_at' => now()]);

        $review->logEvent(
            ($automatic ? 'Lembrete automático' : 'Aviso enviado') . " a {$reviewer->name}",
            ($overdue ? 'Prazo vencido · ' : '') . (filled($customMessage) ? Str::limit(trim($customMessage), 300) : 'sem mensagem')
        );
    }

    /**
     * Aviso a quem está na fila: link para preencher a ficha e acompanhar o
     * andamento (o download só é liberado quando chegar a vez).
     */
    public function sendHeadsUp(ArticleReviewer $reviewer, ?string $customMessage = null, bool $isInclusion = false): void
    {
        $review = $reviewer->articleReview;

        if (!$reviewer->isReachable()) {
            throw new \RuntimeException("{$reviewer->name} não tem e-mail nem Telegram. Use \"Copiar link\" e envie por WhatsApp.");
        }

        $custom = filled($customMessage) ? "Mensagem do Prof. Atilio:\n" . trim($customMessage) . "\n\n" : '';

        $this->notifyParticipant($reviewer, new AppNotificationMail(
            title: 'Você está no processo de revisão',
            messageContent: "Olá, {$reviewer->name}!\n\n"
                . $custom
                . ($isInclusion ? "Você foi incluído(a) pelo Prof. Atilio no processo de revisão do {$review->workNoun()} \"{$review->title}\".\n\n"
                    : "Você está na fila de revisão do {$review->workNoun()} \"{$review->title}\".\n\n")
                . ($review->status === ArticleReview::STATUS_IN_REVIEW ? $this->queueSummary($review) . "\n\n" : '')
                . "Pelo link abaixo, já preencha e confirme a sua ficha de participante (nome completo, filiação, ORCID, telefone e tipo de participação) e acompanhe com quem o arquivo está. "
                . "Quando chegar a sua vez, você receberá outro e-mail e o download será liberado.\n\n"
                . self::disclaimer(),
            actionUrl: $reviewer->accessUrl(),
            actionText: 'Preencher minha ficha',
            subject: 'Revisão de ' . $review->workNoun() . ' — ' . Str::limit($review->title, 60),
        ));

        $reviewer->update(['last_reminder_at' => now()]);

        $review->logEvent(
            ($isInclusion ? 'Convite enviado a ' : 'Aviso enviado a ') . $reviewer->name . ' (na fila)',
            filled($customMessage) ? Str::limit(trim($customMessage), 300) : null
        );
    }

    // ------------------------------------------------------------------
    // Gestão de participantes
    // ------------------------------------------------------------------

    public function addParticipant(ArticleReview $review, array $data, bool $selfRegistered = false): ArticleReviewer
    {
        $reviewer = $review->reviewers()->create([
            'name' => User::normalizeName($data['name']),
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'institution' => ($data['institution'] ?? null) ?: null,
            'review_days' => ($data['review_days'] ?? null) ?: null,
            'author_order' => ($data['author_order'] ?? null) ?: null,
            'position' => ((int) $review->reviewers()->max('position')) + 1,
            'status' => ArticleReviewer::STATUS_WAITING,
            'self_registered' => $selfRegistered,
        ]);

        $review->logEvent(
            ($selfRegistered ? "{$reviewer->name} se cadastrou pelo link do processo" : "{$reviewer->name} incluído(a) no processo"),
            'Entrou no fim da fila',
            true
        );

        if ($review->status === ArticleReview::STATUS_REVIEWS_DONE) {
            $review->update(['status' => ArticleReview::STATUS_IN_REVIEW, 'reviews_completed_at' => null]);
            $review->logEvent('Rodada reaberta para o novo participante', null, true);
            $this->activateNext($review);
        } elseif ($review->status === ArticleReview::STATUS_IN_REVIEW && $reviewer->isReachable() && !$selfRegistered) {
            $this->sendHeadsUp($reviewer, null, true);
        }

        return $reviewer->fresh();
    }

    /**
     * Edição de contato/ordem de autoria pelo professor (fica no registro).
     */
    public function updateParticipant(ArticleReviewer $reviewer, array $data): void
    {
        $fields = ['name', 'email', 'phone', 'review_days', 'author_order'];
        $labels = ['name' => 'nome', 'email' => 'e-mail', 'phone' => 'telefone', 'review_days' => 'prazo', 'author_order' => 'ordem de autoria'];

        $changes = [];
        foreach ($fields as $f) {
            if (!array_key_exists($f, $data)) {
                continue;
            }
            $new = $data[$f] === '' ? null : $data[$f];
            if ((string) $reviewer->{$f} !== (string) $new) {
                $changes[] = $labels[$f] . ': "' . ($reviewer->{$f} ?? '—') . '" → "' . ($new ?? '—') . '"';
                $reviewer->{$f} = $new;
            }
        }

        if ($changes === []) {
            return;
        }

        $reviewer->save();
        $reviewer->articleReview->logEvent("Dados de {$reviewer->name} alterados pelo professor", implode('; ', $changes));
    }

    /**
     * Remove alguém que ainda não entregou. Nada é apagado: a pessoa fica
     * como "Removido do processo" no histórico (auditoria). Quem já entregou
     * a revisão nunca pode ser removido.
     */
    public function removeParticipant(ArticleReviewer $reviewer, ?string $reason = null): void
    {
        if ($reviewer->status === ArticleReviewer::STATUS_DONE) {
            throw new \RuntimeException('Quem já entregou a revisão não pode ser removido (registro de auditoria).');
        }

        if ($reviewer->status === ArticleReviewer::STATUS_IN_REVIEW) {
            throw new \RuntimeException('Esta pessoa está na vez. Use "Trocar quem está na vez".');
        }

        $reviewer->update([
            'status' => ArticleReviewer::STATUS_REMOVED,
            'removed_at' => now(),
            'removed_reason' => filled($reason) ? Str::limit(trim($reason), 250, '') : null,
        ]);
        $reviewer->revokeToken();

        $reviewer->articleReview->logEvent("{$reviewer->name} removido(a) do processo", $reason, true);
    }

    /**
     * Nova ordem de quem ainda está aguardando. Quem já entregou ou está na
     * vez não muda de lugar.
     *
     * @param  array<int, int>  $orderedIds
     */
    public function reorderWaiting(ArticleReview $review, array $orderedIds, bool $log = true): void
    {
        $waiting = $review->reviewers()->where('status', ArticleReviewer::STATUS_WAITING)->get()->keyBy('id');
        $ids = array_values(array_unique(array_filter(array_map('intval', $orderedIds), fn ($id) => $waiting->has($id))));

        foreach ($waiting->keys() as $id) {
            if (!in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        $before = $waiting->pluck('name')->implode(' → ');
        $base = (int) $review->reviewers()->where('status', '!=', ArticleReviewer::STATUS_WAITING)->max('position') + 1;

        foreach ($ids as $i => $id) {
            $waiting[$id]->forceFill(['position' => $base + $i])->save();
        }

        if ($log) {
            $after = collect($ids)->map(fn ($id) => $waiting[$id]->name)->implode(' → ');
            if ($before !== $after) {
                $review->logEvent('Ordem da fila alterada', "Antes: {$before} · Agora: {$after}", true);
            }
        }
    }

    /**
     * Troca quem está na vez (ex.: está demorando). A pessoa atual volta para
     * o fim da fila ou sai do processo; a vez passa para outra pessoa da fila
     * ou para alguém novo.
     */
    public function swapCurrent(ArticleReview $review, ?int $nextId, ?array $newPerson, string $fate, ?string $reason = null): ArticleReviewer
    {
        $current = $review->reviewers()->where('status', ArticleReviewer::STATUS_IN_REVIEW)->first();

        if (!$current) {
            throw new \RuntimeException('Não há ninguém na vez agora.');
        }

        if ($newPerson) {
            $next = $review->reviewers()->create([
                'name' => User::normalizeName($newPerson['name']),
                'email' => $newPerson['email'] ?? null,
                'phone' => $newPerson['phone'] ?? null,
                'review_days' => ($newPerson['review_days'] ?? null) ?: null,
                'position' => ((int) $review->reviewers()->max('position')) + 1,
                'status' => ArticleReviewer::STATUS_WAITING,
            ]);
            $review->logEvent("{$next->name} incluído(a) no processo", 'Para assumir a vez', true);
        } else {
            $next = $review->reviewers()->where('status', ArticleReviewer::STATUS_WAITING)->whereKey($nextId)->first();
            if (!$next) {
                throw new \RuntimeException('Escolha quem vai assumir a vez.');
            }
        }

        if ($fate === 'remove') {
            $current->update([
                'status' => ArticleReviewer::STATUS_REMOVED,
                'removed_at' => now(),
                'removed_reason' => filled($reason) ? Str::limit(trim($reason), 250, '') : 'Substituído(a) durante a avaliação',
            ]);
            $current->revokeToken();
        } else {
            $current->update([
                'status' => ArticleReviewer::STATUS_WAITING,
                'position' => ((int) $review->reviewers()->max('position')) + 1,
                'due_at' => null,
            ]);
        }

        $review->logEvent(
            "Vez retirada de {$current->name} e passada a {$next->name}",
            ($fate === 'remove' ? "{$current->name} saiu do processo" : "{$current->name} voltou para o fim da fila")
                . (filled($reason) ? ' · ' . Str::limit(trim($reason), 250) : ''),
            true
        );

        if ($current->isReachable()) {
            $this->notifyParticipant($current, new AppNotificationMail(
                title: 'Revisão: a vez foi passada adiante',
                messageContent: "Olá, {$current->name}!\n\n"
                    . "O Prof. Atilio passou a vez de revisão de \"{$review->title}\" para outra pessoa.\n"
                    . ($fate === 'remove'
                        ? 'A sua participação nesta rodada foi encerrada e o seu link foi desativado.'
                        : 'Você continua no processo e receberá o arquivo novamente quando chegar a sua vez. Se já tiver feito alterações, guarde o seu arquivo.')
                    . "\n\nEm caso de dúvida, responda a este e-mail.",
                subject: 'Revisão — ' . Str::limit($review->title, 60),
            ));
        }

        $order = $review->reviewers()->where('status', ArticleReviewer::STATUS_WAITING)->pluck('id')->all();
        $order = array_values(array_diff($order, [$next->id]));
        array_unshift($order, $next->id);
        $this->reorderWaiting($review, $order, false);

        return $this->activateNext($review) ?? $next;
    }

    /**
     * Ficha do participante (nome, contato, filiação, ORCID, CRediT, aceites).
     */
    public function saveProfile(ArticleReviewer $reviewer, array $data, string $ip): void
    {
        $before = $reviewer->name;

        $reviewer->fill([
            'name' => User::normalizeName($data['name']),
            'citation_name' => trim($data['citation_name']),
            'email' => $data['email'],
            'phone' => trim($data['phone']),
            'institution' => trim($data['institution']),
            'department' => trim((string) ($data['department'] ?? '')) ?: null,
            'city' => trim($data['city']),
            'state' => trim($data['state']),
            'country' => trim($data['country']),
            'orcid' => $data['no_orcid'] ? null : $data['orcid'],
            'no_orcid' => (bool) $data['no_orcid'],
            'contribution_roles' => array_values($data['roles']),
            'contribution_other' => trim((string) ($data['contribution_other'] ?? '')) ?: null,
            'profile_notes' => trim((string) ($data['profile_notes'] ?? '')) ?: null,
            'consent' => [
                'accept_participation' => now()->format('Y-m-d H:i:s'),
                'aware_content' => now()->format('Y-m-d H:i:s'),
                'checklist' => array_keys(ArticleReviewer::CHECKLIST),
                'ip' => $ip,
            ],
            'profile_confirmed_at' => now(),
        ])->save();

        $review = $reviewer->articleReview;
        $review->logEvent(
            "{$reviewer->name} confirmou a ficha de participante",
            ($before !== $reviewer->name ? "nome alterado de \"{$before}\" · " : '')
                . 'aceite de participação e ciência do conteúdo registrados · IP ' . $ip,
            true
        );
    }

    /**
     * Cadastro pelo link do processo: a pessoa escolhe o nome dela (entre os
     * cadastrados sem e-mail) ou entra como nova no fim da fila.
     */
    public function registerFromSignup(ArticleReview $review, ?int $claimId, array $data): ArticleReviewer
    {
        if ($claimId) {
            $reviewer = $review->reviewers()
                ->whereKey($claimId)
                ->whereNull('email')
                ->whereNotIn('status', ArticleReviewer::INACTIVE_STATUSES)
                ->first();

            if (!$reviewer) {
                throw new \RuntimeException('Este nome já foi vinculado. Se for você, fale com o Prof. Atilio.');
            }

            $reviewer->update(['email' => $data['email'], 'phone' => $data['phone']]);
            $review->logEvent("{$reviewer->name} vinculou e-mail/telefone pelo link do processo", $data['email'], true);
        } else {
            $reviewer = $this->addParticipant($review, $data, true);
        }

        $this->notifyProfessor(
            'Revisão: cadastro pelo link do processo',
            "{$reviewer->name} ({$data['email']}, {$data['phone']}) "
                . ($claimId ? 'se identificou na lista' : 'se cadastrou como novo(a) participante (entrou no fim da fila)')
                . " em \"{$review->title}\".\nConfira no painel; você pode reordenar ou remover.",
            $review
        );

        if ($reviewer->isReachable()) {
            $this->notifyParticipant($reviewer, new AppNotificationMail(
                title: 'Cadastro no processo de revisão',
                messageContent: "Olá, {$reviewer->name}!\n\n"
                    . "Seu cadastro no processo de revisão de \"{$review->title}\" foi registrado. Guarde o link abaixo: é por ele que você confere a ficha, acompanha o andamento e, quando chegar a sua vez, baixa e devolve o arquivo.\n\n"
                    . self::disclaimer(),
                actionUrl: $reviewer->accessUrl(),
                actionText: 'Abrir minha página',
                subject: 'Cadastro na revisão — ' . Str::limit($review->title, 60),
            ));
        }

        return $reviewer;
    }

    // ------------------------------------------------------------------
    // Versões e arquivos
    // ------------------------------------------------------------------

    /**
     * Faz de uma versão anterior a base atual (cópia nova com número novo).
     * Nada é apagado.
     */
    public function restoreVersion(ArticleReview $review, string $key, ?string $reason = null): array
    {
        $source = $review->findVersion($key);
        if (!$source || !Storage::disk(self::DISK)->exists($source['path'])) {
            throw new \RuntimeException('Versão não encontrada.');
        }

        $newVersion = $review->currentVersion()['version'] + 1;
        $ext = strtolower(pathinfo($source['filename'], PATHINFO_EXTENSION)) ?: 'docx';
        $path = 'article_reviews/' . $review->id . '/' . Str::uuid() . '.' . $ext;
        Storage::disk(self::DISK)->copy($source['path'], $path);

        $restores = $review->version_restores ?? [];
        $restores[] = [
            'path' => $path,
            'filename' => sprintf('%s_v%d_restaurada-v%d.%s', $review->baseFileStem(), $newVersion, $source['version'], $ext),
            'version' => $newVersion,
            'from_version' => $source['version'],
            'at' => now()->format('Y-m-d H:i:s'),
            'reason' => filled($reason) ? Str::limit(trim($reason), 300, '') : null,
        ];
        $review->forceFill(['version_restores' => $restores])->save();

        $review->logEvent(
            "Versão v{$source['version']} restaurada como base atual (v{$newVersion})",
            $reason,
            true
        );

        $active = $review->reviewers()->where('status', ArticleReviewer::STATUS_IN_REVIEW)->first();
        if ($active && $active->isReachable()) {
            $this->notifyParticipant($active, new AppNotificationMail(
                title: 'Atenção: a versão base mudou',
                messageContent: "Olá, {$active->name}!\n\n"
                    . "O Prof. Atilio restaurou uma versão anterior de \"{$review->title}\" como base de trabalho (agora v{$newVersion}).\n"
                    . 'Se você já baixou o arquivo, baixe novamente pelo link abaixo antes de continuar a revisão.'
                    . (filled($reason) ? "\n\nMotivo: " . trim($reason) : ''),
                actionUrl: $active->accessUrl(),
                actionText: 'Baixar a nova base',
                subject: 'Versão base alterada — ' . Str::limit($review->title, 60),
            ));
        }

        return end($restores);
    }

    public function addFile(ArticleReview $review, ?ArticleReviewer $reviewer, string $content, string $filename, ?string $description, ?string $uploadedBy): ArticleReviewFile
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $safe = Str::of(pathinfo($filename, PATHINFO_FILENAME))->ascii()->replaceMatches('/[^A-Za-z0-9._-]+/', '_')->trim('_')->limit(80, '')->toString() ?: 'arquivo';
        $path = 'article_reviews/' . $review->id . '/extra/' . Str::uuid() . '.' . $ext;
        Storage::disk(self::DISK)->put($path, $content);

        $file = $review->files()->create([
            'article_reviewer_id' => $reviewer?->id,
            'path' => $path,
            'filename' => $safe . '.' . $ext,
            'description' => filled($description) ? Str::limit(trim($description), 250, '') : null,
            'size_kb' => (int) ceil(strlen($content) / 1024),
            'sha256' => hash('sha256', $content),
            'uploaded_by' => $uploadedBy,
        ]);

        $review->logEvent('Documento complementar adicionado: ' . $file->filename, 'por ' . ($uploadedBy ?: 'professor'), true);

        return $file;
    }

    public function markSubmitted(ArticleReview $review, array $data, ?string $finalTmpPath = null): void
    {
        // Pipeline de submissão: com tipo de artigo definido, não fecha com item pendente.
        if ($review->article_type && ($pending = \App\Support\ArticleTypes::pending($review))) {
            throw new \RuntimeException('Checklist de submissão com ' . count($pending) . ' item(ns) pendente(s): ' . implode(' · ', array_slice($pending, 0, 5))
                . (count($pending) > 5 ? ' …' : '') . '. Marque como feito ou "não se aplica" em "Checklist de submissão".');
        }

        $updates = [
            'journal' => $data['journal'] ?? $review->journal,
            'submitted_at' => $data['submitted_at'],
            'status' => ArticleReview::STATUS_SUBMITTED,
            'signup_enabled' => false,
        ];

        if ($finalTmpPath) {
            $ext = strtolower(pathinfo($finalTmpPath, PATHINFO_EXTENSION)) ?: 'docx';
            $final = 'article_reviews/' . $review->id . '/final_' . Str::uuid() . '.' . $ext;
            Storage::disk(self::DISK)->move($finalTmpPath, $final);

            if ($review->final_file_path) {
                Storage::disk(self::DISK)->delete($review->final_file_path);
            }

            $updates['final_file_path'] = $final;
            $updates['final_filename'] = $review->baseFileStem() . '_FINAL.' . $ext;
        }

        $review->update($updates);

        // Quem estava na fila sem ter revisado não recebe mais o arquivo.
        $review->reviewers()->where('status', ArticleReviewer::STATUS_IN_REVIEW)->update(['status' => ArticleReviewer::STATUS_WAITING]);

        $journal = $review->journal ? " à revista {$review->journal}" : '';
        $date = \Illuminate\Support\Carbon::parse($data['submitted_at'])->format('d/m/Y');

        $review->logEvent("Enviado{$journal} em {$date} — processo fechado", $review->final_filename ? "Versão final: {$review->final_filename}" : null, true);

        $this->broadcastProgress(
            $review,
            "O {$review->workNoun()} \"{$review->title}\" foi enviado{$journal} em {$date}. O processo de revisão foi encerrado com a versão final. Muito obrigado pela sua participação!",
            [],
            'Processo encerrado'
        );
    }

    /**
     * Texto pronto para a submissão: autores na ordem de autoria, filiações
     * numeradas, ORCID, contribuições CRediT e contatos.
     */
    public function authorsBlock(ArticleReview $review): string
    {
        $people = $review->participants()->get()
            ->sortBy(fn (ArticleReviewer $r) => [$r->author_order ?? PHP_INT_MAX, $r->position, $r->id])
            ->values();

        $affiliations = [];
        $lines = [];
        foreach ($people as $i => $p) {
            $aff = $p->affiliationLine();
            $num = '';
            if ($aff !== '') {
                if (!in_array($aff, $affiliations, true)) {
                    $affiliations[] = $aff;
                }
                $num = (string) (array_search($aff, $affiliations, true) + 1);
            }
            $lines[] = ($i + 1) . '. ' . $p->name . ($num ? " [{$num}]" : '')
                . ($p->citation_name ? " — citação: {$p->citation_name}" : '')
                . ($p->orcid ? " — ORCID: https://orcid.org/{$p->orcid}" : ($p->no_orcid ? ' — sem ORCID' : ''))
                . ($p->isProfileComplete() ? '' : ' (ficha pendente)');
        }

        $text = "AUTORES (ordem de autoria)\n" . implode("\n", $lines);

        if ($affiliations) {
            $text .= "\n\nFILIAÇÕES\n" . collect($affiliations)->map(fn ($a, $i) => '[' . ($i + 1) . "] {$a}")->implode("\n");
        }

        $credit = $people->filter(fn ($p) => $p->rolesLabel() !== '')
            ->map(fn ($p) => "{$p->name}: {$p->rolesLabel()}.");
        if ($credit->isNotEmpty()) {
            $text .= "\n\nCONTRIBUIÇÕES (CRediT)\n" . $credit->implode("\n");
        }

        $text .= "\n\nCONTATOS\n" . $people->map(fn ($p) => $p->name . ' — ' . ($p->email ?: 'sem e-mail') . ' — ' . ($p->phone ?: 'sem telefone'))->implode("\n");

        return $text;
    }

    // ------------------------------------------------------------------
    // Notificações
    // ------------------------------------------------------------------

    protected function complete(ArticleReview $review): void
    {
        $review->update([
            'status' => ArticleReview::STATUS_REVIEWS_DONE,
            'reviews_completed_at' => now(),
        ]);

        $current = $review->currentVersion();
        $review->logEvent('Todas as revisões concluídas — voltou para o Prof. Atilio', $current['filename'], true);

        $this->notifyProfessor(
            '✅ Revisões concluídas',
            "Todos os participantes devolveram \"{$review->title}\".\n"
                . "Versão final da rodada: {$current['filename']}\n\n"
                . 'Baixe, consolide e registre o envio à revista no painel.',
            $review
        );

        $this->broadcastProgress(
            $review,
            "Todas as revisões de \"{$review->title}\" foram concluídas. O {$review->workNoun()} voltou para o Prof. Atilio para a consolidação e o envio à revista."
        );
    }

    /**
     * Andamento para todos os participantes com e-mail (exceto removidos e
     * os indicados), cada um com o seu link.
     *
     * @param  array<int, int>  $exceptIds
     */
    public function broadcastProgress(ArticleReview $review, string $headline, array $exceptIds = [], string $title = 'Andamento da revisão'): void
    {
        if (!$review->notify_all) {
            return;
        }

        // Vários e-mails seguidos: vão depois que a resposta já foi entregue,
        // para quem acabou de devolver a revisão não ficar esperando o envio
        // a todo mundo (nem estourar o tempo limite do servidor).
        $this->afterResponse(fn () => $this->sendBroadcast($review->fresh(), $headline, $exceptIds, $title));
    }

    protected function sendBroadcast(?ArticleReview $review, string $headline, array $exceptIds, string $title): void
    {
        if (!$review) {
            return;
        }

        $current = $review->reviewers()->where('status', ArticleReviewer::STATUS_IN_REVIEW)->first();

        foreach ($review->participants()->get()->filter->isReachable() as $p) {
            if (in_array($p->id, $exceptIds, true)) {
                continue;
            }

            $this->notifyParticipant($p, new AppNotificationMail(
                title: $title,
                messageContent: "Olá, {$p->name}!\n\n{$headline}\n\n"
                    . ($review->status === ArticleReview::STATUS_IN_REVIEW ? $this->queueSummary($review) . "\n\n" : '')
                    . ($p->isProfileComplete() || $review->isClosed() ? '' : "Sua ficha de participante ainda está pendente — confirme pelo link abaixo.\n")
                    . "\nO histórico completo fica na sua página do processo.",
                actionUrl: $p->accessUrl(),
                actionText: 'Ver andamento',
                subject: $title . ' — ' . Str::limit($review->title, 60),
            ));
        }
    }

    public function notifyProfessor(string $title, string $body, ArticleReview $review): void
    {
        $admin = User::where('role', 'admin')->whereNotNull('telegram_chat_id')->orderBy('id')->first();
        $url = \App\Filament\Resources\ArticleReviewResource::getUrl('view', ['record' => $review], panel: 'admin');

        // Mesma caixa usada pelo resto do sistema para avisos ao professor.
        $this->mail(self::PROFESSOR_EMAIL, new AppNotificationMail(
            title: $title,
            messageContent: $body,
            actionUrl: $url,
            actionText: 'Abrir no painel',
            subject: $title . ' — ' . Str::limit($review->title, 60),
        ));

        if ($admin) {
            $this->telegram($admin->telegram_chat_id, $title, $body, $url, 'Abrir no painel');
        }
    }

    /**
     * Aviso a um participante: e-mail (se tiver) + Telegram (se vinculado —
     * pelo bot na página do processo ou pela conta de orientando).
     */
    protected function notifyParticipant(ArticleReviewer $p, AppNotificationMail $mail): void
    {
        if ($p->email) {
            $this->mail($p->email, $mail);
        }

        if ($chat = $p->telegramChatId()) {
            $this->telegram($chat, $mail->title, $mail->messageContent, $mail->actionUrl, $mail->actionText);
        }
    }

    protected function telegram(string $chatId, string $title, string $body, ?string $url = null, ?string $urlText = null): void
    {
        try {
            app(TelegramService::class)->sendMessage(
                $chatId,
                '<b>' . e($title) . "</b>\n\n" . e(Str::limit($body, 3300))
                    . ($url ? "\n\n👉 <a href=\"" . e($url) . '">' . e($urlText ?: 'Abrir') . '</a>' : '')
            );
        } catch (\Throwable $e) {
            Log::error('Revisão de artigo: falha ao enviar Telegram: ' . $e->getMessage());
        }
    }

    /**
     * Roda depois que a resposta HTTP foi enviada (defer do Laravel); em
     * comando/console roda na hora.
     */
    protected function afterResponse(callable $callback): void
    {
        if (app()->runningInConsole() && !app()->runningUnitTests()) {
            $callback();

            return;
        }

        \Illuminate\Support\defer($callback);
    }

    /**
     * E-mail síncrono (a pessoa está esperando agora), mas sem deixar uma
     * falha de SMTP derrubar o fluxo.
     */
    protected function mail(string $to, AppNotificationMail $mail): void
    {
        try {
            Mail::to($to)->send($mail);
        } catch (\Throwable $e) {
            Log::error("Revisão de artigo: falha ao enviar e-mail para {$to}: " . $e->getMessage());
        }
    }
}
