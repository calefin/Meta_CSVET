import React, { type ReactElement } from "react";
import { toast } from "sonner";
import type { AnswerSheetConfig, HeaderField } from "@shared/_core/answerSheetTypes";
import { generateAnswerSheetSVG, AnswerSheetOverflowError } from "@/lib/answerSheetSvg";
import { downloadAnswerSheetSVG, downloadAnswerSheetPNG } from "@/lib/downloadAnswerSheet";

// ── Page & geometry (mirrors answerSheetSvg.ts exactly) ───────────────────
const PAGE_W    = 595;
const PAGE_H    = 842;
const MARGIN    = 20;
const ARUCO     = 24;
const ARUCO_CELL = ARUCO / 6;
const GAP       = 8;
const INNER_X   = MARGIN + ARUCO + GAP;
const INNER_W   = PAGE_W - INNER_X * 2;

const FIELD_LABEL_H = 10;
const FIELD_BOX_H   = 24;
const FIELD_ROW_H   = FIELD_LABEL_H + FIELD_BOX_H + 8;

const Q_ROW_H   = 20;
const BUBBLE_R  = 7.0;
const BUBBLE_GAP = BUBBLE_R * 2 + 4;
const STROKE    = 1.1;
const BOT_BAR_H = 34;
const BARCODE_H = 16;
const BARCODE_MW = 1.2;

// Student-ID grid: always 10 rows (digits 0-9) regardless of digit count.
const ID_GRID_CELL_H = 11;
const ID_GRID_H = 14 + 10 * ID_GRID_CELL_H + 8;

const URL_TEXT = "www.prof-aid.com";

// ── ArUco inner 4×4 bit patterns (1=black, 0=white) per corner ────────────
const ARUCO_INNER: number[][][] = [
  [[1,0,1,0],[0,0,0,1],[1,0,0,0],[0,1,0,1]], // 0 TL
  [[0,1,0,1],[1,0,0,0],[0,0,0,1],[1,0,1,0]], // 1 TR
  [[1,1,0,0],[0,0,1,0],[0,1,0,0],[1,0,1,1]], // 2 BL
  [[0,0,1,1],[0,1,0,1],[1,0,1,0],[0,1,0,0]], // 3 BR
];

// ── ArUco marker component ────────────────────────────────────────────────
function ArucoMarker({ id, x, y, size = ARUCO }: { id: number; x: number; y: number; size?: number }) {
  const cell = size / 6;
  const inner = ARUCO_INNER[id];
  const cells: ReactElement[] = [];
  for (let r = 0; r < 4; r++) {
    for (let c = 0; c < 4; c++) {
      cells.push(
        <rect key={`${r}-${c}`}
          x={(x + (c + 1) * cell)}
          y={(y + (r + 1) * cell)}
          width={cell} height={cell}
          fill={inner[r][c] ? "black" : "white"}
        />
      );
    }
  }
  return (
    <g>
      <rect x={x} y={y} width={size} height={size} fill="black" />
      {cells}
    </g>
  );
}

// ── Bubble ────────────────────────────────────────────────────────────────
function Bubble({ cx, cy, label, r = BUBBLE_R }: { cx: number; cy: number; label: string; r?: number }) {
  return (
    <g>
      <circle cx={cx} cy={cy} r={r} fill="none" stroke="black" strokeWidth={STROKE} />
      <text x={cx} y={cy + r * 0.4} textAnchor="middle" fontSize={r * 1.2}
        fontFamily="Arial,sans-serif" fontWeight="bold" fill="black">{label}</text>
    </g>
  );
}

// ── Field box ─────────────────────────────────────────────────────────────
function FieldBox({ x, y, w, label, prefill }: { x: number; y: number; w: number; label: string; prefill?: string }) {
  return (
    <g>
      <text x={x} y={y + FIELD_LABEL_H - 2} fontSize="7" fontFamily="Arial,sans-serif"
        fontWeight="bold" fill="black">{label}</text>
      <rect x={x} y={y + FIELD_LABEL_H} width={w} height={FIELD_BOX_H}
        rx="2" fill="none" stroke="black" strokeWidth={STROKE * 0.6} />
      {prefill && (
        <text x={x + 3} y={y + FIELD_LABEL_H + FIELD_BOX_H - 5}
          fontSize="8" fontFamily="Arial,sans-serif" fill="black">{prefill}</text>
      )}
    </g>
  );
}

// ── Simplified Code 128 visual (preview only — actual barcode in SVG export) ──
function BarcodePreview({ x, y, h, text }: { x: number; y: number; h: number; text: string }) {
  // Render alternating bars of varying width to look like a barcode
  const bars: ReactElement[] = [];
  let cx = x;
  const seed = text.split("").reduce((a, c) => a + c.charCodeAt(0), 0);
  for (let i = 0; i < 40; i++) {
    const w = ((seed * (i + 1) * 7 + i * 13) % 3) + 1;
    if (i % 2 === 0) bars.push(<rect key={i} x={cx} y={y} width={w * BARCODE_MW} height={h} fill="black" />);
    cx += w * BARCODE_MW;
    if (cx > x + 100) break;
  }
  return <g>{bars}</g>;
}

// ── Field layout (same logic as SVG generator) ────────────────────────────
interface FieldLayout { field: HeaderField; x: number; y: number; w: number; }

function computeFieldLayouts(fields: HeaderField[], ox: number, oy: number, ow: number): FieldLayout[] {
  const layouts: FieldLayout[] = [];
  let curY = oy; let i = 0;
  while (i < fields.length) {
    const f = fields[i];
    if (f.width === "large") {
      layouts.push({ field: f, x: ox, y: curY, w: ow });
      curY += FIELD_ROW_H; i++;
    } else {
      const half = Math.floor(ow * 0.5) - 2;
      layouts.push({ field: f, x: ox, y: curY, w: half });
      const next = fields[i + 1];
      if (next && next.width !== "large") {
        layouts.push({ field: next, x: ox + half + 4, y: curY, w: ow - half - 4 });
        i += 2;
      } else { i++; }
      curY += FIELD_ROW_H;
    }
  }
  return layouts;
}

// ── Props ─────────────────────────────────────────────────────────────────
interface Props {
  name: string;
  config: AnswerSheetConfig;
  scale?: number;
  formId?: string;
  studentCode?: string;
  studentName?: string;
  qrPayload?: string;
}

export default function AnswerSheetPreview({
  name, config, scale = 0.42, formId, studentCode, studentName,
  qrPayload,
}: Props) {
  const { header, questions } = config;

  const items: { num: number; choices: string[]; type: string }[] = [];
  for (const block of questions) {
    // Mirrors answerSheetSvg.ts: verbose fallback + alternation odd/even parity.
    const fallbackChoices =
      block.type === "verbose" && (!block.choices || block.choices.length === 0)
        ? (block.verboseAnswers ?? []).map(a => a.shortLabel).filter(Boolean)
        : block.choices;
    const oddChoices  = block.oddChoices  ?? fallbackChoices;
    const evenChoices = block.evenChoices ?? fallbackChoices;

    for (let i = 0; i < block.count; i++) {
      const num = block.startNumber + i;
      const choices = block.type === "alternation"
        ? (num % 2 === 1 ? oddChoices : evenChoices)
        : fallbackChoices;
      items.push({ num, choices, type: block.type });
    }
  }

  const hasIdGrid = header.includeStudentId && header.studentIdDigits > 0;

  // Mirror large-QR geometry from answerSheetSvg.ts
  const showLargeQr  = !!formId;
  const rawQrSize    = showLargeQr ? (hasIdGrid ? 65 : 100) : 0;
  const largeQrGapX  = showLargeQr ? 8 : 0;
  const idColW_base  = INNER_W - Math.floor(INNER_W * 0.62) - 6;
  const idColW       = hasIdGrid ? idColW_base : 0;
  const bubbleStart  = INNER_X + rawQrSize + largeQrGapX;
  const bubbleAvailW = INNER_W - rawQrSize - largeQrGapX;
  const qColW        = hasIdGrid ? (bubbleAvailW - idColW - 6) : bubbleAvailW;
  const idColX       = bubbleStart + qColW + 6;

  const enabledFields = header.fields.filter(f => f.enabled);
  const topBarH    = ARUCO + 4;
  const INNER_Y0   = MARGIN + topBarH + GAP;

  const fieldLayouts = computeFieldLayouts(enabledFields, INNER_X, INNER_Y0, INNER_W);
  const headerH = fieldLayouts.length > 0
    ? fieldLayouts[fieldLayouts.length - 1].y - INNER_Y0 + FIELD_ROW_H
    : 0;

  const qStartY      = INNER_Y0 + headerH + 22;
  const subColW      = Math.floor(qColW / 2) - 2;
  const subColX1     = bubbleStart;
  const subColX2     = bubbleStart + subColW + 4;
  const maxPerSubCol = Math.ceil(items.length / 2);
  const bubbleBodyH  = maxPerSubCol * Q_ROW_H;
  const largeQrSize  = Math.min(rawQrSize, bubbleBodyH + 4);
  const qBodyH       = Math.max(bubbleBodyH, largeQrSize, hasIdGrid ? ID_GRID_H : 0);

  const botY = qStartY + qBodyH + 10;
  const boxH = botY + BOT_BAR_H + MARGIN;

  const offsetY = MARGIN; // top-aligned, no vertical centering

  const ay = (rel: number) => rel + offsetY;

  const topArY  = ay(MARGIN);
  const botArY  = ay(botY);
  const botArYC = botArY + (BOT_BAR_H - ARUCO) / 2;
  const bcY     = botArY + (BOT_BAR_H - BARCODE_H) / 2;

  const examLabel = formId ?? "PROF-AID";

  // Large QR data URI (answer-check URL for students)
  const [qrDataUri, setQrDataUri] = React.useState<string | null>(null);
  const qrDataStr = formId ? `https://prof-aid.com/r/${formId}` : undefined;
  React.useEffect(() => {
    let mounted = true;
    if (!qrDataStr || largeQrSize <= 0) return;
    const url = `/api/qr?size=${largeQrSize}&data=${encodeURIComponent(qrDataStr)}`;
    fetch(url).then(r => r.text()).then(svg => {
      if (!mounted) return;
      setQrDataUri('data:image/svg+xml;utf8,' + encodeURIComponent(svg));
    }).catch(() => {});
    return () => { mounted = false; };
  }, [qrDataStr, largeQrSize]);

  void ARUCO_CELL; // suppress unused

  return (
    <div
      className="border border-gray-200 bg-white shadow overflow-hidden"
      style={{ width: PAGE_W * scale, height: PAGE_H * scale, flexShrink: 0 }}
    >
        <div style={{ padding: 8, display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
        <button className="btn" onClick={() => {
          fetch('/api/telemetry', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ event: 'download_svg', name, formId, studentCode, qrPayload }) }).catch(() => {});
          try {
            const svg = generateAnswerSheetSVG(name, config, { standalone: true, formId: formId, studentCode, studentName, qrPayload });
            downloadAnswerSheetSVG(svg, (formId || 'gabarito'));
          } catch (e) {
            toast.error(e instanceof AnswerSheetOverflowError ? e.message : "Erro ao gerar o gabarito.");
          }
        }}>Download SVG</button>
        <button className="btn" onClick={() => {
          fetch('/api/telemetry', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ event: 'download_png', name, formId, studentCode, qrPayload }) }).catch(() => {});
          try {
            const svg = generateAnswerSheetSVG(name, config, { standalone: true, formId: formId, studentCode, studentName, qrPayload });
            downloadAnswerSheetPNG(svg, (formId || 'gabarito'), 300);
          } catch (e) {
            toast.error(e instanceof AnswerSheetOverflowError ? e.message : "Erro ao gerar o gabarito.");
          }
        }}>Download PNG (300 DPI)</button>
      </div>
      <svg
        width={PAGE_W * scale} height={PAGE_H * scale}
        viewBox={`0 0 ${PAGE_W} ${PAGE_H}`}
        xmlns="http://www.w3.org/2000/svg"
      >
        <rect width={PAGE_W} height={PAGE_H} fill="white" />

        {/* ── TOP ROW ───────────────────────────────────────────────── */}
        <ArucoMarker id={0} x={MARGIN}                  y={topArY} />
        <ArucoMarker id={1} x={PAGE_W - MARGIN - ARUCO} y={topArY} />

        {/* URL */}
        <text
          x={(MARGIN + ARUCO + 6 + (PAGE_W - 2 * (MARGIN + ARUCO + 6)) / 2)}
          y={ay(MARGIN + ARUCO / 2 + 3)}
          textAnchor="middle" fontSize="9" fontFamily="Arial,sans-serif"
          fontWeight="bold" fill="#000"
        >{URL_TEXT}</text>

        {/* Form name */}
        {name && (
          <text
            x={(MARGIN + ARUCO + 6 + (PAGE_W - 2 * (MARGIN + ARUCO + 6)) / 2)}
            y={ay(MARGIN + ARUCO - 2)}
            textAnchor="middle" fontSize="6" fontFamily="Arial,sans-serif" fill="#888"
          >{name}</text>
        )}

        {/* Outer box */}
        <rect
          x={MARGIN} y={ay(MARGIN)}
          width={PAGE_W - 2 * MARGIN} height={boxH - 2 * MARGIN}
          rx="2" fill="none" stroke="#ccc" strokeWidth="0.6"
        />

        {/* ── HEADER FIELDS ─────────────────────────────────────────── */}
        {fieldLayouts.map(layout => (
          <FieldBox
            key={layout.field.id}
            x={layout.x} y={ay(layout.y)} w={layout.w}
            label={layout.field.displayLabel || layout.field.defaultLabel}
            prefill={layout.field.id === "name" ? studentName : undefined}
          />
        ))}

        {/* Divider */}
        {headerH > 0 && (
          <line
            x1={INNER_X} y1={ay(INNER_Y0 + headerH + 4)}
            x2={PAGE_W - INNER_X} y2={ay(INNER_Y0 + headerH + 4)}
            stroke="#333" strokeWidth="0.8"
          />
        )}

        {/* ── LARGE QR (left column, student-facing) ────────────────── */}
        {showLargeQr && largeQrSize > 0 && (
          <g>
            {qrDataUri ? (
              <image href={qrDataUri}
                x={INNER_X} y={ay(qStartY)}
                width={largeQrSize} height={largeQrSize}
                preserveAspectRatio="xMidYMid meet" />
            ) : (
              <rect x={INNER_X} y={ay(qStartY)}
                width={largeQrSize} height={largeQrSize}
                fill="#f0f0f0" stroke="#ccc" strokeWidth="0.8" />
            )}
            <text
              x={INNER_X + largeQrSize / 2} y={ay(qStartY) + largeQrSize + 9}
              textAnchor="middle" fontSize="7" fontFamily="Arial,sans-serif"
              fontWeight="bold" fill="#222"
            >Prova: {formId}</text>
          </g>
        )}

        {/* ── COLUMN LETTER HEADERS (top and bottom, with background band) ── */}
        {items.length > 0 && (() => {
          const choices = items[0].choices;
          const topHdrY = ay(qStartY - 4);
          const botHdrY = ay(qStartY + maxPerSubCol * Q_ROW_H + 10);
          const bandH   = 11;
          return (
            <>
              {/* Background bands */}
              {[topHdrY, botHdrY].map(bandBaseY => (
                <rect key={bandBaseY}
                  x={subColX1} y={bandBaseY - 9}
                  width={qColW} height={bandH}
                  rx="1" fill="#f0f0f0" />
              ))}
              {/* Letter labels */}
              {[subColX1, subColX2].flatMap(sx =>
                choices.map((ch, ci) => {
                  const hx = sx + 24 + ci * BUBBLE_GAP;
                  return [
                    <text key={`th-${sx}-${ci}`} x={hx} y={topHdrY}
                      textAnchor="middle" fontSize="8.5" fontFamily="Arial,sans-serif"
                      fontWeight="bold" fill="#333">{ch}</text>,
                    <text key={`bh-${sx}-${ci}`} x={hx} y={botHdrY}
                      textAnchor="middle" fontSize="8.5" fontFamily="Arial,sans-serif"
                      fontWeight="bold" fill="#333">{ch}</text>,
                  ];
                })
              )}
            </>
          );
        })()}

        {/* ── QUESTION BUBBLES ──────────────────────────────────────── */}
        {items.map((item, idx) => {
          const isRight = idx >= maxPerSubCol;
          const rowIdx  = isRight ? idx - maxPerSubCol : idx;
          const subX    = isRight ? subColX2 : subColX1;
          const iy      = ay(qStartY + rowIdx * Q_ROW_H);

          const rowMid = iy + Q_ROW_H / 2;
          return (
            <g key={`q${item.num}`}>
              <text
                x={subX + 14} y={rowMid + 3.5}
                textAnchor="end" fontSize="9"
                fontFamily="Arial,sans-serif" fontWeight="bold" fill="black"
              >{item.num}</text>

              {item.type === "numeric" || item.type === "short_response" ? (
                <rect x={subX + 16} y={iy + 3} width={subColW - 20} height={Q_ROW_H - 6}
                  rx="2" fill="none" stroke="black" strokeWidth={STROKE * 0.6} />
              ) : (
                item.choices.map((ch, ci) => (
                  <Bubble key={ci}
                    cx={subX + 24 + ci * BUBBLE_GAP}
                    cy={rowMid} label={ch} />
                ))
              )}
            </g>
          );
        })}

        {/* ── STUDENT ID GRID ───────────────────────────────────────── */}
        {hasIdGrid && (() => {
          const digits  = header.studentIdDigits;
          const cellW   = Math.min(15, Math.floor((idColW - 2) / digits));
          const cellH   = 11;
          const gsY     = ay(qStartY);
          return (
            <g>
              <text x={idColX} y={gsY - 2} fontSize="7"
                fontFamily="Arial,sans-serif" fontWeight="bold" fill="black">
                {header.studentIdLabel}
              </text>
              {Array.from({ length: digits }).map((_, di) => (
                <g key={`id-${di}`}>
                  <rect x={idColX + di * cellW} y={gsY} width={cellW - 1} height={9}
                    fill="none" stroke="black" strokeWidth={STROKE * 0.45} />
                  {Array.from({ length: 10 }).map((_, row) => (
                    <Bubble key={row}
                      cx={idColX + di * cellW + (cellW - 1) / 2}
                      cy={gsY + 14 + row * cellH}
                      label={String(row)} r={BUBBLE_R * 0.78} />
                  ))}
                </g>
              ))}
            </g>
          );
        })()}

        {/* ── KEY / VERSION ─────────────────────────────────────────── */}
        {header.includeKeyVersion && header.keyVersionLetters.length > 0 && (
          <g>
            <text x={INNER_X} y={ay(botY - 14)} fontSize="7"
              fontFamily="Arial,sans-serif" fontWeight="bold" fill="black">
              {header.keyVersionLabel}
            </text>
            {header.keyVersionLetters.split("").map((letter, i) => (
              <Bubble key={i}
                cx={INNER_X + 8 + i * (BUBBLE_R * 2 + 5)}
                cy={ay(botY - 2)} label={letter} />
            ))}
          </g>
        )}

        {/* ── BOTTOM ROW: ArUco + barcodes ──────────────────────────── */}
        <ArucoMarker id={2} x={MARGIN}                  y={botArYC} />
        <ArucoMarker id={3} x={PAGE_W - MARGIN - ARUCO} y={botArYC} />

        {/* Exam barcode (preview — actual Code 128 in export) */}
        <BarcodePreview
          x={MARGIN + ARUCO + 6} y={bcY} h={BARCODE_H} text={examLabel} />
        <text
          x={MARGIN + ARUCO + 6 + 50} y={bcY + BARCODE_H + 5}
          textAnchor="middle" fontSize="5.5" fontFamily="Arial,sans-serif" fill="#555"
        >{examLabel}</text>

        {/* Student barcode preview (class packs) */}
        {studentCode && (() => {
          const bcAreaX = MARGIN + ARUCO + 6;
          const bcAreaW = PAGE_W - 2 * (MARGIN + ARUCO + 6);
          const stuX = bcAreaX + bcAreaW - 80;
          return (
            <>
              <BarcodePreview x={stuX} y={bcY} h={BARCODE_H} text={studentCode} />
              <text x={stuX + 40} y={bcY + BARCODE_H + 5}
                textAnchor="middle" fontSize="5.5" fontFamily="Arial,sans-serif" fill="#555"
              >{studentCode}</text>
            </>
          );
        })()}
      </svg>
    </div>
  );
}
