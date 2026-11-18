import { describe, expect, it } from "vitest";
import { generateAnswerSheetSVG, AnswerSheetOverflowError } from "@/lib/answerSheetSvg";
import type { AnswerSheetConfig, QuestionBlock } from "@shared/_core/answerSheetTypes";

// Regression test for a real bug reported by the user: gabaritos with a
// student-ID grid enabled and few questions had the ID grid's 10 digit rows
// (0-9) overflow past the bottom bar, overlapping the ArUco markers/barcode.
// The ID grid is always 10 rows tall regardless of digit count, but the body
// height used to only consider question bubbles + QR — see answerSheetSvg.ts.
describe("gabarito SVG layout — student-ID grid vs. bottom bar", () => {
  function configWith(questionCount: number): AnswerSheetConfig {
    return {
      header: {
        fields: [
          { id: "name", defaultLabel: "Name", displayLabel: "Nome", enabled: true, width: "large" },
        ],
        includeStudentId: true,
        studentIdDigits: 5,
        studentIdLabel: "Matrícula",
        includeKeyVersion: false,
        keyVersionLetters: "",
        keyVersionLabel: "",
      },
      questions: questionCount > 0
        ? [{ id: "b1", type: "multiple_choice", count: questionCount, startNumber: 1, choices: ["A", "B", "C", "D", "E"] }]
        : [],
    };
  }

  function bottomArucoY(svg: string): number {
    // First two <rect> elements after the last <image>/question content mark the
    // bottom ArUco markers; simpler: grab every rect's y and take the max (the
    // bottom bar is always the lowest content in the sheet).
    const ys = [...svg.matchAll(/<rect x="[^"]*" y="([\d.]+)"/g)].map(m => parseFloat(m[1]));
    return Math.max(...ys);
  }

  function idGridBottomY(svg: string): number {
    // The ID grid's digit-selector <circle> elements are the only circles with
    // r="5.46" (BUBBLE_R * 0.78). Find the lowest one.
    const ys = [...svg.matchAll(/<circle cx="[^"]*" cy="([\d.]+)" r="5\.46"/g)].map(m => parseFloat(m[1]));
    return ys.length ? Math.max(...ys) + 5.46 : -Infinity;
  }

  it("keeps the bottom bar below the ID grid even with very few questions", () => {
    const svg = generateAnswerSheetSVG("Teste", configWith(2), { standalone: true, formId: "T1" });
    const bottom = bottomArucoY(svg);
    const idBottom = idGridBottomY(svg);
    expect(idBottom).toBeGreaterThan(0);
    expect(bottom).toBeGreaterThanOrEqual(idBottom);
  });

  it("keeps the bottom bar below the ID grid with a typical question count", () => {
    const svg = generateAnswerSheetSVG("Teste", configWith(10), { standalone: true, formId: "T1" });
    const bottom = bottomArucoY(svg);
    const idBottom = idGridBottomY(svg);
    expect(bottom).toBeGreaterThanOrEqual(idBottom);
  });
});

// Regression test for two more bugs found while auditing question types:
// "alternation" blocks never actually alternated (evenChoices was dead code),
// and "verbose" blocks could render with zero bubbles when the user accepted
// the dialog's defaults without touching the verboseAnswers editor.
describe("gabarito SVG layout — alternation & verbose question blocks", () => {
  const baseHeader: AnswerSheetConfig["header"] = {
    fields: [{ id: "name", defaultLabel: "Name", displayLabel: "Nome", enabled: true, width: "large" }],
    includeStudentId: false,
    studentIdDigits: 0,
    studentIdLabel: "",
    includeKeyVersion: false,
    keyVersionLetters: "",
    keyVersionLabel: "",
  };

  it("alternation: odd questions use oddChoices, even questions use evenChoices", () => {
    const block: QuestionBlock = {
      id: "b1", type: "alternation", count: 4, startNumber: 1,
      choices: ["A", "B", "C"], oddChoices: ["A", "B", "C"], evenChoices: ["X", "Y", "Z"],
    };
    const config: AnswerSheetConfig = { header: baseHeader, questions: [block] };
    const svg = generateAnswerSheetSVG("Teste", config, { standalone: true });

    expect(svg).toContain(">X<");
    expect(svg).toContain(">Y<");
    expect(svg).toContain(">Z<");
  });

  it("verbose: falls back to verboseAnswers short labels when choices is empty", () => {
    const block: QuestionBlock = {
      id: "b1", type: "verbose", count: 2, startNumber: 1,
      choices: [], // as saved by the dialog when the user never touched the editor
      verboseAnswers: [{ shortLabel: "V", verboseLabel: "Verdadeiro" }, { shortLabel: "F", verboseLabel: "Falso" }],
    };
    const config: AnswerSheetConfig = { header: baseHeader, questions: [block] };
    const svg = generateAnswerSheetSVG("Teste", config, { standalone: true });

    // Bubble circles (r=7, plain BUBBLE_R) must exist — an empty `choices`
    // used to render the row with a question number and zero bubbles.
    const bubbleCount = (svg.match(/<circle[^>]*r="7"[^>]*\/>/g) ?? []).length;
    expect(bubbleCount).toBe(4); // 2 questions × 2 choices (V/F)
  });
});

// Regression test for a third bug: a standalone gabarito is a fixed A4 page,
// but the body height was never checked against it — an exam with enough
// questions had the excess rows silently clipped by the SVG viewBox instead
// of failing, producing a PDF/PNG missing questions with no warning at all.
describe("gabarito SVG layout — page-overflow guard", () => {
  const baseHeader: AnswerSheetConfig["header"] = {
    fields: [{ id: "name", defaultLabel: "Name", displayLabel: "Nome", enabled: true, width: "large" }],
    includeStudentId: false,
    studentIdDigits: 0,
    studentIdLabel: "",
    includeKeyVersion: false,
    keyVersionLetters: "",
    keyVersionLabel: "",
  };

  function configWith(questionCount: number): AnswerSheetConfig {
    return {
      header: baseHeader,
      questions: [{ id: "b1", type: "multiple_choice", count: questionCount, startNumber: 1, choices: ["A", "B", "C", "D", "E"] }],
    };
  }

  it("generates normally for a question count that fits one A4 page", () => {
    expect(() => generateAnswerSheetSVG("Teste", configWith(60), { standalone: true })).not.toThrow();
  });

  it("throws AnswerSheetOverflowError instead of silently clipping when content exceeds one A4 page", () => {
    expect(() => generateAnswerSheetSVG("Teste", configWith(100), { standalone: true }))
      .toThrow(AnswerSheetOverflowError);
  });

  it("does not throw for non-standalone (embedded) SVGs, which size to their content", () => {
    expect(() => generateAnswerSheetSVG("Teste", configWith(100), { standalone: false })).not.toThrow();
  });
});
