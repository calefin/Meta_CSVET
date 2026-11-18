import { execSync } from "child_process";
import { promises as fs } from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

async function testImport() {
  console.log("🧪 Teste de importação local\n");
  
  const testFile = "/home/ubuntu/upload/P2AFMATFisiologia.docx";
  const tempPath = `/tmp/${Date.now()}_test.docx`;
  
  // Copiar arquivo para temp
  console.log("1️⃣  Copiando arquivo para temp...");
  const fileBuffer = await fs.readFile(testFile);
  await fs.writeFile(tempPath, fileBuffer);
  console.log(`   ✅ Arquivo copiado: ${tempPath}\n`);
  
  // Executar parser
  console.log("2️⃣  Executando parser...");
  const pythonScript = path.join(__dirname, "server/question-parser.py");
  try {
    const output = execSync(`python3 "${pythonScript}" "${tempPath}" "docx"`, {
      encoding: "utf-8",
      maxBuffer: 10 * 1024 * 1024,
    });
    const questions = JSON.parse(output);
    console.log(`   ✅ Parser retornou ${questions.length} questões\n`);
    
    console.log("3️⃣  Questões:");
    questions.forEach((q, i) => {
      console.log(`   ${i+1}. [${q.type}] ${q.statement.substring(0, 60)}...`);
    });
    
  } catch (error) {
    console.error("   ❌ Erro:", error.message);
  }
  
  // Limpar
  await fs.unlink(tempPath).catch(() => {});
}

testImport();
