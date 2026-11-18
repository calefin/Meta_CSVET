/**
 * VetRank — Gerador automático de questões via Gemini
 *
 * ─── SETUP ───────────────────────────────────────────────────────────────────
 *
 * 1. Obtenha a chave da API Gemini em: https://aistudio.google.com/apikey
 *
 * 2. Defina a variável de ambiente antes de rodar:
 *
 *    Windows (PowerShell):
 *      $env:GEMINI_API_KEY = "sua-chave-aqui"
 *
 *    Windows (CMD):
 *      set GEMINI_API_KEY=sua-chave-aqui
 *
 *    Linux / macOS:
 *      export GEMINI_API_KEY="sua-chave-aqui"
 *
 * 3. (Opcional) Limite de orçamento em USD (padrão: $20):
 *
 *    PowerShell:  $env:BUDGET_USD = "10"
 *    Linux/macOS: export BUDGET_USD="10"
 *
 * 4. Rode o script a partir da raiz do projeto:
 *
 *    npx tsx scripts/generate-questions.ts
 *
 * ─── SAÍDA ───────────────────────────────────────────────────────────────────
 *
 * - Arquivos gerados em: scripts/output/batch_NNN.json (máx 500 q por arquivo)
 * - Progresso salvo em:  scripts/output/progress.json (retoma onde parou)
 * - Para recomeçar do zero, apague scripts/output/progress.json
 *
 * ─── IMPORTAÇÃO ──────────────────────────────────────────────────────────────
 *
 * Após gerar, importe cada batch_NNN.json pelo painel admin → aba "Importar".
 */

import { GoogleGenerativeAI } from "@google/generative-ai";
import { writeFileSync, readFileSync, existsSync, mkdirSync } from "fs";
import { join, dirname, basename } from "path";
import { fileURLToPath } from "url";
import * as dotenv from "dotenv";

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

// Carrega .env.production da raiz do repo (VPS) ou .env local (dev)
dotenv.config({ path: join(__dirname, "../.env.production") });
dotenv.config();

// ─── Config ──────────────────────────────────────────────────────────────────

const GEMINI_API_KEY = process.env.GEMINI_API_KEY ?? "";
const MODEL_NAME = "gemini-1.0-pro"; // ou "gemini-1.5-flash" para mais velocidade
const DELAY_MS = 1200; // ms entre chamadas
// ROUNDS: quantas rodadas por subject/modelo. 1 = ~90 q/subject (barato). 3 = ~270 q/subject.
// Defina via: $env:ROUNDS="2"  ou  export ROUNDS=2
const ROUNDS = Number(process.env.ROUNDS ?? "1");
const OUTPUT_DIR = join(__dirname, "output");
const PROGRESS_FILE = join(OUTPUT_DIR, "progress.json");
const MAX_PER_BATCH = 500; // questões por arquivo de saída

// ─── Orçamento ───────────────────────────────────────────────────────────────
// Defina o limite máximo em USD. O script para automaticamente ao atingir.
const BUDGET_USD = Number(process.env.BUDGET_USD ?? "20");
const WARN_AT_PCT = 0.80; // avisa ao atingir 80% do orçamento
const USD_TO_BRL = 5.85; // taxa aproximada — ajuste se necessário
// Preços gemini-2.5-flash (por 1M tokens) — confira em https://ai.google.dev/pricing
// Atenção: thinking tokens são cobrados à parte mesmo com thinkingBudget:0 em alguns casos.
const PRICE_INPUT_PER_M    = 0.075;  // tokens de prompt
const PRICE_OUTPUT_PER_M   = 0.300;  // tokens de resposta (non-thinking)
const PRICE_THINKING_PER_M = 3.500;  // tokens de thinking (se o modelo usar)

// ─── Curriculum ──────────────────────────────────────────────────────────────

const CURRICULUM: Array<{ disciplineId: number; disciplineName: string; subjectId: number; subjectName: string }> = [
  // Grande Área 30001 — Ciências Biológicas e Ciclo Básico
  { disciplineId: 30001, disciplineName: "Ciências Biológicas e Ciclo Básico", subjectId: 30001, subjectName: "Anatomia Veterinária I e II (Sistêmica e Topográfica)" },
  { disciplineId: 30001, disciplineName: "Ciências Biológicas e Ciclo Básico", subjectId: 30002, subjectName: "Biologia Celular e Molecular" },
  { disciplineId: 30001, disciplineName: "Ciências Biológicas e Ciclo Básico", subjectId: 30003, subjectName: "Histologia Veterinária (Geral e Especial)" },
  { disciplineId: 30001, disciplineName: "Ciências Biológicas e Ciclo Básico", subjectId: 30004, subjectName: "Embriologia Veterinária" },
  { disciplineId: 30001, disciplineName: "Ciências Biológicas e Ciclo Básico", subjectId: 30005, subjectName: "Bioquímica Veterinária e Metabólica" },
  { disciplineId: 30001, disciplineName: "Ciências Biológicas e Ciclo Básico", subjectId: 30006, subjectName: "Biofísica Veterinária" },
  { disciplineId: 30001, disciplineName: "Ciências Biológicas e Ciclo Básico", subjectId: 30007, subjectName: "Fisiologia Veterinária I e II" },
  { disciplineId: 30001, disciplineName: "Ciências Biológicas e Ciclo Básico", subjectId: 30008, subjectName: "Genética e Evolução Animal" },
  { disciplineId: 30001, disciplineName: "Ciências Biológicas e Ciclo Básico", subjectId: 30009, subjectName: "Bioestatística e Metodologia Científica" },
  // Grande Área 30002 — Patobiologia e Agentes Agressores
  { disciplineId: 30002, disciplineName: "Patobiologia e Agentes Agressores", subjectId: 30010, subjectName: "Microbiologia Veterinária (Bacteriologia e Virologia)" },
  { disciplineId: 30002, disciplineName: "Patobiologia e Agentes Agressores", subjectId: 30011, subjectName: "Imunologia Veterinária" },
  { disciplineId: 30002, disciplineName: "Patobiologia e Agentes Agressores", subjectId: 30012, subjectName: "Parasitologia Veterinária" },
  { disciplineId: 30002, disciplineName: "Patobiologia e Agentes Agressores", subjectId: 30013, subjectName: "Patologia Geral e Técnicas de Necropsia" },
  { disciplineId: 30002, disciplineName: "Patobiologia e Agentes Agressores", subjectId: 30014, subjectName: "Patologia Especial/Sistêmica Veterinária" },
  { disciplineId: 30002, disciplineName: "Patobiologia e Agentes Agressores", subjectId: 30015, subjectName: "Farmacologia e Terapêutica Veterinária" },
  { disciplineId: 30002, disciplineName: "Patobiologia e Agentes Agressores", subjectId: 30016, subjectName: "Toxicologia Veterinária" },
  // Grande Área 30003 — Clínica, Cirurgia e Reprodução Animal
  { disciplineId: 30003, disciplineName: "Clínica, Cirurgia e Reprodução Animal", subjectId: 30017, subjectName: "Semiologia Veterinária (Propedêutica)" },
  { disciplineId: 30003, disciplineName: "Clínica, Cirurgia e Reprodução Animal", subjectId: 30018, subjectName: "Patologia Clínica Veterinária (Diagnóstico Laboratorial)" },
  { disciplineId: 30003, disciplineName: "Clínica, Cirurgia e Reprodução Animal", subjectId: 30019, subjectName: "Diagnóstico por Imagem (Radiologia e Ultrassonografia)" },
  { disciplineId: 30003, disciplineName: "Clínica, Cirurgia e Reprodução Animal", subjectId: 30020, subjectName: "Técnica Cirúrgica Veterinária" },
  { disciplineId: 30003, disciplineName: "Clínica, Cirurgia e Reprodução Animal", subjectId: 30021, subjectName: "Anestesiologia Veterinária" },
  { disciplineId: 30003, disciplineName: "Clínica, Cirurgia e Reprodução Animal", subjectId: 30022, subjectName: "Clínica Médica e Cirúrgica de Pequenos Animais (Cães e Gatos)" },
  { disciplineId: 30003, disciplineName: "Clínica, Cirurgia e Reprodução Animal", subjectId: 30023, subjectName: "Clínica Médica e Cirúrgica de Ruminantes (Bovinos, Ovinos e Caprinos)" },
  { disciplineId: 30003, disciplineName: "Clínica, Cirurgia e Reprodução Animal", subjectId: 30024, subjectName: "Clínica Médica e Cirúrgica de Equídeos" },
  { disciplineId: 30003, disciplineName: "Clínica, Cirurgia e Reprodução Animal", subjectId: 30025, subjectName: "Fisiopatologia da Reprodução (Macho e Fêmea)" },
  { disciplineId: 30003, disciplineName: "Clínica, Cirurgia e Reprodução Animal", subjectId: 30026, subjectName: "Obstetrícia Veterinária e Biotecnologia da Reprodução" },
  // Grande Área 30004 — Medicina Preventiva, Saúde Pública e Inspeção
  { disciplineId: 30004, disciplineName: "Medicina Preventiva, Saúde Pública e Inspeção", subjectId: 30027, subjectName: "Epidemiologia Veterinária" },
  { disciplineId: 30004, disciplineName: "Medicina Preventiva, Saúde Pública e Inspeção", subjectId: 30028, subjectName: "Zoonoses e Saúde Pública" },
  { disciplineId: 30004, disciplineName: "Medicina Preventiva, Saúde Pública e Inspeção", subjectId: 30029, subjectName: "Saneamento Ambiental e Planejamento em Saúde Animal" },
  { disciplineId: 30004, disciplineName: "Medicina Preventiva, Saúde Pública e Inspeção", subjectId: 30030, subjectName: "Inspeção de Produtos de Origem Animal (Carnes, Leite, Ovos, Pescado e Mel)" },
  { disciplineId: 30004, disciplineName: "Medicina Preventiva, Saúde Pública e Inspeção", subjectId: 30031, subjectName: "Tecnologia de Produtos de Origem Animal" },
  { disciplineId: 30004, disciplineName: "Medicina Preventiva, Saúde Pública e Inspeção", subjectId: 30032, subjectName: "Higiene de Alimentos" },
  // Grande Área 30005 — Zootecnia e Produção Animal
  { disciplineId: 30005, disciplineName: "Zootecnia e Produção Animal", subjectId: 30033, subjectName: "Introdução à Zootecnia" },
  { disciplineId: 30005, disciplineName: "Zootecnia e Produção Animal", subjectId: 30034, subjectName: "Nutrição e Alimentação Animal" },
  { disciplineId: 30005, disciplineName: "Zootecnia e Produção Animal", subjectId: 30035, subjectName: "Agrostologia e Forragicultura (Pastagens)" },
  { disciplineId: 30005, disciplineName: "Zootecnia e Produção Animal", subjectId: 30036, subjectName: "Melhoramento Genético Animal" },
  { disciplineId: 30005, disciplineName: "Zootecnia e Produção Animal", subjectId: 30037, subjectName: "Bovinocultura de Corte e de Leite" },
  { disciplineId: 30005, disciplineName: "Zootecnia e Produção Animal", subjectId: 30038, subjectName: "Avicultura, Suinocultura e Equideocultura" },
  { disciplineId: 30005, disciplineName: "Zootecnia e Produção Animal", subjectId: 30039, subjectName: "Aquicultura e Piscicultura" },
  // Grande Área 30006 — Ética, Humanidades e Áreas Complementares
  { disciplineId: 30006, disciplineName: "Ética, Humanidades e Áreas Complementares", subjectId: 30040, subjectName: "Deontologia, Ética e Bioética na Medicina Veterinária" },
  { disciplineId: 30006, disciplineName: "Ética, Humanidades e Áreas Complementares", subjectId: 30041, subjectName: "Bem-Estar Animal e Etologia" },
  { disciplineId: 30006, disciplineName: "Ética, Humanidades e Áreas Complementares", subjectId: 30042, subjectName: "Medicina Veterinária Legal (Perícia)" },
  { disciplineId: 30006, disciplineName: "Ética, Humanidades e Áreas Complementares", subjectId: 30043, subjectName: "Administração e Planejamento Rural" },
  { disciplineId: 30006, disciplineName: "Ética, Humanidades e Áreas Complementares", subjectId: 30044, subjectName: "Extensão Rural e Sociologia" },
  { disciplineId: 30006, disciplineName: "Ética, Humanidades e Áreas Complementares", subjectId: 30045, subjectName: "Medicina de Animais Selvagens e Pets Exóticos" },
];

// ─── Prompt builders ──────────────────────────────────────────────────────────

type ModelKey = "M1" | "M2" | "M3" | "M4" | "M5" | "M6" | "M7" | "M8" | "M9";

const BANCAS = ["ENADE", "ENARE", "REVALIDA", "Residência"];

function rndBanca() { return BANCAS[Math.floor(Math.random() * BANCAS.length)]; }

function baseJson(disciplineId: number, subjectId: number) {
  return `"disciplineId": ${disciplineId}, "subjectId": ${subjectId}, "author": "VetRank", "isPremium": true, "status": "approved"`;
}

function buildPrompt(
  key: ModelKey,
  disciplineId: number,
  disciplineName: string,
  subjectId: number,
  subjectName: string,
  round: number,
): string {
  const banca = rndBanca();
  const base = baseJson(disciplineId, subjectId);
  // Vary difficulty distribution across rounds
  const difficulties = round === 0
    ? ["easy", "easy", "medium", "medium", "medium", "hard", "hard", "hard", "very_hard", "very_hard"]
    : round === 1
    ? ["very_easy", "easy", "medium", "medium", "hard", "hard", "very_hard", "very_hard", "hard", "medium"]
    : ["easy", "medium", "medium", "hard", "hard", "very_hard", "very_hard", "hard", "medium", "easy"];
  const diffHint = `Distribua as dificuldades assim (nesta ordem): ${difficulties.join(", ")}.`;

  const intro = `Você é especialista em elaboração de questões para concursos e provas de Medicina Veterinária (ENADE, ENARE, Residência, Aprimoramento, provas de graduação).
Gere questões sobre a disciplina abaixo. VARIE os tópicos cobertos — não repita os mesmos conceitos.
${diffHint}

EXIGÊNCIAS DE QUALIDADE (obrigatórias):
• Enunciados ricos e detalhados: use nomes científicos corretos, valores laboratoriais/referência reais, fármacos com doses, espécies/raças específicas e cenários clínicos verossímeis.
• Jamais use enunciados genéricos como "Assinale a alternativa correta sobre a doença X." — o enunciado deve trazer contexto suficiente para diferenciar candidatos.
• explanationPt: mínimo 3 frases. Explique por que o gabarito está correto E por que pelo menos 2 distratores estão errados.
• Distratores: plausíveis, do mesmo domínio semântico, sem opções claramente absurdas.

Retorne APENAS o JSON válido (array), sem markdown, sem blocos de código, sem comentários.

Disciplina: ${subjectName}
Grande Área (disciplineId): ${disciplineId} — ${disciplineName}
subjectId: ${subjectId}`;

  switch (key) {
    case "M1": return `${intro}

Gere 10 questões M1 (Resposta Única — Pergunta Direta).
REGRAS: modelId "M1", questionType "multiple_choice", questionModel "enade", banca "${banca}".
Enunciado: traga contexto (dado clínico, biológico ou normativo) ANTES da pergunta. Ex: "A dose terapêutica de X em bovinos é de Y mg/kg, porém em doses > Z pode ocorrer [problema]. Assinale o mecanismo pelo qual..."
5 alternativas (A–E), apenas uma correta. Distratores plausíveis do mesmo domínio. Justificativa detalhada em explanationPt.

[{"${base}", "banca":"${banca}", "difficulty":"easy", "questionType":"multiple_choice", "modelId":"M1", "questionModel":"enade",
  "textPt":"[contexto específico com dados]... Assinale a alternativa CORRETA:", "options":[{"id":"A","textPt":"..."},{"id":"B","textPt":"..."},{"id":"C","textPt":"..."},{"id":"D","textPt":"..."},{"id":"E","textPt":"..."}],
  "correctOption":"B", "explanationPt":"B está correta porque [explicação]. A está errada porque [razão]. C está errada porque [razão]."}]`;

    case "M2": return `${intro}

Gere 10 questões M2 (Afirmação Incompleta).
REGRAS: modelId "M2", questionType "multiple_choice", questionModel "enade", banca "${banca}".
Enunciado TERMINA com ":" ou "..." (frase incompleta). As alternativas completam a frase.
Apenas uma é verdadeira. Inclua em explanationPt por que cada distrator está errado.

[{"${base}", "banca":"${banca}", "difficulty":"medium", "questionType":"multiple_choice", "modelId":"M2", "questionModel":"enade",
  "textPt":"O agente etiológico da tuberculose bovina é:", "options":[{"id":"A","textPt":"..."},{"id":"B","textPt":"..."},{"id":"C","textPt":"..."},{"id":"D","textPt":"..."},{"id":"E","textPt":"..."}],
  "correctOption":"C", "explanationPt":"..."}]`;

    case "M3": return `${intro}

Gere 10 questões M3 (Assertivas — Resposta Múltipla).
REGRAS: modelId "M3", questionType "complex_multiple_choice", questionModel "enade", banca "${banca}".
textPt DEVE conter as assertivas I, II, III, IV embutidas, terminando em "São CORRETAS apenas:".
Alternativas são combinações: "Apenas I", "I e III", "I, II e IV", etc. NUNCA "Todas" ou "Nenhuma".
explanationPt: explique cada assertiva (VERDADEIRA/FALSA e por quê).

[{"${base}", "banca":"${banca}", "difficulty":"hard", "questionType":"complex_multiple_choice", "modelId":"M3", "questionModel":"enade",
  "textPt":"Sobre [tema], analise as assertivas:\\n\\nI. ...\\nII. ...\\nIII. ...\\nIV. ...\\n\\nSão CORRETAS apenas:",
  "options":[{"id":"A","textPt":"Apenas I e II."},{"id":"B","textPt":"Apenas I e III."},{"id":"C","textPt":"Apenas II e IV."},{"id":"D","textPt":"I, III e IV."},{"id":"E","textPt":"Apenas II e III."}],
  "correctOption":"D", "explanationPt":"I-VERDADEIRA: ... II-FALSA: ... III-VERDADEIRA: ... IV-VERDADEIRA: ..."}]`;

    case "M4": return `${intro}

Gere 8 questões M4 (Foco Negativo — EXCETO / NÃO / INCORRETO).
REGRAS: modelId "M4", questionType "multiple_choice", questionModel "enade", banca "${banca}".
O enunciado DEVE conter "EXCETO", "NÃO" ou "INCORRETO" em MAIÚSCULAS.
4 alternativas são corretas; apenas 1 é o gabarito (incorreta mas plausível). Dificuldade: medium/hard.
explanationPt: indique qual é incorreta e por quê; confirme brevemente as demais.

[{"${base}", "banca":"${banca}", "difficulty":"medium", "questionType":"multiple_choice", "modelId":"M4", "questionModel":"enade",
  "textPt":"São características de X, EXCETO:", "options":[{"id":"A","textPt":"..."},{"id":"B","textPt":"..."},{"id":"C","textPt":"..."},{"id":"D","textPt":"..."},{"id":"E","textPt":"..."}],
  "correctOption":"C", "explanationPt":"C é INCORRETO porque... A, B, D e E são corretos."}]`;

    case "M5": return `${intro}

Gere 10 questões M5 (Asserção–Razão PORQUE).
REGRAS: modelId "M5", questionType "assertion_reason", questionModel "assertion_reason", banca "${banca}".
textPt: "ASSERÇÃO I: [prop1].\\n\\nPORQUE\\n\\nASSERÇÃO II: [prop2].\\n\\nA respeito das asserções acima, assinale a alternativa CORRETA:"
Inclua campos: assertion1, assertion2 (separados), a1Verdadeira, a2Verdadeira, relacaoCausal (booleans).
Alternativas são SEMPRE as 5 fixas do M5. Distribua gabaritos variando entre A, B, C, D, E.

Mapeamento: A→a1=true,a2=true,rel=true | B→a1=true,a2=true,rel=false | C→a1=true,a2=false,rel=false | D→a1=false,a2=true,rel=false | E→a1=false,a2=false,rel=false

[{"${base}", "banca":"${banca}", "difficulty":"hard", "questionType":"assertion_reason", "modelId":"M5", "questionModel":"assertion_reason",
  "textPt":"ASSERÇÃO I: ...\\n\\nPORQUE\\n\\nASSERÇÃO II: ...\\n\\nA respeito das asserções acima, assinale a alternativa CORRETA:",
  "assertion1":"texto asserção I", "assertion2":"texto asserção II",
  "a1Verdadeira":true, "a2Verdadeira":true, "relacaoCausal":true,
  "options":[
    {"id":"A","textPt":"As duas asserções são verdadeiras e a segunda é uma justificativa correta da primeira."},
    {"id":"B","textPt":"As duas asserções são verdadeiras, mas a segunda não é uma justificativa correta da primeira."},
    {"id":"C","textPt":"A primeira asserção é verdadeira e a segunda é falsa."},
    {"id":"D","textPt":"A primeira asserção é falsa e a segunda é verdadeira."},
    {"id":"E","textPt":"As duas asserções são falsas."}
  ],
  "correctOption":"A", "explanationPt":"Asserção I é VERDADEIRA porque... Asserção II é VERDADEIRA porque... Relação causal: ..."}]`;

    case "M6": return `${intro}

Gere 8 questões M6 (Lacuna / Cloze).
REGRAS: modelId "M6", questionType "cloze", questionModel "enade", banca "${banca}".
textPt: trecho técnico com lacuna [___] (ou [1],[2] para múltiplas), terminando em "Assinale a alternativa que preenche CORRETAMENTE a(s) lacuna(s):".
Distratores: termos da mesma categoria semântica.

[{"${base}", "banca":"${banca}", "difficulty":"medium", "questionType":"cloze", "modelId":"M6", "questionModel":"enade",
  "textPt":"O período de incubação da Raiva em bovinos varia de [___] dias após a exposição.\\n\\nAssinale a alternativa que preenche CORRETAMENTE a lacuna:",
  "options":[{"id":"A","textPt":"2 a 5"},{"id":"B","textPt":"15 a 60"},{"id":"C","textPt":"90 a 180"},{"id":"D","textPt":"1 a 3"},{"id":"E","textPt":"365 a 730"}],
  "correctOption":"B", "explanationPt":"..."}]`;

    case "M7": return `${intro}

Gere 8 questões M7 (Caso Clínico).
REGRAS: modelId "M7", questionType "clinical_case", questionModel "enade", banca "ENADE", difficulty "hard" ou "very_hard".
textPt DEVE conter (OBRIGATÓRIO):
  - Espécie, raça, sexo, idade/peso do animal
  - Anamnese com queixas principais e duração
  - Exame físico (FC, FR, TPC, mucosas, temperatura)
  - Pelo menos 2 achados de exames complementares com valores numéricos reais (hemograma, bioquímica sérica, urinálise, citologia ou imagem)
  - Pergunta clínica direta ao final
O candidato PRECISA dos dados do caso para responder — a resposta NÃO pode ser inferida sem lê-los.
Distratores: diagnósticos ou condutas plausíveis que seriam incorretos dado o quadro descrito.

[{"${base}", "banca":"ENADE", "difficulty":"hard", "questionType":"clinical_case", "modelId":"M7", "questionModel":"enade",
  "textPt":"[CASO CLÍNICO]\\nAnimal: Cão, Labrador Retriever, fêmea castrada, 7 anos, 30 kg. Anamnese: proprietário relata aumento progressivo do abdômen há 3 semanas, poliúria, polidipsia e apatia. Sem histórico vacinal recente.\\nExame físico: FC 110 bpm, FR 28 mpm, TPC 2s, mucosas róseas, temperatura 39,4°C, abdômen abaulado e tenso à palpação.\\nExames: Hemograma — leucocitose (28.000 células/μL) com neutrofilia e desvio à esquerda; glicemia 95 mg/dL; ALT 85 U/L (ref. < 50); ultrassom — útero com conteúdo anecogênico e parede espessada.\\n\\nCom base no quadro clínico, o diagnóstico mais provável e a conduta imediata são:",
  "options":[{"id":"A","textPt":"Piómetra aberta — internação, antibioticoterapia e ovário-histerectomia eletiva."},{"id":"B","textPt":"Piómetra fechada — estabilização clínica e ovário-histerectomia de emergência."},{"id":"C","textPt":"Torção uterina — laparotomia exploratória imediata sem preparo pré-operatório."},{"id":"D","textPt":"Ascite por insuficiência cardíaca — furosemida IV e ecocardiograma."},{"id":"E","textPt":"Diabetes insípidus — restrição hídrica e desmopressina."}],
  "correctOption":"B", "explanationPt":"Piómetra fechada (B): útero distendido com conteúdo purulento, sem descarga vaginal visível (fechada), leucocitose com desvio à esquerda e sinais sistêmicos indicam sepse iminente — OHE de emergência após estabilização é o padrão-ouro. A está errada: piómetra aberta apresenta descarga vaginal; mesmo assim OHE eletiva não é segura em sepse. C está errada: torção uterina é mais aguda e intensa, sem conteúdo líquido homogêneo. D está errada: não há evidência de cardiopatia. E está errada: diabetes insípidus não explica o quadro abdominal e infeccioso."}]`;

    case "M8": return `${intro}

Gere 8 questões M8 (Associação de Colunas).
REGRAS: modelId "M8", questionType "matching", questionModel "enade", banca "${banca}".
Use 4 pares (Coluna I: 1–4; Coluna II: a–d + 1 distrator e).
textPt contém as duas colunas; alternativas no formato "1-a / 2-c / 3-b / 4-d".

[{"${base}", "banca":"${banca}", "difficulty":"medium", "questionType":"matching", "modelId":"M8", "questionModel":"enade",
  "textPt":"Relacione os itens da Coluna I com os da Coluna II:\\n\\nCOLUNA I\\n1. ...\\n2. ...\\n3. ...\\n4. ...\\n\\nCOLUNA II\\na) ...\\nb) ...\\nc) ...\\nd) ...\\ne) ...\\n\\nA associação CORRETA é:",
  "options":[{"id":"A","textPt":"1-b / 2-a / 3-d / 4-c."},{"id":"B","textPt":"1-a / 2-c / 3-b / 4-d."},{"id":"C","textPt":"1-c / 2-d / 3-a / 4-b."},{"id":"D","textPt":"1-d / 2-b / 3-c / 4-a."},{"id":"E","textPt":"1-b / 2-d / 3-a / 4-c."}],
  "correctOption":"A", "explanationPt":"1-b porque... 2-a porque... 3-d porque... 4-c porque..."}]`;

    case "M9": return `${intro}

Gere 8 questões M9 (Ordenação / Sequência de Etapas).
REGRAS: modelId "M9", questionType "ordering", questionModel "enade", banca "${banca}".
Apresente 4 ou 5 etapas em ordem ALEATÓRIA (numeradas I–V). Alternativas são sequências diferentes.
NÃO inclua pistas temporais nas etapas.

[{"${base}", "banca":"${banca}", "difficulty":"hard", "questionType":"ordering", "modelId":"M9", "questionModel":"enade",
  "textPt":"Assinale a sequência CORRETA das etapas do procedimento X:\\n\\nI. ...\\nII. ...\\nIII. ...\\nIV. ...\\nV. ...",
  "options":[{"id":"A","textPt":"III – I – IV – II – V."},{"id":"B","textPt":"I – III – II – IV – V."},{"id":"C","textPt":"II – IV – I – III – V."},{"id":"D","textPt":"IV – II – III – I – V."},{"id":"E","textPt":"I – II – III – IV – V."}],
  "correctOption":"A", "explanationPt":"A sequência correta é III-I-IV-II-V porque: ..."}]`;
  }
}

// ─── JSON repair ──────────────────────────────────────────────────────────────

function tryParseJson(raw: string): unknown[] | null {
  // Strip potential markdown fences
  let text = raw.trim().replace(/^```json\s*/i, "").replace(/^```\s*/i, "").replace(/\s*```$/i, "").trim();
  // Find the array bounds
  const start = text.indexOf("[");
  const end = text.lastIndexOf("]");
  if (start === -1 || end === -1) return null;
  text = text.slice(start, end + 1);
  try {
    return JSON.parse(text);
  } catch {
    // Attempt to fix trailing commas
    text = text.replace(/,\s*([}\]])/g, "$1");
    try {
      return JSON.parse(text);
    } catch {
      return null;
    }
  }
}

// ─── Validation ───────────────────────────────────────────────────────────────

function isValidQuestion(q: Record<string, unknown>): boolean {
  if (typeof q.textPt !== "string" || q.textPt.trim().length < 20) return false;
  if (!q.disciplineId || !q.subjectId) return false;
  if (!q.correctOption || !["A","B","C","D","E"].includes(String(q.correctOption))) return false;
  if (!Array.isArray(q.options) || (q.options as unknown[]).length < 5) return false;
  return true;
}

// ─── Progress ─────────────────────────────────────────────────────────────────

interface Progress {
  done: string[]; // keys: `${subjectId}_${model}_${round}`
  totalGenerated: number;
  totalCostUsd: number;
}

function loadProgress(): Progress {
  if (existsSync(PROGRESS_FILE)) {
    const p = JSON.parse(readFileSync(PROGRESS_FILE, "utf-8")) as Progress;
    p.totalCostUsd ??= 0;
    return p;
  }
  return { done: [], totalGenerated: 0, totalCostUsd: 0 };
}

function formatCost(usd: number) {
  return `$${usd.toFixed(3)} / R$${(usd * USD_TO_BRL).toFixed(2)}`;
}

function calcCallCost(inputTokens: number, outputTokens: number, thinkingTokens = 0): number {
  return (inputTokens   / 1_000_000) * PRICE_INPUT_PER_M
       + (outputTokens  / 1_000_000) * PRICE_OUTPUT_PER_M
       + (thinkingTokens / 1_000_000) * PRICE_THINKING_PER_M;
}

function saveProgress(p: Progress) {
  writeFileSync(PROGRESS_FILE, JSON.stringify(p, null, 2));
}

// ─── Batch output ─────────────────────────────────────────────────────────────

let currentBatch: unknown[] = [];
let batchIndex = 1;
let lastSavedSize = 0;

function batchFile(index: number) {
  return join(OUTPUT_DIR, `batch_${String(index).padStart(3, "0")}.json`);
}

function loadExistingBatches() {
  let i = 1;
  while (existsSync(batchFile(i))) i++;
  batchIndex = i;
  const prev = batchFile(i - 1);
  if (i > 1 && existsSync(prev)) {
    const arr = JSON.parse(readFileSync(prev, "utf-8")) as unknown[];
    if (arr.length < MAX_PER_BATCH) {
      currentBatch = arr;
      lastSavedSize = arr.length;
      batchIndex = i - 1;
      console.log(`  📂 Retomando ${basename(prev)} (${arr.length} questões já salvas)`);
    }
  }
}

function safeWrite(data: unknown[], file: string): boolean {
  if (existsSync(file)) {
    try {
      const existing = JSON.parse(readFileSync(file, "utf-8")) as unknown[];
      if (existing.length >= MAX_PER_BATCH) {
        console.error(`  🚫 Proteção: ${basename(file)} já está completo — escrita ignorada`);
        return false;
      }
    } catch { /* arquivo corrompido, pode sobrescrever */ }
  }
  writeFileSync(file, JSON.stringify(data, null, 2));
  return true;
}

function checkpointBatch() {
  if (currentBatch.length === 0 || currentBatch.length === lastSavedSize) return;
  const file = batchFile(batchIndex);
  if (safeWrite(currentBatch, file)) {
    lastSavedSize = currentBatch.length;
    console.log(`  📌 Checkpoint: ${basename(file)} (${currentBatch.length} questões)`);
  }
}

function appendQuestions(questions: unknown[]) {
  for (const q of questions) {
    currentBatch.push(q);
    if (currentBatch.length >= MAX_PER_BATCH) {
      const file = batchFile(batchIndex);
      if (safeWrite(currentBatch, file)) {
        console.log(`  💾 Lote completo: ${basename(file)} (${currentBatch.length} questões)`);
      }
      currentBatch = [];
      lastSavedSize = 0;
      batchIndex++;
    }
  }
}

function finalFlush() {
  if (currentBatch.length === 0) return;
  const file = batchFile(batchIndex);
  if (safeWrite(currentBatch, file)) {
    console.log(`  💾 Salvo ${basename(file)} (${currentBatch.length} questões)`);
  }
}

// ─── Main ─────────────────────────────────────────────────────────────────────

const MODEL_KEYS: ModelKey[] = ["M1", "M2", "M3", "M4", "M5", "M6", "M7", "M8", "M9"];

async function sleep(ms: number) { return new Promise(r => setTimeout(r, ms)); }

async function main() {
  if (!GEMINI_API_KEY) {
    console.error("❌ GEMINI_API_KEY não definida. Execute: $env:GEMINI_API_KEY='sua-chave' (PowerShell) ou export GEMINI_API_KEY='...' (bash)");
    process.exit(1);
  }

  mkdirSync(OUTPUT_DIR, { recursive: true });
  const progress = loadProgress();
  loadExistingBatches();

  const genAI = new GoogleGenerativeAI(GEMINI_API_KEY);
  const model = genAI.getGenerativeModel({
    model: MODEL_NAME,
    systemInstruction: "Você é especialista em elaboração de questões para concursos e exames de Medicina Veterinária no Brasil. Retorne SEMPRE e SOMENTE um JSON válido (array), sem markdown, sem blocos de código, sem texto extra.",
    generationConfig: {
      // @ts-ignore — thinkingConfig não está nos tipos ainda
      thinkingConfig: { thinkingBudget: 0 },
    },
  });

  let totalCalls = 0;
  const totalExpected = CURRICULUM.length * MODEL_KEYS.length * ROUNDS;
  let errors = 0;

  console.log(`\n🚀 VetRank — Gerador de Questões`);
  console.log(`   ${CURRICULUM.length} disciplinas × ${MODEL_KEYS.length} modelos × ${ROUNDS} rounds = ${totalExpected} chamadas`);
  console.log(`   Já concluídas: ${progress.done.length} | Gerado: ${progress.totalGenerated} questões`);
  console.log(`   💰 Orçamento: ${formatCost(progress.totalCostUsd)} gastos de ${formatCost(BUDGET_USD)} limite\n`);

  for (const subject of CURRICULUM) {
    for (let round = 0; round < ROUNDS; round++) {
      for (const modelKey of MODEL_KEYS) {
        const key = `${subject.subjectId}_${modelKey}_${round}`;
        if (progress.done.includes(key)) continue;

        // ── Verificação de orçamento antes de cada chamada ──
        if (progress.totalCostUsd >= BUDGET_USD) {
          console.error(`\n🛑 ORÇAMENTO ATINGIDO: ${formatCost(progress.totalCostUsd)} (limite: ${formatCost(BUDGET_USD)})`);
          console.error(`   Aumente BUDGET_USD ou defina: $env:BUDGET_USD="50"`);
          console.error(`   Retome com: npx tsx scripts/generate-questions.ts`);
          checkpointBatch();
          saveProgress(progress);
          process.exit(0);
        }
        if (progress.totalCostUsd >= BUDGET_USD * WARN_AT_PCT) {
          console.warn(`\n⚠️  ATENÇÃO: ${Math.round((progress.totalCostUsd / BUDGET_USD) * 100)}% do orçamento usado (${formatCost(progress.totalCostUsd)} de ${formatCost(BUDGET_USD)})`);
        }

        totalCalls++;
        const remaining = totalExpected - progress.done.length;
        const pct = ((totalCalls / remaining) * 100).toFixed(1);
        const costStr = formatCost(progress.totalCostUsd);
        process.stdout.write(`[${pct}% | ${costStr}] ${subject.subjectName.slice(0, 35)} | ${modelKey} r${round + 1}... `);

        const prompt = buildPrompt(modelKey, subject.disciplineId, subject.disciplineName, subject.subjectId, subject.subjectName, round);

        let questions: unknown[] = [];
        let fatalError = false;
        for (let attempt = 0; attempt < 3; attempt++) {
          try {
            const result = await model.generateContent(prompt);

            // Custo real desta chamada via usageMetadata
            const usage       = result.response.usageMetadata;
            const inputTok    = usage?.promptTokenCount     ?? 1500;
            const outputTok   = usage?.candidatesTokenCount ?? 5000;
            const thinkingTok = (usage as any)?.thoughtsTokenCount ?? 0;
            const callCost    = calcCallCost(inputTok, outputTok, thinkingTok);
            progress.totalCostUsd += callCost;
            console.log(`in:${inputTok} out:${outputTok} think:${thinkingTok} custo:${formatCost(callCost)}`);

            const raw = result.response.text();
            const parsed = tryParseJson(raw);
            if (!parsed) {
              console.log(`⚠️  JSON inválido (tentativa ${attempt + 1})`);
              await sleep(2000);
              continue;
            }
            questions = (parsed as Record<string, unknown>[]).filter(isValidQuestion);
            break;
          } catch (err: unknown) {
            const msg = (err as Error).message ?? String(err);
            console.log(`\n⚠️  Erro API (tentativa ${attempt + 1}):\n   ${msg}\n`);

            // Cota esgotada — parar imediatamente
            if (msg.includes("429") || msg.toLowerCase().includes("quota") || msg.toLowerCase().includes("resource exhausted")) {
              console.error("🛑 COTA ESGOTADA — Verifique seu plano em https://aistudio.google.com");
              console.error(`   Progresso salvo. Retome com: npx tsx scripts/generate-questions.ts`);
              checkpointBatch();
              saveProgress(progress);
              process.exit(1);
            }

            // Erro de autenticação — parar
            if (msg.includes("401") || msg.includes("403") || msg.toLowerCase().includes("api key")) {
              console.error("🛑 ERRO DE AUTENTICAÇÃO — Verifique a GEMINI_API_KEY");
              process.exit(1);
            }

            // Erro temporário — aguarda e tenta de novo
            const waitMs = (attempt + 1) * 5000;
            console.log(`   Aguardando ${waitMs / 1000}s antes de tentar novamente...`);
            errors++;
            await sleep(waitMs);

            if (attempt === 2) {
              console.log(`❌ Desistindo após 3 tentativas — pulando esta combinação`);
              fatalError = true;
            }
          }
        }
        if (fatalError) {
          progress.done.push(key);
          saveProgress(progress);
          continue;
        }

        if (questions.length > 0) {
          appendQuestions(questions);
          progress.totalGenerated += questions.length;
          console.log(`✅ ${questions.length}q | gasto acumulado: ${formatCost(progress.totalCostUsd)}`);
        } else {
          console.log(`❌ 0 questões — pulando`);
          errors++;
        }

        progress.done.push(key);
        saveProgress(progress);

        await sleep(DELAY_MS);
      }
    }

    checkpointBatch();
  }

  finalFlush();

  console.log(`\n✅ Geração concluída!`);
  console.log(`   Total gerado : ${progress.totalGenerated} questões`);
  console.log(`   Custo total  : ${formatCost(progress.totalCostUsd)}`);
  console.log(`   Orçamento    : ${formatCost(BUDGET_USD)} (${Math.round((progress.totalCostUsd / BUDGET_USD) * 100)}% usado)`);
  console.log(`   Erros        : ${errors}`);
  console.log(`   Arquivos em  : ${OUTPUT_DIR}`);
  console.log(`\n📥 Importe os batch_*.json em Admin → Importar (um por vez).`);
}

main().catch(err => {
  console.error("Erro fatal:", err);
  process.exit(1);
});
