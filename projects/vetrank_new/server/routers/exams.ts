import { TRPCError } from "@trpc/server";
import { z } from "zod";
import {
  completeExam,
  countRandomQuestions,
  createExam,
  getExamAnswers,
  getExamById,
  getQuestionById,
  getRandomQuestions,
  getUserExams,
  submitExamAnswer,
  updateLoginStreak,
  getDb,
} from "../db";
import { protectedProcedure, publicProcedure, router } from "../_core/trpc";
import { eq, and, like, or, isNull, sql } from "drizzle-orm";
import { exams, questions } from "../../drizzle/schema";
import { invokeLLM } from "../_core/llm";

export const examsRouter = router({
  countForExam: publicProcedure
    .input(
      z.object({
        disciplineIds: z.array(z.number()).optional(),
        subjectId: z.number().optional(),
        author: z.string().optional(),
        year: z.number().optional(),
        difficulty: z.enum(["easy", "medium", "hard", "mixed"]).default("mixed"),
      })
    )
    .query(async ({ input }) => {
      const count = await countRandomQuestions(input);
      return { count };
    }),

  start: protectedProcedure
    .input(
      z.object({
        disciplineId: z.number().optional(),
        disciplineIds: z.array(z.number()).optional(),
        subjectId: z.number().optional(),
        author: z.string().optional(),
        year: z.number().optional(),
        difficulty: z.enum(["easy", "medium", "hard", "mixed"]).default("mixed"),
        questionCount: z.number().min(5).max(100).default(20),
        timeLimitMinutes: z.number().min(5).max(180).optional(),
      })
    )
    .mutation(async ({ input, ctx }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR", message: "Database not available" });

      const randomQuestions = await getRandomQuestions({
        disciplineId: input.disciplineId,
        disciplineIds: input.disciplineIds,
        subjectId: input.subjectId,
        author: input.author,
        year: input.year,
        difficulty: input.difficulty,
        count: input.questionCount,
      });

      if (randomQuestions.length === 0)
        throw new TRPCError({ code: "NOT_FOUND", message: "No questions found for the selected filters" });

      // Determine sequence number for Simulado Personalizado
      const userExams = await db
        .select({ id: exams.id })
        .from(exams)
        .where(
          and(
            eq(exams.userId, ctx.user.id),
            or(isNull(exams.title), like(exams.title, "Simulado Personalizado%"))
          )
        );
      const nextNum = userExams.length + 1;
      const title = `Simulado Personalizado #${nextNum}`;

      const timeLimitSeconds = input.timeLimitMinutes ? input.timeLimitMinutes * 60 : undefined;
      const examId = await createExam({
        userId: ctx.user.id,
        title,
        disciplineId: input.disciplineId,
        difficulty: input.difficulty,
        questionCount: input.questionCount,
        timeLimitSeconds,
        questionIds: randomQuestions.map((q) => q.id),
      });

      // Update login streak
      await updateLoginStreak(ctx.user.id);

      return { examId, questions: randomQuestions, title };
    }),

  // ── Simulado para um concurso do Radar ────────────────────────────────────
  // Distribui as questões pelas áreas que costumam cair em cada tipo de concurso de veterinário
  // e dá preferência a questões da mesma banca, quando houver no banco.
  startConcurso: protectedProcedure
    .input(z.object({
      slug: z.string().max(160),
      questionCount: z.number().min(10).max(100).default(40),
      timeLimitMinutes: z.number().min(10).max(300).default(120),
    }))
    .mutation(async ({ input, ctx }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
      const { disciplines } = await import("../../drizzle/schema");
      const { buscarConcursoPorSlug } = await import("../concursos");
      const c = await buscarConcursoPorSlug(input.slug);
      if (!c) throw new TRPCError({ code: "NOT_FOUND", message: "Concurso não encontrado" });

      const alvo = `${c.orgao} ${((c.cargos as any[]) ?? []).map(x => x.nome).join(" ")}`.toLowerCase();
      const perfil =
        c.area === "zootecnia" ? "zootecnia" :
        /biot[eé]rio|experimenta|universidade|instituto federal|usp|unesp|unicamp|fiocruz|butantan/.test(alvo) ? "pesquisa" :
        /mapa|agropecu|adapar|idaf|iagro|cidasc|indea|adab|defesa|inspe[cç]|fiscal|auditor|sanit[aá]ri/.test(alvo) ? "defesa" :
        /ex[eé]rcito|marinha|aeron[aá]utica|for[cç]as armadas|pol[ií]cia|bombeiro|militar|distrito naval/.test(alvo) ? "militar" :
        "municipal";
      const PESOS: Record<string, Record<string, number>> = {
        municipal: { "medicina-preventiva": 0.35, "clinica-cirurgia": 0.30, "patobiologia": 0.20, "etica-humanidades": 0.10, "zootecnia": 0.05 },
        defesa:    { "medicina-preventiva": 0.45, "patobiologia": 0.25, "zootecnia": 0.10, "clinica-cirurgia": 0.10, "etica-humanidades": 0.10 },
        militar:   { "clinica-cirurgia": 0.35, "medicina-preventiva": 0.30, "patobiologia": 0.20, "ciencias-biologicas": 0.10, "etica-humanidades": 0.05 },
        pesquisa:  { "ciencias-biologicas": 0.30, "patobiologia": 0.30, "clinica-cirurgia": 0.20, "medicina-preventiva": 0.15, "etica-humanidades": 0.05 },
        zootecnia: { "zootecnia": 0.60, "ciencias-biologicas": 0.15, "medicina-preventiva": 0.15, "etica-humanidades": 0.10 },
      };
      const discRows = await db.select({ id: disciplines.id, slug: disciplines.slug }).from(disciplines);
      const idPorSlug = new Map(discRows.map(d => [d.slug, d.id]));

      const escolhidas: any[] = [];
      const usados = new Set<number>();
      // 1) mesma banca (até 30%)
      if (c.banca) {
        const daBanca = await db.select().from(questions)
          .where(and(eq(questions.active, true), like(questions.banca, `%${c.banca.split(/[\s(-]/)[0]}%`)))
          .orderBy(sql`RAND()`).limit(Math.floor(input.questionCount * 0.3));
        for (const q of daBanca) { escolhidas.push(q); usados.add(q.id); }
      }
      // 2) cota por área
      const restante = input.questionCount - escolhidas.length;
      for (const [slug, peso] of Object.entries(PESOS[perfil])) {
        const did = idPorSlug.get(slug);
        if (!did) continue;
        const qs = await getRandomQuestions({ disciplineId: did, count: Math.round(restante * peso), difficulty: "mixed", excludeIds: Array.from(usados) });
        for (const q of qs) { if (!usados.has(q.id)) { escolhidas.push(q); usados.add(q.id); } }
      }
      // 3) completa se alguma área não tinha questões suficientes
      if (escolhidas.length < input.questionCount) {
        const extra = await getRandomQuestions({ count: input.questionCount - escolhidas.length, difficulty: "mixed", excludeIds: Array.from(usados) });
        escolhidas.push(...extra.filter(q => !usados.has(q.id)));
      }
      if (escolhidas.length === 0) throw new TRPCError({ code: "NOT_FOUND", message: "Sem questões disponíveis" });
      const lista = escolhidas.slice(0, input.questionCount).sort(() => Math.random() - 0.5);

      const title = `Simulado — ${c.orgao}${c.uf ? ` (${c.uf})` : ""}`.slice(0, 250);
      const examId = await createExam({
        userId: ctx.user.id,
        title,
        difficulty: "mixed",
        questionCount: lista.length,
        timeLimitSeconds: input.timeLimitMinutes * 60,
        questionIds: lista.map(q => q.id),
      });
      await updateLoginStreak(ctx.user.id);
      return { examId, questions: lista, title, perfil, timeLimitMinutes: input.timeLimitMinutes };
    }),

  startAiStandard: protectedProcedure
    .input(
      z.object({
        examType: z.enum(["ENARE", "USP", "UNESP", "UFMG", "UFRJ", "Geral"]),
        questionCount: z.number().min(5).max(100).default(40),
        timeLimitMinutes: z.number().min(5).max(300).optional(),
      })
    )
    .mutation(async ({ input, ctx }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR", message: "Database not available" });

      // 1. Get the sequential number for this specific examType
      const userExams = await db
        .select({ id: exams.id })
        .from(exams)
        .where(
          and(
            eq(exams.userId, ctx.user.id),
            like(exams.title, `Simulado ${input.examType}%`)
          )
        );
      const nextNum = userExams.length + 1;

      // 2. Fetch candidate questions from database
      const candidates = await db
        .select({
          id: questions.id,
          disciplineId: questions.disciplineId,
          subjectId: questions.subjectId,
          difficulty: questions.difficulty,
          author: questions.author,
          banca: questions.banca,
          instituicao: questions.instituicao,
          carreira: questions.carreira,
          textPt: questions.textPt,
        })
        .from(questions)
        .where(eq(questions.active, true))
        .orderBy(sql`RAND()`)
        .limit(Math.max(input.questionCount * 4, 200));

      if (candidates.length === 0) {
        throw new TRPCError({ code: "NOT_FOUND", message: "Nenhuma questão ativa encontrada no banco" });
      }

      // Filter candidates in JS based on examType (more robust than Drizzle dialect-specific queries)
      let filteredCandidates = candidates.filter(q => {
        if (input.examType === "Geral") {
          return (q.carreira && /resid[eê]ncia/i.test(q.carreira));
        }
        const term = input.examType.toLowerCase();
        return (
          (q.author && q.author.toLowerCase().includes(term)) ||
          (q.banca && q.banca.toLowerCase().includes(term)) ||
          (q.instituicao && q.instituicao.toLowerCase().includes(term)) ||
          (q.carreira && q.carreira.toLowerCase().includes(term))
        );
      });

      // If we don't have enough matching questions, pad with remaining random questions
      if (filteredCandidates.length < input.questionCount) {
        const remaining = candidates.filter(q => !filteredCandidates.some(fc => fc.id === q.id));
        filteredCandidates = [...filteredCandidates, ...remaining].slice(0, input.questionCount * 2 + 10);
      }

      // 3. Call AI to choose/filter the best questions and construct a structured template name
      const candidatesMeta = filteredCandidates.map(c => ({
        id: c.id,
        disciplineId: c.disciplineId,
        difficulty: c.difficulty,
        author: c.author || c.banca || c.instituicao || "Geral",
        textSnippet: c.textPt.slice(0, 80).replace(/\r?\n|\r/g, " ") + "...",
      }));

      let examTitle = `Simulado ${input.examType} #${nextNum}`;
      let selectedIds = filteredCandidates.slice(0, input.questionCount).map(c => c.id);

      try {
        const response = await invokeLLM({
          messages: [
            {
              role: "system",
              content: `Você é um coordenador pedagógico e especialista em exames e residências de Medicina Veterinária. Sua tarefa é selecionar as melhores questões para compor um simulado padrão do tipo: "${input.examType}" contendo exatamente ${input.questionCount} questões.
              
              DIRETRIZES DE SELEÇÃO:
              1. Selecione exatamente ${input.questionCount} IDs das opções fornecidas.
              2. Priorize as questões que melhor correspondam à temática ou origem do exame.
              3. Garanta diversidade de assuntos das disciplinas clínicas da veterinária.
              
              NUMERAÇÃO E TÍTULO:
              - Este simulado é o número #${nextNum} do tipo "${input.examType}" criado para este usuário.
              - Crie um título profissional, motivador e numerado em português do Brasil, no formato: "Simulado ${input.examType} #${nextNum} - [Subtítulo Temático]".
              
              Retorne um objeto JSON contendo:
              - "title": O título formatado e numerado (ex: "Simulado ENARE #${nextNum} - Clínica Geral e Patologia")
              - "selectedQuestionIds": Um array com exatamente ${input.questionCount} números correspondentes aos IDs selecionados.`
            },
            {
              role: "user",
              content: `Aqui estão as candidatas de questões (ID, Área, Dificuldade, Autor/Banca, Trecho):
              ${JSON.stringify(candidatesMeta, null, 2)}`
            }
          ],
          responseFormat: { type: "json_object" }
        });

        const content = response.choices[0].message.content;
        if (typeof content === "string") {
          const aiResult = JSON.parse(content);
          if (aiResult.title) examTitle = aiResult.title;
          if (Array.isArray(aiResult.selectedQuestionIds) && aiResult.selectedQuestionIds.length === input.questionCount) {
            selectedIds = aiResult.selectedQuestionIds.map((val: any) => Number(val));
          }
        }
      } catch (aiErr) {
        console.error("[AI Exam Generator] Fallback due to LLM error:", aiErr);
      }

      // Fetch the actual full question models for the selected IDs in order
      const actualQuestions = await Promise.all(selectedIds.map((id) => getQuestionById(id)));
      const filteredQuestions = actualQuestions.filter(Boolean);

      if (filteredQuestions.length === 0) {
        throw new TRPCError({ code: "NOT_FOUND", message: "Nenhuma questão pôde ser carregada para o simulado" });
      }

      const timeLimitSeconds = input.timeLimitMinutes ? input.timeLimitMinutes * 60 : undefined;

      // 4. Create the exam record in database
      const examId = await createExam({
        userId: ctx.user.id,
        title: examTitle,
        questionCount: filteredQuestions.length,
        timeLimitSeconds,
        questionIds: filteredQuestions.map((q) => q!.id),
      });

      // Update user login streak
      await updateLoginStreak(ctx.user.id);

      return { examId, questions: filteredQuestions, title: examTitle };
    }),

  getById: protectedProcedure
    .input(z.object({ id: z.number() }))
    .query(async ({ input, ctx }) => {
      const exam = await getExamById(input.id);
      if (!exam) throw new TRPCError({ code: "NOT_FOUND" });
      if (exam.userId !== ctx.user.id && ctx.user.role !== "admin")
        throw new TRPCError({ code: "FORBIDDEN" });

      const questionIds = exam.questionIds as number[];
      const questions = await Promise.all(questionIds.map((id) => getQuestionById(id)));
      const answers = await getExamAnswers(input.id);

      return { exam, questions: questions.filter(Boolean), answers };
    }),

  submitAnswer: protectedProcedure
    .input(
      z.object({
        examId: z.number(),
        questionId: z.number(),
        selectedOption: z.string(),
        timeSpentSeconds: z.number().optional(),
      })
    )
    .mutation(async ({ input, ctx }) => {
      const exam = await getExamById(input.examId);
      if (!exam) throw new TRPCError({ code: "NOT_FOUND" });
      if (exam.userId !== ctx.user.id) throw new TRPCError({ code: "FORBIDDEN" });
      if (exam.status !== "in_progress") throw new TRPCError({ code: "BAD_REQUEST", message: "Exam already completed" });

      const question = await getQuestionById(input.questionId);
      if (!question) throw new TRPCError({ code: "NOT_FOUND" });

      const isCorrect = question.correctOption === input.selectedOption;
      await submitExamAnswer({
        examId: input.examId,
        questionId: input.questionId,
        selectedOption: input.selectedOption,
        isCorrect,
        timeSpentSeconds: input.timeSpentSeconds,
      });

      return { isCorrect, correctOption: question.correctOption };
    }),

  complete: protectedProcedure
    .input(z.object({ examId: z.number(), timeSpentSeconds: z.number() }))
    .mutation(async ({ input, ctx }) => {
      const exam = await getExamById(input.examId);
      if (!exam) throw new TRPCError({ code: "NOT_FOUND" });
      if (exam.userId !== ctx.user.id) throw new TRPCError({ code: "FORBIDDEN" });

      const result = await completeExam(input.examId, ctx.user.id, input.timeSpentSeconds, ctx.user.role);
      return result;
    }),

  history: protectedProcedure
    .input(z.object({ limit: z.number().default(20) }))
    .query(async ({ input, ctx }) => {
      return getUserExams(ctx.user.id, input.limit);
    }),

  myHistory: protectedProcedure
    .input(z.object({ limit: z.number().default(20) }))
    .query(async ({ input, ctx }) => {
      return getUserExams(ctx.user.id, input.limit);
    }),

  results: protectedProcedure
    .input(z.object({ examId: z.number() }))
    .query(async ({ input, ctx }) => {
      const exam = await getExamById(input.examId);
      if (!exam) throw new TRPCError({ code: "NOT_FOUND" });
      if (exam.userId !== ctx.user.id && ctx.user.role !== "admin")
        throw new TRPCError({ code: "FORBIDDEN" });

      const questionIds = exam.questionIds as number[];
      const [questions, answers] = await Promise.all([
        Promise.all(questionIds.map((id) => getQuestionById(id))),
        getExamAnswers(input.examId),
      ]);

      return { exam, questions: questions.filter(Boolean), answers };
    }),
});
