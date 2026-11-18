/**
 * Question Types and Structures
 * Defines all types of questions and their specific structures
 */

export type QuestionType =
  | "multiple_choice"
  | "multiple_choice_complex"
  | "essay"
  | "assertion_reason";

export type Difficulty = "easy" | "medium" | "hard";

/**
 * Multiple Choice Question Structure
 * Standard multiple choice with options a-e
 */
export interface MultipleChoiceQuestion {
  type: "multiple_choice";
  statement: string;
  alternatives: {
    a: string;
    b: string;
    c: string;
    d: string;
    e?: string;
  };
  correctAnswer: keyof MultipleChoiceQuestion["alternatives"]; // a, b, c, d, or e
  explanation?: string;
  imageUrls?: string[]; // URLs to question images
}

/**
 * Complex Multiple Choice Question Structure
 * Multiple correct answers or grouped questions
 */
export interface ComplexMultipleChoiceQuestion {
  type: "multiple_choice_complex";
  statement: string;
  // Can be multiple groups of options or allow multiple selections
  alternatives: {
    a: string;
    b: string;
    c: string;
    d: string;
    e?: string;
  };
  correctAnswers: Array<keyof ComplexMultipleChoiceQuestion["alternatives"]>; // Multiple correct answers
  explanation?: string;
  imageUrls?: string[];
}

/**
 * Essay Question Structure
 * Open-ended question requiring written response
 */
export interface EssayQuestion {
  type: "essay";
  statement: string;
  suggestedAnswer?: string; // Teacher's suggested answer
  evaluationCriteria?: string[]; // Points to evaluate
  imageUrls?: string[];
  linesCount?: number; // Number of answer lines in PDF
}

/**
 * Assertion-Reason Question Structure (Asserção e Razão)
 * Brazilian format: statements with V/F and reason analysis
 * Can be 4 items (I-IV) or 5 items (I-V)
 */
export interface AssertionReasonQuestion {
  type: "assertion_reason";
  statement: string; // Main context/instruction
  items: AssertionReasonItem[];
  explanation?: string;
  imageUrls?: string[];
}

export interface AssertionReasonItem {
  id: number; // I, II, III, IV, or V
  assertion: string; // The statement to evaluate
  isTrue: boolean; // Is the assertion true?
  reason: string; // The reason/explanation
  reasonCorrect: boolean; // Is the reason a correct explanation?
}

/**
 * Assertion-Reason Item Presets
 * Provides standard templates for common patterns
 */
export const ASSERTION_REASON_PRESETS = [
  {
    label: "Asserção Verdadeira - Razão Correta",
    assertion: true,
    reasonCorrect: true,
    description: "Afirmação verdadeira com razão correta",
  },
  {
    label: "Asserção Verdadeira - Razão Incorreta",
    assertion: true,
    reasonCorrect: false,
    description: "Afirmação verdadeira, mas razão não explica",
  },
  {
    label: "Asserção Falsa - Razão Correta",
    assertion: false,
    reasonCorrect: true,
    description: "Afirmação falsa, porém razão está correta",
  },
  {
    label: "Asserção Falsa - Razão Incorreta",
    assertion: false,
    reasonCorrect: false,
    description: "Afirmação falsa e razão também incorreta",
  },
  {
    label: "Questão Não se Aplica",
    assertion: false,
    reasonCorrect: false,
    description: "Sem relação entre asserção e razão",
  },
];

/**
 * Base Question Structure (used in database)
 */
export interface BaseQuestion {
  id?: number;
  type: QuestionType;
  statement: string;
  discipline: string;
  subject: string;
  difficulty?: Difficulty;
  tags?: string[];
  content: QuestionContent; // Normalized JSON content
  totalImages?: number;
  sourceFormat?: string;
  createdAt?: Date;
  updatedAt?: Date;
}

/**
 * Union type for all question content structures
 */
export type QuestionContent =
  | MultipleChoiceQuestion
  | ComplexMultipleChoiceQuestion
  | EssayQuestion
  | AssertionReasonQuestion;

/**
 * Form state for creating/editing questions
 */
export interface QuestionFormState extends BaseQuestion {
  id?: number;
  uploadedImageFiles?: File[];
  imageUrls?: string[];
  imageBase64Array?: string[];
}

/**
 * Multiple Choice Template Options
 */
export const MULTIPLE_CHOICE_TEMPLATES = [
  {
    id: "standard_5",
    label: "Múltipla Escolha Padrão (A-E)",
    description: "5 alternativas com uma resposta correta",
    alternatives: { a: "", b: "", c: "", d: "", e: "" },
  },
  {
    id: "standard_4",
    label: "Múltipla Escolha (A-D)",
    description: "4 alternativas com uma resposta correta",
    alternatives: { a: "", b: "", c: "", d: "" },
  },
  {
    id: "complex_multi",
    label: "Múltipla Escolha Complexa",
    description: "Múltiplas respostas corretas",
    alternatives: { a: "", b: "", c: "", d: "", e: "" },
  },
];

/**
 * Question Type Templates
 */
export const QUESTION_TYPE_TEMPLATES = [
  {
    type: "multiple_choice",
    label: "Múltipla Escolha",
    description: "Questão com múltiplas alternativas e uma resposta correta",
    icon: "CheckSquare",
  },
  {
    type: "multiple_choice_complex",
    label: "Múltipla Escolha Complexa",
    description: "Questão com múltiplas respostas corretas",
    icon: "ClipboardList",
  },
  {
    type: "essay",
    label: "Questão Dissertativa",
    description: "Questão aberta que requer resposta escrita",
    icon: "FileText",
  },
  {
    type: "assertion_reason",
    label: "Asserção e Razão",
    description: "Questão com afirmações e justificativas (I-V ou I-IV)",
    icon: "GitBranch",
  },
];

/**
 * Validate question content based on type
 */
export function validateQuestion(question: QuestionFormState): {
  valid: boolean;
  errors: string[];
} {
  const errors: string[] = [];

  if (!question.statement?.trim()) {
    errors.push("Enunciado/pergunta é obrigatório");
  }

  if (!question.discipline?.trim()) {
    errors.push("Disciplina é obrigatória");
  }

  if (!question.subject?.trim()) {
    errors.push("Assunto é obrigatório");
  }

  switch (question.type) {
    case "multiple_choice": {
      const mc = question.content as MultipleChoiceQuestion;
      if (!mc.alternatives?.a?.trim()) errors.push("Alternativa A é obrigatória");
      if (!mc.alternatives?.b?.trim()) errors.push("Alternativa B é obrigatória");
      if (!mc.alternatives?.c?.trim()) errors.push("Alternativa C é obrigatória");
      if (!mc.alternatives?.d?.trim()) errors.push("Alternativa D é obrigatória");
      if (!mc.correctAnswer) errors.push("Resposta correta é obrigatória");
      break;
    }

    case "multiple_choice_complex": {
      const mc = question.content as any;
      if (!mc.alternatives?.a?.trim()) errors.push("Alternativa A é obrigatória");
      if (!mc.alternatives?.b?.trim()) errors.push("Alternativa B é obrigatória");
      if (!mc.alternatives?.c?.trim()) errors.push("Alternativa C é obrigatória");
      if (!mc.alternatives?.d?.trim()) errors.push("Alternativa D é obrigatória");
      if (!mc.correctAnswers || mc.correctAnswers.length === 0) {
        errors.push("Pelo menos uma resposta correta é obrigatória");
      }
      break;
    }

    case "essay": {
      // Essay validation - less strict
      break;
    }

    case "assertion_reason": {
      const ar = question.content as AssertionReasonQuestion;
      if (!ar.items || ar.items.length === 0) {
        errors.push("Pelo menos um item é obrigatório");
      }
      ar.items?.forEach((item, idx) => {
        if (!item.assertion?.trim()) {
          errors.push(`Item ${idx + 1}: Asserção é obrigatória`);
        }
        if (!item.reason?.trim()) {
          errors.push(`Item ${idx + 1}: Razão é obrigatória`);
        }
      });
      break;
    }
  }

  return {
    valid: errors.length === 0,
    errors,
  };
}
