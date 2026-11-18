/**
 * fix_orphaned_questions.mjs
 *
 * Fixes questions whose disciplineId points to a deleted discipline (orphaned).
 * Root cause: reseed_curriculum.mjs set all questions to disciplineId=1 before
 * deleting disciplines, but the follow-up UPDATE used "WHERE disciplineId IS NULL"
 * instead of "WHERE disciplineId NOT IN (SELECT id FROM disciplines)".
 *
 * This script also maps old discipline slugs from the JSON files to the new
 * curriculum discipline slugs, then re-inserts questions that were skipped by
 * seed_topup.mjs because the old slugs were not found in the DB.
 *
 * Safe to run multiple times.
 */

import mysql from "mysql2/promise";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";
import * as dotenv from "dotenv";
dotenv.config();

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const DATA_DIR = path.join(__dirname, "../data");

// Mapping from old discipline slugs (used in JSON files) to new curriculum slugs
const OLD_TO_NEW_SLUG = {
  "pharmacology":   "patobiologia",       // Farmacologia → Patobiologia
  "clinics":        "clinica-cirurgia",   // Clínica Veterinária → Clínica, Cirurgia
  "herpetology":    "etica-humanidades",  // Herpetologia → Animais Selvagens/Exóticos
  "ornithology":    "etica-humanidades",  // Ornitologia → Animais Selvagens/Exóticos
  "anesthesiology": "clinica-cirurgia",   // Anestesiologia → Clínica, Cirurgia
  "small-mammals":  "etica-humanidades",  // Pequenos Mamíferos → Animais Exóticos
};

// Default slug if no mapping found
const DEFAULT_SLUG = "clinica-cirurgia";

async function main() {
  const conn = await mysql.createConnection(process.env.DATABASE_URL);
  console.log("🔧 VetRank — Fix Orphaned Question Disciplines");
  console.log("=".repeat(55));

  // ── 1. Load current discipline IDs from DB ─────────────────────────────────
  const [disciplines] = await conn.execute("SELECT id, slug, namePt FROM disciplines WHERE active = 1");
  if (disciplines.length === 0) {
    console.error("❌ No disciplines found in DB! Run reseed_curriculum.mjs first.");
    await conn.end();
    process.exit(1);
  }

  const disciplineMap = new Map(disciplines.map(d => [d.slug, d.id]));
  console.log(`\n📚 Found ${disciplines.length} disciplines in DB:`);
  for (const d of disciplines) {
    console.log(`   [${d.id}] ${d.namePt} (slug: ${d.slug})`);
  }

  const defaultId = disciplineMap.get(DEFAULT_SLUG);
  if (!defaultId) {
    console.error(`❌ Default discipline "${DEFAULT_SLUG}" not found in DB!`);
    await conn.end();
    process.exit(1);
  }

  // ── 2. Find all valid discipline IDs ──────────────────────────────────────
  const validIds = disciplines.map(d => d.id);
  const placeholders = validIds.map(() => "?").join(",");

  // ── 3. Count orphaned questions ────────────────────────────────────────────
  const [orphanCount] = await conn.execute(
    `SELECT COUNT(*) as cnt FROM questions WHERE disciplineId NOT IN (${placeholders}) OR disciplineId IS NULL`,
    validIds
  );
  console.log(`\n⚠️  Orphaned questions (invalid disciplineId): ${orphanCount[0].cnt}`);

  if (orphanCount[0].cnt > 0) {
    const [updateResult] = await conn.execute(
      `UPDATE questions SET disciplineId = ?, subjectId = NULL
       WHERE disciplineId NOT IN (${placeholders}) OR disciplineId IS NULL`,
      [defaultId, ...validIds]
    );
    console.log(`✅ Reassigned ${updateResult.affectedRows} orphaned questions → "${DEFAULT_SLUG}" (id=${defaultId})`);
  }

  // ── 4. Re-seed questions from JSON with correct new discipline IDs ─────────
  const allFile = path.join(DATA_DIR, "questions_all.json");
  if (!fs.existsSync(allFile)) {
    console.log("\nℹ️  questions_all.json not found — skipping JSON re-seed step.");
  } else {
    const allQuestions = JSON.parse(fs.readFileSync(allFile, "utf8"));
    console.log(`\n📦 questions_all.json: ${allQuestions.length} questions`);

    // Load existing keys to skip duplicates
    const [existingRows] = await conn.execute("SELECT LEFT(textPt, 100) as qkey FROM questions");
    const existingKeys = new Set(existingRows.map(r => r.qkey));

    const newQuestions = allQuestions.filter(q => {
      const key = (q.textPt || "").slice(0, 100);
      return !existingKeys.has(key);
    });

    console.log(`📝 New questions to insert from JSON: ${newQuestions.length}`);

    let inserted = 0;
    let skipped = 0;

    for (const q of newQuestions) {
      if (!q.textPt || !q.options || !q.correctOption) { skipped++; continue; }

      // Resolve discipline slug: try old→new mapping, then direct lookup
      const rawSlug = q.disciplineSlug || "";
      const resolvedSlug = OLD_TO_NEW_SLUG[rawSlug] || rawSlug || DEFAULT_SLUG;
      const disciplineId = disciplineMap.get(resolvedSlug) ?? defaultId;

      try {
        await conn.execute(
          `INSERT INTO questions
             (disciplineId, subjectId, difficulty, year, textPt, textEn, options,
              correctOption, explanationPt, explanationEn, isPremium, active,
              questionType, status, createdAt, updatedAt)
           VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'multiple_choice', 'approved', NOW(), NOW())`,
          [
            disciplineId,
            q.difficulty || "medium",
            q.year || null,
            q.textPt,
            q.textEn || null,
            JSON.stringify(q.options),
            q.correctOption,
            q.explanationPt || null,
            q.explanationEn || null,
            q.isPremium ? 1 : 0,
          ]
        );
        inserted++;
      } catch (e) {
        skipped++;
      }

      if ((inserted + skipped) % 500 === 0 && inserted + skipped > 0) {
        process.stdout.write(`  Progress: ${inserted + skipped}/${newQuestions.length}\n`);
      }
    }

    console.log(`\n✅ JSON re-seed complete: ${inserted} inserted, ${skipped} skipped`);
  }

  // ── 5. Final summary ───────────────────────────────────────────────────────
  const [finalCount] = await conn.execute("SELECT COUNT(*) as cnt FROM questions WHERE active = 1");
  const [perDisc] = await conn.execute(`
    SELECT d.namePt, COUNT(q.id) as total
    FROM disciplines d
    LEFT JOIN questions q ON q.disciplineId = d.id AND q.active = 1
    GROUP BY d.id, d.namePt
    ORDER BY d.namePt
  `);

  console.log(`\n📊 Final state:`);
  console.log(`   Total active questions: ${finalCount[0].cnt}`);
  console.log(`   Questions per Grande Área:`);
  for (const r of perDisc) {
    console.log(`     ${r.namePt}: ${r.total}`);
  }

  // Check remaining orphans
  const [remaining] = await conn.execute(
    `SELECT COUNT(*) as cnt FROM questions WHERE disciplineId NOT IN (${placeholders}) OR disciplineId IS NULL`,
    validIds
  );
  if (remaining[0].cnt > 0) {
    console.warn(`\n⚠️  Still ${remaining[0].cnt} orphaned questions — investigate manually.`);
  } else {
    console.log(`\n✅ No orphaned questions remaining.`);
  }

  await conn.end();
  console.log("\nDone!");
}

main().catch(e => {
  console.error("Fix failed:", e.message);
  process.exit(1);
});
