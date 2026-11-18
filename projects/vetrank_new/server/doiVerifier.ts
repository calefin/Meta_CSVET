/**
 * DOI Verification — checks bibliographic references against the CrossRef API.
 * CrossRef is the official DOI registration authority for most academic publishers.
 * GET /works/{doi} → 200 if exists, 404 if not registered.
 *
 * We use their "polite pool" (faster rate limits) by sending a User-Agent with email.
 */

const CROSSREF_BASE = "https://api.crossref.org/works";
const UA = "VetRank/1.0 (mailto:contato@vetrank.com.br)";
const TIMEOUT_MS = 8_000;
const POLITE_DELAY_MS = 250; // respect CrossRef polite pool

export type DOIStatus = "valid" | "not_found" | "error";

export interface DOIVerification {
  doi: string;
  status: DOIStatus;
  title?: string; // CrossRef title if valid
}

/** Extract all DOI strings from HTML/text. Returns unique DOIs. */
export function extractDOIs(html: string): string[] {
  // Standard DOI pattern: 10.NNNN/anything-not-whitespace-or-html-delimiters
  const pattern = /\b(10\.\d{4,9}\/[^\s"'<>&,;)\]\n]+)/g;
  const matches = [...html.matchAll(pattern)];
  const dois = matches.map(m => m[1].replace(/[.,;]$/, "")); // strip trailing punctuation
  return [...new Set(dois)];
}

/** Verify a single DOI via CrossRef API. Returns 'valid', 'not_found', or 'error'. */
export async function verifyDOI(doi: string): Promise<DOIVerification> {
  try {
    const url = `${CROSSREF_BASE}/${encodeURIComponent(doi)}`;
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), TIMEOUT_MS);

    let res: Response;
    try {
      res = await fetch(url, {
        headers: { "User-Agent": UA, Accept: "application/json" },
        signal: controller.signal,
      });
    } finally {
      clearTimeout(timer);
    }

    if (res.status === 200) {
      try {
        const data = await res.json() as any;
        const title = data?.message?.title?.[0] ?? undefined;
        return { doi, status: "valid", title };
      } catch {
        return { doi, status: "valid" };
      }
    }

    if (res.status === 404) {
      return { doi, status: "not_found" };
    }

    // Rate limit or server error → be conservative, treat as unknown (not invalid)
    return { doi, status: "error" };
  } catch {
    // Network failure, timeout → don't flag as invalid
    return { doi, status: "error" };
  }
}

/**
 * Verify all DOIs with polite rate limiting.
 * Errors (network/timeout) are NOT treated as invalid — only explicit 404s.
 */
export async function verifyAllDOIs(dois: string[]): Promise<DOIVerification[]> {
  const results: DOIVerification[] = [];
  for (let i = 0; i < dois.length; i++) {
    results.push(await verifyDOI(dois[i]));
    if (i < dois.length - 1) {
      await new Promise(r => setTimeout(r, POLITE_DELAY_MS));
    }
  }
  return results;
}

/**
 * Surgically remove invalid DOI links/citations from HTML:
 * - <a href="...10.x/fake...">text</a>  →  text
 * - (doi.org/10.x/fake)                 →  (removed)
 * - doi:10.x/fake                       →  (removed)
 */
export function removeInvalidDOILinks(html: string, invalidDois: string[]): string {
  let result = html;

  for (const doi of invalidDois) {
    const esc = doi.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");

    // Remove <a href="...doi...">inner text</a> → keep inner text
    result = result.replace(
      new RegExp(`<a[^>]*href="[^"]*${esc}[^"]*"[^>]*>(.*?)</a>`, "gi"),
      "$1"
    );

    // Remove parenthetical doi.org reference: (doi.org/10.x/y) or (https://doi.org/10.x/y)
    result = result.replace(
      new RegExp(`\\((?:https?://(?:dx\\.)?doi\\.org/)?${esc}[^)]*\\)`, "gi"),
      ""
    );

    // Remove bare doi.org/10.x/y
    result = result.replace(
      new RegExp(`(?:https?://(?:dx\\.)?doi\\.org/)${esc}\\S*`, "gi"),
      "[referência removida]"
    );

    // Remove inline doi: 10.x/y
    result = result.replace(
      new RegExp(`\\bdoi:\\s*${esc}\\S*`, "gi"),
      "[referência removida]"
    );
  }

  // Clean up orphaned "[referência removida]" sequences
  result = result.replace(/(\[referência removida\]\s*){2,}/gi, "[referência removida] ");

  return result;
}
