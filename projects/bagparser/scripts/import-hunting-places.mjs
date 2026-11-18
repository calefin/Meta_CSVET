#!/usr/bin/env node
/**
 * Hunting places (D-42) from TibiaWiki (CC BY-SA): every page in Category:Hunting Places with an
 * {{Infobox Hunt}} → city, recommended level/skills per vocation, experience and loot stars, creatures
 * ({{CreatureList}}) and map coordinates (Mapper link or {{Mapper Coords}}).
 *
 * Usage: node scripts/import-hunting-places.mjs [--dry-run]     (needs internet, ~1 min)
 * Output: shared/hunting-places.json
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
    const d = await api({
      action: 'query',
      prop: 'revisions',
      rvprop: 'content',
      rvslots: 'main',
      titles: titles.slice(i, i + 50).join('|'),
    });
    for (const p of d.query.pages ?? []) {
      const text = p.revisions?.[0]?.slots?.main?.content;
      if (text) out.set(p.title, text);
    }
    process.stdout.write('.');
  }
  return out;
}

// ---------------------------------------------------------------------------
// Parsing (exported for tests)
// ---------------------------------------------------------------------------

/** Body of the first {{Name ...}} template, honouring nested braces */
export function templateBody(text, name) {
  const start = text.search(new RegExp(`\\{\\{\\s*${name}\\b`, 'i'));
  if (start < 0) return null;
  let depth = 0;
  for (let i = start; i < text.length - 1; i++) {
    if (text[i] === '{' && text[i + 1] === '{') {
      depth++;
      i++;
    } else if (text[i] === '}' && text[i + 1] === '}') {
      depth--;
      i++;
      if (depth === 0) return text.slice(start + 2, i - 1);
    }
  }
  return null;
}

export function field(body, key) {
  // [ \t]* (not \s*) after "=": an empty field must not swallow the next line
  const m = new RegExp(`^[ \\t]*\\|[ \\t]*${key}[ \\t]*=[ \\t]*(.*)$`, 'mi').exec(body ?? '');
  const v = m?.[1].trim();
  return v ? v : null;
}

/** Wiki markup → plain text: [[A|B]] → B, [[A]] → A, [url text] → text, templates and tags removed */
export function plain(s) {
  if (!s) return null;
  const out = s
    .replace(/\[\[(?:[^\]|]*\|)?([^\]]*)\]\]/g, '$1')
    .replace(/\[https?:\/\/\S+\s+([^\]]*)\]/g, '$1')
    .replace(/\[https?:\/\/\S+\]/g, '')
    .replace(/\{\{[^}]*\}\}/g, '')
    .replace(/<[^>]+>/g, '')
    .replace(/'{2,}/g, '')
    .replace(/\s+/g, ' ')
    .trim();
  return out || null;
}

const int = (s) => {
  const m = /\d+/.exec(s ?? '');
  return m ? Number(m[0]) : null;
};

/** "coords=131.52,126.177,7" (Mapper link) or {{Mapper Coords|131.52|126.177|7}} → absolute x, y, z */
export function parseCoords(text) {
  const toAbs = (a) => {
    const [hi, lo] = a.split('.');
    return Number(hi) * 256 + Number(lo ?? 0);
  };
  let m = /coords=(\d+\.\d+),(\d+\.\d+),(\d+)/.exec(text);
  if (!m) m = /\{\{\s*Mapper Coords\s*\|\s*(\d+\.\d+)\s*\|\s*(\d+\.\d+)\s*\|\s*(\d+)/i.exec(text);
  if (!m) return null;
  return { x: toAbs(m[1]), y: toAbs(m[2]), z: Number(m[3]) };
}

/** Creature names of every {{CreatureList}} on the page, in order, without duplicates */
export function parseCreatures(text) {
  const names = [];
  const re = /\{\{\s*CreatureList\b/gi;
  let m;
  while ((m = re.exec(text))) {
    let body = (templateBody(text.slice(m.index), 'CreatureList') ?? '').replace(/<!--.*?-->/gs, '');
    // Nested templates ({{Ilink|Some Key}} - Required) are notes, not creatures
    for (let prev = ''; prev !== body; ) [prev, body] = [body, body.replace(/\{\{[^{}]*\}\}/g, '')];
    for (const part of body.split('|').slice(1)) {
      const name = plain(part.trim());
      if (!name || name.includes('=') || /required|[()]/i.test(name) || name.length > 40) continue;
      // "troll" → "Troll" (some pages are lowercase)
      names.push(name === name.toLowerCase() ? name.replace(/\b\w/g, (c) => c.toUpperCase()) : name);
    }
  }
  return [...new Set(names.filter(Boolean))];
}

/** Text of the sections whose heading matches `re` */
function sections(text, re) {
  const out = [];
  const parts = text.split(/^(==+[^=\n]+==+)\s*$/m);
  for (let i = 1; i < parts.length; i += 2) if (re.test(parts[i])) out.push(parts[i + 1] ?? '');
  return out.join('\n');
}

/**
 * Quests needed to enter (heuristic): [[... Quest ...]] links in the location field and in sections such as
 * "Access", "Requirements" or "How to get there". The infobox has no dedicated field; fix mistakes in
 * shared/hunting-places.overrides.json.
 */
export function parseAccessQuests(text, locationRaw) {
  const LINK = /\[\[([^\]|#]+)(?:#[^\]|]*)?(?:\|[^\]]*)?\]\]/g;
  const isQuest = (t) => /quest/i.test(t) && !/^(category|file):/i.test(t);
  const quests = [];
  // 1. Location field and access-like sections: every quest link counts
  const scope = `${locationRaw ?? ''}\n${sections(text, /access|requirement|how to get|entrance|getting there/i)}`;
  for (const m of scope.matchAll(LINK)) if (isQuest(m[1].trim())) quests.push(m[1].trim());
  // 2. Anywhere else: only when the sentence around the link talks about access
  const body = text.replace(/\{\{\s*Infobox Hunt[\s\S]*?\n\}\}/i, '');
  for (const m of body.matchAll(LINK)) {
    const target = m[1].trim();
    if (!isQuest(target)) continue;
    const from = Math.max(body.lastIndexOf('.', m.index) + 1, m.index - 200);
    const toDot = body.indexOf('.', m.index + m[0].length);
    const sentence = body.slice(from, toDot < 0 ? m.index + 200 : Math.min(toDot, m.index + 200));
    if (/\b(access|need|needs|needed|require|required|requires|enter|entrance|must|complet|in order to|unlock|permission|allowed)/i.test(sentence)) {
      quests.push(target);
    }
  }
  // "Adventures_of_Galthen_Quest" / "Roshamuul Quest/Spoiler" → "Adventures of Galthen Quest" / "Roshamuul Quest"
  return [...new Set(quests.map((q) => q.replace(/_/g, ' ').replace(/\/.*$/, '').replace(/\s+/g, ' ').trim()))];
}

export function parsePlace(title, text) {
  const box = templateBody(text, 'Infobox Hunt');
  if (!box) return null;
  const bestLoot = ['bestloot', 'bestloot2', 'bestloot3', 'bestloot4', 'bestloot5']
    .map((k) => plain(field(box, k)))
    .filter(Boolean);
  const lvl = (k) => int(field(box, `lvl${k}`));
  return {
    name: plain(field(box, 'name')) ?? title,
    wikiTitle: title,
    city: plain(field(box, 'city')),
    location: plain(field(box, 'location'))?.replace(/[,\s]*\bhere\b\.?\s*$/i, '.').replace(/,\s*\.$/, '.') ?? null,
    vocation: plain(field(box, 'vocation')),
    implemented: field(box, 'implemented'),
    level: { knights: lvl('knights'), paladins: lvl('paladins'), mages: lvl('mages') },
    skill: { knights: int(field(box, 'skknights')), paladins: int(field(box, 'skpaladins')), mages: int(field(box, 'skmages')) },
    expStars: int(field(box, 'expstar')),
    lootStars: int(field(box, 'lootstar')),
    exp: plain(field(box, 'exp')),
    loot: plain(field(box, 'loot')),
    mainCreature: plain(field(box, 'image')),
    coords: parseCoords(text),
    creatures: parseCreatures(text),
    bestLoot,
    accessQuests: parseAccessQuests(text, field(box, 'location')),
  };
}

async function main() {
  const titles = (await categoryTitles('Hunting Places')).filter((t) => t !== 'Hunting Places');
  console.log(`${titles.length} pages`);
  const texts = await wikitexts(titles);
  console.log('');
  const places = [];
  for (const title of titles) {
    const p = texts.get(title) && parsePlace(title, texts.get(title));
    if (p) places.push(p);
  }
  places.sort((a, b) => a.name.localeCompare(b.name));
  const withCreatures = places.filter((p) => p.creatures.length).length;
  const withCoords = places.filter((p) => p.coords).length;
  console.log(`${places.length} places · ${withCreatures} with creatures · ${withCoords} with coordinates`);
  const out = {
    _comment: `Generated by scripts/import-hunting-places.mjs on ${new Date().toISOString().slice(0, 10)} from TibiaWiki (CC BY-SA). Do not edit by hand.`,
    places,
  };
  if (DRY_RUN) {
    console.log(JSON.stringify(places.slice(0, 2), null, 1));
    return;
  }
  await writeFile(path.join(ROOT, 'shared/hunting-places.json'), JSON.stringify(out, null, 1) + '\n');
  console.log('wrote shared/hunting-places.json');
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  main().catch((err) => {
    console.error(err);
    process.exit(1);
  });
}
