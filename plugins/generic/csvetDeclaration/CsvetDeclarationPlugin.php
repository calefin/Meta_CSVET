<?php

/**
 * @file plugins/generic/csvetDeclaration/CsvetDeclarationPlugin.php
 *
 * @class CsvetDeclarationPlugin
 *
 * @brief Declaração dos autores preenchida e validada no próprio envio, sem formulário
 *  impresso: cada item vira uma caixa obrigatória no último passo do assistente de
 *  submissão (Confirmação), junto do aceite da licença que o OJS já tem.
 *
 *  - Form::config::before: acrescenta os itens ao formulário confirmSubmission. O
 *    assistente só libera o botão Enviar com todos marcados.
 *  - Submission::validateSubmit: o servidor confere de novo (sem os itens, a
 *    submissão é recusada), exceto nas validações prévias (_validateOnly).
 *  - Submission::edit: no momento do envio, grava no histórico da submissão (Atividade)
 *    o texto aceito, com nome, usuário, data, hora e IP.
 *
 *  Textos em strings.php. Registrado por scripts/configure-journal.php.
 */

namespace APP\plugins\generic\csvetDeclaration;

use APP\core\Application;
use APP\facades\Repo;
use APP\submission\Submission;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FormComponent;
use PKP\components\forms\submission\ConfirmSubmission;
use PKP\core\Core;
use PKP\facades\Locale;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\security\Validation;

class CsvetDeclarationPlugin extends GenericPlugin
{
    /** Prefixo dos campos enviados com o formulário de confirmação */
    public const PREFIX = 'csvetDecl_';

    public function register($category, $path, $mainContextId = null)
    {
        if (!parent::register($category, $path, $mainContextId)) {
            return false;
        }
        if (Application::isInstalled() && !Application::isUnderMaintenance()) {
            Hook::add('Form::config::before', $this->addFields(...));
            Hook::add('Submission::validateSubmit', $this->validate(...));
            Hook::add('Submission::edit', $this->record(...));
        }
        return true;
    }

    public function getDisplayName()
    {
        return 'CSVET: declaração dos autores no envio';
    }

    public function getDescription()
    {
        return 'Declaração dos autores com itens obrigatórios no último passo do envio, validada pelo servidor e registrada no histórico da submissão.';
    }

    /** Textos no idioma da interface (inglês se o idioma não tiver tradução) */
    protected function strings(?string $locale = null): array
    {
        static $all = null;
        $all ??= require __DIR__ . '/strings.php';
        $locale ??= Locale::getLocale();
        return $all[$locale] ?? $all['en'];
    }

    public function addFields(string $hookName, FormComponent $form): bool
    {
        // Início do envio: o idioma da submissão é o idioma do TEXTO do manuscrito
        if ($form->id === 'startSubmission' && ($field = $form->getField('locale'))) {
            $s = $this->strings();
            $field->label = $s['startLocaleLabel'];
            $field->description = $s['startLocaleDescription'];
            return Hook::CONTINUE;
        }
        if ($form->id !== ConfirmSubmission::FORM_CONFIRM_SUBMISSION) {
            return Hook::CONTINUE;
        }
        $s = $this->strings();
        // O assistente já pôs os campos da confirmação num grupo; sem grupo o campo não
        // aparece na tela, mas ainda conta como caixa a marcar e travaria o botão Enviar
        $groupId = $form->groups[0]['id'] ?? null;
        $first = true;
        foreach ($s['items'] as $key => $item) {
            $name = self::PREFIX . $key;
            if ($form->getField($name)) {
                continue;
            }
            $radio = isset($item['choices']);
            $form->addField(new FieldOptions($name, [
                'label' => $item['label'],
                // Links do modelo/formulário do item embaixo do título (a introdução vai no primeiro)
                'description' => (($first ? $s['intro'] : '') . ($item['help'] ?? '')) ?: null,
                'groupId' => $groupId,
                'type' => $radio ? 'radio' : 'checkbox',
                'isRequired' => true,
                'options' => $radio
                    ? array_map(fn ($value, $label) => ['value' => $value, 'label' => $label], array_keys($item['choices']), $item['choices'])
                    : [['value' => true, 'label' => $item['text']]],
                'value' => $radio ? null : false,
            ]));
            $first = false;
        }
        return Hook::CONTINUE;
    }

    /** Itens marcados nesta requisição: [chave => valor] (só os válidos) */
    protected function answered(): array
    {
        $request = Application::get()->getRequest();
        $answers = [];
        foreach ($this->strings('en')['items'] as $key => $item) {
            $value = $request->getUserVar(self::PREFIX . $key);
            if (isset($item['choices'])) {
                if (is_string($value) && isset($item['choices'][$value])) {
                    $answers[$key] = $value;
                }
            } elseif ($value === true || $value === 'true' || $value === '1') {
                $answers[$key] = true;
            }
        }
        return $answers;
    }

    public function validate(string $hookName, array $args): bool
    {
        $errors = &$args[0];
        $request = Application::get()->getRequest();
        // O assistente valida sozinho ao abrir o último passo, ainda sem as caixas marcadas
        if ($request->getUserVar('_validateOnly')) {
            return Hook::CONTINUE;
        }
        $answers = $this->answered();
        $s = $this->strings();
        foreach (array_keys($s['items']) as $key) {
            if (!isset($answers[$key])) {
                $errors[self::PREFIX . $key] = [$s['required']];
            }
        }

        // Idioma do manuscrito: título e resumo precisam existir nesse idioma (aba do idioma
        // no passo Detalhes); se a submissão foi aberta no outro idioma, record() corrige
        $language = $answers['language'] ?? null;
        $submission = $args[1] ?? null;
        if ($language && $submission instanceof Submission) {
            $publication = $submission->getCurrentPublication();
            $section = $publication ? Repo::section()->get((int) $publication->getData('sectionId')) : null;
            $missing = [];
            if (!trim(strip_tags((string) ($publication?->getData('title')[$language] ?? '')))) {
                $missing[] = $s['fieldTitle'];
            }
            if (!$section?->getAbstractsNotRequired() && !trim(strip_tags((string) ($publication?->getData('abstract')[$language] ?? '')))) {
                $missing[] = $s['fieldAbstract'];
            }
            if ($missing) {
                $errors[self::PREFIX . 'language'] = [str_replace(
                    ['{$fields}', '{$language}'],
                    [implode(' / ', $missing), $s['items']['language']['choices'][$language]],
                    $s['languageMissing']
                )];
            }
        }
        return Hook::CONTINUE;
    }

    /**
     * Passa a submissão para o idioma declarado do manuscrito (como "Alterar idioma da
     * submissão" do fluxo): grava o idioma na submissão que está sendo salva e copia para
     * esse idioma os nomes de autores, filiações e arquivos que só existem no idioma antigo.
     */
    protected function switchLocale(Submission $newSubmission, string $newLocale): void
    {
        $oldLocale = $newSubmission->getData('locale');
        if (!$oldLocale || $oldLocale === $newLocale) {
            return;
        }
        $copy = fn ($item, array $props): array => collect($props)
            ->mapWithKeys(fn (string $prop): array => [$prop => ($data = ($item->getData($prop)[$oldLocale] ?? null)) ? array_merge($item->getData($prop) ?? [], [$newLocale => $data]) : null])
            ->filter()
            ->toArray();
        try {
            foreach (Repo::submissionFile()->getCollector()->filterBySubmissionIds([$newSubmission->getId()])->getMany() as $file) {
                if (!($file->getData('name')[$newLocale] ?? null) && ($props = $copy($file, ['name']))) {
                    Repo::submissionFile()->edit($file, $props);
                }
            }
            $publication = $newSubmission->getCurrentPublication();
            if ($publication) {
                foreach (Repo::author()->getCollector()->filterByPublicationIds([$publication->getId()])->getMany() as $author) {
                    foreach ($author->getAffiliations() as $affiliation) {
                        if (!($affiliation->getData('name')[$newLocale] ?? null) && ($props = $copy($affiliation, ['name']))) {
                            Repo::affiliation()->edit($affiliation, $props);
                        }
                    }
                    if (!($author->getData('givenName')[$newLocale] ?? null) && ($props = $copy($author, ['givenName', 'familyName', 'preferredPublicName']))) {
                        Repo::author()->edit($author, $props);
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('csvetDeclaration: cópia para o idioma ' . $newLocale . ' falhou na submissão ' . $newSubmission->getId() . ': ' . $e->getMessage());
        }
        // Gravado pelo próprio Submission::edit logo depois deste gancho
        $newSubmission->setData('locale', $newLocale);
    }

    public function record(string $hookName, array $args): bool
    {
        /** @var Submission $newSubmission */
        [$newSubmission, $submission, $params] = $args;
        $isSubmitting = array_key_exists('submissionProgress', $params)
            && $params['submissionProgress'] === ''
            && $submission->getData('submissionProgress') !== '';
        if (!$isSubmitting) {
            return Hook::CONTINUE;
        }
        $answers = $this->answered();
        $s = $this->strings();
        if (count($answers) !== count($s['items'])) {
            return Hook::CONTINUE;
        }

        $request = Application::get()->getRequest();
        $user = $request->getUser();
        $lines = [];
        foreach ($s['items'] as $key => $item) {
            $lines[] = '[x] ' . (isset($item['choices']) ? $item['label'] . ': ' . $item['choices'][$answers[$key]] : $item['text']);
        }
        $message = str_replace(
            ['{$name}', '{$username}', '{$date}', '{$ip}'],
            [$user?->getFullName() ?? '', $user?->getUsername() ?? '', Core::getCurrentDate(), $request->getRemoteAddr()],
            $s['logHeader']
        ) . ' ' . implode(' ', $lines);

        Repo::eventLog()->add(Repo::eventLog()->newDataObject([
            'assocType' => Application::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submission->getId(),
            'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_COPYRIGHT_AGREED,
            'userId' => Validation::loggedInAs() ?? $user?->getId(),
            'message' => $message,
            'isTranslated' => true,
            'dateLogged' => Core::getCurrentDate(),
            'username' => $user?->getUsername(),
            'userFullName' => $user?->getFullName(),
        ]));

        // Submissão aberta num idioma e manuscrito escrito no outro: título, resumo,
        // e-mails e convites passam a sair no idioma do manuscrito
        if (isset($answers['language'])) {
            $this->switchLocale($newSubmission, $answers['language']);
        }
        return Hook::CONTINUE;
    }
}
