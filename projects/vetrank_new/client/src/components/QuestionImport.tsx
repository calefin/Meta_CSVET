import { useState, useRef, useCallback, useEffect, Fragment } from "react";
import * as XLSX from "xlsx";
import Papa from "papaparse";
import { toast } from "sonner";
import { trpc } from "@/lib/trpc";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Alert, AlertDescription } from "@/components/ui/alert";
import {
  Upload,
  Download,
  FileText,
  CheckCircle,
  XCircle,
  AlertTriangle,
  Eye,
  ChevronDown,
  ChevronRight,
  Pencil,
} from "lucide-react";

// --- Types ---
type QuestionType =
  | "multiple_choice"
  | "assertion_reason"
  | "complex_multiple_choice"
  | "matching"
  | "true_false"
  | "ordering"
  | "cloze"
  | "clinical_case"
  | "image_analysis"
  | "interpretation"
  | "discursive";

export interface ParsedMCQuestion {
  textPt: string;
  textEn?: string;
  disciplineId: number;
  subjectId?: number;
  topicId?: number;
  subjectTag?: string;
  author?: string;
  year?: number;
  difficulty: "very_easy" | "easy" | "medium" | "hard" | "very_hard";
  questionType: QuestionType;
  modelId?: string;
  banca?: string;
  instituicao?: string;
  cargo?: string;
  carreira?: string;
  areaFormacao?: string;
  escolaridade?: string;
  optA: string;
  optB: string;
  optC: string;
  optD: string;
  optE: string;
  correctOption: string;
  explanationPt?: string;
  assertion1?: string;
  assertion2?: string;
  imageUrl?: string;
  _rowIndex: number;
  _errors: string[];
  _valid: boolean;
}

interface ParsedDiscursiveQuestion {
  textPt: string;
  textEn?: string;
  disciplineId: number;
  subjectId?: number;
  subjectTag?: string;
  author?: string;
  year?: number;
  difficulty: "easy" | "medium" | "hard";
  expectedAnswerPt: string;
  expectedAnswerEn?: string;
  imageUrl?: string;
  _rowIndex: number;
  _errors: string[];
  _valid: boolean;
}

// --- Text normalization ---

function normalizeMatchingText(text: string): string {
  // Already has explicit column headers — nothing to do
  if (/\nCOLUNA\s+I/i.test(text)) return text;
  // Detect inline "(Coluna I)...(Coluna II)" format
  if (!/\(\s*coluna\s+I\s*\)/i.test(text) || !/\(\s*coluna\s+II\s*\)/i.test(text)) return text;

  // Find where numeric items start (e.g. "1. " or "1) ")
  const firstNumRe = /(?:^|[^\d])1[.)]\s/;
  const firstNumMatch = firstNumRe.exec(text);
  if (!firstNumMatch) return text;
  const pos1 = text.indexOf("1", firstNumMatch.index);
  if (pos1 < 0) return text;

  const before = text.slice(0, pos1).trimEnd().replace(/:\s*$/, "");
  const itemsText = text.slice(pos1);

  const numRe = /(\d+)[.)]\s+/g;
  const numTokens: { key: string; start: number; end: number }[] = [];
  let m: RegExpExecArray | null;
  while ((m = numRe.exec(itemsText)) !== null)
    numTokens.push({ key: m[1]!, start: m.index, end: m.index + m[0].length });
  if (!numTokens.length) return text;

  const lastNumStart = numTokens[numTokens.length - 1]!.start;
  const letRe = /([a-e])[.)]\s*/g;
  const letTokens: { key: string; start: number; end: number }[] = [];
  while ((m = letRe.exec(itemsText)) !== null)
    if (m.index > lastNumStart)
      letTokens.push({ key: m[1]!, start: m.index, end: m.index + m[0].length });
  if (!letTokens.length) return text;

  const firstLetStart = letTokens[0]!.start;
  const col1Lines = numTokens
    .map((t, i) => `${t.key}. ${itemsText.slice(t.end, i < numTokens.length - 1 ? numTokens[i + 1]!.start : firstLetStart).trim()}`)
    .join("\n");
  const col2Lines = letTokens
    .map((t, i) => `${t.key}) ${itemsText.slice(t.end, i < letTokens.length - 1 ? letTokens[i + 1]!.start : itemsText.length).trim()}`)
    .join("\n");

  return `${before}:\n\nCOLUNA I\n${col1Lines}\n\nCOLUNA II\n${col2Lines}\n\nA associação CORRETA é:`;
}

// --- Validation ---
const VALID_DIFFICULTIES = ["very_easy", "easy", "medium", "hard", "very_hard"];
const VALID_CORRECT_OPTIONS = ["A", "B", "C", "D", "E"];
const VALID_QUESTION_TYPES: string[] = [
  "multiple_choice",
  "assertion_reason",
  "complex_multiple_choice",
  "matching",
  "true_false",
  "ordering",
  "cloze",
  "clinical_case",
  "image_analysis",
  "interpretation",
  "discursive",
];

function validateMCRow(row: Record<string, unknown>, index: number): ParsedMCQuestion {
  const errors: string[] = [];

  const rawText = String(row.textPt ?? row.text_pt ?? row.text ?? row.enunciado ?? "").trim();

  const disciplineId = Number(row.disciplineId ?? row.discipline_id ?? 0);
  if (!disciplineId) errors.push("disciplineId é obrigatório e deve ser número");

  const difficulty = String(row.difficulty ?? row.dificuldade ?? "medium").toLowerCase();
  if (!VALID_DIFFICULTIES.includes(difficulty)) errors.push(`difficulty inválido: ${difficulty}`);

  const questionType = String(row.questionType ?? row.question_type ?? "multiple_choice").toLowerCase();

  // Normalize textPt: convert inline matching format to standard COLUNA I/II format
  let textPt = rawText;
  if (questionType === "matching") textPt = normalizeMatchingText(rawText);

  if (!textPt) errors.push("textPt é obrigatório");
  if (!VALID_QUESTION_TYPES.includes(questionType)) errors.push(`questionType inválido: ${questionType}`);

  // Suporta formato plano (optA/optB) e formato array com {id,textPt} ou {letter,text}
  const rawOptions = Array.isArray(row.options) ? (row.options as any[]) : [];
  const fromArr = (letter: string) => {
    const upper = letter.toUpperCase();
    const opt = rawOptions.find((o: any) =>
      String(o.id ?? o.letter ?? "").toUpperCase() === upper
    );
    return String(opt?.textPt ?? opt?.text ?? "").trim();
  };

  const optA = String(row.optA ?? row.opt_a ?? row.alternativaA ?? fromArr("A")).trim();
  const optB = String(row.optB ?? row.opt_b ?? row.alternativaB ?? fromArr("B")).trim();
  const optC = String(row.optC ?? row.opt_c ?? row.alternativaC ?? fromArr("C")).trim();
  const optD = String(row.optD ?? row.opt_d ?? row.alternativaD ?? fromArr("D")).trim();
  const optE = String(row.optE ?? row.opt_e ?? row.alternativaE ?? fromArr("E")).trim();
  if (!optA || !optB || !optC || !optD || !optE) errors.push("Todas as alternativas (A-E) são obrigatórias");

  const correctOption = String(row.correctOption ?? row.correct_option ?? row.correctOptionLetter ?? row.gabarito ?? "").toUpperCase().trim();
  if (!VALID_CORRECT_OPTIONS.includes(correctOption)) errors.push(`correctOption inválido: ${correctOption}`);

  const str = (k: string, ...aliases: string[]) => {
    for (const key of [k, ...aliases]) {
      if (row[key]) return String(row[key]).trim();
    }
    return undefined;
  };

  return {
    textPt,
    textEn: str("textEn", "text_en"),
    disciplineId,
    subjectId: row.subjectId ? Number(row.subjectId) : undefined,
    // Assunto (3º nível). Antes era descartado na importação — questões entravam sem assunto.
    topicId: (row.topicId ?? row.topic_id) ? Number(row.topicId ?? row.topic_id) : undefined,
    subjectTag: str("subjectTag", "subject_tag", "tag_assunto", "tema"),
    author: str("author", "autor", "elaborador"),
    year: (row.year ?? row.ano) ? Number(row.year ?? row.ano) : undefined,
    difficulty: difficulty as ParsedMCQuestion["difficulty"],
    questionType: questionType as QuestionType,
    modelId: str("modelId", "model_id", "modelo"),
    banca: str("banca", "organizadora"),
    instituicao: str("instituicao", "institution", "universidade"),
    cargo: str("cargo", "position"),
    carreira: str("carreira", "career"),
    areaFormacao: str("areaFormacao", "area_formacao", "area"),
    escolaridade: str("escolaridade", "education_level"),
    optA,
    optB,
    optC,
    optD,
    optE,
    correctOption,
    explanationPt: str("explanationPt", "explicacao", "justificativa"),
    assertion1: str("assertion1", "assercao1"),
    assertion2: str("assertion2", "assercao2"),
    imageUrl: str("imageUrl", "image_url", "imagem"),
    _rowIndex: index + 1,
    _errors: errors,
    _valid: errors.length === 0,
  };
}

function validateDiscursiveRow(row: Record<string, unknown>, index: number): ParsedDiscursiveQuestion {
  const errors: string[] = [];

  const textPt = String(row.textPt ?? row.text_pt ?? row.text ?? row.enunciado ?? "").trim();
  if (!textPt) errors.push("textPt é obrigatório");

  const disciplineId = Number(row.disciplineId ?? row.discipline_id ?? row.discipline ?? row.grandeArea ?? row.grande_area ?? 0);
  if (!disciplineId) errors.push("disciplineId é obrigatório e deve ser número");

  let difficulty = String(row.difficulty ?? row.dificuldade ?? "medium").toLowerCase().trim();
  if (difficulty === "very_easy") difficulty = "easy";
  if (difficulty === "very_hard") difficulty = "hard";
  if (!["easy", "medium", "hard"].includes(difficulty)) {
    errors.push(`difficulty inválido: ${difficulty}`);
  }

  const expectedAnswerPt = String(
    row.expectedAnswerPt ??
    row.expected_answer_pt ??
    row.expectedAnswer ??
    row.expected_answer ??
    row.resposta ??
    row.respostaEsperada ??
    row.resposta_esperada ??
    row.gabarito ??
    ""
  ).trim();
  if (!expectedAnswerPt) errors.push("expectedAnswerPt é obrigatório");

  const subjectIdRaw = row.subjectId ?? row.subject_id ?? row.assuntoId ?? row.assunto_id;
  const subjectTagRaw = row.subjectTag ?? row.subject_tag ?? row.assunto ?? row.tag ?? row.tema;
  const authorRaw = row.author ?? row.autor ?? row.elaborador;
  const yearRaw = row.year ?? row.ano;

  return {
    textPt,
    textEn: row.textEn ? String(row.textEn) : undefined,
    disciplineId,
    subjectId: subjectIdRaw ? Number(subjectIdRaw) : undefined,
    subjectTag: subjectTagRaw ? String(subjectTagRaw) : undefined,
    author: authorRaw ? String(authorRaw) : undefined,
    year: yearRaw ? Number(yearRaw) : undefined,
    difficulty: difficulty as "easy" | "medium" | "hard",
    expectedAnswerPt,
    expectedAnswerEn: row.expectedAnswerEn ? String(row.expectedAnswerEn) : undefined,
    imageUrl: row.imageUrl ? String(row.imageUrl) : undefined,
    _rowIndex: index + 1,
    _errors: errors,
    _valid: errors.length === 0,
  };
}

// --- Template generators ---
function downloadMCTemplate(format: "csv" | "xlsx") {
  const headers = [
    // Obrigatórios
    "textPt", "disciplineId", "difficulty", "optA", "optB", "optC", "optD", "optE", "correctOption",
    // Classificação / proveniência
    "modelId", "questionType", "banca", "instituicao", "cargo", "carreira", "areaFormacao", "escolaridade", "year", "author",
    // Conteúdo complementar
    "textEn", "subjectId", "subjectTag", "explanationPt", "assertion1", "assertion2", "imageUrl",
  ];

  const example = [
    // Obrigatórios
    "Qual é o principal mecanismo de ação da ivermectina em parasitas?",
    "1",          // disciplineId
    "medium",     // difficulty: very_easy | easy | medium | hard | very_hard
    "Inibe síntese de DNA", "Bloqueia canais de Ca2+", "Potencializa canais de Cl- via glutamato",
    "Inibe síntese proteica", "Bloqueia receptores nicotínicos",
    "C",          // correctOption
    // Classificação
    "M1",                           // modelId: M1–M10
    "multiple_choice",              // questionType
    "CESPE/CEBRASPE",               // banca
    "UnB",                          // instituicao
    "Médico Veterinário",           // cargo
    "Residência Veterinária",       // carreira
    "Medicina Veterinária",         // areaFormacao
    "superior",                     // escolaridade: fundamental | medio | superior
    "2023",                         // year
    "Prof. Silva",                  // author
    // Complementar
    "What is the main mechanism of action of ivermectin?",
    "2",                            // subjectId
    "Farmacologia",                 // subjectTag
    "A ivermectina potencializa canais de Cl- dependentes de glutamato, causando paralisia flácida.",
    "",                             // assertion1 (para M5)
    "",                             // assertion2 (para M5)
    "",                             // imageUrl
  ];

  const download = (content: Blob | null, filename: string) => {
    const blob = content ?? new Blob([], {});
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url; a.download = filename; a.click();
    URL.revokeObjectURL(url);
  };

  if (format === "csv") {
    const bom = "﻿"; // UTF-8 BOM para Excel reconhecer acentuação
    const csv = bom + [headers.join(","), example.map(v => `"${v.replace(/"/g, '""')}"`).join(",")].join("\n");
    download(new Blob([csv], { type: "text/csv;charset=utf-8;" }), "template_questoes_alternativas.csv");
  } else {
    const ws = XLSX.utils.aoa_to_sheet([headers, example]);
    // Largura automática das colunas
    ws["!cols"] = headers.map(() => ({ wch: 25 }));
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Questões MC");
    XLSX.writeFile(wb, "template_questoes_alternativas.xlsx");
  }
}

function downloadDiscursiveTemplate(format: "csv" | "xlsx") {
  const headers = [
    // Obrigatórios
    "textPt", "disciplineId", "difficulty", "expectedAnswerPt",
    // Classificação / proveniência
    "banca", "instituicao", "cargo", "carreira", "areaFormacao", "escolaridade", "year", "author",
    // Complementar
    "textEn", "subjectId", "subjectTag", "expectedAnswerEn", "imageUrl",
  ];

  const example = [
    // Obrigatórios
    "Explique o conceito de refugia e sua importância no controle da resistência anti-helmíntica em bovinos.",
    "1",     // disciplineId
    "hard",  // difficulty: very_easy | easy | medium | hard | very_hard
    "Refugia refere-se à população parasitária não exposta ao anti-helmíntico, que dilui genes de resistência ao se cruzar com os resistentes selecionados. Sua preservação é fundamental para retardar o desenvolvimento de resistência.",
    // Classificação
    "CESPE/CEBRASPE",           // banca
    "UnB",                      // instituicao
    "Médico Veterinário",       // cargo
    "Residência Veterinária",   // carreira
    "Medicina Veterinária",     // areaFormacao
    "superior",                 // escolaridade
    "2023",                     // year
    "Prof. Santos",             // author
    // Complementar
    "Explain the concept of refugia and its importance in controlling anthelmintic resistance in cattle.",
    "1",                        // subjectId
    "Farmacologia — Resistência",
    "Refugia refers to the parasite population not exposed to the anthelmintic, which dilutes resistance genes.",
    "",                         // imageUrl
  ];

  if (format === "csv") {
    const bom = "﻿";
    const csv = bom + [headers.join(","), example.map(v => `"${v.replace(/"/g, '""')}"`).join(",")].join("\n");
    const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a"); a.href = url;
    a.download = "template_questoes_discursivas.csv"; a.click();
    URL.revokeObjectURL(url);
  } else {
    const ws = XLSX.utils.aoa_to_sheet([headers, example]);
    ws["!cols"] = headers.map(() => ({ wch: 28 }));
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Discursivas");
    XLSX.writeFile(wb, "template_questoes_discursivas.xlsx");
  }
}

// --- Template download section ---
function Field({ name, desc }: { name: string; desc: string }) {
  return (
    <div className="flex gap-2">
      <code className="bg-muted px-1.5 py-0.5 rounded text-[11px] font-mono shrink-0">{name}</code>
      <span className="text-muted-foreground">{desc}</span>
    </div>
  );
}

function TemplateDownloadSection({ type }: { type: "multiple_choice" | "discursive" }) {
  const isMC = type === "multiple_choice";
  return (
    <Card className="border-dashed">
      <CardContent className="pt-4 space-y-4">
        {/* Download buttons */}
        <div className="flex items-start gap-3">
          <FileText className="h-5 w-5 text-muted-foreground mt-0.5 shrink-0" />
          <div className="flex-1">
            <p className="text-sm font-medium mb-1">Templates para download</p>
            <p className="text-xs text-muted-foreground mb-3">
              Baixe o template, preencha uma questão por linha e faça o upload acima.
              O arquivo deve ter exatamente os mesmos nomes de coluna do template.
            </p>
            <div className="flex flex-wrap gap-2">
              <Button variant="outline" size="sm"
                onClick={() => isMC ? downloadMCTemplate("csv") : downloadDiscursiveTemplate("csv")}>
                <Download className="h-3 w-3 mr-1.5" /> Template CSV
              </Button>
              <Button variant="outline" size="sm"
                onClick={() => isMC ? downloadMCTemplate("xlsx") : downloadDiscursiveTemplate("xlsx")}>
                <Download className="h-3 w-3 mr-1.5" /> Template XLSX
              </Button>
            </div>
          </div>
        </div>

        {/* Field reference */}
        <div className="rounded-lg bg-muted/20 border border-border/40 p-4 space-y-4 text-xs">
          {/* Obrigatórios */}
          <div className="space-y-1.5">
            <p className="text-[11px] font-semibold text-foreground uppercase tracking-wide">
              Campos obrigatórios
            </p>
            <Field name="textPt" desc="Enunciado completo da questão em português." />
            <Field name="disciplineId" desc="ID numérico da Grande Área (consulte a lista de disciplinas no sistema)." />
            <Field name="difficulty" desc="very_easy | easy | medium | hard | very_hard" />
            {isMC ? (
              <>
                <Field name="optA … optE" desc="Texto de cada uma das 5 alternativas (A, B, C, D, E) — todas obrigatórias." />
                <Field name="correctOption" desc="Letra do gabarito: A | B | C | D | E" />
              </>
            ) : (
              <Field name="expectedAnswerPt" desc="Resposta esperada / gabarito comentado em português." />
            )}
          </div>

          {/* Classificação */}
          <div className="space-y-1.5">
            <p className="text-[11px] font-semibold text-foreground uppercase tracking-wide">
              Classificação e proveniência (opcionais, mas recomendados)
            </p>
            {isMC && (
              <>
                <Field name="modelId" desc="Modelo de item: M1 | M2 | M3 | M4 | M5 | M6 | M7 | M8 | M9 | M10" />
                <Field name="questionType" desc="multiple_choice | assertion_reason | complex_multiple_choice | matching | true_false | ordering | cloze | clinical_case | image_analysis | interpretation (padrão: multiple_choice)" />
              </>
            )}
            <Field name="banca" desc="Banca examinadora: ex. CESPE/CEBRASPE, VUNESP, FGV, INEP" />
            <Field name="instituicao" desc="Instituição que aplicou a prova: ex. USP, UFMG, CFMV" />
            <Field name="cargo" desc="Cargo ou vaga: ex. Médico Veterinário — Nível Superior" />
            <Field name="carreira" desc="Trilha de estudo: ex. Residência Veterinária, ENADE, Concurso Público" />
            <Field name="areaFormacao" desc="Área de formação: ex. Medicina Veterinária" />
            <Field name="escolaridade" desc="fundamental | medio | superior (padrão: superior)" />
            <Field name="year" desc="Ano da prova em 4 dígitos: ex. 2023" />
            <Field name="author" desc="Autor ou elaborador da questão." />
          </div>

          {/* Conteúdo complementar */}
          <div className="space-y-1.5">
            <p className="text-[11px] font-semibold text-foreground uppercase tracking-wide">
              Conteúdo complementar (opcionais)
            </p>
            <Field name="subjectId" desc="ID numérico do assunto dentro da grande área." />
            <Field name="subjectTag" desc="Tag de assunto em texto livre: ex. Farmacologia Veterinária" />
            {isMC ? (
              <>
                <Field name="explanationPt" desc="Justificativa do gabarito com análise dos distratores." />
                <Field name="assertion1" desc="[M5] Texto da Asserção I (proposição principal)." />
                <Field name="assertion2" desc="[M5] Texto da Asserção II — razão (conectada pelo PORQUE)." />
              </>
            ) : (
              <Field name="expectedAnswerEn" desc="Resposta esperada em inglês (bilíngue)." />
            )}
            <Field name="textEn" desc="Enunciado em inglês (interface bilíngue)." />
            <Field name="imageUrl" desc="URL pública de imagem para o enunciado (JPEG, PNG ou WebP)." />
          </div>

          {/* Nota premium */}
          <div className="rounded border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-amber-400 text-[11px]">
            ★ Todas as questões importadas são automaticamente marcadas como <strong>Premium</strong> —
            visíveis apenas para assinantes ativos ou em período de trial.
          </div>
        </div>
      </CardContent>
    </Card>
  );
}

// --- Main Component ---
interface QuestionImportProps {
  onImportComplete?: (count: number) => void;
  /** Pre-loaded rows from AI extraction — shown immediately in preview */
  preloadedRows?: ParsedMCQuestion[];
  initialFileName?: string;
}

export function QuestionImport({ onImportComplete, preloadedRows, initialFileName }: QuestionImportProps) {
  const [importType, setImportType] = useState<"multiple_choice" | "discursive">("multiple_choice");
  const [mcRows, setMcRows] = useState<ParsedMCQuestion[]>(preloadedRows ?? []);
  const [discRows, setDiscRows] = useState<ParsedDiscursiveQuestion[]>([]);
  const [fileNames, setFileNames] = useState<string[]>(initialFileName ? [initialFileName] : []);

  useEffect(() => {
    if (initialFileName) {
      setFileNames([initialFileName]);
    }
  }, [initialFileName]);
  const [isDragging, setIsDragging] = useState(false);
  const [isImporting, setIsImporting] = useState(false);
  const [showPreview, setShowPreview] = useState((preloadedRows?.length ?? 0) > 0);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const [expandedRows, setExpandedRows] = useState<Set<number>>(new Set());
  const [editingRowIndex, setEditingRowIndex] = useState<number | null>(null);
  const [editDraft, setEditDraft] = useState<ParsedMCQuestion | null>(null);

  const toggleExpand = (rowIndex: number) => {
    setExpandedRows(prev => {
      const next = new Set(prev);
      if (next.has(rowIndex)) {
        next.delete(rowIndex);
        if (editingRowIndex === rowIndex) { setEditingRowIndex(null); setEditDraft(null); }
      } else {
        next.add(rowIndex);
      }
      return next;
    });
  };

  const startEdit = (row: ParsedMCQuestion) => {
    setEditingRowIndex(row._rowIndex);
    setEditDraft({ ...row });
  };

  const cancelEdit = () => { setEditingRowIndex(null); setEditDraft(null); };

  const saveEdit = () => {
    if (!editDraft) return;
    const raw: Record<string, unknown> = {
      textPt: editDraft.textPt, textEn: editDraft.textEn,
      disciplineId: editDraft.disciplineId, subjectId: editDraft.subjectId,
      subjectTag: editDraft.subjectTag, author: editDraft.author, year: editDraft.year,
      difficulty: editDraft.difficulty, questionType: editDraft.questionType,
      modelId: editDraft.modelId, banca: editDraft.banca, instituicao: editDraft.instituicao,
      cargo: editDraft.cargo, carreira: editDraft.carreira, areaFormacao: editDraft.areaFormacao,
      escolaridade: editDraft.escolaridade,
      optA: editDraft.optA, optB: editDraft.optB, optC: editDraft.optC,
      optD: editDraft.optD, optE: editDraft.optE, correctOption: editDraft.correctOption,
      explanationPt: editDraft.explanationPt, assertion1: editDraft.assertion1,
      assertion2: editDraft.assertion2, imageUrl: editDraft.imageUrl,
    };
    const validated = validateMCRow(raw, editDraft._rowIndex - 1);
    setMcRows(prev => prev.map(r => r._rowIndex === editDraft._rowIndex ? validated : r));
    setEditingRowIndex(null);
    setEditDraft(null);
  };

  // When parent passes new pre-loaded rows, load them into preview
  useEffect(() => {
    if (preloadedRows && preloadedRows.length > 0) {
      setMcRows(preloadedRows);
      setImportType("multiple_choice");
      setShowPreview(true);
    }
  }, [preloadedRows]);

  const bulkImport = trpc.questions.bulkImport.useMutation();
  const bulkImportDiscursive = trpc.discursive.bulkImport.useMutation();
  const utils = trpc.useUtils();

  // --- Parse file ---
  const processRows = useCallback((rawRows: Record<string, unknown>[]) => {
    if (importType === "multiple_choice") {
      setMcRows(prev => {
        const offset = prev.length;
        const parsed = rawRows.map((r, i) => validateMCRow(r, offset + i));
        return [...prev, ...parsed];
      });
    } else {
      setDiscRows(prev => {
        const offset = prev.length;
        const parsed = rawRows.map((r, i) => validateDiscursiveRow(r, offset + i));
        return [...prev, ...parsed];
      });
    }
    setShowPreview(true);
  }, [importType]);

  const parseFile = useCallback((file: File) => {
    setFileNames(prev => {
      if (prev.includes(file.name)) return prev;
      return [...prev, file.name];
    });
    const ext = file.name.split(".").pop()?.toLowerCase();

    if (ext === "csv") {
      // First read as text to detect delimiter and encoding
      const textReader = new FileReader();
      textReader.onload = (e) => {
        let text = e.target?.result as string;
        // Strip BOM (UTF-8 and UTF-16 exported from European Excel)
        text = text.replace(/^﻿/, "").replace(/^/, "");
        // Detect delimiter: semicolon (European) or comma (standard)
        const firstLine = text.split(/\r?\n/)[0] ?? "";
        const semicolons = (firstLine.match(/;/g) ?? []).length;
        const commas = (firstLine.match(/,/g) ?? []).length;
        const delimiter = semicolons > commas ? ";" : ",";

        Papa.parse(text, {
          header: true,
          skipEmptyLines: true,
          delimiter,
          dynamicTyping: false,
          complete: (results) => processRows(results.data as Record<string, unknown>[]),
          error: (err: { message: string }) => toast.error(`Erro ao ler CSV: ${err.message}`),
        });
      };
      textReader.readAsText(file, "UTF-8");
    } else if (ext === "xlsx" || ext === "xls") {
      const reader = new FileReader();
      reader.onload = (e) => {
        try {
          const wb = XLSX.read(e.target?.result, { type: "array" });
          const ws = wb.Sheets[wb.SheetNames[0]];
          const rows = XLSX.utils.sheet_to_json(ws) as Record<string, unknown>[];
          processRows(rows);
        } catch (err: unknown) {
          toast.error(`Erro ao ler XLSX: ${(err as Error).message}`);
        }
      };
      reader.readAsArrayBuffer(file);
    } else if (ext === "json") {
      const reader = new FileReader();
      reader.onload = (e) => {
        try {
          const data = JSON.parse(e.target?.result as string) as unknown;
          const rows = Array.isArray(data) ? data : (data as Record<string, unknown[]>).questions ?? [];
          processRows(rows as Record<string, unknown>[]);
        } catch (err: unknown) {
          toast.error(`Erro ao ler JSON: ${(err as Error).message}`);
        }
      };
      reader.readAsText(file);
    } else {
      toast.error(`Formato não suportado no arquivo ${file.name}. Use CSV, XLSX ou JSON`);
    }
  }, [processRows]);

  const handleDrop = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(false);
    const files = e.dataTransfer.files;
    if (files && files.length > 0) {
      Array.from(files).forEach(file => parseFile(file));
    }
  }, [parseFile]);

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = e.target.files;
    if (files && files.length > 0) {
      Array.from(files).forEach(file => parseFile(file));
    }
    e.target.value = "";
  };

  // --- Import ---
  const handleImport = async () => {
    setIsImporting(true);
    try {
      if (importType === "multiple_choice") {
        const valid = mcRows.filter(r => r._valid);
        if (valid.length === 0) {
          toast.error("Nenhuma questão válida. Corrija os erros antes de importar");
          return;
        }
        const payload = valid.map(r => ({
          textPt: r.textPt,
          textEn: r.textEn,
          disciplineId: r.disciplineId,
          subjectId: r.subjectId,
          topicId: r.topicId || undefined,
          subjectTag: r.subjectTag,
          author: r.author,
          year: r.year,
          difficulty: r.difficulty,
          questionType: r.questionType,
          modelId: r.modelId,
          // Novos campos de classificação
          banca: r.banca,
          instituicao: r.instituicao,
          cargo: r.cargo,
          carreira: r.carreira,
          areaFormacao: r.areaFormacao,
          escolaridade: r.escolaridade as any,
          // Todas as questões importadas são premium por padrão
          isPremium: true,
          options: [
            { id: "A", textPt: r.optA },
            { id: "B", textPt: r.optB },
            { id: "C", textPt: r.optC },
            { id: "D", textPt: r.optD },
            { id: "E", textPt: r.optE },
          ],
          correctOption: r.correctOption,
          explanationPt: r.explanationPt,
          assertion1: r.assertion1,
          assertion2: r.assertion2,
          imageUrl: r.imageUrl,
        }));
        const firstFileName = fileNames[0] || "";
        const fileExt = firstFileName.split(".").pop()?.toLowerCase();
        const detectedSource = fileExt === "csv" ? "csv" : fileExt === "xlsx" || fileExt === "xls" ? "csv" : "json";
        let batchName = fileNames.length > 0
          ? (fileNames.length === 1 ? fileNames[0] : `${fileNames.length} arquivos (${fileNames.join(", ")})`)
          : undefined;
        if (batchName && batchName.length > 250) {
          batchName = batchName.slice(0, 247) + "...";
        }
        const result = await bulkImport.mutateAsync({
          questions: payload,
          batchName,
          source: detectedSource,
        });
        toast.success(`${result.imported} questões importadas com sucesso`);
        await utils.questions.list.invalidate();
        onImportComplete?.(result.imported);
        setMcRows([]);
        setFileNames([]);
        setShowPreview(false);
      } else {
        const valid = discRows.filter(r => r._valid);
        if (valid.length === 0) {
          toast.error("Nenhuma questão discursiva válida. Corrija os erros antes de importar");
          return;
        }
        const payload = valid.map(r => ({
          textPt: r.textPt,
          textEn: r.textEn,
          disciplineId: r.disciplineId,
          subjectId: r.subjectId,
          subjectTag: r.subjectTag,
          author: r.author,
          year: r.year,
          difficulty: r.difficulty,
          expectedAnswerPt: r.expectedAnswerPt,
          expectedAnswerEn: r.expectedAnswerEn,
          isPremium: true,
        }));
        const result = await bulkImportDiscursive.mutateAsync({
          questions: payload,
        });
        toast.success(`${result.imported} questões discursivas importadas com sucesso`);
        await utils.discursive.list.invalidate();
        onImportComplete?.(result.imported);
        setDiscRows([]);
        setFileNames([]);
        setShowPreview(false);
      }
    } catch (err: unknown) {
      toast.error(`Erro na importação: ${(err as Error).message}`);
    } finally {
      setIsImporting(false);
    }
  };

  const rows = importType === "multiple_choice" ? mcRows : discRows;
  const validCount = rows.filter(r => r._valid).length;
  const errorCount = rows.filter(r => !r._valid).length;

  return (
    <div className="space-y-6">
      {/* Import type selector */}
      <Tabs
        value={importType}
        onValueChange={(v) => {
          setImportType(v as "multiple_choice" | "discursive");
          setMcRows([]);
          setDiscRows([]);
          setFileNames([]);
          setShowPreview(false);
          setExpandedRows(new Set());
          setEditingRowIndex(null);
          setEditDraft(null);
        }}
      >
        <TabsList>
          <TabsTrigger value="multiple_choice">Questões de Alternativas</TabsTrigger>
          <TabsTrigger value="discursive">Questões Discursivas</TabsTrigger>
        </TabsList>

        <TabsContent value="multiple_choice" className="space-y-4">
          <TemplateDownloadSection type="multiple_choice" />
        </TabsContent>
        <TabsContent value="discursive" className="space-y-4">
          <TemplateDownloadSection type="discursive" />
        </TabsContent>
      </Tabs>

      {/* Drop zone */}
      <div
        className={`border-2 border-dashed rounded-xl p-10 text-center cursor-pointer transition-colors ${
          isDragging ? "border-primary bg-primary/5" : "border-border hover:border-primary/50"
        }`}
        onDragOver={(e) => { e.preventDefault(); setIsDragging(true); }}
        onDragLeave={() => setIsDragging(false)}
        onDrop={handleDrop}
        onClick={() => fileInputRef.current?.click()}
      >
        <Upload className="mx-auto mb-3 h-10 w-10 text-muted-foreground" />
        <p className="text-sm font-medium">Arraste um ou mais arquivos aqui ou clique para selecionar</p>
        <p className="text-xs text-muted-foreground mt-1">Suporta múltiplos arquivos JSON, CSV e XLSX</p>
        <input
          ref={fileInputRef}
          type="file"
          accept=".csv,.xlsx,.xls,.json"
          className="hidden"
          multiple
          onChange={handleFileChange}
        />
      </div>

      {/* Preview */}
      {showPreview && rows.length > 0 && (
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <CardTitle className="flex items-center gap-2">
                <Eye className="h-5 w-5" />
                Pré-visualização — {rows.length} linhas
              </CardTitle>
              <div className="flex gap-2">
                <Badge variant="default" className="bg-green-600">
                  <CheckCircle className="h-3 w-3 mr-1" />
                  {validCount} válidas
                </Badge>
                {errorCount > 0 && (
                  <Badge variant="destructive">
                    <XCircle className="h-3 w-3 mr-1" />
                    {errorCount} com erro
                  </Badge>
                )}
              </div>
            </div>
            {fileNames.length > 0 && (
              <p className="text-xs text-muted-foreground mt-2 font-sans">
                <strong>Arquivos carregados:</strong> {fileNames.join(", ")}
              </p>
            )}
          </CardHeader>
          <CardContent>
            {errorCount > 0 && (
              <Alert className="mb-4 border-yellow-500/50 bg-yellow-500/10">
                <AlertTriangle className="h-4 w-4 text-yellow-500" />
                <AlertDescription className="text-yellow-700 dark:text-yellow-400">
                  {errorCount} linha(s) com erros serão ignoradas na importação. Corrija o arquivo e reenvie para importar todas.
                </AlertDescription>
              </Alert>
            )}

            <div className="overflow-x-auto max-h-96">
              {importType === "multiple_choice" ? (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead className="w-8"></TableHead>
                      <TableHead className="w-8">#</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead>Enunciado</TableHead>
                      <TableHead>Tipo</TableHead>
                      <TableHead>Dificuldade</TableHead>
                      <TableHead>Gabarito</TableHead>
                      <TableHead>Erros</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {mcRows.map((row) => (
                      <Fragment key={row._rowIndex}>
                        <TableRow className={row._valid ? "" : "bg-red-500/5"}>
                          <TableCell className="p-1 w-8">
                            <button
                              onClick={() => toggleExpand(row._rowIndex)}
                              className="flex items-center justify-center h-6 w-6 rounded hover:bg-muted transition-colors"
                            >
                              {expandedRows.has(row._rowIndex)
                                ? <ChevronDown className="h-4 w-4 text-muted-foreground" />
                                : <ChevronRight className="h-4 w-4 text-muted-foreground" />}
                            </button>
                          </TableCell>
                          <TableCell className="text-muted-foreground">{row._rowIndex}</TableCell>
                          <TableCell>
                            {row._valid
                              ? <CheckCircle className="h-4 w-4 text-green-500" />
                              : <XCircle className="h-4 w-4 text-red-500" />}
                          </TableCell>
                          <TableCell className="max-w-xs truncate">{row.textPt || "—"}</TableCell>
                          <TableCell>
                            <Badge variant="outline" className="text-xs">{row.questionType}</Badge>
                          </TableCell>
                          <TableCell>
                            <Badge variant="outline" className="text-xs">{row.difficulty}</Badge>
                          </TableCell>
                          <TableCell>
                            <Badge className="text-xs">{row.correctOption}</Badge>
                          </TableCell>
                          <TableCell className="text-xs text-red-500 max-w-xs">
                            {row._errors.join("; ")}
                          </TableCell>
                        </TableRow>
                        {expandedRows.has(row._rowIndex) && (
                          <TableRow>
                            <TableCell colSpan={8} className="p-0 border-t-0">
                              <div className="bg-muted/20 border-b border-border px-5 py-4 space-y-4">
                                {editingRowIndex === row._rowIndex && editDraft ? (
                                  // ── EDIT MODE ──
                                  <div className="space-y-3">
                                    <div className="space-y-1">
                                      <p className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Enunciado (PT)</p>
                                      <textarea
                                        rows={3}
                                        className="w-full rounded border border-border bg-background px-2 py-1.5 text-sm resize-none focus:outline-none focus:ring-1 focus:ring-ring"
                                        value={editDraft.textPt}
                                        onChange={e => setEditDraft(d => d ? { ...d, textPt: e.target.value } : d)}
                                      />
                                    </div>
                                    <div className="space-y-1">
                                      <p className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Alternativas</p>
                                      <div className="grid gap-1.5">
                                        {(["optA","optB","optC","optD","optE"] as const).map((opt, i) => (
                                          <div key={opt} className="flex items-center gap-2">
                                            <span className="text-xs font-mono font-bold w-4 shrink-0">{String.fromCharCode(65+i)}</span>
                                            <input
                                              type="text"
                                              className="flex-1 rounded border border-border bg-background px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-ring"
                                              value={editDraft[opt]}
                                              onChange={e => setEditDraft(d => d ? { ...d, [opt]: e.target.value } : d)}
                                            />
                                          </div>
                                        ))}
                                      </div>
                                    </div>
                                    <div className="grid grid-cols-3 gap-3">
                                      {[
                                        { label: "Gabarito", field: "correctOption", type: "select", options: ["A","B","C","D","E"] },
                                        { label: "Dificuldade", field: "difficulty", type: "select", options: VALID_DIFFICULTIES },
                                        { label: "Tipo", field: "questionType", type: "select", options: VALID_QUESTION_TYPES },
                                      ].map(({ label, field, options }) => (
                                        <div key={field} className="space-y-1">
                                          <p className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">{label}</p>
                                          <select
                                            className="w-full rounded border border-border bg-background px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-ring"
                                            value={String(editDraft[field as keyof ParsedMCQuestion] ?? "")}
                                            onChange={e => setEditDraft(d => d ? { ...d, [field]: e.target.value } : d)}
                                          >
                                            {options.map(o => <option key={o} value={o}>{o}</option>)}
                                          </select>
                                        </div>
                                      ))}
                                    </div>
                                    <div className="grid grid-cols-3 gap-3">
                                      {[
                                        { label: "disciplineId", field: "disciplineId", num: true },
                                        { label: "subjectId", field: "subjectId", num: true },
                                        { label: "Ano", field: "year", num: true },
                                      ].map(({ label, field, num }) => (
                                        <div key={field} className="space-y-1">
                                          <p className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">{label}</p>
                                          <input
                                            type={num ? "number" : "text"}
                                            className="w-full rounded border border-border bg-background px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-ring"
                                            value={String(editDraft[field as keyof ParsedMCQuestion] ?? "")}
                                            onChange={e => setEditDraft(d => d ? { ...d, [field]: e.target.value ? Number(e.target.value) : undefined } : d)}
                                          />
                                        </div>
                                      ))}
                                    </div>
                                    <div className="grid grid-cols-2 gap-3">
                                      {[
                                        ["subjectTag","Assunto (tag)"], ["author","Autor"], ["banca","Banca"],
                                        ["instituicao","Instituição"], ["cargo","Cargo"], ["carreira","Carreira"],
                                        ["areaFormacao","Área de Formação"], ["escolaridade","Escolaridade"],
                                        ["modelId","Model ID"], ["imageUrl","Image URL"], ["textEn","Enunciado (EN)"],
                                      ].map(([field, label]) => (
                                        <div key={field} className="space-y-1">
                                          <p className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">{label}</p>
                                          <input
                                            type="text"
                                            className="w-full rounded border border-border bg-background px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-ring"
                                            value={String(editDraft[field as keyof ParsedMCQuestion] ?? "")}
                                            onChange={e => setEditDraft(d => d ? { ...d, [field]: e.target.value || undefined } : d)}
                                          />
                                        </div>
                                      ))}
                                    </div>
                                    {[["explanationPt","Explicação (PT)"],["assertion1","Asserção I"],["assertion2","Asserção II"]].map(([field, label]) => (
                                      <div key={field} className="space-y-1">
                                        <p className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">{label}</p>
                                        <textarea
                                          rows={2}
                                          className="w-full rounded border border-border bg-background px-2 py-1.5 text-sm resize-none focus:outline-none focus:ring-1 focus:ring-ring"
                                          value={String(editDraft[field as keyof ParsedMCQuestion] ?? "")}
                                          onChange={e => setEditDraft(d => d ? { ...d, [field]: e.target.value || undefined } : d)}
                                        />
                                      </div>
                                    ))}
                                    <div className="flex justify-end gap-2 pt-1">
                                      <Button variant="outline" size="sm" onClick={cancelEdit}>Cancelar edição</Button>
                                      <Button size="sm" onClick={saveEdit}>Salvar alterações</Button>
                                    </div>
                                  </div>
                                ) : (
                                  // ── VIEW MODE ──
                                  <div className="space-y-3">
                                    <div>
                                      <p className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground mb-1">Enunciado</p>
                                      <p className="text-sm">{row.textPt}</p>
                                      {row.textEn && <p className="text-xs text-muted-foreground mt-1 italic">{row.textEn}</p>}
                                    </div>
                                    <div>
                                      <p className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground mb-1">Alternativas</p>
                                      <div className="space-y-0.5">
                                        {(["A","B","C","D","E"] as const).map((letter, i) => {
                                          const text = [row.optA,row.optB,row.optC,row.optD,row.optE][i];
                                          const correct = row.correctOption === letter;
                                          return (
                                            <div key={letter} className={`flex gap-2 text-sm ${correct ? "font-semibold text-green-600 dark:text-green-400" : ""}`}>
                                              <span className="font-mono shrink-0">{letter})</span>
                                              <span>{text || <span className="text-muted-foreground italic">vazio</span>}</span>
                                              {correct && <CheckCircle className="h-3.5 w-3.5 self-center ml-1 shrink-0" />}
                                            </div>
                                          );
                                        })}
                                      </div>
                                    </div>
                                    <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                      <span><strong>disciplineId:</strong> {row.disciplineId}</span>
                                      {row.subjectId && <span><strong>subjectId:</strong> {row.subjectId}</span>}
                                      {row.subjectTag && <span><strong>Assunto:</strong> {row.subjectTag}</span>}
                                      {row.banca && <span><strong>Banca:</strong> {row.banca}</span>}
                                      {row.instituicao && <span><strong>Inst.:</strong> {row.instituicao}</span>}
                                      {row.cargo && <span><strong>Cargo:</strong> {row.cargo}</span>}
                                      {row.year && <span><strong>Ano:</strong> {row.year}</span>}
                                      {row.author && <span><strong>Autor:</strong> {row.author}</span>}
                                      {row.modelId && <span><strong>Model:</strong> {row.modelId}</span>}
                                    </div>
                                    {row.explanationPt && (
                                      <div>
                                        <p className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground mb-1">Explicação</p>
                                        <p className="text-sm text-muted-foreground">{row.explanationPt}</p>
                                      </div>
                                    )}
                                    {row._errors.length > 0 && (
                                      <div className="rounded bg-red-500/10 border border-red-500/20 px-3 py-2">
                                        <p className="text-xs font-semibold text-red-500 mb-1">Erros de validação</p>
                                        <ul className="text-xs text-red-500 space-y-0.5">
                                          {row._errors.map((e, i) => <li key={i}>• {e}</li>)}
                                        </ul>
                                      </div>
                                    )}
                                    <div className="flex justify-end">
                                      <Button variant="outline" size="sm" onClick={() => startEdit(row)}>
                                        <Pencil className="h-3.5 w-3.5 mr-1.5" />
                                        Editar questão
                                      </Button>
                                    </div>
                                  </div>
                                )}
                              </div>
                            </TableCell>
                          </TableRow>
                        )}
                      </Fragment>
                    ))}
                  </TableBody>
                </Table>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead className="w-8"></TableHead>
                      <TableHead className="w-8">#</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead>Enunciado</TableHead>
                      <TableHead>Dificuldade</TableHead>
                      <TableHead>Resposta esperada</TableHead>
                      <TableHead>Erros</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {discRows.map((row) => (
                      <Fragment key={row._rowIndex}>
                        <TableRow className={row._valid ? "" : "bg-red-500/5"}>
                          <TableCell className="p-1 w-8">
                            <button
                              onClick={() => toggleExpand(row._rowIndex)}
                              className="flex items-center justify-center h-6 w-6 rounded hover:bg-muted transition-colors"
                            >
                              {expandedRows.has(row._rowIndex)
                                ? <ChevronDown className="h-4 w-4 text-muted-foreground" />
                                : <ChevronRight className="h-4 w-4 text-muted-foreground" />}
                            </button>
                          </TableCell>
                          <TableCell className="text-muted-foreground">{row._rowIndex}</TableCell>
                          <TableCell>
                            {row._valid
                              ? <CheckCircle className="h-4 w-4 text-green-500" />
                              : <XCircle className="h-4 w-4 text-red-500" />}
                          </TableCell>
                          <TableCell className="max-w-xs truncate">{row.textPt || "—"}</TableCell>
                          <TableCell>
                            <Badge variant="outline" className="text-xs">{row.difficulty}</Badge>
                          </TableCell>
                          <TableCell className="max-w-xs truncate text-muted-foreground">
                            {row.expectedAnswerPt || "—"}
                          </TableCell>
                          <TableCell className="text-xs text-red-500 max-w-xs">
                            {row._errors.join("; ")}
                          </TableCell>
                        </TableRow>
                        {expandedRows.has(row._rowIndex) && (
                          <TableRow>
                            <TableCell colSpan={7} className="p-0 border-t-0">
                              <div className="bg-muted/20 border-b border-border px-5 py-4 space-y-3">
                                <div>
                                  <p className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground mb-1">Enunciado</p>
                                  <p className="text-sm">{row.textPt}</p>
                                  {row.textEn && <p className="text-xs text-muted-foreground mt-1 italic">{row.textEn}</p>}
                                </div>
                                <div>
                                  <p className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground mb-1">Resposta esperada</p>
                                  <p className="text-sm text-muted-foreground">{row.expectedAnswerPt}</p>
                                </div>
                                <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                  <span><strong>disciplineId:</strong> {row.disciplineId}</span>
                                  {row.subjectId && <span><strong>subjectId:</strong> {row.subjectId}</span>}
                                  {row.subjectTag && <span><strong>Assunto:</strong> {row.subjectTag}</span>}
                                  {row.author && <span><strong>Autor:</strong> {row.author}</span>}
                                  {row.year && <span><strong>Ano:</strong> {row.year}</span>}
                                </div>
                                {row._errors.length > 0 && (
                                  <div className="rounded bg-red-500/10 border border-red-500/20 px-3 py-2">
                                    <p className="text-xs font-semibold text-red-500 mb-1">Erros de validação</p>
                                    <ul className="text-xs text-red-500 space-y-0.5">
                                      {row._errors.map((e, i) => <li key={i}>• {e}</li>)}
                                    </ul>
                                  </div>
                                )}
                              </div>
                            </TableCell>
                          </TableRow>
                        )}
                      </Fragment>
                    ))}
                  </TableBody>
                </Table>
              )}
            </div>

            <div className="flex justify-end gap-3 mt-4">
              <Button
                variant="outline"
                onClick={() => { setMcRows([]); setDiscRows([]); setFileNames([]); setShowPreview(false); setExpandedRows(new Set()); setEditingRowIndex(null); setEditDraft(null); if (fileInputRef.current) fileInputRef.current.value = ""; }}
              >
                Cancelar
              </Button>
              <Button
                onClick={handleImport}
                disabled={validCount === 0 || isImporting}
                className="bg-green-600 hover:bg-green-700"
              >
                {isImporting ? "Importando..." : `Importar ${validCount} questão(ões) válida(s)`}
              </Button>
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  );
}
