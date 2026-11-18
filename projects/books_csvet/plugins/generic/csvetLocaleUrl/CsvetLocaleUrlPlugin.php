<?php

/**
 * @file plugins/generic/csvetLocaleUrl/CsvetLocaleUrlPlugin.php
 *
 * @class CsvetLocaleUrlPlugin
 *
 * @brief Faz o idioma da URL (/editora/en/...) valer já na própria requisição.
 *
 *  No OJS 3.5.0, quando o idioma da URL difere do idioma da sessão, o roteador grava o
 *  novo idioma na sessão e redireciona (302) para a mesma URL. Quem não guarda cookies
 *  (Googlebot, Bing, prévias do WhatsApp/LinkedIn, Crossref) nunca tem sessão, então
 *  recebe o mesmo 302 para sempre: todas as páginas no idioma que não é o principal (aqui, inglês) ficam fora do Google.
 *
 *  Aqui, antes do roteador, o idioma da URL passa a ser o idioma da requisição (e da
 *  sessão). O roteador vê URL e idioma iguais e segue sem redirecionar. A troca de
 *  idioma pelo menu (user/setLocale) continua com o fluxo normal do OJS.
 *
 *  É um plugin genérico porque precisa rodar no Dispatcher::dispatch, antes do tema.
 *  Registrado por scripts/configure-press.php (mesmo plugin de journals.csvet.com.br).
 */

namespace APP\plugins\generic\csvetLocaleUrl;

use APP\core\Application;
use APP\template\TemplateManager;
use PKP\core\Core;
use PKP\core\PKPPageRouter;
use PKP\core\PKPRequest;
use PKP\facades\Locale;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;

class CsvetLocaleUrlPlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null)
    {
        if (!parent::register($category, $path, $mainContextId)) {
            return false;
        }
        if (Application::isInstalled() && !Application::isUnderMaintenance()) {
            Hook::add('Dispatcher::dispatch', $this->useUrlLocale(...));
        }
        return true;
    }

    public function getDisplayName()
    {
        return 'CSVET: idioma pela URL';
    }

    public function getDescription()
    {
        return 'Usa o idioma indicado na URL sem redirecionar, para que buscadores e prévias de redes sociais acessem as páginas em todos os idiomas.';
    }

    public function useUrlLocale(string $hookName, array $args): bool
    {
        /** @var PKPRequest $request */
        $request = $args[0];
        try {
            $router = $request->getRouter();
            if (!$router instanceof PKPPageRouter || $router->getRequestedOp($request) === 'setLocale') {
                return Hook::CONTINUE;
            }
            $context = $router->getContext($request);
            $locales = $context?->getSupportedLocales() ?? $request->getSite()->getSupportedLocales();
            // Mesma leitura da URL que o PKPPageRouter faz
            $urlLocale = Core::getLocalization($_SERVER['PATH_INFO'] ?? '');
            if (count($locales) < 2 || !in_array($urlLocale, $locales, true) || $urlLocale === Locale::getLocale()) {
                return Hook::CONTINUE;
            }
            Locale::setLocale($urlLocale);
            $request->getSession()->put('currentLocale', $urlLocale);
            $request->setCookieVar('currentLocale', $urlLocale);
            // Outro plugin genérico pode ter iniciado o TemplateManager antes deste hook, e ele
            // guarda textos já traduzidos no idioma anterior: refaz os que dependem do idioma
            // (inclusive o <html lang>, que o Google usa para saber o idioma da página)
            TemplateManager::getManager($request)->assign([
                'currentLocale' => $urlLocale,
                'currentLocaleLangDir' => Locale::getMetadata($urlLocale)?->isRightToLeft() ? 'rtl' : 'ltr',
                'siteTitle' => $context ? $context->getLocalizedName() : $request->getSite()->getLocalizedTitle(),
                'pageFooter' => ($context ?? $request->getSite())->getLocalizedData('pageFooter'),
            ]);
        } catch (\Throwable $e) {
            // Na dúvida, fica o comportamento padrão do OJS
            error_log('csvetLocaleUrl: ' . $e->getMessage());
        }
        return Hook::CONTINUE;
    }
}
