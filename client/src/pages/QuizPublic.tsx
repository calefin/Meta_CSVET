import { useState, useEffect, useRef, useCallback } from "react";
import { trpc } from "@/lib/trpc";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Progress } from "@/components/ui/progress";
import { Loader2, CheckCircle, AlertCircle, FileText, Trophy, BookOpen, Timer, User } from "lucide-react";
import { Model } from "survey-core";
import { Survey } from "survey-react-ui";
import "survey-core/survey-core.fontless.min.css";
import { questionsToSurveyModel, surveyDataToAnswers } from "@/lib/questionsToSurveyModel";

type Phase = "loading" | "enter_info" | "validating" | "answering" | "submitting" | "submitted" | "error";

interface SubmitResult {
  resultMode: string;
  score?: number;
  maxScore?: number;
  answerKey?: Record<number, string>;
}

export default function QuizPublic({ token }: { token: string }) {
  const [phase, setPhase] = useState<Phase>("enter_info");
  const [studentName, setStudentName] = useState("");
  const [studentEmail, setStudentEmail] = useState("");
  const [studentIdInput, setStudentIdInput] = useState("");
  const [enrolledStudentId, setEnrolledStudentId] = useState<number | undefined>();
  const [validationError, setValidationError] = useState("");
  const [submitResult, setSubmitResult] = useState<SubmitResult | null>(null);
  const [secondsLeft, setSecondsLeft] = useState<number | null>(null);
  const surveyModelRef = useRef<Model | null>(null);

  const { data, isLoading, error } = trpc.sharing.getPublicExam.useQuery(
    { token: token ?? "" },
    { enabled: !!token, retry: false }
  );

  const validateMutation = trpc.sharing.validateStudent.useMutation({
    onSuccess: (result) => {
      setStudentName(result.studentName);
      setEnrolledStudentId(result.studentInternalId);
      setValidationError("");
      setPhase("answering");
    },
    onError: (err) => {
      setValidationError(err.message || "Matrícula não encontrada nessa turma.");
      setPhase("enter_info");
    },
  });

  const submitMutation = trpc.sharing.submitAnswers.useMutation({
    onSuccess: (result) => {
      setSubmitResult(result as SubmitResult);
      setPhase("submitted");
    },
    onError: () => setPhase("error"),
  });

  // Build SurveyJS model when entering answering phase
  useEffect(() => {
    if (phase !== "answering" || !data) return;

    const model = new Model(questionsToSurveyModel(data.exam.name, data.questions ?? []));

    // Apply Prof-Aid green theme adjustments

    model.onComplete.add((sender) => {
      setPhase("submitting");
      const answers = surveyDataToAnswers(sender.data as Record<string, unknown>);
      submitMutation.mutate({
        token: token ?? "",
        studentName,
        studentEmail: studentEmail || undefined,
        enrolledStudentId,
        answers,
      });
    });

    surveyModelRef.current = model;
    // Force re-render
    setPhase("answering");
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [phase === "answering" && !surveyModelRef.current]);

  // Timer countdown
  useEffect(() => {
    if (phase !== "answering" || !data?.timeLimitMinutes) return;
    setSecondsLeft(data.timeLimitMinutes * 60);
  }, [phase, data?.timeLimitMinutes]);

  const handleTimerExpired = useCallback(() => {
    surveyModelRef.current?.doComplete();
  }, []);

  useEffect(() => {
    if (secondsLeft === null) return;
    if (secondsLeft <= 0) { handleTimerExpired(); return; }
    const id = setTimeout(() => setSecondsLeft((s) => (s !== null ? s - 1 : null)), 1000);
    return () => clearTimeout(id);
  }, [secondsLeft, handleTimerExpired]);

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <Loader2 className="animate-spin w-8 h-8 text-primary" />
      </div>
    );
  }

  if (error || !data) {
    return (
      <div className="min-h-screen flex items-center justify-center p-4">
        <Card className="max-w-md w-full">
          <CardContent className="flex flex-col items-center py-10 gap-4">
            <AlertCircle className="w-12 h-12 text-destructive" />
            <p className="text-center font-medium">Link não encontrado ou expirado.</p>
            <p className="text-sm text-muted-foreground text-center">
              Verifique com seu professor se o link ainda está ativo.
            </p>
          </CardContent>
        </Card>
      </div>
    );
  }

  const exam = data.exam;

  // ── Submitted ────────────────────────────────────────────────────────────
  if (phase === "submitted" && submitResult) {
    const showScore = submitResult.resultMode === "score" || submitResult.resultMode === "all";
    const showKey = submitResult.resultMode === "answer_key" || submitResult.resultMode === "all";
    const pct = showScore && submitResult.maxScore
      ? Math.round((submitResult.score! / submitResult.maxScore) * 100)
      : null;

    return (
      <div className="min-h-screen bg-muted/30 py-8 px-4">
        <div className="max-w-2xl mx-auto space-y-4">
          <Card>
            <CardContent className="flex flex-col items-center py-8 gap-3">
              <CheckCircle className="w-12 h-12 text-green-500" />
              <p className="text-lg font-semibold">Respostas enviadas!</p>
              <p className="text-sm text-muted-foreground text-center">
                Obrigado, <strong>{studentName}</strong>. Seu professor receberá suas respostas.
              </p>
            </CardContent>
          </Card>

          {showScore && pct !== null && (
            <Card className="border-green-200">
              <CardContent className="py-5 space-y-3">
                <div className="flex items-center gap-2 mb-1">
                  <Trophy className="w-5 h-5 text-yellow-500" />
                  <span className="font-semibold">Sua pontuação</span>
                </div>
                <div className="flex items-baseline gap-2">
                  <span className="text-3xl font-bold">{submitResult.score}</span>
                  <span className="text-muted-foreground">/ {submitResult.maxScore}</span>
                  <Badge
                    className={`ml-2 ${pct >= 70 ? "bg-green-100 text-green-700" : pct >= 50 ? "bg-yellow-100 text-yellow-700" : "bg-red-100 text-red-700"}`}
                    variant="outline"
                  >
                    {pct}%
                  </Badge>
                </div>
                <Progress value={pct} className="h-2" />
              </CardContent>
            </Card>
          )}

          {showKey && submitResult.answerKey && (data.questions ?? []).length > 0 && (
            <Card>
              <CardHeader className="pb-2">
                <CardTitle className="text-base flex items-center gap-2">
                  <BookOpen className="w-4 h-4 text-blue-500" />
                  Gabarito
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-2">
                {(data.questions ?? []).map((q: any, idx: number) => {
                  const correct = submitResult.answerKey![q.id];
                  return (
                    <div key={q.id} className="flex items-start gap-3 py-2 border-b last:border-0">
                      <span className="text-sm text-muted-foreground shrink-0 w-6">{idx + 1}.</span>
                      <p className="flex-1 text-sm line-clamp-2">{q.statement}</p>
                      {correct && (
                        <Badge variant="outline" className="text-xs text-blue-600 border-blue-300 shrink-0">
                          {correct}
                        </Badge>
                      )}
                    </div>
                  );
                })}
              </CardContent>
            </Card>
          )}
        </div>
      </div>
    );
  }

  if (phase === "error") {
    return (
      <div className="min-h-screen flex items-center justify-center p-4">
        <Card className="max-w-md w-full">
          <CardContent className="flex flex-col items-center py-10 gap-4">
            <AlertCircle className="w-12 h-12 text-destructive" />
            <p className="text-center font-medium">Erro ao enviar respostas.</p>
            <Button onClick={() => setPhase("answering")}>Tentar novamente</Button>
          </CardContent>
        </Card>
      </div>
    );
  }

  // ── Enter info ────────────────────────────────────────────────────────────
  if (phase === "enter_info" || phase === "validating") {
    const isStudentIdFlow = data.requireStudentId;

    const handleStart = () => {
      if (isStudentIdFlow) {
        if (!studentIdInput.trim()) return;
        setValidationError("");
        setPhase("validating");
        validateMutation.mutate({ token: token ?? "", studentId: studentIdInput.trim() });
      } else {
        if (!studentName.trim()) return;
        setPhase("answering");
      }
    };

    return (
      <div className="min-h-screen bg-muted/30 flex items-center justify-center p-4">
        <Card className="max-w-md w-full">
          <CardHeader>
            <div className="flex items-center gap-2 mb-1">
              <FileText className="w-5 h-5 text-primary" />
              <CardTitle className="text-lg">{exam.name}</CardTitle>
            </div>
            {exam.discipline && <p className="text-sm text-muted-foreground">{exam.discipline}</p>}
          </CardHeader>
          <CardContent className="space-y-4">
            {isStudentIdFlow ? (
              <>
                <div className="flex items-center gap-2 p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-sm text-emerald-800">
                  <User className="w-4 h-4 shrink-0" />
                  Esta avaliação é restrita a alunos matriculados. Informe sua matrícula para acessar.
                </div>
                <div className="space-y-1">
                  <Label>Número de Matrícula *</Label>
                  <Input
                    value={studentIdInput}
                    onChange={(e) => { setStudentIdInput(e.target.value); setValidationError(""); }}
                    placeholder="Ex: 2024001"
                    autoFocus
                    onKeyDown={(e) => e.key === "Enter" && handleStart()}
                  />
                  {validationError && (
                    <p className="text-sm text-destructive">{validationError}</p>
                  )}
                </div>
              </>
            ) : (
              <>
                <p className="text-sm text-muted-foreground">Informe seus dados para começar.</p>
                <div className="space-y-1">
                  <Label>Nome Completo *</Label>
                  <Input
                    value={studentName}
                    onChange={(e) => setStudentName(e.target.value)}
                    placeholder="Seu nome completo"
                    autoFocus
                    onKeyDown={(e) => e.key === "Enter" && handleStart()}
                  />
                </div>
                <div className="space-y-1">
                  <Label>E-mail <span className="text-muted-foreground text-xs">(opcional)</span></Label>
                  <Input
                    type="email"
                    value={studentEmail}
                    onChange={(e) => setStudentEmail(e.target.value)}
                    placeholder="seu@email.com"
                  />
                </div>
              </>
            )}

            <Button
              className="w-full"
              disabled={
                phase === "validating" ||
                (isStudentIdFlow ? !studentIdInput.trim() : !studentName.trim())
              }
              onClick={handleStart}
            >
              {phase === "validating" ? (
                <><Loader2 className="animate-spin w-4 h-4 mr-2" />Verificando...</>
              ) : (
                "Iniciar Prova"
              )}
            </Button>
          </CardContent>
        </Card>
      </div>
    );
  }

  // ── Answering (SurveyJS) ──────────────────────────────────────────────────
  if (phase === "answering" || phase === "submitting") {
    const formatTime = (secs: number) => {
      const m = Math.floor(secs / 60);
      const s = secs % 60;
      return `${m}:${s.toString().padStart(2, "0")}`;
    };
    const isWarning = secondsLeft !== null && secondsLeft <= 60;

    // Build model once
    if (!surveyModelRef.current && data) {
      const model = new Model(questionsToSurveyModel(data.exam.name, data.questions ?? []));
      model.applyTheme({
        cssVariables: {
          "--sjs-primary-backcolor": "#059669",
          "--sjs-primary-backcolor-dark": "#047857",
          "--sjs-primary-forecolor": "#ffffff",
        },
      });
      model.onComplete.add((sender) => {
        setPhase("submitting");
        submitMutation.mutate({
          token: token ?? "",
          studentName,
          studentEmail: studentEmail || undefined,
          enrolledStudentId,
          answers: surveyDataToAnswers(sender.data as Record<string, unknown>),
        });
      });
      surveyModelRef.current = model;
    }

    return (
      <div className="min-h-screen bg-muted/30 py-6 px-4">
        <div className="max-w-2xl mx-auto space-y-4">
          {/* Exam header */}
          <Card>
            <CardContent className="py-3">
              <div className="flex items-center justify-between flex-wrap gap-2">
                <div>
                  <h1 className="text-lg font-bold">{exam.name}</h1>
                  {exam.discipline && (
                    <p className="text-sm text-muted-foreground">{exam.discipline}</p>
                  )}
                </div>
                <div className="flex items-center gap-3">
                  <span className="text-sm text-muted-foreground">{studentName}</span>
                  {secondsLeft !== null && (
                    <div className={`flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-mono font-semibold ${isWarning ? "bg-red-100 text-red-700 animate-pulse" : "bg-amber-100 text-amber-700"}`}>
                      <Timer className="w-3.5 h-3.5" />
                      {formatTime(secondsLeft)}
                    </div>
                  )}
                </div>
              </div>
              {exam.instructions && (
                <div className="mt-2 p-3 bg-muted rounded-lg text-sm whitespace-pre-line">
                  {exam.instructions}
                </div>
              )}
            </CardContent>
          </Card>

          {/* SurveyJS survey */}
          {surveyModelRef.current && (
            <div className="survey-container rounded-xl overflow-hidden shadow-sm">
              <Survey model={surveyModelRef.current} />
            </div>
          )}

          {phase === "submitting" && (
            <div className="flex items-center justify-center gap-2 py-4 text-muted-foreground">
              <Loader2 className="animate-spin w-5 h-5" />
              Enviando respostas...
            </div>
          )}
        </div>
      </div>
    );
  }

  return null;
}
