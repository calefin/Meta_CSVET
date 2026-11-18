import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";
import * as dotenv from "dotenv";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
dotenv.config({ path: path.join(__dirname, "../.env.local") });

const DEEPSEEK_API_KEY = process.env.DEEPSEEK_API_KEY;
const OUTPUT_DIR = path.join(__dirname, "../data/deepseek_questions");

if (!DEEPSEEK_API_KEY) {
  console.warn("⚠️  WARNING: DEEPSEEK_API_KEY is not set. The script will fail when making API calls. Please add it to your .env.local file.");
}

// ─── Official curriculum structure ──────────────────────────────────────────
const CURRICULUM = [
  {
    disciplineId: 30001,
    disciplineName: "Ciências Biológicas e Ciclo Básico",
    subjects: [
      { id: 30001, name: "Anatomia Veterinária I e II (Sistêmica e Topográfica)" },
      { id: 30002, name: "Biologia Celular e Molecular" },
      { id: 30003, name: "Histologia Veterinária (Geral e Especial)" },
      { id: 30004, name: "Embriologia Veterinária" },
      { id: 30005, name: "Bioquímica Veterinária e Metabólica" },
      { id: 30006, name: "Biofísica Veterinária" },
      { id: 30007, name: "Fisiologia Veterinária I e II" },
      { id: 30008, name: "Genética e Evolução Animal" },
      { id: 30009, name: "Bioestatística e Metodologia Científica" },
    ]
  },
  {
    disciplineId: 30002,
    disciplineName: "Patobiologia e Agentes Agressores",
    subjects: [
      { id: 30010, name: "Microbiologia Veterinária (Bacteriologia e Virologia)" },
      { id: 30011, name: "Imunologia Veterinária" },
      { id: 30012, name: "Parasitologia Veterinária" },
      { id: 30013, name: "Patologia Geral e Técnicas de Necropsia" },
      { id: 30014, name: "Patologia Especial/Sistêmica Veterinária" },
      { id: 30015, name: "Farmacologia e Terapêutica Veterinária" },
      { id: 30016, name: "Toxicologia Veterinária" },
    ]
  },
  {
    disciplineId: 30003,
    disciplineName: "Clínica, Cirurgia e Reprodução Animal",
    subjects: [
      { id: 30017, name: "Semiologia Veterinária (Propedêutica)" },
      { id: 30018, name: "Patologia Clínica Veterinária (Diagnóstico Laboratorial)" },
      { id: 30019, name: "Diagnóstico por Imagem (Radiologia e Ultrassonografia)" },
      { id: 30020, name: "Técnica Cirúrgica Veterinária" },
      { id: 30021, name: "Anestesiologia Veterinária" },
      { id: 30022, name: "Clínica Médica e Cirúrgica de Pequenos Animais (Cães e Gatos)" },
      { id: 30023, name: "Clínica Médica e Cirúrgica de Ruminantes (Bovinos, Ovinos e Caprinos)" },
      { id: 30024, name: "Clínica Médica e Cirúrgica de Equídeos" },
      { id: 30025, name: "Fisiopatologia da Reprodução (Macho e Fêmea)" },
      { id: 30026, name: "Obstetrícia Veterinária e Biotecnologia da Reprodução" },
    ]
  },
  {
    disciplineId: 30004,
    disciplineName: "Medicina Preventiva, Saúde Pública e Inspeção",
    subjects: [
      { id: 30027, name: "Epidemiologia Veterinária" },
      { id: 30028, name: "Zoonoses e Saúde Pública" },
      { id: 30029, name: "Saneamento Ambiental e Planejamento em Saúde Animal" },
      { id: 30030, name: "Inspeção de Produtos de Origem Animal (Carnes, Leite, Ovos, Pescado e Mel)" },
      { id: 30031, name: "Tecnologia de Produtos de Origem Animal" },
      { id: 30032, name: "Higiene de Alimentos" },
    ]
  },
  {
    disciplineId: 30005,
    disciplineName: "Zootecnia e Produção Animal",
    subjects: [
      { id: 30033, name: "Introdução à Zootecnia" },
      { id: 30034, name: "Nutrição e Alimentação Animal" },
      { id: 30035, name: "Agrostologia e Forragicultura (Pastagens)" },
      { id: 30036, name: "Melhoramento Genético Animal" },
      { id: 30037, name: "Bovinocultura de Corte e de Leite" },
      { id: 30038, name: "Avicultura, Suinocultura e Equideocultura" },
      { id: 30039, name: "Aquicultura e Piscicultura" },
    ]
  },
  {
    disciplineId: 30006,
    disciplineName: "Ética, Humanidades e Áreas Complementares",
    subjects: [
      { id: 30040, name: "Deontologia, Ética e Bioética na Medicina Veterinária" },
      { id: 30041, name: "Bem-Estar Animal e Etologia" },
      { id: 30042, name: "Medicina Veterinária Legal (Perícia)" },
      { id: 30043, name: "Administração e Planejamento Rural" },
      { id: 30044, name: "Extensão Rural e Sociologia" },
      { id: 30045, name: "Medicina de Animais Selvagens e Pets Exóticos" },
    ]
  }
];

//latencies & concurrency controls
const CONCURRENCY = 5;
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

class Semaphore {
  constructor(max) {
    this.max = max;
    this.count = 0;
    this.queue = [];
  }
  async acquire() {
    if (this.count < this.max) {
      this.count++;
      return;
    }
    await new Promise((resolve) => this.queue.push(resolve));
    this.count++;
  }
  release() {
    this.count--;
    if (this.queue.length > 0) {
      const next = this.queue.shift();
      next();
    }
  }
}

// ─── Prompts definition ──────────────────────────────────────────────────────
function buildPrompt(modelId, count, topic, discId, subjId) {
  let promptText = `Você é um professor universitário brasileiro de medicina veterinária, especialista em elaboração de questões de alta complexidade no nível do ENADE, CFMV e concursos públicos.
Gere exatamente ${count} questões no modelo **${modelId}** sobre o seguinte tema:

TÓPICO: ${topic}
disciplineId (Grande Área): ${discId}
subjectId (Disciplina): ${subjId}

`;

  const commonRules = `
REGRAS OBRIGATÓRIAS DE ESTRUTURA E VALIDAÇÃO:
1. Todos os campos de texto devem estar em português do Brasil (textPt, explanationPt, options.textPt). Não inclua traduções para o inglês.
2. Defina "author": "VetRank", "isPremium": true, "status": "approved", "year": 2024.
3. Escolha dificuldades variadas ("easy", "medium", "hard").
4. Apenas UMA alternativa correta por questão, definida em correctOption (A, B, C, D ou E).
5. O campo options deve conter exatamente 5 opções (A, B, C, D, E) com IDs correspondentes em maiúsculo.
6. A justificativa (explanationPt) deve explicar de forma aprofundada o porquê de a alternativa correta ser verdadeira e porque cada distrator está errado.
`;

  switch (modelId) {
    case "M1":
      promptText += `
**Modelo M1 — Resposta Única (Pergunta Direta)**
- O enunciado deve ser uma pergunta direta ou um comando claro terminando com "?" ou contendo "Assinale a alternativa CORRETA sobre...".
- Crie distratores realistas baseados em erros comuns.
- questionType: "multiple_choice", questionModel: "enade"

${commonRules}
Retorne no formato JSON requerido.
`;
      break;

    case "M2":
      promptText += `
**Modelo M2 — Afirmação Incompleta**
- O enunciado DEVE terminar com ":" ou "..." (uma frase incompleta).
- As alternativas A-E devem completar sintática e gramaticalmente a frase do enunciado, sendo que apenas uma se torna factual e conceitualmente correta.
- questionType: "multiple_choice", questionModel: "enade"

${commonRules}
Retorne no formato JSON requerido.
`;
      break;

    case "M3":
      promptText += `
**Modelo M3 — Resposta Múltipla / Combinações (Assertivas I–IV)**
- O enunciado (textPt) DEVE conter de 3 a 4 proposições/assertivas numeradas em algarismos romanos (I, II, III, IV).
- Exemplo no textPt: "Sobre [tema], analise as seguintes assertivas:\\n\\nI. [proposição 1]\\nII. [proposição 2]\\nIII. [proposição 3]\\n\\nSão CORRETAS apenas:"
- As alternativas A-E devem ser combinações dessas assertivas (ex: "Apenas I e II.", "I, II e III.").
- Nunca utilize "Todas as alternativas estão corretas" ou "Nenhuma".
- Explique detalhadamente em explanationPt por que cada assertiva é Verdadeira ou Falsa.
- questionType: "complex_multiple_choice", questionModel: "enade"

${commonRules}
Retorne no formato JSON requerido.
`;
      break;

    case "M4":
      promptText += `
**Modelo M4 — Foco Negativo (EXCETO / NÃO / INCORRETO)**
- O enunciado DEVE solicitar o item INCORRETO. Destaque os termos como "EXCETO", "NÃO" ou "INCORRETO" em letras maiúsculas.
- 4 alternativas devem ser perfeitamente verdadeiras; o gabarito (correctOption) deve ser a única alternativa que contém um erro plausível.
- questionType: "multiple_choice", questionModel: "enade"

${commonRules}
Retorne no formato JSON requerido.
`;
      break;

    case "M5":
      promptText += `
**Modelo M5 — Asserção–Razão (PORQUE)**
- O enunciado (textPt) deve ter exatamente este formato:
  "ASSERÇÃO I: [proposição 1].\\n\\nPORQUE\\n\\nASSERÇÃO II: [proposição 2].\\n\\nA respeito das asserções acima, assinale a alternativa CORRETA:"
- Adicione campos no nível raiz do objeto:
  - "assertion1": "texto da asserção I"
  - "assertion2": "texto da asserção II"
  - "a1Verdadeira": true/false
  - "a2Verdadeira": true/false
  - "relacaoCausal": true/false
- As 5 alternativas em 'options' devem ser EXATAMENTE estas, sem mudar uma única vírgula:
  - A) As duas asserções são verdadeiras e a segunda é uma justificativa correta da primeira.
  - B) As duas asserções são verdadeiras, mas a segunda não é uma justificativa correta da primeira.
  - C) A primeira asserção é verdadeira e a segunda é falsa.
  - D) A primeira asserção é falsa e a segunda é verdadeira.
  - E) As duas asserções são falsas.
- O correctOption deve condizer com as asserções (Exemplo: se a1 é true, a2 é true e há relação causal, correctOption é "A").
- questionType: "assertion_reason", questionModel: "assertion_reason"

${commonRules}
Retorne no formato JSON requerido.
`;
      break;

    case "M6":
      promptText += `
**Modelo M6 — Lacuna (Cloze)**
- O enunciado (textPt) deve conter um trecho de texto técnico com uma lacuna marcada como [___] ou múltiplas lacunas marked como [1] e [2].
- Adicione a pergunta: "Assinale a alternativa que preenche CORRETAMENTE a(s) lacuna(s) acima:"
- As alternativas A-E representam os termos que devem preencher as lacunas na ordem correta (ex: "fisiológica — patológica — metabólica").
- questionType: "cloze", questionModel: "enade"

${commonRules}
Retorne no formato JSON requerido.
`;
      break;

    case "M7":
      promptText += `
**Modelo M7 — Caso Clínico / Interpretação**
- O enunciado (textPt) DEVE começar com a tag "[CASO CLÍNICO]" ou "[INTERPRETAÇÃO DE DADOS]".
- Apresente uma situação realista rica em detalhes: espécie, raça, idade, sintomatologia, resultados de exames complementares e valores de referência.
- Faça uma pergunta de interpretação ou conduta terapêutica/diagnóstica complexa com base no caso.
- Dificuldade ideal: "hard" ou "very_hard".
- questionType: "clinical_case" ou "interpretation", questionModel: "enade"

${commonRules}
Retorne no formato JSON requerido.
`;
      break;

    case "M8":
      promptText += `
**Modelo M8 — Associação / Correspondência (Relacionar Colunas)**
- O enunciado (textPt) DEVE conter duas colunas e um comando de associação.
- Formato do textPt:
  "Relacione os itens da Coluna I com os respectivos da Coluna II:\\n\\nCOLUNA I\\n1. [item 1]\\n2. [item 2]\\n3. [item 3]\\n4. [item 4]\\n\\nCOLUNA II\\na) [descrição a]\\nb) [descrição b]\\nc) [descrição c]\\nd) [descrição d]\\ne) [descrição e - distrator extra]\\n\\nA associação CORRETA é:"
- As alternativas A-E devem conter correspondências no formato: "1-b / 2-a / 3-d / 4-c."
- Apenas uma alternativa tem a correspondência 100% correta.
- questionType: "matching", questionModel: "enade"

${commonRules}
Retorne no formato JSON requerido.
`;
      break;

    case "M9":
      promptText += `
**Modelo M9 — Ordenação / Sequência**
- O enunciado (textPt) deve conter etapas de um procedimento cirúrgico, protocolo clínico ou ciclo biológico em ordem aleatória, rotulados de I a V.
- Exemplo: "Assinale a sequência CORRETA das etapas de...:\\n\\nI. [etapa A]\\nII. [etapa B]\\nIII. [etapa C]"
- As alternativas A-E devem conter as ordens possíveis separadas por travessões (ex: "III – I – IV – II – V.").
- A sequência correta deve ser consensual e baseada na literatura veterinária.
- questionType: "ordering", questionModel: "enade"

${commonRules}
Retorne no formato JSON requerido.
`;
      break;

    case "M10":
      promptText += `
**Modelo M10 — Alternativas Constantes (Bloco de Questões)**
- O prompt deve gerar um lote de 4 questões vinculadas ao mesmo caso clínico.
- Cada uma das 4 questões deve possuir o mesmo grupoId (ex: "BLOCO_SUBJ_" + subjId + "_" + Date.now()) e posicaoBloco (1, 2, 3 e 4 respectivamente).
- As 5 alternativas (options) devem ser EXATAMENTE IDÊNTICAS para as 4 questões do bloco (ex: 5 diagnósticos diferenciais possíveis).
- No entanto, cada uma das 4 questões tem um enunciado específico e um correctOption diferente (ou seja, cada caso foca em um diagnóstico diferente do grupo de opções).
- questionType: "multiple_choice", questionModel: "enade"

${commonRules}
Retorne no formato JSON requerido.
`;
      break;
  }

  // Define JSON structure output
  promptText += `
Retorne APENAS um objeto JSON válido contendo uma lista de questões sob a chave "questions":
{
  "questions": [
    {
      "disciplineId": ${discId},
      "subjectId": ${subjId},
      "difficulty": "medium",
      "year": 2024,
      "questionType": "...",
      "modelId": "${modelId}",
      "questionModel": "...",
      "author": "VetRank",
      "banca": "CFMV",
      "textPt": "...",
      "options": [
        { "id": "A", "textPt": "..." },
        { "id": "B", "textPt": "..." },
        { "id": "C", "textPt": "..." },
        { "id": "D", "textPt": "..." },
        { "id": "E", "textPt": "..." }
      ],
      "correctOption": "...",
      "explanationPt": "...",
      "isPremium": true,
      "status": "approved",
      // Campos "assertion1", "assertion2", "a1Verdadeira", "a2Verdadeira" e "relacaoCausal"
      // são EXCLUSIVOS do modelo M5 (Asserção-Razão). NÃO inclua esses campos (ou envie null)
      // para questões de qualquer outro modelo, incluindo M7 (Caso Clínico/Interpretação).
      // Adicionar apenas se questionType === "assertion_reason":
      "assertion1": "...",
      "assertion2": "...",
      "a1Verdadeira": true,
      "a2Verdadeira": true,
      "relacaoCausal": true,
      // Adicionar campos extras para M10 se aplicável:
      "grupoId": "...",
      "posicaoBloco": 1
    }
  ]
}

NÃO use blocos de código markdown (\`\`\`json). Retorne estritamente a string JSON crua.
`;

  return promptText;
}

// ─── DeepSeek API Call ────────────────────────────────────────────────────────
async function callDeepSeek(prompt, retries = 5) {
  const url = "https://api.deepseek.com/chat/completions";
  for (let attempt = 1; attempt <= retries; attempt++) {
    try {
      const response = await fetch(url, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${DEEPSEEK_API_KEY}`,
        },
        body: JSON.stringify({
          model: "deepseek-chat",
          messages: [
            {
              role: "system",
              content: "You are a professional Portuguese veterinary professor database generator. Always respond with pure JSON only."
            },
            {
              role: "user",
              content: prompt
            }
          ],
          temperature: 0.7,
          response_format: { type: "json_object" }
        })
      });

      if (!response.ok) {
        const errText = await response.text();
        throw new Error(`API Error: ${response.status} - ${errText}`);
      }

      const resJson = await response.json();
      const content = resJson.choices?.[0]?.message?.content;
      if (!content) throw new Error("Empty content in response choices");

      // Parse JSON
      const parsed = JSON.parse(content.trim());
      if (!parsed || !Array.isArray(parsed.questions)) {
        throw new Error("Missing 'questions' array in JSON");
      }
      return parsed.questions;
    } catch (error) {
      console.warn(`    ⚠️  [Attempt ${attempt}/${retries}] failed: ${error.message}`);
      if (attempt < retries) {
        const delay = 5000 * Math.pow(2, attempt - 1);
        console.log(`    Waiting ${delay / 1000}s before retrying...`);
        await sleep(delay);
      } else {
        throw error;
      }
    }
  }
  return [];
}

// ─── Main Orchestrator ───────────────────────────────────────────────────────
async function main() {
  console.log("🚀 Starting VetRank DeepSeek Bulk Question Generator...");

  if (!DEEPSEEK_API_KEY) {
    console.error("❌ ERROR: DEEPSEEK_API_KEY is not configured. Exit.");
    process.exit(1);
  }

  fs.mkdirSync(OUTPUT_DIR, { recursive: true });

  // Define batches required per subject
  // We want to generate ~358 questions per subject to exceed 15k+ total (45 subjects * 358 = 16,110)
  // Let's break this down into multiple small batches of questions of a specific model type:
  const batchPlans = [
    { modelId: "M1", count: 10, repeat: 10 }, // 100 Q
    { modelId: "M2", count: 10, repeat: 5 },  // 50 Q
    { modelId: "M3", count: 10, repeat: 5 },  // 50 Q
    { modelId: "M4", count: 10, repeat: 3 },  // 30 Q
    { modelId: "M5", count: 10, repeat: 3 },  // 30 Q
    { modelId: "M6", count: 10, repeat: 2 },  // 20 Q
    { modelId: "M7", count: 10, repeat: 3 },  // 30 Q
    { modelId: "M8", count: 10, repeat: 2 },  // 20 Q
    { modelId: "M9", count: 10, repeat: 2 },  // 20 Q
    { modelId: "M10", count: 4, repeat: 2 }   // 8 Q (2 blocks of 4)
  ];

  // Flat list of all jobs we need to run
  const jobs = [];

  for (const disc of CURRICULUM) {
    for (const subj of disc.subjects) {
      // Create folder for subject
      const subjectDir = path.join(OUTPUT_DIR, `subject_${subj.id}`);
      fs.mkdirSync(subjectDir, { recursive: true });

      for (const plan of batchPlans) {
        for (let r = 0; r < plan.repeat; r++) {
          const filename = `${plan.modelId}_batch_${r}.json`;
          const filePath = path.join(subjectDir, filename);

          // Only push to job queue if file does not exist (enables resumability)
          if (!fs.existsSync(filePath)) {
            jobs.push({
              discId: disc.disciplineId,
              discName: disc.disciplineName,
              subjId: subj.id,
              subjName: subj.name,
              modelId: plan.modelId,
              count: plan.count,
              filePath,
              description: `Subject ${subj.id} (${subj.name}) - ${plan.modelId} (Batch ${r})`
            });
          }
        }
      }
    }
  }

  console.log(`\n📊 Job queue summary:`);
  console.log(`  Total possible jobs (45 subjects * 37 batches): ${45 * 37}`);
  console.log(`  Remaining jobs to run: ${jobs.length}`);

  if (jobs.length === 0) {
    console.log("✅ All jobs are already completed! Nothing to generate.");
    return;
  }

  const sem = new Semaphore(CONCURRENCY);
  let finishedCount = 0;
  const totalJobs = jobs.length;
  const startTime = Date.now();

  const promises = jobs.map(async (job) => {
    await sem.acquire();
    try {
      console.log(`[Pending: ${totalJobs - finishedCount}] Starting: ${job.description}`);
      const prompt = buildPrompt(job.modelId, job.count, job.subjName, job.discId, job.subjId);
      
      const questions = await callDeepSeek(prompt);
      
      if (questions && questions.length > 0) {
        fs.writeFileSync(job.filePath, JSON.stringify(questions, null, 2), "utf8");
        finishedCount++;
        const elapsed = ((Date.now() - startTime) / 1000).toFixed(1);
        console.log(`✔️  Completed [${finishedCount}/${totalJobs}] in ${elapsed}s: ${job.description} (Saved ${questions.length} Qs)`);
      } else {
        console.error(`❌ FAILED (Empty array returned) for: ${job.description}`);
      }
    } catch (err) {
      console.error(`❌ EXCEPTION for ${job.description}: ${err.message}`);
    } finally {
      sem.release();
    }
  });

  await Promise.all(promises);
  console.log(`\n🎉 Generation complete! Total successfully executed jobs in this run: ${finishedCount}`);
}

main().catch((err) => {
  console.error("Fatal error in main generator process:", err);
});
