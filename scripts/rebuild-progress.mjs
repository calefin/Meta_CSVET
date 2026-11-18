/**
 * rebuild-progress.mjs
 *
 * Reconstrói o scripts/output/progress.json consultando o banco de dados.
 * Execute ANTES de rodar generate-questions.ts para evitar questões duplicadas.
 *
 * Como funciona:
 *   - Conta quantas questões existem no banco para cada subjectId do currículo
 *   - Subjects com >= MIN_QUESTIONS_TO_SKIP são marcados como completos (todas as
 *     combinações subjectId_Mx_round entram no array "done")
 *   - Subjects com menos questões ficam de fora do "done" e serão re-gerados
 *
 * Uso:
 *   node scripts/rebuild-progress.mjs
 *
 * Variáveis de ambiente necessárias (mesmo .env do servidor):
 *   DATABASE_URL=mysql://user:pass@host:3306/db
 */

import mysql from "mysql2/promise";
import * as dotenv from "dotenv";
import { writeFileSync, mkdirSync } from "fs";
import { join, dirname } from "path";
import { fileURLToPath } from "url";

// Carrega .env.production da raiz do repo (VPS) ou .env local (dev)
dotenv.config({ path: join(__dirname, "../.env.production") });
dotenv.config();

const __dirname = dirname(fileURLToPath(import.meta.url));
const OUTPUT_DIR = join(__dirname, "output");
const PROGRESS_FILE = join(OUTPUT_DIR, "progress.json");

// ─── Mesmos parâmetros do generate-questions.ts ───────────────────────────────
const MODEL_KEYS = ["M1", "M2", "M3", "M4", "M5", "M6", "M7", "M8", "M9"];
const ROUNDS = 3;

// Subjects com >= este número de questões são considerados concluídos
// (ajuste conforme necessário — padrão: 80 q = cobertura mínima razoável)
const MIN_QUESTIONS_TO_SKIP = Number(process.env.MIN_Q ?? "80");

// ─── Currículo (mesmos IDs do generate-questions.ts) ─────────────────────────
const SUBJECT_IDS = [
  30001, 30002, 30003, 30004, 30005, 30006, 30007, 30008, 30009, // Biológicas
  30010, 30011, 30012, 30013, 30014, 30015, 30016,               // Patobiologia
  30017, 30018, 30019, 30020, 30021, 30022, 30023, 30024,        // Clínica/Cirurgia
  30025, 30026,                                                   // Reprodução
  30027, 30028, 30029, 30030, 30031, 30032,                      // Med. Preventiva
  30033, 30034, 30035, 30036, 30037, 30038, 30039,               // Zootecnia
  30040, 30041, 30042, 30043, 30044, 30045,                      // Ética
];

const SUBJECT_NAMES = {
  30001: "Anatomia Veterinária", 30002: "Biologia Celular e Molecular",
  30003: "Histologia Veterinária", 30004: "Embriologia Veterinária",
  30005: "Bioquímica Veterinária", 30006: "Biofísica Veterinária",
  30007: "Fisiologia Veterinária", 30008: "Genética e Evolução Animal",
  30009: "Bioestatística", 30010: "Microbiologia Veterinária",
  30011: "Imunologia Veterinária", 30012: "Parasitologia Veterinária",
  30013: "Patologia Geral", 30014: "Patologia Especial/Sistêmica",
  30015: "Farmacologia e Terapêutica", 30016: "Toxicologia Veterinária",
  30017: "Semiologia Veterinária", 30018: "Patologia Clínica (Lab.)",
  30019: "Diagnóstico por Imagem", 30020: "Técnica Cirúrgica",
  30021: "Anestesiologia Veterinária", 30022: "Clínica Pequenos Animais",
  30023: "Clínica Ruminantes", 30024: "Clínica Equídeos",
  30025: "Fisiopatologia Reprodução", 30026: "Obstetrícia e Biotecnologia",
  30027: "Epidemiologia Veterinária", 30028: "Zoonoses e Saúde Pública",
  30029: "Saneamento Ambiental", 30030: "Inspeção POA",
  30031: "Tecnologia POA", 30032: "Higiene de Alimentos",
  30033: "Introdução à Zootecnia", 30034: "Nutrição e Alimentação",
  30035: "Forragicultura", 30036: "Melhoramento Genético",
  30037: "Bovinocultura", 30038: "Avicultura/Suinocultura",
  30039: "Aquicultura e Piscicultura", 30040: "Deontologia e Ética",
  30041: "Bem-Estar Animal", 30042: "Medicina Veterinária Legal",
  30043: "Administração Rural", 30044: "Extensão Rural",
  30045: "Medicina de Animais Selvagens",
};

// ─── Main ─────────────────────────────────────────────────────────────────────

const conn = await mysql.createConnection(process.env.DATABASE_URL);

console.log(`\n🔍 Consultando banco de dados...`);
console.log(`   Limite para marcar como concluído: >= ${MIN_QUESTIONS_TO_SKIP} questões por assunto\n`);

const [rows] = await conn.execute(
  `SELECT subjectId, COUNT(*) as total
   FROM questions
   WHERE subjectId IN (${SUBJECT_IDS.join(",")}) AND active = 1
   GROUP BY subjectId`
);

await conn.end();

/** @type {Map<number, number>} */
const counts = new Map(rows.map(r => [Number(r.subjectId), Number(r.total)]));

const done = [];
let skipped = 0;
let willGenerate = 0;
let totalExisting = 0;

console.log("─".repeat(70));
console.log(
  "SubjectId".padEnd(10) +
  "Questões".padEnd(12) +
  "Status".padEnd(15) +
  "Nome"
);
console.log("─".repeat(70));

for (const sid of SUBJECT_IDS) {
  const count = counts.get(sid) ?? 0;
  totalExisting += count;
  const name = SUBJECT_NAMES[sid] ?? `Subject ${sid}`;

  if (count >= MIN_QUESTIONS_TO_SKIP) {
    // Marca todas as combinações deste subject como concluídas
    for (const mk of MODEL_KEYS) {
      for (let r = 0; r < ROUNDS; r++) {
        done.push(`${sid}_${mk}_${r}`);
      }
    }
    skipped++;
    console.log(
      String(sid).padEnd(10) +
      String(count).padEnd(12) +
      "✅ PULAR".padEnd(15) +
      name
    );
  } else {
    willGenerate++;
    console.log(
      String(sid).padEnd(10) +
      String(count).padEnd(12) +
      `⚡ GERAR (${count})`.padEnd(15) +
      name
    );
  }
}

console.log("─".repeat(70));
console.log(`\n📊 Resumo:`);
console.log(`   Questões no banco: ${totalExisting}`);
console.log(`   Assuntos a pular:  ${skipped}/${SUBJECT_IDS.length} (já têm >= ${MIN_QUESTIONS_TO_SKIP} questões)`);
console.log(`   Assuntos a gerar:  ${willGenerate}/${SUBJECT_IDS.length}`);
console.log(`   Chaves marcadas como done: ${done.length}/${SUBJECT_IDS.length * MODEL_KEYS.length * ROUNDS}`);

const progress = { done, totalGenerated: totalExisting, totalCostUsd: 0 };
mkdirSync(OUTPUT_DIR, { recursive: true });
writeFileSync(PROGRESS_FILE, JSON.stringify(progress, null, 2));

console.log(`\n✅ progress.json salvo em: ${PROGRESS_FILE}`);
if (willGenerate > 0) {
  console.log(`\n▶  Agora rode: npx tsx scripts/generate-questions.ts`);
  console.log(`   Apenas os ${willGenerate} assuntos acima marcados com ⚡ serão gerados.\n`);
} else {
  console.log(`\n🎉 Todos os assuntos já têm questões suficientes! Nada a gerar.\n`);
}
