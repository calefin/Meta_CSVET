import { useState, useEffect } from "react";
import { useRoute, Link } from "wouter";
import { trpc } from "@/lib/trpc";
import { useAuth } from "@/_core/hooks/useAuth";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import { Skeleton } from "@/components/ui/skeleton";
import QuestionRenderer from "@/components/QuestionRenderer";
import { toast } from "sonner";
import {
  BookOpen, CheckCircle2, Lock, PlayCircle, Trophy,
  ArrowLeft, Clock, Star, AlertCircle, RefreshCw, ChevronRight,
  Zap, Shield, TrendingUp, Award, Sparkles, Target,
  ShoppingBag, ExternalLink, Loader2, FileText, X, Crown,
} from "lucide-react";
import { motion, AnimatePresence } from "framer-motion";

// ─── XP / Level helpers ───────────────────────────────────────────────────────
const LEVELS = [
  { level: 1, name: "Resident",       icon: "🩺", xpRequired: 0,     color: "#6b7280" },
  { level: 2, name: "Estagiário",     icon: "📋", xpRequired: 100,   color: "#10b981" },
  { level: 3, name: "Clínico Geral",  icon: "🏥", xpRequired: 300,   color: "#3b82f6" },
  { level: 4, name: "Especialista",   icon: "🔬", xpRequired: 700,   color: "#8b5cf6" },
  { level: 5, name: "Consultor",      icon: "💡", xpRequired: 1500,  color: "#f59e0b" },
  { level: 6, name: "Pesquisador",    icon: "🧪", xpRequired: 3000,  color: "#ef4444" },
  { level: 7, name: "Professor",      icon: "📚", xpRequired: 5500,  color: "#ec4899" },
  { level: 8, name: "Mestre",         icon: "🎓", xpRequired: 9000,  color: "#f97316" },
  { level: 9, name: "Lenda",          icon: "👑", xpRequired: 15000, color: "#eab308" },
];

function getLevelInfo(xp: number) {
  let current = LEVELS[0]!;
  for (const lvl of LEVELS) {
    if (xp >= lvl.xpRequired) current = lvl;
  }
  const nextIdx = LEVELS.findIndex(l => l.level === current.level + 1);
  const next = nextIdx >= 0 ? LEVELS[nextIdx] : null;
  const progressToNext = next
    ? Math.round(((xp - current.xpRequired) / (next.xpRequired - current.xpRequired)) * 100)
    : 100;
  return { current, next, progressToNext };
}

function ProgressRing({ percent, size = 48, stroke = 4, color = "#10b981" }: {
  percent: number; size?: number; stroke?: number; color?: string;
}) {
  const r = (size - stroke * 2) / 2;
  const circ = 2 * Math.PI * r;
  const offset = circ - (percent / 100) * circ;
  return (
    <svg width={size} height={size} className="rotate-[-90deg]">
      <circle cx={size / 2} cy={size / 2} r={r} fill="none" stroke="currentColor"
        strokeWidth={stroke} className="text-muted/30" />
      <circle cx={size / 2} cy={size / 2} r={r} fill="none" stroke={color}
        strokeWidth={stroke} strokeDasharray={circ} strokeDashoffset={offset}
        strokeLinecap="round" style={{ transition: "stroke-dashoffset 0.6s ease" }} />
    </svg>
  );
}

// ─── XP Gain Popup ────────────────────────────────────────────────────────────
function XpGainPopup({ xp, show }: { xp: number; show: boolean }) {
  if (!show) return null;
  return (
    <div className="fixed top-20 right-6 z-50 animate-bounce">
      <div className="bg-amber-500 text-white font-bold px-4 py-2 rounded-full shadow-lg flex items-center gap-2">
        <Zap className="h-4 w-4" />
        +{xp} XP
      </div>
    </div>
  );
}

// ─── Level Up Celebration ─────────────────────────────────────────────────────
function LevelUpBanner({ level, name, icon, onClose }: {
  level: number; name: string; icon: string; onClose: () => void;
}) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
      <div className="bg-card border rounded-2xl p-8 text-center max-w-sm mx-4 shadow-2xl animate-in zoom-in-95 duration-300">
        <div className="text-6xl mb-4">{icon}</div>
        <div className="text-amber-400 font-bold text-sm mb-1 tracking-widest uppercase">Nível Alcançado!</div>
        <h2 className="text-3xl font-extrabold mb-2">Nível {level}</h2>
        <p className="text-xl text-muted-foreground mb-6">{name}</p>
        <div className="flex gap-1 justify-center mb-6">
          {[...Array(5)].map((_, i) => <Sparkles key={i} className="h-5 w-5 text-amber-400" />)}
        </div>
        <Button onClick={onClose} className="w-full">Continuar</Button>
      </div>
    </div>
  );
}

type ModuleQuestion = {
  id: number; textPt: string; textEn?: string | null;
  options: unknown; correctOption: string; explanationPt?: string | null;
  questionType?: string | null; formatData?: unknown;
  assertion1?: string | null; assertion2?: string | null;
  imageUrl?: string | null; difficulty?: string | null;
};

type ModuleStatus = "locked" | "available" | "passed" | "failed" | "in_progress";

const DIFFICULTY_LABELS: Record<string, string> = {
  easy: "Fácil", medium: "Médio", hard: "Difícil", mixed: "Misto",
};

export default function TrailDetail() {
  const [, params] = useRoute("/trails/:id");
  const trailId = params?.id ? parseInt(params.id) : 0;
  const { user } = useAuth();

  const [activeModuleId, setActiveModuleId] = useState<number | null>(null);
  const [quizAnswers, setQuizAnswers] = useState<Record<number, string>>({});
  const [quizSubmitted, setQuizSubmitted] = useState(false);
  const [quizResult, setQuizResult] = useState<{
    score: number; correct: number; total: number; passed: boolean;
    minPassRate: number; attemptNumber: number; xpEarned?: number;
  } | null>(null);
  const [showXpPopup, setShowXpPopup] = useState(false);
  const [levelUpInfo, setLevelUpInfo] = useState<{ level: number; name: string; icon: string } | null>(null);
  const [ebookDismissed, setEbookDismissed] = useState<Record<number, boolean>>({});
  const [checkoutPending, setCheckoutPending] = useState(false);
  const [selectedModuleForModal, setSelectedModuleForModal] = useState<any | null>(null);

  const utils = trpc.useUtils();
  const { data: trail, isLoading } = trpc.trails.getById.useQuery({ id: trailId }, { enabled: trailId > 0 });
  const { data: enrollment, refetch: refetchEnrollment } = trpc.trails.getEnrollment.useQuery(
    { trailId }, { enabled: trailId > 0 && !!user }
  );
  const { data: myPurchases } = trpc.trails.myPurchases.useQuery(undefined, { enabled: !!user });
  const hasPurchase = myPurchases?.some((p: { trailId: number; status: string }) => p.trailId === trailId && p.status === "active") ?? false;

  const createPreference = trpc.trails.createPreference.useMutation({
    onSuccess: (data) => { window.location.href = (data as { checkoutUrl: string }).checkoutUrl; },
    onError: (e) => { toast.error(e.message); setCheckoutPending(false); },
  });
  const { data: moduleQuestions, isLoading: loadingQuestions } = trpc.trails.getModuleQuestions.useQuery(
    { moduleId: activeModuleId!, trailId },
    { enabled: activeModuleId !== null && !!enrollment }
  );
  const { data: profile, refetch: refetchProfile } = trpc.gamification.myProfile.useQuery(
    undefined, { enabled: !!user }
  );

  const xp = (profile as { xp?: number } | undefined)?.xp ?? 0;
  const level = (profile as { level?: number } | undefined)?.level ?? 1;
  const { current: lvlInfo, next: nextLvl, progressToNext } = getLevelInfo(xp);

  const enrollMutation = trpc.trails.enroll.useMutation({
    onSuccess: () => { refetchEnrollment(); toast.success("Matriculado com sucesso!"); },
    onError: (e) => toast.error(e.message),
  });

  const submitMutation = trpc.trails.submitModuleAnswers.useMutation({
    onSuccess: (result) => {
      const r = result as typeof result & { xpEarned?: number; newLevel?: number; leveledUp?: boolean; isComplete?: boolean };
      setQuizResult({ ...r, xpEarned: r.xpEarned });
      setQuizSubmitted(true);
      refetchEnrollment();
      refetchProfile();
      utils.trails.getEnrollment.invalidate({ trailId });

      if (!r.isComplete) {
        toast.success(`Progresso salvo! Você acertou ${r.correct} de ${r.total} questões neste ciclo.`);
        if (r.xpEarned && r.xpEarned > 0) {
          setShowXpPopup(true);
          setTimeout(() => setShowXpPopup(false), 2500);
        }
      } else if (r.passed) {
        toast.success(`Módulo concluído! ${r.score}% de acerto`);
        if (r.xpEarned && r.xpEarned > 0) {
          setShowXpPopup(true);
          setTimeout(() => setShowXpPopup(false), 2500);
        }
        if (r.leveledUp && r.newLevel) {
          const lvl = LEVELS.find(l => l.level === r.newLevel);
          if (lvl) setTimeout(() => setLevelUpInfo({ level: lvl.level, name: lvl.name, icon: lvl.icon }), 800);
        }
      } else {
        toast.error(`Módulo falhou. ${r.score}% de acerto (mínimo: ${r.minPassRate}%)`);
      }
    },
    onError: (e) => toast.error(e.message),
  });

  const getModuleProgress = (moduleId: number) => {
    return enrollment?.moduleProgress?.find((p: { moduleId: number }) => p.moduleId === moduleId);
  };

  const handleStartModule = (moduleId: number) => {
    setActiveModuleId(moduleId);
    setQuizAnswers({});
    setQuizSubmitted(false);
    setQuizResult(null);
  };

  const handleRetryModule = () => {
    setQuizAnswers({});
    setQuizSubmitted(false);
    setQuizResult(null);
    utils.trails.getModuleQuestions.invalidate({ moduleId: activeModuleId!, trailId });
  };

  const handleSubmitQuiz = () => {
    if (!activeModuleId || !moduleQuestions) return;
    // Questões puladas vão como resposta em branco (contam como erro) — o módulo exige o total
    // de respostas para fechar a tentativa, então não dá para simplesmente omiti-las.
    const answers = moduleQuestions.map((q: any) => ({
      questionId: q.id as number, selectedOption: quizAnswers[q.id] ?? "",
    }));
    const skipped = answers.filter(a => !a.selectedOption).length;
    const moduleId = activeModuleId;
    if (skipped > 0) {
      toast.warning(`${skipped} questão${skipped !== 1 ? "ões" : ""} em branco — contam como erro na nota do módulo.`, {
        duration: 10000,
        action: { label: "Enviar assim mesmo", onClick: () => submitMutation.mutate({ trailId, moduleId, answers }) },
        cancel: { label: "Voltar", onClick: () => {} },
      });
      return;
    }
    submitMutation.mutate({ trailId, moduleId, answers });
  };

  if (isLoading) {
    return (
      <div className="container py-8 max-w-4xl">
        <Skeleton className="h-8 w-48 mb-4" />
        <Skeleton className="h-40 mb-6 rounded-2xl" />
        <div className="space-y-3">
          {[1, 2, 3].map(i => <Skeleton key={i} className="h-24" />)}
        </div>
      </div>
    );
  }

  if (!trail) return (
    <div className="container py-8 max-w-4xl text-center">
      <p className="text-muted-foreground">Trilha não encontrada.</p>
      <Link href="/trails"><Button variant="outline" className="mt-4 gap-2"><ArrowLeft className="h-4 w-4" />Voltar</Button></Link>
    </div>
  );

  const isPurchaseTrail = (trail as any)?.accessType === "purchase";
  const needsToPurchase = isPurchaseTrail && !!user && !hasPurchase;

  // ─── Purchase Gate ────────────────────────────────────────────────────────
  if (needsToPurchase) {
    return (
      <div className="container py-8 max-w-2xl">
        <Link href="/trails">
          <Button variant="ghost" size="sm" className="gap-1 mb-6">
            <ArrowLeft className="h-4 w-4" />Trilhas
          </Button>
        </Link>
        <Card className="border-amber-500/40 overflow-hidden">
          <div className="h-1 bg-gradient-to-r from-amber-500 to-yellow-400" />
          <CardContent className="p-8 text-center">
            <ShoppingBag className="h-14 w-14 text-amber-400 mx-auto mb-4" />
            <h2 className="text-2xl font-bold mb-2">{(trail as any).title}</h2>
            {(trail as any).description && (
              <p className="text-muted-foreground mb-4 text-sm">{(trail as any).description}</p>
            )}
            <div className="flex justify-center gap-3 mb-6 flex-wrap">
              <Badge variant="outline" className="gap-1">
                <BookOpen className="h-3 w-3" />{(trail as any).modules?.length ?? 0} módulos
              </Badge>
              <Badge variant="outline" className="gap-1">
                <Clock className="h-3 w-3" />{(trail as any).totalHours}h estimadas
              </Badge>
              <Badge variant="outline" className="gap-1">
                <Shield className="h-3 w-3" />{(trail as any).passingScore}% para passar
              </Badge>
            </div>
            <p className="text-3xl font-extrabold text-amber-400 mb-1">
              R$ {Number((trail as any).price ?? 0).toFixed(2)}
            </p>
            <p className="text-xs text-muted-foreground mb-6">compra única — acesso permanente</p>
            <Button
              size="lg"
              className="gap-2 bg-amber-500 text-black hover:bg-amber-400 w-full sm:w-auto"
              disabled={checkoutPending}
              onClick={() => {
                setCheckoutPending(true);
                const base = window.location.origin;
                createPreference.mutate({
                  trailId,
                  successUrl: `${base}/trails/${trailId}?payment=success`,
                  failureUrl: `${base}/trails/${trailId}?payment=failure`,
                  pendingUrl: `${base}/trails/${trailId}?payment=pending`,
                });
              }}
            >
              {checkoutPending
                ? <><Loader2 className="h-5 w-5 animate-spin" />Aguarde...</>
                : <><ShoppingBag className="h-5 w-5" />Comprar agora</>}
            </Button>
          </CardContent>
        </Card>
      </div>
    );
  }

  // ─── Active Quiz View ─────────────────────────────────────────────────────
  if (activeModuleId !== null) {
    const mod = (trail.modules as Array<{ id: number; title: string; questionCount: number; minPassRate: number; ebookUrl?: string | null; ebookTitle?: string | null }>)
      .find(m => m.id === activeModuleId);
    const answered = Object.keys(quizAnswers).length;
    const total = moduleQuestions?.length ?? 0;
    const answerPct = total > 0 ? Math.round((answered / total) * 100) : 0;

    return (
      <div className="min-h-screen bg-background">
        <XpGainPopup xp={quizResult?.xpEarned ?? 0} show={showXpPopup} />
        {levelUpInfo && (
          <LevelUpBanner {...levelUpInfo} onClose={() => setLevelUpInfo(null)} />
        )}

        <div className="container py-6 max-w-3xl">
          {/* Header */}
          <div className="flex items-center gap-3 mb-4">
            <Button variant="ghost" size="sm" onClick={() => setActiveModuleId(null)} className="gap-1">
              <ArrowLeft className="h-4 w-4" />Voltar
            </Button>
            <div className="flex-1 min-w-0">
              <p className="text-xs text-muted-foreground">{trail.title}</p>
              <h2 className="font-semibold text-sm">{mod?.title}</h2>
            </div>
            {/* XP indicator */}
            <div className="flex items-center gap-1.5 text-xs bg-amber-500/10 text-amber-400 border border-amber-500/20 rounded-full px-3 py-1">
              <Zap className="h-3 w-3" />
              <span>{xp.toLocaleString("pt-BR")} XP</span>
            </div>
          </div>

          {/* Progress bar */}
          {!quizSubmitted && total > 0 && (
            <div className="mb-6 p-4 rounded-xl bg-muted/20 border">
              <div className="flex justify-between items-center text-sm mb-2">
                <span className="text-muted-foreground">
                  <span className="font-semibold text-foreground">{answered}</span> / {total} respondidas
                </span>
                <span className="text-muted-foreground">
                  Mínimo: <span className="font-semibold text-foreground">{mod?.minPassRate}%</span>
                </span>
              </div>
              <Progress value={answerPct} className="h-2" />
              <div className="flex justify-between text-xs text-muted-foreground mt-1">
                <span>+{answered * 3} XP acumulado</span>
                <span>{total - answered} restantes</span>
              </div>
            </div>
          )}

          {/* Ebook card — shown before questions until dismissed */}
          {mod?.ebookUrl && !ebookDismissed[activeModuleId] && !quizSubmitted && (
            <Card className="mb-6 border-amber-500/30 bg-amber-500/5">
              <CardContent className="p-5">
                <div className="flex items-start gap-3">
                  <FileText className="h-8 w-8 text-amber-400 shrink-0 mt-0.5" />
                  <div className="flex-1 min-w-0">
                    <p className="font-semibold text-sm mb-0.5">
                      {mod.ebookTitle ?? "Material de apoio"}
                    </p>
                    <p className="text-xs text-muted-foreground mb-3">
                      Leia o ebook antes de responder as questões deste módulo.
                    </p>
                    <div className="flex gap-2 flex-wrap">
                      <a href={mod.ebookUrl} target="_blank" rel="noopener noreferrer">
                        <Button size="sm" className="gap-1.5 bg-amber-500 text-black hover:bg-amber-400">
                          <ExternalLink className="h-3.5 w-3.5" />Abrir PDF
                        </Button>
                      </a>
                      <Button size="sm" variant="ghost" className="gap-1.5 text-muted-foreground"
                        onClick={() => setEbookDismissed(p => ({ ...p, [activeModuleId]: true }))}>
                        Já li, continuar
                      </Button>
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>
          )}

          {/* Result Screen */}
          {quizSubmitted && quizResult && (
            <Card className={`mb-6 border-2 ${quizResult.passed ? "border-emerald-500/40 bg-emerald-500/5" : "border-red-500/40 bg-red-500/5"}`}>
              <CardContent className="p-6 text-center">
                {quizResult.passed ? (
                  <div className="relative inline-block mb-3">
                    <CheckCircle2 className="h-14 w-14 text-emerald-400 mx-auto" />
                    <div className="absolute -top-1 -right-1 w-5 h-5 bg-amber-400 rounded-full flex items-center justify-center">
                      <Sparkles className="h-3 w-3 text-white" />
                    </div>
                  </div>
                ) : (
                  <AlertCircle className="h-14 w-14 text-red-400 mx-auto mb-3" />
                )}
                <h3 className="text-xl font-bold mb-1">
                  {quizResult.passed ? "Módulo Concluído!" : "Não passou desta vez"}
                </h3>
                <p className="text-muted-foreground mb-2">
                  {quizResult.correct} de {quizResult.total} corretas — {quizResult.score}%
                  {!quizResult.passed && ` (mínimo: ${quizResult.minPassRate}%)`}
                </p>
                {quizResult.passed && quizResult.xpEarned && quizResult.xpEarned > 0 && (
                  <div className="inline-flex items-center gap-2 bg-amber-500/10 text-amber-400 border border-amber-500/20 rounded-full px-4 py-1.5 text-sm font-semibold mb-4">
                    <Zap className="h-4 w-4" />
                    +{quizResult.xpEarned} XP ganhos
                  </div>
                )}
                <div className="flex gap-3 justify-center mt-2">
                  {!quizResult.passed && (
                    <Button onClick={handleRetryModule} variant="outline" className="gap-2">
                      <RefreshCw className="h-4 w-4" />Tentar novamente
                    </Button>
                  )}
                  <Button onClick={() => setActiveModuleId(null)} className="gap-2">
                    {quizResult.passed ? <ChevronRight className="h-4 w-4" /> : <ArrowLeft className="h-4 w-4" />}
                    {quizResult.passed ? "Próximo módulo" : "Voltar à trilha"}
                  </Button>
                </div>
              </CardContent>
            </Card>
          )}

          {/* Questions */}
          {loadingQuestions ? (
            <div className="space-y-4">
              {[1, 2, 3].map(i => <Skeleton key={i} className="h-40" />)}
            </div>
          ) : moduleQuestions && moduleQuestions.length > 0 ? (
            <div className="space-y-6">
              {(moduleQuestions as ModuleQuestion[]).map((q, idx) => (
                <Card key={q.id} className={`${quizSubmitted ? "opacity-80" : ""}`}>
                  <CardContent className="p-4">
                    <div className="flex items-center justify-between mb-2">
                      <span className="text-xs text-muted-foreground">Questão {idx + 1} de {total}</span>
                      {quizAnswers[q.id] && !quizSubmitted && (
                        <span className="text-xs text-emerald-400 flex items-center gap-1">
                          <CheckCircle2 className="h-3 w-3" />Respondida
                        </span>
                      )}
                    </div>
                    <QuestionRenderer
                      question={q as Parameters<typeof QuestionRenderer>[0]["question"]}
                      selectedOption={quizAnswers[q.id] ?? null}
                      answered={quizSubmitted}
                      showExplanation={quizSubmitted}
                      revealAnswer={quizSubmitted}
                      onAnswer={quizSubmitted ? undefined : (opt: string) =>
                        setQuizAnswers(prev => ({ ...prev, [q.id]: opt }))
                      }
                    />
                  </CardContent>
                </Card>
              ))}
              {!quizSubmitted && (
                <Button
                  onClick={handleSubmitQuiz}
                  disabled={submitMutation.isPending}
                  className="w-full gap-2"
                  size="lg"
                >
                  <Target className="h-4 w-4" />
                  {submitMutation.isPending ? "Enviando..." : `Enviar ${total} Respostas`}
                </Button>
              )}
            </div>
          ) : (
            <Card className="p-8 text-center">
              <p className="text-muted-foreground">Nenhuma questão disponível para este módulo.</p>
            </Card>
          )}
        </div>
      </div>
    );
  }

  // ─── Trail Overview ───────────────────────────────────────────────────────
  const modules = trail.modules as Array<{
    id: number; title: string; summary?: string | null;
    questionCount: number; minPassRate: number; difficulty: string; order: number;
    ebookUrl?: string | null; ebookTitle?: string | null;
  }>;
  const totalModules = modules.length;
  const passedModules = enrollment?.moduleProgress?.filter((p: { status: string }) => p.status === "passed").length ?? 0;
  const progressPercent = totalModules > 0 ? Math.round((passedModules / totalModules) * 100) : 0;
  const estimatedXp = totalModules * 30 * 3;

  return (
    <div className="min-h-screen bg-background">
      <XpGainPopup xp={0} show={false} />
      {levelUpInfo && <LevelUpBanner {...levelUpInfo} onClose={() => setLevelUpInfo(null)} />}

      <div className="container py-8 max-w-4xl">
        {/* Back */}
        <Link href="/trails">
          <Button variant="ghost" size="sm" className="gap-1 mb-4">
            <ArrowLeft className="h-4 w-4" />Trilhas
          </Button>
        </Link>

        {/* ── Trail Hero ─────────────────────────────────────────────────── */}
        <Card className="mb-6 overflow-hidden">
          <div className="h-2 bg-gradient-to-r from-primary via-primary/70 to-primary/30" style={{ width: `${progressPercent}%`, minWidth: progressPercent > 0 ? "8px" : "0" }} />
          <CardContent className="p-6">
            <div className="flex items-start justify-between gap-4 mb-4">
              <div className="flex-1 min-w-0">
                <h1 className="text-2xl font-bold mb-1">{trail.title}</h1>
                {trail.description && <p className="text-muted-foreground text-sm">{trail.description}</p>}
              </div>
              {enrollment?.status === "completed" && (
                <div className="shrink-0 text-center">
                  <Trophy className="h-10 w-10 text-amber-400 mx-auto" />
                  <p className="text-xs text-amber-400 font-semibold mt-1">Concluída!</p>
                </div>
              )}
            </div>

            {/* Meta row */}
            <div className="flex flex-wrap gap-2 mb-5">
              <Badge variant="outline" className="gap-1">
                <BookOpen className="h-3 w-3" />{totalModules} módulos
              </Badge>
              <Badge variant="outline" className="gap-1">
                <Clock className="h-3 w-3" />{trail.totalHours}h estimadas
              </Badge>
              <Badge variant="outline" className="gap-1">
                <Shield className="h-3 w-3" />{trail.passingScore}% para passar
              </Badge>
              <Badge className="gap-1 bg-amber-500/10 text-amber-400 border-amber-500/20 border">
                <Zap className="h-3 w-3" />~{estimatedXp.toLocaleString("pt-BR")} XP
              </Badge>
            </div>

            {/* Progress section */}
            {enrollment ? (
              <div className="space-y-3">
                {/* Segmented module bar */}
                <div className="flex gap-1 h-3">
                  {modules.map((_, i) => {
                    const prog = getModuleProgress(modules[i]!.id);
                    const st = prog?.status ?? "locked";
                    return (
                      <div
                        key={i}
                        className={`flex-1 rounded-full transition-all duration-500 ${
                          st === "passed" ? "bg-emerald-500" :
                          st === "available" ? "bg-primary/60" :
                          st === "failed" ? "bg-red-500/60" :
                          "bg-muted/30"
                        }`}
                        title={modules[i]?.title}
                      />
                    );
                  })}
                </div>
                <div className="flex justify-between items-center text-sm">
                  <span className="text-muted-foreground">
                    <span className="font-semibold text-foreground">{passedModules}</span> / {totalModules} módulos concluídos
                  </span>
                  <span className={`font-bold text-lg ${progressPercent === 100 ? "text-emerald-400" : "text-primary"}`}>
                    {progressPercent}%
                  </span>
                </div>

                {/* XP bar for this trail */}
                {user && (
                  <div className="mt-2 p-3 rounded-lg bg-muted/20 border">
                    <div className="flex items-center justify-between text-xs mb-1.5">
                      <span className="flex items-center gap-1 text-muted-foreground">
                        <Zap className="h-3 w-3 text-amber-400" />
                        Seu XP total
                      </span>
                      <span className="font-semibold" style={{ color: lvlInfo.color }}>
                        {lvlInfo.icon} {lvlInfo.name} — Nv.{level}
                      </span>
                    </div>
                    <div className="relative h-2 rounded-full bg-muted/30 overflow-hidden">
                      <div
                        className="absolute inset-y-0 left-0 rounded-full transition-all duration-700"
                        style={{ width: `${progressToNext}%`, background: lvlInfo.color }}
                      />
                    </div>
                    {nextLvl && (
                      <p className="text-xs text-muted-foreground mt-1">
                        {(nextLvl.xpRequired - xp).toLocaleString("pt-BR")} XP para {nextLvl.icon} {nextLvl.name}
                      </p>
                    )}
                  </div>
                )}
              </div>
            ) : (
              <Button
                onClick={() => enrollMutation.mutate({ trailId })}
                disabled={enrollMutation.isPending || !user}
                className="gap-2 w-full sm:w-auto"
                size="lg"
              >
                <PlayCircle className="h-5 w-5" />
                {!user ? "Faça login para se matricular" : enrollMutation.isPending ? "Matriculando..." : "Iniciar Trilha"}
              </Button>
            )}
          </CardContent>
        </Card>

        {/* ── Module Timeline ────────────────────────────────────────────── */}
        {/* ── Module Winding Path (Duolingo-Style) ─────────────────────────── */}
        <h2 className="text-lg font-semibold mb-6 flex items-center gap-2">
          <TrendingUp className="h-5 w-5 text-primary" />
          Módulos da Trilha — Mapa de Aprendizado
        </h2>

        <div className="relative flex flex-col items-center py-10 overflow-x-hidden w-full max-w-lg mx-auto bg-card/20 rounded-3xl border border-border/40 px-4">
          {/* Sinuous SVG Connecting Line behind nodes */}
          <div className="absolute inset-0 pointer-events-none flex justify-center z-0">
            <svg width="100%" height="100%" className="max-w-[200px]" style={{ minHeight: `${modules.length * 110 + 20}px` }}>
              <path
                d={modules.map((_, idx) => {
                  const xOffsets = [100, 148, 180, 148, 100, 52, 20, 52];
                  const x = xOffsets[idx % xOffsets.length];
                  const y = idx * 110 + 50;
                  return `${idx === 0 ? "M" : "L"} ${x} ${y}`;
                }).join(" ")}
                fill="none"
                stroke="currentColor"
                strokeWidth="4"
                strokeDasharray="8,8"
                className="text-muted-foreground/30"
              />
            </svg>
          </div>

          {/* Sinuous Nodes list */}
          <div className="w-full flex flex-col items-center gap-2 z-10">
            {modules.map((mod, idx) => {
              const progress = getModuleProgress(mod.id);
              const status: ModuleStatus = progress?.status ?? "locked";
              const isAvailable = status === "available" || status === "failed" || status === "in_progress";
              const isPassed = status === "passed";
              const isLocked = status === "locked" || !enrollment;
              
              // Alternating horizontal offset (sine wave)
              const xOffsets = [0, 48, 80, 48, 0, -48, -80, -48];
              const offset = xOffsets[idx % xOffsets.length];

              // Find first uncompleted available level to display "CONTINUAR" pill
              const activeIndex = modules.findIndex(m => {
                const s = getModuleProgress(m.id)?.status;
                return s === "available" || s === "in_progress" || s === "failed";
              });
              const showActivePill = idx === (activeIndex === -1 && !isLocked && !isPassed ? 0 : activeIndex);

              return (
                <div 
                  key={mod.id} 
                  className="relative flex flex-col items-center my-1.5 transition-transform"
                  style={{ transform: `translateX(${offset}px)` }}
                >
                  {/* Bouncing tooltip pill for active node */}
                  {showActivePill && enrollment && (
                    <div className="absolute -top-9 bg-primary text-primary-foreground text-[9px] font-extrabold px-2.5 py-1 rounded-full animate-bounce shadow-md tracking-wider flex items-center gap-1 z-20 pointer-events-none">
                      <Sparkles className="h-2.5 w-2.5" />
                      {status === "in_progress" ? "CONTINUAR" : "COMEÇAR"}
                    </div>
                  )}

                  {/* Circular Node Wrapper */}
                  <div className="relative flex items-center justify-center w-24 h-24">
                    {/* Outer Progress Ring wrapper */}
                    <div className="absolute inset-0 flex items-center justify-center">
                      <ProgressRing 
                        percent={progress?.bestScore ?? 0} 
                        size={84} 
                        stroke={5} 
                        color={isPassed ? "#10b981" : status === "in_progress" ? "#3b82f6" : "#f59e0b"} 
                      />
                    </div>

                    {/* Circular Level Button */}
                    <button
                      onClick={() => setSelectedModuleForModal({ ...mod, idx })}
                      className={`relative z-10 w-14 h-14 rounded-full flex items-center justify-center border-4 border-background shadow-lg transition-all duration-200 active:scale-95 ${
                        isPassed
                          ? "bg-gradient-to-br from-emerald-400 to-emerald-600 text-white cursor-pointer hover:scale-105"
                          : isAvailable
                          ? "bg-primary text-primary-foreground cursor-pointer hover:scale-105 glow-primary"
                          : isLocked
                          ? "bg-muted text-muted-foreground/50 border-muted-foreground/10 cursor-not-allowed"
                          : "bg-red-500/20 text-red-400 cursor-pointer hover:scale-105"
                      }`}
                      title={mod.title}
                    >
                      {isPassed ? (
                        <Crown className="h-6 w-6 text-yellow-300 drop-shadow" />
                      ) : isLocked ? (
                        <Lock className="h-5 w-5" />
                      ) : status === "failed" ? (
                        <RefreshCw className="h-5 w-5" />
                      ) : (
                        <span className="font-extrabold text-base">{idx + 1}</span>
                      )}
                    </button>
                  </div>

                  {/* Node Label Below */}
                  <span className="text-[10px] font-bold text-muted-foreground/80 max-w-[110px] text-center mt-1 select-none leading-tight truncate">
                    {mod.title.split(":")[0]}
                  </span>
                </div>
              );
            })}
          </div>
        </div>

        {/* ── Final Exam CTA ─────────────────────────────────────────────── */}
        {enrollment && passedModules === totalModules && totalModules > 0 && enrollment.status !== "completed" && (
          <Card className="mt-6 border-amber-500/40 bg-amber-500/5 overflow-hidden">
            <div className="h-1 bg-gradient-to-r from-amber-500 to-yellow-400" />
            <CardContent className="p-6 text-center">
              <Trophy className="h-12 w-12 text-amber-400 mx-auto mb-3" />
              <h3 className="text-xl font-bold mb-1">Todos os módulos concluídos!</h3>
              <p className="text-muted-foreground mb-2">
                Você está pronto para o exame final. Precisa de {trail.passingScore}% para obter o certificado.
              </p>
              <Badge className="mb-4 bg-amber-500/10 text-amber-400 border-amber-500/20 border gap-1">
                <Zap className="h-3 w-3" />Bônus: +{(totalModules * 30 * 3 * 2).toLocaleString("pt-BR")} XP no exame final
              </Badge>
              <br />
              <Link href={`/trails/${trailId}/exam`}>
                <Button className="gap-2 mt-2" size="lg">
                  <Award className="h-5 w-5" />Fazer Exame Final
                </Button>
              </Link>
            </CardContent>
          </Card>
        )}

        {enrollment?.status === "completed" && (
          <Card className="mt-6 border-emerald-500/40 bg-emerald-500/5">
            <CardContent className="p-6 text-center">
              <Trophy className="h-12 w-12 text-amber-400 mx-auto mb-3" />
              <h3 className="text-xl font-bold mb-1">Trilha Concluída!</h3>
              <p className="text-muted-foreground">
                Parabéns! Você concluiu esta trilha com {enrollment.finalExamScore}% no exame final.
              </p>
            </CardContent>
          </Card>
        )}

        {/* ── Premium Level Popover Modal (Duolingo-style) ────────────────── */}
        <AnimatePresence>
          {selectedModuleForModal && (
            <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center px-4">
              {/* Click outside to close */}
              <div 
                className="absolute inset-0 cursor-default" 
                onClick={() => setSelectedModuleForModal(null)} 
              />
              
              <motion.div
                initial={{ opacity: 0, scale: 0.9, y: 50 }}
                animate={{ opacity: 1, scale: 1, y: 0 }}
                exit={{ opacity: 0, scale: 0.9, y: 50 }}
                transition={{ type: "spring", damping: 25, stiffness: 350 }}
                className="relative bg-card border border-border/80 rounded-3xl p-6 w-full max-w-sm shadow-2xl z-10"
              >
                {/* Close Button */}
                <Button
                  size="sm"
                  variant="ghost"
                  className="absolute top-4 right-4 h-8 w-8 p-0 rounded-full text-muted-foreground hover:text-foreground"
                  onClick={() => setSelectedModuleForModal(null)}
                >
                  <X className="h-4 w-4" />
                </Button>

                {/* Content */}
                <div className="text-center mt-2">
                  <div className="flex justify-center mb-3">
                    <div className="w-16 h-16 rounded-full bg-primary/10 flex items-center justify-center text-primary border border-primary/20">
                      {getModuleProgress(selectedModuleForModal.id)?.status === "passed" ? (
                        <Crown className="h-8 w-8 text-amber-400 animate-bounce" />
                      ) : selectedModuleForModal.ebookUrl ? (
                        <FileText className="h-8 w-8 animate-pulse" />
                      ) : (
                        <Target className="h-8 w-8 animate-pulse" />
                      )}
                    </div>
                  </div>

                  <p className="text-[10px] font-bold text-primary tracking-widest uppercase mb-1">
                    MÓDULO {selectedModuleForModal.idx + 1}
                  </p>
                  <h3 className="font-serif text-lg font-bold mb-2 leading-snug text-foreground">
                    {selectedModuleForModal.title}
                  </h3>
                  
                  {selectedModuleForModal.summary && (
                    <p className="text-xs text-muted-foreground font-sans leading-relaxed mb-4">
                      {selectedModuleForModal.summary}
                    </p>
                  )}

                  {/* Level stats */}
                  <div className="grid grid-cols-3 gap-2 py-3 border-y border-border/30 mb-4 text-left">
                    <div className="flex flex-col">
                      <span className="text-[9px] font-bold text-muted-foreground/60 tracking-wider">QUESTÕES</span>
                      <span className="text-xs font-semibold text-foreground flex items-center gap-1 mt-0.5">
                        <BookOpen className="h-3 w-3 text-primary" /> {selectedModuleForModal.questionCount}
                      </span>
                    </div>
                    <div className="flex flex-col">
                      <span className="text-[9px] font-bold text-muted-foreground/60 tracking-wider">MÍNIMO</span>
                      <span className="text-xs font-semibold text-foreground flex items-center gap-1 mt-0.5">
                        <Shield className="h-3 w-3 text-primary" /> {selectedModuleForModal.minPassRate}%
                      </span>
                    </div>
                    <div className="flex flex-col">
                      <span className="text-[9px] font-bold text-muted-foreground/60 tracking-wider">RECOMPENSA</span>
                      <span className="text-xs font-semibold text-amber-400 flex items-center gap-1 mt-0.5">
                        <Zap className="h-3 w-3" /> +{selectedModuleForModal.questionCount * 3} XP
                      </span>
                    </div>
                  </div>

                  {/* Ebook Integration */}
                  {selectedModuleForModal.ebookUrl && (
                    <div className="mb-5 p-3 rounded-2xl bg-amber-500/5 border border-amber-500/20 text-left flex items-start gap-2.5">
                      <FileText className="h-5 w-5 text-amber-400 shrink-0 mt-0.5 animate-pulse" />
                      <div className="flex-1 min-w-0">
                        <p className="text-xs font-bold text-amber-400 mb-0.5 leading-snug">Ebook Didático Incluso!</p>
                        <p className="text-[10px] text-muted-foreground leading-normal mb-1.5">Consulte o material de apoio técnico antes de iniciar.</p>
                        <a 
                          href={selectedModuleForModal.ebookUrl} 
                          target="_blank" 
                          rel="noopener noreferrer"
                          className="inline-flex items-center gap-1 text-[10px] font-bold text-amber-400 hover:underline"
                        >
                          <ExternalLink className="h-3 w-3" /> {selectedModuleForModal.ebookTitle ?? "Abrir PDF Técnico"}
                        </a>
                      </div>
                    </div>
                  )}

                  {/* Action buttons */}
                  {(() => {
                    const progress = getModuleProgress(selectedModuleForModal.id);
                    const status = progress?.status ?? "locked";
                    const isLocked = status === "locked" || !enrollment;

                    if (isLocked) {
                      return (
                        <div className="space-y-2">
                          <Button disabled className="w-full gap-2 text-xs">
                            <Lock className="h-3.5 w-3.5" />
                            Glândula Bloqueada
                          </Button>
                          <p className="text-[9px] text-muted-foreground">Conclua os módulos anteriores para desbloquear este nível!</p>
                        </div>
                      );
                    }

                    return (
                      <div className="space-y-2">
                        <Button 
                          onClick={() => {
                            handleStartModule(selectedModuleForModal.id);
                            setSelectedModuleForModal(null);
                          }} 
                          className="w-full gap-2 text-xs font-extrabold shadow-lg shadow-primary/10"
                        >
                          <PlayCircle className="h-4 w-4" />
                          {status === "passed" ? "REFAZER NÍVEL" : status === "in_progress" ? "CONTINUAR" : "INICIAR NÍVEL"}
                        </Button>
                        {progress && progress.bestScore > 0 && (
                          <p className="text-[10px] text-emerald-400 font-semibold">
                            ✓ Melhor pontuação: {progress.bestScore}% em {progress.attempts} tentativas
                          </p>
                        )}
                      </div>
                    );
                  })()}
                </div>
              </motion.div>
            </div>
          )}
        </AnimatePresence>
      </div>
    </div>
  );
}
