import { z } from "zod";
import { TRPCError } from "@trpc/server";
import { protectedProcedure, router } from "../_core/trpc";
import { getDb } from "../db";
import { questions, questionImages, questionVersions, users } from "../db";
import { eq, and, inArray, desc, count } from "drizzle-orm";
import { PLAN_LIMITS } from "@shared/plans";
import type { PlanKey } from "@shared/plans";
import { getUserStorageQuota, updateUserStorageUsage, initializeUserStorageQuota } from "../_core/quotaManager";
import { saveImageLocally, getImageUrl, deleteImage, calculateUserImageStorageUsage } from "../_core/imageStorage";

// Extended type enums to support all question types
const QuestionTypeEnum = z.enum([
  "multiple_choice",
  "multiple_choice_complex",
  "essay",
  "assertion_reason",
]);

const DifficultyEnum = z.enum(["easy", "medium", "hard"]);

// Validation schema for multiple choice
const MultipleChoiceSchema = z.object({
  type: z.literal("multiple_choice"),
  alternatives: z.object({
    a: z.string().min(1, "Alternativa A é obrigatória"),
    b: z.string().min(1, "Alternativa B é obrigatória"),
    c: z.string().min(1, "Alternativa C é obrigatória"),
    d: z.string().min(1, "Alternativa D é obrigatória"),
    e: z.string().optional(),
  }),
  correctAnswer: z.enum(["a", "b", "c", "d", "e"]),
  explanation: z.string().optional(),
});

// Validation schema for complex multiple choice
const ComplexMultipleChoiceSchema = z.object({
  type: z.literal("multiple_choice_complex"),
  alternatives: z.object({
    a: z.string().min(1, "Alternativa A é obrigatória"),
    b: z.string().min(1, "Alternativa B é obrigatória"),
    c: z.string().min(1, "Alternativa C é obrigatória"),
    d: z.string().min(1, "Alternativa D é obrigatória"),
    e: z.string().optional(),
  }),
  correctAnswers: z.array(z.enum(["a", "b", "c", "d", "e"])).min(1),
  explanation: z.string().optional(),
});

// Validation schema for essay
const EssaySchema = z.object({
  type: z.literal("essay"),
  suggestedAnswer: z.string().optional(),
  evaluationCriteria: z.array(z.string()).optional(),
});

// Validation schema for assertion-reason
const AssertionReasonItemSchema = z.object({
  id: z.number(),
  assertion: z.string().min(1, "Asserção é obrigatória"),
  isTrue: z.boolean(),
  reason: z.string().min(1, "Razão é obrigatória"),
  reasonCorrect: z.boolean(),
});

const AssertionReasonSchema = z.object({
  type: z.literal("assertion_reason"),
  items: z.array(AssertionReasonItemSchema).min(1, "Pelo menos um item é obrigatório"),
  explanation: z.string().optional(),
});

// Union of all question content schemas
const QuestionContentSchema = z.union([
  MultipleChoiceSchema,
  ComplexMultipleChoiceSchema,
  EssaySchema,
  AssertionReasonSchema,
]);

// Base schema for all questions
const CreateQuestionSchema = z.object({
  statement: z.string().min(1, "Enunciado é obrigatório"),
  discipline: z.string().min(1, "Disciplina é obrigatória"),
  subject: z.string().min(1, "Assunto é obrigatório"),
  difficulty: DifficultyEnum.optional(),
  tags: z.array(z.string()).optional(),
  content: QuestionContentSchema,
  imageBase64Array: z.array(z.string()).optional(), // Base64 encoded images
});

type CreateQuestionInput = z.infer<typeof CreateQuestionSchema>;

export const questionsRouter = router({
  /**
   * Create a new question with optional images
   */
  create: protectedProcedure
    .input(CreateQuestionSchema)
    .mutation(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new Error("Database not available");

      // Enforce question count limit based on plan
      const userRow = await db.select({ plan: users.plan }).from(users).where(eq(users.id, ctx.user.id)).limit(1);
      const plan = (userRow[0]?.plan ?? "free") as PlanKey;
      const maxQ = PLAN_LIMITS[plan].maxQuestions;
      if (maxQ > 0) {
        const countResult = await db.select({ value: count() }).from(questions).where(eq(questions.userId, ctx.user.id));
        if ((countResult[0]?.value ?? 0) >= maxQ) {
          throw new TRPCError({ code: "FORBIDDEN", message: `Seu plano ${plan === "free" ? "gratuito" : plan} permite no máximo ${maxQ} questões. Faça upgrade para continuar.` });
        }
      }

      // Initialize quota if needed
      await initializeUserStorageQuota(ctx.user.id);

      // Check storage quota if images are being uploaded
      if (input.imageBase64Array && input.imageBase64Array.length > 0) {
        const quotaInfo = await getUserStorageQuota(ctx.user.id);

        for (const imageBase64 of input.imageBase64Array) {
          const imageBuffer = Buffer.from(imageBase64, "base64");
          const imageSizeBytes = imageBuffer.length;

          if (quotaInfo.remainingMB * 1024 * 1024 < imageSizeBytes) {
            throw new Error(
              `Imagem muito grande. Você tem apenas ${quotaInfo.remainingMB.toFixed(1)}MB disponível.`
            );
          }
        }
      }

      // Create question
      const result = await db.insert(questions).values({
        userId: ctx.user.id,
        type: input.content.type,
        statement: input.statement,
        discipline: input.discipline,
        subject: input.subject,
        difficulty: input.difficulty,
        tags: input.tags?.join(","),
        content: JSON.stringify(input.content),
        sourceFormat: "manual",
        totalImages: input.imageBase64Array?.length ?? 0,
      });

      const raw = result as any;
      const questionId = raw?.insertId ?? raw?.[0]?.insertId ?? Number(raw?.[0]?.lastInsertRowid) ?? null;

      // Upload images if provided
      let imageUrls: string[] = [];
      if (input.imageBase64Array && input.imageBase64Array.length > 0) {
        for (const imageBase64 of input.imageBase64Array) {
          try {
            const imageBuffer = Buffer.from(imageBase64, "base64");
            const savedImage = await saveImageLocally(
              imageBuffer,
              `question-${questionId}-image.jpg`,
              "image/jpeg",
              ctx.user.id,
              questionId
            );

            imageUrls.push(getImageUrl(savedImage.storageKey));

            // Update quota
            await updateUserStorageUsage(ctx.user.id, savedImage.fileSize);
          } catch (error) {
            console.error("Image upload error:", error);
            // Don't fail the entire question creation, but continue
          }
        }
      }

      return {
        success: true,
        questionId,
        imageUrls,
      };
    }),

  /**
   * Get user's storage quota info
   */
  getStorageQuota: protectedProcedure.query(async ({ ctx }) => {
    return await getUserStorageQuota(ctx.user.id);
  }),

  /**
   * List all questions for the user with filtering
   */
  list: protectedProcedure
    .input(
      z.object({
        discipline: z.string().optional(),
        subject: z.string().optional(),
        type: z.string().optional(),
        difficulty: z.string().optional(),
      })
    )
    .query(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) return [];

      let result = await db
        .select()
        .from(questions)
        .where(eq(questions.userId, ctx.user.id));

      // Apply filters
      if (input.discipline) {
        result = result.filter((q: any) => q.discipline === input.discipline);
      }
      if (input.subject) {
        result = result.filter((q: any) => q.subject === input.subject);
      }
      if (input.type) {
        result = result.filter((q: any) => q.type === input.type);
      }
      if (input.difficulty) {
        result = result.filter((q: any) => q.difficulty === input.difficulty);
      }

      return result.map((q: any) => ({
        ...q,
        content: q.content ? JSON.parse(q.content) : null,
        tags: q.tags ? q.tags.split(",") : [],
      }));
    }),

  /**
   * Get a specific question by ID
   */
  get: protectedProcedure
    .input(z.object({ id: z.number() }))
    .query(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) return null;

      const result = await db
        .select()
        .from(questions)
        .where(and(eq(questions.id, input.id), eq(questions.userId, ctx.user.id)))
        .limit(1);

      if (!result.length) return null;

      const q = result[0];

      // Get associated images
      const images = await db
        .select()
        .from(questionImages)
        .where(eq(questionImages.questionId, input.id));

      return {
        ...q,
        content: q.content ? JSON.parse(q.content) : null,
        tags: q.tags ? q.tags.split(",") : [],
        images: images.map((img: any) => ({
          storageKey: img.storageKey,
          url: getImageUrl(img.storageKey),
          originalFileName: img.originalFileName,
        })),
      };
    }),

  /**
   * Update an existing question (auto-saves version snapshot before writing)
   */
  update: protectedProcedure
    .input(
      CreateQuestionSchema.extend({
        id: z.number(),
        removeImageKeys: z.array(z.string()).optional(),
        changeSummary: z.string().optional(),
      })
    )
    .mutation(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new Error("Database not available");

      // Verify ownership
      const existing = await db
        .select()
        .from(questions)
        .where(and(eq(questions.id, input.id), eq(questions.userId, ctx.user.id)))
        .limit(1);

      if (!existing.length) {
        throw new Error("Unauthorized");
      }

      // Save version snapshot before updating
      const existingVersions = await db
        .select()
        .from(questionVersions)
        .where(eq(questionVersions.questionId, input.id));

      const nextVersion = existingVersions.length + 1;
      await db.insert(questionVersions).values({
        questionId: input.id,
        userId: ctx.user.id,
        versionNumber: nextVersion,
        snapshot: JSON.stringify({
          statement: existing[0].statement,
          discipline: existing[0].discipline,
          subject: existing[0].subject,
          difficulty: existing[0].difficulty,
          tags: existing[0].tags,
          type: existing[0].type,
          content: existing[0].content,
        }),
        changeSummary: input.changeSummary ?? null,
      });

      // Remove images if specified
      if (input.removeImageKeys && input.removeImageKeys.length > 0) {
        for (const storageKey of input.removeImageKeys) {
          try {
            await deleteImage(storageKey);
          } catch (error) {
            console.error("Error deleting image:", error);
          }
        }
      }

      // Upload new images if provided
      let newImageCount = existing[0].totalImages ?? 0;
      if (input.imageBase64Array && input.imageBase64Array.length > 0) {
        for (const imageBase64 of input.imageBase64Array) {
          try {
            const imageBuffer = Buffer.from(imageBase64, "base64");
            const savedImage = await saveImageLocally(
              imageBuffer,
              `question-${input.id}-image.jpg`,
              "image/jpeg",
              ctx.user.id,
              input.id
            );

            newImageCount++;
            await updateUserStorageUsage(ctx.user.id, savedImage.fileSize);
          } catch (error) {
            console.error("Image upload error:", error);
          }
        }
      }

      await db
        .update(questions)
        .set({
          statement: input.statement,
          discipline: input.discipline,
          subject: input.subject,
          difficulty: input.difficulty,
          tags: input.tags?.join(","),
          type: input.content.type,
          content: JSON.stringify(input.content),
          totalImages: newImageCount,
          updatedAt: new Date(),
        })
        .where(eq(questions.id, input.id));

      return { success: true };
    }),

  /**
   * Delete a question and its images
   */
  delete: protectedProcedure
    .input(z.object({ id: z.number() }))
    .mutation(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new Error("Database not available");

      // Verify ownership
      const existing = await db
        .select()
        .from(questions)
        .where(and(eq(questions.id, input.id), eq(questions.userId, ctx.user.id)))
        .limit(1);

      if (!existing.length) {
        throw new Error("Unauthorized");
      }

      // Delete associated images
      const images = await db
        .select()
        .from(questionImages)
        .where(eq(questionImages.questionId, input.id));

      for (const image of images) {
        try {
          await deleteImage(image.storageKey);
        } catch (error) {
          console.error("Error deleting image:", error);
        }
      }

      // Delete question
      await db.delete(questions).where(eq(questions.id, input.id));

      return { success: true };
    }),

  /**
   * Search questions by query
   */
  search: protectedProcedure
    .input(z.object({ query: z.string() }))
    .query(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) return [];

      let result = await db
        .select()
        .from(questions)
        .where(eq(questions.userId, ctx.user.id));

      const searchQuery = input.query.toLowerCase();
      result = result.filter(
        (q: any) =>
          q.statement.toLowerCase().includes(searchQuery) ||
          q.discipline?.toLowerCase().includes(searchQuery) ||
          q.subject?.toLowerCase().includes(searchQuery) ||
          q.tags?.toLowerCase().includes(searchQuery)
      );

      return result.map((q: any) => ({
        ...q,
        content: q.content ? JSON.parse(q.content) : null,
        tags: q.tags ? q.tags.split(",") : [],
      }));
    }),

  /**
   * Get unique disciplines and subjects
   */
  getDisciplinesAndSubjects: protectedProcedure.query(async ({ ctx }) => {
    const db = await getDb();
    if (!db) return { disciplines: [], subjects: [] };

    const result = await db
      .select()
      .from(questions)
      .where(eq(questions.userId, ctx.user.id));

    const disciplines = Array.from(
      new Set(result.map((q: any) => q.discipline).filter(Boolean))
    );
    const subjects = Array.from(new Set(result.map((q: any) => q.subject).filter(Boolean)));

    return { disciplines, subjects };
  }),

  /**
   * Export questions in specified format
   */
  export: protectedProcedure
    .input(
      z.object({
        format: z.enum(["xlsx", "json"]),
        questionIds: z.array(z.number()).optional(),
      })
    )
    .mutation(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new Error("Database not available");

      let result = await db
        .select()
        .from(questions)
        .where(eq(questions.userId, ctx.user.id));

      if (input.questionIds) {
        result = result.filter((q: any) => input.questionIds!.includes(q.id));
      }

      if (input.format === "xlsx") {
        const ExcelJS = await import("exceljs");
        const workbook = new ExcelJS.Workbook();
        const worksheet = workbook.addWorksheet("Questões");

        worksheet.columns = [
          { header: "Tipo", key: "type", width: 20 },
          { header: "Disciplina", key: "discipline", width: 20 },
          { header: "Assunto", key: "subject", width: 20 },
          { header: "Enunciado", key: "statement", width: 50 },
          { header: "Dificuldade", key: "difficulty", width: 12 },
        ];

        result.forEach((q: any) => {
          worksheet.addRow({
            type: q.type,
            discipline: q.discipline,
            subject: q.subject,
            statement: q.statement,
            difficulty: q.difficulty,
          });
        });

        const buffer = await workbook.xlsx.writeBuffer();
        return {
          success: true,
          data: Buffer.from(buffer as ArrayBuffer).toString("base64"),
          fileName: `questoes-${Date.now()}.xlsx`,
        };
      }

      // JSON export
      return {
        success: true,
        data: JSON.stringify(
          result.map((q: any) => ({
            ...q,
            content: q.content ? JSON.parse(q.content) : null,
            tags: q.tags ? q.tags.split(",") : [],
          }))
        ),
        fileName: `questoes-${Date.now()}.json`,
      };
    }),

  /**
   * Bulk delete questions
   */
  bulkDelete: protectedProcedure
    .input(z.object({ ids: z.array(z.number()) }))
    .mutation(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new Error("Database not available");

      const targetImages = await db
        .select()
        .from(questionImages)
        .where(
          and(
            eq(questionImages.userId, ctx.user.id),
            inArray(questionImages.questionId, input.ids)
          )
        );

      for (const image of targetImages) {
        try {
          await deleteImage(image.storageKey);
        } catch (error) {
          console.error("Error deleting image:", error);
        }
      }

      await db
        .delete(questions)
        .where(
          and(
            eq(questions.userId, ctx.user.id),
            inArray(questions.id, input.ids)
          )
        );

      return { success: true };
    }),

  /**
   * Batch update common metadata fields for multiple questions
   */
  batchUpdate: protectedProcedure
    .input(
      z.object({
        ids: z.array(z.number()).min(1),
        discipline: z.string().optional(),
        subject: z.string().optional(),
        difficulty: DifficultyEnum.optional(),
        tags: z.array(z.string()).optional(),
      })
    )
    .mutation(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new Error("Database not available");

      const updateData: Record<string, unknown> = { updatedAt: new Date() };
      if (input.discipline !== undefined) updateData.discipline = input.discipline;
      if (input.subject !== undefined) updateData.subject = input.subject;
      if (input.difficulty !== undefined) updateData.difficulty = input.difficulty;
      if (input.tags !== undefined) updateData.tags = input.tags.join(",");

      await db
        .update(questions)
        .set(updateData)
        .where(
          and(
            eq(questions.userId, ctx.user.id),
            inArray(questions.id, input.ids)
          )
        );

      return { success: true, updatedCount: input.ids.length };
    }),

  /**
   * Duplicate a question (clones statement, content and metadata as a new question)
   */
  duplicate: protectedProcedure
    .input(z.object({ id: z.number() }))
    .mutation(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new Error("Database not available");

      const result = await db
        .select()
        .from(questions)
        .where(and(eq(questions.id, input.id), eq(questions.userId, ctx.user.id)))
        .limit(1);

      if (!result.length) throw new Error("Questão não encontrada");

      const src = result[0];
      const inserted = await db.insert(questions).values({
        userId: ctx.user.id,
        type: src.type,
        statement: `${src.statement} (cópia)`,
        discipline: src.discipline,
        subject: src.subject,
        difficulty: src.difficulty,
        tags: src.tags,
        content: src.content,
        sourceFormat: src.sourceFormat ?? "manual",
        totalImages: 0,
      });

      const _ins = inserted as any;
      return { success: true, newId: _ins?.insertId ?? _ins?.[0]?.insertId ?? (_ins?.lastInsertRowid != null ? Number(_ins.lastInsertRowid) : null) };
    }),

  /**
   * Get version history for a question
   */
  getVersionHistory: protectedProcedure
    .input(z.object({ questionId: z.number() }))
    .query(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) return [];

      // Verify ownership
      const owned = await db
        .select()
        .from(questions)
        .where(and(eq(questions.id, input.questionId), eq(questions.userId, ctx.user.id)))
        .limit(1);

      if (!owned.length) return [];

      const versions = await db
        .select()
        .from(questionVersions)
        .where(eq(questionVersions.questionId, input.questionId))
        .orderBy(desc(questionVersions.versionNumber));

      return versions.map((v: any) => ({
        id: v.id,
        versionNumber: v.versionNumber,
        changeSummary: v.changeSummary,
        createdAt: v.createdAt,
        snapshot: v.snapshot ? JSON.parse(v.snapshot) : null,
      }));
    }),

  /**
   * Revert a question to a specific version snapshot
   */
  revertToVersion: protectedProcedure
    .input(z.object({ questionId: z.number(), versionId: z.number() }))
    .mutation(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new Error("Database not available");

      // Verify ownership
      const owned = await db
        .select()
        .from(questions)
        .where(and(eq(questions.id, input.questionId), eq(questions.userId, ctx.user.id)))
        .limit(1);

      if (!owned.length) throw new Error("Unauthorized");

      const versionResult = await db
        .select()
        .from(questionVersions)
        .where(
          and(
            eq(questionVersions.id, input.versionId),
            eq(questionVersions.questionId, input.questionId)
          )
        )
        .limit(1);

      if (!versionResult.length) throw new Error("Versão não encontrada");

      const snap = JSON.parse(versionResult[0].snapshot);

      // Save current state as a new version before reverting
      const allVersions = await db
        .select()
        .from(questionVersions)
        .where(eq(questionVersions.questionId, input.questionId));

      await db.insert(questionVersions).values({
        questionId: input.questionId,
        userId: ctx.user.id,
        versionNumber: allVersions.length + 1,
        snapshot: JSON.stringify({
          statement: owned[0].statement,
          discipline: owned[0].discipline,
          subject: owned[0].subject,
          difficulty: owned[0].difficulty,
          tags: owned[0].tags,
          type: owned[0].type,
          content: owned[0].content,
        }),
        changeSummary: `Revertido para versão ${versionResult[0].versionNumber}`,
      });

      await db
        .update(questions)
        .set({
          statement: snap.statement,
          discipline: snap.discipline,
          subject: snap.subject,
          difficulty: snap.difficulty,
          tags: snap.tags,
          type: snap.type,
          content: snap.content,
          updatedAt: new Date(),
        })
        .where(eq(questions.id, input.questionId));

      return { success: true };
    }),
});
