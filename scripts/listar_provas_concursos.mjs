#!/usr/bin/env node
/**
 * Lista as provas e gabaritos mais recentes (2024–2026) de Medicina Veterinária
 * e Zootecnia disponíveis para download, ordenadas das mais novas para as mais antigas.
 *
 * Uso:
 *   pnpm provas:listar
 */
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const UA = "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36";

const URLS = [
  { url: "https://www.pciconcursos.com.br/provas/medico-veterinario", area: "Medicina Veterinária" },
  { url: "https://www.pciconcursos.com.br/provas/zootecnista", area: "Zootecnia" },
];

const decode = (s) => s
  .replace(/&amp;/g, "&").replace(/&quot;/g, '"').replace(/&#39;/g, "'")
  .replace(/&lt;/g, "<").replace(/&gt;/g, ">").replace(/&nbsp;/g, " ");

async function main() {
  console.log("🔎 Buscando catálogo de provas reais de Medicina Veterinária e Zootecnia...");
  const provas = [];
  const seen = new Set();

  for (const { url, area } of URLS) {
    const r = await fetch(url, { headers: { "User-Agent": UA } });
    const html = await r.text();
    const re = /<tr class="lk_link[^"]*"[^>]*data-url="([^"]+)"[\s\S]*?<td class="[ce]a">([\s\S]*?)<\/td>\s*<td class="[ce]b">(\d{4})<\/td>\s*<td class="[ce]c">([\s\S]*?)<\/td>\s*<td class="[ce]d">([\s\S]*?)<\/td>/g;
    let m;
    while ((m = re.exec(html))) {
      const link = m[1].trim();
      if (seen.has(link)) continue;
      seen.add(link);
      const cargo = decode(m[2].replace(/<[^>]+>/g, "")).trim();
      const ano = Number(m[3]);
      const orgao = decode(m[4].replace(/<[^>]+>/g, "")).trim();
      const banca = decode(m[5].replace(/<[^>]+>/g, "")).trim();
      provas.push({ area, cargo, ano, orgao, banca, link });
    }
  }

  provas.sort((a, b) => b.ano - a.ano || a.orgao.localeCompare(b.orgao));
  const recentes = provas.filter(p => p.ano >= 2024);

  const outPath = path.resolve(__dirname, "../data/catalogo_provas_recentes.json");
  fs.writeFileSync(outPath, JSON.stringify(recentes, null, 2));

  console.log(`✅ ${provas.length} provas encontradas no total (${recentes.length} de 2024 a 2026).`);
  console.log(`📁 Catálogo salvo em: data/catalogo_provas_recentes.json\n`);
  console.log("Top 25 Provas mais recentes (2025–2026) para baixar e extrair questões:");
  for (const p of recentes.slice(0, 25)) {
    console.log(`  [${p.ano}] ${p.orgao} (${p.banca}) — ${p.cargo}`);
    console.log(`         Link: ${p.link}`);
  }
  console.log("\n💡 Depois de baixar o PDF da prova e do gabarito, extraia as questões com custo R$ 0,00:");
  console.log("   pnpm provas:extrair <caminho-prova.pdf> <caminho-gabarito.pdf> --banca=\"FGV\" --orgao=\"MP-ES\" --ano=2026");
}

main().catch(console.error);
