import { z } from "zod";
import { protectedProcedure, publicProcedure, router } from "../_core/trpc";
import { TRPCError } from "@trpc/server";
import { getDb } from "../db";
import { contentRequests, users, disciplines, subjects, topics } from "../../drizzle/schema";
import { eq, desc, and } from "drizzle-orm";

const adminProcedure = protectedProcedure.use(({ ctx, next }) => {
  if (!["admin", "superuser"].includes(ctx.user.role))
    throw new TRPCError({ code: "FORBIDDEN" });
  return next({ ctx });
});

export const contentRequestsRouter = router({
  // ── Criar pedido (Público/Inscritos) ───────────────────────────────────────
  create: publicProcedure
    .input(z.object({
      requestType: z.enum(["multiple_choice", "discursive", "both"]),
      disciplineId: z.number(),
      subjectId: z.number(),
      topicId: z.number().optional(),
      subjectTag: z.string().max(256).optional(),
      notes: z.string().max(2000).optional(),
      name: z.string().max(256).optional(),
      email: z.string().email().optional(),
    }))
    .mutation(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });

      const userId = ctx.user?.id || null;
      const email = ctx.user?.email || input.email || null;
      const name = ctx.user?.name || input.name || null;

      if (!email) {
        throw new TRPCError({
          code: "BAD_REQUEST",
          message: "Identificação necessária. Forneça um e-mail válido.",
        });
      }

      await db.insert(contentRequests).values({
        userId,
        email,
        name,
        requestType: input.requestType,
        disciplineId: input.disciplineId,
        subjectId: input.subjectId,
        topicId: input.topicId || null,
        subjectTag: input.subjectTag || null,
        notes: input.notes || null,
        status: "pending",
      });

      return { success: true };
    }),

  // ── ADMIN: Listar pedidos ──────────────────────────────────────────────────
  adminList: adminProcedure
    .input(z.object({
      page: z.number().default(1),
      limit: z.number().default(30),
      status: z.enum(["all", "pending", "in_progress", "completed", "rejected"]).default("all"),
    }))
    .query(async ({ input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });

      const offset = (input.page - 1) * input.limit;
      const conditions = [];

      if (input.status !== "all") {
        conditions.push(eq(contentRequests.status, input.status));
      }

      const whereClause = conditions.length > 0 ? and(...conditions) : undefined;

      // Query requests
      const rows = await db
        .select({
          id: contentRequests.id,
          userId: contentRequests.userId,
          email: contentRequests.email,
          name: contentRequests.name,
          requestType: contentRequests.requestType,
          disciplineId: contentRequests.disciplineId,
          subjectId: contentRequests.subjectId,
          topicId: contentRequests.topicId,
          subjectTag: contentRequests.subjectTag,
          notes: contentRequests.notes,
          status: contentRequests.status,
          createdAt: contentRequests.createdAt,
          // Joins for names
          userName: users.name,
          userEmail: users.email,
          disciplineName: disciplines.namePt,
          subjectName: subjects.namePt,
          topicName: topics.namePt,
        })
        .from(contentRequests)
        .leftJoin(users, eq(contentRequests.userId, users.id))
        .leftJoin(disciplines, eq(contentRequests.disciplineId, disciplines.id))
        .leftJoin(subjects, eq(contentRequests.subjectId, subjects.id))
        .leftJoin(topics, eq(contentRequests.topicId, topics.id))
        .where(whereClause)
        .orderBy(desc(contentRequests.createdAt))
        .limit(input.limit)
        .offset(offset);

      return rows;
    }),

  // ── ADMIN: Atualizar status do pedido ──────────────────────────────────────
  adminUpdateStatus: adminProcedure
    .input(z.object({
      id: z.number(),
      status: z.enum(["pending", "in_progress", "completed", "rejected"]),
    }))
    .mutation(async ({ input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });

      await db
        .update(contentRequests)
        .set({ status: input.status, updatedAt: new Date() })
        .where(eq(contentRequests.id, input.id));

      return { success: true };
    }),

  // ── Plano de conteúdo: ranking falta x procura ───────────────────────────
  plan: adminProcedure.query(async () => {
    const { computeContentPlan, TOPIC_TARGET, SUBJECT_TARGET, DISCURSIVE_TARGET } = await import("../contentPlan");
    const items = await computeContentPlan();
    return { items, metas: { assunto: TOPIC_TARGET, disciplinaSemAssuntos: SUBJECT_TARGET, discursivas: DISCURSIVE_TARGET } };
  }),

  // Exporta os itens escolhidos + enunciados existentes → arquivo de entrada do gerador local
  exportPlan: adminProcedure
    .input(z.object({ keys: z.array(z.string()).min(1).max(50) }))
    .mutation(async ({ input }) => {
      const { computeContentPlan, existingStems } = await import("../contentPlan");
      const all = await computeContentPlan();
      const chosen = all.filter(i => input.keys.includes(i.key));
      const itens = [];
      for (const it of chosen) {
        itens.push({ ...it, existentes: await existingStems(it.subjectId, it.topicId) });
      }
      return { versao: 1, geradoEm: new Date().toISOString(), itens };
    }),
});
