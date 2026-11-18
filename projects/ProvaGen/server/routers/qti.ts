import { z } from "zod";
import { protectedProcedure, router } from "../_core/trpc";
import { TRPCError } from "@trpc/server";
import { getDb } from "../db";
import { questions, examQuestions, exams } from "../db";
import { eq, and, inArray } from "drizzle-orm";

function escapeXml(str: string): string {
  return str
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&apos;");
}

function buildMultipleChoiceItem(q: any, idx: number): string {
  let content: any = {};
  try { content = JSON.parse(q.content ?? "{}"); } catch { /* */ }

  const alts: Record<string, string> = content.alternatives ?? {};
  const correct: string = (content.correctAnswer ?? "a").toLowerCase();

  const choices = Object.entries(alts).map(([key, val]) => {
    const id = `choice_${key}`;
    return `      <simpleChoice identifier="${id}">${escapeXml(String(val))}</simpleChoice>`;
  }).join("\n");

  const correctId = `choice_${correct}`;

  return `  <assessmentItem identifier="q_${q.id}" title="Questão ${idx + 1}" adaptive="false" timeDependent="false">
    <responseDeclaration identifier="RESPONSE" cardinality="single" baseType="identifier">
      <correctResponse>
        <value>${correctId}</value>
      </correctResponse>
    </responseDeclaration>
    <itemBody>
      <choiceInteraction responseIdentifier="RESPONSE" shuffle="false" maxChoices="1">
        <prompt>${escapeXml(q.statement)}</prompt>
${choices}
      </choiceInteraction>
    </itemBody>
  </assessmentItem>`;
}

function buildEssayItem(q: any, idx: number): string {
  let content: any = {};
  try { content = JSON.parse(q.content ?? "{}"); } catch { /* */ }

  return `  <assessmentItem identifier="q_${q.id}" title="Questão ${idx + 1}" adaptive="false" timeDependent="false">
    <responseDeclaration identifier="RESPONSE" cardinality="single" baseType="string"/>
    <itemBody>
      <extendedTextInteraction responseIdentifier="RESPONSE">
        <prompt>${escapeXml(q.statement)}</prompt>
      </extendedTextInteraction>
    </itemBody>
  </assessmentItem>`;
}

function buildQtiPackage(title: string, items: string[]): string {
  return `<?xml version="1.0" encoding="UTF-8"?>
<assessmentTest xmlns="http://www.imsglobal.org/xsd/imsqti_v2p1"
  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
  xsi:schemaLocation="http://www.imsglobal.org/xsd/imsqti_v2p1 http://www.imsglobal.org/xsd/qti/qtiv2p1/imsqti_v2p1.xsd"
  identifier="test_1" title="${escapeXml(title)}">
  <testPart identifier="part_1" navigationMode="linear" submissionMode="individual">
    <assessmentSection identifier="section_1" title="${escapeXml(title)}" visible="true">
${items.map((item) => `      <assessmentItemRef identifier="${item.match(/identifier="(q_\d+)"/)?.[1] ?? "unknown"}" href="${item.match(/identifier="(q_\d+)"/)?.[1] ?? "unknown"}.xml"/>`).join("\n")}
    </assessmentSection>
  </testPart>
</assessmentTest>

<!-- QTI Items -->
${items.join("\n\n")}`;
}

export const qtiRouter = router({
  exportExam: protectedProcedure
    .input(z.object({ examId: z.number() }))
    .query(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });

      const examResult = await db.select().from(exams)
        .where(and(eq(exams.id, input.examId), eq(exams.userId, ctx.user.id)))
        .limit(1);
      if (!examResult.length) throw new TRPCError({ code: "NOT_FOUND" });
      const exam = examResult[0];

      const eqs = await db.select().from(examQuestions)
        .where(and(eq(examQuestions.examId, input.examId), eq(examQuestions.isVoided, "false")));
      const qIds = eqs.map((e: any) => e.questionId);
      if (qIds.length === 0) return { xml: "", filename: `${exam.name}.xml`, questionCount: 0 };

      const qDetails = await db.select().from(questions).where(inArray(questions.id, qIds));
      const sorted = eqs.sort((a: any, b: any) => a.order - b.order)
        .map((e: any) => qDetails.find((q: any) => q.id === e.questionId))
        .filter(Boolean);

      const items = sorted.map((q: any, i: number) => {
        if (q.type === "essay") return buildEssayItem(q, i);
        return buildMultipleChoiceItem(q, i);
      });

      const xml = buildQtiPackage(exam.name, items);
      const filename = `${exam.name.replace(/[^a-zA-Z0-9]/g, "_")}_QTI.xml`;
      return { xml, filename, questionCount: sorted.length };
    }),

  exportQuestions: protectedProcedure
    .input(z.object({ questionIds: z.array(z.number()).min(1).max(200) }))
    .query(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });

      const qDetails = await db.select().from(questions)
        .where(and(inArray(questions.id, input.questionIds), eq(questions.userId, ctx.user.id)));

      const items = qDetails.map((q: any, i: number) => {
        if (q.type === "essay") return buildEssayItem(q, i);
        return buildMultipleChoiceItem(q, i);
      });

      const xml = buildQtiPackage("Questões Exportadas", items);
      return { xml, filename: "questoes_QTI.xml", questionCount: qDetails.length };
    }),
});
