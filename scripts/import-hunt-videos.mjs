#!/usr/bin/env node
/**
 * Solo hunts reported in YouTube video titles (D-81): vocation, level, XP/h and profit/h as each creator states them,
 * e.g. "EK 200+ Grim Reapers (Yalahar) BEST Solo Hunt | 4,7KK EXP/H & 50K Profit". scripts/build-hunt-table.mjs then works
 * out which place each title talks about and links the videos in the table.
 *
 * Usage: node scripts/import-hunt-videos.mjs [--from=saved-titles.json]   (needs YOUTUBE_API_KEY in .env; 30 searches = 3.000 of the 10.000 daily units)
 * Output: shared/hunt-videos.json  { _comment, videos: [{ title, v, lv, x, l }] }   no video ids or channel names: the site never links to or names other sites   x / l: [low, high] per hour, or null
 */
import { readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const SEARCHES = { knight: 'Elite Knight', paladin: 'Royal Paladin', monk: 'Exalted Monk', sorcerer: 'Master Sorcerer', druid: 'Elder Druid' };
const BANDS = [100, 200, 300, 500, 700, 1000];
const PUBLISHED_AFTER = '2025-06-01T00:00:00Z';

const VOCATIONS = [
  ['knight', /\b(ek|knights?)\b/i],
  ['paladin', /\b(rp|paladins?)\b/i],
  ['monk', /\b(em|monks?)\b/i],
  ['sorcerer', /\b(ms|sorcerers?|sorc)\b/i],
  ['druid', /\b(ed|druids?)\b/i],
];
/** Team content and private servers do not belong in a table of solo hunts on official worlds */
const LEFT_OUT = /\b(team|teams|party|duo|trio|pt\s*x?\d|x[2-5]|rubinot|otserv(er)?|ots|baiak|taleon|tibiaking)\b/i;
const VOC_WORD = String.raw`ek|rp|ed|ms|em|knight|paladin|druid|sorcerer|monk`;
const NUM = String.raw`(\d+(?:[.,]\d+)?)\s*(?:[-–]\s*(\d+(?:[.,]\d+)?)\s*)?(kk|k|m)\b\+?`;
const HOURLY = /[\\/]\s*h|raw/i;

const XP_AFTER = new RegExp(String.raw`${NUM}\s*(?:[\\/]\s*h(?:r|ora)?\b)?\s*[\\/]?\s*(?:de\s+)?(?:raw\s*)?(?:exp|xp)\b(?:\s*[\\/]\s*h(?:r)?\b)?(?:\s+raw\b)?`, 'i');
const XP_BEFORE = new RegExp(String.raw`(?:raw\s+)?(?:exp|xp)\s*[:,]?\s*${NUM}\s*\+?\s*(?:[\\/]\s*h(?:r)?\b)?`, 'i');
/** "3.2KK RAW + 830K PROFIT": raw alone already means XP per hour */
const XP_RAW = new RegExp(String.raw`${NUM}\s*[\\/]?\s*raw\b`, 'i');
const PROFIT_AFTER = new RegExp(String.raw`${NUM}\s*(?:[\\/]\s*h(?:r)?\s*)?(?:de\s+)?(?:profit|lucro|gold)\b`, 'i');
const PROFIT_BEFORE = new RegExp(String.raw`(?:profit|lucro)\s*[:,]?\s*${NUM}(?:\s*[\\/]\s*h(?:r)?\b)?`, 'i');

export const decode = (s) => s.replace(/&amp;/g, '&').replace(/&#39;/g, "'").replace(/&quot;/g, '"');

function toRange(m) {
  const unit = m[3].toLowerCase() === 'k' ? 1e3 : 1e6;
  const value = (s) => Math.round(Number(s.replace(',', '.')) * unit);
  return m[2] ? [value(m[1]), value(m[2])] : [value(m[1]), value(m[1])];
}

/** What one title states; null when it is not a solo hunt of exactly one vocation with a level */
export function parseTitle(raw) {
  const title = decode(raw);
  if (LEFT_OUT.test(title)) return null;
  const vocs = VOCATIONS.filter(([, re]) => re.test(title)).map(([v]) => v);
  if (vocs.length !== 1) return null;
  const level =
    title.match(new RegExp(String.raw`\b(?:${VOC_WORD}|lvl|level|n[ií]vel|lv)\s*\.?\s*(\d{2,4})\b`, 'i'))?.[1] ??
    title.match(new RegExp(String.raw`\b(\d{2,4})\s*\+?\s*(?:${VOC_WORD})\b`, 'i'))?.[1] ??
    title.match(/\b(\d{2,4})\s*\+/)?.[1];
  const lv = Number(level);
  if (!lv || lv < 8 || lv > 2500) return null;
  const xpMatch = title.match(XP_AFTER) ?? title.match(XP_BEFORE) ?? title.match(XP_RAW);
  const xpHourly = !!xpMatch && HOURLY.test(xpMatch[0]);
  const x = xpHourly ? toRange(xpMatch) : null;
  const pMatch = title.match(PROFIT_AFTER) ?? title.match(PROFIT_BEFORE);
  // Totals of a whole session ("10kk profit 53kk xp") say nothing per hour
  const l = pMatch && (HOURLY.test(pMatch[0]) || xpHourly) ? toRange(pMatch) : null;
  return { v: vocs[0], lv, x, l };
}

async function search(key) {
  const found = new Map();
  for (const [, name] of Object.entries(SEARCHES)) {
    for (const lv of BANDS) {
      const q = `tibia ${name} ${lv}+ solo hunt xp/h profit`;
      const url = `https://www.googleapis.com/youtube/v3/search?${new URLSearchParams({ part: 'snippet', type: 'video', maxResults: '50', q, order: 'relevance', publishedAfter: PUBLISHED_AFTER, key })}`;
      const res = await fetch(url);
      if (!res.ok) throw new Error(`YouTube HTTP ${res.status}`);
      for (const it of (await res.json()).items ?? []) {
        found.set(it.id.videoId, { id: it.id.videoId, title: it.snippet.title, channel: it.snippet.channelTitle, at: it.snippet.publishedAt.slice(0, 10) });
      }
    }
  }
  return [...found.values()];
}

async function main() {
  const from = process.argv.find((a) => a.startsWith('--from='))?.slice(7);
  let list;
  if (from) list = JSON.parse(await readFile(from, 'utf8'));
  else {
    const env = await readFile(path.join(ROOT, '.env'), 'utf8').catch(() => '');
    const key = process.env.YOUTUBE_API_KEY ?? env.match(/^YOUTUBE_API_KEY=(.+)$/m)?.[1]?.trim();
    if (!key) throw new Error('YOUTUBE_API_KEY is missing');
    list = await search(key);
  }
  const videos = list
    .flatMap((video) => {
      const parsed = parseTitle(video.title);
      return parsed ? [{ title: decode(video.title), ...parsed }] : [];
    })
    .sort((a, b) => a.v.localeCompare(b.v) || a.lv - b.lv || a.title.localeCompare(b.title));
  await writeFile(
    path.join(ROOT, 'shared/hunt-videos.json'),
    JSON.stringify({ _comment: `Generated by scripts/import-hunt-videos.mjs on ${new Date().toISOString().slice(0, 10)} from YouTube titles. x / l = XP and profit per hour as stated by each creator.`, videos }).replace(/\},\{/g, '},\n{') + '\n',
  );
  console.log(`${list.length} titles, ${videos.length} usable, with XP: ${videos.filter((v) => v.x).length}, with profit: ${videos.filter((v) => v.l).length}`);
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  main().catch((err) => {
    console.error(err.message);
    process.exit(1);
  });
}
