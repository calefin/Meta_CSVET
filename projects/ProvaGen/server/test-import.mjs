#!/usr/bin/env node
import { execSync } from "child_process";
import { promises as fs } from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

async function testImport() {
  try {
    console.log("🧪 Testando importação de questões...\n");

    // 1. Verificar se arquivo existe
    const testFile = "/home/ubuntu/upload/P2AFMATFisiologia.docx";
    console.log(`1️⃣  Verificando arquivo: ${testFile}`);
    try {
      await fs.access(testFile);
      console.log("   ✅ Arquivo encontrado\n");
    } catch (e) {
      console.log("   ❌ Arquivo não encontrado\n");
      return;
    }

    // 2. Testar parser Python diretamente
    console.log("2️⃣  Testando parser Python...");
    const pythonScript = path.join(__dirname, "question-parser.py");
    try {
      const output = execSync(`python3 "${pythonScript}" "${testFile}" "docx"`, {
        encoding: "utf-8",
        stdio: ["pipe", "pipe", "pipe"],
      });
      const questions = JSON.parse(output);
      console.log(`   ✅ Parser retornou ${questions.length} questões\n`);

      // 3. Simular o que o router faz
      console.log("3️⃣  Simulando chamada do router...");
      console.log(`   Tipo de questão 1: ${questions[0].type}`);
      console.log(`   Enunciado 1: ${questions[0].statement.substring(0, 60)}...\n`);

      console.log("✅ Teste concluído com sucesso!");
      console.log(`   Total de questões: ${questions.length}`);
    } catch (error) {
      console.log("   ❌ Erro ao executar parser Python:");
      console.log(`   ${error.message}\n`);
      if (error.stderr) {
        console.log("   Stderr:", error.stderr);
      }
    }
  } catch (error) {
    console.error("❌ Erro geral:", error.message);
  }
}

testImport();
