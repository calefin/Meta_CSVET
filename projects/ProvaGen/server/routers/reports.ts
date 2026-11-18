import { z } from "zod";
import { protectedProcedure, router } from "../_core/trpc";
import { getDb } from "../db";
import { students, studentGrades, exams, classes, studentClasses, sharingLinks, quizResponses, examQuestions, questions } from "../db";
import { eq, and, inArray, desc, sql } from "drizzle-orm";
import { TRPCError } from "@trpc/server";

export const reportsRouter = router({
  /**
   * Overall dashboard metrics for the authenticated user
   */
  dashboardMetrics: protectedProcedure.query(async ({ ctx }) => {
    const db = await getDb();
    if (!db) return null;

    try {
      // 1. Fetch counts using SQL count() to avoid pulling full table records
      const [
        questionsCountResult,
        examsCountResult,
        studentsCountResult,
        classesCountResult,
      ] = await Promise.all([
        db.select({ value: sql<number>`count(*)` }).from(questions).where(eq(questions.userId, ctx.user.id)),
        db.select({ value: sql<number>`count(*)` }).from(exams).where(eq(exams.userId, ctx.user.id)),
        db.select({ value: sql<number>`count(*)` }).from(students).where(eq(students.userId, ctx.user.id)),
        db.select({ value: sql<number>`count(*)` }).from(classes).where(eq(classes.userId, ctx.user.id)),
      ]);

      const totalQuestions = Number(questionsCountResult[0]?.value ?? 0);
      const totalExams = Number(examsCountResult[0]?.value ?? 0);
      const totalStudents = Number(studentsCountResult[0]?.value ?? 0);
      const totalClasses = Number(classesCountResult[0]?.value ?? 0);

      // 2. Fetch only the 5 most recent exams directly from DB
      const recentExamsResult = await db
        .select({
          id: exams.id,
          name: exams.name,
          createdAt: exams.createdAt,
          discipline: exams.discipline,
        })
        .from(exams)
        .where(eq(exams.userId, ctx.user.id))
        .orderBy(desc(exams.createdAt))
        .limit(5);

      // 3. Fetch only the creation dates of exams for the last 6 months analytics
      const examDatesResult = await db
        .select({
          createdAt: exams.createdAt,
        })
        .from(exams)
        .where(eq(exams.userId, ctx.user.id));

      // Exams by month (last 6 months)
      const now = new Date();
      const examsByMonth = Array.from({ length: 6 }, (_, i) => {
        const d = new Date(now.getFullYear(), now.getMonth() - (5 - i), 1);
        const label = d.toLocaleDateString("pt-BR", { month: "short", year: "2-digit" });
        const count = examDatesResult.filter((e: any) => {
          const created = new Date(e.createdAt);
          return created.getFullYear() === d.getFullYear() && created.getMonth() === d.getMonth();
        }).length;
        return { month: label, exams: count };
      });

      // 4. Fetch the average grade directly using SQL avg()
      // We first need the user's exam IDs to filter the student grades
      const userExamIdsResult = await db
        .select({ id: exams.id })
        .from(exams)
        .where(eq(exams.userId, ctx.user.id));

      const examIds = userExamIdsResult.map((e: any) => e.id);
      let avgGrade: number | null = null;

      if (examIds.length > 0) {
        // Query average grade across all student grades for user's exams, scaled to 10 points
        const avgResult = await db
          .select({
            avg: sql<number>`avg(
              case 
                when cast(max_score as real) > 0 
                then (cast(score as real) / cast(max_score as real)) * 10 
                else 0 
              end
            )`
          })
          .from(studentGrades)
          .where(inArray(studentGrades.examId, examIds));

        const rawAvg = avgResult[0]?.avg;
        if (rawAvg !== null && rawAvg !== undefined) {
          avgGrade = Math.round(Number(rawAvg) * 10) / 10;
        }
      }

      return {
        totalQuestions,
        totalExams,
        totalStudents,
        totalClasses,
        avgGrade,
        examsByMonth,
        recentExams: recentExamsResult,
      };
    } catch (error) {
      console.error("Dashboard metrics error:", error);
      throw error;
    }
  }),

  /**
   * Performance report for a single student across all exams
   */
  studentPerformance: protectedProcedure
    .input(z.object({ studentId: z.number() }))
    .query(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) return null;

      // Verify ownership
      const studentResult = await db
        .select()
        .from(students)
        .where(and(eq(students.id, input.studentId), eq(students.userId, ctx.user.id)))
        .limit(1);

      if (!studentResult.length) return null;
      const student = studentResult[0];

      const grades: any[] = await db
        .select()
        .from(studentGrades)
        .where(eq(studentGrades.studentId, input.studentId));

      const examIds = grades.map((g: any) => g.examId);
      let examDetails: any[] = [];
      if (examIds.length > 0) {
        examDetails = await db.select().from(exams).where(inArray(exams.id, examIds));
      }

      const gradeData = grades.map((g: any) => {
        const exam = examDetails.find((e: any) => e.id === g.examId);
        const score = parseFloat(g.score ?? "0");
        const max = parseFloat(g.maxScore ?? "10");
        return {
          examId: g.examId,
          examName: exam?.name ?? "–",
          discipline: exam?.discipline ?? "–",
          examDate: exam?.examDate ?? exam?.createdAt,
          score,
          maxScore: max,
          pct: max > 0 ? Math.round((score / max) * 100) : 0,
          gradedAt: g.gradedAt,
        };
      }).sort((a: any, b: any) => new Date(a.examDate ?? 0).getTime() - new Date(b.examDate ?? 0).getTime());

      const avg = gradeData.length > 0
        ? Math.round(gradeData.reduce((s, g) => s + g.pct, 0) / gradeData.length)
        : null;

      return { student, grades: gradeData, avgPct: avg };
    }),

  /**
   * Class-level grade report: all students × all exams for a class
   */
  classReport: protectedProcedure
    .input(z.object({ classId: z.number() }))
    .query(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) return null;

      const classResult = await db
        .select()
        .from(classes)
        .where(and(eq(classes.id, input.classId), eq(classes.userId, ctx.user.id)))
        .limit(1);

      if (!classResult.length) return null;

      const links = await db.select().from(studentClasses).where(eq(studentClasses.classId, input.classId));
      if (!links.length) return { class: classResult[0], students: [], exams: [], grades: [] };

      const studentIds = links.map((l: any) => l.studentId);
      const [classStudents, classExams] = await Promise.all([
        db.select().from(students).where(inArray(students.id, studentIds)),
        db.select().from(exams).where(and(eq(exams.userId, ctx.user.id), eq(exams.classId, input.classId))),
      ]);

      const examIds = classExams.map((e: any) => e.id);
      let allGrades: any[] = [];
      if (examIds.length > 0 && studentIds.length > 0) {
        allGrades = await db
          .select()
          .from(studentGrades)
          .where(and(inArray(studentGrades.studentId, studentIds), inArray(studentGrades.examId, examIds)));
      }

      return {
        class: classResult[0],
        students: classStudents,
        exams: classExams,
        grades: allGrades,
      };
    }),

  /**
   * Psychometric analysis + grade distribution for an exam.
   * Uses quiz responses (online submissions) as the answer data source.
   */
  examAnalysis: protectedProcedure
    .input(z.object({ examId: z.number() }))
    .query(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) return null;

      // Verify ownership
      const examResult = await db.select().from(exams)
        .where(and(eq(exams.id, input.examId), eq(exams.userId, ctx.user.id)))
        .limit(1);
      if (!examResult.length) throw new TRPCError({ code: "NOT_FOUND" });

      // Exam questions with correct answers
      const eqs = await db.select().from(examQuestions)
        .where(and(eq(examQuestions.examId, input.examId), eq(examQuestions.isVoided, "false")));
      const qIds = eqs.map((eq: any) => eq.questionId);
      if (qIds.length === 0) return { questions: [], gradeDistribution: [], stats: null, totalResponses: 0 };

      const qDetails = await db.select().from(questions).where(inArray(questions.id, qIds));

      // Build correct-answer map
      const correctMap: Record<number, string> = {};
      for (const q of qDetails) {
        if (!q.correctAnswer) continue;
        try {
          const p = JSON.parse(q.correctAnswer);
          correctMap[q.id] = (Array.isArray(p) ? p[0] : String(p)).toUpperCase();
        } catch {
          correctMap[q.id] = q.correctAnswer.toUpperCase();
        }
      }

      // Alternatives map for distractor labels
      const altMap: Record<number, string[]> = {};
      for (const q of qDetails) {
        try {
          const alts = JSON.parse(q.alternatives ?? "[]");
          altMap[q.id] = Array.isArray(alts)
            ? alts.map((_: any, i: number) => String.fromCharCode(65 + i))
            : ["A", "B", "C", "D", "E"];
        } catch {
          altMap[q.id] = ["A", "B", "C", "D", "E"];
        }
      }

      // Gather all online quiz responses for this exam
      const links = await db.select().from(sharingLinks).where(eq(sharingLinks.examId, input.examId));
      const linkIds = links.map((l: any) => l.id);
      let responses: any[] = [];
      if (linkIds.length > 0) {
        responses = await db.select().from(quizResponses).where(inArray(quizResponses.sharingLinkId, linkIds));
      }

      if (responses.length === 0) {
        return { questions: [], gradeDistribution: [], stats: null, totalResponses: 0 };
      }

      // Parse each response into { score, answers: {qId -> chosenLetter} }
      const parsed = responses.map((r: any) => {
        let answerArr: { questionId: number; answer: string }[] = [];
        try { answerArr = JSON.parse(r.answers ?? "[]"); } catch { /* */ }
        const answerMap: Record<number, string> = {};
        for (const a of answerArr) answerMap[a.questionId] = a.answer.toUpperCase();

        // Calculate score from correct answers
        let correct = 0;
        for (const qId of qIds) {
          if (correctMap[qId] && answerMap[qId] === correctMap[qId]) correct++;
        }
        const pct = qIds.length > 0 ? correct / qIds.length : 0;
        return { answerMap, score: correct, pct };
      });

      const total = parsed.length;

      // Grade distribution (buckets 0–10 scaled by maxQ)
      const buckets = Array.from({ length: 10 }, (_, i) => ({
        range: `${i}–${i + 1}`,
        count: 0,
        from: i / 10,
        to: (i + 1) / 10,
      }));
      for (const p of parsed) {
        const idx = Math.min(Math.floor(p.pct * 10), 9);
        buckets[idx].count++;
      }

      // Stats
      const scores = parsed.map((p) => p.pct * 10);
      const mean = scores.reduce((a, b) => a + b, 0) / total;
      const sorted = [...scores].sort((a, b) => a - b);
      const median = total % 2 === 0
        ? (sorted[total / 2 - 1] + sorted[total / 2]) / 2
        : sorted[Math.floor(total / 2)];
      const variance = scores.reduce((acc, s) => acc + (s - mean) ** 2, 0) / total;
      const stddev = Math.sqrt(variance);

      // Per-question psychometric data
      // Discrimination: sort by total score, take top/bottom 27%
      const n27 = Math.max(1, Math.floor(total * 0.27));
      const sortedParsed = [...parsed].sort((a, b) => a.pct - b.pct);
      const bottomGroup = sortedParsed.slice(0, n27);
      const topGroup = sortedParsed.slice(total - n27);

      const questionStats = eqs
        .sort((a: any, b: any) => a.order - b.order)
        .map((eq: any) => {
          const qId = eq.questionId;
          const qDetail = qDetails.find((q: any) => q.id === qId);

          const totalAnswered = parsed.filter((p) => p.answerMap[qId] !== undefined).length;
          const totalCorrect = parsed.filter((p) => p.answerMap[qId] === correctMap[qId]).length;
          const difficulty = totalAnswered > 0 ? totalCorrect / totalAnswered : 0;

          const topCorrect = topGroup.filter((p) => p.answerMap[qId] === correctMap[qId]).length;
          const bottomCorrect = bottomGroup.filter((p) => p.answerMap[qId] === correctMap[qId]).length;
          const discrimination = topGroup.length > 0
            ? topCorrect / topGroup.length - bottomCorrect / bottomGroup.length
            : 0;

          // Distractor counts (only for multiple choice)
          const options = altMap[qId] ?? ["A", "B", "C", "D", "E"];
          const distractors: { label: string; count: number; pct: number; isCorrect: boolean }[] = options.map((label) => {
            const count = parsed.filter((p) => p.answerMap[qId] === label).length;
            return { label, count, pct: total > 0 ? count / total : 0, isCorrect: label === correctMap[qId] };
          });

          return {
            order: eq.order,
            questionId: qId,
            statement: qDetail?.statement?.slice(0, 120) ?? "",
            type: qDetail?.type ?? "multiple_choice",
            correctAnswer: correctMap[qId] ?? "–",
            difficulty: Math.round(difficulty * 100) / 100,
            discrimination: Math.round(discrimination * 100) / 100,
            distractors,
            totalAnswered,
            totalCorrect,
          };
        });

      return {
        totalResponses: total,
        stats: {
          mean: Math.round(mean * 10) / 10,
          median: Math.round(median * 10) / 10,
          stddev: Math.round(stddev * 10) / 10,
          passRate: Math.round((scores.filter((s) => s >= 5).length / total) * 100),
        },
        gradeDistribution: buckets,
        questions: questionStats,
      };
    }),
});
