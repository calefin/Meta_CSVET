#!/usr/bin/env node
/**
 * Creature stats for the hunt analyser (D-61) from TibiaWiki (CC BY-SA): hit points, experience, armor,
 * mitigation, damage taken per element (%), bestiary class and difficulty, speed. Used by the creature analysis
 * and the charm contribution estimate (no runtime calls to the wiki).
 *
 * Usage: node scripts/import-creatures.mjs [--dry-run]     (needs internet, ~1 min)
 * Output: shared/creatures.json  { _comment, creatures: [{ name, hp, exp, arm, mit, cls, lvl, boss, spd, el: {physical: 100, …}, dmg: {physical: 290, earth: 172}, out: {physical: 50, death: 35} }] }
 *         shared/creature-wiki.json (D-80)  { _comment, creatures: { slug: { name, title, ab, loot, tr, … } } }: abilities, drops, traits and notes, served by the API one creature at a time
 */
import { writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { field } from './import-soulcores.mjs';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const API = 'https://tibia.fandom.com/api.php';
const UA = 'BagParser-catalog-import/1.0 (https://github.com/calefin/bagparser)';
const DRY_RUN = process.argv.includes('--dry-run');
const ELEMENTS = { physical: 'physicalDmgMod', earth: 'earthDmgMod', fire: 'fireDmgMod', death: 'deathDmgMod', energy: 'energyDmgMod', holy: 'holyDmgMod', ice: 'iceDmgMod', lifedrain: 'hpDrainDmgMod', drown: 'drownDmgMod', healing: 'healMod' };

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function api(params) {
  const url = `${API}?${new URLSearchParams({ format: 'json', formatversion: '2', ...params })}`;
  for (let attempt = 1; ; attempt++) {
    try {
      const res = await fetch(url, { headers: { 'User-Agent': UA } });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const json = await res.json();
      await sleep(300);
      return json;
    } catch (err) {
      if (attempt >= 4) throw err;
      await sleep(2000 * attempt);
    }
  }
}

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

async function wikitexts(titles) {
  const out = new Map();
  for (let i = 0; i < titles.length; i += 50) {
    const d = await api({ action: 'query', prop: 'revisions', rvprop: 'content', rvslots: 'main', titles: titles.slice(i, i + 50).join('|') });
    for (const p of d.query.pages ?? []) {
      const text = p.revisions?.[0]?.slots?.main?.content;
      if (text) out.set(p.title, text);
    }
    process.stdout.write('.');
  }
  return out;
}

/**
 * D-62: which elements the creature hits with, from "{{Max Damage|physical=290|earth=172|summons=880}}".
 * A plain number ("245+") is melee only → physical. Summons are not the creature's own damage.
 */
export function maxDamage(v) {
  if (!v) return null;
  const tpl = v.match(/\{\{\s*Max Damage\s*\|([^}]*)\}\}/i);
  const out = {};
  if (tpl) {
    for (const part of tpl[1].split('|')) {
      const [k, val] = part.split('=').map((x) => x?.trim().toLowerCase());
      const n = Number((val ?? '').replace(/[^\d]/g, ''));
      if (!k || !n || k === 'summons' || k === 'total') continue;
      const key = k.replace(/\s+/g, '');
      // Wiki typos and old names → the game's elements
      const el = { phyisical: 'physical', poison: 'earth', burning: 'fire', electric: 'energy' }[key] ?? key;
      out[el] = Math.max(out[el] ?? 0, n);
    }
  } else {
    const n = Number(v.replace(/[^\d]/g, ''));
    if (n) out.physical = n;
  }
  return Object.keys(out).length ? out : null;
}

/** "1,000" / "1000?" / "--" → number | null */
const num = (v) => {
  if (!v) return null;
  const m = v.replace(/,/g, '').match(/-?\d+(\.\d+)?/);
  return m ? Number(m[0]) : null;
};

// ---------------------------------------------------------------------------
// D-80: abilities, drops, traits and which elements the creature really hits with
// ---------------------------------------------------------------------------

/** Multi-line infobox field: ends at the next "| key =" outside any template */
export function block(text, key) {
  const m = new RegExp(`^[ \\t]*\\|[ \\t]*${key}[ \\t]*=`, 'mi').exec(text);
  if (!m) return null;
  const from = m.index + m[0].length;
  let depth = 0;
  let i = from;
  for (; i < text.length; i++) {
    if (text.startsWith('{{', i)) {
      depth++;
      i++;
    } else if (text.startsWith('}}', i)) {
      if (depth === 0) break;
      depth--;
      i++;
    } else if (depth === 0 && text[i] === '\n' && /^\n\|[ \t]*\w+[ \t]*=/.test(text.slice(i, i + 60))) break;
  }
  return text.slice(from, i).trim() || null;
}

/** Splits on "|" outside {{templates}} and [[links]] */
export function splitArgs(s) {
  const out = [];
  let depth = 0;
  let cur = '';
  for (let i = 0; i < s.length; i++) {
    const two = s.slice(i, i + 2);
    if (two === '{{' || two === '[[') {
      depth++;
      cur += two;
      i++;
    } else if (two === '}}' || two === ']]') {
      depth--;
      cur += two;
      i++;
    } else if (s[i] === '|' && depth === 0) {
      out.push(cur);
      cur = '';
    } else cur += s[i];
  }
  out.push(cur);
  return out;
}

/** The templates at the top level of a string, without their outer braces */
function topTemplates(s) {
  const out = [];
  let depth = 0;
  let start = -1;
  for (let i = 0; i < s.length; i++) {
    if (s.startsWith('{{', i)) {
      if (depth === 0) start = i;
      depth++;
      i++;
    } else if (s.startsWith('}}', i)) {
      depth--;
      i++;
      if (depth === 0 && start >= 0) out.push(s.slice(start + 2, i - 1));
    }
  }
  return out;
}

/** Wikitext → plain text */
export function cleanWiki(s) {
  if (!s) return '';
  let t = s.replace(/<gallery[\s\S]*?<\/gallery>/gi, ' ').replace(/<ref[\s\S]*?<\/ref>/gi, ' ').replace(/<br\s*\/?>|<\/br>/gi, ' ');
  for (let prev = ''; prev !== t; ) {
    prev = t;
    t = t.replace(/\{\{[^{}]*\}\}/g, ' ');
  }
  return t
    .replace(/\[\[(?:File|Image):[^\]]*\]\]/gi, ' ')
    .replace(/\[\[[^\]|]*\|([^\]]*)\]\]/g, '$1')
    .replace(/\[\[([^\]]*)\]\]/g, '$1')
    .replace(/'{2,}/g, '')
    .replace(/<[^>]+>/g, ' ')
    .replace(/&nbsp;/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

/** Wiki element names → the game's elements; conditions become the element that deals them */
const ELEMENT_NAMES = {
  physical: 'physical', phyisical: 'physical', bleed: 'physical', bleeding: 'physical',
  earth: 'earth', poison: 'earth', 'poison field': 'earth',
  fire: 'fire', 'fire field': 'fire', burning: 'fire',
  energy: 'energy', 'energy field': 'energy', electrified: 'energy', electric: 'energy',
  ice: 'ice', holy: 'holy', death: 'death',
  'life drain': 'lifedrain', lifedrain: 'lifedrain',
  'mana drain': 'manadrain', manadrain: 'manadrain', manadran: 'manadrain',
  drown: 'drown', drowning: 'drown', agony: 'agony',
};
const elementOf = (v) => ELEMENT_NAMES[(v ?? '').trim().toLowerCase()] ?? null;

function parseTemplate(t) {
  const args = splitArgs(t);
  const named = {};
  const pos = [];
  for (const a of args.slice(1)) {
    const m = a.match(/^\s*(\w+)\s*=([\s\S]*)$/);
    if (m) named[m[1].toLowerCase()] = m[2].trim();
    else pos.push(a.trim());
  }
  return { name: args[0].trim().toLowerCase(), named, pos };
}

const isRange = (v) => !!v && /\d/.test(v) && !/sec/i.test(v);

/** "{{Ability List|{{Melee|0-400}}|{{Ability|Energy Wave|element=energy}}…}}" → [{ n, r?, e?, k? }] (k: heal, summon, buff) */
export function parseAbilities(raw) {
  if (!raw) return [];
  const list = topTemplates(raw).find((t) => /^\s*ability list/i.test(t));
  const items = list ? splitArgs(list).slice(1).flatMap(topTemplates) : topTemplates(raw);
  const out = [];
  for (const t of items) {
    const { name, named, pos } = parseTemplate(t);
    const range = named.range ?? pos[0];
    if (name === 'melee') out.push({ n: 'Melee', ...(isRange(range) ? { r: range } : {}), e: 'physical' });
    else if (name === 'healing') out.push({ n: 'Healing', ...(isRange(range) ? { r: range } : {}), k: 'heal' });
    else if (name === 'summon') out.push({ n: cleanWiki(pos[0]) || 'Summon', k: 'summon' });
    else if (name === 'haste') out.push({ n: cleanWiki(pos[0]) || 'Haste', k: 'buff' });
    else if (name === 'ability') {
      const label = cleanWiki(pos[0]);
      if (!label) continue;
      const r = named.range ?? pos[1];
      const e = elementOf(named.element ?? pos[2]);
      out.push({ n: label, ...(isRange(r) ? { r: r.trim() } : {}), ...(e ? { e } : {}) });
    }
  }
  return out;
}

const RARITY = /^(always|common|uncommon|semi-?rare|very rare|extremely rare|rare)$/i;

/** "{{Loot Item|1|Gold-Brocaded Cloth|semi-rare}}" → { i, q?, r? } */
export function parseLoot(raw) {
  if (!raw) return [];
  const seen = new Set();
  const out = [];
  for (const m of raw.matchAll(/\{\{\s*Loot Item\s*\|([^{}]*)\}\}/gi)) {
    const args = m[1].split('|').map((x) => x.trim()).filter(Boolean);
    const r = args.length > 1 && RARITY.test(args[args.length - 1]) ? args.pop().toLowerCase().replace('semirare', 'semi-rare') : null;
    const i = args.pop();
    // {{Loot Item|1}}: a typo on the wiki, a quantity without an item
    if (!i || /^\d+$/.test(i) || seen.has(i.toLowerCase())) continue;
    seen.add(i.toLowerCase());
    out.push({ i, ...(args[0] ? { q: args[0] } : {}), ...(r ? { r } : {}) });
  }
  return out;
}

const toShares = (w) => {
  const total = Object.values(w).reduce((a, b) => a + b, 0);
  if (!total) return null;
  const out = {};
  for (const [k, v] of Object.entries(w).sort((a, b) => b[1] - a[1])) {
    const p = Math.round((v * 100) / total);
    if (p > 0) out[k] = p;
  }
  return out;
};

/**
 * Share (%) of the damage the creature deals per element. Best source first: a percentage split written in the wiki
 * notes, the {{Max Damage}} breakdown, then the abilities weighted by their top damage. A bare "950+" says nothing
 * about elements, so it is not used here.
 */
export function damageShares({ maxdmg, abilities, notes }) {
  const written = {};
  for (const m of (notes ?? '').matchAll(/(\d+)\s*%\s*\[\[\s*([A-Za-z ]+?) Damage/gi)) {
    const e = elementOf(m[2]);
    if (e) written[e] = (written[e] ?? 0) + Number(m[1]);
  }
  if (Object.values(written).reduce((a, b) => a + b, 0) >= 70) return toShares(written);
  if (/\{\{\s*Max Damage/i.test(maxdmg ?? '')) {
    const w = {};
    for (const [k, v] of Object.entries(maxDamage(maxdmg) ?? {})) {
      const e = elementOf(k) ?? k;
      if (Object.values(ELEMENT_NAMES).includes(e)) w[e] = (w[e] ?? 0) + v;
    }
    const shares = toShares(w);
    if (shares) return shares;
  }
  const hitting = (abilities ?? []).filter((a) => a.e);
  const top = (a) => Math.max(0, ...((a.r ?? '').replace(/,/g, '').match(/\d+/g)?.map(Number) ?? [0]));
  const known = hitting.map(top).filter((v) => v > 0);
  const fallback = known.length ? known.reduce((a, b) => a + b, 0) / known.length : 1;
  const w = {};
  for (const a of hitting) w[a.e] = (w[a.e] ?? 0) + (top(a) || fallback);
  return toShares(w);
}

const yesNo = (v) => (/^yes/i.test(v ?? '') ? true : /^no/i.test(v ?? '') ? false : null);
/** Same rule as backend/src/huntingPlaces.ts */
const slugify = (name) =>
  name.normalize('NFKD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/'/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
const clip = (s, max) => (s.length > max ? `${s.slice(0, max).replace(/\s+\S*$/, '')}…` : s);
const emptyDash = (v) => (!v || v === '--' ? null : v);

/** D-80: everything from the page that goes to shared/creature-wiki.json */
function wikiDetails(title, text, abilities, notesRaw) {
  return {
    title,
    kind: emptyDash(field(text, 'primarytype')),
    atk: emptyDash(field(text, 'attacktype')),
    maxHit: (block(text, 'maxdmg') ?? '').match(/^\d[\d,]*\+?$/)?.[0] ?? null,
    ab: abilities,
    loot: parseLoot(block(text, 'loot')),
    tr: {
      para: yesNo(field(text, 'paraimmune')),
      invis: yesNo(field(text, 'senseinvis')),
      push: yesNo(field(text, 'pushable')),
      pushObj: yesNo(field(text, 'pushobjects')),
      illusion: yesNo(field(text, 'illusionable')),
      summon: num(field(text, 'summon')),
      convince: num(field(text, 'convince')),
      through: emptyDash(cleanWiki(field(text, 'walksthrough'))),
      around: emptyDash(cleanWiki(field(text, 'walksaround'))),
      runs: num(field(text, 'runsat')),
    },
    loc: clip(cleanWiki(block(text, 'location')), 600),
    desc: clip(cleanWiki(block(text, 'bestiarytext')), 1200),
    beh: clip(cleanWiki(block(text, 'behaviour')), 800),
    notes: clip(cleanWiki(notesRaw), 1200),
    strat: clip(cleanWiki(block(text, 'strategy')), 1200),
  };
}

const CDN = 'https://static.wikia.nocookie.net/tibia/images/';

/** D-82: file on the wiki for each file name (50 per request); names without a file are left out */
async function wikiFiles(names) {
  const out = new Map();
  for (let i = 0; i < names.length; i += 50) {
    const batch = names.slice(i, i + 50);
    const d = await api({ action: 'query', prop: 'imageinfo', iiprop: 'url', titles: batch.map((n) => `File:${n}.gif`).join('|') });
    const back = new Map((d.query.normalized ?? []).map((n) => [n.to, n.from]));
    for (const p of d.query.pages ?? []) {
      const url = p.imageinfo?.[0]?.url?.split('/revision/')[0];
      if (url?.startsWith(CDN)) out.set((back.get(p.title) ?? p.title).replace(/^File:/, '').replace(/\.gif$/, ''), url.slice(CDN.length));
    }
  }
  return out;
}

async function main() {
  const titles = await categoryTitles('Creatures');
  console.log(`${titles.length} creature pages`);
  const texts = await wikitexts(titles);
  console.log('');
  const creatures = [];
  for (const [title, text] of texts) {
    if (!/\{\{Infobox[ _]Creature/i.test(text)) continue;
    const status = field(text, 'status')?.toLowerCase();
    if (status && /removed|deprecated/.test(status)) continue;
    const hp = num(field(text, 'hp'));
    if (!hp) continue;
    const el = {};
    for (const [k, f] of Object.entries(ELEMENTS)) {
      const v = num(field(text, f));
      if (v != null && v !== 100) el[k] = v;
    }
    const abilities = parseAbilities(block(text, 'abilities'));
    const notesRaw = block(text, 'notes');
    creatures.push({
      _wiki: wikiDetails(title, text, abilities, notesRaw),
      name: (field(text, 'name') || title).replace(/\s*\(.*\)$/, '').trim(),
      hp,
      exp: num(field(text, 'exp')) ?? 0,
      arm: num(field(text, 'armor')),
      mit: num(field(text, 'mitigation')),
      cls: field(text, 'bestiaryclass'),
      lvl: field(text, 'bestiarylevel'),
      occ: field(text, 'occurrence'),
      boss: /^yes/i.test(field(text, 'isboss') ?? ''),
      // D-75: Bosstiary class (Bane, Archfoe, Nemesis); Nemesis = the rare bosses that spawn on the map
      bc: field(text, 'bosstiaryclass'),
      // D-80: boss cooldown in hours, when the wiki gives one
      cd: num(field(text, 'cooldown')),
      spd: num(field(text, 'speed')),
      dmg: maxDamage(field(text, 'maxdmg')),
      // D-80: what it really deals per element (dmg above only knows the max hit)
      out: damageShares({ maxdmg: block(text, 'maxdmg'), abilities, notes: notesRaw }),
      el,
    });
  }
  // One entry per name (variants keep the first)
  const seen = new Set();
  const list = creatures
    .sort((a, b) => a.name.localeCompare(b.name))
    .filter((c) => (seen.has(c.name.toLowerCase()) ? false : seen.add(c.name.toLowerCase())));
  console.log(`${list.length} creatures, bestiary: ${list.filter((c) => c.lvl).length}, bosses: ${list.filter((c) => c.boss).length}`);
  // D-82: the official library has no bosses, quest or newer creatures; every creature keeps its wiki sprite as a fallback
  const byName = await wikiFiles(list.map((c) => c.name));
  const pending = list.filter((c) => !byName.has(c.name) && c._wiki.title !== c.name);
  const byTitle = await wikiFiles(pending.map((c) => c._wiki.title));
  for (const c of list) c.img = byName.get(c.name) ?? byTitle.get(c._wiki.title) ?? null;
  console.log(`sprites: ${list.filter((c) => c.img).length}/${list.length}; none: ${list.filter((c) => !c.img).map((c) => c.name).slice(0, 60).join(', ')}`);
  if (DRY_RUN) {
    console.log(JSON.stringify(list.filter((c) => ['Dragon', 'Mitmah Seer', 'Hydra'].includes(c.name)), null, 1));
    return;
  }
  const today = new Date().toISOString().slice(0, 10);
  const wiki = {};
  for (const c of list) {
    wiki[slugify(c.name)] = { name: c.name, ...c._wiki };
    delete c._wiki;
  }
  const out = {
    _comment: `Generated by scripts/import-creatures.mjs on ${today} from TibiaWiki (CC BY-SA). Do not edit by hand. el = damage taken in % (only values other than 100). out = share of the damage it deals per element, in %.`,
    creatures: list,
  };
  await writeFile(path.join(ROOT, 'shared/creatures.json'), JSON.stringify(out) + '\n');
  await writeFile(
    path.join(ROOT, 'shared/creature-wiki.json'),
    JSON.stringify({ _comment: `Generated by scripts/import-creatures.mjs on ${today} from TibiaWiki (CC BY-SA). Do not edit by hand.`, creatures: wiki }) + '\n',
  );
  console.log('wrote shared/creatures.json and shared/creature-wiki.json');
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  main().catch((err) => {
    console.error(err);
    process.exit(1);
  });
}
