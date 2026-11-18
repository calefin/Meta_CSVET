<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;
use App\Mail\ConsultationSubmitted;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/normas-abnt', function () {
    return view('normas-abnt');
})->name('normas.abnt');

// Consulta Criptografada Zero-Knowledge (LGPD) de Devolutivas de Trabalhos e Espelhos de Provas
Route::get('/consulta-avaliacao', [\App\Http\Controllers\ExamMirrorController::class, 'index'])->name('exam.mirror');
Route::post('/consulta-avaliacao', [\App\Http\Controllers\ExamMirrorController::class, 'consult'])->name('exam.mirror.consult');
Route::redirect('/espelho-prova', '/consulta-avaliacao');
Route::redirect('/devolutivas', '/consulta-avaliacao');
Route::redirect('/devolutiva', '/consulta-avaliacao');

// Receber solicitações de consultoria/parceria da Landing Page
Route::post('/submit-consultation', function (\Illuminate\Http\Request $request) {
    // 1. Proteção Honeypot: Se o campo oculto estiver preenchido, é um bot/robô
    if ($request->filled('secondary_address')) {
        return response()->json([
            'status' => 'success',
            'message' => 'Sua solicitação foi enviada com sucesso! Entraremos em contato em breve.',
        ]);
    }

    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|max:255',
        'phone' => 'nullable|string|max:255',
        'request_type' => 'required|string|max:255',
        'message' => 'required|string|max:2000',
    ]);

    $consultation = \App\Models\ConsultationRequest::create($validated);

    // 2. Enviar e-mail de notificação para o destinatário configurado (em fila)
    try {
        Mail::to('contato@profatilio.com.br')->queue(new ConsultationSubmitted($consultation));
    } catch (\Exception $e) {
        // Registra o erro no log, mas não impede a resposta de sucesso para o cliente
        \Illuminate\Support\Facades\Log::error('Erro ao enviar e-mail de consultoria: ' . $e->getMessage());
    }

    // 3. Enviar notificação via Telegram para os administradores vinculados
    try {
        $telegramService = app(\App\Services\TelegramService::class);
        $adminUsers = \App\Models\User::where('role', 'admin')
            ->whereNotNull('telegram_chat_id')
            ->where('telegram_chat_id', '!=', '')
            ->get();

        if ($adminUsers->isNotEmpty()) {
            $telegramMsg = "<b>🔔 Nova Solicitação de Atendimento / Orçamento Geral!</b>\n\n" .
                "👤 <b>Nome:</b> {$consultation->name}\n" .
                "✉️ <b>E-mail:</b> {$consultation->email}\n" .
                "📞 <b>Telefone:</b> " . ($consultation->phone ?? 'Não informado') . "\n" .
                "🏷️ <b>Tipo:</b> {$consultation->request_type}\n\n" .
                "💬 <b>Mensagem:</b>\n<i>\"{$consultation->message}\"</i>\n\n" .
                "📅 <b>Data:</b> " . now()->format('d/m/Y H:i');

            foreach ($adminUsers as $admin) {
                $telegramService->sendMessage((string)$admin->telegram_chat_id, $telegramMsg);
            }
        }
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Erro ao enviar notificação do Telegram de consultoria: ' . $e->getMessage());
    }

    return response()->json([
        'status' => 'success',
        'message' => 'Sua solicitação foi enviada com sucesso! Entraremos em contato em breve.',
    ]);
})->name('submit.consultation');

// Receber solicitações de agendamento de consulta (imunologia veterinária / animais não convencionais)
Route::post('/agendar-consulta', function (\Illuminate\Http\Request $request) {
    // 1. Proteção Honeypot: Se o campo oculto estiver preenchido, é um bot/robô
    if ($request->filled('secondary_address')) {
        return response()->json([
            'status' => 'success',
            'message' => 'Solicitação enviada! Entraremos em contato em breve.',
        ]);
    }

    $validated = $request->validate([
        'requester_name' => 'required|string|max:255',
        'requester_email' => 'required|email|max:255',
        'requester_phone' => 'required|string|max:255',
        'animal_type' => 'required|in:convencional,nao_convencional',
        'species' => 'required|string|max:255',
        'complaint' => 'required|string|max:2000',
    ]);

    $appointment = \App\Models\AppointmentRequest::create($validated);

    $animalTypeLabel = $appointment->animal_type === 'convencional' ? 'Convencional (cão/gato)' : 'Não Convencional (exótico/silvestre)';

    // 2. Enviar e-mail de notificação (síncrono: o professor quer saber na hora,
    // não depender do cron da fila — mesmo padrão já aplicado em SupportTicketMessage)
    try {
        Mail::to('contato@profatilio.com.br')->send(
            (new \App\Mail\AppNotificationMail(
                title: "Nova solicitação de consulta: {$appointment->species}",
                messageContent: "👤 Nome: {$appointment->requester_name}\n"
                    . "✉️ E-mail: {$appointment->requester_email}\n"
                    . "📞 Telefone: {$appointment->requester_phone}\n"
                    . "🐾 Tipo de animal: {$animalTypeLabel}\n"
                    . "🔬 Espécie: {$appointment->species}\n\n"
                    . "💬 Queixa principal:\n\"{$appointment->complaint}\"",
                actionUrl: url('/admin/appointment-requests'),
                actionText: 'Ver Solicitação',
            ))->replyTo($appointment->requester_email, $appointment->requester_name)
        );
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('Erro ao enviar e-mail de solicitação de consulta: ' . $e->getMessage());
    }

    // 3. Enviar notificação via Telegram para os administradores vinculados
    try {
        $telegramService = app(\App\Services\TelegramService::class);
        $adminUsers = \App\Models\User::where('role', 'admin')
            ->whereNotNull('telegram_chat_id')
            ->where('telegram_chat_id', '!=', '')
            ->get();

        if ($adminUsers->isNotEmpty()) {
            $telegramMsg = "<b>🩺 Nova Solicitação de Agendamento de Consulta!</b>\n\n"
                . "👤 <b>Nome:</b> {$appointment->requester_name}\n"
                . "✉️ <b>E-mail:</b> {$appointment->requester_email}\n"
                . "📞 <b>Telefone:</b> {$appointment->requester_phone}\n"
                . "🐾 <b>Tipo de animal:</b> {$animalTypeLabel}\n"
                . "🔬 <b>Espécie:</b> {$appointment->species}\n\n"
                . "💬 <b>Queixa:</b>\n<i>\"{$appointment->complaint}\"</i>\n\n"
                . "📅 <b>Data:</b> " . now()->format('d/m/Y H:i');

            foreach ($adminUsers as $admin) {
                $telegramService->sendMessage((string) $admin->telegram_chat_id, $telegramMsg);
            }
        }
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('Erro ao enviar notificação do Telegram de solicitação de consulta: ' . $e->getMessage());
    }

    return response()->json([
        'status' => 'success',
        'message' => 'Solicitação enviada! Entraremos em contato em breve.',
    ]);
})->name('submit.appointment');

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
        // Convite nominal (ex.: triagem de revisão sistemática): e-mail travado.
        'email'    => $inviteToken->email,
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

    if (filled($inviteToken->email) && mb_strtolower(trim($validated['email'])) !== mb_strtolower(trim($inviteToken->email))) {
        return redirect()->back()->withInput()->withErrors(['email' => 'Este convite foi enviado para ' . $inviteToken->email . '. Use esse e-mail.']);
    }
    
    \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $inviteToken, $isExternal, &$activationToken) {
        // Marcar token como utilizado
        $inviteToken->update(['used_at' => now()]);
        
        // Criar usuário
        $user = \App\Models\User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => \Illuminate\Support\Facades\Hash::make($validated['password']),
            'role' => 'student',
            // Convite da triagem: o professor já escolheu o aluno — conta liberada ao validar o e-mail.
            'metadata' => $inviteToken->auto_approve ? ['auto_approve' => true] : null,
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
    
    // Convite nominal do professor (triagem de revisão sistemática): libera direto.
    if ($student->status === 'awaiting_email_validation' && $student->user) {
        // E-mail comprovado: agora sim vincula os convites de triagem e a inscrição da seleção.
        \App\Models\SystematicReviewScreener::linkVerifiedUser($student->user);
        \App\Models\ResearchApplication::linkVerifiedUser($student->user);
    }

    if ($student->status === 'awaiting_email_validation' && !empty($student->user?->metadata['auto_approve'])) {
        $student->update(['status' => 'active']);

        return view('registration_status', [
            'status' => 'success',
            'title' => 'Conta Ativada!',
            'message' => 'Seu e-mail foi confirmado e sua conta de aluno já está liberada. Entre no Portal do Aluno, assine o termo e comece em <strong>"Triagem Cega"</strong>.',
            'link' => url('/portal/login'),
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

// Página de instrução de instalação do PWA
Route::get('/instalar-app', fn () => view('instalar_app'))->name('instalar.app');

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

// ── Certificados de Cursos CSvet ──────────────────────────────────────────────

// Admin: download do rascunho não assinado para assinar com e-CPF
Route::get('/admin/certificado-curso/{id}/pdf-rascunho', function ($id) {
    if (auth()->user()?->role !== 'admin') {
        abort(403);
    }

    $request = \App\Models\CertificateRequest::with(['student.user', 'course'])->findOrFail($id);

    if (!$request->cert_hash) {
        $request->cert_hash = hash('sha256', 'cert-' . $request->id . '-' . \Illuminate\Support\Str::random(32));
        $request->save();
    }

    $html = view('certificate_curso', compact('request'))->render();

    if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)
            ->setPaper('a4', 'landscape');
        $name = \Illuminate\Support\Str::slug($request->student->user->name ?? 'aluno');
        return $pdf->download("certificado_rascunho_{$name}.pdf");
    }

    return response($html);
})->middleware(['web', 'auth'])->name('admin.certificado-curso.rascunho');

// Portal: aluno externo baixa o certificado assinado
Route::get('/portal/certificado/{id}/download', function ($id) {
    $certRequest = \App\Models\CertificateRequest::with(['student.user', 'course'])->findOrFail($id);

    $student = \App\Models\Student::where('user_id', auth()->id())->first();
    if (!$student || $certRequest->student_id !== $student->id) {
        abort(403);
    }

    if ($certRequest->status !== 'available' || !$certRequest->signed_pdf_path) {
        abort(404, 'Certificado ainda não disponível.');
    }

    $path = storage_path('app/' . $certRequest->signed_pdf_path);
    if (!file_exists($path)) {
        abort(404, 'Arquivo não encontrado.');
    }

    $name = \Illuminate\Support\Str::slug($certRequest->student->user->name ?? 'aluno');
    return response()->download($path, "certificado_{$name}.pdf", ['Content-Type' => 'application/pdf']);
})->middleware(['web', 'auth'])->name('portal.certificado.download');

// Portal: aluno baixa em PDF o conteúdo de uma etapa da trilha (com seus dados
// impressos no rodapé para evitar redistribuição/plágio do material)
Route::get('/portal/curso/modulo/{module}/pdf', function ($moduleId) {
    $user = auth()->user();
    if (!$user || $user->role !== 'student') {
        abort(403);
    }

    $student = \App\Models\Student::where('user_id', $user->id)->first();
    $module = \App\Models\CourseModule::with('course')->findOrFail($moduleId);

    $enrollment = \App\Models\CourseEnrollment::where('student_id', $student?->id)
        ->where('course_id', $module->course_id)
        ->first();

    if (!$enrollment) {
        abort(403, 'Você não está matriculado neste curso.');
    }

    // Só libera o PDF de etapas já desbloqueadas (concluídas ou a atual) —
    // mantém a mesma trava sequencial da trilha.
    $completedIds = $enrollment->completions()->pluck('course_module_id')->all();
    $current = $enrollment->currentModule();
    $unlocked = in_array($module->id, $completedIds, true) || $current?->id === $module->id;

    if (!$unlocked) {
        abort(403, 'Esta etapa ainda não foi liberada.');
    }

    $html = view('course_module_pdf', compact('module', 'student'))->render();

    if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4', 'portrait');
        $name = \Illuminate\Support\Str::slug(($module->course->name ?? 'curso') . '-' . $module->title);
        return $pdf->download("{$name}.pdf");
    }

    return response($html);
})->middleware(['web', 'auth'])->name('portal.curso.modulo.pdf');

// Validação pública do certificado de curso (via QR)
Route::get('/certificado-curso/validar/{hash}', function ($hash) {
    $cert = \App\Models\CertificateRequest::where('cert_hash', $hash)
        ->where('status', 'available')
        ->with(['student.user', 'course'])
        ->first();

    if (!$cert) {
        return view('registration_status', [
            'status'  => 'error',
            'title'   => 'Certificado Inválido',
            'message' => 'Este QR code não corresponde a nenhum certificado válido ou o certificado ainda não foi emitido.',
        ]);
    }

    return view('registration_status', [
        'status'  => 'success',
        'title'   => 'Certificado Autêntico ✓',
        'message' => "Certificado válido emitido para <strong>{$cert->student->user->name}</strong> — Curso: {$cert->course->name} ({$cert->course->workload}h). Emitido por CSvet Editora, Cursos e Treinamentos em {$cert->available_at?->format('d/m/Y')}.",
    ]);
})->name('certificado-curso.validar');

// ── Auditoria Digital e Validação Forense (Fluxo 8) ──────────────────────────
// Rotas de Auditoria Digital e Validação Forense (Fluxo 8)
Route::get('/documento/recibo/{id}', [\App\Http\Controllers\DocumentAuditController::class, 'receipt'])->name('documento.recibo');
Route::get('/documento/validar/{hash}', [\App\Http\Controllers\DocumentAuditController::class, 'validateSignature'])->name('documento.validar');
Route::get('/documento/download/{id}/{type}', [\App\Http\Controllers\DocumentAuditController::class, 'downloadFile'])->name('documento.download');
Route::get('/documento/download-path', [\App\Http\Controllers\DocumentAuditController::class, 'downloadByPath'])->name('documento.download-path');
Route::get('/documento/linha-tempo/{id}', [\App\Http\Controllers\DocumentAuditController::class, 'timeline'])->name('documento.timeline')->middleware('auth');

// Download de anexo de e-mail em massa (link assinado, sem exigir login do aluno)
Route::get('/email/anexo/download', [\App\Http\Controllers\EmailAttachmentController::class, 'download'])
    ->name('email.anexo.download')
    ->middleware('signed');

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
        ['url' => 'https://profatilio.com.br/necropsia-veterinaria', 'priority' => '0.9', 'freq' => 'monthly', 'lastmod' => '2026-11-01'],
        ['url' => 'https://profatilio.com.br/solicitar-necropsia', 'priority' => '0.8', 'freq' => 'monthly', 'lastmod' => $today],
        ['url' => 'https://profatilio.com.br/solicitar-atendimento', 'priority' => '0.9', 'freq' => 'weekly',  'lastmod' => $today],
        ['url' => 'https://profatilio.com.br/instalar-app', 'priority' => '0.5', 'freq' => 'monthly', 'lastmod' => '2026-06-05'],
        ['url' => 'https://profatilio.com.br/blog',         'priority' => '0.8', 'freq' => 'weekly',  'lastmod' => $today],
        ['url' => 'https://profatilio.com.br/na-midia',     'priority' => '0.6', 'freq' => 'monthly', 'lastmod' => $today],
    ];

    // Posts do blog publicados — query isolada
    try {
        \App\Models\BlogPost::published()
            ->select('slug', 'updated_at')
            ->each(function ($post) use (&$urls, $today) {
                $urls[] = [
                    'url'      => 'https://profatilio.com.br/blog/' . $post->slug,
                    'priority' => '0.6',
                    'freq'     => 'monthly',
                    'lastmod'  => $post->updated_at?->format('Y-m-d') ?? $today,
                ];
            });
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::warning('Sitemap: erro ao carregar posts do blog — ' . $e->getMessage());
    }

    // Chamadas de seleção de alunos abertas — query isolada
    try {
        \App\Models\ResearchCall::where('status', \App\Models\ResearchCall::STATUS_OPEN)
            ->select('slug', 'updated_at')
            ->each(function ($call) use (&$urls, $today) {
                $urls[] = [
                    'url'      => 'https://profatilio.com.br/selecao/' . $call->slug,
                    'priority' => '0.8',
                    'freq'     => 'weekly',
                    'lastmod'  => $call->updated_at?->format('Y-m-d') ?? $today,
                ];
            });
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::warning('Sitemap: erro ao carregar chamadas de seleção — ' . $e->getMessage());
    }

    // Recibos forenses (Termo de Aceite de IC) ficam fora do sitemap desde 2026-11-01:
    // página administrativa com nome/RGM mascarados de aluno, sem valor de SEO (e com noindex).

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

// --- Página de SEO da necropsia (serviço do Prof. Atilio, sem caracterizar laboratório) ---
Route::get('/necropsia-veterinaria', function () {
    $fmt = fn (float $v) => 'R$ ' . number_format($v, 2, ',', '.');
    $bands = [
        ['label' => 'Até 10 kg', 'price' => \App\Models\NecropsyRequest::calculatePricing(10, false)['price']],
        ['label' => 'De 10 a 25 kg', 'price' => \App\Models\NecropsyRequest::calculatePricing(25, false)['price']],
        ['label' => 'De 25 a 45 kg', 'price' => \App\Models\NecropsyRequest::calculatePricing(45, false)['price']],
        ['label' => 'Acima de 45 kg', 'price' => \App\Models\NecropsyRequest::calculatePricing(45.01, false)['price']],
    ];

    $faq = [
        ['q' => 'Quanto custa uma necropsia veterinária?',
         'a' => 'O valor depende do peso do animal: ' . collect($bands)->map(fn ($b) => mb_strtolower($b['label']) . ', ' . $fmt($b['price']))->implode('; ')
             . '. Pedidos feitos por médico veterinário têm 10% de desconto. Relatório macroscópico, exame histopatológico e fotodocumentação já estão incluídos.'],
        ['q' => 'Como conservar o corpo do animal até a necropsia?',
         'a' => 'Mantenha o corpo refrigerado (na geladeira, nunca no freezer) e entre em contato o quanto antes. O congelamento e a demora alteram os tecidos e podem prejudicar o resultado do exame.'],
        ['q' => 'Quais animais podem passar por necropsia?',
         'a' => 'Cães, gatos, aves, répteis, roedores e outras espécies, inclusive animais não convencionais.'],
        ['q' => 'Quem pode pedir a necropsia?',
         'a' => 'O tutor ou responsável pelo animal, ou o médico veterinário que acompanha o caso, informando o CRMV (com 10% de desconto).'],
        ['q' => 'O animal foi eutanasiado. Ainda dá para fazer a necropsia?',
         'a' => 'Sim. Basta informar no pedido que houve eutanásia.'],
        ['q' => 'Tenho plano de cremação. Posso fazer a necropsia?',
         'a' => 'Sim. Informe no pedido o nome da empresa do plano de cremação.'],
        ['q' => 'Como é a entrega do animal e qual o prazo do relatório?',
         'a' => 'A entrega do animal e os prazos são combinados diretamente com o Prof. Atilio, que entra em contato depois do seu pedido.'],
    ];

    $whatsappUrl = null;
    try {
        $admin = \App\Models\User::where('role', 'admin')->first();
        $raw = $admin->metadata['whatsapp_number'] ?? config('services.whatsapp.phone');
        $digits = preg_replace('/\D/', '', (string) $raw);
        if (strlen($digits) >= 10) {
            if (strlen($digits) <= 11 && ! str_starts_with($digits, '55')) {
                $digits = '55' . $digits;
            }
            $whatsappUrl = "https://wa.me/{$digits}?text=" . rawurlencode('Olá, Prof. Atilio! Gostaria de informações sobre necropsia.');
        }
    } catch (\Throwable $e) {
        // Sem WhatsApp configurado: a página mostra só o botão do formulário.
    }

    return view('necropsia', compact('bands', 'faq', 'whatsappUrl'));
})->name('necropsy.landing');

// --- Fluxo Público de Orçamento e Autorização de Necropsia ---
Route::get('/solicitar-necropsia', [\App\Http\Controllers\NecropsyRequestController::class, 'index'])->name('necropsy.orcamento');
Route::post('/solicitar-necropsia', [\App\Http\Controllers\NecropsyRequestController::class, 'store'])->name('necropsy.orcamento.store');
// Link fácil para quem já combinou a necropsia diretamente com o Prof. Atilio.
Route::get('/autorizar-necropsia', [\App\Http\Controllers\NecropsyRequestController::class, 'direct'])->name('necropsy.direto');
Route::get('/solicitar-necropsia/autorizar/{token}', [\App\Http\Controllers\NecropsyRequestController::class, 'showAuthorization'])->name('necropsy.autorizar');
Route::post('/solicitar-necropsia/autorizar/{token}', [\App\Http\Controllers\NecropsyRequestController::class, 'saveAuthorization'])->name('necropsy.autorizar.store');
Route::get('/solicitar-necropsia/sucesso/{token}', [\App\Http\Controllers\NecropsyRequestController::class, 'showSuccess'])->name('necropsy.sucesso');
Route::get('/solicitar-necropsia/pdf/{token}', [\App\Http\Controllers\NecropsyRequestController::class, 'downloadPdf'])->name('necropsy.pdf');

// --- Check-in de Frequência das Aulas Práticas de Necropsia (QR Code) ---
Route::get('/necropsia-aula/checkin/{token}', [\App\Http\Controllers\NecropsyClassCheckInController::class, 'show'])->name('necropsy.class.checkin');
Route::post('/necropsia-aula/checkin/{token}', [\App\Http\Controllers\NecropsyClassCheckInController::class, 'store'])->name('necropsy.class.checkin.store');

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

// --- Revisão de Artigo Científico (revisores externos, sem login, link com token) ---
// --- Revisão de artigos/livros: página do participante ---
// Por link pessoal (token) ...
Route::controller(\App\Http\Controllers\ArticleReviewController::class)->group(function () {
    Route::get('/revisao-artigo/cadastro/{token}', 'signupForm')
        ->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:article-review-read')->name('article-review.signup');
    Route::post('/revisao-artigo/cadastro/{token}', 'signupStore')
        ->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:article-review-auth')->name('article-review.signup.store');

    Route::prefix('/revisao-artigo/{token}')->where(['token' => '[A-Za-z0-9]{64}'])->group(function () {
        Route::get('/', 'show')->middleware('throttle:article-review-read')->name('article-review.show');
        Route::get('/arquivo', 'download')->middleware('throttle:article-review-download')->name('article-review.download');
        Route::get('/complementar/{file}', 'downloadFile')->whereNumber('file')->middleware('throttle:article-review-read')->name('article-review.file');
        Route::post('/', 'store')->middleware('throttle:article-review-write')->name('article-review.store');
        Route::post('/ficha', 'saveProfile')->middleware('throttle:article-review-write')->name('article-review.profile');
        Route::post('/acesso', 'access')->middleware('throttle:article-review-auth')->name('article-review.access');
    });

    // ... ou pela conta com senha (Área do Revisor).
    Route::prefix('/revisao-artigo/conta/{articleReviewer}')->whereNumber('articleReviewer')->group(function () {
        Route::get('/', 'show')->middleware('throttle:article-review-read')->name('article-review.account.show');
        Route::get('/arquivo', 'download')->middleware('throttle:article-review-download')->name('article-review.account.download');
        Route::get('/complementar/{file}', 'downloadFile')->whereNumber('file')->middleware('throttle:article-review-read')->name('article-review.account.file');
        Route::post('/', 'store')->middleware('throttle:article-review-write')->name('article-review.account.store');
        Route::post('/ficha', 'saveProfile')->middleware('throttle:article-review-write')->name('article-review.account.profile');
    });
});
Route::get('/admin-revisao-artigo/arquivo/{articleReview}/{version}', function (\App\Models\ArticleReview $articleReview, string $version) {
    if (!auth()->check()) {
        return redirect()->guest(\Filament\Facades\Filament::getPanel('admin')->getLoginUrl());
    }
    abort_unless(auth()->user()->role === 'admin', 403);

    if ($version === 'final') {
        $path = $articleReview->final_file_path;
        $name = $articleReview->final_filename ?: ($articleReview->baseFileStem() . '_final.docx');
    } elseif (str_starts_with($version, 'file-')) {
        $file = $articleReview->files()->whereKey((int) substr($version, 5))->firstOrFail();
        $path = $file->path;
        $name = $file->filename;
    } else {
        $found = $articleReview->findVersion($version);
        abort_unless($found, 404);
        $path = $found['path'];
        $name = $found['filename'];
    }

    abort_unless($path && \Illuminate\Support\Facades\Storage::disk('local')->exists($path), 404);

    return \Illuminate\Support\Facades\Storage::disk('local')->download($path, $name, ['X-Content-Type-Options' => 'nosniff']);
})->name('article-review.admin.download');
Route::get('/admin-revisao-artigo/dossie/{articleReview}', function (\App\Models\ArticleReview $articleReview) {
    if (!auth()->check()) {
        return redirect()->guest(\Filament\Facades\Filament::getPanel('admin')->getLoginUrl());
    }
    abort_unless(auth()->user()->role === 'admin', 403);

    $html = view('article-review.dossier', [
        'review' => $articleReview,
        'reviewers' => $articleReview->reviewers()->with('files')->get(),
        'versions' => $articleReview->allVersions(),
        'authors' => app(\App\Services\ArticleReviewService::class)->authorsBlock($articleReview),
    ])->render();

    return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4')
        ->download('Dossie_' . $articleReview->baseFileStem() . '_' . now()->format('Y-m-d') . '.pdf');
})->name('article-review.admin.dossier');

// --- Fluxo Público de Solicitação de Atendimento / Orçamento Geral ---
Route::get('/solicitar-atendimento', function () {
    return view('solicitar_atendimento');
})->name('consultation.form');

// --- Blog e Na Mídia (SEO) ---
Route::get('/blog', [\App\Http\Controllers\BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{slug}', [\App\Http\Controllers\BlogController::class, 'show'])->name('blog.show');
Route::get('/na-midia', function () {
    $mentions = \App\Models\PressMention::orderByDesc('published_at')->get();

    return view('na-midia', ['mentions' => $mentions]);
})->name('na-midia');






// --- Revisão sistemática / metanálise (downloads do professor e termo do aluno) ---
Route::controller(\App\Http\Controllers\SystematicReviewExportController::class)->group(function () {
    Route::get('/admin-revisao-sistematica/kit', 'kit')->name('systematic-review.kit');
    Route::get('/admin-revisao-sistematica/{systematicReview}/{type}', 'export')
        ->where('type', 'protocolo|triagem|incluidos-csv|incluidos-ris|relatorio|pacote-osf|osf-ficha|osf-json|pipeline|busca-log|triagem-ta|triagem-ft|prisma-csv|desvios|extracao-modelo|dicionario|extracao-travada|rob-travado|extracao-duplicata|contatos-autores|grade|atas|ia-extracao|ia-prompt')->name('systematic-review.export');
    Route::get('/admin-revisao-sistematica/analise/{run}/{file}', 'analysisFile')->where('file', 'script|[0-9]+')->name('systematic-review.analysis-file');
    Route::get('/admin-revisao-sistematica/termo/{screener}', 'terms')->name('systematic-review.terms');
    Route::get('/triagem-cega/meu-termo/{screener}', 'myTerms')->name('systematic-review.my-terms');
    Route::get('/triagem-cega/protocolo/{screener}', 'myProtocol')->name('systematic-review.my-protocol');
});

// --- Seleção de alunos para os projetos de pesquisa (página pública + acompanhamento) ---
Route::controller(\App\Http\Controllers\ResearchCallController::class)->group(function () {
    Route::get('/selecao', 'index')->name('research-call.index');
    Route::get('/selecao/acompanhar/{token}', 'track')->where('token', '[A-Za-z0-9]{48}')
        ->middleware('throttle:30,1')->name('research-call.track');
    Route::post('/selecao/acompanhar/{token}/aceite', 'acceptAdhesion')->where('token', '[A-Za-z0-9]{48}')
        ->middleware('throttle:10,1')->name('research-call.adhesion');
    Route::get('/selecao/{slug}', 'show')->where('slug', '[a-z0-9-]+')->name('research-call.show');
    Route::post('/selecao/{slug}', 'store')->where('slug', '[a-z0-9-]+')
        ->middleware('throttle:10,1')->name('research-call.store');
});
