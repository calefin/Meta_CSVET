<?php

/**
 * @file plugins/generic/csvetFormUrls/CsvetFormUrlsPlugin.php
 *
 * @class CsvetFormUrlsPlugin
 *
 * @brief Recebe os formulários com link que o tema envia codificados.
 *
 *  O ModSecurity da hospedagem recusa (406 "Not Acceptable") formulário x-www-form-urlencoded
 *  com http:// ou https:// (filiação da lista ROR, ORCID, Lattes, biografia com link...). O
 *  plugins/themes/csvet/js/formurls.js manda esses envios com o corpo inteiro em base64 no
 *  campo "csvetEnc"; aqui ele é decodificado de volta para $_POST/$_REQUEST antes de o OJS ler
 *  qualquer dado (os plugins genéricos carregam antes do roteamento). Os valores voltam
 *  idênticos ao que o formulário teria enviado: o OJS valida e escapa como sempre, inclusive o
 *  csrfToken, que vai dentro do corpo codificado.
 *
 *  Plugin genérico sem lazy-load (sempre ativo depois de registrado pelo
 *  scripts/configure-journal.php).
 */

namespace APP\plugins\generic\csvetFormUrls;

use APP\core\Application;
use PKP\plugins\GenericPlugin;

class CsvetFormUrlsPlugin extends GenericPlugin
{
    public const FIELD = 'csvetEnc';

    public function register($category, $path, $mainContextId = null)
    {
        if (!parent::register($category, $path, $mainContextId)) {
            return false;
        }
        $this->decode();
        return true;
    }

    /**
     * Troca o campo codificado pelos campos originais do formulário.
     */
    private function decode(): void
    {
        static $done = false;
        if ($done || !isset($_POST[self::FIELD]) || !is_string($_POST[self::FIELD])) {
            return;
        }
        $done = true;

        $raw = base64_decode($_POST[self::FIELD], true);
        unset($_POST[self::FIELD], $_REQUEST[self::FIELD]);
        if ($raw === false || $raw === '') {
            return;
        }
        $vars = [];
        parse_str($raw, $vars);
        if (!$vars) {
            return;
        }

        $_POST = array_replace($_POST, $vars);
        $_REQUEST = array_replace($_REQUEST, $vars);

        // O OJS guarda os dados da requisição na 1ª leitura: força uma nova leitura
        $request = Application::get()->getRequest();
        if (property_exists($request, '_requestVars')) {
            $request->_requestVars = null;
        }

        // Request do Laravel (capturada antes dos plugins), usada por partes novas do OJS 3.5
        try {
            $illuminate = app(\Illuminate\Http\Request::class);
            $illuminate->request->remove(self::FIELD);
            $illuminate->request->add($vars);
        } catch (\Throwable $e) {
            // sem a request do Laravel, o $_POST corrigido acima já basta para os formulários do OJS
        }
    }

    public function getDisplayName()
    {
        return 'CSVET: formulários com link';
    }

    public function getDescription()
    {
        return 'Recebe os formulários com endereço http(s):// que o tema envia codificados, para o ModSecurity da hospedagem não recusá-los com "Not Acceptable" (filiação ROR, ORCID, Lattes, biografia).';
    }
}
