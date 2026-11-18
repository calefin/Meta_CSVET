/**
 * Item name normalisation (plural → singular, articles, counts).
 * Mirrors frontend/src/core/items.ts#nameCandidates and parser.ts#parseEntry —
 * keep both in sync (see docs/DECISIONS.md D-15).
 */

function singularizeWord(word: string): string[] {
  const out: string[] = [];
  if (word.endsWith('ies')) out.push(word.slice(0, -3) + 'y');
  if (word.endsWith('ves')) out.push(word.slice(0, -3) + 'f', word.slice(0, -3) + 'fe');
  if (word.endsWith('es')) out.push(word.slice(0, -2));
  if (word.endsWith('s')) out.push(word.slice(0, -1));
  if (word === 'teeth') out.push('tooth');
  return out;
}

export function normalizeName(raw: string): string {
  return raw
    .replace(/\s*\([^)]*\)/g, ' ')
    .toLowerCase()
    .trim()
    .replace(/\.$/, '')
    .replace(/^(?:an?|the)\s+/, '')
    .replace(/\s+/g, ' ');
}

export function nameCandidates(rawName: string): string[] {
  const base = normalizeName(rawName);
  const candidates = [base];
  const ofIdx = base.indexOf(' of ');
  if (ofIdx > 0) {
    const headWords = base.slice(0, ofIdx).split(' ');
    const tail = base.slice(ofIdx);
    const last = headWords.pop()!;
    for (const s of singularizeWord(last)) candidates.push([...headWords, s].join(' ') + tail);
  } else {
    const words = base.split(' ');
    const last = words.pop()!;
    for (const s of singularizeWord(last)) candidates.push([...words, s].join(' '));
  }
  return candidates;
}

/** "2x platinum coin" | "2 platinum coins" | "a ferumbras' hat" | "a cobra sword (Double bonus)" → { count, name, doubleBonus } */
export function parseEntry(text: string): { count: number; name: string; doubleBonus?: boolean } | null {
  const isDouble = /\(double bonus\)/i.test(text);
  const cleaned = text.replace(/\s*\([^)]*\)/g, ' ').replace(/\s+/g, ' ').trim();
  const m = cleaned.match(/^(?:([\d,.]+)x\s+|(\d+)\s+)?(.+)$/i);
  if (!m) return null;
  const rawCount = m[1] ?? m[2];
  const count = rawCount ? parseInt(rawCount.replace(/[,.]/g, ''), 10) : 1;
  const name = m[3].trim().replace(/^(?:an?)\s+/i, '');
  return name ? { count, name, ...(isDouble ? { doubleBonus: true } : {}) } : null;
}
