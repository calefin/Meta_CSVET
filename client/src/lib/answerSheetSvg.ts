import type { AnswerSheetConfig, HeaderField } from "@shared/_core/answerSheetTypes";
import { code128Svg, code128Width } from "./code128";

// ── A4 page (72 dpi points) ────────────────────────────────────────────────
export const PAGE_W = 595;
export const PAGE_H = 842;

/** Thrown when a standalone gabarito's content would not fit on one A4 page. */
export class AnswerSheetOverflowError extends Error {
  constructor(message: string) {
    super(message);
    this.name = "AnswerSheetOverflowError";
  }
}

function mmToPt(mm: number): number {
  return (mm * 72) / 25.4;
}

// ── Gabarito box geometry ──────────────────────────────────────────────────
const MARGIN   = 20;          // page margin (kept small; quiet zone handled separately)
const ARUCO    = Math.round(mmToPt(10)); // marker outer size (min 10mm)
const ARUCO_CELL = 4;
const QUIET_ZONE = Math.round(mmToPt(15)); // 15mm quiet zone around markers
const GAP      = 6;           // visual gap between markers and content
const INNER_X  = MARGIN + ARUCO + QUIET_ZONE; // ensure quiet zone after markers
const INNER_W  = PAGE_W - INNER_X - (MARGIN + ARUCO + QUIET_ZONE);

// ── Header field geometry ──────────────────────────────────────────────────
const FIELD_LABEL_H = 10;
const FIELD_BOX_H   = 24;
const FIELD_ROW_H   = FIELD_LABEL_H + FIELD_BOX_H + 8;

// ── Question grid ──────────────────────────────────────────────────────────
const Q_ROW_H  = 20;
const BUBBLE_R = 7.0;
const STROKE   = 1.1;
const BUBBLE_GAP = BUBBLE_R * 2 + 4; // space between bubble centres

// ── Student-ID grid ──────────────────────────────────────────────────────────
// Always 10 rows (digits 0-9) stacked per column, regardless of how many
// digits the ID has (digit count only changes the grid's width).
const ID_GRID_CELL_H = 11;
const ID_GRID_H = 14 + 10 * ID_GRID_CELL_H + 8; // label offset + 10 rows + bottom clearance

// ── Bottom bar ─────────────────────────────────────────────────────────────
const BOT_BAR_H  = 34;       // height of bottom strip (ArUco + barcode)
const BARCODE_H  = 16;
const BARCODE_MW = 1.2;      // Code 128 module width

// ── Large QR (student-facing answer-check) ─────────────────────────────────
const QR_LARGE_NO_ID   = 100; // ~35mm — no student-ID grid
const QR_LARGE_WITH_ID =  65; // ~23mm — keeps room for ID grid

const URL_TEXT = "www.prof-aid.com";

// ──────────────────────────────────────────────────────────────────────────
// ArUco-style markers (DICT_4X4_50-like patterns).
// 6×6 grid: outer ring always black, inner 4×4 encodes the corner ID.
// Layout:  TL=0  TR=1  BL=2  BR=3
// ──────────────────────────────────────────────────────────────────────────
const ARUCO_INNER: number[][][] = [
  // ID 0 — Top-Left
  [[1,0,1,0],[0,0,0,1],[1,0,0,0],[0,1,0,1]],
  // ID 1 — Top-Right
  [[0,1,0,1],[1,0,0,0],[0,0,0,1],[1,0,1,0]],
  // ID 2 — Bottom-Left
  [[1,1,0,0],[0,0,1,0],[0,1,0,0],[1,0,1,1]],
  // ID 3 — Bottom-Right
  [[0,0,1,1],[0,1,0,1],[1,0,1,0],[0,1,0,0]],
];

function esc(s: string): string {
  return s.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;");
}

// Renders an ArUco-like 6×6 marker at (x,y) with outer size `size`.
function arucoSvg(id: number, x: number, y: number, size = ARUCO): string {
  const cell = size / 6;
  const inner = ARUCO_INNER[id];
  let s = `<rect x="${x}" y="${y}" width="${size}" height="${size}" fill="black"/>`;
  // Inner 4×4 (offset by 1 cell)
  for (let r = 0; r < 4; r++) {
    for (let c = 0; c < 4; c++) {
      const fill = inner[r][c] ? "black" : "white";
      const cx = x + (c + 1) * cell;
      const cy = y + (r + 1) * cell;
      s += `<rect x="${cx.toFixed(2)}" y="${cy.toFixed(2)}" width="${cell.toFixed(2)}" height="${cell.toFixed(2)}" fill="${fill}"/>`;
    }
  }
  return s;
}

function bubble(cx: number, cy: number, label: string, r = BUBBLE_R, filled = false): string {
  const fillCol = filled ? "black" : "white";
  const textCol = filled ? "white" : "black";
  return (
    `<circle cx="${cx.toFixed(2)}" cy="${cy.toFixed(2)}" r="${r}" fill="${fillCol}" stroke="black" stroke-width="${STROKE}" vector-effect="non-scaling-stroke"/>` +
    `<text x="${cx.toFixed(2)}" y="${(cy + r * 0.4).toFixed(2)}" text-anchor="middle" font-size="${(r * 1.2).toFixed(1)}" font-family="Arial,sans-serif" font-weight="bold" fill="${textCol}">${esc(label)}</text>`
  );
}

function fieldBox(x: number, y: number, w: number, label: string, prefill = ""): string {
  return (
    `<text x="${x}" y="${(y + FIELD_LABEL_H - 2).toFixed(2)}" font-size="7" font-family="Arial,sans-serif" font-weight="bold" fill="black">${esc(label)}</text>` +
    `<rect x="${x}" y="${(y + FIELD_LABEL_H).toFixed(2)}" width="${w}" height="${FIELD_BOX_H}" rx="2" fill="white" stroke="black" stroke-width="${(STROKE * 0.6).toFixed(2)}"/>` +
    (prefill
      ? `<text x="${(x + 3).toFixed(2)}" y="${(y + FIELD_LABEL_H + FIELD_BOX_H - 5).toFixed(2)}" font-size="8" font-family="Arial,sans-serif" fill="black">${esc(prefill)}</text>`
      : "")
  );
}

interface FieldLayout { field: HeaderField; x: number; y: number; w: number; }

function computeFieldLayouts(fields: HeaderField[], ox: number, oy: number, ow: number): FieldLayout[] {
  const layouts: FieldLayout[] = [];
  let curY = oy;
  let i = 0;
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

// ── Height calculation ─────────────────────────────────────────────────────

interface SizeOptions {
  config: AnswerSheetConfig;
}

/** Returns the total height of the gabarito box (excluding A4 centering). */
export function gabaritoHeight(opts: SizeOptions): number {
  const { header, questions } = opts.config;
  const enabledFields = header.fields.filter(f => f.enabled);
  const fakeLayouts = computeFieldLayouts(enabledFields, 0, 0, INNER_W);
  const headerH = fakeLayouts.length > 0
    ? fakeLayouts[fakeLayouts.length - 1].y + FIELD_ROW_H
    : 0;

  const totalQ = questions.reduce((s, b) => s + b.count, 0);
  const hasIdGrid = header.includeStudentId && header.studentIdDigits > 0;
  const qColW = hasIdGrid ? Math.floor(INNER_W * 0.62) : INNER_W;
  const subColW = Math.floor(qColW / 2) - 2;
  const maxPerSubCol = Math.ceil(totalQ / 2);
  // Each sub-col height
  const leftH = Math.ceil(totalQ / 2) * Q_ROW_H;
  const rightH = Math.floor(totalQ / 2) * Q_ROW_H;
  const qBodyH = Math.max(leftH, rightH, hasIdGrid ? ID_GRID_H : 0);

  void subColW; void maxPerSubCol; // suppress unused warning

  const topBarH = ARUCO + 4; // markers + URL row
  const sep = 6;             // divider line gap
  return topBarH + GAP + headerH + sep + 22 + qBodyH + 10 + BOT_BAR_H;
}

// ── Main SVG generator ─────────────────────────────────────────────────────

export interface AnswerSheetSvgOptions {
  /** Pre-filled student name (class pack). */
  studentName?: string;
  /** Exam/template ID encoded in the barcode. */
  formId?: string;
  /** Student ID encoded as a second barcode (e.g. enrollment number). */
  studentCode?: string;
  /** Optional payload to render as a QR code (string data). */
  qrPayload?: string;
  /** Pre-generated QR as a data URI (e.g. "data:image/svg+xml;base64,...") for server-side rendering. */
  inlineQrDataUri?: string;
  /**
   * standalone=true  → A4 page (595×842), gabarito centered vertically.
   * standalone=false → returns only the gabarito box SVG (for embedding).
   */
  standalone?: boolean;
}

/**
 * Generates an SVG string for a gabarito sheet.
 *
 * Layout (top → bottom inside the gabarito box):
 *   [ArUco TL] [www.prof-aid.com] [ArUco TR]
 *   header fields (Nome, Turma, Data…)
 *   ─────────────────────────────────────────
 *   question bubbles (2 sub-columns) | student-ID grid (optional)
 *   [ArUco BL] [Code128 exam ID] [Code128 student] [ArUco BR]
 */
export function generateAnswerSheetSVG(
  name: string,
  config: AnswerSheetConfig,
  optionsOrStudentName?: AnswerSheetSvgOptions | string,
): string {
  // Backwards-compatible: old callers pass studentName as 3rd string arg
  const opts: AnswerSheetSvgOptions =
    typeof optionsOrStudentName === "string"
      ? { studentName: optionsOrStudentName }
      : (optionsOrStudentName ?? {});

  const { studentName, formId, studentCode, standalone = true, inlineQrDataUri } = opts;
  const { header, questions } = config;

  const items = questions.flatMap(block => {
    // "verbose" blocks may have been saved with an empty `choices` (only synced
    // when the user actively edited verboseAnswers) — fall back to the short
    // labels so the row still gets bubbles instead of rendering blank.
    const fallbackChoices =
      block.type === "verbose" && (!block.choices || block.choices.length === 0)
        ? (block.verboseAnswers ?? []).map(a => a.shortLabel).filter(Boolean)
        : block.choices;
    // "alternation" blocks must alternate label sets by question-number parity;
    // `block.choices` alone only ever mirrors oddChoices.
    const oddChoices  = block.oddChoices  ?? fallbackChoices;
    const evenChoices = block.evenChoices ?? fallbackChoices;

    return Array.from({ length: block.count }, (_, i) => {
      const num = block.startNumber + i;
      const choices = block.type === "alternation"
        ? (num % 2 === 1 ? oddChoices : evenChoices)
        : fallbackChoices;
      return { num, choices, type: block.type };
    });
  });

  const enabledFields = header.fields.filter(f => f.enabled);
  const hasIdGrid = header.includeStudentId && header.studentIdDigits > 0;

  // ── Large QR column (left of bubbles, for students to check answers) ───────
  const showLargeQr = !!formId;
  const rawQrSize   = showLargeQr ? (hasIdGrid ? QR_LARGE_WITH_ID : QR_LARGE_NO_ID) : 0;
  const largeQrGapX = showLargeQr ? 8 : 0;

  // Preserve the ID-grid absolute width from the original 62/38 split
  const idColW_base = INNER_W - Math.floor(INNER_W * 0.62) - 6; // ≈158 pt
  const idColW = hasIdGrid ? idColW_base : 0;

  // Bubble area starts after QR column
  const bubbleStart = INNER_X + rawQrSize + largeQrGapX;
  const bubbleAvailW = INNER_W - rawQrSize - largeQrGapX;
  const qColW = hasIdGrid ? (bubbleAvailW - idColW - 6) : bubbleAvailW;
  const idColX = bubbleStart + qColW + 6;

  // ── Geometry ────────────────────────────────────────────────────────────
  const topBarH  = ARUCO + 4;
  const INNER_Y0 = MARGIN + topBarH + GAP;   // first pixel of header fields

  const fieldLayouts = computeFieldLayouts(enabledFields, INNER_X, INNER_Y0, INNER_W);
  const headerH = fieldLayouts.length > 0
    ? fieldLayouts[fieldLayouts.length - 1].y - INNER_Y0 + FIELD_ROW_H
    : 0;

  const qStartY = INNER_Y0 + headerH + 22;
  const subColW  = Math.floor(qColW / 2) - 2;
  const subColX1 = bubbleStart;
  const subColX2 = bubbleStart + subColW + 4;

  const maxPerSubCol = Math.ceil(items.length / 2);
  const bubbleBodyH  = maxPerSubCol * Q_ROW_H;
  // QR height must not overflow the body; cap it to the bubble height
  const largeQrSize  = Math.min(rawQrSize, bubbleBodyH + 4);
  // Body height must fit the tallest column: question bubbles, QR, or the student-ID grid
  // (the ID grid is always 10 rows tall regardless of digit count — see ID_GRID_H).
  const qBodyH       = Math.max(bubbleBodyH, largeQrSize, hasIdGrid ? ID_GRID_H : 0);

  const botY = qStartY + qBodyH + 10;         // top of bottom bar
  const boxH = botY + BOT_BAR_H + MARGIN;     // total gabarito box height

  // A standalone sheet is a fixed A4 page: content taller than that gets
  // silently clipped by the SVG viewBox instead of failing loudly, so the
  // extra questions would just vanish from the printed/downloaded sheet.
  // Refuse to generate a broken sheet — tell the caller how many questions
  // would actually fit with this header/ID-grid configuration.
  if (standalone && boxH > PAGE_H - MARGIN) {
    const maxAllowedQBodyH = PAGE_H - MARGIN - qStartY - 10 - BOT_BAR_H - MARGIN;
    const maxPerSubColAllowed = Math.max(0, Math.floor(maxAllowedQBodyH / Q_ROW_H));
    const maxQuestions = maxPerSubColAllowed * 2;
    throw new AnswerSheetOverflowError(
      `Este gabarito tem ${items.length} questões, mas só cabem até ${maxQuestions} numa única folha A4 com esta configuração de cabeçalho${hasIdGrid ? " e matrícula" : ""}. Reduza o número de questões ou simplifique o cabeçalho.`
    );
  }

  // Top-align: start at page top margin (no vertical centering)
  const offsetY = standalone ? MARGIN : 0;

  const svgW = standalone ? PAGE_W : PAGE_W;
  const svgH = standalone ? PAGE_H : boxH;

  // ── SVG header ──────────────────────────────────────────────────────────
  let s = `<?xml version="1.0" encoding="UTF-8"?>\n`;
  s += `<svg width="${svgW}" height="${svgH}" viewBox="0 0 ${svgW} ${svgH}" xmlns="http://www.w3.org/2000/svg">\n`;
  s += `<rect width="${svgW}" height="${svgH}" fill="white"/>\n`;

  // Helper: absolute Y in the SVG
  const ay = (rel: number) => rel + offsetY;

  // ── TOP ROW: ArUco markers + URL ────────────────────────────────────────
  const topArY = ay(MARGIN);
  s += arucoSvg(0, MARGIN,          topArY);            // TL
  s += arucoSvg(1, PAGE_W - MARGIN - ARUCO, topArY);   // TR

  // URL centered between markers
  const urlX = MARGIN + ARUCO + 6;
  const urlW = PAGE_W - 2 * (MARGIN + ARUCO + 6);
  s += `<text x="${(urlX + urlW / 2).toFixed(1)}" y="${(ay(MARGIN + ARUCO / 2 + 3)).toFixed(1)}" `;
  s += `text-anchor="middle" font-size="11" font-family="Arial,sans-serif" font-weight="bold" fill="#000">${esc(URL_TEXT)}</text>\n`;

  // Form name (small, under URL)
  if (name) {
    s += `<text x="${(urlX + urlW / 2).toFixed(1)}" y="${(ay(MARGIN + ARUCO - 2)).toFixed(1)}" `;
    s += `text-anchor="middle" font-size="8" font-family="Arial,sans-serif" fill="#555">${esc(name)}</text>\n`;
  }

  // ── OUTER BOX (visible border around entire gabarito) ──────────────────
  s += `<rect x="${MARGIN}" y="${ay(MARGIN)}" width="${PAGE_W - 2 * MARGIN}" height="${boxH - 2 * MARGIN}" `;
  s += `rx="3" fill="none" stroke="#999" stroke-width="1.0"/>\n`;

  // ── HEADER FIELDS ───────────────────────────────────────────────────────
  for (const layout of fieldLayouts) {
    const prefill = layout.field.id === "name" ? (studentName ?? "") : "";
    const label = layout.field.displayLabel || layout.field.defaultLabel;
    s += fieldBox(layout.x, ay(layout.y), layout.w, label, prefill);
  }

  // Divider under header
  if (headerH > 0) {
    const divY = ay(INNER_Y0 + headerH + 4);
    s += `<line x1="${INNER_X}" y1="${divY.toFixed(1)}" x2="${PAGE_W - INNER_X}" y2="${divY.toFixed(1)}" stroke="#333" stroke-width="0.8"/>\n`;
  }

  // ── LARGE QR (left column, student-facing answer check) ─────────────────
  if (showLargeQr && largeQrSize > 0) {
    const qrData = `https://prof-aid.com/r/${formId}`;
    const qrUrl  = inlineQrDataUri ?? `/api/qr?size=${largeQrSize}&data=${encodeURIComponent(qrData)}`;
    const qrX    = INNER_X;
    const qrY    = ay(qStartY);
    s += `<image href="${qrUrl}" x="${qrX}" y="${qrY.toFixed(1)}" width="${largeQrSize}" height="${largeQrSize}" preserveAspectRatio="xMidYMid meet"/>\n`;
    s += `<text x="${(qrX + largeQrSize / 2).toFixed(1)}" y="${(qrY + largeQrSize + 9).toFixed(1)}" `;
    s += `text-anchor="middle" font-size="7" font-family="Arial,sans-serif" font-weight="bold" fill="#222">Prova: ${esc(formId!)}</text>\n`;
  }

  // ── COLUMN LETTER HEADERS (top and bottom, with background band) ─────────
  if (items.length > 0) {
    const firstChoices = items[0].choices;
    const topHdrY = ay(qStartY - 4);            // baseline of top header row
    const botHdrY = ay(qStartY + maxPerSubCol * Q_ROW_H + 10); // baseline of bottom row
    const bandH   = 11;                          // height of highlight strip

    // Background bands (draw before text so text renders on top)
    for (const bandBaseY of [topHdrY, botHdrY]) {
      s += `<rect x="${subColX1.toFixed(1)}" y="${(bandBaseY - 9).toFixed(1)}" width="${qColW}" height="${bandH}" rx="1" fill="#f0f0f0"/>`;
    }

    for (const sx of [subColX1, subColX2]) {
      firstChoices.forEach((ch, ci) => {
        const hx = (sx + 24 + ci * BUBBLE_GAP).toFixed(1);
        s += `<text x="${hx}" y="${topHdrY.toFixed(1)}" text-anchor="middle" font-size="8.5" font-family="Arial,sans-serif" font-weight="bold" fill="#333">${esc(ch)}</text>`;
        s += `<text x="${hx}" y="${botHdrY.toFixed(1)}" text-anchor="middle" font-size="8.5" font-family="Arial,sans-serif" font-weight="bold" fill="#333">${esc(ch)}</text>`;
      });
    }
    s += "\n";
  }

  // ── QUESTION BUBBLES (2 sub-columns) ────────────────────────────────────
  for (let idx = 0; idx < items.length; idx++) {
    const item = items[idx];
    const isRight = idx >= maxPerSubCol;
    const rowIdx  = isRight ? idx - maxPerSubCol : idx;
    const subX    = isRight ? subColX2 : subColX1;
    const iy      = ay(qStartY + rowIdx * Q_ROW_H);

    const rowMid = iy + Q_ROW_H / 2;
    s += `<text x="${(subX + 14).toFixed(1)}" y="${(rowMid + 3.5).toFixed(1)}" text-anchor="end" font-size="9" font-family="Arial,sans-serif" font-weight="bold" fill="black">${item.num}</text>`;

    if (item.type === "numeric" || item.type === "short_response") {
      s += `<rect x="${(subX + 16).toFixed(1)}" y="${(iy + 3).toFixed(1)}" width="${(subColW - 20).toFixed(1)}" height="${Q_ROW_H - 6}" rx="2" fill="none" stroke="black" stroke-width="${(STROKE * 0.6).toFixed(2)}"/>`;
    } else {
      for (let ci = 0; ci < item.choices.length; ci++) {
        s += bubble(subX + 24 + ci * BUBBLE_GAP, rowMid, item.choices[ci]);
      }
    }
  }

  // ── STUDENT ID GRID (right column) ──────────────────────────────────────
  if (hasIdGrid) {
    const digits     = header.studentIdDigits;
    const cellW      = Math.min(15, Math.floor((idColW - 2) / digits));
    const cellH      = 11;
    const gridStartY = ay(qStartY);

    s += `<text x="${idColX}" y="${(gridStartY - 2).toFixed(1)}" font-size="7" font-family="Arial,sans-serif" font-weight="bold" fill="black">${esc(header.studentIdLabel)}</text>`;

    // Pad studentCode with leading zeros to match the number of digits (e.g. 123 -> 00123)
    const paddedCode = studentCode
      ? studentCode.replace(/\D/g, "").padStart(digits, "0")
      : "";

    for (let di = 0; di < digits; di++) {
      const bx = idColX + di * cellW;
      const prefillDigit = paddedCode && di < paddedCode.length ? paddedCode[di] : "";

      s += `<rect x="${bx}" y="${gridStartY.toFixed(1)}" width="${cellW - 1}" height="9" fill="none" stroke="black" stroke-width="${(STROKE * 0.45).toFixed(2)}"/>`;
      
      if (prefillDigit) {
        s += `<text x="${(bx + (cellW - 1) / 2).toFixed(1)}" y="${(gridStartY + 7.2).toFixed(1)}" text-anchor="middle" font-size="7" font-family="Arial,sans-serif" font-weight="bold" fill="black">${esc(prefillDigit)}</text>`;
      }

      for (let row = 0; row < 10; row++) {
        const cx = bx + (cellW - 1) / 2;
        const isFilled = prefillDigit === String(row);
        s += bubble(cx, gridStartY + 14 + row * cellH, String(row), BUBBLE_R * 0.78, isFilled);
      }
    }
  }

  // ── KEY / VERSION ────────────────────────────────────────────────────────
  if (header.includeKeyVersion && header.keyVersionLetters.length > 0) {
    const kvY = ay(botY - 14);
    s += `<text x="${INNER_X}" y="${(kvY - 3).toFixed(1)}" font-size="8" font-family="Arial,sans-serif" font-weight="bold" fill="black">${esc(header.keyVersionLabel)}</text>`;
    header.keyVersionLetters.split("").forEach((letter, i) => {
      s += bubble(INNER_X + 10 + i * (BUBBLE_GAP + 2), kvY + BUBBLE_R, letter);
    });
  }

  // ── BOTTOM ROW: ArUco markers + barcodes ────────────────────────────────
  const botArY  = ay(botY);
  const botArYC = botArY + (BOT_BAR_H - ARUCO) / 2;    // vertically center ArUco in bar
  s += arucoSvg(2, MARGIN,              botArYC);        // BL
  s += arucoSvg(3, PAGE_W - MARGIN - ARUCO, botArYC);   // BR

  // Barcodes between the two ArUco markers
  const bcAreaX = MARGIN + ARUCO + 6;
  const bcAreaW = PAGE_W - 2 * (MARGIN + ARUCO + 6);
  const bcY     = botArY + (BOT_BAR_H - BARCODE_H) / 2;

  // Exam / form barcode (left side of barcode area)
  const examLabel = formId ?? "PROF-AID";
  const examBcW   = code128Width(examLabel, BARCODE_MW);
  s += code128Svg(examLabel, bcAreaX, bcY, BARCODE_H, BARCODE_MW);
  s += `<text x="${(bcAreaX + examBcW / 2).toFixed(1)}" y="${(bcY + BARCODE_H + 5).toFixed(1)}" text-anchor="middle" font-size="5.5" font-family="Arial,sans-serif" fill="#555">${esc(examLabel)}</text>`;

  // Student barcode (right side of barcode area, class packs only)
  if (studentCode) {
    const stuBcW = code128Width(studentCode, BARCODE_MW);
    const stuX   = bcAreaX + bcAreaW - stuBcW;
    s += code128Svg(studentCode, stuX, bcY, BARCODE_H, BARCODE_MW);
    s += `<text x="${(stuX + stuBcW / 2).toFixed(1)}" y="${(bcY + BARCODE_H + 5).toFixed(1)}" text-anchor="middle" font-size="5.5" font-family="Arial,sans-serif" fill="#555">${esc(studentCode)}</text>`;
  }

  s += `\n</svg>`;
  return s;
}
