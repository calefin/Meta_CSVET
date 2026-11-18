#!/usr/bin/env node
/**
 * DATA-04: builds shared/items.json and shared/npcs.json from TibiaWiki (tibia.fandom.com).
 *
 *   node scripts/import-tibiawiki.mjs            # writes shared/items.json + shared/npcs.json
 *   node scripts/import-tibiawiki.mjs --dry-run  # prints a summary only
 *
 * Sources (MediaWiki API, Infobox templates):
 *   - Items: pages in "Items Bought by NPCs" + "Creature Products" + marketable creature loot
 *     (Infobox Object: actualname, plural, npcvalue, weight, sellto, value, droppedby, marketable)
 *   - Imbuement materials: "astralsources" of pages in "Powerful Imbuements"
 *   - NPC cities: Infobox NPC (city, subarea) of every NPC that buys a catalog item
 * Manual entries (coins, items without a single wiki page) live in shared/items.overrides.json.
 * Destination rules: see docs/DECISIONS.md D-19 / D-20.
 */
import { readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const API = 'https://tibia.fandom.com/api.php';
const UA = 'BagParser-catalog-import/1.0 (https://github.com/calefin/bagparser)';
const DRY_RUN = process.argv.includes('--dry-run');

/** Pre-vocation islands: their NPCs are unreachable for main-continent characters. */
const UNREACHABLE_CITIES = new Set(['Rookgaard', 'Dawnport', 'Isle of Destiny']);
/** NPCs that move around: their city is shown from the in-app schedule / toggle instead. */
const TRAVELLING_NPCS = new Set(['Rashid', 'Yasir']);
const BLUE_DJINN = new Set(["Nah'Bob", 'Haroun']);
const GREEN_DJINN = new Set(['Alesar', 'Yaman']);
/** Market price must beat the NPC price by this much to raise "do not sell to NPC". */
const MARKET_RATIO = 1.5;
const MARKET_MIN_GAIN = 1000;

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function api(params) {
  const url = `${API}?${new URLSearchParams({ format: 'json', formatversion: '2', ...params })}`;
  for (let attempt = 1; ; attempt++) {
    try {
      const res = await fetch(url, { headers: { 'User-Agent': UA } });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const json = await res.json();
      await sleep(300); // be polite to the wiki
      return json;
    } catch (err) {
      if (attempt >= 4) throw err;
      await sleep(2000 * attempt);
    }
  }
}

/** All page titles of a category (namespace 0). */
async function categoryTitles(category) {
  const titles = [];
  let cont = {};
  do {
    const d = await api({ action: 'query', list: 'categorymembers', cmtitle: `Category:${category}`, cmnamespace: '0', cmlimit: '500', ...cont });
    titles.push(...d.query.categorymembers.map((m) => m.title));
    cont = d.continue ?? null;
  } while (cont);
  return titles;
}

/** Wikitext of many pages, 50 per request. Returns Map<requestedTitle, text>. */
async function pagesContent(titles, label) {
  const out = new Map();
  for (let i = 0; i < titles.length; i += 50) {
    const chunk = titles.slice(i, i + 50);
    const d = await api({ action: 'query', prop: 'revisions', rvprop: 'content', rvslots: 'main', redirects: '1', titles: chunk.join('|') });
    const alias = new Map();
    for (const n of d.query.normalized ?? []) alias.set(n.to, n.from);
    for (const r of d.query.redirects ?? []) alias.set(r.to, alias.get(r.from) ?? r.from);
    for (const p of d.query.pages) {
      const text = p.revisions?.[0]?.slots?.main?.content;
      if (text) out.set(alias.get(p.title) ?? p.title, text);
    }
    process.stderr.write(`\r${label}: ${Math.min(i + 50, titles.length)}/${titles.length}`);
  }
  process.stderr.write('\n');
  return out;
}

/** "| key = value" field of an infobox (single line; empty → undefined). */
function field(text, key) {
  const m = text.match(new RegExp(`^\\|[ \\t]*${key}[ \\t]*=[ \\t]*(.*)$`, 'm'));
  const v = m?.[1].trim();
  return v && v !== '?' && v !== '--' ? v : undefined;
}

function toNumber(v) {
  if (!v) return undefined;
  const m = v.replace(/[,\s]/g, '').match(/^-?\d+(?:\.\d+)?/);
  return m ? Number(m[0]) : undefined;
}

/** Wiki "value" (market estimate) like "4,000 - 6,000" or "1.300.000.000 - 1.500.000.000" → lower bound. */
function marketLow(v) {
  if (!v) return undefined;
  const first = v.split(/\s*[-–]\s*/)[0].replace(/[^\d.,]/g, '');
  if (!first) return undefined;
  // Thousands separators may be "," or "."; a single "." followed by 1–2 digits is a decimal
  const n = /^\d+\.\d{1,2}$/.test(first) ? Number(first) : Number(first.replace(/[.,]/g, ''));
  return Number.isFinite(n) && n > 0 ? n : undefined;
}

/** "Fiona, [[Yasir]], Haroun: 50, H.L.: 115" → [{ npc, price? }] */
function parseSellTo(v) {
  if (!v) return [];
  return v
    .replace(/\[\[([^\]|]+)(?:\|[^\]]*)?\]\]/g, '$1')
    .split(/[,;]/)
    .map((s) => s.trim())
    .filter(Boolean)
    .map((s) => {
      const [npc, price] = s.split(':').map((x) => x.trim());
      return { npc, price: toNumber(price) };
    })
    .filter((e) => e.npc && e.npc !== 'H.L.');
}

function chooseVendor(fullPriceNpcs) {
  const set = new Set(fullPriceNpcs);
  if (set.has('Rashid')) return 'Rashid';
  if ([...BLUE_DJINN].some((n) => set.has(n))) return 'Blue Djinn';
  if ([...GREEN_DJINN].some((n) => set.has(n))) return 'Green Djinn';
  if (fullPriceNpcs.some((n) => n !== 'Yasir')) return 'Other NPCs';
  if (set.has('Yasir')) return 'Yasir';
  return 'Market / Imbuement Alerts';
}

async function imbuementMaterials() {
  const titles = await categoryTitles('Powerful Imbuements');
  const pages = await pagesContent(titles, 'imbuements');
  const names = new Set();
  for (const text of pages.values()) {
    for (const part of (field(text, 'astralsources') ?? '').split(',')) {
      const name = part.split(':')[0].trim().toLowerCase();
      if (name) names.add(name);
    }
  }
  return names;
}

async function main() {
  const overrides = JSON.parse(await readFile(path.join(ROOT, 'shared/items.overrides.json'), 'utf8'));
  const imbuement = await imbuementMaterials();
  console.error(`imbuement materials: ${imbuement.size}`);

  const [bought, products, marketable] = await Promise.all([
    categoryTitles('Items Bought by NPCs'),
    categoryTitles('Creature Products'),
    categoryTitles('Marketable Items'),
  ]);
  const titles = [...new Set([...bought, ...products, ...marketable])].sort();
  console.error(`candidate pages: ${titles.length}`);
  const pages = await pagesContent(titles, 'items');

  const raw = [];
  for (const [title, text] of pages) {
    if (!/\{\{Infobox Object/i.test(text)) continue;
    if (field(text, 'pickupable') === 'no') continue;
    const name = (field(text, 'actualname') ?? title).toLowerCase();
    const npcValue = toNumber(field(text, 'npcvalue')) ?? 0;
    const sellTo = parseSellTo(field(text, 'sellto'));
    const dropped = /\{\{Dropped By\|[^}]*[A-Za-z]/.test(field(text, 'droppedby') ?? '');
    const isMarketable = field(text, 'marketable') === 'yes';
    // Keep what can show up in a Hunt Analyser loot list and has a sale route
    if (!(sellTo.length && npcValue > 0) && !(dropped && isMarketable)) continue;
    const itemId = Number((field(text, 'itemid') ?? '').split(/[,\s]+/)[0]) || undefined;
    raw.push({ title, name, npcValue, sellTo, text, isMarketable, itemId });
  }

  // NPC pages: validate buyer names and get their city
  const npcNames = [...new Set(raw.flatMap((r) => r.sellTo.map((s) => s.npc)))].sort();
  const npcPages = await pagesContent(npcNames, 'npcs');
  const npcs = {};
  for (const [name, text] of npcPages) {
    if (!/\{\{Infobox NPC/i.test(text)) continue;
    const city = field(text, 'city');
    const area = field(text, 'subarea');
    const cleanCity = city?.replace(/\[\[|\]\]/g, '');
    if (cleanCity && UNREACHABLE_CITIES.has(cleanCity)) continue;
    npcs[name] = TRAVELLING_NPCS.has(name)
      ? { city: '', travelling: true }
      : { city: cleanCity ?? '?', ...(area && area !== cleanCity ? { area } : {}) };
  }

  const items = new Map();
  const wikiTitles = new Map(); // item name → TibiaWiki page title (used by scripts/import-sprites.mjs)
  for (const r of raw) {
    const buyers = r.sellTo.filter((s) => npcs[s.npc]);
    let price = r.npcValue;
    let full = buyers.filter((s) => s.price === undefined || s.price >= r.npcValue).map((s) => s.npc);
    if (!full.length && buyers.length) {
      // nobody pays the listed value: use the best partial offer
      const best = buyers.reduce((a, b) => ((b.price ?? 0) > (a.price ?? 0) ? b : a));
      full = [best.npc];
      price = best.price ?? 0;
    }
    if (!full.length) price = 0;

    const low = marketLow(field(r.text, 'value'));
    const isImb = imbuement.has(r.name);
    const marketAlert = isImb || (r.isMarketable && low !== undefined && low >= price * MARKET_RATIO && low - price >= MARKET_MIN_GAIN);
    const plural = field(r.text, 'plural')?.toLowerCase();

    const entry = {
      name: r.name,
      npc_price: price,
      vendor: chooseVendor(full),
      weight: toNumber(field(r.text, 'weight')) ?? 0,
      ...(full.length ? { sell_to: [...new Set(full)].sort() } : {}),
      ...(plural && plural !== r.name ? { plural } : {}),
      ...(isImb ? { imbuement: true } : {}),
      ...(/Creature Products/.test(r.text) ? { task_product: true } : {}),
      ...(marketAlert ? { market_alert: true } : {}),
    };
    const prev = items.get(entry.name);
    if (!prev || entry.npc_price > prev.npc_price) {
      items.set(entry.name, entry);
      wikiTitles.set(entry.name, r.itemId ? { title: r.title, id: r.itemId } : { title: r.title });
    }
  }

  for (const o of overrides.items) items.set(o.name, o); // overrides replace the wiki entry

  const list = [...items.values()].sort((a, b) => a.name.localeCompare(b.name));
  const byVendor = list.reduce((acc, i) => ((acc[i.vendor] = (acc[i.vendor] ?? 0) + 1), acc), {});
  console.error(`items: ${list.length}`, byVendor);
  console.error(`npcs: ${Object.keys(npcs).length}`);
  if (DRY_RUN) return;

  const today = new Date().toISOString().slice(0, 10);
  const itemsJson =
    '{\n' +
    `  "_comment": ${JSON.stringify(`Generated by scripts/import-tibiawiki.mjs from TibiaWiki on ${today}. Do not edit by hand: change shared/items.overrides.json and re-run. Destination rules: docs/DECISIONS.md D-19/D-20.`)},\n` +
    '  "items": [\n' +
    list.map((i) => '    ' + JSON.stringify(i)).join(',\n') +
    '\n  ]\n}\n';
  await writeFile(path.join(ROOT, 'shared/items.json'), itemsJson);

  const sortedNpcs = Object.fromEntries(Object.entries(npcs).sort(([a], [b]) => a.localeCompare(b)));
  await writeFile(
    path.join(ROOT, 'shared/npcs.json'),
    JSON.stringify({ _comment: `Generated by scripts/import-tibiawiki.mjs from TibiaWiki on ${today}.`, npcs: sortedNpcs }, null, 1) + '\n',
  );
  const wikiMap = Object.fromEntries([...wikiTitles].filter(([n]) => items.has(n)).sort(([a], [b]) => a.localeCompare(b)));
  await writeFile(
    path.join(ROOT, 'shared/item-wiki.json'),
    JSON.stringify({ _comment: `Generated by scripts/import-tibiawiki.mjs on ${today}: item name → TibiaWiki page title and client item id.`, titles: wikiMap }, null, 1) + '\n',
  );
  console.error('wrote shared/items.json, shared/npcs.json and shared/item-wiki.json');
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
