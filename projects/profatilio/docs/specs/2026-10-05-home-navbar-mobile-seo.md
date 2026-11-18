# Home pública: navbar mobile-first, acesso do aluno em destaque e SEO/performance

**Data:** 2026-10-05
**Escopo:** site público (home, blog, na-mídia). Não toca nos painéis Filament.

## Problema

- Navbar com 12 itens no desktop (Biografia, Projetos, Área do Aluno, ABNT, Blog, Na Mídia, Agendar, Orçamentos▾, Contato, Validador, Portal, Professor) — entulhada; já virava menu de celular em 768px.
- Logo praticamente invisível: `logo.png` (1024×1024) tem ~45% de borda transparente, então num quadrado de 40px o símbolo ficava com ~20px. No celular o nome ainda era truncado ao lado do botão "Portal do Aluno".
- Acesso do aluno disputava espaço com 4 botões grandes no hero.
- Tailwind via `cdn.tailwindcss.com` (script de ~300KB que gera o CSS no navegador e bloqueia a renderização — péssimo para LCP/SEO mobile) e foto de perfil PNG de 1,7MB como imagem principal.
- JSON-LD com `aggregateRating`/`review` no próprio serviço (marcação "self-serving", contra as diretrizes do Google), `SearchAction` apontando para uma âncora (não é busca), `<link rel="alternate" type="application/rss+xml">` apontando para o sitemap, `og:image` declarando 1200×630 numa imagem 864×864.

## Solução

### Navbar (`resources/views/partials/site-nav.blade.php`, compartilhada por home/blog/na-mídia)
- A home deixou de ter cópia própria; usa o partial. Links de seção são `/#...` (na home só rolam, sem recarregar).
- Desktop (≥1024px): Sobre · Serviços▾ (Consulta, Necropsia, Consultoria & Mentoria, Cursos) · Normas ABNT · Blog · Na Mídia · Contato · botão **Portal do Aluno**. Validador e Área do Professor foram para o rodapé/menu mobile.
- Mobile: logo + nome ("PROF. DR." como sobrelinha, "Atilio Calefi" como título — cabe inteiro em 360px) + botão **Aluno** sempre visível + botão de menu.
- Menu mobile: painel lateral em tela cheia (trava o scroll, fecha com Esc/overlay), com card "Sou aluno(a)" no topo (Entrar no Portal, Esqueci a senha, Instalar app), grade de serviços e lista de links.
- Header fica mais opaco/sombreado após rolar (`@scroll.window`).

### Home (`resources/views/welcome.blade.php`)
- Hero: texto mais curto; grade de **acesso rápido** com card largo "Entrar no Portal do Aluno" + Agendar consulta, Serviços & orçamentos, Normas ABNT 2026, Currículo Lattes. Redes sociais viraram ícones de 40px.
- Seção "Área de Alunos": botão grande + links "Esqueci minha senha" e "Instalar app no celular".
- Rodapé novo em 4 colunas (Alunos / Serviços / Conteúdo) — também melhora links internos.
- `<main>` e `aria-label` nas navegações; espaçamento vertical das seções reduzido no celular (`py-16 sm:py-28`).

### SEO / performance
- `resources/css/site.css` (novo, Tailwind 4 compilado pelo Vite, entrada adicionada em `vite.config.js`) substitui o CDN em home, blog (index/show) e na-mídia. ~9,7KB gzip. Os estilos `.glass`, `.glass-card` e `.post-content` foram para esse arquivo; cor `slate-350` passou a existir.
- Imagens novas em `public/images/`: `logo-mark(-96).png/webp` (logo recortado), `profile.webp` / `profile-480.webp` (30KB/14KB, com `<picture>` + `srcset` + preload `fetchpriority=high`), `og-cover.jpg` 1200×630 para Open Graph/Twitter.
- `<title>` e meta description reescritos (≤160 caracteres), `theme-color`, `apple-touch-icon`, `rel=sitemap`.
- JSON-LD: removidos `aggregateRating`/`review` do `Service` e o `SearchAction` do `WebSite`; adicionado `inLanguage`.
- Fontes: pesos não usados removidos.

## Importar no prof-aid.com
1. Copiar `resources/css/site.css` e adicionar a entrada em `vite.config.js`; trocar o bloco `cdn.tailwindcss.com` + `tailwind.config` + `<style>` das páginas públicas por `@vite('resources/css/site.css')`.
2. Copiar o partial `site-nav.blade.php` e trocar a navbar inline da home por `@include('partials.site-nav')` (a `<body>` precisa de `x-data="{ mobileMenu: false }"`).
3. Gerar o logo recortado (bounding box dos pixels com alpha < 90 + 4% de margem) e as versões WebP da foto.
4. `npm run build` e commitar `public/build`.
