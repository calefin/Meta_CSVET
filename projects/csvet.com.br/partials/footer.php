<?php // Rodapé compartilhado. Requer data.php. ?>
<!-- RODAPÉ COMPARTILHADO -->
<footer class="site-footer" id="main-footer-id">
    <div class="container footer-content">
        <img src="/img/logo-csvet-branco.png" alt="CSVET Editora" class="footer-logo" width="640" height="170" loading="lazy">

        <div class="social-links" id="social-links-id">
            <a href="https://www.youtube.com/@CSVET" class="social-btn" target="_blank" rel="noopener noreferrer" aria-label="YouTube CSVET" id="btn-social-yt">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.25 29 29 0 0 0-.46-5.33z" />
                    <polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02" />
                </svg>
            </a>
            <a href="https://www.instagram.com/prof.atilio/" class="social-btn" target="_blank" rel="noopener noreferrer" aria-label="Instagram Prof Atilio" id="btn-social-ig">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="2" width="20" height="20" rx="5" ry="5" />
                    <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z" />
                    <line x1="17.5" y1="6.5" x2="17.51" y2="6.5" />
                </svg>
            </a>
        </div>

        <nav class="footer-legal" aria-label="Informações legais">
            <a href="/privacidade">Política de Privacidade</a>
            <a href="/termos">Termos de Uso</a>
            <a href="/privacidade#direitos">Seus direitos (LGPD)</a>
            <a href="mailto:<?php echo htmlspecialchars($legal['dpo_email']); ?>">Encarregado de dados</a>
        </nav>

        <p class="footer-text">
            &copy; <?php echo date('Y'); ?> <strong><?php echo htmlspecialchars($legal['controller']); ?></strong>. Todos os direitos reservados.<br>
            <?php if (!empty($legal['cnpj'])): ?>CNPJ <?php echo htmlspecialchars($legal['cnpj']); ?> &middot; <?php endif; ?>
            <?php if (!empty($legal['address'])): ?><?php echo htmlspecialchars($legal['address']); ?><br><?php endif; ?>
            Contato: <a href="mailto:<?php echo htmlspecialchars($organization['email']); ?>"><?php echo htmlspecialchars($organization['email']); ?></a> &middot;
            Responsável: <a href="<?php echo htmlspecialchars($organization['founder_url']); ?>" target="_blank" rel="noopener">Prof. Dr. Atilio Sersun Calefi</a>.<br>
            Este site não usa cookies nem ferramentas de rastreamento.
        </p>
    </div>
</footer>
