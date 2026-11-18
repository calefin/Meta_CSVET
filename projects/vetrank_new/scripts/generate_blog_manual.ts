import "dotenv/config";
import path from "path";
import fs from "fs";
import { fileURLToPath } from "url";
import { getDb } from "../server/db";
import { blogPosts } from "../drizzle/schema";
import { invokeLLM } from "../server/_core/llm";
import { extractDOIs, verifyAllDOIs, removeInvalidDOILinks } from "../server/doiVerifier";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const TRENDING_TOPICS = [
  "Avanços na Terapia de Células-Tronco para Cães e Gatos",
  "Como Diagnosticar e Tratar a Síndrome Respiratória dos Braquicefálicos",
  "O Impacto da Nutrição Clínica na Doença Renal Crônica Felina",
  "Novos Protocolos de Controle de Dor Transoperatória na Veterinária",
  "Principais Desafios no Tratamento da Leishmaniose Visceral Canina no Brasil",
  "Estratégias para Interpretação Rápida de Hemogramas em Casos de Emergência",
  "Avanços em Técnicas de Cirurgia Reconstrutiva para Pequenos Animais",
  "Gestão da Osteoartrite Canina: Terapias Multimodais Modernas",
  "Atualizações sobre Controle e Prevenção da Esporotricose Felina",
  "A Importância da Cardiologia Veterinária Preventiva em Animais Idosos"
];

async function verifyAndCorrectBlogPost(post: { title: string; summary: string; body: string; category: string; author: string; coverImageUrl?: string | null }): Promise<typeof post> {
  console.log(`[IA - Verificação] Auditando e verificando fatos para o artigo: "${post.title}"...`);
  try {
    const verificationResult = await invokeLLM({
      messages: [
        {
          role: "system",
          content: `Você é um comitê de revisão científica e conselho editorial de elite em Medicina Veterinária. Sua tarefa é auditar, verificar fatos e corrigir rigorosamente o artigo veterinário fornecido.

DIRETRIZES DE AUDITORIA E VERIFICAÇÃO DE FATOS:
1. **Verificação de Nomes e Referências:**
   - Certifique-se de que todos os nomes de pesquisadores, autores de diretrizes, periódicos, artigos e instituições citados sejam REAIS, EXISTENTES e historicamente precisos (ex: consensos da Anclivepa, guidelines do Ministério da Saúde/MAPA, artigos indexados na SciELO, PubMed ou Merck Manual).
   - Elimine qualquer nome de profissional fictício ou citações bibliográficas fantasiadas/alucinadas pela IA. Se uma citação não for verificável, substitua-a por uma referência real equivalente (ex: guidelines internacionais da LeishVet, consensos de esporotricose da Fiocruz ou artigos clássicos de autores reconhecidos da área) ou remova a menção ao nome falso, mantendo a informação de forma descritiva e impessoal.
2. **Precisão Clínica e Terapêutica:**
   - Audite detalhadamente todas as doses (mg/kg, UI/kg, etc.), intervalos de administração (SID, BID, TID, contínuo), princípios ativos, indicações de medicamentos e diagnósticos diferenciais descritos no texto e na tabela.
   - Corrija imediatamente qualquer dosagem ou conduta que divirja dos protocolos e bulas oficiais das indústrias farmacológicas de referência (como Vetnil, Merck Veterinary Manual ou as diretrizes brasileiras do MAPA).
3. **Tom e Clareza Técnica:**
   - Mantenha a clareza técnica refinada, o HTML semântico limpo e a estrutura do artigo original.

Retorne estritamente em formato JSON contendo as chaves corrigidas/verificadas: title, summary, body, category, author. Não adicione markdown externo.`
        },
        {
          role: "user",
          content: `Aqui está o artigo gerado para auditoria e correção:
${JSON.stringify(post, null, 2)}`
        }
      ],
      responseFormat: { type: "json_object" }
    });

    const responseText = verificationResult.choices[0].message.content;
    if (typeof responseText !== "string") {
      throw new Error("Resposta inválida do validador de IA.");
    }

    const verifiedPost = JSON.parse(responseText);
    console.log("[IA - Verificação] Artigo auditado e verificado com sucesso!");
    return verifiedPost;
  } catch (err) {
    console.error("[IA - Verificação] Erro no passo de auditoria. Utilizando post original.", err);
    return post;
  }
}

async function main() {
  console.log("=== INICIANDO GERAÇÃO MANUAL DE ARTIGO CIENTÍFICO (IA) ===");

  // Determine the topic: either passed as arguments or picked randomly
  const args = process.argv.slice(2);
  let topic = args.join(" ").trim();
  if (!topic) {
    topic = TRENDING_TOPICS[Math.floor(Math.random() * TRENDING_TOPICS.length)];
    console.log(`Nenhum tema fornecido. Escolhendo tema aleatório: "${topic}"`);
  } else {
    console.log(`Tema fornecido: "${topic}"`);
  }

  try {
    console.log("[IA] Solicitando geração de post para o Gemini...");
    const llmResult = await invokeLLM({
      messages: [
        {
          role: "system",
          content: "Você é o redator sênior do blog científico do VetRank, uma plataforma líder de estudos e simulados para Medicina Veterinária. Escreva um artigo técnico altamente informativo, em português do Brasil, voltado para estudantes e profissionais de veterinária se preparando para o ENADE/ENARE e residências.\n\nDIRETRIZES DE PESQUISA & CRIAÇÃO:\n1. Pesquise e embase o artigo com base em novidades, descobertas recentes e informações sólidas de Medicina Veterinária.\n2. Utilize como referências e bases de dados principais os seguintes portais de elite:\n   - Publicações científicas de peso: Revista Clínica Veterinária, BVS-Vet, SciELO / ABMVZ (para fundamentação anatômica, patológica e clínica real).\n   - Blogs industriais líderes do setor: Vetnil e PremieRVet (forte foco em farmacologia prática, terapêutica moderna e nutrição clínica).\n   - Portais de educação continuada e tendências: EnsinaVet, ConnectVets, VetVoz e Anclivepa-DF (para atualizações profissionais, exames de residência e condutas do dia a dia).\n   - Portais de benchmarking clínico: Vet Smart, Merck Veterinary Manual e IVIS (recomendações internacionais e bibliotecas acadêmicas).\n   - Contexto de tráfego Semrush (alta demanda popular de busca e SEO).\n\nO artigo deve ser didático, conter profundidade científica real e ser estruturado contendo: (1) uma introdução cativante sobre a relevância e atualização profissional do tema (com tag h2); (2) subtópicos aprofundados com tags h3 detalhando etiologia, diagnóstico diferencial e protocolos terapêuticos padrão-ouro, citando expressamente condutas validadas por esses portais (como consensos da Anclivepa ou guidelines farmacológicos da Vetnil); (3) listas com marcadores (ul, li) ilustrando 'pegadinhas comuns de provas' (ENARE/ENADE) sobre este assunto; e (4) uma tabela comparativa de parametrização clínica ou doses terapêuticas (tags table, tr, th, td) no final. Retorne estritamente em formato JSON contendo as chaves: title, summary (cerca de 150-180 caracteres otimizado para SEO), body (o corpo em HTML semântico limpo, sem markdown embutido no HTML, utilizando tags h2, h3, p, ul, li, table, tr, td, th), category (escolha um valor exato entre: 'scientific', 'news', 'contest', 'residency' que melhor classifique este artigo), e author (ex: 'Equipe de Ensino VetRank')."
        },
        {
          role: "user",
          content: `Escreva o artigo completo sobre o seguinte tema da Medicina Veterinária: "${topic}"`
        }
      ],
      responseFormat: { type: "json_object" }
    });

    const responseText = llmResult.choices[0].message.content;
    if (typeof responseText !== "string") {
      throw new Error("Resposta inválida da API do Gemini.");
    }

    const rawPost = JSON.parse(responseText);
    
    // Passo 2 — Auditoria científica via LLM
    let post = await verifyAndCorrectBlogPost(rawPost);

    // Passo 3 — Verificação real de DOIs contra CrossRef API
    const foundDois = extractDOIs(post.body);
    if (foundDois.length > 0) {
      console.log(`[DOI] Verificando ${foundDois.length} DOI(s) contra CrossRef API...`);
      const doiResults = await verifyAllDOIs(foundDois);
      const invalidDois = doiResults.filter(r => r.status === "not_found").map(r => r.doi);
      const validCount = doiResults.filter(r => r.status === "valid").length;
      console.log(`[DOI] Válidos: ${validCount}/${foundDois.length}${invalidDois.length ? ` — INVÁLIDOS: ${invalidDois.join(", ")}` : ""}`);

      if (invalidDois.length > 0) {
        post.body = removeInvalidDOILinks(post.body, invalidDois);
        console.log(`[DOI] ${invalidDois.length} referência(s) falsa(s) removida(s) do HTML.`);
      }
    } else {
      console.log("[DOI] Nenhum DOI encontrado no artigo.");
    }

    const slug = post.title
      .toLowerCase()
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .replace(/[^a-z0-9\s-]/g, "")
      .trim()
      .replace(/\s+/g, "-");

    console.log(`[IA] Artigo gerado: "${post.title}"`);
    console.log(`[IA] Slug: "${slug}"`);

    // 1. Save to database
    console.log("[DB] Conectando ao banco de dados...");
    const db = await getDb();
    if (!db) {
      console.warn("[DB] Banco de dados não disponível! Salvando apenas localmente no JSON.");
    } else {
      console.log("[DB] Salvando no banco de dados...");
      await db.insert(blogPosts).values({
        slug,
        title: post.title,
        body: post.body,
        summary: post.summary,
        category: post.category || "scientific",
        author: post.author || "Equipe VetRank",
        active: false, // Inactive by default, requires admin approval
      });
      console.log("[DB] Artigo salvo no banco de dados com sucesso como RASCUNHO (pendente de aprovação)!");
    }

    // 2. Append to blog_posts_seed.json
    console.log("[Local] Lendo blog_posts_seed.json...");
    const seedFilePath = path.resolve(__dirname, "../data/blog_posts_seed.json");
    let localPosts = [];
    if (fs.existsSync(seedFilePath)) {
      try {
        localPosts = JSON.parse(fs.readFileSync(seedFilePath, "utf8"));
      } catch (err) {
        console.error("[Local] Erro ao ler blog_posts_seed.json. Iniciando array vazio.", err);
      }
    }

    // Check if slug already exists in local posts
    const existingIndex = localPosts.findIndex((p: any) => p.slug === slug);
    const newPostData = {
      slug,
      title: post.title,
      summary: post.summary,
      author: post.author || "Equipe VetRank",
      category: post.category || "scientific",
      coverImageUrl: post.coverImageUrl || null,
      body: post.body,
      active: 0, // Inactive by default, requires admin approval
      createdAt: new Date().toISOString(),
      updatedAt: new Date().toISOString()
    };

    if (existingIndex >= 0) {
      console.log("[Local] Post já existia no JSON do seed local. Atualizando...");
      localPosts[existingIndex] = newPostData;
    } else {
      console.log("[Local] Adicionando novo post ao JSON do seed local...");
      localPosts.push(newPostData);
    }

    fs.writeFileSync(seedFilePath, JSON.stringify(localPosts, null, 2), "utf8");
    console.log("[Local] blog_posts_seed.json atualizado com sucesso!");

    console.log("=== PROCESSO CONCLUÍDO COM SUCESSO! ===");
    process.exit(0);
  } catch (err: any) {
    console.error("=== ERRO NA GERAÇÃO MANUAL DE BLOG POST ===");
    console.error(err);
    process.exit(1);
  }
}

main();
