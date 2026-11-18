# BagParser

Web companion for Tibia players:

- **BagParser**: paste your Hunt / Party Hunt Analyser log and see which NPC buys each item, profit per hour, market/imbuement alerts and the party loot split. Parsing runs entirely in the browser.
- **SpawnRank**: best hunting places for a creature (density, task synergy, profit, access).
- **BossLedger**: community-sourced boss drop rates.
- **QuickHunts**: short hunting cheat sheets.

UI languages: English, Português (BR), Español, Polski.

Installable as an app (PWA) on phone and desktop; the loot parser works offline.

Specification and project docs live in [`docs/`](docs/): `SPEC.md`, `REQUIREMENTS.md`, `DECISIONS.md`, `STATUS.md`.

## Stack

| Part | Tech |
|---|---|
| Frontend | Vite + React 18 + TypeScript, served by Nginx (`frontend/`) |
| API | Node.js 20 + Fastify + PostgreSQL + Redis (`backend/`) |
| Item catalog | `shared/items.json` + `shared/npcs.json`, generated from TibiaWiki (upserted into the DB on API start) |
| External data | [TibiaData API v4](https://tibiadata.com) (boosted creature/boss, creature images) |

## Local development

Requirements: Node.js 20+, Docker (for Postgres and Redis).

```bash
# 1. Database and cache
docker compose up -d db redis
# db/redis are not published to the host by default; for local API dev, either run the
# full stack (see below) or temporarily add "ports" to those services.

# 2. API (http://localhost:3000)
cd backend
npm install
DATABASE_URL=postgres://bagparser_user:secure_db_pass@localhost:5432/bagparser_db \
REDIS_URL=redis://localhost:6379 \
TRUST_PROXY_HOPS=1 \
npm run dev

# 3. Frontend (http://localhost:5173, proxies /api to :3000)
cd frontend
npm install
npm run dev
```

The parser page works without the API; SpawnRank, BossLedger and the boosted banners need it.

### Tests

```bash
cd frontend && npm test   # parser, routing, party split, Rashid schedule
cd backend && npm test    # boss log parsing, hashing, SpawnRank score, TibiaData helpers
```

### Updating the item catalog

Prices, buyer NPCs (with city), weights and imbuement flags come from [TibiaWiki](https://tibia.fandom.com):

```bash
node scripts/import-tibiawiki.mjs --dry-run   # summary only
node scripts/import-tibiawiki.mjs             # rewrites shared/items.json and shared/npcs.json
```

Takes about 1.5 minutes. Do not edit `shared/items.json` by hand: put manual entries in `shared/items.overrides.json`. Routing rules are in `docs/DECISIONS.md` (D-19, D-20). Restart the API after updating so it re-seeds the database.

### QuickHunts articles

Add a Markdown file to `frontend/src/content/quickhunts/<slug>.md`:

```markdown
---
title: Darashia Dragon Lair
city: Darashia
level: 60+
vocation: All
---

Article body in Markdown.
```

It is bundled at build time and served at `/quickhunts/<slug>`.

## Running the full stack with Docker

```bash
cp .env.example .env   # then change DB_PASSWORD and FINGERPRINT_SALT
docker compose up --build -d
curl http://127.0.0.1:3080/api/health
```

| Variable | Default | Purpose |
|---|---|---|
| `DB_PASSWORD` | `secure_db_pass` | PostgreSQL password |
| `FINGERPRINT_SALT` | `change_me` | Salt for the anonymous player fingerprint (BossLedger rate limit) |
| `WEB_PORT` | `3080` | Host port, bound to `127.0.0.1` only |
| `TRUST_PROXY_HOPS` | `2` | Reverse proxies in front of the API. `2` = host Nginx + web container; use `1` without a host proxy |

`init.sql` runs only when the Postgres volume is created. To reset the database: `docker compose down -v`.

## Deploying on a VPS

### With Nginx Proxy Manager (production: bagparser.com)
NPM runs in its own container, so it cannot reach `127.0.0.1:3080` on the host. `docker-compose.npm.yml` attaches the web container to the NPM network (`NPM_NETWORK` in `.env`, `apps-network` on our VPS).

The repository is private, so the VPS reads it with a read-only **deploy key** (GitHub → Settings → Deploy keys):

```bash
# first deploy (as root on the VPS)
ssh-keygen -t ed25519 -f ~/.ssh/bagparser_deploy -N "" -C "vps-bagparser"
cat ~/.ssh/bagparser_deploy.pub          # add it as a deploy key, no write access
ssh-keyscan -t ed25519 github.com >> ~/.ssh/known_hosts
GIT_SSH_COMMAND="ssh -i ~/.ssh/bagparser_deploy -o IdentitiesOnly=yes"   git clone git@github.com:calefin/bagparser.git /opt/apps/bagparser
git -C /opt/apps/bagparser config core.sshCommand "ssh -i ~/.ssh/bagparser_deploy -o IdentitiesOnly=yes"
cd /opt/apps/bagparser && bash deploy.sh

# updates
cd /opt/apps/bagparser && bash deploy.sh
```

`deploy.sh` clones to `/opt/apps/bagparser`, creates `.env` with random `DB_PASSWORD`/`FINGERPRINT_SALT`, detects the NPM network, runs `docker compose -f docker-compose.yml -f docker-compose.npm.yml up -d --build` and checks `/api/health`.

Then in NPM (`:81`) → Proxy Hosts → Add: domains `bagparser.com`, `www.bagparser.com`; scheme `http`; forward host `bagparser_web`; port `80`; Block Common Exploits on, **Cache Assets off** (the app sets its own cache headers; the service worker needs `no-cache`). SSL tab: request a Let's Encrypt certificate, Force SSL, HTTP/2. The domain's A records must point to the VPS first, or Let's Encrypt validation fails.

### With Nginx on the host
1. Clone the repo, create `.env` with strong values, run `docker compose up --build -d`.
2. Point the host's Nginx (outside Docker) at the web container:

```nginx
server {
    server_name bagparser.example.com;

    location / {
        proxy_pass http://127.0.0.1:3080;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

3. Add TLS (e.g. `certbot --nginx -d bagparser.example.com`). HTTPS is required for the app to be installable (PWA) and for the service worker.

In both setups the web container proxies `/api/` to the API internally, so the proxy only needs one upstream. Keep `TRUST_PROXY_HOPS=2` (outer proxy + web container); a wrong value either lets clients spoof their IP or makes every player share one rate-limit bucket.

## Legal

Tibia is a trademark of CipSoft GmbH. BagParser is a fan project, not affiliated with CipSoft.
