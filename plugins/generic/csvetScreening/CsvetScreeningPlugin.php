<?php

/**
 * @file plugins/generic/csvetScreening/CsvetScreeningPlugin.php
 *
 * @class CsvetScreeningPlugin
 *
 * @brief Triagem das submissões da revista:
 *
 *  - Submission::validateSubmit: o envio só é concluído com os arquivos obrigatórios do
 *    tipo de artigo (rules.php), cada um com o seu tipo de arquivo. O assistente mostra
 *    o que falta já no passo de revisão, com o link dos modelos.
 *  - SubmissionSubmitted: e-mail "[NVAS] Nova submissão #id: ..." para os gestores da
 *    revista e os endereços do ajuste notifyEmails, com o relatório da triagem (o aviso
 *    padrão do OJS diz só que "precisa de um editor").
 *  - Página /<revista>/screening/view/<id> (e /data/<id> em JSON), só para editores.
 *  - Painel "Triagem editorial" no topo da etapa Submissão do fluxo (js/screening.js),
 *    com o que foi enviado, o que falta e a recomendação: seguir ou devolver.
 *
 *  Registrado por scripts/configure-journal.php ("plugins.install" do journal.json).
 */

namespace APP\plugins\generic\csvetScreening;

use APP\core\Application;
use APP\facades\Repo;
use APP\submission\Submission;
use APP\template\TemplateManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use PKP\context\Context;
use PKP\facades\Locale;
use PKP\mail\Mailable;
use PKP\observers\events\SubmissionSubmitted;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\security\Role;

class CsvetScreeningPlugin extends GenericPlugin
{
    private ?Screening $screening = null;

    public function register($category, $path, $mainContextId = null)
    {
        if (!parent::register($category, $path, $mainContextId)) {
            return false;
        }
        if (Application::isInstalled() && !Application::isUnderMaintenance()) {
            Hook::add('Submission::validateSubmit', $this->validate(...));
            Hook::add('LoadHandler', $this->loadHandler(...));
            Hook::add('TemplateManager::display', $this->addWorkflowPanel(...));
            Event::listen(SubmissionSubmitted::class, $this->notifyEditors(...));
        }
        return true;
    }

    public function getDisplayName()
    {
        return 'CSVET: triagem das submissões';
    }

    public function getDescription()
    {
        return 'Arquivos obrigatórios por tipo de artigo no envio, e-mail ao editor a cada nova submissão com a triagem e painel de triagem no fluxo editorial.';
    }

    public function screening(): Screening
    {
        return $this->screening ??= new Screening(require __DIR__ . '/rules.php');
    }

    /** Idioma dos relatórios ao editor (e-mail): ajuste "locale", senão português */
    public function editorLocale(Context $context): string
    {
        $locale = (string) ($this->getSetting($context->getId(), 'locale') ?: 'pt_BR');
        return in_array($locale, $context->getSupportedLocales(), true) ? $locale : $context->getPrimaryLocale();
    }

    /** Usuário logado pode ver a triagem: gestor ou editor de seção da revista */
    public function canScreen(?Context $context): bool
    {
        $user = Application::get()->getRequest()->getUser();
        return $context && $user && $user->hasRole([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR], $context->getId());
    }

    // ------------------------------------------------------------------ envio

    public function validate(string $hookName, array $args): bool
    {
        $errors = &$args[0];
        /** @var Submission $submission */
        $submission = $args[1];
        /** @var Context $context */
        $context = $args[2];
        try {
            $screening = $this->screening();
            $missing = $screening->missing($submission, $context);
            if (!$missing) {
                return Hook::CONTINUE;
            }
            $locale = Locale::getLocale();
            $s = $screening->strings($locale);
            $messages = [];
            foreach ($missing as $key => $genre) {
                $messages[] = $screening->fmt($s['wizardMissing'], [
                    'file' => $s['files'][$key] ?? $key,
                    'genre' => $screening->genreName($genre, $locale),
                ]);
            }
            $messages[] = $screening->fmt($s['wizardHelp'], ['url' => $s['urls']['templates']]);
            // Substitui o aviso genérico do OJS (tipos marcados como obrigatórios), que já está incluído
            $errors['files'] = $messages;
        } catch (\Throwable $e) {
            error_log('csvetScreening validate: ' . $e->getMessage());
        }
        return Hook::CONTINUE;
    }

    public function notifyEditors(SubmissionSubmitted $event): void
    {
        try {
            $context = $event->context;
            $submission = Repo::submission()->get($event->submission->getId());
            $locale = $this->editorLocale($context);
            $screening = $this->screening();
            $s = $screening->strings($locale);
            $report = $screening->report($submission, $context, $locale);

            $status = $report['missing']
                ? $screening->fmt($s['mailStatusMissing'], ['count' => $report['missing']])
                : ($report['warnings'] ? $screening->fmt($s['mailStatusWarn'], ['count' => $report['warnings']]) : $s['mailStatusOk']);
            $subject = $screening->fmt($s['mailSubject'], ['id' => $submission->getId(), 'status' => $status]);
            $body = '<p>' . htmlspecialchars($screening->fmt($s['mailIntro'], ['journal' => $context->getLocalizedName($locale)])) . '</p>'
                . $screening->html($report, $locale)
                . '<p style="font-size:12px;color:#5a6080;margin-top:16px;">' . htmlspecialchars($s['mailFooter']) . '</p>';

            foreach ($this->recipients($context) as $email => $name) {
                $mailable = new Mailable();
                $mailable
                    ->from($context->getData('contactEmail'), $context->getData('contactName'))
                    ->to([['email' => $email, 'name' => $name]])
                    ->subject($subject)
                    ->body($this->escapeTemplateVariables($body));
                Mail::send($mailable);
            }
        } catch (\Throwable $e) {
            // Sem aviso é ruim; sem envio da submissão seria pior
            error_log('csvetScreening notify: ' . $e->getMessage());
        }
    }

    /** Gestores da revista e os e-mails do ajuste notifyEmails (sem repetir) */
    private function recipients(Context $context): array
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
        foreach ((array) $this->getSetting($context->getId(), 'notifyEmails') as $email) {
            $email = strtolower(trim((string) $email));
            if (filter_var($email, FILTER_VALIDATE_EMAIL) && !isset($recipients[$email])) {
                $recipients[$email] = $context->getData('contactName') ?: $email;
            }
        }
        return $recipients;
    }

    /** O corpo do Mailable é um modelo: "{$" do texto do autor não pode virar variável */
    private function escapeTemplateVariables(string $html): string
    {
        return str_replace('{$', '{&#36;', $html);
    }

    // ------------------------------------------------------------------ página e painel

    public function loadHandler(string $hookName, array $params): bool
    {
        $page = &$params[0];
        $handler = &$params[3];
        if ($page === 'screening') {
            $handler = new ScreeningHandler($this);
            return true;
        }
        return false;
    }

    public function addWorkflowPanel(string $hookName, array $args): bool
    {
        /** @var TemplateManager $templateMgr */
        $templateMgr = $args[0];
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        if (!$this->canScreen($context) || !str_starts_with((string) $args[1], 'dashboard/')) {
            return Hook::CONTINUE;
        }
        $file = 'js/screening.js';
        $url = $request->getBaseUrl() . '/' . $this->getPluginPath() . '/' . $file . '?v=' . substr((string) md5_file(__DIR__ . '/' . $file), 0, 8);
        $config = [
            'dataUrl' => $request->getDispatcher()->url($request, Application::ROUTE_PAGE, $context->getPath(), 'screening', 'data', ['__ID__']),
            'pageUrl' => $request->getDispatcher()->url($request, Application::ROUTE_PAGE, $context->getPath(), 'screening', 'view', ['__ID__']),
            'strings' => array_intersect_key($this->screening()->strings(Locale::getLocale()), array_flip([
                'title', 'lead', 'required', 'present', 'missing', 'otherFiles', 'manualTitle', 'openScreening',
            ])),
        ];
        $templateMgr->addJavaScript('csvetScreeningConfig', 'window.csvetScreening = ' . json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ';', [
            'inline' => true,
            'contexts' => 'backend',
        ]);
        // Depois do build.js do OJS (STYLE_SEQUENCE_LATE), que cria pkp.registry: antes dele o
        // script não acha o registro e o painel não aparece
        $templateMgr->addJavaScript('csvetScreening', $url, [
            'contexts' => 'backend',
            'priority' => TemplateManager::STYLE_SEQUENCE_LAST,
        ]);
        return Hook::CONTINUE;
    }
}
