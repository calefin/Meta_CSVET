import { TRPCError } from "@trpc/server";
import { z } from "zod";
import { and, asc, desc, eq, gte, inArray, like, lte, sql } from "drizzle-orm";
import { getDb } from "../db";
import {
  courses, courseModules, courseLessons,
  lessonVideos, lessonEbooks, lessonQuestionsConfig,
  coursePurchases, courseProgress, lessonQuestionAttempts,
  courseCertificates, questions, users,
} from "../../drizzle/schema";
import { adminProcedure, protectedProcedure, publicProcedure, router } from "../_core/trpc";
import { MercadoPagoConfig, Preference } from "mercadopago";
import crypto from "crypto";

// ─── DB helper ─────────────────────────────────────────────────────────────────
async function db() {
  const d = await getDb();
  if (!d) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR", message: "DB unavailable" });
  return d;
}

// ─── Access check helper ───────────────────────────────────────────────────────
async function assertCourseAccess(userId: number, courseId: number) {
  const d = await db();
  const [purchase] = await d
    .select({ id: coursePurchases.id })
    .from(coursePurchases)
    .where(and(
      eq(coursePurchases.userId, userId),
      eq(coursePurchases.courseId, courseId),
      eq(coursePurchases.status, "active"),
    ))
    .limit(1);
  if (!purchase) throw new TRPCError({ code: "FORBIDDEN", message: "Curso não adquirido." });
  return true;
}

// ─── Mercado Pago client ───────────────────────────────────────────────────────
function getMPClient() {
  const token = process.env.MP_ACCESS_TOKEN;
  if (!token) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR", message: "MP_ACCESS_TOKEN não configurado" });
  return new MercadoPagoConfig({ accessToken: token });
}

// ─── Build ordered course structure (modules + lessons) ───────────────────────
async function getCourseStructure(courseId: number) {
  const d = await db();
  const mods = await d
    .select()
    .from(courseModules)
    .where(eq(courseModules.courseId, courseId))
    .orderBy(asc(courseModules.order));

  const lessons = await d
    .select()
    .from(courseLessons)
    .where(eq(courseLessons.courseId, courseId))
    .orderBy(asc(courseLessons.order));

  return mods.map(mod => ({
    ...mod,
    lessons: lessons.filter(l => l.moduleId === mod.id),
  }));
}

// ─── Compute course completion % for a user ────────────────────────────────────
async function getCourseCompletion(userId: number, courseId: number) {
  const d = await db();
  const allLessons = await d
    .select({ id: courseLessons.id, isRequired: courseLessons.isRequired })
    .from(courseLessons)
    .where(and(eq(courseLessons.courseId, courseId)));

  if (!allLessons.length) return 0;

  const required = allLessons.filter(l => l.isRequired);
  if (!required.length) return 0;

  const completed = await d
    .select({ lessonId: courseProgress.lessonId })
    .from(courseProgress)
    .where(and(
      eq(courseProgress.userId, userId),
      eq(courseProgress.courseId, courseId),
      eq(courseProgress.status, "completed"),
      inArray(courseProgress.lessonId, required.map(l => l.id)),
    ));

  return Math.round((completed.length / required.length) * 100);
}

// ═══════════════════════════════════════════════════════════════════════════════
// ROUTER
// ═══════════════════════════════════════════════════════════════════════════════

export const coursesRouter = router({

  // ─── PUBLIC: Catálogo ────────────────────────────────────────────────────────
  list: publicProcedure
    .input(z.object({
      search:   z.string().optional(),
      category: z.string().optional(),
      level:    z.enum(["beginner", "intermediate", "advanced"]).optional(),
      minPrice: z.number().optional(),
      maxPrice: z.number().optional(),
      limit:    z.number().min(1).max(50).default(20),
      offset:   z.number().min(0).default(0),
    }))
    .query(async ({ input }) => {
      const d = await db();
      const conds = [eq(courses.status, "published"), eq(courses.active, true)];

      if (input.search) conds.push(like(courses.title, `%${input.search}%`));
      if (input.category) conds.push(eq(courses.category, input.category));
      if (input.level)    conds.push(eq(courses.level, input.level));
      if (input.minPrice != null) conds.push(gte(courses.price, String(input.minPrice)));
      if (input.maxPrice != null) conds.push(lte(courses.price, String(input.maxPrice)));

      const rows = await d
        .select({
          id: courses.id,
          slug: courses.slug,
          title: courses.title,
          thumbnailUrl: courses.thumbnailUrl,
          instructorName: courses.instructorName,
          instructorAvatarUrl: courses.instructorAvatarUrl,
          level: courses.level,
          category: courses.category,
          durationHours: courses.durationHours,
          price: courses.price,
        })
        .from(courses)
        .where(and(...conds))
        .orderBy(desc(courses.createdAt))
        .limit(input.limit)
        .offset(input.offset);

      return rows;
    }),

  // ─── PUBLIC: Detalhe do curso ────────────────────────────────────────────────
  getBySlug: publicProcedure
    .input(z.object({ slug: z.string() }))
    .query(async ({ input }) => {
      const d = await db();
      const [course] = await d
        .select()
        .from(courses)
        .where(and(eq(courses.slug, input.slug), eq(courses.active, true)))
        .limit(1);

      if (!course) throw new TRPCError({ code: "NOT_FOUND", message: "Curso não encontrado." });

      const structure = await getCourseStructure(course.id);

      // Total de aulas para exibir no card de detalhes
      const totalLessons = structure.reduce((acc, m) => acc + m.lessons.length, 0);

      return { ...course, modules: structure, totalLessons };
    }),

  // ─── PUBLIC: Verificar certificado ──────────────────────────────────────────
  verifyCertificate: publicProcedure
    .input(z.object({ code: z.string() }))
    .query(async ({ input }) => {
      const d = await db();
      const [cert] = await d
        .select({
          id: courseCertificates.id,
          validationCode: courseCertificates.validationCode,
          issuedAt: courseCertificates.issuedAt,
          userName: users.name,
          userCpf: users.cpf,
          courseTitle: courses.title,
          courseDurationHours: courses.durationHours,
          instructorName: courses.instructorName,
        })
        .from(courseCertificates)
        .innerJoin(users, eq(users.id, courseCertificates.userId))
        .innerJoin(courses, eq(courses.id, courseCertificates.courseId))
        .where(eq(courseCertificates.validationCode, input.code))
        .limit(1);

      if (!cert) return { valid: false };

      // Mascarar CPF: XXX.***.***-XX
      const cpfMasked = cert.userCpf
        ? cert.userCpf.replace(/^(\d{3})\.\d{3}\.\d{3}(-\d{2})$/, "$1.***.***$2")
        : null;

      return {
        valid: true,
        studentName: cert.userName,
        cpfMasked,
        courseTitle: cert.courseTitle,
        durationHours: cert.courseDurationHours,
        instructorName: cert.instructorName,
        issuedAt: cert.issuedAt,
        validationCode: cert.validationCode,
      };
    }),

  // ─── PROTECTED: Verificar acesso ao curso ────────────────────────────────────
  checkAccess: protectedProcedure
    .input(z.object({ courseId: z.number() }))
    .query(async ({ ctx, input }) => {
      const d = await db();
      const [purchase] = await d
        .select({ status: coursePurchases.status, purchasedAt: coursePurchases.purchasedAt })
        .from(coursePurchases)
        .where(and(
          eq(coursePurchases.userId, ctx.user.id),
          eq(coursePurchases.courseId, input.courseId),
        ))
        .limit(1);

      return {
        hasAccess: purchase?.status === "active",
        status: purchase?.status ?? null,
        purchasedAt: purchase?.purchasedAt ?? null,
      };
    }),

  // ─── PROTECTED: Criar preference MP para compra de curso ─────────────────────
  createPreference: protectedProcedure
    .input(z.object({
      courseId:   z.number(),
      successUrl: z.string().url(),
      failureUrl: z.string().url(),
      pendingUrl: z.string().url(),
    }))
    .mutation(async ({ ctx, input }) => {
      const d = await db();

      const [course] = await d
        .select({ id: courses.id, title: courses.title, price: courses.price, status: courses.status })
        .from(courses)
        .where(and(eq(courses.id, input.courseId), eq(courses.active, true)))
        .limit(1);

      if (!course || course.status !== "published") {
        throw new TRPCError({ code: "NOT_FOUND", message: "Curso não encontrado ou não disponível." });
      }

      // Verificar se já comprou
      const [existing] = await d
        .select({ status: coursePurchases.status })
        .from(coursePurchases)
        .where(and(
          eq(coursePurchases.userId, ctx.user.id),
          eq(coursePurchases.courseId, input.courseId),
          eq(coursePurchases.status, "active"),
        ))
        .limit(1);

      if (existing) throw new TRPCError({ code: "CONFLICT", message: "Você já possui acesso a este curso." });

      const [u] = await d
        .select({ name: users.name, email: users.email, cpf: users.cpf })
        .from(users)
        .where(eq(users.id, ctx.user.id))
        .limit(1);

      const client = getMPClient();
      const preference = new Preference(client);
      const price = Number(course.price);

      const result = await preference.create({
        body: {
          items: [{
            id: `course_${course.id}`,
            title: course.title,
            quantity: 1,
            unit_price: price,
            currency_id: "BRL",
          }],
          payer: {
            name: u?.name ?? undefined,
            email: u?.email ?? undefined,
            identification: u?.cpf ? { type: "CPF", number: u.cpf.replace(/\D/g, "") } : undefined,
          },
          external_reference: JSON.stringify({ userId: ctx.user.id, courseId: course.id, type: "course" }),
          back_urls: {
            success: input.successUrl,
            failure: input.failureUrl,
            pending: input.pendingUrl,
          },
          auto_return: "approved",
          notification_url: `${process.env.SERVER_URL ?? process.env.VITE_SERVER_URL ?? ""}/api/mp/webhook`,
        },
      });

      // Criar registro pending no banco
      await d.insert(coursePurchases).values({
        userId: ctx.user.id,
        courseId: course.id,
        pricePaid: String(price) as any,
        status: "pending",
      });

      return {
        checkoutUrl: result.init_point,
        sandboxUrl: result.sandbox_init_point,
      };
    }),

  // ─── PROTECTED: Dados do ambiente de estudo ──────────────────────────────────
  getStudyData: protectedProcedure
    .input(z.object({ courseId: z.number() }))
    .query(async ({ ctx, input }) => {
      await assertCourseAccess(ctx.user.id, input.courseId);

      const d = await db();
      const [course] = await d
        .select()
        .from(courses)
        .where(eq(courses.id, input.courseId))
        .limit(1);

      if (!course) throw new TRPCError({ code: "NOT_FOUND" });

      const structure = await getCourseStructure(input.courseId);

      // Progresso de todas as etapas
      const progressRows = await d
        .select()
        .from(courseProgress)
        .where(and(
          eq(courseProgress.userId, ctx.user.id),
          eq(courseProgress.courseId, input.courseId),
        ));

      const progressMap = new Map(progressRows.map(p => [p.lessonId, p]));

      // Enriquecer cada lesson com status de progresso + dados específicos do tipo
      const modulesWithProgress = await Promise.all(
        structure.map(async (mod) => ({
          ...mod,
          lessons: await Promise.all(
            mod.lessons.map(async (lesson) => {
              const progress = progressMap.get(lesson.id);

              let typeData: Record<string, unknown> = {};
              if (lesson.type === "video") {
                const [v] = await d.select().from(lessonVideos).where(eq(lessonVideos.lessonId, lesson.id)).limit(1);
                if (v) typeData = { videoUrl: v.videoUrl, provider: v.provider, durationSeconds: v.durationSeconds };
              } else if (lesson.type === "ebook") {
                const ebooks = await d.select().from(lessonEbooks)
                  .where(eq(lessonEbooks.lessonId, lesson.id))
                  .orderBy(asc(lessonEbooks.order));
                typeData = { ebooks: ebooks.map(e => ({ id: e.id, fileName: e.fileName, fileUrl: e.fileUrl, allowDownload: e.allowDownload, order: e.order })) };
              } else if (lesson.type === "questions") {
                const [cfg] = await d.select().from(lessonQuestionsConfig)
                  .where(eq(lessonQuestionsConfig.lessonId, lesson.id)).limit(1);
                if (cfg) typeData = { minQuestions: cfg.minQuestions, minPassingScore: cfg.minPassingScore };
              }

              return {
                ...lesson,
                progress: {
                  status: progress?.status ?? "not_started",
                  watchedPercent: progress?.watchedPercent ?? 0,
                  completedAt: progress?.completedAt ?? null,
                },
                ...typeData,
              };
            })
          ),
        }))
      );

      const completionPercent = await getCourseCompletion(ctx.user.id, input.courseId);

      return {
        course,
        modules: modulesWithProgress,
        completionPercent,
      };
    }),

  // ─── PROTECTED: Progresso geral do usuário no curso ──────────────────────────
  getProgress: protectedProcedure
    .input(z.object({ courseId: z.number() }))
    .query(async ({ ctx, input }) => {
      await assertCourseAccess(ctx.user.id, input.courseId);
      const d = await db();

      const progressRows = await d
        .select()
        .from(courseProgress)
        .where(and(
          eq(courseProgress.userId, ctx.user.id),
          eq(courseProgress.courseId, input.courseId),
        ));

      const completionPercent = await getCourseCompletion(ctx.user.id, input.courseId);

      return { progress: progressRows, completionPercent };
    }),

  // ─── PROTECTED: Atualizar progresso de vídeo ─────────────────────────────────
  markVideoProgress: protectedProcedure
    .input(z.object({
      lessonId:       z.number(),
      courseId:       z.number(),
      watchedPercent: z.number().min(0).max(100),
    }))
    .mutation(async ({ ctx, input }) => {
      await assertCourseAccess(ctx.user.id, input.courseId);

      const d = await db();
      const isCompleted = input.watchedPercent >= 80;

      await d.execute(sql`
        INSERT INTO course_progress (userId, courseId, lessonId, status, watchedPercent, completedAt, createdAt, updatedAt)
        VALUES (
          ${ctx.user.id}, ${input.courseId}, ${input.lessonId},
          ${isCompleted ? "completed" : "in_progress"},
          ${input.watchedPercent},
          ${isCompleted ? new Date() : null},
          NOW(), NOW()
        )
        ON DUPLICATE KEY UPDATE
          watchedPercent = GREATEST(watchedPercent, VALUES(watchedPercent)),
          status = CASE WHEN status = 'completed' THEN 'completed'
                        WHEN VALUES(watchedPercent) >= 80 THEN 'completed'
                        ELSE 'in_progress' END,
          completedAt = CASE WHEN status = 'completed' THEN completedAt
                             WHEN VALUES(watchedPercent) >= 80 THEN NOW()
                             ELSE NULL END,
          updatedAt = NOW()
      `);

      return { completed: isCompleted };
    }),

  // ─── PROTECTED: Submeter tentativa de quiz ────────────────────────────────────
  submitQuizAttempt: protectedProcedure
    .input(z.object({
      lessonId:  z.number(),
      courseId:  z.number(),
      answers:   z.record(z.string(), z.string()), // { questionId: selectedOption }
    }))
    .mutation(async ({ ctx, input }) => {
      await assertCourseAccess(ctx.user.id, input.courseId);

      const d = await db();

      // Buscar config da aula
      const [cfg] = await d
        .select()
        .from(lessonQuestionsConfig)
        .where(eq(lessonQuestionsConfig.lessonId, input.lessonId))
        .limit(1);

      if (!cfg) throw new TRPCError({ code: "NOT_FOUND", message: "Configuração de quiz não encontrada." });

      // Buscar questões respondidas para validar gabarito
      const questionIds = Object.keys(input.answers).map(Number);
      if (!questionIds.length) throw new TRPCError({ code: "BAD_REQUEST", message: "Nenhuma resposta enviada." });

      const questionsData = await d
        .select({ id: questions.id, correctOption: questions.correctOption })
        .from(questions)
        .where(inArray(questions.id, questionIds));

      const correctMap = new Map(questionsData.map(q => [q.id, q.correctOption]));
      let correct = 0;
      for (const [qIdStr, selected] of Object.entries(input.answers)) {
        if (correctMap.get(Number(qIdStr)) === selected) correct++;
      }

      const total = questionIds.length;
      const score = total > 0 ? Math.round((correct / total) * 100) : 0;
      const passed = score >= cfg.minPassingScore;

      // Registrar tentativa
      await d.insert(lessonQuestionAttempts).values({
        userId: ctx.user.id,
        lessonId: input.lessonId,
        courseId: input.courseId,
        questionsJson: questionIds,
        answersJson: input.answers,
        totalQuestions: total,
        correctAnswers: correct,
        score,
        passed,
        startedAt: new Date(),
        finishedAt: new Date(),
      });

      // Se aprovado, marcar aula como concluída
      if (passed) {
        await d.execute(sql`
          INSERT INTO course_progress (userId, courseId, lessonId, status, watchedPercent, completedAt, createdAt, updatedAt)
          VALUES (${ctx.user.id}, ${input.courseId}, ${input.lessonId}, 'completed', 0, NOW(), NOW(), NOW())
          ON DUPLICATE KEY UPDATE status = 'completed', completedAt = COALESCE(completedAt, NOW()), updatedAt = NOW()
        `);
      }

      return { score, correct, total, passed, minPassingScore: cfg.minPassingScore };
    }),

  // ─── PROTECTED: Questões de uma aula (quiz) ───────────────────────────────────
  getLessonQuestions: protectedProcedure
    .input(z.object({ lessonId: z.number(), courseId: z.number() }))
    .query(async ({ ctx, input }) => {
      await assertCourseAccess(ctx.user.id, input.courseId);

      const d = await db();
      const [cfg] = await d
        .select()
        .from(lessonQuestionsConfig)
        .where(eq(lessonQuestionsConfig.lessonId, input.lessonId))
        .limit(1);

      if (!cfg) throw new TRPCError({ code: "NOT_FOUND" });

      const filters = (cfg.filtersJson ?? {}) as Record<string, unknown>;
      const conds = [eq(questions.active, true), eq(questions.status, "approved")];

      if (filters.disciplineId) conds.push(eq(questions.disciplineId, filters.disciplineId as number));
      if (filters.subjectId)    conds.push(eq(questions.subjectId, filters.subjectId as number));
      if (filters.topicId)      conds.push(eq(questions.topicId, filters.topicId as number));
      if (filters.difficulty)   conds.push(eq(questions.difficulty, filters.difficulty as any));
      if (filters.banca)        conds.push(eq(questions.banca, filters.banca as string));

      const rows = await d
        .select({
          id: questions.id,
          textPt: questions.textPt,
          options: questions.options,
          correctOption: questions.correctOption,
          explanationPt: questions.explanationPt,
          difficulty: questions.difficulty,
          questionType: questions.questionType,
          modelId: questions.modelId,
          imageUrl: questions.imageUrl,
          formatData: questions.formatData,
        })
        .from(questions)
        .where(and(...conds))
        .orderBy(sql`RAND()`)
        .limit(cfg.minQuestions);

      return rows;
    }),

  // ─── PROTECTED: Obter/emitir certificado ─────────────────────────────────────
  getCertificate: protectedProcedure
    .input(z.object({ courseId: z.number() }))
    .mutation(async ({ ctx, input }) => {
      await assertCourseAccess(ctx.user.id, input.courseId);

      const d = await db();

      // Verificar se já existe
      const [existing] = await d
        .select()
        .from(courseCertificates)
        .where(and(
          eq(courseCertificates.userId, ctx.user.id),
          eq(courseCertificates.courseId, input.courseId),
        ))
        .limit(1);

      if (existing) return { certificate: existing, alreadyIssued: true };

      // Verificar conclusão 100%
      const completionPercent = await getCourseCompletion(ctx.user.id, input.courseId);
      if (completionPercent < 100) {
        throw new TRPCError({
          code: "PRECONDITION_FAILED",
          message: `Curso não concluído (${completionPercent}% completo). Conclua todas as etapas obrigatórias.`,
        });
      }

      // Gerar código único
      const validationCode = crypto.randomUUID();
      const hash = crypto.createHash("sha256")
        .update(`${ctx.user.id}-${input.courseId}-${validationCode}-${Date.now()}`)
        .digest("hex");

      const [inserted] = await d
        .insert(courseCertificates)
        .values({ userId: ctx.user.id, courseId: input.courseId, validationCode, hash })
        .$returningId();

      const [cert] = await d
        .select()
        .from(courseCertificates)
        .where(eq(courseCertificates.id, inserted.id))
        .limit(1);

      return { certificate: cert, alreadyIssued: false };
    }),

  // ═══════════════════════════════════════════════════════════════════════════════
  // ADMIN
  // ═══════════════════════════════════════════════════════════════════════════════

  admin: router({

    // ─── Listar todos os cursos (rascunho + publicado) ────────────────────────
    listAll: adminProcedure.query(async () => {
      const d = await db();
      return d
        .select({
          id: courses.id,
          slug: courses.slug,
          title: courses.title,
          status: courses.status,
          level: courses.level,
          category: courses.category,
          price: courses.price,
          durationHours: courses.durationHours,
          instructorName: courses.instructorName,
          createdAt: courses.createdAt,
        })
        .from(courses)
        .where(eq(courses.active, true))
        .orderBy(desc(courses.createdAt));
    }),

    // ─── Buscar curso completo (com estrutura) ────────────────────────────────
    getOne: adminProcedure
      .input(z.object({ id: z.number() }))
      .query(async ({ input }) => {
        const d = await db();
        const [course] = await d.select().from(courses).where(eq(courses.id, input.id)).limit(1);
        if (!course) throw new TRPCError({ code: "NOT_FOUND" });
        const structure = await getCourseStructure(input.id);
        return { ...course, modules: structure };
      }),

    // ─── Criar / Atualizar curso ──────────────────────────────────────────────
    upsert: adminProcedure
      .input(z.object({
        id:                   z.number().optional(),
        slug:                 z.string().min(3).max(128),
        title:                z.string().min(3).max(256),
        description:          z.string().optional(),
        objectives:           z.string().optional(),
        prerequisites:        z.string().optional(),
        thumbnailUrl:         z.string().url().optional().or(z.literal("")),
        trailerUrl:           z.string().url().optional().or(z.literal("")),
        instructorName:       z.string().optional(),
        instructorBio:        z.string().optional(),
        instructorAvatarUrl:    z.string().url().optional().or(z.literal("")),
        instructorSignatureUrl: z.string().url().optional().or(z.literal("")),
        level:                  z.enum(["beginner", "intermediate", "advanced"]),
        category:             z.string().optional(),
        durationHours:        z.number().min(0).default(0),
        price:                z.number().min(0),
        status:               z.enum(["draft", "published"]),
      }))
      .mutation(async ({ ctx, input }) => {
        const d = await db();
        const { id, ...data } = input;
        const vals = {
          ...data,
          price: String(data.price) as any,
          thumbnailUrl: data.thumbnailUrl || null,
          trailerUrl: data.trailerUrl || null,
          instructorAvatarUrl: data.instructorAvatarUrl || null,
          instructorSignatureUrl: data.instructorSignatureUrl || null,
        };

        if (id) {
          await d.update(courses).set(vals).where(eq(courses.id, id));
          return { id };
        }
        const [res] = await d.insert(courses).values({ ...vals, createdBy: ctx.user.id }).$returningId();
        return { id: res.id };
      }),

    // ─── Desativar curso (soft delete) ────────────────────────────────────────
    deactivate: adminProcedure
      .input(z.object({ id: z.number() }))
      .mutation(async ({ input }) => {
        const d = await db();
        await d.update(courses).set({ active: false }).where(eq(courses.id, input.id));
        return { success: true };
      }),

    // ─── Criar / Atualizar módulo ─────────────────────────────────────────────
    upsertModule: adminProcedure
      .input(z.object({
        id:       z.number().optional(),
        courseId: z.number(),
        title:    z.string().min(1).max(256),
        order:    z.number().min(1),
      }))
      .mutation(async ({ input }) => {
        const d = await db();
        const { id, ...data } = input;
        if (id) {
          await d.update(courseModules).set(data).where(eq(courseModules.id, id));
          return { id };
        }
        const [res] = await d.insert(courseModules).values(data).$returningId();
        return { id: res.id };
      }),

    // ─── Deletar módulo (e suas aulas) ────────────────────────────────────────
    deleteModule: adminProcedure
      .input(z.object({ id: z.number() }))
      .mutation(async ({ input }) => {
        const d = await db();
        // Deletar aulas do módulo primeiro
        const lessons = await d
          .select({ id: courseLessons.id })
          .from(courseLessons)
          .where(eq(courseLessons.moduleId, input.id));
        for (const l of lessons) {
          await d.delete(lessonVideos).where(eq(lessonVideos.lessonId, l.id));
          await d.delete(lessonEbooks).where(eq(lessonEbooks.lessonId, l.id));
          await d.delete(lessonQuestionsConfig).where(eq(lessonQuestionsConfig.lessonId, l.id));
          await d.delete(courseLessons).where(eq(courseLessons.id, l.id));
        }
        await d.delete(courseModules).where(eq(courseModules.id, input.id));
        return { success: true };
      }),

    // ─── Criar / Atualizar aula ───────────────────────────────────────────────
    upsertLesson: adminProcedure
      .input(z.object({
        id:         z.number().optional(),
        moduleId:   z.number(),
        courseId:   z.number(),
        title:      z.string().min(1).max(256),
        type:       z.enum(["video", "ebook", "questions"]),
        order:      z.number().min(1),
        isRequired: z.boolean().default(true),
      }))
      .mutation(async ({ input }) => {
        const d = await db();
        const { id, ...data } = input;
        if (id) {
          await d.update(courseLessons).set(data).where(eq(courseLessons.id, id));
          return { id };
        }
        const [res] = await d.insert(courseLessons).values(data).$returningId();
        return { id: res.id };
      }),

    // ─── Deletar aula ─────────────────────────────────────────────────────────
    deleteLesson: adminProcedure
      .input(z.object({ id: z.number() }))
      .mutation(async ({ input }) => {
        const d = await db();
        await d.delete(lessonVideos).where(eq(lessonVideos.lessonId, input.id));
        await d.delete(lessonEbooks).where(eq(lessonEbooks.lessonId, input.id));
        await d.delete(lessonQuestionsConfig).where(eq(lessonQuestionsConfig.lessonId, input.id));
        await d.delete(courseLessons).where(eq(courseLessons.id, input.id));
        return { success: true };
      }),

    // ─── Configurar vídeo de uma aula ─────────────────────────────────────────
    setLessonVideo: adminProcedure
      .input(z.object({
        lessonId:        z.number(),
        videoUrl:        z.string().url(),
        provider:        z.enum(["vimeo", "panda", "bunny", "youtube", "other"]),
        durationSeconds: z.number().min(0).default(0),
      }))
      .mutation(async ({ input }) => {
        const d = await db();
        const { lessonId, ...data } = input;
        const [existing] = await d.select({ id: lessonVideos.id }).from(lessonVideos)
          .where(eq(lessonVideos.lessonId, lessonId)).limit(1);
        if (existing) {
          await d.update(lessonVideos).set(data).where(eq(lessonVideos.lessonId, lessonId));
        } else {
          await d.insert(lessonVideos).values({ lessonId, ...data });
        }
        return { success: true };
      }),

    // ─── Adicionar ebook a uma aula ───────────────────────────────────────────
    addLessonEbook: adminProcedure
      .input(z.object({
        lessonId:      z.number(),
        fileUrl:       z.string().url(),
        fileName:      z.string().min(1).max(256),
        allowDownload: z.boolean().default(false),
        order:         z.number().min(1).default(1),
      }))
      .mutation(async ({ input }) => {
        const d = await db();
        const [res] = await d.insert(lessonEbooks).values(input).$returningId();
        return { id: res.id };
      }),

    // ─── Atualizar ebook ──────────────────────────────────────────────────────
    updateLessonEbook: adminProcedure
      .input(z.object({
        id:            z.number(),
        fileName:      z.string().min(1).max(256).optional(),
        allowDownload: z.boolean().optional(),
        order:         z.number().min(1).optional(),
      }))
      .mutation(async ({ input }) => {
        const d = await db();
        const { id, ...data } = input;
        await d.update(lessonEbooks).set(data).where(eq(lessonEbooks.id, id));
        return { success: true };
      }),

    // ─── Deletar ebook ────────────────────────────────────────────────────────
    deleteLessonEbook: adminProcedure
      .input(z.object({ id: z.number() }))
      .mutation(async ({ input }) => {
        const d = await db();
        await d.delete(lessonEbooks).where(eq(lessonEbooks.id, input.id));
        return { success: true };
      }),

    // ─── Configurar quiz de questões ──────────────────────────────────────────
    setLessonQuestionsConfig: adminProcedure
      .input(z.object({
        lessonId:        z.number(),
        filtersJson:     z.record(z.string(), z.unknown()),
        minQuestions:    z.number().min(1).default(10),
        minPassingScore: z.number().min(0).max(100).default(70),
      }))
      .mutation(async ({ input }) => {
        const d = await db();
        const { lessonId, ...data } = input;
        const [existing] = await d.select({ id: lessonQuestionsConfig.id })
          .from(lessonQuestionsConfig)
          .where(eq(lessonQuestionsConfig.lessonId, lessonId)).limit(1);
        if (existing) {
          await d.update(lessonQuestionsConfig).set(data).where(eq(lessonQuestionsConfig.lessonId, lessonId));
        } else {
          await d.insert(lessonQuestionsConfig).values({ lessonId, ...data });
        }
        return { success: true };
      }),

    // ─── Listar alunos matriculados ───────────────────────────────────────────
    getStudents: adminProcedure
      .input(z.object({ courseId: z.number() }))
      .query(async ({ input }) => {
        const d = await db();
        return d
          .select({
            purchaseId:  coursePurchases.id,
            userId:      users.id,
            userName:    users.name,
            userEmail:   users.email,
            pricePaid:   coursePurchases.pricePaid,
            status:      coursePurchases.status,
            purchasedAt: coursePurchases.purchasedAt,
          })
          .from(coursePurchases)
          .innerJoin(users, eq(users.id, coursePurchases.userId))
          .where(eq(coursePurchases.courseId, input.courseId))
          .orderBy(desc(coursePurchases.purchasedAt));
      }),

    // ─── Conceder acesso manualmente ──────────────────────────────────────────
    grantAccess: adminProcedure
      .input(z.object({ userId: z.number(), courseId: z.number() }))
      .mutation(async ({ input }) => {
        const d = await db();
        await d.execute(sql`
          INSERT INTO course_purchases (userId, courseId, pricePaid, status, purchasedAt, createdAt, updatedAt)
          VALUES (${input.userId}, ${input.courseId}, '0.00', 'active', NOW(), NOW(), NOW())
          ON DUPLICATE KEY UPDATE status = 'active', updatedAt = NOW()
        `);
        return { success: true };
      }),

    // ─── Revogar acesso ───────────────────────────────────────────────────────
    revokeAccess: adminProcedure
      .input(z.object({ userId: z.number(), courseId: z.number() }))
      .mutation(async ({ input }) => {
        const d = await db();
        await d.update(coursePurchases)
          .set({ status: "cancelled" })
          .where(and(
            eq(coursePurchases.userId, input.userId),
            eq(coursePurchases.courseId, input.courseId),
          ));
        return { success: true };
      }),
  }),
});
