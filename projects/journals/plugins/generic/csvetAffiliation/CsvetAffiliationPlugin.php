<?php

/**
 * @file plugins/generic/csvetAffiliation/CsvetAffiliationPlugin.php
 *
 * @class CsvetAffiliationPlugin
 *
 * @brief Filiação dos contribuidores digitada em qualquer idioma da revista.
 *
 *  No OJS 3.5, uma filiação digitada (sem escolher a instituição na lista ROR) só é aceita
 *  com o nome no idioma principal da submissão. O autor com a interface em português, numa
 *  submissão em inglês, preenche o nome só em português: a janela do contribuidor recusa
 *  gravar ("Forneça o nome da afiliação no idioma principal da submissão") e o último passo
 *  diz que a submissão está incompleta, por mais que ele corrija em português.
 *
 *  Aqui o nome escrito em outro idioma vale também para o idioma da submissão:
 *  - Affiliation::validate: tira o erro de idioma quando há nome em algum idioma;
 *  - Author::edit (antes de gravar) e Affiliation::add (contribuidor novo): copia o nome
 *    para o idioma da submissão;
 *  - Submission::validateSubmit: corrige as submissões já gravadas sem esse nome e tira o
 *    aviso de filiação incompleta do último passo.
 *
 *  É um plugin genérico sem lazy-load (sempre ativo depois de registrado pelo
 *  scripts/configure-journal.php), porque a API de contribuidores não carrega o tema.
 */

namespace APP\plugins\generic\csvetAffiliation;

use APP\core\Application;
use APP\facades\Repo;
use APP\submission\Submission;
use PKP\affiliation\Affiliation;
use PKP\author\Author;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;

class CsvetAffiliationPlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null)
    {
        if (!parent::register($category, $path, $mainContextId)) {
            return false;
        }
        if (Application::isInstalled() && !Application::isUnderMaintenance()) {
            Hook::add('Affiliation::validate', $this->acceptAnyLocale(...));
            Hook::add('Author::edit', $this->fillOnEdit(...));
            Hook::add('Affiliation::add', $this->fillOnAdd(...));
            Hook::add('Submission::validateSubmit', $this->fillOnSubmit(...));
        }
        return true;
    }

    public function getDisplayName()
    {
        return 'CSVET: filiação em qualquer idioma';
    }

    public function getDescription()
    {
        return 'Aceita a filiação dos contribuidores escrita em qualquer idioma da revista e a copia para o idioma da submissão, para a submissão não ficar incompleta.';
    }

    /** Primeiro nome preenchido da filiação, em qualquer idioma */
    private function anyName(Affiliation $affiliation): ?string
    {
        foreach ((array) $affiliation->getName(null) as $name) {
            if (is_string($name) && trim($name) !== '') {
                return $name;
            }
        }
        return null;
    }

    /** Copia o nome para $locale quando falta. Devolve true se mudou. */
    private function fill(Affiliation $affiliation, ?string $locale): bool
    {
        if (!$locale || $affiliation->getRor() || trim((string) $affiliation->getName($locale)) !== '') {
            return false;
        }
        $name = $this->anyName($affiliation);
        if ($name === null) {
            return false;
        }
        $affiliation->setName($name, $locale);
        return true;
    }

    private function submissionLocaleOfAuthor(Author $author): ?string
    {
        $publication = Repo::publication()->get((int) $author->getData('publicationId'));
        $submission = $publication ? Repo::submission()->get($publication->getData('submissionId')) : null;
        return $submission?->getData('locale');
    }

    public function acceptAnyLocale(string $hookName, array $args): bool
    {
        $errors = &$args[0];
        /** @var Submission $submission */
        [, , $props, $submission] = $args;
        $locale = $submission->getData('locale');
        if (!empty($props['ror']) || !isset($errors['name'][$locale])) {
            return Hook::CONTINUE;
        }
        $hasName = array_filter((array) ($props['name'] ?? []), fn ($n) => is_string($n) && trim($n) !== '');
        if ($hasName) {
            unset($errors['name'][$locale]);
            if (!$errors['name']) {
                unset($errors['name']);
            }
        }
        return Hook::CONTINUE;
    }

    public function fillOnEdit(string $hookName, array $args): bool
    {
        /** @var Author $newAuthor */
        $newAuthor = $args[0];
        try {
            $affiliations = $newAuthor->getAffiliations();
            if ($affiliations) {
                $locale = $this->submissionLocaleOfAuthor($newAuthor);
                foreach ($affiliations as $affiliation) {
                    $this->fill($affiliation, $locale);
                }
            }
        } catch (\Throwable $e) {
            error_log('csvetAffiliation edit: ' . $e->getMessage());
        }
        return Hook::CONTINUE;
    }

    public function fillOnAdd(string $hookName, array $args): bool
    {
        /** @var Affiliation $affiliation */
        $affiliation = $args[0];
        try {
            $author = Repo::author()->get((int) $affiliation->getData('authorId'));
            if ($author && $this->fill($affiliation, $this->submissionLocaleOfAuthor($author))) {
                Repo::affiliation()->edit($affiliation, []);
            }
        } catch (\Throwable $e) {
            error_log('csvetAffiliation add: ' . $e->getMessage());
        }
        return Hook::CONTINUE;
    }

    public function fillOnSubmit(string $hookName, array $args): bool
    {
        $errors = &$args[0];
        /** @var Submission $submission */
        $submission = $args[1];
        try {
            $locale = $submission->getData('locale');
            $publication = $submission->getCurrentPublication();
            $complete = true;
            foreach ($publication ? $publication->getData('authors') : [] as $author) {
                foreach ($author->getAffiliations() as $affiliation) {
                    if ($this->fill($affiliation, $locale)) {
                        Repo::affiliation()->edit($affiliation, []);
                    }
                    if (!$affiliation->getRor() && trim((string) $affiliation->getName($locale)) === '') {
                        $complete = false;
                    }
                }
            }
            // O aviso do OJS foi calculado antes da correção acima: sai se agora está tudo certo
            if ($complete && !empty($errors['contributors'])) {
                $errors['contributors'] = array_values(array_filter(
                    $errors['contributors'],
                    fn ($m) => !$this->isAffiliationMessage((string) $m)
                ));
                if (!$errors['contributors']) {
                    unset($errors['contributors']);
                }
            }
        } catch (\Throwable $e) {
            error_log('csvetAffiliation submit: ' . $e->getMessage());
        }
        return Hook::CONTINUE;
    }

    /** A mensagem do OJS sobre filiação sem nome no idioma da submissão, em qualquer idioma */
    private function isAffiliationMessage(string $message): bool
    {
        // Partes fixas do texto traduzido, antes e depois do nome do idioma
        $parts = explode("\0", __('submission.wizard.missingContributorAffiliationLanguage', ['language' => "\0"]));
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '' && !str_contains($message, $part)) {
                return false;
            }
        }
        return true;
    }
}
