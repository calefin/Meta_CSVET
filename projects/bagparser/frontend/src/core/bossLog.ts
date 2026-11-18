export const BOSS_LOG_RE = /(?:^|\]\s*|\b\d{1,2}:\d{2}(?::\d{2})?\s+)Loot of (?:an?\s+)?([A-Za-z0-9\s'.-]+?):\s*(.+?)\.?\s*$/i;

export interface BossDropEntry {
  count: number;
  name: string;
}

export interface ParsedBossDropLine {
  bossName: string;
  drops: BossDropEntry[];
}

export function parseBossDropLine(line: string): ParsedBossDropLine | null {
  const m = line.trim().match(BOSS_LOG_RE);
  if (!m) return null;

  const bossName = m[1].trim();
  const body = m[2].trim();
  if (/^nothing$/i.test(body)) return { bossName, drops: [] };

  const drops: BossDropEntry[] = [];
  for (const part of body.split(',')) {
    const raw = part.trim();
    if (!raw) continue;
    const match = raw.match(/^(?:([\d,.]+)x\s+|(\d+)\s+)?(?:an?\s+)?(.+)$/i);
    if (match) {
      const count = parseInt((match[1] ?? match[2] ?? '1').replace(/[,.]/g, ''), 10) || 1;
      const name = match[3].trim();
      drops.push({ count, name });
    }
  }
  return { bossName, drops };
}
