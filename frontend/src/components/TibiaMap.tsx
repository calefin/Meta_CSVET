/**
 * Light Tibia minimap (D-61): 256 px tiles served by our own site (scripts/import-minimap.mjs, from
 * tibiamaps/tibia-map-data, MIT; map © CipSoft). Only the tiles in view are loaded (~4–6 × ≤15 KB).
 * Drag to move, buttons for zoom and floor; the marker shows the target position.
 * D-62: the community markers (stairs, ropes, holes, NPCs, quests…) per floor, loaded on demand; tap one to read it.
 */
import { useEffect, useRef, useState } from 'react';
import { useI18n } from '../i18n';

interface MapMeta {
  version: string;
  xMin: number;
  yMin: number;
  width: number;
  height: number;
  tile: number;
  tiles: Record<string, string[]>;
  icons?: string[];
  markers?: number[];
}

/** [x, y, icon index, description] */
type Mk = [number, number, number, string];
const markerCache = new Map<number, Promise<Mk[]>>();
const loadMarkers = (z: number, version: string) => {
  if (!markerCache.has(z))
    markerCache.set(
      z,
      fetch(`/map/markers-${z}.json?v=${version}`)
        .then((r) => (r.ok ? (r.json() as Promise<Mk[]>) : []))
        .catch(() => []),
    );
  return markerCache.get(z)!;
};

/** Minimap icons as text glyphs (no images to download) */
const ICON: Record<string, string> = {
  up: '⬆', down: '⬇', 'red up': '⬆', 'red down': '⬇', 'red left': '⬅', 'red right': '➡', flag: '⚑', '!': '!', '?': '?', $: '$',
  bag: '👜', checkmark: '✓', cross: '✝', crossmark: '✕', lock: '🔒', mouth: '👄', skull: '☠', spear: '⚔', star: '★', sword: '⚔',
};

let metaPromise: Promise<MapMeta> | null = null;
const loadMeta = () => {
  metaPromise ??= fetch('/map/meta.json').then((r) => {
    if (!r.ok) throw new Error('map meta');
    return r.json() as Promise<MapMeta>;
  });
  metaPromise.catch(() => (metaPromise = null));
  return metaPromise;
};

const ZOOMS = [1, 2, 4, 8];

/** Floor label the way players say it: ground floor 7, "+1" above, "−1" below */
export const floorLabel = (z: number) => (z === 7 ? '0' : z < 7 ? `+${7 - z}` : `−${z - 7}`);

export function TibiaMap({ x, y, z, height = 320, label }: { x: number; y: number; z: number; height?: number; label?: string }) {
  const { t } = useI18n();
  const [meta, setMeta] = useState<MapMeta | null>(null);
  const [failed, setFailed] = useState(false);
  const [floor, setFloor] = useState(z);
  const [zoom, setZoom] = useState(2);
  const [center, setCenter] = useState({ x, y });
  const [width, setWidth] = useState(0);
  const [showMarkers, setShowMarkers] = useState(true);
  const [markers, setMarkers] = useState<{ z: number; list: Mk[] } | null>(null);
  const [note, setNote] = useState<string | null>(null);
  const box = useRef<HTMLDivElement>(null);
  const drag = useRef<{ id: number; sx: number; sy: number; cx: number; cy: number } | null>(null);

  useEffect(() => {
    loadMeta().then(setMeta, () => setFailed(true));
  }, []);
  useEffect(() => {
    setFloor(z);
    setCenter({ x, y });
  }, [x, y, z]);
  useEffect(() => {
    if (!meta || !showMarkers || !(meta.markers ?? []).includes(floor)) return;
    let alive = true;
    void loadMarkers(floor, meta.version).then((list) => alive && setMarkers({ z: floor, list }));
    return () => {
      alive = false;
    };
  }, [meta, floor, showMarkers]);
  useEffect(() => setNote(null), [floor]);
  useEffect(() => {
    const el = box.current;
    if (!el) return;
    const ro = new ResizeObserver(([e]) => setWidth(Math.round(e.contentRect.width)));
    ro.observe(el);
    return () => ro.disconnect();
  }, []);

  if (failed) return <p className="muted small">{t('hunt_no_map')}</p>;

  const tiles: { key: string; left: number; top: number; src: string }[] = [];
  if (meta && width) {
    const px = center.x - meta.xMin;
    const py = center.y - meta.yMin;
    const have = new Set(meta.tiles[String(floor)] ?? []);
    const x0 = Math.floor((px - width / 2 / zoom) / meta.tile);
    const x1 = Math.floor((px + width / 2 / zoom) / meta.tile);
    const y0 = Math.floor((py - height / 2 / zoom) / meta.tile);
    const y1 = Math.floor((py + height / 2 / zoom) / meta.tile);
    for (let ty = y0; ty <= y1; ty++)
      for (let tx = x0; tx <= x1; tx++) {
        const key = `${tx}-${ty}`;
        if (!have.has(key)) continue;
        tiles.push({
          key: `${floor}/${key}`,
          left: Math.round((tx * meta.tile - px) * zoom + width / 2),
          top: Math.round((ty * meta.tile - py) * zoom + height / 2),
          src: `/map/${floor}/${key}.png?v=${meta.version}`,
        });
      }
  }
  const visibleMarkers: { key: string; left: number; top: number; icon: string; red: boolean; desc: string }[] = [];
  if (meta && width && showMarkers && markers?.z === floor) {
    const halfW = width / 2 / zoom + 2;
    const halfH = height / 2 / zoom + 2;
    for (const [mx, my, ic, desc] of markers.list) {
      if (Math.abs(mx - center.x) > halfW || Math.abs(my - center.y) > halfH) continue;
      const name = meta.icons?.[ic] ?? '';
      visibleMarkers.push({
        key: `${mx}-${my}-${ic}`,
        left: Math.round((mx + 0.5 - center.x) * zoom + width / 2),
        top: Math.round((my + 0.5 - center.y) * zoom + height / 2),
        icon: ICON[name] ?? '•',
        red: name.startsWith('red') || name === 'skull' || name === 'crossmark',
        desc: desc || name,
      });
      if (visibleMarkers.length >= 250) break;
    }
  }
  const marker = floor === z ? { left: Math.round((x - center.x) * zoom + width / 2), top: Math.round((y - center.y) * zoom + height / 2) } : null;

  const onDown = (e: React.PointerEvent) => {
    drag.current = { id: e.pointerId, sx: e.clientX, sy: e.clientY, cx: center.x, cy: center.y };
    (e.currentTarget as HTMLElement).setPointerCapture(e.pointerId);
  };
  const onMove = (e: React.PointerEvent) => {
    const d = drag.current;
    if (!d || d.id !== e.pointerId) return;
    setCenter({ x: d.cx - (e.clientX - d.sx) / zoom, y: d.cy - (e.clientY - d.sy) / zoom });
  };
  const onUp = () => (drag.current = null);
  const changed = floor !== z || Math.abs(center.x - x) > 1 || Math.abs(center.y - y) > 1;

  return (
    <div className="tmap">
      <div
        ref={box}
        className="tmap-view"
        style={{ height }}
        onPointerDown={onDown}
        onPointerMove={onMove}
        onPointerUp={onUp}
        onPointerCancel={onUp}
        role="img"
        aria-label={`${label ?? t('hunt_map')}: ${x}, ${y}, ${z}`}
      >
        {tiles.map((tl) => (
          <img key={tl.key} src={tl.src} alt="" draggable={false} loading="lazy" style={{ left: tl.left, top: tl.top, width: (meta?.tile ?? 256) * zoom, height: (meta?.tile ?? 256) * zoom }} />
        ))}
        {visibleMarkers.map((mk) => (
          <button
            key={mk.key}
            type="button"
            className={`tmap-mk${mk.red ? ' red' : ''}`}
            style={{ left: mk.left, top: mk.top }}
            title={mk.desc}
            aria-label={mk.desc}
            onPointerDown={(e) => e.stopPropagation()}
            onClick={() => setNote(mk.desc)}
          >
            {mk.icon}
          </button>
        ))}
        {note && <span className="tmap-note">{note}</span>}
        {marker && <span className="tmap-marker" style={{ left: marker.left, top: marker.top, width: Math.max(zoom, 6), height: Math.max(zoom, 6) }} aria-hidden="true" />}
        {!meta && <span className="tmap-loading muted small">{t('loading')}</span>}
      </div>
      <div className="tmap-bar">
        <div className="seg" role="group" aria-label={t('map_floor')}>
          <button type="button" onClick={() => setFloor((f) => Math.max(0, f - 1))} disabled={floor <= 0} aria-label={t('map_floor_up')}>
            ▲
          </button>
          <button type="button" disabled aria-live="polite">
            {t('map_floor')} {floorLabel(floor)}
          </button>
          <button type="button" onClick={() => setFloor((f) => Math.min(15, f + 1))} disabled={floor >= 15} aria-label={t('map_floor_down')}>
            ▼
          </button>
        </div>
        <div className="seg" role="group" aria-label="Zoom">
          <button type="button" onClick={() => setZoom((v) => ZOOMS[Math.max(0, ZOOMS.indexOf(v) - 1)])} disabled={zoom === ZOOMS[0]} aria-label={t('map_zoom_out')}>
            −
          </button>
          <button type="button" onClick={() => setZoom((v) => ZOOMS[Math.min(ZOOMS.length - 1, ZOOMS.indexOf(v) + 1)])} disabled={zoom === ZOOMS[ZOOMS.length - 1]} aria-label={t('map_zoom_in')}>
            +
          </button>
        </div>
        <button type="button" className={`button secondary small-btn${showMarkers ? ' on' : ''}`} aria-pressed={showMarkers} onClick={() => setShowMarkers((v) => !v)}>
          ⚑ {t('map_markers')}
        </button>
        {changed && (
          <button
            type="button"
            className="button secondary small-btn"
            onClick={() => {
              setFloor(z);
              setCenter({ x, y });
            }}
          >
            📍 {t('map_recenter')}
          </button>
        )}
        <span className="muted small tmap-coords">
          {Math.round(center.x)}, {Math.round(center.y)}, {floor}
        </span>
      </div>
    </div>
  );
}
