#!/usr/bin/env node
/**
 * Minimap tiles served by our own site (D-61). The tibiamaps.io embed loads a whole web app; instead we take the
 * floor images from the tibiamaps/tibia-map-data project (MIT; map data © CipSoft, explored by players), cut them
 * into 256×256 tiles and drop the empty (black) ones. A hunting place page then loads only the 4–6 tiles around it.
 *
 * Usage: node scripts/import-minimap.mjs        (needs internet, ~20 s; no npm dependencies: PNG read/write via zlib)
 * Output: frontend/public/map/<z>/<tx>-<ty>.png, markers-<z>.json (stairs, ropes, NPCs…) + frontend/public/map/meta.json
 */
import { mkdir, rm, writeFile } from 'node:fs/promises';
import path from 'node:path';
import zlib from 'node:zlib';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const OUT = path.join(ROOT, 'frontend/public/map');
const BASE = 'https://tibiamaps.github.io/tibia-map-data';
const TILE = 256;

// ---------------------------------------------------------------------------
// Minimal PNG codec: non-interlaced indexed (palette) images only, which is what tibia-map-data publishes
// ---------------------------------------------------------------------------

function readPng(buf) {
  if (buf.readUInt32BE(0) !== 0x89504e47) throw new Error('not a PNG');
  let pos = 8;
  const idat = [];
  const out = { chunks: {} };
  while (pos < buf.length) {
    const len = buf.readUInt32BE(pos);
    const type = buf.toString('ascii', pos + 4, pos + 8);
    const data = buf.subarray(pos + 8, pos + 8 + len);
    if (type === 'IHDR') {
      out.width = data.readUInt32BE(0);
      out.height = data.readUInt32BE(4);
      out.depth = data[8];
      out.colorType = data[9];
      if (data[12] !== 0) throw new Error('interlaced PNG not supported');
    } else if (type === 'IDAT') idat.push(data);
    else if (type === 'PLTE' || type === 'tRNS') out.chunks[type] = Buffer.from(data);
    pos += 12 + len;
  }
  if (out.colorType !== 3) throw new Error(`colour type ${out.colorType} not supported (expected palette)`);
  const raw = zlib.inflateSync(Buffer.concat(idat));
  const stride = Math.ceil((out.width * out.depth) / 8);
  const bpp = 1; // bytes per complete pixel, rounded up to 1 for indexed images
  const pixels = Buffer.alloc(stride * out.height);
  for (let y = 0; y < out.height; y++) {
    const f = raw[y * (stride + 1)];
    const src = raw.subarray(y * (stride + 1) + 1, (y + 1) * (stride + 1));
    const row = pixels.subarray(y * stride, (y + 1) * stride);
    const prev = y ? pixels.subarray((y - 1) * stride, y * stride) : Buffer.alloc(stride);
    for (let i = 0; i < stride; i++) {
      const a = i >= bpp ? row[i - bpp] : 0;
      const b = prev[i];
      const c = i >= bpp ? prev[i - bpp] : 0;
      let v = src[i];
      if (f === 1) v += a;
      else if (f === 2) v += b;
      else if (f === 3) v += (a + b) >> 1;
      else if (f === 4) {
        const p = a + b - c;
        const pa = Math.abs(p - a);
        const pb = Math.abs(p - b);
        const pc = Math.abs(p - c);
        v += pa <= pb && pa <= pc ? a : pb <= pc ? b : c;
      }
      row[i] = v & 0xff;
    }
  }
  return { ...out, stride, pixels };
}

const CRC_TABLE = Array.from({ length: 256 }, (_, n) => {
  let c = n;
  for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
  return c >>> 0;
});
function crc32(buf) {
  let c = 0xffffffff;
  for (const b of buf) c = CRC_TABLE[(c ^ b) & 0xff] ^ (c >>> 8);
  return (c ^ 0xffffffff) >>> 0;
}
function chunk(type, data) {
  const head = Buffer.alloc(8);
  head.writeUInt32BE(data.length, 0);
  head.write(type, 4, 'ascii');
  const crc = Buffer.alloc(4);
  crc.writeUInt32BE(crc32(Buffer.concat([head.subarray(4), data])));
  return Buffer.concat([head, data, crc]);
}
function writePng(width, height, depth, rows, chunks) {
  const ihdr = Buffer.alloc(13);
  ihdr.writeUInt32BE(width, 0);
  ihdr.writeUInt32BE(height, 4);
  ihdr[8] = depth;
  ihdr[9] = 3;
  const raw = Buffer.concat(rows.map((r) => Buffer.concat([Buffer.from([0]), r])));
  return Buffer.concat([
    Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
    chunk('IHDR', ihdr),
    chunk('PLTE', chunks.PLTE),
    ...(chunks.tRNS ? [chunk('tRNS', chunks.tRNS)] : []),
    chunk('IDAT', zlib.deflateSync(raw, { level: 9 })),
    chunk('IEND', Buffer.alloc(0)),
  ]);
}

/** Palette indexes that are pure black (unexplored / empty) */
function blackIndexes(plte) {
  const out = new Set();
  for (let i = 0; i * 3 < plte.length; i++) if (plte[i * 3] === 0 && plte[i * 3 + 1] === 0 && plte[i * 3 + 2] === 0) out.add(i);
  return out;
}

async function get(url) {
  const res = await fetch(url, { headers: { 'User-Agent': 'BagParser-map-import/1.0 (https://github.com/calefin/bagparser)' } });
  if (!res.ok) throw new Error(`${url}: HTTP ${res.status}`);
  return Buffer.from(await res.arrayBuffer());
}

async function main() {
  const bounds = JSON.parse((await get(`${BASE}/bounds.json`)).toString('utf8'));
  await rm(OUT, { recursive: true, force: true });
  const tiles = {};
  let bytes = 0;
  for (const id of bounds.floorIDs) {
    const png = readPng(await get(`${BASE}/floor-${id}-map.png`));
    const black = blackIndexes(png.chunks.PLTE);
    const perPx = png.depth / 8;
    const tileBytes = TILE * perPx;
    const z = Number(id);
    tiles[z] = [];
    await mkdir(path.join(OUT, String(z)), { recursive: true });
    for (let ty = 0; ty * TILE < png.height; ty++) {
      for (let tx = 0; tx * TILE < png.width; tx++) {
        const rows = [];
        let empty = true;
        for (let y = ty * TILE; y < (ty + 1) * TILE; y++) {
          const row = Buffer.alloc(tileBytes);
          if (y < png.height) png.pixels.copy(row, 0, y * png.stride + tx * tileBytes, Math.min(y * png.stride + (tx + 1) * tileBytes, (y + 1) * png.stride));
          if (empty)
            for (const b of row) {
              const hi = png.depth === 4 ? b >> 4 : b;
              const lo = png.depth === 4 ? b & 15 : b;
              if (!black.has(hi) || !black.has(lo)) {
                empty = false;
                break;
              }
            }
          rows.push(row);
        }
        if (empty) continue;
        const file = writePng(TILE, TILE, png.depth, rows, png.chunks);
        bytes += file.length;
        await writeFile(path.join(OUT, String(z), `${tx}-${ty}.png`), file);
        tiles[z].push(`${tx}-${ty}`);
      }
    }
    process.stdout.write(`${id}:${tiles[z].length} `);
  }
  const meta = {
    _comment: `Generated by scripts/import-minimap.mjs on ${new Date().toISOString().slice(0, 10)} from tibiamaps/tibia-map-data (MIT; map © CipSoft). Do not edit by hand.`,
    version: new Date().toISOString().slice(0, 10),
    xMin: bounds.xMin,
    yMin: bounds.yMin,
    width: bounds.width,
    height: bounds.height,
    tile: TILE,
    tiles,
  };
  // Markers (stairs, ropes, NPCs, quests…) per floor: [x, y, icon index, description]
  const markers = JSON.parse((await get(`${BASE}/markers.json`)).toString('utf8'));
  const icons = [...new Set(markers.map((mk) => mk.icon))].sort();
  const byFloor = {};
  for (const mk of markers) (byFloor[mk.z] ??= []).push([mk.x, mk.y, icons.indexOf(mk.icon), mk.description || '']);
  for (const [z, list] of Object.entries(byFloor)) await writeFile(path.join(OUT, `markers-${z}.json`), JSON.stringify(list));
  meta.icons = icons;
  meta.markers = Object.keys(byFloor).map(Number).sort((x, y) => x - y);
  await writeFile(path.join(OUT, 'meta.json'), JSON.stringify(meta) + '\n');
  console.log(`\n${Object.values(tiles).flat().length} tiles, ${(bytes / 1024 / 1024).toFixed(1)} MB → frontend/public/map/`);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
