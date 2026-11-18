/** Run with: node --test scripts/*.test.mjs */
import assert from 'node:assert/strict';
import test from 'node:test';
import { parseTitle } from './import-hunt-videos.mjs';

test('reads level, XP and profit per hour', () => {
  assert.deepEqual(parseTitle('EK 200+ Grim Reapers (Yalahar) BEST Solo Hunt | 4,7KK EXP/H &amp; 50K Profit – Tibia Hunt Español'), { v: 'knight', lv: 200, x: [4700000, 4700000], l: [50000, 50000] });
  assert.deepEqual(parseTitle('TIBIA EK 130+ | 500K PROFIT/H 1,5KK EXP/H - DARK CAVE -2 Elite Knight'), { v: 'knight', lv: 130, x: [1500000, 1500000], l: [500000, 500000] });
});

test('ranges, raw XP and other word orders', () => {
  assert.deepEqual(parseTitle('[EK 50+] Knight Hunt Solo Winterlight Solstice | 500k/h EXP | 200-300k\\h PROFIT | Tibia'), { v: 'knight', lv: 50, x: [500000, 500000], l: [200000, 300000] });
  assert.deepEqual(parseTitle('Tibia Draken Walls North EK 200+ | 3–3.5KK/H EXP + 500K/H PROFIT | Post-Rebalance Guide'), { v: 'knight', lv: 200, x: [3000000, 3500000], l: [500000, 500000] });
  assert.deepEqual(parseTitle('5.1KK RAW XP/H on 344 ED?! Nagas Are INSANE | 300K Profit | #tibia2026'), { v: 'druid', lv: 344, x: [5100000, 5100000], l: [300000, 300000] });
  assert.deepEqual(parseTitle('Hunt Renegade Quaras Oramond EK 200+, Exp 2kk/hr (150%) Profit, 350k/hr'), { v: 'knight', lv: 200, x: [2000000, 2000000], l: [350000, 350000] });
});

test('raw alone means XP per hour', () => {
  assert.deepEqual(parseTitle('TIBIA - EK 318 | BANUTA -4 | 3.2KK RAW + 830K PROFIT'), { v: 'knight', lv: 318, x: [3200000, 3200000], l: [830000, 830000] });
});

test('a session total is not a value per hour', () => {
  assert.deepEqual(parseTitle('EK 500 Hunt Solo - Werecrocodiles 10kk de EXP com 1.2kk de Profit'), { v: 'knight', lv: 500, x: null, l: null });
});

test('team hunts, private servers, several vocations and no level are left out', () => {
  assert.equal(parseTitle('[MELHORES HUNTS DE PT X4 NO TIBIA] EK 500+ 3kk exp/h'), null);
  assert.equal(parseTitle('AS MELHORES HUNTS DE XP SOLO PRA EK 300 NO RUBINOT 10KK/RAW'), null);
  assert.equal(parseTitle('Tibia Exotic Cave - Issavi Hunt RP (RAW XP 1.1KK +/h) Where to level up 100–200 RP/ED/MS/EK'), null);
  assert.equal(parseTitle('Best hunt for knights 3kk exp/h'), null);
});
