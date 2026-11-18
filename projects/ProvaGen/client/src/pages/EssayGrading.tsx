import { useState } from "react";
import { useLocation } from "wouter";
import { trpc } from "@/lib/trpc";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Loader2, PenLine, CheckCircle2, ChevronDown, ChevronUp } from "lucide-react";
import { toast } from "sonner";
import BackButton from "@/components/BackButton";

export default function EssayGrading() {
  const examId = parseInt(window.location.pathname.split("/")[2] ?? "0");
  const { data: exam } = trpc.exams.get.useQuery({ id: examId }, { enabled: !!examId });
  const { data: responses = [], isLoading, refetch } = trpc.sharing.listEssayResponses.useQuery(
    { examId },
    { enabled: !!examId }
  );

  const gradeMutation = trpc.sharing.gradeEssay.useMutation({
    onSuccess: () => { refetch(); toast.success("Nota salva!"); },
    onError: (e) => toast.error(e.message),
  });

  const [expanded, setExpanded] = useState<number | null>(null);
  const [scores, setScores] = useState<Record<string, string>>({});

  function handleGrade(responseId: number, questionId: number) {
    const key = `${responseId}-${questionId}`;
    const points = parseFloat(scores[key] ?? "");
    if (isNaN(points) || points < 0) return toast.error("Nota inválida");
    gradeMutation.mutate({ examId, responseId, questionId, points });
  }

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-24">
        <Loader2 className="animate-spin w-8 h-8 text-emerald-600" />
      </div>
    );
  }

  return (
    <div className="space-y-5 max-w-4xl">
      <BackButton />

      <div>
        <h1 className="text-2xl font-bold flex items-center gap-2">
          <PenLine className="w-6 h-6 text-emerald-600" />
          Correção de Dissertativas
        </h1>
        <p className="text-muted-foreground text-sm mt-1">
          {exam?.name ?? `Avaliação #${examId}`} · {responses.length} resposta(s) recebida(s)
        </p>
      </div>

      {responses.length === 0 ? (
        <Card>
          <CardContent className="flex flex-col items-center py-16 gap-3">
            <PenLine className="w-10 h-10 text-muted-foreground" />
            <p className="text-muted-foreground text-sm text-center">
              Nenhuma resposta encontrada com questões dissertativas.
            </p>
          </CardContent>
        </Card>
      ) : (
        <div className="space-y-3">
          {responses.map((r: any) => {
            const isOpen = expanded === r.responseId;
            const gradedCount = r.essayAnswers.filter((a: any) => a.manualScore !== null).length;
            const totalEssays = r.essayAnswers.length;
            const allGraded = gradedCount === totalEssays;

            return (
              <Card key={r.responseId} className={`border ${allGraded ? "border-emerald-200" : ""}`}>
                <button
                  className="w-full text-left"
                  onClick={() => setExpanded(isOpen ? null : r.responseId)}
                >
                  <CardHeader className="py-3 px-4">
                    <div className="flex items-center justify-between gap-2">
                      <div className="flex items-center gap-2 min-w-0">
                        {allGraded && <CheckCircle2 className="w-4 h-4 text-emerald-500 shrink-0" />}
                        <CardTitle className="text-base font-semibold truncate">{r.studentName}</CardTitle>
                        {r.studentEmail && (
                          <span className="text-xs text-muted-foreground truncate hidden sm:block">{r.studentEmail}</span>
                        )}
                      </div>
                      <div className="flex items-center gap-2 shrink-0">
                        <Badge variant="outline" className={`text-xs ${allGraded ? "text-emerald-600 border-emerald-300" : "text-amber-600 border-amber-300"}`}>
                          {gradedCount}/{totalEssays} corrigida(s)
                        </Badge>
                        {isOpen ? <ChevronUp className="w-4 h-4 text-muted-foreground" /> : <ChevronDown className="w-4 h-4 text-muted-foreground" />}
                      </div>
                    </div>
                    <p className="text-xs text-muted-foreground mt-0.5">
                      Enviado em {new Date(r.submittedAt).toLocaleString("pt-BR")}
                    </p>
                  </CardHeader>
                </button>

                {isOpen && (
                  <CardContent className="px-4 pb-4 pt-0 space-y-4 border-t">
                    {r.essayAnswers.map((a: any) => {
                      const key = `${r.responseId}-${a.questionId}`;
                      const currentScore = scores[key] ?? (a.manualScore !== null ? String(a.manualScore) : "");
                      return (
                        <div key={a.questionId} className="space-y-2 p-3 rounded-lg bg-muted/30 border">
                          <p className="text-sm font-medium text-foreground">{a.statement}</p>
                          <div className="bg-white border rounded-md p-3 text-sm whitespace-pre-wrap min-h-[60px]">
                            {a.answer || <span className="text-muted-foreground italic">Sem resposta</span>}
                          </div>
                          <div className="flex items-center gap-2">
                            <label className="text-sm text-muted-foreground shrink-0">Nota:</label>
                            <Input
                              type="number"
                              min={0}
                              max={100}
                              step={0.5}
                              className="w-24 h-8 text-sm"
                              placeholder="0.0"
                              value={currentScore}
                              onChange={(e) => setScores((prev) => ({ ...prev, [key]: e.target.value }))}
                            />
                            <Button
                              size="sm"
                              className="h-8 gap-1"
                              onClick={() => handleGrade(r.responseId, a.questionId)}
                              disabled={gradeMutation.isPending}
                            >
                              {gradeMutation.isPending ? <Loader2 className="w-3 h-3 animate-spin" /> : <CheckCircle2 className="w-3 h-3" />}
                              Salvar
                            </Button>
                            {a.manualScore !== null && (
                              <Badge variant="outline" className="text-emerald-600 border-emerald-300 text-xs">
                                Salvo: {a.manualScore}
                              </Badge>
                            )}
                          </div>
                        </div>
                      );
                    })}
                  </CardContent>
                )}
              </Card>
            );
          })}
        </div>
      )}
    </div>
  );
}
