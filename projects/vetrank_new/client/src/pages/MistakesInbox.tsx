import { useState } from "react";
import { Link } from "wouter";
import { trpc } from "@/lib/trpc";
import { useAuth } from "@/_core/hooks/useAuth";
import { useLanguage } from "@/contexts/LanguageContext";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { toast } from "sonner";
import {
  BookOpen, ChevronRight, CheckCircle, XCircle, Lightbulb,
  ArrowLeft, RefreshCw, Trophy, AlertTriangle, PlayCircle, Flame
} from "lucide-react";
import QuestionRenderer from "@/components/QuestionRenderer";
import { useAuthModal } from "@/contexts/AuthModalContext";

export default function MistakesInbox() {
  const { isAuthenticated, user } = useAuth();
  const isStudent = !user?.role || user.role === "user";
  const { language: lang } = useLanguage();
  const { openLogin } = useAuthModal();
  const utils = trpc.useUtils();

  // State for active practice session
  const [activeDisciplineId, setActiveDisciplineId] = useState<number | undefined>();
  const [inSession, setInSession] = useState(false);
  const [selectedOption, setSelectedOption] = useState<string | null>(null);
  const [submitted, setSubmitted] = useState(false);
  const [showExplanation, setShowExplanation] = useState(false);
  const [sessionCorrect, setSessionCorrect] = useState(0);
  const [sessionTotal, setSessionTotal] = useState(0);
  const [sessionTargetCount] = useState(5); // 5 questions per correction run
  const [seenIds, setSeenIds] = useState<number[]>([]);

  // Fetch mistakes overview
  const { data: mistakesData, isLoading: loadingOverview, refetch: refetchOverview } = 
    trpc.practice.getIncorrectQuestions.useQuery(undefined, { enabled: isAuthenticated });

  // Fetch incorrect question
  const { data: question, refetch: refetchQuestion, isLoading: loadingQuestion } = 
    trpc.practice.getIncorrectQuestion.useQuery(
      { disciplineId: activeDisciplineId, excludeIds: seenIds },
      { enabled: isAuthenticated && inSession }
    );

  const submitMutation = trpc.practice.submitAnswer.useMutation({
    onSuccess: () => {
      utils.practice.performanceDashboard.invalidate();
      refetchOverview();
    },
  });

  const handleStartSession = (disciplineId?: number) => {
    setActiveDisciplineId(disciplineId);
    setInSession(true);
    setSessionCorrect(0);
    setSessionTotal(0);
    setSeenIds([]);
    setSelectedOption(null);
    setSubmitted(false);
    setShowExplanation(false);
  };

  const handleAnswer = (option: string) => {
    if (submitted || !question) return;
    setSelectedOption(option);
    setSubmitted(true);
    const isCorrect = option === question.correctOption;
    setSessionTotal(t => t + 1);
    if (isCorrect) {
      setSessionCorrect(c => c + 1);
    }
    
    submitMutation.mutate({
      questionId: question.id,
      disciplineId: question.disciplineId ?? activeDisciplineId ?? 0,
      selectedOption: option,
      isCorrect,
    });
  };

  const handleNext = () => {
    if (question) setSeenIds(prev => [...prev, question.id]);
    setSelectedOption(null);
    setSubmitted(false);
    setShowExplanation(false);
    
    if (sessionTotal >= sessionTargetCount) {
      // Session finished
      setInSession(false);
      toast.success(`Sessão finalizada! Você resolveu ${sessionCorrect} de ${sessionTotal} erros.`);
    } else {
      refetchQuestion();
    }
  };

  const handleExitSession = () => {
    setInSession(false);
    refetchOverview();
  };

  if (!isAuthenticated) {
    return (
      <div className="min-h-screen flex items-center justify-center px-4">
        <Card className="p-8 text-center max-w-sm border-border/50">
          <BookOpen className="w-12 h-12 text-primary mx-auto mb-4" />
          <h2 className="text-xl font-bold mb-2">Faça login para praticar</h2>
          <Button className="w-full mt-2" onClick={() => openLogin()}>Entrar</Button>
        </Card>
      </div>
    );
  }

  // Active Practice Session View
  if (inSession) {
    return (
      <div className="min-h-screen bg-background pt-20 pb-12 px-4">
        <div className="max-w-3xl mx-auto">
          {/* Header */}
          <div className="flex items-center justify-between mb-6">
            <Button variant="ghost" size="sm" onClick={handleExitSession} className="gap-1 font-sans text-muted-foreground hover:text-foreground">
              <ArrowLeft className="w-4 h-4" />
              Sair da Sessão
            </Button>
            <div className="text-right">
              <span className="text-xs text-muted-foreground font-sans block">Sessão de Prática</span>
              <span className="text-sm font-semibold font-sans">{sessionTotal}/{sessionTargetCount} questões</span>
            </div>
          </div>

          {/* Progress bar */}
          <div className="w-full h-1.5 bg-muted rounded-full overflow-hidden mb-6">
            <div 
              className="h-full bg-rose-500 transition-all duration-300" 
              style={{ width: `${(sessionTotal / sessionTargetCount) * 100}%` }}
            />
          </div>

          {/* Question View */}
          {loadingQuestion ? (
            <Card className="border-border/50 bg-card/50 backdrop-blur-md">
              <CardContent className="py-20 flex items-center justify-center">
                <div className="animate-spin w-8 h-8 border-2 border-rose-500 border-t-transparent rounded-full" />
              </CardContent>
            </Card>
          ) : !question ? (
            <Card className="border-border/50 bg-card/50 backdrop-blur-md text-center py-16 px-6">
              <CardContent>
                <Trophy className="w-12 h-12 text-yellow-400 mx-auto mb-4" />
                <h3 className="text-xl font-bold mb-2">Sem erros pendentes!</h3>
                <p className="text-muted-foreground text-sm max-w-md mx-auto mb-6">
                  Parabéns! Você respondeu corretamente ou não possui erros cadastrados nesta disciplina.
                </p>
                <Button className="bg-primary text-primary-foreground" onClick={handleExitSession}>
                  Voltar ao Painel
                </Button>
              </CardContent>
            </Card>
          ) : (
            <Card className="border-border/50 shadow-lg relative overflow-hidden bg-card/60 backdrop-blur-lg">
              <div className="absolute top-0 left-0 w-full h-[3px] bg-gradient-to-r from-rose-500 to-amber-500" />
              <CardContent className="p-6">
                {/* Meta details */}
                <div className="flex justify-between items-center text-xs text-muted-foreground mb-4 font-sans">
                  <span>{question.disciplineName} &gt; {question.subjectName}</span>
                  <span className="bg-rose-500/10 text-rose-400 border border-rose-500/20 px-2 py-0.5 rounded-full font-medium">Revisando Erro</span>
                </div>

                {/* Universal question renderer */}
                <QuestionRenderer
                  question={{
                    ...question,
                    options: (question.options as any[]) ?? [],
                    formatData: (question as any).formatData,
                  }}
                  selectedOption={selectedOption}
                  answered={submitted}
                  onAnswer={handleAnswer}
                  language={lang as "pt" | "en"}
                  showExplanation={showExplanation}
                />

                {/* Result feedback */}
                {submitted && (
                  <div className={`mt-4 p-3 rounded-lg flex items-center gap-2 ${selectedOption === question.correctOption ? "bg-green-500/10 text-green-400 border border-green-500/20" : "bg-red-500/10 text-red-400 border border-red-500/20"}`}>
                    {selectedOption === question.correctOption
                      ? <><CheckCircle className="w-4.5 h-4.5 text-green-400" /><span className="text-sm font-medium font-sans">Erro Corrigido! {isStudent ? "+2 XP" : ""}</span></>
                      : <><XCircle className="w-4.5 h-4.5 text-red-400" /><span className="text-sm font-medium font-sans">Ainda Incorreto. O gabarito é <strong>{question.correctOption}</strong>.</span></>
                    }
                  </div>
                )}

                {/* Explanation toggle */}
                {submitted && (
                  <div className="space-y-2 mt-3">
                    {question.explanationPt && (
                      <button
                        onClick={() => setShowExplanation(!showExplanation)}
                        className="text-sm text-rose-400 hover:text-rose-300 hover:underline flex items-center gap-1 font-sans font-medium"
                      >
                        <Lightbulb className="w-4 h-4" />
                        {showExplanation ? "Ocultar explicação" : "Ver explicação"}
                      </button>
                    )}
                  </div>
                )}

                {/* Next button */}
                {submitted && (
                  <Button onClick={handleNext} className="w-full mt-5 bg-gradient-to-r from-rose-500 to-amber-500 hover:from-rose-600 hover:to-amber-600 text-white font-semibold font-sans">
                    {sessionTotal >= sessionTargetCount ? "Finalizar Sessão" : "Próxima Questão"} <ChevronRight className="w-4 h-4 ml-1" />
                  </Button>
                )}
              </CardContent>
            </Card>
          )}
        </div>
      </div>
    );
  }

  // Dashboard / Overview View
  return (
    <div className="min-h-screen bg-background pt-20 pb-12 px-4">
      <div className="max-w-4xl mx-auto space-y-6">
        
        {/* Banner principal */}
        <div className="relative rounded-2xl overflow-hidden bg-gradient-to-br from-rose-950 via-slate-900 to-slate-900 border border-rose-500/30 p-6 sm:p-8 shadow-xl">
          <div className="absolute right-0 top-0 w-1/3 h-full bg-gradient-to-l from-rose-500/10 to-transparent pointer-events-none" />
          <div className="max-w-xl space-y-3">
            <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-rose-500/15 border border-rose-500/30 text-xs font-bold text-rose-400 uppercase tracking-wider font-sans">
              <Flame className="w-3.5 h-3.5 fill-rose-400" />
              Recuperação Ativa
            </div>
            <h1 className="text-3xl sm:text-4xl font-extrabold tracking-tight text-foreground" style={{ fontFamily: "Playfair Display, Georgia, serif" }}>
              Caderno de Erros
            </h1>
            <p className="text-sm sm:text-base text-muted-foreground font-sans leading-relaxed">
              O segredo dos aprovados é focar nas maiores fraquezas. Aqui ficam as questões que você errou nos simulados ou modo prática. Resolva-as até acertar para removê-las do seu inbox!
            </p>
          </div>
        </div>

        {/* Stats Grid */}
        {loadingOverview ? (
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            {[...Array(3)].map((_, i) => (
              <Card key={i} className="border-border/50"><CardContent className="h-24" /></Card>
            ))}
          </div>
        ) : (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <Card className="border-border/50 bg-card/40 backdrop-blur-sm">
              <CardContent className="p-4 flex items-center gap-4">
                <div className="w-12 h-12 rounded-xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400">
                  <AlertTriangle className="w-6 h-6" />
                </div>
                <div>
                  <span className="text-xs text-muted-foreground font-sans block">Erros Pendentes</span>
                  <span className="text-2xl font-bold font-sans">
                    {mistakesData?.totalIncorrect ?? 0}
                  </span>
                </div>
              </CardContent>
            </Card>

            <Card className="border-border/50 bg-card/40 backdrop-blur-sm">
              <CardContent className="p-4 flex items-center gap-4">
                <div className="w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                  <PlayCircle className="w-6 h-6" />
                </div>
                <div>
                  <span className="text-xs text-muted-foreground font-sans block">Formatos de Prática</span>
                  <span className="text-sm font-semibold font-sans block mt-0.5">Sessões de 5 questões</span>
                </div>
              </CardContent>
            </Card>

            {mistakesData && mistakesData.totalIncorrect > 0 && (
              <div className="sm:col-span-2 lg:col-span-1 flex items-center">
                <Button 
                  onClick={() => handleStartSession()} 
                  className="w-full h-12 bg-gradient-to-r from-rose-500 to-amber-500 hover:from-rose-600 hover:to-amber-600 text-white font-bold font-sans rounded-xl shadow-md gap-2"
                >
                  <Flame className="w-4 h-4 fill-white" />
                  Praticar Todos os Erros
                </Button>
              </div>
            )}
          </div>
        )}

        {/* Section List / Disciplines */}
        <div className="space-y-3">
          <h2 className="text-lg font-bold font-sans">Revisar por Grande Área</h2>
          {loadingOverview ? (
            <div className="space-y-2">
              {[...Array(3)].map((_, i) => (
                <div key={i} className="h-14 bg-muted animate-pulse rounded-lg" />
              ))}
            </div>
          ) : !mistakesData || mistakesData.totalIncorrect === 0 ? (
            <Card className="border-border/50 bg-card/40 text-center py-16">
              <CardContent>
                <Trophy className="w-12 h-12 text-yellow-400 mx-auto mb-4" />
                <h3 className="text-xl font-bold mb-1 font-sans">Seu inbox está zerado! 🏆</h3>
                <p className="text-muted-foreground text-sm max-w-sm mx-auto mb-4">
                  Excelente! Você não possui nenhum erro pendente no momento. Continue resolvendo simulados e praticando.
                </p>
                <Link href="/practice">
                  <Button className="bg-primary text-primary-foreground font-sans font-medium">Ir para Modo Prática</Button>
                </Link>
              </CardContent>
            </Card>
          ) : (
            <div className="grid grid-cols-1 gap-3">
              {mistakesData.byDiscipline.map((d: any) => (
                <div 
                  key={d.disciplineId}
                  className="flex items-center justify-between p-4 rounded-xl bg-card/50 border border-border/50 hover:bg-card/75 transition-all group"
                >
                  <div className="flex items-center gap-3">
                    <div className="w-8 h-8 rounded-lg bg-rose-500/10 border border-rose-500/20 text-rose-400 flex items-center justify-center text-xs font-bold font-sans">
                      {d.cnt}
                    </div>
                    <div>
                      <span className="font-semibold text-sm block">{d.disciplineName}</span>
                      <span className="text-xs text-muted-foreground font-sans">Questões erradas: {d.cnt}</span>
                    </div>
                  </div>
                  <Button 
                    size="sm" 
                    variant="ghost" 
                    onClick={() => handleStartSession(d.disciplineId)}
                    className="text-rose-400 hover:text-rose-300 hover:bg-rose-500/10 gap-1 font-sans font-semibold"
                  >
                    Estudar
                    <ChevronRight className="w-4 h-4 group-hover:translate-x-0.5 transition-transform" />
                  </Button>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
