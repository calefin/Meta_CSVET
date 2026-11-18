import { z } from "zod";
import {
  LEVELS,
  addCoins,
  buyShopItem,
  claimDailyChest,
  getAllBadges,
  getLevelForXp,
  getNextLevel,
  getShopItems,
  getUserBadgeIds,
  getUserById,
  recoverStreak,
  spinDailyRoulette,
  updateLoginStreak,
} from "../db";
import { protectedProcedure, publicProcedure, router } from "../_core/trpc";
import { TRPCError } from "@trpc/server";

export const gamificationRouter = router({
  levels: publicProcedure.query(() => LEVELS),

  myProfile: protectedProcedure.query(async ({ ctx }) => {
    const user = await getUserById(ctx.user.id);
    if (!user) return null;

    const currentLevel = getLevelForXp(user.xp);
    const nextLevel = getNextLevel(currentLevel.level);
    const allBadges = await getAllBadges();
    const earnedBadgeIds = await getUserBadgeIds(user.id);

    const xpInCurrentLevel = user.xp - currentLevel.xpRequired;
    const xpForNextLevel = nextLevel ? nextLevel.xpRequired - currentLevel.xpRequired : 0;
    const progressPercent = nextLevel && xpForNextLevel > 0 ? Math.min(100, (xpInCurrentLevel / xpForNextLevel) * 100) : 100;

    const accuracy = user.totalQuestions > 0 ? (user.totalCorrect / user.totalQuestions) * 100 : 0;

    return {
      user: {
        id: user.id,
        name: user.name,
        email: user.email,
        xp: user.xp,
        level: user.level,
        streak: user.streak,
        streakFreezes: user.streakFreezes ?? 0,
        coins: user.coins ?? 0,
        plan: user.plan,
        trialEndsAt: user.trialEndsAt,
        totalExams: user.totalExams,
        totalQuestions: user.totalQuestions,
        totalCorrect: user.totalCorrect,
        accuracy: Math.round(accuracy * 10) / 10,
      },
      currentLevel,
      nextLevel,
      progressPercent: Math.round(progressPercent),
      xpInCurrentLevel,
      xpForNextLevel,
      badges: allBadges.map((b) => ({ ...b, earned: earnedBadgeIds.includes(b.id) })),
    };
  }),

  badges: publicProcedure.query(() => getAllBadges()),

  dailyLogin: protectedProcedure.mutation(async ({ ctx }) => {
    return updateLoginStreak(ctx.user.id);
  }),

  // ── Streak ──────────────────────────────────────────────────────────────────

  streakStatus: protectedProcedure.query(async ({ ctx }) => {
    const user = await getUserById(ctx.user.id);
    if (!user) throw new TRPCError({ code: "NOT_FOUND" });
    const today = new Date().toISOString().split("T")[0]!;
    const currentMonth = today.slice(0, 7);
    return {
      streak: user.streak,
      streakFreezes: user.streakFreezes ?? 0,
      lastPracticeDate: user.lastPracticeDate ?? null,
      practiceToday: user.lastPracticeDate === today,
      canRecoverStreak: user.streakRecoveryUsedAt !== currentMonth,
      coins: user.coins ?? 0,
    };
  }),

  claimStreakRecovery: protectedProcedure.mutation(async ({ ctx }) => {
    const result = await recoverStreak(ctx.user.id);
    if (!result.success) throw new TRPCError({ code: "BAD_REQUEST", message: result.error });
    return result;
  }),

  // ── Baú Diário ──────────────────────────────────────────────────────────────

  claimDailyChest: protectedProcedure.mutation(async ({ ctx }) => {
    const result = await claimDailyChest(ctx.user.id);
    if (!result.success) throw new TRPCError({ code: "BAD_REQUEST", message: result.error });
    return result;
  }),

  dailyChestStatus: protectedProcedure.query(async ({ ctx }) => {
    const { getDb } = await import("../db");
    const { dailyChests, practiceSessions } = await import("../../drizzle/schema");
    const { and, eq, sql } = await import("drizzle-orm");
    const db = await getDb();
    if (!db) return { canClaim: false, claimed: false, practiceCount: 0 };

    const today = new Date().toISOString().split("T")[0]!;
    const startOfDay = `${today} 00:00:00`;

    const [chestRow] = await db.select().from(dailyChests)
      .where(and(eq(dailyChests.userId, ctx.user.id), eq(dailyChests.claimedDate, today))).limit(1);

    const [practiceRow] = await db.select({ count: sql<number>`count(*)` }).from(practiceSessions)
      .where(and(eq(practiceSessions.userId, ctx.user.id), sql`created_at >= ${startOfDay}`));

    const practiceCount = Number(practiceRow?.count ?? 0);
    return {
      claimed: !!chestRow,
      canClaim: !chestRow && practiceCount >= 5,
      practiceCount,
      reward: chestRow ? { type: chestRow.rewardType, amount: chestRow.rewardAmount } : null,
    };
  }),

  // ── Roleta Diária ────────────────────────────────────────────────────────────

  spinRoulette: protectedProcedure.mutation(async ({ ctx }) => {
    const result = await spinDailyRoulette(ctx.user.id);
    if (!result.success) throw new TRPCError({ code: "BAD_REQUEST", message: result.error });
    return result;
  }),

  rouletteStatus: protectedProcedure.query(async ({ ctx }) => {
    const { getDb } = await import("../db");
    const { dailyRoulette } = await import("../../drizzle/schema");
    const { and, eq } = await import("drizzle-orm");
    const db = await getDb();
    if (!db) return { canSpin: false, spun: false };
    const today = new Date().toISOString().split("T")[0]!;
    const [row] = await db.select().from(dailyRoulette)
      .where(and(eq(dailyRoulette.userId, ctx.user.id), eq(dailyRoulette.spunDate, today))).limit(1);
    return {
      spun: !!row,
      canSpin: !row,
      reward: row ? { type: row.rewardType, amount: row.rewardAmount } : null,
    };
  }),

  // ── Loja ─────────────────────────────────────────────────────────────────────

  shopList: publicProcedure
    .input(z.object({ productType: z.enum(["gamification", "course", "content"]).optional() }))
    .query(async ({ input }) => getShopItems(input.productType)),

  shopBuy: protectedProcedure
    .input(z.object({ itemId: z.number() }))
    .mutation(async ({ ctx, input }) => {
      const result = await buyShopItem(ctx.user.id, input.itemId);
      if (!result.success) throw new TRPCError({ code: "BAD_REQUEST", message: result.error });
      return result;
    }),

  // ── Coins ────────────────────────────────────────────────────────────────────

  myCoins: protectedProcedure.query(async ({ ctx }) => {
    const user = await getUserById(ctx.user.id);
    return {
      coins: user?.coins ?? 0,
      xp: user?.xp ?? 0,
      level: user?.level ?? 1,
      streak: user?.streak ?? 0,
    };
  }),

  // ── Daily Missions ────────────────────────────────────────────────────────────

  dailyMissions: protectedProcedure.query(async ({ ctx }) => {
    const { getDb } = await import("../db");
    const db = await getDb();
    if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });

    const userId = ctx.user.id;
    const user = await getUserById(userId);
    if (!user) throw new TRPCError({ code: "NOT_FOUND" });

    const today = new Date().toISOString().slice(0, 10);
    const now = new Date();
    const dayOfWeek = now.getDay();
    const mondayOffset = dayOfWeek === 0 ? 6 : dayOfWeek - 1;
    const monday = new Date(now);
    monday.setDate(now.getDate() - mondayOffset);
    const weekStart = monday.toISOString().slice(0, 10);
    const monthStart = today.slice(0, 7) + "-01";

    const [[tp], [te], [wp], [we], [mp], [me], [dr]] = await Promise.all([
      db.execute(`SELECT COUNT(*) as c FROM practice_sessions WHERE userId=${userId} AND DATE(createdAt)='${today}'`) as any,
      db.execute(`SELECT COUNT(*) as c FROM exams WHERE userId=${userId} AND status='completed' AND DATE(COALESCE(completedAt,createdAt))='${today}'`) as any,
      db.execute(`SELECT COUNT(*) as c FROM practice_sessions WHERE userId=${userId} AND DATE(createdAt)>='${weekStart}'`) as any,
      db.execute(`SELECT COUNT(*) as c FROM exams WHERE userId=${userId} AND status='completed' AND DATE(COALESCE(completedAt,createdAt))>='${weekStart}'`) as any,
      db.execute(`SELECT COUNT(*) as c FROM practice_sessions WHERE userId=${userId} AND DATE(createdAt)>='${monthStart}'`) as any,
      db.execute(`SELECT COUNT(*) as c FROM exams WHERE userId=${userId} AND status='completed' AND DATE(COALESCE(completedAt,createdAt))>='${monthStart}'`) as any,
      db.execute(`SELECT COUNT(*) as c FROM (SELECT DATE(createdAt) d FROM practice_sessions WHERE userId=${userId} AND DATE(createdAt)>='${monthStart}' GROUP BY d HAVING COUNT(*)>=5) x`) as any,
    ]);

    const currentLevel = getLevelForXp(user.xp);
    const nextLevel = getNextLevel(currentLevel.level);
    const xpInLevel = user.xp - currentLevel.xpRequired;
    const xpForNext = nextLevel ? nextLevel.xpRequired - currentLevel.xpRequired : 0;

    const todayPractice = Number(tp[0]?.c ?? 0);
    const todayExam = Number(te[0]?.c ?? 0);
    const weekPractice = Number(wp[0]?.c ?? 0);
    const weekExam = Number(we[0]?.c ?? 0);
    const monthPractice = Number(mp[0]?.c ?? 0);
    const monthExam = Number(me[0]?.c ?? 0);
    const daysRouteCompleted = Number(dr[0]?.c ?? 0);

    return {
      profile: {
        xp: user.xp,
        level: user.level,
        streak: user.streak,
        coins: user.coins,
        levelTitle: (currentLevel as any).title ?? `Nível ${user.level}`,
        levelIcon: (currentLevel as any).icon ?? "🩺",
        xpProgress: xpForNext > 0 ? Math.min(100, Math.round((xpInLevel / xpForNext) * 100)) : 100,
        xpInLevel,
        xpForNext,
        nextLevelTitle: (nextLevel as any)?.title,
      },
      daily: {
        login: true,
        practice5: todayPractice >= 5,
        practice5Count: Math.min(todayPractice, 5),
        practice10: todayPractice >= 10,
        practice10Count: Math.min(todayPractice, 10),
        simulado: todayExam >= 1,
        routeComplete: todayPractice >= 5,
      },
      weekly: {
        practiceCount: weekPractice,
        practiceGoal: 30,
        practice: weekPractice >= 30,
        examCount: weekExam,
        examGoal: 2,
        exam: weekExam >= 2,
        streak: user.streak >= 5,
        streakGoal: 5,
        routeComplete: weekPractice >= 30 && weekExam >= 2,
      },
      monthly: {
        practiceCount: monthPractice,
        practiceGoal: 150,
        practice: monthPractice >= 150,
        examCount: monthExam,
        examGoal: 5,
        exam: monthExam >= 5,
        daysCompleted: daysRouteCompleted,
        daysGoal: 20,
        days: daysRouteCompleted >= 20,
        routeComplete: daysRouteCompleted >= 20,
      },
    };
  }),
});
