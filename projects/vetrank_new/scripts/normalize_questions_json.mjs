/**
 * Normaliza o JSON gerado pelo generate_questions_from_pdf.ts
 * Converte variações de campos geradas pelo Gemini para o formato padrão
 * do importador VetRank.
 *
 * Uso: node scripts/normalize_questions_json.mjs <input.json> [output.json]
 */

import fs from 'fs';
import path from 'path';

const LETTERS = ['A', 'B', 'C', 'D', 'E'];

function stripMarkdown(text) {
  if (!text) return text;
  return text
    .replace(/\*\*([^*]+)\*\*/g, '$1')   // **negrito** → texto
    .replace(/\*([^*\n]+)\*/g, '$1')      // *itálico* → texto (inclui nomes científicos)
    .replace(/__([^_]+)__/g, '$1')        // __negrito__ → texto
    .replace(/_([^_\n]+)_/g, '$1')        // _itálico_ → texto
    .replace(/^#+\s+/gm, '')             // # Título → Título
    .replace(/`([^`]+)`/g, '$1')         // `código` → texto
    .trim();
}

function normalizeOptions(options) {
  if (!Array.isArray(options) || options.length === 0) return options;

  // Strings simples → objetos {id, textPt}
  if (typeof options[0] === 'string') {
    return options.map((text, i) => ({
      id: LETTERS[i] || String.fromCharCode(65 + i),
      textPt: String(text).trim(),
    }));
  }

  if (typeof options[0] !== 'object') return options;

  return options.map((o, i) => {
    const out = { ...o };

    // Normalizar chave da letra: label / letter → id
    if (!out.id) {
      if (out.label) { out.id = String(out.label).trim(); delete out.label; }
      else if (out.letter) { out.id = String(out.letter).trim(); delete out.letter; }
    }

    // id numérico (1,2,3,4,5) → letra (A,B,C,D,E)
    const idNum = parseInt(String(out.id ?? ''), 10);
    if (!isNaN(idNum) && idNum >= 1 && idNum <= 5) {
      out.id = LETTERS[idNum - 1];
    }

    // Normalizar texto: text → textPt
    if (!out.textPt && out.text) { out.textPt = out.text; delete out.text; }

    // Remover isCorrect (redundante após derivar gabarito)
    delete out.isCorrect;

    return out;
  });
}

function deriveCorrectLetter(q, normalizedOptions) {
  // 1. Campos já com letra válida
  for (const key of ['correctOption', 'correct_option', 'correctOptionLetter', 'gabarito']) {
    const v = String(q[key] ?? '').toUpperCase().trim();
    if (LETTERS.includes(v)) return v;
  }

  // 2. correctOptionId / answer com letra
  for (const key of ['correctOptionId', 'answer']) {
    const v = String(q[key] ?? '').toUpperCase().trim();
    if (LETTERS.includes(v)) return v;
  }

  // 3. isCorrect: true em opção ANTES da normalização
  if (Array.isArray(q.options)) {
    for (const o of q.options) {
      if (typeof o === 'object' && o.isCorrect === true) {
        // id pode ser número ainda (antes da normalização das opções)
        const rawId = String(o.id ?? o.label ?? o.letter ?? '').trim();
        const idNum = parseInt(rawId, 10);
        if (!isNaN(idNum) && idNum >= 1 && idNum <= 5) return LETTERS[idNum - 1];
        if (LETTERS.includes(rawId.toUpperCase())) return rawId.toUpperCase();
      }
    }
  }

  // 4. correctOptionIndex (0-based)
  if (q.correctOptionIndex !== undefined) {
    const idx = Number(q.correctOptionIndex);
    if (idx >= 0 && idx < 5) return LETTERS[idx];
  }

  // 5. Gabarito numérico (1-5) → letra
  for (const key of ['correctOption', 'correct_option', 'correctOptionLetter', 'gabarito', 'correctOptionId', 'answer']) {
    const v = String(q[key] ?? '').trim();
    const n = parseInt(v, 10);
    if (!isNaN(n) && n >= 1 && n <= 5 && String(n) === v) return LETTERS[n - 1];
  }

  // 6. Gabarito como texto completo: buscar em options normalizadas qual tem esse texto
  const fullTextGabarito = String(
    q.correctOption ?? q.correct_option ?? q.correctOptionLetter ?? q.gabarito ?? ''
  ).trim().toLowerCase();

  if (fullTextGabarito && Array.isArray(normalizedOptions)) {
    for (const o of normalizedOptions) {
      if (typeof o === 'object') {
        const optText = String(o.textPt ?? o.text ?? '').trim().toLowerCase();
        if (optText && optText === fullTextGabarito) return String(o.id ?? '').toUpperCase();
        // Partial match — gabarito começa com o texto da opção (após retirar o prefixo da letra)
        const stripped = optText.replace(/^[a-e][).]\s*/i, '');
        if (stripped && fullTextGabarito.startsWith(stripped)) return String(o.id ?? '').toUpperCase();
        if (stripped && stripped.startsWith(fullTextGabarito.slice(0, 20))) return String(o.id ?? '').toUpperCase();
      }
    }
  }

  return null;
}

function normalizeQuestion(q) {
  const out = { ...q };

  // 1. Normalizar enunciado
  if (!out.textPt && !out.text && !out.text_pt && !out.enunciado) {
    if (out.statement) { out.textPt = out.statement; delete out.statement; }
    else if (out.question) { out.textPt = out.question; delete out.question; }
  } else {
    delete out.statement;
    delete out.question;
  }

  // 2. Normalizar options
  out.options = normalizeOptions(out.options);

  // 3. Derivar gabarito correto
  const letter = deriveCorrectLetter(q, out.options);
  if (letter) {
    out.correctOptionLetter = letter;
  }

  // Limpar campos redundantes de gabarito
  delete out.correctOptionId;
  delete out.answer;
  delete out.correctOptionIndex;

  // 4. Limpar markdown dos campos de texto
  if (out.textPt) out.textPt = stripMarkdown(out.textPt);
  if (out.text) out.text = stripMarkdown(out.text);
  if (out.explanationPt) out.explanationPt = stripMarkdown(out.explanationPt);
  if (out.assertion1) out.assertion1 = stripMarkdown(out.assertion1);
  if (out.assertion2) out.assertion2 = stripMarkdown(out.assertion2);
  if (Array.isArray(out.options)) {
    out.options = out.options.map(o => {
      if (typeof o !== 'object') return o;
      const cleaned = { ...o };
      if (cleaned.textPt) cleaned.textPt = stripMarkdown(cleaned.textPt);
      if (cleaned.text) cleaned.text = stripMarkdown(cleaned.text);
      return cleaned;
    });
  }

  return out;
}

function validate(q) {
  const errors = [];

  const text = String(q.textPt ?? q.text ?? q.text_pt ?? q.enunciado ?? '').trim();
  if (!text) errors.push('enunciado vazio');
  if (!q.disciplineId) errors.push('disciplineId ausente');

  const diff = String(q.difficulty ?? '').toLowerCase();
  if (!['very_easy', 'easy', 'medium', 'hard', 'very_hard'].includes(diff))
    errors.push('difficulty inválido: ' + diff);

  const rawOpts = Array.isArray(q.options) ? q.options : [];
  const fromArr = (l) => {
    const o = rawOpts.find(o => typeof o === 'object' && String(o.id ?? '').toUpperCase() === l);
    return String(o?.textPt ?? o?.text ?? '').trim();
  };
  const missing = LETTERS.filter(l => !fromArr(l));
  if (missing.length) errors.push('opções faltando: ' + missing.join(','));

  const co = String(
    q.correctOption ?? q.correct_option ?? q.correctOptionLetter ?? q.gabarito ?? ''
  ).toUpperCase().trim();
  if (!LETTERS.includes(co)) errors.push('gabarito inválido: ' + (co || 'vazio'));

  return errors;
}

// ── Main ─────────────────────────────────────────────────────────────────────
const args = process.argv.slice(2);
if (!args[0]) {
  console.error('Uso: node scripts/normalize_questions_json.mjs <input.json> [output.json] [subjectTag]');
  process.exit(1);
}

const SUBJECT_TAG = args[2] ?? null;

const inputPath = path.resolve(args[0]);
const outputPath = args[1]
  ? path.resolve(args[1])
  : inputPath.replace(/\.json$/i, '_normalizado.json');

console.log(`\nLendo: ${inputPath}`);
const raw = JSON.parse(fs.readFileSync(inputPath, 'utf-8'));
console.log(`Total de questões: ${raw.length}`);

const normalized = raw.map(q => {
  const n = normalizeQuestion(q);
  if (SUBJECT_TAG) n.subjectTag = SUBJECT_TAG;
  return n;
});

const validQuestions = [];
const invalidList = [];

normalized.forEach((q, i) => {
  const errs = validate(q);
  if (errs.length === 0) {
    validQuestions.push(q);
  } else {
    invalidList.push({ index: i + 1, modelId: q.modelId, errors: errs });
  }
});

fs.writeFileSync(outputPath, JSON.stringify(validQuestions, null, 2), 'utf-8');

console.log(`\n✅ Válidas (salvas):  ${validQuestions.length}`);
console.log(`❌ Inválidas (descartadas): ${invalidList.length}`);
console.log(`\nArquivo salvo em: ${outputPath}`);

if (invalidList.length > 0) {
  // Agrupar erros por categoria
  const cats = {};
  invalidList.forEach(e => {
    const key = e.errors.map(s => s.replace(/:.*/,'')).sort().join('+');
    cats[key] = (cats[key] || 0) + 1;
  });
  console.log('\nCategorias de erros nas descartadas:', JSON.stringify(cats, null, 2));

  const toShow = Math.min(invalidList.length, 10);
  console.log(`\nPrimeiras ${toShow} questões descartadas:`);
  invalidList.slice(0, toShow).forEach(e =>
    console.log(`  [${e.index}] ${e.modelId}: ${e.errors.join('; ')}`)
  );
  if (invalidList.length > toShow) console.log(`  ... e mais ${invalidList.length - toShow}`);
}
