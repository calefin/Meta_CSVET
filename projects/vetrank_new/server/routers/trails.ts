import { TRPCError } from "@trpc/server";
import { z } from "zod";
import { and, eq, inArray, sql } from "drizzle-orm";
import { getDb } from "../db";
import {
  trails, trailModules, trailModuleQuestions, trailEnrollments,
  trailModuleProgress, trailModuleAnswers, trailPurchases,
  questions, users, xpEvents,
} from "../../drizzle/schema";
import { protectedProcedure, publicProcedure, router } from "../_core/trpc";
import { MercadoPagoConfig, Preference } from "mercadopago";

// ─── DB helper ────────────────────────────────────────────────────────────────
async function db() {
  const d = await getDb();
  if (!d) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR", message: "DB unavailable" });
  return d;
}

// ─── MP client ───────────────────────────────────────────────────────────────
function getMPClient() {
  const token = process.env.MP_ACCESS_TOKEN;
  if (!token) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR", message: "MP_ACCESS_TOKEN não configurado" });
  return new MercadoPagoConfig({ accessToken: token });
}

// ─── Access check ─────────────────────────────────────────────────────────────
async function assertTrailAccess(userId: number, trail: { id: number; accessType: string }) {
  if (trail.accessType === "purchase") {
    const d = await db();
    const [purchase] = await d
      .select({ id: trailPurchases.id })
      .from(trailPurchases)
      .where(and(
        eq(trailPurchases.userId, userId),
        eq(trailPurchases.trailId, trail.id),
        eq(trailPurchases.status, "active"),
      ))
      .limit(1);
    if (!purchase) throw new TRPCError({ code: "FORBIDDEN", message: "Trilha não adquirida." });
  } else {
    // subscription trail — requires active premium or trial
    const d = await db();
    const [u] = await d.select({ plan: users.plan, premiumEndsAt: users.premiumEndsAt, trialEndsAt: users.trialEndsAt, role: users.role })
      .from(users).where(eq(users.id, userId)).limit(1);
    if (!u) throw new TRPCError({ code: "NOT_FOUND" });
    const isStaff = ["admin", "superuser", "coordinator", "teacher"].includes(u.role);
    if (isStaff) return;
    const now = new Date();
    const hasAccess = (u.plan === "premium" && !!u.premiumEndsAt && u.premiumEndsAt > now)
      || (u.plan === "trial" && !!u.trialEndsAt && u.trialEndsAt > now);
    if (!hasAccess) throw new TRPCError({ code: "FORBIDDEN", message: "Acesso premium necessário para esta trilha." });
  }
}

// ─── XP constants ─────────────────────────────────────────────────────────────
const TRAIL_XP_PER_CORRECT = 3;

// ─── Level thresholds (exponential curve) ─────────────────────────────────────
export const LEVEL_THRESHOLDS = [0, 100, 250, 500, 900, 1500, 2400, 3800, 6000, 9500, 15000];
export function calcLevel(xp: number): number {
  let level = 1;
  for (let i = 1; i < LEVEL_THRESHOLDS.length; i++) {
    if (xp >= LEVEL_THRESHOLDS[i]) level = i + 1;
    else break;
  }
  return Math.min(level, LEVEL_THRESHOLDS.length);
}

// ─── Award XP helper ──────────────────────────────────────────────────────────
export async function awardTrailXp(userId: number, amount: number, reason: string) {
  if (amount <= 0) return;
  const d = await db();
  const [row] = await d.select({ xp: users.xp }).from(users).where(eq(users.id, userId));
  const currentXp = row?.xp ?? 0;
  const newXp = currentXp + amount;
  const newLevel = calcLevel(newXp);
  await d.update(users).set({ xp: newXp, level: newLevel }).where(eq(users.id, userId));
  await d.insert(xpEvents).values({ userId, amount, reason });
  const now = new Date();
  const weekKey = `${now.getFullYear()}-${String(Math.ceil(now.getDate() / 7)).padStart(2, "0")}`;
  const monthKey = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}`;
  await d.execute(
    sql`INSERT INTO weekly_xp (userId, weekKey, xp) VALUES (${userId}, ${weekKey}, ${amount}) ON DUPLICATE KEY UPDATE xp = xp + ${amount}`
  );
  await d.execute(
    sql`INSERT INTO monthly_xp (userId, monthKey, xp) VALUES (${userId}, ${monthKey}, ${amount}) ON DUPLICATE KEY UPDATE xp = xp + ${amount}`
  );
}

// ─── Get questions for a module ───────────────────────────────────────────────
async function getModuleQuestions(moduleId: number, count: number, excludeIds: number[] = []) {
  const d = await db();
  const assigned = await d
    .select({ questionId: trailModuleQuestions.questionId, order: trailModuleQuestions.order })
    .from(trailModuleQuestions)
    .where(eq(trailModuleQuestions.moduleId, moduleId))
    .orderBy(trailModuleQuestions.order);

  if (assigned.length > 0) {
    const ids = assigned
      .map((a: { questionId: number }) => a.questionId)
      .filter((id: number) => !excludeIds.includes(id));
    if (ids.length === 0) return [];
    return d.select().from(questions).where(and(inArray(questions.id, ids), eq(questions.active, true)));
  }

  const [mod] = await d.select().from(trailModules).where(eq(trailModules.id, moduleId));
  if (!mod) return [];
  const filter = (mod.questionFilter as Record<string, unknown>) ?? {};
  const conds: ReturnType<typeof eq>[] = [eq(questions.active, true)];
  if (filter.disciplineId) conds.push(eq(questions.disciplineId, filter.disciplineId as number));
  if (Array.isArray(filter.subjectIds) && (filter.subjectIds as number[]).length) {
    conds.push(inArray(questions.subjectId, filter.subjectIds as number[]));
  }
  if (filter.difficulty && filter.difficulty !== "mixed") {
    conds.push(eq(questions.difficulty, filter.difficulty as "easy" | "medium" | "hard"));
  }
  const pool = await d.select().from(questions).where(and(...conds)).limit(count * 3);
  return [...pool].sort(() => Math.random() - 0.5).slice(0, count);
}

type QuestionRow = typeof questions.$inferSelect;
function mapQ(q: QuestionRow) {
  return {
    id: q.id, textPt: q.textPt, textEn: q.textEn, options: q.options,
    correctOption: q.correctOption, explanationPt: q.explanationPt, explanationEn: q.explanationEn,
    questionType: q.questionType, formatData: q.formatData, modelId: q.modelId,
    assertion1: q.assertion1, assertion2: q.assertion2,
    a1Verdadeira: q.a1Verdadeira, a2Verdadeira: q.a2Verdadeira, relacaoCausal: q.relacaoCausal,
    imageUrl: q.imageUrl, difficulty: q.difficulty, banca: q.banca,
    author: q.author, subjectTag: q.subjectTag, isAnulada: q.isAnulada, isDesatualizada: q.isDesatualizada,
  };
}

export const trailsRouter = router({
  // ─── List active trails ───────────────────────────────────────────────────
  list: publicProcedure
    .input(z.object({ disciplineId: z.number().optional() }).optional())
    .query(async ({ input }) => {
      const d = await db();
      const conds: ReturnType<typeof eq>[] = [eq(trails.active, true)];
      if (input?.disciplineId) conds.push(eq(trails.disciplineId, input.disciplineId));
      const rows = await d.select({
        id: trails.id, disciplineId: trails.disciplineId, title: trails.title,
        description: trails.description, totalHours: trails.totalHours, passingScore: trails.passingScore,
      }).from(trails).where(and(...conds));
      if (rows.length === 0) return [];
      const trailIds = rows.map((r: { id: number }) => r.id);
      const modCounts = await d
        .select({ trailId: trailModules.trailId, cnt: sql<number>`COUNT(*)` })
        .from(trailModules).where(inArray(trailModules.trailId, trailIds)).groupBy(trailModules.trailId);
      const modMap: Record<number, number> = {};
      for (const m of modCounts) modMap[m.trailId] = m.cnt;
      return rows.map((r: { id: number }) => ({ ...r, moduleCount: modMap[r.id] ?? 0 }));
    }),

  // ─── Get trail by ID ──────────────────────────────────────────────────────
  getById: publicProcedure
    .input(z.object({ id: z.number() }))
    .query(async ({ input }) => {
      const d = await db();
      const [trail] = await d.select().from(trails).where(eq(trails.id, input.id));
      if (!trail) throw new TRPCError({ code: "NOT_FOUND" });
      const mods = await d.select().from(trailModules)
        .where(eq(trailModules.trailId, input.id)).orderBy(trailModules.order);
      return { ...trail, modules: mods };
    }),

  // ─── Enroll ───────────────────────────────────────────────────────────────
  enroll: protectedProcedure
    .input(z.object({ trailId: z.number() }))
    .mutation(async ({ ctx, input }) => {
      const d = await db();
      const userId = ctx.user.id;

      const [trail] = await d.select({ id: trails.id, accessType: trails.accessType })
        .from(trails).where(eq(trails.id, input.trailId)).limit(1);
      if (!trail) throw new TRPCError({ code: "NOT_FOUND" });

      await assertTrailAccess(userId, trail);

      const [existing] = await d.select().from(trailEnrollments)
        .where(and(eq(trailEnrollments.userId, userId), eq(trailEnrollments.trailId, input.trailId)));
      if (existing) return existing;

      const mods = await d.select().from(trailModules)
        .where(eq(trailModules.trailId, input.trailId)).orderBy(trailModules.order);
      if (mods.length === 0) throw new TRPCError({ code: "BAD_REQUEST", message: "Trail has no modules" });

      const [result] = await d.insert(trailEnrollments).values({
        userId, trailId: input.trailId, status: "enrolled", currentModuleId: mods[0].id,
      });
      const enrollmentId = (result as { insertId: number }).insertId;
      for (let i = 0; i < mods.length; i++) {
        await d.insert(trailModuleProgress).values({
          enrollmentId, moduleId: mods[i].id, userId,
          status: i === 0 ? "available" : "locked",
        });
      }
      return { enrollmentId, currentModuleId: mods[0].id };
    }),

  // ─── Checkout para trilha paga ────────────────────────────────────────────
  createPreference: protectedProcedure
    .input(z.object({
      trailId: z.number(),
      successUrl: z.string().url(),
      failureUrl: z.string().url(),
      pendingUrl: z.string().url(),
    }))
    .mutation(async ({ ctx, input }) => {
      const d = await db();
      const [trail] = await d
        .select({ id: trails.id, title: trails.title, price: trails.price, accessType: trails.accessType, active: trails.active })
        .from(trails).where(eq(trails.id, input.trailId)).limit(1);

      if (!trail || !trail.active) throw new TRPCError({ code: "NOT_FOUND", message: "Trilha não encontrada." });
      if (trail.accessType !== "purchase") throw new TRPCError({ code: "BAD_REQUEST", message: "Trilha não disponível para compra avulsa." });
      if (!trail.price) throw new TRPCError({ code: "BAD_REQUEST", message: "Trilha sem preço definido." });

      const [existing] = await d
        .select({ id: trailPurchases.id })
        .from(trailPurchases)
        .where(and(eq(trailPurchases.userId, ctx.user.id), eq(trailPurchases.trailId, input.trailId), eq(trailPurchases.status, "active")))
        .limit(1);
      if (existing) throw new TRPCError({ code: "CONFLICT", message: "Você já possui acesso a esta trilha." });

      const [u] = await d
        .select({ name: users.name, email: users.email, cpf: users.cpf })
        .from(users).where(eq(users.id, ctx.user.id)).limit(1);

      const price = Number(trail.price);
      const client = getMPClient();
      const preference = new Preference(client);

      const result = await preference.create({
        body: {
          items: [{ id: `trail_${trail.id}`, title: trail.title, quantity: 1, unit_price: price, currency_id: "BRL" }],
          payer: {
            name: u?.name ?? undefined,
            email: u?.email ?? undefined,
            identification: u?.cpf ? { type: "CPF", number: u.cpf.replace(/\D/g, "") } : undefined,
          },
          external_reference: JSON.stringify({ userId: ctx.user.id, trailId: trail.id, type: "trail" }),
          back_urls: { success: input.successUrl, failure: input.failureUrl, pending: input.pendingUrl },
          auto_return: "approved",
          notification_url: `${process.env.SERVER_URL ?? process.env.VITE_SERVER_URL ?? ""}/api/mp/webhook`,
        },
      });

      await d.insert(trailPurchases).values({
        userId: ctx.user.id, trailId: trail.id,
        pricePaid: String(price) as any, status: "pending",
      });

      return { checkoutUrl: result.init_point, sandboxUrl: result.sandbox_init_point };
    }),

  // ─── Minhas compras de trilha ─────────────────────────────────────────────
  myPurchases: protectedProcedure.query(async ({ ctx }) => {
    const d = await db();
    return d.select({
      id: trailPurchases.id,
      trailId: trailPurchases.trailId,
      status: trailPurchases.status,
      pricePaid: trailPurchases.pricePaid,
      purchasedAt: trailPurchases.purchasedAt,
    })
      .from(trailPurchases)
      .where(and(eq(trailPurchases.userId, ctx.user.id), eq(trailPurchases.status, "active")));
  }),

  // ─── Get enrollment ───────────────────────────────────────────────────────
  getEnrollment: protectedProcedure
    .input(z.object({ trailId: z.number() }))
    .query(async ({ ctx, input }) => {
      const d = await db();
      const userId = ctx.user.id;
      const [enrollment] = await d.select().from(trailEnrollments)
        .where(and(eq(trailEnrollments.userId, userId), eq(trailEnrollments.trailId, input.trailId)));
      if (!enrollment) return null;
      const progress = await d.select().from(trailModuleProgress)
        .where(eq(trailModuleProgress.enrollmentId, enrollment.id));
      return { ...enrollment, moduleProgress: progress };
    }),

  // ─── Get module questions ─────────────────────────────────────────────────
  getModuleQuestions: protectedProcedure
    .input(z.object({ moduleId: z.number(), trailId: z.number() }))
    .query(async ({ ctx, input }) => {
      const d = await db();
      const userId = ctx.user.id;
      const [enrollment] = await d.select().from(trailEnrollments)
        .where(and(eq(trailEnrollments.userId, userId), eq(trailEnrollments.trailId, input.trailId)));
      if (!enrollment) throw new TRPCError({ code: "FORBIDDEN", message: "Not enrolled" });
      const [mod] = await d.select().from(trailModules).where(eq(trailModules.id, input.moduleId));
      if (!mod) throw new TRPCError({ code: "NOT_FOUND" });
      
      const [progress] = await d.select().from(trailModuleProgress)
        .where(and(eq(trailModuleProgress.enrollmentId, enrollment.id), eq(trailModuleProgress.moduleId, input.moduleId)));
      
      const currentAttempt = progress && progress.status === "failed" ? progress.attempts + 1 : Math.max(1, progress?.attempts || 1);

      const prevAnswers = await d.select({ questionId: trailModuleAnswers.questionId })
        .from(trailModuleAnswers)
        .where(and(
          eq(trailModuleAnswers.userId, userId), 
          eq(trailModuleAnswers.moduleId, input.moduleId),
          eq(trailModuleAnswers.attemptNumber, currentAttempt)
        ));
      const prevIds = prevAnswers.map((a: { questionId: number }) => a.questionId);
      
      const answeredCount = prevIds.length;
      const remainingForModule = Math.max(0, mod.questionCount - answeredCount);
      const fetchCount = mod.cycleSize > 0 ? Math.min(mod.cycleSize, remainingForModule) : remainingForModule;
      
      if (fetchCount <= 0) return []; // Already finished this attempt's target

      let qs = await getModuleQuestions(input.moduleId, fetchCount, prevIds);
      if (qs.length < fetchCount) {
        const extra = await getModuleQuestions(input.moduleId, fetchCount - qs.length, []);
        const existingIds = new Set(qs.map((q: QuestionRow) => q.id));
        qs = [...qs, ...extra.filter((q: QuestionRow) => !existingIds.has(q.id))].slice(0, fetchCount);
      }
      return qs.map((q: QuestionRow) => mapQ(q));
    }),

  // ─── Submit module answers ────────────────────────────────────────────────
  submitModuleAnswers: protectedProcedure
    .input(z.object({
      trailId: z.number(),
      moduleId: z.number(),
      answers: z.array(z.object({ questionId: z.number(), selectedOption: z.string() })),
    }))
    .mutation(async ({ ctx, input }) => {
      const d = await db();
      const userId = ctx.user.id;
      const [enrollment] = await d.select().from(trailEnrollments)
        .where(and(eq(trailEnrollments.userId, userId), eq(trailEnrollments.trailId, input.trailId)));
      if (!enrollment) throw new TRPCError({ code: "FORBIDDEN" });
      const [progress] = await d.select().from(trailModuleProgress)
        .where(and(eq(trailModuleProgress.enrollmentId, enrollment.id), eq(trailModuleProgress.moduleId, input.moduleId)));
      if (!progress) throw new TRPCError({ code: "NOT_FOUND" });
      const [mod] = await d.select().from(trailModules).where(eq(trailModules.id, input.moduleId));
      if (!mod) throw new TRPCError({ code: "NOT_FOUND" });

      const qIds = input.answers.map(a => a.questionId);
      const qs = await d.select({ id: questions.id, correctOption: questions.correctOption })
        .from(questions).where(inArray(questions.id, qIds));
      const correctMap: Record<number, string> = {};
      for (const q of qs) correctMap[q.id] = q.correctOption;

      const currentAttempt = progress.status === "failed" ? progress.attempts + 1 : Math.max(1, progress.attempts);
      
      let correctThisBatch = 0;
      for (const ans of input.answers) {
        const isCorrect = correctMap[ans.questionId] === ans.selectedOption;
        if (isCorrect) correctThisBatch++;
        await d.insert(trailModuleAnswers).values({
          progressId: progress.id, userId, moduleId: input.moduleId,
          questionId: ans.questionId, selectedOption: ans.selectedOption,
          isCorrect, attemptNumber: currentAttempt,
        });
      }
      
      const attemptAnswers = await d.select({ isCorrect: trailModuleAnswers.isCorrect })
        .from(trailModuleAnswers)
        .where(and(
           eq(trailModuleAnswers.progressId, progress.id),
           eq(trailModuleAnswers.attemptNumber, currentAttempt)
        ));
        
      const totalAnsweredInAttempt = attemptAnswers.length;
      const totalCorrectInAttempt = attemptAnswers.filter((a: any) => a.isCorrect).length;
      const isComplete = totalAnsweredInAttempt >= mod.questionCount;
      
      let passed = false;
      let score = progress.lastScore;
      let newStatus = progress.status;

      if (isComplete) {
        score = totalAnsweredInAttempt > 0 ? Math.round((totalCorrectInAttempt / totalAnsweredInAttempt) * 100) : 0;
        passed = score >= mod.minPassRate;
        newStatus = passed ? "passed" : "failed";
      } else {
        newStatus = "in_progress";
      }

      if (correctThisBatch > 0) {
        await awardTrailXp(userId, correctThisBatch * TRAIL_XP_PER_CORRECT, `trail_module_${input.moduleId}_attempt_${currentAttempt}`);
      }

      await d.update(trailModuleProgress).set({
        status: newStatus,
        attempts: currentAttempt,
        bestScore: isComplete ? Math.max(progress.bestScore, score) : progress.bestScore,
        lastScore: isComplete ? score : progress.lastScore,
        questionsAnswered: progress.questionsAnswered + input.answers.length,
        questionsCorrect: progress.questionsCorrect + correctThisBatch,
        completedAt: passed ? new Date() : undefined,
      }).where(eq(trailModuleProgress.id, progress.id));

      if (passed) {
        const allMods = await d.select({ id: trailModules.id })
          .from(trailModules).where(eq(trailModules.trailId, input.trailId)).orderBy(trailModules.order);
        const currentIdx = allMods.findIndex((m: { id: number }) => m.id === input.moduleId);
        const nextMod = allMods[currentIdx + 1];
        if (nextMod) {
          await d.update(trailModuleProgress).set({ status: "available" })
            .where(and(eq(trailModuleProgress.enrollmentId, enrollment.id), eq(trailModuleProgress.moduleId, nextMod.id)));
          await d.update(trailEnrollments).set({ status: "in_progress", currentModuleId: nextMod.id })
            .where(eq(trailEnrollments.id, enrollment.id));
        } else {
          await d.update(trailEnrollments).set({ status: "in_progress", currentModuleId: null })
            .where(eq(trailEnrollments.id, enrollment.id));
        }
      }
      return { 
        score: isComplete ? score : 0, 
        correct: correctThisBatch, 
        total: input.answers.length, 
        passed, 
        isComplete,
        minPassRate: mod.minPassRate, 
        attemptNumber: currentAttempt 
      };
    }),

  // ─── Get final exam ───────────────────────────────────────────────────────
  getFinalExam: protectedProcedure
    .input(z.object({ trailId: z.number() }))
    .query(async ({ ctx, input }) => {
      const d = await db();
      const userId = ctx.user.id;
      const [enrollment] = await d.select().from(trailEnrollments)
        .where(and(eq(trailEnrollments.userId, userId), eq(trailEnrollments.trailId, input.trailId)));
      if (!enrollment) throw new TRPCError({ code: "FORBIDDEN" });
      if (enrollment.status === "completed") throw new TRPCError({ code: "BAD_REQUEST", message: "Already completed" });
      const allProgress = await d.select({ status: trailModuleProgress.status })
        .from(trailModuleProgress).where(eq(trailModuleProgress.enrollmentId, enrollment.id));
      const allPassed = allProgress.every((p: { status: string }) => p.status === "passed");
      if (!allPassed) throw new TRPCError({ code: "FORBIDDEN", message: "Complete all modules first" });
      const [trail] = await d.select().from(trails).where(eq(trails.id, input.trailId));
      if (!trail) throw new TRPCError({ code: "NOT_FOUND" });
      const mods = await d.select().from(trailModules)
        .where(eq(trailModules.trailId, input.trailId)).orderBy(trailModules.order);
      const perMod = Math.ceil(trail.finalExamQuestions / mods.length);
      let allQs: QuestionRow[] = [];
      for (const mod of mods) {
        const qs = await getModuleQuestions(mod.id, perMod, []);
        allQs.push(...(qs as QuestionRow[]));
      }
      allQs = allQs.sort(() => Math.random() - 0.5).slice(0, trail.finalExamQuestions);
      return {
        questions: allQs.map((q: QuestionRow) => mapQ(q)),
        timeLimitSeconds: trail.finalExamTimeSeconds,
        passingScore: trail.passingScore,
      };
    }),

  // ─── Submit final exam ────────────────────────────────────────────────────
  submitFinalExam: protectedProcedure
    .input(z.object({
      trailId: z.number(),
      answers: z.array(z.object({ questionId: z.number(), selectedOption: z.string() })),
    }))
    .mutation(async ({ ctx, input }) => {
      const d = await db();
      const userId = ctx.user.id;
      const [enrollment] = await d.select().from(trailEnrollments)
        .where(and(eq(trailEnrollments.userId, userId), eq(trailEnrollments.trailId, input.trailId)));
      if (!enrollment) throw new TRPCError({ code: "FORBIDDEN" });
      const [trail] = await d.select().from(trails).where(eq(trails.id, input.trailId));
      if (!trail) throw new TRPCError({ code: "NOT_FOUND" });
      const qIds = input.answers.map(a => a.questionId);
      const qs = await d.select({ id: questions.id, correctOption: questions.correctOption })
        .from(questions).where(inArray(questions.id, qIds));
      const correctMap: Record<number, string> = {};
      for (const q of qs) correctMap[q.id] = q.correctOption;
      let correct = 0;
      for (const ans of input.answers) {
        if (correctMap[ans.questionId] === ans.selectedOption) correct++;
      }
      const total = input.answers.length;
      const score = total > 0 ? Math.round((correct / total) * 100) : 0;
      const passed = score >= trail.passingScore;
      if (correct > 0) {
        await awardTrailXp(userId, correct * TRAIL_XP_PER_CORRECT * 2, `trail_${input.trailId}_final_exam`);
      }
      if (passed) {
        await awardTrailXp(userId, 200, `trail_${input.trailId}_completion_bonus`);
        await d.update(trailEnrollments).set({
          status: "completed", finalExamScore: score, finalExamPassed: true, completedAt: new Date(),
        }).where(eq(trailEnrollments.id, enrollment.id));
      } else {
        await d.update(trailEnrollments).set({ finalExamScore: score, finalExamPassed: false })
          .where(eq(trailEnrollments.id, enrollment.id));
      }
      return { score, correct, total, passed, passingScore: trail.passingScore };
    }),

  // ─── My enrollments ───────────────────────────────────────────────────────
  myEnrollments: protectedProcedure.query(async ({ ctx }) => {
    const d = await db();
    const userId = ctx.user.id;
    const enrollments = await d.select({
      id: trailEnrollments.id, trailId: trailEnrollments.trailId,
      status: trailEnrollments.status, startedAt: trailEnrollments.startedAt,
      completedAt: trailEnrollments.completedAt, trailTitle: trails.title,
      trailDisciplineId: trails.disciplineId, trailTotalHours: trails.totalHours,
    }).from(trailEnrollments)
      .innerJoin(trails, eq(trailEnrollments.trailId, trails.id))
      .where(eq(trailEnrollments.userId, userId));
    const result = [];
    for (const e of enrollments) {
      const progress = await d.select({ status: trailModuleProgress.status })
        .from(trailModuleProgress).where(eq(trailModuleProgress.enrollmentId, e.id));
      const totalMods = progress.length;
      const passedMods = progress.filter((p: { status: string }) => p.status === "passed").length;
      result.push({ ...e, totalModules: totalMods, passedModules: passedMods });
    }
    return result;
  }),

  // ─── COORDINATOR: Create trail ────────────────────────────────────────────
  create: protectedProcedure
    .input(z.object({
      disciplineId: z.number(),
      title: z.string().min(3),
      description: z.string().optional(),
      totalHours: z.number().default(20),
      passingScore: z.number().min(50).max(100).default(70),
      finalExamQuestions: z.number().min(10).max(100).default(30),
      finalExamTimeSeconds: z.number().default(3600),
      accessType: z.enum(["subscription", "purchase"]).default("subscription"),
      price: z.number().positive().optional(),
      imageUrl: z.string().url().optional(),
    }))
    .mutation(async ({ ctx, input }) => {
      if (!["coordinator", "admin", "superuser"].includes(ctx.user.role)) throw new TRPCError({ code: "FORBIDDEN" });
      const d = await db();
      const { price, ...rest } = input;
      const [result] = await d.insert(trails).values({
        ...rest,
        price: price != null ? String(price) : undefined,
        createdBy: ctx.user.id,
      });
      return { id: (result as { insertId: number }).insertId };
    }),

  // ─── COORDINATOR: Update trail ────────────────────────────────────────────
  update: protectedProcedure
    .input(z.object({
      id: z.number(),
      title: z.string().min(3).optional(),
      description: z.string().optional(),
      totalHours: z.number().optional(),
      passingScore: z.number().min(50).max(100).optional(),
      finalExamQuestions: z.number().optional(),
      finalExamTimeSeconds: z.number().optional(),
      accessType: z.enum(["subscription", "purchase"]).optional(),
      price: z.number().positive().optional(),
      imageUrl: z.string().url().optional(),
      active: z.boolean().optional(),
    }))
    .mutation(async ({ ctx, input }) => {
      if (!["coordinator", "admin", "superuser"].includes(ctx.user.role)) throw new TRPCError({ code: "FORBIDDEN" });
      const d = await db();
      const { id, price, ...rest } = input;
      await d.update(trails).set({
        ...rest,
        ...(price != null ? { price: String(price) } : {}),
      }).where(eq(trails.id, id));
      return { success: true };
    }),

  // ─── COORDINATOR: Delete trail ────────────────────────────────────────────
  delete: protectedProcedure
    .input(z.object({ id: z.number() }))
    .mutation(async ({ ctx, input }) => {
      if (!["coordinator", "admin", "superuser"].includes(ctx.user.role)) throw new TRPCError({ code: "FORBIDDEN" });
      const d = await db();
      await d.update(trails).set({ active: false }).where(eq(trails.id, input.id));
      return { success: true };
    }),

  // ─── COORDINATOR: Add module ──────────────────────────────────────────────
  addModule: protectedProcedure
    .input(z.object({
      trailId: z.number(),
      title: z.string().min(2),
      summary: z.string().optional(),
      difficulty: z.enum(["easy", "medium", "hard", "mixed"]).default("mixed"),
      questionCount: z.number().min(5).max(1000).default(20),
      cycleSize: z.number().min(0).max(200).default(10),
      minPassRate: z.number().min(50).max(100).default(70),
      ebookUrl: z.string().url().optional(),
      ebookTitle: z.string().optional(),
      questionFilter: z.object({
        disciplineId: z.number().optional(),
        subjectIds: z.array(z.number()).optional(),
        difficulty: z.string().optional(),
        author: z.string().optional(),
        year: z.number().optional(),
      }).optional(),
    }))
    .mutation(async ({ ctx, input }) => {
      if (!["coordinator", "admin", "superuser"].includes(ctx.user.role)) throw new TRPCError({ code: "FORBIDDEN" });
      const d = await db();
      const [maxRow] = await d.select({ maxOrder: sql<number>`MAX(\`order\`)` })
        .from(trailModules).where(eq(trailModules.trailId, input.trailId));
      const nextOrder = (maxRow?.maxOrder ?? 0) + 1;
      const { trailId, ...rest } = input;
      const [result] = await d.insert(trailModules).values({ trailId, ...rest, order: nextOrder });
      return { id: (result as { insertId: number }).insertId, order: nextOrder };
    }),

  // ─── COORDINATOR: Update module ───────────────────────────────────────────
  updateModule: protectedProcedure
    .input(z.object({
      id: z.number(),
      title: z.string().optional(),
      summary: z.string().optional(),
      difficulty: z.enum(["easy", "medium", "hard", "mixed"]).optional(),
      questionCount: z.number().optional(),
      cycleSize: z.number().optional(),
      minPassRate: z.number().optional(),
      ebookUrl: z.string().url().optional().nullable(),
      ebookTitle: z.string().optional().nullable(),
      questionFilter: z.any().optional(),
    }))
    .mutation(async ({ ctx, input }) => {
      if (!["coordinator", "admin", "superuser"].includes(ctx.user.role)) throw new TRPCError({ code: "FORBIDDEN" });
      const d = await db();
      const { id, ...data } = input;
      await d.update(trailModules).set(data).where(eq(trailModules.id, id));
      return { success: true };
    }),

  // ─── COORDINATOR: Delete module ───────────────────────────────────────────
  deleteModule: protectedProcedure
    .input(z.object({ id: z.number() }))
    .mutation(async ({ ctx, input }) => {
      if (!["coordinator", "admin", "superuser"].includes(ctx.user.role)) throw new TRPCError({ code: "FORBIDDEN" });
      const d = await db();
      await d.delete(trailModuleQuestions).where(eq(trailModuleQuestions.moduleId, input.id));
      await d.delete(trailModules).where(eq(trailModules.id, input.id));
      return { success: true };
    }),

  // ─── COORDINATOR: Assign questions to module ──────────────────────────────
  assignQuestions: protectedProcedure
    .input(z.object({
      moduleId: z.number(),
      questionIds: z.array(z.number()),
    }))
    .mutation(async ({ ctx, input }) => {
      if (!["coordinator", "admin", "superuser"].includes(ctx.user.role)) throw new TRPCError({ code: "FORBIDDEN" });
      const d = await db();
      await d.delete(trailModuleQuestions).where(eq(trailModuleQuestions.moduleId, input.moduleId));
      for (let i = 0; i < input.questionIds.length; i++) {
        await d.insert(trailModuleQuestions).values({
          moduleId: input.moduleId, questionId: input.questionIds[i], order: i + 1,
        });
      }
      return { success: true, count: input.questionIds.length };
    }),

  // ─── Level info ───────────────────────────────────────────────────────────
  getLevelInfo: publicProcedure.query(async () => {
    return LEVEL_THRESHOLDS.map((xp, i) => ({
      level: i + 1, xpRequired: xp, xpToNext: LEVEL_THRESHOLDS[i + 1] ?? null,
    }));
  }),
});
