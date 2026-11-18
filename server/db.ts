import { and, asc, desc, eq, exists, gte, inArray, isNotNull, isNull, like, lte, ne, not, or, sql } from "drizzle-orm";
import { drizzle } from "drizzle-orm/mysql2";
import mysql from "mysql2";
import {
  Badge,
  Discipline,
  DiscursiveQuestion,
  Exam,
  InsertDiscursiveQuestion,
  InsertQuestion,
  InsertSavedFilter,
  InsertUser,
  Question,
  SavedFilter,
  Subject,
  Topic,
  User,
  badges,
  discursiveQuestions,
  disciplines,
  examAnswers,
  exams,
  monthlyXp,
  practiceSessions,
  questions,
  InsertQuestionAssertiva,
  InsertQuestionGroup,
  QuestionAssertiva,
  QuestionGroup,
  QuestionMatchingCol,
  QuestionMatchingPair,
  questionAssertivas,
  questionGroups,
  questionMatchingCols,
  questionMatchingPairs,
  savedFilters,
  subjects,
  topics,
  userBadges,
  users,
  weeklyXp,
  xpEvents,
  coinEvents,
  shopItems,
  purchases,
  dailyChests,
  dailyRoulette,
  leagues,
  leagueMemberships,
} from "../drizzle/schema";
import { ENV } from "./_core/env";

let _db: ReturnType<typeof drizzle> | null = null;

export async function getDb() {
  if (!_db && process.env.DATABASE_URL) {
    try {
      // Strip any existing charset param, then add our own
      const baseUrl = process.env.DATABASE_URL.replace(/\?.*$/, "");
      const url = new URL(baseUrl);
      const pool = mysql.createPool({
        host: url.hostname,
        port: url.port ? Number(url.port) : 3306,
        user: decodeURIComponent(url.username),
        password: decodeURIComponent(url.password),
        database: url.pathname.replace(/^\//, ""),
        charset: "utf8mb4",
        waitForConnections: true,
        connectionLimit: 10,
      });

      _db = drizzle(pool);
    } catch (error) {
      console.warn("[Database] Failed to connect:", error);
      _db = null;
    }
  }
  return _db;
}

// ─── User helpers ─────────────────────────────────────────────────────────────
export async function upsertUser(user: InsertUser): Promise<void> {
  if (!user.openId) throw new Error("User openId is required for upsert");
  const db = await getDb();
  if (!db) return;

  const values: InsertUser = { openId: user.openId };
  const updateSet: Record<string, unknown> = {};

  const textFields = ["name", "email", "loginMethod"] as const;
  for (const field of textFields) {
    const value = user[field];
    if (value !== undefined) {
      values[field] = value ?? null;
      updateSet[field] = value ?? null;
    }
  }

  if (user.lastSignedIn !== undefined) {
    values.lastSignedIn = user.lastSignedIn;
    updateSet.lastSignedIn = user.lastSignedIn;
  }

  if (user.openId === ENV.ownerOpenId) {
    values.role = "admin";
    updateSet.role = "admin";
  }

  if (!values.lastSignedIn) values.lastSignedIn = new Date();
  if (Object.keys(updateSet).length === 0) updateSet.lastSignedIn = new Date();

  // Set trial plan for new users (only on INSERT, not on duplicate update)
  const trialNow = new Date();
  const trialEnd = new Date(trialNow.getTime() + 7 * 24 * 60 * 60 * 1000);
  if (values.plan === undefined) values.plan = "trial";
  if (values.trialStartedAt === undefined) values.trialStartedAt = trialNow;
  if (values.trialEndsAt === undefined) values.trialEndsAt = trialEnd;

  await db.insert(users).values(values).onDuplicateKeyUpdate({ set: updateSet });
}

export async function getUserByOpenId(openId: string): Promise<User | undefined> {
  const db = await getDb();
  if (!db) return undefined;
  const result = await db.select().from(users).where(eq(users.openId, openId)).limit(1);
  return result[0];
}

export async function getUserById(id: number): Promise<User | undefined> {
  const db = await getDb();
  if (!db) return undefined;
  const result = await db.select().from(users).where(eq(users.id, id)).limit(1);
  return result[0];
}

export async function updateUser(id: number, data: Partial<User>): Promise<void> {
  const db = await getDb();
  if (!db) return;
  await db.update(users).set(data).where(eq(users.id, id));
}

// ─── Streak & Login ───────────────────────────────────────────────────────────
export async function updateLoginStreak(userId: number): Promise<{ streak: number; xpBonus: number }> {
  const db = await getDb();
  if (!db) return { streak: 0, xpBonus: 0 };

  const user = await getUserById(userId);
  if (!user) return { streak: 0, xpBonus: 0 };

  const today = new Date().toISOString().split("T")[0]!;
  if (user.lastLoginDate === today) return { streak: user.streak, xpBonus: 0 };

  const yesterday = new Date(Date.now() - 86400000).toISOString().split("T")[0]!;
  const newStreak = user.lastLoginDate === yesterday ? user.streak + 1 : 1;
  const xpBonus = newStreak >= 7 ? 50 : newStreak >= 3 ? 20 : 10;

  await db.update(users).set({ streak: newStreak, lastLoginDate: today }).where(eq(users.id, userId));
  await addXp(userId, xpBonus, `Daily login streak (day ${newStreak})`);

  return { streak: newStreak, xpBonus };
}

// ─── Streak por Prática (5 questões/dia) ─────────────────────────────────────
export async function updatePracticeStreak(userId: number): Promise<{
  streak: number; xpBonus: number; freezeConsumed: boolean; freezeGranted: boolean;
}> {
  const db = await getDb();
  if (!db) return { streak: 0, xpBonus: 0, freezeConsumed: false, freezeGranted: false };

  const user = await getUserById(userId);
  if (!user) return { streak: 0, xpBonus: 0, freezeConsumed: false, freezeGranted: false };

  const today = new Date().toISOString().split("T")[0]!;
  // Já atingiu a meta hoje
  if (user.lastPracticeDate === today) return { streak: user.streak, xpBonus: 0, freezeConsumed: false, freezeGranted: false };

  const yesterday = new Date(Date.now() - 86400000).toISOString().split("T")[0]!;
  const twoDaysAgo = new Date(Date.now() - 2 * 86400000).toISOString().split("T")[0]!;

  let newStreak = user.streak;
  let freezeConsumed = false;

  if (user.lastPracticeDate === yesterday) {
    // Dia consecutivo normal
    newStreak = user.streak + 1;
  } else if (user.lastPracticeDate === twoDaysAgo && user.streakFreezes > 0) {
    // Perdeu 1 dia mas tem freeze — consome e mantém streak
    newStreak = user.streak + 1;
    freezeConsumed = true;
    await db.update(users).set({ streakFreezes: Math.max(0, user.streakFreezes - 1) }).where(eq(users.id, userId));
  } else if (!user.lastPracticeDate) {
    // Primeiro dia de prática
    newStreak = 1;
  } else {
    // Streak quebrou
    newStreak = 1;
  }

  const xpBonus = newStreak >= 30 ? 100 : newStreak >= 14 ? 75 : newStreak >= 7 ? 50 : newStreak >= 3 ? 20 : 10;

  // Concede 1 freeze a cada múltiplo de 7 dias (máx 2)
  let freezeGranted = false;
  if (newStreak % 7 === 0 && newStreak > 0 && user.streakFreezes < 2) {
    const newFreezes = Math.min(2, user.streakFreezes + 1);
    await db.update(users).set({ streak: newStreak, lastPracticeDate: today, streakFreezes: newFreezes }).where(eq(users.id, userId));
    freezeGranted = true;
  } else {
    await db.update(users).set({ streak: newStreak, lastPracticeDate: today }).where(eq(users.id, userId));
  }

  await addXp(userId, xpBonus, `Streak de prática (dia ${newStreak})`);

  return { streak: newStreak, xpBonus, freezeConsumed, freezeGranted };
}

// ─── Recuperação de Streak (1x/mês, custa 500 coins) ─────────────────────────
export async function recoverStreak(userId: number): Promise<{ success: boolean; error?: string }> {
  const db = await getDb();
  if (!db) return { success: false, error: "DB unavailable" };

  const user = await getUserById(userId);
  if (!user) return { success: false, error: "User not found" };

  const today = new Date().toISOString().split("T")[0]!;
  const currentMonth = today.slice(0, 7); // YYYY-MM

  if (user.streakRecoveryUsedAt === currentMonth) {
    return { success: false, error: "Recuperação já usada este mês" };
  }
  if (user.coins < 500) {
    return { success: false, error: "Coins insuficientes (500 necessários)" };
  }

  // Verifica se a quebra foi recente (últimas 48h)
  const yesterday = new Date(Date.now() - 86400000).toISOString().split("T")[0]!;
  const twoDaysAgo = new Date(Date.now() - 2 * 86400000).toISOString().split("T")[0]!;
  const lastPractice = user.lastPracticeDate ?? "";
  if (lastPractice >= yesterday || lastPractice === twoDaysAgo) {
    // ainda dentro da janela de 48h
  } else {
    return { success: false, error: "Janela de 48h para recuperação expirou" };
  }

  await spendCoins(userId, 500, "Recuperação de streak");
  await db.update(users).set({
    lastPracticeDate: yesterday,
    streakRecoveryUsedAt: currentMonth,
  }).where(eq(users.id, userId));

  return { success: true };
}

// ─── XP & Levels ─────────────────────────────────────────────────────────────
export const LEVELS = [
  { level: 1, name: "Resident", nameEn: "Resident", xpRequired: 0 },
  { level: 2, name: "Estagiário", nameEn: "Intern", xpRequired: 100 },
  { level: 3, name: "Clínico Geral", nameEn: "General Practitioner", xpRequired: 300 },
  { level: 4, name: "Especialista", nameEn: "Specialist", xpRequired: 700 },
  { level: 5, name: "Consultor", nameEn: "Consultant", xpRequired: 1500 },
  { level: 6, name: "Pesquisador", nameEn: "Researcher", xpRequired: 3000 },
  { level: 7, name: "Professor", nameEn: "Professor", xpRequired: 5500 },
  { level: 8, name: "Mestre", nameEn: "Master", xpRequired: 9000 },
  { level: 9, name: "Lenda", nameEn: "Legend", xpRequired: 15000 },
];

export function getLevelForXp(xp: number) {
  let current = LEVELS[0]!;
  for (const lvl of LEVELS) {
    if (xp >= lvl.xpRequired) current = lvl;
    else break;
  }
  return current;
}

export function getNextLevel(currentLevel: number) {
  return LEVELS.find((l) => l.level === currentLevel + 1) ?? null;
}

export async function addXp(
  userId: number,
  amount: number,
  reason: string,
  examId?: number
): Promise<{ newXp: number; newLevel: number; leveledUp: boolean; coinsEarned: number }> {
  const db = await getDb();
  if (!db) return { newXp: 0, newLevel: 1, leveledUp: false, coinsEarned: 0 };

  const user = await getUserById(userId);
  if (!user) return { newXp: 0, newLevel: 1, leveledUp: false, coinsEarned: 0 };

  const newXp = user.xp + amount;
  const oldLevel = user.level;
  const newLevelObj = getLevelForXp(newXp);
  const newLevel = newLevelObj.level;

  // VetCoins: 10 coins a cada 100 XP ganhos
  const coinsEarned = Math.floor(amount / 10);

  await db.update(users).set({
    xp: newXp,
    level: newLevel,
    coins: sql`${users.coins} + ${coinsEarned}`,
  }).where(eq(users.id, userId));
  await db.insert(xpEvents).values({ userId, amount, reason, examId: examId ?? null });

  if (coinsEarned > 0) {
    await db.insert(coinEvents).values({ userId, amount: coinsEarned, reason: `XP → Coins: ${reason}` });
  }

  // Update weekly/monthly XP
  const now = new Date();
  const weekKey = getWeekKey(now);
  const monthKey = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}`;

  await db
    .insert(weeklyXp)
    .values({ userId, weekKey, xp: amount })
    .onDuplicateKeyUpdate({ set: { xp: sql`${weeklyXp.xp} + ${amount}` } });

  await db
    .insert(monthlyXp)
    .values({ userId, monthKey, xp: amount })
    .onDuplicateKeyUpdate({ set: { xp: sql`${monthlyXp.xp} + ${amount}` } });

  return { newXp, newLevel, leveledUp: newLevel > oldLevel, coinsEarned };
}

// ─── VetCoins ─────────────────────────────────────────────────────────────────
export async function addCoins(userId: number, amount: number, reason: string): Promise<void> {
  const db = await getDb();
  if (!db) return;
  await db.update(users).set({ coins: sql`${users.coins} + ${amount}` }).where(eq(users.id, userId));
  await db.insert(coinEvents).values({ userId, amount, reason });
}

export async function spendCoins(userId: number, amount: number, reason: string): Promise<{ success: boolean }> {
  const db = await getDb();
  if (!db) return { success: false };
  const user = await getUserById(userId);
  if (!user || user.coins < amount) return { success: false };
  await db.update(users).set({ coins: sql`${users.coins} - ${amount}` }).where(eq(users.id, userId));
  await db.insert(coinEvents).values({ userId, amount: -amount, reason });
  return { success: true };
}

function getWeekKey(date: Date): string {
  const d = new Date(date);
  d.setHours(0, 0, 0, 0);
  d.setDate(d.getDate() + 3 - ((d.getDay() + 6) % 7));
  const week1 = new Date(d.getFullYear(), 0, 4);
  const weekNum = 1 + Math.round(((d.getTime() - week1.getTime()) / 86400000 - 3 + ((week1.getDay() + 6) % 7)) / 7);
  return `${d.getFullYear()}-${String(weekNum).padStart(2, "0")}`;
}

// ─── Badges ───────────────────────────────────────────────────────────────────
export async function getAllBadges(): Promise<Badge[]> {
  const db = await getDb();
  if (!db) return [];
  return db.select().from(badges).orderBy(asc(badges.id));
}

export async function getUserBadgeIds(userId: number): Promise<number[]> {
  const db = await getDb();
  if (!db) return [];
  const rows = await db.select({ badgeId: userBadges.badgeId }).from(userBadges).where(eq(userBadges.userId, userId));
  return rows.map((r) => r.badgeId);
}

export async function awardBadge(userId: number, badgeId: number): Promise<boolean> {
  const db = await getDb();
  if (!db) return false;
  const existing = await db
    .select()
    .from(userBadges)
    .where(and(eq(userBadges.userId, userId), eq(userBadges.badgeId, badgeId)))
    .limit(1);
  if (existing.length > 0) return false;
  await db.insert(userBadges).values({ userId, badgeId });
  return true;
}

export async function checkAndAwardBadges(userId: number): Promise<Badge[]> {
  const db = await getDb();
  if (!db) return [];

  const user = await getUserById(userId);
  if (!user) return [];

  const allBadges = await getAllBadges();
  const earnedIds = await getUserBadgeIds(userId);
  const newBadges: Badge[] = [];

  const examCount = await db
    .select({ count: sql<number>`count(*)` })
    .from(exams)
    .where(and(eq(exams.userId, userId), eq(exams.status, "completed")));
  const totalExams = Number(examCount[0]?.count ?? 0);

  const practiceCount = await db
    .select({ count: sql<number>`count(*)` })
    .from(practiceSessions)
    .where(eq(practiceSessions.userId, userId));
  const totalPractice = Number(practiceCount[0]?.count ?? 0);

  for (const badge of allBadges) {
    if (earnedIds.includes(badge.id)) continue;
    const cond = badge.condition as { type: string; value: number; minQuestions?: number; key?: string } | null;
    if (!cond) continue;

    let earned = false;
    if (cond.type === "xp" && user.xp >= cond.value) earned = true;
    if (cond.type === "level" && user.level >= cond.value) earned = true;
    if (cond.type === "streak" && user.streak >= cond.value) earned = true;
    if (cond.type === "exams" && totalExams >= cond.value) earned = true;
    if (cond.type === "practice_count" && totalPractice >= cond.value) earned = true;
    if (cond.type === "accuracy" && user.totalQuestions > 0) {
      const acc = (user.totalCorrect / user.totalQuestions) * 100;
      if (acc >= cond.value) earned = true;
    }
    if (cond.type === "accuracy_min_questions" && user.totalQuestions >= (cond.minQuestions ?? 0)) {
      const acc = (user.totalCorrect / user.totalQuestions) * 100;
      if (acc >= cond.value) earned = true;
    }
    // Easter eggs são concedidos manualmente via awardEasterEgg()

    if (earned) {
      await awardBadge(userId, badge.id);
      newBadges.push(badge);
    }
  }

  return newBadges;
}

// ─── Disciplines & Subjects ───────────────────────────────────────────────────
export async function getDisciplines(): Promise<Discipline[]> {
  const db = await getDb();
  if (!db) return [];
  return db.select().from(disciplines).where(eq(disciplines.active, true)).orderBy(asc(disciplines.namePt));
}

export async function getAllDisciplines(): Promise<Discipline[]> {
  const db = await getDb();
  if (!db) return [];
  return db.select().from(disciplines).orderBy(asc(disciplines.namePt));
}

export async function getSubjectsByDiscipline(disciplineId: number): Promise<Subject[]> {
  const db = await getDb();
  if (!db) return [];
  return db
    .select()
    .from(subjects)
    .where(and(eq(subjects.disciplineId, disciplineId), eq(subjects.active, true)))
    .orderBy(asc(subjects.namePt));
}

export async function getAllSubjects(): Promise<Subject[]> {
  const db = await getDb();
  if (!db) return [];
  return db.select().from(subjects).orderBy(asc(subjects.namePt));
}

export async function getTopicsBySubject(subjectId: number): Promise<Topic[]> {
  const db = await getDb();
  if (!db) return [];
  return db
    .select()
    .from(topics)
    .where(and(eq(topics.subjectId, subjectId), eq(topics.active, true)))
    .orderBy(asc(topics.namePt));
}

export async function getAllTopics(): Promise<Topic[]> {
  const db = await getDb();
  if (!db) return [];
  return db.select().from(topics).orderBy(asc(topics.namePt));
}

// ─── Shop ─────────────────────────────────────────────────────────────────────
export async function getShopItems(productType?: "gamification" | "course" | "content") {
  const db = await getDb();
  if (!db) return [];
  const cond = productType
    ? and(eq(shopItems.active, true), eq(shopItems.productType, productType))
    : eq(shopItems.active, true);
  return db.select().from(shopItems).where(cond).orderBy(asc(shopItems.sortOrder));
}

export async function buyShopItem(userId: number, itemId: number): Promise<{ success: boolean; error?: string }> {
  const db = await getDb();
  if (!db) return { success: false, error: "DB unavailable" };

  const [item] = await db.select().from(shopItems).where(and(eq(shopItems.id, itemId), eq(shopItems.active, true))).limit(1);
  if (!item) return { success: false, error: "Item não encontrado" };
  if (item.productType !== "gamification") return { success: false, error: "Item requer pagamento" };

  const result = await spendCoins(userId, item.cost, `Loja: ${item.namePt}`);
  if (!result.success) return { success: false, error: "VetCoins insuficientes" };

  await db.insert(purchases).values({ userId, itemId, productType: "gamification", coinsSpent: item.cost });

  // Aplica efeito do item
  if (item.itemType === "freeze") {
    const user = await getUserById(userId);
    if (user) {
      const newFreezes = Math.min(2, user.streakFreezes + 1);
      await db.update(users).set({ streakFreezes: newFreezes }).where(eq(users.id, userId));
    }
  }

  return { success: true };
}

export async function seedShopItems(): Promise<void> {
  const db = await getDb();
  if (!db) return;
  const existing = await db.select({ count: sql<number>`count(*)` }).from(shopItems);
  if (Number(existing[0]?.count ?? 0) > 0) return;

  const items = [
    { slug: "freeze-extra", namePt: "Congelamento Extra", nameEn: "Extra Freeze", descriptionPt: "Protege sua ofensiva por 1 dia", cost: 200, category: "utility" as const, itemType: "freeze", icon: "🧊", productType: "gamification" as const, sortOrder: 1 },
    { slug: "streak-recovery", namePt: "Recuperação de Emergência", nameEn: "Emergency Recovery", descriptionPt: "Restaura streak quebrado (48h, 1x/mês)", cost: 500, category: "utility" as const, itemType: "recovery", icon: "🚑", productType: "gamification" as const, sortOrder: 2 },
    { slug: "chest-rare", namePt: "Baú Raro", nameEn: "Rare Chest", descriptionPt: "Chance de badge épico ou item especial", cost: 300, category: "luck" as const, itemType: "chest_rare", icon: "📦", productType: "gamification" as const, sortOrder: 3 },
  ];

  for (const item of items) {
    await db.insert(shopItems).values(item).onDuplicateKeyUpdate({ set: { namePt: item.namePt } });
  }
}

// ─── Baú Diário (após 5 questões no dia) ─────────────────────────────────────
const CHEST_REWARDS = [
  { type: "coins", amount: 10, weight: 40 },
  { type: "coins", amount: 25, weight: 25 },
  { type: "coins", amount: 50, weight: 15 },
  { type: "freeze", amount: 1, weight: 10 },
  { type: "coins", amount: 100, weight: 7 },
  { type: "xp_bonus", amount: 50, weight: 3 },
];

function weightedRandom<T extends { weight: number }>(items: T[]): T {
  const total = items.reduce((s, i) => s + i.weight, 0);
  let r = Math.random() * total;
  for (const item of items) {
    r -= item.weight;
    if (r <= 0) return item;
  }
  return items[items.length - 1]!;
}

export async function claimDailyChest(userId: number): Promise<{
  success: boolean; rewardType?: string; rewardAmount?: number; error?: string;
}> {
  const db = await getDb();
  if (!db) return { success: false, error: "DB unavailable" };

  const today = new Date().toISOString().split("T")[0]!;
  const alreadyClaimed = await db.select().from(dailyChests).where(
    and(eq(dailyChests.userId, userId), eq(dailyChests.claimedDate, today))
  ).limit(1);
  if (alreadyClaimed.length > 0) return { success: false, error: "Baú já coletado hoje" };

  // Verifica se tem 5+ questões hoje
  const startOfDay = `${today} 00:00:00`;
  const practiceToday = await db.select({ count: sql<number>`count(*)` }).from(practiceSessions)
    .where(and(eq(practiceSessions.userId, userId), sql`created_at >= ${startOfDay}`));
  if (Number(practiceToday[0]?.count ?? 0) < 5) return { success: false, error: "Responda 5 questões hoje primeiro" };

  const reward = weightedRandom(CHEST_REWARDS);
  await db.insert(dailyChests).values({ userId, claimedDate: today, rewardType: reward.type, rewardAmount: reward.amount });

  if (reward.type === "coins") await addCoins(userId, reward.amount, "Baú diário");
  if (reward.type === "freeze") {
    const user = await getUserById(userId);
    if (user) await db.update(users).set({ streakFreezes: Math.min(2, user.streakFreezes + 1) }).where(eq(users.id, userId));
  }
  if (reward.type === "xp_bonus") await addXp(userId, reward.amount, "Baú diário (bônus XP)");

  return { success: true, rewardType: reward.type, rewardAmount: reward.amount };
}

// ─── Roleta Diária Gratuita ───────────────────────────────────────────────────
const ROULETTE_REWARDS = [
  { type: "coins", amount: 5, weight: 45 },
  { type: "coins", amount: 15, weight: 30 },
  { type: "coins", amount: 30, weight: 15 },
  { type: "coins", amount: 75, weight: 7 },
  { type: "freeze", amount: 1, weight: 3 },
];

export async function spinDailyRoulette(userId: number): Promise<{
  success: boolean; rewardType?: string; rewardAmount?: number; error?: string;
}> {
  const db = await getDb();
  if (!db) return { success: false, error: "DB unavailable" };

  const today = new Date().toISOString().split("T")[0]!;
  const alreadySpun = await db.select().from(dailyRoulette).where(
    and(eq(dailyRoulette.userId, userId), eq(dailyRoulette.spunDate, today))
  ).limit(1);
  if (alreadySpun.length > 0) return { success: false, error: "Roleta já girada hoje" };

  const reward = weightedRandom(ROULETTE_REWARDS);
  await db.insert(dailyRoulette).values({ userId, spunDate: today, rewardType: reward.type, rewardAmount: reward.amount });

  if (reward.type === "coins") await addCoins(userId, reward.amount, "Roleta diária");
  if (reward.type === "freeze") {
    const user = await getUserById(userId);
    if (user) await db.update(users).set({ streakFreezes: Math.min(2, user.streakFreezes + 1) }).where(eq(users.id, userId));
  }

  return { success: true, rewardType: reward.type, rewardAmount: reward.amount };
}

// ─── Questions ────────────────────────────────────────────────────────────────
export type QuestionFilters = {
  // Basic
  disciplineId?: number;
  subjectId?: number;
  topicId?: number;
  search?: string;
  isPremium?: boolean;
  // Classification
  questionType?: string;
  difficulty?: string;
  year?: number;
  yearFrom?: number;
  yearTo?: number;
  author?: string;
  banca?: string;
  instituicao?: string;
  cargo?: string;
  carreira?: string;
  areaFormacao?: string;
  escolaridade?: string;
  // Status
  status?: string;          // pending | approved | rejected
  isValidated?: boolean;
  isAnulada?: boolean;
  isDesatualizada?: boolean;
  includeAnuladas?: boolean;
  includeDesatualizadas?: boolean;
  // Content filters
  hasExplanation?: boolean; // has AI explanation
  // User activity filters (requires userId)
  myAnswers?: "answered" | "unanswered" | "correct" | "incorrect";
  userId?: number;
  // Sorting
  orderBy?: "newest" | "oldest" | "year_desc" | "year_asc";
  // Pagination
  page?: number;
  limit?: number;
  // Admin/teacher context: if true, don't auto-filter rejected questions
  includeRejected?: boolean;
  // Exclude discursive question type (used by questionMode="multiple_choice")
  excludeDiscursive?: boolean;
};

export async function getQuestions(filters: QuestionFilters): Promise<{ questions: Question[]; total: number }> {
  const db = await getDb();
  if (!db) return { questions: [], total: 0 };

  const conditions = [eq(questions.active, true)];

  // Rejected questions are never shown to end users unless admin/teacher explicitly requests
  if (!filters.includeRejected && !filters.status) {
    conditions.push(ne(questions.status, "rejected"));
  }

  // By default exclude anuladas/desatualizadas unless explicitly included
  if (!filters.includeAnuladas) conditions.push(eq(questions.isAnulada, false));
  if (!filters.includeDesatualizadas) conditions.push(eq(questions.isDesatualizada, false));

  if (filters.disciplineId) conditions.push(eq(questions.disciplineId, filters.disciplineId));
  if (filters.subjectId) conditions.push(eq(questions.subjectId, filters.subjectId));
  if (filters.topicId) conditions.push(eq(questions.topicId, filters.topicId));
  if (filters.difficulty) conditions.push(eq(questions.difficulty, filters.difficulty as any));
  if (filters.questionType) conditions.push(eq(questions.questionType, filters.questionType as any));
  if (filters.excludeDiscursive) conditions.push(ne(questions.questionType, "discursive"));
  if (filters.year) conditions.push(eq(questions.year, filters.year));
  if (filters.yearFrom) conditions.push(gte(questions.year, filters.yearFrom));
  if (filters.yearTo) conditions.push(lte(questions.year, filters.yearTo));
  if (filters.isPremium !== undefined) conditions.push(eq(questions.isPremium, filters.isPremium));
  if (filters.search) {
    conditions.push(or(
      like(questions.textPt, `%${filters.search}%`),
      like(questions.banca, `%${filters.search}%`),
      like(questions.instituicao, `%${filters.search}%`),
    )!);
  }
  if (filters.author) conditions.push(like(questions.author, `%${filters.author}%`));
  if (filters.banca) conditions.push(eq(questions.banca, filters.banca));
  if (filters.instituicao) conditions.push(like(questions.instituicao, `%${filters.instituicao}%`));
  if (filters.cargo) conditions.push(like(questions.cargo, `%${filters.cargo}%`));
  if (filters.carreira) conditions.push(eq(questions.carreira, filters.carreira));
  if (filters.areaFormacao) conditions.push(eq(questions.areaFormacao, filters.areaFormacao));
  if (filters.escolaridade) conditions.push(eq(questions.escolaridade, filters.escolaridade as any));
  if (filters.status) conditions.push(eq(questions.status, filters.status as any));
  if (filters.isValidated !== undefined) conditions.push(eq(questions.isValidated, filters.isValidated));
  if (filters.hasExplanation) conditions.push(isNotNull(questions.explanationPt));

  const page = filters.page ?? 1;
  const limit = filters.limit ?? 20;
  const offset = (page - 1) * limit;

  const orderCol = (() => {
    switch (filters.orderBy) {
      case "oldest":    return asc(questions.createdAt);
      case "year_desc": return desc(questions.year);
      case "year_asc":  return asc(questions.year);
      default:          return desc(questions.createdAt);
    }
  })();

  // "Minhas questões" — correlated EXISTS subquery (single SQL, no Node.js round-trip)
  if (filters.myAnswers && filters.userId) {
    const uid = filters.userId;
    const baseMatch = [
      eq(practiceSessions.userId, uid),
      eq(practiceSessions.questionId, questions.id),
    ];

    if (filters.myAnswers === "unanswered") {
      conditions.push(not(exists(
        db.select({ one: sql`1` }).from(practiceSessions).where(and(...baseMatch))
      )));
    } else {
      const isCorrectFilter =
        filters.myAnswers === "correct"   ? eq(practiceSessions.isCorrect, true)  :
        filters.myAnswers === "incorrect" ? eq(practiceSessions.isCorrect, false) :
        undefined;
      conditions.push(exists(
        db.select({ one: sql`1` }).from(practiceSessions)
          .where(and(...baseMatch, ...(isCorrectFilter ? [isCorrectFilter] : [])))
      ));
    }
  }

  const where = and(...conditions);
  const [rows, countRows] = await Promise.all([
    db.select().from(questions).where(where).orderBy(orderCol).limit(limit).offset(offset),
    db.select({ count: sql<number>`count(*)` }).from(questions).where(where),
  ]);

  return { questions: rows, total: Number(countRows[0]?.count ?? 0) };
}

// ─── Distinct filter values ───────────────────────────────────────────────────
export async function getDistinctBancas(): Promise<string[]> {
  const db = await getDb();
  if (!db) return [];
  const rows = await db.selectDistinct({ banca: questions.banca }).from(questions)
    .where(and(eq(questions.active, true), isNotNull(questions.banca)))
    .orderBy(asc(questions.banca));
  return rows.map(r => r.banca!).filter(Boolean);
}

export async function getDistinctInstituicoes(): Promise<string[]> {
  const db = await getDb();
  if (!db) return [];
  const rows = await db.selectDistinct({ instituicao: questions.instituicao }).from(questions)
    .where(and(eq(questions.active, true), isNotNull(questions.instituicao)))
    .orderBy(asc(questions.instituicao));
  return rows.map(r => r.instituicao!).filter(Boolean);
}

export async function getDistinctCargos(): Promise<string[]> {
  const db = await getDb();
  if (!db) return [];
  const rows = await db.selectDistinct({ cargo: questions.cargo }).from(questions)
    .where(and(eq(questions.active, true), isNotNull(questions.cargo)))
    .orderBy(asc(questions.cargo));
  return rows.map(r => r.cargo!).filter(Boolean);
}

export async function getDistinctCarreiras(): Promise<string[]> {
  const db = await getDb();
  if (!db) return [];
  const rows = await db.selectDistinct({ carreira: questions.carreira }).from(questions)
    .where(and(eq(questions.active, true), isNotNull(questions.carreira)))
    .orderBy(asc(questions.carreira));
  return rows.map(r => r.carreira!).filter(Boolean);
}

export async function getDistinctAreasFormacao(): Promise<string[]> {
  const db = await getDb();
  if (!db) return [];
  const rows = await db.selectDistinct({ areaFormacao: questions.areaFormacao }).from(questions)
    .where(and(eq(questions.active, true), isNotNull(questions.areaFormacao)))
    .orderBy(asc(questions.areaFormacao));
  return rows.map(r => r.areaFormacao!).filter(Boolean);
}

// ─── Saved Filters ────────────────────────────────────────────────────────────
export async function getSavedFilters(userId: number): Promise<SavedFilter[]> {
  const db = await getDb();
  if (!db) return [];
  return db.select().from(savedFilters)
    .where(eq(savedFilters.userId, userId))
    .orderBy(desc(savedFilters.createdAt));
}

export async function saveFilter(userId: number, name: string, filters: unknown): Promise<void> {
  const db = await getDb();
  if (!db) return;
  await db.insert(savedFilters).values({ userId, name, filters });
}

export async function deleteSavedFilter(id: number, userId: number): Promise<void> {
  const db = await getDb();
  if (!db) return;
  await db.delete(savedFilters)
    .where(and(eq(savedFilters.id, id), eq(savedFilters.userId, userId)));
}

export async function getQuestionById(id: number): Promise<Question | undefined> {
  const db = await getDb();
  if (!db) return undefined;
  const result = await db.select().from(questions).where(eq(questions.id, id)).limit(1);
  return result[0];
}

// ─── M3: Assertivas ───────────────────────────────────────────────────────────

export async function getAssertivas(questionId: number): Promise<QuestionAssertiva[]> {
  const db = await getDb();
  if (!db) return [];
  return db.select().from(questionAssertivas)
    .where(eq(questionAssertivas.questionId, questionId))
    .orderBy(asc(questionAssertivas.ordem));
}

export async function upsertAssertivas(
  questionId: number,
  items: Array<{ label: string; textPt: string; textEn?: string; correta: boolean }>
): Promise<void> {
  const db = await getDb();
  if (!db) return;
  // Delete existing and re-insert (simpler than diffing)
  await db.delete(questionAssertivas).where(eq(questionAssertivas.questionId, questionId));
  if (items.length === 0) return;
  await db.insert(questionAssertivas).values(
    items.map((item, i) => ({
      questionId,
      ordem: i + 1,
      label: item.label,
      textPt: item.textPt,
      textEn: item.textEn ?? null,
      correta: item.correta,
    }))
  );
}

// ─── M8: Colunas de Associação ────────────────────────────────────────────────

export type MatchingData = {
  esquerda: QuestionMatchingCol[];
  direita: QuestionMatchingCol[];
  pairs: QuestionMatchingPair[];
};

export async function getMatchingData(questionId: number): Promise<MatchingData> {
  const db = await getDb();
  if (!db) return { esquerda: [], direita: [], pairs: [] };
  const [cols, pairs] = await Promise.all([
    db.select().from(questionMatchingCols)
      .where(eq(questionMatchingCols.questionId, questionId))
      .orderBy(asc(questionMatchingCols.coluna), asc(questionMatchingCols.ordem)),
    db.select().from(questionMatchingPairs)
      .where(eq(questionMatchingPairs.questionId, questionId)),
  ]);
  return {
    esquerda: cols.filter(c => c.coluna === "esquerda"),
    direita:  cols.filter(c => c.coluna === "direita"),
    pairs,
  };
}

export async function upsertMatchingData(
  questionId: number,
  esquerda: Array<{ label: string; textPt: string; textEn?: string }>,
  direita:  Array<{ label: string; textPt: string; textEn?: string }>,
  pairs: Array<{ esquerdaOrdem: number; direitaOrdem: number }>
): Promise<void> {
  const db = await getDb();
  if (!db) return;
  await db.delete(questionMatchingPairs).where(eq(questionMatchingPairs.questionId, questionId));
  await db.delete(questionMatchingCols).where(eq(questionMatchingCols.questionId, questionId));

  if (!esquerda.length || !direita.length) return;

  // Insert cols and get their IDs
  const esquerdaRows = esquerda.map((item, i) => ({
    questionId, coluna: "esquerda" as const, ordem: i + 1, label: item.label,
    textPt: item.textPt, textEn: item.textEn ?? null,
  }));
  const direitaRows = direita.map((item, i) => ({
    questionId, coluna: "direita" as const, ordem: i + 1, label: item.label,
    textPt: item.textPt, textEn: item.textEn ?? null,
  }));

  await db.insert(questionMatchingCols).values([...esquerdaRows, ...direitaRows]);

  // Reload to get IDs
  const allCols = await db.select().from(questionMatchingCols)
    .where(eq(questionMatchingCols.questionId, questionId));
  const esquerdaCols = allCols.filter(c => c.coluna === "esquerda");
  const direitaCols  = allCols.filter(c => c.coluna === "direita");

  const pairRows = pairs.map(p => ({
    questionId,
    esquerdaId: esquerdaCols.find(c => c.ordem === p.esquerdaOrdem)?.id ?? 0,
    direitaId:  direitaCols.find(c => c.ordem === p.direitaOrdem)?.id ?? 0,
  })).filter(p => p.esquerdaId && p.direitaId);

  if (pairRows.length) {
    await db.insert(questionMatchingPairs).values(pairRows);
  }
}

// ─── M10: Grupos de Alternativas Constantes ───────────────────────────────────

export async function getQuestionGroup(grupoId: string): Promise<QuestionGroup | undefined> {
  const db = await getDb();
  if (!db) return undefined;
  const result = await db.select().from(questionGroups)
    .where(eq(questionGroups.grupoId, grupoId)).limit(1);
  return result[0];
}

export async function upsertQuestionGroup(
  grupoId: string,
  data: { titulo?: string; textBasePt: string; textBaseEn?: string; alternativas: Record<string, string> }
): Promise<void> {
  const db = await getDb();
  if (!db) return;
  await db.insert(questionGroups)
    .values({ grupoId, ...data })
    .onDuplicateKeyUpdate({ set: { titulo: data.titulo, textBasePt: data.textBasePt, textBaseEn: data.textBaseEn ?? null, alternativas: data.alternativas } });
}

export async function updateGroupQuestionCount(grupoId: string): Promise<void> {
  const db = await getDb();
  if (!db) return;
  const [row] = await db.select({ count: sql<number>`count(*)` })
    .from(questions).where(eq(questions.grupoId, grupoId));
  if (row) {
    await db.update(questionGroups)
      .set({ totalQuestoes: Number(row.count) })
      .where(eq(questionGroups.grupoId, grupoId));
  }
}

export async function getRandomQuestions(filters: {
  disciplineId?: number;
  disciplineIds?: number[];
  subjectId?: number;
  author?: string;
  year?: number;
  difficulty?: string;
  count: number;
  excludeIds?: number[];
}): Promise<Question[]> {
  const db = await getDb();
  if (!db) return [];

  const conditions = [eq(questions.active, true)];

  // Single discipline (legacy) or multi-discipline
  if (filters.disciplineIds && filters.disciplineIds.length > 0) {
    conditions.push(inArray(questions.disciplineId, filters.disciplineIds));
  } else if (filters.disciplineId) {
    conditions.push(eq(questions.disciplineId, filters.disciplineId));
  }

  if (filters.subjectId) conditions.push(eq(questions.subjectId, filters.subjectId));
  if (filters.author) conditions.push(eq(questions.author, filters.author));
  if (filters.year) conditions.push(eq(questions.year, filters.year));
  if (filters.difficulty && filters.difficulty !== "mixed")
    conditions.push(eq(questions.difficulty, filters.difficulty as "easy" | "medium" | "hard"));

  const rows = await db
    .select()
    .from(questions)
    .where(and(...conditions))
    .orderBy(sql`RAND()`)
    .limit(filters.count);

  return rows;
}

export async function countRandomQuestions(filters: {
  disciplineIds?: number[];
  subjectId?: number;
  author?: string;
  year?: number;
  difficulty?: string;
}): Promise<number> {
  const db = await getDb();
  if (!db) return 0;
  const conditions = [eq(questions.active, true)];
  if (filters.disciplineIds && filters.disciplineIds.length > 0)
    conditions.push(inArray(questions.disciplineId, filters.disciplineIds));
  if (filters.subjectId) conditions.push(eq(questions.subjectId, filters.subjectId));
  if (filters.author) conditions.push(eq(questions.author, filters.author));
  if (filters.year) conditions.push(eq(questions.year, filters.year));
  if (filters.difficulty && filters.difficulty !== "mixed")
    conditions.push(eq(questions.difficulty, filters.difficulty as "easy" | "medium" | "hard"));
  const [row] = await db
    .select({ count: sql<number>`count(*)` })
    .from(questions)
    .where(and(...conditions));
  return Number(row?.count ?? 0);
}

export async function getDistinctAuthors(): Promise<string[]> {
  const db = await getDb();
  if (!db) return [];
  const rows = await db
    .selectDistinct({ author: questions.author })
    .from(questions)
    .where(and(eq(questions.active, true), isNotNull(questions.author)))
    .orderBy(questions.author);
  return rows.map((r) => r.author!).filter(Boolean);
}

export async function getDistinctYears(): Promise<number[]> {
  const db = await getDb();
  if (!db) return [];
  const rows = await db
    .selectDistinct({ year: questions.year })
    .from(questions)
    .where(and(eq(questions.active, true), isNotNull(questions.year)))
    .orderBy(desc(questions.year));
  return rows.map((r) => r.year!).filter(Boolean);
}

// Assertion I/II fields only belong to assertion_reason questions. AI generators/importers
// sometimes include them for other types (e.g. clinical_case) by mistake, which makes the
// renderer misdetect the question as assertion_reason (see QuestionRenderer's effectiveQType).
function stripAssertionFieldsIfNotApplicable<T extends { questionType?: string; assertion1?: string | null; assertion2?: string | null; a1Verdadeira?: boolean | null; a2Verdadeira?: boolean | null; relacaoCausal?: boolean | null }>(data: T): T {
  if (data.questionType && data.questionType !== "assertion_reason") {
    return { ...data, assertion1: null, assertion2: null, a1Verdadeira: null, a2Verdadeira: null, relacaoCausal: null };
  }
  return data;
}

export async function createQuestion(data: InsertQuestion): Promise<number> {
  const db = await getDb();
  if (!db) throw new Error("DB not available");
  const result = await db.insert(questions).values(stripAssertionFieldsIfNotApplicable(data));
  return (result[0] as { insertId: number }).insertId;
}

export async function updateQuestion(id: number, data: Partial<InsertQuestion>): Promise<void> {
  const db = await getDb();
  if (!db) return;
  await db.update(questions).set(stripAssertionFieldsIfNotApplicable(data)).where(eq(questions.id, id));
}

export async function deleteQuestion(id: number): Promise<void> {
  const db = await getDb();
  if (!db) return;
  await db.update(questions).set({ active: false }).where(eq(questions.id, id));
}

// ─── Exams ────────────────────────────────────────────────────────────────────
export async function createExam(data: {
  userId: number;
  title?: string;
  disciplineId?: number;
  difficulty?: string;
  questionCount: number;
  timeLimitSeconds?: number;
  questionIds: number[];
}): Promise<number> {
  const db = await getDb();
  if (!db) throw new Error("DB not available");
  const result = await db.insert(exams).values({
    userId: data.userId,
    title: data.title ?? null,
    disciplineId: data.disciplineId ?? null,
    difficulty: (data.difficulty as "easy" | "medium" | "hard" | "mixed") ?? "mixed",
    questionCount: data.questionCount,
    timeLimitSeconds: data.timeLimitSeconds ?? null,
    totalQuestions: data.questionIds.length,
    questionIds: data.questionIds,
    status: "in_progress",
  });
  return (result[0] as { insertId: number }).insertId;
}

export async function getExamById(id: number): Promise<Exam | undefined> {
  const db = await getDb();
  if (!db) return undefined;
  const result = await db.select().from(exams).where(eq(exams.id, id)).limit(1);
  return result[0];
}

export async function submitExamAnswer(data: {
  examId: number;
  questionId: number;
  selectedOption: string;
  isCorrect: boolean;
  timeSpentSeconds?: number;
}): Promise<void> {
  const db = await getDb();
  if (!db) return;
  await db
    .insert(examAnswers)
    .values({
      examId: data.examId,
      questionId: data.questionId,
      selectedOption: data.selectedOption,
      isCorrect: data.isCorrect,
      timeSpentSeconds: data.timeSpentSeconds ?? null,
    })
    .onDuplicateKeyUpdate({
      set: {
        selectedOption: data.selectedOption,
        isCorrect: data.isCorrect,
        timeSpentSeconds: data.timeSpentSeconds ?? null,
      },
    });
}

export async function completeExam(
  examId: number,
  userId: number,
  timeSpentSeconds: number,
  userRole?: string
): Promise<{ xpEarned: number; accuracy: number; correctAnswers: number; newBadges: Badge[] }> {
  const db = await getDb();
  if (!db) return { xpEarned: 0, accuracy: 0, correctAnswers: 0, newBadges: [] };

  const exam = await getExamById(examId);
  if (!exam) throw new Error("Exam not found");

  const answers = await db.select().from(examAnswers).where(eq(examAnswers.examId, examId));
  const correctAnswers = answers.filter((a) => a.isCorrect).length;
  const accuracy = exam.totalQuestions > 0 ? (correctAnswers / exam.totalQuestions) * 100 : 0;

  // XP calculation
  const baseXp = correctAnswers * 10;
  const difficultyBonus = exam.difficulty === "hard" ? 1.5 : exam.difficulty === "medium" ? 1.2 : 1.0;
  const xpEarned = Math.round(baseXp * difficultyBonus);

  await db.update(exams).set({
    status: "completed",
    correctAnswers,
    accuracy,
    xpEarned,
    timeSpentSeconds,
    completedAt: new Date(),
  }).where(eq(exams.id, examId));

  // Update user stats
  await db.update(users).set({
    totalExams: sql`${users.totalExams} + 1`,
    totalQuestions: sql`${users.totalQuestions} + ${exam.totalQuestions}`,
    totalCorrect: sql`${users.totalCorrect} + ${correctAnswers}`,
  }).where(eq(users.id, userId));

  // Award XP only for regular students
  if (!userRole || userRole === "user") {
    await addXp(userId, xpEarned, `Exam completed (${correctAnswers}/${exam.totalQuestions} correct)`, examId);
  }

  // Check badges
  const newBadges = await checkAndAwardBadges(userId);

  return { xpEarned, accuracy, correctAnswers, newBadges };
}

export async function getUserExams(userId: number, limit = 10): Promise<Exam[]> {
  const db = await getDb();
  if (!db) return [];
  return db
    .select()
    .from(exams)
    .where(and(eq(exams.userId, userId), eq(exams.status, "completed")))
    .orderBy(desc(exams.completedAt))
    .limit(limit);
}

export async function getExamAnswers(examId: number) {
  const db = await getDb();
  if (!db) return [];
  return db.select().from(examAnswers).where(eq(examAnswers.examId, examId));
}

// ─── Leaderboard ──────────────────────────────────────────────────────────────
export async function getLeaderboard(
  type: "weekly" | "monthly" | "alltime",
  limit = 50
): Promise<Array<{ userId: number; name: string | null; xp: number; level: number; rank: number }>> {
  const db = await getDb();
  if (!db) return [];

  if (type === "alltime") {
    const rows = await db
      .select({ userId: users.id, name: users.name, xp: users.xp, level: users.level })
      .from(users)
      .orderBy(desc(users.xp))
      .limit(limit);
    return rows.map((r, i) => ({ ...r, rank: i + 1 }));
  }

  if (type === "weekly") {
    const weekKey = getWeekKey(new Date());
    // GROUP BY userId to handle duplicate rows (missing UNIQUE constraint)
    const [rows] = await db.execute(`
      SELECT w.userId, u.name, SUM(w.xp) as xp, u.level
      FROM weekly_xp w
      INNER JOIN users u ON w.userId = u.id
      WHERE w.weekKey = '${weekKey}'
      GROUP BY w.userId, u.name, u.level
      ORDER BY xp DESC
      LIMIT ${limit}
    `) as any;
    return (rows as any[]).map((r, i) => ({ userId: r.userId, name: r.name, xp: Number(r.xp), level: r.level, rank: i + 1 }));
  }

  // Monthly
  const now = new Date();
  const monthKey = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}`;
  const [rows] = await db.execute(`
    SELECT m.userId, u.name, SUM(m.xp) as xp, u.level
    FROM monthly_xp m
    INNER JOIN users u ON m.userId = u.id
    WHERE m.monthKey = '${monthKey}'
    GROUP BY m.userId, u.name, u.level
    ORDER BY xp DESC
    LIMIT ${limit}
  `) as any;
  return (rows as any[]).map((r, i) => ({ userId: r.userId, name: r.name, xp: Number(r.xp), level: r.level, rank: i + 1 }));
}

// ─── Admin helpers ────────────────────────────────────────────────────────────
export async function createDiscipline(data: { slug: string; namePt: string; nameEn: string; icon?: string; color?: string }): Promise<number> {
  const db = await getDb();
  if (!db) throw new Error("DB not available");
  const result = await db.insert(disciplines).values(data);
  return (result[0] as { insertId: number }).insertId;
}

export async function updateDiscipline(id: number, data: Partial<Discipline>): Promise<void> {
  const db = await getDb();
  if (!db) return;
  await db.update(disciplines).set(data).where(eq(disciplines.id, id));
}

export async function createSubject(data: { disciplineId: number; slug: string; namePt: string; nameEn: string }): Promise<number> {
  const db = await getDb();
  if (!db) throw new Error("DB not available");
  const result = await db.insert(subjects).values(data);
  return (result[0] as { insertId: number }).insertId;
}

export async function updateSubject(id: number, data: Partial<Subject>): Promise<void> {
  const db = await getDb();
  if (!db) return;
  await db.update(subjects).set(data).where(eq(subjects.id, id));
}

export async function getAdminStats() {
  const db = await getDb();
  if (!db) return { totalUsers: 0, totalQuestions: 0, totalExams: 0, premiumUsers: 0, totalDisciplines: 0, totalSubjects: 0 };

  const [userCount, questionCount, examCount, premiumCount, disciplineCount, subjectCount] = await Promise.all([
    db.select({ count: sql<number>`count(*)` }).from(users),
    db.select({ count: sql<number>`count(*)` }).from(questions).where(eq(questions.active, true)),
    db.select({ count: sql<number>`count(*)` }).from(exams).where(eq(exams.status, "completed")),
    db.select({ count: sql<number>`count(*)` }).from(users).where(or(eq(users.plan, "premium"), eq(users.plan, "trial"))),
    db.select({ count: sql<number>`count(*)` }).from(disciplines),
    db.select({ count: sql<number>`count(*)` }).from(subjects),
  ]);

  return {
    totalUsers: Number(userCount[0]?.count ?? 0),
    totalQuestions: Number(questionCount[0]?.count ?? 0),
    totalExams: Number(examCount[0]?.count ?? 0),
    premiumUsers: Number(premiumCount[0]?.count ?? 0),
    totalDisciplines: Number(disciplineCount[0]?.count ?? 0),
    totalSubjects: Number(subjectCount[0]?.count ?? 0),
  };
}

// ─── Subscription ─────────────────────────────────────────────────────────────
export async function activateTrial(userId: number): Promise<void> {
  const db = await getDb();
  if (!db) return;
  const now = new Date();
  const trialEnd = new Date(now.getTime() + 7 * 24 * 60 * 60 * 1000);
  await db.update(users).set({ plan: "trial", trialStartedAt: now, trialEndsAt: trialEnd }).where(eq(users.id, userId));
}

export async function checkAndExpirePlans(): Promise<void> {
  const db = await getDb();
  if (!db) return;
  const now = new Date();
  
  // Expire trials
  await db.update(users).set({ plan: "free" }).where(and(eq(users.plan, "trial"), lte(users.trialEndsAt, now)));
  
  // Expire premiums
  await db.update(users).set({ plan: "free", subscriptionPlan: null }).where(and(eq(users.plan, "premium"), lte(users.premiumEndsAt, now)));
}

export async function getUserPlanStatus(user: User): Promise<{
  plan: string;
  isActive: boolean;
  trialDaysLeft?: number;
  canAccessPremium: boolean;
}> {
  if (user.plan === "premium") {
    const isActive = !user.premiumEndsAt || user.premiumEndsAt > new Date();
    if (!isActive) {
      await updateUser(user.id, { plan: "free", subscriptionPlan: null });
      return { plan: "free", isActive: false, canAccessPremium: false };
    }
    return { plan: "premium", isActive, canAccessPremium: isActive };
  }
  if (user.plan === "trial") {
    const now = new Date();
    const trialActive = user.trialEndsAt ? user.trialEndsAt > now : false;
    const trialDaysLeft = user.trialEndsAt
      ? Math.max(0, Math.ceil((user.trialEndsAt.getTime() - now.getTime()) / 86400000))
      : 0;
    if (!trialActive) {
      await updateUser(user.id, { plan: "free" });
      return { plan: "free", isActive: false, canAccessPremium: false };
    }
    return { plan: "trial", isActive: true, trialDaysLeft, canAccessPremium: true };
  }
  return { plan: "free", isActive: true, canAccessPremium: false };
}

// ─── Discursive Questions ───────────────────────────────────────────────────────────────
export async function getDiscursiveQuestions(filters: {
  disciplineId?: number;
  subjectId?: number;
  difficulty?: string;
  search?: string;
  isPremium?: boolean;
  page?: number;
  limit?: number;
}): Promise<{ questions: DiscursiveQuestion[]; total: number }> {
  const db = await getDb();
  if (!db) return { questions: [], total: 0 };

  const conditions = [eq(discursiveQuestions.active, true)];
  if (filters.disciplineId) conditions.push(eq(discursiveQuestions.disciplineId, filters.disciplineId));
  if (filters.subjectId) conditions.push(eq(discursiveQuestions.subjectId, filters.subjectId));
  if (filters.difficulty) conditions.push(eq(discursiveQuestions.difficulty, filters.difficulty as "easy" | "medium" | "hard"));
  if (filters.isPremium !== undefined) conditions.push(eq(discursiveQuestions.isPremium, filters.isPremium));
  if (filters.search) conditions.push(like(discursiveQuestions.textPt, `%${filters.search}%`));

  const where = and(...conditions);
  const page = filters.page ?? 1;
  const limit = filters.limit ?? 20;
  const offset = (page - 1) * limit;

  const [rows, countRows] = await Promise.all([
    db.select().from(discursiveQuestions).where(where).orderBy(desc(discursiveQuestions.createdAt)).limit(limit).offset(offset),
    db.select({ count: sql<number>`count(*)` }).from(discursiveQuestions).where(where),
  ]);

  return { questions: rows, total: Number(countRows[0]?.count ?? 0) };
}

export async function getDiscursiveQuestionById(id: number): Promise<DiscursiveQuestion | undefined> {
  const db = await getDb();
  if (!db) return undefined;
  const result = await db.select().from(discursiveQuestions).where(eq(discursiveQuestions.id, id)).limit(1);
  return result[0];
}

export async function createDiscursiveQuestion(data: InsertDiscursiveQuestion): Promise<number> {
  const db = await getDb();
  if (!db) throw new Error("DB not available");
  const result = await db.insert(discursiveQuestions).values(data);
  return (result[0] as { insertId: number }).insertId;
}

export async function updateDiscursiveQuestion(id: number, data: Partial<InsertDiscursiveQuestion>): Promise<void> {
  const db = await getDb();
  if (!db) return;
  await db.update(discursiveQuestions).set(data).where(eq(discursiveQuestions.id, id));
}

export async function deleteDiscursiveQuestion(id: number): Promise<void> {
  const db = await getDb();
  if (!db) return;
  await db.update(discursiveQuestions).set({ active: false }).where(eq(discursiveQuestions.id, id));
}

// ─── Seed badges ────────────────────────────────────────────────────────────────
export async function seedBadges(): Promise<void> {
  const db = await getDb();
  if (!db) return;

  const existing = await db.select({ count: sql<number>`count(*)` }).from(badges);
  if (Number(existing[0]?.count ?? 0) > 0) return;

  const badgeData = [
    // ── Comuns: marcos iniciais ─────────────────────────────────────────────
    { slug: "first-question", namePt: "Primeiro Plantão", nameEn: "First Shift", descriptionPt: "Responda sua primeira questão", descriptionEn: "Answer your first question", icon: "🩺", color: "#4ade80", rarity: "common" as const, condition: { type: "practice_count", value: 1 } },
    { slug: "first-exam", namePt: "Primeiro Passo", nameEn: "First Step", descriptionPt: "Complete seu primeiro simulado", descriptionEn: "Complete your first exam", icon: "🎯", color: "#4ade80", rarity: "common" as const, condition: { type: "exams", value: 1 } },
    { slug: "streak-3", namePt: "Dedicado", nameEn: "Dedicated", descriptionPt: "3 dias consecutivos de prática", descriptionEn: "3 consecutive practice days", icon: "🔥", color: "#f97316", rarity: "common" as const, condition: { type: "streak", value: 3 } },
    { slug: "xp-100", namePt: "Calouro de Mérito", nameEn: "Merit Freshman", descriptionPt: "Acumule 100 XP", descriptionEn: "Accumulate 100 XP", icon: "📋", color: "#a3e635", rarity: "common" as const, condition: { type: "xp", value: 100 } },
    { slug: "questions-50", namePt: "50 Questões", nameEn: "50 Questions", descriptionPt: "Responda 50 questões", descriptionEn: "Answer 50 questions", icon: "📚", color: "#38bdf8", rarity: "common" as const, condition: { type: "practice_count", value: 50 } },
    { slug: "exams-3", namePt: "Caderno de Campo", nameEn: "Field Notebook", descriptionPt: "Complete 3 simulados", descriptionEn: "Complete 3 exams", icon: "📝", color: "#fb923c", rarity: "common" as const, condition: { type: "exams", value: 3 } },

    // ── Raros: esforço consistente ──────────────────────────────────────────
    { slug: "streak-7", namePt: "Acende a Chama", nameEn: "Light the Flame", descriptionPt: "7 dias consecutivos de prática", descriptionEn: "7 consecutive practice days", icon: "⚡", color: "#eab308", rarity: "rare" as const, condition: { type: "streak", value: 7 } },
    { slug: "streak-14", namePt: "Residente em Campo", nameEn: "Field Resident", descriptionPt: "14 dias consecutivos de prática", descriptionEn: "14 consecutive practice days", icon: "🏕️", color: "#f59e0b", rarity: "rare" as const, condition: { type: "streak", value: 14 } },
    { slug: "xp-500", namePt: "Estudante Dedicado", nameEn: "Dedicated Student", descriptionPt: "Acumule 500 XP", descriptionEn: "Accumulate 500 XP", icon: "⭐", color: "#06b6d4", rarity: "rare" as const, condition: { type: "xp", value: 500 } },
    { slug: "xp-2000", namePt: "Veterano", nameEn: "Veteran", descriptionPt: "Acumule 2000 XP", descriptionEn: "Accumulate 2000 XP", icon: "🏅", color: "#3b82f6", rarity: "rare" as const, condition: { type: "xp", value: 2000 } },
    { slug: "questions-200", namePt: "200 Questões", nameEn: "200 Questions", descriptionPt: "Responda 200 questões", descriptionEn: "Answer 200 questions", icon: "📖", color: "#818cf8", rarity: "rare" as const, condition: { type: "practice_count", value: 200 } },
    { slug: "exams-10", namePt: "Maratonista", nameEn: "Marathon Runner", descriptionPt: "Complete 10 simulados", descriptionEn: "Complete 10 exams", icon: "🏃", color: "#ef4444", rarity: "rare" as const, condition: { type: "exams", value: 10 } },
    { slug: "accuracy-70", namePt: "Diagnóstico Preciso", nameEn: "Precise Diagnosis", descriptionPt: "Mantenha 70% de acerto geral", descriptionEn: "Maintain 70% overall accuracy", icon: "🔬", color: "#2dd4bf", rarity: "rare" as const, condition: { type: "accuracy", value: 70 } },
    { slug: "level-5", namePt: "Meio do Caminho", nameEn: "Halfway There", descriptionPt: "Alcance o nível 5", descriptionEn: "Reach level 5", icon: "🚀", color: "#8b5cf6", rarity: "rare" as const, condition: { type: "level", value: 5 } },

    // ── Épicos: feitos difíceis ──────────────────────────────────────────────
    { slug: "streak-30", namePt: "Chama Azul", nameEn: "Blue Flame", descriptionPt: "30 dias consecutivos de prática", descriptionEn: "30 consecutive practice days", icon: "💙", color: "#a855f7", rarity: "epic" as const, condition: { type: "streak", value: 30 } },
    { slug: "streak-100", namePt: "Ofensiva de Diamante", nameEn: "Diamond Offensive", descriptionPt: "100 dias consecutivos de prática", descriptionEn: "100 consecutive practice days", icon: "💎", color: "#67e8f9", rarity: "epic" as const, condition: { type: "streak", value: 100 } },
    { slug: "xp-5000", namePt: "Especialista Sênior", nameEn: "Senior Specialist", descriptionPt: "Acumule 5000 XP", descriptionEn: "Accumulate 5000 XP", icon: "🏆", color: "#f59e0b", rarity: "epic" as const, condition: { type: "xp", value: 5000 } },
    { slug: "questions-1000", namePt: "Ultra-Maratonista", nameEn: "Ultra-Marathon", descriptionPt: "Responda 1000 questões", descriptionEn: "Answer 1000 questions", icon: "🌙", color: "#6366f1", rarity: "epic" as const, condition: { type: "practice_count", value: 1000 } },
    { slug: "accuracy-80", namePt: "Precisão Cirúrgica", nameEn: "Surgical Precision", descriptionPt: "Mantenha 80% de acerto geral", descriptionEn: "Maintain 80% overall accuracy", icon: "🎖️", color: "#10b981", rarity: "epic" as const, condition: { type: "accuracy", value: 80 } },
    { slug: "exams-30", namePt: "Plantonista Dedicado", nameEn: "Dedicated On-Call", descriptionPt: "Complete 30 simulados", descriptionEn: "Complete 30 exams", icon: "🏥", color: "#f43f5e", rarity: "epic" as const, condition: { type: "exams", value: 30 } },

    // ── Lendários ────────────────────────────────────────────────────────────
    { slug: "level-9", namePt: "A Lenda", nameEn: "The Legend", descriptionPt: "Alcance o nível máximo", descriptionEn: "Reach the maximum level", icon: "👑", color: "#f59e0b", rarity: "legendary" as const, condition: { type: "level", value: 9 } },
    { slug: "streak-365", namePt: "Médico Veterinário de Plantão", nameEn: "On-Call Vet", descriptionPt: "365 dias consecutivos de prática", descriptionEn: "365 consecutive practice days", icon: "🌟", color: "#fbbf24", rarity: "legendary" as const, condition: { type: "streak", value: 365 } },
    { slug: "accuracy-90-100q", namePt: "Tiro Cirúrgico", nameEn: "Surgical Shot", descriptionPt: "90% de acerto com ao menos 100 questões respondidas", descriptionEn: "90% accuracy with at least 100 questions answered", icon: "🎯", color: "#ef4444", rarity: "legendary" as const, condition: { type: "accuracy_min_questions", value: 90, minQuestions: 100 } },

    // ── Easter Eggs (hidden) ─────────────────────────────────────────────────
    { slug: "esteatose-saudavel", namePt: "Esteatose Saudável", nameEn: "Healthy Steatosis", descriptionPt: "Acertou questões sobre elasmobrânquios (tubarões e raias)", descriptionEn: "Answered questions about elasmobranchs", icon: "🦈", color: "#0ea5e9", rarity: "hidden" as const, condition: { type: "easter_egg", value: 1, key: "elasmobranch" } },
    { slug: "reptil-sangue-frio", namePt: "Réptil de Sangue Frio", nameEn: "Cold-Blooded Reptile", descriptionPt: "Estudou em 5 feriados nacionais", descriptionEn: "Studied on 5 national holidays", icon: "🐍", color: "#84cc16", rarity: "hidden" as const, condition: { type: "easter_egg", value: 5, key: "holiday_study" } },
    { slug: "one-health", namePt: "One Health", nameEn: "One Health", descriptionPt: "Acertou questões de todas as grandes áreas no mesmo dia", descriptionEn: "Answered correctly in all major areas on the same day", icon: "🐾", color: "#22d3ee", rarity: "hidden" as const, condition: { type: "easter_egg", value: 1, key: "one_health_day" } },
    { slug: "cafe-casuistica", namePt: "Café com Casuística", nameEn: "Coffee & Casuistry", descriptionPt: "Respondeu questões antes das 7h por 7 dias", descriptionEn: "Answered questions before 7am for 7 days", icon: "☕", color: "#92400e", rarity: "hidden" as const, condition: { type: "easter_egg", value: 7, key: "early_bird" } },
  ];

  for (const badge of badgeData) {
    await db.insert(badges).values(badge).onDuplicateKeyUpdate({ set: { namePt: badge.namePt } });
  }
}

// ─── Seed disciplines ─────────────────────────────────────────────────────────
export async function seedDisciplines(): Promise<void> {
  const db = await getDb();
  if (!db) return;

  const existing = await db.select({ count: sql<number>`count(*)` }).from(disciplines);
  if (Number(existing[0]?.count ?? 0) > 0) return;

  const disciplineData = [
    { slug: "pharmacology", namePt: "Farmacologia", nameEn: "Pharmacology", icon: "💊", color: "#4ade80" },
    { slug: "clinics", namePt: "Clínica", nameEn: "Clinics", icon: "🩺", color: "#06b6d4" },
    { slug: "herpetology", namePt: "Herpetologia", nameEn: "Herpetology", icon: "🦎", color: "#84cc16" },
    { slug: "ornithology", namePt: "Ornitologia", nameEn: "Ornithology", icon: "🦜", color: "#f59e0b" },
    { slug: "anesthesiology", namePt: "Anestesiologia", nameEn: "Anesthesiology", icon: "💉", color: "#8b5cf6" },
    { slug: "small-mammals", namePt: "Pequenos Mamíferos", nameEn: "Small Mammals", icon: "🐹", color: "#f97316" },
  ];

  for (const d of disciplineData) {
    await db.insert(disciplines).values(d).onDuplicateKeyUpdate({ set: { namePt: d.namePt } });
  }
}
