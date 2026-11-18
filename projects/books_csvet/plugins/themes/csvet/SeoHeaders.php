<?php

/**
 * @file plugins/themes/csvet/SeoHeaders.php
 *
 * @class SeoHeaders
 *
 * @brief SEO do portal de livros da CSVET Editora (OMP).
 *
 *  O OMP e os plugins Google Scholar / Dublin Core já cuidam de hreflang e
 *  das meta tags citation_* e DC.*. Aqui entra o que falta:
 *  - link canonical (sem query string; livro e capítulo sempre na versão atual)
 *  - meta description em livros, capítulos e coleções (o OMP só gera na página inicial)
 *  - Open Graph e Twitter Card (compartilhamento em WhatsApp, LinkedIn, X...),
 *    com a capa do livro como imagem
 *  - JSON-LD: Book (com ISBN de cada formato), Chapter e BookSeries,
 *    com a CSVET Editora (mesmo @id de csvet.com.br) como publisher
 *  - sitemap sem as páginas bloqueadas no robots.txt (login, cadastro, busca)
 */

namespace APP\plugins\themes\csvet;

use APP\core\Application;
use APP\facades\Repo;
use APP\file\PublicFileManager;
use APP\template\TemplateManager;
use Illuminate\Support\Facades\DB;
use PKP\i18n\LocaleConversion;

class SeoHeaders
{
    /** Mesma entidade declarada em https://csvet.com.br (JSON-LD Organization) */
    public const PUBLISHER = [
        '@type' => 'Organization',
        '@id' => 'https://csvet.com.br/#organization',
        'name' => 'CSVET Editora',
        'url' => 'https://csvet.com.br/',
        'logo' => 'https://csvet.com.br/img/logo-csvet.png',
    ];

    private const DESCRIPTION_LENGTH = 160;

    /** Códigos ONIX (lista 5) de ISBN: 15 = ISBN-13, 02 = ISBN-10 */
    private const ISBN_CODES = ['15', '02'];

    /** Páginas que entram no sitemap mas são bloqueadas no robots.txt */
    private const SITEMAP_EXCLUDE = '#/(login|user/register|search)(/|$)#';

    /**
     * Hook TemplateManager::display
     */
    public function display(string $hookName, array $args): bool
    {
        // SEO nunca pode derrubar a página: em caso de erro, registra no log e segue sem as tags
        try {
            $this->addHeaders($args[0], $args[1] ?? null);
        } catch (\Throwable $e) {
            error_log('[csvet theme] SEO headers: ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
        }
        return false;
    }

    private function addHeaders(TemplateManager $templateMgr, $template): void
    {
        if (!is_string($template)) {
            return;
        }
        $isStatic = str_contains($template, 'staticPages') && str_ends_with($template, 'content.tpl');
        if (!$isStatic && !str_starts_with($template, 'frontend/pages/')) {
            return;
        }

        $request = Application::get()->getRequest();
        $context = $request->getContext();
        if (!$context) {
            return;
        }

        $locale = \PKP\facades\Locale::getLocale();
        $page = $isStatic ? 'staticPage' : basename($template, '.tpl');
        $siteName = $context->getLocalizedName();
        $home = ['name' => $siteName, 'url' => $this->contextUrl($context)];
        $catalog = ['name' => __('navigation.catalog'), 'url' => $request->getDispatcher()->url($request, Application::ROUTE_PAGE, null, 'catalog')];

        $title = $siteName;
        $description = $this->clean($context->getLocalizedData('searchDescription') ?: $context->getLocalizedData('description'));
        $image = $this->contextImage($context);
        $type = 'website';
        $graph = [];
        $crumbs = [];
        $url = null;
        $indexable = true;

        switch ($page) {
            case 'index':
                // Título da página inicial com o que o leitor procura, não só o nome da editora
                $title = $siteName . ' | ' . __('plugins.themes.csvet.seo.tagline');
                $templateMgr->assign('pageTitleTranslated', $title);
                $graph[] = $this->organization($context);
                $graph[] = $this->website($context, $description);
                break;

            case 'book':
                $submission = $templateMgr->getTemplateVars('publishedSubmission');
                $publication = $templateMgr->getTemplateVars('publication');
                if (!$submission || !$publication) {
                    break;
                }
                $indexable = $publication->getId() == $submission->getData('currentPublicationId');
                $pubLocale = $publication->getData('locale');
                $bookUrl = $this->bookUrl($request, $submission);
                $book = $this->book($context, $submission, $publication, $templateMgr, $bookUrl, $pubLocale);
                $image = $this->coverImage($context, $publication) ?: $image;
                $type = 'book';
                $crumbs = [$home, $catalog];
                if ($series = $this->seriesOf($publication, $context)) {
                    $crumbs[] = ['name' => trim($series->getLocalizedFullTitle()), 'url' => $request->getDispatcher()->url($request, Application::ROUTE_PAGE, null, 'catalog', 'series', [$series->getPath()])];
                }
                $crumbs[] = ['name' => $book['name'], 'url' => $bookUrl];

                $chapter = $templateMgr->getTemplateVars('isChapterRequest') ? $templateMgr->getTemplateVars('chapter') : null;
                if ($chapter) {
                    $title = strip_tags($chapter->getLocalizedFullTitle($locale)) . ' | ' . $book['name'];
                    $description = $this->clean($chapter->getLocalizedAbstract()) ?: ($book['abstract'] ?? $description);
                    $url = $bookUrl . '/chapter/' . $chapter->getSourceChapterId();
                    $graph[] = $this->chapter($chapter, $book, $url, $templateMgr->getTemplateVars('chapterAuthors'), $pubLocale);
                    $crumbs[] = ['name' => strip_tags($chapter->getLocalizedFullTitle($locale)), 'url' => $url];
                    $type = 'article';
                } else {
                    $title = $book['name'];
                    $description = $book['abstract'] ?? $description;
                    $url = $bookUrl;
                    $graph[] = $book;
                }
                $this->addBookOpenGraph($templateMgr, $book);
                // Obra sem licença aberta: reserva de mineração de texto e dados (TDMRep) e recusa de uso por IA
                if (!str_contains((string) ($book['license'] ?? ''), 'creativecommons.org')) {
                    $templateMgr->addHeader('csvetTdm', '<meta name="tdm-reservation" content="1" />' . "\n"
                        . '<meta name="robots" content="noai, noimageai" />');
                }
                break;

            case 'catalog':
                $title = __('navigation.catalog') . ' | ' . $siteName;
                $description = __('plugins.themes.csvet.seo.catalogDescription');
                $crumbs = [$home, $catalog];
                $graph[] = ['@type' => 'CollectionPage', 'name' => $title, 'url' => $catalog['url'], 'description' => $description, 'isPartOf' => ['@id' => $home['url'] . '#website']];
                break;

            case 'catalogSeries':
                $series = $templateMgr->getTemplateVars('series');
                if ($series) {
                    $seriesName = trim($series->getLocalizedFullTitle());
                    $title = $seriesName . ' | ' . $siteName;
                    $description = $this->clean($series->getLocalizedData('description')) ?: $description;
                    $seriesUrl = strtok($request->getCompleteUrl(), '?');
                    $graph[] = array_filter([
                        '@type' => 'BookSeries',
                        'name' => $seriesName,
                        'description' => $description,
                        'url' => $seriesUrl,
                        'issn' => $series->getData('onlineIssn') ?: ($series->getData('printIssn') ?: null),
                        'publisher' => self::PUBLISHER,
                    ]);
                    $crumbs = [$home, $catalog, ['name' => $seriesName, 'url' => $seriesUrl]];
                }
                break;

            case 'catalogCategory':
                $category = $templateMgr->getTemplateVars('category');
                if ($category) {
                    $title = $category->getLocalizedTitle() . ' | ' . $siteName;
                    $description = $this->clean($category->getLocalizedData('description'))
                        ?: __('plugins.themes.csvet.seo.categoryDescription', ['category' => $category->getLocalizedTitle()]);
                    $categoryUrl = strtok($request->getCompleteUrl(), '?');
                    $graph[] = ['@type' => 'CollectionPage', 'name' => $title, 'url' => $categoryUrl, 'description' => $description, 'about' => $category->getLocalizedTitle()];
                    $crumbs = [$home, $catalog, ['name' => $category->getLocalizedTitle(), 'url' => $categoryUrl]];
                }
                break;

            case 'staticPage':
                $pageTitle = (string) $templateMgr->getTemplateVars('title');
                $content = (string) $templateMgr->getTemplateVars('content');
                $title = $pageTitle . ' | ' . $siteName;
                // Descrição: o parágrafo de abertura (classe csvet-lead) ou o primeiro parágrafo
                if (preg_match('#<p class="csvet-lead">(.*?)</p>#s', $content, $m) || preg_match('#<p[^>]*>(.*?)</p>#s', $content, $m)) {
                    $description = $this->clean($m[1]) ?: $description;
                }
                $url = strtok($request->getCompleteUrl(), '?');
                $type = 'article';
                $graph[] = ['@type' => 'WebPage', 'name' => $pageTitle, 'url' => $url, 'description' => $description, 'inLanguage' => LocaleConversion::toBcp47($locale), 'isPartOf' => ['@id' => $home['url'] . '#website'], 'publisher' => self::PUBLISHER];
                if ($faq = $this->faq($content)) {
                    $graph[] = $faq;
                }
                $crumbs = [$home, ['name' => $pageTitle, 'url' => $url]];
                break;
        }

        $url ??= $this->canonicalUrl($request, $page);
        if ($crumbs) {
            $graph[] = $this->breadcrumbs($crumbs);
        }

        if ($description) {
            $templateMgr->addHeader('searchDescription', '<meta name="description" content="' . $this->attr($this->truncate($description)) . '" />');
        }
        if ($url && $indexable) {
            // Mesma chave usada pelo OMP nas versões antigas do livro: substitui em vez de duplicar
            $templateMgr->addHeader('canonical', '<link rel="canonical" href="' . $this->attr($url) . '" />');
        }
        // Prévia grande de imagem e trecho completo no Google; versões antigas ficam com o noindex do OMP
        if ($indexable && !in_array($page, ['search', 'searchResults', 'userLogin', 'userRegister', 'userLostPassword', 'error'], true)) {
            $templateMgr->addHeader('csvetRobots', '<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1" />');
        }
        $templateMgr->addHeader('csvetThemeColor', '<meta name="theme-color" content="#000080" />');

        $og = [
            'og:site_name' => $siteName,
            'og:type' => $type,
            'og:title' => $title,
            'og:description' => $description ? $this->truncate($description, 300) : null,
            'og:url' => $url,
            'og:locale' => $locale,
            'og:image' => $image,
            'og:image:alt' => $image ? $title : null,
        ];
        $tags = '';
        foreach ($og as $property => $content) {
            if ($content) {
                $tags .= '<meta property="' . $property . '" content="' . $this->attr($content) . '" />' . "\n";
            }
        }
        $tags .= '<meta name="twitter:card" content="' . ($image ? 'summary_large_image' : 'summary') . '" />' . "\n";
        $tags .= '<meta name="twitter:title" content="' . $this->attr($title) . '" />';
        if ($description) {
            $tags .= "\n" . '<meta name="twitter:description" content="' . $this->attr($this->truncate($description, 200)) . '" />';
        }
        $templateMgr->addHeader('csvetOpenGraph', $tags);

        if ($graph) {
            $data = ['@context' => 'https://schema.org', '@graph' => array_map(fn ($node) => array_diff_key($node, ['@context' => 1]), $graph)];
            $templateMgr->addHeader(
                'csvetJsonLd',
                '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>'
            );
        }
    }

    /** Organização: a mesma entidade de csvet.com.br, ligada aos outros portais da marca */
    private function organization($context): array
    {
        return array_filter(self::PUBLISHER + [
            'alternateName' => $context->getLocalizedName(),
            'sameAs' => ['https://csvet.com.br/', 'https://journals.csvet.com.br/', $this->contextUrl($context)],
            'email' => $context->getData('supportEmail') ?: null,
            'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Americana', 'addressRegion' => 'SP', 'addressCountry' => 'BR'],
        ]);
    }

    /** Trilha de navegação (aparece no lugar da URL nos resultados do Google) */
    private function breadcrumbs(array $items): array
    {
        $list = [];
        foreach (array_values($items) as $i => $item) {
            $list[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $item['name'], 'item' => $item['url']];
        }
        return ['@type' => 'BreadcrumbList', 'itemListElement' => $list];
    }

    /** Perguntas frequentes escritas como <details><summary>pergunta</summary>resposta</details> */
    private function faq(string $html): ?array
    {
        if (!preg_match_all('#<details[^>]*>\s*<summary[^>]*>(.*?)</summary>(.*?)</details>#s', $html, $m, PREG_SET_ORDER)) {
            return null;
        }
        $questions = [];
        foreach ($m as [, $question, $answer]) {
            $questions[] = ['@type' => 'Question', 'name' => $this->clean($question), 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $this->clean($answer)]];
        }
        return ['@type' => 'FAQPage', 'mainEntity' => $questions];
    }

    private function seriesOf($publication, $context)
    {
        $seriesId = $publication->getData('seriesId');
        return $seriesId ? Repo::section()->get($seriesId, $context->getId()) : null;
    }

    /**
     * Hook SitemapHandler::createPressSitemap
     */
    public function sitemap(string $hookName, array $args): bool
    {
        /** @var \DOMDocument $doc */
        $doc = $args[0];
        $remove = [];
        foreach ($doc->getElementsByTagName('url') as $node) {
            $loc = $node->getElementsByTagName('loc')->item(0);
            if ($loc && preg_match(self::SITEMAP_EXCLUDE, parse_url($loc->textContent, PHP_URL_PATH) ?? '')) {
                $remove[] = $node;
            }
        }
        foreach ($remove as $node) {
            $node->parentNode->removeChild($node);
        }

        // Páginas de conteúdo (plugin Static Pages: ética, monografias, coletâneas) entram no sitemap
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $urlset = $doc->getElementsByTagName('urlset')->item(0);
        if ($context && $urlset) {
            $paths = DB::table('static_pages')->where('context_id', $context->getId())->pluck('path');
            foreach ($paths as $path) {
                $url = $doc->createElement('url');
                $url->appendChild($doc->createElement('loc', htmlspecialchars($request->getDispatcher()->url($request, Application::ROUTE_PAGE, null, $path))));
                $urlset->appendChild($url);
            }
        }
        return false;
    }

    private function website($context, ?string $description): array
    {
        $request = Application::get()->getRequest();
        $search = $request->getDispatcher()->url($request, Application::ROUTE_PAGE, $context->getPath(), 'search', 'search', urlLocaleForPage: '');
        $data = [
            '@type' => 'WebSite',
            '@id' => $this->contextUrl($context) . '#website',
            'name' => $context->getName($context->getPrimaryLocale()),
            'alternateName' => $context->getData('acronym', $context->getPrimaryLocale()) ?: null,
            'url' => $this->contextUrl($context),
            'description' => $description ?: null,
            'inLanguage' => array_map(fn ($l) => LocaleConversion::toBcp47($l), $context->getSupportedSubmissionLocales() ?: [$context->getPrimaryLocale()]),
            'publisher' => ['@id' => self::PUBLISHER['@id']],
            // Caixa de busca do site nos resultados do Google
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => $search . '?query={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ],
        ];
        return array_filter($data, fn ($v) => $v !== null && $v !== '');
    }

    private function book($context, $submission, $publication, TemplateManager $templateMgr, string $url, ?string $pubLocale): array
    {
        $isEditedVolume = $submission->getData('workType') == $submission::WORK_TYPE_EDITED_VOLUME;
        $authors = $editors = $contributors = [];
        $creditGroups = AcademicCredits::groupIds($context->getId());
        foreach ($publication->getData('authors') ?? [] as $author) {
            $person = $this->person($author, $pubLocale);
            // Orientação e banca: contribuidores com papel, nunca autores
            if ($role = $creditGroups[(int) $author->getData('userGroupId')] ?? null) {
                $contributors[] = ['@type' => 'Role', 'roleName' => $role, 'contributor' => $person];
                continue;
            }
            if ($isEditedVolume && $author->getIsVolumeEditor()) {
                $editors[] = $person;
            } elseif (!$isEditedVolume) {
                $authors[] = $person;
            }
        }

        // ISBN e tipo de cada formato disponível (PDF, EPUB, impresso...)
        $isbn = null;
        $workExample = [];
        foreach ($templateMgr->getTemplateVars('publicationFormats') ?? [] as $format) {
            $formatIsbn = null;
            foreach ($format->getIdentificationCodes()->toArray() as $code) {
                if (in_array($code->getCode(), self::ISBN_CODES, true) && $code->getValue()) {
                    $formatIsbn = preg_replace('/[^0-9X]/i', '', $code->getValue());
                    break;
                }
            }
            $isbn ??= $formatIsbn;
            $workExample[] = array_filter([
                '@type' => 'Book',
                'name' => $format->getLocalizedName(),
                'bookFormat' => $format->getPhysicalFormat() ? 'https://schema.org/Hardcover' : 'https://schema.org/EBook',
                'isbn' => $formatIsbn,
                'url' => $format->getData('urlRemote') ?: null,
            ]);
        }

        $keywords = [];
        foreach ((array) $publication->getData('keywords', $pubLocale) as $keyword) {
            $keywords[] = is_array($keyword) ? ($keyword['name'] ?? '') : (string) $keyword;
        }

        $series = null;
        if ($seriesId = $publication->getData('seriesId')) {
            $s = Repo::section()->get($seriesId, $context->getId());
            if ($s) {
                $series = array_filter([
                    '@type' => 'BookSeries',
                    'name' => trim($s->getLocalizedFullTitle()),
                    'issn' => $s->getData('onlineIssn') ?: ($s->getData('printIssn') ?: null),
                ]);
            }
        }

        $doi = $publication->getDoi();
        $datePublished = $publication->getData('datePublished');
        $abstract = $this->clean($publication->getLocalizedData('abstract', $pubLocale));

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Book',
            '@id' => $url . '#book',
            'name' => strip_tags($publication->getLocalizedFullTitle($pubLocale)),
            'author' => $authors ?: null,
            'editor' => $editors ?: null,
            'contributor' => $contributors ?: null,
            'isbn' => $isbn,
            'bookEdition' => $publication->getData('version') > 1 ? (string) $publication->getData('version') : null,
            'datePublished' => $datePublished ? date('Y-m-d', strtotime($datePublished)) : null,
            'abstract' => $abstract,
            'keywords' => array_values(array_filter($keywords)) ?: null,
            'inLanguage' => $pubLocale ? LocaleConversion::toBcp47($pubLocale) : null,
            'image' => $this->coverImage($context, $publication),
            'url' => $url,
            'sameAs' => $doi ? 'https://doi.org/' . $doi : null,
            'identifier' => $doi ? ['@type' => 'PropertyValue', 'propertyID' => 'DOI', 'value' => $doi] : null,
            'license' => $publication->getData('licenseUrl') ?: ($context->getData('licenseUrl') ?: null),
            'isAccessibleForFree' => true,
            'isPartOf' => $series,
            'workExample' => $workExample ?: null,
            'about' => array_values(array_map(fn ($c) => $c->getLocalizedTitle(), $templateMgr->getTemplateVars('categories') ?? [])) ?: null,
            // Acesso aberto: versão digital gratuita
            'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'BRL', 'availability' => 'https://schema.org/InStock', 'url' => $url],
            'publisher' => self::PUBLISHER,
        ];
        return array_filter($data, fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    private function chapter($chapter, array $book, string $url, $chapterAuthors, ?string $pubLocale): array
    {
        $authors = [];
        foreach ($chapterAuthors ?? [] as $author) {
            $authors[] = $this->person($author, $pubLocale);
        }
        [$pageStart, $pageEnd] = array_pad(explode('-', (string) $chapter->getPages(), 2), 2, null);
        $doi = $chapter->getDoi();

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Chapter',
            'name' => strip_tags($chapter->getLocalizedFullTitle($pubLocale)),
            'author' => $authors ?: null,
            'abstract' => $this->clean($chapter->getLocalizedAbstract()),
            'datePublished' => $chapter->getDatePublished() ? date('Y-m-d', strtotime($chapter->getDatePublished())) : ($book['datePublished'] ?? null),
            'pageStart' => $pageStart ? trim($pageStart) : null,
            'pageEnd' => $pageEnd ? trim($pageEnd) : null,
            'url' => $url,
            'sameAs' => $doi ? 'https://doi.org/' . $doi : null,
            'license' => $chapter->getLicenseUrl() ?: ($book['license'] ?? null),
            'isAccessibleForFree' => true,
            'isPartOf' => array_filter([
                '@type' => 'Book',
                '@id' => $book['@id'],
                'name' => $book['name'],
                'isbn' => $book['isbn'] ?? null,
                'url' => $book['url'],
            ]),
            'publisher' => self::PUBLISHER,
        ];
        return array_filter($data, fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    private function person($author, ?string $locale): array
    {
        $person = ['@type' => 'Person', 'name' => $author->getFullName(false, false, $locale)];
        $affiliations = [];
        foreach ((array) (method_exists($author, 'getAffiliations') ? $author->getAffiliations() : []) as $affiliation) {
            if ($name = $affiliation->getLocalizedName($locale)) {
                $affiliations[] = ['@type' => 'Organization', 'name' => $name];
            }
        }
        if ($affiliations) {
            $person['affiliation'] = $affiliations;
        }
        if ($orcid = $author->getData('orcid')) {
            $person['sameAs'] = $orcid;
        }
        return $person;
    }

    private function addBookOpenGraph(TemplateManager $templateMgr, array $book): void
    {
        $tags = '';
        foreach (array_merge($book['author'] ?? [], $book['editor'] ?? []) as $person) {
            $tags .= '<meta property="book:author" content="' . $this->attr($person['name']) . '" />' . "\n";
        }
        if (!empty($book['isbn'])) {
            $tags .= '<meta property="book:isbn" content="' . $this->attr($book['isbn']) . '" />' . "\n";
        }
        if (!empty($book['datePublished'])) {
            $tags .= '<meta property="book:release_date" content="' . $this->attr($book['datePublished']) . '" />' . "\n";
        }
        foreach ($book['keywords'] ?? [] as $keyword) {
            $tags .= '<meta property="book:tag" content="' . $this->attr($keyword) . '" />' . "\n";
        }
        if ($tags) {
            $templateMgr->addHeader('csvetBookOg', $tags);
        }
    }

    /**
     * URL canônica das demais páginas: sem query string.
     */
    private function canonicalUrl($request, string $page): ?string
    {
        if (in_array($page, ['search', 'searchResults', 'userLogin', 'userRegister', 'userLostPassword', 'error'], true)) {
            return null;
        }
        return strtok($request->getCompleteUrl(), '?') ?: null;
    }

    /** URL do livro na versão atual (sem /version/N), com o caminho amigável quando houver */
    private function bookUrl($request, $submission): string
    {
        return $request->getDispatcher()->url($request, Application::ROUTE_PAGE, null, 'catalog', 'book', [$submission->getBestId()]);
    }

    private function contextUrl($context): string
    {
        $request = Application::get()->getRequest();
        return $request->getDispatcher()->url($request, Application::ROUTE_PAGE, $context->getPath(), urlLocaleForPage: '');
    }

    private function contextImage($context): ?string
    {
        // Imagem de compartilhamento 1200x630 versionada no tema: images/og/<path da editora>.png
        $path = preg_replace('/[^a-z0-9_-]/i', '', (string) $context->getPath());
        if ($path && is_file(__DIR__ . "/images/og/{$path}.png")) {
            return Application::get()->getRequest()->getBaseUrl() . "/plugins/themes/csvet/images/og/{$path}.png";
        }

        foreach (['homepageImage', 'pressThumbnail', 'pageHeaderLogoImage'] as $setting) {
            $file = $context->getLocalizedData($setting);
            if (!empty($file['uploadName'])) {
                return $this->publicFileUrl($context, $file['uploadName']);
            }
        }
        return null;
    }

    private function coverImage($context, $publication): ?string
    {
        $cover = $publication->getLocalizedData('coverImage');
        return !empty($cover['uploadName']) ? $this->publicFileUrl($context, $cover['uploadName']) : null;
    }

    private function publicFileUrl($context, string $fileName): string
    {
        $request = Application::get()->getRequest();
        $publicFileManager = new PublicFileManager();
        return $request->getBaseUrl() . '/' . $publicFileManager->getContextFilesPath($context->getId()) . '/' . rawurlencode($fileName);
    }

    private function clean(?string $html): ?string
    {
        if (!$html) {
            return null;
        }
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        return $text !== '' ? $text : null;
    }

    private function truncate(string $text, int $length = self::DESCRIPTION_LENGTH): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        $cut = mb_substr($text, 0, $length - 1);
        $space = mb_strrpos($cut, ' ');
        return rtrim($space > $length * 0.6 ? mb_substr($cut, 0, $space) : $cut, " ,.;:") . '…';
    }

    private function attr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
