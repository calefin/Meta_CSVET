import { describe, expect, it } from 'vitest';
import { cleanCreatureName, creatureKey, duplicateGroups, pickCanonical } from './creatureNames.js';

describe('creature names', () => {
  it('maps every spelling to one key', () => {
    expect(creatureKey('Bog_Raider')).toBe(creatureKey('Bog Raider'));
    expect(creatureKey('Bone Beast')).toBe('bonebeast');
    expect(creatureKey('Nightmare scion')).toBe(creatureKey('Nightmare Scion'));
    expect(cleanCreatureName('Water_Elemental ')).toBe('Water Elemental');
  });

  it('groups the duplicates found in the database', () => {
    const rows = [
      { id: 34, name: 'Bog Raider', race: 'bograider' },
      { id: 1015, name: 'Bog_Raider', race: null },
      { id: 61, name: 'Bonebeast', race: 'bonebeast' },
      { id: 1005, name: 'Bone Beast', race: null },
      { id: 7, name: 'Dragon', race: 'dragon' },
    ];
    expect(duplicateGroups(rows).map((g) => g.map((r) => r.id))).toEqual([
      [34, 1015],
      [61, 1005],
    ]);
  });

  it('keeps the official spelling', () => {
    // linked to TibiaData wins
    expect(pickCanonical([{ id: 1005, name: 'Bone Beast', race: null }, { id: 61, name: 'Bonebeast', race: 'bonebeast' }]).id).toBe(61);
    // neither linked: no underscore, then capitalisation, then the oldest row
    expect(pickCanonical([{ id: 1014, name: 'Water_Elemental', race: null }, { id: 383, name: 'Water Elemental', race: null }]).id).toBe(383);
    expect(pickCanonical([{ id: 37, name: 'Nightmare scion', race: null }, { id: 756, name: 'Nightmare Scion', race: null }]).id).toBe(756);
    expect(pickCanonical([{ id: 936, name: 'Fury Of The Emperor', race: null }, { id: 726, name: 'Fury of the Emperor', race: null }]).id).toBe(726);
  });
});
