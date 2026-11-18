import { z } from "zod";
import { protectedProcedure, publicProcedure, router } from "../_core/trpc";
import { TRPCError } from "@trpc/server";
import { getDb } from "../db";
import { supportTickets, supportMessages, users } from "../../drizzle/schema";
import { eq, desc, and, or, like } from "drizzle-orm";
import { sendTicketCreated, sendTicketUpdated, sendTicketReply } from "../email";
import { getAdminEmails } from "../adminNotify";

const adminProcedure = protectedProcedure.use(({ ctx, next }) => {
  if (!["admin", "superuser"].includes(ctx.user.role))
    throw new TRPCError({ code: "FORBIDDEN" });
  return next({ ctx });
});

const CATEGORY_PT: Record<string, string> = {
  pagamento: "Pagamento / Assinatura",
  acesso: "Problema de Acesso",
  bug: "Bug / Erro no Sistema",
  conteudo: "Conteúdo / Questões",
  sugestao: "Sugestão",
  outros: "Outros",
};

const STATUS_PT: Record<string, string> = {
  open: "Aberto",
  in_progress: "Em andamento",
  waiting_user: "Aguardando usuário",
  resolved: "Resolvido",
  closed: "Fechado",
};

const PRIORITY_PT: Record<string, string> = {
  low: "Baixa", medium: "Média", high: "Alta", urgent: "Urgente",
};

export const ticketsRouter = router({
  // ── Criar ticket ──────────────────────────────────────────────────────────
  create: protectedProcedure
    .input(z.object({
      subject: z.string().min(5).max(256),
      category: z.enum(["pagamento", "acesso", "bug", "conteudo", "sugestao", "outros"]).default("outros"),
      priority: z.enum(["low", "medium", "high", "urgent"]).default("medium"),
      message: z.string().min(20),
    }))
    .mutation(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });

      const [result] = await db.insert(supportTickets).values({
        userId: ctx.user.id,
        subject: input.subject,
        category: input.category,
        priority: input.priority,
        status: "open",
      });
      const ticketId = (result as any).insertId as number;

      await db.insert(supportMessages).values({
        ticketId,
        authorId: ctx.user.id,
        authorType: "user",
        message: input.message,
      });

      // Notificações por email
      const userEmail = ctx.user.email;
      const userName = ctx.user.name ?? "Usuário";
      const categoryLabel = CATEGORY_PT[input.category] ?? input.category;

      await sendTicketCreated({
        ticketId,
        subject: input.subject,
        category: categoryLabel,
        message: input.message,
        userName,
        userEmail: userEmail ?? undefined,
        adminEmail: await getAdminEmails(),
      }).catch(e => console.error("[Ticket] Email error:", e));

      return { ticketId, success: true };
    }),

  // ── Listar tickets do usuário ─────────────────────────────────────────────
  myTickets: protectedProcedure.query(async ({ ctx }) => {
    const db = await getDb();
    if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
    return db.select().from(supportTickets)
      .where(eq(supportTickets.userId, ctx.user.id))
      .orderBy(desc(supportTickets.updatedAt));
  }),

  // ── Ver ticket + mensagens ─────────────────────────────────────────────────
  getTicket: protectedProcedure
    .input(z.object({ ticketId: z.number() }))
    .query(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });

      const [ticket] = await db.select().from(supportTickets)
        .where(eq(supportTickets.id, input.ticketId)).limit(1);

      if (!ticket) throw new TRPCError({ code: "NOT_FOUND" });

      // Só admin ou o próprio usuário
      const isAdmin = ["admin", "superuser"].includes(ctx.user.role);
      if (!isAdmin && ticket.userId !== ctx.user.id)
        throw new TRPCError({ code: "FORBIDDEN" });

      const messages = await db.select({
        id: supportMessages.id,
        ticketId: supportMessages.ticketId,
        authorId: supportMessages.authorId,
        authorType: supportMessages.authorType,
        message: supportMessages.message,
        createdAt: supportMessages.createdAt,
      }).from(supportMessages)
        .where(eq(supportMessages.ticketId, input.ticketId))
        .orderBy(supportMessages.createdAt);

      // Enriquecer com nome do autor
      const authorIds = Array.from(new Set(messages.map(m => m.authorId)));
      const authorRows = authorIds.length > 0
        ? await db.select({ id: users.id, name: users.name }).from(users)
          .where(or(...authorIds.map(id => eq(users.id, id))))
        : [];
      const authorMap = Object.fromEntries(authorRows.map(u => [u.id, u.name]));

      return {
        ticket,
        messages: messages.map(m => ({ ...m, authorName: authorMap[m.authorId] ?? "Usuário" })),
      };
    }),

  // ── Usuário responde ticket ───────────────────────────────────────────────
  addMessage: protectedProcedure
    .input(z.object({ ticketId: z.number(), message: z.string().min(5) }))
    .mutation(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });

      const [ticket] = await db.select().from(supportTickets)
        .where(eq(supportTickets.id, input.ticketId)).limit(1);
      if (!ticket) throw new TRPCError({ code: "NOT_FOUND" });

      const isAdmin = ["admin", "superuser"].includes(ctx.user.role);
      if (!isAdmin && ticket.userId !== ctx.user.id)
        throw new TRPCError({ code: "FORBIDDEN" });

      await db.insert(supportMessages).values({
        ticketId: input.ticketId,
        authorId: ctx.user.id,
        authorType: isAdmin ? "admin" : "user",
        message: input.message,
      });

      // Reabrir ticket se estava waiting_user e usuário respondeu
      if (!isAdmin && ticket.status === "waiting_user") {
        await db.update(supportTickets).set({ status: "open" })
          .where(eq(supportTickets.id, input.ticketId));
      }

      // Notificar admin se foi resposta do usuário
      if (!isAdmin) {
        await sendTicketReply({
          ticketId: input.ticketId,
          subject: ticket.subject,
          message: input.message,
          userName: ctx.user.name ?? "Usuário",
          adminEmail: await getAdminEmails(),
        }).catch(e => console.error("[Ticket] Reply email error:", e));
      }

      return { success: true };
    }),

  // ── ADMIN: listar todos os tickets ────────────────────────────────────────
  adminList: adminProcedure
    .input(z.object({
      page: z.number().default(1),
      limit: z.number().default(30),
      status: z.enum(["all", "open", "in_progress", "waiting_user", "resolved", "closed"]).default("open"),
      search: z.string().optional(),
    }))
    .query(async ({ input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
      const offset = (input.page - 1) * input.limit;

      const rows = await db.execute(`
        SELECT t.*, u.name as userName, u.email as userEmail, u.phone as userPhone,
          (SELECT COUNT(*) FROM support_messages sm WHERE sm.ticketId = t.id) as messageCount,
          (SELECT MAX(sm2.createdAt) FROM support_messages sm2 WHERE sm2.ticketId = t.id) as lastMessageAt
        FROM support_tickets t
        LEFT JOIN users u ON t.userId = u.id
        ${input.status !== "all" ? `WHERE t.status = '${input.status}'` : ""}
        ${input.search ? `${input.status !== "all" ? "AND" : "WHERE"} (u.name LIKE '%${input.search}%' OR u.email LIKE '%${input.search}%' OR t.subject LIKE '%${input.search}%')` : ""}
        ORDER BY
          CASE t.priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END,
          t.updatedAt DESC
        LIMIT ${input.limit} OFFSET ${offset}
      `) as any;

      return { rows: (rows[0] as any[]) ?? [] };
    }),

  // ── ADMIN: atualizar status e enviar resposta ─────────────────────────────
  adminUpdate: adminProcedure
    .input(z.object({
      ticketId: z.number(),
      status: z.enum(["open", "in_progress", "waiting_user", "resolved", "closed"]).optional(),
      priority: z.enum(["low", "medium", "high", "urgent"]).optional(),
      adminNote: z.string().optional(),
      message: z.string().optional(), // resposta visível ao usuário
    }))
    .mutation(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });

      const [ticket] = await db.select().from(supportTickets)
        .where(eq(supportTickets.id, input.ticketId)).limit(1);
      if (!ticket) throw new TRPCError({ code: "NOT_FOUND" });

      // Atualizar ticket
      const updateData: Record<string, unknown> = {};
      if (input.status) updateData.status = input.status;
      if (input.priority) updateData.priority = input.priority;
      if (input.adminNote !== undefined) updateData.adminNote = input.adminNote;
      if (input.status === "resolved" || input.status === "closed") {
        updateData.closedAt = new Date();
      }

      if (Object.keys(updateData).length > 0) {
        await db.update(supportTickets).set(updateData as any)
          .where(eq(supportTickets.id, input.ticketId));
      }

      // Adicionar mensagem visível ao usuário
      if (input.message) {
        await db.insert(supportMessages).values({
          ticketId: input.ticketId,
          authorId: ctx.user.id,
          authorType: "admin",
          message: input.message,
        });
      }

      // Notificar usuário se houve resposta ou mudança de status
      const [userData] = await db.select({ email: users.email, name: users.name })
        .from(users).where(eq(users.id, ticket.userId)).limit(1);

      const newStatus = input.status ?? ticket.status;

      if ((input.message || input.status) && userData?.email) {
        await sendTicketUpdated({
          ticketId: input.ticketId,
          subject: ticket.subject,
          status: STATUS_PT[newStatus] ?? newStatus,
          message: input.message,
          userName: userData.name ?? "Usuário",
          userEmail: userData.email,
          adminEmail: await getAdminEmails(),
        }).catch(e => console.error("[Ticket] Update email error:", e));
      }

      return { success: true };
    }),

  // ── Stats para admin ──────────────────────────────────────────────────────
  adminStats: adminProcedure.query(async () => {
    const db = await getDb();
    if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
    const [rows] = await db.execute(`
      SELECT
        COUNT(*) as total,
        SUM(status = 'open') as open_count,
        SUM(status = 'in_progress') as in_progress,
        SUM(status = 'waiting_user') as waiting_user,
        SUM(status = 'resolved') as resolved,
        SUM(priority = 'urgent' AND status NOT IN ('resolved','closed')) as urgent_open,
        SUM(priority = 'high' AND status NOT IN ('resolved','closed')) as high_open
      FROM support_tickets
    `) as any;
    return (rows as any[])[0] ?? {};
  }),
});
