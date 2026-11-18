<?php

/**
 * @file plugins/generic/csvetTransparency/CsvetTransparencyPlugin.php
 *
 * @class CsvetTransparencyPlugin
 *
 * @brief Transparência editorial exigida pelas bases (SciELO, PMC, Scopus):
 *
 *  - Página do artigo (Templates::Article::Details): quadro "Processo editorial" com as
 *    datas de recebimento e de aceite e o nome do editor responsável. Quando um autor é
 *    do corpo editorial (gestor ou editor de seção da revista), acrescenta a nota de que
 *    esse autor não participou do processo editorial.
 *  - Publicação (Publication::validatePublish): só publica com o ORCID verificado de
 *    todos os autores. O editor pede a verificação na aba Contribuidores (campo ORCID ›
 *    "Solicitar verificação"): o autor recebe um e-mail e confirma com um clique.
 *    Com autor do corpo editorial, exige ainda dois pareceres externos concluídos
 *    (avaliador fora do corpo editorial e que não seja autor do artigo).
 *
 *  Editor responsável = quem registrou a decisão de aceite; sem ela, o editor designado
 *  (fora os autores). Textos em strings.php. Registrado por scripts/configure-journal.php.
 */

namespace APP\plugins\generic\csvetTransparency;

use APP\core\Application;
use APP\decision\Decision;
use APP\facades\Repo;
use APP\publication\Publication;
use APP\submission\Submission;
use APP\template\TemplateManager;
use PKP\facades\Locale;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;

class CsvetTransparencyPlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null)
    {
        if (!parent::register($category, $path, $mainContextId)) {
            return false;
        }
        if (Application::isInstalled() && !Application::isUnderMaintenance()) {
            Hook::add('Templates::Article::Details', $this->articleDetails(...));
            Hook::add('Publication::validatePublish', $this->requireOrcid(...));
        }
        return true;
    }

    public function getDisplayName()
    {
        return 'CSVET: transparência editorial';
    }

    public function getDescription()
    {
        return 'Editor responsável e datas em cada artigo, nota sobre autores do corpo editorial e ORCID verificado de todos os autores para publicar.';
    }

    protected function strings(?string $locale = null): array
    {
        static $all = null;
        $all ??= require __DIR__ . '/strings.php';
        $locale ??= Locale::getLocale();
        return $all[$locale] ?? $all['en'];
    }

    /** Usuários do corpo editorial (gestores e editores de seção) da revista */
    protected function editorialBoard(int $contextId): array
    {
        static $cache = [];
        if (!isset($cache[$contextId])) {
            $cache[$contextId] = Repo::user()->getCollector()
                ->filterByContextIds([$contextId])
                ->filterByRoleIds([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR])
                ->getMany()
                ->all();
        }
        return $cache[$contextId];
    }

    /** O autor é a mesma pessoa que o usuário? (e-mail ou ORCID) */
    protected function samePerson($author, $user): bool
    {
        $email = fn ($o) => strtolower(trim((string) $o->getEmail()));
        $orcid = fn ($o) => preg_replace('/[^0-9X]/', '', strtoupper((string) $o->getData('orcid')));
        return ($email($author) !== '' && $email($author) === $email($user))
            || ($orcid($author) !== '' && $orcid($author) === $orcid($user));
    }

    /** Editor responsável: quem aceitou; sem aceite registrado, o editor designado que não é autor */
    protected function handlingEditor(Submission $submission, Publication $publication)
    {
        $accept = Repo::decision()->getCollector()
            ->filterBySubmissionIds([$submission->getId()])
            ->filterByDecisionTypes([Decision::ACCEPT])
            ->getMany()
            ->sortByDesc(fn ($d) => $d->getData('dateDecided'))
            ->first();
        $authors = $publication->getData('authors')?->all() ?? [];
        $isAuthor = function ($user) use ($authors) {
            foreach ($authors as $author) {
                if ($this->samePerson($author, $user)) {
                    return true;
                }
            }
            return false;
        };

        if ($accept && ($user = Repo::user()->get((int) $accept->getData('editorId'))) && !$isAuthor($user)) {
            return [$user, $accept->getData('dateDecided')];
        }

        // Editor de seção designado antes do gestor (o editor-chefe é designado em tudo)
        foreach ([Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_MANAGER] as $roleId) {
            $userIds = StageAssignment::withSubmissionIds([$submission->getId()])
                ->withRoleIds([$roleId])
                ->withRecommendOnly(false)
                ->orderBy('stage_assignments.date_assigned')
                ->pluck('stage_assignments.user_id');
            foreach ($userIds as $userId) {
                $user = Repo::user()->get((int) $userId);
                if ($user && !$isAuthor($user)) {
                    return [$user, $accept?->getData('dateDecided')];
                }
            }
        }
        return [null, $accept?->getData('dateDecided')];
    }

    public function articleDetails(string $hookName, array $args): bool
    {
        /** @var TemplateManager $smarty */
        $smarty = $args[1];
        $output = &$args[2];
        $submission = $smarty->getTemplateVars('article');
        $publication = $smarty->getTemplateVars('publication');
        $context = Application::get()->getRequest()->getContext();
        if (!$submission instanceof Submission || !$publication instanceof Publication || !$context) {
            return Hook::CONTINUE;
        }

        [$editor, $dateAccepted] = $this->handlingEditor($submission, $publication);

        $boardAuthors = [];
        foreach ($publication->getData('authors') ?? [] as $author) {
            foreach ($this->editorialBoard($context->getId()) as $user) {
                if ($this->samePerson($author, $user)) {
                    $boardAuthors[] = $author->getFullName();
                    break;
                }
            }
        }

        $s = $this->strings();
        $note = null;
        if ($boardAuthors) {
            $note = str_replace(
                ['{$authors}', '{$editor}', '{$policyUrl}'],
                [
                    htmlspecialchars(implode(', ', $boardAuthors)),
                    htmlspecialchars($editor?->getFullName() ?? $s['anotherEditor']),
                    htmlspecialchars(Application::get()->getRequest()->url(null, 'about', null, null, null, 'editors-articles')),
                ],
                $s['boardNote']
            );
        }

        $smarty->assign([
            'csvetT' => $s,
            'csvetDateSubmitted' => $submission->getData('dateSubmitted'),
            'csvetDateAccepted' => $dateAccepted,
            'csvetEditorName' => $editor?->getFullName(),
            'csvetEditorAffiliation' => $editor ? $editor->getLocalizedAffiliation() : null,
            'csvetBoardNote' => $note,
        ]);
        $output .= $smarty->fetch($this->getTemplateResource('editorialProcess.tpl'));
        return Hook::CONTINUE;
    }

    public function requireOrcid(string $hookName, array $args): bool
    {
        $errors = &$args[0];
        /** @var Publication $publication */
        $publication = $args[1];
        $s = $this->strings();
        $missing = [];
        foreach ($publication->getData('authors') ?? [] as $author) {
            if (!$author->getData('orcid') || !$author->getData('orcidIsVerified')) {
                $missing[] = $author->getFullName();
            }
        }
        if ($missing) {
            $errors['csvetOrcid'] = str_replace('{$authors}', implode(', ', $missing), $s['orcidRequired']);
        }

        // Artigo com autor do corpo editorial: pelo menos dois pareceres externos concluídos
        // (avaliador fora do corpo editorial e que não seja autor do artigo)
        $submission = $args[2];
        $context = Application::getContextDAO()->getById((int) $submission->getData('contextId'));
        $authors = $publication->getData('authors')?->all() ?? [];
        $board = $context ? $this->editorialBoard($context->getId()) : [];
        $isBoardOrAuthor = function ($user) use ($authors, $board) {
            foreach ($board as $member) {
                if ($member->getId() === $user->getId()) {
                    return true;
                }
            }
            foreach ($authors as $author) {
                if ($this->samePerson($author, $user)) {
                    return true;
                }
            }
            return false;
        };
        $boardAuthor = false;
        foreach ($authors as $author) {
            foreach ($board as $member) {
                if ($this->samePerson($author, $member)) {
                    $boardAuthor = true;
                    break 2;
                }
            }
        }
        if ($boardAuthor) {
            $external = [];
            $reviews = Repo::reviewAssignment()->getCollector()
                ->filterBySubmissionIds([$submission->getId()])
                ->getMany();
            foreach ($reviews as $review) {
                if (!$review->getDateCompleted() || $review->getDeclined() || $review->getData('cancelled')) {
                    continue;
                }
                $reviewer = Repo::user()->get((int) $review->getReviewerId());
                if ($reviewer && !$isBoardOrAuthor($reviewer)) {
                    $external[$reviewer->getId()] = true;
                }
            }
            if (count($external) < 2) {
                $errors['csvetBoardReviews'] = str_replace('{$count}', (string) count($external), $s['boardReviewsRequired']);
            }
        }
        return Hook::CONTINUE;
    }
}
