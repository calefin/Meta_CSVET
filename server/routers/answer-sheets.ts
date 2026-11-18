import { z } from "zod";
import { protectedProcedure, router } from "../_core/trpc";
import { getDb } from "../db";
import { answerSheets, exams, examQuestions, questions as questionsTable } from "../db";
import { eq, inArray } from "drizzle-orm";
import { exec } from "child_process";
import { promisify } from "util";
import path from "path";
import { storagePut } from "../storage";

const execAsync = promisify(exec);

export const answerSheetsRouter = router({
  upload: protectedProcedure
    .input(
      z.object({
        examId: z.number(),
        imageData: z.string(),
      })
    )
    .mutation(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new Error("Database not available");

      try {
        // Verify exam ownership
        const examResult = await db
          .select()
          .from(exams)
          .where(eq(exams.id, input.examId))
          .limit(1);

        if (!examResult.length || examResult[0].userId !== ctx.user.id) {
          throw new Error("Unauthorized");
        }

        // Store image in S3
        const imageBuffer = Buffer.from(input.imageData, "base64");
        const imageKey = `answer-sheets/${ctx.user.id}/${input.examId}/${Date.now()}.jpg`;
        const { url: imageUrl } = await storagePut(imageKey, imageBuffer, "image/jpeg");

        // Obter questões da prova para cálculo dinâmico de quantidade e alternativas
        const examQuestionsResult = await db
          .select()
          .from(examQuestions)
          .where(eq(examQuestions.examId, input.examId));

        const numQuestions = examQuestionsResult.length;
        let numAlternatives = 5; // valor default se não achar questões de múltipla escolha

        if (numQuestions > 0) {
          const questionIds = examQuestionsResult.map((eq: any) => eq.questionId);
          const allQuestions = await db.select().from(questionsTable).where(inArray(questionsTable.id, questionIds));
          
          let maxAlts = 0;
          for (const q of allQuestions) {
            if ((q.type === "multiple_choice" || q.type === "multiple_choice_complex") && q.alternatives) {
              try {
                const alts = JSON.parse(q.alternatives);
                if (Array.isArray(alts) && alts.length > maxAlts) {
                  maxAlts = alts.length;
                }
              } catch (e) {}
            }
          }
          if (maxAlts > 0) {
            numAlternatives = maxAlts;
          }
        }

        // Process answer sheet
        const processorScript = path.join(__dirname, "../answer-sheet-processor.py");
        const processorInput = {
          imageData: input.imageData,
          numQuestions: numQuestions > 0 ? numQuestions : 50,
          numAlternatives,
        };

        const { stdout } = await execAsync(
          `python3 "${processorScript}" '${JSON.stringify(processorInput).replace(/'/g, "'\\''")}'`
        );

        const processorResult = JSON.parse(stdout);

        if (!processorResult.success) {
          throw new Error(processorResult.error || "Failed to process answer sheet");
        }

        // Save answer sheet record
        const result = await db.insert(answerSheets).values({
          examId: input.examId,
          imageStorageKey: imageKey,
          imageUrl,
          extractedAnswers: JSON.stringify(processorResult.answers),
          processingStatus: "completed",
        });

        return {
          success: true,
          sheetId: (() => { const r = result as any; return r?.insertId ?? r?.[0]?.insertId ?? (r?.lastInsertRowid != null ? Number(r.lastInsertRowid) : null); })(),
          answers: processorResult.answers,
          confidenceScores: processorResult.confidenceScores,
          imageUrl,
        };
      } catch (error) {
        console.error("Answer sheet upload error:", error);
        throw error;
      }
    }),

  get: protectedProcedure
    .input(z.object({ sheetId: z.number() }))
    .query(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new Error("Database not available");

      try {
        const result = await db
          .select()
          .from(answerSheets)
          .where(eq(answerSheets.id, input.sheetId))
          .limit(1);

        if (!result.length) {
          throw new Error("Answer sheet not found");
        }

        const sheet = result[0];

        return {
          id: sheet.id,
          examId: sheet.examId,
          imageUrl: sheet.imageUrl,
          processingStatus: sheet.processingStatus,
          extractedAnswers: JSON.parse(sheet.extractedAnswers || "[]"),
          errorMessage: sheet.errorMessage,
        };
      } catch (error) {
        console.error("Get answer sheet error:", error);
        throw error;
      }
    }),

  listByExam: protectedProcedure
    .input(z.object({ examId: z.number() }))
    .query(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new Error("Database not available");

      try {
        // Verify exam ownership
        const examResult = await db
          .select()
          .from(exams)
          .where(eq(exams.id, input.examId))
          .limit(1);

        if (!examResult.length || examResult[0].userId !== ctx.user.id) {
          throw new Error("Unauthorized");
        }

        const sheets = await db
          .select()
          .from(answerSheets)
          .where(eq(answerSheets.examId, input.examId));

        return sheets.map((sheet: any) => ({
          id: sheet.id,
          imageUrl: sheet.imageUrl,
          processingStatus: sheet.processingStatus,
          createdAt: sheet.createdAt,
        }));
      } catch (error) {
        console.error("List answer sheets error:", error);
        throw error;
      }
    }),

  syncPapers: protectedProcedure
    .input(
      z.object({
        examId: z.number(),
        papers: z.array(
          z.object({
            studentIdentifier: z.string(), // ID interno ou Matrícula/RM
            score: z.number(),
            maxScore: z.number(),
            answers: z.record(z.string(), z.string()), // { "1": "A", "2": "B" }
            scannedAt: z.string(), // ISO String
            imageData: z.string().optional(), // base64 da imagem corrigida
          })
        ),
      })
    )
    .mutation(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new Error("Database not available");

      const { getClassStudents, upsertStudentGrade, answerSheets: answerSheetsTable } = await import("../db");

      try {
        // 1. Verificar propriedade da prova
        const examResult = await db
          .select()
          .from(exams)
          .where(eq(exams.id, input.examId))
          .limit(1);

        if (!examResult.length || examResult[0].userId !== ctx.user.id) {
          throw new Error("Unauthorized access to exam");
        }

        const classId = examResult[0].classId;
        if (!classId) {
          throw new Error("Exam is not linked to any class");
        }

        // 2. Obter estudantes da turma para correspondência
        const classStudentsList = await getClassStudents(classId);

        const results = [];

        for (const paper of input.papers) {
          // Tentar encontrar estudante por ID interno ou por Matrícula (studentId/RM)
          let student = classStudentsList.find(
            (s: any) =>
              String(s.id) === paper.studentIdentifier ||
              (s.studentId && s.studentId.trim() === paper.studentIdentifier.trim())
          );

          // Se não encontrar, tentar por externalRef
          if (!student) {
            student = classStudentsList.find(
              (s: any) =>
                s.externalRef && s.externalRef.trim() === paper.studentIdentifier.trim()
            );
          }

          if (!student) {
            results.push({
              studentIdentifier: paper.studentIdentifier,
              success: false,
              error: "Student not found in class",
            });
            continue;
          }

          let imageUrl = null;
          let imageKey = `answer-sheets-sync/${ctx.user.id}/${input.examId}/${student.id}_${Date.now()}.jpg`;

          // 3. Se houver base64 da imagem, salvar na Storage (S3 ou local)
          if (paper.imageData) {
            try {
              const imageBuffer = Buffer.from(paper.imageData, "base64");
              const uploadRes = await storagePut(imageKey, imageBuffer, "image/jpeg");
              imageUrl = uploadRes.url;
            } catch (storageErr) {
              console.error("Storage upload error during sync:", storageErr);
            }
          }

          // 4. Salvar registro na tabela de gabaritos
          const sheetInsert = await db.insert(answerSheetsTable).values({
            examId: input.examId,
            imageStorageKey: paper.imageData ? imageKey : "sync_offline",
            imageUrl: imageUrl,
            extractedAnswers: JSON.stringify(paper.answers),
            processingStatus: "completed",
          });

          const sheetId = (() => {
            const r = sheetInsert as any;
            return r?.insertId ?? r?.[0]?.insertId ?? (r?.lastInsertRowid != null ? Number(r.lastInsertRowid) : null);
          })();

          // 5. Upsert na tabela de notas
          await upsertStudentGrade({
            studentId: student.id,
            examId: input.examId,
            score: paper.score.toString(),
            maxScore: paper.maxScore.toString(),
            answerSheetId: sheetId,
            gradedAt: new Date(paper.scannedAt),
          });

          results.push({
            studentIdentifier: paper.studentIdentifier,
            success: true,
            studentId: student.id,
            gradeId: sheetId,
          });
        }

        return { success: true, results };
      } catch (error: any) {
        console.error("Sync papers error:", error);
        throw new Error(error?.message || "Failed to sync papers");
      }
    }),
});
