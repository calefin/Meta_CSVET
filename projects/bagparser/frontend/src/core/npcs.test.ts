import { describe, expect, it } from 'vitest';
import { npcInfo, npcPlace } from './npcs';

describe('npc places', () => {
  it('formats sub-area and city', () => {
    expect(npcPlace("Nah'Bob")).toBe("Ashta'daramai, Darashia");
    expect(npcPlace('Fiona')).toBe('Edron');
  });
  it('treats Rashid and Yasir as travelling', () => {
    expect(npcInfo('Rashid')?.travelling).toBe(true);
    expect(npcPlace('Yasir')).toBe('');
  });
});
