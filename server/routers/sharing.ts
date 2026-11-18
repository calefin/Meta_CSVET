import { protectedProcedure, publicProcedure, router } from "../_core/trpc";
import { z } from "zod";
import { TRPCError } from "@trpc/server";
import { randomBytes } from "crypto";
import {
  createSharingLink, getSharingLinks, getSharingLinkByToken, deactivateSharingLink,
  createQuizResponse, getQuizResponses, getUserById,
} from "../db";
import { getExamById } from "../db";
import { sendEmail, quizResponseEmailHtml } from "../_core/email";
import { getDb } from "../db";
import {
  examQuestions, questions, sharingLinks, quizResponses, students, studentClasses, examScores, users,
} from "../db";
import { eq, inArray, and } from "drizzle-orm";
import { PLAN_LIMITS } from "@shared/plans";
import type { PlanKey } from "@shared/plans";

export const sharingRouter = router({
  list: protectedProcedure.query(async ({ ctx }) => {
    const links = await getSharingLinks(ctx.user.id);
    const withCounts = await Promise.all(
      links.map(async (link: any) => ({
        ...link,
        responseCount: (await getQuizResponses(link.id)).length,
      }))
    );
    return withCounts;
  }),

  create: protectedProcedure
    .input(z.object({
      examId: z.number(),
      resultMode: z.enum(["none", "score", "answer_key", "all"]).default("none"),
      timeLimitMinutes: z.number().int().min(1).max(600).nullable().optional(),
      classId: z.number().nullable().optional(),
      requireStudentId: z.boolean().default(false),
    }))
    .mutation(async ({ ctx, input }) => {
      const exam = await getExamById(input.examId);
      if (!exam || exam.userId !== ctx.user.id) {
        throw new TRPCError({ code: "NOT_FOUND", message: "Exam not found" });
      }

      // Enforce sharing feature gate
      const { getDb } = await import("../db");
      const db = await getDb();
      if (db) {
        const userRow = await db.select({ plan: users.plan }).from(users).where(eq(users.id, ctx.user.id)).limit(1);
        const plan = (userRow[0]?.plan ?? "free") as PlanKey;
        if (!PLAN_LIMITS[plan].sharingEnabled) {
          throw new TRPCError({ code: "FORBIDDEN", message: "Compartilhamento público está disponível a partir do plano Basic. Faça upgrade para continuar." });
        }
      }

      const token = randomBytes(24).toString("hex");
      await createSharingLink(
        input.examId, ctx.user.id, token, input.resultMode,
        input.timeLimitMinutes ?? null,
        input.classId ?? null,
        input.requireStudentId,
      );
      return { success: true, token };
    }),

  deactivate: protectedProcedure
    .input(z.object({ id: z.number() }))
    .mutation(async ({ ctx, input }) => {
      await deactivateSharingLink(input.id, ctx.user.id);
      return { success: true };
    }),

  // ── Public procedures ─────────────────────────────────────────────────────

  getPublicExam: publicProcedure
    .input(z.object({ token: z.string() }))
    .query(async ({ input }) => {
      const link = await getSharingLinkByToken(input.token);
      if (!link) {
        throw new TRPCError({ code: "NOT_FOUND", message: "Link not found or expired" });
      }
      const exam = await getExamById(link.examId);
      if (!exam) {
        throw new TRPCError({ code: "NOT_FOUND", message: "Exam not found" });
      }

      // Fetch questions with point values for display
      const db = await getDb();
      let questionsWithPoints: any[] = [];
      if (db) {
        const eqs = await db.select().from(examQuestions).where(eq(examQuestions.examId, link.examId));
        const qIds = eqs.map((q: any) => q.questionId);
        if (qIds.length > 0) {
          const qDetails = await db.select().from(questions).where(inArray(questions.id, qIds));
          const scores = await db.select().from(examScores).where(eq(examScores.examId, link.examId));
          const pointsMap: Record<number, string> = {};
          for (const s of scores as any[]) pointsMap[s.questionId] = s.points;

          questionsWithPoints = eqs
            .filter((eq: any) => eq.isVoided !== "true")
            .map((eq: any) => {
              const q = qDetails.find((qd: any) => qd.id === eq.questionId);
              return q ? { ...q, points: pointsMap[q.id] ?? null } : null;
            })
            .filter(Boolean);
        }
      }

      return {
        exam,
        questions: questionsWithPoints,
        linkId: link.id,
        resultMode: link.resultMode ?? "none",
        timeLimitMinutes: link.timeLimitMinutes ?? null,
        requireStudentId: !!(link as any).requireStudentId,
        classId: (link as any).classId ?? null,
      };
    }),

  // Validates a student's enrollment number against the class linked to this quiz.
  validateStudent: publicProcedure
    .input(z.object({
      token: z.string(),
      studentId: z.string().min(1),
    }))
    .mutation(async ({ input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });

      const link = await getSharingLinkByToken(input.token);
      if (!link) throw new TRPCError({ code: "NOT_FOUND", message: "Link não encontrado" });

      const classId = (link as any).classId;
      if (!classId) throw new TRPCError({ code: "BAD_REQUEST", message: "Link não exige validação de aluno" });

      // Find student with matching studentId that belongs to the link's class
      const sc = await db
        .select()
        .from(studentClasses)
        .where(eq(studentClasses.classId, classId));

      const studentIds = (sc as any[]).map((r: any) => r.studentId);
      if (!studentIds.length) throw new TRPCError({ code: "NOT_FOUND", message: "Nenhum aluno cadastrado nessa turma" });

      const enrolled = await db
        .select()
        .from(students)
        .where(
          and(
            inArray(students.id, studentIds),
            eq(students.studentId, input.studentId),
          )
        )
        .limit(1);

      if (!enrolled.length) {
        throw new TRPCError({ code: "NOT_FOUND", message: "Matrícula não encontrada nessa turma" });
      }

      const student = enrolled[0] as any;
      return {
        studentInternalId: student.id,
        studentName: `${student.firstName} ${student.lastName}`,
      };
    }),

  submitAnswers: publicProcedure
    .input(z.object({
      token: z.string(),
      studentName: z.string().min(1).max(255),
      studentEmail: z.string().email().optional(),
      enrolledStudentId: z.number().optional(),
      answers: z.array(z.object({
        questionId: z.number(),
        answer: z.string(),
      })),
    }))
    .mutation(async ({ input }) => {
      const link = await getSharingLinkByToken(input.token);
      if (!link) {
        throw new TRPCError({ code: "NOT_FOUND", message: "Link not found or expired" });
      }

      const db = await getDb();
      let examQs: any[] = [];
      let questionDetails: any[] = [];
      if (db) {
        examQs = await db.select().from(examQuestions).where(eq(examQuestions.examId, link.examId));
        const qIds = examQs.map((eq: any) => eq.questionId);
        if (qIds.length > 0) {
          questionDetails = await db.select().from(questions).where(inArray(questions.id, qIds));
        }
      }

      const resultMode: string = link.resultMode ?? "none";
      const showScore = resultMode === "score" || resultMode === "all";
      const showAnswerKey = resultMode === "answer_key" || resultMode === "all";

      let score = 0;
      let maxScore = 0;
      const answerKeyMap: Record<number, string> = {};

      for (const eq of examQs) {
        if (eq.isVoided === "true") continue;
        const q = questionDetails.find((qd: any) => qd.id === eq.questionId);
        if (!q) continue;

        const correctRaw = q.correctAnswer;
        if (!correctRaw) continue;

        let correct: string;
        try {
          const parsed = JSON.parse(correctRaw);
          correct = Array.isArray(parsed) ? parsed[0] : String(parsed);
        } catch {
          correct = correctRaw;
        }

        answerKeyMap[q.id] = correct.toUpperCase();
        maxScore += 1;

        const studentAnswer = input.answers.find((a) => a.questionId === q.id);
        if (studentAnswer && studentAnswer.answer.toUpperCase() === correct.toUpperCase()) {
          score += 1;
        }
      }

      await createQuizResponse({
        sharingLinkId: link.id,
        studentName: input.studentName,
        studentEmail: input.studentEmail ?? null,
        answers: JSON.stringify(input.answers),
        score: showScore ? String(score) : null,
        ...(input.enrolledStudentId ? { enrolledStudentId: input.enrolledStudentId } : {}),
      } as any);

      try {
        const exam = await getExamById(link.examId);
        if (exam) {
          const owner = await getUserById(link.userId);
          if (owner?.email) {
            await sendEmail({
              to: owner.email,
              subject: `Nova resposta: ${exam.name}`,
              html: quizResponseEmailHtml({
                examName: exam.name,
                studentName: input.studentName,
                studentEmail: input.studentEmail,
                score: showScore ? `${score}/${maxScore}` : undefined,
                submittedAt: new Date(),
              }),
            });
          }
        }
      } catch (e) {
        console.error("[sharing] email notification failed:", e);
      }

      return {
        success: true,
        resultMode,
        ...(showScore ? { score, maxScore } : {}),
        ...(showAnswerKey ? { answerKey: answerKeyMap } : {}),
      };
    }),

  // ── Manual essay grading ─────────────────────────────────────────────────

  listEssayResponses: protectedProcedure
    .input(z.object({ examId: z.number() }))
    .query(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) return [];

      const exam = await getExamById(input.examId);
      if (!exam || exam.userId !== ctx.user.id) throw new TRPCError({ code: "NOT_FOUND" });

      const eqs = await db.select().from(examQuestions).where(eq(examQuestions.examId, input.examId));
      const qIds = eqs.map((e: any) => e.questionId);
      if (qIds.length === 0) return [];
      const qDetails = await db.select().from(questions).where(inArray(questions.id, qIds));
      const essayQIds = qDetails.filter((q: any) => q.type === "essay").map((q: any) => q.id);
      if (essayQIds.length === 0) return [];

      const links = await db.select().from(sharingLinks).where(
        and(eq(sharingLinks.examId, input.examId), eq(sharingLinks.userId, ctx.user.id))
      );
      const linkIds = links.map((l: any) => l.id);
      if (linkIds.length === 0) return [];
      const responses = await db.select().from(quizResponses).where(inArray(quizResponses.sharingLinkId, linkIds));

      return responses.map((r: any) => {
        let answers: { questionId: number; answer: string }[] = [];
        try { answers = JSON.parse(r.answers ?? "[]"); } catch { /* */ }
        let manualScores: Record<number, number> = {};
        try { manualScores = JSON.parse(r.manualScores ?? "{}"); } catch { /* */ }

        const essayAnswers = answers.filter((a) => essayQIds.includes(a.questionId));
        return {
          responseId: r.id,
          studentName: r.studentName,
          studentEmail: r.studentEmail,
          submittedAt: r.submittedAt,
          essayAnswers: essayAnswers.map((a) => ({
            questionId: a.questionId,
            statement: qDetails.find((q: any) => q.id === a.questionId)?.statement ?? "",
            answer: a.answer,
            manualScore: manualScores[a.questionId] ?? null,
          })),
        };
      });
    }),

  gradeEssay: protectedProcedure
    .input(z.object({
      examId: z.number(),
      responseId: z.number(),
      questionId: z.number(),
      points: z.number().min(0).max(100),
    }))
    .mutation(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });

      const exam = await getExamById(input.examId);
      if (!exam || exam.userId !== ctx.user.id) throw new TRPCError({ code: "NOT_FOUND" });

      const responseResult = await db.select().from(quizResponses)
        .where(eq(quizResponses.id, input.responseId)).limit(1);
      if (!responseResult.length) throw new TRPCError({ code: "NOT_FOUND" });

      const response = responseResult[0];
      let manualScores: Record<number, number> = {};
      try { manualScores = JSON.parse((response as any).manualScores ?? "{}"); } catch { /* */ }
      manualScores[input.questionId] = input.points;

      await db.update(quizResponses)
        .set({ manualScores: JSON.stringify(manualScores) } as any)
        .where(eq(quizResponses.id, input.responseId));

      return { success: true };
    }),
});
