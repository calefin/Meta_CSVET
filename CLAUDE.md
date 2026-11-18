# VetRank — Notas para Claude Code

## Stack
React 19 + TypeScript, tRPC 11, Drizzle ORM, MySQL 8, Vite 7, pnpm 10, Docker

## Comandos principais
```bash
pnpm dev          # dev server porta 3000
pnpm build        # vite build + esbuild backend
pnpm start        # node dist/index.js
pnpm db:push      # drizzle-kit generate + migrate
pnpm blog:generate [tema]  # gera post de blog via IA
```

## Repositório
- **Oficial:** https://github.com/calefin/vetrank_new (privado) — remote `origin` local e no VPS.
- O antigo `sersung/vetrank` (conta de terceiro) está **descontinuado**; localmente ficou como remote `irmao`, só para histórico. Não fazer push nele.
- VPS acessa o repo via deploy key somente-leitura (`~/.ssh/vetrank_deploy`, host alias `github-vetrank`):
  `git remote -v` no VPS → `git@github-vetrank:calefin/vetrank_new.git`.

## Deploy no VPS
```bash
cd /opt/apps/vetrank
git pull origin main
docker compose up -d --build
```
O build roda dentro do Docker (pnpm install + pnpm build). Demora ~450s no VPS por ser CPU-bound.

## Regras críticas de build

### NÃO usar manualChunks no vite.config.ts
Tentativas anteriores de code splitting causaram crashes em produção:
- `Cannot set properties of undefined (setting 'unstable_now')` — react-dom separado do scheduler
- `Cannot access 'P'/'T' before initialization` — dependências circulares no catch-all vendor

O bundle único de 2.4MB é lento no VPS mas **funciona corretamente**. Não tentar otimizar
com manualChunks sem testar exaustivamente em produção primeiro.

### Service Worker (client/public/sw.js)
- Navegações (HTML) usam **Network-first** — nunca cacheia index.html
- Assets `/assets/*` (com content-hash do Vite) usam **Cache-first** — são imutáveis
- Cache name: `vetrank-cache-v2` — bumpar a versão a cada mudança de estratégia
- Falha em cachear index.html foi a causa de tela branca pós-deploy anteriores

## Endpoints agendados (crontab no VPS host)
- Exigem header `X-Cron-Secret` = `CRON_SECRET` do `.env` (sem `CRON_SECRET` → 503). Middleware em `server/_core/index.ts`.
- Bloqueados para a internet no Nginx Proxy Manager (403); o cron chama direto o container em `127.0.0.1:3001`.
- O header fica em `/root/.config/vetrank-cron.header` (600), lido com `curl -H @arquivo`.
- Ativos no crontab do VPS (saída em `/var/log/vetrank-cron.log`):
```
0 11 * * *  curl -s -X POST -H @/root/.config/vetrank-cron.header http://127.0.0.1:3001/api/scheduled/send-daily-question
0 12 * * *  curl -s -X POST -H @/root/.config/vetrank-cron.header http://127.0.0.1:3001/api/scheduled/send-expiry-emails
0 14 * * 1  curl -s -X POST -H @/root/.config/vetrank-cron.header http://127.0.0.1:3001/api/scheduled/send-new-questions-digest
```
- Fora do cron: `generate-blog-post`, `sync-concursos`, `concursos-weekly-post` (usam IA — só na base de teste)
  e `billing-audit`.

## E-mail (SMTP HostGator)
- O SMTP da HostGator recusa mais de ~5 conexões simultâneas por IP (ECONNREFUSED). `server/email.ts` usa
  pool: transacional (1 conexão) e envio em massa (`bulk: true`: 2 conexões, `SMTP_BULK_PER_MINUTE`, padrão 8/min).
- Não voltar a abrir uma conexão por e-mail nem disparar lotes em paralelo sem pool.

## Radar de Concursos (`/concursos`)
- `server/concursos.ts`: coleta diária de https://www.pciconcursos.com.br/cargos/veterinario (robots.txt permite),
  máx. 15 notícias novas/execução, 3s entre requisições. LLM (`invokeLLM`) extrai SÓ fatos + escreve resumo próprio.
  Nunca republicar o texto da notícia; sempre mostrar fonte + link oficial.
- Notícias sem vaga de veterinária ou repetidas (prorrogação) ficam como linhas inativas `ignorado-*`/`dup-*`
  só para não serem reprocessadas.
- Cards de prévia (WhatsApp/redes): `/api/og/concurso/:slug.png`, `/api/og/concursos-semana.png` (`server/shareCard.ts`,
  sharp + fontes DejaVu instaladas no Dockerfile). `seoPrerender.ts` agora troca og:/twitter: por página.
- Concursos entram no feed de Novidades e no e-mail semanal (send-new-questions-digest).

## Cobranças (Mercado Pago) — regras anti-cobrança indevida
- Todo crédito de premium passa por `creditPremium()` em `server/mpWebhook.ts` (transação + lock por usuário,
  dedup por cobrança real, janela de 20 dias no mensal). Nunca chamar `activatePremium` direto.
- `createSubscription` cancela a assinatura antiga antes de criar outra; pagamento anual cancela a mensal.
- `billing-audit` (diário) só LÊ: cruza MP x banco e manda e-mail aos admins se achar risco.
- Avisos internos (tickets, alertas, auditoria) vão para `ADMIN_NOTIFY_EMAILS` (.env, separado por vírgula);
  sem ele, para todos os usuários `role=admin`.

## Notificações de novas questões importadas
- **Mural (automático)**: toda chamada de `questions.bulkImport` ou `questions.importCsv` que
  importar ao menos 1 questão cria automaticamente um announcement tipo `update` no mural,
  citando a(s) disciplina(s) e a quantidade. Ver `announceNewQuestions()` em `server/routers/questions.ts`.
- **Email (digest semanal)**: `POST /api/scheduled/send-new-questions-digest` soma as questões
  com `batchId` criadas nos últimos 7 dias, agrupadas por disciplina, e envia um resumo
  (`newQuestionsDigestTemplate` em `server/email.ts`) para todos os usuários com email cadastrado.
  Se nenhuma questão nova na semana, não envia nada. Mesmo padrão de batch/rate-limit do
  send-daily-question (lotes de 20, 500ms entre lotes).

## Pipeline de blog (3 passos)
1. Geração via Gemini 2.5 Flash
2. Auditoria LLM (dosagens, nomes fictícios)
3. Verificação real de DOIs via CrossRef API (`server/doiVerifier.ts`)
   - GET `https://api.crossref.org/works/{doi}` → 200=válido, 404=falso
   - DOIs inválidos são removidos cirurgicamente do HTML

## Auth
- Google OAuth + email/senha + local dev (VITE_APP_ID=local-dev)
- JWT em cookie HttpOnly `app_session_id` (1 ano)
- Roles: user / teacher / coordinator / superuser / admin

## Banco de dados
- Schema: `drizzle/schema.ts`
- Migrações: `pnpm db:push`
- Blog posts salvos com `active: false` — exigem aprovação admin

## Routers tRPC
Agregados em `server/routers.ts`. Principais: questions, exams, practice,
gamification, blog, admin, teacher, coordinator, trails, payment, lgpd.

## Observações de UX
- Dashboard mostra "Questão do Dia" (widget inline com reveal de resposta)
- Usuários com 0 questões respondidas veem card de onboarding
- Admin → aba "Blog": verificar/corrigir DOIs por post ou em massa
