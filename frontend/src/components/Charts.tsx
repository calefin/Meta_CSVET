/**
 * Small dependency-free SVG charts (D-28): a line chart with crosshair tooltip and an
 * optional goal line + projection, and a bar chart with per-bar tooltips.
 * Single series each, so no legend: the card title names the series.
 */
import { useEffect, useRef, useState, type ReactNode } from 'react';

const PAD = { top: 12, right: 12, bottom: 26, left: 52 };

function useWidth<T extends HTMLElement>(): [React.RefObject<T>, number] {
  const ref = useRef<T>(null);
  const [width, setWidth] = useState(0);
  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    setWidth(Math.max(260, Math.floor(el.clientWidth)));
    const ro = new ResizeObserver(([entry]) => setWidth(Math.max(260, Math.floor(entry.contentRect.width))));
    ro.observe(el);
    return () => ro.disconnect();
  }, []);
  return [ref, width];
}

/** "Nice" tick values for a numeric range */
function ticks(min: number, max: number, count = 4): number[] {
  if (max <= min) return [min];
  const raw = (max - min) / count;
  const mag = 10 ** Math.floor(Math.log10(raw));
  const step = [1, 2, 2.5, 5, 10].map((m) => m * mag).find((s) => s >= raw) ?? raw;
  const out: number[] = [];
  for (let v = Math.ceil(min / step) * step; v <= max + 1e-9; v += step) out.push(+v.toFixed(10));
  return out;
}

function dateTicks(t0: number, t1: number, width: number): number[] {
  const n = Math.max(2, Math.min(6, Math.floor(width / 110)));
  if (t1 <= t0) return [t0];
  return Array.from({ length: n }, (_, i) => t0 + ((t1 - t0) * i) / (n - 1));
}

interface Tip {
  x: number;
  y: number;
  content: ReactNode;
}

function Tooltip({ tip, width }: { tip: Tip | null; width: number }) {
  if (!tip) return null;
  const left = Math.min(Math.max(tip.x, 70), width - 70);
  return (
    <div className="chart-tip" style={{ left, top: Math.max(0, tip.y - 8) }} role="status">
      {tip.content}
    </div>
  );
}

// ---------------------------------------------------------------------------

export interface LinePoint {
  t: number;
  y: number;
}

export function LineChart({
  points,
  formatY,
  formatDate,
  tooltip,
  goal,
  projection,
  ariaLabel,
}: {
  points: LinePoint[];
  formatY: (v: number) => string;
  formatDate: (t: number) => string;
  tooltip: (p: LinePoint) => ReactNode;
  goal?: { y: number; label: string };
  projection?: { t: number; y: number };
  ariaLabel: string;
}) {
  const [ref, width] = useWidth<HTMLDivElement>();
  const height = 220;
  const [hover, setHover] = useState<number | null>(null);
  if (!points.length) return null;

  const t0 = points[0].t;
  const t1 = Math.max(projection?.t ?? 0, points[points.length - 1].t, t0 + 86_400_000);
  const ys = points.map((p) => p.y).concat(goal ? [goal.y] : [], projection ? [projection.y] : []);
  let yMin = Math.min(...ys);
  let yMax = Math.max(...ys);
  if (yMax - yMin < 1) {
    yMin -= 0.5;
    yMax += 0.5;
  }
  const padY = (yMax - yMin) * 0.08;
  yMin -= padY;
  yMax += padY;

  const iw = width - PAD.left - PAD.right;
  const ih = height - PAD.top - PAD.bottom;
  const x = (t: number) => PAD.left + ((t - t0) / (t1 - t0)) * iw;
  const y = (v: number) => PAD.top + (1 - (v - yMin) / (yMax - yMin)) * ih;

  const path = points.map((p, i) => `${i ? 'L' : 'M'}${x(p.t).toFixed(1)},${y(p.y).toFixed(1)}`).join(' ');
  const last = points[points.length - 1];

  const onMove = (e: React.PointerEvent<SVGRectElement>) => {
    const rect = e.currentTarget.getBoundingClientRect();
    const px = e.clientX - rect.left + PAD.left;
    let best = 0;
    points.forEach((p, i) => {
      if (Math.abs(x(p.t) - px) < Math.abs(x(points[best].t) - px)) best = i;
    });
    setHover(best);
  };

  const hp = hover != null ? points[hover] : null;

  return (
    <div className="chart" ref={ref}>
      {width > 0 && (
      <svg width={width} height={height} role="img" aria-label={ariaLabel}>
        {ticks(yMin, yMax).map((v) => (
          <g key={v}>
            <line className="chart-grid" x1={PAD.left} x2={width - PAD.right} y1={y(v)} y2={y(v)} />
            <text className="chart-axis" x={PAD.left - 6} y={y(v)} textAnchor="end" dominantBaseline="middle">
              {formatY(v)}
            </text>
          </g>
        ))}
        {dateTicks(t0, t1, width).map((t, i, arr) => (
          <text
            key={t}
            className="chart-axis"
            x={x(t)}
            y={height - 6}
            textAnchor={i === 0 ? 'start' : i === arr.length - 1 ? 'end' : 'middle'}
          >
            {formatDate(t)}
          </text>
        ))}
        {goal && (
          <g>
            <line className="chart-goal" x1={PAD.left} x2={width - PAD.right} y1={y(goal.y)} y2={y(goal.y)} />
            <text className="chart-goal-label" x={width - PAD.right - 4} y={y(goal.y) - 6} textAnchor="end">
              {goal.label}
            </text>
          </g>
        )}
        {projection && (
          <line className="chart-projection" x1={x(last.t)} y1={y(last.y)} x2={x(projection.t)} y2={y(projection.y)} />
        )}
        <path className="chart-line" d={path} />
        {points.length <= 40 &&
          points.map((p) => <circle key={p.t} className="chart-dot" cx={x(p.t)} cy={y(p.y)} r={4} />)}
        {hp && (
          <g>
            <line className="chart-crosshair" x1={x(hp.t)} x2={x(hp.t)} y1={PAD.top} y2={PAD.top + ih} />
            <circle className="chart-dot active" cx={x(hp.t)} cy={y(hp.y)} r={5} />
          </g>
        )}
        <rect
          className="chart-hit"
          x={PAD.left}
          y={PAD.top}
          width={iw}
          height={ih}
          onPointerMove={onMove}
          onPointerDown={onMove}
          onPointerLeave={() => setHover(null)}
        />
      </svg>
      )}
      <Tooltip tip={hp ? { x: x(hp.t), y: y(hp.y), content: tooltip(hp) } : null} width={width} />
    </div>
  );
}

// ---------------------------------------------------------------------------

export interface Bar {
  key: string;
  value: number;
  label: string;
}

export function BarChart({
  bars,
  formatY,
  tooltip,
  target,
  ariaLabel,
}: {
  bars: Bar[];
  formatY: (v: number) => string;
  tooltip: (b: Bar) => ReactNode;
  target?: { y: number; label: string };
  ariaLabel: string;
}) {
  const [ref, width] = useWidth<HTMLDivElement>();
  const height = 200;
  const [hover, setHover] = useState<number | null>(null);
  if (!bars.length) return null;

  const max = Math.max(...bars.map((b) => b.value), target?.y ?? 0, 1) * 1.1;
  // Negative values (a hunt with loss) grow downwards from the zero line
  const min = Math.min(0, ...bars.map((b) => b.value)) * 1.1;
  const iw = width - PAD.left - PAD.right;
  const ih = height - PAD.top - PAD.bottom;
  const slot = iw / bars.length;
  const bw = Math.max(3, Math.min(28, slot - 2)); // 2px gap between bars
  const y = (v: number) => PAD.top + (1 - (v - min) / (max - min)) * ih;
  const labelEvery = Math.ceil(bars.length / Math.max(2, Math.floor(iw / 70)));

  return (
    <div className="chart" ref={ref}>
      {width > 0 && (
      <svg width={width} height={height} role="img" aria-label={ariaLabel}>
        {ticks(min, max).map((v) => (
          <g key={v}>
            <line className="chart-grid" x1={PAD.left} x2={width - PAD.right} y1={y(v)} y2={y(v)} />
            <text className="chart-axis" x={PAD.left - 6} y={y(v)} textAnchor="end" dominantBaseline="middle">
              {formatY(v)}
            </text>
          </g>
        ))}
        {bars.map((b, i) => {
          const cx = PAD.left + slot * i + slot / 2;
          const h = Math.abs(y(0) - y(b.value));
          const r = Math.min(4, bw / 2, h);
          const top = y(b.value);
          const left = cx - bw / 2;
          const neg = b.value < 0;
          // Rounded data end, square at the zero line
          const d = neg
            ? `M${left},${y(0)} V${top - r} Q${left},${top} ${left + r},${top} H${left + bw - r} Q${left + bw},${top} ${left + bw},${top - r} V${y(0)} Z`
            : `M${left},${y(0)} V${top + r} Q${left},${top} ${left + r},${top} H${left + bw - r} Q${left + bw},${top} ${left + bw},${top + r} V${y(0)} Z`;
          return (
            <g key={b.key}>
              {h > 0 && <path className={`chart-bar${neg ? ' neg' : ''}${hover === i ? ' active' : ''}`} d={d} />}
              {i % labelEvery === 0 && (
                <text className="chart-axis" x={cx} y={height - 6} textAnchor="middle">
                  {b.label}
                </text>
              )}
              <rect
                className="chart-hit"
                x={PAD.left + slot * i}
                y={PAD.top}
                width={slot}
                height={ih}
                onPointerEnter={() => setHover(i)}
                onPointerDown={() => setHover(i)}
                onPointerLeave={() => setHover(null)}
              />
            </g>
          );
        })}
        {target && (
          <g pointerEvents="none">
            <line className="chart-goal" x1={PAD.left} x2={width - PAD.right} y1={y(target.y)} y2={y(target.y)} />
            <text className="chart-goal-label" x={width - PAD.right - 4} y={y(target.y) - 6} textAnchor="end">
              {target.label}
            </text>
          </g>
        )}
      </svg>
      )}
      <Tooltip
        tip={
          hover != null
            ? { x: PAD.left + slot * hover + slot / 2, y: y(bars[hover].value), content: tooltip(bars[hover]) }
            : null
        }
        width={width}
      />
    </div>
  );
}

// ---------------------------------------------------------------------------

export interface ScatterPoint {
  key: string;
  x: number;
  y: number;
}

/** Two numeric variables (e.g. level × XP/h) with an optional least-squares trend line */
export function ScatterChart({
  points,
  formatX,
  formatY,
  tooltip,
  trend = true,
  ariaLabel,
}: {
  points: ScatterPoint[];
  formatX: (v: number) => string;
  formatY: (v: number) => string;
  tooltip: (p: ScatterPoint) => ReactNode;
  trend?: boolean;
  ariaLabel: string;
}) {
  const [ref, width] = useWidth<HTMLDivElement>();
  const height = 220;
  const [hover, setHover] = useState<number | null>(null);
  if (!points.length) return null;
  const xs = points.map((p) => p.x);
  const ys = points.map((p) => p.y);
  let [x0, x1] = [Math.min(...xs), Math.max(...xs)];
  let [y0, y1] = [Math.min(0, ...ys), Math.max(...ys)];
  if (x1 - x0 < 1) [x0, x1] = [x0 - 1, x1 + 1];
  if (y1 - y0 < 1) y1 = y0 + 1;
  const px = (x1 - x0) * 0.05;
  x0 -= px;
  x1 += px;
  y1 += (y1 - y0) * 0.08;
  const iw = width - PAD.left - PAD.right;
  const ih = height - PAD.top - PAD.bottom;
  const X = (v: number) => PAD.left + ((v - x0) / (x1 - x0)) * iw;
  const Y = (v: number) => PAD.top + (1 - (v - y0) / (y1 - y0)) * ih;

  let line: { a: number; b: number } | null = null;
  if (trend && points.length >= 3) {
    const n = points.length;
    const mx = xs.reduce((s, v) => s + v, 0) / n;
    const my = ys.reduce((s, v) => s + v, 0) / n;
    const sxx = xs.reduce((s, v) => s + (v - mx) ** 2, 0);
    if (sxx > 0) {
      const b = xs.reduce((s, v, i) => s + (v - mx) * (ys[i] - my), 0) / sxx;
      line = { a: my - b * mx, b };
    }
  }
  const hp = hover != null ? points[hover] : null;

  return (
    <div className="chart" ref={ref}>
      {width > 0 && (
        <svg width={width} height={height} role="img" aria-label={ariaLabel}>
          {ticks(y0, y1).map((v) => (
            <g key={v}>
              <line className="chart-grid" x1={PAD.left} x2={width - PAD.right} y1={Y(v)} y2={Y(v)} />
              <text className="chart-axis" x={PAD.left - 6} y={Y(v)} textAnchor="end" dominantBaseline="middle">
                {formatY(v)}
              </text>
            </g>
          ))}
          {ticks(x0, x1, Math.max(2, Math.floor(iw / 80))).map((v) => (
            <text key={v} className="chart-axis" x={X(v)} y={height - 6} textAnchor="middle">
              {formatX(v)}
            </text>
          ))}
          {line && (
            <line
              className="chart-projection"
              x1={X(x0)}
              y1={Y(line.a + line.b * x0)}
              x2={X(x1)}
              y2={Y(line.a + line.b * x1)}
            />
          )}
          {points.map((p, i) => (
            <circle
              key={p.key}
              className={hover === i ? 'chart-dot active' : 'chart-dot'}
              cx={X(p.x)}
              cy={Y(p.y)}
              r={hover === i ? 6 : 4.5}
              onPointerEnter={() => setHover(i)}
              onPointerDown={() => setHover(i)}
              onPointerLeave={() => setHover(null)}
            />
          ))}
        </svg>
      )}
      <Tooltip tip={hp ? { x: X(hp.x), y: Y(hp.y), content: tooltip(hp) } : null} width={width} />
    </div>
  );
}

/** Horizontal share bars (damage types, sources, loot per creature) — plain HTML, wraps on mobile */
export function ShareBars({ rows, format, max = 8 }: { rows: { name: string; value: number; pct?: number }[]; format: (v: number) => string; max?: number }) {
  if (!rows.length) return null;
  const top = Math.max(...rows.map((r) => Math.abs(r.value)), 1);
  return (
    <ul className="share-bars">
      {rows.slice(0, max).map((r) => (
        <li key={r.name}>
          <span className="share-name">{r.name}</span>
          <span className="share-track" aria-hidden="true">
            <span className="share-fill" style={{ width: `${Math.max(2, (Math.abs(r.value) / top) * 100)}%` }} />
          </span>
          <span className="share-value">
            {format(r.value)}
            {r.pct != null && <small className="muted"> · {r.pct}%</small>}
          </span>
        </li>
      ))}
    </ul>
  );
}

/** Compact number for axes: 1.2k, 3.4M, 5.6B */
export function compact(v: number, locale: string): string {
  return new Intl.NumberFormat(locale, { notation: 'compact', maximumFractionDigits: 1 }).format(v);
}

// ---------------------------------------------------------------------------
// Several series over the same x (minutes of a hunt): smooth lines with a soft area under them,
// a legend and one crosshair tooltip with every series. Used for the hunt minute timeline.

export interface SeriesDef {
  key: string;
  name: string;
  values: number[];
  /** CSS class with the series colour (stroke + fill via currentColor) */
  className: string;
}

/** Smooth path through the points (Catmull-Rom → cubic Bézier, clamped to the chart area) */
function smoothPath(pts: [number, number][], yTop: number, yBottom: number): string {
  if (!pts.length) return '';
  if (pts.length < 3) return pts.map(([px, py], i) => `${i ? 'L' : 'M'}${px.toFixed(1)},${py.toFixed(1)}`).join(' ');
  const clamp = (v: number) => Math.min(yBottom, Math.max(yTop, v));
  let d = `M${pts[0][0].toFixed(1)},${pts[0][1].toFixed(1)}`;
  for (let i = 0; i < pts.length - 1; i++) {
    const p0 = pts[i - 1] ?? pts[i];
    const p1 = pts[i];
    const p2 = pts[i + 1];
    const p3 = pts[i + 2] ?? p2;
    const c1x = p1[0] + (p2[0] - p0[0]) / 6;
    const c1y = clamp(p1[1] + (p2[1] - p0[1]) / 6);
    const c2x = p2[0] - (p3[0] - p1[0]) / 6;
    const c2y = clamp(p2[1] - (p3[1] - p1[1]) / 6);
    d += ` C${c1x.toFixed(1)},${c1y.toFixed(1)} ${c2x.toFixed(1)},${c2y.toFixed(1)} ${p2[0].toFixed(1)},${p2[1].toFixed(1)}`;
  }
  return d;
}

export function SeriesChart({
  labels,
  series,
  raw,
  formatY,
  tooltipValue,
  ariaLabel,
  height = 220,
}: {
  labels: string[];
  /** values drawn (e.g. smoothed) */
  series: SeriesDef[];
  /** values shown in the tooltip (e.g. raw per minute); defaults to the drawn ones */
  raw?: Record<string, number[]>;
  formatY: (v: number) => string;
  tooltipValue: (key: string, v: number) => string;
  ariaLabel: string;
  height?: number;
}) {
  const [ref, width] = useWidth<HTMLDivElement>();
  const [hover, setHover] = useState<number | null>(null);
  const n = labels.length;
  if (n < 2 || !series.length) return null;

  const yMax = Math.max(1, ...series.flatMap((s) => s.values)) * 1.08;
  const iw = width - PAD.left - PAD.right;
  const ih = height - PAD.top - PAD.bottom;
  const x = (i: number) => PAD.left + (i / (n - 1)) * iw;
  const y = (v: number) => PAD.top + (1 - v / yMax) * ih;
  const tickCount = Math.max(2, Math.min(7, Math.floor(iw / 70)));
  const xTicks = Array.from(new Set(Array.from({ length: tickCount }, (_, k) => Math.round(((n - 1) * k) / (tickCount - 1)))));

  const onMove = (e: React.PointerEvent<SVGRectElement>) => {
    const rect = e.currentTarget.getBoundingClientRect();
    const i = Math.round(((e.clientX - rect.left) / rect.width) * (n - 1));
    setHover(Math.min(n - 1, Math.max(0, i)));
  };

  return (
    <div className="chart" ref={ref}>
      <div className="chart-legend">
        {series.map((s) => (
          <span key={s.key} className={`chart-legend-item ${s.className}`}>
            <i aria-hidden="true" /> {s.name}
          </span>
        ))}
      </div>
      {width > 0 && (
        <svg width={width} height={height} role="img" aria-label={ariaLabel}>
          <defs>
            {series.map((s) => (
              <linearGradient key={s.key} id={`sc-grad-${s.key}`} x1="0" y1="0" x2="0" y2="1" className={s.className}>
                <stop offset="0%" stopColor="currentColor" stopOpacity="0.28" />
                <stop offset="100%" stopColor="currentColor" stopOpacity="0" />
              </linearGradient>
            ))}
          </defs>
          {ticks(0, yMax).map((v) => (
            <g key={v}>
              <line className="chart-grid" x1={PAD.left} x2={width - PAD.right} y1={y(v)} y2={y(v)} />
              <text className="chart-axis" x={PAD.left - 6} y={y(v)} textAnchor="end" dominantBaseline="middle">
                {formatY(v)}
              </text>
            </g>
          ))}
          {xTicks.map((i, k, arr) => (
            <text key={i} className="chart-axis" x={x(i)} y={height - 6} textAnchor={k === 0 ? 'start' : k === arr.length - 1 ? 'end' : 'middle'}>
              {labels[i]}
            </text>
          ))}
          {series.map((s) => {
            const pts = s.values.map((v, i) => [x(i), y(v)] as [number, number]);
            const line = smoothPath(pts, PAD.top, PAD.top + ih);
            return (
              <g key={s.key} className={s.className}>
                <path d={`${line} L${x(n - 1).toFixed(1)},${(PAD.top + ih).toFixed(1)} L${x(0).toFixed(1)},${(PAD.top + ih).toFixed(1)} Z`} fill={`url(#sc-grad-${s.key})`} stroke="none" />
                <path className="chart-series-line" d={line} />
              </g>
            );
          })}
          {hover != null && (
            <g>
              <line className="chart-crosshair" x1={x(hover)} x2={x(hover)} y1={PAD.top} y2={PAD.top + ih} />
              {series.map((s) => (
                <circle key={s.key} className={`chart-series-dot ${s.className}`} cx={x(hover)} cy={y(s.values[hover])} r={4.5} />
              ))}
            </g>
          )}
          <rect
            className="chart-hit"
            x={PAD.left}
            y={PAD.top}
            width={Math.max(0, iw)}
            height={ih}
            onPointerMove={onMove}
            onPointerDown={onMove}
            onPointerLeave={() => setHover(null)}
          />
        </svg>
      )}
      <Tooltip
        tip={
          hover != null
            ? {
                x: x(hover),
                y: Math.min(...series.map((s) => y(s.values[hover]))),
                content: (
                  <>
                    <strong>{labels[hover]}</strong>
                    {series.map((s) => (
                      <span key={s.key} className={`chart-tip-row ${s.className}`}>
                        <i aria-hidden="true" /> {s.name}: {tooltipValue(s.key, (raw?.[s.key] ?? s.values)[hover])}
                      </span>
                    ))}
                  </>
                ),
              }
            : null
        }
        width={width}
      />
    </div>
  );
}
