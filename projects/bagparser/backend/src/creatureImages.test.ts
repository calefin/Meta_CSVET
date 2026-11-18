import { describe, expect, it } from 'vitest';
import { libraryIndex, libraryLookup, libraryNameKeys } from './creatureImages.js';

describe('creature library matching', () => {
  it('turns official plural names into singular keys', () => {
    expect(libraryNameKeys('Acolytes Of The Cult').has('acolyteofthecult')).toBe(true);
    expect(libraryNameKeys('Massive Fire Elementals').has('massivefireelemental')).toBe(true);
    expect(libraryNameKeys('Werewolves').has('werewolf')).toBe(true);
    expect(libraryNameKeys('Banshees').has('banshee')).toBe(true);
    expect(libraryNameKeys('Lizard Swordsmen').has('lizardswordsman')).toBe(true);
    expect(libraryNameKeys('Wyverns').has('wyvern')).toBe(true);
    expect(libraryNameKeys('Mutated Bats').has('mutatedbat')).toBe(true);
  });

  it('finds our singular names by race or by the plural name', () => {
    const index = libraryIndex([
      { name: 'Acolytes Of The Cult', race: 'cultacolyte', image_url: 'a.gif' },
      { name: 'Cobra Scouts', race: 'cobrascout', image_url: 'c.gif' },
      { name: 'Dragons', race: 'dragon', image_url: 'd.gif' },
    ]);
    expect(libraryLookup(index, 'Acolyte of the Cult')?.race).toBe('cultacolyte');
    expect(libraryLookup(index, 'Cobra Scout')?.race).toBe('cobrascout');
    expect(libraryLookup(index, 'Dragon')?.race).toBe('dragon');
    expect(libraryLookup(index, 'Mahrdis')).toBeUndefined();
  });
});
