/**
 * Seed de dados de teste para produção.
 * Cria 20 questões de múltipla escolha em 2 disciplinas.
 *
 * A autenticação usa cookie de sessão (httpOnly), não Bearer token.
 *
 * Como obter o cookie:
 *   1. Acesse prof-aid.com logado (Google OAuth)
 *   2. F12 → Application → Cookies → https://prof-aid.com
 *   3. Copie o valor da linha "app_session_id"
 *
 * Uso:
 *   node scripts/seed-test-data.mjs --cookie VALOR_DO_COOKIE
 *
 * Ou com email e senha (se tiver cadastrado senha):
 *   node scripts/seed-test-data.mjs ascalefi@gmail.com "SUASENHA"
 */

const BASE_URL = process.env.PROD_URL || "https://prof-aid.com";
const COOKIE_NAME = "app_session_id";

// Suporte a --cookie direto (para usuários OAuth sem senha)
const cookieArgIdx = process.argv.indexOf("--cookie");
const DIRECT_COOKIE = cookieArgIdx !== -1 ? process.argv[cookieArgIdx + 1] : null;

const EMAIL    = DIRECT_COOKIE ? null : (process.env.PROD_EMAIL    || process.argv[2]);
const PASSWORD = DIRECT_COOKIE ? null : (process.env.PROD_PASSWORD || process.argv[3]);

if (!DIRECT_COOKIE && (!EMAIL || !PASSWORD)) {
  console.error("Uso com cookie (recomendado para login Google):");
  console.error("  node scripts/seed-test-data.mjs --cookie VALOR_DO_COOKIE");
  console.error("\nComo obter o cookie:");
  console.error("  1. Acesse prof-aid.com logado");
  console.error("  2. F12 → Application → Cookies → https://prof-aid.com");
  console.error("  3. Copie o valor de 'app_session_id'");
  console.error("\nOu com email e senha:");
  console.error("  node scripts/seed-test-data.mjs email@gmail.com 'SUASENHA'");
  process.exit(1);
}

// ── tRPC helper ─────────────────────────────────────────────────────────────

async function trpc(cookieValue, procedure, input) {
  const res = await fetch(`${BASE_URL}/api/trpc/${procedure}`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "Cookie": `${COOKIE_NAME}=${cookieValue}`,
    },
    body: JSON.stringify({ json: input }),
  });
  const text = await res.text();
  let data;
  try { data = JSON.parse(text); } catch { throw new Error(`Non-JSON response: ${text.slice(0, 200)}`); }
  if (!res.ok || data?.error) {
    throw new Error(`${procedure} failed: ${JSON.stringify(data?.error ?? data)}`);
  }
  return data?.result?.data?.json ?? data?.result?.data;
}

// ── Login ────────────────────────────────────────────────────────────────────

async function login() {
  const res = await fetch(`${BASE_URL}/api/auth/login`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ email: EMAIL, password: PASSWORD }),
  });
  const data = await res.json();
  if (!res.ok) throw new Error(`Login falhou: ${JSON.stringify(data)}`);

  // Extrair cookie app_session_id da resposta
  const setCookie = res.headers.get("set-cookie") ?? "";
  const match = setCookie.match(new RegExp(`${COOKIE_NAME}=([^;]+)`));
  if (!match) throw new Error("Login OK mas cookie de sessão não encontrado na resposta");

  console.log(`✓ Login OK — ${data.email}`);
  return match[1];
}

// ── Questões de teste ────────────────────────────────────────────────────────

const QUESTIONS = [
  // Disciplina: Biologia Celular
  { discipline: "Biologia Celular", subject: "Membrana Plasmática",
    statement: "Qual das seguintes afirmativas descreve corretamente a estrutura da membrana plasmática?",
    alternatives: { a: "Bicamada fosfolipídica com proteínas periféricas e integrais", b: "Monocamada de lipídios sem proteínas", c: "Estrutura rígida de celulose", d: "Bicamada protéica com lipídios periféricos", e: "Polissacarídeos organizados em dupla camada" },
    correctAnswer: "a", difficulty: "easy" },

  { discipline: "Biologia Celular", subject: "Mitocôndrias",
    statement: "A síntese de ATP nas mitocôndrias ocorre principalmente por qual processo?",
    alternatives: { a: "Glicólise", b: "Fosforilação oxidativa", c: "Fotossíntese", d: "Fermentação lática", e: "Beta-oxidação de ácidos graxos" },
    correctAnswer: "b", difficulty: "medium" },

  { discipline: "Biologia Celular", subject: "Ciclo Celular",
    statement: "Durante qual fase do ciclo celular ocorre a duplicação do DNA?",
    alternatives: { a: "Fase G1", b: "Fase G2", c: "Fase S", d: "Mitose", e: "Citocinese" },
    correctAnswer: "c", difficulty: "easy" },

  { discipline: "Biologia Celular", subject: "Divisão Celular",
    statement: "Em qual etapa da mitose os cromossomos se alinham na placa equatorial?",
    alternatives: { a: "Prófase", b: "Metáfase", c: "Anáfase", d: "Telófase", e: "Interfase" },
    correctAnswer: "b", difficulty: "easy" },

  { discipline: "Biologia Celular", subject: "Retículo Endoplasmático",
    statement: "O retículo endoplasmático rugoso difere do liso principalmente porque:",
    alternatives: { a: "É mais liso ao microscópio", b: "Possui ribossomos aderidos à membrana", c: "Produz lipídios para a membrana", d: "Está ligado ao aparelho de Golgi diretamente", e: "Armazena cálcio para contração muscular" },
    correctAnswer: "b", difficulty: "medium" },

  { discipline: "Biologia Celular", subject: "Lisossomos",
    statement: "Os lisossomos são organelas responsáveis principalmente por:",
    alternatives: { a: "Síntese de proteínas", b: "Produção de energia", c: "Digestão intracelular", d: "Fotossíntese", e: "Secreção de hormônios" },
    correctAnswer: "c", difficulty: "easy" },

  { discipline: "Biologia Celular", subject: "Núcleo Celular",
    statement: "O nucléolo, estrutura encontrada no interior do núcleo celular, é responsável por:",
    alternatives: { a: "Armazenar o DNA", b: "Síntese de rRNA e montagem dos ribossomos", c: "Controlar a entrada de moléculas no núcleo", d: "Duplicar os cromossomos", e: "Produzir ATP nuclear" },
    correctAnswer: "b", difficulty: "medium" },

  { discipline: "Biologia Celular", subject: "Transporte Celular",
    statement: "O transporte de glicose para o interior de células musculares após refeição é um exemplo de:",
    alternatives: { a: "Difusão simples", b: "Difusão facilitada mediada por transportador", c: "Endocitose", d: "Transporte ativo primário", e: "Exocitose" },
    correctAnswer: "b", difficulty: "hard" },

  { discipline: "Biologia Celular", subject: "Citoesqueleto",
    statement: "Os microtúbulos do citoesqueleto são formados principalmente por:",
    alternatives: { a: "Actina", b: "Miosina", c: "Tubulina", d: "Queratina", e: "Colágeno" },
    correctAnswer: "c", difficulty: "medium" },

  { discipline: "Biologia Celular", subject: "Apoptose",
    statement: "A apoptose é definida como:",
    alternatives: { a: "Morte celular acidental por lesão", b: "Divisão celular descontrolada", c: "Morte celular programada", d: "Fusão de duas células", e: "Processo de senescência celular irreversível" },
    correctAnswer: "c", difficulty: "easy" },

  // Disciplina: Fisiologia Animal
  { discipline: "Fisiologia Animal", subject: "Sistema Nervoso",
    statement: "O potencial de ação em neurônios é gerado principalmente pelo fluxo de:",
    alternatives: { a: "Íons K⁺ para dentro da célula", b: "Íons Na⁺ para dentro da célula", c: "Íons Ca²⁺ para fora da célula", d: "Íons Cl⁻ para dentro da célula", e: "Moléculas de glicose" },
    correctAnswer: "b", difficulty: "medium" },

  { discipline: "Fisiologia Animal", subject: "Sistema Cardiovascular",
    statement: "A frequência cardíaca é controlada principalmente pelo:",
    alternatives: { a: "Nó atrioventricular", b: "Nó sinoatrial", c: "Feixe de His", d: "Fibras de Purkinje", e: "Valvas cardíacas" },
    correctAnswer: "b", difficulty: "medium" },

  { discipline: "Fisiologia Animal", subject: "Sistema Respiratório",
    statement: "A hemoglobina satura mais rapidamente com oxigênio em ambientes com:",
    alternatives: { a: "Alta temperatura e baixo pH", b: "Baixa pressão parcial de O₂", c: "Alta pressão parcial de O₂", d: "Elevado CO₂", e: "Baixa temperatura e pH ácido" },
    correctAnswer: "c", difficulty: "hard" },

  { discipline: "Fisiologia Animal", subject: "Sistema Renal",
    statement: "A principal função da alça de Henle no néfron é:",
    alternatives: { a: "Filtração do plasma sanguíneo", b: "Secreção de H⁺", c: "Concentração da urina por gradiente osmótico", d: "Reabsorção de glicose", e: "Produção de eritropoietina" },
    correctAnswer: "c", difficulty: "hard" },

  { discipline: "Fisiologia Animal", subject: "Sistema Endócrino",
    statement: "O glucagon é um hormônio produzido pelo pâncreas que tem como principal função:",
    alternatives: { a: "Reduzir a glicemia", b: "Elevar a glicemia", c: "Estimular a produção de insulina", d: "Inibir a digestão", e: "Controlar a pressão arterial" },
    correctAnswer: "b", difficulty: "easy" },

  { discipline: "Fisiologia Animal", subject: "Contração Muscular",
    statement: "O deslizamento dos filamentos de actina sobre os de miosina durante a contração muscular depende de:",
    alternatives: { a: "Sódio e potássio", b: "Ferro e zinco", c: "Cálcio e ATP", d: "Glicose e oxigênio", e: "Fósforo e magnésio" },
    correctAnswer: "c", difficulty: "medium" },

  { discipline: "Fisiologia Animal", subject: "Sistema Digestório",
    statement: "A maior parte da absorção de nutrientes no trato gastrointestinal ocorre no:",
    alternatives: { a: "Estômago", b: "Duodeno", c: "Intestino delgado (jejuno e íleo)", d: "Intestino grosso", e: "Esôfago" },
    correctAnswer: "c", difficulty: "easy" },

  { discipline: "Fisiologia Animal", subject: "Termorregulação",
    statement: "Animais homeotérmicos são aqueles que:",
    alternatives: { a: "Têm temperatura variável conforme o ambiente", b: "Mantêm temperatura corporal constante independente do ambiente", c: "Dependem do sol para regular a temperatura", d: "São exclusivamente aquáticos", e: "Não possuem mecanismos de troca de calor" },
    correctAnswer: "b", difficulty: "easy" },

  { discipline: "Fisiologia Animal", subject: "Sistema Imune",
    statement: "Os linfócitos T citotóxicos (CD8+) atuam principalmente:",
    alternatives: { a: "Produzindo anticorpos", b: "Destruindo células infectadas por vírus", c: "Ativando células B", d: "Produzindo histamina", e: "Fagocitando bactérias" },
    correctAnswer: "b", difficulty: "hard" },

  { discipline: "Fisiologia Animal", subject: "Sistema Nervoso Autônomo",
    statement: "A ativação do sistema nervoso parassimpático causa:",
    alternatives: { a: "Aumento da frequência cardíaca", b: "Dilatação das pupilas", c: "Diminuição da frequência cardíaca", d: "Broncodilatação", e: "Inibição da peristalse" },
    correctAnswer: "c", difficulty: "medium" },
];

// ── Script principal ──────────────────────────────────────────────────────────

async function main() {
  console.log(`\n🚀 ProvaGen — Seed de dados de teste`);
  console.log(`   Servidor: ${BASE_URL}\n`);

  const token = DIRECT_COOKIE ?? await login();
  const createdIds = [];

  // 1. Criar questões
  console.log(`\n📝 Criando ${QUESTIONS.length} questões de teste...\n`);
  for (const q of QUESTIONS) {
    try {
      const result = await trpc(token, "questions.create", {
        statement: q.statement,
        discipline: q.discipline,
        subject: q.subject,
        difficulty: q.difficulty,
        tags: ["teste"],
        content: {
          type: "multiple_choice",
          alternatives: q.alternatives,
          correctAnswer: q.correctAnswer,
        },
      });
      const id = result?.id ?? result?.insertId ?? result;
      createdIds.push(id);
      console.log(`  ✓ Q${createdIds.length} [${q.discipline}] — ${q.statement.slice(0, 55)}...`);
    } catch (err) {
      console.error(`  ✗ Falha ao criar questão: ${err.message}`);
    }
  }

  console.log(`\n✅ ${createdIds.length} questões criadas com IDs: ${createdIds.slice(0, 8).join(", ")}...`);
  console.log(`\n💡 Próximos passos:`);
  console.log(`   1. Acesse prof-aid.com e confirme as questões em "Banco de Questões"`);
  console.log(`   2. Crie uma nova Prova usando essas questões`);
  console.log(`   3. Gere o gabarito com 2 versões para testar a randomização`);
  console.log(`   4. Baixe o gabarito em PNG/PDF para validar o layout\n`);
}

main().catch(err => {
  console.error("\n❌ Erro fatal:", err.message);
  process.exit(1);
});
