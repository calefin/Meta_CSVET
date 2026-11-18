import { z } from "zod";
import { publicProcedure, adminProcedure, router } from "../_core/trpc";
import { getDb } from "../db";
import { projectQuotes } from "../db";
import { eq, desc } from "drizzle-orm";
import { sendProjectQuoteNotification } from "../_core/email";

export const quotesRouter = router({
  // Público: Qualquer empresa pode solicitar orçamento
  create: publicProcedure
    .input(
      z.object({
        name: z.string().min(2, "Nome deve ter no mínimo 2 caracteres"),
        email: z.string().email("Formato de e-mail corporativo inválido"),
        phone: z.string().min(8, "Telefone deve conter o código de área e número"),
        company: z.string().min(2, "Nome da empresa/instituição é obrigatório"),
        role: z.string().min(2, "Cargo ou função é obrigatório"),
        projectType: z.string().min(1, "Tipo de serviço é obrigatório"),
        estimatedVolume: z.string().min(1, "Volume estimado é obrigatório"),
        timeframe: z.string().min(1, "Prazo estimado é obrigatório"),
        description: z.string().min(10, "Escreva uma breve descrição das necessidades do projeto"),
        features: z.array(z.string()).optional(),
      })
    )
    .mutation(async ({ input }) => {
      const db = await getDb();
      if (!db) throw new Error("Database not available");

      const result = await db.insert(projectQuotes).values({
        name: input.name,
        email: input.email,
        phone: input.phone,
        company: input.company,
        role: input.role,
        projectType: input.projectType,
        estimatedVolume: input.estimatedVolume,
        timeframe: input.timeframe,
        description: input.description,
        features: input.features ? JSON.stringify(input.features) : null,
        status: "pending",
      });

      const _r = result as any;
      const quoteId = _r?.insertId ?? _r?.[0]?.insertId ?? (_r?.lastInsertRowid != null ? Number(_r.lastInsertRowid) : 1);

      // Dispara envio do email de forma assíncrona
      sendProjectQuoteNotification({
        id: quoteId,
        name: input.name,
        email: input.email,
        phone: input.phone,
        company: input.company,
        role: input.role,
        projectType: input.projectType,
        estimatedVolume: input.estimatedVolume,
        timeframe: input.timeframe,
        description: input.description,
        features: input.features,
      }).catch(err => console.error("[Quote Email] Failed to send:", err));

      return {
        success: true,
        quoteId,
      };
    }),

  // Admin: Listar todas as solicitações de orçamento
  listAll: adminProcedure.query(async () => {
    const db = await getDb();
    if (!db) return [];

    return await db
      .select()
      .from(projectQuotes)
      .orderBy(desc(projectQuotes.createdAt));
  }),

  // Admin: Atualizar o status e observações de uma solicitação de orçamento
  update: adminProcedure
    .input(
      z.object({
        quoteId: z.number(),
        status: z.enum(["pending", "contacted", "closed", "declined"]),
        adminNotes: z.string().optional(),
      })
    )
    .mutation(async ({ input }) => {
      const db = await getDb();
      if (!db) throw new Error("Database not available");

      await db
        .update(projectQuotes)
        .set({
          status: input.status,
          adminNotes: input.adminNotes ?? null,
          updatedAt: new Date(),
        })
        .where(eq(projectQuotes.id, input.quoteId));

      return { success: true };
    }),
});
