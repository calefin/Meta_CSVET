import { protectedProcedure, router } from "../_core/trpc";
import { z } from "zod";
import { TRPCError } from "@trpc/server";
import {
  getClasses, getClassById, createClass, updateClass, archiveClass, deleteClass,
  getClassStudents, getClassStudentCount, getGradeBook,
} from "../db";

export const classesRouter = router({
  list: protectedProcedure
    .input(z.object({ includeArchived: z.boolean().default(false) }))
    .query(async ({ ctx, input }) => {
      const rows = await getClasses(ctx.user.id, input.includeArchived);
      const withCounts = await Promise.all(
        rows.map(async (c: any) => ({
          ...c,
          studentCount: await getClassStudentCount(c.id),
        }))
      );
      return withCounts;
    }),

  get: protectedProcedure
    .input(z.object({ id: z.number() }))
    .query(async ({ ctx, input }) => {
      const cls = await getClassById(input.id);
      if (!cls || cls.userId !== ctx.user.id) {
        throw new TRPCError({ code: "NOT_FOUND", message: "Class not found" });
      }
      return cls;
    }),

  create: protectedProcedure
    .input(z.object({
      name: z.string().min(1).max(255),
      institutionId: z.number().optional(),
      courseId: z.number().optional(),
      semester: z.enum(["1", "2"]),
      classYear: z.number().min(2000).max(2100),
    }))
    .mutation(async ({ ctx, input }) => {
      await createClass({ userId: ctx.user.id, ...input });
      return { success: true };
    }),

  update: protectedProcedure
    .input(z.object({
      id: z.number(),
      name: z.string().min(1).max(255).optional(),
      institutionId: z.number().optional().nullable(),
      courseId: z.number().optional().nullable(),
      semester: z.enum(["1", "2"]).optional(),
      classYear: z.number().min(2000).max(2100).optional(),
    }))
    .mutation(async ({ ctx, input }) => {
      const { id, ...data } = input;
      await updateClass(id, ctx.user.id, data as any);
      return { success: true };
    }),

  archive: protectedProcedure
    .input(z.object({ id: z.number() }))
    .mutation(async ({ ctx, input }) => {
      await archiveClass(input.id, ctx.user.id, true);
      return { success: true };
    }),

  unarchive: protectedProcedure
    .input(z.object({ id: z.number() }))
    .mutation(async ({ ctx, input }) => {
      await archiveClass(input.id, ctx.user.id, false);
      return { success: true };
    }),

  delete: protectedProcedure
    .input(z.object({ id: z.number() }))
    .mutation(async ({ ctx, input }) => {
      const cls = await getClassById(input.id);
      if (!cls || cls.userId !== ctx.user.id) {
        throw new TRPCError({ code: "NOT_FOUND", message: "Class not found" });
      }
      await deleteClass(input.id, ctx.user.id);
      return { success: true };
    }),

  getStudents: protectedProcedure
    .input(z.object({ classId: z.number() }))
    .query(async ({ ctx, input }) => {
      const cls = await getClassById(input.classId);
      if (!cls || cls.userId !== ctx.user.id) {
        throw new TRPCError({ code: "FORBIDDEN", message: "Access denied" });
      }
      return await getClassStudents(input.classId);
    }),

  getGradeBook: protectedProcedure
    .input(z.object({ classId: z.number() }))
    .query(async ({ ctx, input }) => {
      const cls = await getClassById(input.classId);
      if (!cls || cls.userId !== ctx.user.id) {
        throw new TRPCError({ code: "FORBIDDEN", message: "Access denied" });
      }
      return await getGradeBook(input.classId, ctx.user.id);
    }),
});
