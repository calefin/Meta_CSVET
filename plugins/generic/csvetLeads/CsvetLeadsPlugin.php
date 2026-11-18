<?php

/**
 * @file plugins/generic/csvetLeads/CsvetLeadsPlugin.php
 *
 * @class CsvetLeadsPlugin
 *
 * @brief Captação de autores: formulário "Quero publicar" da página /nvas/<idioma>/publish-with-us
 *  (content/nvas/pages/publish-with-us.<idioma>.html).
 *
 *  - /<revista>/interest/token e /interest/submit (públicos): grava o interessado na tabela
 *    csvet_leads, manda a ele a orientação por e-mail no idioma escolhido (português, inglês
 *    ou espanhol) e avisa os gestores da revista com links para e-mail e WhatsApp.
 *  - /<revista>/interest/list e /interest/csv (só gestores): lista com o que cada pessoa já fez
 *    no sistema (conta, rascunho, submissão), para acompanhar quem ainda não enviou.
 *  - Contra robôs, sem captcha: campo escondido, tempo mínimo de preenchimento (token
 *    assinado) e limite de envios por IP.
 *
 *  A tabela é criada no primeiro uso. Registrado por scripts/configure-journal.php
 *  ("plugins.install" do journal.json); o prazo da chamada vem do ajuste "deadline".
 */

namespace APP\plugins\generic\csvetLeads;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\security\Role;

class CsvetLeadsPlugin extends GenericPlugin
{
    public const TABLE = 'csvet_leads';
    public const LOCALES = ['pt_BR', 'en', 'es'];

    private ?array $strings = null;

    public function register($category, $path, $mainContextId = null)
    {
        if (!parent::register($category, $path, $mainContextId)) {
            return false;
        }
        if (Application::isInstalled() && !Application::isUnderMaintenance()) {
            Hook::add('LoadHandler', $this->loadHandler(...));
        }
        return true;
    }

    public function getDisplayName()
    {
        return 'CSVET: interessados em publicar';
    }

    public function getDescription()
    {
        return 'Formulário "Quero publicar": guarda os interessados, envia a orientação por e-mail e avisa os editores.';
    }

    public function loadHandler(string $hookName, array $params): bool
    {
        $page = &$params[0];
        $handler = &$params[3];
        if ($page === 'interest') {
            $handler = new LeadsHandler($this);
            return true;
        }
        return false;
    }

    public function strings(): array
    {
        return $this->strings ??= require __DIR__ . '/strings.php';
    }

    /** Cria a tabela no primeiro uso (o OJS não roda migração de plugin registrado por script) */
    public function ensureTable(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            return;
        }
        Schema::create(self::TABLE, function (Blueprint $table) {
            $table->bigIncrements('lead_id');
            $table->bigInteger('context_id');
            $table->dateTime('date_created');
            $table->string('page_locale', 14);
            $table->string('contact_locale', 14);
            $table->string('name', 120);
            $table->string('email', 254);
            $table->string('whatsapp', 30)->nullable();
            $table->string('institution', 200)->nullable();
            $table->string('profile', 20);
            $table->string('article_type', 20);
            $table->string('stage', 20);
            $table->text('message')->nullable();
            $table->string('source', 255)->nullable();
            $table->string('ip', 64);
            $table->string('user_agent', 255)->nullable();
            $table->boolean('replied')->default(false);
            $table->index(['context_id', 'date_created'], 'csvet_leads_context_date');
            $table->index(['ip', 'date_created'], 'csvet_leads_ip_date');
            $table->index(['email'], 'csvet_leads_email');
        });
    }

    /** Páginas da revista citadas nos e-mails, no idioma do interessado (espanhol → inglês) */
    public function urls(Context $context, string $locale): array
    {
        $request = Application::get()->getRequest();
        $pageLocale = $locale === 'pt_BR' ? 'pt_BR' : 'en';
        $url = fn (string $page, ?string $op = null) => $request->getDispatcher()->url(
            $request,
            Application::ROUTE_PAGE,
            $context->getPath(),
            $page,
            $op,
            urlLocaleForPage: $pageLocale
        );
        return [
            'urlCall' => $url('call-for-papers'),
            'urlTemplates' => $url('templates'),
            'urlRegister' => $url('user', 'register'),
        ];
    }

    /** Orientação ao interessado, no idioma de contato escolhido */
    public function sendReply(Context $context, array $lead): void
    {
        $s = $this->strings()['reply'][$lead['contact_locale']] ?? $this->strings()['reply']['en'];
        $vars = $this->urls($context, $lead['contact_locale']);
        $deadline = $this->deadline($context, $lead['contact_locale']);
        $vars['deadline'] = $deadline ? str_replace('{date}', $deadline, $s['deadline']) : '';
        $vars['name'] = htmlspecialchars($this->firstName($lead['name']));

        $steps = '';
        foreach ($s['steps'] as $step) {
            $steps .= '<li style="margin:0 0 10px;">' . $this->fill($step, $vars) . '</li>';
        }
        $body = '<p>' . $this->fill($s['hello'], $vars) . '</p>'
            . '<p>' . $this->fill($s['intro'], $vars) . '</p>'
            . '<ol style="padding-left:20px;">' . $steps . '</ol>'
            . (isset($s['stage'][$lead['stage']]) ? '<p>' . $s['stage'][$lead['stage']] . '</p>' : '')
            . (in_array($lead['profile'], ['undergrad', 'graduate'], true) ? '<p>' . $s['students'] . '</p>' : '')
            . '<p>' . $s['questions'] . '</p>'
            . '<p>' . $s['regards'] . '</p>'
            . '<p>' . htmlspecialchars((string) $context->getData('contactName')) . '<br>' . $s['role']
            . '<br><em>' . htmlspecialchars($context->getName($context->getPrimaryLocale())) . '</em></p>';

        $mailable = new Mailable();
        $mailable
            ->from($context->getData('contactEmail'), $context->getData('contactName'))
            ->to([['email' => $lead['email'], 'name' => $lead['name']]])
            ->subject($s['subject'])
            ->body($this->escapeTemplateVariables($body));
        Mail::send($mailable);
    }

    /** Aviso aos gestores da revista */
    public function notifyEditors(Context $context, array $lead, bool $replied): void
    {
        $s = $this->strings()['editor'];
        $o = $this->strings()['options']['pt_BR'];
        $e = fn ($v) => htmlspecialchars((string) $v);
        $request = Application::get()->getRequest();
        $listUrl = $request->getDispatcher()->url($request, Application::ROUTE_PAGE, $context->getPath(), 'interest', 'list', urlLocaleForPage: 'pt_BR');

        $rows = [
            'name' => $e($lead['name']),
            'email' => '<a href="mailto:' . $e($lead['email']) . '">' . $e($lead['email']) . '</a>',
            'whatsapp' => $lead['whatsapp']
                ? $e($lead['whatsapp']) . ' · <a href="' . $e($this->whatsappUrl($lead['whatsapp'])) . '">' . $s['whatsappLink'] . '</a>'
                : '—',
            'contactLang' => $e($o['lang'][$lead['contact_locale']] ?? $lead['contact_locale']),
            'profile' => $e($o['profile'][$lead['profile']] ?? $lead['profile']),
            'institution' => $e($lead['institution'] ?: '—'),
            'type' => $e($o['type'][$lead['article_type']] ?? $lead['article_type']),
            'stage' => $e($o['stage'][$lead['stage']] ?? $lead['stage']),
            'message' => $lead['message'] ? nl2br($e($lead['message'])) : '—',
            'source' => $e($lead['source'] ?: '—'),
            'pageLang' => $e($lead['page_locale']),
        ];
        $table = '';
        foreach ($rows as $key => $value) {
            $table .= '<tr><td style="padding:4px 12px 4px 0;color:#4a5168;vertical-align:top;white-space:nowrap;">'
                . $s['fields'][$key] . '</td><td style="padding:4px 0;">' . $value . '</td></tr>';
        }
        $replyLang = $replied ? ' (' . $e($o['lang'][$lead['contact_locale']] ?? '') . ')' : $s['noReply'];
        $body = '<p>' . str_replace('{replyLang}', $replyLang, $s['intro']) . '</p>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="font-size:14px;line-height:1.5;">' . $table . '</table>'
            . '<p style="margin-top:16px;"><a href="' . $e($listUrl) . '">' . $s['openList'] . '</a></p>';
        $subject = strtr($s['subject'], [
            '{name}' => $lead['name'],
            '{type}' => $o['type'][$lead['article_type']] ?? $lead['article_type'],
            '{stage}' => mb_strtolower($o['stage'][$lead['stage']] ?? $lead['stage']),
        ]);

        foreach ($this->managers($context) as $email => $name) {
            $mailable = new Mailable();
            $mailable
                ->from($context->getData('contactEmail'), $context->getData('contactName'))
                ->to([['email' => $email, 'name' => $name]])
                ->replyTo($lead['email'], $lead['name'])
                ->subject($subject)
                ->body($this->escapeTemplateVariables($body));
            Mail::send($mailable);
        }
    }

    /** Link do WhatsApp; número brasileiro sem código do país ganha o 55 */
    public function whatsappUrl(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (!str_starts_with(trim($phone), '+') && in_array(strlen($digits), [10, 11], true)) {
            $digits = '55' . $digits;
        }
        return 'https://wa.me/' . $digits;
    }

    /** Prazo da chamada (ajuste "deadline", AAAA-MM-DD) por extenso no idioma, ou null se passou */
    public function deadline(Context $context, string $locale): ?string
    {
        $date = (string) $this->getSetting($context->getId(), 'deadline');
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) || $date < date('Y-m-d')) {
            return null;
        }
        $months = [
            'pt_BR' => ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'],
            'en' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
            'es' => ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'],
        ];
        $month = $months[$locale][(int) $m[2] - 1] ?? $months['en'][(int) $m[2] - 1];
        return match ($locale) {
            'en' => (int) $m[3] . " {$month} {$m[1]}",
            default => (int) $m[3] . " de {$month} de {$m[1]}",
        };
    }

    private function managers(Context $context): array
    {
        $recipients = [];
        $managers = Repo::user()->getCollector()
            ->filterByRoleIds([Role::ROLE_ID_MANAGER])
            ->filterByContextIds([$context->getId()])
            ->getMany();
        foreach ($managers as $manager) {
            if (!$manager->getDisabled()) {
                $recipients[strtolower($manager->getEmail())] = $manager->getFullName();
            }
        }
        return $recipients ?: [$context->getData('contactEmail') => $context->getData('contactName')];
    }

    private function firstName(string $name): string
    {
        return explode(' ', trim($name))[0] ?: $name;
    }

    private function fill(string $text, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $text = str_replace('{' . $key . '}', (string) $value, $text);
        }
        return $text;
    }

    /** O corpo do Mailable é um modelo: "{$" digitado no formulário não pode virar variável */
    private function escapeTemplateVariables(string $html): string
    {
        return str_replace('{$', '{&#36;', $html);
    }
}
