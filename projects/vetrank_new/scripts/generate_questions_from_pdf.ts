/**
 * VetRank — Extração de Questões de PDFs via IA (Gemini)
 * Saída: array JSON plano compatível com normalize_questions_json.mjs
 *
 * Env vars (.env):
 *   GEMINI_API_KEY=...
 *   PDF_PATH=D:\vetrank\Livro\seu_pdf.pdf
 *   OUTPUT_JSON_PATH=D:\vetrank\Livro\questoes_gemini.json
 *   SUBJECT_TAG=Patologia Hepática
 *   SUBJECT_ID=30014
 *   DISCIPLINE_ID=30002
 *   YEAR=2026
 *   QUESTIONS_PER_CHUNK=5
 *   MAX_CHUNKS=0   (0 = todos os chunks)
 *   GEMINI_MODEL=gemini-2.0-flash   (opcional)
 *
 * IDs de referência (Grande Área → disciplineId | Disciplina → subjectId):
 *   30001 Ciências Biológicas  → 30001-30009
 *   30002 Patobiologia         → 30010-30016
 *   30003 Clínica e Cirurgia   → 30017-30026
 *   30004 Saúde Pública        → 30027-30032
 *   30005 Produção Animal      → 30033-30039
 *   30006 Ética e Humanidades  → 30040-30045
 *
 * Uso: npx tsx scripts/generate_questions_from_pdf.ts
 */

import fs from 'fs';
import { GoogleGenerativeAI } from '@google/generative-ai';
import 'dotenv/config';
import * as pdfjsLib from 'pdfjs-dist/legacy/build/pdf.mjs';

pdfjsLib.GlobalWorkerOptions.workerSrc = 'pdfjs-dist/legacy/build/pdf.worker.mjs';

// ── Configuração ─────────────────────────────────────────────────────────────
const PDF_PATH          = process.env.PDF_PATH           || './livro.pdf';
const OUTPUT_PATH       = process.env.OUTPUT_JSON_PATH   || './questoes_gemini.json';
const CHUNK_SIZE        = 6000;
const QUESTIONS_PER_CHUNK = Number(process.env.QUESTIONS_PER_CHUNK ?? 5);
const MAX_CHUNKS        = Number(process.env.MAX_CHUNKS  ?? 0);   // 0 = todos
const DISCIPLINE_ID     = Number(process.env.DISCIPLINE_ID  ?? 30002);
const SUBJECT_ID        = Number(process.env.SUBJECT_ID     ?? 30014);
const SUBJECT_TAG       = process.env.SUBJECT_TAG             || '';
const YEAR              = Number(process.env.YEAR            ?? 2026);
const MODEL_NAME        = process.env.GEMINI_MODEL || 'gemini-2.0-flash';

const QUESTION_MODELS   = ['M1', 'M2', 'M3', 'M4', 'M5', 'M6', 'M7', 'M8', 'M9'];

// Opções fixas do M5 — NUNCA geradas pela IA, sempre injetadas pelo script
const M5_OPTIONS = [
  { id: 'A', textPt: 'As asserções I e II são verdadeiras, e a II é justificativa correta da I.' },
  { id: 'B', textPt: 'As asserções I e II são verdadeiras, mas a II não é justificativa correta da I.' },
  { id: 'C', textPt: 'A asserção I é verdadeira, e a II é falsa.' },
  { id: 'D', textPt: 'A asserção I é falsa, e a II é verdadeira.' },
  { id: 'E', textPt: 'As asserções I e II são falsas.' },
] as const;

const genAI = new GoogleGenerativeAI(process.env.GEMINI_API_KEY || '');
const geminiModel = genAI.getGenerativeModel({ model: MODEL_NAME });

// ── Regras por modelo ─────────────────────────────────────────────────────────
function getModelRules(m: string): string {
  switch (m) {
    case 'M1': return `modelId: "M1", questionType: "multiple_choice", questionModel: "enade"
  - Pergunta direta. 5 alternativas (A-E), apenas uma correta.`;
    case 'M2': return `modelId: "M2", questionType: "multiple_choice", questionModel: "enade"
  - textPt é afirmação incompleta terminando em ":" ou "...". As 5 alternativas completam a frase.`;
    case 'M3': return `modelId: "M3", questionType: "complex_multiple_choice", questionModel: "enade"
  - O textPt DEVE conter as assertivas I, II, III, IV embutidas. Alternativas são combinações (ex: "Apenas I e II").
  - Formato: "Sobre [tema]:\\n\\nI. [assertiva]\\nII. [assertiva]\\nIII. [assertiva]\\nIV. [assertiva]\\n\\nSão CORRETAS apenas:"`;
    case 'M4': return `modelId: "M4", questionType: "multiple_choice", questionModel: "enade"
  - Enunciado DEVE conter "EXCETO", "NÃO" ou "INCORRETO" em MAIÚSCULAS.`;
    case 'M5': return `modelId: "M5", questionType: "assertion_reason", questionModel: "assertion_reason"
  - textPt no formato EXATO: "ASSERÇÃO I: [texto].\\n\\nPORQUE\\n\\nASSERÇÃO II: [texto]."
  - Inclua "assertion1" (texto da Asserção I sem prefixo) e "assertion2" (texto da Asserção II sem prefixo).
  - "correctOptionLetter" deve ser A–E: A=ambas V+II justifica I, B=ambas V sem relação, C=I V II F, D=I F II V, E=ambas F.
  - NÃO inclua o campo "options" — ele é gerado automaticamente.`;
    case 'M6': return `modelId: "M6", questionType: "cloze", questionModel: "enade"
  - textPt com lacuna [___] (uma) ou [1], [2] (múltiplas). Alternativas preenchem as lacunas.`;
    case 'M7': return `modelId: "M7", questionType: "clinical_case", questionModel: "enade"
  - textPt DEVE começar com "[CASO CLÍNICO]" seguido de caso clínico detalhado e pergunta.`;
    case 'M8': return `modelId: "M8", questionType: "matching", questionModel: "enade"
  - Formato: "Relacione...\\n\\nCOLUNA I\\n1. [item]\\n2. ...\\n\\nCOLUNA II\\na) [desc]\\nb) ...\\n\\nA associação CORRETA é:"
  - Alternativas no formato "1-a / 2-c / 3-b / 4-d".`;
    case 'M9': return `modelId: "M9", questionType: "ordering", questionModel: "enade"
  - textPt apresenta etapas em ordem aleatória numeradas I–V. Alternativas são sequências (ex: "III – I – IV – II – V").`;
    default: return '';
  }
}

// ── Pós-processamento ─────────────────────────────────────────────────────────
function postProcess(q: any, modelType: string): any {
  const out = { ...q };

  // Campos obrigatórios — sobrescrever sempre
  out.disciplineId  = DISCIPLINE_ID;
  out.subjectId     = SUBJECT_ID;
  out.year          = YEAR;
  out.author        = 'VetRank';
  out.isPremium     = true;
  out.status        = 'approved';
  out.modelId       = modelType;
  if (SUBJECT_TAG) out.subjectTag = SUBJECT_TAG;

  // questionType e questionModel por modelo
  if (modelType === 'M5') {
    out.questionType  = 'assertion_reason';
    out.questionModel = 'assertion_reason';
    out.options = M5_OPTIONS.map(o => ({ ...o }));
  } else if (modelType === 'M3') {
    out.questionType  = out.questionType  || 'complex_multiple_choice';
    out.questionModel = out.questionModel || 'enade';
  } else {
    out.questionType  = out.questionType  || 'multiple_choice';
    out.questionModel = out.questionModel || 'enade';
  }

  // Converter alternatives → options (se a IA usou o nome errado)
  if (!Array.isArray(out.options) && Array.isArray(out.alternatives)) {
    out.options = (out.alternatives as string[]).map((text, i) => ({
      id: ['A', 'B', 'C', 'D', 'E'][i] ?? String.fromCharCode(65 + i),
      textPt: String(text).replace(/^[A-E][).]\s*/i, '').trim(),
    }));
  }
  delete out.alternatives;

  // Garantir que options são objetos {id, textPt}
  if (Array.isArray(out.options)) {
    out.options = out.options.map((o: any, i: number) => {
      if (typeof o === 'string') {
        return {
          id: ['A', 'B', 'C', 'D', 'E'][i] ?? String.fromCharCode(65 + i),
          textPt: o.replace(/^[A-E][).]\s*/i, '').trim(),
        };
      }
      const result: any = { ...o };
      if (!result.id && result.label) { result.id = result.label; delete result.label; }
      if (!result.textPt && result.text) { result.textPt = result.text; delete result.text; }
      return result;
    });
  }

  // Converter aliases de gabarito → correctOptionLetter
  if (!out.correctOptionLetter && out.correctAlternative) {
    out.correctOptionLetter = String(out.correctAlternative).toUpperCase().charAt(0);
  }
  if (!out.correctOptionLetter && out.correctOption) {
    out.correctOptionLetter = String(out.correctOption).toUpperCase().charAt(0);
  }
  if (out.correctOptionLetter) {
    out.correctOptionLetter = String(out.correctOptionLetter).toUpperCase().trim().charAt(0);
  }

  // Limpar campos que podem atrapalhar a importação
  delete out.id;
  delete out.correctAlternative;
  delete out.answer;
  delete out.correct;

  return out;
}

// ── Extração de texto do PDF ──────────────────────────────────────────────────
async function extractTextFromPDF(pdfPath: string): Promise<string> {
  console.log(`\n📄 Lendo PDF: ${pdfPath}`);
  const data = new Uint8Array(fs.readFileSync(pdfPath));
  const pdfDocument = await pdfjsLib.getDocument(data).promise;

  let fullText = '';
  for (let i = 1; i <= pdfDocument.numPages; i++) {
    const page = await pdfDocument.getPage(i);
    const textContent = await page.getTextContent();
    fullText += textContent.items.map((item: any) => ('str' in item ? item.str : '')).join(' ') + ' ';
    if (i % 10 === 0) console.log(`   📖 Página ${i}/${pdfDocument.numPages}`);
  }
  const text = fullText.trim();
  console.log(`   ✅ ${text.length} caracteres extraídos`);
  return text;
}

// ── Gerar lote ────────────────────────────────────────────────────────────────
async function generateBatch(
  batchNum: number,
  modelType: string,
  textChunk: string,
  numQ: number,
): Promise<any[]> {

  const isM5 = modelType === 'M5';

  const exampleJson = isM5
    ? `{
  "textPt": "ASSERÇÃO I: [texto da asserção].\\n\\nPORQUE\\n\\nASSERÇÃO II: [texto da asserção].",
  "assertion1": "texto da Asserção I",
  "assertion2": "texto da Asserção II",
  "correctOptionLetter": "A",
  "explanationPt": "justificativa detalhada",
  "difficulty": "hard"
}`
    : `{
  "textPt": "enunciado da questão",
  "options": [
    {"id": "A", "textPt": "alternativa A"},
    {"id": "B", "textPt": "alternativa B"},
    {"id": "C", "textPt": "alternativa C"},
    {"id": "D", "textPt": "alternativa D"},
    {"id": "E", "textPt": "alternativa E"}
  ],
  "correctOptionLetter": "B",
  "explanationPt": "justificativa detalhada",
  "difficulty": "medium"
}`;

  const prompt = `Você é especialista em elaborar questões para concursos de Medicina Veterinária.
Use o texto abaixo como fonte de conhecimento. Gere exatamente ${numQ} questão(ões) do modelo ${modelType}.

REGRAS GERAIS:
- NUNCA mencione "segundo o texto", "de acordo com o texto" ou referências ao PDF.
- O enunciado deve ser autossuficiente.
- difficulty: "medium" ou "hard"

MODELO ${modelType}:
${getModelRules(modelType)}

FORMATO DE RESPOSTA:
Retorne APENAS um array JSON válido com ${numQ} objeto(s). Sem markdown, sem texto extra.
Exemplo de um objeto:
${exampleJson}

TEXTO FONTE:
"""
${textChunk}
"""`;

  try {
    const result = await geminiModel.generateContent(prompt);
    const usage = result.response.usageMetadata;
    const inTokens = usage?.promptTokenCount ?? 0;
    const outTokens = usage?.candidatesTokenCount ?? 0;

    let text = result.response.text().trim();
    // Remover blocos de código markdown se presentes
    text = text.replace(/^```(?:json)?\s*/i, '').replace(/\s*```$/i, '').trim();
    // Garantir que começa e termina como array
    if (!text.startsWith('[')) text = `[${text}`;
    if (!text.endsWith(']')) text = `${text}]`;

    const raw: any[] = JSON.parse(text);
    const questions = Array.isArray(raw)
      ? raw.map(q => postProcess(q, modelType))
      : [];

    console.log(`   📊 Tokens In=${inTokens} | Out=${outTokens} | Questões: ${questions.length}`);
    return questions;

  } catch (err) {
    console.error(`   ❌ Erro no lote ${batchNum}:`, (err as Error).message);
    return [];
  }
}

// ── Main ──────────────────────────────────────────────────────────────────────
async function main() {
  console.log('\n' + '='.repeat(60));
  console.log('🚀 VetRank — Gerador Gemini');
  console.log('='.repeat(60));
  console.log(`   disciplineId: ${DISCIPLINE_ID} | subjectId: ${SUBJECT_ID}`);
  console.log(`   subjectTag: "${SUBJECT_TAG}" | year: ${YEAR}`);
  console.log(`   Modelos: ${QUESTION_MODELS.join(', ')}`);
  console.log(`   Questões por chunk: ${QUESTIONS_PER_CHUNK}`);
  console.log(`   Gemini model: ${MODEL_NAME}`);

  const pdfText = await extractTextFromPDF(PDF_PATH);

  const chunks: string[] = [];
  for (let i = 0; i < pdfText.length; i += CHUNK_SIZE) {
    chunks.push(pdfText.slice(i, i + CHUNK_SIZE));
  }
  const limit = MAX_CHUNKS > 0 ? Math.min(chunks.length, MAX_CHUNKS) : chunks.length;
  console.log(`\n🔢 ${chunks.length} chunk(s) de ~${CHUNK_SIZE} chars. Processando ${limit}.\n`);

  // Retomada segura — carregar questões já geradas
  let allQuestions: any[] = [];
  if (fs.existsSync(OUTPUT_PATH)) {
    try {
      const existing = JSON.parse(fs.readFileSync(OUTPUT_PATH, 'utf-8'));
      if (Array.isArray(existing) && existing.length > 0) {
        allQuestions = existing;
        console.log(`   ♻️  Retomando: ${allQuestions.length} questões já existentes em ${OUTPUT_PATH}`);
      }
    } catch { /* arquivo corrompido ou vazio — começa do zero */ }
  }

  for (let i = 0; i < limit; i++) {
    const modelType = QUESTION_MODELS[i % QUESTION_MODELS.length];
    console.log(`\n📦 Chunk ${i + 1}/${limit} | Modelo: ${modelType}`);

    const questions = await generateBatch(i + 1, modelType, chunks[i], QUESTIONS_PER_CHUNK);

    if (questions.length > 0) {
      allQuestions.push(...questions);
      // Salva como array plano — compatível com normalize_questions_json.mjs
      fs.writeFileSync(OUTPUT_PATH, JSON.stringify(allQuestions, null, 2), 'utf-8');
      console.log(`   ✅ Total acumulado: ${allQuestions.length}`);
    } else {
      console.log(`   ⚠️  Nenhuma questão gerada neste chunk`);
    }

    await new Promise(r => setTimeout(r, 3000));
  }

  console.log('\n' + '='.repeat(60));
  console.log(`🎉 Concluído! ${allQuestions.length} questões salvas em:`);
  console.log(`   ${OUTPUT_PATH}`);
  console.log('\nPróximo passo — validar e normalizar:');
  console.log(`   node scripts/normalize_questions_json.mjs ${OUTPUT_PATH} ${OUTPUT_PATH.replace('.json', '_validado.json')} "${SUBJECT_TAG}"`);
  console.log('='.repeat(60));
}

main().catch(console.error);
