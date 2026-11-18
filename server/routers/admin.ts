import { TRPCError } from "@trpc/server";
import { z } from "zod";
import { MercadoPagoConfig, Payment as MPPayment } from "mercadopago";
import {
  createDiscipline,
  createSubject,
  getAdminStats,
  getAllDisciplines,
  getAllSubjects,
  seedBadges,
  seedDisciplines,
  seedShopItems,
  updateDiscipline,
  updateSubject,
} from "../db";
import { getDb } from "../db";
import { users, payments, mpWebhookEvents, trails, trailModules, questions, subjects, disciplines, topics } from "../../drizzle/schema";
import { desc, like, or, eq, and, isNotNull, isNull, sql, inArray, asc } from "drizzle-orm";
import { protectedProcedure, router } from "../_core/trpc";

const adminProcedure = protectedProcedure.use(({ ctx, next }) => {
  if (ctx.user.role !== "admin") throw new TRPCError({ code: "FORBIDDEN" });
  return next({ ctx });
});

export const adminRouter = router({
  stats: adminProcedure.query(() => getAdminStats()),

  seed: adminProcedure.mutation(async () => {
    await seedDisciplines();
    await seedBadges();
    await seedShopItems();
    return { success: true };
  }),

  disciplines: adminProcedure.query(() => getAllDisciplines()),

  createDiscipline: adminProcedure
    .input(
      z.object({
        slug: z.string().min(2),
        namePt: z.string().min(2),
        nameEn: z.string().min(2),
        icon: z.string().optional(),
        color: z.string().optional(),
      })
    )
    .mutation(async ({ input }) => {
      const id = await createDiscipline(input);
      return { id };
    }),

  updateDiscipline: adminProcedure
    .input(
      z.object({
        id: z.number(),
        namePt: z.string().optional(),
        nameEn: z.string().optional(),
        icon: z.string().optional(),
        color: z.string().optional(),
        active: z.boolean().optional(),
      })
    )
    .mutation(async ({ input }) => {
      const { id, ...data } = input;
      await updateDiscipline(id, data as any);
      return { success: true };
    }),

  subjects: adminProcedure.query(() => getAllSubjects()),

  createSubject: adminProcedure
    .input(
      z.object({
        disciplineId: z.number(),
        slug: z.string().min(2),
        namePt: z.string().min(2),
        nameEn: z.string().min(2),
      })
    )
    .mutation(async ({ input }) => {
      const id = await createSubject(input);
      return { id };
    }),

  updateSubject: adminProcedure
    .input(
      z.object({
        id: z.number(),
        namePt: z.string().optional(),
        nameEn: z.string().optional(),
        active: z.boolean().optional(),
      })
    )
    .mutation(async ({ input }) => {
      const { id, ...data } = input;
      await updateSubject(id, data as any);
      return { success: true };
    }),

  // ── Customer Management ──────────────────────────────────────────────────
  customers: adminProcedure
    .input(
      z.object({
        page: z.number().default(1),
        limit: z.number().default(50),
        search: z.string().optional(),
        plan: z.enum(["free", "trial", "premium", "all"]).default("all"),
      })
    )
    .query(async ({ input }) => {
      const db = await getDb();
      if (!db) return { users: [], total: 0 };
      const offset = (input.page - 1) * input.limit;

      let query = db
        .select({
          id: users.id,
          name: users.name,
          email: users.email,
          role: users.role,
          plan: users.plan,
          xp: users.xp,
          level: users.level,
          streak: users.streak,
          totalExams: users.totalExams,
          totalQuestions: users.totalQuestions,
          totalCorrect: users.totalCorrect,
          trialEndsAt: users.trialEndsAt,
          premiumEndsAt: users.premiumEndsAt,
          createdAt: users.createdAt,
          lastSignedIn: users.lastSignedIn,
          lgpdConsentAt: users.lgpdConsentAt,
          cpf: users.cpf,
          phone: users.phone,
          avatarUrl: users.avatarUrl,
        })
        .from(users)
        .$dynamic();

      if (input.search) {
        query = query.where(
          or(
            like(users.name, `%${input.search}%`),
            like(users.email, `%${input.search}%`)
          )
        );
      }
      if (input.plan !== "all") {
        query = query.where(eq(users.plan, input.plan as any));
      }

      const allUsers = await query.orderBy(desc(users.createdAt));
      const total = allUsers.length;
      const paginated = allUsers.slice(offset, offset + input.limit);

      return { users: paginated, total };
    }),

  customerDetail: adminProcedure
    .input(z.object({ userId: z.number() }))
    .query(async ({ input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "NOT_FOUND" });
      const result = await db.select().from(users).where(eq(users.id, input.userId)).limit(1);
      if (!result[0]) throw new TRPCError({ code: "NOT_FOUND" });
      // Get exam stats
      const conn = db as any;
      const examStats = await conn.execute(
        `SELECT COUNT(*) as totalExams, COALESCE(SUM(correctAnswers),0) as totalCorrect, COALESCE(SUM(totalQuestions),0) as totalQ FROM exams WHERE userId = ? AND status = 'completed'`,
        [input.userId]
      );
      const stats = examStats[0]?.[0] || { totalExams: 0, totalCorrect: 0, totalQ: 0 };
      const accuracy = stats.totalQ > 0 ? Math.round((stats.totalCorrect / stats.totalQ) * 100) : 0;
      return {
        ...result[0],
        totalExams: Number(stats.totalExams),
        accuracy,
      };
    }),

  updateCustomerPlan: adminProcedure
    .input(
      z.object({
        userId: z.number(),
        plan: z.enum(["free", "trial", "premium"]),
        role: z.enum(["user", "teacher", "coordinator", "superuser", "admin"]).optional(),
      })
    )
    .mutation(async ({ input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
      const updateData: Record<string, unknown> = { plan: input.plan };
      if (input.role) updateData.role = input.role;
      if (input.plan === "premium") {
        updateData.premiumStartedAt = new Date();
        updateData.premiumEndsAt = new Date(Date.now() + 365 * 24 * 60 * 60 * 1000);
      }
      await db.update(users).set(updateData as any).where(eq(users.id, input.userId));
      return { success: true };
    }),

  // ── Financeiro: listagem de pagamentos ─────────────────────────────────────
  listPayments: adminProcedure
    .input(z.object({
      page: z.number().default(1),
      limit: z.number().default(50),
      status: z.enum(["all", "approved", "rejected", "pending", "cancelled", "refunded"]).default("all"),
      planType: z.enum(["all", "monthly", "annual"]).default("all"),
    }))
    .query(async ({ input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
      const offset = (input.page - 1) * input.limit;

      const conditions = [];
      if (input.status !== "all") conditions.push(eq(payments.status, input.status as any));
      if (input.planType !== "all") conditions.push(eq(payments.planType, input.planType as any));
      const where = conditions.length > 0 ? and(...conditions) : undefined;

      const [rows, countRows] = await Promise.all([
        db.select({
          id: payments.id, userId: payments.userId, amount: payments.amount,
          currency: payments.currency, status: payments.status,
          paymentMethod: payments.paymentMethod, planType: payments.planType,
          externalId: payments.externalId, failureReason: payments.failureReason,
          notes: payments.notes, metadata: payments.metadata,
          createdAt: payments.createdAt, updatedAt: payments.updatedAt,
          userName: users.name, userEmail: users.email,
          cpf: users.cpf, phone: users.phone,
        })
          .from(payments)
          .leftJoin(users, eq(payments.userId, users.id))
          .where(where)
          .orderBy(desc(payments.createdAt))
          .limit(input.limit)
          .offset(offset),
        db.select({ count: sql<number>`count(*)` })
          .from(payments)
          .where(where),
      ]);

      return { rows, total: Number(countRows[0]?.count ?? 0) };
    }),

  // ── Financeiro: assinantes ativos ──────────────────────────────────────────
  listSubscribers: adminProcedure.query(async () => {
    const db = await getDb();
    if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
    const rows = await db.select({
      id: users.id, name: users.name, email: users.email,
      cpf: users.cpf, phone: users.phone,
      plan: users.plan, subscriptionPlan: users.subscriptionPlan,
      premiumEndsAt: users.premiumEndsAt, premiumStartedAt: users.premiumStartedAt,
      createdAt: users.createdAt, lastSignedIn: users.lastSignedIn,
    }).from(users)
      .where(eq(users.plan, "premium"))
      .orderBy(desc(users.premiumStartedAt));
    return rows;
  }),

  // ── Financeiro: eventos MP (reclamações, fraudes, chargebacks) ─────────────
  listMpEvents: adminProcedure
    .input(z.object({
      page: z.number().default(1),
      limit: z.number().default(50),
      type: z.string().optional(),
      resolved: z.boolean().optional(),
    }))
    .query(async ({ input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
      const offset = (input.page - 1) * input.limit;

      const conditions = [];
      if (input.type) conditions.push(eq(mpWebhookEvents.type, input.type));
      if (input.resolved !== undefined) conditions.push(eq(mpWebhookEvents.resolved, input.resolved));
      const where = conditions.length > 0 ? and(...conditions) : undefined;

      const rows = await db.select({
        id: mpWebhookEvents.id,
        type: mpWebhookEvents.type,
        action: mpWebhookEvents.action,
        status: mpWebhookEvents.status,
        externalId: mpWebhookEvents.externalId,
        amount: mpWebhookEvents.amount,
        planType: mpWebhookEvents.planType,
        resolved: mpWebhookEvents.resolved,
        adminNote: mpWebhookEvents.adminNote,
        raw: mpWebhookEvents.raw,
        createdAt: mpWebhookEvents.createdAt,
        userName: users.name,
        userEmail: users.email,
      })
        .from(mpWebhookEvents)
        .leftJoin(users, eq(mpWebhookEvents.userId, users.id))
        .where(where)
        .orderBy(desc(mpWebhookEvents.createdAt))
        .limit(input.limit)
        .offset(offset);

      return { rows };
    }),

  // ── Financeiro: resolver evento ────────────────────────────────────────────
  resolveMpEvent: adminProcedure
    .input(z.object({ id: z.number(), note: z.string().optional() }))
    .mutation(async ({ input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
      await db.update(mpWebhookEvents)
        .set({ resolved: true, adminNote: input.note ?? null })
        .where(eq(mpWebhookEvents.id, input.id));
      return { success: true };
    }),

  // ── Financeiro: resumo financeiro ──────────────────────────────────────────
  financialSummary: adminProcedure.query(async () => {
    const db = await getDb();
    if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });

    const thirtyDaysAgo = new Date(Date.now() - 30 * 86400000);

    const [summary, alerts] = await Promise.all([
      db.select({
        totalApproved:  sql<number>`COUNT(CASE WHEN ${payments.status} = 'approved' THEN 1 END)`,
        totalRevenue:   sql<number>`COALESCE(SUM(CASE WHEN ${payments.status} = 'approved' THEN ${payments.amount} END), 0)`,
        monthlyCount:   sql<number>`COUNT(CASE WHEN ${payments.status} = 'approved' AND ${payments.planType} = 'monthly' THEN 1 END)`,
        annualCount:    sql<number>`COUNT(CASE WHEN ${payments.status} = 'approved' AND ${payments.planType} = 'annual' THEN 1 END)`,
        totalRejected:  sql<number>`COUNT(CASE WHEN ${payments.status} = 'rejected' THEN 1 END)`,
        totalCancelled: sql<number>`COUNT(CASE WHEN ${payments.status} = 'cancelled' THEN 1 END)`,
        last30Days:     sql<number>`COALESCE(SUM(CASE WHEN ${payments.status} = 'approved' AND ${payments.createdAt} >= ${thirtyDaysAgo} THEN ${payments.amount} END), 0)`,
      }).from(payments),
      db.select({
        openClaims:      sql<number>`COUNT(CASE WHEN ${mpWebhookEvents.type} = 'claim' AND ${mpWebhookEvents.resolved} = 0 THEN 1 END)`,
        openChargebacks: sql<number>`COUNT(CASE WHEN ${mpWebhookEvents.type} = 'chargebacks' AND ${mpWebhookEvents.resolved} = 0 THEN 1 END)`,
        openFraudAlerts: sql<number>`COUNT(CASE WHEN ${mpWebhookEvents.type} = 'fraud_alert' AND ${mpWebhookEvents.resolved} = 0 THEN 1 END)`,
      }).from(mpWebhookEvents),
    ]);

    return { ...summary[0], ...alerts[0] };
  }),

  // ── Dashboard overview ──────────────────────────────────────────────────
  dashboardOverview: adminProcedure.query(async () => {
    const db = await getDb();
    if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });

    const [[usersRow], [contentRow], [financeRow], [ticketsRow], [activityRows]] = await Promise.all([
      db.execute(sql`
        SELECT
          COUNT(*) as total,
          SUM(plan = 'premium') as premium,
          SUM(plan = 'trial') as trial,
          SUM(plan = 'free') as free,
          SUM(createdAt >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as newThisWeek,
          SUM(createdAt >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as newThisMonth,
          SUM(lastSignedIn >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as activeThisWeek
        FROM users
      `),
      db.execute(sql`
        SELECT
          COUNT(*) as totalQuestions,
          SUM(status = 'pending') as pendingValidation,
          SUM(status = 'approved') as approved,
          SUM(active = 1) as activeQuestions,
          (SELECT COUNT(*) FROM discursive_questions WHERE active = 1) as discursive
        FROM questions
      `),
      db.execute(sql`
        SELECT
          COALESCE(SUM(CASE WHEN status='approved' AND createdAt >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN amount END),0) as mrr,
          COALESCE(SUM(CASE WHEN status='approved' AND createdAt >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN amount END),0) as last7Days,
          COALESCE(SUM(CASE WHEN status='approved' THEN amount END),0) as totalRevenue,
          COUNT(CASE WHEN status='approved' AND createdAt >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as salesThisMonth
        FROM payments
      `),
      db.execute(sql`
        SELECT
          COUNT(*) as total,
          SUM(status='open') as open,
          SUM(status='in_progress') as inProgress,
          SUM(priority='urgent' AND status NOT IN ('resolved','closed')) as urgentOpen
        FROM support_tickets
      `),
      db.execute(sql`
        SELECT al.action, al.entityType, al.createdAt,
          u.name as userName, u.role as userRole
        FROM activity_log al
        LEFT JOIN users u ON al.userId = u.id
        ORDER BY al.id DESC LIMIT 8
      `),
    ]) as any[];

    return {
      users: (usersRow as any[])[0],
      content: (contentRow as any[])[0],
      finance: (financeRow as any[])[0],
      tickets: (ticketsRow as any[])[0],
      recentActivity: activityRows as any[],
    };
  }),

  // ── Activity log ────────────────────────────────────────────────────────
  activityLog: adminProcedure
    .input(z.object({ page: z.number().default(1), limit: z.number().default(50) }))
    .query(async ({ input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
      const offset = (input.page - 1) * input.limit;
      const [rows] = await db.execute(sql`
        SELECT al.*, u.name as userName, u.email as userEmail, u.role as userRole
        FROM activity_log al
        LEFT JOIN users u ON al.userId = u.id
        ORDER BY al.createdAt DESC
        LIMIT ${input.limit} OFFSET ${offset}
      `) as any;
      return rows as any[];
    }),

  // ── Global search ────────────────────────────────────────────────────────
  globalSearch: adminProcedure
    .input(z.object({ q: z.string().min(2) }))
    .query(async ({ input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
      const term = `%${input.q}%`;

      const [[foundUsers], [foundQuestions], [foundTickets]] = await Promise.all([
        db.execute(sql`SELECT id, name, email, role, plan, createdAt FROM users WHERE name LIKE ${term} OR email LIKE ${term} LIMIT 5`),
        db.execute(sql`SELECT id, textPt, difficulty, status, banca, year FROM questions WHERE textPt LIKE ${term} AND active = 1 LIMIT 5`),
        db.execute(sql`SELECT t.id, t.subject, t.status, t.category, u.name as userName FROM support_tickets t LEFT JOIN users u ON t.userId = u.id WHERE t.subject LIKE ${term} OR u.name LIKE ${term} LIMIT 5`),
      ]) as any[];

      return {
        users: foundUsers as any[],
        questions: foundQuestions as any[],
        tickets: foundTickets as any[],
      };
    }),

  // ── Trails management ────────────────────────────────────────────────────
  listTrails: adminProcedure.query(async () => {
    const db = await getDb();
    if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
    const [rows] = await db.execute(sql`
      SELECT t.*, d.namePt as disciplineName,
        (SELECT COUNT(*) FROM trail_modules tm WHERE tm.trailId = t.id) as moduleCount,
        (SELECT COUNT(*) FROM trail_enrollments te WHERE te.trailId = t.id) as enrollmentCount
      FROM trails t
      LEFT JOIN disciplines d ON t.disciplineId = d.id
      ORDER BY t.createdAt DESC
    `) as any;
    return rows as any[];
  }),

  listTrailModules: adminProcedure
    .input(z.object({ trailId: z.number() }))
    .query(async ({ input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
      const [rows] = await db.execute(sql`
        SELECT tm.*,
          (SELECT COUNT(*) FROM trail_module_questions tmq WHERE tmq.moduleId = tm.id) as questionCount
        FROM trail_modules tm
        WHERE tm.trailId = ${input.trailId}
        ORDER BY tm.\`order\`
      `) as any;
      return rows as any[];
    }),

  // ── Importação de trilha via JSON ────────────────────────────────────────────
  importTrail: adminProcedure
    .input(z.object({
      title: z.string().min(3),
      description: z.string().optional(),
      disciplineId: z.number().int().positive(),
      accessType: z.enum(["subscription", "purchase"]).default("subscription"),
      price: z.number().positive().optional(),
      imageUrl: z.string().url().optional(),
      totalHours: z.number().int().min(1).default(40),
      passingScore: z.number().int().min(50).max(100).default(70),
      finalExamQuestions: z.number().int().min(5).max(100).default(30),
      finalExamTimeSeconds: z.number().int().min(300).default(3600),
      modules: z.array(z.object({
        title: z.string().min(2),
        summary: z.string().optional(),
        difficulty: z.enum(["easy", "medium", "hard", "mixed"]).default("mixed"),
        questionCount: z.number().int().min(5).max(200).default(40),
        minPassRate: z.number().int().min(50).max(100).default(70),
        questionFilter: z.object({
          disciplineId: z.number().optional(),
          subjectIds: z.array(z.number()).optional(),
          difficulty: z.enum(["easy", "medium", "hard"]).optional(),
          author: z.string().optional(),
          year: z.number().optional(),
        }).optional(),
      })).min(1),
    }))
    .mutation(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
      const { modules, price, ...trailData } = input;
      const [trailResult] = await db.insert(trails).values({
        ...trailData,
        price: price != null ? String(price) : undefined,
        createdBy: ctx.user.id,
      }) as any;
      const trailId = trailResult.insertId;
      for (let i = 0; i < modules.length; i++) {
        await db.insert(trailModules).values({
          trailId,
          order: i + 1,
          title: modules[i].title,
          summary: modules[i].summary,
          difficulty: modules[i].difficulty,
          questionCount: modules[i].questionCount,
          minPassRate: modules[i].minPassRate,
          questionFilter: modules[i].questionFilter ?? null,
        });
      }
      return { trailId, moduleCount: modules.length };
    }),

  // ── Distribuição de questões por área ────────────────────────────────────────
  questionDistribution: adminProcedure.query(async () => {
    const db = await getDb();
    if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
    const [byDiscipline] = await db.execute(sql`
      SELECT d.id, d.namePt,
        COUNT(q.id) as total,
        SUM(CASE WHEN q.status = 'approved' AND q.active = 1 THEN 1 ELSE 0 END) as approved
      FROM disciplines d
      LEFT JOIN questions q ON q.disciplineId = d.id
      GROUP BY d.id, d.namePt
      ORDER BY total DESC
    `) as any;
    const [bySubject] = await db.execute(sql`
      SELECT s.id, s.namePt, s.disciplineId,
        COUNT(q.id) as total,
        SUM(CASE WHEN q.status = 'approved' AND q.active = 1 THEN 1 ELSE 0 END) as approved
      FROM subjects s
      LEFT JOIN questions q ON q.subjectId = s.id
      WHERE s.active = 1
      GROUP BY s.id, s.namePt, s.disciplineId
      ORDER BY s.disciplineId, total DESC
    `) as any;
    return {
      byDiscipline: (byDiscipline as any[]).map((r: any) => ({
        id: Number(r.id), namePt: r.namePt as string,
        total: Number(r.total), approved: Number(r.approved),
      })),
      bySubject: (bySubject as any[]).map((r: any) => ({
        id: Number(r.id), namePt: r.namePt as string,
        disciplineId: Number(r.disciplineId),
        total: Number(r.total), approved: Number(r.approved),
      })),
    };
  }),

  // ── Duplicatas ───────────────────────────────────────────────────────────────
  findDuplicates: adminProcedure.query(async () => {
    const db = await getDb();
    if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
    const [rows] = await db.execute(sql`
      SELECT
        q.textPt,
        COUNT(*) AS total,
        GROUP_CONCAT(q.id ORDER BY q.id SEPARATOR ',') AS ids,
        GROUP_CONCAT(q.id ORDER BY q.id SEPARATOR ',') AS idsRaw,
        MIN(q.id) AS keepId,
        MAX(q.createdAt) AS lastCreatedAt,
        d.namePt AS disciplineName
      FROM questions q
      LEFT JOIN disciplines d ON q.disciplineId = d.id
      WHERE q.active = 1
      GROUP BY q.textPt
      HAVING total > 1
      ORDER BY total DESC, lastCreatedAt DESC
      LIMIT 200
    `) as any;
    return (rows as any[]).map((r: any) => ({
      textPt: r.textPt as string,
      total: Number(r.total),
      keepId: Number(r.keepId),
      ids: (r.ids as string).split(",").map(Number),
      duplicateIds: (r.ids as string).split(",").map(Number).slice(1), // all except the oldest
      disciplineName: r.disciplineName as string | null,
      lastCreatedAt: r.lastCreatedAt as string,
    }));
  }),

  deleteDuplicates: adminProcedure
    .input(z.object({ ids: z.array(z.number()).min(1).max(500) }))
    .mutation(async ({ input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
      const idList = input.ids.join(",");
      await db.execute(sql`UPDATE questions SET active = 0 WHERE id IN (${sql.raw(idList)})`);
      return { deleted: input.ids.length };
    }),

  // ── MP: list recent approved payments and cross-reference with DB ────────────
  listRecentMPPayments: adminProcedure
    .input(z.object({ days: z.number().min(1).max(30).default(7) }))
    .query(async ({ input }) => {
      const token = process.env.MP_ACCESS_TOKEN;
      if (!token) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR", message: "MP_ACCESS_TOKEN não configurado" });
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });

      const client = new MercadoPagoConfig({ accessToken: token });
      const paymentClient = new MPPayment(client);
      const beginDate = new Date(Date.now() - input.days * 24 * 60 * 60 * 1000);

      let mpResults: any[] = [];
      try {
        const search = await paymentClient.search({
          options: {
            sort: "date_created", criteria: "desc", limit: 50, offset: 0,
            range: "date_created",
            begin_date: beginDate.toISOString(),
            end_date: new Date().toISOString(),
          } as any,
        });
        mpResults = (search as any).results ?? [];
      } catch (e: any) {
        throw new TRPCError({ code: "INTERNAL_SERVER_ERROR", message: `Erro ao buscar MP: ${e?.message}` });
      }

      const result = await Promise.all(mpResults.map(async (p: any) => {
        const [dbRec] = await db.select({ id: payments.id, status: payments.status, userId: payments.userId })
          .from(payments).where(eq(payments.externalId, String(p.id))).limit(1);
        let ref: any = null;
        try { ref = JSON.parse(p.external_reference ?? "{}"); } catch {}
        return {
          mpId: String(p.id),
          status: p.status,
          amount: p.transaction_amount,
          payerEmail: p.payer?.email,
          date: p.date_created,
          externalRef: p.external_reference,
          refUserId: ref?.userId ?? null,
          refPlan: ref?.plan ?? null,
          inDb: !!dbRec,
          dbStatus: dbRec?.status ?? null,
          dbUserId: dbRec?.userId ?? null,
        };
      }));

      return { payments: result };
    }),

  // ── MP: manually activate a payment by MP payment ID ─────────────────────────
  syncMPPayment: adminProcedure
    .input(z.object({ paymentId: z.string().min(1) }))
    .mutation(async ({ input }) => {
      const token = process.env.MP_ACCESS_TOKEN;
      if (!token) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR", message: "MP_ACCESS_TOKEN não configurado" });
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });

      const client = new MercadoPagoConfig({ accessToken: token });
      const paymentClient = new MPPayment(client);
      const payment = await paymentClient.get({ id: input.paymentId });
      if (!payment) throw new TRPCError({ code: "NOT_FOUND", message: "Pagamento não encontrado no MP" });

      const base = {
        mpId: input.paymentId,
        mpStatus: payment.status,
        amount: payment.transaction_amount,
        payerEmail: (payment.payer as any)?.email as string | undefined,
      };

      if (payment.status !== "approved") return { ...base, action: "skipped_not_approved" as const };

      // Check existing record
      const [existing] = await db.select({ id: payments.id, status: payments.status })
        .from(payments).where(eq(payments.externalId, input.paymentId)).limit(1);
      if (existing?.status === "approved") return { ...base, action: "already_processed" as const, alreadyInDb: true };

      // Resolve userId
      let ref: any = null;
      try { ref = JSON.parse((payment as any).external_reference ?? "{}"); } catch {}
      let userId: number | undefined = ref?.userId;
      if (!userId && base.payerEmail) {
        const [found] = await db.select({ id: users.id }).from(users)
          .where(eq(users.email, base.payerEmail)).limit(1);
        userId = found?.id;
      }
      if (!userId) return { ...base, action: "user_not_found" as const };

      // Activate premium
      const durationDays: number = ref?.durationDays ?? (ref?.plan === "monthly" ? 30 : 365);
      const now = new Date();
      const [cur] = await db.select({ premiumEndsAt: users.premiumEndsAt })
        .from(users).where(eq(users.id, userId)).limit(1);
      const baseDate = cur?.premiumEndsAt && cur.premiumEndsAt > now ? cur.premiumEndsAt : now;
      const premiumEndsAt = new Date(baseDate);
      premiumEndsAt.setDate(premiumEndsAt.getDate() + durationDays);
      await db.update(users).set({ plan: "premium", premiumStartedAt: now, premiumEndsAt })
        .where(eq(users.id, userId));

      if (existing) {
        await db.update(payments).set({ status: "approved" }).where(eq(payments.id, existing.id));
      } else {
        await db.insert(payments).values({
          userId, amount: payment.transaction_amount ?? 0, currency: "BRL",
          status: "approved", paymentMethod: payment.payment_type_id ?? undefined,
          planType: (ref?.plan ?? "annual") as "monthly" | "annual",
          externalId: input.paymentId,
          metadata: { mp_payment_id: input.paymentId, manual_sync: true },
        });
      }

      return { ...base, action: "activated" as const, userId, premiumEndsAt: premiumEndsAt.toISOString() };
    }),

  // ── Topics (3º nível: Grande Área → Assunto → Tópico) ───────────────────
  topics: adminProcedure
    .input(z.object({ subjectId: z.number().optional(), disciplineId: z.number().optional() }))
    .query(async ({ input }) => {
      const db = await getDb();
      const conditions: any[] = [];
      if (input.subjectId) conditions.push(eq(topics.subjectId, input.subjectId));
      if (input.disciplineId) conditions.push(eq(topics.disciplineId, input.disciplineId));
      const where = conditions.length > 0 ? and(...conditions) : undefined;
      return db!.select().from(topics).where(where).orderBy(asc(topics.namePt));
    }),

  createTopic: adminProcedure
    .input(z.object({
      subjectId: z.number(),
      disciplineId: z.number(),
      namePt: z.string().min(2),
      nameEn: z.string().min(2),
      slug: z.string().min(2),
    }))
    .mutation(async ({ input }) => {
      const db = await getDb();
      const [result] = await db!.insert(topics).values(input);
      return { id: (result as any).insertId };
    }),

  // ── Quality / Maintenance ────────────────────────────────────────────────
  questionsWithIssues: adminProcedure
    .input(z.object({
      issue: z.enum(["no_subject", "no_subject_tag", "all_issues", "all"]).default("no_subject"),
      disciplineId: z.number().optional(),
      currentSubjectId: z.number().nullable().optional(),
      subjectTagSearch: z.string().optional(),
      page: z.number().default(1),
      limit: z.number().default(50),
    }))
    .query(async ({ input }) => {
      const db = await getDb();
      const offset = (input.page - 1) * input.limit;

      const conditions: any[] = [];
      if (input.issue === "no_subject") conditions.push(isNull(questions.subjectId));
      if (input.issue === "no_subject_tag") conditions.push(isNull(questions.subjectTag));
      if (input.issue === "all_issues") conditions.push(or(isNull(questions.subjectId), isNull(questions.subjectTag))!);
      // "all" → sem filtro de issue, mostra tudo da disciplina
      if (input.disciplineId) conditions.push(eq(questions.disciplineId, input.disciplineId));
      if (input.currentSubjectId === null) conditions.push(isNull(questions.subjectId));
      else if (input.currentSubjectId !== undefined) conditions.push(eq(questions.subjectId, input.currentSubjectId));
      if (input.subjectTagSearch) conditions.push(like(questions.subjectTag, `%${input.subjectTagSearch}%`));

      const where = conditions.length > 0 ? and(...conditions) : undefined;

      const [rows, countRow] = await Promise.all([
        db!
          .select({
            id: questions.id,
            textPt: questions.textPt,
            disciplineId: questions.disciplineId,
            disciplineName: disciplines.namePt,
            subjectId: questions.subjectId,
            subjectName: subjects.namePt,
            subjectTag: questions.subjectTag,
            difficulty: questions.difficulty,
            createdAt: questions.createdAt,
          })
          .from(questions)
          .leftJoin(disciplines, eq(questions.disciplineId, disciplines.id))
          .leftJoin(subjects, eq(questions.subjectId, subjects.id))
          .where(where)
          .orderBy(asc(questions.disciplineId), asc(questions.id))
          .limit(input.limit)
          .offset(offset),
        db!
          .select({ count: sql<number>`count(*)` })
          .from(questions)
          .leftJoin(disciplines, eq(questions.disciplineId, disciplines.id))
          .where(where),
      ]);

      return { rows, total: Number(countRow[0]?.count ?? 0) };
    }),

  bulkPatchQuestions: adminProcedure
    .input(z.object({
      ids: z.array(z.number()).min(1).max(500),
      disciplineId: z.number().optional(),
      subjectId: z.number().nullable().optional(),
      topicId: z.number().nullable().optional(),
      subjectTag: z.string().nullable().optional(),
    }))
    .mutation(async ({ input }) => {
      const db = await getDb();
      const patch: Record<string, unknown> = {};
      if (input.disciplineId !== undefined) patch.disciplineId = input.disciplineId;
      if (input.subjectId !== undefined) patch.subjectId = input.subjectId;
      if (input.topicId !== undefined) patch.topicId = input.topicId;
      if (input.subjectTag !== undefined) patch.subjectTag = input.subjectTag;
      if (Object.keys(patch).length === 0) throw new TRPCError({ code: "BAD_REQUEST", message: "Nenhum campo para atualizar" });
      await db!.update(questions).set(patch as any).where(inArray(questions.id, input.ids));
      return { updated: input.ids.length };
    }),
});
