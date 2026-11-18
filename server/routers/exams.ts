import { z } from "zod";
import { TRPCError } from "@trpc/server";
import { protectedProcedure, router } from "../_core/trpc";
import { getDb } from "../db";
import { exams, examQuestions, questions as questionsTable, studentGrades, examScores, users } from "../db";
import { eq, and, inArray } from "drizzle-orm";
import { PLAN_LIMITS } from "@shared/plans";
import type { PlanKey } from "@shared/plans";
import { saveImageLocally } from "../_core/imageStorage";

const CreateExamSchema = z.object({
  name: z.string().min(1, "Nome da avaliação é obrigatório"),
  course: z.string().min(1, "Curso é obrigatório"),
  class: z.string().min(1, "Turma é obrigatória"),
  campus: z.string().optional(),
  discipline: z.string().min(1, "Disciplina é obrigatória"),
  professor: z.string().min(1, "Professor é obrigatório"),
  examDate: z.date().optional(),
  period: z.string().optional(),
  instructions: z.string().optional(),
  numberOfVersions: z.number().min(1).default(1),
  shuffleMode: z.enum(["all", "questions", "items", "none"]).default("all"),
  printFontSize: z.enum(["small", "normal", "large"]).default("normal"),
  printMargins: z.enum(["compact", "normal", "wide"]).default("normal"),
  questionIds: z.array(z.number()),
  headerType: z.enum(["standard", "custom"]).optional(),
  examLanguage: z.enum(["pt", "en", "es"]).optional(),
  examType: z.enum(["full", "answer_key_only"]).optional(),
  totalPoints: z.string().optional(),
  institutionId: z.number().optional(),
  classId: z.number().optional(),
});

export const examsRouter = router({
  create: protectedProcedure
    .input(CreateExamSchema)
    .mutation(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new Error("Database not available");

      // Enforce version limit based on plan
      const userRow = await db.select({ plan: users.plan }).from(users).where(eq(users.id, ctx.user.id)).limit(1);
      const plan = (userRow[0]?.plan ?? "free") as PlanKey;
      const maxVersions = PLAN_LIMITS[plan].maxVersions;
      if ((input.numberOfVersions ?? 1) > maxVersions) {
        throw new TRPCError({ code: "FORBIDDEN", message: `Seu plano permite no máximo ${maxVersions} versão(ões) por avaliação. Faça upgrade para continuar.` });
      }

      try {
        // Create exam
        const examResult = await db.insert(exams).values({
          userId: ctx.user.id,
          name: input.name,
          course: input.course,
          class: input.class,
          campus: input.campus || null,
          discipline: input.discipline,
          professor: input.professor,
          examDate: input.examDate || null,
          period: input.period || null,
          instructions: input.instructions || null,
          numberOfVersions: input.numberOfVersions,
          shuffleMode: input.shuffleMode,
          printFontSize: input.printFontSize,
          printMargins: input.printMargins,
          headerType: input.headerType ?? "standard",
          examLanguage: input.examLanguage ?? "pt",
          examType: input.examType ?? "full",
          totalPoints: input.totalPoints ?? null,
          institutionId: input.institutionId ?? null,
          classId: input.classId ?? null,
        });

        const _er = examResult as any;
        const examId = _er?.insertId ?? _er?.[0]?.insertId ?? (_er?.lastInsertRowid != null ? Number(_er.lastInsertRowid) : 1);

        // Add questions to exam
        for (let i = 0; i < input.questionIds.length; i++) {
          await db.insert(examQuestions).values({
            examId,
            questionId: input.questionIds[i],
            order: i + 1,
          });
        }

        return { success: true, examId };
      } catch (error) {
        console.error("Error creating exam:", error);
        throw error;
      }
    }),

  list: protectedProcedure.query(async ({ ctx }) => {
    const db = await getDb();
    if (!db) return [];

    const result = await db
      .select()
      .from(exams)
      .where(eq(exams.userId, ctx.user.id));

    return result;
  }),

  get: protectedProcedure
    .input(z.object({ id: z.number() }))
    .query(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) return null;

      const result = await db
        .select()
        .from(exams)
        .where(eq(exams.id, input.id))
        .limit(1);

      if (!result.length || result[0].userId !== ctx.user.id) return null;

      return result[0];
    }),

  update: protectedProcedure
    .input(
      z.object({
        id: z.number(),
        ...CreateExamSchema.omit({ questionIds: true }).shape,
      })
    )
    .mutation(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new Error("Database not available");

      // Verify ownership
      const existing = await db
        .select()
        .from(exams)
        .where(eq(exams.id, input.id))
        .limit(1);

      if (!existing.length || existing[0].userId !== ctx.user.id) {
        throw new Error("Unauthorized");
      }

      await db
        .update(exams)
        .set({
          name: input.name,
          course: input.course,
          class: input.class,
          campus: input.campus || null,
          discipline: input.discipline,
          professor: input.professor,
          examDate: input.examDate || null,
          period: input.period || null,
          instructions: input.instructions || null,
          numberOfVersions: input.numberOfVersions,
          updatedAt: new Date(),
        })
        .where(eq(exams.id, input.id));

      return { success: true };
    }),

  delete: protectedProcedure
    .input(z.object({ id: z.number() }))
    .mutation(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new Error("Database not available");

      // Verify ownership
      const existing = await db
        .select()
        .from(exams)
        .where(eq(exams.id, input.id))
        .limit(1);

      if (!existing.length || existing[0].userId !== ctx.user.id) {
        throw new Error("Unauthorized");
      }

      // Delete associated questions
      await db.delete(examQuestions).where(eq(examQuestions.examId, input.id));

      // Delete exam
      await db.delete(exams).where(eq(exams.id, input.id));

      return { success: true };
    }),

  getQuestions: protectedProcedure
    .input(z.object({ examId: z.number() }))
    .query(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) return [];

      const exam = await db.select().from(exams).where(eq(exams.id, input.examId)).limit(1);
      if (!exam.length || exam[0].userId !== ctx.user.id) return [];

      const eqs = await db.select().from(examQuestions).where(eq(examQuestions.examId, input.examId));
      const qIds = eqs.map((eq: any) => eq.questionId);
      if (!qIds.length) return [];

      const qs = await db.select().from(questionsTable).where(inArray(questionsTable.id, qIds));
      return eqs.map((eq: any) => ({
        ...eq,
        question: qs.find((q: any) => q.id === eq.questionId) ?? null,
      }));
    }),

  /**
   * Void a question in an exam and recalculate grades
   */
  voidQuestion: protectedProcedure
    .input(z.object({ examId: z.number(), examQuestionId: z.number() }))
    .mutation(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new Error("Database not available");

      const exam = await db.select().from(exams).where(eq(exams.id, input.examId)).limit(1);
      if (!exam.length || exam[0].userId !== ctx.user.id) throw new Error("Unauthorized");

      await db
        .update(examQuestions)
        .set({ isVoided: "true", voidedAt: new Date() })
        .where(and(eq(examQuestions.id, input.examQuestionId), eq(examQuestions.examId, input.examId)));

      return { success: true };
    }),

  /**
   * Unvoid a previously voided question
   */
  unvoidQuestion: protectedProcedure
    .input(z.object({ examId: z.number(), examQuestionId: z.number() }))
    .mutation(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new Error("Database not available");

      const exam = await db.select().from(exams).where(eq(exams.id, input.examId)).limit(1);
      if (!exam.length || exam[0].userId !== ctx.user.id) throw new Error("Unauthorized");

      await db
        .update(examQuestions)
        .set({ isVoided: "false", voidedAt: null })
        .where(and(eq(examQuestions.id, input.examQuestionId), eq(examQuestions.examId, input.examId)));

      return { success: true };
    }),

  createQuick: protectedProcedure
    .input(z.object({
      name: z.string().min(1),
      discipline: z.string().default("Geral"),
      professor: z.string().default("-"),
      examDate: z.date().optional(),
      classId: z.number().optional(),
      institutionId: z.number().optional(),
      totalPoints: z.string().optional(),
      questions: z.array(z.object({
        numAlternatives: z.number().min(2).max(8).default(5),
        correctAnswer: z.string().nullable(),
        points: z.number().min(0).default(1),
        statement: z.string().optional(),
        alternatives: z.array(z.string()).optional(),
        images: z.array(z.string()).optional(),
      })),
    }))
    .mutation(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new Error("Database not available");

      const letters = ["a", "b", "c", "d", "e", "f", "g", "h"];
      const questionIds: number[] = [];

      for (let i = 0; i < input.questions.length; i++) {
        const q = input.questions[i];
        const numAlts = q.alternatives ? q.alternatives.length : q.numAlternatives;
        const qLetters = letters.slice(0, numAlts);

        let alternativesObj: Record<string, string> = {};
        if (q.alternatives) {
          q.alternatives.forEach((alt, idx) => {
            const letter = qLetters[idx] || "a";
            alternativesObj[letter] = alt;
          });
        } else {
          alternativesObj = qLetters.reduce<Record<string, string>>((acc, l) => {
            acc[l] = l.toUpperCase();
            return acc;
          }, {});
        }

        const correctAnswer = q.correctAnswer ?? qLetters[0];
        const content = { type: "multiple_choice", alternatives: alternativesObj, correctAnswer };

        const statementText = q.statement || `Questão ${i + 1}`;
        const alternativesJson = JSON.stringify(q.alternatives || qLetters.map((l) => l.toUpperCase()));

        const result = await db.insert(questionsTable).values({
          userId: ctx.user.id,
          type: "multiple_choice",
          statement: statementText,
          discipline: input.discipline || "Geral",
          subject: "Geral",
          content: JSON.stringify(content),
          alternatives: alternativesJson,
          correctAnswer,
          sourceFormat: "manual",
          totalImages: q.images?.length ?? 0,
        });

        const _qr = result as any;
        const questionId = _qr?.insertId ?? _qr?.[0]?.insertId ?? (_qr?.lastInsertRowid != null ? Number(_qr.lastInsertRowid) : null);
        questionIds.push(questionId);

        // Upload images if provided
        if (questionId && q.images && q.images.length > 0) {
          for (let imgIdx = 0; imgIdx < q.images.length; imgIdx++) {
            try {
              const imageBuffer = Buffer.from(q.images[imgIdx], "base64");
              await saveImageLocally(
                imageBuffer,
                `question-${questionId}-image-${imgIdx}.jpg`,
                "image/jpeg",
                ctx.user.id,
                questionId
              );
            } catch (err) {
              console.error("Error saving image in createQuick:", err);
            }
          }
        }
      }

      const examResult = await db.insert(exams).values({
        userId: ctx.user.id,
        name: input.name,
        course: input.discipline || "Geral",
        class: "-",
        discipline: input.discipline || "Geral",
        professor: input.professor || "-",
        examDate: input.examDate || null,
        numberOfVersions: 1,
        shuffleMode: "none",
        printFontSize: "normal",
        printMargins: "normal",
        headerType: "standard",
        examLanguage: "pt",
        examType: "full",
        totalPoints: input.totalPoints ?? null,
        institutionId: input.institutionId ?? null,
        classId: input.classId ?? null,
      });
      const _er2 = examResult as any;
      const examId = _er2?.insertId ?? _er2?.[0]?.insertId ?? (_er2?.lastInsertRowid != null ? Number(_er2.lastInsertRowid) : null);

      for (let i = 0; i < questionIds.length; i++) {
        await db.insert(examQuestions).values({
          examId,
          questionId: questionIds[i],
          order: i + 1,
        });
        await db.insert(examScores).values({
          examId,
          questionId: questionIds[i],
          points: input.questions[i].points.toFixed(2),
        });
      }

      return { success: true, examId };
    }),
});
