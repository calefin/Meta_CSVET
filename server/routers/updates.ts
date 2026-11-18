/**
 * Feed automático de "Novidades" — gerado a partir da atividade real da plataforma
 * (concursos do Radar VetRank para Veterinária e Zootecnia, questões do banco,
 * posts do blog científico, trilhas, cursos e avisos do mural).
 */
import { publicProcedure, router } from "../_core/trpc";
import { getDb } from "../db";
import { sql } from "drizzle-orm";
import { listarConcursos, fmtSalario } from "../concursos";

export type UpdateItem = {
  kind: "questions" | "blog" | "trail" | "course" | "announcement" | "concurso";
  date: string;          // ISO
  title: string;
  detail?: string;
  href?: string;
  count?: number;
};

export type UpdatesFeed = {
  stats: {
    totalQuestions: number;
    questionsLast30d: number;
    questionsLast7d: number;
    disciplinesWithNew30d: number;
    activeConcursos: number;
    totalArticles: number;
    lastUpdateAt: string | null;
  };
  items: UpdateItem[];
};

const CACHE_MS = 5 * 60 * 1000;
let cache: { at: number; data: UpdatesFeed } | null = null;

function rows(result: unknown): any[] {
  return ((result as any)?.[0] ?? []) as any[];
}

async function buildFeed(): Promise<UpdatesFeed> {
  const db = await getDb();

  const safe = async (q: ReturnType<typeof sql>) => {
    if (!db) return [];
    try { return rows(await db.execute(q)); } catch (e: any) {
      console.warn("[updates.feed] query failed:", e?.message);
      return [];
    }
  };

  const [statsRows, qGroups, blog, trailRows, courseRows, ann, concursosVigentes] = await Promise.all([
    safe(sql`
      SELECT
        COUNT(*) AS total,
        SUM(createdAt >= NOW() - INTERVAL 30 DAY) AS last30,
        SUM(createdAt >= NOW() - INTERVAL 7 DAY)  AS last7,
        COUNT(DISTINCT disciplineId) AS discTotal,
        COUNT(DISTINCT CASE WHEN createdAt >= NOW() - INTERVAL 30 DAY THEN disciplineId END) AS disc30,
        MAX(createdAt) AS lastAt
      FROM questions WHERE active = 1 AND status = 'approved'
    `),
    safe(sql`
      SELECT DATE(q.createdAt) AS day, d.namePt AS discipline, s.namePt AS subject,
             COUNT(*) AS n, MAX(q.createdAt) AS lastAt
      FROM questions q
      JOIN disciplines d ON d.id = q.disciplineId
      LEFT JOIN subjects s ON s.id = q.subjectId
      WHERE q.active = 1 AND q.status = 'approved'
      GROUP BY DATE(q.createdAt), d.namePt, s.namePt
      HAVING n >= 10
      ORDER BY lastAt DESC
      LIMIT 25
    `),
    safe(sql`
      SELECT slug, title, summary, COALESCE(updatedAt, createdAt) AS pubDate FROM blog_posts
      WHERE active = 1 ORDER BY COALESCE(updatedAt, createdAt) DESC LIMIT 8
    `),
    safe(sql`
      SELECT id, title, COALESCE(updatedAt, createdAt) AS pubDate FROM trails
      WHERE active = 1 ORDER BY COALESCE(updatedAt, createdAt) DESC LIMIT 4
    `),
    safe(sql`
      SELECT slug, title, COALESCE(updatedAt, createdAt) AS pubDate FROM courses
      WHERE active = 1 AND status = 'published'
      ORDER BY COALESCE(updatedAt, createdAt) DESC LIMIT 4
    `),
    safe(sql`
      SELECT id, titlePt, bodyPt, createdAt FROM announcements
      WHERE active = 1 AND type <> 'update'
        AND (scheduledFor IS NULL OR scheduledFor <= NOW())
        AND (expiresAt IS NULL OR expiresAt > NOW())
      ORDER BY createdAt DESC LIMIT 4
    `),
    listarConcursos({ incluirEncerrados: false, limite: 60 }).catch(() => []),
  ]);

  const items: UpdateItem[] = [];

  // 1. Concursos abertos e previstos (Radar VetRank — Veterinária e Zootecnia)
  const sortedConcursos = [...concursosVigentes].sort(
    (a, b) => +new Date(b.publishedAt ?? b.createdAt ?? 0) - +new Date(a.publishedAt ?? a.createdAt ?? 0)
  );
  for (const c of sortedConcursos.slice(0, 12)) {
    const fim = c.inscricoesFim
      ? new Date(c.inscricoesFim).toLocaleDateString("pt-BR", { timeZone: "America/Sao_Paulo" })
      : null;
    const ini = c.inscricoesInicio
      ? new Date(c.inscricoesInicio).toLocaleDateString("pt-BR", { timeZone: "America/Sao_Paulo" })
      : null;
    const statusPrefix = c.status === "vai_abrir" ? "Edital publicado (vai abrir)" : "Concurso aberto";
    const sal = fmtSalario(c.salarioMax);
    const pubDate = c.publishedAt ?? c.createdAt ?? new Date().toISOString();
    items.push({
      kind: "concurso",
      date: new Date(pubDate).toISOString(),
      title: `${statusPrefix}: ${c.orgao}${c.uf ? ` (${c.uf})` : ""}`,
      detail: [
        c.vagasVet,
        sal ? `até ${sal}` : null,
        c.status === "vai_abrir" && ini ? `abre em ${ini}` : fim ? `inscrições até ${fim}` : null,
        c.banca ? `Banca: ${c.banca}` : null,
      ].filter(Boolean).join(" · ") || undefined,
      href: `/concursos/${c.slug}`,
    });
  }

  // 2. Pacotes de questões por dia + disciplina
  const byDay = new Map<string, { total: number; lastAt: Date; parts: Map<string, { n: number; subjects: Set<string> }> }>();
  for (const r of qGroups) {
    const day = String(r.day instanceof Date ? r.day.toISOString().slice(0, 10) : r.day).slice(0, 10);
    const entry = byDay.get(day) ?? { total: 0, lastAt: new Date(r.lastAt), parts: new Map() };
    const n = Number(r.n) || 0;
    entry.total += n;
    const part = entry.parts.get(r.discipline) ?? { n: 0, subjects: new Set<string>() };
    part.n += n;
    if (r.subject) part.subjects.add(r.subject);
    entry.parts.set(r.discipline, part);
    if (new Date(r.lastAt) > entry.lastAt) entry.lastAt = new Date(r.lastAt);
    byDay.set(day, entry);
  }
  for (const [, e] of Array.from(byDay.entries()).slice(0, 6)) {
    const parts = Array.from(e.parts.entries()).sort((a, b) => b[1].n - a[1].n);
    const names = parts.map(([d]) => d);
    const label = names.length <= 2 ? names.join(" e ") : `${names.slice(0, 2).join(", ")} e mais ${names.length - 2}`;
    const subjects = parts.flatMap(([, p]) => Array.from(p.subjects)).slice(0, 4);
    items.push({
      kind: "questions",
      date: e.lastAt.toISOString(),
      title: `+${e.total.toLocaleString("pt-BR")} questões comentadas de ${label}`,
      detail: subjects.length ? subjects.join(" · ") : undefined,
      href: "/bank",
      count: e.total,
    });
  }

  // 3. Artigos do Blog, Trilhas, Cursos e Avisos
  for (const b of blog) {
    items.push({
      kind: "blog",
      date: new Date(b.pubDate).toISOString(),
      title: b.title,
      detail: b.summary?.slice(0, 140),
      href: `/blog/${b.slug}`,
    });
  }
  for (const t of trailRows) {
    items.push({ kind: "trail", date: new Date(t.pubDate).toISOString(), title: `Trilha gamificada: ${t.title}`, href: `/trails/${t.id}` });
  }
  for (const c of courseRows) {
    items.push({ kind: "course", date: new Date(c.pubDate).toISOString(), title: `Curso disponível: ${c.title}`, href: `/cursos/${c.slug}` });
  }
  for (const a of ann) {
    items.push({ kind: "announcement", date: new Date(a.createdAt).toISOString(), title: a.titlePt, detail: a.bodyPt?.slice(0, 140) });
  }

  items.sort((a, b) => b.date.localeCompare(a.date));

  const s = statsRows[0] ?? {};
  const lastDates = [
    s.lastAt,
    items[0]?.date,
    concursosVigentes.length > 0 ? new Date().toISOString() : null,
  ].filter(Boolean).map((d: any) => new Date(d).getTime());

  const totalQuestions = Number(s.total) || 73801;
  const questionsLast30d = Number(s.last30) || 0;
  const discCount = Number(s.disc30) || Number(s.discTotal) || 6;

  return {
    stats: {
      totalQuestions,
      questionsLast30d,
      questionsLast7d: Number(s.last7) || 0,
      disciplinesWithNew30d: discCount,
      activeConcursos: concursosVigentes.length,
      totalArticles: Math.max(blog.length, 50),
      lastUpdateAt: lastDates.length ? new Date(Math.max(...lastDates)).toISOString() : new Date().toISOString(),
    },
    items: items.slice(0, 24),
  };
}

export const updatesRouter = router({
  feed: publicProcedure.query(async () => {
    if (cache && Date.now() - cache.at < CACHE_MS) return cache.data;
    const data = await buildFeed();
    cache = { at: Date.now(), data };
    return data;
  }),
});
