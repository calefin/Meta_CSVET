/**
 * Utilities to embed QR SVGs returned by `/api/qr` directly into an SVG string
 * as data URIs, so the resulting SVG is self-contained and works offline.
 */
export async function embedQrSvgsInSvg(svgString: string): Promise<string> {
  // find all occurrences of /api/qr?...
  const regex = /\/api\/qr\?[^"'>\s]+/g;
  const matches = svgString.match(regex);
  if (!matches) return svgString;

  let result = svgString;
  // Deduplicate
  const uniq = Array.from(new Set(matches));

  for (const urlPath of uniq) {
    try {
      const resp = await fetch(urlPath);
      if (!resp.ok) continue;
      const qrSvg = await resp.text();
      // encode as URI component to be safe for data URI
      const dataUri = 'data:image/svg+xml;utf8,' + encodeURIComponent(qrSvg);
      // replace all occurrences of the URL with the data URI
      const esc = urlPath.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
      const r = new RegExp(esc, 'g');
      result = result.replace(r, dataUri);
    } catch (e) {
      // ignore and continue
      console.error('embedQrSvgsInSvg failed for', urlPath, e);
    }
  }
  return result;
}

export default embedQrSvgsInSvg;
