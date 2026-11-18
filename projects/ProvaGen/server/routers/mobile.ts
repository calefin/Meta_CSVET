import { protectedProcedure, router } from "../_core/trpc";
import {
  getDb,
  institutions,
  classes,
  students,
  studentClasses,
  exams,
  examQuestions,
  questions as questionsTable,
  examScores,
} from "../db";
import { eq, inArray } from "drizzle-orm";

export const mobileRouter = router({
  syncData: protectedProcedure.query(async ({ ctx }) => {
    const db = await getDb();
    if (!db) throw new Error("Database not available");

    try {
      // 1. Fetch Institutions
      const userInstitutions = await db
        .select()
        .from(institutions)
        .where(eq(institutions.userId, ctx.user.id));

      // 2. Fetch Classes
      const userClasses = await db
        .select()
        .from(classes)
        .where(eq(classes.userId, ctx.user.id));

      // 3. Fetch Students and their relations
      const userStudents = await db
        .select()
        .from(students)
        .where(eq(students.userId, ctx.user.id));

      const studentIds = userStudents.map((s: any) => s.id);
      const userStudentClasses =
        studentIds.length > 0
          ? await db
              .select()
              .from(studentClasses)
              .where(inArray(studentClasses.studentId, studentIds))
          : [];

      // 4. Fetch Exams and their question keys
      const userExams = await db
        .select()
        .from(exams)
        .where(eq(exams.userId, ctx.user.id));

      const enrichedExams = [];
      for (const exam of userExams) {
        // Fetch questions for this exam
        const eqs = await db
          .select()
          .from(examQuestions)
          .where(eq(examQuestions.examId, exam.id));
        const qIds = eqs.map((eq: any) => eq.questionId);

        let qs: any[] = [];
        if (qIds.length > 0) {
          qs = await db
            .select()
            .from(questionsTable)
            .where(inArray(questionsTable.id, qIds));
        }

        // Fetch custom scores if set, or default to 1.0 per question
        const scores = await db
          .select()
          .from(examScores)
          .where(eq(examScores.examId, exam.id));

        const questionKeys = eqs.map((eq: any) => {
          const question = qs.find((q: any) => q.id === eq.questionId);
          const score = scores.find((s: any) => s.questionId === eq.questionId);
          return {
            questionNumber: eq.order,
            correctOption: question?.correctAnswer ?? "A",
            points: score?.points ?? 1.0,
            isCancelled: eq.isVoided === "true",
            redistributePoints: false,
          };
        });

        enrichedExams.push({
          ...exam,
          questionKeys,
        });
      }

      return {
        institutions: userInstitutions.map((i: any) => ({
          id: String(i.id),
          name: i.name,
        })),
        classes: userClasses.map((c: any) => ({
          id: String(c.id),
          name: c.name,
          institutionId: c.institutionId ? String(c.institutionId) : "1",
        })),
        students: userStudents.map((s: any) => {
          const rels = userStudentClasses.filter((sc: any) => sc.studentId === s.id);
          return {
            id: String(s.id),
            name: `${s.firstName} ${s.lastName}`.trim(),
            identifier: s.studentId ?? String(s.id),
            classId: rels.length > 0 ? String(rels[0].classId) : "101",
            quizGrades: {}, // Will be populated locally
          };
        }),
        exams: enrichedExams.map((e: any) => ({
          id: String(e.id),
          name: e.name,
          gabaritoTemplate: e.gabaritoTemplate ?? "Gabarito 20 Questões",
          date: e.examDate ? e.examDate.toISOString() : e.createdAt.toISOString(),
          institutionId: e.institutionId ? String(e.institutionId) : "1",
          classId: e.classId ? String(e.classId) : "101",
          questionsCount: e.questionKeys.length,
          totalPoints: e.totalPoints ? parseFloat(String(e.totalPoints)) : 10.0,
          correctedExamsCount: 0, // Will be computed locally
          questionKeys: e.questionKeys,
        })),
      };
    } catch (error) {
      console.error("Mobile sync data error:", error);
      throw error;
    }
  }),
});
