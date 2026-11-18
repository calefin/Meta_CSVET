<?php
// Início das páginas institucionais. Defina antes: $page = ['title', 'description', 'path', 'heading', 'lead'].
$pageUrl = $seo_config['base_url'] . $page['path'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page['title'] . ' | ' . $seo_config['site_name']); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($page['description']); ?>">
    <meta name="author" content="<?php echo htmlspecialchars($seo_config['author']); ?>">
    <meta name="theme-color" content="<?php echo htmlspecialchars($seo_config['theme_color']); ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?php echo htmlspecialchars($pageUrl); ?>">

    <meta property="og:locale" content="<?php echo htmlspecialchars($seo_config['locale']); ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo htmlspecialchars($page['title'] . ' | ' . $seo_config['site_name']); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($page['description']); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($pageUrl); ?>">
    <meta property="og:site_name" content="<?php echo htmlspecialchars($seo_config['site_name']); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($seo_config['og_image']); ?>">

    <script type="application/ld+json">
<?php echo json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'WebPage',
    'name' => $page['title'],
    'url' => $pageUrl,
    'inLanguage' => 'pt-BR',
    'isPartOf' => ['@id' => $seo_config['base_url'] . '/#website'],
    'publisher' => ['@id' => $seo_config['base_url'] . '/#organization'],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_PRETTY_PRINT); ?>

    </script>

    <?php include __DIR__ . '/assets.php'; ?>
</head>
<body class="is-portal">
    <?php include __DIR__ . '/header.php'; ?>

    <section class="legal-hero">
        <div class="container">
            <h1><?php echo htmlspecialchars($page['heading']); ?></h1>
            <p><?php echo htmlspecialchars($page['lead']); ?></p>
        </div>
    </section>

    <main class="container legal">
