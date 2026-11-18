<?php

namespace App\Services;

use App\Mail\AppNotificationMail;
use App\Models\InviteToken;
use App\Models\ResearchApplication;
use App\Models\ResearchCall;
use App\Models\SystematicReview;
use App\Models\SystematicReviewScreener;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Seleção de alunos para os projetos de pesquisa: inscrição pública com
 * requisitos conferidos automaticamente, entrevista, alocação em dupla
 * cruzada (líder de um projeto + revisor cego de outro) e avisos.
 */
class ResearchRecruitmentService
{
    // ------------------------------------------------------------------
    // Inscrição
    // ------------------------------------------------------------------

    /** Requisitos que barram a inscrição (mensagens para o aluno). */
    public function eligibilityProblems(ResearchCall $call, array $data): array
    {
        $problems = [];
        $courses = $call->eligibleCourses();

        if ($courses && !in_array($data['course'] ?? null, $courses, true)) {
            $problems['course'] = 'Esta seleção é só para alunos de ' . self::humanList($courses) . '.';
        }
        if ((int) ($data['semester'] ?? 0) < $call->min_semester) {
            $problems['semester'] = "É preciso estar no {$call->min_semester}º semestre ou depois. Guarde o link: novas chamadas são abertas todo ano.";
        }
        if ((int) ($data['weekly_hours'] ?? 0) < $call->min_weekly_hours) {
            $problems['weekly_hours'] = "O projeto exige pelo menos {$call->min_weekly_hours} horas por semana, porque a sua dupla depende do seu ritmo.";
        }
        $accepted = array_map('intval', array_keys(array_filter((array) ($data['commitments'] ?? []))));
        foreach (array_keys($call->texts('commitments')) as $i) {
            if (!in_array($i, $accepted, true)) {
                $problems['commitments'] = 'Para se inscrever é preciso aceitar todos os compromissos.';
                break;
            }
        }

        return $problems;
    }

    /**
     * Registra a inscrição e manda a confirmação com o link de
     * acompanhamento. Se o e-mail já se inscreveu, só reenvia o link.
     *
     * @return array{0: ResearchApplication, 1: bool} [inscrição, já existia]
     */
    public function submit(ResearchCall $call, array $data, ?string $ip = null, ?string $userAgent = null): array
    {
        $email = mb_strtolower(trim($data['email']));

        $existing = $call->applications()->where('email', $email)->first();
        if ($existing) {
            $this->notifyCandidate($existing, 'Sua inscrição já está registrada',
                "Olá, {$existing->firstName()}!\n\nRecebemos um novo envio do formulário com este e-mail, mas a sua inscrição na \"{$call->title}\" já estava registrada desde "
                . $existing->created_at->format('d/m/Y') . ".\n\nSituação atual: {$existing->statusLabel()}.\n\nPelo botão abaixo você acompanha cada etapa da seleção.");

            return [$existing, true];
        }

        $token = Str::random(48);
        $texts = $call->texts('commitments');
        $user = User::where('email', $email)->first();
        $acceptedAt = now();
        $adhesion = $call->adhesionText(Str::squish($data['name']), $email, $acceptedAt->format('d/m/Y H:i:s') . ' (horário de Brasília) · IP ' . ($ip ?: '—'));

        $application = $call->applications()->create([
            'user_id' => $user && in_array($user->role, ['student', 'admin'], true) ? $user->id : null,
            'access_token_hash' => ResearchApplication::hashToken($token),
            'access_token' => $token,
            // Exatamente como o aluno digitou (só tira espaços sobrando): é o nome
            // que ele digita para assinar o termo e que vai para a autoria.
            'name' => Str::squish($data['name']),
            'email' => $email,
            'phone' => trim($data['phone']),
            'course' => $data['course'],
            'institution' => trim($data['institution']),
            'semester' => (int) $data['semester'],
            'purpose' => $data['purpose'],
            'axis_preferences' => array_values(array_intersect((array) ($data['axes'] ?? []), $call->axisNames())),
            'project_preferences' => array_values(array_slice(array_intersect((array) ($data['projects'] ?? []), array_keys($call->openProjectsByCode())), 0, 3)),
            'weekly_hours' => (int) $data['weekly_hours'],
            'english_level' => $data['english_level'],
            'lattes_url' => $data['lattes_url'] ?? null,
            'orcid' => $data['orcid'] ?? null,
            'motivation' => trim($data['motivation']),
            'experience' => filled($data['experience'] ?? null) ? trim($data['experience']) : null,
            'declarations' => array_map(fn ($t) => ['text' => $t, 'accepted_at' => $acceptedAt->toIso8601String()], $texts),
            'adhesion_text' => $adhesion,
            'adhesion_sha256' => hash('sha256', $adhesion),
            'adhesion_accepted_at' => $acceptedAt,
            'status' => ResearchApplication::STATUS_NEW,
            'ip' => $ip,
            'user_agent' => Str::limit((string) $userAgent, 490, ''),
        ]);
        $application->logEvent('Inscrição enviada pelo site com aceite do termo de adesão',
            'IP ' . ($ip ?: '—') . ' · SHA-256 ' . substr($application->adhesion_sha256, 0, 16) . '…');

        $this->notifyCandidate($application, 'Inscrição recebida — confirme o seu e-mail',
            "Olá, {$application->firstName()}!\n\nRecebemos a sua inscrição na \"{$call->title}\".\n\n"
            . "⚠️ Clique no botão abaixo para CONFIRMAR o seu e-mail. Todos os avisos da seleção (reunião, resultado e próximos passos) chegam neste endereço, e a inscrição só segue com o e-mail confirmado.\n\n"
            . "Próximos passos:\n"
            . "1. O Prof. Atilio confere os requisitos da sua inscrição.\n"
            . "2. Se estiver tudo certo, você recebe por e-mail a data e o link de uma reunião rápida (5 a 10 minutos).\n"
            . "3. Depois da reunião sai o resultado, com os códigos dos projetos em que você vai trabalhar.\n\n"
            . "COMPROVANTE DO ACEITE DO TERMO DE ADESÃO\n"
            . 'Data e hora: ' . $application->adhesion_accepted_at->format('d/m/Y H:i:s') . " (horário de Brasília)\n"
            . 'Endereço IP: ' . ($ip ?: '—') . "\n"
            . "Impressão digital (SHA-256) do texto aceito: {$application->adhesion_sha256}\n\n"
            . "--- Texto aceito ---\n{$application->adhesion_text}\n--- Fim do texto ---\n\n"
            . 'Guarde este e-mail: ele é o seu comprovante, e pelo botão você acompanha a inscrição a qualquer momento.');

        $this->notifyProfessor('Nova inscrição na seleção de pesquisa',
            "{$application->name} ({$application->course}, {$application->semester}º semestre, {$application->institution})\n"
            . "Objetivo: {$application->purposeLabel()} · {$application->weekly_hours} h/semana\n"
            . 'Eixos: ' . (implode('; ', $application->axis_preferences ?? []) ?: '—') . "\n"
            . 'Projetos: ' . (implode(', ', $application->project_preferences ?? []) ?: '—'));

        return [$application, false];
    }

    // ------------------------------------------------------------------
    // Seleção (professor)
    // ------------------------------------------------------------------

    public function scheduleInterview(ResearchApplication $app, \DateTimeInterface $at, string $place, ?string $message = null): void
    {
        $app->update([
            'status' => ResearchApplication::STATUS_INTERVIEW,
            'interview_at' => $at,
            'interview_place' => trim($place),
        ]);
        $app->logEvent('Reunião marcada', $app->interview_at->format('d/m/Y H:i') . ' · ' . $app->interview_place);

        $this->notifyCandidate($app, 'Reunião marcada',
            "Olá, {$app->firstName()}!\n\nA sua inscrição passou na análise dos requisitos. Vamos fazer uma reunião rápida (5 a 10 minutos):\n\n"
            . '📅 ' . ucfirst($app->interview_at->translatedFormat('l, d/m/Y \à\s H:i')) . "\n"
            . "📍 {$app->interview_place}\n\n"
            . "Na entrevista vamos falar sobre a sua disponibilidade semanal, a leitura de artigos em inglês, o que você espera do projeto e o compromisso com os prazos da dupla (atrasos podem levar ao desligamento).\n\n"
            . (filled($message) ? 'Recado do Prof. Atilio: ' . trim($message) . "\n\n" : '')
            . 'Se não puder nesse horário, responda este e-mail ou avise pelo WhatsApp o quanto antes.');
    }

    public function recordInterview(ResearchApplication $app, array $answers, ?string $notes): void
    {
        $app->update([
            'status' => ResearchApplication::STATUS_INTERVIEWED,
            'interview_answers' => $answers,
            'interview_notes' => $notes,
        ]);
        $ok = count(array_filter($answers));
        $app->logEvent('Reunião anotada', "{$ok}/" . count($answers) . ' respostas positivas');
    }

    /** Problemas que impedem a alocação (códigos inválidos ou já ocupados). */
    public function allocationProblems(ResearchApplication $app, string $lead, ?string $reviewer): array
    {
        $call = $app->researchCall;
        $codes = array_keys($call->projectsByCode());
        $problems = [];

        if (!in_array($lead, $codes, true)) {
            $problems[] = "Projeto {$lead} não existe nesta chamada.";
        }
        if ($reviewer !== null && $reviewer !== '' && !in_array($reviewer, $codes, true)) {
            $problems[] = "Projeto {$reviewer} não existe nesta chamada.";
        }
        if ($reviewer === $lead) {
            $problems[] = 'O aluno não pode ser líder e revisor cego do mesmo projeto.';
        }

        $others = $call->applications()->where('id', '!=', $app->id)->where('status', ResearchApplication::STATUS_APPROVED);
        if ((clone $others)->where('lead_project_code', $lead)->exists()) {
            $problems[] = "O projeto {$lead} já tem líder.";
        }
        if (filled($reviewer) && (clone $others)->where('reviewer_project_code', $reviewer)->exists()) {
            $problems[] = "O projeto {$reviewer} já tem revisor cego.";
        }

        return $problems;
    }

    /**
     * Aprova e aloca. Inclui o aluno como triador nos projetos já criados em
     * Revisões Sistemáticas e manda um e-mail único com o convite (se ainda
     * não tem conta) e o passo a passo.
     */
    public function approve(ResearchApplication $app, string $lead, ?string $reviewer = null, ?string $message = null): ?string
    {
        $reviewer = filled($reviewer) ? $reviewer : null;
        if (!$app->adhesion_sha256) {
            throw new \RuntimeException('O aluno ainda não aceitou o termo de adesão. Use "Reenviar confirmação" para pedir o aceite.');
        }
        if ($problems = $this->allocationProblems($app, $lead, $reviewer)) {
            throw new \RuntimeException(implode(' ', $problems));
        }

        $call = $app->researchCall;
        $user = User::where('email', $app->email)->first();
        if ($user && !in_array($user->role, ['student', 'admin'], true)) {
            throw new \RuntimeException('Esse e-mail pertence a uma conta que não é de aluno.');
        }

        $app->update([
            'status' => ResearchApplication::STATUS_APPROVED,
            'lead_project_code' => $lead,
            'reviewer_project_code' => $reviewer,
            'decision_message' => $message,
            'decided_at' => now(),
            'user_id' => $user?->id,
        ]);
        $app->logEvent('Aprovado(a)', "Líder: {$lead}" . ($reviewer ? " · Revisor cego: {$reviewer}" : ''));

        // Triador nos projetos já vinculados a uma revisão sistemática.
        $projects = $call->projectsByCode();
        $linked = [];
        foreach (array_filter(['Líder (1º autor)' => $lead, 'Revisor(a) cego(a)' => $reviewer]) as $label => $code) {
            $review = SystematicReview::find($projects[$code]['systematic_review_id'] ?? null);
            if (!$review) {
                continue;
            }
            try {
                app(SystematicReviewService::class)->addScreener($review, [
                    'user_id' => $user?->id,
                    'name' => $app->name,
                    'email' => $app->email,
                    'label' => $label,
                    'stages' => array_keys(SystematicReview::STAGE_LABELS),
                    'skip_invite' => true,
                ]);
                $linked[] = $code;
            } catch (\RuntimeException $e) {
                // Já era triador desse projeto: segue.
                Log::info("Seleção: {$app->email} não incluído em {$code}: " . $e->getMessage());
                $linked[] = $code;
            }
        }

        $inviteUrl = null;
        if (!$user) {
            InviteToken::where('email', $app->email)->whereNull('used_at')->delete();
            $invite = InviteToken::create([
                'email' => $app->email,
                'category' => 'external',
                'auto_approve' => true,
                'expires_at' => now()->addDays(30),
            ]);
            $inviteUrl = url('/invite?token=' . $invite->token);
        }

        $describe = fn (string $code) => $code . ' — ' . ($projects[$code]['area'] ?? '');
        $body = "Olá, {$app->firstName()}!\n\n🎉 Você foi aprovado(a) na \"{$call->title}\".\n\n"
            . "Seus projetos:\n"
            . '• Líder (1º autor): ' . $describe($lead) . "\n"
            . ($reviewer ? '• Revisor(a) cego(a) (coautor): ' . $describe($reviewer) . "\n" : '')
            . "\nO título real de cada projeto aparece no Portal do Aluno depois que você assinar o termo de confidencialidade.\n\n"
            . "Próximos passos:\n"
            . ($inviteUrl
                ? "1. Crie o seu login de aluno pelo link do convite (use este mesmo e-mail; vale por 30 dias): {$inviteUrl}\n2. Valide o seu e-mail pelo link que chega logo depois. A conta é liberada na hora.\n"
                : "1. Entre no Portal do Aluno com a sua conta de sempre: " . url('/portal') . "\n2. Confira se os seus dados estão atualizados.\n")
            . "3. Assim que o projeto for cadastrado, a opção \"Triagem Cega\" aparece no menu do portal: abra-a, leia e assine online o termo de confidencialidade de cada projeto. Depois disso você vê o título, o seu papel, o contato da sua dupla e o protocolo.\n"
            . "4. Aguarde o aviso de início da triagem (você recebe e-mail) e leia o guia da etapa.\n\n"
            . (filled($message) ? 'Recado do Prof. Atilio: ' . trim($message) . "\n\n" : '')
            . 'Pelo botão abaixo você acompanha todas as etapas.';

        $this->notifyCandidate($app, 'Você foi aprovado(a)!', $body);

        return $inviteUrl;
    }

    public function waitlist(ResearchApplication $app, ?string $message = null): void
    {
        $app->update(['status' => ResearchApplication::STATUS_WAITLIST, 'decision_message' => $message, 'decided_at' => now()]);
        $app->logEvent('Lista de espera', $message);
        $this->notifyCandidate($app, 'Você está na lista de espera',
            "Olá, {$app->firstName()}!\n\nTodas as vagas desta rodada já foram preenchidas, mas você ficou na lista de espera da \"{$app->researchCall->title}\". "
            . "Se abrir uma vaga, você é chamado(a) por e-mail.\n\n" . (filled($message) ? 'Recado do Prof. Atilio: ' . trim($message) . "\n\n" : '')
            . 'Obrigado pelo interesse!');
    }

    public function reject(ResearchApplication $app, ?string $message = null): void
    {
        $app->update(['status' => ResearchApplication::STATUS_REJECTED, 'decision_message' => $message, 'decided_at' => now()]);
        $app->logEvent('Não aprovado(a)', $message);
        $this->notifyCandidate($app, 'Resultado da seleção',
            "Olá, {$app->firstName()}!\n\nObrigado por se inscrever na \"{$app->researchCall->title}\". Desta vez você não foi selecionado(a).\n\n"
            . (filled($message) ? trim($message) . "\n\n" : '')
            . 'Novas chamadas são abertas todo ano. Fique de olho!');
    }

    /**
     * Desliga o aluno do projeto (atraso, falta de resposta, violação do
     * termo...). Remove o acesso às triagens dos projetos vinculados, registra
     * o motivo e avisa por e-mail citando o compromisso aceito na inscrição.
     */
    public function remove(ResearchApplication $app, string $reason): void
    {
        $app->update(['status' => ResearchApplication::STATUS_REMOVED, 'decision_message' => $reason, 'decided_at' => now()]);
        $app->logEvent('Desligado(a) do projeto', $reason);

        $projects = $app->researchCall->projectsByCode();
        foreach (array_filter([$app->lead_project_code, $app->reviewer_project_code]) as $code) {
            $review = SystematicReview::find($projects[$code]['systematic_review_id'] ?? null);
            $screener = $review?->screeners()->where('email', $app->email)->whereNull('removed_at')->first();
            if ($screener) {
                app(SystematicReviewService::class)->removeScreener($screener, 'Desligado(a) pela seleção: ' . $reason);
            }
        }

        $accepted = collect($app->declarations ?? [])->first(fn ($d) => str_contains(mb_strtolower($d['text'] ?? ''), 'atrasos na entrega'));
        $this->notifyCandidate($app, 'Desligamento do projeto',
            "Olá, {$app->firstName()}.\n\nVocê foi desligado(a) do projeto de pesquisa da \"{$app->researchCall->title}\".\n\n"
            . "Motivo: {$reason}\n\n"
            . ($accepted ? 'Na inscrição, em ' . \Carbon\Carbon::parse($accepted['accepted_at'])->format('d/m/Y') . ", você aceitou o compromisso: \"{$accepted['text']}\"\n\n" : '')
            . "O seu acesso ao projeto foi encerrado e a sua parte será redistribuída. As obrigações de confidencialidade do termo continuam valendo.\n\n"
            . 'Se tiver alguma dúvida, responda este e-mail.');
    }

    public function withdraw(ResearchApplication $app, ?string $reason = null): void
    {
        $app->update(['status' => ResearchApplication::STATUS_WITHDRAWN]);
        $app->logEvent('Desistência registrada', $reason);
    }

    // ------------------------------------------------------------------
    // Painéis
    // ------------------------------------------------------------------

    /**
     * Matriz de alocação: para cada projeto, líder, revisor cego e quantos
     * candidatos o escolheram.
     */
    public function allocationMatrix(ResearchCall $call): array
    {
        $apps = $call->applications()->get();
        $approved = $apps->where('status', ResearchApplication::STATUS_APPROVED);

        return collect($call->projects ?? [])->map(fn ($p) => [
            'code' => $p['code'],
            'axis' => $p['axis'] ?? '',
            'area' => $p['area'] ?? '',
            'lead' => $approved->firstWhere('lead_project_code', $p['code'])?->name,
            'reviewer' => $approved->firstWhere('reviewer_project_code', $p['code'])?->name,
            'wanted' => $apps->filter(fn ($a) => in_array($p['code'], $a->project_preferences ?? [], true))->count(),
            'linked' => filled($p['systematic_review_id'] ?? null),
        ])->all();
    }

    /**
     * Etapa atual do aluno no passo a passo da chamada (índice em steps).
     * Segue a ordem padrão: inscrição, análise, entrevista, resultado, login,
     * termo, treinamento...
     */
    // ------------------------------------------------------------------
    // Mudança de linha, realocação e encerramento
    // ------------------------------------------------------------------

    /**
     * Redireciona um inscrito (ainda não aprovado) para outro eixo e, se
     * quiser, para outros projetos de preferência. Fica no histórico.
     */
    public function redirect(ResearchApplication $app, string $axis, array $projects = [], ?string $message = null, bool $notify = true): void
    {
        $call = $app->researchCall;
        if (!in_array($axis, $call->axisNames(), true)) {
            throw new \RuntimeException('Eixo inválido.');
        }
        $inAxis = collect($call->projects ?? [])->where('axis', $axis)->pluck('code')->all();
        $projects = array_values(array_slice(array_intersect($projects, $inAxis), 0, 3));

        $from = implode('; ', $app->axis_preferences ?? []) ?: '—';
        $app->update(['axis_preferences' => [$axis], 'project_preferences' => $projects]);
        $app->logEvent('Redirecionado(a) para outra linha', "De: {$from} → Para: {$axis}" . ($projects ? ' (' . implode(', ', $projects) . ')' : ''));

        if ($notify) {
            $areas = collect($projects)->map(fn ($c) => "• {$c} — " . ($call->projectsByCode()[$c]['area'] ?? ''))->implode("\n");
            $this->notifyCandidate($app, 'Sua inscrição foi direcionada para outra linha',
                "Olá, {$app->firstName()}!\n\nPelo seu perfil, o Prof. Atilio direcionou a sua inscrição para a linha \"{$axis}\""
                . ($areas ? ", com estes projetos:\n{$areas}" : '.') . "\n\n"
                . (filled($message) ? 'Recado do Prof. Atilio: ' . trim($message) . "\n\n" : '')
                . 'A seleção continua normalmente. Se tiver alguma dúvida, responda este e-mail.');
        }
    }

    /**
     * Realoca um aluno já aprovado (troca o projeto que lidera e/ou o de
     * revisor cego). Move o acesso de triador entre as revisões vinculadas.
     */
    public function reallocate(ResearchApplication $app, string $lead, ?string $reviewer = null, ?string $message = null): void
    {
        $reviewer = filled($reviewer) ? $reviewer : null;
        if ($app->status !== ResearchApplication::STATUS_APPROVED) {
            throw new \RuntimeException('Só dá para realocar quem já foi aprovado(a).');
        }
        if ($problems = $this->allocationProblems($app, $lead, $reviewer)) {
            throw new \RuntimeException(implode(' ', $problems));
        }

        $call = $app->researchCall;
        $projects = $call->projectsByCode();
        $old = array_filter([$app->lead_project_code, $app->reviewer_project_code]);
        $new = array_filter(['Líder (1º autor)' => $lead, 'Revisor(a) cego(a)' => $reviewer]);
        $sr = app(SystematicReviewService::class);

        foreach (array_diff($old, $new) as $code) {
            $review = SystematicReview::find($projects[$code]['systematic_review_id'] ?? null);
            $screener = $review?->screeners()->where('email', $app->email)->whereNull('removed_at')->first();
            if ($screener) {
                $sr->removeScreener($screener, 'Realocado(a) pela seleção para outro projeto');
            }
        }
        $user = User::where('email', $app->email)->first();
        foreach ($new as $label => $code) {
            $review = SystematicReview::find($projects[$code]['systematic_review_id'] ?? null);
            if (!$review || $review->screeners()->where('email', $app->email)->whereNull('removed_at')->exists()) {
                continue;
            }
            $sr->addScreener($review, ['user_id' => $user?->id, 'name' => $app->name, 'email' => $app->email, 'label' => $label,
                'stages' => array_keys(SystematicReview::STAGE_LABELS), 'skip_invite' => true]);
        }

        $before = ($app->lead_project_code ?: '—') . ' / ' . ($app->reviewer_project_code ?: '—');
        $app->update(['lead_project_code' => $lead, 'reviewer_project_code' => $reviewer]);
        $app->logEvent('Realocado(a)', "Antes (líder / revisor): {$before} → Agora: {$lead} / " . ($reviewer ?: '—'));

        $describe = fn (string $code) => $code . ' — ' . ($projects[$code]['area'] ?? '');
        $this->notifyCandidate($app, 'Seus projetos foram alterados',
            "Olá, {$app->firstName()}!\n\nO Prof. Atilio alterou os seus projetos na \"{$call->title}\":\n"
            . '• Líder (1º autor): ' . $describe($lead) . "\n"
            . ($reviewer ? '• Revisor(a) cego(a) (coautor): ' . $describe($reviewer) . "\n" : '')
            . "\n" . (filled($message) ? 'Recado do Prof. Atilio: ' . trim($message) . "\n\n" : '')
            . 'O título real de cada projeto aparece no Portal do Aluno depois que você assina o termo de confidencialidade.');
    }

    /** Abre ou encerra as inscrições da chamada (as inscrições já feitas seguem normalmente). */
    public function setOpen(ResearchCall $call, bool $open): void
    {
        $call->update(['status' => $open ? ResearchCall::STATUS_OPEN : ResearchCall::STATUS_CLOSED]
            + ($open && $call->closes_at?->isPast() ? ['closes_at' => null] : []));
    }

    /** Confirmação manual do e-mail (ex.: confirmado com o aluno pelo WhatsApp). */
    public function confirmEmailManually(ResearchApplication $app, string $note): void
    {
        $app->update(['email_verified_at' => now()]);
        $app->logEvent('E-mail confirmado manualmente pelo professor', $note);
    }

    /**
     * Aceite do termo de adesão pela página de acompanhamento (inscrições
     * feitas antes do termo existir). Grava o texto exato, IP, navegador e SHA-256.
     */
    public function acceptAdhesion(ResearchApplication $app, ?string $ip, ?string $userAgent): void
    {
        if ($app->adhesion_sha256) {
            return;
        }
        $acceptedAt = now();
        $text = $app->researchCall->adhesionText($app->name, $app->email, $acceptedAt->format('d/m/Y H:i:s') . ' (horário de Brasília) · IP ' . ($ip ?: '—'));
        $app->update([
            'adhesion_text' => $text,
            'adhesion_sha256' => hash('sha256', $text),
            'adhesion_accepted_at' => $acceptedAt,
            'declarations' => array_map(fn ($t) => ['text' => $t, 'accepted_at' => $acceptedAt->toIso8601String()], $app->researchCall->texts('commitments')),
            'ip' => $ip,
            'user_agent' => Str::limit((string) $userAgent, 490, ''),
        ]);
        $app->logEvent('Termo de adesão aceito pela página de acompanhamento', 'IP ' . ($ip ?: '—') . ' · SHA-256 ' . substr($app->adhesion_sha256, 0, 16) . '…');

        $this->notifyCandidate($app, 'Comprovante do aceite do termo de adesão',
            "Olá, {$app->firstName()}!\n\nRegistramos o seu aceite do termo de adesão.\n\n"
            . 'Data e hora: ' . $acceptedAt->format('d/m/Y H:i:s') . " (horário de Brasília)\nEndereço IP: " . ($ip ?: '—') . "\n"
            . "Impressão digital (SHA-256): {$app->adhesion_sha256}\n\n--- Texto aceito ---\n{$text}\n--- Fim do texto ---");
    }

    /**
     * O link de acompanhamento só chega pelo e-mail: abri-lo confirma que o
     * endereço é do aluno e funciona.
     */
    public function confirmEmail(ResearchApplication $app, ?string $ip = null): void
    {
        if ($app->email_verified_at) {
            return;
        }
        $app->update(['email_verified_at' => now()]);
        $app->logEvent('E-mail confirmado pelo link de acompanhamento', 'IP ' . ($ip ?: '—'));
    }

    /** Reenvia o e-mail de confirmação (com o link de acompanhamento). */
    public function resendConfirmation(ResearchApplication $app): void
    {
        $pending = $app->adhesion_sha256 ? '' : "\n\nNa página que vai abrir, leia e aceite o termo de adesão da seleção (natureza voluntária da participação, compromissos e proteção de dados). Sem esse aceite a inscrição não pode ser aprovada.";
        $this->notifyCandidate($app, $app->adhesion_sha256 ? 'Confirme o seu e-mail — seleção de pesquisa' : 'Confirme o seu e-mail e aceite o termo de adesão',
            "Olá, {$app->firstName()}!\n\nPara seguir com a sua inscrição na \"{$app->researchCall->title}\", clique no botão abaixo e confirme o seu e-mail. Todos os avisos da seleção chegam neste endereço.{$pending}");
        $app->logEvent($app->adhesion_sha256 ? 'Confirmação de e-mail reenviada' : 'Pedido de confirmação de e-mail e de aceite do termo de adesão enviado');
    }

    public function currentStep(ResearchApplication $app): int
    {
        return match ($app->status) {
            ResearchApplication::STATUS_NEW => 1,
            ResearchApplication::STATUS_INTERVIEW => 2,
            ResearchApplication::STATUS_INTERVIEWED, ResearchApplication::STATUS_WAITLIST => 3,
            ResearchApplication::STATUS_APPROVED => $this->approvedStep($app),
            default => 3,
        };
    }

    protected function approvedStep(ResearchApplication $app): int
    {
        $user = User::where('email', $app->email)->first();
        if (!$user || ($user->role === 'student' && $user->student?->status !== 'active')) {
            return 4;
        }
        $signed = SystematicReviewScreener::where('user_id', $user->id)->whereNotNull('terms_accepted_at')->exists();

        return $signed ? 6 : 5;
    }

    /** E-mail de divulgação pronto para copiar (assunto + corpo). */
    public function announcementEmail(ResearchCall $call): array
    {
        $axes = collect($call->projectsByAxis())->map(fn ($g, $name) => "• {$name} (" . count($g['projects']) . " projetos)\n  " . ($g['axis']['description'] ?? ''))->implode("\n");
        $benefits = collect($call->texts('benefits'))->map(fn ($t) => "✅ {$t}")->implode("\n");
        $req = collect($call->texts('requirements'))->map(fn ($t, $i) => ($i + 1) . ". {$t}")->implode("\n");

        return [
            'subject' => "Seleção aberta: {$call->title}",
            'body' => "Olá!\n\nEstão abertas as inscrições para a {$call->title}, sob orientação do Prof. Dr. Atilio Sersun Calefi.\n\n"
                . ($call->headline ? $call->headline . "\n\n" : '')
                . "SEM CUSTO PARA O ALUNO\nA participação é gratuita: não há taxa de inscrição, mensalidade nem cobrança de qualquer tipo. O que o projeto exige é o seu compromisso com a dedicação semanal, os prazos e o sigilo.\n\n"
                . "NATUREZA DA PARTICIPAÇÃO\nMentoria científica voluntária, com orientação do Prof. Dr. Atilio Sersun Calefi: não há bolsa nem remuneração, não há horário fixo (você organiza os seus horários e cumpre os prazos das etapas) e a participação não configura vínculo empregatício nem estágio. A participação é pessoal e intransferível; a leitura e a participação na escrita do artigo (em inglês, para revista internacional) são obrigatórias; a triagem não pode ser feita por inteligência artificial; e atrasos ou o não atendimento ao objetivo da pesquisa podem levar ao desligamento. Os dados são tratados conforme a LGPD.\n\n"
                . "O QUE VOCÊ GANHA\n{$benefits}\n\n"
                . "ÁREAS DOS PROJETOS\n{$axes}\n\n"
                . "PRÉ-REQUISITOS\n{$req}\n\n"
                . "COMO PARTICIPAR\nNa página abaixo estão todas as condições, o passo a passo completo (da inscrição à submissão do artigo), as perguntas frequentes e o formulário de inscrição:\n{$call->publicUrl()}\n\n"
                . ($call->closes_at ? 'Inscrições até ' . $call->closes_at->format('d/m/Y') . ".\n\n" : '')
                . "As vagas são limitadas. A seleção tem análise da inscrição e uma entrevista rápida.\n\n"
                . "Prof. Dr. Atilio Sersun Calefi\nprofatilio.com.br",
        ];
    }

    // ------------------------------------------------------------------
    // Avisos
    // ------------------------------------------------------------------

    public function notifyCandidate(ResearchApplication $app, string $title, string $body): void
    {
        $this->mail($app->email, new AppNotificationMail(
            title: $title,
            messageContent: $body,
            actionUrl: $app->trackingUrl(),
            actionText: 'Acompanhar minha inscrição',
            subject: $title . ' — Seleção de pesquisa Prof. Atilio',
        ));
    }

    // ------------------------------------------------------------------
    // Mensagens do professor (individual, dupla de um projeto ou grupo)
    // ------------------------------------------------------------------

    /**
     * Mensagem livre do professor a um aluno, por e-mail e Telegram (se ligado).
     * {nome} vira o primeiro nome. Aprovado com conta vai para o Portal; os
     * demais, para a página de acompanhamento. Fica no histórico da inscrição.
     */
    public function sendMessage(ResearchApplication $app, string $title, string $body): void
    {
        $title = trim($title);
        $body = str_replace(['{nome}', '{nome_completo}'], [$app->firstName(), $app->name], trim($body));
        $user = User::where('email', $app->email)->first();
        $portal = $app->status === ResearchApplication::STATUS_APPROVED && $user;
        $url = $portal ? url('/portal') : $app->trackingUrl();

        $this->mail($app->email, new AppNotificationMail(
            title: $title,
            messageContent: $body,
            actionUrl: $url,
            actionText: $portal ? 'Abrir o Portal do Aluno' : 'Acompanhar minha inscrição',
            subject: $title . ' — Prof. Atilio',
        ));
        $chat = $user?->telegram_chat_id ?: $user?->student?->telegram_chat_id;
        if ($chat) {
            try {
                app(TelegramService::class)->sendMessage($chat, '<b>' . e($title) . "</b>\n\n" . e(Str::limit($body, 3300))
                    . ($url ? "\n\n👉 <a href=\"" . e($url) . '">Abrir</a>' : ''));
            } catch (\Throwable $e) {
                Log::error('Seleção de pesquisa: falha ao enviar Telegram: ' . $e->getMessage());
            }
        }
        $app->logEvent('Mensagem do professor enviada', Str::limit($title, 200));
    }

    /** Aprovados que estão na dupla de um projeto (líder e revisor cego). */
    public function projectPair(ResearchCall $call, string $code)
    {
        return ResearchApplication::where('research_call_id', $call->id)
            ->where('status', ResearchApplication::STATUS_APPROVED)
            ->where(fn ($q) => $q->where('lead_project_code', $code)->orWhere('reviewer_project_code', $code))
            ->orderByRaw('lead_project_code = ? desc', [$code])->orderBy('id')->get();
    }

    /** Projetos com pelo menos um aprovado, para escolher a dupla. @return array<string, string> */
    public function projectsWithPairs(ResearchCall $call): array
    {
        $out = [];
        foreach (ResearchApplication::where('research_call_id', $call->id)->where('status', ResearchApplication::STATUS_APPROVED)->get() as $a) {
            foreach (array_filter([$a->lead_project_code, $a->reviewer_project_code]) as $code) {
                $out[$code] ??= [];
                $out[$code][] = $a->firstName();
            }
        }
        ksort($out);

        return collect($out)->mapWithKeys(fn ($names, $code) => [$code => $code . ' — ' . implode(' e ', array_unique($names))])->all();
    }

    /** Lista de contatos dos aprovados, agrupada por dupla (os dois projetos que dividem). */
    public function contactRows(ResearchCall $call): array
    {
        $rows = [['dupla', 'nome', 'email', 'lider_1o_autor', 'revisor_cego']];
        $apps = ResearchApplication::where('research_call_id', $call->id)->where('status', ResearchApplication::STATUS_APPROVED)->get()
            ->sortBy(fn ($a) => collect([$a->lead_project_code, $a->reviewer_project_code])->filter()->sort()->implode('+') . '|' . $a->lead_project_code);
        foreach ($apps as $a) {
            $pair = collect([$a->lead_project_code, $a->reviewer_project_code])->filter()->sort()->implode(' + ');
            $rows[] = [$pair, $a->name, $a->email, $a->lead_project_code, $a->reviewer_project_code];
        }

        return $rows;
    }

    public function notifyProfessor(string $title, string $body): void
    {
        $url = \App\Filament\Resources\ResearchApplicationResource::getUrl('index', panel: 'admin');
        $this->mail((string) config('services.research.professor_email'), new AppNotificationMail(
            title: $title,
            messageContent: $body,
            actionUrl: $url,
            actionText: 'Ver inscrições',
            subject: $title,
        ));

        $admin = User::where('role', 'admin')->whereNotNull('telegram_chat_id')->where('telegram_chat_id', '!=', '')->orderBy('id')->first();
        if ($admin) {
            try {
                app(TelegramService::class)->sendMessage(
                    $admin->telegram_chat_id,
                    '<b>' . e($title) . "</b>\n\n" . e(Str::limit($body, 3300)) . "\n\n👉 <a href=\"" . e($url) . '">Abrir</a>'
                );
            } catch (\Throwable $e) {
                Log::error('Seleção de pesquisa: falha ao enviar Telegram: ' . $e->getMessage());
            }
        }
    }

    protected function mail(string $to, AppNotificationMail $mail): void
    {
        // Síncrono: o aluno está esperando a confirmação agora.
        try {
            Mail::to($to)->send($mail);
        } catch (\Throwable $e) {
            Log::error("Seleção de pesquisa: falha ao enviar e-mail para {$to}: " . $e->getMessage());
        }
    }

    public static function humanList(array $items): string
    {
        if (count($items) <= 1) {
            return (string) ($items[0] ?? '');
        }
        $last = array_pop($items);

        return implode(', ', $items) . ' ou ' . $last;
    }
}
