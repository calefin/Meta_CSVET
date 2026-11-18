#!/usr/bin/env node
/**
 * D-59: builds shared/equipment.json (equipment slots + attributes) from TibiaWiki (tibia.fandom.com, CC BY-SA).
 *
 *   node scripts/import-equipment.mjs            # writes shared/equipment.json
 *   node scripts/import-equipment.mjs --dry-run  # prints a summary only
 *
 * Used by the hunt session "character set" editor: pick an item per slot and the combat stats
 * (critical, leech, skills, resistances…) are summed per source. Facts only (numbers/names), no wiki text.
 */
import { writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const API = 'https://tibia.fandom.com/api.php';
const UA = 'BagParser-catalog-import/1.0 (https://github.com/calefin/bagparser)';
const DRY_RUN = process.argv.includes('--dry-run');
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function api(params) {
  const url = `${API}?${new URLSearchParams({ format: 'json', formatversion: '2', ...params })}`;
  for (let attempt = 1; ; attempt++) {
    try {
      const res = await fetch(url, { headers: { 'User-Agent': UA } });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const json = await res.json();
      await sleep(250);
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

async function pagesContent(titles) {
  const out = new Map();
  for (let i = 0; i < titles.length; i += 50) {
    const chunk = titles.slice(i, i + 50);
    const d = await api({ action: 'query', prop: 'revisions', rvprop: 'content', rvslots: 'main', titles: chunk.join('|') });
    for (const p of d.query.pages) {
      const text = p.revisions?.[0]?.slots?.main?.content;
      if (text) out.set(p.title, text);
    }
    process.stderr.write(`\rpages: ${Math.min(i + 50, titles.length)}/${titles.length}`);
  }
  process.stderr.write('\n');
  return out;
}

/** "| key = value" lines of the first Infobox Object */
function infobox(text) {
  const start = text.search(/\{\{Infobox[ _]Object/i);
  if (start < 0) return null;
  const body = text.slice(start);
  const fields = {};
  for (const line of body.split('\n').slice(1)) {
    if (/^\}\}/.test(line)) break;
    const m = line.match(/^\|\s*([a-z_]+)\s*=\s*(.*)$/i);
    if (m) fields[m[1].toLowerCase()] = m[2].replace(/<!--.*?-->/g, '').replace(/\[\[(?:[^\]|]*\|)?([^\]]*)\]\]/g, '$1').trim();
  }
  return fields;
}

const SLOT = {
  head: 'helmet',
  body: 'armor',
  legs: 'legs',
  feet: 'boots',
  neck: 'amulet',
  finger: 'ring',
  'weapon hand': 'weapon',
  'shield hand': 'shield',
  'two-handed': 'weapon',
  'both hands': 'weapon',
  ammunition: 'ammo',
  'ammo slot': 'ammo',
  arrow: 'ammo',
};

/** attrib/resist strings → [[label, value]]; "magic level +4, critical hit chance +5%" */
function pairs(s) {
  if (!s) return [];
  const out = [];
  for (const part of s.split(/,(?![^(]*\))/)) {
    const m = part.trim().match(/^(.+?)\s*([+-]\d+(?:\.\d+)?)\s*(%?)$/);
    if (m) out.push([m[1].trim().toLowerCase(), Number(m[2])]);
  }
  return out;
}

function vocations(s) {
  const v = (s ?? '').toLowerCase();
  if (!v || /none|all|any/.test(v)) return undefined;
  const out = ['knight', 'paladin', 'sorcerer', 'druid', 'monk'].filter((x) => v.includes(x));
  return out.length && out.length < 5 ? out : undefined;
}

async function main() {
  const cats = ['Body Equipment', 'Weapons', 'Helmets', 'Armors', 'Legs', 'Boots', 'Shields', 'Spellbooks', 'Quivers', 'Amulets and Necklaces', 'Rings', 'Wands', 'Rods', 'Fist Fighting Weapons'];
  const titles = new Set();
  for (const c of cats) {
    try {
      for (const t of await categoryTitles(c)) titles.add(t);
    } catch {
      /* missing category */
    }
  }
  process.stderr.write(`titles: ${titles.size}\n`);
  const pages = await pagesContent([...titles]);
  const items = [];
  for (const [title, text] of pages) {
    const f = infobox(text);
    if (!f) continue;
    if (/^yes/i.test(f.deprecated ?? '') || /^(yes|1)/i.test(f.historical ?? '')) continue;
    const slotRaw = (f.slot ?? '').toLowerCase().trim();
    let slot = SLOT[slotRaw] ?? null;
    const ptype = (f.primarytype ?? '').toLowerCase();
    if (!slot && /ammunition/.test(ptype)) slot = 'ammo';
    if (!slot) continue;
    if (slot === 'shield' && /quiver/.test(ptype)) slot = 'shield'; // quivers go in the shield hand
    const attrs = pairs(f.attrib).filter(([k]) => !/^(imbue|charges)/.test(k));
    const resist = pairs(f.resist);
    const e = {
      name: (f.name || title).trim(),
      slot,
      type: f.primarytype || undefined,
      level: Number(f.levelrequired) || undefined,
      voc: vocations(f.vocrequired),
      arm: Number(f.armor) || undefined,
      def: Number(String(f.defense ?? '').match(/\d+/)?.[0]) || undefined,
      atk: Number(f.attack) || undefined,
      imbue: Number(f.imbueslots) || undefined,
      tier: Number(f.upgradeclass) || undefined,
      two: /two/i.test(f.hands ?? '') || undefined,
      a: attrs.length ? Object.fromEntries(attrs) : undefined,
      r: resist.length ? Object.fromEntries(resist) : undefined,
      aug: f.augments && !/^none$/i.test(f.augments) ? f.augments.slice(0, 300) : undefined,
    };
    items.push(JSON.parse(JSON.stringify(e)));
  }
  items.sort((a, b) => a.name.localeCompare(b.name));
  const bySlot = {};
  for (const i of items) bySlot[i.slot] = (bySlot[i.slot] ?? 0) + 1;
  console.log(`equipment: ${items.length}`, bySlot);
  if (DRY_RUN) return;
  const out = {
    _comment: 'Generated by scripts/import-equipment.mjs from TibiaWiki (CC BY-SA). Do not edit by hand.',
    generated: new Date().toISOString().slice(0, 10),
    items,
  };
  await writeFile(path.join(ROOT, 'shared/equipment.json'), JSON.stringify(out) + '\n');
  console.log('wrote shared/equipment.json');
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
