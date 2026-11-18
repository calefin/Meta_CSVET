import { useState, useMemo, useCallback } from "react";
import { useAuth } from "@/_core/hooks/useAuth";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { trpc } from "@/lib/trpc";
import { toast } from "sonner";
import { useLocation } from "wouter";
import {
  Loader2, Printer, Save, Plus, Minus, ArrowLeft,
  ChevronRight, ChevronLeft, FileText, Settings, List, Download, Check,
} from "lucide-react";
import { generateAnswerSheetSVG, AnswerSheetOverflowError } from "@/lib/answerSheetSvg";
import { printAnswerSheetPDF } from "@/lib/downloadAnswerSheet";
import buildQrPayload from "@/lib/buildQrPayload";
import AnswerSheetPreview from "@/components/answerSheets/AnswerSheetPreview";
import type { AnswerSheetConfig } from "@shared/_core/answerSheetTypes";

const LETTERS = ["A", "B", "C", "D", "E"];

function getQuestionChoices(numAlt: number): string[] {
  if (numAlt === 2) return ["V", "F"];
  return LETTERS.slice(0, numAlt);
}

interface QuickQuestion {
  statement: string;
  alternatives: string[];
  correctAnswer: string | null;
  points: number;
  image?: string; // Data URL or Base64 string
}

type Step = 1 | 2 | 3 | 4;

const STEPS: { label: string; Icon: React.ElementType }[] = [
  { label: "Configuração", Icon: Settings },
  { label: "Questões",     Icon: List },
  { label: "Gabarito",     Icon: FileText },
  { label: "Gerar",        Icon: Download },
];

function makeQuestion(numAlt: number, pts: number): QuickQuestion {
  return { statement: "", alternatives: Array(numAlt).fill(""), correctAnswer: null, points: pts };
}

function buildConfig(
  numQ: number,
  numAlt: number,
  showName: boolean,
  showClass: boolean,
  showDate: boolean,
): AnswerSheetConfig {
  return {
    header: {
      fields: [
        { id: "name",  defaultLabel: "Name",  displayLabel: "Nome",  enabled: showName,  width: "large"  },
        { id: "class", defaultLabel: "Class", displayLabel: "Turma", enabled: showClass, width: "medium" },
        { id: "date",  defaultLabel: "Date",  displayLabel: "Data",  enabled: showDate,  width: "medium" },
      ],
      includeStudentId: false,
      studentIdDigits: 0,
      studentIdLabel: "Matrícula",
      includeKeyVersion: false,
      keyVersionLetters: "",
      keyVersionLabel: "Versão",
    },
    questions: [{
      id: "q-main",
      type: "multiple_choice" as const,
      count: numQ,
      startNumber: 1,
      choices: getQuestionChoices(numAlt),
    }],
  };
}

function ToggleSwitch({ value, onChange, label }: { value: boolean; onChange: (v: boolean) => void; label: string }) {
  return (
    <label className="flex items-center gap-3 cursor-pointer select-none">
      <button
        role="switch"
        aria-checked={value}
        onClick={() => onChange(!value)}
        className={`w-10 h-5 rounded-full transition-colors flex-shrink-0 relative ${value ? "bg-primary" : "bg-border"}`}
      >
        <span className={`absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform ${value ? "translate-x-5" : "translate-x-0.5"}`} />
      </button>
      <span className="text-sm">{label}</span>
    </label>
  );
}

export default function GabaritoRapido() {
  const { user, loading: authLoading } = useAuth();
  const [, navigate] = useLocation();

  // Step 1 — exam metadata
  const [name,       setName]       = useState("");
  const [discipline, setDiscipline] = useState("");
  const [professor,  setProfessor]  = useState("");
  const [examDate,   setExamDate]   = useState("");
  const [numQ,       setNumQ]       = useState(10);
  const [numAlt,     setNumAlt]     = useState(5);
  const [totalPts,   setTotalPts]   = useState("10");
  const [pointsMode, setPointsMode] = useState<"equal" | "custom">("equal");

  // Step 2 — questions
  const [questions, setQuestions] = useState<QuickQuestion[]>(() =>
    Array.from({ length: 10 }, () => makeQuestion(5, 1))
  );

  // Step 3 — answer sheet options
  const [showName,     setShowName]     = useState(true);
  const [showClass,    setShowClass]    = useState(true);
  const [showDate,     setShowDate]     = useState(true);
  const [numVersions,  setNumVersions]  = useState<1 | 2 | 4>(1);

  // Wizard nav
  const [step,     setStep]     = useState<Step>(1);
  const [isSaving,       setIsSaving]       = useState(false);
  const [isGeneratingPdf, setIsGeneratingPdf] = useState(false);

  const createQuick     = trpc.exams.createQuick.useMutation();
  const generateInline  = trpc.pdf.generateInline.useMutation();

  const ptsEach = useMemo(() => {
    if (pointsMode !== "equal") return null;
    const total = parseFloat(totalPts) || 0;
    return numQ > 0 ? total / numQ : 0;
  }, [pointsMode, totalPts, numQ]);

  const sheetConfig = useMemo(
    () => buildConfig(numQ, numAlt, showName, showClass, showDate),
    [numQ, numAlt, showName, showClass, showDate],
  );

  // ─── helpers ────────────────────────────────────────────────────────────────

  function changeNumQ(n: number) {
    const clamped = Math.max(1, Math.min(100, n));
    const pts = pointsMode === "equal" ? (parseFloat(totalPts) || 0) / clamped : 1;
    setNumQ(clamped);
    setQuestions((prev) => {
      const next = Array.from({ length: clamped }, (_, i) => prev[i] ?? makeQuestion(numAlt, pts));
      return pointsMode === "equal" ? next.map((q) => ({ ...q, points: pts })) : next;
    });
  }

  function changeNumAlt(n: number) {
    setNumAlt(n);
    setQuestions((prev) =>
      prev.map((q) => ({
        ...q,
        alternatives: Array.from({ length: n }, (_, i) => q.alternatives[i] ?? ""),
        correctAnswer: q.correctAnswer && LETTERS.indexOf(q.correctAnswer) < n ? q.correctAnswer : null,
      }))
    );
  }

  const updateQ = useCallback((idx: number, patch: Partial<QuickQuestion>) => {
    setQuestions((prev) => {
      const next = [...prev];
      next[idx] = { ...next[idx], ...patch };
      return next;
    });
  }, []);

  function toggleAnswer(idx: number, letter: string) {
    updateQ(idx, { correctAnswer: questions[idx].correctAnswer === letter ? null : letter });
  }

  // ─── actions ────────────────────────────────────────────────────────────────

  async function handleGeneratePdf() {
    setIsGeneratingPdf(true);
    try {
      const result = await generateInline.mutateAsync({
        exam: {
          name,
          discipline: discipline || undefined,
          professor: professor || undefined,
          examDate: examDate
            ? new Date(examDate).toLocaleDateString("pt-BR")
            : undefined,
        },
        questions: questions.map((q, i) => {
          let correct = q.correctAnswer;
          if (numAlt === 2 && correct) {
            correct = correct === "V" ? "a" : "b";
          }
          return {
            type: "multiple_choice" as const,
            statement: q.statement || `Questão ${i + 1}`,
            alternatives: q.alternatives.map((alt, ai) => alt || (numAlt === 2 ? (ai === 0 ? "V" : "F") : LETTERS[ai])),
            correctAnswer: correct ? correct.toLowerCase() : null,
            points: pointsMode === "equal" ? (ptsEach ?? 1) : q.points,
            images: q.image ? [q.image] : [],
          };
        }),
        answerSheetConfig: sheetConfig,
        numberOfVersions: numVersions,
        shuffleMode: numVersions > 1 ? "all" : "none",
      });

      for (const pdf of result.pdfs) {
        const bytes = Uint8Array.from(atob(pdf.data), (c) => c.charCodeAt(0));
        const blob = new Blob([bytes], { type: "application/pdf" });
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = `${name.replace(/\s+/g, "_")}_v${pdf.version}.pdf`;
        a.click();
        URL.revokeObjectURL(url);
      }

      toast.success(result.pdfs.length > 1 ? `${result.pdfs.length} PDFs gerados!` : "PDF gerado!");
    } catch (e: any) {
      toast.error(e?.message || "Erro ao gerar PDF");
    } finally {
      setIsGeneratingPdf(false);
    }
  }

  function handlePrint() {
    if (!name.trim()) { toast.error("Informe o nome da avaliação"); return; }
    try {
      const svg = generateAnswerSheetSVG(name, sheetConfig, { standalone: true });
      printAnswerSheetPDF([svg], `${name} — Gabarito`);
    } catch (e) {
      toast.error(e instanceof AnswerSheetOverflowError ? e.message : "Erro ao gerar o gabarito.");
    }
  }

  async function handleSaveAndPrint() {
    if (!name.trim()) { toast.error("Informe o nome da avaliação"); return; }
    setIsSaving(true);
    try {
      const pts = pointsMode === "equal" ? (parseFloat(totalPts) || 0) / numQ : 1;
      const result = await createQuick.mutateAsync({
        name,
        discipline: discipline || "Geral",
        professor: professor || "-",
        examDate: examDate ? new Date(examDate) : undefined,
        totalPoints: totalPts || undefined,
        questions: questions.map((q, i) => {
          let correct = q.correctAnswer;
          if (numAlt === 2 && correct) {
            correct = correct === "V" ? "a" : "b";
          }
          return {
            numAlternatives: numAlt,
            correctAnswer: correct ? correct.toLowerCase() : null,
            points: pointsMode === "equal" ? pts : q.points,
            statement: q.statement || `Questão ${i + 1}`,
            alternatives: q.alternatives.map((alt, ai) => alt || (numAlt === 2 ? (ai === 0 ? "V" : "F") : LETTERS[ai])),
            images: q.image ? [q.image.split(",")[1] || q.image] : [],
          };
        }),
      });
      toast.success("Avaliação salva!");
      const qrPayload = buildQrPayload({ templateId: String(result.examId) });
      const svg = generateAnswerSheetSVG(name, sheetConfig, {
        standalone: true,
        formId: String(result.examId),
        qrPayload,
      });
      printAnswerSheetPDF([svg], `${name} — Gabarito`);
      navigate(`/exam/${result.examId}/pdf`);
    } catch (e: any) {
      toast.error(e?.message || "Erro ao salvar avaliação");
    } finally {
      setIsSaving(false);
    }
  }

  // ─── render ─────────────────────────────────────────────────────────────────

  if (authLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <Loader2 className="animate-spin w-8 h-8" />
      </div>
    );
  }
  if (!user) { navigate("/"); return null; }

  const filledStatements = questions.filter((q) => q.statement.trim()).length;
  const filledAnswers    = questions.filter((q) => q.correctAnswer).length;

  return (
    <div className="max-w-4xl mx-auto space-y-6 pb-16">

      {/* Back link */}
      <Button variant="ghost" size="sm" onClick={() => navigate("/exams")} className="px-0">
        <ArrowLeft className="w-4 h-4 mr-1" /> Avaliações
      </Button>

      <div>
        <h1 className="text-2xl font-bold">Gabarito Rápido</h1>
        <p className="text-muted-foreground text-sm">
          Crie e imprima um gabarito em segundos, sem precisar de banco de questões
        </p>
      </div>

      {/* Step indicator */}
      <div className="flex items-center">
        {STEPS.map(({ label, Icon }, i) => {
          const s = (i + 1) as Step;
          const active = step === s;
          const done   = step > s;
          return (
            <div key={i} className="flex items-center flex-1 last:flex-none">
              <button
                onClick={() => (done || active) && setStep(s)}
                disabled={!done && !active}
                className={`flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium transition-colors ${
                  active ? "bg-primary text-primary-foreground"
                  : done  ? "text-primary hover:bg-primary/10 cursor-pointer"
                  :         "text-muted-foreground cursor-default"
                }`}
              >
                {done ? <Check className="w-4 h-4" /> : <Icon className="w-4 h-4" />}
                <span className="hidden sm:inline">{label}</span>
                <span className="sm:hidden font-bold">{s}</span>
              </button>
              {i < STEPS.length - 1 && (
                <div className={`h-px flex-1 mx-1 ${step > s ? "bg-primary" : "bg-border"}`} />
              )}
            </div>
          );
        })}
      </div>

      {/* ── Step 1: Configuração ── */}
      {step === 1 && (
        <div className="space-y-4">
          <div className="border rounded-xl p-5 bg-card space-y-4">
            <h2 className="font-semibold text-xs uppercase tracking-wide text-muted-foreground">Dados da Avaliação</h2>
            <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
              <div className="col-span-2 space-y-1">
                <Label className="text-xs">Nome da Avaliação *</Label>
                <Input value={name} onChange={(e) => setName(e.target.value)} placeholder="Ex: Prova 1 — Biologia" />
              </div>
              <div className="space-y-1">
                <Label className="text-xs">Disciplina</Label>
                <Input value={discipline} onChange={(e) => setDiscipline(e.target.value)} placeholder="Ex: Biologia" />
              </div>
              <div className="space-y-1">
                <Label className="text-xs">Data</Label>
                <Input type="date" value={examDate} onChange={(e) => setExamDate(e.target.value)} />
              </div>
              <div className="col-span-2 space-y-1">
                <Label className="text-xs">Professor</Label>
                <Input value={professor} onChange={(e) => setProfessor(e.target.value)} placeholder="Ex: Prof. João Silva" />
              </div>
            </div>
          </div>

          <div className="border rounded-xl p-5 bg-card space-y-4">
            <h2 className="font-semibold text-xs uppercase tracking-wide text-muted-foreground">Configuração das Questões</h2>
            <div className="flex flex-wrap gap-6 items-end">

              {/* Num questions */}
              <div className="space-y-1">
                <Label className="text-xs">Nº de questões</Label>
                <div className="flex items-center gap-1.5">
                  <Button variant="outline" size="icon" className="h-8 w-8" onClick={() => changeNumQ(numQ - 1)}>
                    <Minus className="w-3 h-3" />
                  </Button>
                  <Input
                    type="number" min={1} max={100}
                    value={numQ}
                    onChange={(e) => changeNumQ(parseInt(e.target.value) || 1)}
                    className="w-16 text-center h-8 text-sm"
                  />
                  <Button variant="outline" size="icon" className="h-8 w-8" onClick={() => changeNumQ(numQ + 1)}>
                    <Plus className="w-3 h-3" />
                  </Button>
                </div>
              </div>

              {/* Num alternatives */}
              <div className="space-y-1">
                <Label className="text-xs">Alternativas por questão</Label>
                <div className="flex gap-1">
                  {[2, 3, 4, 5].map((n) => (
                    <button
                      key={n}
                      onClick={() => changeNumAlt(n)}
                      className={`px-3 py-1.5 text-sm rounded border transition ${numAlt === n ? "bg-primary text-primary-foreground border-primary" : "border-border hover:border-primary/50"}`}
                    >
                      {n === 2 ? "V/F" : LETTERS.slice(0, n).join("")}
                    </button>
                  ))}
                </div>
              </div>

              {/* Points mode */}
              <div className="space-y-1">
                <Label className="text-xs">Distribuição de pontos</Label>
                <div className="flex gap-1">
                  {(["equal", "custom"] as const).map((m) => (
                    <button
                      key={m}
                      onClick={() => setPointsMode(m)}
                      className={`px-3 py-1.5 text-sm rounded border transition ${pointsMode === m ? "bg-primary text-primary-foreground border-primary" : "border-border hover:border-primary/50"}`}
                    >
                      {m === "equal" ? "Igualitária" : "Personalizada"}
                    </button>
                  ))}
                </div>
              </div>

              {/* Total points (equal mode) */}
              {pointsMode === "equal" && (
                <div className="space-y-1">
                  <Label className="text-xs">Pontuação total</Label>
                  <div className="flex items-center gap-2">
                    <Input
                      type="number" min={0} step={0.5}
                      value={totalPts}
                      onChange={(e) => setTotalPts(e.target.value)}
                      className="w-24 h-8 text-sm"
                    />
                    {numQ > 0 && (
                      <span className="text-xs text-muted-foreground whitespace-nowrap">
                        = {ptsEach?.toFixed(2)} pts/q
                      </span>
                    )}
                  </div>
                </div>
              )}

            </div>
          </div>
        </div>
      )}

      {/* ── Step 2: Questões ── */}
      {step === 2 && (
        <div className="space-y-3">
          <div className="flex flex-col gap-1.5 p-3 rounded-lg border bg-blue-50/50 text-blue-900 border-blue-200/50 text-xs">
            <p className="font-semibold text-blue-800">Dica de Formatação:</p>
            <p className="text-blue-700">• Use <strong>**negrito**</strong> ou <em>*itálico*</em> nos enunciados e alternativas para formatar o texto do PDF.</p>
            <p className="text-blue-700">• Use <strong>&lt;br/&gt;</strong> para adicionar quebras de linha manuais.</p>
          </div>

          <p className="text-sm text-muted-foreground">
            Preencha o enunciado e as alternativas de cada questão. Clique na letra para marcar a resposta correta.
            O enunciado é <strong>opcional</strong> — deixe em branco para gerar apenas a folha de gabarito.
          </p>

          {questions.map((q, idx) => (
            <div key={idx} className="border rounded-xl bg-card overflow-hidden">
              {/* Card header */}
              <div className="flex items-center gap-3 px-4 py-2.5 border-b bg-muted/20">
                <div className="w-7 h-7 rounded-full bg-primary text-primary-foreground flex items-center justify-center text-xs font-bold flex-shrink-0">
                  {idx + 1}
                </div>
                {q.correctAnswer && (
                  <span className="text-xs text-emerald-600 font-medium">
                    Gabarito: {q.correctAnswer}
                  </span>
                )}
                <div className="ml-auto flex items-center gap-2">
                  {pointsMode === "custom" ? (
                    <>
                      <Label className="text-xs text-muted-foreground">Pts:</Label>
                      <Input
                        type="number" min={0} step={0.5}
                        value={q.points}
                        onChange={(e) => updateQ(idx, { points: parseFloat(e.target.value) || 0 })}
                        className="w-16 h-7 text-xs text-center"
                      />
                    </>
                  ) : (
                    <span className="text-xs text-muted-foreground">{ptsEach?.toFixed(2)} pts</span>
                  )}
                </div>
              </div>

              {/* Card body */}
              <div className="p-4 space-y-3">
                <Textarea
                  placeholder={`Enunciado da questão ${idx + 1} (opcional)`}
                  value={q.statement}
                  onChange={(e) => updateQ(idx, { statement: e.target.value })}
                  rows={2}
                  className="text-sm resize-none"
                />

                {/* Image Upload/Preview */}
                <div className="space-y-1">
                  <Label className="text-xs text-muted-foreground">Imagem da Questão (opcional)</Label>
                  <div className="flex items-center gap-3">
                    {q.image ? (
                      <div className="relative w-20 h-20 rounded border overflow-hidden bg-muted flex items-center justify-center">
                        <img src={q.image} alt="Preview da Questão" className="object-contain w-full h-full" />
                        <button
                          type="button"
                          onClick={() => updateQ(idx, { image: undefined })}
                          className="absolute top-0.5 right-0.5 bg-destructive text-destructive-foreground rounded-full p-0.5 shadow-md hover:bg-destructive/90 transition"
                        >
                          <Minus className="w-3 h-3" />
                        </button>
                      </div>
                    ) : (
                      <label className="flex items-center justify-center border-2 border-dashed rounded-lg p-3 cursor-pointer hover:bg-muted/40 transition w-full h-14">
                        <span className="text-xs text-muted-foreground flex items-center gap-1.5">
                          <Plus className="w-3.5 h-3.5" /> Enviar foto/imagem
                        </span>
                        <input
                          type="file"
                          accept="image/*"
                          className="hidden"
                          onChange={(e) => {
                            const file = e.target.files?.[0];
                            if (file) {
                              const reader = new FileReader();
                              reader.onloadend = () => {
                                updateQ(idx, { image: reader.result as string });
                              };
                              reader.readAsDataURL(file);
                            }
                          }}
                        />
                      </label>
                    )}
                  </div>
                </div>

                <div className="space-y-1.5">
                  {getQuestionChoices(numAlt).map((letter, li) => {
                    const isCorrect = q.correctAnswer === letter;
                    return (
                      <div key={letter} className="flex items-center gap-2">
                        <button
                          onClick={() => toggleAnswer(idx, letter)}
                          title={isCorrect ? "Resposta correta (clique para desmarcar)" : "Marcar como correta"}
                          className={`w-7 h-7 rounded-full border-2 text-xs font-bold flex-shrink-0 transition ${
                            isCorrect
                              ? "bg-emerald-500 border-emerald-500 text-white"
                              : "border-border hover:border-emerald-400 text-muted-foreground hover:text-foreground"
                          }`}
                        >
                          {letter}
                        </button>
                        <Input
                          placeholder={numAlt === 2 ? (letter === "V" ? "Verdadeiro" : "Falso") : `Alternativa ${letter}`}
                          value={q.alternatives[li] ?? ""}
                          onChange={(e) => {
                            const alts = [...q.alternatives];
                            alts[li] = e.target.value;
                            updateQ(idx, { alternatives: alts });
                          }}
                          className="h-8 text-sm"
                        />
                      </div>
                    );
                  })}
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* ── Step 3: Folha de Gabarito ── */}
      {step === 3 && (
        <div className="grid md:grid-cols-2 gap-6">
          <div className="space-y-4">
            <div className="border rounded-xl p-5 bg-card space-y-4">
              <h2 className="font-semibold text-xs uppercase tracking-wide text-muted-foreground">Cabeçalho da Folha</h2>
              <div className="space-y-3">
                <ToggleSwitch value={showName}  onChange={setShowName}  label="Campo Nome"  />
                <ToggleSwitch value={showClass} onChange={setShowClass} label="Campo Turma" />
                <ToggleSwitch value={showDate}  onChange={setShowDate}  label="Campo Data"  />
              </div>
            </div>

            <div className="border rounded-xl p-5 bg-card space-y-3">
              <h2 className="font-semibold text-xs uppercase tracking-wide text-muted-foreground">Versões da Prova</h2>
              <div className="flex gap-2">
                {([1, 2, 4] as const).map((v) => (
                  <button
                    key={v}
                    onClick={() => setNumVersions(v)}
                    className={`px-4 py-2 text-sm rounded-lg border transition ${numVersions === v ? "bg-primary text-primary-foreground border-primary" : "border-border hover:border-primary/50"}`}
                  >
                    {v === 1 ? "1 versão" : `${v} versões`}
                  </button>
                ))}
              </div>
              {numVersions > 1 && (
                <p className="text-xs text-muted-foreground">
                  As questões serão embaralhadas para gerar cada versão.
                </p>
              )}
            </div>
          </div>

          <div>
            <p className="text-xs text-muted-foreground mb-2">Pré-visualização</p>
            <div className="border rounded-xl overflow-hidden bg-white">
              <AnswerSheetPreview name={name} config={sheetConfig} scale={0.44} />
            </div>
          </div>
        </div>
      )}

      {/* ── Step 4: Gerar ── */}
      {step === 4 && (
        <div className="space-y-4">
          <div className="border rounded-xl p-5 bg-card space-y-3">
            <h2 className="font-semibold text-xs uppercase tracking-wide text-muted-foreground">Resumo</h2>
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
              <div>
                <span className="text-muted-foreground block text-xs mb-0.5">Nome</span>
                {name}
              </div>
              <div>
                <span className="text-muted-foreground block text-xs mb-0.5">Disciplina</span>
                {discipline || "—"}
              </div>
              <div>
                <span className="text-muted-foreground block text-xs mb-0.5">Questões</span>
                {numQ} × {LETTERS.slice(0, numAlt).join("")}
              </div>
              <div>
                <span className="text-muted-foreground block text-xs mb-0.5">Pontuação total</span>
                {totalPts} pts
              </div>
              <div>
                <span className="text-muted-foreground block text-xs mb-0.5">Versões</span>
                {numVersions}
              </div>
              <div>
                <span className="text-muted-foreground block text-xs mb-0.5">Enunciados preenchidos</span>
                {filledStatements} / {numQ}
              </div>
              <div>
                <span className="text-muted-foreground block text-xs mb-0.5">Gabarito preenchido</span>
                {filledAnswers} / {numQ}
              </div>
            </div>
          </div>

          <div className="grid md:grid-cols-2 gap-4">
            {/* Folha de gabarito */}
            <div className="border rounded-xl p-5 bg-card space-y-3">
              <h3 className="font-semibold">Folha de Gabarito</h3>
              <p className="text-sm text-muted-foreground">
                Imprime apenas a folha de bolinha para o aluno marcar as respostas.
              </p>
              <div className="flex gap-2">
                <Button variant="outline" onClick={handlePrint} className="flex-1">
                  <Printer className="w-4 h-4 mr-2" />
                  Imprimir
                </Button>
                <Button onClick={handleSaveAndPrint} disabled={isSaving} className="flex-1">
                  {isSaving
                    ? <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                    : <Save className="w-4 h-4 mr-2" />}
                  Salvar e Imprimir
                </Button>
              </div>
            </div>

            {/* PDF completo */}
            <div className="border rounded-xl p-5 bg-card space-y-3">
              <h3 className="font-semibold">PDF Completo da Prova</h3>
              <p className="text-sm text-muted-foreground">
                Gera o PDF com questões em 2 colunas e o gabarito embutido na pág. 1.
              </p>
              <Button
                className="w-full"
                disabled={filledStatements === 0 || isGeneratingPdf}
                onClick={handleGeneratePdf}
              >
                {isGeneratingPdf
                  ? <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                  : <FileText className="w-4 h-4 mr-2" />}
                {isGeneratingPdf ? "Gerando PDF..." : "Gerar PDF Completo"}
              </Button>
              {filledStatements === 0 && (
                <p className="text-xs text-amber-600">
                  Preencha ao menos um enunciado na Etapa 2 para habilitar.
                </p>
              )}
            </div>
          </div>
        </div>
      )}

      {/* Navigation */}
      <div className="flex items-center justify-between pt-2 border-t">
        <Button
          variant="outline"
          onClick={() => setStep((s) => Math.max(1, s - 1) as Step)}
          disabled={step === 1}
        >
          <ChevronLeft className="w-4 h-4 mr-1" /> Anterior
        </Button>

        {step < 4 && (
          <Button
            onClick={() => setStep((s) => Math.min(4, s + 1) as Step)}
            disabled={step === 1 && !name.trim()}
          >
            Próximo <ChevronRight className="w-4 h-4 ml-1" />
          </Button>
        )}
      </div>

    </div>
  );
}
