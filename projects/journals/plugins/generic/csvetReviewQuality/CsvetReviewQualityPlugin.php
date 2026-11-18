<?php

/**
 * @file plugins/generic/csvetReviewQuality/CsvetReviewQualityPlugin.php
 *
 * @class CsvetReviewQualityPlugin
 *
 * @brief Qualidade dos pareceres, como nas revistas internacionais indexadas:
 *
 *  - Envio do parecer (reviewerreviewstep3form::validate): as perguntas de texto com
 *    mínimo de palavras (minWords) recusam respostas curtas; a recomendação "Aceitar"
 *    não pode ser combinada com respostas que indicam problema grave (blocksAccept);
 *    parecer sem formulário exige minWordsWithoutForm palavras. "Salvar para depois"
 *    não é validado.
 *  - Convite (ReviewAssignment::add e ::edit): convite em aberto nunca fica sem formulário;
 *    sem escolha (ou com "Nenhum"), vale o padrão da seção (defaultForms, por sigla;
 *    * = demais). O configure-journal.php também grava esse padrão na seção, para ele já
 *    aparecer selecionado na janela "Adicionar avaliador".
 *  - Anonimato: arquivos dos tipos em blockedGenres (folha de rosto, carta, ética,
 *    termos, autorizações) nunca chegam ao avaliador. Três camadas: (1) somem da lista
 *    "Selecionar arquivos" das decisões que mandam para avaliação; (2) a janela de
 *    arquivos da rodada de avaliação recusa esses tipos; (3) o avaliador não consegue
 *    baixá-los, mesmo que tenham ido para a avaliação por engano.
 *
 *  As regras por pergunta (id do elemento) são gravadas por scripts/configure-journal.php
 *  a partir de reviewForms do journal.json. Textos em strings.php.
 */

namespace APP\plugins\generic\csvetReviewQuality;

use APP\core\Application;
use APP\facades\Repo;
use APP\template\TemplateManager;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\security\Role;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\submission\reviewAssignment\ReviewAssignment;

class CsvetReviewQualityPlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null)
    {
        if (!parent::register($category, $path, $mainContextId)) {
            return false;
        }
        if (Application::isInstalled() && !Application::isUnderMaintenance()) {
            Hook::add('reviewerreviewstep3form::validate', $this->validateReview(...));
            Hook::add('ReviewAssignment::add', $this->defaultForm(...));
            Hook::add('ReviewAssignment::edit', $this->keepForm(...));
            Hook::add('TemplateManager::display', $this->filterPromoteFiles(...));
            Hook::add('managereviewfilesform::validate', $this->validateReviewFiles(...));
            Hook::add('File::download', $this->guardReviewerDownload(...));
        }
        return true;
    }

    public function getDisplayName()
    {
        return 'CSVET: qualidade dos pareceres';
    }

    public function getDescription()
    {
        return 'Mínimo de palavras nas respostas de texto, recomendação coerente com as respostas, formulário de avaliação sempre presente e arquivos com os nomes dos autores fora do alcance dos avaliadores.';
    }

    /** Ids dos tipos de arquivo que nunca vão aos avaliadores */
    protected function blockedGenreIds(int $contextId): array
    {
        static $cache = [];
        if (!isset($cache[$contextId])) {
            $genreDao = DAORegistry::getDAO('GenreDAO');
            $cache[$contextId] = [];
            foreach ((array) ($this->getSetting($contextId, 'blockedGenres') ?: []) as $key) {
                $genre = $genreDao->getByKey($key, $contextId);
                if ($genre) {
                    $cache[$contextId][] = (int) $genre->getId();
                }
            }
        }
        return $cache[$contextId];
    }

    /** (1) Decisão: tira os tipos bloqueados das listas de arquivos que vão para a avaliação */
    public function filterPromoteFiles(string $hookName, array $args): bool
    {
        /** @var TemplateManager $templateMgr */
        $templateMgr = $args[0];
        if (($args[1] ?? null) !== 'decision/record.tpl') {
            return Hook::CONTINUE;
        }
        $context = Application::get()->getRequest()->getContext();
        $blocked = $context ? $this->blockedGenreIds($context->getId()) : [];
        $steps = $templateMgr->getState('steps');
        if (!$blocked || !is_array($steps)) {
            return Hook::CONTINUE;
        }
        $reviewStages = [4, 19]; // SubmissionFile::SUBMISSION_FILE_REVIEW_FILE e INTERNAL_REVIEW_FILE
        foreach ($steps as $step) {
            if (!is_object($step) || !isset($step->lists) || !in_array((int) ($step->to ?? 0), $reviewStages, true)) {
                continue;
            }
            $removed = [];
            foreach ($step->lists as $i => $list) {
                $kept = [];
                foreach ($list['files'] as $file) {
                    $file = (array) $file;
                    if (in_array((int) ($file['genreId'] ?? 0), $blocked, true)) {
                        $removed[] = (int) $file['id'];
                    } else {
                        $kept[] = $file;
                    }
                }
                $step->lists[$i]['files'] = $kept;
            }
            $step->selected = array_values(array_diff(array_map(intval(...), (array) ($step->selected ?? [])), $removed));
            if ($removed) {
                $step->description = ($step->description ?? '') . ' ' . $this->strings()['promoteFilteredNote'];
            }
        }
        $templateMgr->setState(['steps' => $steps]);
        return Hook::CONTINUE;
    }

    /** (2) Arquivos da rodada de avaliação (janela de selecionar arquivos da rodada) */
    public function validateReviewFiles(string $hookName, array $args): bool
    {
        $form = $args[0];
        $context = Application::get()->getRequest()->getContext();
        $blocked = $context ? $this->blockedGenreIds($context->getId()) : [];
        foreach ((array) $form->getData('selectedFiles') as $fileId) {
            $file = Repo::submissionFile()->get((int) $fileId);
            if ($file && in_array((int) $file->getData('genreId'), $blocked, true)) {
                $form->addError('selectedFiles', str_replace('{$file}', (string) $file->getLocalizedData('name'), $this->strings()['reviewFileBlocked']));
            }
        }
        return Hook::CONTINUE;
    }

    /** (3) Avaliador nunca baixa arquivo de tipo bloqueado, mesmo que tenha ido para a avaliação */
    public function guardReviewerDownload(string $hookName, array $args): bool
    {
        $request = Application::get()->getRequest();
        $user = $request->getUser();
        $context = $request->getContext();
        $submissionFileId = (int) $request->getUserVar('submissionFileId');
        if (!$user || !$context || !$submissionFileId) {
            return Hook::CONTINUE;
        }
        $submissionFile = Repo::submissionFile()->get($submissionFileId);
        if (!$submissionFile || !in_array((int) $submissionFile->getData('genreId'), $this->blockedGenreIds($context->getId()), true)) {
            return Hook::CONTINUE;
        }
        // Editores, assistentes e gestores da revista baixam normalmente
        $isStaff = Repo::user()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByRoleIds([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT])
            ->filterByUserIds([$user->getId()])
            ->getCount();
        if ($isStaff) {
            return Hook::CONTINUE;
        }
        $isReviewer = Repo::reviewAssignment()->getCollector()
            ->filterBySubmissionIds([(int) $submissionFile->getData('submissionId')])
            ->filterByReviewerIds([$user->getId()])
            ->getMany()
            ->count();
        if (!$isReviewer) {
            return Hook::CONTINUE;
        }
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo $this->strings()['downloadBlocked'];
        return true;
    }

    protected function strings(): array
    {
        static $all = null;
        $all ??= require __DIR__ . '/strings.php';
        return $all[Locale::getLocale()] ?? $all['en'];
    }

    public static function countWords(?string $text): int
    {
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return preg_match_all('/[\p{L}\p{N}]+(?:[\'’-][\p{L}\p{N}]+)*/u', $text);
    }

    public function validateReview(string $hookName, array $args): bool
    {
        $form = $args[0];
        if (!method_exists($form, 'getReviewAssignment')) {
            return Hook::CONTINUE;
        }
        /** @var ReviewAssignment $review */
        $review = $form->getReviewAssignment();
        $contextId = (int) Application::get()->getRequest()->getContext()?->getId();
        $s = $this->strings();

        $reviewFormId = (int) $review->getReviewFormId();
        if (!$reviewFormId) {
            $min = (int) ($this->getSetting($contextId, 'minWordsWithoutForm') ?: 0);
            $count = self::countWords($form->getData('comments'));
            if ($min && $count < $min) {
                $form->addError('comments', str_replace(['{$min}', '{$count}'], [$min, $count], $s['commentsTooShort']));
            }
            return Hook::CONTINUE;
        }

        $rules = $this->getSetting($contextId, 'rules') ?: [];
        $responses = $form->getData('reviewFormResponses') ?: [];
        $elementDao = DAORegistry::getDAO('ReviewFormElementDAO');
        $blocking = [];
        foreach ($elementDao->getByReviewFormId($reviewFormId)->toArray() as $element) {
            $rule = $rules[(string) $element->getId()] ?? null;
            if (!$rule) {
                continue;
            }
            $value = $responses[$element->getId()] ?? null;
            $question = $element->getLocalizedQuestion();
            if (!empty($rule['minWords']) && !is_array($value)) {
                $count = self::countWords((string) $value);
                // Pergunta opcional deixada em branco passa; respondida, precisa do mínimo
                if ($count < (int) $rule['minWords'] && ($element->getRequired() || $count > 0)) {
                    $form->addError(
                        'reviewFormResponses[' . $element->getId() . ']',
                        str_replace(['{$question}', '{$min}', '{$count}'], [$question, (int) $rule['minWords'], $count], $s['answerTooShort'])
                    );
                }
            }
            if (!empty($rule['blocksAccept']) && $value !== null && !is_array($value)
                && in_array((int) $value, array_map(intval(...), $rule['blocksAccept']), true)) {
                $answers = $element->getLocalizedPossibleResponses() ?: [];
                $blocking[] = $question . ': "' . (array_values($answers)[(int) $value] ?? '') . '"';
            }
        }

        if ($blocking && (int) $form->getData('recommendation') === ReviewAssignment::SUBMISSION_REVIEWER_RECOMMENDATION_ACCEPT) {
            $form->addError('recommendation', str_replace('{$answers}', implode('; ', $blocking), $s['acceptInconsistent']));
        }
        return Hook::CONTINUE;
    }

    public function defaultForm(string $hookName, array $args): bool
    {
        /** @var ReviewAssignment $review */
        $review = $args[0];
        if ($review->getReviewFormId()) {
            return Hook::CONTINUE;
        }
        $formId = $this->defaultFormId($review);
        if ($formId) {
            Repo::reviewAssignment()->edit($review, ['reviewFormId' => $formId]);
        }
        return Hook::CONTINUE;
    }

    /**
     * A janela "Adicionar avaliador" (ReviewerForm::execute) e a "Editar avaliação" gravam o
     * formulário escolhido logo depois de criar o convite; com "Nenhum", apagavam o padrão
     * posto em ReviewAssignment::add (caso da #3, rodada 2, em 16/11/2026). Aqui, toda gravação
     * que deixa um convite em aberto sem formulário volta ao padrão da seção. Parecer já
     * concluído fica como está.
     */
    public function keepForm(string $hookName, array $args): bool
    {
        /** @var ReviewAssignment $newReview */
        [$newReview, $review, $params] = $args;
        if (!array_key_exists('reviewFormId', $params) || $newReview->getReviewFormId() || $review->getDateCompleted()) {
            return Hook::CONTINUE;
        }
        $formId = $this->defaultFormId($newReview);
        if ($formId) {
            $newReview->setReviewFormId($formId);
        }
        return Hook::CONTINUE;
    }

    /** Formulário padrão da seção do artigo (defaultForms, por sigla; * = demais), se ativo. */
    private function defaultFormId(ReviewAssignment $review): ?int
    {
        $submission = Repo::submission()->get((int) $review->getSubmissionId());
        if (!$submission) {
            return null;
        }
        $contextId = (int) $submission->getData('contextId');
        $defaults = $this->getSetting($contextId, 'defaultForms') ?: [];
        $section = Repo::section()->get((int) $submission->getCurrentPublication()?->getData('sectionId'));
        $title = $defaults[(string) $section?->getLocalizedAbbrev()] ?? $defaults['*'] ?? null;
        if (!$title) {
            return null;
        }
        $reviewFormDao = DAORegistry::getDAO('ReviewFormDAO');
        foreach ($reviewFormDao->getByAssocId(Application::getContextAssocType(), $contextId)->toArray() as $form) {
            if ($form->getActive() && $form->getTitle('en') === $title) {
                return (int) $form->getId();
            }
        }
        return null;
    }
}
