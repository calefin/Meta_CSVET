import { z } from "zod";
import { protectedProcedure, adminProcedure, router } from "../_core/trpc";
import { getDb } from "../db";
import { supportTickets, users } from "../db";
import { eq, desc } from "drizzle-orm";
import { sendSupportTicketNotification } from "../_core/email";

export const ticketsRouter = router({
  // Users create ticket
  create: protectedProcedure
    .input(
      z.object({
        title: z.string().min(3, "Título deve ter no mínimo 3 caracteres"),
        description: z.string().min(5, "Descrição deve ter no mínimo 5 caracteres"),
        priority: z.enum(["low", "medium", "high"]).default("medium"),
      })
    )
    .mutation(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new Error("Database not available");

      const result = await db.insert(supportTickets).values({
        userId: ctx.user.id,
        title: input.title,
        description: input.description,
        priority: input.priority,
        status: "open",
      });

      const _r = result as any;
      const ticketId = _r?.insertId ?? _r?.[0]?.insertId ?? (_r?.lastInsertRowid != null ? Number(_r.lastInsertRowid) : 1);

      // Dispara envio do email de forma assíncrona
      sendSupportTicketNotification({
        id: ticketId,
        title: input.title,
        description: input.description,
        priority: input.priority,
      }).catch(err => console.error("[Ticket Email] Failed to send:", err));

      return {
        success: true,
        ticketId,
      };
    }),

  // List my tickets (for normal user)
  listMyTickets: protectedProcedure.query(async ({ ctx }) => {
    const db = await getDb();
    if (!db) return [];

    return await db
      .select()
      .from(supportTickets)
      .where(eq(supportTickets.userId, ctx.user.id))
      .orderBy(desc(supportTickets.createdAt));
  }),

  // Admin list all tickets with users details
  listAllTickets: adminProcedure.query(async () => {
    const db = await getDb();
    if (!db) return [];

    // Simple join manually due to SQLite driver constraint
    const tickets = await db.select().from(supportTickets).orderBy(desc(supportTickets.createdAt));
    const allUsers = await db.select({ id: users.id, name: users.name, email: users.email }).from(users);

    return tickets.map((t: any) => ({
      ...t,
      user: allUsers.find((u: any) => u.id === t.userId) || null,
    }));
  }),

  // Admin respond to a ticket
  respond: adminProcedure
    .input(
      z.object({
        ticketId: z.number(),
        response: z.string().min(1, "A resposta não pode estar vazia"),
        status: z.enum(["open", "in_progress", "resolved", "closed"]).default("resolved"),
      })
    )
    .mutation(async ({ input }) => {
      const db = await getDb();
      if (!db) throw new Error("Database not available");

      await db
        .update(supportTickets)
        .set({
          adminResponse: input.response,
          status: input.status,
          respondedAt: new Date(),
          updatedAt: new Date(),
        })
        .where(eq(supportTickets.id, input.ticketId));

      return { success: true };
    }),

  // Admin update ticket status only
  updateStatus: adminProcedure
    .input(
      z.object({
        ticketId: z.number(),
        status: z.enum(["open", "in_progress", "resolved", "closed"]),
      })
    )
    .mutation(async ({ input }) => {
      const db = await getDb();
      if (!db) throw new Error("Database not available");

      await db
        .update(supportTickets)
        .set({
          status: input.status,
          updatedAt: new Date(),
        })
        .where(eq(supportTickets.id, input.ticketId));

      return { success: true };
    }),
});
