# Implementação — visão geral

Estrutura principal do repositório:

- `client/`: frontend (Vite, React, TypeScript)
  - `src/`: código fonte do app
  - `public/`: ativos estáticos (onde `sitemap.xml` e `robots.txt` podem residir)
- `server/`: backend (Express, tRPC)
  - `_core/`: utilitários e inicialização do servidor
  - `routers/`: rotas tRPC
  - scripts Python para processamento (ex.: `question-parser.py`)
- `drizzle/`: migrations e schema
- `shared/`: tipos e utilitários compartilhados

Decisões importantes:
- SEO: o servidor já serve `/robots.txt` e `/sitemap.xml`; agora usamos a variável `SITE_BASE` em `.env`.
- Autenticação: Passport + Google OAuth (veja `server/_core/passport.ts` e `googleAuth.ts`).
- Armazenamento de imagens e arquivos: implementado via `storage.ts` e `imageStorage.ts`.

Pontos de entrada:
- Frontend: `client/index.html` e `client/src/main.tsx`.

Auth specifics: detalhamento completo do fluxo Google OAuth foi movido para `doc/GOOGLE_AUTH_SETUP.md`.

Note: `doc/IMPLEMENTATION_SUMMARY.md` foi consolidado em `doc/GOOGLE_AUTH_SETUP.md` e removido do repositório.
- Backend: `server/_core/index.ts`.

Como estender:
- Para adicionar páginas ao sitemap, edite `scripts/generate-sitemap.mjs`.
- Para suportar múltiplos domínios canônicos, defina `SITE_BASE` para o domínio preferido e configure redirects no nível do proxy/VPS.
