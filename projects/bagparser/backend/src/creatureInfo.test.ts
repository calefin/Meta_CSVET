import Fastify from 'fastify';
import { describe, expect, it } from 'vitest';
import { buildCreatureSheets, registerCreatureInfoRoutes } from './creatureInfo.js';

const wiki = { 'mitmah-seer': { name: 'Mitmah Seer', loot: [{ i: 'Crystal of the Mitmah' }] }, 'lonely-one': { name: 'Lonely One' } };
const stats = [{ name: 'Mitmah Seer', hp: 4620 }];
const level = (n: number | null) => ({ knights: n, paladins: n, mages: n });
const places = [
  { name: 'Iksupan Waterways', city: 'Port Hope', level: level(500), creatures: ['Mitmah Seer'] },
  { name: 'Iksupan Last Stand', city: 'Port Hope', level: level(400), creatures: ['mitmah_seer', 'Mitmah Scout'] },
];

describe('creature sheets (D-80)', () => {
  const sheets = buildCreatureSheets(wiki, stats, places);

  it('joins the stats and the places, lowest level first, whatever the spelling', () => {
    const s = sheets.get('mitmah-seer')!;
    expect(s.stats?.hp).toBe(4620);
    expect(s.places.map((p) => p.slug)).toEqual(['iksupan-last-stand', 'iksupan-waterways']);
  });

  it('keeps creatures without stats or places', () => {
    const s = sheets.get('lonely-one')!;
    expect(s.stats).toBeNull();
    expect(s.places).toEqual([]);
  });

  it('serves one creature and answers 404 for the rest', async () => {
    const app = Fastify();
    registerCreatureInfoRoutes(app, { sheets });
    const ok = await app.inject({ url: '/api/creature-info/mitmah-seer' });
    expect(ok.statusCode).toBe(200);
    expect(ok.json().name).toBe('Mitmah Seer');
    expect((await app.inject({ url: '/api/creature-info/nope' })).statusCode).toBe(404);
    await app.close();
  });
});
