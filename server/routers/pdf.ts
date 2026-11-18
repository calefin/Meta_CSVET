import { z } from "zod";
import { protectedProcedure, router } from "../_core/trpc";
import { getDb } from "../db";
import { exams, examQuestions, questions as questionsTable, examScores, questionImages } from "../db";
import { eq, inArray } from "drizzle-orm";
import { exec } from "child_process";
import { promisify } from "util";
import path from "path";
import { storagePut } from "../storage";
import QRCode from "qrcode";
import { generateAnswerSheetSVG } from "../../client/src/lib/answerSheetSvg";
import type { AnswerSheetConfig } from "@shared/_core/answerSheetTypes";
import { getImageFromStorage } from "../_core/imageStorage";

const PYTHON_SCRIPT = path.join(import.meta.dirname, "../pdf-generator.py");

const execAsync = promisify(exec);

function buildAnswerSheetConfig(
  exam: any,
  examQuestionsResult: any[],
  questionsResult: any[],
): AnswerSheetConfig {
  const questionsList: any[] = [];
  let currentBlock: any = null;

  examQuestionsResult.forEach((eq: any, index: number) => {
    const q = questionsResult.find((qu: any) => qu.id === eq.questionId);
    if (!q) return;

    const qType = (q.type === "multiple_choice" || q.type === "multiple_choice_complex") ? "multiple_choice" : "short_response";
    let choices: string[] = ["A", "B", "C", "D", "E"];
    if ((q.type === "multiple_choice" || q.type === "multiple_choice_complex") && q.alternatives) {
      try {
        const alts = JSON.parse(q.alternatives);
        if (Array.isArray(alts) && alts.length > 0) {
          choices = Array.from({ length: alts.length }, (_, i) => String.fromCharCode(65 + i));
        }
      } catch (_) {}
    }

    if (currentBlock && currentBlock.type === qType && JSON.stringify(currentBlock.choices) === JSON.stringify(choices)) {
      currentBlock.count += 1;
    } else {
      if (currentBlock) questionsList.push(currentBlock);
      currentBlock = {
        id: `block-${index}`,
        type: qType,
        count: 1,
        startNumber: index + 1,
        choices,
      };
    }
  });

  if (currentBlock) questionsList.push(currentBlock);

  return {
    header: {
      fields: [
        { id: "name",  defaultLabel: "Name",  displayLabel: "Nome",  enabled: true,  width: "large" },
        { id: "class", defaultLabel: "Class", displayLabel: "Turma", enabled: true,  width: "medium" },
        { id: "date",  defaultLabel: "Date",  displayLabel: "Data",  enabled: true,  width: "medium" },
      ],
      includeStudentId: !!exam.classId,
      studentIdDigits: 5,
      studentIdLabel: "Matrícula",
      includeKeyVersion: exam.numberOfVersions > 1,
      keyVersionLetters: Array.from({ length: exam.numberOfVersions }, (_, i) => String.fromCharCode(65 + i)).join(""),
      keyVersionLabel: "Versão",
    },
    questions: questionsList,
  };
}

async function generateAnswerSheetSvgBase64(
  exam: any,
  answerSheetConfig: AnswerSheetConfig,
): Promise<string> {
  const formId = String(exam.id);
  const qrData = `https://prof-aid.com/r/${formId}`;
  const qrSvg = await QRCode.toString(qrData, { type: "svg", width: 100, margin: 0 });
  const qrDataUri = `data:image/svg+xml;base64,${Buffer.from(qrSvg).toString("base64")}`;

  const svg = generateAnswerSheetSVG(exam.name, answerSheetConfig, {
    standalone: true,
    formId,
    inlineQrDataUri: qrDataUri,
  });

  return Buffer.from(svg).toString("base64");
}

async function fetchPdfData(
  db: any,
  exam: any,
  examQuestionsResult: any[],
  input: { fontSize?: string; margins?: string },
) {
  const questionIds = examQuestionsResult.map((eq: any) => eq.questionId);
  let questionsResult: any[] = [];
  let questionsImagesResult: any[] = [];

  if (questionIds.length > 0) {
    const allQuestions = await db.select().from(questionsTable).limit(1000);
    questionsResult = (allQuestions as any[]).filter((q: any) => questionIds.includes(q.id));
    
    // Fetch image metadata for these questions
    questionsImagesResult = await db
      .select()
      .from(questionImages)
      .where(inArray(questionImages.questionId, questionIds));
  }

  const scoresResult = await db
    .select()
    .from(examScores)
    .where(eq(examScores.examId, exam.id));
  const pointsMap: Record<number, string> = {};
  for (const s of scoresResult as any[]) {
    pointsMap[s.questionId] = s.points;
  }

  const answerSheetConfig = buildAnswerSheetConfig(exam, examQuestionsResult, questionsResult);
  const answerSheetSvgB64 = await generateAnswerSheetSvgBase64(exam, answerSheetConfig);

  // Map question results with fallbacks for manual creations and base64 images
  const mappedQuestions = [];
  for (const q of questionsResult) {
    let alternatives: string[] = [];
    let correctAnswer = q.correctAnswer;
    let correctAnswers: string[] = [];

    // Parse content JSON column
    let contentObj: any = null;
    if (q.content) {
      try {
        contentObj = JSON.parse(q.content);
      } catch (_) {}
    }

    // Try to load alternatives
    if (q.alternatives) {
      try {
        alternatives = JSON.parse(q.alternatives);
      } catch (_) {}
    } else if (contentObj && contentObj.alternatives) {
      if (Array.isArray(contentObj.alternatives)) {
        alternatives = contentObj.alternatives;
      } else if (typeof contentObj.alternatives === "object") {
        const keys = Object.keys(contentObj.alternatives).sort();
        alternatives = keys.map((k) => contentObj.alternatives[k]);
      }
    }

    // Try to load correct answers
    if (contentObj && contentObj.correctAnswers) {
      correctAnswers = contentObj.correctAnswers.map((a: string) => a.toUpperCase());
    } else if (correctAnswer) {
      correctAnswers = [correctAnswer.toUpperCase()];
    } else if (contentObj && contentObj.correctAnswer) {
      correctAnswers = [contentObj.correctAnswer.toUpperCase()];
    }

    // Load base64 image data from local storage
    const qImages = questionsImagesResult.filter((img: any) => img.questionId === q.id);
    const images: string[] = [];
    for (const img of qImages) {
      try {
        const fileData = await getImageFromStorage(img.storageKey);
        if (fileData) {
          images.push(fileData.buffer.toString("base64"));
        }
      } catch (err) {
        console.error("Error reading image content for PDF:", err);
      }
    }

    mappedQuestions.push({
      id: q.id,
      type: q.type,
      statement: q.statement,
      alternatives,
      correctAnswer: correctAnswers.join(""),
      correctAnswers,
      points: pointsMap[q.id] ?? null,
      images,
      content: contentObj,
    });
  }

  return {
    exam: {
      name: exam.name,
      course: exam.course,
      class: exam.class,
      campus: exam.campus,
      discipline: exam.discipline,
      professor: exam.professor,
      examDate: exam.examDate ? new Date(exam.examDate).toLocaleDateString("pt-BR") : null,
      period: (exam as any).period ?? null,
      instructions: (exam as any).instructions ?? null,
    },
    questions: mappedQuestions,
    answerSheetSvg: answerSheetSvgB64,
    examId: exam.id,
    fontSize: input.fontSize ?? (exam as any).printFontSize ?? "normal",
    margins: input.margins ?? (exam as any).printMargins ?? "normal",
    logoUrl: null,
  };
}

export const pdfRouter = router({
  generate: protectedProcedure
    .input(z.object({
      examId: z.number(),
      fontSize: z.enum(["small", "normal", "large"]).optional(),
      margins: z.enum(["compact", "normal", "wide"]).optional(),
      pdfLayout: z.enum(["merged", "separated", "questions_only", "answer_sheet_only"]).default("merged"),
    }))
    .mutation(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new Error("Database not available");

      const examResult = await db
        .select()
        .from(exams)
        .where(eq(exams.id, input.examId))
        .limit(1);

      if (!examResult.length || examResult[0].userId !== ctx.user.id) {
        throw new Error("Unauthorized");
      }

      const exam = examResult[0];

      const examQuestionsResult = await db
        .select()
        .from(examQuestions)
        .where(eq(examQuestions.examId, input.examId));

      if (examQuestionsResult.length === 0) throw new Error("No questions in exam");

      const pdfData = await fetchPdfData(db, exam, examQuestionsResult, input);
      const pythonScript = PYTHON_SCRIPT;
      const uploadedPdfs = [];

      if (input.pdfLayout === "separated") {
        // 1. Gera o PDF contendo APENAS as Questões
        const payloadQ = {
          ...pdfData,
          pdfLayout: "questions_only",
          numberOfVersions: exam.numberOfVersions,
          shuffleMode: (exam as any).shuffleMode ?? "all",
        };
        const { stdout: stdoutQ } = await execAsync(
          `python3 "${pythonScript}" '${JSON.stringify(payloadQ).replace(/'/g, "'\\''")}'`
        );
        const resultQ = JSON.parse(stdoutQ);
        if (!resultQ.success) throw new Error(resultQ.error || "Questions PDF generation failed");

        // 2. Gera o PDF contendo APENAS o Gabarito (Folha de Respostas) e as Instruções
        const payloadA = {
          ...pdfData,
          pdfLayout: "answer_sheet_only",
          numberOfVersions: exam.numberOfVersions,
          shuffleMode: (exam as any).shuffleMode ?? "all",
        };
        const { stdout: stdoutA } = await execAsync(
          `python3 "${pythonScript}" '${JSON.stringify(payloadA).replace(/'/g, "'\\''")}'`
        );
        const resultA = JSON.parse(stdoutA);
        if (!resultA.success) throw new Error(resultA.error || "Answer Sheet PDF generation failed");

        // Upload de ambos para o storage
        for (const pdf of resultQ.pdfs) {
          const buffer = Buffer.from(pdf.data, "base64");
          const key = `exams/${ctx.user.id}/${input.examId}/v${pdf.version}_questoes.pdf`;
          const { url } = await storagePut(key, buffer, "application/pdf");
          uploadedPdfs.push({
            version: pdf.version,
            url,
            filename: `prova_v${pdf.version}_questoes.pdf`,
            layoutType: "questions"
          });
        }

        for (const pdf of resultA.pdfs) {
          const buffer = Buffer.from(pdf.data, "base64");
          const key = `exams/${ctx.user.id}/${input.examId}/v${pdf.version}_gabarito.pdf`;
          const { url } = await storagePut(key, buffer, "application/pdf");
          uploadedPdfs.push({
            version: pdf.version,
            url,
            filename: `prova_v${pdf.version}_gabarito.pdf`,
            layoutType: "answer_sheet"
          });
        }
      } else {
        // Comportamento normal com o pdfLayout selecionado ("merged", "questions_only", "answer_sheet_only")
        const payload = {
          ...pdfData,
          pdfLayout: input.pdfLayout,
          numberOfVersions: exam.numberOfVersions,
          shuffleMode: (exam as any).shuffleMode ?? "all",
        };

        const { stdout } = await execAsync(
          `python3 "${pythonScript}" '${JSON.stringify(payload).replace(/'/g, "'\\''")}'`
        );

        const result = JSON.parse(stdout);
        if (!result.success) throw new Error(result.error || "PDF generation failed");

        for (const pdf of result.pdfs) {
          const buffer = Buffer.from(pdf.data, "base64");
          const key = `exams/${ctx.user.id}/${input.examId}/v${pdf.version}.pdf`;
          const { url } = await storagePut(key, buffer, "application/pdf");
          uploadedPdfs.push({
            version: pdf.version,
            url,
            filename: pdf.filename,
            layoutType: input.pdfLayout
          });
        }
      }

      return { success: true, pdfs: uploadedPdfs };
    }),

  generateInline: protectedProcedure
    .input(z.object({
      exam: z.object({
        name: z.string(),
        discipline: z.string().optional(),
        professor: z.string().optional(),
        examDate: z.string().optional(),
        course: z.string().optional(),
        class: z.string().optional(),
        campus: z.string().optional(),
        period: z.string().optional(),
        instructions: z.string().optional(),
      }),
      questions: z.array(z.object({
        type: z.enum(["multiple_choice", "essay"]).default("multiple_choice"),
        statement: z.string(),
        alternatives: z.array(z.string()),
        correctAnswer: z.string().nullable(),
        points: z.number(),
        images: z.array(z.string()).optional(),
      })),
      answerSheetConfig: z.any(),
      numberOfVersions: z.number().int().min(1).max(4).default(1),
      shuffleMode: z.enum(["all", "questions", "items", "none"]).default("none"),
    }))
    .mutation(async ({ input }) => {
      const svg = generateAnswerSheetSVG(
        input.exam.name,
        input.answerSheetConfig as AnswerSheetConfig,
        { standalone: true },
      );
      const answerSheetSvgB64 = Buffer.from(svg).toString("base64");

      const payload = {
        exam: input.exam,
        questions: input.questions.map(q => ({
          ...q,
          images: q.images || [],
        })),
        answerSheetSvg: answerSheetSvgB64,
        examId: null,
        numberOfVersions: input.numberOfVersions,
        shuffleMode: input.shuffleMode,
      };

      const { stdout } = await execAsync(
        `python3 "${PYTHON_SCRIPT}" '${JSON.stringify(payload).replace(/'/g, "'\\''")}'`
      );

      const result = JSON.parse(stdout);
      if (!result.success) throw new Error(result.error || "PDF generation failed");

      return {
        success: true,
        pdfs: result.pdfs as Array<{ version: number; data: string; filename: string }>,
      };
    }),

  preview: protectedProcedure
    .input(z.object({ examId: z.number(), version: z.number().default(1) }))
    .query(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new Error("Database not available");

      const examResult = await db
        .select()
        .from(exams)
        .where(eq(exams.id, input.examId))
        .limit(1);

      if (!examResult.length || examResult[0].userId !== ctx.user.id) {
        throw new Error("Unauthorized");
      }

      const exam = examResult[0];

      const examQuestionsResult = await db
        .select()
        .from(examQuestions)
        .where(eq(examQuestions.examId, input.examId));

      if (examQuestionsResult.length === 0) throw new Error("No questions in exam");

      const pdfData = await fetchPdfData(db, exam, examQuestionsResult, {});

      const payload = {
        ...pdfData,
        numberOfVersions: 1,
        shuffleMode: "none",
      };

      const pythonScript = PYTHON_SCRIPT;
      const { stdout } = await execAsync(
        `python3 "${pythonScript}" '${JSON.stringify(payload).replace(/'/g, "'\\''")}'`
      );

      const result = JSON.parse(stdout);
      if (!result.success) throw new Error(result.error || "PDF generation failed");

      return { success: true, pdf: result.pdfs[0]?.data };
    }),
});
