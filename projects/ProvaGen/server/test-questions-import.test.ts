import { describe, it, expect, beforeAll, afterAll } from "vitest";
import { execSync } from "child_process";
import { promises as fs } from "fs";
import path from "path";

describe("questions.import", () => {
  let tempJsonFile: string;
  let tempXlsxFile: string;
  let tempDocxFile: string;
  let tempPdfFile: string;

  beforeAll(async () => {
    // Criar arquivos de teste temporários
    tempJsonFile = `/tmp/test-json-${Date.now()}.json`;
    tempXlsxFile = `/tmp/test-xlsx-${Date.now()}.xlsx`;
    tempDocxFile = `/tmp/test-docx-${Date.now()}.docx`;
    tempPdfFile = `/tmp/test-pdf-${Date.now()}.pdf`;

    // Criar arquivo JSON de teste
    const fs = await import("fs").then((m) => m.promises);
    await fs.writeFile(tempJsonFile, JSON.stringify([
      {
        "type": "multiple_choice",
        "statement": "Qual é a capital do Brasil?",
        "alternatives": ["São Paulo", "Rio de Janeiro", "Brasília", "Belo Horizonte"],
        "correctAnswer": "Brasília"
      },
      {
        "type": "essay",
        "statement": "Explique o conceito de fotossíntese."
      }
    ]));

    // Copiar arquivos de teste existentes
    try {
      await fs.copyFile("/tmp/test-questions.xlsx", tempXlsxFile);
    } catch (e) {
      console.log("Arquivo XLSX de teste não disponível");
    }

    try {
      await fs.copyFile("/tmp/test-questions.docx", tempDocxFile);
    } catch (e) {
      console.log("Arquivo DOCX de teste não disponível");
    }

    try {
      await fs.copyFile("/tmp/test-questions.pdf", tempPdfFile);
    } catch (e) {
      console.log("Arquivo PDF de teste não disponível");
    }
  });

  afterAll(async () => {
    const fs = await import("fs").then((m) => m.promises);
    try {
      await fs.unlink(tempJsonFile);
      await fs.unlink(tempXlsxFile);
      await fs.unlink(tempDocxFile);
      await fs.unlink(tempPdfFile);
    } catch (e) {
      // Ignore cleanup errors
    }
  });

  it("should parse JSON file and extract questions", () => {
    const pythonScript = path.join(process.cwd(), "server/question-parser.py");
    const output = execSync(`python3 "${pythonScript}" "${tempJsonFile}" "json"`, {
      encoding: "utf-8",
      maxBuffer: 10 * 1024 * 1024,
    });

    const questions = JSON.parse(output);
    expect(questions).toBeInstanceOf(Array);
    expect(questions.length).toBe(2);
    expect(questions[0].type).toBe("multiple_choice");
    expect(questions[1].type).toBe("essay");
  });

  it("should parse XLSX file and extract questions", () => {
    if (!require("fs").existsSync(tempXlsxFile)) {
      console.log("Pulando teste XLSX - arquivo não disponível");
      return;
    }

    const pythonScript = path.join(process.cwd(), "server/question-parser.py");
    const output = execSync(`python3 "${pythonScript}" "${tempXlsxFile}" "xlsx"`, {
      encoding: "utf-8",
      maxBuffer: 10 * 1024 * 1024,
    });

    const questions = JSON.parse(output);
    expect(questions).toBeInstanceOf(Array);
    expect(questions.length).toBeGreaterThan(0);
  });

  it("should parse DOCX file and extract questions", () => {
    if (!require("fs").existsSync(tempDocxFile)) {
      console.log("Pulando teste DOCX - arquivo não disponível");
      return;
    }

    const pythonScript = path.join(process.cwd(), "server/question-parser.py");
    const output = execSync(`python3 "${pythonScript}" "${tempDocxFile}" "docx"`, {
      encoding: "utf-8",
      maxBuffer: 10 * 1024 * 1024,
    });

    const questions = JSON.parse(output);
    expect(questions).toBeInstanceOf(Array);
    expect(questions.length).toBeGreaterThan(0);
  });

  it("should parse PDF file and extract questions", () => {
    if (!require("fs").existsSync(tempPdfFile)) {
      console.log("Pulando teste PDF - arquivo não disponível");
      return;
    }

    const pythonScript = path.join(process.cwd(), "server/question-parser.py");
    const output = execSync(`python3 "${pythonScript}" "${tempPdfFile}" "pdf"`, {
      encoding: "utf-8",
      maxBuffer: 10 * 1024 * 1024,
    });

    const questions = JSON.parse(output);
    expect(questions).toBeInstanceOf(Array);
    expect(questions.length).toBeGreaterThan(0);
  });

  it("should extract multiple choice and essay questions with correct structure", () => {
    const pythonScript = path.join(process.cwd(), "server/question-parser.py");
    const output = execSync(`python3 "${pythonScript}" "${tempJsonFile}" "json"`, {
      encoding: "utf-8",
      maxBuffer: 10 * 1024 * 1024,
    });

    const questions = JSON.parse(output);
    expect(questions.length).toBe(2);

    const mcQuestion = questions[0]; // First question is multiple choice
    const essayQuestion = questions[1]; // Second question is essay

    expect(mcQuestion.type).toBe("multiple_choice");
    expect(mcQuestion).toHaveProperty("statement");
    expect(mcQuestion).toHaveProperty("alternatives");
    expect(mcQuestion.alternatives).toBeInstanceOf(Array);
    expect(mcQuestion.alternatives.length).toBeGreaterThan(0);

    expect(essayQuestion.type).toBe("essay");
    expect(essayQuestion).toHaveProperty("statement");
    expect(essayQuestion.alternatives).toBeInstanceOf(Array);
    expect(essayQuestion.alternatives.length).toBe(0);
  });
});
