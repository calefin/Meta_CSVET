import { z } from "zod";
import { protectedProcedure, router } from "../_core/trpc";
import { TRPCError } from "@trpc/server";
import { getDb } from "../db";
import { users, activityLog, practiceSessions } from "../../drizzle/schema";
import { eq } from "drizzle-orm";

const LGPD_VERSION = "1.0";
const TOS_VERSION = "1.0";

export const lgpdRouter = router({
  // ── Check if user has consented ───────────────────────────────────────────
  checkConsent: protectedProcedure.query(async ({ ctx }) => {
    const db = await getDb();
    if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
    const [user] = await db.select({
      lgpdConsentAt: users.lgpdConsentAt,
      lgpdConsentVersion: users.lgpdConsentVersion,
    }).from(users).where(eq(users.id, ctx.user.id)).limit(1);
    return {
      hasConsented: !!(user?.lgpdConsentAt && user?.lgpdConsentVersion === LGPD_VERSION),
      consentedAt: user?.lgpdConsentAt,
      currentVersion: LGPD_VERSION,
    };
  }),

  // ── Record LGPD consent ────────────────────────────────────────────────────
  recordConsent: protectedProcedure.mutation(async ({ ctx }) => {
    const db = await getDb();
    if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
    await db.update(users)
      .set({ lgpdConsentAt: new Date(), lgpdConsentVersion: LGPD_VERSION })
      .where(eq(users.id, ctx.user.id));
    return { success: true };
  }),

  // ── Check if user has accepted Terms of Service ───────────────────────────
  checkTosAccepted: protectedProcedure.query(async ({ ctx }) => {
    const db = await getDb();
    if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
    const [user] = await db.select({
      tosAcceptedAt: users.tosAcceptedAt,
      tosVersion: users.tosVersion,
    }).from(users).where(eq(users.id, ctx.user.id)).limit(1);
    return { accepted: !!(user?.tosAcceptedAt && user?.tosVersion === TOS_VERSION) };
  }),

  // ── Accept Terms of Service (records IP, date, version) ───────────────────
  acceptTerms: protectedProcedure.mutation(async ({ ctx }) => {
    const db = await getDb();
    if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
    const now = new Date();
    const ip = (ctx.req.headers["x-forwarded-for"] as string | undefined)
      ?.split(",")[0].trim() ?? ctx.req.ip ?? "unknown";

    await db.update(users)
      .set({
        tosAcceptedAt: now,
        tosVersion: TOS_VERSION,
        tosAcceptedIp: ip,
        lgpdConsentAt: now,
        lgpdConsentVersion: LGPD_VERSION,
      })
      .where(eq(users.id, ctx.user.id));

    await db.insert(activityLog).values({
      userId: ctx.user.id,
      action: "accept_terms",
      entityType: "user",
      entityId: ctx.user.id,
      details: { tosVersion: TOS_VERSION, lgpdVersion: LGPD_VERSION },
      ipAddress: ip,
    });

    return { success: true };
  }),
});

export const practiceRouter = router({
  // ── Get next practice question ─────────────────────────────────────────────
  getQuestion: protectedProcedure
    .input(z.object({
      disciplineId: z.number().optional(),
      subjectId: z.number().optional(),
      difficulty: z.enum(["easy", "medium", "hard", "any"]).default("any"),
      excludeAnswered: z.boolean().default(false),
      seenIds: z.array(z.number()).optional(),
    }))
    .query(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });

      let excludeIds: number[] = input.seenIds ?? [];
      if (input.excludeAnswered) {
        const answered = await db
          .select({ questionId: practiceSessions.questionId })
          .from(practiceSessions)
          .where(eq(practiceSessions.userId, ctx.user.id));
        const answeredIds = answered.map(a => a.questionId);
        excludeIds = Array.from(new Set([...excludeIds, ...answeredIds]));
      }

      const conditions = ["q.active = 1", "q.status = 'approved'"];
      if (input.disciplineId) conditions.push(`q.disciplineId = ${input.disciplineId}`);
      if (input.subjectId) conditions.push(`q.subjectId = ${input.subjectId}`);
      if (input.difficulty !== "any") conditions.push(`q.difficulty = '${input.difficulty}'`);
      if (excludeIds.length > 0) conditions.push(`q.id NOT IN (${excludeIds.join(",")})`);

      const rows = await db.execute(`
        SELECT q.id, q.textPt, q.textEn, q.options, q.correctOption, q.explanationPt,
          q.explanationEn, q.difficulty, q.questionType, q.questionModel, q.modelId,
          q.imageUrl, q.year, q.banca, q.author, q.subjectTag,
          q.assertion1, q.assertion2, q.a1Verdadeira, q.a2Verdadeira, q.relacaoCausal,
          q.formatData, q.isAnulada, q.isDesatualizada,
          d.namePt as disciplineName, s.namePt as subjectName
        FROM questions q
        LEFT JOIN disciplines d ON q.disciplineId = d.id
        LEFT JOIN subjects s ON q.subjectId = s.id
        WHERE ${conditions.join(" AND ")}
        ORDER BY RAND()
        LIMIT 1
      `) as any;

      const q = (rows[0] as any[])[0];
      if (!q) return null;
      return q;
    }),

  // ── Submit practice answer ─────────────────────────────────────────────────
  submitAnswer: protectedProcedure
    .input(z.object({
      questionId: z.number(),
      disciplineId: z.number(),
      subjectId: z.number().optional(),
      difficulty: z.enum(["easy", "medium", "hard"]).optional(),
      selectedOption: z.string(),
      isCorrect: z.boolean(),
    }))
    .mutation(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
      await db.insert(practiceSessions).values({
        userId: ctx.user.id,
        questionId: input.questionId,
        disciplineId: input.disciplineId,
        subjectId: input.subjectId,
        difficulty: input.difficulty,
        selectedOption: input.selectedOption,
        isCorrect: input.isCorrect,
      });
      const isStudent = ctx.user.role === "user";
      if (input.isCorrect) {
        if (isStudent) {
          await db.execute(`UPDATE users SET xp = xp + 2, totalCorrect = totalCorrect + 1, totalQuestions = totalQuestions + 1 WHERE id = ${ctx.user.id}`);
        } else {
          await db.execute(`UPDATE users SET totalCorrect = totalCorrect + 1, totalQuestions = totalQuestions + 1 WHERE id = ${ctx.user.id}`);
        }
      } else {
        await db.execute(`UPDATE users SET totalQuestions = totalQuestions + 1 WHERE id = ${ctx.user.id}`);
      }

      // Verifica se chegou à 5ª questão do dia → dispara streak de prática
      const today = new Date().toISOString().split("T")[0]!;
      const startOfDay = `${today} 00:00:00`;
      const countRows = await db.execute(
        `SELECT COUNT(*) as cnt FROM practice_sessions WHERE userId = ${ctx.user.id} AND created_at >= '${startOfDay}'`
      ) as any;
      const todayCount = Number((countRows[0] as any[])[0]?.cnt ?? 0);

      if (todayCount >= 5) {
        const { updatePracticeStreak, checkAndAwardBadges } = await import("../db");
        const streakResult = await updatePracticeStreak(ctx.user.id);
        await checkAndAwardBadges(ctx.user.id);
        return { success: true, streakResult };
      }

      return { success: true, practiceCount: todayCount };
    }),

  // ── Get practice stats ─────────────────────────────────────────────────────
  myStats: protectedProcedure.query(async ({ ctx }) => {
    const db = await getDb();
    if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
    const rows = await db.execute(`
      SELECT
        COUNT(*) as total,
        SUM(isCorrect) as correct,
        d.namePt as disciplineName,
        ps.disciplineId
      FROM practice_sessions ps
      LEFT JOIN disciplines d ON ps.disciplineId = d.id
      WHERE ps.userId = ${ctx.user.id}
      GROUP BY ps.disciplineId, d.namePt
    `) as any;
    return rows[0] as any[];
  }),

  // ── Get performance dashboard data ─────────────────────────────────────────
  performanceDashboard: protectedProcedure.query(async ({ ctx }) => {
    const db = await getDb();
    if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });

    // Overall stats
    const [overall] = await db.execute(`
      SELECT
        totalExams, totalQuestions, totalCorrect, xp, level, streak,
        CASE WHEN totalQuestions > 0 THEN ROUND(totalCorrect * 100.0 / totalQuestions, 1) ELSE 0 END as accuracy
      FROM users WHERE id = ${ctx.user.id}
    `) as any;

    // Per-discipline accuracy from exams
    const [byDiscipline] = await db.execute(`
      SELECT d.namePt as disciplineName, d.id as disciplineId,
        COUNT(ea.id) as total,
        SUM(ea.isCorrect) as correct,
        CASE WHEN COUNT(ea.id) > 0 THEN ROUND(SUM(ea.isCorrect) * 100.0 / COUNT(ea.id), 1) ELSE 0 END as accuracy
      FROM exam_answers ea
      JOIN exams e ON ea.examId = e.id
      JOIN questions q ON ea.questionId = q.id
      JOIN disciplines d ON q.disciplineId = d.id
      WHERE e.userId = ${ctx.user.id} AND e.status = 'completed'
      GROUP BY d.id, d.namePt
      ORDER BY accuracy ASC
    `) as any;

    // Last 10 exams
    const [recentExams] = await db.execute(`
      SELECT e.id, e.accuracy, e.correctAnswers, e.totalQuestions, e.xpEarned,
        e.timeSpentSeconds, e.createdAt, d.namePt as disciplineName
      FROM exams e
      LEFT JOIN disciplines d ON e.disciplineId = d.id
      WHERE e.userId = ${ctx.user.id} AND e.status = 'completed'
      ORDER BY e.createdAt DESC
      LIMIT 10
    `) as any;

    // Per-subject accuracy from exams
    const [bySubject] = await db.execute(`
      SELECT s.namePt as subjectName, s.id as subjectId, d.namePt as disciplineName, d.id as disciplineId,
        COUNT(ea.id) as total,
        SUM(ea.isCorrect) as correct,
        CASE WHEN COUNT(ea.id) > 0 THEN ROUND(SUM(ea.isCorrect) * 100.0 / COUNT(ea.id), 1) ELSE 0 END as accuracy
      FROM exam_answers ea
      JOIN exams e ON ea.examId = e.id
      JOIN questions q ON ea.questionId = q.id
      JOIN subjects s ON q.subjectId = s.id
      JOIN disciplines d ON q.disciplineId = d.id
      WHERE e.userId = ${ctx.user.id} AND e.status = 'completed' AND q.subjectId IS NOT NULL
      GROUP BY s.id, s.namePt, d.id, d.namePt
      ORDER BY accuracy ASC
    `) as any;

    // XP history (last 30 days)
    const [xpHistory] = await db.execute(`
      SELECT DATE(createdAt) as date, SUM(amount) as xp
      FROM xp_events
      WHERE userId = ${ctx.user.id} AND createdAt >= DATE_SUB(NOW(), INTERVAL 30 DAY)
      GROUP BY DATE(createdAt)
      ORDER BY date ASC
    `) as any;

    return {
      overall: (overall as any[])[0],
      byDiscipline: byDiscipline as any[],
      bySubject: bySubject as any[],
      recentExams: recentExams as any[],
      xpHistory: xpHistory as any[],
    };
  }),

  // ── Get active incorrect questions count and details ────────────────────────
  getIncorrectQuestions: protectedProcedure.query(async ({ ctx }) => {
    const db = await getDb();
    if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });

    // Active incorrect questions count
    const [countRows] = await db.execute(`
      SELECT COUNT(DISTINCT q.id) as cnt
      FROM practice_sessions ps
      JOIN questions q ON ps.questionId = q.id
      WHERE ps.userId = ${ctx.user.id} AND ps.isCorrect = 0
        AND q.active = 1 AND q.status = 'approved'
        AND NOT EXISTS (
          SELECT 1 FROM practice_sessions ps2 
          WHERE ps2.userId = ps.userId AND ps2.questionId = ps.questionId AND ps2.isCorrect = 1 AND ps2.id > ps.id
        )
    `) as any;

    const totalIncorrect = Number((countRows as any[])[0]?.cnt ?? 0);

    // Grouped by discipline
    const [byDisciplineRows] = await db.execute(`
      SELECT d.id as disciplineId, d.namePt as disciplineName, COUNT(DISTINCT q.id) as cnt
      FROM practice_sessions ps
      JOIN questions q ON ps.questionId = q.id
      JOIN disciplines d ON q.disciplineId = d.id
      WHERE ps.userId = ${ctx.user.id} AND ps.isCorrect = 0
        AND q.active = 1 AND q.status = 'approved'
        AND NOT EXISTS (
          SELECT 1 FROM practice_sessions ps2 
          WHERE ps2.userId = ps.userId AND ps2.questionId = ps.questionId AND ps2.isCorrect = 1 AND ps2.id > ps.id
        )
      GROUP BY d.id, d.namePt
    `) as any;

    return {
      totalIncorrect,
      byDiscipline: byDisciplineRows as any[],
    };
  }),

  // ── Get a random active incorrect question for practice ─────────────────────
  getIncorrectQuestion: protectedProcedure
    .input(z.object({
      disciplineId: z.number().optional(),
      excludeIds: z.array(z.number()).optional(),
    }))
    .query(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });

      const excludeList = input.excludeIds ?? [];
      const conditions = [
        `ps.userId = ${ctx.user.id}`,
        `ps.isCorrect = 0`,
        `q.active = 1`,
        `q.status = 'approved'`,
        `NOT EXISTS (
          SELECT 1 FROM practice_sessions ps2 
          WHERE ps2.userId = ps.userId AND ps2.questionId = ps.questionId AND ps2.isCorrect = 1 AND ps2.id > ps.id
        )`
      ];

      if (input.disciplineId) {
        conditions.push(`q.disciplineId = ${input.disciplineId}`);
      }
      if (excludeList.length > 0) {
        conditions.push(`q.id NOT IN (${excludeList.join(",")})`);
      }

      const rows = await db.execute(`
        SELECT q.id, q.textPt, q.textEn, q.options, q.correctOption, q.explanationPt,
          q.explanationEn, q.difficulty, q.questionType, q.questionModel, q.modelId,
          q.imageUrl, q.year, q.banca, q.author, q.subjectTag,
          q.assertion1, q.assertion2, q.a1Verdadeira, q.a2Verdadeira, q.relacaoCausal,
          q.formatData, q.isAnulada, q.isDesatualizada,
          d.namePt as disciplineName, s.namePt as subjectName
        FROM practice_sessions ps
        JOIN questions q ON ps.questionId = q.id
        LEFT JOIN disciplines d ON q.disciplineId = d.id
        LEFT JOIN subjects s ON q.subjectId = s.id
        WHERE ${conditions.join(" AND ")}
        ORDER BY RAND()
        LIMIT 1
      `) as any;

      const q = (rows[0] as any[])[0];
      if (!q) return null;
      return q;
    }),
});
