<?php

/**
 * @file plugins/generic/csvetGuide/GuideHandler.php
 *
 * @class GuideHandler
 *
 * @brief /<revista>/guide: guia editorial dentro do layout do painel (menu lateral do OJS).
 *  Só gestores e editores de seção da revista.
 */

namespace APP\plugins\generic\csvetGuide;

use APP\handler\Handler;
use APP\template\TemplateManager;
use PKP\security\authorization\ContextRequiredPolicy;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\Role;

class GuideHandler extends Handler
{
    /** Monta o painel (menu lateral, cabeçalho) em volta da página */
    public $_isBackendPage = true;

    public function __construct(public CsvetGuidePlugin $plugin)
    {
        parent::__construct();
        $this->addRoleAssignment([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR], ['index']);
    }

    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new ContextRequiredPolicy($request));
        $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);
        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }
        $this->addPolicy($rolePolicy);
        return parent::authorize($request, $args, $roleAssignments);
    }

    public function index($args, $request)
    {
        $this->setupTemplate($request);
        header('X-Robots-Tag: noindex');
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->addStyleSheet('csvetGuide', file_get_contents(__DIR__ . '/guide.css'), [
            'inline' => true,
            'contexts' => 'backend',
        ]);
        $templateMgr->assign([
            'pageTitle' => 'Guia editorial',
            'guides' => $this->plugin->guides(),
        ]);
        $templateMgr->display($this->plugin->getTemplateResource('guide.tpl'));
    }
}
