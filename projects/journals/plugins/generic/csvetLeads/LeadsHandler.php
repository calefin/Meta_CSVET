<?php

/**
 * @file plugins/generic/csvetLeads/LeadsHandler.php
 *
 * @class LeadsHandler
 *
 * @brief /<revista>/<idioma>/interest/...
 *  - token (GET, público): token assinado com a hora em que o formulário abriu
 *  - submit (POST, público): valida, grava, responde em JSON {ok} ou {ok:false, errors}
 *  - list e csv (só gestores): interessados, com conta, rascunhos e submissões de cada um
 */

namespace APP\plugins\generic\csvetLeads;

use APP\handler\Handler;
use Illuminate\Support\Facades\DB;
use PKP\config\Config;
use PKP\core\Core;
use PKP\core\PKPRequest;
use PKP\security\authorization\ContextRequiredPolicy;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\Role;

class LeadsHandler extends Handler
{
    private const MIN_SECONDS = 4;
    private const MAX_SECONDS = 2 * 86400;
    private const MAX_PER_IP_HOUR = 5;

    public function __construct(public CsvetLeadsPlugin $plugin)
    {
        parent::__construct();
        $this->addRoleAssignment([Role::ROLE_ID_MANAGER], ['list', 'csv']);
    }

    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new ContextRequiredPolicy($request));
        if (in_array($request->getRequestedOp(), ['list', 'csv'], true)) {
            $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);
            foreach ($roleAssignments as $role => $operations) {
                $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
            }
            $this->addPolicy($rolePolicy);
        } else {
            // token e submit são públicos; sem isto o OJS manda o visitante para o login
            $this->markRoleAssignmentsChecked();
        }
        return parent::authorize($request, $args, $roleAssignments);
    }

    // ------------------------------------------------------------------ formulário

    public function token($args, $request)
    {
        $this->json(['t' => $this->sign((string) time())]);
    }

    public function submit($args, $request)
    {
        $context = $request->getContext();
        if (!$request->isPost()) {
            $this->json(['ok' => false], 405);
            return;
        }
        $pageLocale = in_array($request->getUserVar('pageLocale'), ['pt_BR', 'en'], true) ? $request->getUserVar('pageLocale') : 'en';
        $s = $this->plugin->strings()[$pageLocale];

        // Robôs: campo escondido preenchido, token inválido, rápido ou velho demais
        $issued = $this->verify((string) $request->getUserVar('t'));
        $age = $issued === null ? -1 : time() - $issued;
        if (trim((string) $request->getUserVar('website')) !== '' || $age < self::MIN_SECONDS || $age > self::MAX_SECONDS) {
            $this->json(['ok' => false, 'message' => $s['errSpam']], 400);
            return;
        }

        [$lead, $errors] = $this->readLead($request, $s);
        if ($errors) {
            $this->json(['ok' => false, 'errors' => $errors], 422);
            return;
        }

        try {
            $this->plugin->ensureTable();
            $ip = (string) $request->getRemoteAddr();
            $recent = DB::table(CsvetLeadsPlugin::TABLE)
                ->where('ip', $ip)
                ->where('date_created', '>', date('Y-m-d H:i:s', time() - 3600))
                ->count();
            if ($recent >= self::MAX_PER_IP_HOUR) {
                $this->json(['ok' => false, 'message' => $s['errRate']], 429);
                return;
            }
            // Orientação por e-mail no máximo uma vez por dia para o mesmo endereço
            $replyToday = !DB::table(CsvetLeadsPlugin::TABLE)
                ->where('email', $lead['email'])
                ->where('replied', true)
                ->where('date_created', '>', date('Y-m-d H:i:s', time() - 86400))
                ->exists();

            $lead += [
                'context_id' => $context->getId(),
                'date_created' => Core::getCurrentDate(),
                'page_locale' => $pageLocale,
                'ip' => $ip,
                'user_agent' => mb_substr((string) $request->getUserAgent(), 0, 255),
                'replied' => $replyToday,
            ];
            DB::table(CsvetLeadsPlugin::TABLE)->insert($lead);
        } catch (\Throwable $e) {
            error_log('csvetLeads submit: ' . $e->getMessage());
            $this->json(['ok' => false, 'message' => str_replace('{email}', (string) $context->getData('contactEmail'), $s['errServer'])], 500);
            return;
        }

        // Gravado: falha de e-mail não pode virar erro para quem preencheu
        try {
            if ($replyToday) {
                $this->plugin->sendReply($context, $lead);
            }
        } catch (\Throwable $e) {
            error_log('csvetLeads reply: ' . $e->getMessage());
        }
        try {
            $this->plugin->notifyEditors($context, $lead, $replyToday);
        } catch (\Throwable $e) {
            error_log('csvetLeads notify: ' . $e->getMessage());
        }
        $this->json(['ok' => true]);
    }

    /** @return array{0: array, 1: array<string, string>} dados limpos e erros por campo */
    private function readLead(PKPRequest $request, array $s): array
    {
        $o = $this->plugin->strings()['options']['pt_BR'];
        $text = fn (string $key) => trim(preg_replace('/\s+/u', ' ', (string) $request->getUserVar($key)));
        $errors = [];

        $name = $text('name');
        if (mb_strlen($name) < 2) {
            $errors['name'] = $s['errName'];
        } elseif (mb_strlen($name) > 120) {
            $errors['name'] = $s['errTooLong'];
        }
        $email = mb_strtolower($text('email'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 254) {
            $errors['email'] = $s['errEmail'];
        }
        $whatsapp = $text('whatsapp');
        if ($whatsapp !== '' && (!preg_match('/^\+?[\d\s().-]{8,30}$/', $whatsapp) || strlen(preg_replace('/\D/', '', $whatsapp)) < 8)) {
            $errors['whatsapp'] = $s['errPhone'];
        }
        $choice = function (string $key, array $allowed) use ($request, &$errors, $s) {
            $value = (string) $request->getUserVar($key);
            if (!in_array($value, $allowed, true)) {
                $errors[$key] = $s['errChoice'];
            }
            return $value;
        };
        $contactLocale = $choice('contactLocale', CsvetLeadsPlugin::LOCALES);
        $profile = $choice('profile', array_keys($o['profile']));
        $type = $choice('type', array_keys($o['type']));
        $stage = $choice('stage', array_keys($o['stage']));
        $institution = $text('institution');
        if (mb_strlen($institution) > 200) {
            $errors['institution'] = $s['errTooLong'];
        }
        $message = trim((string) $request->getUserVar('message'));
        if (mb_strlen($message) > 2000) {
            $errors['message'] = $s['errTooLong'];
        }
        if ($request->getUserVar('consent') !== '1') {
            $errors['consent'] = $s['errConsent'];
        }
        // Origem (utm e site de onde veio, sem https://): só para saber o que funciona
        $source = mb_substr(preg_replace('/[^\w .:\/|=@-]/u', '', (string) $request->getUserVar('source')), 0, 255);

        return [[
            'name' => $name,
            'email' => $email,
            'whatsapp' => $whatsapp ?: null,
            'contact_locale' => $contactLocale,
            'profile' => $profile,
            'article_type' => $type,
            'stage' => $stage,
            'institution' => $institution ?: null,
            'message' => $message ?: null,
            'source' => $source ?: null,
        ], $errors];
    }

    private function sign(string $timestamp): string
    {
        return $timestamp . '.' . hash_hmac('sha256', 'csvetLeads|' . $timestamp, (string) Config::getVar('security', 'salt'));
    }

    private function verify(string $token): ?int
    {
        [$timestamp] = explode('.', $token, 2) + [''];
        return ctype_digit($timestamp) && hash_equals($this->sign($timestamp), $token) ? (int) $timestamp : null;
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    // ------------------------------------------------------------------ lista (gestores)

    /** Interessados com o que já fizeram no sistema (conta, rascunhos, submissões) */
    private function leads(PKPRequest $request): array
    {
        $this->plugin->ensureTable();
        $contextId = $request->getContext()->getId();
        $leads = DB::table(CsvetLeadsPlugin::TABLE)->where('context_id', $contextId)->orderByDesc('date_created')->get();
        $userIds = DB::table('users')
            ->whereIn(DB::raw('LOWER(email)'), $leads->pluck('email')->unique()->all() ?: [''])
            ->select('user_id', DB::raw('LOWER(email) AS email_lc'))
            ->pluck('user_id', 'email_lc');
        $counts = DB::table('stage_assignments as sa')
            ->join('submissions as s', 's.submission_id', '=', 'sa.submission_id')
            ->where('s.context_id', $contextId)
            ->whereIn('sa.user_id', $userIds->values()->all() ?: [0])
            // No OJS 3.5 o rascunho já tem date_submitted; o envio concluído é o submission_progress vazio
            ->select(
                'sa.user_id',
                DB::raw("COUNT(DISTINCT CASE WHEN COALESCE(s.submission_progress, '') <> '' THEN s.submission_id END) AS drafts"),
                DB::raw("COUNT(DISTINCT CASE WHEN COALESCE(s.submission_progress, '') = '' THEN s.submission_id END) AS submitted")
            )
            ->groupBy('sa.user_id')
            ->get()
            ->keyBy('user_id');
        return $leads->map(function ($lead) use ($userIds, $counts) {
            $userId = $userIds[$lead->email] ?? null;
            $lead->has_account = $userId !== null;
            $lead->drafts = (int) ($counts[$userId]->drafts ?? 0);
            $lead->submitted = (int) ($counts[$userId]->submitted ?? 0);
            return $lead;
        })->all();
    }

    public function list($args, $request)
    {
        $s = $this->plugin->strings()['editor'];
        $o = $this->plugin->strings()['options']['pt_BR'];
        $e = fn ($v) => htmlspecialchars((string) $v);
        $csvUrl = $request->getDispatcher()->url($request, \APP\core\Application::ROUTE_PAGE, null, 'interest', 'csv');

        $rows = '';
        foreach ($this->leads($request) as $lead) {
            $contact = '<a href="mailto:' . $e($lead->email) . '">' . $e($lead->email) . '</a>';
            if ($lead->whatsapp) {
                $contact .= '<br><a href="' . $e($this->plugin->whatsappUrl($lead->whatsapp)) . '">WhatsApp ' . $e($lead->whatsapp) . '</a>';
            }
            $system = $lead->has_account ? $s['account'] : $s['noAccount'];
            if ($lead->drafts) {
                $system .= '<br>' . str_replace('{n}', (string) $lead->drafts, $s['drafts']);
            }
            if ($lead->submitted) {
                $system .= '<br><strong>' . str_replace('{n}', (string) $lead->submitted, $s['submitted']) . '</strong>';
            }
            $rows .= '<tr>'
                . '<td>' . $e(date('d/m/Y H:i', strtotime($lead->date_created))) . '</td>'
                . '<td><strong>' . $e($lead->name) . '</strong><br>' . $contact
                . '<br><small>' . $e($o['profile'][$lead->profile] ?? $lead->profile) . ($lead->institution ? ' · ' . $e($lead->institution) : '')
                . ' · ' . $e($o['lang'][$lead->contact_locale] ?? $lead->contact_locale) . '</small></td>'
                . '<td>' . $e($o['type'][$lead->article_type] ?? $lead->article_type) . '<br><small>' . $e($o['stage'][$lead->stage] ?? $lead->stage) . '</small>'
                . ($lead->message ? '<br><small>' . nl2br($e($lead->message)) . '</small>' : '')
                . ($lead->source ? '<br><small style="color:#5a6080;">' . $e($lead->source) . '</small>' : '') . '</td>'
                . '<td>' . $system . '</td>'
                . '</tr>';
        }

        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
        echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">'
            . '<title>' . $e($s['listTitle']) . '</title><style>'
            . 'body{margin:0;background:#f4f5fa;font-family:Arial,sans-serif;color:#1d2340;}'
            . 'main{max-width:1000px;margin:0 auto;padding:24px 16px;}h1{font-size:20px;color:#000080;margin:0 0 6px;}'
            . 'p{font-size:13px;color:#4a5168;}a{color:#000080;}table{width:100%;border-collapse:collapse;background:#fff;font-size:14px;}'
            . 'td,th{padding:10px;border-bottom:1px solid #dde1ee;text-align:left;vertical-align:top;}th{background:#eef0f8;font-size:12px;text-transform:uppercase;letter-spacing:.05em;}'
            . '@media(max-width:640px){thead{display:none;}tr{display:block;border-bottom:2px solid #dde1ee;}td{display:block;border:0;padding:6px 10px;}}'
            . '</style></head><body><main>'
            . '<h1>' . $e($s['listTitle']) . '</h1><p>' . $e($s['listLead']) . '</p>'
            . '<p><a href="' . $e($csvUrl) . '">' . $e($s['csv']) . '</a></p>'
            . ($rows
                ? '<table><thead><tr><th>' . $e($s['colDate']) . '</th><th>' . $e($s['colPerson']) . '</th><th>' . $e($s['colWork']) . '</th><th>' . $e($s['colSystem']) . '</th></tr></thead><tbody>' . $rows . '</tbody></table>'
                : '<p>' . $e($s['empty']) . '</p>')
            . '<p>' . $e($s['privacy']) . '</p>'
            . '</main></body></html>';
    }

    public function csv($args, $request)
    {
        $o = $this->plugin->strings()['options']['pt_BR'];
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="nvas-interessados-' . date('Y-m-d') . '.csv"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // Excel abre com acentos
        fputcsv($out, ['data', 'nome', 'email', 'whatsapp', 'idioma_contato', 'perfil', 'instituicao', 'tipo', 'fase', 'mensagem', 'origem', 'pagina', 'tem_conta', 'rascunhos', 'submissoes'], ';');
        foreach ($this->leads($request) as $lead) {
            fputcsv($out, [
                $lead->date_created, $lead->name, $lead->email, $lead->whatsapp,
                $o['lang'][$lead->contact_locale] ?? $lead->contact_locale,
                $o['profile'][$lead->profile] ?? $lead->profile, $lead->institution,
                $o['type'][$lead->article_type] ?? $lead->article_type, $o['stage'][$lead->stage] ?? $lead->stage,
                $lead->message, $lead->source, $lead->page_locale,
                $lead->has_account ? 'sim' : 'não', $lead->drafts, $lead->submitted,
            ], ';');
        }
        fclose($out);
    }
}
