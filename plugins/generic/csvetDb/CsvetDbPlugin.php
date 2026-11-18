<?php

/**
 * @file plugins/generic/csvetDb/CsvetDbPlugin.php
 *
 * @class CsvetDbPlugin
 *
 * @brief Evita o erro 1615 do MySQL da hospedagem ("Prepared statement needs to be re-prepared").
 *
 *  O Laravel prepara cada consulta no próprio MySQL. Na hospedagem compartilhada o cache de
 *  definições de tabelas é pequeno e algumas consultas falham sempre com o erro 1615; o autor
 *  vê só "Ocorreu um erro inesperado" (ex.: salvar palavras-chave no formulário de submissão,
 *  2026-10-17). Com a preparação feita pelo PHP (emulated prepares) o MySQL recebe a consulta
 *  pronta e o erro não acontece. Os valores continuam escapados pelo PDO, e no PHP 8.1+ os
 *  números voltam como número, como antes.
 *
 *  É um plugin genérico sem lazy-load porque precisa valer em toda requisição, inclusive na API,
 *  antes do roteamento. Registrado por scripts/configure-journal.php.
 */

namespace APP\plugins\generic\csvetDb;

use APP\core\Application;
use Illuminate\Support\Facades\DB;
use PDO;
use PKP\plugins\GenericPlugin;

class CsvetDbPlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null)
    {
        if (!parent::register($category, $path, $mainContextId)) {
            return false;
        }
        if (Application::isInstalled()) {
            $connection = DB::connection();
            if (in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
                $connection->getPdo()->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
            }
        }
        return true;
    }

    public function getDisplayName()
    {
        return 'CSVET: banco de dados da hospedagem';
    }

    public function getDescription()
    {
        return 'Evita o erro 1615 do MySQL ("Prepared statement needs to be re-prepared"), que aparecia ao autor como "Ocorreu um erro inesperado".';
    }
}
