<?php
require_once 'data.php';

// Helper para converter hex em rgba no PHP
function hex2rgba($color, $opacity) {
    $color = ltrim($color, '#');
    if (strlen($color) == 6) {
        list($r, $g, $b) = array($color[0].$color[1], $color[2].$color[3], $color[4].$color[5]);
    } elseif (strlen($color) == 3) {
        list($r, $g, $b) = array($color[0].$color[0], $color[1].$color[1], $color[2].$color[2]);
    } else {
        return "rgba(0,0,0,0)";
    }
    $r = hexdec($r);
    $g = hexdec($g);
    $b = hexdec($b);
    return "rgba($r, $g, $b, $opacity)";
}

// Helper para obter SVG
function getSvgIcon($iconName) {
    switch ($iconName) {
        case 'app':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="3" width="20" height="14" rx="2" ry="2" />
                <line x1="8" y1="21" x2="16" y2="21" />
                <line x1="12" y1="17" x2="12" y2="21" />
            </svg>';
        case 'academic':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 10v6M2 10l10-5 10 5-10 5z" />
                <path d="M6 12v5c0 2 2 3 6 3s6-1 6-3v-5" />
            </svg>';
        case 'ranking':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
            </svg>';
        case 'legal':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="2" x2="12" y2="22" />
                <line x1="5" y1="7" x2="19" y2="7" />
                <path d="M19 7c0 5-3.5 8-7 8s-7-3-7-8" />
                <path d="M12 15c0 3 2.5 5 5 5h-10c2.5 0 5-2 5-5" />
            </svg>';
        case 'genetic':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4.5 10.5c3-3 6-3 9 0s6 3 9 0" />
                <path d="M4.5 13.5c3 3 6 3 9 0s6-3 9 0" />
                <line x1="8" y1="9" x2="8" y2="15" />
                <line x1="16" y1="9" x2="16" y2="15" />
                <line x1="12" y1="7.5" x2="12" y2="16.5" />
            </svg>';
        case 'exam':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                <polyline points="14 2 14 8 20 8" />
                <line x1="16" y1="13" x2="8" y2="13" />
                <line x1="16" y1="17" x2="8" y2="17" />
                <polyline points="10 9 9 9 8 9" />
            </svg>';
        case 'journal':
            // Pegada com folha, o mesmo símbolo do logo da NVAS
            return '<svg viewBox="0 0 24 24" fill="currentColor" stroke="none">
                <path d="M12 20.5c-3.3 0-6-1.3-6-3.8 0-2.6 2.4-5.6 6-5.6s6 3 6 5.6c0 2.5-2.7 3.8-6 3.8z" />
                <ellipse cx="5.2" cy="10.6" rx="1.6" ry="2.1" transform="rotate(-25 5.2 10.6)" />
                <ellipse cx="9.3" cy="6.9" rx="1.7" ry="2.3" transform="rotate(-8 9.3 6.9)" />
                <ellipse cx="14.7" cy="6.9" rx="1.7" ry="2.3" transform="rotate(8 14.7 6.9)" />
                <ellipse cx="18.8" cy="10.6" rx="1.6" ry="2.1" transform="rotate(25 18.8 10.6)" />
                <path d="M17.3 5.6c0-1.9 1.4-3.2 3.6-3.2 0 2-1.4 3.4-3.6 3.2z" fill="#00aaff" />
            </svg>';
        default:
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="8" x2="12" y2="16" />
                <line x1="8" y1="12" x2="16" y2="12" />
            </svg>';
    }
}

// Verifica se há pedido de redirecionamento
$isRedirect = false;
$redirectSite = null;

if (isset($_GET['go'])) {
    $go = strtolower(trim($_GET['go']));
    if (isset($redirect_map[$go])) {
        $targetKey = $redirect_map[$go];
        if (isset($sites[$targetKey])) {
            $redirectSite = $sites[$targetKey];
            
            // Se for redirecionamento imediato via parâmetro direct=1
            if (isset($_GET['direct']) && $_GET['direct'] == '1') {
                header("Location: " . $redirectSite['url'], true, 302);
                exit;
            }
            
            $isRedirect = true;
        }
    }
    if (!$isRedirect) {
        header("Location: " . $seo_config['base_url'] . "/", true, 301);
        exit;
    }
}

// Páginas de transição: noindex também no cabeçalho HTTP (vale para robôs que não leem o HTML)
if ($isRedirect) {
    header('X-Robots-Tag: noindex, follow');
}
?>
<?php
$pageTitle = $isRedirect
    ? "Redirecionando para " . htmlspecialchars($redirectSite['title']) . " | " . htmlspecialchars($seo_config['site_name'])
    : htmlspecialchars($seo_config['title_default']);

$pageDesc = $isRedirect
    ? "Você está sendo redirecionado para o site " . htmlspecialchars($redirectSite['title']) . " no ecossistema " . htmlspecialchars($seo_config['site_name']) . "."
    : htmlspecialchars($seo_config['description_default']);

$canonicalUrl = $seo_config['base_url'] . "/";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <meta name="description" content="<?php echo $pageDesc; ?>">
    <meta name="author" content="<?php echo htmlspecialchars($seo_config['author']); ?>">
    <meta name="theme-color" content="<?php echo htmlspecialchars($seo_config['theme_color']); ?>">

    <!-- Indexação controlada: rotas de redirect nunca são indexadas -->
    <?php if ($isRedirect): ?>
        <meta name="robots" content="noindex, follow">
        <meta http-equiv="refresh" content="3;url=<?php echo htmlspecialchars($redirectSite['url']); ?>">
    <?php else: ?>
        <meta name="robots" content="index, follow, max-image-preview:large">
    <?php endif; ?>

    <?php if (!$isRedirect): ?>
    <link rel="canonical" href="<?php echo $canonicalUrl; ?>">
    <link rel="alternate" hreflang="pt-BR" href="<?php echo $canonicalUrl; ?>">
    <?php endif; ?>

    <!-- Open Graph (Facebook / WhatsApp / Telegram) -->
    <meta property="og:locale" content="<?php echo htmlspecialchars($seo_config['locale']); ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo $pageTitle; ?>">
    <meta property="og:description" content="<?php echo $pageDesc; ?>">
    <meta property="og:url" content="<?php echo $canonicalUrl; ?>">
    <meta property="og:site_name" content="<?php echo htmlspecialchars($seo_config['site_name']); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($seo_config['og_image']); ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="<?php echo htmlspecialchars($seo_config['og_image_alt']); ?>">

    <!-- Twitter Cards (X) -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo $pageTitle; ?>">
    <meta name="twitter:description" content="<?php echo $pageDesc; ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($seo_config['og_image']); ?>">
    <meta name="twitter:image:alt" content="<?php echo htmlspecialchars($seo_config['og_image_alt']); ?>">

    <!-- Structured Data (JSON-LD): a CSVET Editora (Organization) publica o site, os periódicos e mantém os demais domínios -->
    <?php if (!$isRedirect):
        $orgId = $seo_config['base_url'] . '/#organization';
        $graph = [
            [
                '@type' => 'Organization',
                '@id' => $orgId,
                'name' => $organization['name'],
                'legalName' => $organization['legal_name'],
                'url' => $seo_config['base_url'] . '/',
                'logo' => ['@type' => 'ImageObject', 'url' => $organization['logo']],
                'email' => $organization['email'],
                'taxID' => $organization['tax_id'],
                'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Americana', 'addressRegion' => 'SP', 'addressCountry' => 'BR'],
                'founder' => ['@type' => 'Person', 'name' => $organization['founder'], 'url' => $organization['founder_url']],
                'sameAs' => $organization['same_as'],
            ],
            [
                '@type' => 'WebSite',
                '@id' => $seo_config['base_url'] . '/#website',
                'name' => $seo_config['site_name'],
                'url' => $seo_config['base_url'] . '/',
                'description' => $seo_config['description_default'],
                'inLanguage' => 'pt-BR',
                'publisher' => ['@id' => $orgId],
                'relatedLink' => array_values(array_column($sites, 'url')),
            ],
        ];
        foreach ($journals as $journal) {
            $graph[] = [
                '@type' => 'Periodical',
                'name' => $journal['title'],
                'alternateName' => $journal['abbr'],
                'url' => $journal['url'],
                'description' => $journal['description'],
                'inLanguage' => $journal['languages'],
                'isAccessibleForFree' => true,
                'publisher' => ['@id' => $orgId],
            ];
        }
    ?>
    <script type="application/ld+json">
<?php echo json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_PRETTY_PRINT); ?>

    </script>
    <?php endif; ?>

    <?php include __DIR__ . '/partials/assets.php'; ?>
</head>
<body class="<?php echo $isRedirect ? 'is-redirect' : 'is-portal'; ?>">

    <?php if ($isRedirect && $redirectSite):
        $themeColor = $redirectSite['color'];
        $themeColorOp10 = hex2rgba($themeColor, 0.1);
        $themeColorOp20 = hex2rgba($themeColor, 0.2);
        $themeColorOp30 = hex2rgba($themeColor, 0.3);
    ?>
        <!-- TELA DE REDIRECIONAMENTO COM COUNTDOWN -->
        <main class="redirect-main">
            <div class="redirect-container" id="redirect-element" style="--theme-color: <?php echo $themeColor; ?>; --theme-color-op10: <?php echo $themeColorOp10; ?>; --theme-color-op20: <?php echo $themeColorOp20; ?>; --theme-color-op30: <?php echo $themeColorOp30; ?>;">
                <img src="/img/logo-csvet.png" alt="CSVET Editora" class="redirect-logo" width="640" height="170">

                <div class="redirect-icon-large">
                    <?php echo getSvgIcon($redirectSite['icon']); ?>
                </div>

                <h1 class="redirect-title" id="redirect-heading">Redirecionando...</h1>
                <p class="redirect-subtitle" id="redirect-desc">
                    Você está sendo direcionado para <strong><?php echo htmlspecialchars($redirectSite['title']); ?></strong>.<br>
                    <?php echo htmlspecialchars($redirectSite['badge']); ?>
                </p>

                <div class="loader-wrapper" id="loader-wrapper-id">
                    <svg class="loader-svg" viewBox="0 0 100 100">
                        <circle class="loader-track" cx="50" cy="50" r="45"></circle>
                        <circle class="loader-bar" cx="50" cy="50" r="45"></circle>
                    </svg>
                    <div class="loader-text" id="countdown-text">3</div>
                </div>

                <div class="btn-group" id="btn-group-id">
                    <a href="<?php echo htmlspecialchars($redirectSite['url']); ?>" class="btn btn-primary" id="btn-go-now">
                        Ir agora
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </a>
                    <a href="/" class="btn btn-outline" id="btn-cancel">Voltar ao portal</a>
                </div>
            </div>
        </main>

        <script>
            let timeLeft = 3;
            const countdownText = document.getElementById('countdown-text');
            const targetUrl = <?php echo json_encode($redirectSite['url'], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG); ?>;

            const timer = setInterval(() => {
                timeLeft--;
                if (countdownText) countdownText.textContent = timeLeft;
                if (timeLeft <= 0) {
                    clearInterval(timer);
                    window.location.href = targetUrl;
                }
            }, 1000);
        </script>

    <?php else: ?>

        <?php include __DIR__ . '/partials/header.php'; ?>

        <!-- HERO -->
        <section class="hero">
            <div class="container hero-inner">
                <p class="eyebrow">CSVET Editora, Cursos e Treinamentos</p>
                <h1 class="hero-title" id="main-title-id">Ciência, educação e tecnologia em Medicina Veterinária</h1>
                <p class="hero-subtitle" id="main-subtitle-id">
                    Editora científica responsável por periódicos revisados por pares, materiais didáticos e plataformas de ensino, perícia e gestão genética, sob coordenação do <strong>Prof. Dr. Atilio Sersun Calefi</strong>.
                </p>
                <div class="hero-actions">
                    <a href="#periodicos" class="btn btn-primary">Conheça nossos periódicos</a>
                    <a href="#plataformas" class="btn btn-ghost">Ver plataformas</a>
                </div>
            </div>
        </section>

        <main>
            <!-- A EDITORA -->
            <section class="section" id="editora">
                <div class="container">
                    <div class="section-head">
                        <p class="eyebrow eyebrow-dark">A Editora</p>
                        <h2 class="section-title">Uma editora, um ecossistema</h2>
                        <p class="section-lead">A CSVET Editora reúne sob a mesma marca a publicação científica, a produção de material didático e as plataformas digitais voltadas à Medicina Veterinária.</p>
                    </div>
                    <div class="pillars">
                        <div class="pillar">
                            <h3>Publicação científica</h3>
                            <p>Periódicos de acesso aberto com revisão por pares, fluxo editorial no Open Journal Systems e submissão online.</p>
                        </div>
                        <div class="pillar">
                            <h3>Material didático e cursos</h3>
                            <p>Apostilas, cursos preparatórios, mentorias acadêmicas e treinamentos especializados.</p>
                        </div>
                        <div class="pillar">
                            <h3>Tecnologia aplicada</h3>
                            <p>Aplicativos educacionais, bancos de questões, perícia veterinária e gestão genética de aves.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- PERIÓDICOS -->
            <section class="section section-tint" id="periodicos">
                <div class="container">
                    <div class="section-head">
                        <p class="eyebrow eyebrow-dark">Publicação científica</p>
                        <h2 class="section-title">Periódicos da CSVET Editora</h2>
                        <p class="section-lead">Acesso aberto, revisão por pares e submissão online em <a href="<?php echo htmlspecialchars($sites['journals']['url']); ?>">journals.csvet.com.br</a>.</p>
                    </div>

                    <?php foreach ($journals as $key => $journal): ?>
                        <article class="journal-feature" id="journal-<?php echo $key; ?>">
                            <div class="journal-mark" aria-hidden="true">
                                <?php echo getSvgIcon('journal'); ?>
                            </div>
                            <div class="journal-body">
                                <p class="journal-kicker"><?php echo htmlspecialchars($journal['abbr']); ?> &middot; <?php echo htmlspecialchars(strtoupper(implode(' / ', $journal['languages']))); ?></p>
                                <h3 class="journal-title"><?php echo htmlspecialchars($journal['title']); ?></h3>
                                <p class="journal-desc"><?php echo htmlspecialchars($journal['description']); ?></p>
                                <div class="journal-actions">
                                    <a href="<?php echo htmlspecialchars($journal['url']); ?>" class="btn btn-primary">Acessar a revista</a>
                                    <a href="<?php echo htmlspecialchars($journal['url']); ?>/about/submissions" class="btn btn-outline">Submeter manuscrito</a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- PLATAFORMAS: links diretos (seguíveis) para transferir autoridade a cada domínio -->
            <section class="section" id="plataformas">
                <div class="container">
                    <div class="section-head">
                        <p class="eyebrow eyebrow-dark">Ecossistema CSVET</p>
                        <h2 class="section-title">Plataformas e projetos</h2>
                    </div>

                    <div class="portal-grid" id="portal-grid-id">
                        <?php foreach ($sites as $key => $site):
                            $themeColor = $site['color'];
                            $themeColorOp10 = hex2rgba($themeColor, 0.1);
                            $themeColorOp20 = hex2rgba($themeColor, 0.2);
                        ?>
                            <a href="<?php echo htmlspecialchars($site['url']); ?>" class="portal-card" id="card-<?php echo $key; ?>" style="--theme-color: <?php echo $themeColor; ?>; --theme-color-op10: <?php echo $themeColorOp10; ?>; --theme-color-op20: <?php echo $themeColorOp20; ?>;">
                                <div class="card-header">
                                    <div class="card-icon-container">
                                        <?php echo getSvgIcon($site['icon']); ?>
                                    </div>
                                    <span class="badge"><?php echo htmlspecialchars($site['badge']); ?></span>
                                </div>

                                <h3 class="card-title"><?php echo htmlspecialchars($site['title']); ?></h3>
                                <p class="card-description"><?php echo htmlspecialchars($site['description']); ?></p>

                                <div class="card-footer">
                                    <span><?php echo htmlspecialchars(preg_replace('#^https?://#', '', $site['url'])); ?></span>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                        <polyline points="12 5 19 12 12 19"></polyline>
                                    </svg>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        </main>

    <?php endif; ?>

    <?php include __DIR__ . '/partials/footer.php'; ?>

</body>
</html>
