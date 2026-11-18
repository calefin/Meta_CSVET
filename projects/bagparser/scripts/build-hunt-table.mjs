#!/usr/bin/env node
/**
 * Hunt table per vocation (D-80, D-81). Joins, per vocation:
 *   - shared/hunt-videos.json: solo hunts as reported by the community (level, XP/h, profit/h)
 *   - frontend/src/content/quickhunts/guia-*.md: our own guides (no team hunts)
 *   - shared/hunting-places.json + shared/creatures.json (TibiaWiki): creatures, resistances, damage dealt
 * and computes the best element to hit with and the elements to protect from.
 *
 * Usage: node scripts/build-hunt-table.mjs     (offline; run again after changing any of the files above)
 * Output: shared/hunt-table.json  { _comment, rows: [{ v, lv, name, slug, x, l, xs, ls, c, hit, pr, m }] }
 *   x / l: XP/h and profit/h as written ("1,6kk–1,7kk"), "loss" when the guide only says it loses money, null = unknown ("var")
 */
import { readdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const GUIDES = {
  'guia-elite-knight.md': 'knight',
  'guia-royal-paladin.md': 'paladin',
  'guia-monk.md': 'monk',
  'guia-master-sorcerer.md': 'sorcerer',
  'guia-elder-druid.md': 'druid',
};

/** Elements each vocation can really deal: weapons, ammo, spells with mastery and area runes */
export const VOC_ELEMENTS = {
  knight: ['physical', 'fire', 'ice', 'energy', 'earth', 'death'],
  paladin: ['physical', 'holy', 'fire', 'ice', 'energy', 'earth', 'death'],
  monk: ['physical', 'energy', 'earth'],
  sorcerer: ['fire', 'energy', 'death'],
  druid: ['ice', 'earth', 'fire', 'energy', 'death'],
};

const P = (place, label) => ({ place, label });
const C = (label, ...creatures) => ({ creatures, label });

/** Community names that neither match a place nor name a creature (first match wins) */
const ALIASES = [
  [/alchemist center/, P('Alchemist Quarter')],
  [/^carlin cults/, P('Forbidden Temple (Carlin)')],
  [/^coryms?/, C('Coryms', 'Corym Charlatan', 'Corym Skirmisher', 'Corym Vanguard')],
  [/pirates/, C('Pirates', 'Pirate Marauder', 'Pirate Cutthroat', 'Pirate Buccaneer', 'Pirate Corsair')],
  [/infernoids/, C('Targuna Infernoids', 'Infernoid Blob', 'Infernoid Hound', 'Infernoid Soul', 'Infernoid Spiritual')],
  [/zombies cemetery/, P('Cemetery Quarter')],
  [/zombies treasure/, P('Treasure Island')],
  [/carnisylvans/, C('Carnisylvans', 'Dark Carnisylvan', 'Hulking Carnisylvan', 'Poisonous Carnisylvan', 'Carnisylvan Sapling')],
  [/usurpers/, C('Usurpers', 'Usurper Archer', 'Usurper Knight', 'Usurper Warlock', 'Hardened Usurper Archer', 'Hardened Usurper Knight', 'Hardened Usurper Warlock')],
  [/marapur turtles/, C('Marapur Turtles', 'Two-Headed Turtle', 'Foam Stalker', 'Venerable Foam Stalker')],
  [/ingol/, C('Ingol', 'Boar Man', 'Crape Man', 'Harpy', 'Liodile', 'Rhindeer', 'Carnivostrich')],
  [/cults? (de )?yalahar|yalahar cults/, C('Yalahar Cults', 'Novice of the Cult', 'Acolyte of the Cult', 'Adept of the Cult', 'Enlightened of the Cult')],
  [/orc cults/, C('Orc Cults', 'Orc Cultist', 'Orc Cult Inquisitor', 'Orc Cult Fanatic', 'Orc Cult Priest', 'Orc Cult Minion')],
  [/minotaur cults/, C('Minotaur Cults', 'Minotaur Cult Follower', 'Minotaur Cult Prophet', 'Minotaur Cult Zealot')],
  [/bonebeasts?\)?$|old fortress bonebeasts/, C('Edron Bonebeasts', 'Bonebeast')],
  [/edron heroes|old fortress.*hero/, P('Old Fortress')],
  [/oramond west|wildlife raid/, P('OramondWestern Plains', 'Oramond Western Plains')],
  [/golem raid/, C('Oramond Golem Raid', 'Glooth Golem', 'Rustheap Golem', 'Metal Gargoyle')],
  [/quaras? (liberty bay|lb)|liberty bay quaras/, C('Liberty Bay Quaras', 'Quara Constrictor', 'Quara Hydromancer', 'Quara Mantassin', 'Quara Pincher', 'Quara Predator')],
  [/yalahar sunken/, P('Sunken Quarter')],
  [/podzilla/, P('Podzilla')],
  [/^grimvale/, P('Grimvale')],
  [/mini rosha/, C('Mini Roshamuul (Feyrist)', 'Weakened Frazzlemaw', 'Enfeebled Silencer')],
  [/^barkless/, P('Barkless Cult Trial Zone')],
  [/edron (south )?were/, P('Edron Lycantrophes Cave')],
  [/oramond mino/, P('Oramond Minotaur Camp')],
  [/deeplings library/, C('Deepling Library', 'Deepling Guard', 'Deepling Warrior', 'Deepling Spellsinger', 'Deepling Scout')],
  [/lower spike/, P('Lower Spike')],
  [/draken walls|wote 8/, C('Draken Walls', 'Draken Spellweaver', 'Draken Warmaster', 'Draken Abomination', 'Draken Elite')],
  [/hive/, P('Hive Outpost')],
  [/wreckoning|pirats ship/, P('The Wreckoning')],
  [/^pirats$/, P('Pirat Mine')],
  [/carnivora/, P("Carnivora's Rocks")],
  [/deeper banuta/, P('Deeper Banuta')],
  [/banuta apes|apes banuta/, C('Banuta Apes', 'Kongra', 'Merlkin', 'Sibang')],
  [/book world/, P('Book World')],
  [/asura mirror/, P('Asura Palace')],
  [/true asuras/, P('Asura Vaults')],
  [/shockheads/, C('Roshamuul Shock Heads', 'Shock Head')],
  [/terrosleep/, P('Roshamuul Mines')],
  [/upper rosh/, P('Upper Roshamuul')],
  [/lower rosh|rosha(muul)? west/, P('Lower Roshamuul')],
  [/buried cathedral/, P('Buried Cathedral')],
  [/winter court/, P('Court of Winter')],
  [/summer court/, P('Court of Summer')],
  [/^prison -/, P('Roshamuul Prison')],
  [/nagas/, C('Marapur Nagas', 'Naga Archer', 'Naga Warrior')],
  [/issavi (crypts|sphinx)|sphinx\/crypt/, C('Issavi Sphinxes and Crypt Wardens', 'Sphinx', 'Crypt Warden', 'Lamassu', 'Feral Sphinx')],
  [/issavi goannas/, C('Issavi Goannas', 'Young Goanna', 'Adult Goanna')],
  [/issavi ogre/, C('Issavi Ogres', 'Ogre Sage', 'Ogre Rowdy', 'Ogre Ruffian')],
  [/issavi surface/, P('Kilmaresh Central Steppe')],
  [/krailos (surface|ogres)/, P('Krailos Steppe')],
  [/deathlings/, C('Deathlings', 'Deathling Scout', 'Deathling Spellsinger')],
  [/flims/, C('Flimsy Lost Souls', 'Flimsy Lost Soul', 'Mean Lost Soul', 'Freakish Lost Soul')],
  [/azzilon walls/, P('Azzilon Castle')],
  [/oramond (mountain )?hideout/, P('Glooth Bandit Hideouts')],
  [/girtablilu/, C('Girtablilus', 'Girtablilu Warrior', 'Venerable Girtablilu')],
  [/bulltaurs/, P('Bulltaur Lair')],
  [/nimmersatt/, P("Nimmersatt's Breeding Ground")],
  [/plague (feru )?seal|feru plague/, P('Grounds of Plague')],
  [/pumin/, P('Grounds of Despair')],
  [/infernatil|mazoran/, P('Grounds of Fire')],
  [/feru dt/, P('Grounds of Damnation')],
  [/undead dragon/, P('Grounds of Undeath')],
  [/juggernaut/, P('Grounds of Destruction')],
  [/feru way|ferumbras way/, P('Ferumbras Citadel')],
  [/norcferatu/, P('Norcferatu Dungeons')],
  [/raubritter/, P('Bloodfire Gorge')],
  [/edron (forgotten )?tomb/, P('Forgotten Tomb')],
  [/^misguided/, P('Misguided Camp')],
  [/otherworld kazordoon/, P('Otherworld (Dwarf Bridge)')],
  [/otherworld edron/, P('Otherworld (Edron)')],
  [/falcon/, P('Falcon Bastion')],
  [/(energy|fire|ice) library/, P('Secret Library')],
  [/yielothax/, P('Yielothax Dimension')],
  [/orclops/, P('Desecrated Glade')],
  [/feyrist surface/, P('Feyrist Meadows')],
  [/dessert dungeon/, P('Dessert Dungeons')],
  [/iksupan entrance/, P('Iksupan')],
  [/mosl -3|mummy\/bonebeasts/, C('Mother of Scarabs Lair -3', 'Mummy', 'Bonebeast')],
  [/mosl -4/, C('Mother of Scarabs Lair -4', 'Ancient Scarab')],
  [/^cobra/, P('Cobra Bastion')],
];

const norm = (s) => s.toLowerCase().replace(/\(.*?\)/g, ' ').replace(/[^a-z0-9 ]/g, ' ').replace(/\s+/g, ' ').trim();
const singular = (w) => w.replace(/ies$/, 'y').replace(/ves$/, 'f').replace(/([^s])s$/, '$1');
const slugify = (name) =>
  name.normalize('NFKD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/'/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
/** Words that are creatures on the wiki but here name something else ("Fire Library") */
const NOT_CREATURES = new Set(['fire', 'ice', 'energy', 'earth', 'holy', 'hive', 'book', 'monk', 'hunter', 'priestess', 'guide', 'cat', 'bat']);

/** Every creature named in a place name, longest names first ("Mammoths/Badgers Svargrond" → both) */
export function creaturesInName(name, creatureKeys) {
  const tokens = norm(name).split(' ');
  const found = [];
  for (let i = 0; i < tokens.length; ) {
    let hit = null;
    for (let len = Math.min(4, tokens.length - i); len >= 1 && !hit; len--) {
      const words = tokens.slice(i, i + len);
      const candidates = [words.join(' '), [...words.slice(0, -1), singular(words[len - 1])].join(' ')];
      const key = candidates.find((k) => creatureKeys.has(k) && !NOT_CREATURES.has(k));
      if (key) hit = { key, len };
    }
    if (hit) {
      found.push(creatureKeys.get(hit.key));
      i += hit.len;
    } else i++;
  }
  return [...new Set(found)];
}

/** Name → { label, place?, creatures } */
export function resolver(places, creatureKeys) {
  const byName = new Map(places.map((p) => [norm(p.name), p]));
  const bySlug = new Map(places.map((p) => [slugify(p.name), p]));
  const stripFloors = (n) => n.replace(/\s-\d.*$/, '').trim();
  const cleanLabel = (s) => s.replace(/\s*\(.*?\)\s*/g, ' ').replace(/\s-\d.*$/, '').replace(/\s+/g, ' ').trim();

  const fromPlace = (p, label) => ({ label: label ?? p.name, place: p, creatures: p.creatures });
  return (raw, linkSlug) => {
    if (linkSlug && bySlug.has(linkSlug)) return fromPlace(bySlug.get(linkSlug));
    const lower = raw.toLowerCase();
    for (const [re, target] of ALIASES) {
      if (!re.test(lower)) continue;
      if (target.place) {
        const p = byName.get(norm(target.place));
        if (!p) throw new Error(`alias points to an unknown place: ${target.place}`);
        return fromPlace(p, target.label);
      }
      for (const c of target.creatures) if (!creatureKeys.has(norm(c))) console.warn(`  alias creature not on the wiki: ${c}`);
      return { label: target.label, creatures: target.creatures.filter((c) => creatureKeys.has(norm(c))) };
    }
    const direct = byName.get(norm(raw)) ?? byName.get(norm(stripFloors(raw)));
    if (direct) return fromPlace(direct);
    const creatures = creaturesInName(raw, creatureKeys);
    if (creatures.length) {
      const tokens = norm(raw).split(' ');
      const inPlace = places.find((p) => p.creatures.includes(creatures[0]) && tokens.some((t) => t.length > 3 && norm(p.name).split(' ').includes(t)));
      return { label: cleanLabel(raw), place: inPlace, creatures, linkOnly: true };
    }
    return { label: cleanLabel(raw), creatures: [] };
  };
}

/** Which place a video title talks about: a place named in full, then a community name or creature, then a one-word place */
export function placeFromTitle(title, places, resolve) {
  const text = ` ${norm(title.replace(/\b(ek|rp|ed|ms|em|monk|knight|paladin|sorcerer|druid)s?\b/gi, ' '))} `;
  const byLength = [...places].sort((a, b) => b.name.length - a.name.length);
  const has = (p) => text.includes(` ${norm(p.name)} `);
  const full = byLength.find((p) => norm(p.name).includes(' ') && has(p));
  if (full) return { label: full.name, place: full, creatures: full.creatures };
  const resolved = resolve(text.trim());
  if (resolved.creatures.length) {
    return resolved.linkOnly ? { ...resolved, label: resolved.creatures.join(' / ') } : resolved;
  }
  const single = byLength.find(has);
  return single && single.creatures.length ? { label: single.name, place: single, creatures: single.creatures } : null;
}

const fmtAmount = (n) => (n >= 1e6 ? `${String(Math.round(n / 1e5) / 10).replace('.', ',')}kk` : `${Math.round(n / 1e3)}k`);
/** [[1,2],[3,3]] → "1k–3k": everything the creators reported for that place */
export function spread(ranges) {
  if (!ranges.length) return null;
  const lo = Math.min(...ranges.map((r) => r[0]));
  const hi = Math.max(...ranges.map((r) => r[1]));
  return lo === hi ? fmtAmount(lo) : `${fmtAmount(lo)}–${fmtAmount(hi)}`;
}

/** Density the same way the API estimates it (backend/src/huntingPlaces.ts) */
const density = (place, name, index, total) => {
  if (place?.mainCreature && place.mainCreature.toLowerCase() === name.toLowerCase()) return 5;
  if (total <= 3) return 4;
  return index < 3 ? 4 : 3;
};

/** Best elements for one vocation and the damage to protect from, weighted by how much HP of each creature you chew through */
export function elementsFor(voc, creatures, place, stats) {
  const known = creatures
    .map((name, i) => ({ c: stats.get(name.toLowerCase()), w: density(place, name, i, creatures.length) }))
    .filter((x) => x.c && !x.c.boss);
  // Wiki lists mix in everything that spawns there: only what is worth killing counts (Slimes do not make Deeper Banuta)
  const topExp = Math.max(0, ...known.map((x) => x.c.exp));
  const weighted = known.filter((x) => x.c.exp >= topExp * 0.2).map((x) => ({ c: x.c, w: x.w * x.c.hp }));
  const total = weighted.reduce((a, x) => a + x.w, 0);
  if (!total) return { hit: [], pr: [], main: [], m: {} };
  const main = [...weighted].sort((a, b) => b.c.exp - a.c.exp).slice(0, 8).map((x) => x.c.name);
  const hits = VOC_ELEMENTS[voc]
    .map((e) => [e, Math.round(weighted.reduce((a, x) => a + x.w * (x.c.el[e] ?? 100), 0) / total)])
    .sort((a, b) => b[1] - a[1]);
  const hit = hits.filter(([, pct], i) => i === 0 || (i === 1 && pct >= hits[0][1] - 5));
  const incoming = {};
  for (const x of weighted) for (const [e, share] of Object.entries(x.c.out ?? {})) incoming[e] = (incoming[e] ?? 0) + (x.w * share) / 100;
  const sum = Object.values(incoming).reduce((a, b) => a + b, 0);
  const pr = sum
    ? Object.entries(incoming)
        .map(([e, v]) => [e, Math.round((v * 100) / sum)])
        .sort((a, b) => b[1] - a[1])
        .filter(([, pct], i) => i === 0 || (i === 1 && pct >= 15))
    : [];
  return { hit, pr, main, m: Object.fromEntries(hits) };
}

/** "~-130k", "1,0kk–1,2kk" stay as written; words become "loss" or null (= "var" on screen) */
export function amount(text) {
  const t = (text ?? '').trim();
  const m = t.match(/~?-?\d[\d.,]*\s*k{0,2}(\s*[–-]\s*~?-?\d[\d.,]*\s*k{0,2})?\+?/i);
  if (m) return m[0].replace(/\s+/g, '');
  return /preju[ií]zo/i.test(t) ? 'loss' : null;
}

/** Number used to sort a value ("1,6kk–1,7kk" → 1600000); "loss" sorts below every profit */
export function amountValue(v) {
  if (v === 'loss') return -1;
  const m = v?.match(/(-?\d[\d.,]*)\s*(k{0,2})/i);
  if (!m) return null;
  const n = Number(m[1].replace(/,/g, '.').replace(/\.(?=.*\.)/g, ''));
  return Math.round(n * (m[2].length === 2 ? 1e6 : m[2].length === 1 ? 1e3 : 1));
}

/** Rows of a guide's tables; team content is left out */
export function guideRows(markdown) {
  const out = [];
  for (const line of markdown.split(/\r?\n/)) {
    if (!line.startsWith('|') || /^\|\s*(Level|---)/.test(line)) continue;
    const [level, local, xp, loot, tip] = line.split('|').slice(1).map((c) => c.trim());
    if (!local || xp === undefined || loot === undefined) continue;
    if (/\b(time|team)\b/i.test(`${local} ${xp} ${loot} ${tip ?? ''}`)) continue;
    const lv = Number(level.match(/\d+/)?.[0]);
    if (!lv) continue;
    out.push({
      lv,
      name: local.replace(/\[([^\]]*)\]\([^)]*\)/g, '$1').replace(/\*/g, '').trim(),
      slug: local.match(/\]\(\/hunting\/([^)]+)\)/)?.[1] ?? null,
      x: amount(xp),
      l: amount(loot),
    });
  }
  return out;
}

async function main() {
  const read = async (f) => JSON.parse(await readFile(path.join(ROOT, f), 'utf8'));
  const { places } = await read('shared/hunting-places.json');
  const { creatures } = await read('shared/creatures.json');
  const { videos } = await read('shared/hunt-videos.json');
  const stats = new Map(creatures.map((c) => [c.name.toLowerCase(), c]));
  const creatureKeys = new Map(creatures.map((c) => [norm(c.name), c.name]));
  const resolve = resolver(places, creatureKeys);

  const rows = new Map();
  const put = (v, resolved, fields) => {
    const key = `${v}|${resolved.label.toLowerCase()}`;
    const cur = rows.get(key);
    if (!cur) {
      rows.set(key, { v, name: resolved.label, resolved, lv: fields.lv, x: fields.x ?? null, l: fields.l ?? null, guide: !!fields.guide });
      return;
    }
    // The lowest level anyone recommends; what creators report in a video wins over a range in a guide
    cur.lv = Math.min(cur.lv, fields.lv);
    cur.x = fields.reported ? (fields.x ?? cur.x) : (cur.x ?? fields.x ?? null);
    cur.l = fields.reported ? (fields.l ?? cur.l) : (cur.l ?? fields.l ?? null);
    cur.guide ||= !!fields.guide;
  };

  const dir = path.join(ROOT, 'frontend/src/content/quickhunts');
  for (const file of await readdir(dir)) {
    const v = GUIDES[file];
    if (!v) continue;
    for (const g of guideRows(await readFile(path.join(dir, file), 'utf8'))) put(v, resolve(g.name, g.slug), { ...g, guide: true });
  }
  const groups = new Map();
  let unplaced = 0;
  for (const video of videos) {
    const resolved = placeFromTitle(video.title, places, resolve);
    // A creature named in passing ("Demon Hunter Outfit") or a boss fight is not a hunt: creature-only titles must state numbers
    const bossOnly = resolved?.creatures.every((c) => stats.get(c.toLowerCase())?.boss);
    if (!resolved || bossOnly || (resolved.linkOnly && !video.x && !video.l)) {
      unplaced++;
      continue;
    }
    const key = `${video.v}|${resolved.label.toLowerCase()}`;
    if (!groups.has(key)) groups.set(key, { v: video.v, resolved, list: [] });
    groups.get(key).list.push(video);
  }
  for (const g of groups.values()) {
    put(g.v, g.resolved, {
      lv: Math.min(...g.list.map((x) => x.lv)),
      x: spread(g.list.flatMap((x) => (x.x ? [x.x] : []))),
      l: spread(g.list.flatMap((x) => (x.l ? [x.l] : []))),
      reported: true,
    });
  }
  console.log(`videos without a known place: ${unplaced} of ${videos.length}`);

  const output = [...rows.values()]
    .map((r) => {
      const { hit, pr, main, m } = elementsFor(r.v, r.resolved.creatures, r.resolved.place, stats);
      return {
        v: r.v,
        lv: r.lv,
        name: r.name,
        slug: r.resolved.place ? slugify(r.resolved.place.name) : null,
        x: r.x,
        l: r.l,
        xs: amountValue(r.x),
        ls: amountValue(r.l),
        c: main,
        hit,
        pr,
        m,
      };
    })
    // Nothing to show: no element and no number
    .filter((r) => r.hit.length || r.x || r.l)
    .sort((a, b) => a.v.localeCompare(b.v) || a.lv - b.lv || a.name.localeCompare(b.name));

  await writeFile(
    path.join(ROOT, 'shared/hunt-table.json'),
    JSON.stringify({
      _comment: `Generated by scripts/build-hunt-table.mjs on ${new Date().toISOString().slice(0, 10)}. Do not edit by hand. Creatures and elements: TibiaWiki (CC BY-SA). XP/h and profit/h: community reports and BagParser guides. hit = [element, % of damage it takes]; m = the same for every element of the vocation; pr = [element, share of the damage received in %].`,
      rows: output,
    }) + '\n',
  );
  const noEl = output.filter((r) => !r.hit.length);
  const counts = Object.fromEntries(Object.keys(GUIDES).map((f) => [GUIDES[f], output.filter((r) => r.v === GUIDES[f]).length]));
  console.log(`${output.length} rows`, counts, `with XP: ${output.filter((r) => r.x).length}, no elements: ${noEl.length}`);
  if (noEl.length) console.log(`no creatures resolved: ${[...new Set(noEl.map((r) => r.name))].join(' | ')}`);
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  main().catch((err) => {
    console.error(err);
    process.exit(1);
  });
}
