<?php

/**
 * @file plugins/generic/csvetEditorial/CsvetEditorialPlugin.php
 *
 * @class CsvetEditorialPlugin
 *
 * @brief Regras editoriais da Editora CSVET que precisam valer em todas as requisições
 *  (inclusive na API usada pelo formulário de submissão), por isso num plugin genérico:
 *  - termo de submissão obrigatório, com registro de aceite (usuário, data, IP, navegador e
 *    impressão digital SHA-256 do texto aceito) no histórico de atividades da submissão;
 *  - orientação e banca (papéis Orientador(a), Coorientador(a), Membro da banca examinadora)
 *    fora das listas de autores do catálogo;
 *  - e-mails em HTML com a identidade visual da editora;
 *  - marca d'água personalizada nos e-books vendidos (Watermark.php).
 */

namespace APP\plugins\generic\csvetEditorial;

use APP\core\Application;
use APP\facades\Repo;
use PKP\config\Config;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\security\Validation;

class CsvetEditorialPlugin extends GenericPlugin
{
    /** Papéis de crédito acadêmico (nome em inglês do user group): não são autoria */
    public const CREDIT_GROUPS = ['Supervisor', 'Co-supervisor', 'Examining committee member'];

    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if ($success && $this->getEnabled($mainContextId)) {
            Hook::add('Form::config::after', $this->requireTerms(...));
            Hook::add('Submission::validateSubmit', $this->validateTerms(...));
            Hook::add('Submission::edit', $this->logTerms(...));
            Hook::add('Author::add::before', $this->creditNotInBrowse(...));
            Hook::add('Author::edit', $this->creditNotInBrowse(...));
            Hook::add('Email::send::before', $this->brandEmail(...));
            Hook::add('CatalogBookHandler::download', (new Watermark())->download(...));
        }
        return $success;
    }

    public function getDisplayName()
    {
        return __('plugins.generic.csvetEditorial.name');
    }

    public function getDescription()
    {
        return __('plugins.generic.csvetEditorial.description');
    }

    // ------------------------------------------------------------------ termo de submissão

    /** Formulário "Revise e submeta": a caixa de concordância com o termo passa a ser obrigatória */
    public function requireTerms(string $hookName, array $args): bool
    {
        $config = &$args[0];
        if (($config['id'] ?? null) !== 'confirmSubmission') {
            return false;
        }
        foreach ($config['fields'] as &$field) {
            if (($field['name'] ?? null) === 'confirmCopyright') {
                $field['isRequired'] = true;
                $field['label'] = __('plugins.generic.csvetEditorial.terms.label');
                $field['options'][0]['label'] = __('plugins.generic.csvetEditorial.terms.agree');
            }
        }
        return false;
    }

    /** No envio definitivo, recusa a submissão sem a concordância com o termo */
    public function validateTerms(string $hookName, array $args): bool
    {
        $errors = &$args[0];
        $context = $args[2];
        $input = $this->input();
        if (!empty($input['_validateOnly']) || !$context->getLocalizedData('copyrightNotice')) {
            return false;
        }
        if (empty($input['confirmCopyright']) || $input['confirmCopyright'] === 'false') {
            $errors['confirmCopyright'] = [__('plugins.generic.csvetEditorial.terms.required')];
        }
        return false;
    }

    /** Ao concluir a submissão, registra a prova do aceite no histórico de atividades */
    public function logTerms(string $hookName, array $args): bool
    {
        [$newSubmission, , $params] = $args;
        $input = $this->input();
        if (empty($params['dateSubmitted']) || empty($input['confirmCopyright'])) {
            return false;
        }
        try {
            $request = Application::get()->getRequest();
            $context = $request->getContext();
            $user = $request->getUser();
            if (!$context || !$user) {
                return false;
            }
            $locale = $newSubmission->getData('locale') ?: $context->getPrimaryLocale();
            $terms = $context->getData('copyrightNotice', $locale) ?: $context->getLocalizedData('copyrightNotice');
            $hash = hash('sha256', (string) $terms);
            $message = __('plugins.generic.csvetEditorial.terms.log', [
                'name' => $user->getFullName(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'date' => Core::getCurrentDate(),
                'ip' => $request->getRemoteAddr(),
                'agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200),
                'hash' => $hash,
                'version' => substr($hash, 0, 12),
            ]);
            Repo::eventLog()->add(Repo::eventLog()->newDataObject([
                'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
                'assocId' => $newSubmission->getId(),
                'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_COPYRIGHT_AGREED,
                'userId' => Validation::loggedInAs() ?? $user->getId(),
                'message' => $message,
                'isTranslated' => true,
                'dateLogged' => Core::getCurrentDate(),
            ]));
        } catch (\Throwable $e) {
            error_log('[csvetEditorial] registro do termo: ' . $e->getMessage());
        }
        return false;
    }

    /** Parâmetros da requisição (formulário ou JSON da API) */
    private function input(): array
    {
        static $input;
        if ($input === null) {
            $json = json_decode((string) file_get_contents('php://input'), true);
            $input = array_merge($_GET, $_POST, is_array($json) ? $json : []);
        }
        return $input;
    }

    // ------------------------------------------------------------------ créditos acadêmicos

    public function creditNotInBrowse(string $hookName, array $args): bool
    {
        $author = $args[0];
        $groupId = (int) $author->getData('userGroupId');
        if ($groupId && \Illuminate\Support\Facades\DB::table('user_group_settings')
            ->where('user_group_id', $groupId)->where('setting_name', 'name')->where('locale', 'en')
            ->whereIn('setting_value', self::CREDIT_GROUPS)->exists()) {
            $author->setData('includeInBrowse', false);
        }
        return false;
    }

    // ------------------------------------------------------------------ e-mails

    /** Envolve o corpo HTML de todo e-mail no layout da editora (uma vez só) */
    public function brandEmail(string $hookName, array $args): bool
    {
        /** @var \Symfony\Component\Mime\Email $message */
        $message = $args['message'] ?? $args[0] ?? null;
        if (!$message || !method_exists($message, 'getHtmlBody')) {
            return false;
        }
        $html = $message->getHtmlBody();
        if (is_resource($html)) {
            $html = stream_get_contents($html);
        }
        if (!is_string($html) || $html === '' || str_contains($html, '<!--csvet-email-->')) {
            return false;
        }
        $message->html($this->layout($html, (string) $message->getSubject()));
        return false;
    }

    public function layout(string $body, string $subject): string
    {
        $base = rtrim((string) Config::getVar('general', 'base_url'), '/');
        $logo = $base . '/plugins/generic/csvetEditorial/images/email-logo.png';
        $site = $base . '/editora';
        $year = date('Y');
        $title = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
        // Links na cor da marca (clientes de e-mail ignoram CSS em <style>)
        $body = preg_replace('/<a (?![^>]*style=)/i', '<a style="color:#0088cc;" ', $body);
        $footer = __('plugins.generic.csvetEditorial.email.footer', ['year' => $year]);
        $notice = __('plugins.generic.csvetEditorial.email.notice');
        return <<<HTML
<!--csvet-email--><!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{$title}</title></head>
<body style="margin:0;padding:0;background:#eef0f6;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef0f6;padding:24px 12px;">
<tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:10px;overflow:hidden;font-family:Roboto,'Segoe UI',Arial,sans-serif;">
<tr><td style="background:#000080;padding:22px 32px;border-bottom:4px solid #00aaff;">
<a href="{$site}"><img src="{$logo}" width="190" alt="Editora CSVET" style="display:block;border:0;width:190px;max-width:100%;height:auto;"></a>
</td></tr>
<tr><td style="padding:30px 32px 10px;color:#1d2233;font-size:15px;line-height:1.6;">
{$body}
</td></tr>
<tr><td style="padding:10px 32px 28px;">
<table role="presentation" cellpadding="0" cellspacing="0"><tr><td style="background:#00aaff;border-radius:6px;">
<a href="{$site}" style="display:inline-block;padding:10px 18px;color:#ffffff;text-decoration:none;font-size:14px;font-weight:bold;">books.csvet.com.br</a>
</td></tr></table>
</td></tr>
<tr><td style="background:#00003d;padding:18px 32px;color:#c6c6c6;font-size:12px;line-height:1.5;">
{$footer}<br><span style="color:#8d91b8;">{$notice}</span>
</td></tr>
</table>
</td></tr></table>
</body></html>
HTML;
    }
}
