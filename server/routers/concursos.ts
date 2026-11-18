import { z } from "zod";
import { protectedProcedure, publicProcedure, router } from "../_core/trpc";
import { TRPCError } from "@trpc/server";
import { getDb } from "../db";
import { concursos } from "../../drizzle/schema";
import { desc, eq } from "drizzle-orm";
import { buscarConcursoPorSlug, listarConcursos, syncConcursos } from "../concursos";

const adminProcedure = protectedProcedure.use(({ ctx, next }) => {
  if (!["admin", "superuser"].includes(ctx.user.role)) throw new TRPCError({ code: "FORBIDDEN" });
  return next({ ctx });
});

export const concursosRouter = router({
  list: publicProcedure
    .input(
      z.object({
        uf: z.string().length(2).optional(),
        area: z.enum(["todas", "veterinaria", "zootecnia"]).optional(),
        incluirEncerrados: z.boolean().optional(),
      }).optional()
    )
    .query(async ({ input }) => {
      const itens = await listarConcursos({
        uf: input?.uf,
        area: input?.area,
        incluirEncerrados: input?.incluirEncerrados ?? true,
      });
      const abertos = itens.filter(i => i.status === "aberto");
      const vaiAbrir = itens.filter(i => i.status === "vai_abrir");
      const vigentes = itens.filter(i => i.status !== "encerrado");
      const encerrados = itens.filter(i => i.status === "encerrado");
      const salarios = vigentes.map(i => Number(i.salarioMax) || 0).filter(Boolean);
      return {
        itens,
        resumo: {
          abertos: abertos.length,
          vaiAbrir: vaiAbrir.length,
          vigentes: vigentes.length,
          encerrados: encerrados.length,
          encerrandoEm7Dias: abertos.filter(i => (i.diasRestantes ?? 99) <= 7).length,
          maiorSalario: salarios.length ? Math.max(...salarios) : null,
          ufs: Array.from(new Set(itens.map(i => i.uf).filter(Boolean))).sort() as string[],
        },
      };
    }),

  bySlug: publicProcedure
    .input(z.object({ slug: z.string().max(160) }))
    .query(async ({ input }) => {
      const c = await buscarConcursoPorSlug(input.slug);
      if (!c) throw new TRPCError({ code: "NOT_FOUND" });
      return c;
    }),

  // ── Admin ────────────────────────────────────────────────────────────────
  adminList: adminProcedure.query(async () => {
    const db = await getDb();
    if (!db) return [];
    return db.select().from(concursos).orderBy(desc(concursos.createdAt)).limit(300);
  }),

  adminSetActive: adminProcedure
    .input(z.object({ id: z.number(), active: z.boolean() }))
    .mutation(async ({ input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
      await db.update(concursos).set({ active: input.active }).where(eq(concursos.id, input.id));
      return { success: true };
    }),

  adminSync: adminProcedure.mutation(async () => syncConcursos()),
});
