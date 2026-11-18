import {
  boolean,
  decimal,
  index,
  int,
  json,
  mysqlEnum,
  mysqlTable,
  text,
  timestamp,
  varchar,
  float,
  bigint,
} from "drizzle-orm/mysql-core";

// ─── Users ────────────────────────────────────────────────────────────────────
export const users = mysqlTable("users", {
  id: int("id").autoincrement().primaryKey(),
  openId: varchar("openId", { length: 64 }).notNull().unique(),
  name: text("name"),
  email: varchar("email", { length: 320 }),
  loginMethod: varchar("loginMethod", { length: 64 }),
  role: mysqlEnum("role", ["user", "teacher", "coordinator", "superuser", "admin"]).default("user").notNull(),
  // Gamification
  xp: int("xp").default(0).notNull(),
  level: int("level").default(1).notNull(),
  streak: int("streak").default(0).notNull(),
  lastLoginDate: varchar("lastLoginDate", { length: 10 }), // YYYY-MM-DD
  lastPracticeDate: varchar("lastPracticeDate", { length: 10 }), // YYYY-MM-DD (5+ questões/dia)
  coins: int("coins").default(0).notNull(),
  streakFreezes: int("streakFreezes").default(0).notNull(),
  streakRecoveryUsedAt: varchar("streakRecoveryUsedAt", { length: 10 }), // YYYY-MM
  // Subscription
  plan: mysqlEnum("plan", ["free", "trial", "premium"]).default("free").notNull(),
  trialStartedAt: timestamp("trialStartedAt"),
  trialEndsAt: timestamp("trialEndsAt"),
  premiumStartedAt: timestamp("premiumStartedAt"),
  premiumEndsAt: timestamp("premiumEndsAt"),
  subscriptionPlan: mysqlEnum("subscriptionPlan", ["monthly", "annual"]),  // active plan type
  mpPreapprovalId: varchar("mpPreapprovalId", { length: 128 }),  // Mercado Pago recurring subscription (preapproval) ID — needed to cancel
  // Stats
  totalExams: int("totalExams").default(0).notNull(),
  totalQuestions: int("totalQuestions").default(0).notNull(),
  totalCorrect: int("totalCorrect").default(0).notNull(),
  // Profile
  cpf: varchar("cpf", { length: 14 }), // 000.000.000-00
  phone: varchar("phone", { length: 20 }),
  avatarUrl: varchar("avatarUrl", { length: 512 }),
  // Referral
  referralCode: varchar("referralCode", { length: 16 }).unique(),
  referredBy: int("referredBy"), // userId of referrer
  // Email/password auth
  passwordHash: varchar("passwordHash", { length: 255 }),
  passwordResetToken: varchar("passwordResetToken", { length: 64 }),
  passwordResetTokenExpiresAt: timestamp("passwordResetTokenExpiresAt"),
  emailVerifiedAt: timestamp("emailVerifiedAt"),
  emailVerificationToken: varchar("emailVerificationToken", { length: 64 }),
  emailVerificationTokenExpiresAt: timestamp("emailVerificationTokenExpiresAt"),
  // LGPD / Terms
  lgpdConsentAt: timestamp("lgpdConsentAt"),
  lgpdConsentVersion: varchar("lgpdConsentVersion", { length: 16 }),
  tosAcceptedAt: timestamp("tosAcceptedAt"),
  tosVersion: varchar("tosVersion", { length: 16 }),
  tosAcceptedIp: varchar("tosAcceptedIp", { length: 64 }),
  // Timestamps
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
  lastSignedIn: timestamp("lastSignedIn").defaultNow().notNull(),
});

export type User = typeof users.$inferSelect;
export type InsertUser = typeof users.$inferInsert;

// ─── Teacher Permissions ──────────────────────────────────────────────────────
export const teacherPermissions = mysqlTable("teacher_permissions", {
  id: int("id").autoincrement().primaryKey(),
  teacherId: int("teacherId").notNull(),
  disciplineId: int("disciplineId").notNull(),
  canCreateQuestions: boolean("canCreateQuestions").default(true).notNull(),
  canValidateQuestions: boolean("canValidateQuestions").default(false).notNull(),
  canCreateExams: boolean("canCreateExams").default(true).notNull(),
  grantedBy: int("grantedBy").notNull(), // coordinator or superuser id
  createdAt: timestamp("createdAt").defaultNow().notNull(),
});

export type TeacherPermission = typeof teacherPermissions.$inferSelect;

// ─── Activity Log ─────────────────────────────────────────────────────────────
export const activityLog = mysqlTable("activity_log", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("userId").notNull(),
  action: varchar("action", { length: 128 }).notNull(),
  entityType: varchar("entityType", { length: 64 }), // question, exam, user, etc.
  entityId: int("entityId"),
  details: json("details"),
  ipAddress: varchar("ipAddress", { length: 64 }),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
});

export type ActivityLog = typeof activityLog.$inferSelect;

// ─── Announcements ────────────────────────────────────────────────────────────
export const announcements = mysqlTable("announcements", {
  id: int("id").autoincrement().primaryKey(),
  authorId: int("authorId").notNull(),
  titlePt: varchar("titlePt", { length: 256 }).notNull(),
  titleEn: varchar("titleEn", { length: 256 }),
  bodyPt: text("bodyPt").notNull(),
  bodyEn: text("bodyEn"),
  type: mysqlEnum("type", ["info", "exam", "update", "warning"]).default("info").notNull(),
  pinned: boolean("pinned").default(false).notNull(),
  active: boolean("active").default(true).notNull(),
  scheduledFor: timestamp("scheduledFor"),
  expiresAt: timestamp("expiresAt"),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
});

export type Announcement = typeof announcements.$inferSelect;

// ─── Question Reports ─────────────────────────────────────────────────────────
export const questionReports = mysqlTable("question_reports", {
  id: int("id").autoincrement().primaryKey(),
  questionId: int("questionId").notNull(),
  reporterId: int("reporterId").notNull(),
  category: mysqlEnum("category", ["wrong_answer", "typo", "outdated", "unclear", "image_issue", "other"]).notNull(),
  description: text("description"),
  status: mysqlEnum("status", ["pending", "reviewed", "resolved", "dismissed"]).default("pending").notNull(),
  reviewedBy: int("reviewedBy"),
  reviewNote: text("reviewNote"),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
});

export type QuestionReport = typeof questionReports.$inferSelect;

// ─── Disciplines ──────────────────────────────────────────────────────────────
export const disciplines = mysqlTable("disciplines", {
  id: int("id").autoincrement().primaryKey(),
  slug: varchar("slug", { length: 64 }).notNull().unique(),
  namePt: varchar("namePt", { length: 128 }).notNull(),
  nameEn: varchar("nameEn", { length: 128 }).notNull(),
  icon: varchar("icon", { length: 64 }),
  color: varchar("color", { length: 32 }),
  active: boolean("active").default(true).notNull(),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
});

export type Discipline = typeof disciplines.$inferSelect;

// ─── Subjects (Disciplinas) ───────────────────────────────────────────────────
export const subjects = mysqlTable("subjects", {
  id: int("id").autoincrement().primaryKey(),
  disciplineId: int("disciplineId").notNull(),
  slug: varchar("slug", { length: 64 }).notNull(),
  namePt: varchar("namePt", { length: 128 }).notNull(),
  nameEn: varchar("nameEn", { length: 128 }).notNull(),
  active: boolean("active").default(true).notNull(),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
});

export type Subject = typeof subjects.$inferSelect;

// ─── Topics (Assuntos — 3º nível da hierarquia) ───────────────────────────────
// Hierarquia: disciplines (Grande Área) → subjects (Disciplina) → topics (Assunto)
export const topics = mysqlTable("topics", {
  id: int("id").autoincrement().primaryKey(),
  subjectId: int("subjectId").notNull(),          // FK → subjects.id
  disciplineId: int("disciplineId").notNull(),    // denormalizado para queries rápidas
  slug: varchar("slug", { length: 96 }).notNull(),
  namePt: varchar("namePt", { length: 128 }).notNull(),
  nameEn: varchar("nameEn", { length: 128 }).notNull(),
  active: boolean("active").default(true).notNull(),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
}, (t) => [
  index("idx_topics_subject").on(t.subjectId),
  index("idx_topics_discipline").on(t.disciplineId),
]);

export type Topic = typeof topics.$inferSelect;
export type InsertTopic = typeof topics.$inferInsert;

// ─── Questions ────────────────────────────────────────────────────────────────
export const questions = mysqlTable("questions", {
  id: int("id").autoincrement().primaryKey(),
  disciplineId: int("disciplineId").notNull(),
  subjectId: int("subjectId"),
  topicId: int("topicId"),               // FK → topics.id (Assunto — 3º nível)
  createdBy: int("createdBy"),
  difficulty: mysqlEnum("difficulty", ["very_easy", "easy", "medium", "hard", "very_hard"]).notNull(),
  year: int("year"),
  // Question type (format)
  questionType: mysqlEnum("questionType", [
    "multiple_choice",
    "assertion_reason",
    "complex_multiple_choice",
    "matching",
    "true_false",
    "ordering",
    "cloze",
    "clinical_case",
    "image_analysis",
    "interpretation",
    "discursive",
  ]).default("multiple_choice").notNull(),
  formatData: json("formatData"),
  questionModel: mysqlEnum("questionModel", ["standard", "enade", "true_false", "assertion_reason"]).default("standard").notNull(),
  // Classification / provenance
  subjectTag: varchar("subjectTag", { length: 128 }),
  author: varchar("author", { length: 256 }),
  banca: varchar("banca", { length: 128 }),           // Examination board (VUNESP, FGV, CESPE…)
  instituicao: varchar("instituicao", { length: 256 }), // Institution (USP, UFMG…)
  cargo: varchar("cargo", { length: 256 }),             // Job/position being selected for
  carreira: varchar("carreira", { length: 128 }),        // Career track
  areaFormacao: varchar("areaFormacao", { length: 128 }), // Field of study / degree
  escolaridade: mysqlEnum("escolaridade", ["fundamental", "medio", "superior"]),
  // Content (bilingual)
  textPt: text("textPt").notNull(),
  textEn: text("textEn"),
  imageUrl: varchar("imageUrl", { length: 512 }),
  options: json("options").notNull(),
  correctOption: varchar("correctOption", { length: 4 }).notNull(),
  explanationPt: text("explanationPt"),
  explanationEn: text("explanationEn"),
  assertion1: text("assertion1"),
  assertion2: text("assertion2"),
  // M5 (Asserção–Razão): booleanos que determinam o gabarito automaticamente
  // A=V/V+relação | B=V/V | C=V/F | D=F/V | E=F/F
  a1Verdadeira: boolean("a1Verdadeira"),
  a2Verdadeira: boolean("a2Verdadeira"),
  relacaoCausal: boolean("relacaoCausal"),
  // Validation
  status: mysqlEnum("status", ["pending", "approved", "rejected"]).default("approved").notNull(),
  isValidated: boolean("isValidated").default(false).notNull(),
  validatedBy: int("validatedBy"),
  validatedAt: timestamp("validatedAt"),
  // Item model (M1–M10) — see shared/questionModels.ts
  modelId: varchar("modelId", { length: 4 }),  // "M1"|"M2"|...|"M10"|null (legado)
  grupoId: varchar("grupoId", { length: 64 }),  // M10: ID do bloco de alternativas constantes
  posicaoBloco: int("posicaoBloco"),            // M10: posição dentro do bloco (1, 2, 3…)
  // Revision flow: quando professor rejeita, o autor pode revisar e reenviar
  revisionNotes: text("revisionNotes"),        // o que foi alterado na revisão
  revisedAt: timestamp("revisedAt"),           // data da última revisão
  revisionCount: int("revisionCount").default(0).notNull(), // nº de revisões
  // Status flags
  isAnulada: boolean("isAnulada").default(false).notNull(),
  isDesatualizada: boolean("isDesatualizada").default(false).notNull(),
  // Import tracking
  batchId: int("batchId"),
  // Meta
  active: boolean("active").default(true).notNull(),
  isPremium: boolean("isPremium").default(false).notNull(),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
}, (t) => [
  // Filtros mais frequentes nas queries de listagem e simulados
  index("idx_q_discipline_subject").on(t.disciplineId, t.subjectId),
  index("idx_q_active_discipline").on(t.active, t.disciplineId),
  index("idx_q_difficulty").on(t.difficulty),
  index("idx_q_year").on(t.year),
  index("idx_q_question_type").on(t.questionType),
  index("idx_q_model_id").on(t.modelId),
  // Filtros de classificação/proveniência adicionados no sprint de filtros
  index("idx_q_banca").on(t.banca),
  index("idx_q_carreira").on(t.carreira),
  index("idx_q_area_formacao").on(t.areaFormacao),
  index("idx_q_escolaridade").on(t.escolaridade),
  // Flags de status — sempre combinados com active=true
  index("idx_q_flags").on(t.isAnulada, t.isDesatualizada),
  index("idx_q_validated").on(t.isValidated, t.status),
  // Ordenação por data e M10 bloco
  index("idx_q_created_at").on(t.createdAt),
  index("idx_q_grupo_id").on(t.grupoId, t.posicaoBloco),
]);

export type Question = typeof questions.$inferSelect;
export type InsertQuestion = typeof questions.$inferInsert;

// ─── Import Batches ───────────────────────────────────────────────────────────
export const importBatches = mysqlTable("import_batches", {
  id: int("id").autoincrement().primaryKey(),
  name: varchar("name", { length: 256 }).notNull(),
  source: mysqlEnum("source", ["json", "csv", "manual"]).default("json").notNull(),
  importedBy: int("importedBy").notNull(),
  questionCount: int("questionCount").default(0).notNull(),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
});

// ─── Exams (sessions) ─────────────────────────────────────────────────────────
export const exams = mysqlTable("exams", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("userId").notNull(),
  title: varchar("title", { length: 256 }),
  // Config
  disciplineId: int("disciplineId"),
  difficulty: mysqlEnum("difficulty", ["easy", "medium", "hard", "mixed"]),
  questionCount: int("questionCount").notNull(),
  timeLimitSeconds: int("timeLimitSeconds"),
  questionModel: mysqlEnum("questionModel", ["standard", "enade", "mixed"]).default("standard"),
  // Subject distribution: JSON [{subjectId, percentage}]
  subjectDistribution: json("subjectDistribution"),
  // Results
  score: int("score").default(0),
  totalQuestions: int("totalQuestions").notNull(),
  correctAnswers: int("correctAnswers").default(0),
  accuracy: float("accuracy").default(0),
  xpEarned: int("xpEarned").default(0),
  timeSpentSeconds: int("timeSpentSeconds"),
  // Per-discipline stats: JSON [{disciplineId, total, correct, accuracy}]
  disciplineStats: json("disciplineStats"),
  status: mysqlEnum("status", ["in_progress", "completed", "abandoned"]).default("in_progress").notNull(),
  // Question IDs stored as JSON
  questionIds: json("questionIds").notNull(),
  completedAt: timestamp("completedAt"),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
});

export type Exam = typeof exams.$inferSelect;

// ─── Exam Answers ─────────────────────────────────────────────────────────────
export const examAnswers = mysqlTable("exam_answers", {
  id: int("id").autoincrement().primaryKey(),
  examId: int("examId").notNull(),
  questionId: int("questionId").notNull(),
  selectedOption: varchar("selectedOption", { length: 4 }),
  isCorrect: boolean("isCorrect").default(false),
  timeSpentSeconds: int("timeSpentSeconds"),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
});

export type ExamAnswer = typeof examAnswers.$inferSelect;

// ─── Practice Sessions (non-exam question answering) ──────────────────────────
export const practiceSessions = mysqlTable("practice_sessions", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("userId").notNull(),
  questionId: int("questionId").notNull(),
  disciplineId: int("disciplineId").notNull(),
  subjectId: int("subjectId"),
  difficulty: mysqlEnum("difficulty", ["easy", "medium", "hard"]),
  selectedOption: varchar("selectedOption", { length: 4 }),
  isCorrect: boolean("isCorrect").default(false),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
}, (t) => [
  // Suporta EXISTS subquery de "Minhas Questões" e queries de progresso
  index("idx_ps_user_question").on(t.userId, t.questionId),
  index("idx_ps_user_correct").on(t.userId, t.isCorrect),
  index("idx_ps_user_discipline").on(t.userId, t.disciplineId),
]);

export type PracticeSession = typeof practiceSessions.$inferSelect;

// ─── Badges ───────────────────────────────────────────────────────────────────
export const badges = mysqlTable("badges", {
  id: int("id").autoincrement().primaryKey(),
  slug: varchar("slug", { length: 64 }).notNull().unique(),
  namePt: varchar("namePt", { length: 128 }).notNull(),
  nameEn: varchar("nameEn", { length: 128 }).notNull(),
  descriptionPt: text("descriptionPt"),
  descriptionEn: text("descriptionEn"),
  icon: varchar("icon", { length: 64 }).notNull(),
  color: varchar("color", { length: 32 }),
  rarity: mysqlEnum("rarity", ["common", "rare", "epic", "legendary", "hidden"]).default("common").notNull(),
  condition: json("condition"), // {type, value, ...extra}
  createdAt: timestamp("createdAt").defaultNow().notNull(),
});

export type Badge = typeof badges.$inferSelect;

// ─── User Badges ──────────────────────────────────────────────────────────────
export const userBadges = mysqlTable("user_badges", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("userId").notNull(),
  badgeId: int("badgeId").notNull(),
  earnedAt: timestamp("earnedAt").defaultNow().notNull(),
});

export type UserBadge = typeof userBadges.$inferSelect;

// ─── XP Events ────────────────────────────────────────────────────────────────
export const xpEvents = mysqlTable("xp_events", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("userId").notNull(),
  amount: int("amount").notNull(),
  reason: varchar("reason", { length: 128 }).notNull(),
  examId: int("examId"),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
});

export type XpEvent = typeof xpEvents.$inferSelect;

// ─── Weekly XP Snapshots (for leaderboard) ────────────────────────────────────
export const weeklyXp = mysqlTable("weekly_xp", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("userId").notNull(),
  weekKey: varchar("weekKey", { length: 10 }).notNull(), // YYYY-WW
  xp: int("xp").default(0).notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
});

export const monthlyXp = mysqlTable("monthly_xp", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("userId").notNull(),
  monthKey: varchar("monthKey", { length: 7 }).notNull(), // YYYY-MM
  xp: int("xp").default(0).notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
});

// ─── Coin Events ─────────────────────────────────────────────────────────────
export const coinEvents = mysqlTable("coin_events", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("userId").notNull(),
  amount: int("amount").notNull(), // positivo = ganho, negativo = gasto
  reason: varchar("reason", { length: 128 }).notNull(),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
});

export type CoinEvent = typeof coinEvents.$inferSelect;

// ─── Shop Items ───────────────────────────────────────────────────────────────
// productType distingue itens de gamificação (moedas) de produtos pagos (cursos)
export const shopItems = mysqlTable("shop_items", {
  id: int("id").autoincrement().primaryKey(),
  slug: varchar("slug", { length: 64 }).notNull().unique(),
  namePt: varchar("namePt", { length: 128 }).notNull(),
  nameEn: varchar("nameEn", { length: 128 }).notNull(),
  descriptionPt: text("descriptionPt"),
  descriptionEn: text("descriptionEn"),
  // Tipo de produto: gamification = comprado com VetCoins; course/content = comprado com R$ (futuro)
  productType: mysqlEnum("productType", ["gamification", "course", "content"]).default("gamification").notNull(),
  // Para gamification: custo em VetCoins. Para course/content: preço em centavos (BRL)
  cost: int("cost").notNull(),
  category: mysqlEnum("category", ["utility", "cosmetic", "luck", "education"]).notNull(),
  itemType: varchar("itemType", { length: 64 }).notNull(), // freeze, recovery, chest_rare, course_id, etc.
  icon: varchar("icon", { length: 64 }),
  imageUrl: varchar("imageUrl", { length: 512 }),
  // Para cursos: referência ao conteúdo (disciplineId, trailId, etc.)
  contentRef: json("contentRef"),
  sortOrder: int("sortOrder").default(0).notNull(),
  active: boolean("active").default(true).notNull(),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
});

export type ShopItem = typeof shopItems.$inferSelect;

// ─── Purchases ────────────────────────────────────────────────────────────────
export const purchases = mysqlTable("purchases", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("userId").notNull(),
  itemId: int("itemId").notNull(),
  productType: mysqlEnum("productType", ["gamification", "course", "content"]).default("gamification").notNull(),
  // Para gamification: coinsSpent. Para cursos: priceAtPurchase em centavos
  coinsSpent: int("coinsSpent"),
  priceAtPurchase: int("priceAtPurchase"), // centavos BRL (para compras pagas)
  paymentRef: varchar("paymentRef", { length: 128 }), // ID da transação MP (para compras pagas)
  createdAt: timestamp("createdAt").defaultNow().notNull(),
});

export type Purchase = typeof purchases.$inferSelect;

// ─── Daily Chests ─────────────────────────────────────────────────────────────
export const dailyChests = mysqlTable("daily_chests", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("userId").notNull(),
  claimedDate: varchar("claimedDate", { length: 10 }).notNull(), // YYYY-MM-DD
  rewardType: varchar("rewardType", { length: 32 }).notNull(), // coins, badge, freeze
  rewardAmount: int("rewardAmount").default(0).notNull(),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
});

export type DailyChest = typeof dailyChests.$inferSelect;

// ─── Daily Roulette ───────────────────────────────────────────────────────────
export const dailyRoulette = mysqlTable("daily_roulette", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("userId").notNull(),
  spunDate: varchar("spunDate", { length: 10 }).notNull(), // YYYY-MM-DD
  rewardType: varchar("rewardType", { length: 32 }).notNull(),
  rewardAmount: int("rewardAmount").default(0).notNull(),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
});

export type DailyRouletteEntry = typeof dailyRoulette.$inferSelect;

// ─── Leagues ──────────────────────────────────────────────────────────────────
export const leagues = mysqlTable("leagues", {
  id: int("id").autoincrement().primaryKey(),
  weekKey: varchar("weekKey", { length: 10 }).notNull(), // YYYY-WW
  division: mysqlEnum("division", ["iron", "bronze", "silver", "gold", "platinum", "diamond", "master"]).notNull(),
  groupIndex: int("groupIndex").notNull(), // grupo dentro da divisão
  status: mysqlEnum("status", ["active", "completed"]).default("active").notNull(),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
});

export type League = typeof leagues.$inferSelect;

// ─── League Memberships ───────────────────────────────────────────────────────
export const leagueMemberships = mysqlTable("league_memberships", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("userId").notNull(),
  leagueId: int("leagueId").notNull(),
  division: mysqlEnum("division", ["iron", "bronze", "silver", "gold", "platinum", "diamond", "master"]).notNull(),
  xpGained: int("xpGained").default(0).notNull(),
  rank: int("rank"),
  result: mysqlEnum("result", ["promoted", "stayed", "relegated", "none"]).default("none").notNull(),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
});

export type LeagueMembership = typeof leagueMemberships.$inferSelect;

// ─── Discursive Questions ───────────────────────────────────────────────────────────────
export const discursiveQuestions = mysqlTable("discursive_questions", {
  id: int("id").autoincrement().primaryKey(),
  disciplineId: int("disciplineId").notNull(),
  subjectId: int("subjectId"),
  subjectTag: varchar("subjectTag", { length: 128 }),
  author: varchar("author", { length: 256 }),
  difficulty: mysqlEnum("difficulty", ["easy", "medium", "hard"]).notNull(),
  year: int("year"),
  imageUrl: varchar("imageUrl", { length: 512 }),
  textPt: text("textPt").notNull(),
  textEn: text("textEn"),
  expectedAnswerPt: text("expectedAnswerPt").notNull(),
  expectedAnswerEn: text("expectedAnswerEn"),
  isValidated: boolean("isValidated").default(false).notNull(),
  validatedBy: int("validatedBy"),
  validatedAt: timestamp("validatedAt"),
  active: boolean("active").default(true).notNull(),
  isPremium: boolean("isPremium").default(true).notNull(),
  createdBy: int("createdBy"),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
});

export type DiscursiveQuestion = typeof discursiveQuestions.$inferSelect;
export type InsertDiscursiveQuestion = typeof discursiveQuestions.$inferInsert;

// ─── Trails (Trilhas do Conhecimento) ────────────────────────────────────────
export const trails = mysqlTable("trails", {
  id: int("id").autoincrement().primaryKey(),
  disciplineId: int("disciplineId").notNull(),
  title: varchar("title", { length: 256 }).notNull(),
  description: text("description"),
  totalHours: int("totalHours").default(20).notNull(),
  passingScore: int("passingScore").default(70).notNull(),
  finalExamQuestions: int("finalExamQuestions").default(30).notNull(),
  finalExamTimeSeconds: int("finalExamTimeSeconds").default(3600).notNull(),
  // "subscription" = incluída no plano premium; "purchase" = comprada individualmente
  accessType: mysqlEnum("accessType", ["subscription", "purchase"]).default("subscription").notNull(),
  price: decimal("price", { precision: 8, scale: 2 }),
  imageUrl: varchar("imageUrl", { length: 512 }),
  active: boolean("active").default(true).notNull(),
  createdBy: int("createdBy").notNull(),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
});

export type Trail = typeof trails.$inferSelect;
export type InsertTrail = typeof trails.$inferInsert;

// ─── Trail Modules ────────────────────────────────────────────────────────────
export const trailModules = mysqlTable("trail_modules", {
  id: int("id").autoincrement().primaryKey(),
  trailId: int("trailId").notNull(),
  order: int("order").notNull(), // 1-based sequential order
  title: varchar("title", { length: 256 }).notNull(),
  summary: text("summary"),
  difficulty: mysqlEnum("difficulty", ["easy", "medium", "hard", "mixed"]).default("mixed").notNull(),
  questionCount: int("questionCount").default(20).notNull(),
  cycleSize: int("cycleSize").default(10).notNull(), // How many questions per answering cycle
  minPassRate: int("minPassRate").default(70).notNull(), // % correct to pass
  // Filter config for question selection (JSON: {disciplineId, subjectIds, difficulty, author, year})
  questionFilter: json("questionFilter"),
  ebookUrl: varchar("ebookUrl", { length: 512 }),
  ebookTitle: varchar("ebookTitle", { length: 256 }),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
});

export type TrailModule = typeof trailModules.$inferSelect;
export type InsertTrailModule = typeof trailModules.$inferInsert;

// ─── Trail Module Questions (manually assigned questions) ─────────────────────
export const trailModuleQuestions = mysqlTable("trail_module_questions", {
  id: int("id").autoincrement().primaryKey(),
  moduleId: int("moduleId").notNull(),
  questionId: int("questionId").notNull(),
  order: int("order").default(0).notNull(),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
});

export type TrailModuleQuestion = typeof trailModuleQuestions.$inferSelect;

// ─── Trail Enrollments ────────────────────────────────────────────────────────
export const trailEnrollments = mysqlTable("trail_enrollments", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("userId").notNull(),
  trailId: int("trailId").notNull(),
  status: mysqlEnum("status", ["enrolled", "in_progress", "completed", "failed"]).default("enrolled").notNull(),
  currentModuleId: int("currentModuleId"),
  finalExamScore: int("finalExamScore"),
  finalExamPassed: boolean("finalExamPassed").default(false),
  startedAt: timestamp("startedAt").defaultNow().notNull(),
  completedAt: timestamp("completedAt"),
  certificateUrl: varchar("certificateUrl", { length: 512 }),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
});

export type TrailEnrollment = typeof trailEnrollments.$inferSelect;

// ─── Trail Module Progress ────────────────────────────────────────────────────
export const trailModuleProgress = mysqlTable("trail_module_progress", {
  id: int("id").autoincrement().primaryKey(),
  enrollmentId: int("enrollmentId").notNull(),
  moduleId: int("moduleId").notNull(),
  userId: int("userId").notNull(),
  status: mysqlEnum("status", ["locked", "available", "in_progress", "passed", "failed"]).default("locked").notNull(),
  attempts: int("attempts").default(0).notNull(),
  bestScore: int("bestScore").default(0).notNull(), // best % correct across attempts
  lastScore: int("lastScore").default(0).notNull(),
  questionsAnswered: int("questionsAnswered").default(0).notNull(),
  questionsCorrect: int("questionsCorrect").default(0).notNull(),
  completedAt: timestamp("completedAt"),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
});

export type TrailModuleProgress = typeof trailModuleProgress.$inferSelect;

// ─── Trail Module Answers ─────────────────────────────────────────────────────
export const trailModuleAnswers = mysqlTable("trail_module_answers", {
  id: int("id").autoincrement().primaryKey(),
  progressId: int("progressId").notNull(),
  userId: int("userId").notNull(),
  moduleId: int("moduleId").notNull(),
  questionId: int("questionId").notNull(),
  selectedOption: varchar("selectedOption", { length: 4 }),
  isCorrect: boolean("isCorrect").default(false),
  attemptNumber: int("attemptNumber").default(1).notNull(),
  answeredAt: timestamp("answeredAt").defaultNow().notNull(),
});

export type TrailModuleAnswer = typeof trailModuleAnswers.$inferSelect;

// ─── Trail Purchases (paid trails — accessType="purchase") ───────────────────
export const trailPurchases = mysqlTable("trail_purchases", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("userId").notNull(),
  trailId: int("trailId").notNull(),
  pricePaid: decimal("pricePaid", { precision: 8, scale: 2 }).notNull(),
  status: mysqlEnum("status", ["pending", "active", "refunded"]).default("pending").notNull(),
  transactionId: varchar("transactionId", { length: 128 }),
  purchasedAt: timestamp("purchasedAt"),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
});

export type TrailPurchase = typeof trailPurchases.$inferSelect;
export type InsertTrailPurchase = typeof trailPurchases.$inferInsert;

// ─── Referrals ────────────────────────────────────────────────────────────────
export const referrals = mysqlTable("referrals", {
  id: int("id").autoincrement().primaryKey(),
  referrerId: int("referrerId").notNull(), // user who referred
  referredEmail: varchar("referredEmail", { length: 320 }).notNull(),
  referredUserId: int("referredUserId"), // set when referred user registers
  status: mysqlEnum("status", ["pending", "registered", "paid", "expired"]).default("pending").notNull(),
  // Only counts after referred user pays (not trial)
  paidAt: timestamp("paidAt"),
  planPurchased: mysqlEnum("planPurchased", ["monthly", "annual"]),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
});

export type Referral = typeof referrals.$inferSelect;
export type InsertReferral = typeof referrals.$inferInsert;

// ─── Referral Bonuses ─────────────────────────────────────────────────────────
export const referralBonuses = mysqlTable("referral_bonuses", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("userId").notNull(),
  bonusType: mysqlEnum("bonusType", ["free_annual"]).default("free_annual").notNull(),
  paidReferralsCount: int("paidReferralsCount").notNull(), // snapshot at time of bonus
  activatedAt: timestamp("activatedAt").defaultNow().notNull(),
  expiresAt: timestamp("expiresAt").notNull(), // +1 year from activation
  createdAt: timestamp("createdAt").defaultNow().notNull(),
});

export type ReferralBonus = typeof referralBonuses.$inferSelect;

// ─── Question Assignments (coordinator assigns questions to professors for validation) ─
export const questionAssignments = mysqlTable("question_assignments", {
  id: int("id").autoincrement().primaryKey(),
  assignedBy: int("assignedBy").notNull(),
  assignedTo: int("assignedTo").notNull(),
  questionId: int("questionId").notNull(),
  questionType: mysqlEnum("questionType", ["multiple_choice", "discursive"]).default("multiple_choice").notNull(),
  status: mysqlEnum("status", ["pending", "approved", "rejected"]).default("pending").notNull(),
  notes: text("notes"),
  // Structured rejection: obrigatório quando status = "rejected"
  rejectionReason: mysqlEnum("rejectionReason", [
    "erro_conteudo",      // Erro técnico no conteúdo
    "gabarito_incorreto", // Gabarito incorreto
    "alternativas",       // Alternativas mal elaboradas
    "enunciado_ambiguo",  // Enunciado ambíguo ou confuso
    "nivel_inadequado",   // Nível de dificuldade inadequado
    "fora_escopo",        // Fora do escopo da disciplina
    "duplicata",          // Questão duplicada no banco
    "linguagem",          // Linguagem ou formato inadequado
    "outros",             // Outros motivos
  ]),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
}, (t) => [
  index("idx_assign_status").on(t.status, t.assignedTo),
  index("idx_assign_question").on(t.questionId),
]);

export type QuestionAssignment = typeof questionAssignments.$inferSelect;
export type InsertQuestionAssignment = typeof questionAssignments.$inferInsert;

// ─── Payments ─────────────────────────────────────────────────────────────────
export const payments = mysqlTable("payments", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("userId").notNull(),
  amount: float("amount").notNull(),            // in BRL
  currency: varchar("currency", { length: 3 }).default("BRL").notNull(),
  status: mysqlEnum("status", ["pending", "approved", "rejected", "cancelled", "refunded"]).default("pending").notNull(),
  paymentMethod: varchar("paymentMethod", { length: 64 }),  // pix, credit_card, boleto, etc.
  planType: mysqlEnum("planType", ["monthly", "annual"]),
  externalId: varchar("externalId", { length: 128 }),       // Mercado Pago preference/payment id
  failureReason: text("failureReason"),                     // reason when status=rejected
  metadata: json("metadata"),                               // raw MP response
  notes: text("notes"),                                     // admin notes
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
});
export type Payment = typeof payments.$inferSelect;
export type InsertPayment = typeof payments.$inferInsert;

// ─── MP Webhook Events (log de todos os eventos do Mercado Pago) ──────────────
export const mpWebhookEvents = mysqlTable("mp_webhook_events", {
  id: int("id").autoincrement().primaryKey(),
  mpEventId: varchar("mpEventId", { length: 128 }),      // MP event ID (dedup)
  type: varchar("type", { length: 64 }).notNull(),        // payment, subscription_preapproval, claim, chargebacks, fraud_alert
  action: varchar("action", { length: 64 }),              // created, updated, etc.
  status: varchar("status", { length: 64 }),              // approved, rejected, opened, closed, etc.
  userId: int("userId"),                                   // resolved from external_reference (nullable)
  externalId: varchar("externalId", { length: 128 }),     // MP payment/subscription/claim ID
  amount: float("amount"),                                 // valor envolvido (quando aplicável)
  planType: varchar("planType", { length: 16 }),          // monthly | annual
  resolved: boolean("resolved").default(false).notNull(), // admin marcou como resolvido
  adminNote: text("adminNote"),                           // nota do admin
  raw: json("raw"),                                       // payload bruto do webhook
  createdAt: timestamp("createdAt").defaultNow().notNull(),
}, (t) => [
  index("idx_mp_events_type").on(t.type),
  index("idx_mp_events_user").on(t.userId),
  index("idx_mp_events_resolved").on(t.resolved),
]);

export type MpWebhookEvent = typeof mpWebhookEvents.$inferSelect;

// ─── Support Tickets ──────────────────────────────────────────────────────────
export const supportTickets = mysqlTable("support_tickets", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("userId").notNull(),
  subject: varchar("subject", { length: 256 }).notNull(),
  category: mysqlEnum("category", [
    "pagamento", "acesso", "bug", "conteudo", "sugestao", "outros"
  ]).default("outros").notNull(),
  priority: mysqlEnum("priority", ["low", "medium", "high", "urgent"]).default("medium").notNull(),
  status: mysqlEnum("status", ["open", "in_progress", "waiting_user", "resolved", "closed"]).default("open").notNull(),
  adminNote: text("adminNote"),
  assignedTo: int("assignedTo"),
  closedAt: timestamp("closedAt"),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
}, (t) => [
  index("idx_ticket_user").on(t.userId),
  index("idx_ticket_status").on(t.status),
]);

export type SupportTicket = typeof supportTickets.$inferSelect;

export const supportMessages = mysqlTable("support_messages", {
  id: int("id").autoincrement().primaryKey(),
  ticketId: int("ticketId").notNull(),
  authorId: int("authorId").notNull(),
  authorType: mysqlEnum("authorType", ["user", "admin"]).notNull(),
  message: text("message").notNull(),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
}, (t) => [
  index("idx_msg_ticket").on(t.ticketId),
]);

export type SupportMessage = typeof supportMessages.$inferSelect;

// ─── Saved Filters ────────────────────────────────────────────────────────────
export const savedFilters = mysqlTable("saved_filters", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("userId").notNull(),
  name: varchar("name", { length: 128 }).notNull(),
  filters: json("filters").notNull(), // serialized QuestionFilters object
  createdAt: timestamp("createdAt").defaultNow().notNull(),
});

export type SavedFilter = typeof savedFilters.$inferSelect;
export type InsertSavedFilter = typeof savedFilters.$inferInsert;

// ─── M3: Assertivas (Complex Multiple Choice) ────────────────────────────────
// Armazena cada asserção (I, II, III, IV) com seu valor de verdade.
// Permite revelar V/F individualmente ao aluno após a resposta.
export const questionAssertivas = mysqlTable("question_assertivas", {
  id: int("id").autoincrement().primaryKey(),
  questionId: int("questionId").notNull(),
  ordem: int("ordem").notNull(),              // 1, 2, 3, 4 (posição)
  label: varchar("label", { length: 4 }).notNull(), // "I", "II", "III", "IV"
  textPt: text("textPt").notNull(),
  textEn: text("textEn"),
  correta: boolean("correta").default(false).notNull(), // verdadeira ou falsa
  createdAt: timestamp("createdAt").defaultNow().notNull(),
}, (t) => [
  index("idx_assertivas_question").on(t.questionId, t.ordem),
]);

export type QuestionAssertiva = typeof questionAssertivas.$inferSelect;
export type InsertQuestionAssertiva = typeof questionAssertivas.$inferInsert;

// ─── M8: Colunas de Associação (Matching) ────────────────────────────────────
// Armazena itens de cada coluna + pares corretos separadamente.
// Permite colorir pares corretos/incorretos no renderer do aluno.
export const questionMatchingCols = mysqlTable("question_matching_cols", {
  id: int("id").autoincrement().primaryKey(),
  questionId: int("questionId").notNull(),
  coluna: mysqlEnum("coluna", ["esquerda", "direita"]).notNull(),
  ordem: int("ordem").notNull(),
  label: varchar("label", { length: 8 }).notNull(), // "1","2","3" ou "a","b","c"
  textPt: text("textPt").notNull(),
  textEn: text("textEn"),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
}, (t) => [
  index("idx_matching_question_col").on(t.questionId, t.coluna),
]);

export type QuestionMatchingCol = typeof questionMatchingCols.$inferSelect;

export const questionMatchingPairs = mysqlTable("question_matching_pairs", {
  id: int("id").autoincrement().primaryKey(),
  questionId: int("questionId").notNull(),
  esquerdaId: int("esquerdaId").notNull(), // FK → question_matching_cols.id (coluna esquerda)
  direitaId:  int("direitaId").notNull(),  // FK → question_matching_cols.id (coluna direita)
}, (t) => [
  index("idx_pairs_question").on(t.questionId),
]);

export type QuestionMatchingPair = typeof questionMatchingPairs.$inferSelect;

// ─── M10: Grupos de Alternativas Constantes ───────────────────────────────────
// Um grupo compartilha texto-base e 5 alternativas fixas entre várias questões.
// Economiza digitação e garante consistência dentro do bloco.
export const questionGroups = mysqlTable("question_groups", {
  id: int("id").autoincrement().primaryKey(),
  grupoId: varchar("grupoId", { length: 64 }).notNull().unique(),
  titulo: varchar("titulo", { length: 256 }),
  textBasePt: text("textBasePt").notNull(),
  textBaseEn: text("textBaseEn"),
  // Alternativas fixas para todo o bloco: { A:"...", B:"...", C:"...", D:"...", E:"..." }
  alternativas: json("alternativas").notNull(),
  totalQuestoes: int("totalQuestoes").default(0).notNull(),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
}, (t) => [
  index("idx_groups_grupo_id").on(t.grupoId),
]);

export type QuestionGroup = typeof questionGroups.$inferSelect;
export type InsertQuestionGroup = typeof questionGroups.$inferInsert;

// ─── Leads (potenciais clientes) ──────────────────────────────────────────────
export const leads = mysqlTable("leads", {
  id: int("id").autoincrement().primaryKey(),
  email: varchar("email", { length: 320 }).notNull().unique(),
  name: varchar("name", { length: 256 }),
  phone: varchar("phone", { length: 20 }),
  source: varchar("source", { length: 64 }).default("manual").notNull(), // manual | landing | referral | import
  status: mysqlEnum("status", ["new", "contacted", "converted", "lost"]).default("new").notNull(),
  notes: text("notes"),
  convertedUserId: int("convertedUserId"), // FK → users.id quando virar assinante
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
}, (t) => [
  index("idx_leads_status").on(t.status),
  index("idx_leads_email").on(t.email),
]);

export type Lead = typeof leads.$inferSelect;
export type InsertLead = typeof leads.$inferInsert;

// ─── Email Campaigns (histórico de disparos) ──────────────────────────────────
export const emailCampaigns = mysqlTable("email_campaigns", {
  id: int("id").autoincrement().primaryKey(),
  createdBy: int("createdBy").notNull(),
  subject: varchar("subject", { length: 256 }).notNull(),
  body: text("body").notNull(),
  // target: 'all' | 'free' | 'trial' | 'premium' | 'leads' | 'leads+free' | 'leads+all'
  target: varchar("target", { length: 64 }).notNull(),
  recipientCount: int("recipientCount").default(0).notNull(),
  sentCount: int("sentCount").default(0).notNull(),
  failedCount: int("failedCount").default(0).notNull(),
  status: mysqlEnum("status", ["sent", "failed", "partial"]).default("sent").notNull(),
  sentAt: timestamp("sentAt").defaultNow().notNull(),
});

export type EmailCampaign = typeof emailCampaigns.$inferSelect;

// ═══════════════════════════════════════════════════════════════════════════════
// MÓDULO CURSOS
// ═══════════════════════════════════════════════════════════════════════════════

// ─── Courses ──────────────────────────────────────────────────────────────────
export const courses = mysqlTable("courses", {
  id: int("id").autoincrement().primaryKey(),
  slug: varchar("slug", { length: 128 }).notNull().unique(),
  title: varchar("title", { length: 256 }).notNull(),
  description: text("description"),
  objectives: text("objectives"),       // o que o aluno vai aprender
  prerequisites: text("prerequisites"), // pré-requisitos
  thumbnailUrl: varchar("thumbnailUrl", { length: 512 }),
  trailerUrl: varchar("trailerUrl", { length: 512 }), // vídeo público de apresentação
  instructorId: int("instructorId"),    // FK → users.id (pode ser null se instrutor externo)
  instructorName: varchar("instructorName", { length: 256 }), // denormalizado para exibição
  instructorBio: text("instructorBio"),
  instructorAvatarUrl: varchar("instructorAvatarUrl", { length: 512 }),
  instructorSignatureUrl: varchar("instructorSignatureUrl", { length: 512 }), // imagem da rubrica/assinatura do instrutor
  level: mysqlEnum("level", ["beginner", "intermediate", "advanced"]).default("beginner").notNull(),
  category: varchar("category", { length: 128 }), // área veterinária (ex: "Clínica Médica")
  durationHours: int("durationHours").default(0).notNull(),
  price: decimal("price", { precision: 8, scale: 2 }).notNull(), // em BRL
  status: mysqlEnum("status", ["draft", "published"]).default("draft").notNull(),
  active: boolean("active").default(true).notNull(),
  createdBy: int("createdBy").notNull(), // admin que criou
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
}, (t) => [
  index("idx_courses_status").on(t.status, t.active),
  index("idx_courses_category").on(t.category),
  index("idx_courses_level").on(t.level),
]);

export type Course = typeof courses.$inferSelect;
export type InsertCourse = typeof courses.$inferInsert;

// ─── Course Modules ───────────────────────────────────────────────────────────
export const courseModules = mysqlTable("course_modules", {
  id: int("id").autoincrement().primaryKey(),
  courseId: int("courseId").notNull(),
  title: varchar("title", { length: 256 }).notNull(),
  order: int("order").notNull(), // 1-based, sequencial
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
}, (t) => [
  index("idx_course_modules_course").on(t.courseId, t.order),
]);

export type CourseModule = typeof courseModules.$inferSelect;
export type InsertCourseModule = typeof courseModules.$inferInsert;

// ─── Course Lessons ───────────────────────────────────────────────────────────
// Cada "etapa" dentro de um módulo — pode ser vídeo, ebook ou quiz de questões
export const courseLessons = mysqlTable("course_lessons", {
  id: int("id").autoincrement().primaryKey(),
  moduleId: int("moduleId").notNull(),
  courseId: int("courseId").notNull(), // denormalizado para queries rápidas
  title: varchar("title", { length: 256 }).notNull(),
  type: mysqlEnum("type", ["video", "ebook", "questions"]).notNull(),
  order: int("order").notNull(),        // 1-based dentro do módulo
  isRequired: boolean("isRequired").default(true).notNull(), // obrigatório para avançar
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
}, (t) => [
  index("idx_course_lessons_module").on(t.moduleId, t.order),
  index("idx_course_lessons_course").on(t.courseId),
]);

export type CourseLesson = typeof courseLessons.$inferSelect;
export type InsertCourseLesson = typeof courseLessons.$inferInsert;

// ─── Lesson Videos ────────────────────────────────────────────────────────────
export const lessonVideos = mysqlTable("lesson_videos", {
  id: int("id").autoincrement().primaryKey(),
  lessonId: int("lessonId").notNull().unique(), // 1:1 com courseLessons
  videoUrl: varchar("videoUrl", { length: 512 }).notNull(),
  provider: mysqlEnum("provider", ["vimeo", "panda", "bunny", "youtube", "other"]).default("other").notNull(),
  durationSeconds: int("durationSeconds").default(0).notNull(),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
});

export type LessonVideo = typeof lessonVideos.$inferSelect;
export type InsertLessonVideo = typeof lessonVideos.$inferInsert;

// ─── Lesson Ebooks ────────────────────────────────────────────────────────────
// Suporta múltiplos arquivos por aula (order define a sequência)
export const lessonEbooks = mysqlTable("lesson_ebooks", {
  id: int("id").autoincrement().primaryKey(),
  lessonId: int("lessonId").notNull(),
  fileUrl: varchar("fileUrl", { length: 512 }).notNull(), // URL S3 do PDF original
  fileName: varchar("fileName", { length: 256 }).notNull(), // nome exibido ao aluno
  allowDownload: boolean("allowDownload").default(false).notNull(), // se true → gera PDF com marca d'água
  order: int("order").default(1).notNull(), // ordem dentro da aula (vários PDFs)
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
}, (t) => [
  index("idx_lesson_ebooks_lesson").on(t.lessonId, t.order),
]);

export type LessonEbook = typeof lessonEbooks.$inferSelect;
export type InsertLessonEbook = typeof lessonEbooks.$inferInsert;

// ─── Lesson Questions Config ──────────────────────────────────────────────────
// Define os filtros do banco de questões para etapas do tipo "questions"
export const lessonQuestionsConfig = mysqlTable("lesson_questions_config", {
  id: int("id").autoincrement().primaryKey(),
  lessonId: int("lessonId").notNull().unique(), // 1:1 com courseLessons
  // Filtros aplicados ao banco: { disciplineId?, subjectId?, topicId?, difficulty?, banca?, year? }
  filtersJson: json("filtersJson").notNull(),
  minQuestions: int("minQuestions").default(10).notNull(),  // nº de questões exibidas
  minPassingScore: int("minPassingScore").default(70).notNull(), // % mínimo para avançar (0–100)
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
});

export type LessonQuestionsConfig = typeof lessonQuestionsConfig.$inferSelect;
export type InsertLessonQuestionsConfig = typeof lessonQuestionsConfig.$inferInsert;

// ─── Course Purchases ─────────────────────────────────────────────────────────
// Registro de compra individual de curso (independente da assinatura)
export const coursePurchases = mysqlTable("course_purchases", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("userId").notNull(),
  courseId: int("courseId").notNull(),
  pricePaid: decimal("pricePaid", { precision: 8, scale: 2 }).notNull(), // preço no momento da compra
  status: mysqlEnum("status", ["pending", "active", "refunded", "cancelled"]).default("pending").notNull(),
  transactionId: varchar("transactionId", { length: 128 }), // ID da transação MP
  purchasedAt: timestamp("purchasedAt").defaultNow().notNull(),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
}, (t) => [
  index("idx_course_purchases_user_course").on(t.userId, t.courseId),
  index("idx_course_purchases_status").on(t.status),
  index("idx_course_purchases_transaction").on(t.transactionId),
]);

export type CoursePurchase = typeof coursePurchases.$inferSelect;
export type InsertCoursePurchase = typeof coursePurchases.$inferInsert;

// ─── Course Progress ──────────────────────────────────────────────────────────
// Progresso do aluno por etapa (lesson). Uma linha por (userId, lessonId).
export const courseProgress = mysqlTable("course_progress", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("userId").notNull(),
  courseId: int("courseId").notNull(), // denormalizado
  lessonId: int("lessonId").notNull(),
  status: mysqlEnum("status", ["not_started", "in_progress", "completed"]).default("not_started").notNull(),
  watchedPercent: int("watchedPercent").default(0).notNull(), // apenas para type=video (0–100)
  completedAt: timestamp("completedAt"),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
}, (t) => [
  index("idx_course_progress_user_lesson").on(t.userId, t.lessonId),
  index("idx_course_progress_user_course").on(t.userId, t.courseId),
]);

export type CourseProgress = typeof courseProgress.$inferSelect;
export type InsertCourseProgress = typeof courseProgress.$inferInsert;

// ─── Lesson Question Attempts ─────────────────────────────────────────────────
// Tentativas de quiz em etapas do tipo "questions"
export const lessonQuestionAttempts = mysqlTable("lesson_question_attempts", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("userId").notNull(),
  lessonId: int("lessonId").notNull(),
  courseId: int("courseId").notNull(), // denormalizado
  questionsJson: json("questionsJson").notNull(),  // IDs das questões usadas
  answersJson: json("answersJson").notNull(),       // { questionId: selectedOption }
  totalQuestions: int("totalQuestions").notNull(),
  correctAnswers: int("correctAnswers").default(0).notNull(),
  score: int("score").default(0).notNull(), // percentual 0–100
  passed: boolean("passed").default(false).notNull(),
  startedAt: timestamp("startedAt").defaultNow().notNull(),
  finishedAt: timestamp("finishedAt"),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
}, (t) => [
  index("idx_lqa_user_lesson").on(t.userId, t.lessonId),
]);

export type LessonQuestionAttempt = typeof lessonQuestionAttempts.$inferSelect;
export type InsertLessonQuestionAttempt = typeof lessonQuestionAttempts.$inferInsert;

// ─── Course Certificates ──────────────────────────────────────────────────────
export const courseCertificates = mysqlTable("course_certificates", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("userId").notNull(),
  courseId: int("courseId").notNull(),
  validationCode: varchar("validationCode", { length: 64 }).notNull().unique(), // UUID para verificação pública
  issuedAt: timestamp("issuedAt").defaultNow().notNull(),
  pdfUrl: varchar("pdfUrl", { length: 512 }), // URL S3 do certificado gerado
  hash: varchar("hash", { length: 64 }),       // SHA-256 do conteúdo
  createdAt: timestamp("createdAt").defaultNow().notNull(),
}, (t) => [
  index("idx_certificates_user_course").on(t.userId, t.courseId),
  index("idx_certificates_validation_code").on(t.validationCode),
]);

export type CourseCertificate = typeof courseCertificates.$inferSelect;
export type InsertCourseCertificate = typeof courseCertificates.$inferInsert;

// ─── Ebook Download Logs ──────────────────────────────────────────────────────
// Auditoria anti-pirataria: cada download de ebook gera um registro imutável
export const ebookDownloadLogs = mysqlTable("ebook_download_logs", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("userId").notNull(),
  lessonEbookId: int("lessonEbookId").notNull(),
  courseId: int("courseId").notNull(), // denormalizado
  ipAddress: varchar("ipAddress", { length: 64 }).notNull(),
  userAgent: text("userAgent"),
  downloadHash: varchar("downloadHash", { length: 64 }).notNull().unique(), // ID único por evento
  downloadedAt: timestamp("downloadedAt").defaultNow().notNull(),
}, (t) => [
  index("idx_ebook_logs_user").on(t.userId),
  index("idx_ebook_logs_ebook").on(t.lessonEbookId),
]);

export type EbookDownloadLog = typeof ebookDownloadLogs.$inferSelect;
export type InsertEbookDownloadLog = typeof ebookDownloadLogs.$inferInsert;

// ─── Blog Posts ───────────────────────────────────────────────────────────────
export const blogPosts = mysqlTable("blog_posts", {
  id: int("id").autoincrement().primaryKey(),
  slug: varchar("slug", { length: 128 }).notNull().unique(),
  title: varchar("title", { length: 256 }).notNull(),
  body: text("body").notNull(),
  author: varchar("author", { length: 256 }).default("Equipe VetRank").notNull(),
  summary: text("summary").notNull(),
  category: varchar("category", { length: 64 }).default("scientific").notNull(),
  coverImageUrl: varchar("coverImageUrl", { length: 512 }),
  active: boolean("active").default(true).notNull(),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
}, (t) => [
  index("idx_blog_posts_slug").on(t.slug),
  index("idx_blog_posts_active_created").on(t.active, t.createdAt),
]);

export type BlogPost = typeof blogPosts.$inferSelect;
export type InsertBlogPost = typeof blogPosts.$inferInsert;

// ─── Content Requests ─────────────────────────────────────────────────────────
export const contentRequests = mysqlTable("content_requests", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("userId"),
  email: varchar("email", { length: 256 }),
  name: varchar("name", { length: 256 }),
  requestType: mysqlEnum("requestType", ["multiple_choice", "discursive", "both"]).notNull(),
  disciplineId: int("disciplineId").notNull(),
  subjectId: int("subjectId").notNull(),
  topicId: int("topicId"),
  subjectTag: varchar("subjectTag", { length: 256 }),
  notes: text("notes"),
  status: mysqlEnum("status", ["pending", "in_progress", "completed", "rejected"]).default("pending").notNull(),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
}, (t) => [
  index("idx_content_req_user").on(t.userId),
  index("idx_content_req_status").on(t.status),
]);

export type ContentRequest = typeof contentRequests.$inferSelect;
export type InsertContentRequest = typeof contentRequests.$inferInsert;



// ─── Concursos (Radar de Concursos de Veterinária) ───────────────────────────
// Só FATOS extraídos da notícia de origem (órgão, datas, vagas, salário, banca) + resumo próprio.
// Sempre exibido com link para a fonte e para o edital/órgão oficial.
export const concursos = mysqlTable("concursos", {
  id: int("id").autoincrement().primaryKey(),
  slug: varchar("slug", { length: 160 }).notNull().unique(),
  sourceUrl: varchar("sourceUrl", { length: 512 }).notNull().unique(),
  sourceName: varchar("sourceName", { length: 64 }).default("PCI Concursos").notNull(),
  orgao: varchar("orgao", { length: 256 }).notNull(),
  uf: varchar("uf", { length: 2 }),
  cidade: varchar("cidade", { length: 128 }),
  cargos: json("cargos"),                                   // [{ nome, vagas, salario, cargaHoraria }]
  vagasVet: varchar("vagasVet", { length: 64 }),            // "3 vagas" | "CR" | "1 vaga + CR"
  salarioMax: decimal("salarioMax", { precision: 10, scale: 2 }),
  inscricoesInicio: timestamp("inscricoesInicio"),
  inscricoesFim: timestamp("inscricoesFim"),
  taxa: varchar("taxa", { length: 128 }),
  banca: varchar("banca", { length: 128 }),
  bancaUrl: varchar("bancaUrl", { length: 512 }),
  orgaoUrl: varchar("orgaoUrl", { length: 512 }),
  editalNumero: varchar("editalNumero", { length: 64 }),
  escolaridade: varchar("escolaridade", { length: 64 }),
  etapas: varchar("etapas", { length: 256 }),               // "objetiva, discursiva, títulos"
  resumo: text("resumo"),                                   // texto PRÓPRIO, gerado a partir dos fatos
  publishedAt: timestamp("publishedAt"),
  active: boolean("active").default(true).notNull(),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
  updatedAt: timestamp("updatedAt").defaultNow().onUpdateNow().notNull(),
}, (t) => [
  index("idx_concursos_fim").on(t.active, t.inscricoesFim),
  index("idx_concursos_uf").on(t.uf),
]);

export type Concurso = typeof concursos.$inferSelect;
export type InsertConcurso = typeof concursos.$inferInsert;

// ─── Flashcards (repetição espaçada — SM-2) ──────────────────────────────────
// Cards são compartilhados (biblioteca). sourceQuestionId = card derivado de uma questão (gerado 1x, serve a todos).
export const flashcards = mysqlTable("flashcards", {
  id: int("id").autoincrement().primaryKey(),
  disciplineId: int("disciplineId").notNull(),
  subjectId: int("subjectId"),
  topicId: int("topicId"),
  front: text("front").notNull(),
  back: text("back").notNull(),
  sourceQuestionId: int("sourceQuestionId"),
  source: mysqlEnum("source", ["question", "import", "manual"]).default("question").notNull(),
  active: boolean("active").default(true).notNull(),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
}, (t) => [
  index("idx_fc_subject").on(t.active, t.subjectId),
  index("idx_fc_discipline").on(t.active, t.disciplineId),
  index("idx_fc_source_q").on(t.sourceQuestionId),
]);
export type Flashcard = typeof flashcards.$inferSelect;

export const flashcardReviews = mysqlTable("flashcard_reviews", {
  id: int("id").autoincrement().primaryKey(),
  userId: int("userId").notNull(),
  flashcardId: int("flashcardId").notNull(),
  ease: float("ease").default(2.5).notNull(),
  intervalDays: float("intervalDays").default(0).notNull(),
  repetitions: int("repetitions").default(0).notNull(),
  lapses: int("lapses").default(0).notNull(),
  dueAt: timestamp("dueAt").defaultNow().notNull(),
  lastReviewedAt: timestamp("lastReviewedAt"),
  createdAt: timestamp("createdAt").defaultNow().notNull(),
}, (t) => [
  index("idx_fcr_user_due").on(t.userId, t.dueAt),
  index("idx_fcr_user_card").on(t.userId, t.flashcardId),
]);
export type FlashcardReview = typeof flashcardReviews.$inferSelect;
