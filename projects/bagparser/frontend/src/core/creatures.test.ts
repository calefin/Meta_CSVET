import { describe, expect, it } from 'vitest';
import { bestElements, charmEstimates, creatureRows, findCreature, loadCreatures } from './creatures';

describe('creature analysis', () => {
  it('finds creatures from analyser names (plural, lower case)', async () => {
    const idx = await loadCreatures();
    expect(findCreature(idx, 'dragon lords')?.name).toBe('Dragon Lord');
    expect(findCreature(idx, 'Wardragons')?.name).toBe('Wardragon');
    expect(findCreature(idx, 'Not A Creature')).toBeNull();
  });

  it('builds rows with weaknesses, best charm and bestiary share', async () => {
    const idx = await loadCreatures();
    const rows = creatureRows(
      [
        { name: 'Dragon', count: 100 },
        { name: 'Hydra', count: 10 },
        { name: 'Mystery Thing', count: 3 },
      ],
      idx,
    );
    const dragon = rows.find((r) => r.name === 'Dragon')!;
    expect(dragon.hpTotal).toBe(100 * dragon.stats!.hp);
    expect(dragon.weak[0].element).toBe('ice');
    expect(dragon.strong[0]).toEqual({ element: 'fire', pct: 0 });
    expect(dragon.bestCharm?.name).toBe('Freeze');
    expect(dragon.bestiaryPct).toBe(10); // Medium: 1000 kills
    expect(rows[rows.length - 1]?.stats).toBeNull();
    const best = bestElements(rows);
    // Hydras resist ice (50 %): over this mix, physical is the safer element
    expect(best[0].element).toBe('physical');
  });

  it('estimates elemental charm damage only with Server Log attacks', async () => {
    const idx = await loadCreatures();
    const monsters = [{ name: 'Dragon', count: 100 }];
    const [noLog] = charmEstimates([{ name: 'Freeze', kind: 'major', tier: 2, creature: 'dragon' }], monsters, idx);
    expect(noLog.perHit).toBe(Math.round(1000 * 0.05 * 1.1));
    expect(noLog.procs).toBeNull();
    const [withLog, gut] = charmEstimates(
      [
        { name: 'Freeze', kind: 'major', tier: 2, creature: 'dragon' },
        { name: 'Gut', kind: 'minor', tier: 1, creature: 'dragon' },
      ],
      monsters,
      idx,
      { hits: 1000, by_target: [{ name: 'Dragon', pct: 100 }] },
    );
    expect(withLog.procs).toBe(100); // 1000 attacks × 10 %
    expect(withLog.damage).toBe(100 * withLog.perHit!);
    expect(gut.note).toBe('loot');
  });
});

describe('damage per creature and type (D-62)', () => {
  it('splits the Input Analyser example exactly', async () => {
    const { damageMatrix } = await import('./creatures');
    const { parseInputAnalyser } = await import('./huntLog');
    const idx = await loadCreatures();
    const input = parseInputAnalyser(`Received Damage
Total: 152
Max-DPS: 1,306
Damage Types
  Earth 83 (54.6%)
  Physical 69 (45.4%)
Damage Sources
  glooth blob 83 (54.6%)
  minotaur hunter 69 (45.4%)`)!;
    expect(input.total).toBe(152);
    const m = damageMatrix(input, idx);
    expect(m.rows).toEqual([
      { name: 'glooth blob', creature: 'Glooth Blob', total: 83, types: { Earth: 83 } },
      { name: 'minotaur hunter', creature: 'Minotaur Hunter', total: 69, types: { Physical: 69 } },
    ]);
    expect(m.estimated).toBe(false);
  });

  it('marks mixed or unknown sources as an estimate and keeps the totals', async () => {
    const { damageMatrix } = await import('./creatures');
    const idx = await loadCreatures();
    const m = damageMatrix(
      {
        types: [
          { name: 'Physical', value: 600 },
          { name: 'Ice', value: 300 },
          { name: 'Fire', value: 100 },
        ],
        sources: [
          { name: 'hydra', value: 700 },
          { name: 'something odd', value: 300 },
        ],
      },
      idx,
    );
    expect(m.estimated).toBe(true);
    for (const r of m.rows) expect(Object.values(r.types).reduce((a, b) => a + b, 0)).toBe(r.total);
    expect(m.rows[0].types.Fire ?? 0).toBe(0); // hydras do not deal fire
  });
});

describe('received damage over many hunts (D-62)', () => {
  it('adds types and creatures, merging names through the catalog split', async () => {
    const { sumReceived } = await import('./creatures');
    const r = sumReceived([
      { types: [{ name: 'Earth', value: 83 }, { name: 'Physical', value: 69 }], sources: [{ name: 'glooth blob', value: 83 }, { name: 'minotaur hunter', value: 69 }], by_source_type: [{ name: 'glooth blob', creature: 'Glooth Blob', total: 83, types: { Earth: 83 } }] },
      { types: [{ name: 'Earth', value: 17 }], sources: [{ name: 'glooth blobs', value: 17 }], by_source_type: [{ name: 'glooth blobs', creature: 'Glooth Blob', total: 17, types: { Earth: 17 } }] },
      null,
    ]);
    expect(r.hunts).toBe(2);
    expect(r.total).toBe(169);
    expect(r.types[0]).toMatchObject({ name: 'Earth', value: 100 });
    expect(r.sources[0]).toMatchObject({ name: 'Glooth Blob', value: 100, types: { Earth: 100 } });
  });
});
