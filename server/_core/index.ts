import "dotenv/config";
import express, { type Request, type Response, type NextFunction } from "express";
import { createServer } from "http";
import { timingSafeEqual } from "crypto";
import net from "net";
import path from "path";
import { fileURLToPath } from "url";
import { createExpressMiddleware } from "@trpc/server/adapters/express";
import { registerLocalAuthRoutes } from "./localAuth";
import { registerGoogleAuthRoutes } from "./googleAuth";
import { registerEmailAuthRoutes } from "./emailAuth";
import { registerStorageProxy } from "./storageProxy";
import { appRouter } from "../routers";
import { createContext } from "./context";
import { serveStatic, setupVite } from "./vite";
import { handleMPWebhook } from "../mpWebhook";
import { runExpiryEmailCheck } from "../routers/notifications";
import { sendDailyQuestionEmail, sendNewQuestionsDigestEmail } from "../email";
import { getRandomQuestions, getDb } from "../db";
import { blogPosts, courses, users, subjects } from "../../drizzle/schema";
import { eq, desc, isNotNull } from "drizzle-orm";
import { extractDOIs, verifyAllDOIs, removeInvalidDOILinks } from "../doiVerifier";
import { registerEbookDownload } from "../ebookDownload";
import { registerCertificateDownload } from "../certificateDownload";
import { registerConcursoPdfRoutes } from "../concursoPdf";
import { syncConcursosSeed, syncConcursos } from "../concursos";
import rateLimit, { ipKeyGenerator } from "express-rate-limit";
import { invokeLLM } from "./llm";
import fs from "fs";

// ─── Rate limiters ─────────────────────────────────────────────────────────────

/** General API limiter: 200 req / 1 min per IP */
const apiLimiter = rateLimit({
  windowMs: 60 * 1000,
  max: 200,
  standardHeaders: true,
  legacyHeaders: false,
  message: { error: "Too many requests. Please slow down." },
  keyGenerator: (req) => ipKeyGenerator(req.ip ?? "unknown"),
});

/** Strict question-scraping limiter: 60 req / 1 min per IP */
const questionLimiter = rateLimit({
  windowMs: 60 * 1000,
  max: 60,
  standardHeaders: true,
  legacyHeaders: false,
  message: { error: "Question rate limit exceeded. Please wait before fetching more questions." },
  keyGenerator: (req) => ipKeyGenerator(req.ip ?? "unknown"),
});

/** Strict authentication route limiter: 5 req / 1 min per IP to prevent brute-forcing */
const authLimiter = rateLimit({
  windowMs: 60 * 1000,
  max: 5,
  standardHeaders: true,
  legacyHeaders: false,
  message: { error: "Muitas tentativas de autenticação. Por favor, aguarde 1 minuto antes de tentar novamente." },
  keyGenerator: (req) => ipKeyGenerator(req.ip ?? "unknown"),
});

/** Detect bulk scraping: block IPs that fetch >30 questions in 10 seconds */
const scrapeDetector = (() => {
  const counts = new Map<string, { count: number; resetAt: number }>();
  return (req: Request, res: Response, next: NextFunction) => {
    const ip = req.ip ?? "unknown";
    const now = Date.now();
    const entry = counts.get(ip);
    if (!entry || now > entry.resetAt) {
      counts.set(ip, { count: 1, resetAt: now + 10_000 });
    } else {
      entry.count++;
      if (entry.count > 30) {
        res.status(429).json({ error: "Suspicious activity detected. Access temporarily blocked." });
        return;
      }
    }
    next();
  };
})();

function isPortAvailable(port: number): Promise<boolean> {
  return new Promise(resolve => {
    const server = net.createServer();
    server.listen(port, () => {
      server.close(() => resolve(true));
    });
    server.on("error", () => resolve(false));
  });
}

async function findAvailablePort(startPort: number = 3000): Promise<number> {
  for (let port = startPort; port < startPort + 20; port++) {
    if (await isPortAvailable(port)) {
      return port;
    }
  }
  throw new Error(`No available port found starting from ${startPort}`);
}

async function runMigrations() {
  if (!process.env.DATABASE_URL) return;
  try {
    const mysql = await import("mysql2/promise");
    const fs = await import("fs");
    const { createHash } = await import("crypto");

    const url = new URL(process.env.DATABASE_URL.replace(/\?.*$/, ""));
    const conn = await mysql.default.createConnection({
      host: url.hostname,
      port: url.port ? Number(url.port) : 3306,
      user: decodeURIComponent(url.username),
      password: decodeURIComponent(url.password),
      database: url.pathname.replace(/^\//, ""),
    });

    const __dir = path.dirname(fileURLToPath(import.meta.url));
    const migrationsFolder = path.resolve(__dir, "../drizzle");

    await conn.execute(`
      CREATE TABLE IF NOT EXISTS \`__drizzle_migrations\` (
        id SERIAL AUTO_INCREMENT PRIMARY KEY,
        hash text NOT NULL,
        created_at bigint
      )
    `);

    const [rows] = await conn.execute("SELECT hash FROM `__drizzle_migrations`");
    const applied = new Set((rows as any[]).map((r: any) => r.hash as string));

    const journalPath = path.join(migrationsFolder, "meta/_journal.json");
    const journal = JSON.parse(fs.default.readFileSync(journalPath, "utf8"));

    for (const entry of journal.entries) {
      const sqlFile = path.join(migrationsFolder, `${entry.tag}.sql`);
      if (!fs.default.existsSync(sqlFile)) continue;

      const sql = fs.default.readFileSync(sqlFile, "utf8");
      const hash = createHash("sha256").update(sql).digest("hex");
      if (applied.has(hash)) continue;

      const stmts = sql.split("--> statement-breakpoint").map((s: string) => s.trim()).filter(Boolean);
      for (const stmt of stmts) {
        try {
          await conn.execute(stmt);
        } catch (err: any) {
          // 1050 table exists, 1060 duplicate column, 1061 duplicate key — schema already correct
          if (err.errno === 1050 || err.errno === 1060 || err.errno === 1061) continue;
          throw err;
        }
      }

      await conn.execute("INSERT INTO `__drizzle_migrations` (hash, created_at) VALUES (?, ?)", [hash, Date.now()]);
      console.log(`[DB] Applied migration: ${entry.tag}`);
    }

    await conn.end();
    console.log("[DB] Migrations complete");
  } catch (err) {
    console.error("[DB] Migration error:", err);
  }
}

async function verifyAndCorrectBlogPost(post: { title: string; summary: string; body: string; category: string; author: string; coverImageUrl?: string | null }): Promise<typeof post> {
  console.log(`[Blog Verification] Fact-checking and verifying article: "${post.title}"...`);
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
    console.log("[Blog Verification] Article successfully verified and corrected.");
    return verifiedPost;
  } catch (err) {
    console.error("[Blog Verification] Error during fact-checking step. Falling back to original post.", err);
    return post;
  }
}

async function syncBlogPosts() {
  console.log("[Blog Sync] Starting blog posts synchronization...");
  try {
    const seedFilePath = path.resolve(import.meta.dirname, "../../data/blog_posts_seed.json");
    if (!fs.existsSync(seedFilePath)) {
      console.warn("[Blog Sync] Seed file not found at:", seedFilePath);
      return;
    }

    const db = await getDb();
    if (!db) {
      console.warn("[Blog Sync] Database connection not available. Skipping synchronization.");
      return;
    }

    const rawSeed = fs.readFileSync(seedFilePath, "utf8");
    const posts = JSON.parse(rawSeed);

    if (!Array.isArray(posts)) {
      console.error("[Blog Sync] Seed file is not a valid JSON array.");
      return;
    }

    console.log(`[Blog Sync] Found ${posts.length} posts in seed file. Synchronizing...`);

    let insertedCount = 0;
    let updatedCount = 0;

    for (const post of posts) {
      if (!post.slug) continue;

      const existing = await db
        .select()
        .from(blogPosts)
        .where(eq(blogPosts.slug, post.slug))
        .limit(1);

      const isActive = post.active === 1 || post.active === true || post.active === "1";

      if (existing.length > 0) {
        await db
          .update(blogPosts)
          .set({
            title: post.title,
            body: post.body,
            summary: post.summary,
            author: post.author || "Equipe VetRank",
            category: post.category || "scientific",
            coverImageUrl: post.coverImageUrl || null,
            active: isActive,
            updatedAt: post.updatedAt ? new Date(post.updatedAt) : new Date(),
          })
          .where(eq(blogPosts.slug, post.slug));
        updatedCount++;
      } else {
        await db.insert(blogPosts).values({
          slug: post.slug,
          title: post.title,
          body: post.body,
          summary: post.summary,
          author: post.author || "Equipe VetRank",
          category: post.category || "scientific",
          coverImageUrl: post.coverImageUrl || null,
          active: isActive,
          createdAt: post.createdAt ? new Date(post.createdAt) : new Date(),
          updatedAt: post.updatedAt ? new Date(post.updatedAt) : new Date(),
        });
        insertedCount++;
      }
    }

    console.log(`[Blog Sync] Synchronization finished. Inserted: ${insertedCount}, Updated: ${updatedCount}.`);
  } catch (err) {
    console.error("[Blog Sync] Critical error during blog post synchronization:", err);
  }
}

async function startServer() {
  await runMigrations();
  await syncBlogPosts();
  await syncConcursosSeed();
  // Sincronização automática de concursos em background (100% gratuita, zero IA)
  setTimeout(() => {
    syncConcursos(30).catch(err => console.warn("[Concursos AutoSync] erro:", err?.message));
  }, 15000);
  const app = express();
  const server = createServer(app);
  // Trust proxy headers (required for accurate IP detection behind load balancers)
  app.set("trust proxy", 1);

  // Configure body parser with larger size limit for file uploads
  app.use(express.json({ limit: "50mb" }));
  app.use(express.urlencoded({ limit: "50mb", extended: true }));

  // Rotinas agendadas (/api/scheduled/*) disparam e-mails em massa e auditorias: exigem o header
  // X-Cron-Secret igual a CRON_SECRET (.env). Sem CRON_SECRET configurado, ficam desativadas (fail-safe).
  // O crontab do VPS chama via http://127.0.0.1:3001 e o NPM também bloqueia o caminho para a internet.
  // app.use com path é case-insensitive no Express, igual ao roteamento — cobre /API/Scheduled também.
  app.use("/api/scheduled", (req: Request, res: Response, next: NextFunction) => {
    const expected = process.env.CRON_SECRET ?? "";
    const given = req.get("x-cron-secret") ?? "";
    if (expected.length < 16) {
      return res.status(503).json({ success: false, error: "CRON_SECRET não configurado" });
    }
    const a = Buffer.from(given), b = Buffer.from(expected);
    if (a.length !== b.length || !timingSafeEqual(a, b)) {
      return res.status(401).json({ success: false, error: "unauthorized" });
    }
    next();
  });

  // Redirect trailing slashes to non-trailing slashes (except '/' and API routes) with 301 Moved Permanently
  app.use((req, res, next) => {
    if (
      req.method === "GET" &&
      req.path.length > 1 &&
      req.path.endsWith("/") &&
      !req.path.startsWith("/api/")
    ) {
      const query = req.url.slice(req.path.length);
      const safePath = req.path.slice(0, -1) + query;
      return res.redirect(301, safePath);
    }
    next();
  });

  registerStorageProxy(app);
  registerGoogleAuthRoutes(app);   // Google OAuth (ativo quando GOOGLE_CLIENT_ID definido)
  app.use("/api/auth/email", authLimiter); // Protect email auth routes
  registerEmailAuthRoutes(app);    // Email/password auth (sempre disponível)
  if (process.env.NODE_ENV !== "production") {
    registerLocalAuthRoutes(app);  // Login local (apenas dev)
  }

  // Mercado Pago webhook — must be registered before tRPC
  app.post("/api/mp/webhook", handleMPWebhook);

  // Secure ebook download with PDF watermarking
  registerEbookDownload(app);
  // Certificate PDF generation
  registerCertificateDownload(app);
  // Concurso PDF guide & branded edital download
  registerConcursoPdfRoutes(app);

  // Flashcards: converte diariamente questões do banco em cards (biblioteca cresce sozinha)
  app.post("/api/scheduled/generate-flashcards", async (req, res) => {
    try {
      const { gerarFlashcardsDiario } = await import("../flashcards");
      const limite = Math.min(200, Math.max(10, Number(req.query.limite) || 80));
      res.json({ success: true, ...(await gerarFlashcardsDiario(limite)) });
    } catch (err: any) {
      console.error("[Flashcards] job failed:", err);
      res.status(500).json({ success: false, error: err.message });
    }
  });

  // ── Radar de Concursos ────────────────────────────────────────────────────
  // Coleta diária (máx. 15 notícias novas por execução, 1 a cada 3s).
  let lastConcursosSync = 0;
  app.post("/api/scheduled/sync-concursos", async (_req, res) => {
    if (Date.now() - lastConcursosSync < 10 * 60 * 1000) {
      res.status(429).json({ success: false, error: "Sincronização rodou há menos de 10 minutos." });
      return;
    }
    lastConcursosSync = Date.now();
    try {
      const { syncConcursos } = await import("../concursos");
      const r = await syncConcursos();
      if (r.inseridos > 0) {
        const { notifyAdmins } = await import("../adminNotify");
        notifyAdmins(`📢 ${r.inseridos} novo(s) concurso(s) de veterinária no Radar`,
          `<p>${r.inseridos} novo(s), ${r.atualizados} atualizado(s). Confira em <a href="https://www.vetrank.com.br/concursos">/concursos</a>.</p>`);
      }
      res.json({ success: true, ...r, erros: r.erros.length });
    } catch (err: any) {
      console.error("[Concursos] sync failed:", err);
      res.status(500).json({ success: false, error: err.message });
    }
  });

  // Post semanal do blog com os concursos abertos (rascunho → aprovação no Admin → Blog)
  app.post("/api/scheduled/concursos-weekly-post", async (_req, res) => {
    try {
      const { criarPostSemanalConcursos } = await import("../concursos");
      const r = await criarPostSemanalConcursos();
      if (r.criado) {
        const { notifyAdmins } = await import("../adminNotify");
        notifyAdmins("📝 Post semanal de concursos pronto para aprovar",
          `<p>${r.total} concursos abertos. Aprove em Admin → Blog (slug <b>${r.slug}</b>) para publicar.</p>`);
      }
      res.json({ success: true, ...r });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  app.get("/api/og/concursos-semana.png", async (_req, res) => {
    try {
      const { listarConcursos, fmtSalario } = await import("../concursos");
      const { renderCard } = await import("../shareCard");
      const abertos = (await listarConcursos({ limite: 200 })).filter(c => c.status === "aberto" || c.status === "vai_abrir");
      const sal = abertos.map(c => Number(c.salarioMax) || 0).filter(Boolean);
      const ufs = new Set(abertos.map(c => c.uf).filter(Boolean));
      const png = await renderCard(`semana:${abertos.length}:${new Date().toISOString().slice(0, 10)}`, {
        kicker: "Radar de Concursos · Veterinária e Zootecnia",
        title: `${abertos.length} concursos vigentes para Veterinária e Zootecnia`,
        subtitle: "Prazos, vagas, salários e simulados atualizados todos os dias",
        stats: [
          { label: "Estados", value: String(ufs.size) },
          { label: "Encerram em 7 dias", value: String(abertos.filter(c => c.status === "aberto" && (c.diasRestantes ?? 99) <= 7).length) },
          ...(sal.length ? [{ label: "Maior salário", value: fmtSalario(Math.max(...sal))! }] : []),
        ],
        footer: "vetrank.com.br/concursos",
      });
      res.setHeader("Content-Type", "image/png");
      res.setHeader("Cache-Control", "public, max-age=3600");
      res.end(png);
    } catch {
      res.redirect(302, "/og-image.png");
    }
  });

  app.get("/api/og/concursos-semana/story.png", async (_req, res) => {
    try {
      const { listarConcursos, fmtSalario } = await import("../concursos");
      const { renderStoryCard } = await import("../shareCard");
      const abertos = (await listarConcursos({ limite: 200 })).filter(c => c.status === "aberto" || c.status === "vai_abrir");
      const sal = abertos.map(c => Number(c.salarioMax) || 0).filter(Boolean);
      const ufs = new Set(abertos.map(c => c.uf).filter(Boolean));
      const png = await renderStoryCard(`semana-story:${abertos.length}:${new Date().toISOString().slice(0, 10)}`, {
        kicker: "Radar da Semana · Vet & Zoo",
        title: `${abertos.length} Concursos Abertos para Veterinária e Zootecnia`,
        subtitle: "Editais municipais, estaduais, federais e Forças Armadas em todo o Brasil",
        stats: [
          { label: "Estados com vagas", value: `${ufs.size} estados` },
          { label: "Maior remuneração", value: sal.length ? fmtSalario(Math.max(...sal))! : "Ver editais" },
          { label: "Últimos 7 dias", value: `${abertos.filter(c => c.status === "aberto" && (c.diasRestantes ?? 99) <= 7).length} editais` },
          { label: "Simulados prontos", value: "+73.800 questões" },
        ],
        footer: "Veja a lista completa e baixe os editais:",
        cta: "vetrank.com.br/concursos",
      });
      res.setHeader("Content-Type", "image/png");
      res.setHeader("Cache-Control", "public, max-age=3600");
      res.end(png);
    } catch {
      res.redirect(302, "/og-image.png");
    }
  });

  // Card 1200×630 para prévia de link (WhatsApp/Instagram/Twitter)
  app.get("/api/og/concurso/:slug.png", async (req, res) => {
    try {
      const { buscarConcursoPorSlug, fmtData, fmtSalario } = await import("../concursos");
      const c = await buscarConcursoPorSlug(req.params.slug);
      if (!c) { res.redirect(302, "/og-image.png"); return; }
      const { renderCard } = await import("../shareCard");
      const stats = [
        c.vagasVet ? { label: "Vagas", value: c.vagasVet } : null,
        c.salarioMax ? { label: "Salário até", value: fmtSalario(c.salarioMax)! } : null,
        c.banca ? { label: "Banca", value: c.banca } : null,
      ].filter(Boolean) as { label: string; value: string }[];
      const kickerStatus = c.status === "vai_abrir" ? "Edital publicado · Vai abrir" : c.status === "encerrado" ? "Prova em andamento" : "Inscrições abertas";
      const png = await renderCard(`concurso:${c.slug}:${c.status}`, {
        kicker: `${kickerStatus}${c.uf ? " · " + c.uf : ""}`,
        title: c.orgao,
        subtitle: (c.cargos as any[] | null)?.map(x => x.nome).slice(0, 2).join(" · ") || "Médico Veterinário / Zootecnista",
        stats,
        footer: c.inscricoesFim ? `Inscrições até ${fmtData(c.inscricoesFim)}` : "Confira o edital em vetrank.com.br",
      });
      res.setHeader("Content-Type", "image/png");
      res.setHeader("Cache-Control", "public, max-age=3600");
      res.end(png);
    } catch (err) {
      console.error("[OG concurso]", err);
      res.redirect(302, "/og-image.png");
    }
  });

  // Card 1080×1920 para Instagram Story / Status do WhatsApp
  app.get("/api/og/concurso/:slug/story.png", async (req, res) => {
    try {
      const { buscarConcursoPorSlug, fmtData, fmtSalario } = await import("../concursos");
      const c = await buscarConcursoPorSlug(req.params.slug);
      if (!c) { res.redirect(302, "/og-image.png"); return; }
      const { renderStoryCard } = await import("../shareCard");
      const kickerStatus = c.status === "vai_abrir" ? "VAI ABRIR" : c.status === "encerrado" ? "EM ANDAMENTO" : "INSCRIÇÕES ABERTAS";
      const stats = [
        { label: "Vagas contempladas", value: c.vagasVet || "Ver edital" },
        { label: "Remuneração até", value: fmtSalario(c.salarioMax) || "Consultar edital" },
        { label: "Prazo de inscrição", value: c.inscricoesFim ? `Até ${fmtData(c.inscricoesFim)}` : "Ver edital" },
        { label: "Banca organizadora", value: c.banca || "Ver edital" },
      ];
      const png = await renderStoryCard(`concurso:${c.slug}:${c.status}`, {
        kicker: `${kickerStatus}${c.uf ? " · " + c.uf : ""}`,
        title: c.orgao,
        subtitle: (c.cargos as any[] | null)?.map(x => x.nome).slice(0, 2).join(" · ") || "Médico Veterinário / Zootecnista",
        stats,
        footer: "Baixe o Guia do Edital e faça o Simulado:",
        cta: "vetrank.com.br/concursos",
      });
      res.setHeader("Content-Type", "image/png");
      res.setHeader("Cache-Control", "public, max-age=3600");
      res.end(png);
    } catch (err) {
      console.error("[Story concurso]", err);
      res.redirect(302, "/og-image.png");
    }
  });

  // Card 1080×1080 para Feed do Instagram
  app.get("/api/og/concurso/:slug/feed.png", async (req, res) => {
    try {
      const { buscarConcursoPorSlug, fmtData, fmtSalario } = await import("../concursos");
      const c = await buscarConcursoPorSlug(req.params.slug);
      if (!c) { res.redirect(302, "/og-image.png"); return; }
      const { renderFeedCard } = await import("../shareCard");
      const kickerStatus = c.status === "vai_abrir" ? "VAI ABRIR" : c.status === "encerrado" ? "EM ANDAMENTO" : "INSCRIÇÕES ABERTAS";
      const stats = [
        { label: "Vagas", value: c.vagasVet || "Ver edital" },
        { label: "Salário até", value: fmtSalario(c.salarioMax) || "Ver edital" },
        { label: "Inscrições até", value: fmtData(c.inscricoesFim) || "Ver edital" },
        { label: "Banca", value: c.banca || "Ver edital" },
      ];
      const png = await renderFeedCard(`concurso:${c.slug}:${c.status}`, {
        kicker: `${kickerStatus}${c.uf ? " · " + c.uf : ""}`,
        title: c.orgao,
        subtitle: (c.cargos as any[] | null)?.map(x => x.nome).slice(0, 2).join(" · ") || "Médico Veterinário / Zootecnista",
        stats,
        footer: "Baixe o Guia do Edital + Simulado Exclusivo no VetRank",
      });
      res.setHeader("Content-Type", "image/png");
      res.setHeader("Cache-Control", "public, max-age=3600");
      res.end(png);
    } catch (err) {
      console.error("[Feed concurso]", err);
      res.redirect(302, "/og-image.png");
    }
  });

  // Scheduled task endpoint: billing audit (MP x banco) — e-mail para admins se houver risco.
  // Resposta só com contagens: os detalhes têm e-mails de clientes e vão apenas por e-mail.
  let lastBillingAudit = 0;
  app.post("/api/scheduled/billing-audit", async (req, res) => {
    if (Date.now() - lastBillingAudit < 10 * 60 * 1000) {
      res.status(429).json({ success: false, error: "Auditoria rodou há menos de 10 minutos." });
      return;
    }
    lastBillingAudit = Date.now();
    try {
      const { runBillingAuditAndNotify } = await import("../billingAudit");
      const { findings, checked } = await runBillingAuditAndNotify({ alwaysEmail: req.query.always === "1" });
      res.json({
        success: true, checked,
        findings: findings.length,
        high: findings.filter(f => f.severity === "alta").length,
        byCheck: findings.reduce((acc: Record<number, number>, f) => { acc[f.check] = (acc[f.check] ?? 0) + 1; return acc; }, {}),
      });
    } catch (err: any) {
      console.error("[BillingAudit] failed:", err);
      res.status(500).json({ success: false, error: err.message });
    }
  });

  // Scheduled task endpoint: trigger expiry email check
  app.post("/api/scheduled/send-expiry-emails", async (_req, res) => {
    try {
      const result = await runExpiryEmailCheck();
      res.json({ success: true, ...result });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  // Scheduled task endpoint: daily question email to all registered users
  app.post("/api/scheduled/send-daily-question", async (_req, res) => {
    try {
      const db = await getDb();
      if (!db) throw new Error("Database not available");

      const [q] = await getRandomQuestions({ count: 1 });
      if (!q) throw new Error("No questions available");

      const subjectRow = q.subjectId
        ? await db.select({ namePt: subjects.namePt }).from(subjects).where(eq(subjects.id, q.subjectId)).limit(1)
        : [];
      const subjectName = subjectRow[0]?.namePt ?? null;

      const allUsers = await db
        .select({ email: users.email, name: users.name })
        .from(users)
        .where(isNotNull(users.email))
        .limit(10000);

      const options = (q.options ?? []) as Array<{ id?: string; letter?: string; textPt?: string; text?: string }>;
      let sent = 0, failed = 0, firstError: string | null = null;
      const startedAt = Date.now();

      // O ritmo real é controlado pelo pool SMTP de envio em massa (server/email.ts); o lote só
      // limita quantas mensagens ficam enfileiradas ao mesmo tempo.
      const BATCH = 20;
      for (let i = 0; i < allUsers.length; i += BATCH) {
        const batch = allUsers.slice(i, i + BATCH);
        await Promise.all(
          batch.map(async (u) => {
            if (!u.email) return;
            try {
              await sendDailyQuestionEmail(u.email, u.name ?? "Veterinário(a)", {
                textPt: q.textPt,
                options,
                correctOption: q.correctOption,
                subjectName,
                explanationPt: q.explanationPt ?? null,
              });
              sent++;
            } catch (e: any) {
              failed++;
              firstError ??= `${e?.code ?? ""} ${e?.message ?? e}`.trim().slice(0, 200);
            }
          })
        );
      }

      const seconds = Math.round((Date.now() - startedAt) / 1000);
      console.log(`[daily-question] sent=${sent} failed=${failed} total=${allUsers.length} em ${seconds}s${firstError ? ` | 1º erro: ${firstError}` : ""}`);
      res.json({ success: true, at: new Date().toISOString(), sent, failed, total: allUsers.length, seconds, firstError });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  // Scheduled task endpoint: weekly digest of newly imported questions
  app.post("/api/scheduled/send-new-questions-digest", async (_req, res) => {
    try {
      const db = await getDb();
      if (!db) throw new Error("Database not available");

      const rows = await db.execute(`
        SELECT d.namePt as name, COUNT(*) as cnt
        FROM questions q
        JOIN disciplines d ON q.disciplineId = d.id
        WHERE q.createdAt >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND q.batchId IS NOT NULL
        GROUP BY d.id, d.namePt
        ORDER BY cnt DESC
      `) as any;
      const disciplineCounts = (rows[0] as Array<{ name: string; cnt: number }>).map((r) => ({ name: r.name, count: Number(r.cnt) }));
      const total = disciplineCounts.reduce((sum, d) => sum + d.count, 0);

      // Concursos para veterinário: novos na semana + os que fecham nos próximos 10 dias
      const concRows = (await db.execute(`
        SELECT slug, orgao, uf, vagasVet, salarioMax, inscricoesFim FROM concursos
        WHERE active = 1 AND (inscricoesFim IS NULL OR inscricoesFim >= NOW())
          AND (createdAt >= DATE_SUB(NOW(), INTERVAL 7 DAY) OR inscricoesFim <= DATE_ADD(NOW(), INTERVAL 10 DAY))
        ORDER BY (inscricoesFim IS NULL), inscricoesFim ASC LIMIT 8
      `).catch(() => [[]]) as any)[0] as any[];
      const concursosDigest = concRows.map(c => ({
        orgao: c.orgao, uf: c.uf, vagas: c.vagasVet,
        salario: c.salarioMax ? Number(c.salarioMax).toLocaleString("pt-BR", { style: "currency", currency: "BRL", maximumFractionDigits: 0 }) : null,
        fim: c.inscricoesFim ? new Date(c.inscricoesFim).toLocaleDateString("pt-BR", { timeZone: "America/Sao_Paulo" }) : null,
        url: `https://www.vetrank.com.br/concursos/${c.slug}`,
      }));

      if (total === 0 && concursosDigest.length === 0) {
        return res.json({ success: true, sent: 0, failed: 0, total: 0, newQuestions: 0, concursos: 0 });
      }

      const allUsers = await db
        .select({ email: users.email, name: users.name })
        .from(users)
        .where(isNotNull(users.email))
        .limit(10000);

      let sent = 0, failed = 0, firstError: string | null = null;
      const startedAt = Date.now();
      // Ritmo controlado pelo pool SMTP de envio em massa (server/email.ts).
      const BATCH = 20;
      for (let i = 0; i < allUsers.length; i += BATCH) {
        const batch = allUsers.slice(i, i + BATCH);
        await Promise.all(
          batch.map(async (u) => {
            if (!u.email) return;
            try {
              await sendNewQuestionsDigestEmail(u.email, u.name ?? "Veterinário(a)", { disciplines: disciplineCounts, total, concursos: concursosDigest });
              sent++;
            } catch (e: any) {
              failed++;
              firstError ??= `${e?.code ?? ""} ${e?.message ?? e}`.trim().slice(0, 200);
            }
          })
        );
      }

      const seconds = Math.round((Date.now() - startedAt) / 1000);
      console.log(`[new-questions-digest] sent=${sent} failed=${failed} total=${allUsers.length} em ${seconds}s${firstError ? ` | 1º erro: ${firstError}` : ""}`);
      res.json({ success: true, at: new Date().toISOString(), sent, failed, total: allUsers.length, newQuestions: total, seconds, firstError });
    } catch (err: any) {
      res.status(500).json({ success: false, error: err.message });
    }
  });

  // Scheduled task endpoint: weekly automatic AI blog post generation
  app.post("/api/scheduled/generate-blog-post", async (req, res) => {
    try {
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

      const topic = req.body?.topic || TRENDING_TOPICS[Math.floor(Math.random() * TRENDING_TOPICS.length)];
      console.log(`[Scheduled Blog] Generating post for topic: "${topic}"...`);

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
        throw new Error("Invalid response content from LLM");
      }
      const rawPost = JSON.parse(responseText);
      
      // Passo 2 — Auditoria científica via LLM
      let post = await verifyAndCorrectBlogPost(rawPost);

      // Passo 3 — Verificação real de DOIs contra CrossRef API
      const foundDois = extractDOIs(post.body);
      if (foundDois.length > 0) {
        console.log(`[Blog DOI] Verificando ${foundDois.length} DOI(s) contra CrossRef API...`);
        const doiResults = await verifyAllDOIs(foundDois);
        const invalidDois = doiResults.filter(r => r.status === "not_found").map(r => r.doi);
        const validCount = doiResults.filter(r => r.status === "valid").length;
        console.log(`[Blog DOI] Válidos: ${validCount}/${foundDois.length}${invalidDois.length ? ` | INVÁLIDOS removidos: ${invalidDois.join(", ")}` : " — todos OK"}`);
        if (invalidDois.length > 0) {
          post = { ...post, body: removeInvalidDOILinks(post.body, invalidDois) };
        }
      }

      const slug = post.title
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/[^a-z0-9\s-]/g, "")
        .trim()
        .replace(/\s+/g, "-");

      const db = await getDb();
      if (!db) throw new Error("Database connection not found");

      await db.insert(blogPosts).values({
        slug,
        title: post.title,
        body: post.body,
        summary: post.summary,
        category: post.category || "scientific",
        author: post.author || "Equipe VetRank",
        active: false, // Inactive by default, requires admin approval
      });

      console.log(`[Scheduled Blog] Successfully generated and saved post as DRAFT: "${post.title}" (${slug})`);
      res.json({ success: true, title: post.title, slug });
    } catch (err: any) {
      console.error("[Scheduled Blog] Error generating post:", err);
      res.status(500).json({ success: false, error: err.message });
    }
  });

  // ─── Rate limiting & anti-scraping ─────────────────────────────────────────
  // General rate limit on all tRPC endpoints
  app.use("/api/trpc", apiLimiter);

  // Stricter limits on question-fetching endpoints to prevent database scraping
  app.use("/api/trpc/questions.list", questionLimiter, scrapeDetector);
  app.use("/api/trpc/questions.getById", questionLimiter, scrapeDetector);
  app.use("/api/trpc/questions.bulkImport", questionLimiter);

  // ─── tRPC API ────────────────────────────────────────────────────────────────
  app.use(
    "/api/trpc",
    createExpressMiddleware({
      router: appRouter,
      createContext,
    })
  );

  // ─── Dynamic sitemap.xml ─────────────────────────────────────────────────────
  app.get("/sitemap.xml", async (req, res) => {
    try {
      const db = await getDb();
      let blogs: { slug: string; updatedAt?: Date; createdAt?: Date }[] = [];
      let activeCourses: { slug: string; updatedAt?: Date; createdAt?: Date }[] = [];

      if (db) {
        try {
          blogs = await db
            .select({
              slug: blogPosts.slug,
              updatedAt: blogPosts.updatedAt,
              createdAt: blogPosts.createdAt,
            })
            .from(blogPosts)
            .where(eq(blogPosts.active, true))
            .orderBy(desc(blogPosts.createdAt));
        } catch (err) {
          console.error("Failed to fetch blog posts for sitemap", err);
        }

        try {
          activeCourses = await db
            .select({
              slug: courses.slug,
              updatedAt: courses.updatedAt,
              createdAt: courses.createdAt,
            })
            .from(courses)
            .where(eq(courses.active, true));
        } catch (err) {
          console.error("Failed to fetch courses for sitemap", err);
        }
      }

      // Fallback for blogs from seed if DB fails/empty
      if (blogs.length === 0) {
        try {
          const seedFilePath = path.resolve(import.meta.dirname, "../../data/blog_posts_seed.json");
          if (fs.existsSync(seedFilePath)) {
            const seed = JSON.parse(fs.readFileSync(seedFilePath, "utf-8"));
            blogs = seed
              .filter((p: any) => p.active)
              .map((p: any) => ({
                slug: p.slug,
                createdAt: new Date(p.createdAt || Date.now()),
                updatedAt: new Date(p.updatedAt || p.createdAt || Date.now()),
              }));
          }
        } catch (err) {
          console.error("Failed to load blog posts seed for sitemap", err);
        }
      }

      // Build XML
      let xml = `<?xml version="1.0" encoding="UTF-8"?>\n`;
      xml += `<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">\n`;

      const staticUrls = [
        { loc: "/", changefreq: "weekly", priority: "1.0" },
        { loc: "/concursos", changefreq: "daily", priority: "0.95" },
        { loc: "/novidades", changefreq: "daily", priority: "0.85" },
        { loc: "/bank", changefreq: "daily", priority: "0.9" },
        { loc: "/leaderboard", changefreq: "daily", priority: "0.8" },
        { loc: "/pricing", changefreq: "monthly", priority: "0.8" },
        { loc: "/enade", changefreq: "weekly", priority: "0.9" },
        { loc: "/enare", changefreq: "weekly", priority: "0.9" },
        { loc: "/residencia", changefreq: "weekly", priority: "0.9" },
        { loc: "/blog", changefreq: "daily", priority: "0.8" },
        { loc: "/cursos", changefreq: "daily", priority: "0.8" },
        { loc: "/verificar-certificado", changefreq: "monthly", priority: "0.5" },
        { loc: "/terms", changefreq: "yearly", priority: "0.3" },
        { loc: "/privacy", changefreq: "yearly", priority: "0.3" },
      ];

      const nowStr = new Date().toISOString().split("T")[0];

      staticUrls.forEach((u) => {
        xml += `  <url>\n`;
        xml += `    <loc>https://vetrank.com.br${u.loc}</loc>\n`;
        xml += `    <lastmod>${nowStr}</lastmod>\n`;
        xml += `    <changefreq>${u.changefreq}</changefreq>\n`;
        xml += `    <priority>${u.priority}</priority>\n`;
        xml += `  </url>\n`;
      });

      blogs.forEach((b) => {
        const date = b.updatedAt || b.createdAt || new Date();
        const dateStr = new Date(date).toISOString().split("T")[0];
        xml += `  <url>\n`;
        xml += `    <loc>https://vetrank.com.br/blog/${b.slug}</loc>\n`;
        xml += `    <lastmod>${dateStr}</lastmod>\n`;
        xml += `    <changefreq>monthly</changefreq>\n`;
        xml += `    <priority>0.7</priority>\n`;
        xml += `  </url>\n`;
      });

      activeCourses.forEach((c) => {
        const date = c.updatedAt || c.createdAt || new Date();
        const dateStr = new Date(date).toISOString().split("T")[0];
        xml += `  <url>\n`;
        xml += `    <loc>https://vetrank.com.br/cursos/${c.slug}</loc>\n`;
        xml += `    <lastmod>${dateStr}</lastmod>\n`;
        xml += `    <changefreq>weekly</changefreq>\n`;
        xml += `    <priority>0.8</priority>\n`;
        xml += `  </url>\n`;
      });

      try {
        const { listarConcursos } = await import("../concursos");
        const activeConcursos = await listarConcursos({ incluirEncerrados: false, limite: 150 });
        activeConcursos.forEach((c) => {
          const date = c.publishedAt || c.updatedAt || c.createdAt || new Date();
          const dateStr = new Date(date).toISOString().split("T")[0];
          xml += `  <url>\n`;
          xml += `    <loc>https://vetrank.com.br/concursos/${c.slug}</loc>\n`;
          xml += `    <lastmod>${dateStr}</lastmod>\n`;
          xml += `    <changefreq>daily</changefreq>\n`;
          xml += `    <priority>0.85</priority>\n`;
          xml += `  </url>\n`;
        });
      } catch (err) {
        console.error("Failed to fetch concursos for sitemap", err);
      }

      xml += `</urlset>\n`;

      res.header("Content-Type", "application/xml");
      res.status(200).send(xml);
    } catch (err) {
      console.error("Error generating dynamic sitemap", err);
      res.status(500).send("Error generating sitemap");
    }
  });

  // development mode uses Vite, production mode uses static files
  if (process.env.NODE_ENV === "development") {
    await setupVite(app, server);
  } else {
    serveStatic(app);
  }

  const preferredPort = parseInt(process.env.PORT || "3000");
  const port = await findAvailablePort(preferredPort);

  if (port !== preferredPort) {
    console.log(`Port ${preferredPort} is busy, using port ${port} instead`);
  }

  server.listen(port, () => {
    console.log(`Server running on http://localhost:${port}/`);
  });
}

startServer().catch(console.error);
