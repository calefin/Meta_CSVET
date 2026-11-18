<?php // Cabeçalho do site (home e páginas institucionais). Requer data.php. ?>
<!-- CABEÇALHO -->
<header class="site-header" id="main-header-id">
    <div class="container header-inner">
        <a href="/" class="header-brand" aria-label="CSVET Editora - início">
            <img src="/img/logo-csvet-branco.png" alt="CSVET Editora" class="header-logo" width="640" height="170">
        </a>
        <nav class="header-nav" aria-label="Seções">
            <a href="/#editora">A Editora</a>
            <a href="/#periodicos">Periódicos</a>
            <a href="/#plataformas">Plataformas</a>
            <a href="<?php echo htmlspecialchars($sites['journals']['url']); ?>" class="btn-nav">Journals</a>
        </nav>
    </div>
</header>
