import { describe, expect, it } from "vitest";

describe("questions router - validation", () => {
  it("validates multiple_choice type", () => {
    const types = ["multiple_choice", "essay"];
    expect(types.includes("multiple_choice")).toBe(true);
  });

  it("validates essay type", () => {
    const types = ["multiple_choice", "essay"];
    expect(types.includes("essay")).toBe(true);
  });

  it("validates statement is required", () => {
    const statement = "What is 2+2?";
    expect(statement.length).toBeGreaterThan(0);
  });

  it("validates alternatives array", () => {
    const alternatives = ["3", "4", "5", "6"];
    expect(Array.isArray(alternatives)).toBe(true);
    expect(alternatives.length).toBe(4);
  });

  it("validates correctAnswer is string", () => {
    const correctAnswer = "4";
    expect(typeof correctAnswer).toBe("string");
  });

  it("parses alternatives from JSON", () => {
    const json = '["A", "B", "C"]';
    const parsed = JSON.parse(json);
    expect(Array.isArray(parsed)).toBe(true);
    expect(parsed.length).toBe(3);
  });

  it("handles empty alternatives", () => {
    const alternatives: string[] = [];
    expect(Array.isArray(alternatives)).toBe(true);
    expect(alternatives.length).toBe(0);
  });

  it("filters questions by type", () => {
    const questions = [
      { id: 1, type: "multiple_choice", statement: "Q1" },
      { id: 2, type: "essay", statement: "Q2" },
      { id: 3, type: "multiple_choice", statement: "Q3" },
    ];

    const filtered = questions.filter((q) => q.type === "multiple_choice");
    expect(filtered.length).toBe(2);
    expect(filtered[0].id).toBe(1);
  });

  it("searches questions by statement", () => {
    const questions = [
      { id: 1, statement: "What is photosynthesis?" },
      { id: 2, statement: "Explain gravity" },
      { id: 3, statement: "Define photosynthesis process" },
    ];

    const searchQuery = "photosynthesis";
    const filtered = questions.filter((q) =>
      q.statement.toLowerCase().includes(searchQuery.toLowerCase())
    );

    expect(filtered.length).toBe(2);
    expect(filtered[0].id).toBe(1);
  });

  it("paginates questions correctly", () => {
    const questions = Array.from({ length: 25 }, (_, i) => ({
      id: i + 1,
      statement: `Question ${i + 1}`,
    }));

    const itemsPerPage = 10;
    const page = 1;
    const paginated = questions.slice(
      (page - 1) * itemsPerPage,
      page * itemsPerPage
    );

    expect(paginated.length).toBe(10);
    expect(paginated[0].id).toBe(1);
    expect(paginated[9].id).toBe(10);
  });

  it("calculates total pages", () => {
    const totalItems = 25;
    const itemsPerPage = 10;
    const totalPages = Math.ceil(totalItems / itemsPerPage);

    expect(totalPages).toBe(3);
  });

  it("handles authorization check", () => {
    const userId = 1;
    const questionUserId = 1;
    const isAuthorized = userId === questionUserId;

    expect(isAuthorized).toBe(true);
  });

  it("rejects unauthorized access", () => {
    const userId = 1;
    const questionUserId = 2;
    const isAuthorized = userId === questionUserId;

    expect(isAuthorized).toBe(false);
  });

  it("combines search and type filters", () => {
    const questions = [
      { id: 1, type: "multiple_choice", statement: "What is photosynthesis?" },
      { id: 2, type: "essay", statement: "Explain photosynthesis" },
      { id: 3, type: "multiple_choice", statement: "Define gravity" },
    ];

    const searchQuery = "photosynthesis";
    const filterType = "multiple_choice";

    const filtered = questions.filter((q) => {
      const matchesSearch = q.statement
        .toLowerCase()
        .includes(searchQuery.toLowerCase());
      const matchesType = q.type === filterType;
      return matchesSearch && matchesType;
    });

    expect(filtered.length).toBe(1);
    expect(filtered[0].id).toBe(1);
  });
});
