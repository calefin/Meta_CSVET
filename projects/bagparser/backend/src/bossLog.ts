/** BossLedger log parsing and dedup hashing (SPEC §7, FR-BL-01..03). */
import { createHash } from 'node:crypto';
import { readFile } from 'node:fs/promises';
import type pg from 'pg';
import { normalizeName, parseEntry } from './normalize.js';

// SPEC §7.1 pattern, supporting timestamps ("12:34", "12:34:56", "[2026-11-04 12:34:56]") and optional articles "a / an"
export const BOSS_LOG_RE = /(?:^|\]\s*|\b\d{1,2}:\d{2}(?::\d{2})?\s+)Loot of (?:an?\s+)?([A-Za-z0-9\s'.-]+?):\s*(.+?)\.?\s*$/i;

export interface BossDrop {
  count: number;
  name: string;
  doubleBonus?: boolean;
}

export interface ParsedBossLog {
  bossName: string;
  drops: BossDrop[];
}

export function parseBossLogLine(line: string): ParsedBossLog | null {
  const m = line.trim().match(BOSS_LOG_RE);
  if (!m) return null;

  const bossName = m[1].trim();
  const body = m[2].trim();
  if (/^nothing$/i.test(body)) return { bossName, drops: [] };

  const drops: BossDrop[] = [];
  for (const part of body.split(',')) {
    const entry = parseEntry(part);
    if (entry) drops.push(entry);
  }
  return { bossName, drops };
}

/** "2026-11-03-14" */
export function utcHourKey(date: Date): string {
  return date.toISOString().slice(0, 13).replace('T', '-');
}

/** SHA256(BossName + UTCDate(YYYY-MM-DD-HH) + SortedItemsList) */
export function payloadHash(parsed: ParsedBossLog, at: Date): string {
  const items = parsed.drops.map((d) => `${d.count}x ${normalizeName(d.name)}`).sort();
  return createHash('sha256')
    .update(normalizeName(parsed.bossName) + utcHourKey(at) + items.join(','))
    .digest('hex');
}

/** Salted SHA256 of IP + User-Agent; raw values are never stored (FR-BL-04, NFR-07). */
export function playerFingerprint(ip: string, userAgent: string, salt: string): string {
  return createHash('sha256').update(`${salt}|${ip}|${userAgent}`).digest('hex');
}

/** D-80: every boss the wiki knows, with its cooldown in whole hours (null = the wiki gives none) */
export function bossSeedRows(creatures: { name: string; boss: boolean; cd?: number | null }[]): { name: string; hours: number | null }[] {
  return creatures.filter((c) => c.boss).map((c) => ({ name: c.name, hours: c.cd && c.cd > 0 ? Math.ceil(c.cd) : null }));
}

/** Adds the missing bosses and corrects cooldowns from the wiki; bosses that only exist in our list stay as they are */
export async function seedBosses(db: Pick<pg.Pool, 'query'>, creaturesFile: string): Promise<number> {
  const { creatures } = JSON.parse(await readFile(creaturesFile, 'utf8')) as { creatures: { name: string; boss: boolean; cd?: number | null }[] };
  const rows = bossSeedRows(creatures);
  const names = rows.map((r) => r.name);
  const hours = rows.map((r) => r.hours);
  const added = await db.query(
    `INSERT INTO bosses (name, cooldown_hours)
     SELECT u.n, COALESCE(u.h, 20) FROM unnest($1::text[], $2::int[]) AS u(n, h)
      WHERE NOT EXISTS (SELECT 1 FROM bosses b WHERE LOWER(b.name) = LOWER(u.n))
     ON CONFLICT (name) DO NOTHING`,
    [names, hours],
  );
  await db.query(
    `UPDATE bosses b SET cooldown_hours = u.h FROM unnest($1::text[], $2::int[]) AS u(n, h)
      WHERE LOWER(b.name) = LOWER(u.n) AND u.h IS NOT NULL AND b.cooldown_hours <> u.h`,
    [names, hours],
  );
  return added.rowCount ?? 0;
}
