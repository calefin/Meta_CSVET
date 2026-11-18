/**
 * Flashcards com repetição espaçada (SM-2).
 *
 * De onde vêm os cards (sem trabalho manual):
 *  - convertQuestions(): converte questões do banco (enunciado + gabarito + explicação) em cards curtos
 *    via LLM. Cada questão vira card UMA vez e o card serve a todos (biblioteca compartilhada).
 *    Roda diariamente (/api/scheduled/generate-flashcards) priorizando as disciplinas mais estudadas,
 *    e sob demanda para os erros de cada aluno.
 *  - importação JSON (gerador local scripts/gerador_questoes.py --flashcards).
 */
import { getDb } from "./db";
import { flashcards, flashcardReviews, questions, users } from "../drizzle/schema";
import { and, eq, inArray, sql } from "drizzle-orm";
import { invokeLLM } from "./_core/llm";

export const FREE_DAILY_LIMIT = 20;

// ── SM-2 ──────────────────────────────────────────────────────────────────────
export type Nota = 0 | 3 | 4 | 5;   // 0 errei · 3 difícil · 4 bom · 5 fácil
export function sm2(prev: { ease: number; intervalDays: number; repetitions: number; lapses: number }, q: Nota) {
  let { ease, intervalDays, repetitions, lapses } = prev;
  const now = Date.now();
  if (q < 3) {
    repetitions = 0; lapses += 1; intervalDays = 0;
    ease = Math.max(1.3, ease - 0.2);
    return { ease, intervalDays, repetitions, lapses, dueAt: new Date(now + 10 * 60 * 1000) }; // volta em 10 min
  }
  repetitions += 1;
  if (repetitions === 1) intervalDays = q === 5 ? 3 : 1;
  else if (repetitions === 2) intervalDays = q === 5 ? 7 : q === 3 ? 3 : 4;
  else intervalDays = Math.round(intervalDays * ease * (q === 3 ? 0.8 : q === 5 ? 1.3 : 1));
  ease = Math.max(1.3, ease + 0.1 - (5 - q) * (0.08 + (5 - q) * 0.02));
  intervalDays = Math.min(intervalDays, 365);
  return { ease, intervalDays, repetitions, lapses, dueAt: new Date(now + intervalDays * 86400000) };
}

// ── Acesso ────────────────────────────────────────────────────────────────────
export async function temAcessoIlimitado(userId: number, role: string): Promise<boolean> {
  if (["admin", "superuser", "coordinator", "teacher"].includes(role)) return true;
  const db = await getDb();
  if (!db) return false;
  const [u] = await db.select({ trialEndsAt: users.trialEndsAt, premiumEndsAt: users.premiumEndsAt }).from(users).where(eq(users.id, userId)).limit(1);
  const now = new Date();
  return !!((u?.premiumEndsAt && u.premiumEndsAt > now) || (u?.trialEndsAt && u.trialEndsAt > now));
}

export async function revisoesHoje(userId: number): Promise<number> {
  const db = await getDb();
  if (!db) return 0;
  const [r] = ((await db.execute(sql`
    SELECT COUNT(*) AS n FROM flashcard_reviews
    WHERE userId = ${userId} AND lastReviewedAt >= CURDATE()`)) as any)[0];
  return Number(r?.n ?? 0);
}

// ── Conversão questão → card ──────────────────────────────────────────────────
type QRow = { id: number; disciplineId: number; subjectId: number | null; topicId: number | null; textPt: string; options: any; correctOption: string; explanationPt: string | null };

export async function convertQuestions(rows: QRow[]): Promise<number> {
  const db = await getDb();
  if (!db || rows.length === 0) return 0;
  let criados = 0;
  for (let i = 0; i < rows.length; i += 10) {
    const lote = rows.slice(i, i + 10);
    const blocos = lote.map((q, idx) => {
      const opts = Array.isArray(q.options) ? q.options : [];
      const correta = opts.find((o: any) => String(o.id).toUpperCase() === String(q.correctOption).toUpperCase());
      return `### ${idx}\nENUNCIADO: ${q.textPt.slice(0, 1500)}\nRESPOSTA CORRETA: ${correta?.textPt ?? q.correctOption}\nEXPLICAÇÃO: ${(q.explanationPt ?? "").slice(0, 1500)}`;
    }).join("\n\n");
    try {
      const res = await invokeLLM({
        messages: [
          {
            role: "system",
            content:
              "Você cria flashcards de Medicina Veterinária para revisão espaçada. Para cada questão, extraia o CONCEITO-CHAVE " +
              "que ela testa e escreva 1 flashcard (ou 2, se houver dois conceitos independentes e importantes). " +
              "front: pergunta curta e direta (até 160 caracteres), sem alternativas, sem 'segundo a questão'. " +
              "back: resposta objetiva (até 320 caracteres) com o porquê em uma frase. Não invente dados além do enunciado/explicação. " +
              "Se a questão não tiver conceito aproveitável, não gere card para ela. Responda só com JSON.",
          },
          { role: "user", content: `${blocos}\n\nJSON: {"cards":[{"i":0,"front":"...","back":"..."}]}` },
        ],
        responseFormat: { type: "json_object" },
      });
      const txt = res.choices[0].message.content;
      const parsed = typeof txt === "string" ? JSON.parse(txt) : { cards: [] };
      const values = [];
      for (const c of parsed.cards ?? []) {
        const q = lote[Number(c.i)];
        const front = String(c.front ?? "").trim(), back = String(c.back ?? "").trim();
        if (!q || front.length < 10 || back.length < 5 || front.length > 400 || back.length > 800) continue;
        values.push({ disciplineId: q.disciplineId, subjectId: q.subjectId, topicId: q.topicId, front, back, sourceQuestionId: q.id, source: "question" as const });
      }
      // Marca questões sem card aproveitável para não reprocessar (card inativo sentinela)
      const comCard = new Set(values.map(v => v.sourceQuestionId));
      for (const q of lote) if (!comCard.has(q.id)) values.push({ disciplineId: q.disciplineId, subjectId: q.subjectId, topicId: q.topicId, front: "-", back: "-", sourceQuestionId: q.id, source: "question" as const, active: false } as any);
      if (values.length) { await db.insert(flashcards).values(values as any); criados += values.filter((v: any) => v.active !== false).length; }
    } catch (e: any) {
      console.error("[Flashcards] conversão falhou:", e?.message);
    }
  }
  return criados;
}

const Q_COLS = sql`q.id, q.disciplineId, q.subjectId, q.topicId, q.textPt, q.options, q.correctOption, q.explanationPt`;
const parseRows = (r: any): QRow[] => (r[0] as any[]).map(x => ({ ...x, options: typeof x.options === "string" ? JSON.parse(x.options) : x.options }));

/** Job diário: converte questões ainda sem card, priorizando as disciplinas mais estudadas nos últimos 30 dias. */
export async function gerarFlashcardsDiario(limite = 80): Promise<{ convertidas: number; cards: number }> {
  const db = await getDb();
  if (!db) throw new Error("DB indisponível");
  const rows = parseRows(await db.execute(sql`
    SELECT ${Q_COLS} FROM questions q
    LEFT JOIN (
      SELECT subjectId, COUNT(*) AS n FROM practice_sessions
      WHERE createdAt >= NOW() - INTERVAL 30 DAY AND subjectId IS NOT NULL GROUP BY subjectId
    ) d ON d.subjectId = q.subjectId
    WHERE q.active = 1 AND q.status = 'approved' AND q.questionType <> 'discursive'
      AND q.explanationPt IS NOT NULL AND CHAR_LENGTH(q.explanationPt) > 80
      AND NOT EXISTS (SELECT 1 FROM flashcards f WHERE f.sourceQuestionId = q.id)
    ORDER BY COALESCE(d.n, 0) DESC, RAND()
    LIMIT ${limite}`));
  return { convertidas: rows.length, cards: await convertQuestions(rows) };
}

/** Questões que o aluno errou → cards (cria os que faltam) → entram na fila dele para hoje. */
export async function cardsDosMeusErros(userId: number, limite = 20): Promise<{ adicionados: number; gerados: number }> {
  const db = await getDb();
  if (!db) throw new Error("DB indisponível");
  const erradas = parseRows(await db.execute(sql`
    SELECT ${Q_COLS} FROM questions q WHERE q.id IN (
      SELECT questionId FROM practice_sessions WHERE userId = ${userId} AND isCorrect = 0 AND createdAt >= NOW() - INTERVAL 90 DAY
      UNION
      SELECT ea.questionId FROM exam_answers ea JOIN exams e ON e.id = ea.examId
      WHERE e.userId = ${userId} AND ea.isCorrect = 0 AND ea.createdAt >= NOW() - INTERVAL 90 DAY
    ) AND q.explanationPt IS NOT NULL LIMIT 200`));
  if (!erradas.length) return { adicionados: 0, gerados: 0 };

  const ids = erradas.map(q => q.id);
  const existentes = await db.select({ id: flashcards.id, sq: flashcards.sourceQuestionId, active: flashcards.active })
    .from(flashcards).where(inArray(flashcards.sourceQuestionId, ids));
  const jaConvertidas = new Set(existentes.map(e => e.sq));
  const faltam = erradas.filter(q => !jaConvertidas.has(q.id)).slice(0, limite);
  const gerados = await convertQuestions(faltam);

  const cards = await db.select({ id: flashcards.id }).from(flashcards)
    .where(and(inArray(flashcards.sourceQuestionId, ids), eq(flashcards.active, true)));
  const naFila = new Set((await db.select({ f: flashcardReviews.flashcardId }).from(flashcardReviews)
    .where(and(eq(flashcardReviews.userId, userId), inArray(flashcardReviews.flashcardId, cards.length ? cards.map(c => c.id) : [0])))).map(r => r.f));
  const novos = cards.filter(c => !naFila.has(c.id)).slice(0, 60);
  if (novos.length) await db.insert(flashcardReviews).values(novos.map(c => ({ userId, flashcardId: c.id, dueAt: new Date() })));
  return { adicionados: novos.length, gerados };
}
