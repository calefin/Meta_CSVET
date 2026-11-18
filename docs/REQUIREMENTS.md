# BagParser — Requirements

Derived from `docs/SPEC.md` (original technical spec) plus decisions taken during implementation
(see `docs/DECISIONS.md`). Status of each item is tracked in `docs/STATUS.md`.

IDs are stable: reference them in commits/PRs (e.g. `FR-CORE-03`).

---

## 1. Product scope

Web companion for Tibia players with four modules:

| Module | Purpose |
|---|---|
| **BagParser Core** | Paste a Hunt/Party Analyser log → loot routed by NPC destination, profit/XP per hour, market alerts |
| **SpawnRank** | Rank hunting zones for a creature (tasks, weekly bounty) by density and other factors |
| **BossLedger** | Crowdsourced boss loot logs → drop counts and drop rates |
| **QuickHunts** | Short, indexable text cheat sheets for hunting places |

---

## 2. Functional requirements

### 2.1 BagParser Core (client-side)
- **FR-CORE-01** Parsing runs entirely in the browser; no log text is sent to the server.
- **FR-CORE-02** Parse immediately on paste/typing (no submit button required).
- **FR-CORE-03** Extract session metrics: duration (`Session: HH:MMh`), XP Gain, Loot, Supplies, Balance; compute XP/h and Balance/h. Duration 0 → treated as 1 minute; missing duration → 60 minutes.
- **FR-CORE-04** Metric extraction must be line-anchored (e.g. `Raw XP Gain` must not be read as `XP Gain`).
- **FR-CORE-05** Parse the looted items block (`Looted Items:` in the real client; `Loot Items:` accepted) with counts (`12x name`), articles (`a`/`an`) and plurals normalised to the catalog singular name.
- **FR-CORE-06** Parse the `Killed Monsters:` block (count per creature).
- **FR-CORE-07** Route each recognised item to a destination: Rashid, Yasir, Green Djinn, Blue Djinn, Market / Imbuement Alerts, Other NPCs, Coins. Imbuement items always go to *Market / Imbuement Alerts*.
- **FR-CORE-08** Per destination: item list, subtotal gold, subtotal weight (oz). Show total load.
- **FR-CORE-09** List unrecognised items separately.
- **FR-CORE-10** Clicking an item name copies it to the clipboard (for depot search).
- **FR-CORE-11** Show where Rashid is today, using the server-save day boundary (10:00 Europe/Berlin). Schedule: Mon Svargrond, Tue Liberty Bay, Wed Port Hope, Thu Ankrahmun, Fri Darashia, Sat Edron, Sun Carlin.
- **FR-CORE-12** Yasir presence is not knowable automatically: user toggles "Yasir is here today"; the choice is remembered until the next server save. Show active/inactive message accordingly.
- **FR-CORE-13** Party Hunt Analyser logs: show session totals, per-player balance and the loot-split transfers (who pays whom) so everyone ends with an equal share.
- **FR-CORE-14** Provide a "load sample log" button for first-time users.

### 2.2 SpawnRank
- **FR-SR-01** User selects a creature; API returns zones ranked by
  `FinalScore = D×0.50 + S×0.25 + P×0.15 − A×0.10`
  - D = density tier (1–5) of the creature in the zone
  - S = synergy (0–5): other task/bounty creatures in the same zone (capped at 5)
  - P = zone profitability factor (1–5)
  - A = zone access friction (0–5)
- **FR-SR-02** Show zone, city, min level, vocation, access quest, the score and its components.
- **FR-SR-03** Highlight creatures that are this week's bounty.

### 2.3 BossLedger
- **FR-BL-01** Accept pasted server log lines `Loot of <Boss>: item, item, ...` (optional `HH:MM:SS` prefix; trailing `.` optional).
- **FR-BL-02** Normalise drops: articles `a`/`an`, multiples `2x name` and `2 name`, plurals → catalog singular.
- **FR-BL-03** Deduplicate with `SHA256(BossName + UTC YYYY-MM-DD-HH + SortedItemsList)` stored in `boss_drop_logs.payload_hash` (unique).
- **FR-BL-04** Rate limit: 1 entry per boss per player fingerprint per boss cooldown (default 20 h). Fingerprint = salted SHA256 of IP + User-Agent; only the hash is stored.
- **FR-BL-05** Update `boss_drop_aggregated_stats` per recognised item; unknown items are kept only in `raw_loot_line`.
- **FR-BL-06** Stats page per boss: number of recorded kills, per item total drops and drop rate (drops / kills). Cached in Redis, invalidated on new entry.
- **FR-BL-07** Flag `has_rare_item` when any drop has a market alert flag or NPC value ≥ 10,000 gp.

### 2.4 QuickHunts
- **FR-QH-01** Articles authored as Markdown files in the repo (`frontend/src/content/quickhunts/*.md`) with a small front-matter (title, city, level, vocation).
- **FR-QH-02** List page + article page; may embed creator route links (YouTube/maps).
- **FR-QH-03** (Later) Pre-rendered HTML for search engine indexing.

### 2.5 TibiaData integration (https://tibiadata.com, API v4)
- **FR-TD-01** Show today's boosted creature (`/v4/creatures`) on SpawnRank and flag it in the creature list.
- **FR-TD-02** Show today's boosted boss (`/v4/boostablebosses`) on BossLedger and flag it in the boss list.
- **FR-TD-03** Link `creatures` rows to TibiaData race ids and library images (`creatures.race`, `creatures.image_url`), synced on API start.
- **FR-TD-04** Cache TibiaData responses in Redis until the next server save (+2 min); the creature library for 7 days. Requests use a generic User-Agent (`BagParser/0.1 (+repo URL)`).
- **FR-TD-05** (Later) Use `/v4/creature/{race}` (HP, XP, weaknesses, loot list) on SpawnRank/QuickHunts pages.
- Note: TibiaData has **no item prices/NPC vendors** — the item catalog still needs TibiaWiki (DATA-02/04).

### 2.6 Internationalisation
- **FR-I18N-01** UI languages: English (`en`), Portuguese (`pt-BR`), Polish (`pl`), Spanish (`es`).
- **FR-I18N-02** Resolution priority: `?lang=` URL parameter > `localStorage` (`bagparser_locale`) > `navigator.language` > `en`.
- **FR-I18N-03** Manual language selector persists the choice to `localStorage`.
- **FR-I18N-04** Item names, creature names and in-game log content always stay in English.

---

## 3. Non-functional requirements

- **NFR-01** Deployment via Docker Compose, isolated network `bagparser_net`, named volumes, no DB/Redis ports exposed to the host.
- **NFR-02** Only the web container is exposed, bound to `127.0.0.1:3080` (configurable `WEB_PORT`), to sit behind the host reverse proxy (Nginx / NPM / Traefik / Caddy).
- **NFR-03** Stack: Postgres 16 Alpine, Redis 7 Alpine, Node.js 20 API, static frontend served by Nginx.
- **NFR-04** Secrets via `.env` (`DB_PASSWORD`, `FINGERPRINT_SALT`); never committed.
- **NFR-05** Parser has automated tests (Vitest) using real-format sample logs.
- **NFR-06** Works on mobile widths; dark theme.
- **NFR-07** Privacy: no personal data stored; player fingerprints only as salted hashes.

---

## 4. Data

- **DATA-01** Item catalog lives in `shared/items.json` (single source of truth). The frontend bundles it for the parser; the API upserts it into `items` on every start.
- **DATA-02** Seed values (prices, weights, vendors) are provisional and **must be verified against TibiaWiki** before launch.
- **DATA-03** Zones/creatures/bosses in `init.sql` are sample data for development.
- **DATA-04** (Later) Import script to build the catalog from TibiaWiki.
