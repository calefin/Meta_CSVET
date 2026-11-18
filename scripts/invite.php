<?php

/**
 * Envia, em lote, convites do OMP a partir de uma planilha CSV: Conselho Editorial,
 * editores de coleção, pareceristas e AUTORES DE CAPÍTULO de coletâneas.
 * Usa a API REST do próprio OMP (o mesmo fluxo do botão "Convidar para um papel"):
 * a pessoa recebe o e-mail, cria a conta (ou entra na que já tem), informa afiliação/ORCID e aceita.
 *
 * Uso (no servidor, na raiz do OMP):
 *   php scripts/invite.php ~/convites/conselho.csv --user=SEU_USUARIO           # simula
 *   php scripts/invite.php ~/convites/conselho.csv --user=SEU_USUARIO --send    # envia
 *
 * Coletâneas: o organizador preenche a planilha "convites de capítulos" (download em
 * Instruções aos Autores); depois do aceite, scripts/assign-chapter-authors.php coloca
 * cada autor no livro, já com o capítulo cadastrado.
 *
 * Opções:
 *   --user=USUARIO   gera o token de API a partir da chave de API desse usuário (perfil > Chave de API).
 *                    Alternativa: exportar OMP_API_TOKEN com o token copiado do perfil.
 *   --send           envia de verdade (sem isso, só mostra o que faria e testa o acesso à API)
 *   --press=editora  caminho da editora (padrão: editora)
 *   --base-url=URL   URL do site (padrão: base_url do config.inc.php)
 *
 * A planilha (CSV UTF-8, separado por ; ou ,) precisa das colunas:
 *   nome, sobrenome, email, funcao   (obrigatórias; funcao = uma das chaves de content/editora/invitations.json)
 *   area, instituicao, pais, idioma, data_inicio, status   (opcionais)
 *   livro, capitulo, prazo, organizador   (autores de capítulo)
 * Só são processadas as linhas com status vazio ou "a convidar". O resultado de cada
 * linha é gravado em <planilha>.resultado.csv, para atualizar a planilha depois.
 *
 * A planilha tem dados pessoais: guarde-a FORA da pasta pública e fora do Git.
 */

$root = dirname(__DIR__);
chdir($root);

$opts = ['send' => false, 'press' => 'editora', 'user' => null, 'base-url' => null];
$csvPath = null;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--send') {
        $opts['send'] = true;
    } elseif (preg_match('/^--(press|user|base-url)=(.+)$/', $arg, $m)) {
        $opts[$m[1]] = $m[2];
    } elseif ($arg[0] !== '-') {
        $csvPath = $arg;
    } else {
        fwrite(STDERR, "Opção desconhecida: {$arg}\n");
        exit(1);
    }
}
if (!$csvPath || !is_file($csvPath)) {
    fwrite(STDERR, "Informe a planilha CSV. Ex.: php scripts/invite.php ~/convites/conselho.csv --user=admin\n");
    exit(1);
}

require $root . '/tools/bootstrap.php';

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Support\Facades\DB;
use PKP\config\Config;

function out(string $msg): void
{
    echo $msg, "\n";
}
function fail(string $msg): never
{
    fwrite(STDERR, "ERRO: {$msg}\n");
    exit(1);
}
function normalize(string $s): string
{
    $s = mb_strtolower(trim($s));
    $s = strtr($s, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c']);
    return preg_replace('/[^a-z0-9]+/', '_', $s);
}

// ---------------------------------------------------------------- configuração
$templates = json_decode(file_get_contents("{$root}/content/{$opts['press']}/invitations.json"), true, 512, JSON_THROW_ON_ERROR);
$context = Application::getContextDAO()->getByPath($opts['press']) ?: fail("editora '{$opts['press']}' não encontrada");
$contextId = $context->getId();
$baseUrl = rtrim($opts['base-url'] ?: Config::getVar('general', 'base_url'), '/');
$apiBase = "{$baseUrl}/{$opts['press']}/api/v1";

$site = DB::table('site')->first();
$nameLocales = array_values(array_unique(array_merge(
    array_intersect($context->getSupportedFormLocales() ?: [], ['en', 'pt_BR', 'es']),
    [$context->getPrimaryLocale(), $site->primary_locale]
)));

// Papéis (user groups) da editora, pelo role_id + nome em inglês
$roleGroups = [];
foreach ($templates['roles'] as $function => $role) {
    $id = DB::table('user_groups as g')
        ->join('user_group_settings as s', 's.user_group_id', '=', 'g.user_group_id')
        ->where('g.context_id', $contextId)
        ->where('g.role_id', $role['userGroup']['roleId'])
        ->where('s.setting_name', 'name')
        ->where('s.locale', 'en')
        ->where('s.setting_value', $role['userGroup']['name'])
        ->value('g.user_group_id');
    $roleGroups[normalize($function)] = ['function' => $function, 'userGroupId' => $id] + $role;
}

// ---------------------------------------------------------------- token de API
$token = getenv('OMP_API_TOKEN') ?: null;
if (!$token && $opts['user']) {
    $secret = Config::getVar('security', 'api_key_secret', '');
    if ($secret === '') {
        fail("defina api_key_secret no config.inc.php (seção [security]) e gere a chave de API no seu perfil do OMP.");
    }
    $user = Repo::user()->getByUsername($opts['user'], true) ?: fail("usuário '{$opts['user']}' não encontrado");
    if (!$user->getData('apiKeyEnabled') || !$user->getData('apiKey')) {
        fail("o usuário '{$opts['user']}' não tem chave de API. No OMP: Perfil > Chave de API > Gerar nova chave > Salvar.");
    }
    $token = \Firebase\JWT\JWT::encode([$user->getData('apiKey')], $secret, 'HS256');
}
if (!$token) {
    fail('informe --user=SEU_USUARIO (ou exporte OMP_API_TOKEN).');
}

function api(string $method, string $url, ?array $data = null): array
{
    global $token;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => array_filter([
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
            $data !== null ? 'Content-Type: application/json' : null,
        ]),
        CURLOPT_POSTFIELDS => $data !== null ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    return [$code, $body === false ? null : json_decode($body, true), $error ?: $body];
}

[$code, , $raw] = api('GET', "{$apiBase}/users?count=1");
if ($code !== 200) {
    fail("a API recusou o acesso (HTTP {$code}). Confira api_key_secret, a chave de API do usuário e se ele é gerente/editor da editora. Resposta: " . mb_substr((string) $raw, 0, 300));
}
out("==> API ok em {$apiBase}" . ($opts['send'] ? '' : '  [SIMULAÇÃO: use --send para enviar]'));

// ---------------------------------------------------------------- planilha
$content = preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents($csvPath));
$lines = preg_split('/\r\n|\r|\n/', trim($content));
$delimiter = substr_count($lines[0], ';') >= substr_count($lines[0], ',') ? ';' : ',';
$header = array_map('normalize', str_getcsv(array_shift($lines), $delimiter));
foreach (['nome', 'sobrenome', 'email', 'funcao'] as $required) {
    if (!in_array($required, $header, true)) {
        fail("a planilha não tem a coluna '{$required}'. Colunas encontradas: " . implode(', ', $header));
    }
}

$results = [['email', 'funcao', 'resultado', 'convite_id', 'data']];
$counts = ['enviado' => 0, 'simulado' => 0, 'ignorado' => 0, 'erro' => 0];

foreach ($lines as $n => $line) {
    if (trim($line) === '' || trim($line, $delimiter . ' ') === '') {
        continue;
    }
    $row = array_combine($header, array_pad(array_map('trim', str_getcsv($line, $delimiter)), count($header), ''));
    $label = sprintf('linha %d: %s %s <%s>', $n + 2, $row['nome'], $row['sobrenome'], $row['email']);
    $status = normalize($row['status'] ?? '');
    $record = function (string $result, $invitationId = '') use (&$results, $row) {
        $results[] = [$row['email'], $row['funcao'], $result, $invitationId, date('Y-m-d H:i')];
    };

    if ($status !== '' && $status !== 'a_convidar') {
        $counts['ignorado']++;
        continue;
    }
    $role = $roleGroups[normalize($row['funcao'])] ?? null;
    if (!$role) {
        out("  ✗ {$label}: função '{$row['funcao']}' desconhecida (use: " . implode(', ', array_column($roleGroups, 'function')) . ')');
        $record('erro: função desconhecida');
        $counts['erro']++;
        continue;
    }
    if (!$role['userGroupId']) {
        out("  ✗ {$label}: o papel '{$role['userGroup']['name']}' não existe na editora");
        $record('erro: papel inexistente');
        $counts['erro']++;
        continue;
    }
    if (!filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
        out("  ✗ {$label}: e-mail inválido");
        $record('erro: e-mail inválido');
        $counts['erro']++;
        continue;
    }

    $existing = Repo::user()->getByEmail($row['email'], true);
    if ($existing && DB::table('user_user_groups')->where('user_id', $existing->getId())->where('user_group_id', $role['userGroupId'])->exists()) {
        out("  - {$label}: já tem o papel {$role['function']}");
        $record('já possui o papel');
        $counts['ignorado']++;
        continue;
    }

    // Idioma do e-mail: coluna "idioma" (pt/en) ou, sem ela, pt_BR para o Brasil e en para os demais
    $language = normalize($row['idioma'] ?? '');
    $country = strtoupper(trim($row['pais'] ?? '')) ?: 'BR';
    $locale = str_starts_with($language, 'en') ? 'en' : (str_starts_with($language, 'pt') ? 'pt_BR' : ($country === 'BR' ? 'pt_BR' : 'en'));

    $replace = [
        '{nome}' => htmlspecialchars($row['nome']),
        '{area}' => htmlspecialchars(($row['area'] ?? '') !== '' ? $row['area'] : ($locale === 'en' ? 'your field' : 'sua área')),
        '{funcao}' => htmlspecialchars($role['function']),
        '{livro}' => htmlspecialchars($row['livro'] ?? ''),
        '{capitulo}' => htmlspecialchars($row['capitulo'] ?? ''),
        '{prazo}' => htmlspecialchars($row['prazo'] ?? ''),
        '{organizador}' => htmlspecialchars($row['organizador'] ?? ''),
    ];
    foreach ($role['requires'] ?? [] as $column) {
        if (trim($row[$column] ?? '') === '') {
            out("  ✗ {$label}: a função {$role['function']} exige a coluna '{$column}' preenchida");
            $record("erro: falta {$column}");
            $counts['erro']++;
            continue 2;
        }
    }
    $body = strtr(str_replace('{intro}', $role['intro'][$locale], $templates['body'][$role['body'] ?? 'default'][$locale]), $replace);
    $subject = strtr($role['subject'][$locale], $replace);

    $dateStart = preg_match('/^\d{4}-\d{2}-\d{2}$/', $row['data_inicio'] ?? '') ? $row['data_inicio'] : date('Y-m-d');
    if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $row['data_inicio'] ?? '', $m)) {
        $dateStart = "{$m[3]}-{$m[2]}-{$m[1]}";
    }

    $invitationData = [
        'userGroupsToAdd' => [['userGroupId' => (int) $role['userGroupId'], 'masthead' => (bool) $role['masthead'], 'dateStart' => $dateStart]],
        'emailComposer' => ['subject' => $subject, 'body' => $body],
    ];
    if (!$existing) {
        $invitationData['givenName'] = array_fill_keys($nameLocales, $row['nome']);
        $invitationData['familyName'] = array_fill_keys($nameLocales, $row['sobrenome']);
        if (($row['instituicao'] ?? '') !== '') {
            $invitationData['affiliation'] = array_fill_keys($nameLocales, $row['instituicao']);
        }
        $invitationData['userCountry'] = $country;
    }

    $who = $existing ? "usuário existente #{$existing->getId()}" : 'novo usuário';
    if (!$opts['send']) {
        out("  ○ {$label}: {$role['function']} ({$who}, e-mail em {$locale}) — \"{$subject}\"");
        $counts['simulado']++;
        continue;
    }

    [$code, $json, $raw] = api('POST', "{$apiBase}/invitations/add/userRoleAssignment", [
        'invitationData' => $existing ? ['userId' => $existing->getId()] : ['inviteeEmail' => $row['email']],
    ]);
    $invitationId = $json['invitationId'] ?? null;
    if ($code !== 200 || !$invitationId) {
        out("  ✗ {$label}: falha ao criar o convite (HTTP {$code}) " . mb_substr((string) $raw, 0, 300));
        $record("erro: criar ({$code})");
        $counts['erro']++;
        continue;
    }
    [$code, , $raw] = api('PUT', "{$apiBase}/invitations/{$invitationId}/populate", ['invitationData' => $invitationData]);
    if ($code !== 200) {
        out("  ✗ {$label}: dados recusados (HTTP {$code}) " . mb_substr((string) $raw, 0, 400));
        api('PUT', "{$apiBase}/invitations/{$invitationId}/cancel");
        $record("erro: dados ({$code})", $invitationId);
        $counts['erro']++;
        continue;
    }
    [$code, , $raw] = api('PUT', "{$apiBase}/invitations/{$invitationId}/invite", []);
    if ($code !== 200) {
        out("  ✗ {$label}: falha ao enviar (HTTP {$code}) " . mb_substr((string) $raw, 0, 400));
        $record("erro: envio ({$code})", $invitationId);
        $counts['erro']++;
        continue;
    }
    out("  ✓ {$label}: convite enviado ({$role['function']}, {$locale}, convite #{$invitationId})");
    $record('convidado', $invitationId);
    $counts['enviado']++;
}

if ($opts['send']) {
    $resultFile = preg_replace('/\.csv$/i', '', $csvPath) . '.resultado.csv';
    $fh = fopen($resultFile, 'w');
    fwrite($fh, "\xEF\xBB\xBF");
    foreach ($results as $line) {
        fputcsv($fh, $line, ';');
    }
    fclose($fh);
    out("==> Resultado gravado em {$resultFile}");
}
out(sprintf('==> %d enviado(s), %d simulado(s), %d ignorado(s), %d erro(s).', $counts['enviado'], $counts['simulado'], $counts['ignorado'], $counts['erro']));
