import { useAuth } from "@/_core/hooks/useAuth";
import { getLoginUrl } from "@/const";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Progress } from "@/components/ui/progress";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Slider } from "@/components/ui/slider";
import { useLanguage, useT } from "@/contexts/LanguageContext";
import { trpc } from "@/lib/trpc";
import { toast } from "sonner";
import {
  AlertCircle,
  ArrowLeft,
  ArrowRight,
  CheckCircle,
  Clock,
  Crown,
  FlaskConical,
  Loader2,
  Trophy,
  XCircle,
  Zap,
} from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { Link, useLocation } from "wouter";
import QuestionRenderer from "@/components/QuestionRenderer";
import {
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from "@/components/ui/tabs";

type ExamState = "config" | "taking" | "results";

interface ExamQuestion {
  id: number;
  textPt: string;
  textEn?: string | null;
  options: Array<{ id: string; textPt: string; textEn?: string }>;
  correctOption: string;
  explanationPt?: string | null;
  explanationEn?: string | null;
  difficulty: string;
  questionType?: string;
  modelId?: string | null;
  assertion1?: string | null;
  assertion2?: string | null;
  a1Verdadeira?: boolean | null;
  a2Verdadeira?: boolean | null;
  relacaoCausal?: boolean | null;
  formatData?: any;
  imageUrl?: string | null;
  disciplineName?: string;
  subjectTag?: string | null;
  author?: string | null;
  banca?: string | null;
  year?: number | null;
}

export default function ExamPage() {
  const t = useT();
  const { language } = useLanguage();
  const { isAuthenticated, user } = useAuth();
  const [, setLocation] = useLocation();

  const [examState, setExamState] = useState<ExamState>("config");
  const [examId, setExamId] = useState<number | null>(null);
  const [questions, setQuestions] = useState<ExamQuestion[]>([]);
  const [currentIdx, setCurrentIdx] = useState(0);
  const [answers, setAnswers] = useState<Record<number, string>>({});
  const [revealed, setRevealed] = useState<Record<number, boolean>>({});
  const [timeLeft, setTimeLeft] = useState<number | null>(null);
  const [timeSpent, setTimeSpent] = useState(0);
  const [results, setResults] = useState<any>(null);
  // Custom Config
  const [disciplineIds, setDisciplineIds] = useState<number[]>([]);
  const [subjectId, setSubjectId] = useState<string>("");
  const [author, setAuthor] = useState<string>("");
  const [year, setYear] = useState<string>("");
  const [difficulty, setDifficulty] = useState<string>("mixed");
  const [questionCount, setQuestionCount] = useState(20);
  const [timeLimitMinutes, setTimeLimitMinutes] = useState<number | null>(null);

  // Standard Config
  const [examTitle, setExamTitle] = useState<string>("");
  const [activeTab, setActiveTab] = useState<"custom" | "standard">("custom");
  const [selectedStandard, setSelectedStandard] = useState<"ENARE" | "USP" | "UNESP" | "UFMG" | "UFRJ" | "Geral">("ENARE");
  const [standardCount, setStandardCount] = useState(40);
  const [standardTime, setStandardTime] = useState<number | null>(240);

  const { data: disciplines } = trpc.questions.disciplines.useQuery();
  const { data: distinctAuthors } = trpc.questions.distinctAuthors.useQuery();
  const { data: distinctYears } = trpc.questions.distinctYears.useQuery();
  // Load subjects for all selected disciplines
  const { data: allSubjects } = trpc.questions.allSubjects.useQuery();
  const filteredSubjects = allSubjects?.filter((s) =>
    disciplineIds.length === 0 || disciplineIds.includes(s.disciplineId)
  );
  const { data: examCount } = trpc.exams.countForExam.useQuery({
    disciplineIds: disciplineIds.length > 0 ? disciplineIds : undefined,
    subjectId: subjectId && subjectId !== "all" ? parseInt(subjectId) : undefined,
    author: author && author !== "all" ? author : undefined,
    year: year && year !== "all" ? parseInt(year) : undefined,
    difficulty: difficulty as "easy" | "medium" | "hard" | "mixed",
  });
  const startExam = trpc.exams.start.useMutation();
  const startAiStandardExam = trpc.exams.startAiStandard.useMutation();
  // Simulado para um concurso do Radar: /exam?concurso=<slug>
  const concursoSlug = typeof window !== "undefined" ? new URLSearchParams(window.location.search).get("concurso") : null;
  const { data: concursoInfo } = trpc.concursos.bySlug.useQuery({ slug: concursoSlug ?? "" }, { enabled: !!concursoSlug, retry: false });
  const startConcursoExam = trpc.exams.startConcurso.useMutation();
  const submitAnswer = trpc.exams.submitAnswer.useMutation();
  const completeExam = trpc.exams.complete.useMutation();
  const utils = trpc.useUtils();

  const timerRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const spentRef = useRef<ReturnType<typeof setInterval> | null>(null);

  // Restore exam in progress from sessionStorage (survives browser back / accidental navigation)
  useEffect(() => {
    try {
      const saved = sessionStorage.getItem("vetrank_exam");
      if (saved) {
        const data = JSON.parse(saved);
        if (data.examState === "taking" && data.examId && data.questions?.length > 0) {
          setExamId(data.examId);
          setQuestions(data.questions);
          setAnswers(data.answers ?? {});
          setCurrentIdx(data.currentIdx ?? 0);
          setTimeSpent(data.timeSpent ?? 0);
          setExamTitle(data.examTitle ?? "");
          setExamState("taking");
        }
      }
    } catch {}
  }, []);

  // Persist exam state while taking
  useEffect(() => {
    if (examState === "taking" && examId && questions.length > 0) {
      sessionStorage.setItem("vetrank_exam", JSON.stringify({
        examState, examId, questions, answers, currentIdx, timeSpent, examTitle,
      }));
    } else if (examState === "config" || examState === "results") {
      sessionStorage.removeItem("vetrank_exam");
    }
  }, [examState, examId, questions, answers, currentIdx, timeSpent, examTitle]);

  useEffect(() => {
    if (examState === "taking") {
      spentRef.current = setInterval(() => setTimeSpent((s) => s + 1), 1000);
      if (timeLeft !== null) {
        timerRef.current = setInterval(() => {
          setTimeLeft((t) => {
            if (t === null || t <= 1) {
              handleComplete();
              return 0;
            }
            return t - 1;
          });
        }, 1000);
      }
    }
    return () => {
      if (timerRef.current) clearInterval(timerRef.current);
      if (spentRef.current) clearInterval(spentRef.current);
    };
  }, [examState]);

  const handleStart = async () => {
    if (!isAuthenticated) {
      window.location.href = getLoginUrl();
      return;
    }
    try {
      const result = await startExam.mutateAsync({
        disciplineIds: disciplineIds.length > 0 ? disciplineIds : undefined,
        subjectId: subjectId && subjectId !== "all" ? parseInt(subjectId) : undefined,
        author: author && author !== "all" ? author : undefined,
        year: year && year !== "all" ? parseInt(year) : undefined,
        difficulty: difficulty as "easy" | "medium" | "hard" | "mixed",
        questionCount,
        timeLimitMinutes: timeLimitMinutes ?? undefined,
      });
      sessionStorage.removeItem("vetrank_exam");
      setExamId(result.examId);
      setQuestions(result.questions as ExamQuestion[]);
      setExamTitle(result.title || "");
      setCurrentIdx(0);
      setAnswers({});
      setRevealed({});
      if (timeLimitMinutes) setTimeLeft(timeLimitMinutes * 60);
      setTimeSpent(0);
      setExamState("taking");
    } catch (err: any) {
      toast.error(err.message || t("error"));
    }
  };

  const handleStartConcurso = async () => {
    if (!concursoSlug) return;
    try {
      const result = await startConcursoExam.mutateAsync({ slug: concursoSlug, questionCount: 40, timeLimitMinutes: 120 });
      sessionStorage.removeItem("vetrank_exam");
      setExamId(result.examId);
      setQuestions(result.questions as ExamQuestion[]);
      setExamTitle(result.title || "");
      setCurrentIdx(0);
      setAnswers({});
      setRevealed({});
      setTimeLeft(result.timeLimitMinutes * 60);
      setTimeSpent(0);
      setExamState("taking");
    } catch (err: any) {
      toast.error(err.message || t("error"));
    }
  };

  const handleStartStandard = async () => {
    if (!isAuthenticated) {
      window.location.href = getLoginUrl();
      return;
    }
    try {
      const result = await startAiStandardExam.mutateAsync({
        examType: selectedStandard,
        questionCount: standardCount,
        timeLimitMinutes: standardTime ?? undefined,
      });
      sessionStorage.removeItem("vetrank_exam");
      setExamId(result.examId);
      setQuestions(result.questions as ExamQuestion[]);
      setExamTitle(result.title || "");
      setCurrentIdx(0);
      setAnswers({});
      setRevealed({});
      if (standardTime) setTimeLeft(standardTime * 60);
      setTimeSpent(0);
      setExamState("taking");
    } catch (err: any) {
      toast.error(err.message || t("error"));
    }
  };

  const handleAnswer = async (questionId: number, option: string) => {
    if (revealed[questionId]) return;
    setAnswers((prev) => ({ ...prev, [questionId]: option }));
    if (examId) {
      await submitAnswer.mutateAsync({
        examId,
        questionId,
        selectedOption: option,
      });
    }
  };

  const handleComplete = async () => {
    if (!examId) return;
    if (timerRef.current) clearInterval(timerRef.current);
    if (spentRef.current) clearInterval(spentRef.current);
    try {
      const result = await completeExam.mutateAsync({ examId, timeSpentSeconds: timeSpent });
      setResults(result);
      setExamState("results");
      utils.gamification.myProfile.invalidate();
      if (result.newBadges?.length > 0) {
        result.newBadges.forEach((b: any) => {
          toast.success(`🏅 ${language === "pt" ? "Badge desbloqueado" : "Badge unlocked"}: ${language === "pt" ? b.namePt : b.nameEn}`);
        });
      }
    } catch (err: any) {
      toast.error(err.message || t("error"));
    }
  };

  const handleManualComplete = () => {
    const unanswered = questions.filter((q) => !answers[q.id]);
    if (unanswered.length > 0) {
      // Questões puladas são permitidas (conteúdo ainda não estudado) — contam como não respondidas.
      // Pede confirmação em vez de bloquear.
      const firstIdx = questions.findIndex((q) => q.id === unanswered[0]!.id);
      const n = unanswered.length;
      toast.warning(
        language === "pt"
          ? `${n} questão${n !== 1 ? "ões" : ""} em branco (contam como não acertadas).`
          : `${n} unanswered question${n !== 1 ? "s" : ""} (count as not correct).`,
        {
          duration: 10000,
          action: {
            label: language === "pt" ? "Finalizar assim mesmo" : "Submit anyway",
            onClick: () => handleComplete(),
          },
          cancel: {
            label: language === "pt" ? "Revisar" : "Review",
            onClick: () => { if (firstIdx !== -1) setCurrentIdx(firstIdx); },
          },
        }
      );
      return;
    }
    handleComplete();
  };

  const formatTime = (seconds: number) => {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `${String(m).padStart(2, "0")}:${String(s).padStart(2, "0")}`;
  };

  const currentQuestion = questions[currentIdx];
  const answeredCount = Object.keys(answers).length;
  const progress = questions.length > 0 ? (answeredCount / questions.length) * 100 : 0;

  // ── CONFIG ──────────────────────────────────────────────────────────────────
  if (examState === "config") {
    return (
      <div className="min-h-screen bg-background">
        <div className="container py-10 max-w-2xl mx-auto">
          <div className="text-center mb-10">
            <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/15 border border-primary/30 mx-auto mb-4">
              <FlaskConical className="h-8 w-8 text-primary" />
            </div>
            <h1 className="font-serif text-3xl font-bold mb-2">{t("exam_title")}</h1>
            <p className="text-muted-foreground font-sans">
              {language === "pt" ? "Configure seu simulado personalizado" : "Configure your personalized mock exam"}
            </p>
          </div>

          {concursoSlug && concursoInfo && (
            <div className="rounded-2xl border-2 border-primary/50 bg-primary/10 p-6 mb-6">
              <p className="text-xs font-semibold uppercase tracking-wide text-primary mb-1">Simulado para concurso</p>
              <h2 className="font-serif text-2xl font-bold">{concursoInfo.orgao}{concursoInfo.uf ? ` (${concursoInfo.uf})` : ""}</h2>
              <p className="text-sm text-muted-foreground mt-1 mb-4">
                40 questões no estilo de concursos para veterinário — saúde pública, clínica, patologia e legislação — com 2 horas de prova
                {concursoInfo.banca ? `, priorizando questões da banca ${concursoInfo.banca} quando houver` : ""}.
              </p>
              <Button onClick={handleStartConcurso} disabled={startConcursoExam.isPending} className="gap-2">
                {startConcursoExam.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <FlaskConical className="h-4 w-4" />}
                Começar simulado do concurso
              </Button>
            </div>
          )}

          <div className="bg-card border border-border/50 rounded-2xl p-8">
            <Tabs value={activeTab} onValueChange={(v) => setActiveTab(v as any)} className="w-full">
              <TabsList className="grid w-full grid-cols-2 mb-6 bg-muted/50 p-1 border border-border/40 rounded-xl">
                <TabsTrigger value="custom" className="py-2.5 rounded-lg text-sm font-sans transition-all">
                  {language === "pt" ? "Simulado Personalizado" : "Custom Simulator"}
                </TabsTrigger>
                <TabsTrigger value="standard" className="py-2.5 rounded-lg text-sm font-sans transition-all">
                  {language === "pt" ? "Provas Padrão (IA)" : "Standard Exams (AI)"}
                </TabsTrigger>
              </TabsList>

              <TabsContent value="custom" className="space-y-6 focus:outline-none">
                {/* Disciplines — multi-select checkboxes */}
                <div>
                  <label className="text-sm font-medium font-sans mb-3 block">
                    {language === "pt" ? "Disciplinas" : "Disciplines"}
                    {disciplineIds.length > 0 && (
                      <span className="ml-2 text-xs text-primary font-normal">
                        ({disciplineIds.length} {language === "pt" ? "selecionada(s)" : "selected"})
                      </span>
                    )}
                  </label>
                  <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
                    {disciplines?.map((d) => {
                      const checked = disciplineIds.includes(d.id);
                      return (
                        <button
                          key={d.id}
                          type="button"
                          onClick={() => {
                            setDisciplineIds((prev) =>
                              checked ? prev.filter((id) => id !== d.id) : [...prev, d.id]
                            );
                            setSubjectId("");
                          }}
                          className={`text-left px-3 py-2 rounded-lg border text-xs font-sans transition-all ${
                            checked
                              ? "bg-primary/20 border-primary/50 text-primary font-medium"
                              : "bg-background border-border/50 text-muted-foreground hover:border-primary/30"
                          }`}
                        >
                          {language === "pt" ? d.namePt : d.nameEn}
                        </button>
                      );
                    })}
                  </div>
                  {disciplineIds.length > 0 && (
                    <button
                      type="button"
                      onClick={() => { setDisciplineIds([]); setSubjectId(""); }}
                      className="mt-2 text-xs text-muted-foreground hover:text-foreground font-sans underline"
                    >
                      {language === "pt" ? "Limpar seleção" : "Clear selection"}
                    </button>
                  )}
                </div>

                {/* Subject — filtered by selected disciplines */}
                <div>
                  <label className="text-sm font-medium font-sans mb-2 block">
                    {language === "pt" ? "Assunto" : "Subject"}
                  </label>
                  <Select
                    value={subjectId || "all"}
                    onValueChange={(v) => setSubjectId(v === "all" ? "" : v)}
                    disabled={!filteredSubjects || filteredSubjects.length === 0}
                  >
                    <SelectTrigger className="bg-background border-border/50 font-sans">
                      <SelectValue placeholder={language === "pt" ? "Todos os assuntos" : "All subjects"} />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all">{language === "pt" ? "Todos os assuntos" : "All subjects"}</SelectItem>
                      {filteredSubjects?.map((s) => (
                        <SelectItem key={s.id} value={String(s.id)}>
                          {language === "pt" ? s.namePt : s.nameEn}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                {/* Author */}
                <div>
                  <label className="text-sm font-medium font-sans mb-2 block">
                    {language === "pt" ? "Autor" : "Author"}
                  </label>
                  <Select value={author || "all"} onValueChange={(v) => setAuthor(v === "all" ? "" : v)}>
                    <SelectTrigger className="bg-background border-border/50 font-sans">
                      <SelectValue placeholder={language === "pt" ? "Todos os autores" : "All authors"} />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all">{language === "pt" ? "Todos os autores" : "All authors"}</SelectItem>
                      {distinctAuthors?.map((a) => (
                        <SelectItem key={a} value={a}>{a}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                {/* Year */}
                <div>
                  <label className="text-sm font-medium font-sans mb-2 block">
                    {language === "pt" ? "Ano" : "Year"}
                  </label>
                  <Select value={year || "all"} onValueChange={(v) => setYear(v === "all" ? "" : v)}>
                    <SelectTrigger className="bg-background border-border/50 font-sans">
                      <SelectValue placeholder={language === "pt" ? "Todos os anos" : "All years"} />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all">{language === "pt" ? "Todos os anos" : "All years"}</SelectItem>
                      {distinctYears?.map((y) => (
                        <SelectItem key={y} value={String(y)}>{y}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                {/* Difficulty */}
                <div>
                  <label className="text-sm font-medium font-sans mb-2 block">{t("exam_difficulty")}</label>
                  <Select value={difficulty} onValueChange={setDifficulty}>
                    <SelectTrigger className="bg-background border-border/50 font-sans">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="mixed">{t("exam_mixed")}</SelectItem>
                      <SelectItem value="easy">{t("exam_easy")}</SelectItem>
                      <SelectItem value="medium">{t("exam_medium")}</SelectItem>
                      <SelectItem value="hard">{t("exam_hard")}</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                {/* Question count */}
                <div>
                  <label className="text-sm font-medium font-sans mb-3 block">
                    {t("exam_count")}: <span className="text-primary font-bold">{questionCount}</span>
                  </label>
                  <Slider
                    min={5}
                    max={60}
                    step={5}
                    value={[questionCount]}
                    onValueChange={([v]) => setQuestionCount(v)}
                    className="w-full"
                  />
                  <div className="flex justify-between text-xs text-muted-foreground mt-1 font-sans">
                    <span>5</span><span>60</span>
                  </div>
                </div>

                {/* Timer */}
                <div>
                  <label className="text-sm font-medium font-sans mb-2 block">{t("exam_timer")}</label>
                  <Select
                    value={timeLimitMinutes === null ? "none" : String(timeLimitMinutes)}
                    onValueChange={(v) => setTimeLimitMinutes(v === "none" ? null : parseInt(v))}
                  >
                    <SelectTrigger className="bg-background border-border/50 font-sans">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="none">{t("exam_no_timer")}</SelectItem>
                      <SelectItem value="15">15 min</SelectItem>
                      <SelectItem value="30">30 min</SelectItem>
                      <SelectItem value="45">45 min</SelectItem>
                      <SelectItem value="60">60 min</SelectItem>
                      <SelectItem value="90">90 min</SelectItem>
                      <SelectItem value="120">120 min</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                {/* Paywall banner for free users */}
                {isAuthenticated && (user as any)?.plan === "free" && (
                  <div className="p-4 bg-yellow-500/10 border border-yellow-500/30 rounded-xl flex items-start gap-3">
                    <Crown className="h-5 w-5 text-yellow-400 shrink-0 mt-0.5" />
                    <div className="flex-1">
                      <p className="text-sm font-sans font-medium text-yellow-300 mb-1">
                        {language === "pt" ? "Simulados são exclusivos para assinantes" : "Mock exams are exclusive to subscribers"}
                      </p>
                      <p className="text-xs font-sans text-yellow-400/80 mb-2">
                        {language === "pt" ? "Faça upgrade para acessar simulados ilimitados e muito mais." : "Upgrade to access unlimited mock exams and more."}
                      </p>
                      <Link href="/pricing">
                        <Button size="sm" className="bg-yellow-500 text-black hover:bg-yellow-400 font-sans text-xs gap-1">
                          <Crown className="h-3 w-3" />
                          {language === "pt" ? "Ver planos" : "View plans"}
                        </Button>
                      </Link>
                    </div>
                  </div>
                )}

                {examCount !== undefined && (
                  <p className={`text-center text-sm font-sans ${examCount.count === 0 ? "text-destructive" : "text-muted-foreground"}`}>
                    {examCount.count === 0
                      ? (language === "pt" ? "Nenhuma questão encontrada com esses filtros." : "No questions found for the selected filters.")
                      : (language === "pt"
                        ? `${examCount.count} questão${examCount.count !== 1 ? "s" : ""} disponível${examCount.count !== 1 ? "is" : ""} com esses filtros.`
                        : `${examCount.count} question${examCount.count !== 1 ? "s" : ""} available with these filters.`)
                    }
                  </p>
                )}
                <Button
                  size="lg"
                  className="w-full bg-primary text-primary-foreground hover:bg-primary/90 glow-primary gap-2 font-sans"
                  onClick={handleStart}
                  disabled={startExam.isPending || (isAuthenticated && (user as any)?.plan === "free") || examCount?.count === 0}
                >
                  {startExam.isPending ? (
                    <Loader2 className="h-4 w-4 animate-spin" />
                  ) : (
                    <Zap className="h-4 w-4" />
                  )}
                  {t("exam_start")}
                </Button>
              </TabsContent>

              <TabsContent value="standard" className="space-y-6 focus:outline-none">
                {/* Standard exam / residencies options */}
                <div className="space-y-6">
                  <div>
                    <label className="text-sm font-medium font-sans mb-3 block">
                      {language === "pt" ? "Selecione o Exame / Residência" : "Select the Exam / Residency"}
                    </label>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                      {[
                        {
                          id: "ENARE",
                          name: "ENARE",
                          subtitle: "Exame Nacional de Residência",
                          desc: language === "pt"
                            ? "O maior exame de residência médica veterinária do Brasil. Questões selecionadas e balanceadas por IA pedagógica."
                            : "The largest veterinary residency exam in Brazil. Selected and balanced by pedagogical AI.",
                          recommendedTime: 240,
                          recommendedCount: 40,
                          color: "from-teal-500/10 to-emerald-500/10 border-teal-500/30",
                          activeColor: "ring-2 ring-emerald-500 border-emerald-500 bg-emerald-500/5",
                        },
                        {
                          id: "USP",
                          name: "USP",
                          subtitle: "Universidade de São Paulo",
                          desc: language === "pt"
                            ? "Simulado focado no padrão de excelência e estrutura de residência da USP."
                            : "Mock exam focused on the USP residency excellence standard and structure.",
                          recommendedTime: 240,
                          recommendedCount: 40,
                          color: "from-blue-500/10 to-indigo-500/10 border-blue-500/30",
                          activeColor: "ring-2 ring-blue-500 border-blue-500 bg-blue-500/5",
                        },
                        {
                          id: "UNESP",
                          name: "UNESP",
                          subtitle: "Universidade Estadual Paulista",
                          desc: language === "pt"
                            ? "Simulado focado no padrão de prova e temas mais cobrados nas residências da UNESP."
                            : "Focused on the exam standard and most asked topics at UNESP.",
                          recommendedTime: 240,
                          recommendedCount: 40,
                          color: "from-orange-500/10 to-red-500/10 border-orange-500/30",
                          activeColor: "ring-2 ring-orange-500 border-orange-500 bg-orange-500/5",
                        },
                        {
                          id: "UFMG",
                          name: "UFMG",
                          subtitle: "Univ. Federal de Minas Gerais",
                          desc: language === "pt"
                            ? "Simulado baseado nos exames de residência em veterinária da UFMG."
                            : "Mock exam based on the UFMG veterinary residency exams.",
                          recommendedTime: 240,
                          recommendedCount: 40,
                          color: "from-red-500/10 to-rose-500/10 border-red-500/30",
                          activeColor: "ring-2 ring-red-500 border-red-500 bg-red-500/5",
                        },
                        {
                          id: "UFRJ",
                          name: "UFRJ",
                          subtitle: "Univ. Federal do Rio de Janeiro",
                          desc: language === "pt"
                            ? "Focado no estilo e exigência da residência de veterinária da UFRJ."
                            : "Focused on the style and requirements of UFRJ veterinary residency.",
                          recommendedTime: 240,
                          recommendedCount: 40,
                          color: "from-purple-500/10 to-indigo-500/10 border-purple-500/30",
                          activeColor: "ring-2 ring-purple-500 border-purple-500 bg-purple-500/5",
                        },
                        {
                          id: "Geral",
                          name: "Residência Geral",
                          subtitle: "Outras Instituições",
                          desc: language === "pt"
                            ? "Simulado geral composto por questões de diversas residências veterinárias do país."
                            : "General mock exam containing questions from various vet residencies in Brazil.",
                          recommendedTime: 180,
                          recommendedCount: 40,
                          color: "from-gray-500/10 to-slate-500/10 border-gray-500/30",
                          activeColor: "ring-2 ring-slate-400 border-slate-400 bg-slate-500/5",
                        },
                      ].map((item) => {
                        const isSelected = selectedStandard === item.id;
                        return (
                          <div
                            key={item.id}
                            onClick={() => {
                              setSelectedStandard(item.id as any);
                              setStandardCount(item.recommendedCount);
                              setStandardTime(item.recommendedTime);
                            }}
                            className={`group cursor-pointer rounded-xl border p-4 transition-all duration-300 flex flex-col justify-between h-full bg-gradient-to-br ${
                              isSelected ? item.activeColor : `${item.color} hover:border-primary/50 hover:bg-muted/30`
                            }`}
                          >
                            <div>
                              <div className="flex items-center justify-between mb-1.5">
                                <span className="font-serif text-lg font-bold tracking-tight">
                                  {item.name}
                                </span>
                                <Badge variant="secondary" className="text-[10px] font-sans font-normal opacity-85 bg-primary/10 text-primary border-primary/20">
                                  IA Pedagógica
                                </Badge>
                              </div>
                              <div className="text-xs font-semibold text-primary/80 mb-2 font-sans">
                                {item.subtitle}
                              </div>
                              <p className="text-xs text-muted-foreground font-sans line-clamp-3 leading-relaxed">
                                {item.desc}
                              </p>
                            </div>
                            <div className="mt-4 pt-3 border-t border-border/30 flex justify-between items-center text-[10px] text-muted-foreground font-sans">
                              <span>Recomendado: {item.recommendedCount} Qs</span>
                              <span>Tempo: {item.recommendedTime} min</span>
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  </div>

                  {/* Standard Question Count */}
                  <div>
                    <label className="text-sm font-medium font-sans mb-3 block">
                      {t("exam_count")}: <span className="text-primary font-bold">{standardCount}</span>
                    </label>
                    <Slider
                      min={5}
                      max={100}
                      step={5}
                      value={[standardCount]}
                      onValueChange={([v]) => setStandardCount(v)}
                      className="w-full"
                    />
                    <div className="flex justify-between text-xs text-muted-foreground mt-1 font-sans">
                      <span>5</span><span>100</span>
                    </div>
                  </div>

                  {/* Standard Timer */}
                  <div>
                    <label className="text-sm font-medium font-sans mb-2 block">{t("exam_timer")}</label>
                    <Select
                      value={standardTime === null ? "none" : String(standardTime)}
                      onValueChange={(v) => setStandardTime(v === "none" ? null : parseInt(v))}
                    >
                      <SelectTrigger className="bg-background border-border/50 font-sans">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="none">{t("exam_no_timer")}</SelectItem>
                        <SelectItem value="60">60 min</SelectItem>
                        <SelectItem value="120">120 min</SelectItem>
                        <SelectItem value="180">180 min (3h)</SelectItem>
                        <SelectItem value="240">240 min (4h)</SelectItem>
                        <SelectItem value="300">300 min (5h)</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>

                  {/* Paywall Banner for Free Plan */}
                  {isAuthenticated && (user as any)?.plan === "free" && (
                    <div className="p-4 bg-yellow-500/10 border border-yellow-500/30 rounded-xl flex items-start gap-3">
                      <Crown className="h-5 w-5 text-yellow-400 shrink-0 mt-0.5" />
                      <div className="flex-1">
                        <p className="text-sm font-sans font-medium text-yellow-300 mb-1">
                          {language === "pt" ? "Simulados são exclusivos para assinantes" : "Mock exams are exclusive to subscribers"}
                        </p>
                        <p className="text-xs font-sans text-yellow-400/80 mb-2">
                          {language === "pt" ? "Faça upgrade para acessar simulados de residência ilimitados e muito mais." : "Upgrade to access unlimited residency mock exams and more."}
                        </p>
                        <Link href="/pricing">
                          <Button size="sm" className="bg-yellow-500 text-black hover:bg-yellow-400 font-sans text-xs gap-1">
                            <Crown className="h-3 w-3" />
                            {language === "pt" ? "Ver planos" : "View plans"}
                          </Button>
                        </Link>
                      </div>
                    </div>
                  )}

                  <Button
                    size="lg"
                    className="w-full bg-primary text-primary-foreground hover:bg-primary/90 glow-primary gap-2 font-sans"
                    onClick={handleStartStandard}
                    disabled={startAiStandardExam.isPending || (isAuthenticated && (user as any)?.plan === "free")}
                  >
                    {startAiStandardExam.isPending ? (
                      <Loader2 className="h-4 w-4 animate-spin" />
                    ) : (
                      <Zap className="h-4 w-4" />
                    )}
                    {language === "pt" ? `Iniciar Simulado ${selectedStandard}` : `Start Mock ${selectedStandard}`}
                  </Button>
                </div>
              </TabsContent>
            </Tabs>

            {!isAuthenticated && (
              <p className="text-center text-sm text-muted-foreground font-sans">
                {t("login_required")}{" "}
                <button onClick={() => (window.location.href = getLoginUrl())} className="text-primary hover:underline">
                  {t("login_btn")}
                </button>
              </p>
            )}
          </div>
        </div>
      </div>
    );
  }

  // ── TAKING ──────────────────────────────────────────────────────────────────
  if (examState === "taking" && currentQuestion) {
    const options = currentQuestion.options;
    const selectedAnswer = answers[currentQuestion.id];

    return (
      <div className="min-h-screen bg-background">
        {/* Top bar */}
        <div className="sticky top-0 z-10 bg-background/90 backdrop-blur-xl border-b border-border/50">
          <div className="container py-3">
            <div className="flex items-center justify-between gap-4">
              <div className="flex items-center gap-3">
                {examTitle && (
                  <span className="text-sm font-semibold font-sans hidden md:inline text-muted-foreground mr-3 border-r border-border/60 pr-3">
                    {examTitle}
                  </span>
                )}
                <span className="text-sm font-medium font-sans">
                  {t("exam_question")} <span className="text-primary font-bold">{currentIdx + 1}</span> {t("exam_of")} {questions.length}
                </span>
                <Badge variant="outline" className="font-sans text-xs">
                  {answeredCount}/{questions.length} {language === "pt" ? "respondidas" : "answered"}
                </Badge>
              </div>
              <div className="flex items-center gap-3">
                {timeLeft !== null && (
                  <div className={`flex items-center gap-1.5 text-sm font-mono font-bold ${timeLeft < 60 ? "text-red-400" : "text-primary"}`}>
                    <Clock className="h-4 w-4" />
                    {formatTime(timeLeft)}
                  </div>
                )}
                <Button
                  size="sm"
                  variant="outline"
                  onClick={handleManualComplete}
                  disabled={completeExam.isPending}
                  className="font-sans text-xs"
                >
                  {t("exam_submit")}
                </Button>
              </div>
            </div>
            <Progress value={progress} className="mt-2 h-1.5" />
          </div>
        </div>

        <div className="container py-8 max-w-3xl mx-auto">
          {/* Question + Options via universal renderer */}
          <div className="bg-card border border-border/50 rounded-2xl p-8 mb-6">
            <QuestionRenderer
              question={{
                id: currentQuestion.id,
                textPt: currentQuestion.textPt,
                textEn: currentQuestion.textEn ?? undefined,
                questionType: currentQuestion.questionType,
                modelId: currentQuestion.modelId ?? undefined,
                options: options,
                correctOption: currentQuestion.correctOption,
                explanationPt: currentQuestion.explanationPt ?? undefined,
                explanationEn: currentQuestion.explanationEn ?? undefined,
                assertion1: currentQuestion.assertion1 ?? undefined,
                assertion2: currentQuestion.assertion2 ?? undefined,
                a1Verdadeira: currentQuestion.a1Verdadeira ?? undefined,
                a2Verdadeira: currentQuestion.a2Verdadeira ?? undefined,
                relacaoCausal: currentQuestion.relacaoCausal ?? undefined,
                formatData: currentQuestion.formatData,
                imageUrl: currentQuestion.imageUrl ?? undefined,
                difficulty: currentQuestion.difficulty,
                disciplineName: currentQuestion.disciplineName,
                subjectTag: currentQuestion.subjectTag ?? undefined,
                author: currentQuestion.author ?? undefined,
                banca: currentQuestion.banca ?? undefined,
                year: currentQuestion.year ?? undefined,
              }}
              selectedOption={selectedAnswer}
              answered={false}
              onAnswer={(optId) => handleAnswer(currentQuestion.id, optId)}
              language={language as "pt" | "en"}
            />
          </div>

          {/* Navigation */}
          <div className="flex items-center justify-between">
            <Button
              variant="outline"
              onClick={() => setCurrentIdx((i) => Math.max(0, i - 1))}
              disabled={currentIdx === 0}
              className="gap-2 font-sans"
            >
              <ArrowLeft className="h-4 w-4" />
              {t("exam_prev")}
            </Button>

            {/* Question dots */}
            <div className="hidden sm:flex items-center gap-1.5 flex-wrap justify-center max-w-xs">
              {questions.map((q, i) => (
                <button
                  key={q.id}
                  onClick={() => setCurrentIdx(i)}
                  className={`h-6 w-6 rounded-full text-xs font-bold transition-colors ${
                    i === currentIdx
                      ? "bg-primary text-primary-foreground"
                      : answers[q.id]
                      ? "bg-primary/30 text-primary"
                      : "bg-accent text-muted-foreground"
                  }`}
                >
                  {i + 1}
                </button>
              ))}
            </div>

            {currentIdx < questions.length - 1 ? (
              <Button
                onClick={() => setCurrentIdx((i) => Math.min(questions.length - 1, i + 1))}
                className="gap-2 bg-primary text-primary-foreground hover:bg-primary/90 font-sans"
              >
                {answers[currentQuestion.id] ? t("exam_next") : (language === "pt" ? "Pular" : "Skip")}
                <ArrowRight className="h-4 w-4" />
              </Button>
            ) : (
              <Button
                onClick={handleManualComplete}
                disabled={completeExam.isPending}
                className="gap-2 bg-primary text-primary-foreground hover:bg-primary/90 font-sans"
              >
                {completeExam.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <CheckCircle className="h-4 w-4" />}
                {t("exam_submit")}
              </Button>
            )}
          </div>
        </div>
      </div>
    );
  }

  // ── RESULTS ─────────────────────────────────────────────────────────────────
  if (examState === "results" && results) {
    const accuracy = Math.round(results.accuracy);
    const wrong = questions.length - results.correctAnswers;

    return (
      <div className="min-h-screen bg-background">
        <div className="container py-10 max-w-3xl mx-auto">
          {/* Score card */}
          <div className="bg-card border border-border/50 rounded-2xl p-8 mb-8 text-center">
            <div className={`inline-flex h-20 w-20 items-center justify-center rounded-full mb-4 ${
              accuracy >= 70 ? "bg-green-500/15 border-2 border-green-500/40" : "bg-red-500/15 border-2 border-red-500/40"
            }`}>
              {accuracy >= 70 ? (
                <Trophy className="h-10 w-10 text-green-400" />
              ) : (
                <AlertCircle className="h-10 w-10 text-red-400" />
              )}
            </div>
            <h1 className="font-serif text-3xl font-bold mb-2">
              {examTitle || t("exam_results_title")}
            </h1>
            <p className="text-muted-foreground font-sans text-sm mb-4">
              {language === "pt" ? "Resultado do Simulado" : "Exam Results"}
            </p>
            <div className="font-serif text-6xl font-bold text-gradient mb-6">{accuracy}%</div>

            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
              {[
                { label: t("exam_correct"), value: results.correctAnswers, icon: CheckCircle, color: "text-green-400" },
                { label: t("exam_wrong"), value: wrong, icon: XCircle, color: "text-red-400" },
                { label: t("exam_xp_earned"), value: `+${results.xpEarned}`, icon: Zap, color: "text-primary" },
                { label: t("exam_time_spent"), value: formatTime(timeSpent), icon: Clock, color: "text-cyan-400" },
              ].map((stat) => (
                <div key={stat.label} className="p-4 rounded-xl bg-accent/50 border border-border/30">
                  <stat.icon className={`h-5 w-5 ${stat.color} mx-auto mb-1`} />
                  <div className={`font-serif text-xl font-bold ${stat.color}`}>{stat.value}</div>
                  <div className="text-xs text-muted-foreground font-sans mt-0.5">{stat.label}</div>
                </div>
              ))}
            </div>

            <div className="flex gap-3 justify-center mt-6">
              <Button variant="outline" onClick={() => setExamState("config")} className="gap-2 font-sans">
                <FlaskConical className="h-4 w-4" />
                {t("exam_new")}
              </Button>
              <Button
                className="bg-primary text-primary-foreground hover:bg-primary/90 gap-2 font-sans"
                onClick={() => setLocation("/profile")}
              >
                <Trophy className="h-4 w-4" />
                {language === "pt" ? "Ver Perfil" : "View Profile"}
              </Button>
            </div>
          </div>

          {/* Review */}
          <h2 className="font-serif text-xl font-bold mb-4">{t("exam_review")}</h2>
          <div className="space-y-4">
            {questions.map((q, idx) => {
              const opts = q.options;
              const selected = answers[q.id];
              const isCorrect = selected === q.correctOption;

              return (
                <div key={q.id} className={`rounded-xl border p-5 ${isCorrect ? "border-green-500/30 bg-green-500/5" : "border-red-500/30 bg-red-500/5"}`}>
                  <div className="flex items-start gap-3 mb-3">
                    {isCorrect ? (
                      <CheckCircle className="h-5 w-5 text-green-400 shrink-0 mt-0.5" />
                    ) : (
                      <XCircle className="h-5 w-5 text-red-400 shrink-0 mt-0.5" />
                    )}
                    <div className="flex-1">
                      <span className="text-xs text-muted-foreground font-sans mr-2">Q{idx + 1}</span>
                      <span className="text-sm font-sans leading-relaxed">
                        {language === "pt" ? q.textPt : (q.textEn || q.textPt)}
                      </span>
                    </div>
                  </div>

                  <div className="ml-8 space-y-1.5">
                    {opts.map((opt) => (
                      <div
                        key={opt.id}
                        className={`text-xs font-sans px-3 py-1.5 rounded-lg ${
                          opt.id === q.correctOption
                            ? "bg-green-500/20 text-green-300"
                            : opt.id === selected && !isCorrect
                            ? "bg-red-500/20 text-red-300"
                            : "text-muted-foreground"
                        }`}
                      >
                        <span className="font-bold mr-2">{opt.id})</span>
                        {language === "pt" ? opt.textPt : (opt.textEn || opt.textPt)}
                        {opt.id === q.correctOption && (
                          <span className="ml-2 text-green-400">✓</span>
                        )}
                      </div>
                    ))}
                  </div>

                </div>
              );
            })}
          </div>
        </div>
      </div>
    );
  }

  return null;
}
