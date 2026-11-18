import { protectedProcedure, router } from "../_core/trpc";
import { z } from "zod";
import { TRPCError } from "@trpc/server";
import {
  getInstitutions, getInstitutionById, createInstitution, updateInstitution, deleteInstitution,
  getDepartments, createDepartment, updateDepartment, deleteDepartment,
  getCourses, createCourse, updateCourse, deleteCourse,
} from "../db";

export const institutionsRouter = router({
  list: protectedProcedure.query(async ({ ctx }) => {
    return await getInstitutions(ctx.user.id);
  }),

  get: protectedProcedure
    .input(z.object({ id: z.number() }))
    .query(async ({ ctx, input }) => {
      const institution = await getInstitutionById(input.id);
      if (!institution || institution.userId !== ctx.user.id) {
        throw new TRPCError({ code: "NOT_FOUND", message: "Institution not found" });
      }
      return institution;
    }),

  create: protectedProcedure
    .input(z.object({
      name: z.string().min(1).max(255),
      type: z.enum(["university", "faculty", "school"]).default("university"),
      address: z.string().optional(),
    }))
    .mutation(async ({ ctx, input }) => {
      await createInstitution({ userId: ctx.user.id, ...input });
      return { success: true };
    }),

  update: protectedProcedure
    .input(z.object({
      id: z.number(),
      name: z.string().min(1).max(255).optional(),
      type: z.enum(["university", "faculty", "school"]).optional(),
      address: z.string().optional(),
    }))
    .mutation(async ({ ctx, input }) => {
      const { id, ...data } = input;
      await updateInstitution(id, ctx.user.id, data);
      return { success: true };
    }),

  delete: protectedProcedure
    .input(z.object({ id: z.number() }))
    .mutation(async ({ ctx, input }) => {
      await deleteInstitution(input.id, ctx.user.id);
      return { success: true };
    }),

  // ── Departments ──

  listDepartments: protectedProcedure
    .input(z.object({ institutionId: z.number() }))
    .query(async ({ input }) => {
      return await getDepartments(input.institutionId);
    }),

  createDepartment: protectedProcedure
    .input(z.object({
      institutionId: z.number(),
      name: z.string().min(1).max(255),
    }))
    .mutation(async ({ input }) => {
      await createDepartment(input);
      return { success: true };
    }),

  updateDepartment: protectedProcedure
    .input(z.object({
      id: z.number(),
      name: z.string().min(1).max(255),
    }))
    .mutation(async ({ input }) => {
      const { id, ...data } = input;
      await updateDepartment(id, data);
      return { success: true };
    }),

  deleteDepartment: protectedProcedure
    .input(z.object({ id: z.number() }))
    .mutation(async ({ input }) => {
      await deleteDepartment(input.id);
      return { success: true };
    }),

  // ── Courses ──

  listCourses: protectedProcedure
    .input(z.object({ institutionId: z.number() }))
    .query(async ({ input }) => {
      return await getCourses(input.institutionId);
    }),

  createCourse: protectedProcedure
    .input(z.object({
      institutionId: z.number(),
      departmentId: z.number().optional(),
      name: z.string().min(1).max(255),
    }))
    .mutation(async ({ input }) => {
      await createCourse(input);
      return { success: true };
    }),

  updateCourse: protectedProcedure
    .input(z.object({
      id: z.number(),
      name: z.string().min(1).max(255).optional(),
      departmentId: z.number().optional(),
    }))
    .mutation(async ({ input }) => {
      const { id, ...data } = input;
      await updateCourse(id, data);
      return { success: true };
    }),

  deleteCourse: protectedProcedure
    .input(z.object({ id: z.number() }))
    .mutation(async ({ input }) => {
      await deleteCourse(input.id);
      return { success: true };
    }),
});
