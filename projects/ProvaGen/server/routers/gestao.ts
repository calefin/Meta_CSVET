import { protectedProcedure, router } from "../_core/trpc";
import { z } from "zod";
import { listGestaoClasses, importGestaoClass } from "../_core/gestaoImport";

/**
 * Ponte de leitura com o módulo de gestão (Laravel, mesmo banco físico) —
 * permite importar a turma/roster de lá para gerar prova aqui. Ver
 * server/_core/gestaoImport.ts.
 */
export const gestaoRouter = router({
  listClasses: protectedProcedure.query(async ({ ctx }) => {
    return await listGestaoClasses(ctx.user.id);
  }),

  importClass: protectedProcedure
    .input(z.object({
      classGroupId: z.number(),
      classYear: z.number().min(2000).max(2100).default(new Date().getFullYear()),
    }))
    .mutation(async ({ ctx, input }) => {
      return await importGestaoClass(ctx.user.id, input.classGroupId, input.classYear);
    }),
});
