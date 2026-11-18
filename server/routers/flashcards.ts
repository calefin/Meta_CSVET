import { z } from "zod";
import { protectedProcedure, publicProcedure, router } from "../_core/trpc";
import { TRPCError } from "@trpc/server";
import { getDb } from "../db";
import { flashcards, flashcardReviews } from "../../drizzle/schema";
import { and, eq, sql } from "drizzle-orm";
import { FREE_DAILY_LIMIT, cardsDosMeusErros, revisoesHoje, sm2, temAcessoIlimitado, type Nota } from "../flashcards";

const deckInput = z.object({
  disciplineId: z.number().optional(),
  subjectId: z.number().optional(),
  mistakes: z.boolean().optional(),      // só cards vindos de questões que o aluno errou
}).optional();

export const flashcardsRouter = router({
  // Baralhos disponíveis (público — aparece na demo)
  decks: publicProcedure.query(async () => {
    const db = await getDb();
    if (!db) return { total: 0, areas: [] as any[] };
    const [rows] = await db.execute(sql`
      SELECT d.id AS disciplineId, d.namePt AS disciplineName, s.id AS subjectId, s.namePt AS subjectName, COUNT(*) AS n
      FROM flashcards f JOIN disciplines d ON d.id = f.disciplineId LEFT JOIN subjects s ON s.id = f.subjectId
      WHERE f.active = 1 GROUP BY d.id, d.namePt, s.id, s.namePt ORDER BY d.namePt, n DESC`) as any;
    const areas = new Map<number, { disciplineId: number; disciplineName: string; total: number; subjects: { subjectId: number; subjectName: string; n: number }[] }>();
    let total = 0;
    for (const r of rows as any[]) {
      const a = areas.get(r.disciplineId) ?? { disciplineId: r.disciplineId, disciplineName: r.disciplineName, total: 0, subjects: [] as { subjectId: number; subjectName: string; n: number }[] };
      a.total += Number(r.n); total += Number(r.n);
      if (r.subjectId) a.subjects.push({ subjectId: r.subjectId, subjectName: r.subjectName, n: Number(r.n) });
      areas.set(r.disciplineId, a);
    }
    return { total, areas: Array.from(areas.values()) };
  }),

  // Demonstração para visitante sem conta
  demo: publicProcedure.query(async () => {
    const db = await getDb();
    if (!db) return [];
    const [rows] = await db.execute(sql`
      SELECT f.id, f.front, f.back, d.namePt AS area FROM flashcards f JOIN disciplines d ON d.id = f.disciplineId
      WHERE f.active = 1 ORDER BY RAND() LIMIT 8`) as any;
    return rows as { id: number; front: string; back: string; area: string }[];
  }),

  stats: protectedProcedure.query(async ({ ctx }) => {
    const db = await getDb();
    if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
    const [[r]] = await db.execute(sql`
      SELECT SUM(dueAt <= NOW()) AS vencidos, SUM(repetitions >= 2) AS aprendidos, COUNT(*) AS naFila
      FROM flashcard_reviews WHERE userId = ${ctx.user.id}`) as any;
    const ilimitado = await temAcessoIlimitado(ctx.user.id, ctx.user.role);
    const hoje = await revisoesHoje(ctx.user.id);
    return {
      vencidos: Number(r?.vencidos ?? 0), aprendidos: Number(r?.aprendidos ?? 0), naFila: Number(r?.naFila ?? 0),
      hoje, ilimitado, limiteDiario: ilimitado ? null : FREE_DAILY_LIMIT,
      restantesHoje: ilimitado ? null : Math.max(0, FREE_DAILY_LIMIT - hoje),
    };
  }),

  // Sessão: primeiro os cards vencidos, depois cards novos do baralho escolhido
  session: protectedProcedure
    .input(z.object({ deck: deckInput, limit: z.number().min(5).max(50).default(20) }))
    .query(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
      const ilimitado = await temAcessoIlimitado(ctx.user.id, ctx.user.role);
      const restantes = ilimitado ? input.limit : Math.max(0, Math.min(input.limit, FREE_DAILY_LIMIT - await revisoesHoje(ctx.user.id)));
      if (restantes === 0) return { cards: [], limiteAtingido: true };

      const d = input.deck ?? {};
      const filtro = sql`${d.disciplineId ? sql`AND f.disciplineId = ${d.disciplineId}` : sql``} ${d.subjectId ? sql`AND f.subjectId = ${d.subjectId}` : sql``}`;
      const [vencidos] = await db.execute(sql`
        SELECT f.id, f.front, f.back, r.repetitions, 'revisao' AS tipo FROM flashcard_reviews r JOIN flashcards f ON f.id = r.flashcardId
        WHERE r.userId = ${ctx.user.id} AND r.dueAt <= NOW() AND f.active = 1 ${filtro}
        ORDER BY r.dueAt ASC LIMIT ${restantes}`) as any;
      let cards = vencidos as any[];
      if (cards.length < restantes && !d.mistakes) {
        const [novos] = await db.execute(sql`
          SELECT f.id, f.front, f.back, 0 AS repetitions, 'novo' AS tipo FROM flashcards f
          WHERE f.active = 1 ${filtro}
            AND NOT EXISTS (SELECT 1 FROM flashcard_reviews r WHERE r.userId = ${ctx.user.id} AND r.flashcardId = f.id)
          ORDER BY RAND() LIMIT ${restantes - cards.length}`) as any;
        cards = [...cards, ...(novos as any[])];
      }
      return { cards: cards as { id: number; front: string; back: string; repetitions: number; tipo: "novo" | "revisao" }[], limiteAtingido: false };
    }),

  review: protectedProcedure
    .input(z.object({ flashcardId: z.number(), nota: z.union([z.literal(0), z.literal(3), z.literal(4), z.literal(5)]) }))
    .mutation(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
      if (!(await temAcessoIlimitado(ctx.user.id, ctx.user.role)) && (await revisoesHoje(ctx.user.id)) >= FREE_DAILY_LIMIT) {
        throw new TRPCError({ code: "FORBIDDEN", message: `Limite de ${FREE_DAILY_LIMIT} flashcards por dia no plano gratuito. Assine o Premium para revisar sem limite.` });
      }
      const [prev] = await db.select().from(flashcardReviews)
        .where(and(eq(flashcardReviews.userId, ctx.user.id), eq(flashcardReviews.flashcardId, input.flashcardId))).limit(1);
      const next = sm2(prev ?? { ease: 2.5, intervalDays: 0, repetitions: 0, lapses: 0 }, input.nota as Nota);
      if (prev) {
        await db.update(flashcardReviews).set({ ...next, lastReviewedAt: new Date() }).where(eq(flashcardReviews.id, prev.id));
      } else {
        await db.insert(flashcardReviews).values({ userId: ctx.user.id, flashcardId: input.flashcardId, ...next, lastReviewedAt: new Date() });
      }
      return { proximaRevisao: next.dueAt, intervaloDias: next.intervalDays };
    }),

  fromMistakes: protectedProcedure.mutation(async ({ ctx }) => cardsDosMeusErros(ctx.user.id)),

  // Importação (gerador local --flashcards ou planilha)
  adminImport: protectedProcedure
    .input(z.object({ cards: z.array(z.object({
      disciplineId: z.number(), subjectId: z.number().optional(), topicId: z.number().optional(),
      front: z.string().min(5).max(600), back: z.string().min(2).max(1200),
    })).min(1).max(2000) }))
    .mutation(async ({ ctx, input }) => {
      if (!["admin", "superuser", "coordinator", "teacher"].includes(ctx.user.role)) throw new TRPCError({ code: "FORBIDDEN" });
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
      for (let i = 0; i < input.cards.length; i += 200) {
        await db.insert(flashcards).values(input.cards.slice(i, i + 200).map(c => ({ ...c, source: "import" as const })));
      }
      return { imported: input.cards.length };
    }),
});
