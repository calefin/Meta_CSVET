// @ts-nocheck — arquivo legado, não registrado nos routers ativos
import { z } from "zod";
import { protectedProcedure, router } from "../_core/trpc";
import { getDb } from "../db";
import { questions } from "../../drizzle/schema";
import { eq } from "drizzle-orm";

const QuestionTypeEnum = z.enum(["multiple_choice", "essay"]);
const DifficultyEnum = z.enum(["easy", "medium", "hard"]);

const QuestionSchema = z.object({
  type: QuestionTypeEnum,
  statement: z.string().min(1, "Enunciado é obrigatório"),
  alternatives: z.array(z.string()).optional(),
  correctAnswer: z.string().optional(),
});

type Question = (z.infer<typeof QuestionSchema> & { explanation?: string; points?: number; sourceFormat?: string }) | any;

export const questionsRouter = router({
  import: protectedProcedure
    .input(
      z.object({
        fileName: z.string(),
        fileData: z.string(),
        fileType: z.enum(["xlsx", "docx", "pdf", "json"]),
      })
    )
    .mutation(async ({ ctx, input }) => {
      try {
        const buffer = Buffer.from(input.fileData, "base64");
        const tempPath = `/tmp/${Date.now()}_${input.fileName}`;
        const fs = await import("fs").then((m) => m.promises);
        await fs.writeFile(tempPath, buffer);

        const { execSync } = await import("child_process");
        const pythonScript = `${process.cwd()}/server/question-parser.py`;

        let parsedQuestions: Question[] = [];
        try {
          console.log(`[IMPORT] Executando parser: python3 "${pythonScript}" "${tempPath}" "${input.fileType}"`);
          const pythonCommand = `${process.cwd()}/.venv/bin/python3`;
          const output = execSync(
            `"${pythonCommand}" "${pythonScript}" "${tempPath}" "${input.fileType}"`,
            { encoding: "utf-8", maxBuffer: 10 * 1024 * 1024 }
          );
          parsedQuestions = JSON.parse(output);
          console.log(`[IMPORT] Parser retornou ${parsedQuestions.length} questões`);
        } catch (error: any) {
          console.error("[IMPORT] Parser error:", error.message);
          if (error.stderr) console.error("[IMPORT] Stderr:", error.stderr);
          if (error.stdout) console.error("[IMPORT] Stdout:", error.stdout);
          parsedQuestions = [];
        }

        try {
          await fs.unlink(tempPath);
        } catch (e) {
          // Ignore cleanup errors
        }

        console.log(`[IMPORT] Retornando ${parsedQuestions.length} questões ao frontend`);
        return {
          success: parsedQuestions.length > 0,
          count: parsedQuestions.length,
          questions: parsedQuestions,
        };
      } catch (error: any) {
        console.error("[IMPORT] Import error:", error.message);
        return {
          success: false,
          error: error.message || "Erro ao importar questões",
          count: 0,
          questions: [],
        };
      }
    }),

  create: protectedProcedure
    .input(QuestionSchema)
    .mutation(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new Error("Database not available");

      await db.insert(questions).values({
        userId: ctx.user.id,
        type: input.type,
        statement: input.statement,
        alternatives: input.alternatives
          ? JSON.stringify(input.alternatives)
          : null,
        correctAnswer: input.correctAnswer || null,
        sourceFormat: "manual",
      });

      return { success: true };
    }),

  list: protectedProcedure.query(async ({ ctx }) => {
    const db = await getDb();
    if (!db) return [];

    const result = await db
      .select()
      .from(questions)
      .where(eq(questions.userId, ctx.user.id));

    return result.map((q) => ({
      ...q,
      alternatives: q.alternatives ? JSON.parse(q.alternatives) : [],
    }));
  }),

  get: protectedProcedure
    .input(z.object({ id: z.number() }))
    .query(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) return null;

      const result = await db
        .select()
        .from(questions)
        .where(eq(questions.id, input.id))
        .limit(1);

      if (!result.length || result[0].userId !== ctx.user.id) return null;

      const q = result[0];
      return {
        ...q,
        alternatives: q.alternatives ? JSON.parse(q.alternatives) : [],
      };
    }),

  update: protectedProcedure
    .input(
      z.object({
        id: z.number(),
        type: QuestionTypeEnum,
        statement: z.string(),
        alternatives: z.array(z.string()).optional(),
        correctAnswer: z.string().optional(),
      })
    )
    .mutation(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new Error("Database not available");

      // Verify ownership
      const existing = await db
        .select()
        .from(questions)
        .where(eq(questions.id, input.id))
        .limit(1);

      if (!existing.length || existing[0].userId !== ctx.user.id) {
        throw new Error("Unauthorized");
      }

      await db
        .update(questions)
        .set({
          type: input.type,
          statement: input.statement,
          alternatives: input.alternatives
            ? JSON.stringify(input.alternatives)
            : null,
          correctAnswer: input.correctAnswer || null,
          updatedAt: new Date(),
        })
        .where(eq(questions.id, input.id));

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
        .from(questions)
        .where(eq(questions.id, input.id))
        .limit(1);

      if (!existing.length || existing[0].userId !== ctx.user.id) {
        throw new Error("Unauthorized");
      }

      await db.delete(questions).where(eq(questions.id, input.id));

      return { success: true };
    }),

  search: protectedProcedure
    .input(z.object({ query: z.string().optional() }))
    .query(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) return [];

      let result = await db
        .select()
        .from(questions)
        .where(eq(questions.userId, ctx.user.id));

      if (input.query) {
        const searchQuery = input.query.toLowerCase();
        result = result.filter(
          (q) =>
            q.statement.toLowerCase().includes(searchQuery) ||
            q.type.includes(searchQuery)
        );
      }

      return result.map((q) => ({
        ...q,
        alternatives: q.alternatives ? JSON.parse(q.alternatives) : [],
      }));
    }),

  export: protectedProcedure
    .input(
      z.object({
        format: z.enum(["xlsx", "json"]),
        questions: z.array(
          z.object({
            type: QuestionTypeEnum,
            statement: z.string(),
            alternatives: z.array(z.string()).optional(),
            correctAnswer: z.string().optional(),
            points: z.number().optional(),
          })
        ),
      })
    )
    .mutation(async ({ ctx, input }) => {
      const { storagePut } = await import("../storage");

      if (input.format === "xlsx") {
        const ExcelJS = await import("exceljs");
        const workbook = new ExcelJS.Workbook();
        const worksheet = workbook.addWorksheet("Questões");

        worksheet.columns = [
          { header: "Tipo", key: "type", width: 15 },
          { header: "Enunciado", key: "statement", width: 50 },
          { header: "Alternativa A", key: "altA", width: 30 },
          { header: "Alternativa B", key: "altB", width: 30 },
          { header: "Alternativa C", key: "altC", width: 30 },
          { header: "Alternativa D", key: "altD", width: 30 },
          { header: "Resposta Correta", key: "correctAnswer", width: 15 },
          { header: "Pontos", key: "points", width: 10 },
        ];

        input.questions.forEach((q) => {
          worksheet.addRow({
            type: q.type === "multiple_choice" ? "Múltipla Escolha" : "Discursiva",
            statement: q.statement,
            altA: q.alternatives?.[0] || "",
            altB: q.alternatives?.[1] || "",
            altC: q.alternatives?.[2] || "",
            altD: q.alternatives?.[3] || "",
            correctAnswer: q.correctAnswer || "",
            points: q.points || 1,
          });
        });

        const buffer = await workbook.xlsx.writeBuffer();
        const fileName = `questoes_${Date.now()}.xlsx`;
        const { url } = await storagePut(
          `questions/${ctx.user.id}/${fileName}`,
          Buffer.from(buffer),
          "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
        );

        return { success: true, url };
      } else {
        const jsonData = JSON.stringify(input.questions, null, 2);
        const fileName = `questoes_${Date.now()}.json`;
        const { url } = await storagePut(
          `questions/${ctx.user.id}/${fileName}`,
          jsonData,
          "application/json"
        );

        return { success: true, url };
      }
    }),
});
