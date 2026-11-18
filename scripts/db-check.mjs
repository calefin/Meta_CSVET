import postgres from "postgres";
import * as dotenv from "dotenv";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
dotenv.config({ path: path.join(__dirname, "../.env.local") });

const dbUrl = process.env.DATABASE_URL;
if (!dbUrl) {
  console.error("DATABASE_URL not found in .env.local");
  process.exit(1);
}

console.log("Connecting to database...");
const sql = postgres(dbUrl);

try {
  const disciplinesList = await sql`SELECT id, "namePt", "nameEn", slug FROM disciplines ORDER BY id`;
  console.log("\n--- DISCIPLINES ---");
  console.log(JSON.stringify(disciplinesList, null, 2));

  const subjectsList = await sql`SELECT id, "disciplineId", "namePt", "nameEn", slug FROM subjects ORDER BY id`;
  console.log("\n--- SUBJECTS ---");
  console.log(JSON.stringify(subjectsList, null, 2));

  const countRes = await sql`SELECT COUNT(*) as total FROM questions`;
  console.log("\nTotal questions in DB:", countRes[0].total);

} catch (error) {
  console.error("Error running query:", error);
} finally {
  await sql.end();
}
