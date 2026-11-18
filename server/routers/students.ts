import { protectedProcedure, publicProcedure, router } from "../_core/trpc";
import { z } from "zod";
import { TRPCError } from "@trpc/server";
import {
  getStudents, getStudentById, createStudent, updateStudent, deleteStudent, bulkDeleteStudents,
  assignStudentToClass, removeStudentFromClass, getStudentClasses, importStudents,
  upsertStudentGrade,
} from "../db";
import ExcelJS from "exceljs";
import { getDb } from "../db";
import { eq, and } from "drizzle-orm";

function parseCSV(csv: string): Record<string, string>[] {
  const lines = csv.trim().split("\n");
  if (lines.length < 2) return [];
  const headers = lines[0].split(",").map((h) => h.trim().replace(/^"|"$/g, ""));
  return lines.slice(1).map((line) => {
    const values = line.split(",").map((v) => v.trim().replace(/^"|"$/g, ""));
    return Object.fromEntries(headers.map((h, i) => [h, values[i] ?? ""]));
  });
}

function rowsToStudents(rows: Record<string, string>[]) {
  return rows.map((row) => {
    const firstName = row["firstName"] || row["first_name"] || row["Nome"] || row["nome"] || "";
    const lastName = row["lastName"] || row["last_name"] || row["Sobrenome"] || row["sobrenome"] || "";
    const studentId = row["studentId"] || row["student_id"] || row["Matrícula"] || row["matricula"] || row["id"] || undefined;
    const externalRef = row["externalRef"] || row["external_ref"] || row["Ref"] || row["ref"] || undefined;
    const email = row["email"] || row["Email"] || undefined;
    return { firstName, lastName, studentId, externalRef, email };
  }).filter((r) => r.firstName || r.lastName);
}

export const studentsRouter = router({
  list: protectedProcedure
    .input(z.object({
      classId: z.number().optional(),
      page: z.number().default(1),
      perPage: z.number().default(50),
    }))
    .query(async ({ ctx, input }) => {
      const all = await getStudents(ctx.user.id, input.classId);
      const studentsWithClasses = await Promise.all(
        all.map(async (s: any) => ({
          ...s,
          classes: await getStudentClasses(s.id),
        }))
      );
      const total = studentsWithClasses.length;
      const start = (input.page - 1) * input.perPage;
      return {
        students: studentsWithClasses.slice(start, start + input.perPage),
        total,
        page: input.page,
        perPage: input.perPage,
      };
    }),

  get: protectedProcedure
    .input(z.object({ id: z.number() }))
    .query(async ({ ctx, input }) => {
      const student = await getStudentById(input.id);
      if (!student || student.userId !== ctx.user.id) {
        throw new TRPCError({ code: "NOT_FOUND", message: "Student not found" });
      }
      return student;
    }),

  create: protectedProcedure
    .input(z.object({
      firstName: z.string().min(1).max(100),
      lastName: z.string().min(1).max(100),
      studentId: z.string().max(50).optional(),
      externalRef: z.string().max(100).optional(),
      email: z.string().email().optional(),
      classId: z.number().optional(),
    }))
    .mutation(async ({ ctx, input }) => {
      const { classId, ...data } = input;
      const result = await createStudent({ userId: ctx.user.id, ...data }) as any;
      const _r = result as any;
      const insertId = _r?.[0]?.insertId ?? _r?.insertId ?? (_r?.lastInsertRowid != null ? Number(_r.lastInsertRowid) : null);
      if (classId && insertId) {
        await assignStudentToClass(insertId, classId);
      }
      return { success: true };
    }),

  update: protectedProcedure
    .input(z.object({
      id: z.number(),
      firstName: z.string().min(1).max(100).optional(),
      lastName: z.string().min(1).max(100).optional(),
      studentId: z.string().max(50).optional().nullable(),
      externalRef: z.string().max(100).optional().nullable(),
      email: z.string().email().optional().nullable(),
    }))
    .mutation(async ({ ctx, input }) => {
      const { id, ...data } = input;
      await updateStudent(id, ctx.user.id, data as any);
      return { success: true };
    }),

  delete: protectedProcedure
    .input(z.object({ id: z.number() }))
    .mutation(async ({ ctx, input }) => {
      await deleteStudent(input.id, ctx.user.id);
      return { success: true };
    }),

  bulkDelete: protectedProcedure
    .input(z.object({ ids: z.array(z.number()) }))
    .mutation(async ({ ctx, input }) => {
      await bulkDeleteStudents(input.ids, ctx.user.id);
      return { success: true };
    }),

  assignToClass: protectedProcedure
    .input(z.object({ studentId: z.number(), classId: z.number() }))
    .mutation(async ({ input }) => {
      await assignStudentToClass(input.studentId, input.classId);
      return { success: true };
    }),

  removeFromClass: protectedProcedure
    .input(z.object({ studentId: z.number(), classId: z.number() }))
    .mutation(async ({ input }) => {
      await removeStudentFromClass(input.studentId, input.classId);
      return { success: true };
    }),

  importCSV: protectedProcedure
    .input(z.object({
      csvData: z.string(),
      classId: z.number().optional(),
    }))
    .mutation(async ({ ctx, input }) => {
      const rows = parseCSV(input.csvData);
      const students = rowsToStudents(rows);
      if (students.length === 0) {
        throw new TRPCError({ code: "BAD_REQUEST", message: "No valid rows found in CSV" });
      }
      await importStudents(ctx.user.id, students.map((s) => ({ ...s, classId: input.classId })));
      return { success: true, count: students.length };
    }),

  importXLSX: protectedProcedure
    .input(z.object({
      base64: z.string(),
      classId: z.number().optional(),
    }))
    .mutation(async ({ ctx, input }) => {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const buffer: any = Buffer.from(input.base64, "base64");
      const workbook = new ExcelJS.Workbook();
      await workbook.xlsx.load(buffer);
      const sheet = workbook.worksheets[0];
      if (!sheet) throw new TRPCError({ code: "BAD_REQUEST", message: "No worksheet found" });

      const rows: Record<string, string>[] = [];
      let headers: string[] = [];

      sheet.eachRow((row, idx) => {
        if (idx === 1) {
          headers = (row.values as (string | null)[]).slice(1).map((v) => String(v ?? ""));
        } else {
          const values = (row.values as (string | number | null)[]).slice(1);
          const obj: Record<string, string> = {};
          headers.forEach((h, i) => { obj[h] = String(values[i] ?? ""); });
          rows.push(obj);
        }
      });

      const students = rowsToStudents(rows);
      if (students.length === 0) {
        throw new TRPCError({ code: "BAD_REQUEST", message: "No valid rows found in XLSX" });
      }
      await importStudents(ctx.user.id, students.map((s) => ({ ...s, classId: input.classId })));
      return { success: true, count: students.length };
    }),

  exportCSV: protectedProcedure
    .input(z.object({ classId: z.number().optional() }))
    .query(async ({ ctx, input }) => {
      const all = await getStudents(ctx.user.id, input.classId);
      const headers = ["studentId", "externalRef", "firstName", "lastName", "email"];
      const lines = [
        headers.join(","),
        ...all.map((s: any) =>
          [s.studentId ?? "", s.externalRef ?? "", s.firstName, s.lastName, s.email ?? ""].join(",")
        ),
      ];
      return { csv: lines.join("\n") };
    }),

  exportXLSX: protectedProcedure
    .input(z.object({ classId: z.number().optional() }))
    .query(async ({ ctx, input }) => {
      const all = await getStudents(ctx.user.id, input.classId);
      const workbook = new ExcelJS.Workbook();
      const sheet = workbook.addWorksheet("Students");
      sheet.columns = [
        { header: "studentId", key: "studentId", width: 15 },
        { header: "externalRef", key: "externalRef", width: 15 },
        { header: "firstName", key: "firstName", width: 20 },
        { header: "lastName", key: "lastName", width: 20 },
        { header: "email", key: "email", width: 30 },
      ];
      all.forEach((s: any) => sheet.addRow(s));
      const buffer = await workbook.xlsx.writeBuffer();
      return { base64: Buffer.from(buffer as ArrayBuffer).toString("base64") };
    }),

  /**
   * Generate (or regenerate) a portal access token for a student
   */
  generatePortalToken: protectedProcedure
    .input(z.object({ studentId: z.number() }))
    .mutation(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new Error("Database not available");
      const { students } = await import("../../drizzle/schema");
      const { randomBytes } = await import("crypto");
      const existing = await db
        .select()
        .from(students)
        .where(and(eq(students.id, input.studentId), eq(students.userId, ctx.user.id)))
        .limit(1);
      if (!existing.length) throw new Error("Student not found");
      const token = randomBytes(24).toString("hex");
      await db
        .update(students)
        .set({ portalToken: token, updatedAt: new Date() })
        .where(eq(students.id, input.studentId));
      return { token };
    }),

  /**
   * Public: get student data by portal token (no auth required)
   */
  getPortalData: publicProcedure
    .input(z.object({ token: z.string() }))
    .query(async ({ input }) => {
      const db = await getDb();
      if (!db) return null;
      const { students, studentGrades, exams, studentClasses, classes } = await import("../../drizzle/schema");
      const { inArray } = await import("drizzle-orm");
      const studentResult = await db.select().from(students).where(eq(students.portalToken, input.token)).limit(1);
      if (!studentResult.length) return null;
      const student = studentResult[0];

      const grades: any[] = await db.select().from(studentGrades).where(eq(studentGrades.studentId, student.id));
      const examIds = grades.map((g: any) => g.examId);
      let examDetails: any[] = [];
      if (examIds.length > 0) {
        examDetails = await db.select().from(exams).where(inArray(exams.id, examIds));
      }

      const gradeData = grades.map((g: any) => {
        const exam = examDetails.find((e: any) => e.id === g.examId);
        return {
          examId: g.examId,
          examName: exam?.name ?? "–",
          discipline: exam?.discipline ?? "–",
          examDate: exam?.examDate ?? exam?.createdAt,
          score: parseFloat(g.score ?? "0"),
          maxScore: parseFloat(g.maxScore ?? "10"),
          gradedAt: g.gradedAt,
        };
      });

      return {
        student: {
          firstName: student.firstName,
          lastName: student.lastName,
          studentId: student.studentId,
        },
        grades: gradeData,
      };
    }),

  upsertGrade: protectedProcedure
    .input(z.object({
      studentId: z.number(),
      examId: z.number(),
      score: z.number(),
      maxScore: z.number(),
      answerSheetId: z.number().optional().nullable(),
    }))
    .mutation(async ({ ctx, input }) => {
      const student = await getStudentById(input.studentId);
      if (!student || student.userId !== ctx.user.id) {
        throw new TRPCError({ code: "UNAUTHORIZED", message: "Unauthorized access to student" });
      }

      await upsertStudentGrade({
        studentId: input.studentId,
        examId: input.examId,
        score: input.score.toString(),
        maxScore: input.maxScore.toString(),
        answerSheetId: input.answerSheetId || null,
        gradedAt: new Date(),
      });
      return { success: true };
    }),
});
