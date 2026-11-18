<?php

/**
 * @file plugins/generic/csvetReviewReminder/CsvetReviewReminderPlugin.php
 *
 * @class CsvetReviewReminderPlugin
 *
 * @brief Modelos na janela "Lembrete ao Avaliador".
 *
 *  O OJS 3.5 manda o lembrete sempre com o modelo REVIEW_REMIND, escrito para quem já aceitou
 *  a avaliação. Aqui a janela ganha um seletor com o REVIEW_REMIND e os modelos da revista
 *  oferecidos como opção dele ("alternateTo": "REVIEW_REMIND" no journal.json):
 *  - NVAS_REVIEW_INVITE_RESEND, já escolhido quando o avaliador ainda não respondeu ao convite;
 *  - NVAS_REVIEW_OVERDUE, já escolhido quando a avaliação aceita passou do prazo.
 *  O texto continua editável, e o assunto do e-mail acompanha o modelo escolhido.
 *
 *  Abaixo da mensagem, para gestores e editores de seção, fica um texto curto com um link de
 *  acesso direto novo, pronto para copiar e mandar ao avaliador por WhatsApp, caso o e-mail não
 *  chegue ou para reforçar. O link só continua valendo depois de outros envios com o patch 2 do
 *  scripts/patch-ojs.sh (sem ele, o OJS apaga os links anteriores do avaliador).
 *
 *  O OJS só mostra o botão "Enviar lembrete" com o prazo vencido; js/reviewerActions.js o
 *  põe também para convites em aberto e avaliações aceitas dentro do prazo.
 *
 *  É um plugin genérico sem lazy-load (sempre ativo depois de registrado pelo
 *  scripts/configure-journal.php).
 */

namespace APP\plugins\generic\csvetReviewReminder;

use APP\core\Application;
use APP\facades\Repo;
use APP\template\TemplateManager;
use Illuminate\Support\Facades\Mail;
use PKP\controllers\grid\users\reviewer\form\ReviewReminderForm;
use PKP\core\Core;
use PKP\facades\Locale;
use PKP\invitation\core\enums\InvitationAction;
use PKP\invitation\invitations\reviewerAccess\ReviewerAccessInvite;
use PKP\mail\Mailable;
use PKP\mail\mailables\ReviewRemind;
use PKP\mail\variables\ReviewAssignmentEmailVariable;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\security\Role;
use PKP\submission\reviewAssignment\ReviewAssignment;

class CsvetReviewReminderPlugin extends GenericPlugin
{
    private const INVITE_RESEND = 'NVAS_REVIEW_INVITE_RESEND';
    private const OVERDUE = 'NVAS_REVIEW_OVERDUE';

    public function register($category, $path, $mainContextId = null)
    {
        if (!parent::register($category, $path, $mainContextId)) {
            return false;
        }
        if (Application::isInstalled() && !Application::isUnderMaintenance()) {
            Hook::add('reviewreminderform::display', $this->addTemplates(...));
            Hook::add('Mailable::build', $this->useSubject(...));
            Hook::add('TemplateManager::display', $this->addReviewerActions(...));
        }
        return true;
    }

    public function getDisplayName()
    {
        return 'CSVET: modelos no lembrete ao avaliador';
    }

    public function getDescription()
    {
        return 'Na janela Lembrete ao Avaliador, escolhe entre o lembrete, o reenvio do convite e a cobrança de avaliação em atraso.';
    }

    /** Botão "Enviar lembrete" antes do prazo, na lista de avaliadores do fluxo editorial */
    public function addReviewerActions(string $hookName, array $args): bool
    {
        /** @var TemplateManager $templateMgr */
        $templateMgr = $args[0];
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $user = $request->getUser();
        if (!$context || !$user || !str_starts_with((string) $args[1], 'dashboard/')
            || !$user->hasRole([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR], $context->getId())) {
            return Hook::CONTINUE;
        }
        $file = 'js/reviewerActions.js';
        $url = $request->getBaseUrl() . '/' . $this->getPluginPath() . '/' . $file . '?v=' . substr((string) md5_file(__DIR__ . '/' . $file), 0, 8);
        // Depois do build.js do OJS, que cria pkp.registry (ver csvetScreening)
        $templateMgr->addJavaScript('csvetReviewerActions', $url, [
            'contexts' => 'backend',
            'priority' => TemplateManager::STYLE_SEQUENCE_LAST,
        ]);
        return Hook::CONTINUE;
    }

    /** REVIEW_REMIND e as opções dele, na ordem do seletor */
    private function templates(int $contextId): array
    {
        $templates = [];
        $default = Repo::emailTemplate()->getByKey($contextId, ReviewRemind::getEmailTemplateKey());
        if ($default) {
            $templates[$default->getData('key')] = $default;
        }
        $alternates = Repo::emailTemplate()->getCollector($contextId)
            ->alternateTo([ReviewRemind::getEmailTemplateKey()])
            ->getMany();
        foreach ($alternates as $template) {
            $templates[$template->getData('key')] = $template;
        }
        return $templates;
    }

    /** Modelo que combina com a situação do avaliador */
    private function suggested(ReviewAssignment $reviewAssignment, array $templates): string
    {
        if (!$reviewAssignment->getDateConfirmed() && isset($templates[self::INVITE_RESEND])) {
            return self::INVITE_RESEND;
        }
        $due = $reviewAssignment->getDateDue();
        if ($reviewAssignment->getDateConfirmed() && $due && strtotime($due) < strtotime(Core::getCurrentDate())
            && isset($templates[self::OVERDUE])) {
            return self::OVERDUE;
        }
        return ReviewRemind::getEmailTemplateKey();
    }

    public function addTemplates(string $hookName, array $args): bool
    {
        /** @var ReviewReminderForm $form */
        $form = $args[0];
        try {
            $request = Application::get()->getRequest();
            $context = $request->getContext();
            $reviewAssignment = $form->getReviewAssignment();
            $templates = $this->templates($context->getId());

            // Mesmas variáveis do texto que o OJS põe na janela (ReviewReminderForm::initData)
            $submission = Repo::submission()->get($reviewAssignment->getSubmissionId());
            $reviewer = Repo::user()->get($reviewAssignment->getReviewerId());
            $mailable = new ReviewRemind($context, $submission, $reviewAssignment);
            $mailable->sender($request->getUser())->recipients([$reviewer]);
            $data = $mailable->getData(Locale::getLocale());
            // O link de acesso direto do avaliador não aparece no e-mail editável; o OJS o preenche no envio
            $data[ReviewAssignmentEmailVariable::REVIEW_ASSIGNMENT_URL] = '{$' . ReviewAssignmentEmailVariable::REVIEW_ASSIGNMENT_URL . '}';

            $options = [];
            $selected = '';
            if (count($templates) >= 2) {
                foreach ($templates as $key => $template) {
                    $options[$key] = [
                        'name' => $template->getLocalizedData('name') ?: $key,
                        'body' => Mail::compileParams((string) $template->getLocalizedData('body'), $data),
                    ];
                }
                $selected = $this->suggested($reviewAssignment, $templates);
                $form->setData('message', $options[$selected]['body']);
            }

            $linkMessage = $this->linkMessage($reviewAssignment, $reviewer, $data);
            if (!$options && $linkMessage === null) {
                return Hook::CONTINUE;
            }

            $pt = str_starts_with(Locale::getLocale(), 'pt');
            $weeks = (int) $context->getData('numWeeksPerReview') + 4; // ReviewerAccessInvite::getExpiryDays
            TemplateManager::getManager($request)->assign([
                'csvetReminderOptions' => array_map(fn ($o) => $o['name'], $options),
                'csvetReminderSelected' => $selected,
                'csvetReminderLabel' => $pt ? 'Modelo da mensagem' : 'Message template',
                'csvetReminderBodies' => json_encode(
                    (object) array_map(fn ($o) => $o['body'], $options),
                    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
                ),
                'csvetLinkMessage' => $linkMessage,
                'csvetLinkLabel' => $pt ? 'Mensagem com o link, para enviar por WhatsApp' : 'Message with the link, to send by WhatsApp',
                'csvetLinkCopy' => $pt ? 'Copiar mensagem' : 'Copy message',
                'csvetLinkCopied' => $pt ? 'Copiada.' : 'Copied.',
                'csvetLinkHelp' => $pt
                    ? "O link entra no sistema como o avaliador, sem senha: envie só para essa pessoa e não o abra no seu navegador logado como editor. Vale por {$weeks} semanas, mesmo que outros lembretes sejam enviados. Para mandar só pelo WhatsApp, copie a mensagem e feche esta janela sem enviar o e-mail."
                    : "The link signs in as the reviewer, with no password: send it only to them and do not open it in your browser while signed in as editor. It is valid for {$weeks} weeks, even if other reminders are sent. To send it only by WhatsApp, copy the message and close this window without sending the e-mail.",
            ]);
            $form->setTemplate($this->getTemplateResource('reviewReminderForm.tpl'));
        } catch (\Throwable $e) {
            // Sem seletor, a janela do OJS continua funcionando como antes
            error_log('csvetReviewReminder: ' . $e->getMessage());
        }
        return Hook::CONTINUE;
    }

    /**
     * Texto curto com um link de acesso direto novo, para o editor copiar e mandar ao avaliador
     * (WhatsApp). Só para gestores e editores de seção; null quando o acesso direto está desligado.
     */
    private function linkMessage(ReviewAssignment $reviewAssignment, $reviewer, array $data): ?string
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $user = $request->getUser();
        if (!$context->getData('reviewerAccessKeysEnabled') || !$user
            || !$user->hasRole([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR], $context->getId())) {
            return null;
        }

        try {
            // Mesmo caminho do OJS no lembrete (PKP\mail\traits\OneClickReviewerAccess), sem e-mail
            $invitation = new ReviewerAccessInvite();
            $invitation->initialize($reviewAssignment->getReviewerId(), $context->getId(), null, $user->getId());
            $invitation->getPayload()->reviewAssignmentId = $reviewAssignment->getId();
            $url = $invitation->updatePayload() && $invitation->invite()
                ? $invitation->getActionURL(InvitationAction::ACCEPT)
                : null;
        } catch (\Throwable $e) {
            error_log('csvetReviewReminder link: ' . $e->getMessage());
            $url = null;
        }
        if (!$url) {
            return null;
        }

        $name = trim((string) $reviewer->getLocalizedGivenName()) ?: $reviewer->getFullName();
        $title = (string) ($data['submissionTitle'] ?? '');
        $responseDue = (string) ($data[ReviewAssignmentEmailVariable::RESPONSE_DUE_DATE] ?? '');
        $reviewDue = (string) ($data[ReviewAssignmentEmailVariable::REVIEW_DUE_DATE] ?? '');
        $journal = $context->getLocalizedName();
        $sender = $user->getFullName();

        if (str_starts_with(Locale::getLocale(), 'pt')) {
            $lines = ["Olá, {$name}! Aqui é {$sender}, da {$journal}."];
            if (!$reviewAssignment->getDateConfirmed()) {
                $lines[] = "Enviamos por e-mail o convite para você avaliar o manuscrito \"{$title}\". Para garantir, segue o link direto (entra no sistema sem senha), onde você aceita ou recusa o convite:";
                $lines[] = $url;
                $lines[] = "Prazo para responder: {$responseDue}. Se aceitar, a avaliação vai até {$reviewDue}.";
            } else {
                $lines[] = "Segue o link direto para a sua avaliação do manuscrito \"{$title}\" (entra no sistema sem senha):";
                $lines[] = $url;
                $lines[] = "Prazo da avaliação: {$reviewDue}.";
            }
            $lines[] = 'Qualquer dúvida, é só me chamar por aqui. Obrigado!';
        } else {
            $lines = ["Hello, {$name}! This is {$sender}, from {$journal}."];
            if (!$reviewAssignment->getDateConfirmed()) {
                $lines[] = "We have e-mailed you an invitation to review the manuscript \"{$title}\". Just in case, here is the direct link (it signs you in with no password), where you can accept or decline the invitation:";
                $lines[] = $url;
                $lines[] = "Please respond by {$responseDue}. If you accept, the review is due by {$reviewDue}.";
            } else {
                $lines[] = "Here is the direct link to your review of the manuscript \"{$title}\" (it signs you in with no password):";
                $lines[] = $url;
                $lines[] = "Review due date: {$reviewDue}.";
            }
            $lines[] = 'If you have any questions, just message me here. Thank you!';
        }
        return implode("\n\n", $lines);
    }

    /**
     * Assunto do modelo escolhido na janela. O OJS põe o assunto do REVIEW_REMIND antes de
     * enviar; Mailable::build roda no envio, depois disso.
     */
    public function useSubject(string $hookName, Mailable $mailable): bool
    {
        try {
            if (!$mailable instanceof ReviewRemind) {
                return Hook::CONTINUE;
            }
            $request = Application::get()->getRequest();
            $key = (string) $request->getUserVar('csvetTemplate');
            $context = $request->getContext();
            if ($key === '' || $key === ReviewRemind::getEmailTemplateKey() || !$context) {
                return Hook::CONTINUE;
            }
            $template = $this->templates($context->getId())[$key] ?? null;
            if ($template) {
                $mailable->subject(Mail::compileParams(
                    (string) $template->getLocalizedData('subject'),
                    $mailable->getData(Locale::getLocale())
                ));
            }
        } catch (\Throwable $e) {
            error_log('csvetReviewReminder subject: ' . $e->getMessage());
        }
        return Hook::CONTINUE;
    }
}
