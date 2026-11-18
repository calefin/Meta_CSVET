<?php

/**
 * @file plugins/generic/csvetGuide/CsvetGuidePlugin.php
 *
 * @class CsvetGuidePlugin
 *
 * @brief Guia editorial no painel: item "Guia editorial" no menu lateral e a página
 *  /<revista>/guide, dentro do layout do painel, com o fluxo do editor-chefe e o da coeditora
 *  (guides/*.html). Só gestores e editores de seção da revista veem o item e abrem a página.
 *
 *  Sempre ativo depois de registrado por scripts/configure-journal.php ("plugins.install" do
 *  journal.json). Para mudar o texto, edite guides/editor.html ou guides/coeditora.html.
 */

namespace APP\plugins\generic\csvetGuide;

use APP\core\Application;
use APP\template\TemplateManager;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\security\Role;

class CsvetGuidePlugin extends GenericPlugin
{
    public const PAGE = 'guide';

    public function register($category, $path, $mainContextId = null)
    {
        if (!parent::register($category, $path, $mainContextId)) {
            return false;
        }
        if (Application::isInstalled() && !Application::isUnderMaintenance()) {
            Hook::add('LoadHandler', $this->loadHandler(...));
            Hook::add('TemplateManager::setupBackendPage', $this->addMenuItem(...));
        }
        return true;
    }

    public function getDisplayName()
    {
        return 'CSVET: guia editorial';
    }

    public function getDescription()
    {
        return 'Guia do fluxo editorial (editor-chefe e coeditora) no menu do painel.';
    }

    public function loadHandler(string $hookName, array $params): bool
    {
        $page = &$params[0];
        $handler = &$params[3];
        if ($page === self::PAGE) {
            $handler = new GuideHandler($this);
            return true;
        }
        return false;
    }

    /** Item do menu lateral, depois dos que o OJS já montou */
    public function addMenuItem(string $hookName, array $args): bool
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $user = $request->getUser();
        if (!$context || !$user || !$user->hasRole([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR], $context->getId())) {
            return false;
        }
        $templateMgr = TemplateManager::getManager($request);
        $menu = (array) $templateMgr->getState('menu');
        $menu['csvetGuide'] = [
            'name' => 'Guia editorial',
            'url' => $request->getDispatcher()->url($request, Application::ROUTE_PAGE, null, self::PAGE),
            'isCurrent' => $request->getRequestedPage() === self::PAGE,
            'icon' => 'Book',
        ];
        $templateMgr->setState(['menu' => $menu]);
        return false;
    }

    /** Conteúdo dos guias (HTML próprio, sem dados de usuário) */
    public function guides(): array
    {
        return [
            'editor' => file_get_contents(__DIR__ . '/guides/editor.html'),
            'coeditora' => file_get_contents(__DIR__ . '/guides/coeditora.html'),
        ];
    }
}
