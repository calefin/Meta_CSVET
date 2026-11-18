# BagParser — instruções para o Claude Code

## Como trabalhar neste projeto
- Responder sempre em **português (pt-BR)**.
- "Continue de onde paramos" = ler `docs/STATUS.md` (seção **Resume here**) e seguir a lista em ordem.
- Manter `docs/STATUS.md` e `docs/DECISIONS.md` atualizados a cada mudança relevante (decisões novas: próximo `D-xx` no fim da tabela).
- O usuário trabalha em **mais de um PC** e sincroniza pelo GitHub (`calefin/bagparser`, branch `main`).
  Antes de encerrar uma sessão, garantir que tudo foi commitado e enviado (`git status` limpo, `main` igual a `origin/main`).
- Só fazer commit/push quando o usuário pedir. Mensagens de commit em português.
- O app precisa funcionar bem no **celular** e como **PWA**: conferir telas em 390px de largura além do desktop.

## Primeira vez em um PC novo
Requisitos: Git, Node.js 20+, Docker com Compose v2.

```bash
git clone https://github.com/calefin/bagparser.git
cd bagparser
cp .env.example .env            # em produção: trocar DB_PASSWORD e FINGERPRINT_SALT
# sem Nginx do host na frente (uso local): TRUST_PROXY_HOPS=1 no .env
docker compose up --build -d    # app em http://127.0.0.1:3080
curl http://127.0.0.1:3080/api/health

# para rodar testes / build fora do Docker
(cd frontend && npm ci && npm test && npm run build)
(cd backend && npm ci && npm test)
```

PC que já tem o repositório: `git pull` e `docker compose up --build -d`.

Observações:
- O banco (drops do BossLedger) fica no volume Docker de cada máquina e **não** vai pelo Git. `init.sql` só roda quando o volume é criado; `docker compose down -v` zera o banco.
- `.env`, `node_modules/` e `dist/` não são versionados.
- Catálogos gerados (não editar à mão): `shared/soulcores.json` (`node scripts/import-soulcores.mjs`) e `shared/hunting-places.json` (`node scripts/import-hunting-places.mjs`; correções manuais em `shared/hunting-places.overrides.json`; a API recarrega os locais no start quando os arquivos mudam).
- O catálogo `shared/items.json` / `shared/npcs.json` é gerado: não editar à mão. Atualizar com
  `node scripts/import-tibiawiki.mjs` (precisa de internet, ~1,5 min) e reiniciar a API. Entradas manuais: `shared/items.overrides.json`.
- Equipamentos para o set da hunt (D-59): `shared/equipment.json` é gerado por `node scripts/import-equipment.mjs` (TibiaWiki, ~2 min); não editar à mão.
- Criaturas (HP, XP, elementos, bestiário) para a análise de criaturas (D-61): `shared/creatures.json` ← `node scripts/import-creatures.mjs` (TibiaWiki, ~1 min).
- Ficha de criatura e hunts por vocação (D-80): `node scripts/import-creatures.mjs` também gera `shared/creature-wiki.json`; `shared/hunt-videos.json` ← `node scripts/import-hunt-videos.mjs` (D-81; títulos do YouTube, usa `YOUTUBE_API_KEY`, 3.000 unidades da cota); depois rodar `node scripts/build-hunt-table.mjs` (offline; gera `shared/hunt-table.json` a partir dos vídeos e dos guias). Testes dos scripts: `node --test scripts/*.test.mjs`.
- Minimapa (D-61/D-62): blocos e marcadores por andar em `frontend/public/map/` ← `node scripts/import-minimap.mjs` (tibiamaps/tibia-map-data, MIT, ~20 s); não editar à mão.
- Painel do admin (D-62): `/admin` (abas: visão geral, usuários, pagamentos, tickets, erros, moderação, promoções, log). Erros do site chegam sozinhos em `/admin/errors`.

## Deploy em produção (https://bagparser.com) — no ar desde 2026-11-04
- **VPS:** `129.121.37.243` (Ubuntu 22.04, usuário `root`), acesso manual pelo usuário (`ssh meu-vps` no WSL). Compartilhada com plumora, prof-aid, gestao, vetrank.
- **Proxy/SSL:** Nginx Proxy Manager em container (`nginx-proxy-manager`, painel `http://129.121.37.243:81`). Proxy Host `bagparser.com` + `www` → `http://bagparser_web:80`, Let's Encrypt, Force SSL, HTTP/2, Block Common Exploits; **Cache Assets desligado** (service worker).
- **App:** `/opt/apps/bagparser`, sobe com `docker compose -f docker-compose.yml -f docker-compose.npm.yml` (o `web` entra na rede `apps-network` do NPM, ver `.env` → `NPM_NETWORK`). `.env` da VPS tem segredos gerados pelo `deploy.sh` — nunca sobrescrever (perde o acesso ao banco).
- **DNS:** zona na HostGator (cPanel → Zone Editor): A `bagparser.com` → `129.121.37.243`, `www` = CNAME para `bagparser.com`.
- **GitHub privado:** a VPS lê o repo por deploy key só-leitura (`/root/.ssh/bagparser_deploy`, "Chave Bagparser" no GitHub), configurada no repo com `core.sshCommand`.

**Atualizar a produção** (depois de `git push` na `main`), na VPS:
```bash
cd /opt/apps/bagparser && bash deploy.sh
```
O script baixa a `main` do GitHub (`git fetch` + `reset --hard`), mostra os commits novos (avisa se não há nenhum: faltou `git push`), rebuilda, sobe os containers e só termina quando `/api/health` responde com a versão do `backend/package.json`. Se o GitHub recusar a deploy key (`Permission denied (publickey)`, aconteceu em 2026-11-04), o script mostra o que conferir; para publicar mesmo assim o código já presente: `SKIP_FETCH=1 bash deploy.sh`.

Contas/admin (D-24..D-26): o `.env` da VPS precisa de `ADMIN_EMAILS=<e-mail do dono>` (acrescentar a linha, sem sobrescrever o arquivo). Quem se cadastrar com esse e-mail vira admin (`/admin`). As tabelas novas são criadas pela API no start (`backend/src/migrate.ts`), não pelo `init.sql`.

Login Google/Discord (D-39) e YouTube (D-38): acrescentar também `PUBLIC_URL=https://bagparser.com`, `GOOGLE_CLIENT_ID/SECRET`, `DISCORD_CLIENT_ID/SECRET` e `YOUTUBE_API_KEY`. Localmente use `PUBLIC_URL=http://localhost:3080` e abra o site em `localhost` (não `127.0.0.1`).

E-mail (D-65): o `.env` da VPS precisa de `SMTP_HOST=mail.csvet.app`, `SMTP_PORT=465`, `SMTP_USER=noreply@csvet.app`, `SMTP_PASS` (só na VPS) e `SUPPORT_EMAIL=support@csvet.app` (`MAIL_FROM` é opcional); em /admin há "Enviar e-mail de teste". Local: `MAIL_LOG=1` mostra os e-mails no log da API em vez de enviar. Anúncios (D-66): `ADSENSE_CLIENT` e `ADSENSE_SLOT` (vazios = sem anúncios).

Backup (D-68): container `bagparser_backup`, um dump por dia em `backups/` (14 dias); restaurar com `bash scripts/restore-db.sh backups/ARQUIVO.dump` (use `--check` para só conferir). Pix (D-69): `MP_ACCESS_TOKEN` e `MP_WEBHOOK_SECRET` no `.env` (vazios = sem Pix). Alertas de e-mail (D-70) vão para `ADMIN_EMAILS`.

Telegram (D-75): `TELEGRAM_BOT_TOKEN` no `.env` da VPS (vazio = bot desligado); com `PUBLIC_URL` https a API registra o webhook sozinha no start (log `telegram: webhook`). **Local:** nunca usar o token do bot de produção com `TELEGRAM_POLLING=1` (o polling apaga o webhook dele); crie um bot de teste no BotFather para isso. Boletins/resumos: tabela `notify_targets`; o job roda dentro da API (a cada 5 min); `POST /api/admin/daily/run` força o envio do que estiver na hora. Avisos na hora, ranking da guild, doações (Stars/Pix) e retrospectiva: D-76 (`personalAlerts.ts`, `guildRanking.ts`, `retro.ts`; tabelas `boss_timers`, `guild_days`, `donations`). Textos e imagens do BotFather: `docs/telegram/`.

Conferir depois do deploy: `curl https://bagparser.com/api/health` → `{"ok":true,"redis":"ready"}`.
Logs: `docker compose -f docker-compose.yml -f docker-compose.npm.yml logs -f api`.

## Mapa rápido
- `frontend/src/core/` — lógica pura (parser, catálogo, NPCs, split de party, roteiro de venda, Rashid) com testes Vitest.
- `frontend/src/pages/` — páginas; `frontend/src/pwa.ts` + `frontend/public/sw.js` — PWA.
- `backend/src/` — API Fastify (SpawnRank, BossLedger, TibiaData).
- `backend/src/accounts.ts` + `auth.ts` + `migrate.ts` — login, sessões, painel admin, contador de visitas.
- `docs/` — SPEC, REQUIREMENTS, DECISIONS, STATUS, FANSITE (checklist do programa de fansites).

## Verificação visual (opcional)
Screenshots e teste offline podem ser feitos com Playwright usando o Chrome do sistema
(`chromium.launch({ executablePath: '/usr/bin/google-chrome' })`), contra `http://localhost:3080`
(localhost conta como contexto seguro, então o service worker funciona).
