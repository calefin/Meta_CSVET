<?php

/**
 * @file plugins/themes/csvet/SeoHeaders.php
 *
 * @class SeoHeaders
 *
 * @brief SEO do portal de periódicos da CSVET Editora.
 *
 *  O OJS e os plugins Google Scholar / Dublin Core já cuidam de hreflang,
 *  meta tags citation_* e DC.*. Aqui entra o que falta:
 *  - link canonical (sem query string; artigo sempre aponta para a versão atual)
 *  - meta description em artigos e edições (o OJS só gera na página inicial)
 *  - Open Graph e Twitter Card (compartilhamento em WhatsApp, LinkedIn, X...)
 *  - JSON-LD: Periodical na página inicial e ScholarlyArticle nos artigos,
 *    com a CSVET Editora (mesmo @id de csvet.com.br) como publisher
 *  - sitemap sem as páginas bloqueadas no robots.txt (login, cadastro, busca)
 */

namespace APP\plugins\themes\csvet;

use APP\core\Application;
use APP\file\PublicFileManager;
use APP\template\TemplateManager;
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

        if (!is_string($template) || !str_starts_with($template, 'frontend/pages/')) {
            return;
        }

        $request = Application::get()->getRequest();
        $context = $request->getContext();
        if (!$context) {
            return;
        }

        $locale = \PKP\facades\Locale::getLocale();
        $page = basename($template, '.tpl');
        $siteName = $context->getLocalizedName();

        $title = $siteName;
        $description = $this->clean($context->getLocalizedData('searchDescription') ?: $context->getLocalizedData('description'));
        $image = $this->contextImage($context);
        $type = 'website';
        $jsonLd = null;

        switch ($page) {
            case 'indexJournal':
                $jsonLd = $this->periodical($context, $description);
                break;

            case 'article':
                $publication = $templateMgr->getTemplateVars('publication');
                $article = $templateMgr->getTemplateVars('article');
                $issue = $templateMgr->getTemplateVars('issue');
                if (!$publication || !$article) {
                    break;
                }
                $pubLocale = $publication->getData('locale');
                $title = strip_tags($publication->getLocalizedFullTitle($locale)) ?: $title;
                $abstract = $this->clean($publication->getLocalizedData('abstract', $locale));
                $description = $abstract ?: $description;
                $image = $this->coverImage($context, $publication) ?: $image;
                $type = 'article';
                $jsonLd = $this->scholarlyArticle($request, $context, $article, $publication, $issue, $title, $abstract, $pubLocale);
                $this->addArticleOpenGraph($templateMgr, $publication, $pubLocale);
                break;

            // Página personalizada (item de menu com conteúdo próprio, ex.: chamada de artigos)
            case 'navigationMenuItemViewContent':
                // data-title no primeiro elemento: título para o Google e o <h1>, mais descritivo
                // que o título curto do item de menu (que continua no menu)
                if (preg_match('/^\s*<[a-z]+[^>]*\sdata-title="([^"]+)"/i', (string) $templateMgr->getTemplateVars('content'), $m)) {
                    $templateMgr->assign('title', html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                }
                $title = trim(strip_tags((string) $templateMgr->getTemplateVars('title'))) . ' | ' . $siteName;
                [$description, $image] = $this->customPageSummary($context, (string) $templateMgr->getTemplateVars('content'), $locale);
                $description ??= $this->clean($context->getLocalizedData('searchDescription'));
                $image ??= $this->contextImage($context);
                break;

            case 'issue':
                $issue = $templateMgr->getTemplateVars('issue');
                if ($issue) {
                    $title = $issue->getIssueIdentification() . ' | ' . $siteName;
                    $description = $this->clean($issue->getLocalizedDescription()) ?: $description;
                }
                break;
        }

        $url = $this->canonicalUrl($request, $page, $templateMgr);

        if ($url) {
            $this->addHreflang($templateMgr, $context, $url);
        }

        if ($page === 'navigationMenuItemViewContent' && $url) {
            $jsonLd = $this->webPage($context, $title, $description, $url, $image, $locale);
        }

        if ($description) {
            $templateMgr->addHeader('searchDescription', '<meta name="description" content="' . $this->attr($this->truncate($description)) . '" />');
        }
        if ($url) {
            $templateMgr->addHeader('csvetCanonical', '<link rel="canonical" href="' . $this->attr($url) . '" />');
        }

        $og = [
            'og:site_name' => $siteName,
            'og:type' => $type,
            'og:title' => $title,
            'og:description' => $description ? $this->truncate($description, 300) : null,
            'og:url' => $url,
            'og:locale' => $locale,
            'og:image' => $image,
        ];
        $tags = '';
        foreach ($og as $property => $content) {
            if ($content) {
                $tags .= '<meta property="' . $property . '" content="' . $this->attr($content) . '" />' . "\n";
            }
        }
        $tags .= '<meta name="twitter:card" content="' . ($image ? 'summary_large_image' : 'summary') . '" />';
        $templateMgr->addHeader('csvetOpenGraph', $tags);

        if ($jsonLd) {
            $templateMgr->addHeader(
                'csvetJsonLd',
                '<script type="application/ld+json">' . json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>'
            );
        }

    }

    /**
     * Hook SitemapHandler::createJournalSitemap
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

        // O OJS lista só o idioma principal: acrescenta as versões nos outros idiomas,
        // cada URL com as alternativas hreflang (o Google descobre e indexa as páginas em português)
        try {
            $this->addSitemapLanguages($doc);
        } catch (\Throwable $e) {
            error_log('[csvet theme] sitemap: ' . $e->getMessage());
        }
        return false;
    }

    private function addSitemapLanguages(\DOMDocument $doc): void
    {
        $context = Application::get()->getRequest()->getContext();
        $locales = $context?->getSupportedLocales() ?? [];
        if (count($locales) < 2) {
            return;
        }
        $xhtml = 'http://www.w3.org/1999/xhtml';
        $doc->documentElement->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xhtml', $xhtml);

        $urls = [];
        foreach ($doc->getElementsByTagName('url') as $node) {
            $urls[] = $node;
        }
        foreach ($urls as $node) {
            $loc = $node->getElementsByTagName('loc')->item(0);
            $variants = $loc ? $this->localeVariants($context, $loc->textContent) : [];
            if (!$variants) {
                continue;
            }
            $extras = [];
            foreach ($node->childNodes as $child) {
                if ($child instanceof \DOMElement && $child->localName !== 'loc') {
                    $extras[] = $child;
                }
            }
            $first = true;
            foreach ($variants as $locale => $href) {
                $target = $first ? $node : $doc->createElement('url');
                if (!$first) {
                    $target->appendChild($doc->createElement('loc', htmlspecialchars($href, ENT_COMPAT, 'UTF-8')));
                    foreach ($extras as $extra) {
                        $target->appendChild($extra->cloneNode(true));
                    }
                    $node->parentNode->appendChild($target);
                } else {
                    $loc->textContent = $href;
                }
                foreach ($variants as $altLocale => $altHref) {
                    $link = $doc->createElementNS($xhtml, 'xhtml:link');
                    $link->setAttribute('rel', 'alternate');
                    $link->setAttribute('hreflang', LocaleConversion::toBcp47($altLocale));
                    $link->setAttribute('href', $altHref);
                    $target->appendChild($link);
                }
                $first = false;
            }
        }
    }

    /**
     * Mesma URL em cada idioma da revista, trocando o segmento de idioma depois do caminho
     * da revista (/nvas/en/... → /nvas/pt_BR/...). Vazio se a URL não tiver idioma.
     *
     * @return array<string, string> idioma => URL
     */
    private function localeVariants($context, string $url): array
    {
        $locales = $context->getSupportedLocales() ?: [];
        $journal = preg_quote('/' . $context->getPath() . '/', '#');
        $pattern = '#' . $journal . '(' . implode('|', array_map(fn ($l) => preg_quote($l, '#'), $locales)) . ')(?=/|$)#';
        if (!preg_match($pattern, $url)) {
            return [];
        }
        $out = [];
        foreach ($locales as $locale) {
            $out[$locale] = preg_replace($pattern, '/' . $context->getPath() . '/' . $locale, $url, 1);
        }
        return $out;
    }

    /**
     * hreflang a partir da URL canônica: o OJS gera as alternativas com "/index" no fim
     * (.../about/index), que não coincidem com a canônica e são ignoradas pelo Google.
     * x-default aponta para o idioma principal da revista.
     */
    private function addHreflang(TemplateManager $templateMgr, $context, string $url): void
    {
        $variants = $this->localeVariants($context, $url);
        if (count($variants) < 2) {
            return;
        }
        foreach ($variants as $locale => $href) {
            $templateMgr->addHeader("language-{$locale}", "<link rel='alternate' hreflang='" . LocaleConversion::toBcp47($locale) . "' href='" . $this->attr($href) . "' />");
        }
        $primary = $variants[$context->getPrimaryLocale()] ?? reset($variants);
        $templateMgr->addHeader('language-xdefault', "<link rel='alternate' hreflang='x-default' href='" . $this->attr($primary) . "' />");
    }

    private function webPage($context, string $title, ?string $description, string $url, ?string $image, string $locale): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $title,
            'description' => $description ?: null,
            'url' => $url,
            'inLanguage' => LocaleConversion::toBcp47($locale),
            'primaryImageOfPage' => $image ?: null,
            'isPartOf' => [
                '@type' => 'Periodical',
                'name' => $context->getName($context->getPrimaryLocale()),
                'url' => $this->contextUrl($context),
            ],
            'publisher' => self::PUBLISHER,
        ];
        return array_filter($data, fn ($v) => $v !== null && $v !== '');
    }

    private function periodical($context, ?string $description): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Periodical',
            'name' => $context->getName($context->getPrimaryLocale()),
            'alternateName' => $context->getData('acronym', $context->getPrimaryLocale()) ?: null,
            'url' => $this->contextUrl($context),
            'description' => $description ?: null,
            'inLanguage' => array_map(fn ($l) => LocaleConversion::toBcp47($l), $context->getSupportedSubmissionLocales() ?: [$context->getPrimaryLocale()]),
            'issn' => $context->getData('onlineIssn') ?: ($context->getData('printIssn') ?: null),
            'isAccessibleForFree' => true,
            'publisher' => self::PUBLISHER,
        ];
        return array_filter($data, fn ($v) => $v !== null && $v !== '');
    }

    private function scholarlyArticle($request, $context, $article, $publication, $issue, string $title, ?string $abstract, ?string $pubLocale): array
    {
        $authors = [];
        foreach ($publication->getData('authors') ?? [] as $author) {
            $person = ['@type' => 'Person', 'name' => $author->getFullName(false, false, $pubLocale)];
            $affiliations = [];
            foreach ((array) (method_exists($author, 'getAffiliations') ? $author->getAffiliations() : []) as $affiliation) {
                if ($name = $affiliation->getLocalizedName($pubLocale)) {
                    $affiliations[] = ['@type' => 'Organization', 'name' => $name];
                }
            }
            if ($affiliations) {
                $person['affiliation'] = $affiliations;
            }
            if ($orcid = $author->getData('orcid')) {
                $person['sameAs'] = $orcid;
            }
            $authors[] = $person;
        }

        $keywords = [];
        foreach ((array) $publication->getData('keywords', $pubLocale) as $keyword) {
            $keywords[] = is_array($keyword) ? ($keyword['name'] ?? '') : (string) $keyword;
        }

        $doi = method_exists($publication, 'getDoi') ? $publication->getDoi() : null;
        $datePublished = $publication->getData('datePublished');

        $periodical = array_filter([
            '@type' => 'Periodical',
            'name' => $context->getName($context->getPrimaryLocale()),
            'issn' => $context->getData('onlineIssn') ?: ($context->getData('printIssn') ?: null),
            'url' => $this->contextUrl($context),
        ]);

        $isPartOf = $periodical;
        if ($issue && ($issue->getVolume() || $issue->getNumber())) {
            $isPartOf = array_filter([
                '@type' => 'PublicationIssue',
                'issueNumber' => $issue->getNumber() ?: null,
                'datePublished' => $issue->getDatePublished() ? date('Y-m-d', strtotime($issue->getDatePublished())) : null,
                'isPartOf' => array_filter([
                    '@type' => ['PublicationVolume', 'Periodical'],
                    'volumeNumber' => $issue->getVolume() ?: null,
                    'name' => $periodical['name'],
                    'issn' => $periodical['issn'] ?? null,
                    'url' => $periodical['url'],
                ]),
            ]);
        }

        $pages = $publication->getData('pages');
        [$pageStart, $pageEnd] = array_pad(explode('-', (string) $pages, 2), 2, null);

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'ScholarlyArticle',
            'headline' => mb_substr($title, 0, 110),
            'name' => $title,
            'author' => $authors ?: null,
            'datePublished' => $datePublished ? date('Y-m-d', strtotime($datePublished)) : null,
            'dateModified' => $publication->getData('lastModified') ? date('Y-m-d', strtotime($publication->getData('lastModified'))) : null,
            'abstract' => $abstract ?: null,
            'keywords' => array_values(array_filter($keywords)) ?: null,
            'inLanguage' => $pubLocale ? LocaleConversion::toBcp47($pubLocale) : null,
            'url' => $this->articleUrl($request, $article),
            'sameAs' => $doi ? 'https://doi.org/' . $doi : null,
            'identifier' => $doi ? ['@type' => 'PropertyValue', 'propertyID' => 'DOI', 'value' => $doi] : null,
            'pageStart' => $pageStart ? trim($pageStart) : null,
            'pageEnd' => $pageEnd ? trim($pageEnd) : null,
            'license' => $publication->getData('licenseUrl') ?: ($context->getData('licenseUrl') ?: null),
            'isAccessibleForFree' => true,
            'isPartOf' => $isPartOf,
            'publisher' => self::PUBLISHER,
        ];
        return array_filter($data, fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    private function addArticleOpenGraph(TemplateManager $templateMgr, $publication, ?string $pubLocale): void
    {
        $tags = '';
        if ($date = $publication->getData('datePublished')) {
            $tags .= '<meta property="article:published_time" content="' . $this->attr(date('c', strtotime($date))) . '" />' . "\n";
        }
        foreach ($publication->getData('authors') ?? [] as $author) {
            $tags .= '<meta property="article:author" content="' . $this->attr($author->getFullName(false, false, $pubLocale)) . '" />' . "\n";
        }
        if ($tags) {
            $templateMgr->addHeader('csvetArticleOg', $tags);
        }
    }

    /**
     * URL canônica: sem query string; no artigo, sempre a versão atual (sem /version/N).
     */
    private function canonicalUrl($request, string $page, TemplateManager $templateMgr): ?string
    {
        if (in_array($page, ['search', 'searchResults', 'userLogin', 'userRegister', 'userLostPassword', 'error'], true)) {
            return null;
        }
        if ($page === 'article' && ($article = $templateMgr->getTemplateVars('article'))) {
            $args = $request->getRequestedArgs();
            if (count($args) > 1 && $args[1] === 'version') {
                return $this->articleUrl($request, $article);
            }
        }
        $url = strtok($request->getCompleteUrl(), '?') ?: null;
        // .../about/index e .../nvas/pt_BR/index são a mesma página que .../about e .../nvas/pt_BR
        return $url ? preg_replace('#^(https?://[^/]+/[^/]+/[^/]+(?:/.*?)?)(?:/index)+$#', '$1', $url) : null;
    }

    private function articleUrl($request, $article): string
    {
        $publication = $article->getCurrentPublication();
        $bestId = $publication?->getData('urlPath') ?: $article->getId();
        return $request->getDispatcher()->url($request, Application::ROUTE_PAGE, null, 'article', 'view', [$bestId]);
    }

    private function contextUrl($context): string
    {
        $request = Application::get()->getRequest();
        return $request->getDispatcher()->url($request, Application::ROUTE_PAGE, $context->getPath(), urlLocaleForPage: '');
    }

    private function contextImage($context): ?string
    {
        // Imagem de compartilhamento 1200x630 versionada no tema: images/og/<path da revista>.png
        $path = preg_replace('/[^a-z0-9_-]/i', '', (string) $context->getPath());
        // ?v=<hash>: imagem nova = URL nova, senão WhatsApp/Facebook seguem com a versão em cache
        $file = __DIR__ . "/images/og/{$path}.png";
        if ($path && is_file($file)) {
            return Application::get()->getRequest()->getBaseUrl() . "/plugins/themes/csvet/images/og/{$path}.png?v=" . substr(md5_file($file), 0, 8);
        }

        foreach (['homepageImage', 'journalThumbnail', 'pageHeaderLogoImage'] as $setting) {
            $file = $context->getLocalizedData($setting);
            if (!empty($file['uploadName'])) {
                return $this->publicFileUrl($context, $file['uploadName']);
            }
        }
        return null;
    }

    /**
     * Resumo e imagem de compartilhamento de uma página personalizada:
     * - título: atributo data-title do primeiro elemento (ver display()), senão o do menu
     * - descrição: atributo data-summary do primeiro elemento, senão o primeiro parágrafo
     * - imagem: images/og/<revista>-<página>.<idioma>.png ou images/og/<revista>-<página>.png
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function customPageSummary($context, string $content, string $locale): array
    {
        $description = null;
        if (preg_match('/^\s*<[a-z]+[^>]*\sdata-summary="([^"]*)"/i', $content, $m)) {
            $description = $this->clean($m[1]);
        } elseif (preg_match('#<p[^>]*>(.*?)</p>#is', $content, $m)) {
            $description = $this->clean($m[1]);
        }

        $image = null;
        $request = Application::get()->getRequest();
        $args = $request->getRequestedArgs();
        $pagePath = preg_replace('/[^a-z0-9_-]/i', '', $request->getRequestedPage() . (count($args) ? '-' . implode('-', $args) : ''));
        $journal = preg_replace('/[^a-z0-9_-]/i', '', (string) $context->getPath());
        foreach (["{$journal}-{$pagePath}.{$locale}", "{$journal}-{$pagePath}"] as $name) {
            $file = __DIR__ . "/images/og/{$name}.png";
            if ($journal && $pagePath && is_file($file)) {
                $image = $request->getBaseUrl() . "/plugins/themes/csvet/images/og/{$name}.png?v=" . substr(md5_file($file), 0, 8);
                break;
            }
        }
        return [$description, $image];
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
