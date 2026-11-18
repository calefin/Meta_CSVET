/** Run with: node --test scripts/*.test.mjs */
import assert from 'node:assert/strict';
import test from 'node:test';
import { block, cleanWiki, damageShares, parseAbilities, parseLoot } from './import-creatures.mjs';

const PAGE = `{{Infobox Creature
| abilities      = {{Ability List
|{{Melee|0-400}}
|{{Healing|range=177-223|scene={{Scene|spell=buffspell}}}}
|{{Ability|Energy Wave|element=energy
 |scene={{Scene|spell=5sqmwavewide2}}}}
|{{Ability|Death Bomb|100-300|death}}
|{{Ability|critical=yes}}
|{{Ability|Turns invisible|2 seconds|invisible}}
}}
| maxdmg         = 950+
| loot           = {{Loot Table
 |{{Loot Item|1|Gold-Brocaded Cloth|semi-rare}}
 |{{Loot Item|Crystal of the Mitmah}}
 |{{Loot Item|0-20|Gold Coin}}
}}
}}`;

test('block stops at the next field, outside templates', () => {
  assert.equal(block(PAGE, 'maxdmg'), '950+');
  assert.match(block(PAGE, 'abilities'), /Turns invisible/);
});

test('abilities keep names, ranges and elements', () => {
  const a = parseAbilities(block(PAGE, 'abilities'));
  assert.deepEqual(a[0], { n: 'Melee', r: '0-400', e: 'physical' });
  assert.deepEqual(a[1], { n: 'Healing', r: '177-223', k: 'heal' });
  assert.deepEqual(a[2], { n: 'Energy Wave', e: 'energy' });
  assert.deepEqual(a[3], { n: 'Death Bomb', r: '100-300', e: 'death' });
  assert.deepEqual(a[4], { n: 'Turns invisible' });
});

test('loot reads quantity, name and rarity', () => {
  assert.deepEqual(parseLoot(block(PAGE, 'loot')), [
    { i: 'Gold-Brocaded Cloth', q: '1', r: 'semi-rare' },
    { i: 'Crystal of the Mitmah' },
    { i: 'Gold Coin', q: '0-20' },
  ]);
});

test('damage shares: a split written in the notes beats everything', () => {
  const notes = 'The attacks consist of roughly 50% [[Physical Damage|Physical]] damage, 35% [[Death Damage|Death]] damage, and 15% [[Energy Damage|Energy]] damage.';
  assert.deepEqual(damageShares({ maxdmg: '950+', abilities: [], notes }), { physical: 50, death: 35, energy: 15 });
});

test('damage shares: Max Damage breakdown, ignoring summons', () => {
  assert.deepEqual(damageShares({ maxdmg: '{{Max Damage|physical=300|poison=100|summons=900}}', abilities: [{ n: 'x', e: 'fire' }] }), { physical: 75, earth: 25 });
});

test('damage shares: a bare max hit falls back to the abilities', () => {
  const abilities = [{ n: 'Melee', r: '0-100', e: 'physical' }, { n: 'Bolt', r: '0-300', e: 'energy' }, { n: 'Heal', k: 'heal' }];
  assert.deepEqual(damageShares({ maxdmg: '950+', abilities }), { energy: 75, physical: 25 });
});

test('cleanWiki turns links into text and drops templates', () => {
  assert.equal(cleanWiki("See [[Fire Damage|fire]] and '''[[Ice]]'''{{Note|x}}<br/>end"), 'See fire and Ice end');
});
