/**
 * VetRank — Geração de Questões via DeepSeek API
 * Saída: array JSON plano compatível com normalize_questions_json.mjs
 *
 * Env vars (.env):
 *   DEEPSEEK_API_KEY=sk-...
 *   PDF_PATH=D:\vetrank\Livro\seu_pdf.pdf
 *   OUTPUT_JSON_PATH=D:\vetrank\Livro\questoes_deepseek.json
 *   SUBJECT_TAG=Fisiologia
 *   SUBJECT_ID=30014
 *   DISCIPLINE_ID=30002
 *   YEAR=2026
 *   QUESTIONS_PER_BATCH=5
 *   MAX_CHUNKS=0   (0 = todos os chunks)
 *
 * Uso: npx tsx scripts/generate_questions_deepseek.ts
 */

import fs from 'fs';
import 'dotenv/config';
import * as pdfjsLib from 'pdfjs-dist/legacy/build/pdf.mjs';
import OpenAI from 'openai';

pdfjsLib.GlobalWorkerOptions.workerSrc = 'pdfjs-dist/legacy/build/pdf.worker.mjs';

// ── Configuração ─────────────────────────────────────────────────────────────
const PDF_PATH          = process.env.PDF_PATH           || './livro.pdf';
const OUTPUT_PATH       = process.env.OUTPUT_JSON_PATH   || './questoes_deepseek.json';
const CHUNK_SIZE        = 6000;
const QUESTIONS_PER_BATCH = Number(process.env.QUESTIONS_PER_BATCH ?? 5);
const MAX_CHUNKS        = Number(process.env.MAX_CHUNKS  ?? 0);   // 0 = todos
const DISCIPLINE_ID     = Number(process.env.DISCIPLINE_ID  ?? 30002);
const SUBJECT_ID        = Number(process.env.SUBJECT_ID     ?? 30014);
const SUBJECT_TAG       = process.env.SUBJECT_TAG             || '';
const YEAR              = Number(process.env.YEAR            ?? 2026);

// M1–M8 apenas (M9 não existe no VetRank)
const QUESTION_MODELS   = ['M1', 'M2', 'M3', 'M4', 'M5', 'M6', 'M7', 'M8'];

// Opções fixas do M5 — NUNCA geradas pela IA, sempre injetadas pelo script
const M5_OPTIONS = [
  { id: 'A', textPt: 'As asserções I e II são verdadeiras, e a II é justificativa correta da I.' },
  { id: 'B', textPt: 'As asserções I e II são verdadeiras, mas a II não é justificativa correta da I.' },
  { id: 'C', textPt: 'A asserção I é verdadeira, e a II é falsa.' },
  { id: 'D', textPt: 'A asserção I é falsa, e a II é verdadeira.' },
  { id: 'E', textPt: 'As asserções I e II são falsas.' },
] as const;

const client = new OpenAI({
  apiKey: process.env.DEEPSEEK_API_KEY,
  baseURL: 'https://api.deepseek.com',
});

// ── Regras por modelo ─────────────────────────────────────────────────────────
function modelRules(model: string): string {
  switch (model) {
    case 'M1': return `Pergunta direta. 5 alternativas, apenas uma correta.
  "modelId": "M1", "questionType": "multiple_choice", "questionModel": "enade"`;
    case 'M2': return `textPt é uma afirmação incompleta terminando em ":" ou "..."; as alternativas completam a frase.
  "modelId": "M2", "questionType": "multiple_choice", "questionModel": "enade"`;
    case 'M3': return `textPt contém assertivas I, II, III, IV. Alternativas são combinações (ex: "Apenas I e III estão corretas").
  "modelId": "M3", "questionType": "complex_multiple_choice", "questionModel": "enade"`;
    case 'M4': return `textPt DEVE conter "EXCETO", "NÃO" ou "INCORRETO" em MAIÚSCULAS.
  "modelId": "M4", "questionType": "multiple_choice", "questionModel": "enade"`;
    case 'M5': return `textPt no formato EXATO: "ASSERÇÃO I: [texto].\\n\\nPORQUE\\n\\nASSERÇÃO II: [texto]."
  Inclua "assertion1" (texto da Asserção I sem prefixo) e "assertion2" (texto da Asserção II sem prefixo).
  "correctOptionLetter" deve ser a letra (A–E) que corresponde à relação correta entre as asserções:
    A = ambas verdadeiras e II justifica I
    B = ambas verdadeiras mas II NÃO justifica I
    C = I verdadeira, II falsa
    D = I falsa, II verdadeira
    E = ambas falsas
  NÃO inclua o campo "options" — ele é gerado automaticamente.
  "modelId": "M5", "questionType": "assertion_reason", "questionModel": "assertion_reason"`;
    case 'M6': return `textPt com lacuna _______________ . Alternativas preenchem a lacuna.
  "modelId": "M6", "questionType": "cloze", "questionModel": "enade"`;
    case 'M7': return `textPt começa com caso clínico detalhado seguido da pergunta.
  "modelId": "M7", "questionType": "clinical_case", "questionModel": "enade"`;
    case 'M8': return `textPt contém duas colunas para associação. Alternativas no formato "1-A, 2-B, 3-C...".
  "modelId": "M8", "questionType": "matching", "questionModel": "enade"`;
    default: return '';
  }
}

// ── Pós-processamento ─────────────────────────────────────────────────────────
function postProcess(q: any, modelType: string): any {
  const out = { ...q };

  // Campos obrigatórios — sobrescrever sempre para garantir consistência
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
    // Injetar opções fixas (sem variação permitida)
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

  // Converter correctAlternative → correctOptionLetter (se a IA usou o nome errado)
  if (!out.correctOptionLetter && out.correctAlternative) {
    out.correctOptionLetter = String(out.correctAlternative).toUpperCase().charAt(0);
  }
  // Garantir que é só a letra
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
    const content = await page.getTextContent();
    fullText += content.items.map((item: any) => item.str ?? '').join(' ') + ' ';
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
${modelRules(modelType)}

FORMATO DE RESPOSTA:
Retorne APENAS um array JSON válido com ${numQ} objeto(s). Sem markdown, sem texto extra.
Exemplo de um objeto:
${exampleJson}

TEXTO FONTE:
"""
${textChunk}
"""`;

  try {
    const response = await client.chat.completions.create({
      model: 'deepseek-chat',
      messages: [
        {
          role: 'system',
          content: 'Você elabora questões de Medicina Veterinária. Responda APENAS com um array JSON válido, sem markdown.',
        },
        { role: 'user', content: prompt },
      ],
      temperature: 0.7,
    });

    let text = (response.choices[0].message.content || '[]').trim();
    // Remover blocos de código markdown se presentes
    text = text.replace(/^```(?:json)?\s*/i, '').replace(/\s*```$/i, '').trim();
    // Garantir que começa e termina como array
    if (!text.startsWith('[')) text = `[${text}`;
    if (!text.endsWith(']')) text = `${text}]`;

    const raw: any[] = JSON.parse(text);
    const questions = Array.isArray(raw)
      ? raw.map(q => postProcess(q, modelType))
      : [];

    const { prompt_tokens = 0, completion_tokens = 0 } = response.usage ?? {};
    console.log(`   📊 Tokens In=${prompt_tokens} | Out=${completion_tokens} | Questões: ${questions.length}`);
    return questions;

  } catch (err) {
    console.error(`   ❌ Erro no lote ${batchNum}:`, (err as Error).message);
    return [];
  }
}

// ── Main ──────────────────────────────────────────────────────────────────────
async function main() {
  console.log('\n' + '='.repeat(60));
  console.log('🚀 VetRank — Gerador DeepSeek');
  console.log('='.repeat(60));
  console.log(`   disciplineId: ${DISCIPLINE_ID} | subjectId: ${SUBJECT_ID}`);
  console.log(`   subjectTag: "${SUBJECT_TAG}" | year: ${YEAR}`);
  console.log(`   Modelos: ${QUESTION_MODELS.join(', ')}`);
  console.log(`   Questões por chunk: ${QUESTIONS_PER_BATCH}`);

  const pdfText = await extractTextFromPDF(PDF_PATH);

  const chunks: string[] = [];
  for (let i = 0; i < pdfText.length; i += CHUNK_SIZE) {
    chunks.push(pdfText.slice(i, i + CHUNK_SIZE));
  }
  const limit = MAX_CHUNKS > 0 ? Math.min(chunks.length, MAX_CHUNKS) : chunks.length;
  console.log(`\n🔢 ${chunks.length} chunk(s) de ~${CHUNK_SIZE} chars. Processando ${limit}.\n`);

  // Carregar questões já geradas (retomada segura após erro)
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
    console.log(`📦 Chunk ${i + 1}/${limit} | Modelo: ${modelType}`);

    const questions = await generateBatch(i + 1, modelType, chunks[i], QUESTIONS_PER_BATCH);

    if (questions.length > 0) {
      allQuestions.push(...questions);
      // Salva como array plano — compatível com normalize_questions_json.mjs
      fs.writeFileSync(OUTPUT_PATH, JSON.stringify(allQuestions, null, 2), 'utf-8');
      console.log(`   ✅ Total acumulado: ${allQuestions.length}`);
    } else {
      console.log(`   ⚠️  Nenhuma questão gerada neste chunk`);
    }

    await new Promise(r => setTimeout(r, 1000));
  }

  console.log('\n' + '='.repeat(60));
  console.log(`🎉 Concluído! ${allQuestions.length} questões salvas em:`);
  console.log(`   ${OUTPUT_PATH}`);
  console.log('\nPróximo passo — validar e normalizar:');
  console.log(`   node scripts/normalize_questions_json.mjs ${OUTPUT_PATH} ${OUTPUT_PATH.replace('.json', '_validado.json')} "${SUBJECT_TAG}"`);
  console.log('='.repeat(60));
}

main().catch(console.error);
