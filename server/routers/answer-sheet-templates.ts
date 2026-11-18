import { protectedProcedure, router } from "../_core/trpc";
import { z } from "zod";
import { TRPCError } from "@trpc/server";
import {
  getAnswerSheetTemplates, getAnswerSheetTemplateById,
  createAnswerSheetTemplate, updateAnswerSheetTemplate,
  publishAnswerSheetTemplate, deleteAnswerSheetTemplate,
} from "../db";

export const answerSheetTemplatesRouter = router({
  list: protectedProcedure.query(async ({ ctx }) => {
    return await getAnswerSheetTemplates(ctx.user.id);
  }),

  get: protectedProcedure
    .input(z.object({ id: z.number() }))
    .query(async ({ ctx, input }) => {
      const tpl = await getAnswerSheetTemplateById(input.id);
      if (!tpl || tpl.userId !== ctx.user.id) {
        throw new TRPCError({ code: "NOT_FOUND" });
      }
      return { ...tpl, config: JSON.parse(tpl.config) };
    }),

  create: protectedProcedure
    .input(z.object({
      name: z.string().min(1).max(255),
      config: z.string(), // JSON string
    }))
    .mutation(async ({ ctx, input }) => {
      await createAnswerSheetTemplate({
        userId: ctx.user.id,
        name: input.name,
        config: input.config,
        published: "false",
      });
      return { success: true };
    }),

  update: protectedProcedure
    .input(z.object({
      id: z.number(),
      name: z.string().min(1).max(255).optional(),
      config: z.string().optional(),
    }))
    .mutation(async ({ ctx, input }) => {
      const { id, ...data } = input;
      await updateAnswerSheetTemplate(id, ctx.user.id, data as any);
      return { success: true };
    }),

  publish: protectedProcedure
    .input(z.object({ id: z.number() }))
    .mutation(async ({ ctx, input }) => {
      await publishAnswerSheetTemplate(input.id, ctx.user.id);
      return { success: true };
    }),

  delete: protectedProcedure
    .input(z.object({ id: z.number() }))
    .mutation(async ({ ctx, input }) => {
      await deleteAnswerSheetTemplate(input.id, ctx.user.id);
      return { success: true };
    }),
});
