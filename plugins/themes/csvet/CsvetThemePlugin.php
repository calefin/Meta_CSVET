<?php

/**
 * @file plugins/themes/csvet/CsvetThemePlugin.php
 *
 * @class CsvetThemePlugin
 *
 * @brief Tema da CSVET Editora: filho do tema padrão com a identidade visual
 *  compartilhada com csvet.com.br e csvet.app (navy, azul #00aaff, Roboto).
 */

namespace APP\plugins\themes\csvet;

use APP\core\Application;
use APP\template\TemplateManager;
use PKP\plugins\Hook;
use PKP\plugins\ThemePlugin;

class CsvetThemePlugin extends ThemePlugin
{
    public function init()
    {
        $this->setParent('defaultthemeplugin');

        // Variáveis da marca: aplicadas depois das do tema padrão, então prevalecem
        // mesmo que as opções de cor/tipografia do tema pai fiquem no padrão.
        $this->modifyStyle('stylesheet', [
            'addLessVariables' => join("\n", [
                '@bg-base: #000067;',
                '@text-bg-base: #fff;',
                '@bg-base-border-color: rgba(255,255,255,0.12);',
                '@primary: #000080;',
                '@primary-lift: #0088cc;',
                '@font: Roboto, -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;',
                '@font-heading: @font;',
                '@radius: 6px;',
            ]),
        ]);

        // Fontes locais (sem Google Fonts) e estilos da marca
        $this->modifyStyle('stylesheet', ['addLess' => ['styles/fonts.less', 'styles/csvet.less']]);
        $this->removeStyle('font');

        // SEO: canonical, description, Open Graph, JSON-LD e sitemap limpo
        $seo = new SeoHeaders();
        Hook::add('TemplateManager::display', $seo->display(...));
        Hook::add('SitemapHandler::createJournalSitemap', $seo->sitemap(...));

        // Painel do OJS (área logada) utilizável no celular: menu em gaveta e conteúdo na largura toda
        // (?v= com a impressão digital do arquivo: o navegador guarda CSS/JS por uma semana)
        $version = fn (string $file) => $file . '?v=' . substr((string) @md5_file(__DIR__ . '/' . $file), 0, 8);
        $this->addStyle('csvetBackendMobile', $version('styles/backend.css'), ['contexts' => 'backend']);
        $this->addScript('csvetBackendMobile', $version('js/backend.js'), ['contexts' => 'backend']);
        // Confirmação em palavras simples antes de registrar uma decisão editorial
        $this->addScript('csvetDecisionConfirm', $version('js/decisionConfirm.js'), ['contexts' => 'backend']);
        // Formulários com link (ROR, ORCID, Lattes...) sem o 406 "Not Acceptable" do ModSecurity;
        // o servidor desfaz a codificação no plugin generic/csvetFormUrls
        $this->addScript('csvetFormUrls', $version('js/formurls.js'), ['contexts' => ['frontend', 'backend']]);

        // App instalável no celular (PWA): manifesto, ícones e service worker na raiz do site
        $templateMgr = TemplateManager::getManager(Application::get()->getRequest());
        $icons = '/plugins/themes/csvet/images/pwa';
        $templateMgr->addHeader('csvetPwa', join("\n", [
            '<link rel="manifest" href="/manifest.webmanifest" />',
            '<meta name="theme-color" content="#000067" />',
            '<meta name="mobile-web-app-capable" content="yes" />',
            '<meta name="apple-mobile-web-app-capable" content="yes" />',
            '<meta name="apple-mobile-web-app-title" content="NVAS" />',
            '<meta name="apple-mobile-web-app-status-bar-style" content="black" />',
            "<link rel=\"apple-touch-icon\" href=\"{$icons}/apple-touch-icon.png\" />",
        ]), ['contexts' => ['frontend', 'backend']]);
        $templateMgr->addJavaScript(
            'csvetServiceWorker',
            "if ('serviceWorker' in navigator) { window.addEventListener('load', function () { navigator.serviceWorker.register('/sw.js').catch(function () {}); }); }",
            ['inline' => true, 'contexts' => ['frontend', 'backend']]
        );
    }

    public function getDisplayName()
    {
        return __('plugins.themes.csvet.name');
    }

    public function getDescription()
    {
        return __('plugins.themes.csvet.description');
    }
}
