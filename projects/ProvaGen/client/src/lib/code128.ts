/**
 * Code 128B barcode generator for SVG output.
 * Encodes ASCII 32–126 (printable characters).
 * Implements ISO/IEC 15417 — widths table from the standard.
 */

// Each entry: 6 digits = bar1, space1, bar2, space2, bar3, space3 (module widths)
// Index = symbol value (0–106). Code B: symbol = ASCII - 32.
const SYMBOL_WIDTHS: string[] = [
  "212222","222122","222221","121223","121322","131222","122213","122312",
  "132212","221213","221312","231212","112232","122132","122231","113222",
  "123122","123221","223211","221132","221231","213212","223112","312131",
  "311222","321122","321221","312212","322112","322211","212123","212321",
  "232121","111323","131123","131321","112313","132113","132311","211313",
  "231113","231311","112133","112331","132131","113123","113321","133121",
  "313121","211331","231131","213113","213311","213131","311123","311321",
  "331121","312113","312311","332111","314111","221411","431111","111224",
  "111422","121124","121421","141122","141221","112214","112412","122114",
  "122411","142112","142211","241211","221114","413111","241112","134111",
  "111242","121142","121241","114212","124112","124211","411212","421112",
  "421211","212141","214121","412121","111143","111341","131141","114113",
  "114311","411113","411311","113141","114131","311141","411131",
  // 103=Start A, 104=Start B, 105=Start C (used internally)
  "211412","211214","211232",
];

// Stop: 13-module pattern + 2-module terminal bar
const STOP_WIDTHS = "2331112";
const STOP_TERMINAL = 2;

const START_B = 104;

function symModules(sym: number): number {
  return SYMBOL_WIDTHS[sym].split("").reduce((s, c) => s + parseInt(c, 10), 0);
}

/** Total SVG width in px for a given text encoded as Code 128B. */
export function code128Width(text: string, moduleWidth: number): number {
  let modules = symModules(START_B); // start
  for (let i = 0; i < text.length; i++) {
    const v = text.charCodeAt(i) - 32;
    if (v >= 0 && v <= 94) modules += symModules(v);
  }
  modules += symModules(0); // check (any symbol is 11 modules)
  // stop: 13 + terminal 2
  modules += STOP_WIDTHS.split("").reduce((s, c) => s + parseInt(c, 10), 0) + STOP_TERMINAL;
  return modules * moduleWidth;
}

/**
 * Generate SVG rect elements for a Code 128B barcode.
 * Returns raw SVG string (no outer <svg> tag).
 */
export function code128Svg(
  text: string,
  x: number,
  y: number,
  barHeight: number,
  moduleWidth = 1.5,
): string {
  if (!text) return "";

  const symbols: number[] = [START_B];
  for (let i = 0; i < text.length; i++) {
    const v = text.charCodeAt(i) - 32;
    if (v >= 0 && v <= 94) symbols.push(v);
  }

  // Check character: (startValue + Σ position*value) % 103
  let check = START_B;
  for (let i = 1; i < symbols.length; i++) check += i * symbols[i];
  symbols.push(check % 103);

  let svg = "";
  let cx = x;

  function drawWidths(widths: string) {
    for (let i = 0; i < widths.length; i++) {
      const w = parseInt(widths[i], 10) * moduleWidth;
      if (i % 2 === 0) { // bar
        svg += `<rect x="${cx.toFixed(2)}" y="${y}" width="${w.toFixed(2)}" height="${barHeight}" fill="black"/>`;
      }
      cx += w;
    }
  }

  for (const sym of symbols) drawWidths(SYMBOL_WIDTHS[sym]);

  // Stop pattern + terminal bar
  drawWidths(STOP_WIDTHS);
  svg += `<rect x="${cx.toFixed(2)}" y="${y}" width="${(STOP_TERMINAL * moduleWidth).toFixed(2)}" height="${barHeight}" fill="black"/>`;

  return svg;
}
