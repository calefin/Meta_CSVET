/**
 * Gerador de Cards de Compartilhamento (SVG → PNG com sharp):
 *  - 1200×630 (Open Graph / WhatsApp / LinkedIn / Twitter)
 *  - 1080×1920 (Instagram Story / Reels / Status do WhatsApp)
 *  - 1080×1080 (Instagram Feed / Carrossel)
 * Fonte: DejaVu Sans (instalada no Dockerfile). Cache em memória por chave.
 */
import sharp from "sharp";

export type CardInput = {
  kicker: string;            // faixa superior, ex.: "CONCURSO ABERTO · SP"
  title: string;             // título grande (quebra automática)
  subtitle?: string;         // linha abaixo do título
  stats?: { label: string; value: string }[];   // até 4 blocos
  footer?: string;           // ex.: "Inscrições até 10/12/2026"
  cta?: string;              // chamada para ação (usado em Story e Feed)
  accent?: string;           // cor de destaque
};

const esc = (s: string) => s.replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]!));

function wrap(text: string, maxChars: number, maxLines: number): string[] {
  const words = text.replace(/\s+/g, " ").trim().split(" ");
  const lines: string[] = [];
  let cur = "";
  for (const w of words) {
    if ((cur + " " + w).trim().length > maxChars) {
      if (cur) lines.push(cur);
      cur = w;
      if (lines.length === maxLines) break;
    } else cur = (cur + " " + w).trim();
  }
  if (cur && lines.length < maxLines) lines.push(cur);
  if (lines.length === maxLines && words.join(" ").length > lines.join(" ").length) {
    lines[maxLines - 1] = lines[maxLines - 1].replace(/\s*\S*$/, "") + "…";
  }
  return lines;
}

export function cardSvg(c: CardInput): string {
  const accent = c.accent ?? "#10b981";
  const titleLines = wrap(c.title, 30, 3);
  const titleSize = titleLines.length >= 3 ? 54 : 62;
  const titleY0 = 210;
  const titleSvg = titleLines.map((l, i) =>
    `<text x="72" y="${titleY0 + i * (titleSize + 12)}" font-size="${titleSize}" font-weight="700" fill="#f8fafc">${esc(l)}</text>`).join("");
  const afterTitle = titleY0 + titleLines.length * (titleSize + 12);
  const subtitle = c.subtitle
    ? `<text x="72" y="${afterTitle + 8}" font-size="30" fill="#94a3b8">${esc(wrap(c.subtitle, 58, 1)[0] ?? "")}</text>` : "";
  const stats = (c.stats ?? []).slice(0, 3);
  const statW = 330;
  const statsSvg = stats.map((s, i) => {
    const x = 72 + i * (statW + 24);
    return `<rect x="${x}" y="430" width="${statW}" height="104" rx="18" fill="#0f1f1a" stroke="#1f3b32"/>
      <text x="${x + 24}" y="472" font-size="22" fill="#94a3b8">${esc(s.label)}</text>
      <text x="${x + 24}" y="514" font-size="34" font-weight="700" fill="${accent}">${esc(wrap(s.value, 16, 1)[0] ?? "")}</text>`;
  }).join("");
  return `<svg width="1200" height="630" viewBox="0 0 1200 630" xmlns="http://www.w3.org/2000/svg" font-family="DejaVu Sans, sans-serif">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#05110d"/><stop offset="1" stop-color="#0b1220"/></linearGradient>
    <radialGradient id="glow" cx="85%" cy="10%" r="60%"><stop offset="0" stop-color="${accent}" stop-opacity="0.28"/><stop offset="1" stop-color="#05110d" stop-opacity="0"/></radialGradient>
  </defs>
  <rect width="1200" height="630" fill="url(#bg)"/>
  <rect width="1200" height="630" fill="url(#glow)"/>
  <rect x="72" y="72" width="${Math.min(1056, 28 + c.kicker.length * 15)}" height="48" rx="24" fill="${accent}" fill-opacity="0.15" stroke="${accent}" stroke-opacity="0.5"/>
  <text x="96" y="104" font-size="22" font-weight="700" fill="${accent}" letter-spacing="1">${esc(c.kicker.toUpperCase())}</text>
  ${titleSvg}
  ${subtitle}
  ${statsSvg}
  <text x="72" y="590" font-size="26" fill="#cbd5e1">${esc(c.footer ?? "")}</text>
  <text x="1128" y="590" font-size="30" font-weight="700" fill="${accent}" text-anchor="end">VetRank</text>
</svg>`;
}

/** Card vertical 1080×1920 para Instagram Story / Reels / Status do WhatsApp */
export function storySvg(c: CardInput): string {
  const accent = c.accent ?? "#10b981";
  const titleLines = wrap(c.title, 22, 4);
  const titleSize = titleLines.length >= 4 ? 60 : 68;
  const titleY0 = 430;
  const titleSvg = titleLines.map((l, i) =>
    `<text x="84" y="${titleY0 + i * (titleSize + 18)}" font-size="${titleSize}" font-weight="700" fill="#f8fafc">${esc(l)}</text>`
  ).join("");
  const afterTitle = titleY0 + titleLines.length * (titleSize + 18);

  const subLines = c.subtitle ? wrap(c.subtitle, 38, 2) : [];
  const subtitleSvg = subLines.map((l, i) =>
    `<text x="84" y="${afterTitle + 24 + i * 44}" font-size="34" fill="#94a3b8">${esc(l)}</text>`
  ).join("");

  const stats = (c.stats ?? []).slice(0, 4);
  const statsY0 = 960;
  const statsSvg = stats.map((s, i) => {
    const y = statsY0 + i * 145;
    return `<rect x="84" y="${y}" width="912" height="122" rx="24" fill="#0f1f1a" stroke="#1f3b32" stroke-width="2"/>
      <text x="124" y="${y + 48}" font-size="26" fill="#94a3b8">${esc(s.label.toUpperCase())}</text>
      <text x="124" y="${y + 96}" font-size="42" font-weight="700" fill="${accent}">${esc(wrap(s.value, 28, 1)[0] ?? "")}</text>`;
  }).join("");

  const ctaText = c.cta ?? "Simulado e Edital Verticalizado em vetrank.com.br";

  return `<svg width="1080" height="1920" viewBox="0 0 1080 1920" xmlns="http://www.w3.org/2000/svg" font-family="DejaVu Sans, sans-serif">
  <defs>
    <linearGradient id="bgS" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#04100c"/><stop offset="0.6" stop-color="#091522"/><stop offset="1" stop-color="#05110d"/></linearGradient>
    <radialGradient id="glowS" cx="80%" cy="15%" r="65%"><stop offset="0" stop-color="${accent}" stop-opacity="0.32"/><stop offset="1" stop-color="#05110d" stop-opacity="0"/></radialGradient>
  </defs>
  <rect width="1080" height="1920" fill="url(#bgS)"/>
  <rect width="1080" height="1920" fill="url(#glowS)"/>

  <!-- Top Brand Header -->
  <text x="84" y="160" font-size="44" font-weight="700" fill="${accent}">VetRank</text>
  <text x="996" y="156" font-size="24" fill="#94a3b8" text-anchor="end">RADAR DE CONCURSOS</text>
  <line x1="84" y1="196" x2="996" y2="196" stroke="#1f3b32" stroke-width="2"/>

  <!-- Kicker Pill -->
  <rect x="84" y="260" width="${Math.min(912, 48 + c.kicker.length * 19)}" height="64" rx="32" fill="${accent}" fill-opacity="0.16" stroke="${accent}" stroke-opacity="0.6" stroke-width="2"/>
  <text x="116" y="302" font-size="28" font-weight="700" fill="${accent}" letter-spacing="1">${esc(c.kicker.toUpperCase())}</text>

  ${titleSvg}
  ${subtitleSvg}
  ${statsSvg}

  <!-- Bottom CTA Banner -->
  <rect x="84" y="1580" width="912" height="190" rx="28" fill="${accent}" fill-opacity="0.14" stroke="${accent}" stroke-opacity="0.55" stroke-width="2"/>
  <text x="540" y="1645" font-size="30" font-weight="700" fill="#f8fafc" text-anchor="middle">${esc(c.footer ?? "Confira o edital e treine com simulados")}</text>
  <rect x="144" y="1672" width="792" height="68" rx="34" fill="${accent}"/>
  <text x="540" y="1717" font-size="28" font-weight="700" fill="#04100c" text-anchor="middle">${esc(wrap(ctaText, 44, 1)[0] ?? "")}</text>

  <text x="540" y="1845" font-size="26" fill="#94a3b8" text-anchor="middle">+73.800 questões comentadas · Veterinária e Zootecnia</text>
</svg>`;
}

/** Card quadrado 1080×1080 para Feed do Instagram / Carrossel */
export function feedSvg(c: CardInput): string {
  const accent = c.accent ?? "#10b981";
  const titleLines = wrap(c.title, 24, 3);
  const titleSize = titleLines.length >= 3 ? 54 : 62;
  const titleY0 = 260;
  const titleSvg = titleLines.map((l, i) =>
    `<text x="72" y="${titleY0 + i * (titleSize + 14)}" font-size="${titleSize}" font-weight="700" fill="#f8fafc">${esc(l)}</text>`
  ).join("");
  const afterTitle = titleY0 + titleLines.length * (titleSize + 14);
  const subtitle = c.subtitle
    ? `<text x="72" y="${afterTitle + 18}" font-size="32" fill="#94a3b8">${esc(wrap(c.subtitle, 44, 1)[0] ?? "")}</text>` : "";

  const stats = (c.stats ?? []).slice(0, 4);
  const statsSvg = stats.map((s, i) => {
    const col = i % 2;
    const row = Math.floor(i / 2);
    const x = 72 + col * 480;
    const y = 560 + row * 150;
    return `<rect x="${x}" y="${y}" width="456" height="126" rx="20" fill="#0f1f1a" stroke="#1f3b32" stroke-width="2"/>
      <text x="${x + 28}" y="${y + 48}" font-size="22" fill="#94a3b8">${esc(s.label.toUpperCase())}</text>
      <text x="${x + 28}" y="${y + 96}" font-size="36" font-weight="700" fill="${accent}">${esc(wrap(s.value, 20, 1)[0] ?? "")}</text>`;
  }).join("");

  return `<svg width="1080" height="1080" viewBox="0 0 1080 1080" xmlns="http://www.w3.org/2000/svg" font-family="DejaVu Sans, sans-serif">
  <defs>
    <linearGradient id="bgF" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#04100c"/><stop offset="1" stop-color="#0b1220"/></linearGradient>
    <radialGradient id="glowF" cx="85%" cy="15%" r="60%"><stop offset="0" stop-color="${accent}" stop-opacity="0.3"/><stop offset="1" stop-color="#04100c" stop-opacity="0"/></radialGradient>
  </defs>
  <rect width="1080" height="1080" fill="url(#bgF)"/>
  <rect width="1080" height="1080" fill="url(#glowF)"/>

  <rect x="72" y="72" width="${Math.min(936, 40 + c.kicker.length * 16)}" height="54" rx="27" fill="${accent}" fill-opacity="0.16" stroke="${accent}" stroke-opacity="0.55" stroke-width="2"/>
  <text x="100" y="108" font-size="24" font-weight="700" fill="${accent}" letter-spacing="1">${esc(c.kicker.toUpperCase())}</text>
  <text x="1008" y="110" font-size="34" font-weight="700" fill="${accent}" text-anchor="end">VetRank</text>

  ${titleSvg}
  ${subtitle}
  ${statsSvg}

  <rect x="72" y="900" width="936" height="108" rx="22" fill="${accent}" fill-opacity="0.14" stroke="${accent}" stroke-opacity="0.5"/>
  <text x="104" y="948" font-size="26" font-weight="700" fill="#f8fafc">${esc(c.footer ?? "Prepare-se com simulados e questões comentadas")}</text>
  <text x="104" y="984" font-size="24" fill="${accent}">vetrank.com.br/concursos · Medicina Veterinária e Zootecnia</text>
</svg>`;
}

const cache = new Map<string, { at: number; png: Buffer }>();
const TTL = 30 * 60 * 1000;

export async function renderCard(key: string, input: CardInput): Promise<Buffer> {
  const hit = cache.get(key);
  if (hit && Date.now() - hit.at < TTL) return hit.png;
  const png = await sharp(Buffer.from(cardSvg(input))).png({ compressionLevel: 9 }).toBuffer();
  if (cache.size > 500) cache.delete(cache.keys().next().value!);
  cache.set(key, { at: Date.now(), png });
  return png;
}

export async function renderStoryCard(key: string, input: CardInput): Promise<Buffer> {
  const fullKey = `story:${key}`;
  const hit = cache.get(fullKey);
  if (hit && Date.now() - hit.at < TTL) return hit.png;
  const png = await sharp(Buffer.from(storySvg(input))).png({ compressionLevel: 9 }).toBuffer();
  if (cache.size > 500) cache.delete(cache.keys().next().value!);
  cache.set(fullKey, { at: Date.now(), png });
  return png;
}

export async function renderFeedCard(key: string, input: CardInput): Promise<Buffer> {
  const fullKey = `feed:${key}`;
  const hit = cache.get(fullKey);
  if (hit && Date.now() - hit.at < TTL) return hit.png;
  const png = await sharp(Buffer.from(feedSvg(input))).png({ compressionLevel: 9 }).toBuffer();
  if (cache.size > 500) cache.delete(cache.keys().next().value!);
  cache.set(fullKey, { at: Date.now(), png });
  return png;
}
