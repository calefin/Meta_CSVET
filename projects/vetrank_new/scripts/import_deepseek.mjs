import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";
import postgres from "postgres";
import * as dotenv from "dotenv";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
dotenv.config({ path: path.join(__dirname, "../.env.local") });

const dbUrl = process.env.DATABASE_URL;
const INPUT_DIR = path.join(__dirname, "../data/deepseek_questions");

// Official curriculum mapping to assist database matching
const OFFICIAL_DISCIPLINES = {
  30001: "Ciências Biológicas e Ciclo Básico",
  30002: "Patobiologia e Agentes Agressores",
  30003: "Clínica, Cirurgia e Reprodução Animal",
  30004: "Medicina Preventiva, Saúde Pública e Inspeção",
  30005: "Zootecnia e Produção Animal",
  30006: "Ética, Humanidades e Áreas Complementares"
};

const OFFICIAL_SUBJECTS = {
  30001: "Anatomia Veterinária I e II (Sistêmica e Topográfica)",
  30002: "Biologia Celular e Molecular",
  30003: "Histologia Veterinária (Geral e Especial)",
  30004: "Embriologia Veterinária",
  30005: "Bioquímica Veterinária e Metabólica",
  30006: "Biofísica Veterinária",
  30007: "Fisiologia Veterinária I e II",
  30008: "Genética e Evolução Animal",
  30009: "Bioestatística e Metodologia Científica",
  30010: "Microbiologia Veterinária (Bacteriologia e Virologia)",
  30011: "Imunologia Veterinária",
  30012: "Parasitologia Veterinária",
  30013: "Patologia Geral e Técnicas de Necropsia",
  30014: "Patologia Especial/Sistêmica Veterinária",
  30015: "Farmacologia e Terapêutica Veterinária",
  30016: "Toxicologia Veterinária",
  30017: "Semiologia Veterinária (Propedêutica)",
  30018: "Patologia Clínica Veterinária (Diagnóstico Laboratorial)",
  30019: "Diagnóstico por Imagem (Radiologia e Ultrassonografia)",
  30020: "Técnica Cirúrgica Veterinária",
  30021: "Anestesiologia Veterinária",
  30022: "Clínica Médica e Cirúrgica de Pequenos Animais (Cães e Gatos)",
  30023: "Clínica Médica e Cirúrgica de Ruminantes (Bovinos, Ovinos e Caprinos)",
  30024: "Clínica Médica e Cirúrgica de Equídeos",
  30025: "Fisiopatologia da Reprodução (Macho e Fêmea)",
  30026: "Obstetrícia Veterinária e Biotecnologia da Reprodução",
  30027: "Epidemiologia Veterinária",
  30028: "Zoonoses e Saúde Pública",
  30029: "Saneamento Ambiental e Planejamento em Saúde Animal",
  30030: "Inspeção de Produtos de Origem Animal (Carnes, Leite, Ovos, Pescado e Mel)",
  30031: "Tecnologia de Produtos de Origem Animal",
  30032: "Higiene de Alimentos",
  30033: "Introdução à Zootecnia",
  30034: "Nutrição e Alimentação Animal",
  30035: "Agrostologia e Forragicultura (Pastagens)",
  30036: "Melhoramento Genético Animal",
  30037: "Bovinocultura de Corte e de Leite",
  30038: "Avicultura, Suinocultura e Equideocultura",
  30039: "Aquicultura e Piscicultura",
  30040: "Deontologia, Ética e Bioética na Medicina Veterinária",
  30041: "Bem-Estar Animal e Etologia",
  30042: "Medicina Veterinária Legal (Perícia)",
  30043: "Administração e Planejamento Rural",
  30044: "Extensão Rural e Sociologia",
  30045: "Medicina de Animais Selvagens e Pets Exóticos"
};

function normalizeString(str) {
  if (!str) return "";
  return str.toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-z0-9]/g, "");
}

async function main() {
  if (!dbUrl) {
    console.error("❌ ERROR: DATABASE_URL not found in environment or .env.local");
    process.exit(1);
  }

  console.log("Connecting to PostgreSQL database...");
  const sql = postgres(dbUrl);

  try {
    // 1. Fetch existing curriculum from DB for matching
    const dbDisciplines = await sql`SELECT id, "namePt" FROM disciplines WHERE active = true`;
    const dbSubjects = await sql`SELECT id, "disciplineId", "namePt" FROM subjects WHERE active = true`;

    console.log(`Curriculum fetched: ${dbDisciplines.length} disciplines, ${dbSubjects.length} subjects.`);

    // 2. Build maps for name-matching
    const discMap = new Map(); // normalizedName -> id
    dbDisciplines.forEach(d => {
      discMap.set(normalizeString(d.namePt), d.id);
    });

    const subjMap = new Map(); // normalizedName -> id
    dbSubjects.forEach(s => {
      subjMap.set(normalizeString(s.namePt), s.id);
    });

    // Match helper
    function getMatchedIds(offDiscId, offSubjId) {
      // First try direct ID matching in case DB has IDs like 30001, etc.
      const hasDirectDisc = dbDisciplines.some(d => d.id === offDiscId);
      const hasDirectSubj = dbSubjects.some(s => s.id === offSubjId);

      if (hasDirectDisc && hasDirectSubj) {
        return { discId: offDiscId, subjId: offSubjId };
      }

      // Otherwise, match by name similarity
      const offDiscName = OFFICIAL_DISCIPLINES[offDiscId];
      const offSubjName = OFFICIAL_SUBJECTS[offSubjId];

      if (!offDiscName || !offSubjName) {
        throw new Error(`Invalid official IDs: discId=${offDiscId}, subjId=${offSubjId}`);
      }

      const discId = discMap.get(normalizeString(offDiscName));
      const subjId = subjMap.get(normalizeString(offSubjName));

      if (!discId) {
        throw new Error(`Could not match discipline by name: "${offDiscName}" (official ID: ${offDiscId})`);
      }
      if (!subjId) {
        throw new Error(`Could not match subject by name: "${offSubjName}" (official ID: ${offSubjId})`);
      }

      return { discId, subjId };
    }

    if (!fs.existsSync(INPUT_DIR)) {
      console.log(`Directory ${INPUT_DIR} does not exist. Nothing to import.`);
      return;
    }

    const folders = fs.readdirSync(INPUT_DIR).filter(f => f.startsWith("subject_"));
    console.log(`Found ${folders.length} subject folders in generated data.`);

    let totalFiles = 0;
    let totalImported = 0;
    let totalErrors = 0;
    let totalSkipped = 0;

    for (const folder of folders) {
      const folderPath = path.join(INPUT_DIR, folder);
      const files = fs.readdirSync(folderPath).filter(f => f.endsWith(".json"));

      console.log(`\n📂 Processing ${folder} (${files.length} JSON files)...`);

      for (const file of files) {
        const filePath = path.join(folderPath, file);
        totalFiles++;

        let questions;
        try {
          const raw = fs.readFileSync(filePath, "utf8");
          questions = JSON.parse(raw);
          if (!Array.isArray(questions)) {
            questions = [questions];
          }
        } catch (err) {
          console.error(`  ❌ Error reading/parsing file ${file}: ${err.message}`);
          totalErrors++;
          continue;
        }

        let fileImported = 0;

        for (const q of questions) {
          try {
            // Validation: correctOption
            const validOptions = ["A", "B", "C", "D", "E"];
            if (!validOptions.includes(q.correctOption)) {
              console.warn(`    ⚠️ Skipping question: invalid correctOption "${q.correctOption}"`);
              totalSkipped++;
              continue;
            }

            // Validation: options array length
            if (!q.options || q.options.length !== 5) {
              console.warn(`    ⚠️ Skipping question: options length is ${q.options?.length || 0} instead of 5`);
              totalSkipped++;
              continue;
            }

            // Map official IDs to DB IDs
            const { discId, subjId } = getMatchedIds(q.disciplineId, q.subjectId);

            // Normalize options structure
            const normalizedOptions = q.options.map(o => ({
              id: o.id,
              textPt: o.textPt || o.text_pt || ""
            }));

            // Assertion I/II fields only belong to assertion_reason (M5) questions. The LLM
            // sometimes includes them for other models (e.g. clinical_case) by mistake, which
            // makes the renderer misdetect the question as assertion_reason — force-null them
            // here for anything that isn't actually assertion_reason.
            const isAssertionReason = q.questionType === "assertion_reason";

            // Insert question into database using raw query for Drizzle schema compatibility
            await sql`
              INSERT INTO questions (
                "disciplineId", "subjectId", difficulty, "questionType", "questionModel",
                author, "textPt", options, "correctOption", "explanationPt",
                assertion1, assertion2, "a1Verdadeira", "a2Verdadeira", "relacaoCausal",
                "grupoId", "posicaoBloco", status, "isValidated", active, "isPremium",
                "createdAt", "updatedAt"
              ) VALUES (
                ${discId}, ${subjId}, ${q.difficulty || "medium"}, ${q.questionType || "multiple_choice"}, ${q.questionModel || "standard"},
                ${q.author || "VetRank"}, ${q.textPt}, ${JSON.stringify(normalizedOptions)}, ${q.correctOption}, ${q.explanationPt || null},
                ${isAssertionReason ? (q.assertion1 || null) : null}, ${isAssertionReason ? (q.assertion2 || null) : null}, ${isAssertionReason && q.a1Verdadeira !== undefined ? q.a1Verdadeira : null}, ${isAssertionReason && q.a2Verdadeira !== undefined ? q.a2Verdadeira : null}, ${isAssertionReason && q.relacaoCausal !== undefined ? q.relacaoCausal : null},
                ${q.grupoId || null}, ${q.posicaoBloco !== undefined ? q.posicaoBloco : null}, 'approved', true, true, true,
                NOW(), NOW()
              )
            `;

            fileImported++;
            totalImported++;
          } catch (err) {
            console.error(`    ❌ DB Insert Error in file ${file}: ${err.message}`);
            totalErrors++;
          }
        }
        if (fileImported > 0) {
          console.log(`  ✔️  Imported ${fileImported}/${questions.length} from ${file}`);
        }
      }
    }

    console.log("\n==============================");
    console.log("🎉 IMPORT COMPLETED!");
    console.log(`Total Files Processed: ${totalFiles}`);
    console.log(`Questions Seeding Count: ${totalImported}`);
    console.log(`Skipped Questions: ${totalSkipped}`);
    console.log(`Errors Encountered: ${totalErrors}`);
    console.log("==============================");

  } catch (error) {
    console.error("Fatal error during import:", error);
  } finally {
    await sql.end();
  }
}

main().catch(err => {
  console.error("Unhandled fatal exception:", err);
});
