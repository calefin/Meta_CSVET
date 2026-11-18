/** Run with: node --test scripts/*.test.mjs */
import assert from 'node:assert/strict';
import test from 'node:test';
import { amount, amountValue, creaturesInName, elementsFor, guideRows } from './build-hunt-table.mjs';

const stats = new Map(
  [
    { name: 'Hydra', hp: 2350, exp: 2100, boss: false, el: { ice: 110, fire: 90 }, out: { earth: 60, physical: 40 } },
    { name: 'Medusa', hp: 4500, exp: 4050, boss: false, el: { fire: 120, ice: 110 }, out: { earth: 80, physical: 20 } },
    { name: 'Slime', hp: 150, exp: 160, boss: false, el: { fire: 300 }, out: { physical: 100 } },
    { name: 'Boss', hp: 90000, exp: 90000, boss: true, el: { holy: 300 }, out: { holy: 100 } },
  ].map((c) => [c.name.toLowerCase(), c]),
);

test('elements: trash and bosses do not count; sorcerers only get their elements', () => {
  const { hit, pr, main } = elementsFor('sorcerer', ['Hydra', 'Medusa', 'Slime', 'Boss'], null, stats);
  assert.deepEqual(main, ['Medusa', 'Hydra']);
  assert.equal(hit[0][0], 'fire');
  assert.equal(hit.some(([e]) => e === 'ice'), false);
  assert.equal(pr[0][0], 'earth');
});

test('elements: druids may hit with ice', () => {
  assert.equal(elementsFor('druid', ['Hydra'], null, stats).hit[0][0], 'ice');
});

test('no known creature gives no recommendation', () => {
  assert.deepEqual(elementsFor('knight', ['Nobody'], null, stats), { hit: [], pr: [], main: [], m: {} });
});

test('amounts keep numbers, words are loss or unknown', () => {
  assert.equal(amount('1,6kk–1,7kk'), '1,6kk–1,7kk');
  assert.equal(amount('prejuízo forte (~-130k)'), '~-130k');
  assert.equal(amount('prejuízo total'), 'loss');
  assert.equal(amount('moderado'), null);
  assert.equal(amount('variável'), null);
  assert.equal(amountValue('1,6kk–1,7kk'), 1600000);
  assert.equal(amountValue('~-130k'), -130000);
  assert.equal(amountValue('45k'), 45000);
  assert.equal(amountValue(null), null);
});

test('creature names inside a place name, plural and several', () => {
  const keys = new Map([['dragon lord', 'Dragon Lord'], ['mammoth', 'Mammoth'], ['badger', 'Badger'], ['fire', 'Fire']]);
  assert.deepEqual(creaturesInName('Dragon Lords Fenrock', keys), ['Dragon Lord']);
  assert.deepEqual(creaturesInName('Mammoths/Badgers Svargrond', keys), ['Mammoth', 'Badger']);
  assert.deepEqual(creaturesInName('Fire Library', keys), []);
});

test('guide rows: links, level ranges, team content left out', () => {
  const md = [
    '| Level | Local | XP/h (aprox.) | Lucro/h (aprox.) | Dica principal |',
    '|---|---|---|---|---|',
    '| 15–35+ | Coryms ([Venore](/hunting/venore-corym-cave)) | ~270k | baixo | Dica. |',
    '| 200+ | Algo | 1kk | muito alto (time) | Em time. |',
  ].join('\n');
  assert.deepEqual(guideRows(md), [{ lv: 15, name: 'Coryms (Venore)', slug: 'venore-corym-cave', x: '~270k', l: null }]);
});
