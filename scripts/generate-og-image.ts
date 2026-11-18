/**
 * Gera client/public/og-image.png (1200×630) para Open Graph / Twitter Card.
 * Uso: npx tsx scripts/generate-og-image.ts
 */

import sharp from "sharp";
import { writeFileSync } from "fs";
import { join, dirname } from "path";
import { fileURLToPath } from "url";

const __dirname = dirname(fileURLToPath(import.meta.url));
const OUT = join(__dirname, "../client/public/og-image.png");

// ─── SVG template ────────────────────────────────────────────────────────────
const svg = `
<svg width="1200" height="630" viewBox="0 0 1200 630" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <!-- Background gradient -->
    <linearGradient id="bgGrad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%"   stop-color="#060d1f"/>
      <stop offset="100%" stop-color="#0a1628"/>
    </linearGradient>

    <!-- Blue glow top-left -->
    <radialGradient id="glow1" cx="20%" cy="30%" r="55%">
      <stop offset="0%"   stop-color="#1d4ed8" stop-opacity="0.35"/>
      <stop offset="100%" stop-color="#060d1f" stop-opacity="0"/>
    </radialGradient>

    <!-- Cyan glow bottom-right -->
    <radialGradient id="glow2" cx="85%" cy="75%" r="45%">
      <stop offset="0%"   stop-color="#0891b2" stop-opacity="0.25"/>
      <stop offset="100%" stop-color="#060d1f" stop-opacity="0"/>
    </radialGradient>

    <!-- Title gradient -->
    <linearGradient id="titleGrad" x1="0%" y1="0%" x2="100%" y2="0%">
      <stop offset="0%"   stop-color="#60a5fa"/>
      <stop offset="100%" stop-color="#38bdf8"/>
    </linearGradient>

    <!-- Border gradient -->
    <linearGradient id="borderGrad" x1="0%" y1="0%" x2="100%" y2="0%">
      <stop offset="0%"   stop-color="#1d4ed8" stop-opacity="0.6"/>
      <stop offset="50%"  stop-color="#0891b2" stop-opacity="0.6"/>
      <stop offset="100%" stop-color="#1d4ed8" stop-opacity="0.6"/>
    </linearGradient>
  </defs>

  <!-- Background -->
  <rect width="1200" height="630" fill="url(#bgGrad)"/>
  <rect width="1200" height="630" fill="url(#glow1)"/>
  <rect width="1200" height="630" fill="url(#glow2)"/>

  <!-- Top border accent -->
  <rect x="0" y="0" width="1200" height="3" fill="url(#borderGrad)"/>

  <!-- Subtle grid dots pattern (decorative) -->
  ${Array.from({ length: 8 }, (_, row) =>
    Array.from({ length: 14 }, (_, col) =>
      `<circle cx="${col * 90 + 45}" cy="${row * 90 + 45}" r="1.5" fill="#ffffff" fill-opacity="0.04"/>`
    ).join("")
  ).join("")}

  <!-- Zap icon background circle -->
  <circle cx="140" cy="190" r="60" fill="#1d4ed8" fill-opacity="0.15" stroke="#3b82f6" stroke-opacity="0.3" stroke-width="1.5"/>

  <!-- Zap / lightning bolt icon (simplified) -->
  <path d="M130 162 L114 194 L136 194 L120 222 L156 186 L134 186 Z"
        fill="#60a5fa" fill-opacity="0.9"/>

  <!-- VetRank wordmark -->
  <text x="220" y="215"
        font-family="Georgia, 'Times New Roman', serif"
        font-size="88"
        font-weight="700"
        fill="url(#titleGrad)"
        letter-spacing="-1">VetRank</text>

  <!-- Divider line -->
  <rect x="100" y="258" width="600" height="2" fill="url(#borderGrad)" rx="1"/>

  <!-- Tagline -->
  <text x="100" y="318"
        font-family="Arial, Helvetica, sans-serif"
        font-size="34"
        font-weight="400"
        fill="#cbd5e1"
        letter-spacing="0.5">Prepare-se para Medicina Veterin&#xe1;ria</text>

  <!-- Exam type pills -->
  <!-- ENADE -->
  <rect x="100" y="350" width="130" height="38" rx="19" fill="#1d4ed8" fill-opacity="0.25" stroke="#3b82f6" stroke-opacity="0.5" stroke-width="1.5"/>
  <text x="165" y="374" font-family="Arial, Helvetica, sans-serif" font-size="18" font-weight="600" fill="#93c5fd" text-anchor="middle">ENADE</text>

  <!-- Residência -->
  <rect x="248" y="350" width="168" height="38" rx="19" fill="#0891b2" fill-opacity="0.2" stroke="#22d3ee" stroke-opacity="0.4" stroke-width="1.5"/>
  <text x="332" y="374" font-family="Arial, Helvetica, sans-serif" font-size="18" font-weight="600" fill="#67e8f9" text-anchor="middle">Resid&#xea;ncia</text>

  <!-- ENARE -->
  <rect x="434" y="350" width="130" height="38" rx="19" fill="#7c3aed" fill-opacity="0.2" stroke="#a78bfa" stroke-opacity="0.4" stroke-width="1.5"/>
  <text x="499" y="374" font-family="Arial, Helvetica, sans-serif" font-size="18" font-weight="600" fill="#c4b5fd" text-anchor="middle">ENARE</text>

  <!-- Aprimoramento -->
  <rect x="582" y="350" width="210" height="38" rx="19" fill="#047857" fill-opacity="0.2" stroke="#34d399" stroke-opacity="0.4" stroke-width="1.5"/>
  <text x="687" y="374" font-family="Arial, Helvetica, sans-serif" font-size="18" font-weight="600" fill="#6ee7b7" text-anchor="middle">Aprimoramento</text>

  <!-- Decorative right-side element: stacked answer cards -->
  <g transform="translate(870, 140)" opacity="0.85">
    <!-- Card 3 (back) -->
    <rect x="30" y="30" width="260" height="60" rx="10"
          fill="#0f1e3d" stroke="#1d4ed8" stroke-opacity="0.3" stroke-width="1"/>
    <!-- Card 2 (middle) -->
    <rect x="15" y="15" width="260" height="60" rx="10"
          fill="#0f1e3d" stroke="#1d4ed8" stroke-opacity="0.4" stroke-width="1"/>
    <!-- Card 1 (front) -->
    <rect x="0" y="0" width="260" height="60" rx="10"
          fill="#132040" stroke="#3b82f6" stroke-opacity="0.6" stroke-width="1.5"/>
    <!-- Option A circle -->
    <circle cx="30" cy="30" r="12" fill="#22c55e" fill-opacity="0.2" stroke="#22c55e" stroke-opacity="0.7" stroke-width="1.5"/>
    <text x="30" y="36" font-family="Arial, sans-serif" font-size="14" font-weight="700" fill="#4ade80" text-anchor="middle">A</text>
    <!-- Answer text placeholder -->
    <rect x="52" y="22" width="160" height="8" rx="4" fill="#cbd5e1" fill-opacity="0.15"/>
    <rect x="52" y="36" width="120" height="6" rx="3" fill="#cbd5e1" fill-opacity="0.1"/>
    <!-- Checkmark -->
    <path d="M228 24 L236 32 L250 18" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
  </g>

  <!-- Trophy icon area (bottom right decorative) -->
  <g transform="translate(1000, 340)" opacity="0.5">
    <circle cx="60" cy="60" r="55" fill="#f59e0b" fill-opacity="0.08" stroke="#f59e0b" stroke-opacity="0.2" stroke-width="1.5"/>
    <!-- Trophy simplified -->
    <path d="M40 30 L80 30 L80 65 Q80 85 60 90 Q40 85 40 65 Z" fill="#f59e0b" fill-opacity="0.3" stroke="#fbbf24" stroke-opacity="0.5" stroke-width="1.5"/>
    <rect x="53" y="90" width="14" height="18" fill="#f59e0b" fill-opacity="0.3"/>
    <rect x="42" y="108" width="36" height="6" rx="3" fill="#fbbf24" fill-opacity="0.4"/>
    <path d="M40 42 Q22 42 22 58 Q22 72 40 72" fill="none" stroke="#fbbf24" stroke-opacity="0.4" stroke-width="2"/>
    <path d="M80 42 Q98 42 98 58 Q98 72 80 72" fill="none" stroke="#fbbf24" stroke-opacity="0.4" stroke-width="2"/>
  </g>

  <!-- Bottom URL -->
  <text x="100" y="570"
        font-family="Arial, Helvetica, sans-serif"
        font-size="22"
        fill="#475569"
        letter-spacing="1">vetrank.com.br</text>

  <!-- Bottom right: "Trial grátis 7 dias" badge -->
  <rect x="900" y="548" width="250" height="42" rx="21"
        fill="#1d4ed8" fill-opacity="0.2" stroke="#3b82f6" stroke-opacity="0.5" stroke-width="1.5"/>
  <text x="1025" y="575"
        font-family="Arial, Helvetica, sans-serif"
        font-size="18" font-weight="600"
        fill="#93c5fd" text-anchor="middle">Trial gr&#xe1;tis por 7 dias</text>
</svg>
`.trim();

// ─── Convert to PNG ───────────────────────────────────────────────────────────
async function main() {
  console.log("Gerando og-image.png...");
  const png = await sharp(Buffer.from(svg))
    .png({ compressionLevel: 9 })
    .toBuffer();

  writeFileSync(OUT, png);
  console.log(`✓ Salvo em ${OUT} (${(png.length / 1024).toFixed(1)} KB)`);
}

main().catch(err => {
  console.error("Erro ao gerar imagem:", err);
  process.exit(1);
});
