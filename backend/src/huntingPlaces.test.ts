import { describe, expect, it } from 'vitest';
import { estimateDensity, minLevel, slugify } from './huntingPlaces.js';

describe('hunting places (D-42)', () => {
  it('slugifies names', () => {
    expect(slugify("Ab'Dendriel Elf Cave")).toBe('abdendriel-elf-cave');
    expect(slugify('Roshamuul Prison')).toBe('roshamuul-prison');
    expect(slugify('Dragão Ñ')).toBe('dragao-n');
  });

  it('estimates density from the infobox creature and list order', () => {
    const place = { mainCreature: 'Demon Outcast', creatures: ['Nightmare', 'Lost Soul', 'Plaguesmith', 'Demon Outcast', 'Juggernaut'] };
    expect(estimateDensity(place, 'Demon Outcast', 3)).toBe(5);
    expect(estimateDensity(place, 'Nightmare', 0)).toBe(4);
    expect(estimateDensity(place, 'Juggernaut', 4)).toBe(3);
    expect(estimateDensity({ mainCreature: null, creatures: ['A', 'B'] }, 'B', 1)).toBe(4);
  });

  it('takes the lowest recommended level', () => {
    expect(minLevel({ level: { knights: 250, paladins: 200, mages: null } })).toBe(200);
    expect(minLevel({ level: { knights: null, paladins: null, mages: null } })).toBe(0);
  });
});
