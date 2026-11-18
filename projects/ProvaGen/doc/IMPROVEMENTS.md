# Melhorias sugeridas

Prioridade alta:
- Externalizar `SITE_BASE` (feito) e garantir que o VPS responda para ambos domínios com redirects.
- Adicionar testes de integração para importadores de questões (QTI, JSON).
- Cobrir geração de PDF com testes e verificação de estabilidade.

Prioridade média:
- Gerar automaticamente `sitemap.xml` a partir das rotas do frontend.
- Adicionar meta tags dinâmicas por página para SEO (usar React Helmet ou similar).
- Otimizar imagens e adicionar `og-image` dinâmico para páginas com conteúdo gerado.

Baixa prioridade:
- Suporte a i18n para meta tags e conteúdo (já existem `locales/`).
- Monitoramento de SEO (Google Search Console, sitemap submission automatizada).

Tarefas operacionais:
- Garantir `robots.txt` e certificados TLS válidos para `prof-aid.com` e `www.prof-aid.com`.
- Adicionar `memories/repo/decisions.md` para registrar decisões arquiteturais.

## Próximos passos e ativos SEO

- Criar imagem Open Graph (`client/public/og-image.png`) a partir do placeholder SVG (`client/public/og-image.svg`). Recomendo 1200×630 PNG.
- Criar `client/public/favicon.svg` e, opcionalmente, `apple-touch-icon.png` (180×180).
- Submeter `sitemap.xml` no Google Search Console para ambos domínios (`https://www.prof-aid.com/sitemap.xml` e `https://prof-aid.com/sitemap.xml`).
- Verificar indexação via `site:www.prof-aid.com` e monitorar `/robots.txt` e certificados TLS.

Esses passos foram documentados em `doc/PROXIMOS_PASSOS.md` e foram consolidados aqui.
