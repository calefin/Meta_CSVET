# BagParser — Product Architecture & Technical Specification

> **Platform Overview:** High-performance web companion for Tibia players, focused on post-hunt loot routing, spawn density tracking, crowdsourced boss drop rates, and concise textual cheat sheets.
> **Core Decisions:** Client-side parsing (zero server overhead for text analytics), lightweight multi-container Docker deployment (isolated from other VPS services), and automatic client-side multilingual localization (i18n).

---

## 1. Domain, Branding & Internationalization (i18n)

### 1.1 Brand Identity
* **Product Name:** BagParser
* **Sub-modules:**
  * **BagParser Core:** Clipboard-to-Depot loot decomposition engine.
  * **SpawnRank:** Creature task, weekly bounty and density recommender.
  * **BossLedger:** Crowdsourced boss drops, rarity tiers, and drop-rate analytics.
  * **QuickHunts:** Textual, indexable cheat-sheet blog with embedded creator routes.

### 1.2 Automatic Multilingual Support (i18n)
The Tibia player base is primarily divided across English, Portuguese, Polish, and Spanish.

#### Detection Strategy:
1. **Resolution Priority:** `URL Parameter (?lang=)` > `localStorage` > `navigator.language` > Fallback to `en-US`.
2. **Canonical Data Handling:** In-game logs and item names always remain in English (matching Tibia client standards). UI labels, NPC hints, and market warnings switch automatically to the detected language.

---

## 2. Docker & Multi-App VPS Infrastructure

Since the host VPS runs multiple applications, BagParser is fully containerized with private internal networking, isolated volumes, and configurable exposed ports to integrate smoothly with the host's existing reverse proxy (Host Nginx, Traefik, NPM, or Caddy).

```
                      [ Host Reverse Proxy (Nginx / NPM / Traefik) ]
                                            │
                                ┌───────────┴───────────┐ (Reverse Proxy to Port 3080)
                                ▼
  ┌──────────────────────────────────────────────────────────────┐
  │                 DOCKER NETWORK: bagparser_net                │
  │                                                              │
  │  ┌───────────────────────┐        ┌───────────────────────┐  │
  │  │  bagparser_web        │───────▶│  bagparser_api        │  │
  │  │  (Static / Nginx)     │        │  (Node.js / Go)       │  │
  │  │  Port 3080:80         │        │  Internal Port 3000   │  │
  │  └───────────────────────┘        └───────────┬───────────┘  │
  │                                               │              │
  │                     ┌─────────────────────────┴────────────┐ │
  │                     ▼                                      ▼ │
  │        ┌─────────────────────────┐            ┌───────────┐  │
  │        │  bagparser_db           │            │  bag_redis│  │
  │        │  (Postgres 16 Alpine)   │            │  (Redis)  │  │
  │        └─────────────────────────┘            └───────────┘  │
  └──────────────────────────────────────────────────────────────┘
```

### 2.1 `docker-compose.yml`

```yaml
version: '3.8'

services:
  # 1. Frontend Web App (Nginx Alpine serving static build)
  web:
    container_name: bagparser_web
    build:
      context: ./frontend
      dockerfile: Dockerfile
    restart: unless-stopped
    ports:
      # Exposes port 3080 to the host (bound to localhost for security)
      - "127.0.0.1:3080:80"
    depends_on:
      - api
    networks:
      - bagparser_net

  # 2. Backend API (Session recording & BossLedger)
  api:
    container_name: bagparser_api
    build:
      context: ./backend
      dockerfile: Dockerfile
    restart: unless-stopped
    environment:
      NODE_ENV: production
      PORT: 3000
      DATABASE_URL: postgres://bagparser_user:${DB_PASSWORD:-secure_db_pass}@db:5432/bagparser_db
      REDIS_URL: redis://redis:6379
    depends_on:
      db:
        condition: service_healthy
      redis:
        condition: service_started
    networks:
      - bagparser_net

  # 3. Database (PostgreSQL isolated from host)
  db:
    container_name: bagparser_db
    image: postgres:16-alpine
    restart: unless-stopped
    environment:
      POSTGRES_USER: bagparser_user
      POSTGRES_PASSWORD: ${DB_PASSWORD:-secure_db_pass}
      POSTGRES_DB: bagparser_db
    volumes:
      - bagparser_pgdata:/var/lib/postgresql/data
      - ./init.sql:/docker-entrypoint-initdb.d/init.sql:ro
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U bagparser_user -d bagparser_db"]
      interval: 5s
      timeout: 5s
      retries: 5
    networks:
      - bagparser_net

  # 4. In-Memory Cache (Drop rate aggregations and rate limiting)
  redis:
    container_name: bagparser_redis
    image: redis:7-alpine
    restart: unless-stopped
    command: ["redis-server", "--appendonly", "yes"]
    volumes:
      - bagparser_redisdata:/data
    networks:
      - bagparser_net

volumes:
  bagparser_pgdata:
  bagparser_redisdata:

networks:
  bagparser_net:
    driver: bridge
```

### 2.2 Host Nginx Configuration Snippet (Connecting to BagParser)

On your VPS's main Nginx (outside Docker), simply add this server block or route to point your chosen domain/subdomain:

```nginx
server {
    server_name bagparser.seudominio.com; # ou subdomínio

    location / {
        proxy_pass http://127.0.0.1:3080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

---

## 3. Database Schema (DDL)

```sql
CREATE TABLE items (
    id SERIAL PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    slug VARCHAR(120) NOT NULL,
    npc_price INT NOT NULL DEFAULT 0,
    npc_vendor_category VARCHAR(50) NOT NULL,
    weight_oz DECIMAL(6, 2) NOT NULL DEFAULT 0.00,
    is_imbuement BOOLEAN NOT NULL DEFAULT FALSE,
    is_task_creature_product BOOLEAN NOT NULL DEFAULT FALSE,
    market_priority_alert BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_items_name ON items(LOWER(name));
CREATE INDEX idx_items_category ON items(npc_vendor_category);

CREATE TABLE hunting_zones (
    id SERIAL PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    city VARCHAR(80) NOT NULL,
    recommended_min_level INT NOT NULL,
    recommended_vocation VARCHAR(20) DEFAULT 'ALL',
    access_quest VARCHAR(200) NULL,
    map_coordinates VARCHAR(100) NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE creatures (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    grizzly_task_points INT DEFAULT 0,
    is_weekly_bounty BOOLEAN DEFAULT FALSE
);

CREATE TABLE spawn_densities (
    id SERIAL PRIMARY KEY,
    hunting_zone_id INT REFERENCES hunting_zones(id) ON DELETE CASCADE,
    creature_id INT REFERENCES creatures(id) ON DELETE CASCADE,
    density_tier INT NOT NULL CHECK (density_tier BETWEEN 1 AND 5),
    spawn_distribution_note VARCHAR(255) NULL
);

CREATE INDEX idx_spawn_density ON spawn_densities(creature_id, density_tier DESC);

CREATE TABLE bosses (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    cooldown_hours INT DEFAULT 20
);

CREATE TABLE boss_drop_logs (
    id BIGSERIAL PRIMARY KEY,
    boss_id INT REFERENCES bosses(id) ON DELETE CASCADE,
    player_identifier_hash VARCHAR(64) NULL,
    payload_hash VARCHAR(64) NOT NULL UNIQUE,
    drop_timestamp TIMESTAMP WITH TIME ZONE NOT NULL,
    raw_loot_line TEXT NOT NULL,
    total_npc_value INT NOT NULL DEFAULT 0,
    has_rare_item BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE boss_drop_aggregated_stats (
    boss_id INT REFERENCES bosses(id) ON DELETE CASCADE,
    item_id INT REFERENCES items(id) ON DELETE CASCADE,
    total_recorded_drops BIGINT NOT NULL DEFAULT 0,
    PRIMARY KEY(boss_id, item_id)
);
```

---

## 4. Internationalization (i18n) Engine

### 4.1 Dictionary Definitions (`src/i18n/translations.json`)

```json
{
  "en": {
    "app_title": "BagParser - Intelligent Tibia Loot Router",
    "paste_placeholder": "Paste your Party Hunt or Session Analyser log here (Ctrl+V)...",
    "session_summary": "Session Overview",
    "duration": "Duration",
    "profit_hour": "Profit / Hour",
    "xp_hour": "XP / Hour",
    "rashid_location": "Today is {{day}}: Rashid is in {{city}}",
    "yasir_alert_active": "Yasir is currently docking in Oriental ships!",
    "yasir_alert_inactive": "Yasir status unknown or not present today. Store items in depot.",
    "warning_market": "DO NOT SELL TO NPC! High Market Value / Imbuement item.",
    "copy_name_tooltip": "Copy item name for Depot Search",
    "total_weight": "Total Load"
  },
  "pt-BR": {
    "app_title": "BagParser - Roteador Inteligente de Loot do Tibia",
    "paste_placeholder": "Cole o log do Party Hunt ou Session Analyser aqui (Ctrl+V)...",
    "session_summary": "Resumo da Sessão",
    "duration": "Duração",
    "profit_hour": "Lucro / Hora",
    "xp_hour": "XP / Hora",
    "rashid_location": "Hoje é {{day}}: Rashid está em {{city}}",
    "yasir_alert_active": "Yasir está atracado nos portos hoje!",
    "yasir_alert_inactive": "Yasir ausente ou não confirmado hoje. Guarde os itens no depot.",
    "warning_market": "NÃO VENDA NO NPC! Item de Imbuement com alto valor no Market.",
    "copy_name_tooltip": "Copiar nome para buscar no Depot",
    "total_weight": "Cap Total Necessário"
  },
  "pl": {
    "app_title": "BagParser - Inteligentny Rozdzielacz Łupu w Tibii",
    "paste_placeholder": "Wklej log z Party Hunt lub Session Analysera tutaj (Ctrl+V)...",
    "session_summary": "Podsumowanie Sesji",
    "duration": "Czas trwania",
    "profit_hour": "Zysk / Godzinę",
    "xp_hour": "XP / Godzinę",
    "rashid_location": "Dziś jest {{day}}: Rashid znajduje się w {{city}}",
    "yasir_alert_active": "Yasir cumuje dzisiaj w dokach!",
    "yasir_alert_inactive": "Yasira nie ma lub brak potwierdzenia. Zachowaj w depozycie.",
    "warning_market": "NIE SPRZEDAWAJ U NPC! Wysoka wartość w Markecie / Imbuing.",
    "copy_name_tooltip": "Kopiuj nazwę do schowka",
    "total_weight": "Łączna Waga (Oz)"
  }
}
```

### 4.2 Auto-Detection Module (`src/i18n/detector.ts`)

```typescript
export type SupportedLocale = 'en' | 'pt-BR' | 'pl';

export function resolveUserLocale(): SupportedLocale {
  const urlParams = new URLSearchParams(window.location.search);
  const paramLang = urlParams.get('lang');
  if (paramLang && ['en', 'pt-BR', 'pl'].includes(paramLang)) {
    return paramLang as SupportedLocale;
  }

  const stored = localStorage.getItem('bagparser_locale');
  if (stored && ['en', 'pt-BR', 'pl'].includes(stored)) {
    return stored as SupportedLocale;
  }

  const browserLang = navigator.language;
  if (browserLang.startsWith('pt')) return 'pt-BR';
  if (browserLang.startsWith('pl')) return 'pl';

  return 'en';
}
```

---

## 5. Core Engine: `BagParser` Client-Side Parser

Runs entirely in the browser to deliver immediate parsing upon clipboard paste.

```typescript
export interface SessionMetrics {
  durationMinutes: number;
  xpTotal: number;
  xpHour: number;
  lootTotal: number;
  suppliesTotal: number;
  balanceNet: number;
  balanceHour: number;
}

export interface ParsedItemRow {
  name: string;
  count: number;
  unitNpcPrice: number;
  totalValue: number;
  weightOz: number;
  isImbuement: boolean;
}

export interface RoutingOutput {
  metrics: SessionMetrics;
  destinations: Record<string, {
    items: ParsedItemRow[];
    subtotalGps: number;
    subtotalWeightOz: number;
  }>;
  unrecognizedItems: string[];
}

export function parseTibiaSession(rawText: string, lookupDb: Record<string, any>): RoutingOutput {
  const cleanInt = (v?: string) => v ? parseInt(v.replace(/[,.]/g, ''), 10) : 0;

  const sessionMatch = rawText.match(/Session:\s*(\d{1,2}):(\d{2})h/i);
  let durationMinutes = 60;
  if (sessionMatch) {
    durationMinutes = parseInt(sessionMatch[1], 10) * 60 + parseInt(sessionMatch[2], 10);
    if (durationMinutes === 0) durationMinutes = 1;
  }

  const xpMatch = rawText.match(/XP Gain:\s*([\d,.]+)/i);
  const lootMatch = rawText.match(/Loot:\s*([\d,.]+)/i);
  const suppliesMatch = rawText.match(/Supplies:\s*([\d,.]+)/i);
  const balanceMatch = rawText.match(/Balance:\s*(-?[\d,.]+)/i);

  const xpTotal = cleanInt(xpMatch?.[1]);
  const lootTotal = cleanInt(lootMatch?.[1]);
  const suppliesTotal = cleanInt(suppliesMatch?.[1]);
  const balanceNet = cleanInt(balanceMatch?.[1]);

  const metrics: SessionMetrics = {
    durationMinutes,
    xpTotal,
    xpHour: Math.round((xpTotal / durationMinutes) * 60),
    lootTotal,
    suppliesTotal,
    balanceNet,
    balanceHour: Math.round((balanceNet / durationMinutes) * 60),
  };

  const lootBlockMatch = rawText.match(/(?:Loot Items|Loot):\s*\n([\s\S]*?)(?:\n\s*[A-Z][a-zA-Z\s]+:|$)/i);
  const destinations: RoutingOutput['destinations'] = {
    'Rashid': { items: [], subtotalGps: 0, subtotalWeightOz: 0 },
    'Yasir': { items: [], subtotalGps: 0, subtotalWeightOz: 0 },
    'Green Djinn': { items: [], subtotalGps: 0, subtotalWeightOz: 0 },
    'Blue Djinn': { items: [], subtotalGps: 0, subtotalWeightOz: 0 },
    'Market / Imbuement Alerts': { items: [], subtotalGps: 0, subtotalWeightOz: 0 },
    'Other NPCs': { items: [], subtotalGps: 0, subtotalWeightOz: 0 }
  };

  const unrecognizedItems: string[] = [];
  if (!lootBlockMatch) return { metrics, destinations, unrecognizedItems };

  const lines = lootBlockMatch[1].split('\n').map(l => l.trim()).filter(Boolean);
  const itemLineRegex = /^(?:(\d+)x\s+|an?\s+)?(.+)$/i;

  for (const line of lines) {
    const match = line.match(itemLineRegex);
    if (!match) continue;

    const count = match[1] ? parseInt(match[1], 10) : 1;
    const rawItemName = match[2].trim();
    const normalizedKey = rawItemName.toLowerCase();

    const info = lookupDb[normalizedKey];
    if (!info) {
      unrecognizedItems.push(`${count}x ${rawItemName}`);
      continue;
    }

    const itemTotalValue = info.npc_price * count;
    const itemTotalWeight = Number((info.weight * count).toFixed(2));

    const itemRecord: ParsedItemRow = {
      name: info.name,
      count,
      unitNpcPrice: info.npc_price,
      totalValue: itemTotalValue,
      weightOz: itemTotalWeight,
      isImbuement: !!info.is_imbuement
    };

    let targetCategory = info.vendor_category || 'Other NPCs';
    if (itemRecord.isImbuement) {
      targetCategory = 'Market / Imbuement Alerts';
    }

    if (!destinations[targetCategory]) {
      destinations[targetCategory] = { items: [], subtotalGps: 0, subtotalWeightOz: 0 };
    }

    destinations[targetCategory].items.push(itemRecord);
    destinations[targetCategory].subtotalGps += itemTotalValue;
    destinations[targetCategory].subtotalWeightOz += itemTotalWeight;
  }

  return { metrics, destinations, unrecognizedItems };
}
```

---

## 6. SpawnRank Algorithm (Task & Density Engine)

Calculation used to score hunting locations:

$$\text{FinalScore} = (D \times 0.50) + (S \times 0.25) + (P \times 0.15) - (A \times 0.10)$$

* **$D$ (Density Tier, 1 to 5):** Creature frequency in the area.
* **$S$ (Synergy Score, 0 to 5):** Secondary task/bounty creatures present in the same run.
* **$P$ (Profitability Factor, 1 to 5):** Historic average profit per kill.
* **$A$ (Access Friction, 0 to 5):** Quest locking penalty (0 = open access, 5 = long quest chains).

---

## 7. BossLedger: Log Regex & Ingestion

### 7.1 Server Log Regex
* **Log Pattern:** `/Loot of ([A-Za-z\s'-]+):\s*(.+)\.?$/i`
* **Parsing:** Captures boss name and comma-separated drops, normalizing singular prefixes (`a`, `an`) and multiples (`2x`).

### 7.2 Ingestion & Deduplication
$$\text{Hash} = \text{SHA256}(\text{BossName} + \text{UTCDate(YYYY-MM-DD-HH)} + \text{SortedItemsList})$$

Rate limiting allows 1 entry per boss per player fingerprint every 20 hours to prevent duplicate entries from party kills.