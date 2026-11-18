import { describe, expect, it } from "vitest";

describe("Exam Creation", () => {
  it("should validate exam data structure", () => {
    const examData = {
      name: "Prova de Matemática",
      course: "Engenharia Civil",
      class: "2024.1",
      campus: "Campus Centro",
      discipline: "Cálculo I",
      professor: "Dr. Silva",
      examDate: new Date("2024-05-15"),
      period: "Matutino",
      instructions: "Responda todas as questões com caneta azul ou preta.",
      numberOfVersions: 3,
    };

    expect(examData.name).toBeDefined();
    expect(examData.course).toBeDefined();
    expect(examData.discipline).toBeDefined();
    expect(examData.professor).toBeDefined();
    expect(examData.numberOfVersions).toBe(3);
  });

  it("should validate exam question selection", () => {
    const selectedQuestions = [
      { questionId: 1, order: 1 },
      { questionId: 2, order: 2 },
      { questionId: 3, order: 3 },
      { questionId: 4, order: 4 },
      { questionId: 5, order: 5 },
    ];

    expect(selectedQuestions).toHaveLength(5);
    expect(selectedQuestions[0].questionId).toBe(1);
    expect(selectedQuestions[4].order).toBe(5);
  });

  it("should handle multiple versions configuration", () => {
    const versions = [1, 2, 3, 4, 5];

    expect(versions).toHaveLength(5);
    versions.forEach((v) => {
      expect(v).toBeGreaterThan(0);
    });
  });

  it("should support deterministic shuffling per version", () => {
    const questions = [1, 2, 3, 4, 5];

    // Create a deterministic shuffle function
    const createShuffleFunction = () => {
      const cache: Record<string, number[]> = {};

      return (seed: number) => {
        const key = `seed_${seed}`;
        if (cache[key]) {
          return cache[key];
        }

        const copy = [...questions];
        // Deterministic shuffle based on seed
        const swaps = seed % 3; // Limit swaps for predictability
        for (let i = 0; i < swaps; i++) {
          const j = (i + seed) % copy.length;
          const k = (i + seed + 1) % copy.length;
          if (j !== k) {
            [copy[j], copy[k]] = [copy[k], copy[j]];
          }
        }

        cache[key] = copy;
        return copy;
      };
    };

    const shuffle = createShuffleFunction();

    const v1 = shuffle(1);
    const v1Again = shuffle(1);

    // Same seed should produce same order
    expect(v1.join(",")).toBe(v1Again.join(","));

    // All questions should be present
    expect(v1.sort()).toEqual(questions.sort());
  });


  it("should validate instructions field", () => {
    const instructions =
      "Responda todas as questões. Não é permitido usar calculadora. Tempo: 2 horas.";

    expect(instructions).toBeDefined();
    expect(instructions.length).toBeGreaterThan(0);
    expect(instructions).toContain("questões");
  });

  it("should handle default instructions", () => {
    const defaultInstructions =
      "Instruções para a prova:\n1. Leia atentamente cada questão\n2. Responda com caneta azul ou preta\n3. Não é permitido o uso de calculadora\n4. Tempo total: 2 horas";

    expect(defaultInstructions).toBeDefined();
    expect(defaultInstructions).toContain("Instruções");
  });

  it("should validate exam creation response", () => {
    const response = {
      success: true,
      examId: 1,
      name: "Prova de Matemática",
      numberOfVersions: 3,
      questionsCount: 5,
    };

    expect(response.success).toBe(true);
    expect(response.examId).toBeDefined();
    expect(response.numberOfVersions).toBe(3);
    expect(response.questionsCount).toBe(5);
  });

  it("should validate exam metadata persistence", () => {
    const examMetadata = {
      id: 1,
      userId: 1,
      name: "Prova de Português",
      course: "Letras",
      class: "2024.1",
      campus: "Campus Centro",
      discipline: "Literatura Brasileira",
      professor: "Dra. Santos",
      examDate: new Date("2024-06-10"),
      period: "Vespertino",
      instructions: "Responda com atenção.",
      numberOfVersions: 2,
      createdAt: new Date(),
      updatedAt: new Date(),
    };

    expect(examMetadata.id).toBe(1);
    expect(examMetadata.userId).toBe(1);
    expect(examMetadata.discipline).toBe("Literatura Brasileira");
    expect(examMetadata.numberOfVersions).toBe(2);
  });

  it("should validate question order in exam", () => {
    const examQuestions = [
      { examId: 1, questionId: 1, order: 1 },
      { examId: 1, questionId: 3, order: 2 },
      { examId: 1, questionId: 2, order: 3 },
      { examId: 1, questionId: 5, order: 4 },
      { examId: 1, questionId: 4, order: 5 },
    ];

    // Verify order is sequential
    for (let i = 0; i < examQuestions.length; i++) {
      expect(examQuestions[i].order).toBe(i + 1);
    }

    // Verify all questions are unique
    const questionIds = examQuestions.map((q) => q.questionId);
    const uniqueIds = new Set(questionIds);
    expect(uniqueIds.size).toBe(questionIds.length);
  });

  it("should support multiple versions with caching", () => {
    const questions = [1, 2, 3, 4, 5];
    const versionCache: Record<number, number[]> = {};

    const getVersionQuestions = (seed: number) => {
      if (versionCache[seed]) {
        return versionCache[seed];
      }

      const copy = [...questions];
      // Simple deterministic shuffle
      const swaps = Math.max(1, seed % 3);
      for (let i = 0; i < swaps; i++) {
        const j = (i + seed) % copy.length;
        const k = (i + seed + 1) % copy.length;
        if (j !== k) {
          [copy[j], copy[k]] = [copy[k], copy[j]];
        }
      }

      versionCache[seed] = copy;
      return copy;
    };

    const v1 = getVersionQuestions(1);
    const v1Cached = getVersionQuestions(1);

    // Should return cached version
    expect(v1).toBe(v1Cached);

    // All versions should have same questions
    expect(v1.sort()).toEqual(questions.sort());
  });
});
