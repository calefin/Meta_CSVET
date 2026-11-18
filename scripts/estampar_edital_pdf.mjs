#!/usr/bin/env node
/**
 * Anexa um PDF de edital baixado localmente a um concurso do Radar VetRank e/ou
 * gera na hora o PDF final com Capa de Propaganda VetRank + Resumo + Checklist + Edital.
 *
 * Quando salvo em data/editais/<slug>.pdf, o endpoint /api/concursos/<slug>/guia.pdf
 * entrega automaticamente:
 *   Página 1: Capa de Propaganda VetRank + Ficha Técnica + Resumo
 *   Página 2: Checklist Verticalizado de Estudos
 *   Páginas seguintes: O PDF do edital oficial com rodapé VetRank em cada página!
 *
 * Uso:
 *   pnpm concursos:pdf                              -> Lista os slugs disponíveis no Radar
 *   pnpm concursos:pdf <caminho-do-pdf> <slug>      -> Anexa o PDF oficial ao concurso
 */
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const [pdfInput, slug] = process.argv.slice(2);
const seedPath = path.resolve(__dirname, "../data/concursos_seed.json");

if (!pdfInput || !slug) {
  console.log("=================================================================");
  console.log("📄 VetRank — Gerador de Edital com Capa Promocional + Resumo");
  console.log("=================================================================");
  console.log("Como anexar um PDF de edital baixado da banca ao concurso:");
  console.log("  pnpm concursos:pdf <caminho-do-edital.pdf> <slug-do-concurso>\n");
  if (fs.existsSync(seedPath)) {
    const lista = JSON.parse(fs.readFileSync(seedPath, "utf-8"));
    console.log(`Concursos ativos na semente (${lista.length} editais):`);
    for (const c of lista.slice(0, 12)) {
      const temPdf = fs.existsSync(path.resolve(__dirname, `../data/editais/${c.slug}.pdf`));
      console.log(`  • ${c.slug}  (${c.orgao}${c.uf ? "/" + c.uf : ""}) ${temPdf ? "[PDF Oficial Anexado ✅]" : "[Guia + Resumo Automático 📄]"}`);
    }
    if (lista.length > 12) console.log(`  ... e mais ${lista.length - 12} concursos em data/concursos_seed.json`);
  }
  process.exit(0);
}

const srcPath = path.resolve(process.cwd(), pdfInput);
if (!fs.existsSync(srcPath)) {
  console.error(`❌ Arquivo não encontrado: ${srcPath}`);
  process.exit(1);
}

const outDir = path.resolve(__dirname, "../data/editais");
fs.mkdirSync(outDir, { recursive: true });
const destPath = path.join(outDir, `${slug}.pdf`);
fs.copyFileSync(srcPath, destPath);
console.log(`✅ Edital vinculado com sucesso em: data/editais/${slug}.pdf`);
console.log(`📄 Ao acessar /api/concursos/${slug}/guia.pdf no site, o aluno receberá o PDF com a Capa de Propaganda VetRank + Resumo + Checklist + Edital completo!`);
