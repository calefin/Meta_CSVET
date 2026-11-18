<?php

/**
 * @file plugins/generic/csvetScreening/ScreeningHandler.php
 *
 * @class ScreeningHandler
 *
 * @brief /<revista>/screening/view/<id>: relatório de triagem em página própria (link do
 *  e-mail ao editor); /<revista>/screening/data/<id>: o mesmo em JSON (painel do fluxo).
 *  Só gestores e editores de seção da revista.
 */

namespace APP\plugins\generic\csvetScreening;

use APP\facades\Repo;
use APP\handler\Handler;
use PKP\core\PKPRequest;
use PKP\facades\Locale;
use PKP\security\authorization\ContextRequiredPolicy;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\Role;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ScreeningHandler extends Handler
{
    public function __construct(public CsvetScreeningPlugin $plugin)
    {
        parent::__construct();
        $this->addRoleAssignment([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR], ['view', 'data']);
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

    private function report(array $args, PKPRequest $request): array
    {
        $context = $request->getContext();
        $submission = Repo::submission()->get((int) ($args[0] ?? 0));
        if (!$submission || (int) $submission->getData('contextId') !== (int) $context->getId() || !$submission->getCurrentPublication()) {
            throw new NotFoundHttpException();
        }
        return $this->plugin->screening()->report($submission, $context, Locale::getLocale());
    }

    public function data($args, $request)
    {
        $report = $this->report($args, $request);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function view($args, $request)
    {
        $report = $this->report($args, $request);
        $locale = Locale::getLocale();
        $s = $this->plugin->screening()->strings($locale);
        $title = htmlspecialchars($s['title'] . ' #' . $report['id']);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
        echo '<!doctype html><html lang="' . str_replace('_', '-', $locale) . '"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">'
            . "<title>{$title}</title></head>"
            . '<body style="margin:0;background:#f4f5fa;"><main style="max-width:760px;margin:0 auto;padding:24px 16px;">'
            . '<p style="font-family:Arial,sans-serif;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#000080;margin:0 0 4px;">NVAS · ' . htmlspecialchars($s['title']) . '</p>'
            . '<p style="font-family:Arial,sans-serif;font-size:13px;color:#5a6080;margin:0 0 16px;">' . htmlspecialchars($s['lead']) . '</p>'
            . '<div style="background:#fff;border:1px solid #dde1ee;border-radius:8px;padding:20px;">'
            . $this->plugin->screening()->html($report, $locale)
            . '</div></main></body></html>';
    }
}
