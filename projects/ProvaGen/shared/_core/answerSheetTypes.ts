export type HeaderFieldWidth = "small" | "medium" | "large";

export interface HeaderField {
  id: string;
  defaultLabel: string; // e.g. "Name", "Quiz"
  displayLabel: string; // editable by user
  enabled: boolean;
  width: HeaderFieldWidth;
}

export interface AnswerSheetHeader {
  fields: HeaderField[];
  includeStudentId: boolean;
  studentIdDigits: number;
  studentIdLabel: string;
  includeKeyVersion: boolean;
  keyVersionLetters: string; // e.g. "ABCD"
  keyVersionLabel: string;
}

export type QuestionBlockType =
  | "multiple_choice"
  | "alternation"
  | "verbose"
  | "numeric"
  | "short_response";

export interface VerboseAnswer {
  shortLabel: string;
  verboseLabel: string;
}

export interface QuestionBlock {
  id: string;
  type: QuestionBlockType;
  count: number;
  startNumber: number;
  // multiple_choice & short_response
  choices: string[];
  // alternation
  oddChoices?: string[];
  evenChoices?: string[];
  // shared label fields
  internalLabel?: string;
  externalLabel?: string;
  // verbose
  showShortLabels?: boolean;
  verboseAnswers?: VerboseAnswer[];
  // numeric
  hasNegativeSign?: boolean;
  hasDecimalPoint?: boolean;
  hasFractionBar?: boolean;
  hasScientificNotation?: boolean;
  numDigits?: number;
  // short_response
  entryBoxWidth?: "small" | "medium" | "large";
  entryBoxHeight?: number; // 1-7
}

export interface AnswerSheetConfig {
  header: AnswerSheetHeader;
  questions: QuestionBlock[];
}

export const DEFAULT_HEADER_FIELDS: HeaderField[] = [
  { id: "name",    defaultLabel: "Name",    displayLabel: "Name",    enabled: true,  width: "large"  },
  { id: "quiz",    defaultLabel: "Quiz",    displayLabel: "Quiz",    enabled: true,  width: "medium" },
  { id: "class",   defaultLabel: "Class",   displayLabel: "Class",   enabled: true,  width: "medium" },
  { id: "other1",  defaultLabel: "Other 1", displayLabel: "",        enabled: false, width: "medium" },
  { id: "other2",  defaultLabel: "Other 2", displayLabel: "",        enabled: false, width: "medium" },
  { id: "other3",  defaultLabel: "Other 3", displayLabel: "",        enabled: false, width: "medium" },
];

export const DEFAULT_CONFIG: AnswerSheetConfig = {
  header: {
    fields: DEFAULT_HEADER_FIELDS,
    includeStudentId: true,
    studentIdDigits: 5,
    studentIdLabel: "Student ID",
    includeKeyVersion: true,
    keyVersionLetters: "ABCD",
    keyVersionLabel: "Key",
  },
  questions: [],
};

/* ── Standard form configurations (actual AnswerSheetConfig per form) ──── */

const stdHeader20q: AnswerSheetHeader = {
  fields: [
    { id: "name",  defaultLabel: "Name",      displayLabel: "Nome",       enabled: true,  width: "large"  },
    { id: "class", defaultLabel: "Class",     displayLabel: "Turma",      enabled: true,  width: "medium" },
    { id: "date",  defaultLabel: "Date",      displayLabel: "Data",       enabled: true,  width: "medium" },
    { id: "other1",defaultLabel: "Other 1",   displayLabel: "Assinatura", enabled: false, width: "medium" },
    { id: "other2",defaultLabel: "Other 2",   displayLabel: "",           enabled: false, width: "medium" },
    { id: "other3",defaultLabel: "Other 3",   displayLabel: "",           enabled: false, width: "medium" },
  ],
  includeStudentId: false,
  studentIdDigits: 0,
  studentIdLabel: "Matrícula",
  includeKeyVersion: true,
  keyVersionLetters: "ABCD",
  keyVersionLabel: "Versão",
};

const stdHeader50q: AnswerSheetHeader = {
  fields: [
    { id: "name",  defaultLabel: "Name",      displayLabel: "Nome",  enabled: true,  width: "large"  },
    { id: "class", defaultLabel: "Class",     displayLabel: "Turma", enabled: true,  width: "medium" },
    { id: "date",  defaultLabel: "Date",      displayLabel: "Data",  enabled: true,  width: "medium" },
    { id: "other1",defaultLabel: "Other 1",   displayLabel: "",      enabled: false, width: "medium" },
    { id: "other2",defaultLabel: "Other 2",   displayLabel: "",      enabled: false, width: "medium" },
    { id: "other3",defaultLabel: "Other 3",   displayLabel: "",      enabled: false, width: "medium" },
  ],
  includeStudentId: true,
  studentIdDigits: 5,
  studentIdLabel: "Matrícula",
  includeKeyVersion: true,
  keyVersionLetters: "ABCD",
  keyVersionLabel: "Versão",
};

const stdHeader100q: AnswerSheetHeader = {
  fields: [
    { id: "name",  defaultLabel: "Name",      displayLabel: "Nome",  enabled: true,  width: "large"  },
    { id: "class", defaultLabel: "Class",     displayLabel: "Turma", enabled: true,  width: "medium" },
    { id: "date",  defaultLabel: "Date",      displayLabel: "Data",  enabled: true,  width: "medium" },
    { id: "other1",defaultLabel: "Other 1",   displayLabel: "",      enabled: false, width: "medium" },
    { id: "other2",defaultLabel: "Other 2",   displayLabel: "",      enabled: false, width: "medium" },
    { id: "other3",defaultLabel: "Other 3",   displayLabel: "",      enabled: false, width: "medium" },
  ],
  includeStudentId: true,
  studentIdDigits: 9,
  studentIdLabel: "Matrícula",
  includeKeyVersion: true,
  keyVersionLetters: "ABCDE",
  keyVersionLabel: "Versão",
};

export const STANDARD_FORM_CONFIGS: Record<string, AnswerSheetConfig> = {
  "20q": {
    header: stdHeader20q,
    questions: [{ id: "q20", type: "multiple_choice", count: 20, startNumber: 1, choices: ["A","B","C","D","E"] }],
  },
  "20q-noversion": {
    header: { ...stdHeader20q, includeKeyVersion: false },
    questions: [{ id: "q20", type: "multiple_choice", count: 20, startNumber: 1, choices: ["A","B","C","D","E"] }],
  },
  "50q": {
    header: stdHeader50q,
    questions: [{ id: "q50", type: "multiple_choice", count: 50, startNumber: 1, choices: ["A","B","C","D","E"] }],
  },
  "50q-noversion": {
    header: { ...stdHeader50q, includeKeyVersion: false },
    questions: [{ id: "q50", type: "multiple_choice", count: 50, startNumber: 1, choices: ["A","B","C","D","E"] }],
  },
  "100q": {
    header: stdHeader100q,
    questions: [{ id: "q100", type: "multiple_choice", count: 100, startNumber: 1, choices: ["A","B","C","D","E"] }],
  },
  "100q-noversion": {
    header: { ...stdHeader100q, includeKeyVersion: false },
    questions: [{ id: "q100", type: "multiple_choice", count: 100, startNumber: 1, choices: ["A","B","C","D","E"] }],
  },
};

export const STANDARD_FORMS = [
  {
    id: "20q",
    label: "20 Question Form",
    maxQuestions: 20,
    hasStudentId: false,
    studentIdDigits: 0,
    formsPerPage: "1 or 2",
    classPacksAvailable: false,
  },
  {
    id: "50q",
    label: "50 Question Form",
    maxQuestions: 50,
    hasStudentId: true,
    studentIdDigits: 5,
    formsPerPage: "1",
    classPacksAvailable: true,
  },
  {
    id: "100q",
    label: "100 Question Form",
    maxQuestions: 100,
    hasStudentId: true,
    studentIdDigits: 9,
    formsPerPage: "1",
    classPacksAvailable: true,
  },
] as const;
