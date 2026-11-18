<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Entrada única do professor, vinda do prof-aid — ver App\Http\Controllers\SsoController.
Route::get('/sso', [\App\Http\Controllers\SsoController::class, 'callback'])->name('sso.callback');

Route::get('/normas-abnt', function () {
    return view('normas-abnt');
})->name('normas.abnt');

// Rota para abrir o formulário de convite
Route::get('/invite', function (\Illuminate\Http\Request $request) {
    $tokenStr = $request->query('token');
    
    if (!$tokenStr) {
        return view('registration_status', [
            'status' => 'error',
            'title' => 'Acesso Negado',
            'message' => 'Um token de convite válido é necessário para realizar o pré-cadastro.'
        ]);
    }
    
    $inviteToken = \App\Models\InviteToken::where('token', $tokenStr)
        ->whereNull('used_at')
        ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
        ->first();
        
    if (!$inviteToken) {
        return view('registration_status', [
            'status' => 'error',
            'title' => 'Convite Inválido',
            'message' => 'Este link de convite é inválido ou já foi utilizado. Por favor, solicite um novo link ao Prof. Atilio.'
        ]);
    }
    
    return view('invite', [
        'token'    => $tokenStr,
        'category' => $inviteToken->category ?? 'regular',
    ]);
});

// Rota para processar o formulário de cadastro por convite
Route::post('/invite/register', function (\Illuminate\Http\Request $request) {
    $tokenStr = $request->input('token');
    
    $inviteToken = \App\Models\InviteToken::where('token', $tokenStr)
        ->whereNull('used_at')
        ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
        ->first();
        
    if (!$inviteToken) {
        return redirect()->back()->withErrors(['token' => 'O convite fornecido não é válido ou já foi utilizado.']);
    }
    
    $isExternal = ($inviteToken->category ?? 'regular') === 'external';

    $validated = $request->validate([
        'name'           => 'required|string|max:255',
        'email'          => 'required|email|max:255|unique:users,email',
        'password'       => 'required|string|min:8|confirmed',
        'cpf'            => $isExternal ? 'required|string|max:14' : 'nullable|string|max:14',
        'registration'   => $isExternal ? 'nullable|string|max:255' : 'required|string|max:255',
        'institution'    => $isExternal ? 'nullable|string|max:255' : 'required|string|max:255',
        'campus'         => $isExternal ? 'nullable|string|max:255' : 'required|string|max:255',
        'academic_level' => 'required|string|in:Graduação,Especialização,Mestrado,Doutorado,Outro',
        'tcc_status'     => $isExternal ? 'nullable|string|in:none,defined,needs_evaluation' : 'required|string|in:none,defined,needs_evaluation',
        'tcc_title'      => 'nullable|string|max:255',
        'ic_title'       => 'nullable|string|max:255',
        'phone'          => 'required|string|max:255',
        'terms_accepted' => 'required|accepted',
        'period'         => $isExternal ? 'nullable|string|in:Matutino,Vespertino,Noturno' : 'required|string|in:Matutino,Vespertino,Noturno',
    ]);
    
    \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $inviteToken, $isExternal, &$activationToken) {
        // Marcar token como utilizado
        $inviteToken->update(['used_at' => now()]);
        
        // Criar usuário
        $user = \App\Models\User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => \Illuminate\Support\Facades\Hash::make($validated['password']),
            'role' => 'student',
        ]);
        
        // Criar estudante
        $activationToken = bin2hex(random_bytes(16));
        
        $tccStatus = $validated['tcc_status'] ?? 'none';
        $researchTopic = 'Geral';
        if ($tccStatus !== 'none' && !empty($validated['tcc_title'])) {
            $researchTopic = $validated['tcc_title'];
        } elseif (!empty($validated['ic_title'])) {
            $researchTopic = $validated['ic_title'];
        }

        $student = $user->student()->create([
            'registration'   => $validated['registration'] ?? null,
            'institution'    => $validated['institution'] ?? 'Não informada',
            'campus'         => $validated['campus'] ?? null,
            'academic_level' => $validated['academic_level'],
            'tcc_status'     => $tccStatus,
            'research_topic' => $researchTopic,
            'phone'          => $validated['phone'],
            'cpf'            => $validated['cpf'] ?? null,
            'terms_accepted'    => true,
            'terms_accepted_at' => now(),
            'status'         => 'awaiting_email_validation',
            'category'       => $isExternal ? 'external' : 'regular',
            'activation_token' => $activationToken,
            'period'         => $validated['period'] ?? null,
        ]);

        if ($tccStatus === 'defined' && !empty($validated['tcc_title'])) {
            $student->titles()->create([
                'type' => 'TCC',
                'title' => $validated['tcc_title'],
            ]);
        }

        if (!empty($validated['ic_title'])) {
            $student->titles()->create([
                'type' => 'IC',
                'title' => $validated['ic_title'],
            ]);
        }
        
        // Enviar e-mail de ativação para o aluno
        try {
            $activationUrl = str_starts_with(config('app.url'), 'https') 
                ? secure_url("/activate?token={$activationToken}") 
                : url("/activate?token={$activationToken}");
            $botUsername = ltrim(config('services.telegram.bot_username') ?? '', '@');
            $messageBody = "Olá, {$validated['name']}!\n\n" .
                "Seu pré-cadastro foi recebido na plataforma profatilio.com.br. Para prosseguir e validar seu endereço de e-mail, por favor clique no botão abaixo:\n\n" .
                "📢 **Importante: Receba Alertas Rápidos no Telegram**\n" .
                "A plataforma agora conta com integração oficial com o Telegram! Ao vincular sua conta ao bot @{$botUsername}, você receberá notificações em tempo real direto no seu celular sempre que o professor revisar ou assinar seus documentos acadêmicos.\n\n" .
                "Você poderá realizar a vinculação assim que sua conta for ativada pelo professor, acessando seu painel de controle.";

            \Illuminate\Support\Facades\Mail::to($validated['email'])->send(
                new \App\Mail\AppNotificationMail(
                    title: 'Ativação de Conta',
                    messageContent: $messageBody,
                    actionUrl: $activationUrl,
                    actionText: 'Validar E-mail',
                    subject: 'Ativação de Conta - Plataforma Prof. Atilio'
                )
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Erro ao enviar e-mail de ativação: " . $e->getMessage());
        }
    });
    
    return view('registration_status', [
        'status' => 'info',
        'title' => 'Pré-cadastro Realizado!',
        'message' => 'Sua conta foi pré-registrada com sucesso. Enviamos um link de confirmação para o seu e-mail.<br><br><strong>Próximos passos:</strong><br>1. Clique no link no e-mail para validar seu endereço.<br>2. Aguarde a aprovação manual do Prof. Atilio.<br>3. Você receberá um e-mail quando o acesso estiver liberado.',
    ]);
});

Route::get('/reenviar-confirmacao', function () {
    return view('resend_confirmation');
})->name('resend.confirmation');

Route::post('/reenviar-confirmacao', function (\Illuminate\Http\Request $request) {
    $validated = $request->validate([
        'email' => 'required|email|max:255',
    ]);

    $user = \App\Models\User::where('email', $validated['email'])->first();

    if (!$user) {
        return redirect()->back()->withErrors(['email' => 'Este e-mail não foi encontrado em nossa base de dados.']);
    }

    $student = $user->student;

    if (!$student || $student->status !== 'awaiting_email_validation') {
        return redirect()->back()->withErrors(['email' => 'Este e-mail já foi verificado ou a conta já está ativa.']);
    }

    // Cooldown logic to prevent email spamming: check if sent in the last 15 minutes
    $metadata = $user->metadata ?? [];
    $lastSentStr = $metadata['last_confirmation_email_sent_at'] ?? null;
    if ($lastSentStr) {
        try {
            $lastSent = \Illuminate\Support\Carbon::parse($lastSentStr);
            if ($lastSent->diffInMinutes(now()) < 15) {
                return redirect()->back()->withErrors(['email' => 'Você já solicitou o envio do e-mail recentemente. Por favor, aguarde alguns minutos antes de tentar novamente ou verifique sua caixa de spam.']);
            }
        } catch (\Exception $e) {
            // Em caso de erro na análise, prossegue
        }
    }

    // Generate activation token if missing
    if (empty($student->activation_token)) {
        $student->activation_token = bin2hex(random_bytes(16));
        $student->save();
    }

    try {
        $activationUrl = str_starts_with(config('app.url'), 'https') 
            ? secure_url("/activate?token={$student->activation_token}") 
            : url("/activate?token={$student->activation_token}");
        $botUsername = ltrim(config('services.telegram.bot_username') ?? '', '@');
        $messageBody = "Olá, {$user->name}!\n\n" .
            "Você solicitou o reenvio do link de validação para o seu endereço de e-mail na plataforma profatilio.com.br. Para prosseguir e validar seu e-mail, por favor clique no botão abaixo:\n\n" .
            "📢 **Importante: Receba Alertas Rápidos no Telegram**\n" .
            "A plataforma agora conta com integração oficial com o Telegram! Ao vincular sua conta ao bot @{$botUsername}, você receberá notificações em tempo real direto no seu celular sempre que o professor revisar ou assinar seus documentos acadêmicos.\n\n" .
            "Você poderá realizar a vinculação assim que sua conta for ativada pelo professor, acessando seu painel de controle.";

        \Illuminate\Support\Facades\Mail::to($user->email)->send(
            new \App\Mail\AppNotificationMail(
                title: 'Ativação de Conta (Reenvio)',
                messageContent: $messageBody,
                actionUrl: $activationUrl,
                actionText: 'Validar E-mail',
                subject: 'Ativação de Conta - Plataforma Prof. Atilio'
            )
        );

        // Update metadata
        $metadata['last_confirmation_email_sent_at'] = now()->toIso8601String();
        $user->metadata = $metadata;
        $user->save();

    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error("Erro ao reenviar e-mail de confirmação: " . $e->getMessage());
        return redirect()->back()->withErrors(['email' => 'Ocorreu um erro ao enviar o e-mail de confirmação. Por favor, tente novamente mais tarde.']);
    }

    return redirect()->back()->with('success', 'E-mail de confirmação reenviado com sucesso! Verifique sua caixa de entrada e pasta de spam.');
});

// Rota para ativar a conta pelo link de e-mail
Route::get('/activate', function (\Illuminate\Http\Request $request) {
    $activationToken = $request->query('token');
    
    if (!$activationToken) {
        return view('registration_status', [
            'status' => 'error',
            'title' => 'Ativação Inválida',
            'message' => 'Token de ativação ausente.'
        ]);
    }
    
    $student = \App\Models\Student::where('activation_token', $activationToken)->first();
        
    if (!$student) {
        return view('registration_status', [
            'status' => 'error',
            'title' => 'Ativação Inválida',
            'message' => 'Token de ativação inválido ou expirado. Se sua conta já foi aprovada, você pode realizar o login.'
        ]);
    }
    
    // Se a conta já estiver totalmente ativa
    if ($student->status === 'active') {
        return view('registration_status', [
            'status' => 'success',
            'title' => 'Conta Ativa!',
            'message' => 'Sua conta já foi verificada e ativada. Você já pode acessar a plataforma.',
            'link' => url('/portal/login'),
        ]);
    }
    
    // Se já estiver aguardando aprovação (caso de múltiplos cliques ou pre-fetch)
    if ($student->status === 'awaiting_advisor_approval') {
        return view('registration_status', [
            'status' => 'pending',
            'title' => 'E-mail Confirmado!',
            'message' => 'Seu endereço de e-mail já foi verificado com sucesso. Sua conta está aguardando a liberação manual do Prof. Atilio.',
        ]);
    }
    
    // Processo normal de ativação para awaiting_email_validation
    if ($student->status === 'awaiting_email_validation') {
        $student->update([
            'status' => 'awaiting_advisor_approval',
        ]);
        
        $studentName = $student->user ? $student->user->name : 'N/A';
        $studentEmail = $student->user ? $student->user->email : null;

        // Notificar o Prof. Atilio por e-mail
        try {
            \Illuminate\Support\Facades\Mail::to('contato@profatilio.com.br')->send(
                new \App\Mail\AppNotificationMail(
                    title: 'Nova Aprovação Pendente',
                    messageContent: "Olá Prof. Atilio,\n\nO orientando {$studentName} realizou a validação de e-mail e está aguardando a aprovação manual de sua conta no painel administrativo.",
                    actionUrl: url('/admin'),
                    actionText: 'Acessar Painel',
                    subject: 'Solicitação de Aprovação de Orientando'
                )
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Erro ao notificar professor sobre ativação pendente: " . $e->getMessage());
        }

        // Notificar o aluno que seu cadastro está aguardando aprovação
        if ($studentEmail) {
            try {
                \Illuminate\Support\Facades\Mail::to($studentEmail)->send(
                    new \App\Mail\AppNotificationMail(
                        title: 'Cadastro em Analise',
                        messageContent: "Ola, {$studentName}!\n\nSeu e-mail foi verificado com sucesso. Sua solicitacao de acesso foi encaminhada ao Prof. Atilio para aprovacao manual.\n\n**Importante:** voce ainda nao tem acesso a plataforma. O Prof. Atilio precisara liberar seu acesso manualmente. Assim que sua conta for aprovada, voce receberá um novo e-mail com as instrucoes de acesso.\n\nNao precisa ficar tentando entrar — voce sera avisado quando estiver tudo pronto!",
                        actionUrl: url('/'),
                        actionText: 'Voltar ao Site',
                        subject: "Cadastro em Analise — Plataforma Prof. Atilio"
                    )
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("Erro ao notificar aluno sobre aprovacao pendente: " . $e->getMessage());
            }
        }

        return view('registration_status', [
            'status' => 'pending',
            'title' => 'E-mail Verificado!',
            'message' => 'Seu e-mail foi confirmado com sucesso. Sua conta foi encaminhada para o Prof. Atilio e está <strong>aguardando aprovação manual</strong> para liberar o acesso.',
        ]);
    }

    return view('registration_status', [
        'status' => 'error',
        'title' => 'Status Inválido',
        'message' => 'O status atual da sua conta não é reconhecido.'
    ]);
});

// Páginas de instrução de instalação do PWA — uma pro Portal do Aluno,
// outra dedicada à Gestão de Alunos (painel do professor).
Route::get('/instalar-app', fn () => view('instalar_app'))->name('instalar.app');
Route::get('/instalar-app-gestao', fn () => view('instalar_app_gestao'))->name('instalar.app.gestao');

// Portal Público do Orientador
Route::get('/orientador', function () {
    $stats = [
        'total_students' => \App\Models\Student::where('status', 'active')->count(),
        'concluded'      => \App\Models\DocumentSubmission::where('status', 'like', 'Processo Concluído%')->count(),
        'document_types' => \App\Models\DocumentSubmission::distinct('document_type')->count('document_type'),
        'years_active'   => max(1, now()->year - 2022),
        'by_type'        => \App\Models\DocumentSubmission::selectRaw('document_type, count(*) as total')
            ->groupBy('document_type')
            ->orderByDesc('total')
            ->get(),
    ];
    return view('orientador_profile', compact('stats'));
})->name('orientador.profile');

// Relatório de Orientação por Aluno (PDF) — protegido para admin
Route::get('/admin/relatorio-aluno/{student}', function (\App\Models\Student $student) {
    if (auth()->user()?->role !== 'admin') {
        abort(403);
    }

    $submissions = $student->submissions()->with('student')->orderBy('created_at', 'desc')->get();

    $html = view('student_orientation_report', compact('student', 'submissions'))->render();

    if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4', 'portrait');
        $name = \Illuminate\Support\Str::slug($student->user->name ?? 'aluno');
        return $pdf->download("relatorio_orientacao_{$name}.pdf");
    }

    return response($html);
})->middleware(['web', 'auth'])->name('admin.relatorio-aluno');

// Certificado de Conclusão (PDF público com QR de validação)
Route::get('/documento/certificado/{id}', function ($id) {
    $submission = \App\Models\DocumentSubmission::findOrFail($id);
    $student    = $submission->student()->with('user', 'titles')->first();

    $eligibleTypes = [
        'Artigo Científico',
        'Trabalho de Congresso',
        'Relato de Caso',
        'Relatório de Aula',
        'Relatório de Necropsia',
    ];

    if (!in_array($submission->document_type, $eligibleTypes)) {
        abort(404, 'Certificado não disponível para este tipo de documento.');
    }

    if (!str_starts_with($submission->status, 'Processo Concluído')) {
        abort(404, 'O processo ainda não foi concluído.');
    }

    $certHash = hash('sha256', $submission->process_id . '|' . $submission->updated_at . '|certificado');

    $html = view('completion_certificate', compact('submission', 'student', 'certHash'))->render();

    if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4', 'landscape');
        return $pdf->stream("certificado_{$submission->process_id}.pdf");
    }

    return response($html);
})->name('documento.certificado');

// Validação pública do certificado
Route::get('/documento/certificado/validar/{hash}', function ($hash) {
    $submissions = \App\Models\DocumentSubmission::where('status', 'like', 'Processo Concluído%')->get();
    $found = null;
    foreach ($submissions as $sub) {
        $expected = hash('sha256', $sub->process_id . '|' . $sub->updated_at . '|certificado');
        if (hash_equals($expected, $hash)) {
            $found = $sub;
            break;
        }
    }

    if (!$found) {
        return view('registration_status', [
            'status'  => 'error',
            'title'   => 'Certificado Inválido',
            'message' => 'Este QR code não corresponde a nenhum certificado válido na plataforma.',
        ]);
    }

    $student = $found->student()->with('user')->first();
    return view('registration_status', [
        'status'  => 'success',
        'title'   => 'Certificado Autêntico',
        'message' => "Certificado válido emitido para {$student?->user?->name} — Processo {$found->process_id} ({$found->document_type}). Concluído em " . $found->updated_at?->format('d/m/Y') . '.',
    ]);
})->name('documento.certificado.validar');

// ── Auditoria Digital e Validação Forense (Fluxo 8) ──────────────────────────
// Rotas de Auditoria Digital e Validação Forense (Fluxo 8)
Route::get('/documento/recibo/{id}', [\App\Http\Controllers\DocumentAuditController::class, 'receipt'])->name('documento.recibo');
Route::get('/documento/validar/{hash}', [\App\Http\Controllers\DocumentAuditController::class, 'validateSignature'])->name('documento.validar');
Route::get('/documento/download/{id}/{type}', [\App\Http\Controllers\DocumentAuditController::class, 'downloadFile'])->name('documento.download');
Route::get('/documento/download-path', [\App\Http\Controllers\DocumentAuditController::class, 'downloadByPath'])->name('documento.download-path');
Route::get('/documento/linha-tempo/{id}', [\App\Http\Controllers\DocumentAuditController::class, 'timeline'])->name('documento.timeline')->middleware('auth');

// Rota do Verificador e Validador Público de Assinaturas (PAdES/ICP-Brasil)
Route::get('/verificador', [\App\Http\Controllers\DocumentAuditController::class, 'showVerifier'])->name('documento.verificador');
Route::post('/verificador', [\App\Http\Controllers\DocumentAuditController::class, 'verifyUpload'])->name('documento.verificador.post');

// Rota de Webhook do Telegram (Notificações)
Route::post('/telegram/webhook', [\App\Http\Controllers\TelegramWebhookController::class, 'handle'])->name('telegram.webhook');

// Rotas de Integração da Assinatura Eletrônica Gov.br (Grátis)
Route::get('/documento/govbr/bulk-redirect', [\App\Http\Controllers\DocumentAuditController::class, 'govbrBulkRedirect'])->name('documento.govbr.bulk.redirect');
Route::get('/documento/govbr/redirect/{id}', [\App\Http\Controllers\DocumentAuditController::class, 'govbrRedirect'])->name('documento.govbr.redirect');
Route::get('/documento/govbr/callback', [\App\Http\Controllers\DocumentAuditController::class, 'govbrCallback'])->name('documento.govbr.callback');

// Rota de Sitemap Dinâmico para indexadores (SEO)
Route::get('/sitemap.xml', function () {
    $today = now()->format('Y-m-d');

    // Páginas públicas estáticas — sempre presentes
    $urls = [
        ['url' => 'https://profatilio.com.br/',             'priority' => '1.0', 'freq' => 'weekly',  'lastmod' => $today],
        ['url' => 'https://profatilio.com.br/normas-abnt',  'priority' => '0.9', 'freq' => 'monthly', 'lastmod' => '2026-06-01'],
        ['url' => 'https://profatilio.com.br/orientador',   'priority' => '0.8', 'freq' => 'weekly',  'lastmod' => $today],
        ['url' => 'https://profatilio.com.br/verificador',  'priority' => '0.7', 'freq' => 'monthly', 'lastmod' => '2026-06-01'],
        ['url' => 'https://profatilio.com.br/instalar-app', 'priority' => '0.5', 'freq' => 'monthly', 'lastmod' => '2026-06-05'],
    ];

    // Recibos forenses (IC) — query isolada
    try {
        \App\Models\DocumentSubmission::where('document_type', 'Termo de Aceite de IC')
            ->select('metadata', 'updated_at')
            ->each(function ($sub) use (&$urls, $today) {
                $hash = $sub->metadata['forensic_hash'] ?? null;
                if ($hash && preg_match('/^[a-f0-9]{64}$/', $hash)) {
                    $urls[] = [
                        'url'      => 'https://profatilio.com.br/documento/validar/' . $hash,
                        'priority' => '0.6',
                        'freq'     => 'never',
                        'lastmod'  => $sub->updated_at?->format('Y-m-d') ?? $today,
                    ];
                }
            });
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::warning('Sitemap: erro ao carregar recibos forenses — ' . $e->getMessage());
    }

    // Certificados de conclusão elegíveis — query isolada
    try {
        $eligible = ['Artigo Científico', 'Trabalho de Congresso', 'Relato de Caso', 'Relatório de Aula', 'Relatório de Necropsia'];
        \App\Models\DocumentSubmission::where('status', 'like', 'Processo Concluído%')
            ->whereIn('document_type', $eligible)
            ->select('process_id', 'updated_at')
            ->each(function ($sub) use (&$urls, $today) {
                if (!$sub->process_id) return;
                $certHash = hash('sha256', $sub->process_id . '|' . ($sub->updated_at ?? '') . '|certificado');
                $urls[] = [
                    'url'      => 'https://profatilio.com.br/documento/certificado/validar/' . $certHash,
                    'priority' => '0.5',
                    'freq'     => 'never',
                    'lastmod'  => $sub->updated_at?->format('Y-m-d') ?? $today,
                ];
            });
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::warning('Sitemap: erro ao carregar certificados — ' . $e->getMessage());
    }

    // Gera o XML — sem nenhum output antes da declaração
    $entries = '';
    foreach ($urls as $u) {
        $entries .= '<url>'
            . '<loc>' . htmlspecialchars($u['url'], ENT_XML1, 'UTF-8') . '</loc>'
            . '<lastmod>' . htmlspecialchars($u['lastmod'], ENT_XML1, 'UTF-8') . '</lastmod>'
            . '<changefreq>' . $u['freq'] . '</changefreq>'
            . '<priority>' . $u['priority'] . '</priority>'
            . '</url>';
    }

    $xml = '<?xml version="1.0" encoding="UTF-8"?>'
         . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
         . $entries
         . '</urlset>';

    return response($xml, 200, [
        'Content-Type'  => 'application/xml; charset=UTF-8',
        'Cache-Control' => 'public, max-age=3600',
    ]);
})->name('sitemap');

// Rota para Geração e Exportação do Relatório Consolidado de Orientações (PDF)
Route::get('/admin/relatorio-consolidado', function () {
    if (auth()->user()?->role !== 'admin') {
        abort(403);
    }

    $students = \App\Models\Student::with('user', 'submissions')->where('status', 'active')->get();
    
    $reportData = [];
    foreach ($students as $student) {
        $deadlineInfo = $student->getICDeadlineInfo();
        $reportData[] = [
            'name' => $student->user->name ?? 'N/A',
            'registration' => $student->registration ?? 'N/A',
            'institution' => $student->institution ?? 'N/A',
            'academic_level' => $student->academic_level ?? 'N/A',
            'research_topic' => $student->research_topic ?? 'N/A',
            'has_ic' => $deadlineInfo['has_ic'],
            'quarter' => $deadlineInfo['quarter'],
            'label' => $deadlineInfo['label'],
            'status' => $deadlineInfo['status'],
            'due_date' => $deadlineInfo['due_date'] ? \Carbon\Carbon::parse($deadlineInfo['due_date'])->format('d/m/Y') : '-',
        ];
    }

    $html = view('admin_consolidated_report', [
        'data' => $reportData,
        'generated_at' => now()->format('d/m/Y H:i:s'),
    ])->render();

    if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4', 'landscape');
        return $pdf->download('relatorio_consolidado_orientacoes_' . now()->format('Ymd_His') . '.pdf');
    }
    
    return response($html);
})->middleware(['web', 'auth'])->name('admin.relatorio-consolidado');

// Rota de fallback nomeada 'password.reset' para evitar exceções em notificações do Laravel.
// A rota de reset do Filament é protegida por ValidateSignature, então redirecionamos para a
// URL *assinada* do painel correto (getResetPasswordUrl) — um redirect sem assinatura resultaria
// em "Acesso Negado" (403).
Route::get('/password-reset/{token}', function (\Illuminate\Http\Request $request, $token) {
    $email = $request->query('email');
    $user = $email ? \App\Models\User::where('email', $email)->first() : null;

    if (! $user) {
        abort(404);
    }

    $panelId = $user->role === 'admin' ? 'admin' : 'portal';

    return redirect()->to(
        \Filament\Facades\Filament::getPanel($panelId)->getResetPasswordUrl($token, $user)
    );
})->name('password.reset');

// --- Portal Público de Assinatura Externa de Documentos de Estágio ---
Route::get('/estagio/turma/{token}', [\App\Http\Controllers\ExternalClassAccessController::class, 'show'])
    ->middleware('throttle:10,1')->name('estagio.turma');
Route::post('/estagio/turma/{token}/verificar', [\App\Http\Controllers\ExternalClassAccessController::class, 'verify'])
    ->middleware('throttle:5,1')->name('estagio.turma.verificar');
Route::get('/estagio/turma/{token}/sair', [\App\Http\Controllers\ExternalClassAccessController::class, 'logout'])
    ->name('estagio.turma.sair');
Route::get('/estagio/turma/{token}/documentos', [\App\Http\Controllers\ExternalDocumentController::class, 'index'])
    ->middleware('throttle:20,1')->name('estagio.documentos');
Route::post('/estagio/turma/{token}/documentos', [\App\Http\Controllers\ExternalDocumentController::class, 'store'])
    ->middleware('throttle:20,1')->name('estagio.documentos.store');
Route::get('/estagio/turma/{token}/documentos/{submission}/assinado', [\App\Http\Controllers\ExternalDocumentController::class, 'downloadSignedViaPortal'])
    ->middleware('throttle:20,1')->name('estagio.documentos.assinado.portal');
// Link enviado por e-mail ao aluno quando o documento é assinado: URL assinada
// (HMAC do Laravel) com expiração embutida — não exige sessão/login, mas
// também não funciona mais depois de vencida (padrão: 15 dias).
Route::get('/estagio/documento/{submission}/assinado', [\App\Http\Controllers\ExternalDocumentController::class, 'downloadSignedViaEmailLink'])
    ->middleware(['throttle:20,1', 'signed'])->name('estagio.documento.assinado.email');




