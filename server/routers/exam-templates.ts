import { z } from "zod";
import { protectedProcedure, router } from "../_core/trpc";
import { getDb } from "../db";
import { examTemplates, questions } from "../db";
import { eq, and } from "drizzle-orm";

const StructureItemSchema = z.object({
  difficulty: z.enum(["easy", "medium", "hard"]),
  count: z.number().min(1),
  subject: z.string().optional(),
});

const ExamTemplateSchema = z.object({
  name: z.string().min(1),
  description: z.string().optional(),
  discipline: z.string().optional(),
  structure: z.array(StructureItemSchema).min(1),
  shuffleMode: z.enum(["all", "questions", "items", "none"]).default("all"),
  numberOfVersions: z.number().min(1).default(1),
  totalPoints: z.string().optional(),
});

export const examTemplatesRouter = router({
  list: protectedProcedure.query(async ({ ctx }) => {
    const db = await getDb();
    if (!db) return [];
    const result = await db.select().from(examTemplates).where(eq(examTemplates.userId, ctx.user.id));
    return result.map((t: any) => ({
      ...t,
      structure: t.structure ? JSON.parse(t.structure) : [],
    }));
  }),

  get: protectedProcedure
    .input(z.object({ id: z.number() }))
    .query(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) return null;
      const result = await db
        .select()
        .from(examTemplates)
        .where(and(eq(examTemplates.id, input.id), eq(examTemplates.userId, ctx.user.id)))
        .limit(1);
      if (!result.length) return null;
      const t = result[0];
      return { ...t, structure: t.structure ? JSON.parse(t.structure) : [] };
    }),

  create: protectedProcedure
    .input(ExamTemplateSchema)
    .mutation(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new Error("Database not available");
      await db.insert(examTemplates).values({
        userId: ctx.user.id,
        name: input.name,
        description: input.description ?? null,
        discipline: input.discipline ?? null,
        structure: JSON.stringify(input.structure),
        shuffleMode: input.shuffleMode,
        numberOfVersions: input.numberOfVersions,
        totalPoints: input.totalPoints ?? null,
      });
      return { success: true };
    }),

  update: protectedProcedure
    .input(ExamTemplateSchema.extend({ id: z.number() }))
    .mutation(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new Error("Database not available");
      await db
        .update(examTemplates)
        .set({
          name: input.name,
          description: input.description ?? null,
          discipline: input.discipline ?? null,
          structure: JSON.stringify(input.structure),
          shuffleMode: input.shuffleMode,
          numberOfVersions: input.numberOfVersions,
          totalPoints: input.totalPoints ?? null,
          updatedAt: new Date(),
        })
        .where(and(eq(examTemplates.id, input.id), eq(examTemplates.userId, ctx.user.id)));
      return { success: true };
    }),

  delete: protectedProcedure
    .input(z.object({ id: z.number() }))
    .mutation(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new Error("Database not available");
      await db
        .delete(examTemplates)
        .where(and(eq(examTemplates.id, input.id), eq(examTemplates.userId, ctx.user.id)));
      return { success: true };
    }),

  /**
   * Preview: returns matching questions from the bank for this template structure
   */
  previewQuestions: protectedProcedure
    .input(z.object({ id: z.number() }))
    .query(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) return { matched: [], unmatched: [] };

      const tplResult = await db
        .select()
        .from(examTemplates)
        .where(and(eq(examTemplates.id, input.id), eq(examTemplates.userId, ctx.user.id)))
        .limit(1);

      if (!tplResult.length) return { matched: [], unmatched: [] };
      const tpl = tplResult[0];
      const structure: { difficulty: string; count: number; subject?: string }[] = JSON.parse(tpl.structure);

      const allQuestions: any[] = await db
        .select()
        .from(questions)
        .where(eq(questions.userId, ctx.user.id));

      const matched: any[] = [];
      const unmatched: typeof structure = [];

      for (const slot of structure) {
        const pool = allQuestions.filter(
          (q) =>
            q.difficulty === slot.difficulty &&
            (!tpl.discipline || q.discipline === tpl.discipline) &&
            (!slot.subject || q.subject === slot.subject)
        );
        if (pool.length >= slot.count) {
          matched.push(...pool.slice(0, slot.count));
        } else {
          matched.push(...pool);
          unmatched.push({ ...slot, count: slot.count - pool.length });
        }
      }

      return { matched, unmatched };
    }),
});
