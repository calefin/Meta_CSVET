import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';
import { loadSoulCores, soulCoreIndex } from './soulcoreRoutes.js';

const FILE = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../shared/soulcores.json');

describe('soul core catalog (D-41)', () => {
  it('loads the generated catalog', async () => {
    const list = await loadSoulCores(FILE);
    expect(list.length).toBeGreaterThan(700);
    expect(list.every((s) => s.creature && s.itemId > 0)).toBe(true);
    expect(list.some((s) => s.creature === 'Ominous')).toBe(false);
  });

  it('resolves names case-insensitively, with or without the "Soul Core" suffix', () => {
    const resolve = soulCoreIndex([
      { creature: 'Dragon Lord', itemId: 1, bestiaryClass: 'Dragon', difficulty: 'Hard' },
    ]);
    expect(resolve('dragon lord')).toBe('Dragon Lord');
    expect(resolve('  Dragon   Lord Soul Core ')).toBe('Dragon Lord');
    expect(resolve('Dragon')).toBeNull();
    expect(resolve(42)).toBeNull();
  });

  it('returns an empty list when the file is missing', async () => {
    expect(await loadSoulCores('/nope/soulcores.json')).toEqual([]);
  });
});
