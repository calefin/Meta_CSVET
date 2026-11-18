import { describe, expect, it } from 'vitest';
import {
  balancesWithExpenses,
  charmCreatureStats,
  computeSetStats,
  expensesTotal,
  imbuementsFor,
  searchEquipment,
  type Equipment,
} from './build';
import { analysePaste, combineAnalyses } from './huntLog';
import { computeLootSplit } from './party';
import equipment from '../../../shared/equipment.json';

const list = (equipment as unknown as { items: Equipment[] }).items;
const index = new Map(list.map((e) => [e.name.toLowerCase(), e]));

describe('equipment catalog', () => {
  it('has the main slots and known items', () => {
    const galoshes = index.get('sanguine galoshes');
    expect(galoshes?.slot).toBe('boots');
    expect(galoshes?.a?.['magic level']).toBe(2);
    expect(galoshes?.r?.fire).toBe(8);
    for (const slot of ['helmet', 'armor', 'legs', 'boots', 'weapon', 'shield', 'ring', 'amulet']) {
      expect(list.filter((e) => e.slot === slot).length).toBeGreaterThan(40);
    }
  });

  it('searches one slot, prefix first, own vocation first', () => {
    const r = searchEquipment(list, 'soul', 'armor', 'sorcerer');
    expect(r[0].name).toBe('Soulmantle');
    expect(r.every((e) => e.slot === 'armor')).toBe(true);
  });
});

describe('computeSetStats', () => {
  it('sums attributes, resistances and imbuements per source', () => {
    const s = computeSetStats(
      {
        slots: {
          boots: { name: 'Sanguine Galoshes', imbues: [{ type: 'swiftness', tier: 3 }] },
          armor: { name: 'Soulmantle', imbues: [{ type: 'dragon hide', tier: 3 }, { type: 'vampirism', tier: 3 }] },
          helmet: { name: 'Unknown Hat', imbues: [{ type: 'void', tier: 3 }] },
        },
        consumables: ['Mastermind Potion'],
      },
      index,
    );
    const ml = s.stats.find((x) => x.key === 'magic level');
    expect(ml?.total).toBe(2 + 4 + 3);
    expect(ml?.sources.map((x) => x.from)).toEqual(['Soulmantle', 'Sanguine Galoshes', 'Mastermind Potion']);
    expect(s.stats.find((x) => x.key === 'mana leech')?.total).toBe(8);
    expect(s.stats.find((x) => x.key === 'life leech')?.total).toBe(25);
    expect(s.stats.find((x) => x.key === 'speed')?.total).toBe(40);
    expect(s.resist.find((x) => x.key === 'fire')?.total).toBe(8 + 15);
    expect(s.resist[0].key).toBe('physical');
    expect(s.missing).toEqual(['Unknown Hat']);
    expect(s.augments[0].from).toBe('Sanguine Galoshes');
  });

  it('offers only the imbuements an item takes', () => {
    expect(imbuementsFor({ slot: 'boots' }).map((i) => i.type)).toEqual(['swiftness', 'vibrancy']);
    expect(imbuementsFor({ slot: 'weapon', type: 'Wands' }).map((i) => i.type)).toContain('epiphany');
    expect(imbuementsFor({ slot: 'weapon', type: 'Sword Weapons' }).map((i) => i.type)).not.toContain('epiphany');
    expect(imbuementsFor({ slot: 'ring' })).toEqual([]);
  });
});

describe('charms and expenses', () => {
  it('groups charm stats per creature', () => {
    const r = charmCreatureStats([
      { name: 'Savage Blow', kind: 'major', tier: 3, creature: 'wardragon' },
      { name: 'Low Blow', kind: 'major', tier: 2, creature: 'dragolisk' },
      { name: 'Gut', kind: 'minor', tier: 3, creature: 'dragolisk' },
    ]);
    expect(r.find((x) => x.creature === 'wardragon')?.lines).toEqual([{ stat: 'critical extra damage', value: 44, from: 'Savage Blow' }]);
    expect(r.find((x) => x.creature === 'dragolisk')?.lines.map((l) => l.value)).toEqual([8, 12]);
  });

  it('takes extra expenses off the payer before the split', () => {
    const rows = [{ tc: 25, tc_price: 38000, gold: 0, reason: 'prey', payer: 'Bob' }];
    expect(expensesTotal(rows)).toBe(950000);
    const members = [
      { name: 'Ann', balance: 1_000_000, isLeader: true },
      { name: 'Bob', balance: 1_000_000 },
    ];
    const adjusted = balancesWithExpenses(members, rows);
    expect(adjusted.map((m) => m.balance)).toEqual([1_000_000, 50_000]);
    const split = computeLootSplit(adjusted);
    expect(split.sharePerPlayer).toBe(525_000);
    expect(split.transfers).toEqual([{ from: 'Ann', to: 'Bob', amount: 475_000 }]);
    // unknown payer → leader
    expect(balancesWithExpenses(members, [{ ...rows[0], payer: null }])[0].balance).toBe(50_000);
  });
});

describe('combineAnalyses', () => {
  const partyText = `Session data: From 2024-02-19, 16:35:59 to 2024-02-19, 17:39:48
Session: 01:03h
Loot Type: Leader
Loot: 3,019,278
Supplies: 911,660
Balance: 2,107,618
Elder Himanen (Leader)
    Loot: 2,768,147
    Supplies: 384,806
    Balance: 2,383,341
    Damage: 5,000,000
    Healing: 1,000,000
Sir Bob
    Loot: 251,131
    Supplies: 526,854
    Balance: -275,723
    Damage: 4,000,000
    Healing: 200,000`;
  const huntText = `Session data: From 2024-02-19, 16:36:01 to 2024-02-19, 17:39:40
Session: 01:03h
Raw XP Gain: 4,000,000
XP Gain: 11,894,872
Raw XP/h: 3,800,000
XP/h: 11,200,000
Loot: 253,308
Supplies: 552,851
Balance: -299,543
Damage: 5,000,000
Damage/h: 4,700,000
Healing: 1,000,000
Healing/h: 940,000
Killed Monsters:
  994x dragolisk
Looted Items:
  1396x a platinum coin`;

  it('takes XP and kills from the Hunt Analyser and the money from the Party Hunt', () => {
    const r = combineAnalyses([analysePaste(partyText), analysePaste(huntText), analysePaste('')]);
    expect(r.hunt?.kind).toBe('party');
    expect(r.hunt?.xp).toBe(11_894_872);
    expect(r.hunt?.monsters[0]).toEqual({ name: 'dragolisk', count: 994 });
    expect(r.hunt?.balance).toBe(2_107_618);
    expect(r.hunt?.party?.members).toHaveLength(2);
    expect(r.formats.sort()).toEqual(['hunt', 'party']);
  });

  it('works with a single part', () => {
    expect(combineAnalyses([analysePaste(huntText)]).hunt?.kind).toBe('solo');
    expect(combineAnalyses([analysePaste('')]).hunt).toBeNull();
  });
});

describe('itemLibrary (D-61)', () => {
  it('collects configured items from saved sets, once each', async () => {
    const { itemLibrary } = await import('./build');
    const lib = itemLibrary([
      { slots: { weapon: { name: 'Falcon Longsword', tier: 2, imbues: [{ type: 'vampirism', tier: 3 }, { type: 'strike', tier: 3 }] }, boots: { name: 'Leather Boots' } }, consumables: [] },
      { slots: { weapon: { name: 'Falcon Longsword', tier: 2, imbues: [{ type: 'strike', tier: 3 }, { type: 'vampirism', tier: 3 }] } }, consumables: [] },
    ]);
    expect(lib.weapon).toHaveLength(1);
    expect(lib.weapon?.[0].imbues?.map((i) => i.type)).toEqual(['strike', 'vampirism']);
    expect(lib.boots).toBeUndefined();
  });
});
