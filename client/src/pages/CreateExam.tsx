import { useState } from "react";
import { useAuth } from "@/_core/hooks/useAuth";
import { useI18n } from "@/contexts/I18nContext";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Slider } from "@/components/ui/slider";
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from "@/components/ui/select";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { Switch } from "@/components/ui/switch";
import { Badge } from "@/components/ui/badge";
import { Separator } from "@/components/ui/separator";
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter,
} from "@/components/ui/dialog";
import { trpc } from "@/lib/trpc";
import { toast } from "sonner";
import { useLocation } from "wouter";
import {
  Loader2, ChevronRight, ChevronLeft, FileText, CheckSquare,
  Plus, Trash2, LayoutGrid, LayoutList, TreeDeciduous, Accessibility,
  Printer, Shuffle, BookOpen, Globe, PenLine,
} from "lucide-react";

type ExamMode = "full" | "answer_key_only" | null;
type Step = "choose" | "info" | "questions" | "print";
type HeaderType = "standard" | "custom";
type PrintStyle = "double_column" | "single_column" | "economic" | "accessibility";
type PrintSided = "double" | "single";
type AnswerKeyPos = "first_page" | "last_page" | "separate";
type ShuffleMode = "all" | "questions" | "items" | "none";

const DEFAULT_INSTRUCTIONS = `1. Leia atentamente todas as questões antes de responder.
2. Responda com clareza e objetividade.
3. Não é permitido consultar materiais durante a prova.
4. Tempo total: 2 horas.`;

export default function CreateExam() {
  const { user, loading: authLoading } = useAuth();
  const { t } = useI18n();
  const [, navigate] = useLocation();

  const [mode, setMode] = useState<ExamMode>(null);
  const [step, setStep] = useState<Step>("choose");
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Info fields
  const [name, setName] = useState("");
  const [headerType, setHeaderType] = useState<HeaderType>("standard");
  const [institutionId, setInstitutionId] = useState("");
  const [courseId, setCourseId] = useState("");
  const [classId, setClassId] = useState("");
  const [discipline, setDiscipline] = useState("");
  const [professor, setProfessor] = useState("");
  const [examDate, setExamDate] = useState("");
  const [instructions, setInstructions] = useState(DEFAULT_INSTRUCTIONS);
  const [examLanguage, setExamLanguage] = useState<"pt" | "en" | "es">("pt");

  // Questions & scoring
  const [selectedQuestionIds, setSelectedQuestionIds] = useState<number[]>([]);
  const [distributeEvenly, setDistributeEvenly] = useState(true);
  const [totalPoints, setTotalPoints] = useState("10");
  const [perQuestionScores, setPerQuestionScores] = useState<Record<number, string>>({});

  // Print options
  const [printStyle, setPrintStyle] = useState<PrintStyle>("double_column");
  const [printSided, setPrintSided] = useState<PrintSided>("single");
  const [answerKeyPos, setAnswerKeyPos] = useState<AnswerKeyPos>("last_page");
  const [shuffleMode, setShuffleMode] = useState<ShuffleMode>("all");
  const [numVersions, setNumVersions] = useState(1);

  const [showQuickAdd, setShowQuickAdd] = useState(false);

  const { data: questions = [], refetch: refetchQuestions } = trpc.questions.list.useQuery({});
  const { data: institutions = [] } = trpc.institutions.list.useQuery();
  const { data: classes = [] } = trpc.classes.list.useQuery({ includeArchived: false });
  const { data: availableCourses = [] } = trpc.institutions.listCourses.useQuery(
    { institutionId: Number(institutionId) },
    { enabled: !!institutionId }
  );

  const createExamMutation = trpc.exams.create.useMutation();
  const createQuestionMutation = trpc.questions.create.useMutation();

  if (authLoading) return <div className="flex items-center justify-center min-h-[60vh]"><Loader2 className="animate-spin w-8 h-8" /></div>;
  if (!user) { navigate("/"); return null; }

  function toggleQuestion(id: number) {
    setSelectedQuestionIds((prev) =>
      prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]
    );
  }

  function computeTotalPoints(): number {
    if (distributeEvenly) return parseFloat(totalPoints) || 0;
    return Object.values(perQuestionScores).reduce((acc, v) => acc + (parseFloat(v) || 0), 0);
  }

  function pointsPerQuestion(): number {
    if (!distributeEvenly || selectedQuestionIds.length === 0) return 0;
    return (parseFloat(totalPoints) || 0) / selectedQuestionIds.length;
  }

  async function handleSubmit() {
    if (!name.trim()) { toast.error("Informe o nome da avaliação"); return; }
    if (mode === "full" && selectedQuestionIds.length === 0) { toast.error("Selecione pelo menos uma questão"); return; }

    setIsSubmitting(true);
    try {
      const payload: any = {
        name,
        course: availableCourses.find((c: any) => c.id === Number(courseId))?.name || discipline || "–",
        class: classes.find((c: any) => c.id === Number(classId))?.name || "–",
        discipline,
        professor,
        examDate: examDate ? new Date(examDate) : undefined,
        instructions,
        numberOfVersions: numVersions,
        questionIds: selectedQuestionIds,
        headerType,
        examLanguage,
        examType: mode ?? "full",
        totalPoints: totalPoints ? totalPoints : undefined,
        institutionId: institutionId ? Number(institutionId) : undefined,
        classId: classId ? Number(classId) : undefined,
      };
      const result = await createExamMutation.mutateAsync(payload);
      toast.success(t("exams.messages.created"));
      navigate("/exams");
    } catch (e: any) {
      toast.error(e?.message || t("messages.error"));
    } finally {
      setIsSubmitting(false);
    }
  }

  const steps: Step[] = mode === null ? ["choose"] : ["choose", "info", "questions", "print"];
  const stepIndex = steps.indexOf(step);
  const canGoNext = step === "choose" ? mode !== null
    : step === "info" ? !!name.trim()
    : step === "questions" ? (mode === "answer_key_only" || selectedQuestionIds.length > 0)
    : true;

  function goNext() {
    if (step === "choose") setStep("info");
    else if (step === "info") setStep("questions");
    else if (step === "questions") setStep("print");
  }
  function goPrev() {
    if (step === "print") setStep("questions");
    else if (step === "questions") setStep("info");
    else if (step === "info") { setStep("choose"); setMode(null); }
  }

  return (
    <div className="max-w-3xl mx-auto space-y-6">
      <div>
        <h1 className="text-2xl font-bold">{t("exams.create")}</h1>
        {step !== "choose" && (
          <div className="flex gap-2 mt-4">
            {(["info", "questions", "print"] as const).map((s, i) => (
              <div key={s} className={`flex-1 h-1.5 rounded-full transition ${
                ["info", "questions", "print"].indexOf(step) >= i ? "bg-primary" : "bg-muted"
              }`} />
            ))}
          </div>
        )}
      </div>

      {/* Step: Choose mode */}
      {step === "choose" && (
        <div className="grid md:grid-cols-2 gap-4">
          <Card
            className={`cursor-pointer border-2 transition-all ${mode === "full" ? "border-primary" : "border-border hover:border-primary/50"}`}
            onClick={() => setMode("full")}
          >
            <CardHeader>
              <div className="flex items-center gap-3">
                <FileText className="w-8 h-8 text-primary" />
                <CardTitle className="text-lg">{t("exams.createNew")}</CardTitle>
              </div>
              <CardDescription>Avaliação completa com questões, cabeçalho e gabarito automático</CardDescription>
            </CardHeader>
          </Card>
          <Card
            className={`cursor-pointer border-2 transition-all ${mode === "answer_key_only" ? "border-primary" : "border-border hover:border-primary/50"}`}
            onClick={() => setMode("answer_key_only")}
          >
            <CardHeader>
              <div className="flex items-center gap-3">
                <CheckSquare className="w-8 h-8 text-primary" />
                <CardTitle className="text-lg">{t("exams.createAnswerKeyOnly")}</CardTitle>
              </div>
              <CardDescription>Apenas o gabarito de preenchimento, sem questões</CardDescription>
            </CardHeader>
          </Card>
        </div>
      )}

      {/* Step: Info */}
      {step === "info" && (
        <Card>
          <CardHeader>
            <CardTitle>Informações da Avaliação</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="space-y-1">
              <Label>{t("exams.examName")} *</Label>
              <Input value={name} onChange={(e) => setName(e.target.value)} placeholder="Ex: Prova 1 — Biologia" />
            </div>

            {/* Header type */}
            <div className="space-y-2">
              <Label>{t("exams.headerType")}</Label>
              <RadioGroup value={headerType} onValueChange={(v) => setHeaderType(v as HeaderType)} className="flex gap-4">
                <div className="flex items-center gap-2">
                  <RadioGroupItem value="standard" id="h-std" />
                  <Label htmlFor="h-std" className="cursor-pointer">{t("exams.standardHeader")}</Label>
                </div>
                <div className="flex items-center gap-2">
                  <RadioGroupItem value="custom" id="h-cus" />
                  <Label htmlFor="h-cus" className="cursor-pointer">{t("exams.customHeader")}</Label>
                </div>
              </RadioGroup>
            </div>

            {headerType === "standard" ? (
              <div className="grid md:grid-cols-2 gap-4">
                <div className="space-y-1">
                  <Label>{t("institutions.title")} <span className="text-muted-foreground text-xs">({t("common.optional")})</span></Label>
                  <Select value={institutionId} onValueChange={(v) => { setInstitutionId(v); setCourseId(""); }}>
                    <SelectTrigger><SelectValue placeholder="Selecionar..." /></SelectTrigger>
                    <SelectContent>
                      {institutions.map((i: any) => <SelectItem key={i.id} value={i.id.toString()}>{i.name}</SelectItem>)}
                    </SelectContent>
                  </Select>
                </div>
                {availableCourses.length > 0 && (
                  <div className="space-y-1">
                    <Label>{t("classes.course")} <span className="text-muted-foreground text-xs">({t("common.optional")})</span></Label>
                    <Select value={courseId} onValueChange={setCourseId}>
                      <SelectTrigger><SelectValue placeholder="Selecionar..." /></SelectTrigger>
                      <SelectContent>
                        {availableCourses.map((c: any) => <SelectItem key={c.id} value={c.id.toString()}>{c.name}</SelectItem>)}
                      </SelectContent>
                    </Select>
                  </div>
                )}
                <div className="space-y-1">
                  <Label>{t("classes.title")} <span className="text-muted-foreground text-xs">({t("common.optional")})</span></Label>
                  <Select value={classId} onValueChange={setClassId}>
                    <SelectTrigger><SelectValue placeholder="Selecionar..." /></SelectTrigger>
                    <SelectContent>
                      {classes.map((c: any) => <SelectItem key={c.id} value={c.id.toString()}>{c.name} — {c.semester}º/{c.classYear}</SelectItem>)}
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-1">
                  <Label>{t("exams.discipline")} *</Label>
                  <Input value={discipline} onChange={(e) => setDiscipline(e.target.value)} placeholder="Ex: Biologia" />
                </div>
                <div className="space-y-1">
                  <Label>{t("exams.professor")} *</Label>
                  <Input value={professor} onChange={(e) => setProfessor(e.target.value)} placeholder="Ex: Prof. João Silva" />
                </div>
                <div className="space-y-1">
                  <Label>{t("exams.examDate")}</Label>
                  <Input type="date" value={examDate} onChange={(e) => setExamDate(e.target.value)} />
                </div>
              </div>
            ) : (
              <div className="p-4 border-2 border-dashed rounded-lg flex flex-col items-center gap-3 text-muted-foreground">
                <BookOpen className="w-8 h-8" />
                <p className="text-sm">Upload do cabeçalho personalizado (imagem)</p>
                <Button variant="outline" size="sm">Selecionar Imagem</Button>
              </div>
            )}

            <div className="space-y-1">
              <Label>{t("exams.instructions")}</Label>
              <Textarea value={instructions} onChange={(e) => setInstructions(e.target.value)} rows={5} className="font-mono text-sm" />
            </div>

            <div className="space-y-1">
              <Label>{t("exams.examLanguage")}</Label>
              <Select value={examLanguage} onValueChange={(v) => setExamLanguage(v as "pt" | "en" | "es")}>
                <SelectTrigger className="w-40"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="pt">🇧🇷 Português</SelectItem>
                  <SelectItem value="en">🇺🇸 English</SelectItem>
                  <SelectItem value="es">🇪🇸 Español</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Step: Questions */}
      {step === "questions" && (
        <div className="space-y-4">
          {mode === "answer_key_only" ? (
            <Card>
              <CardContent className="py-8 text-center">
                <CheckSquare className="w-12 h-12 mx-auto text-muted-foreground mb-3" />
                <p className="text-muted-foreground">Modo de gabarito simples — não é necessário selecionar questões.</p>
                <p className="text-sm text-muted-foreground mt-1">Informe apenas a quantidade de questões e pontuação.</p>
              </CardContent>
            </Card>
          ) : (
            <>
              <Card>
                <CardHeader className="flex flex-row items-center justify-between pb-3">
                  <CardTitle className="text-base">
                    {selectedQuestionIds.length === 0
                      ? `${t("exams.selectQuestions")} — Total: 0 pts`
                      : `${selectedQuestionIds.length} questão(ões) — Total: ${computeTotalPoints().toFixed(1)} pts`}
                  </CardTitle>
                  <Button size="sm" variant="outline" onClick={() => setShowQuickAdd(true)}>
                    <PenLine className="w-3.5 h-3.5 mr-1" /> Nova Questão
                  </Button>
                </CardHeader>
                <CardContent>
                  <div className="space-y-2 max-h-72 overflow-y-auto pr-1">
                    {questions.length === 0 ? (
                      <p className="text-muted-foreground text-sm">
                        Nenhuma questão no banco.{" "}
                        <button onClick={() => setShowQuickAdd(true)} className="text-primary underline">Criar questão agora</button>
                      </p>
                    ) : questions.map((q: any) => {
                      const isSelected = selectedQuestionIds.includes(q.id);
                      return (
                        <div
                          key={q.id}
                          onClick={() => toggleQuestion(q.id)}
                          className={`p-3 border-2 rounded-lg cursor-pointer transition text-sm ${isSelected ? "border-primary bg-primary/5" : "border-border hover:border-primary/40"}`}
                        >
                          <div className="flex items-start gap-2">
                            <input type="checkbox" checked={isSelected} onChange={() => {}} className="mt-0.5 shrink-0" />
                            <div className="flex-1 min-w-0">
                              <p className="line-clamp-2">{q.statement}</p>
                              <div className="flex items-center gap-2 mt-1">
                                <Badge variant="secondary" className="text-xs">{q.type}</Badge>
                                {q.discipline && <span className="text-xs text-muted-foreground">{q.discipline}</span>}
                              </div>
                            </div>
                            {!distributeEvenly && isSelected && (
                              <Input
                                type="number"
                                min="0"
                                step="0.5"
                                value={perQuestionScores[q.id] ?? ""}
                                onChange={(e) => { e.stopPropagation(); setPerQuestionScores((prev) => ({ ...prev, [q.id]: e.target.value })); }}
                                onClick={(e) => e.stopPropagation()}
                                className="w-20 h-7 text-xs"
                                placeholder="pts"
                              />
                            )}
                          </div>
                        </div>
                      );
                    })}
                  </div>
                </CardContent>
              </Card>

              {/* Scoring */}
              <Card>
                <CardHeader>
                  <CardTitle className="text-base">Pontuação</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                  <div className="flex items-center gap-3">
                    <Switch checked={distributeEvenly} onCheckedChange={setDistributeEvenly} />
                    <Label className="cursor-pointer">{t("exams.distributeEvenly")}</Label>
                  </div>
                  {distributeEvenly ? (
                    <div className="flex items-center gap-3">
                      <div className="space-y-1">
                        <Label>{t("exams.totalPoints")}</Label>
                        <Input type="number" min="0" step="0.5" value={totalPoints} onChange={(e) => setTotalPoints(e.target.value)} className="w-28" />
                      </div>
                      {selectedQuestionIds.length > 0 && (
                        <p className="text-sm text-muted-foreground mt-5">= {pointsPerQuestion().toFixed(2)} pts/questão</p>
                      )}
                    </div>
                  ) : (
                    <p className="text-sm text-muted-foreground">Defina a pontuação individualmente em cada questão acima.</p>
                  )}
                </CardContent>
              </Card>
            </>
          )}
        </div>
      )}

      {/* Step: Print Preparation */}
      {step === "print" && (
        <Card>
          <CardHeader>
            <CardTitle>{t("exams.printPrep.title")}</CardTitle>
            <CardDescription>Configure as opções antes de gerar o PDF</CardDescription>
          </CardHeader>
          <CardContent className="space-y-6">
            {/* Style */}
            <div className="space-y-2">
              <Label className="text-sm font-medium">{t("exams.printPrep.style")}</Label>
              <div className="grid grid-cols-2 gap-2">
                {[
                  { value: "double_column", label: t("exams.printPrep.doubleColumn"), Icon: LayoutGrid },
                  { value: "single_column", label: t("exams.printPrep.singleColumn"), Icon: LayoutList },
                  { value: "economic", label: t("exams.printPrep.economic"), Icon: TreeDeciduous },
                  { value: "accessibility", label: t("exams.printPrep.accessibility"), Icon: Accessibility },
                ].map(({ value, label, Icon }) => (
                  <button
                    key={value}
                    onClick={() => setPrintStyle(value as PrintStyle)}
                    className={`flex items-center gap-2 p-3 border-2 rounded-lg text-sm transition ${printStyle === value ? "border-primary bg-primary/5" : "border-border hover:border-primary/40"}`}
                  >
                    <Icon className="w-4 h-4 shrink-0" />{label}
                  </button>
                ))}
              </div>
            </div>

            <Separator />

            {/* Print sided + answer key position */}
            <div className="grid md:grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label className="text-sm font-medium">{t("exams.printPrep.printType")}</Label>
                <RadioGroup value={printSided} onValueChange={(v) => setPrintSided(v as PrintSided)}>
                  <div className="flex items-center gap-2"><RadioGroupItem value="double" id="p-double" /><Label htmlFor="p-double" className="cursor-pointer">{t("exams.printPrep.doubleSided")}</Label></div>
                  <div className="flex items-center gap-2"><RadioGroupItem value="single" id="p-single" /><Label htmlFor="p-single" className="cursor-pointer">{t("exams.printPrep.singleSided")}</Label></div>
                </RadioGroup>
              </div>
              <div className="space-y-2">
                <Label className="text-sm font-medium">{t("exams.printPrep.answerKeyPosition")}</Label>
                <RadioGroup value={answerKeyPos} onValueChange={(v) => setAnswerKeyPos(v as AnswerKeyPos)}>
                  <div className="flex items-center gap-2"><RadioGroupItem value="first_page" id="ak-first" /><Label htmlFor="ak-first" className="cursor-pointer">{t("exams.printPrep.firstPage")}</Label></div>
                  <div className="flex items-center gap-2"><RadioGroupItem value="last_page" id="ak-last" /><Label htmlFor="ak-last" className="cursor-pointer">{t("exams.printPrep.lastPage")}</Label></div>
                  <div className="flex items-center gap-2"><RadioGroupItem value="separate" id="ak-sep" /><Label htmlFor="ak-sep" className="cursor-pointer">{t("exams.printPrep.separateSheet")}</Label></div>
                </RadioGroup>
              </div>
            </div>

            <Separator />

            {/* Shuffle */}
            <div className="space-y-2">
              <Label className="text-sm font-medium"><Shuffle className="inline w-4 h-4 mr-1" />{t("exams.printPrep.shuffle")}</Label>
              <RadioGroup value={shuffleMode} onValueChange={(v) => setShuffleMode(v as ShuffleMode)} className="grid grid-cols-2 gap-1">
                {[
                  { value: "all", label: t("exams.printPrep.shuffleAll") },
                  { value: "questions", label: t("exams.printPrep.shuffleQuestions") },
                  { value: "items", label: t("exams.printPrep.shuffleItems") },
                  { value: "none", label: t("exams.printPrep.noShuffle") },
                ].map(({ value, label }) => (
                  <div key={value} className="flex items-center gap-2">
                    <RadioGroupItem value={value} id={`sh-${value}`} />
                    <Label htmlFor={`sh-${value}`} className="cursor-pointer text-sm">{label}</Label>
                  </div>
                ))}
              </RadioGroup>
            </div>

            <Separator />

            {/* Versions */}
            <div className="space-y-2">
              <Label className="text-sm font-medium">{t("exams.printPrep.versions")}: <span className="text-primary font-bold">{numVersions}</span></Label>
              <Slider
                min={1} max={50} step={1}
                value={[numVersions]}
                onValueChange={([v]) => setNumVersions(v)}
                className="w-full"
              />
              <p className="text-xs text-muted-foreground">Máximo: 50 versões</p>
            </div>

            <Separator />

            {/* Language override */}
            <div className="space-y-1">
              <Label className="text-sm font-medium"><Globe className="inline w-4 h-4 mr-1" />{t("exams.examLanguage")}</Label>
              <Select value={examLanguage} onValueChange={(v) => setExamLanguage(v as "pt" | "en" | "es")}>
                <SelectTrigger className="w-40"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="pt">🇧🇷 Português</SelectItem>
                  <SelectItem value="en">🇺🇸 English</SelectItem>
                  <SelectItem value="es">🇪🇸 Español</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Navigation */}
      <div className="flex justify-between">
        <Button variant="outline" onClick={goPrev} disabled={step === "choose"}>
          <ChevronLeft className="w-4 h-4 mr-1" />{t("common.previous")}
        </Button>

        {step === "print" ? (
          <Button onClick={handleSubmit} disabled={isSubmitting} className="gap-2">
            {isSubmitting ? <Loader2 className="animate-spin w-4 h-4" /> : <Printer className="w-4 h-4" />}
            {t("exams.printPrep.generate")}
          </Button>
        ) : (
          <Button onClick={goNext} disabled={!canGoNext}>
            {t("common.next")}<ChevronRight className="w-4 h-4 ml-1" />
          </Button>
        )}
      </div>

      {showQuickAdd && (
        <QuickAddQuestionDialog
          discipline={discipline || "Geral"}
          onClose={() => setShowQuickAdd(false)}
          onCreated={(id) => {
            setSelectedQuestionIds((prev) => [...prev, id]);
            refetchQuestions();
            setShowQuickAdd(false);
            toast.success("Questão criada e adicionada à prova!");
          }}
          createMutation={createQuestionMutation}
        />
      )}
    </div>
  );
}

const ALT_LETTERS = ["a", "b", "c", "d", "e"];

function QuickAddQuestionDialog({
  discipline,
  onClose,
  onCreated,
  createMutation,
}: {
  discipline: string;
  onClose: () => void;
  onCreated: (id: number) => void;
  createMutation: any;
}) {
  const [statement, setStatement] = useState("");
  const [numAlts, setNumAlts] = useState(5);
  const [alts, setAlts] = useState<Record<string, string>>({ a: "", b: "", c: "", d: "", e: "" });
  const [correct, setCorrect] = useState<string>("a");
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function handleCreate() {
    if (!statement.trim()) { toast.error("Informe o enunciado da questão"); return; }
    const letters = ALT_LETTERS.slice(0, numAlts);
    const alternatives: Record<string, string> = {};
    for (const l of letters) {
      alternatives[l] = alts[l]?.trim() || l.toUpperCase();
    }
    setIsSubmitting(true);
    try {
      const result = await createMutation.mutateAsync({
        statement: statement.trim(),
        discipline: discipline || "Geral",
        subject: "Geral",
        content: {
          type: "multiple_choice" as const,
          alternatives,
          correctAnswer: correct as "a" | "b" | "c" | "d" | "e",
        },
      });
      const id = (result as any)?.questionId;
      if (id) onCreated(Number(id));
    } catch (e: any) {
      toast.error(e?.message || "Erro ao criar questão");
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <Dialog open onOpenChange={(o) => { if (!o) onClose(); }}>
      <DialogContent className="max-w-lg max-h-[85vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <PenLine className="w-4 h-4" /> Nova Questão de Múltipla Escolha
          </DialogTitle>
        </DialogHeader>

        <div className="space-y-4 py-2">
          <div className="space-y-1">
            <Label className="text-xs">Enunciado *</Label>
            <Textarea
              value={statement}
              onChange={(e) => setStatement(e.target.value)}
              placeholder="Digite o enunciado da questão..."
              rows={3}
              className="text-sm"
            />
          </div>

          <div className="space-y-1">
            <Label className="text-xs">Nº de alternativas</Label>
            <div className="flex gap-1">
              {[3, 4, 5].map((n) => (
                <button
                  key={n}
                  onClick={() => setNumAlts(n)}
                  className={`px-3 py-1 text-xs rounded border transition ${numAlts === n ? "bg-primary text-primary-foreground border-primary" : "border-border"}`}
                >
                  {n}
                </button>
              ))}
            </div>
          </div>

          <div className="space-y-2">
            <Label className="text-xs">Alternativas — clique na letra para marcar a correta</Label>
            {ALT_LETTERS.slice(0, numAlts).map((l) => (
              <div key={l} className="flex items-center gap-2">
                <button
                  onClick={() => setCorrect(l)}
                  className={`w-7 h-7 rounded-full border-2 text-xs font-bold shrink-0 transition ${
                    correct === l ? "bg-primary border-primary text-primary-foreground" : "border-border"
                  }`}
                >
                  {l.toUpperCase()}
                </button>
                <Input
                  value={alts[l] || ""}
                  onChange={(e) => setAlts((prev) => ({ ...prev, [l]: e.target.value }))}
                  placeholder={`Alternativa ${l.toUpperCase()} (opcional)`}
                  className="h-8 text-sm"
                />
              </div>
            ))}
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={onClose} disabled={isSubmitting}>Cancelar</Button>
          <Button onClick={handleCreate} disabled={isSubmitting || !statement.trim()}>
            {isSubmitting ? <Loader2 className="w-4 h-4 animate-spin mr-1" /> : <Plus className="w-4 h-4 mr-1" />}
            Adicionar à Prova
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
