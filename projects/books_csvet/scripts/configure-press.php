<?php

/**
 * Aplica a configuração da editora (textos, políticas, imagens, coleções,
 * categorias, plugins, menu lateral e portal) a partir de
 * content/<editora>/press.json.
 *
 * Uso (na raiz do OMP, local ou no servidor):
 *   php scripts/configure-press.php                        # content/editora/press.json
 *   php scripts/configure-press.php content/editora/press.json
 *   php scripts/configure-press.php --create               # cria a editora se ainda não existir
 *   php scripts/configure-press.php --dry-run              # só mostra o que faria
 *
 * Idempotente: pode rodar quantas vezes quiser. Não apaga nada: campos vazios
 * no JSON não alteram o OMP; coleções e categorias existentes são atualizadas
 * (casadas pelo caminho), as demais são criadas; idiomas que a editora
 * não usa são ignorados.
 */

$root = dirname(__DIR__);
chdir($root);
require $root . '/tools/bootstrap.php';

use APP\core\Application;
use APP\facades\Repo;
use APP\file\PublicFileManager;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;

$args = array_slice($argv, 1);
$dryRun = in_array('--dry-run', $args, true);
$create = in_array('--create', $args, true);
$args = array_values(array_filter($args, fn ($a) => !str_starts_with($a, '--')));
$configFile = $args[0] ?? 'content/editora/press.json';
$baseDir = dirname($root . '/' . $configFile);

$config = json_decode(file_get_contents($root . '/' . $configFile), true, 512, JSON_THROW_ON_ERROR);

// Na linha de comando não há roteador; as URLs (e-mails e textos padrão) usam o base_url do config.inc.php
$router = new \APP\core\PageRouter();
$router->setApplication(Application::get());
Application::get()->getRequest()->setRouter($router);

function say(string $msg): void
{
    echo $msg, "\n";
}

$contextDao = Application::getContextDAO();
$context = $contextDao->getByPath($config['path']);
if (!$context && $create && !$dryRun) {
    // Mesmo caminho do painel (Administração › Editoras › Criar): o serviço cria papéis,
    // gêneros de arquivo, e-mails e menus padrão nos idiomas do portal.
    // O serviço torna o usuário atual gerente da editora: na linha de comando, o 1º administrador do site
    $admin = Repo::user()->getAdminUsers()->first();
    \PKP\core\Registry::set('user', $admin);
    $site = Application::get()->getRequest()->getSite();
    $primary = $config['primaryLocale'] ?? $site->getPrimaryLocale();
    $context = $contextDao->newDataObject();
    $context->setAllData([
        'urlPath' => $config['path'],
        'name' => array_intersect_key($config['settings']['name'], array_flip($site->getSupportedLocales())),
        'primaryLocale' => $primary,
        'supportedLocales' => array_values(array_intersect($config['locales']['supportedLocales'] ?? [$primary], $site->getSupportedLocales())),
        'enabled' => true,
    ]);
    $context = app()->get('context')->add($context, Application::get()->getRequest());
    say("  editora criada: /{$config['path']} (id {$context->getId()})");
}
if (!$context) {
    fwrite(STDERR, "Editora '{$config['path']}' não encontrada. Crie-a no painel (Administração › Editoras) ou rode com --create.\n");
    exit(1);
}
$contextId = $context->getId();

// Idiomas de formulário/metadados: acrescenta os pedidos no JSON (sem remover nenhum),
// limitados aos idiomas já ativos na editora. Sem isso os textos daquele idioma são ignorados.
$uiLocales = $context->getSupportedLocales() ?: [$context->getPrimaryLocale()];
foreach (['supportedFormLocales', 'supportedSubmissionLocales', 'supportedSubmissionMetadataLocales'] as $setting) {
    $wanted = array_intersect($config['locales'][$setting] ?? [], $uiLocales);
    $current = $context->getData($setting) ?: [];
    $merged = array_values(array_unique(array_merge($current, $wanted)));
    if ($merged !== $current) {
        $context->setData($setting, $merged);
        say("  {$setting}: " . implode(', ', $merged));
    }
}
$locales = $context->getData('supportedFormLocales') ?: [$context->getPrimaryLocale()];
say("==> Editora {$config['path']} (id {$contextId}), idiomas: " . implode(', ', $locales) . ($dryRun ? '  [DRY-RUN]' : ''));

/** Mantém só os idiomas da editora e funde com o que já existe. */
$localized = function ($value, $current) use ($locales) {
    $out = is_array($current) ? $current : [];
    foreach ($value as $locale => $text) {
        if (in_array($locale, $locales, true) && $text !== '' && $text !== null) {
            $out[$locale] = $text;
        }
    }
    return $out;
};
$isLocalizedMap = fn ($v) => is_array($v) && $v !== [] && array_keys($v) !== range(0, count($v) - 1);

// ---------------------------------------------------------------- textos e opções
$changed = [];
foreach ($config['settings'] ?? [] as $name => $value) {
    // Chaves com "_" são comentários do JSON
    if (str_starts_with($name, '_') || $value === '' || $value === null || $value === []) {
        continue;
    }
    if ($isLocalizedMap($value)) {
        $value = $localized($value, $context->getData($name));
        if (!$value) {
            continue;
        }
    }
    $context->setData($name, $value);
    $changed[] = $name;
}

foreach ($config['files'] ?? [] as $name => $pattern) {
    $values = [];
    foreach ($locales as $locale) {
        $file = $baseDir . '/' . str_replace('{locale}', $locale, $pattern);
        if (is_file($file)) {
            $values[$locale] = str_replace('{$baseUrl}', rtrim(\PKP\config\Config::getVar('general', 'base_url'), '/'), trim(file_get_contents($file)));
        }
    }
    if ($values) {
        $context->setData($name, $localized($values, $context->getData($name)));
        $changed[] = $name . ' (' . implode(', ', array_keys($values)) . ')';
    }
}

// ---------------------------------------------------------------- imagens
/**
 * Copia a imagem para a pasta pública com a impressão digital do arquivo no nome:
 * imagem nova = URL nova, então navegadores, redes sociais e buscadores não ficam
 * presos à versão antiga em cache. Remove a versão anterior gerada por este script.
 */
$publishImage = function (string $name, array $image, string $publicDir, array $current, array $imageLocales) use ($baseDir, $dryRun): ?array {
    $source = $baseDir . '/' . $image['file'];
    if (!is_file($source)) {
        say("  (aviso) imagem não encontrada: {$image['file']}");
        return null;
    }
    [$width, $height] = getimagesize($source);
    $extension = '.' . strtolower(pathinfo($source, PATHINFO_EXTENSION));
    $hash = substr(md5_file($source), 0, 8);
    foreach ($imageLocales as $locale) {
        $uploadName = "{$name}_{$locale}-{$hash}{$extension}";
        $previous = $current[$locale]['uploadName'] ?? null;
        if (!$dryRun) {
            is_dir($publicDir) || mkdir($publicDir, 0755, true);
            copy($source, "{$publicDir}/{$uploadName}");
            if ($previous && $previous !== $uploadName && preg_match('/^' . preg_quote("{$name}_{$locale}", '/') . '(-[0-9a-f]{8})?\.\w+$/', $previous)) {
                @unlink("{$publicDir}/{$previous}");
            }
        }
        $current[$locale] = [
            'name' => basename($source),
            'uploadName' => $uploadName,
            'width' => $width,
            'height' => $height,
            'dateUploaded' => Core::getCurrentDate(),
            'altText' => $image['altText'][$locale] ?? ($image['altText']['en'] ?? ''),
        ];
    }
    return $current;
};

$publicFileManager = new PublicFileManager();
$publicDir = $root . '/' . $publicFileManager->getContextFilesPath($contextId);
foreach ($config['images'] ?? [] as $name => $image) {
    $value = $publishImage($name, $image, $publicDir, $context->getData($name) ?: [], $locales);
    if ($value) {
        $context->setData($name, $value);
        $changed[] = $name;
    }
}

// ---------------------------------------------------------------- plugins
// Plugins próprios versionados no repositório (ex.: plugins/generic/csvetEditorial) precisam ser
// registrados na tabela de versões do OMP antes de serem habilitados
foreach (glob($root . '/plugins/generic/csvet*/version.xml') ?: [] as $versionFile) {
    $product = basename(dirname($versionFile));
    $installed = \Illuminate\Support\Facades\DB::table('versions')->where('product_type', 'plugins.generic')->where('product', $product)->exists();
    if (!$installed && !$dryRun) {
        passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/lib/pkp/tools/installPluginVersion.php') . ' ' . escapeshellarg($versionFile) . ' 2>&1');
        say("  plugin registrado: {$product}");
    }
}
$pluginSettings = DAORegistry::getDAO('PluginSettingsDAO');
foreach ($config['plugins']['enable'] ?? [] as $plugin) {
    $dryRun || $pluginSettings->updateSetting($contextId, $plugin, 'enabled', true, 'bool');
}
foreach ($config['plugins']['disable'] ?? [] as $plugin) {
    $dryRun || $pluginSettings->updateSetting($contextId, $plugin, 'enabled', false, 'bool');
}
foreach ($config['plugins']['enableSite'] ?? [] as $plugin) {
    $dryRun || $pluginSettings->updateSetting(PKPApplication::SITE_CONTEXT_ID, $plugin, 'enabled', true, 'bool');
}
say('  plugins habilitados: ' . implode(', ', $config['plugins']['enable'] ?? []));
if (!empty($config['plugins']['disable'])) {
    say('  plugins desabilitados: ' . implode(', ', $config['plugins']['disable']));
}

if (!$dryRun) {
    $contextDao->updateObject($context);
}
say('  configurações: ' . implode(', ', $changed));

// Configurações de plugins (ex.: instruções do pagamento manual por Pix)
foreach ($config['pluginSettings'] ?? [] as $plugin => $values) {
    foreach ($values as $name => $value) {
        if ($isLocalizedMap($value)) {
            $value = $value[$context->getPrimaryLocale()] ?? reset($value);
        }
        $dryRun || $pluginSettings->updateSetting($contextId, $plugin, $name, $value, is_bool($value) ? 'bool' : 'string');
    }
    say("  plugin {$plugin}: " . implode(', ', array_keys($values)));
}

// ---------------------------------------------------------------- tipos de arquivo (genres)
// Tipos extras na submissão (ex.: "Contrato de edição assinado"), casados pela chave entry_key
foreach ($config['genres'] ?? [] as $cfg) {
    $genreId = \Illuminate\Support\Facades\DB::table('genres')->where('context_id', $contextId)->where('entry_key', $cfg['key'])->value('genre_id');
    if (!$dryRun) {
        $genreId ??= \Illuminate\Support\Facades\DB::table('genres')->insertGetId([
            'context_id' => $contextId,
            'seq' => (int) \Illuminate\Support\Facades\DB::table('genres')->where('context_id', $contextId)->max('seq') + 1,
            'enabled' => 1, 'category' => $cfg['category'] ?? 3, 'dependent' => 0,
            'supplementary' => (int) ($cfg['supplementary'] ?? 1), 'required' => 0, 'entry_key' => $cfg['key'],
        ]);
        foreach ($cfg['name'] as $locale => $name) {
            \Illuminate\Support\Facades\DB::table('genre_settings')->updateOrInsert(
                ['genre_id' => $genreId, 'locale' => $locale, 'setting_name' => 'name'],
                ['setting_value' => $name, 'setting_type' => 'string']
            );
        }
    }
    say("  tipo de arquivo: {$cfg['name']['pt_BR']}");
}

// ---------------------------------------------------------------- portal (site)
if (!empty($config['site'])) {
    $siteDao = DAORegistry::getDAO('SiteDAO');
    $site = $siteDao->getSite();
    $siteLocales = $site->getSupportedLocales() ?: [$site->getPrimaryLocale()];
    foreach ($config['site'] as $name => $value) {
        if ($isLocalizedMap($value)) {
            $current = is_array($site->getData($name)) ? $site->getData($name) : [];
            foreach ($value as $locale => $text) {
                if (in_array($locale, $siteLocales, true)) {
                    $current[$locale] = $text;
                }
            }
            $value = $current;
        }
        $site->setData($name, $value);
    }
    // Portal com uma só editora: a raiz (books.csvet.com.br) abre direto a editora
    if (!empty($config['redirectSiteToPress'])) {
        $site->setData('redirectContextId', $contextId);
    }
    // Imagens do portal (logo do cabeçalho em public/site/)
    $siteDir = $root . '/' . $publicFileManager->getSiteFilesPath();
    foreach ($config['siteImages'] ?? [] as $name => $image) {
        $current = is_array($site->getData($name)) ? $site->getData($name) : [];
        $value = $publishImage($name, $image, $siteDir, $current, $siteLocales);
        if ($value) {
            $site->setData($name, $value);
        }
    }
    $dryRun || $siteDao->updateObject($site);
    say('  portal: ' . implode(', ', array_keys($config['site'])) . (!empty($config['redirectSiteToPress']) ? ', redirecionamento para /' . $config['path'] : ''));
}

// ---------------------------------------------------------------- coleções (séries)
$allSeries = Repo::section()->getCollector()->filterByContextIds([$contextId])->getMany()->all();
foreach ($config['series'] ?? [] as $i => $cfg) {
    $existing = null;
    foreach ($allSeries as $series) {
        if ($series->getData('path') === $cfg['path']) {
            $existing = $series;
            break;
        }
    }
    $params = [
        'title' => $localized($cfg['title'], $existing?->getData('title')),
        'path' => $cfg['path'],
        'sequence' => $i + 1,
        'featured' => $cfg['featured'] ?? false,
        'editorRestricted' => $cfg['editorRestricted'] ?? false,
        'isInactive' => $cfg['isInactive'] ?? false,
    ];
    foreach (['subtitle', 'description', 'prefix'] as $field) {
        if (!empty($cfg[$field])) {
            $params[$field] = $localized($cfg[$field], $existing?->getData($field));
        }
    }
    foreach (['onlineIssn', 'printIssn', 'sortOption'] as $field) {
        if (!empty($cfg[$field])) {
            $params[$field] = $cfg[$field];
        }
    }

    if ($existing) {
        $dryRun || Repo::section()->edit($existing, $params);
        say("  coleção atualizada: {$cfg['path']}");
    } else {
        if (!$dryRun) {
            $series = Repo::section()->newDataObject(array_merge($params, ['contextId' => $contextId]));
            Repo::section()->add($series);
        }
        say("  coleção criada: {$cfg['path']}");
    }
}

// Coleções que saíram da estrutura: desativadas (não recebem novas submissões), nunca apagadas
foreach ($config['deactivateSeries'] ?? [] as $path) {
    foreach ($allSeries as $series) {
        if ($series->getData('path') === $path && !in_array($path, array_column($config['series'] ?? [], 'path'), true)) {
            $dryRun || Repo::section()->edit($series, ['isInactive' => true, 'sequence' => 100]);
            say("  coleção desativada: {$path}");
        }
    }
}

// ---------------------------------------------------------------- categorias
$categories = Repo::category()->getCollector()->filterByContextIds([$contextId])->getMany()->all();
foreach ($config['categories'] ?? [] as $i => $cfg) {
    $existing = null;
    foreach ($categories as $category) {
        if ($category->getData('path') === $cfg['path']) {
            $existing = $category;
            break;
        }
    }
    $params = [
        'title' => $localized($cfg['title'], $existing?->getData('title')),
        'sequence' => $i + 1,
    ];
    if (!empty($cfg['description'])) {
        $params['description'] = $localized($cfg['description'], $existing?->getData('description'));
    }
    if ($existing) {
        $dryRun || Repo::category()->edit($existing, $params);
        say("  categoria atualizada: {$cfg['path']}");
    } else {
        if (!$dryRun) {
            $category = Repo::category()->newDataObject(array_merge($params, [
                'contextId' => $contextId,
                'path' => $cfg['path'],
                'parentId' => null,
            ]));
            Repo::category()->add($category);
        }
        say("  categoria criada: {$cfg['path']}");
    }
}

// ---------------------------------------------------------------- papéis (user groups)
// Casados pelo role_id + nome em inglês. Ajusta nomes/abreviações por idioma (ex.: "Editor de volume"
// vira "Organizador(a)") e acrescenta etapas do fluxo (nunca remove): o autor de capítulo passa a
// participar desde a submissão, para entregar o capítulo e conversar com o organizador.
foreach ($config['userGroups'] ?? [] as $cfg) {
    $groupId = \Illuminate\Support\Facades\DB::table('user_groups as g')
        ->join('user_group_settings as s', 's.user_group_id', '=', 'g.user_group_id')
        ->where('g.context_id', $contextId)
        ->where('g.role_id', $cfg['roleId'])
        ->where('s.setting_name', 'name')
        ->where('s.locale', 'en')
        ->where('s.setting_value', $cfg['match'])
        ->value('g.user_group_id');
    if (!$groupId && !empty($cfg['create'])) {
        // Papel novo de contribuidor (ex.: Orientador(a)): mesmo tipo do papel Autor, sem autocadastro
        if ($dryRun) {
            say("  papel criado: {$cfg['match']} (simulado)");
            continue;
        }
        // Papéis de editor (ex.: Editor-chefe) pedem permitSettings/permitMetadataEdit/masthead no JSON
        $groupId = \Illuminate\Support\Facades\DB::table('user_groups')->insertGetId([
            'context_id' => $contextId, 'role_id' => $cfg['roleId'], 'is_default' => 0, 'show_title' => 1,
            'permit_self_registration' => 0,
            'permit_metadata_edit' => (int) !empty($cfg['permitMetadataEdit']),
            'permit_settings' => (int) !empty($cfg['permitSettings']),
            'masthead' => (int) !empty($cfg['masthead']),
        ]);
        say("  papel criado: {$cfg['match']}");
    }
    if (!$groupId) {
        say("  (aviso) papel não encontrado: {$cfg['match']}");
        continue;
    }
    foreach (['name', 'abbrev'] as $setting) {
        foreach ($cfg[$setting] ?? [] as $locale => $value) {
            $dryRun || \Illuminate\Support\Facades\DB::table('user_group_settings')->updateOrInsert(
                ['user_group_id' => $groupId, 'locale' => $locale, 'setting_name' => $setting],
                ['setting_value' => $value]
            );
        }
    }
    $stages = \Illuminate\Support\Facades\DB::table('user_group_stage')->where('user_group_id', $groupId)->pluck('stage_id')->all();
    foreach (array_diff($cfg['stages'] ?? [], $stages) as $stageId) {
        $dryRun || \Illuminate\Support\Facades\DB::table('user_group_stage')->insert(['context_id' => $contextId, 'user_group_id' => $groupId, 'stage_id' => $stageId]);
    }
    say("  papel: {$cfg['match']}" . (isset($cfg['name']['pt_BR']) ? " → {$cfg['name']['pt_BR']}" : '') . (!empty($cfg['stages']) ? ' (etapas ' . implode(',', $cfg['stages']) . ')' : ''));
}

// Grupos de contribuidores usados pelo plugin de citação (Como citar): só autores, organizadores,
// autores de capítulo e tradutores entram na citação; orientação e banca ficam de fora
$groupIdByName = function (string $name) use ($contextId) {
    return \Illuminate\Support\Facades\DB::table('user_groups as g')
        ->join('user_group_settings as s', 's.user_group_id', '=', 'g.user_group_id')
        ->where('g.context_id', $contextId)->where('s.setting_name', 'name')->where('s.locale', 'en')->where('s.setting_value', $name)
        ->value('g.user_group_id');
};
foreach ($config['citationGroups'] ?? [] as $setting => $names) {
    $ids = array_values(array_filter(array_map($groupIdByName, $names)));
    $dryRun || $pluginSettings->updateSetting($contextId, 'citationstylelanguageplugin', $setting, $ids, 'object');
}
if (!empty($config['citationGroups'])) {
    say('  citação: grupos de autoria configurados (' . implode(', ', array_keys($config['citationGroups'])) . ')');
}

// ---------------------------------------------------------------- equipe editorial (expediente)
// Papéis com "mastheadFirst" no JSON abrem a página Equipe Editorial, na ordem do JSON.
// Em "masthead", cada pessoa aparece com o papel próprio (ex.: Editor-chefe), some dos papéis
// genéricos ("hideOnMastheadAs"; o acesso continua) e tem o perfil gravado direto (o formulário
// de perfil do site falha em alguns campos). A saída mostra o perfil para conferência.
$mastheadFirst = array_values(array_filter(array_map(
    fn ($cfg) => !empty($cfg['mastheadFirst']) ? $groupIdByName($cfg['match']) : null,
    $config['userGroups'] ?? []
)));
if ($mastheadFirst && !$dryRun) {
    $allMasthead = \Illuminate\Support\Facades\DB::table('user_groups')->where('context_id', $contextId)->where('masthead', 1)
        ->orderBy('user_group_id')->pluck('user_group_id')->map(fn ($v) => (int) $v)->all();
    $context = $contextDao->getById($contextId);
    $context->setData('mastheadUserGroupIds', array_values(array_unique(array_merge(
        array_map('intval', $mastheadFirst), (array) $context->getData('mastheadUserGroupIds'), $allMasthead
    ))));
    $contextDao->updateObject($context);
}
foreach ($config['masthead'] ?? [] as $cfg) {
    $user = Repo::user()->getByEmail($cfg['email'], true);
    $userGroupId = $groupIdByName($cfg['userGroup']);
    if (!$user || !$userGroupId) {
        say('  ! equipe editorial: ' . (!$user ? "usuário {$cfg['email']} não encontrado" : "papel '{$cfg['userGroup']}' não existe"));
        continue;
    }
    $profile = [];
    foreach ($cfg['profile'] ?? [] as $field => $value) {
        $profile[$field] = $isLocalizedMap($value) ? $localized($value, $user->getData($field)) : $value;
    }
    if ($profile && !$dryRun) {
        Repo::user()->edit($user, $profile);
        $user = Repo::user()->get($user->getId(), true);
    }
    $show = fn ($field) => ($v = $user->getData($field)) === null || $v === '' || $v === [] ? '(vazio)' : (is_array($v) ? implode(' | ', array_map(fn ($l, $t) => "{$l}: {$t}", array_keys($v), $v)) : $v);
    say("  equipe editorial: {$user->getFullName()} como {$cfg['userGroup']}");
    say("    nome: {$show('givenName')} / sobrenome: {$show('familyName')}");
    say("    e-mail: {$user->getEmail()} / usuário: {$user->getUsername()} / país: {$show('country')}");
    say("    afiliação: {$show('affiliation')}");
    say("    página pessoal: {$show('url')}");
    say('    ORCID: ' . ($user->getData('orcid') ? $user->getData('orcid') . ($user->getData('orcidIsVerified') ? ' (verificado)' : ' (NÃO verificado)') : '(vazio)'));
    if (!$dryRun) {
        if (Repo::userGroup()->userInGroup($user->getId(), $userGroupId)) {
            Repo::userGroup()->updateActiveUserUserGroupMasthead($user->getId(), $userGroupId, true);
        } else {
            Repo::userGroup()->assignUserToGroup($user->getId(), $userGroupId, \PKP\core\Core::getCurrentDate(), null, true);
        }
        foreach ($cfg['hideOnMastheadAs'] ?? [] as $other) {
            ($otherId = $groupIdByName($other)) && Repo::userGroup()->updateActiveUserUserGroupMasthead($user->getId(), $otherId, false);
        }
    }
}

// ---------------------------------------------------------------- páginas (plugin Static Pages)
// Conteúdo em content/<editora>/pages/<caminho>.<idioma>.html; {$baseUrl} vira o base_url do
// config.inc.php (local ou produção). Endereço público: <base_url>/<editora>/<caminho>.
$baseUrl = rtrim(\PKP\config\Config::getVar('general', 'base_url'), '/');
$pageUrls = [];
if (!empty($config['staticPages'])) {
    \PKP\plugins\PluginRegistry::loadCategory('generic', true, $contextId);
    $staticPagesDao = DAORegistry::getDAO('StaticPagesDAO');
    foreach ($config['staticPages'] as $cfg) {
        $title = $localized($cfg['title'], []);
        $content = [];
        foreach ($locales as $locale) {
            $file = $baseDir . '/' . str_replace('{locale}', $locale, $cfg['file']);
            if (is_file($file)) {
                $content[$locale] = str_replace('{$baseUrl}', $baseUrl, trim(file_get_contents($file)));
            }
        }
        $page = $staticPagesDao->getByPath($contextId, $cfg['path']);
        $isNew = !$page;
        if (!$dryRun) {
            $page ??= $staticPagesDao->newDataObject();
            $page->setContextId($contextId);
            $page->setPath($cfg['path']);
            $page->setTitle($title, null);
            $page->setContent($content, null);
            $isNew ? $staticPagesDao->insertObject($page) : $staticPagesDao->updateObject($page);
        }
        $pageUrls[$cfg['path']] = "{$baseUrl}/{$config['path']}/{$cfg['path']}";
        say('  página ' . ($isNew ? 'criada' : 'atualizada') . ": /{$config['path']}/{$cfg['path']} (" . implode(', ', array_keys($content)) . ')');
    }
}

// ---------------------------------------------------------------- menu principal
// Itens próprios (links) marcados com path "csvet-<chave>" para serem reconhecidos nas próximas
// execuções. "under": "about" põe o item no submenu Sobre; "children" cria um submenu.
$primaryMenuId = \Illuminate\Support\Facades\DB::table('navigation_menus')->where('context_id', $contextId)->where('area_name', 'primary')->value('navigation_menu_id');
$menuItem = function (array $item) use ($contextId, $localized, $pageUrls, $baseUrl, $config, $dryRun) {
    $url = isset($item['page']) ? ($pageUrls[$item['page']] ?? "{$baseUrl}/{$config['path']}/{$item['page']}") : $baseUrl . $item['url'];
    $marker = 'csvet-' . $item['key'];
    $id = \Illuminate\Support\Facades\DB::table('navigation_menu_items')->where('context_id', $contextId)->where('path', $marker)->value('navigation_menu_item_id');
    if ($dryRun) {
        return $id ?: 0;
    }
    $id ??= \Illuminate\Support\Facades\DB::table('navigation_menu_items')->insertGetId(['context_id' => $contextId, 'path' => $marker, 'type' => 'NMI_TYPE_REMOTE_URL']);
    foreach ($localized($item['title'], []) as $locale => $title) {
        foreach (['title' => $title, 'remoteUrl' => $url] as $name => $value) {
            \Illuminate\Support\Facades\DB::table('navigation_menu_item_settings')->updateOrInsert(
                ['navigation_menu_item_id' => $id, 'locale' => $locale, 'setting_name' => $name],
                ['setting_value' => $value, 'setting_type' => 'string']
            );
        }
    }
    return $id;
};
$assign = function (int $itemId, ?int $parentId, int $seq) use ($primaryMenuId, $dryRun) {
    if ($dryRun || !$itemId) {
        return;
    }
    \Illuminate\Support\Facades\DB::table('navigation_menu_item_assignments')->updateOrInsert(
        ['navigation_menu_id' => $primaryMenuId, 'navigation_menu_item_id' => $itemId],
        ['parent_id' => $parentId, 'seq' => $seq]
    );
};
if ($primaryMenuId && !empty($config['menu'])) {
    $aboutId = \Illuminate\Support\Facades\DB::table('navigation_menu_item_assignments as a')
        ->join('navigation_menu_items as i', 'i.navigation_menu_item_id', '=', 'a.navigation_menu_item_id')
        ->where('a.navigation_menu_id', $primaryMenuId)->whereNull('a.parent_id')->where('i.type', 'NMI_TYPE_ABOUT')
        ->value('i.navigation_menu_item_id');
    foreach ($config['menu'] as $item) {
        $id = $menuItem($item);
        if (($item['under'] ?? null) === 'about') {
            $assign($id, $aboutId, $item['seq'] ?? 10);
        } else {
            $assign($id, null, $item['seq'] ?? 1);
            foreach ($item['children'] ?? [] as $i => $child) {
                $assign($menuItem($child), $id, $i);
            }
        }
        say("  menu: {$item['key']}" . (!empty($item['children']) ? ' (' . count($item['children']) . ' subitens)' : '') . (($item['under'] ?? null) === 'about' ? ' (em Sobre)' : ''));
    }
}

// O OMP guarda os menus em cache por 24 h
if ($primaryMenuId && !$dryRun) {
    \Illuminate\Support\Facades\Cache::forget("navigationMenu-{$primaryMenuId}");
}

// ---------------------------------------------------------------- menus
// Itens padrão (com titleLocaleKey) devem ser traduzidos pela chave. Se a criação da
// editora gravou o título num idioma só, ele aparece assim em todos os idiomas:
// remove só os títulos idênticos ao padrão (títulos personalizados ficam).
$items = \Illuminate\Support\Facades\DB::table('navigation_menu_items as i')
    ->join('navigation_menu_item_settings as k', function ($join) {
        $join->on('k.navigation_menu_item_id', '=', 'i.navigation_menu_item_id')->where('k.setting_name', '=', 'titleLocaleKey');
    })
    ->where('i.context_id', $contextId)
    ->select('i.navigation_menu_item_id as id', 'k.setting_value as localeKey')
    ->get();
$resetTitles = 0;
foreach ($items as $item) {
    if (!str_contains($item->localeKey, '.')) {
        continue;
    }
    $defaults = array_unique(array_map(fn ($l) => __($item->localeKey, [], $l), $locales));
    $query = \Illuminate\Support\Facades\DB::table('navigation_menu_item_settings')
        ->where('navigation_menu_item_id', $item->id)
        ->where('setting_name', 'title')
        ->where(fn ($q) => $q->whereIn('setting_value', $defaults)->orWhere('setting_value', '')->orWhereNull('setting_value'));
    $resetTitles += $dryRun ? $query->count() : $query->delete();
}
say("  menu: {$resetTitles} título(s) padrão liberados para tradução");

// ---------------------------------------------------------------- caches
if (!$dryRun) {
    foreach (array_merge(glob($root . '/cache/t_compile/*') ?: [], glob($root . '/cache/*.css') ?: [], glob($root . '/cache/_db/*') ?: []) as $file) {
        is_file($file) && @unlink($file);
    }
}
say('==> Pronto.' . ($dryRun ? ' (nada foi gravado)' : ' Caches limpos.'));
