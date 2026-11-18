import { useState } from "react";
import { trpc } from "@/lib/trpc";
import { useAuth } from "@/_core/hooks/useAuth";
import { useLanguage } from "@/contexts/LanguageContext";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Label } from "@/components/ui/label";
import { toast } from "sonner";
import { BookOpen, ChevronRight, CheckCircle, XCircle, Lightbulb, Flag, SkipForward } from "lucide-react";
import QuestionRenderer from "@/components/QuestionRenderer";
import { Textarea } from "@/components/ui/textarea";
import { useAuthModal } from "@/contexts/AuthModalContext";

export default function PracticeMode() {
  const { isAuthenticated, user } = useAuth();
  const isStudent = !user?.role || user.role === "user";
  const { language: lang } = useLanguage();
  const { openLogin } = useAuthModal();
  const utils = trpc.useUtils();

  const [disciplineId, setDisciplineId] = useState<number | undefined>();
  const [subjectId, setSubjectId] = useState<number | undefined>();
  const [difficulty, setDifficulty] = useState<"easy" | "medium" | "hard" | "any">("any");
  const [excludeAnswered, setExcludeAnswered] = useState(false);
  const [selectedOption, setSelectedOption] = useState<string | null>(null);
  const [submitted, setSubmitted] = useState(false);
  const [showExplanation, setShowExplanation] = useState(false);
  const [sessionCorrect, setSessionCorrect] = useState(0);
  const [sessionTotal, setSessionTotal] = useState(0);
  const [reportOpen, setReportOpen] = useState(false);
  const [seenIds, setSeenIds] = useState<number[]>([]);

  const { data: disciplines } = trpc.questions.disciplines.useQuery();
  const { data: subjects } = trpc.questions.subjects.useQuery(
    { disciplineId: disciplineId! },
    { enabled: !!disciplineId }
  );

  const { data: question, refetch, isLoading } = trpc.practice.getQuestion.useQuery(
    { disciplineId, subjectId, difficulty, excludeAnswered, seenIds },
    { enabled: isAuthenticated }
  );

  const submitMutation = trpc.practice.submitAnswer.useMutation({
    onSuccess: () => {
      utils.practice.performanceDashboard.invalidate();
    },
  });

  const handleAnswer = (option: string) => {
    if (submitted) return;
    setSelectedOption(option);
    setSubmitted(true);
    const isCorrect = option === question?.correctOption;
    setSessionTotal(t => t + 1);
    if (isCorrect) setSessionCorrect(c => c + 1);
    if (question) {
      submitMutation.mutate({
        questionId: question.id,
        disciplineId: question.disciplineId ?? disciplineId ?? 0,
        subjectId: question.subjectId ?? subjectId,
        difficulty: question.difficulty,
        selectedOption: option,
        isCorrect,
      });
    }
  };

  const handleNext = () => {
    if (question) setSeenIds(prev => [...prev, question.id]);
    setSelectedOption(null);
    setSubmitted(false);
    setShowExplanation(false);
    refetch();
  };

  if (!isAuthenticated) {
    return (
      <div className="min-h-screen flex items-center justify-center px-4">
        <Card className="p-8 text-center max-w-sm">
          <BookOpen className="w-12 h-12 text-primary mx-auto mb-4" />
          <h2 className="text-xl font-bold mb-2">Faça login para praticar</h2>
          <Button className="w-full mt-2" onClick={() => openLogin()}>Entrar</Button>
        </Card>
      </div>
    );
  }

  const accuracy = sessionTotal > 0 ? Math.round((sessionCorrect / sessionTotal) * 100) : 0;

  return (
    <div className="min-h-screen bg-background pt-20 pb-12 px-4">
      <div className="max-w-3xl mx-auto">
        {/* Header */}
        <div className="flex items-center justify-between mb-6">
          <div>
            <h1 className="text-2xl font-bold" style={{ fontFamily: "Playfair Display, serif" }}>
              Modo Prática
            </h1>
            <p className="text-sm text-muted-foreground">Responda questões no seu ritmo, sem timer.</p>
          </div>
          {sessionTotal > 0 && (
            <div className="text-right">
              <p className="text-sm font-medium">{sessionCorrect}/{sessionTotal} corretas</p>
              <p className={`text-lg font-bold ${accuracy >= 70 ? "text-green-400" : accuracy >= 50 ? "text-yellow-400" : "text-red-400"}`}>
                {accuracy}%
              </p>
            </div>
          )}
        </div>

        {/* Filters */}
        <Card className="mb-6 border-border/50">
          <CardContent className="p-4">
            <div className="flex flex-col gap-3">
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                  <Label className="text-xs mb-1.5 block text-muted-foreground">Grande Área</Label>
                  <Select onValueChange={(v) => { setDisciplineId(v === "all" ? undefined : Number(v)); setSubjectId(undefined); }}>
                    <SelectTrigger className="h-9 text-sm"><SelectValue placeholder="Todas as áreas" /></SelectTrigger>
                    <SelectContent className="min-w-[220px]">
                      <SelectItem value="all">Todas as áreas</SelectItem>
                      {(disciplines as any[])?.map((d: any) => (
                        <SelectItem key={d.id} value={String(d.id)} className="whitespace-normal">{d.namePt}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div>
                  <Label className="text-xs mb-1.5 block text-muted-foreground">Disciplina</Label>
                  <Select onValueChange={(v) => setSubjectId(v === "all" ? undefined : Number(v))} disabled={!disciplineId}>
                    <SelectTrigger className="h-9 text-sm"><SelectValue placeholder={disciplineId ? "Todas" : "Selecione área"} /></SelectTrigger>
                    <SelectContent className="min-w-[220px]">
                      <SelectItem value="all">Todas</SelectItem>
                      {(subjects as any[])?.map((s: any) => (
                        <SelectItem key={s.id} value={String(s.id)} className="whitespace-normal">{s.namePt}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div>
                  <Label className="text-xs mb-1.5 block text-muted-foreground">Dificuldade</Label>
                  <Select value={difficulty} onValueChange={(v) => setDifficulty(v as any)}>
                    <SelectTrigger className="h-9 text-sm"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="any">Qualquer</SelectItem>
                      <SelectItem value="easy">Fácil</SelectItem>
                      <SelectItem value="medium">Médio</SelectItem>
                      <SelectItem value="hard">Difícil</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>
              <div className="flex items-center gap-2">
                <Switch id="exclude" checked={excludeAnswered} onCheckedChange={setExcludeAnswered} />
                <Label htmlFor="exclude" className="text-sm text-muted-foreground cursor-pointer">Ocultar questões já respondidas</Label>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Question Card */}
        {isLoading ? (
          <Card className="border-border/50">
            <CardContent className="py-16 flex items-center justify-center">
              <div className="animate-spin w-8 h-8 border-2 border-primary border-t-transparent rounded-full" />
            </CardContent>
          </Card>
        ) : !question ? (
          <Card className="border-border/50">
            <CardContent className="py-16 text-center">
              <BookOpen className="w-12 h-12 text-muted-foreground mx-auto mb-3" />
              <p className="text-muted-foreground mb-4">Nenhuma questão encontrada com esses filtros.</p>
              <Button variant="outline" onClick={() => { setDisciplineId(undefined); setSubjectId(undefined); setDifficulty("any"); }}>
                Limpar filtros
              </Button>
            </CardContent>
          </Card>
        ) : (
          <Card className="border-border/50">
            <CardContent className="p-6">
              {/* Report button */}
              <div className="flex justify-end mb-2">
                <button
                  onClick={() => setReportOpen(true)}
                  className="text-muted-foreground hover:text-destructive transition-colors"
                  title="Reportar erro"
                >
                  <Flag className="w-4 h-4" />
                </button>
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
                <div className={`mt-4 p-3 rounded-lg flex items-center gap-2 ${selectedOption === question.correctOption ? "bg-green-500/20 text-green-300" : "bg-red-500/20 text-red-300"}`}>
                  {selectedOption === question.correctOption
                    ? <><CheckCircle className="w-4 h-4" /><span className="text-sm font-medium">Correto!{isStudent ? " +2 XP" : ""}</span></>
                    : <><XCircle className="w-4 h-4" /><span className="text-sm font-medium">Incorreto. A resposta correta é <strong>{question.correctOption}</strong>.</span></>
                  }
                </div>
              )}

              {/* Explanation toggle + AI */}
              {submitted && (
                <div className="space-y-2 mt-3">
                  {question.explanationPt && (
                    <button
                      onClick={() => setShowExplanation(!showExplanation)}
                      className="text-sm text-primary hover:underline flex items-center gap-1"
                    >
                      <Lightbulb className="w-4 h-4" />
                      {showExplanation ? "Ocultar explicação" : "Ver explicação"}
                    </button>
                  )}
                </div>
              )}

              {/* Next button */}
              {submitted ? (
                <Button onClick={handleNext} className="w-full mt-4">
                  Próxima Questão <ChevronRight className="w-4 h-4 ml-1" />
                </Button>
              ) : (
                // Pular: não registra resposta nem conta como erro; a questão não volta nesta sessão.
                <Button variant="ghost" onClick={handleNext} className="w-full mt-4 text-muted-foreground gap-1">
                  <SkipForward className="w-4 h-4" /> Pular questão (ainda não estudei)
                </Button>
              )}
            </CardContent>
          </Card>
        )}

        {/* Report Modal */}
        {question && reportOpen && (
          <ReportModal
            questionId={question.id}
            onClose={() => setReportOpen(false)}
          />
        )}
      </div>
    </div>
  );
}

function ReportModal({ questionId, onClose }: { questionId: number; onClose: () => void }) {
  const [reason, setReason] = useState("");
  const [description, setDescription] = useState("");
  const reportMutation = trpc.reports.submit.useMutation({
    onSuccess: () => { toast.success("Erro reportado! Obrigado pelo feedback."); onClose(); },
    onError: (e) => toast.error(e.message),
  });

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4" onClick={onClose}>
      <Card className="w-full max-w-md" onClick={(e: React.MouseEvent) => e.stopPropagation()}>
        <CardContent className="p-6">
          <h3 className="font-bold text-lg mb-4 flex items-center gap-2">
            <Flag className="w-4 h-4 text-destructive" />
            Reportar Erro na Questão
          </h3>
          <div className="space-y-3">
            <div>
              <Label className="text-xs mb-1 block">Tipo de erro *</Label>
              <Select onValueChange={setReason}>
                <SelectTrigger><SelectValue placeholder="Selecione..." /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="wrong_answer">Gabarito incorreto</SelectItem>
                  <SelectItem value="typo">Erro de digitação</SelectItem>
                  <SelectItem value="outdated">Questão desatualizada</SelectItem>
                  <SelectItem value="unclear">Enunciado confuso</SelectItem>
                  <SelectItem value="image_issue">Problema com imagem</SelectItem>
                  <SelectItem value="other">Outro</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div>
              <Label className="text-xs mb-1 block">Descrição (opcional)</Label>
              <Textarea
                placeholder="Descreva o problema..."
                value={description}
                onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setDescription(e.target.value)}
                rows={3}
              />
            </div>
            <div className="flex gap-2">
              <Button variant="outline" className="flex-1" onClick={onClose}>Cancelar</Button>
              <Button
                className="flex-1"
                disabled={!reason || reportMutation.isPending}
                onClick={() => reportMutation.mutate({ questionId, category: reason as any, description: description || undefined })}
              >
                Enviar
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
