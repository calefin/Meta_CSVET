<?php

/**
 * @file plugins/themes/csvet/AcademicCredits.php
 *
 * @class AcademicCredits
 *
 * @brief Créditos acadêmicos de monografias, dissertações e teses publicadas como livro.
 *
 *  Orientador(a), coorientador(a) e membros da banca são cadastrados como contribuidores
 *  com papéis próprios (criados por scripts/configure-press.php). Eles NÃO são autores:
 *  - na página do livro aparecem em "Orientação", "Coorientação" e "Banca examinadora",
 *    separados dos autores;
 *  - não entram nas listas do catálogo (includeInBrowse desligado ao salvar);
 *  - são retirados das meta tags de autoria (Google Acadêmico citation_author, Dublin Core
 *    DC.Creator) e do JSON-LD author, onde entram como contributor com o papel;
 *  - o plugin de citação já os ignora (grupos de autoria definidos em press.json).
 *  Se o orientador também escreveu a versão em livro, ele é cadastrado como Autor, não aqui.
 */

namespace APP\plugins\themes\csvet;

use APP\core\Application;
use APP\template\TemplateManager;
use Illuminate\Support\Facades\DB;

class AcademicCredits
{
    /** Nome em inglês do papel => chave do rótulo exibido (locale do tema), na ordem de exibição */
    public const GROUPS = [
        'Supervisor' => 'plugins.themes.csvet.credits.supervisor',
        'Co-supervisor' => 'plugins.themes.csvet.credits.cosupervisor',
        'Examining committee member' => 'plugins.themes.csvet.credits.committee',
    ];

    private static array $cache = [];

    /** @return array<int, string> user_group_id => nome em inglês */
    public static function groupIds(int $contextId): array
    {
        return self::$cache[$contextId] ??= DB::table('user_groups as g')
            ->join('user_group_settings as s', 's.user_group_id', '=', 'g.user_group_id')
            ->where('g.context_id', $contextId)
            ->where('s.setting_name', 'name')
            ->where('s.locale', 'en')
            ->whereIn('s.setting_value', array_keys(self::GROUPS))
            ->pluck('s.setting_value', 'g.user_group_id')
            ->map(fn ($v) => (string) $v)
            ->all();
    }

    public static function isCredit($author, int $contextId): bool
    {
        return isset(self::groupIds($contextId)[(int) $author->getData('userGroupId')]);
    }

    /**
     * Hooks Author::add::before e Author::edit: créditos acadêmicos não entram nas listas do catálogo.
     */
    public function beforeSave(string $hookName, array $args): bool
    {
        $author = $args[0];
        $context = Application::get()->getRequest()->getContext();
        $contextId = $context?->getId() ?? (int) DB::table('publications as p')->join('submissions as s', 's.submission_id', '=', 'p.submission_id')
            ->where('p.publication_id', $author->getData('publicationId'))->value('s.context_id');
        if ($contextId && self::isCredit($author, $contextId)) {
            $author->setData('includeInBrowse', false);
        }
        return false;
    }

    /**
     * Hook TemplateManager::display na página do livro: separa os créditos para o template e
     * retira esses nomes das meta tags de autoria já adicionadas pelos plugins.
     */
    public function display(string $hookName, array $args): bool
    {
        try {
            $this->prepare($args[0], $args[1] ?? null);
        } catch (\Throwable $e) {
            error_log('[csvet theme] créditos acadêmicos: ' . $e->getMessage());
        }
        return false;
    }

    /**
     * Lista de autores para o catálogo (resumos de livros), sem orientação e banca.
     * Usado no template frontend/objects/monograph_summary.tpl do tema: {$publication|csvet_authors}
     */
    public static function authorString($publication): string
    {
        $context = Application::get()->getRequest()->getContext();
        $groups = $context ? self::groupIds($context->getId()) : [];
        $names = [];
        $editors = 0;
        foreach ($publication->getData('authors') ?? [] as $author) {
            if (isset($groups[(int) $author->getData('userGroupId')]) || !$author->getData('includeInBrowse')) {
                continue;
            }
            $names[] = $author->getFullName();
            $editors += $author->getIsVolumeEditor() ? 1 : 0;
        }
        $string = implode(__('common.commaListSeparator'), $names);
        if ($names && $editors === count($names)) {
            $string .= ' (' . __('plugins.themes.csvet.credits.editorsAbbrev') . ')';
        }
        return $string;
    }

    private function prepare(TemplateManager $templateMgr, $template): void
    {
        $smarty = $templateMgr->registered_plugins['modifier'] ?? [];
        if (!isset($smarty['csvet_authors'])) {
            $templateMgr->registerPlugin('modifier', 'csvet_authors', [self::class, 'authorString']);
        }
        if ($template !== 'frontend/pages/book.tpl' || $templateMgr->getTemplateVars('isChapterRequest')) {
            return;
        }
        $publication = $templateMgr->getTemplateVars('publication');
        $context = Application::get()->getRequest()->getContext();
        if (!$publication || !$context) {
            return;
        }
        $groups = self::groupIds($context->getId());
        if (!$groups) {
            return;
        }

        $credits = [];
        $creditIds = [];
        foreach ($publication->getData('authors') ?? [] as $key => $author) {
            $groupName = $groups[(int) $author->getData('userGroupId')] ?? null;
            if (!$groupName) {
                continue;
            }
            $creditIds[] = $author->getId();
            $name = $author->getFullName();
            if ($affiliation = $author->getLocalizedAffiliationNamesAsString(null, ', ')) {
                $name .= " ({$affiliation})";
            }
            $credits[$groupName][] = $name;

            // Mesmas chaves usadas pelos plugins Google Scholar e Dublin Core: sobrescrever = remover
            $templateMgr->addHeader('googleScholarAuthor' . $key, '');
            $i = $key + 1;
            foreach ($author->getAffiliations() as $affiliation) {
                $templateMgr->addHeader('googleScholarAuthor' . $i++ . 'Affiliation' . $affiliation->getId(), '');
            }
            $templateMgr->addHeader('dublinCoreAuthor' . $key, '');
        }

        // Direitos autorais: o OMP monta o titular padrão com todos os contribuidores. Os direitos
        // são dos autores; corrige a exibição quando o titular gravado inclui orientação ou banca.
        $holder = $publication->getData('copyrightHolder');
        if (is_array($holder) && $creditIds) {
            $creditNames = [];
            foreach ($publication->getData('authors') as $author) {
                if (in_array($author->getId(), $creditIds, true)) {
                    $creditNames[] = $author->getFullName();
                }
            }
            $authorsOnly = self::authorString($publication);
            foreach ($holder as $locale => $value) {
                foreach ($creditNames as $name) {
                    if ($name !== '' && str_contains((string) $value, $name)) {
                        $holder[$locale] = $authorsOnly;
                        break;
                    }
                }
            }
            $publication->setData('copyrightHolder', $holder);
            $year = $publication->getData('copyrightYear');
            if ($year && ($localized = $publication->getLocalizedData('copyrightHolder'))) {
                $templateMgr->addHeader('dublinCoreCopyright', '<meta name="DC.Rights" content="'
                    . htmlspecialchars(__('submission.copyrightStatement', ['copyrightHolder' => $localized, 'copyrightYear' => $year])) . '"/>');
            }
        }

        $labelled = [];
        foreach (self::GROUPS as $groupName => $labelKey) {
            if (!empty($credits[$groupName])) {
                $labelled[] = ['label' => __($labelKey), 'names' => implode('; ', $credits[$groupName])];
            }
        }
        $templateMgr->assign([
            'csvetCredits' => $labelled,
            'csvetCreditAuthorIds' => $creditIds,
        ]);
    }
}
