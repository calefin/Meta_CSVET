<?php

/**
 * Aplica a configuração de uma revista (textos, políticas, imagens, seções,
 * categorias, plugins, menu lateral, páginas personalizadas (ex.: chamada de artigos),
 * formulários de avaliação, equipe editorial, modelos de e-mail, tipos de arquivo
 * do envio, ajustes de plugins e título do portal) a partir de
 * content/<revista>/journal.json.
 *
 * Uso (na raiz do OJS, local ou no servidor):
 *   php scripts/configure-journal.php                      # content/nvas/journal.json
 *   php scripts/configure-journal.php content/nvas/journal.json
 *   php scripts/configure-journal.php --dry-run            # só mostra o que faria
 *   php scripts/configure-journal.php --verbose            # lista também o que já estava certo
 *
 * Idempotente: pode rodar quantas vezes quiser. Cada item é comparado com o que já está
 * no OJS e só é gravado (e mostrado) quando muda; no fim vem o total do que ficou como
 * estava. Não apaga nada: campos vazios no JSON não alteram o OJS; seções e categorias
 * existentes são atualizadas (casadas pela sigla/caminho, ou pelo antigo em "replaces"),
 * as demais são criadas; idiomas que a revista não usa são ignorados.
 */

$root = dirname(__DIR__);
chdir($root);
require $root . '/tools/bootstrap.php';

use APP\core\Application;
use APP\facades\Repo;
use APP\file\PublicFileManager;
use Illuminate\Support\Facades\DB;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;

$args = array_slice($argv, 1);
$dryRun = in_array('--dry-run', $args, true);
$verbose = in_array('--verbose', $args, true) || in_array('-v', $args, true);
$args = array_values(array_filter($args, fn ($a) => !in_array($a, ['--dry-run', '--verbose', '-v'], true)));
$configFile = $args[0] ?? 'content/nvas/journal.json';
$baseDir = dirname($root . '/' . $configFile);

$config = json_decode(file_get_contents($root . '/' . $configFile), true, 512, JSON_THROW_ON_ERROR);

function say(string $msg): void
{
    echo $msg, "\n";
}

// Relatório: só o que muda aparece; o que já estava certo é contado (e listado com --verbose)
$changes = 0;
$unchanged = 0;
$changed = function (string $msg) use (&$changes) {
    $changes++;
    say($msg);
};
$same = function (string $msg) use (&$unchanged, $verbose) {
    $unchanged++;
    if ($verbose) {
        say("  = {$msg}");
    }
};

/** Forma comparável: textos sem espaços nas pontas e com \n, números e booleanos como texto, mapas ordenados */
$norm = function ($value) use (&$norm) {
    if (is_array($value)) {
        if ($value === []) {
            return '';
        }
        $isList = array_keys($value) === range(0, count($value) - 1);
        $out = [];
        foreach ($value as $k => $v) {
            $out[$k] = $norm($v);
        }
        $isList || ksort($out);
        return $out;
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    if ($value === null) {
        return '';
    }
    return str_replace("\r\n", "\n", trim((string) $value));
};
$equal = fn ($a, $b) => $norm($a) === $norm($b);
/** Campos de $wanted cujo valor difere do de $current(campo) */
$diff = function (array $wanted, callable $current) use ($equal) {
    return array_keys(array_filter($wanted, fn ($v, $k) => !$equal($v, $current($k)), ARRAY_FILTER_USE_BOTH));
};

$contextDao = Application::getContextDAO();
$context = $contextDao->getByPath($config['path']);
// Revista renomeada: encontra pelo caminho antigo e troca o caminho (URL) para o novo
foreach ($config['previousPaths'] ?? [] as $oldPath) {
    if ($context) {
        break;
    }
    $old = $contextDao->getByPath($oldPath);
    if ($old) {
        if (!$dryRun) {
            DB::table('journals')->where('journal_id', $old->getId())->update(['path' => $config['path']]);
        }
        $changed("  caminho da revista: /{$oldPath} → /{$config['path']}" . ($dryRun ? ' (simulado)' : ''));
        $context = $dryRun ? $old : $contextDao->getByPath($config['path']);
    }
}
if (!$context) {
    fwrite(STDERR, "Revista '{$config['path']}' não encontrada. Crie-a no painel primeiro.\n");
    exit(1);
}
$contextId = $context->getId();
$contextDirty = false;

// Idiomas de formulário/submissão/metadados: acrescenta os pedidos no JSON (sem remover nenhum),
// limitados aos idiomas já ativos na revista. Sem isso os textos daquele idioma são ignorados.
$uiLocales = $context->getSupportedLocales() ?: [$context->getPrimaryLocale()];
foreach (['supportedFormLocales', 'supportedSubmissionLocales', 'supportedSubmissionMetadataLocales'] as $setting) {
    $wanted = array_intersect($config['locales'][$setting] ?? [], $uiLocales);
    $current = $context->getData($setting) ?: [];
    $merged = array_values(array_unique(array_merge($current, $wanted)));
    if ($merged !== $current) {
        $context->setData($setting, $merged);
        $contextDirty = true;
        $changed("  {$setting}: " . implode(', ', $merged));
    }
}
$locales = $context->getData('supportedFormLocales') ?: [$context->getPrimaryLocale()];
say("==> Revista {$config['path']} (id {$contextId}), idiomas: " . implode(', ', $locales) . ($dryRun ? '  [DRY-RUN]' : ''));

/** Mantém só os idiomas da revista e funde com o que já existe. */
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
$settingsChanged = [];
$setContext = function (string $name, $value, string $label) use ($context, $equal, &$settingsChanged, &$contextDirty, $same) {
    if ($equal($value, $context->getData($name))) {
        $same("configuração {$label}");
        return;
    }
    $context->setData($name, $value);
    $contextDirty = true;
    $settingsChanged[] = $label;
};
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
    $setContext($name, $value, $name);
}

foreach ($config['files'] ?? [] as $name => $pattern) {
    $values = [];
    foreach ($locales as $locale) {
        $file = $baseDir . '/' . str_replace('{locale}', $locale, $pattern);
        if (is_file($file)) {
            $values[$locale] = trim(file_get_contents($file));
        }
    }
    if ($values) {
        $setContext($name, $localized($values, $context->getData($name)), $name . ' (' . implode(', ', array_keys($values)) . ')');
    }
}

// ---------------------------------------------------------------- imagens
// Nome com a impressão digital do arquivo: imagem nova = URL nova, então navegadores,
// redes sociais e buscadores não ficam presos à versão antiga em cache. Mesma impressão
// digital e mesmo texto alternativo = nada a fazer (o arquivo nem é copiado de novo).
$publicFileManager = new PublicFileManager();
$placeImage = function (string $name, array $image, array $current, array $imageLocales, string $dir, ?string $altFallbackLocale)
    use ($baseDir, $dryRun) {
    $source = $baseDir . '/' . $image['file'];
    [$width, $height] = getimagesize($source);
    $extension = '.' . strtolower(pathinfo($source, PATHINFO_EXTENSION));
    $hash = substr(md5_file($source), 0, 8);
    $updated = false;
    foreach ($imageLocales as $locale) {
        $uploadName = "{$name}_{$locale}-{$hash}{$extension}";
        $alt = $image['altText'][$locale] ?? ($altFallbackLocale ? ($image['altText'][$altFallbackLocale] ?? '') : '');
        $previous = $current[$locale]['uploadName'] ?? null;
        if ($previous === $uploadName && ($current[$locale]['altText'] ?? '') === $alt && is_file("{$dir}/{$uploadName}")) {
            continue;
        }
        if (!$dryRun) {
            is_dir($dir) || mkdir($dir, 0755, true);
            copy($source, "{$dir}/{$uploadName}");
            // Remove a versão anterior gerada por este script (mesmo campo e idioma)
            if ($previous && $previous !== $uploadName && preg_match('/^' . preg_quote("{$name}_{$locale}", '/') . '(-[0-9a-f]{8})?\.\w+$/', $previous)) {
                @unlink("{$dir}/{$previous}");
            }
        }
        $current[$locale] = [
            'name' => basename($source),
            'uploadName' => $uploadName,
            'width' => $width,
            'height' => $height,
            'dateUploaded' => Core::getCurrentDate(),
            'altText' => $alt,
        ];
        $updated = true;
    }
    return $updated ? $current : null;
};
$publicDir = $root . '/' . $publicFileManager->getContextFilesPath($contextId);
foreach ($config['images'] ?? [] as $name => $image) {
    if (!is_file($baseDir . '/' . $image['file'])) {
        say("  (aviso) imagem não encontrada: {$image['file']}");
        continue;
    }
    $new = $placeImage($name, $image, $context->getData($name) ?: [], $locales, $publicDir, null);
    if ($new === null) {
        $same("imagem {$name}");
        continue;
    }
    $context->setData($name, $new);
    $contextDirty = true;
    $settingsChanged[] = "{$name} (imagem)";
}

// ---------------------------------------------------------------- plugins
// Plugins próprios (fora da distribuição do OJS): registra a versão, como o
// lib/pkp/tools/installPluginVersion.php, para o OJS passar a carregá-los
$versionDao = DAORegistry::getDAO('VersionDAO');
foreach ($config['plugins']['install'] ?? [] as $pluginDir) {
    $descriptor = "{$root}/plugins/{$pluginDir}/version.xml";
    if (!is_file($descriptor)) {
        say("  ! plugin não encontrado: plugins/{$pluginDir}");
        continue;
    }
    $version = \PKP\site\VersionCheck::parseVersionXML($descriptor)['version'];
    $installed = $versionDao->getCurrentVersion($version->getProductType(), $version->getProduct());
    if (!$installed || $installed->compare($version) < 0) {
        $dryRun || $versionDao->insertVersion($version, true);
        $changed("  plugin registrado: {$pluginDir} " . $version->getVersionString());
    } else {
        $same("plugin {$pluginDir}");
    }
}

$pluginSettings = DAORegistry::getDAO('PluginSettingsDAO');
$setPlugin = function (?int $ctx, string $plugin, string $name, $value, string $type, string $label) use ($pluginSettings, $equal, $dryRun, $changed, $same) {
    if ($equal($value, $pluginSettings->getSetting($ctx, $plugin, $name))) {
        $same($label);
        return;
    }
    $dryRun || $pluginSettings->updateSetting($ctx, $plugin, $name, $value, $type);
    $changed("  {$label}");
};
foreach ($config['plugins']['enable'] ?? [] as $plugin) {
    $setPlugin($contextId, $plugin, 'enabled', true, 'bool', "plugin habilitado: {$plugin}");
}
foreach ($config['plugins']['disable'] ?? [] as $plugin) {
    $setPlugin($contextId, $plugin, 'enabled', false, 'bool', "plugin desabilitado: {$plugin}");
}
foreach ($config['plugins']['enableSite'] ?? [] as $plugin) {
    $setPlugin(PKPApplication::SITE_CONTEXT_ID, $plugin, 'enabled', true, 'bool', "plugin habilitado no portal: {$plugin}");
}
// Ajustes dos plugins (ex.: e-mails extras que recebem a triagem de cada nova submissão)
foreach ($config['plugins']['settings'] ?? [] as $plugin => $settings) {
    foreach ($settings as $name => $value) {
        if (str_starts_with($name, '_')) {
            continue;
        }
        $type = is_bool($value) ? 'bool' : (is_int($value) ? 'int' : (is_array($value) ? 'object' : 'string'));
        $setPlugin($contextId, $plugin, $name, $value, $type, "ajuste do plugin {$plugin}: {$name}");
    }
}

if ($contextDirty) {
    $dryRun || $contextDao->updateObject($context);
}
if ($settingsChanged) {
    $changes += count($settingsChanged);
    say('  configurações: ' . implode(', ', $settingsChanged));
}

// ---------------------------------------------------------------- portal (site)
if (!empty($config['site'])) {
    $siteDao = DAORegistry::getDAO('SiteDAO');
    $site = $siteDao->getSite();
    $siteLocales = $site->getSupportedLocales() ?: [$site->getPrimaryLocale()];
    $siteChanged = [];
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
        if ($equal($value, $site->getData($name))) {
            $same("portal {$name}");
            continue;
        }
        $site->setData($name, $value);
        $siteChanged[] = $name;
    }
    // Imagens do portal (logo do cabeçalho em public/site/), com o mesmo esquema de nome por hash
    $siteDir = $root . '/' . $publicFileManager->getSiteFilesPath();
    foreach ($config['siteImages'] ?? [] as $name => $image) {
        if (!is_file($baseDir . '/' . $image['file'])) {
            say("  (aviso) imagem do portal não encontrada: {$image['file']}");
            continue;
        }
        $current = is_array($site->getData($name)) ? $site->getData($name) : [];
        $new = $placeImage($name, $image, $current, $siteLocales, $siteDir, 'en');
        if ($new === null) {
            $same("imagem do portal {$name}");
            continue;
        }
        $site->setData($name, $new);
        $siteChanged[] = "{$name} (imagem)";
    }
    if ($siteChanged) {
        $dryRun || $siteDao->updateObject($site);
        $changed('  portal: ' . implode(', ', $siteChanged));
    }
}

// ---------------------------------------------------------------- seções
$sections = Repo::section()->getCollector()->filterByContextIds([$contextId])->getMany()->all();
$abbrevOf = fn ($section) => strtoupper((string) ($section->getData('abbrev', 'en') ?? $section->getLocalizedData('abbrev')));
foreach ($config['sections'] ?? [] as $i => $cfg) {
    $params = [
        'title' => $localized($cfg['title'], []),
        'abbrev' => $localized(array_fill_keys(array_keys($cfg['title']), $cfg['abbrev']), []),
        'sequence' => $i + 1,
        'metaReviewed' => $cfg['metaReviewed'] ?? true,
        'metaIndexed' => $cfg['metaIndexed'] ?? true,
        'abstractsNotRequired' => $cfg['abstractsNotRequired'] ?? false,
        'editorRestricted' => $cfg['editorRestricted'] ?? false,
        'isInactive' => $cfg['isInactive'] ?? false,
    ];
    if (isset($cfg['wordCount'])) {
        $params['wordCount'] = $cfg['wordCount'];
    }
    // Política da seção: aparece para o autor ao escolher a seção no formulário de envio
    if (isset($cfg['policy'])) {
        $params['policy'] = $localized($cfg['policy'], []);
    }

    $existing = null;
    foreach ($sections as $section) {
        if ($abbrevOf($section) === strtoupper($cfg['abbrev'])) {
            $existing = $section;
            break;
        }
    }
    foreach ((array) ($cfg['replaces'] ?? []) as $oldAbbrev) {
        foreach ($sections as $section) {
            if (!$existing && $abbrevOf($section) === strtoupper($oldAbbrev)) {
                $existing = $section;
            }
        }
    }

    if ($existing) {
        $params['title'] = $localized($cfg['title'], $existing->getData('title'));
        $params['abbrev'] = $localized(array_fill_keys(array_keys($cfg['title']), $cfg['abbrev']), $existing->getData('abbrev'));
        if (isset($cfg['policy'])) {
            $params['policy'] = $localized($cfg['policy'], $existing->getData('policy'));
        }
        $fields = $diff($params, fn ($k) => $existing->getData($k));
        if (!$fields) {
            $same("seção {$cfg['abbrev']}");
            continue;
        }
        $dryRun || Repo::section()->edit($existing, $params);
        $changed("  seção atualizada: {$cfg['abbrev']} " . $cfg['title']['en'] . ' (' . implode(', ', $fields) . ')');
    } else {
        if (!$dryRun) {
            $section = Repo::section()->newDataObject(array_merge($params, ['contextId' => $contextId]));
            Repo::section()->add($section);
        }
        $changed("  seção criada: {$cfg['abbrev']} " . $cfg['title']['en']);
    }
}

// Seções que saíram da estrutura: desativadas (não aparecem para novas submissões), nunca apagadas
if (!empty($config['deactivateSections'])) {
    $sections = Repo::section()->getCollector()->filterByContextIds([$contextId])->getMany()->all();
    $keep = array_map('strtoupper', array_column($config['sections'] ?? [], 'abbrev'));
    foreach ($sections as $section) {
        $abbrev = $abbrevOf($section);
        if (in_array($abbrev, array_map('strtoupper', $config['deactivateSections']), true) && !in_array($abbrev, $keep, true)) {
            if ($section->getData('isInactive')) {
                $same("seção desativada {$abbrev}");
                continue;
            }
            $dryRun || Repo::section()->edit($section, ['isInactive' => true, 'sequence' => 100]);
            $changed("  seção desativada: {$abbrev}");
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
    // "replaces": renomeia o path de uma categoria antiga, mantendo os artigos vinculados
    $renamedFrom = null;
    foreach ((array) ($cfg['replaces'] ?? []) as $oldPath) {
        foreach ($categories as $category) {
            if (!$existing && $category->getData('path') === $oldPath) {
                $existing = $category;
                $renamedFrom = $oldPath;
            }
        }
    }
    if ($existing) {
        $params = [
            'path' => $cfg['path'],
            'title' => $localized($cfg['title'], $existing->getData('title')),
            'sequence' => $i + 1,
        ];
        $fields = $diff($params, fn ($k) => $existing->getData($k));
        if (!$fields) {
            $same("categoria {$cfg['path']}");
            continue;
        }
        $dryRun || Repo::category()->edit($existing, $params);
        $changed("  categoria atualizada: {$cfg['path']}" . ($renamedFrom ? " (antes {$renamedFrom})" : '') . ' (' . implode(', ', $fields) . ')');
    } else {
        if (!$dryRun) {
            $category = Repo::category()->newDataObject([
                'contextId' => $contextId,
                'path' => $cfg['path'],
                'title' => $localized($cfg['title'], []),
                'sequence' => $i + 1,
                'parentId' => null,
            ]);
            Repo::category()->add($category);
        }
        $changed("  categoria criada: {$cfg['path']}");
    }
}

// ---------------------------------------------------------------- tipos de arquivo (componentes)
// O que o autor escolhe para cada arquivo no envio ("Folha de rosto (com os autores)"...).
// Casados pela chave (entry_key): os existentes têm nome, ordem e categoria atualizados,
// os novos são criados. "disable": tipos padrão do OJS que só confundem o autor; ficam
// desativados (arquivos já enviados com eles continuam como estão). Os arquivos exigidos
// em cada tipo de artigo ficam no plugin csvetScreening (rules.php), não aqui.
/** @var \PKP\submission\GenreDAO $genreDao */
$genreDao = DAORegistry::getDAO('GenreDAO');
foreach ($config['genres']['items'] ?? [] as $i => $cfg) {
    $genre = $genreDao->getByKey($cfg['key'], $contextId);
    $isNew = !$genre;
    $names = $localized($cfg['name'], []);
    $wanted = [
        'sequence' => $i + 1,
        'category' => $cfg['category'] ?? \PKP\submission\Genre::GENRE_CATEGORY_DOCUMENT,
        'dependent' => false,
        'supplementary' => !empty($cfg['supplementary']),
        'required' => !empty($cfg['required']),
        'enabled' => true,
    ];
    if (!$isNew) {
        $current = [
            'sequence' => $genre->getSequence(),
            'category' => $genre->getCategory(),
            'dependent' => $genre->getDependent(),
            'supplementary' => $genre->getSupplementary(),
            'required' => $genre->getRequired(),
            'enabled' => $genre->getEnabled(),
        ];
        $fields = $diff($wanted, fn ($k) => $current[$k]);
        foreach ($names as $locale => $name) {
            if (!$equal($name, $genre->getName($locale))) {
                $fields[] = "nome {$locale}";
            }
        }
        if (!$fields) {
            $same("tipo de arquivo {$cfg['key']}");
            continue;
        }
    } else {
        $genre = $genreDao->newDataObject();
        $genre->setKey($cfg['key']);
        $genre->setContextId($contextId);
    }
    $genre->setSequence($wanted['sequence']);
    $genre->setCategory($wanted['category']);
    $genre->setDependent($wanted['dependent']);
    $genre->setSupplementary($wanted['supplementary']);
    $genre->setRequired($wanted['required']);
    $genre->setEnabled(true);
    foreach ($names as $locale => $name) {
        $genre->setName($name, $locale);
    }
    if (!$dryRun) {
        $isNew ? $genreDao->insertObject($genre) : $genreDao->updateObject($genre);
    }
    $changed('  tipo de arquivo ' . ($isNew ? 'criado' : 'atualizado') . ": {$cfg['key']} ({$cfg['name']['pt_BR']})"
        . ($isNew ? '' : ' (' . implode(', ', $fields) . ')'));
}
foreach ($config['genres']['disable'] ?? [] as $key) {
    $genre = $genreDao->getByKey($key, $contextId);
    if ($genre && $genre->getEnabled()) {
        $genre->setEnabled(false);
        $genre->setRequired(false);
        $dryRun || $genreDao->updateObject($genre);
        $changed("  tipo de arquivo desativado: {$key}");
    } elseif ($genre) {
        $same("tipo de arquivo desativado {$key}");
    }
}

// ---------------------------------------------------------------- formulários de avaliação
// Casados pelo título em inglês. Formulário ainda sem avaliações: perguntas recriadas a
// partir do JSON quando mudam. Já usado em alguma avaliação: fica como está (mudar as
// perguntas embaralharia as respostas antigas); para mudar, crie um novo com outro título.
$reviewFormDao = DAORegistry::getDAO('ReviewFormDAO');
$reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO');
$elementTypes = [
    'text' => \PKP\reviewForm\ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_TEXT_FIELD,
    'textarea' => \PKP\reviewForm\ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_TEXTAREA,
    'checkboxes' => \PKP\reviewForm\ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_CHECKBOXES,
    'radio' => \PKP\reviewForm\ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_RADIO_BUTTONS,
    'dropdown' => \PKP\reviewForm\ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_DROP_DOWN_BOX,
];
$assocType = Application::getContextAssocType();
$existingForms = $reviewFormDao->getByAssocId($assocType, $contextId)->toArray();
foreach ($config['reviewForms'] ?? [] as $i => $cfg) {
    $reviewForm = null;
    foreach ($existingForms as $form) {
        if ($form->getTitle('en') === $cfg['title']['en']) {
            $reviewForm = $form;
            break;
        }
    }
    // O formulário e as perguntas como o JSON pede, na forma em que o OJS guarda
    $wantedForm = [
        'sequence' => $i + 1,
        'active' => 1,
        'title' => $localized($cfg['title'], []),
        'description' => $localized($cfg['description'] ?? [], []),
    ];
    $wantedElements = array_map(fn ($el) => [
        'type' => $elementTypes[$el['type']],
        'required' => !empty($el['required']) ? 1 : 0,
        'included' => !empty($el['includedForAuthors']) ? 1 : 0,
        'question' => $localized($el['question'], []),
        'description' => $localized($el['description'] ?? [], []),
        'responses' => isset($el['responses']) ? $localized($el['responses'], []) : null,
    ], $cfg['elements']);
    $isSame = false;
    if ($reviewForm) {
        $currentElements = array_map(fn ($e) => [
            'type' => $e->getElementType(),
            'required' => $e->getRequired() ? 1 : 0,
            'included' => $e->getIncluded() ? 1 : 0,
            'question' => $e->getQuestion(null),
            'description' => $e->getDescription(null),
            'responses' => $e->getPossibleResponses(null),
        ], $reviewFormElementDao->getByReviewFormId($reviewForm->getId())->toArray());
        $isSame = $equal($wantedElements, $currentElements) && $equal($wantedForm, [
            'sequence' => $reviewForm->getSequence(),
            'active' => $reviewForm->getActive() ? 1 : 0,
            'title' => $reviewForm->getTitle(null),
            'description' => $reviewForm->getDescription(null),
        ]);
    }
    if ($isSame) {
        $same("formulário de avaliação {$cfg['title']['en']}");
        continue;
    }
    if ($reviewForm && ($reviewForm->getCompleteCount() || $reviewForm->getIncompleteCount())) {
        say("  ! formulário de avaliação já usado, mudanças do JSON NÃO aplicadas: {$cfg['title']['en']} (para mudar, crie outro com outro título)");
        continue;
    }
    if ($dryRun) {
        $changed('  formulário de avaliação ' . ($reviewForm ? 'atualizado' : 'criado') . ": {$cfg['title']['en']} (" . count($cfg['elements']) . ' perguntas)');
        continue;
    }
    $isNew = !$reviewForm;
    if ($isNew) {
        $reviewForm = $reviewFormDao->newDataObject();
        $reviewForm->setAssocType($assocType);
        $reviewForm->setAssocId($contextId);
    }
    $reviewForm->setSequence($wantedForm['sequence']);
    $reviewForm->setActive(1);
    $reviewForm->setTitle($wantedForm['title'], null);
    $reviewForm->setDescription($wantedForm['description'], null);
    $isNew ? $reviewFormDao->insertObject($reviewForm) : $reviewFormDao->updateObject($reviewForm);

    $reviewFormElementDao->deleteByReviewFormId($reviewForm->getId());
    foreach ($wantedElements as $j => $el) {
        $element = $reviewFormElementDao->newDataObject();
        $element->setReviewFormId($reviewForm->getId());
        $element->setSequence($j + 1);
        $element->setElementType($el['type']);
        $element->setRequired($el['required']);
        $element->setIncluded($el['included']);
        $element->setQuestion($el['question'], null);
        $element->setDescription($el['description'], null);
        $element->setPossibleResponses($el['responses'], null);
        $reviewFormElementDao->insertObject($element);
    }
    $changed('  formulário de avaliação ' . ($isNew ? 'criado' : 'atualizado') . ": {$cfg['title']['en']} (" . count($cfg['elements']) . ' perguntas)');
}

// Formulários aposentados (já usados, o OJS não deixa mudar): inativos, fora do convite
$retiredTitles = $config['retiredReviewForms']['titles'] ?? [];
foreach ($reviewFormDao->getByAssocId($assocType, $contextId)->toArray() as $form) {
    if (in_array($form->getTitle('en'), $retiredTitles, true) && $form->getActive()) {
        $dryRun || $form->setActive(0);
        $dryRun || $reviewFormDao->updateObject($form);
        $changed('  formulário de avaliação desativado: ' . $form->getTitle('en'));
    }
}

// Regras de qualidade dos pareceres (plugin csvetReviewQuality), por id da pergunta:
// minWords (texto) e blocksAccept (respostas, em inglês, que não combinam com "Aceitar").
// Um formulário aposentado herda as regras do formulário cujo título começa com o dele
// ("NVAS standard review" → "NVAS standard review (v2)"), casando pelo texto da pergunta.
$reviewRules = [];
foreach ($reviewFormDao->getByAssocId($assocType, $contextId)->toArray() as $form) {
    $title = $form->getTitle('en');
    $source = null;
    foreach ($config['reviewForms'] ?? [] as $cfg) {
        if ($cfg['title']['en'] === $title) {
            $source = $cfg;
            break;
        }
    }
    if (!$source && in_array($title, $retiredTitles, true)) {
        foreach ($config['reviewForms'] ?? [] as $cfg) {
            if (str_starts_with($cfg['title']['en'], $title)) {
                $source = $cfg;
                break;
            }
        }
    }
    if (!$source) {
        continue;
    }
    foreach ($reviewFormElementDao->getByReviewFormId($form->getId())->toArray() as $element) {
        foreach ($source['elements'] as $el) {
            if ($el['question']['en'] !== $element->getQuestion('en')) {
                continue;
            }
            $rule = [];
            if (!empty($el['minWords'])) {
                $rule['minWords'] = (int) $el['minWords'];
            }
            if (!empty($el['blocksAccept'])) {
                $responses = $element->getPossibleResponses('en') ?: [];
                $rule['blocksAccept'] = array_values(array_keys(array_intersect(array_values($responses), $el['blocksAccept'])));
            }
            if ($rule) {
                $reviewRules[(string) $element->getId()] = $rule;
            }
            break;
        }
    }
}
if (in_array('generic/csvetReviewQuality', $config['plugins']['install'] ?? [], true)) {
    $setPlugin($contextId, 'csvetreviewqualityplugin', 'rules', $reviewRules, 'object', 'regras dos pareceres (' . count($reviewRules) . ' perguntas)');
}

// Formulário de avaliação padrão de cada seção (defaultForms do csvetReviewQuality; * = demais).
// O OJS já o deixa selecionado na janela "Adicionar avaliador"; o plugin cobre o resto.
$defaultForms = $config['plugins']['settings']['csvetreviewqualityplugin']['defaultForms'] ?? [];
if ($defaultForms) {
    $formIdByTitle = [];
    foreach ($reviewFormDao->getByAssocId($assocType, $contextId)->toArray() as $form) {
        if ($form->getActive()) {
            $formIdByTitle[$form->getTitle('en')] = (int) $form->getId();
        }
    }
    foreach (Repo::section()->getCollector()->filterByContextIds([$contextId])->getMany() as $section) {
        $abbrev = $abbrevOf($section);
        $title = $defaultForms[$abbrev] ?? $defaultForms['*'] ?? null;
        $wanted = $title ? ($formIdByTitle[$title] ?? null) : null;
        if (!$wanted) {
            $title && say("  ! formulário padrão não encontrado para a seção {$abbrev}: {$title}");
            continue;
        }
        if ((int) $section->getData('reviewFormId') === $wanted) {
            $same("formulário padrão da seção {$abbrev}");
            continue;
        }
        $dryRun || Repo::section()->edit($section, ['reviewFormId' => $wanted]);
        $changed("  formulário padrão da seção {$abbrev}: {$title}");
    }
}

// ---------------------------------------------------------------- papéis próprios
// Papéis com título próprio no expediente (ex.: Editor-chefe), casados pelo nome em
// inglês. Criados com as permissões do papel-base (roleId) e todas as etapas do fluxo
// liberadas; os existentes só têm o nome atualizado. "mastheadOrder" põe esses papéis
// no topo da página Equipe Editorial, na ordem do JSON.
$groupIdByName = fn (int $roleId, string $name) => DB::table('user_groups as g')
    ->join('user_group_settings as s', 's.user_group_id', '=', 'g.user_group_id')
    ->where('g.context_id', $contextId)
    ->where('g.role_id', $roleId)
    ->where('s.setting_name', 'name')
    ->where('s.locale', 'en')
    ->where('s.setting_value', $name)
    ->value('g.user_group_id');
$mastheadFirst = [];
foreach ($config['userGroups'] ?? [] as $cfg) {
    $id = $groupIdByName($cfg['roleId'], $cfg['name']['en']);
    $userGroup = $id ? \PKP\userGroup\UserGroup::find($id) : null;
    $label = "{$cfg['name']['en']} / " . ($cfg['name']['pt_BR'] ?? '');
    if ($userGroup) {
        $name = $localized($cfg['name'], $userGroup->name ?? []);
        $abbrev = $localized($cfg['abbrev'], $userGroup->abbrev ?? []);
        if ($equal($name, $userGroup->name ?? []) && $equal($abbrev, $userGroup->abbrev ?? [])) {
            $same("papel {$label}");
        } else {
            if (!$dryRun) {
                $userGroup->name = $name;
                $userGroup->abbrev = $abbrev;
                $userGroup->save();
            }
            $changed("  papel atualizado: {$label}");
        }
    } else {
        if (!$dryRun) {
            $userGroup = new \PKP\userGroup\UserGroup();
            $userGroup->contextId = $contextId;
            $userGroup->roleId = $cfg['roleId'];
            $userGroup->isDefault = false;
            $userGroup->showTitle = true;
            $userGroup->permitSelfRegistration = false;
            $userGroup->permitSettings = !empty($cfg['permitSettings']) && $cfg['roleId'] == \PKP\security\Role::ROLE_ID_MANAGER;
            $userGroup->permitMetadataEdit = true;
            $userGroup->recommendOnly = false;
            $userGroup->masthead = true;
            $userGroup->name = $localized($cfg['name'], []);
            $userGroup->abbrev = $localized($cfg['abbrev'], []);
            $userGroup->save();
            foreach (array_keys(\PKP\workflow\WorkflowStageDAO::getWorkflowStageTranslationKeys()) as $stageId) {
                \PKP\userGroup\relationships\UserGroupStage::create(['contextId' => $contextId, 'userGroupId' => $userGroup->id, 'stageId' => $stageId]);
            }
            $id = $userGroup->id;
        }
        $changed("  papel criado: {$label}");
    }
    $mastheadFirst[] = $id;
}
if ($mastheadFirst && !$dryRun) {
    $allMasthead = DB::table('user_groups')->where('context_id', $contextId)->where('masthead', 1)
        ->orderBy('user_group_id')->pluck('user_group_id')->map(fn ($v) => (int) $v)->all();
    $context = $contextDao->getById($contextId);
    $currentOrder = array_map('intval', (array) $context->getData('mastheadUserGroupIds'));
    $order = array_values(array_unique(array_merge(array_map('intval', $mastheadFirst), $currentOrder, $allMasthead)));
    if ($order !== $currentOrder) {
        $context->setData('mastheadUserGroupIds', $order);
        $contextDao->updateObject($context);
        $changed('  ordem dos papéis no expediente atualizada');
    }
}

// ---------------------------------------------------------------- equipe editorial (expediente)
// Garante na página Equipe Editorial quem já está na revista mas não foi convidado por
// scripts/invite-editors.php (os convidados entram sozinhos ao aceitar o convite).
/** Está no papel, ativo, com a marca de aparecer no expediente igual a $masthead? */
$inMasthead = fn (int $userId, int $userGroupId) => DB::table('user_user_groups')
    ->where('user_id', $userId)->where('user_group_id', $userGroupId)
    ->where(fn ($q) => $q->whereNull('date_end')->orWhere('date_end', '>', Core::getCurrentDate()))
    ->value('masthead');
foreach ($config['masthead'] ?? [] as $cfg) {
    $user = Repo::user()->getByEmail($cfg['email'], true);
    $userGroupId = $groupIdByName($cfg['userGroup']['roleId'], $cfg['userGroup']['name']);
    if (!$user || !$userGroupId) {
        say("  ! equipe editorial: " . (!$user ? "usuário {$cfg['email']} não encontrado" : "papel '{$cfg['userGroup']['name']}' não existe"));
        continue;
    }
    $what = [];
    // Perfil exibido no expediente: grava os campos do JSON (o formulário de perfil do
    // site falha em alguns campos)
    $profile = [];
    foreach ($cfg['profile'] ?? [] as $field => $value) {
        $profile[$field] = $isLocalizedMap($value) ? $localized($value, $user->getData($field)) : $value;
    }
    $profileFields = $diff($profile, fn ($k) => $user->getData($k));
    if ($profileFields) {
        if (!$dryRun) {
            Repo::user()->edit($user, array_intersect_key($profile, array_flip($profileFields)));
            $user = Repo::user()->get($user->getId(), true);
        }
        $what[] = 'perfil (' . implode(', ', $profileFields) . ')';
    }
    $membership = $inMasthead($user->getId(), $userGroupId);
    if ((int) $membership !== 1) {
        if (!$dryRun) {
            if (Repo::userGroup()->userInGroup($user->getId(), $userGroupId)) {
                Repo::userGroup()->updateActiveUserUserGroupMasthead($user->getId(), $userGroupId, true);
            } else {
                Repo::userGroup()->assignUserToGroup($user->getId(), $userGroupId, Core::getCurrentDate(), null, true);
            }
        }
        $what[] = "no expediente como {$cfg['userGroup']['name']}";
    }
    // Some do expediente nos papéis genéricos (o acesso continua): aparece só com o título próprio
    foreach ($cfg['hideOnMastheadAs'] ?? [] as $other) {
        $otherId = $groupIdByName($other['roleId'], $other['name']);
        if ($otherId && (int) $inMasthead($user->getId(), $otherId) === 1) {
            $dryRun || Repo::userGroup()->updateActiveUserUserGroupMasthead($user->getId(), $otherId, false);
            $what[] = "fora do expediente como {$other['name']}";
        }
    }
    if (!$what) {
        $same("equipe editorial: {$user->getFullName()}");
        continue;
    }
    $changed("  equipe editorial: {$user->getFullName()}: " . implode('; ', $what));
    // Mostra o perfil para conferência só quando ele mudou
    if ($profileFields) {
        $show = fn ($field) => ($v = $user->getData($field)) === null || $v === '' || $v === [] ? '(vazio)' : (is_array($v) ? implode(' | ', array_map(fn ($l, $t) => "{$l}: {$t}", array_keys($v), $v)) : $v);
        say("    nome: {$show('givenName')} / sobrenome: {$show('familyName')}");
        say("    e-mail: {$user->getEmail()} / usuário: {$user->getUsername()} / país: {$show('country')}");
        say("    afiliação: {$show('affiliation')}");
        say("    página pessoal: {$show('url')}");
        say('    ORCID: ' . ($user->getData('orcid') ? $user->getData('orcid') . ($user->getData('orcidIsVerified') ? ' (verificado)' : ' (NÃO verificado)') : '(vazio)'));
    }
}

// ---------------------------------------------------------------- páginas personalizadas
// Item de menu do tipo "Página personalizada" (NMI_TYPE_CUSTOM): o OJS serve o conteúdo
// em /<revista>/<path>. Título e conteúdo vêm do JSON e dos arquivos HTML por idioma
// (edite os arquivos e rode o script; salvar pelo painel passa pelo TinyMCE, que tira
// <script> e <svg>). "menu": área do menu ("primary") e "first" para abrir a lista.
$nmiDao = DAORegistry::getDAO('NavigationMenuItemDAO');
$menuDao = DAORegistry::getDAO('NavigationMenuDAO');
$assignmentDao = DAORegistry::getDAO('NavigationMenuItemAssignmentDAO');
foreach ($config['pages'] ?? [] as $pageCfg) {
    $content = [];
    foreach ($locales as $locale) {
        $file = $baseDir . '/' . str_replace('{locale}', $locale, $pageCfg['file']);
        if (is_file($file)) {
            $content[$locale] = trim(file_get_contents($file));
        }
    }
    $title = array_intersect_key($pageCfg['title'], array_flip($locales));
    $item = $nmiDao->getByPath($contextId, $pageCfg['path']);
    $isNew = !$item;
    $label = "página /{$config['path']}/{$pageCfg['path']}";
    if ($isNew) {
        $item = $nmiDao->newDataObject();
        $item->setPath($pageCfg['path']);
        $item->setContextId($contextId);
        $item->setType(\PKP\navigationMenu\NavigationMenuItem::NMI_TYPE_CUSTOM);
    }
    $fields = $isNew ? [] : $diff(['título' => $title, 'conteúdo' => $content],
        fn ($k) => $k === 'título' ? $item->getTitle(null) : $item->getContent(null));
    if ($isNew || $fields) {
        $item->setTitle($title, null);
        $item->setContent($content, null);
        if (!$dryRun) {
            $isNew ? $nmiDao->insertObject($item) : $nmiDao->updateObject($item);
        }
        $changed("  {$label}: " . ($isNew ? 'criada' : 'atualizada (' . implode(', ', $fields) . ')') . ' [' . implode(', ', array_keys($content)) . ']');
    } else {
        $same($label);
    }

    $area = $pageCfg['menu']['area'] ?? null;
    $menu = $area ? $menuDao->getByArea($contextId, $area)->next() : null;
    if (!$menu || $dryRun) {
        continue;
    }
    $assignments = $assignmentDao->getByMenuIdAndParentId($menu->getId(), null)->toArray();
    $assignment = null;
    foreach ($assignments as $a) {
        if ((int) $a->getMenuItemId() === (int) $item->getId()) {
            $assignment = $a;
        }
    }
    if (!$assignment) {
        $seqs = array_map(fn ($a) => $a->getSequence(), $assignments) ?: [0];
        $assignment = $assignmentDao->newDataObject();
        $assignment->setMenuId($menu->getId());
        $assignment->setMenuItemId($item->getId());
        $assignment->setParentId(null);
        $assignment->setSequence(($pageCfg['menu']['first'] ?? false) ? min($seqs) - 1 : max($seqs) + 1);
        $assignmentDao->insertObject($assignment);
        $changed("    {$label} no menu {$area}" . (($pageCfg['menu']['first'] ?? false) ? ' (primeiro item)' : ''));
    }
    // Título do item no menu acompanha o título da página
    if (!$equal($title, $assignment->getTitle(null))) {
        $assignment->setTitle($title, null);
        $assignmentDao->updateObject($assignment);
    }
}

// ---------------------------------------------------------------- modelos de e-mail
// Reescreve modelos padrão do OJS (assunto no JSON, corpo em HTML por idioma). Fica como
// modelo personalizado da revista: "Restaurar padrão" no painel volta ao texto do OJS.
// Com "alternateTo": modelo novo da revista, oferecido como opção ("Usar modelo") no e-mail
// daquele modelo padrão (ex.: a devolução técnica na decisão Recusar submissão).
foreach ($config['emailTemplates'] ?? [] as $tplCfg) {
    $template = Repo::emailTemplate()->getByKey($contextId, $tplCfg['key']);
    $isNew = false;
    if (!$template && !empty($tplCfg['alternateTo'])) {
        $template = Repo::emailTemplate()->newDataObject([
            'key' => $tplCfg['key'],
            'contextId' => $contextId,
            'alternateTo' => $tplCfg['alternateTo'],
        ]);
        $isNew = true;
    }
    if (!$template) {
        say("  ! modelo de e-mail {$tplCfg['key']} não existe nesta versão do OJS");
        continue;
    }
    $body = [];
    foreach ($locales as $locale) {
        $file = $baseDir . '/' . str_replace('{locale}', $locale, $tplCfg['file']);
        if (is_file($file)) {
            $body[$locale] = trim(file_get_contents($file));
        }
    }
    $params = [
        'key' => $tplCfg['key'],
        'contextId' => $contextId,
        'subject' => $localized($tplCfg['subject'], $template->getData('subject')),
        'body' => $localized($body, $template->getData('body')),
    ];
    if (!empty($tplCfg['name'])) {
        $params['name'] = $localized($tplCfg['name'], $template->getData('name'));
    }
    if (!empty($tplCfg['alternateTo'])) {
        $params['alternateTo'] = $tplCfg['alternateTo'];
    }
    $label = "e-mail {$tplCfg['key']}" . (!empty($tplCfg['alternateTo']) ? " (opção de {$tplCfg['alternateTo']})" : '');
    $fields = $isNew ? ['novo'] : $diff(array_diff_key($params, ['key' => 1, 'contextId' => 1]), fn ($k) => $template->getData($k));
    if (!$fields) {
        $same($label);
        continue;
    }
    $dryRun || Repo::emailTemplate()->edit($template, $params);
    $changed("  {$label}: " . ($isNew ? 'criado' : 'atualizado (' . implode(', ', $fields) . ')'));
}

// ---------------------------------------------------------------- menus
// Itens padrão (com titleLocaleKey) devem ser traduzidos pela chave. Se a criação da
// revista gravou o título em inglês, ele aparece em inglês em todos os idiomas:
// remove só os títulos idênticos ao padrão em inglês (títulos personalizados ficam).
$items = DB::table('navigation_menu_items as i')
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
    $default = __($item->localeKey, [], 'en');
    $query = DB::table('navigation_menu_item_settings')
        ->where('navigation_menu_item_id', $item->id)
        ->where('setting_name', 'title')
        ->where(fn ($q) => $q->where('setting_value', $default)->orWhere('setting_value', '')->orWhereNull('setting_value'));
    $resetTitles += $dryRun ? $query->count() : $query->delete();
}
if ($resetTitles) {
    $changed("  menu: {$resetTitles} título(s) padrão liberados para tradução");
}

// ---------------------------------------------------------------- caches
// Sempre: o CSS do tema (LESS) e os templates compilados também mudam sem mexer no banco
if (!$dryRun) {
    foreach (array_merge(glob($root . '/cache/t_compile/*') ?: [], glob($root . '/cache/*.css') ?: [], glob($root . '/cache/_db/*') ?: []) as $file) {
        is_file($file) && @unlink($file);
    }
}
say('==> Pronto: ' . ($changes ? "{$changes} " . ($changes === 1 ? 'mudança' : 'mudanças') : 'nenhuma mudança')
    . ", {$unchanged} " . ($unchanged === 1 ? 'item já estava certo' : 'itens já estavam certos')
    . ($verbose || !$unchanged ? '' : ' (--verbose lista)')
    . ($dryRun ? '. Nada foi gravado (dry-run).' : '. Caches limpos.'));
