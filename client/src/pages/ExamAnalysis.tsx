import { useLocation } from "wouter";
import { trpc } from "@/lib/trpc";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Loader2, BarChart3, ArrowLeft, TrendingUp, AlertTriangle, Info } from "lucide-react";
import { Button } from "@/components/ui/button";
import {
  BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid, Cell,
} from "recharts";
import BackButton from "@/components/BackButton";
import {
  Tooltip as UITooltip,
  TooltipContent,
  TooltipTrigger,
} from "@/components/ui/tooltip";

// ── Helpers ────────────────────────────────────────────────────────────────

function difficultyLabel(d: number): { label: string; color: string } {
  if (d >= 0.7) return { label: "Fácil", color: "bg-green-100 text-green-700 border-green-200" };
  if (d >= 0.3) return { label: "Médio", color: "bg-yellow-100 text-yellow-700 border-yellow-200" };
  return { label: "Difícil", color: "bg-red-100 text-red-700 border-red-200" };
}

function discriminationLabel(d: number): { label: string; color: string } {
  if (d >= 0.4) return { label: "Ótimo", color: "text-green-600" };
  if (d >= 0.3) return { label: "Bom", color: "text-teal-600" };
  if (d >= 0.2) return { label: "Aceitável", color: "text-yellow-600" };
  if (d >= 0) return { label: "Fraco", color: "text-orange-600" };
  return { label: "Negativo", color: "text-red-600" };
}

function StatCard({ label, value, sub, icon: Icon }: { label: string; value: string; sub?: string; icon?: any }) {
  return (
    <Card className="border shadow-sm">
      <CardContent className="py-4 px-4">
        <div className="flex items-center justify-between">
          <div>
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="text-2xl font-bold mt-0.5">{value}</p>
            {sub && <p className="text-xs text-muted-foreground mt-0.5">{sub}</p>}
          </div>
          {Icon && (
            <div className="p-2 rounded-lg bg-emerald-50">
              <Icon className="w-5 h-5 text-emerald-600" />
            </div>
          )}
        </div>
      </CardContent>
    </Card>
  );
}

// ── Distractor mini-bars ───────────────────────────────────────────────────

function DistractorBars({ distractors }: { distractors: { label: string; pct: number; isCorrect: boolean }[] }) {
  return (
    <div className="flex gap-1 items-end h-8">
      {distractors.map((d) => (
        <UITooltip key={d.label}>
          <TooltipTrigger asChild>
            <div className="flex flex-col items-center gap-0.5 cursor-default">
              <div
                className={`w-5 rounded-sm ${d.isCorrect ? "bg-emerald-500" : "bg-slate-300"}`}
                style={{ height: `${Math.max(4, d.pct * 28)}px` }}
              />
              <span className="text-[9px] text-muted-foreground">{d.label}</span>
            </div>
          </TooltipTrigger>
          <TooltipContent>
            {d.label}: {Math.round(d.pct * 100)}%{d.isCorrect ? " ✓ correta" : ""}
          </TooltipContent>
        </UITooltip>
      ))}
    </div>
  );
}

// ── Main ───────────────────────────────────────────────────────────────────

export default function ExamAnalysis() {
  const [, navigate] = useLocation();
  const examId = parseInt(window.location.pathname.split("/")[2] ?? "0");

  const { data: exam } = trpc.exams.get.useQuery({ id: examId }, { enabled: !!examId });
  const { data, isLoading } = trpc.reports.examAnalysis.useQuery({ examId }, { enabled: !!examId });

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-24">
        <Loader2 className="animate-spin w-8 h-8 text-emerald-600" />
      </div>
    );
  }

  if (!data || data.totalResponses === 0) {
    return (
      <div className="space-y-4 max-w-4xl">
        <BackButton />
        <h1 className="text-2xl font-bold">{exam?.name ?? "Análise da Avaliação"}</h1>
        <Card>
          <CardContent className="flex flex-col items-center py-16 gap-3">
            <BarChart3 className="w-10 h-10 text-muted-foreground" />
            <p className="text-muted-foreground text-sm text-center">
              Nenhuma resposta encontrada para esta avaliação.<br />
              Compartilhe o link de quiz e aguarde os alunos responderem.
            </p>
            <Button variant="outline" size="sm" onClick={() => navigate("/sharing")}>
              Ir para Compartilhamento
            </Button>
          </CardContent>
        </Card>
      </div>
    );
  }

  const { stats, gradeDistribution, questions } = data;

  return (
    <div className="space-y-6 max-w-5xl">
      <BackButton />

      <div>
        <h1 className="text-2xl font-bold">{exam?.name ?? "Análise da Avaliação"}</h1>
        <p className="text-muted-foreground text-sm mt-1">
          {data.totalResponses} resposta(s) · análise psicométrica e distribuição de notas
        </p>
      </div>

      {/* Stats row */}
      {stats && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <StatCard label="Média" value={`${stats.mean}/10`} icon={TrendingUp} />
          <StatCard label="Mediana" value={`${stats.median}/10`} />
          <StatCard label="Desvio Padrão" value={stats.stddev.toFixed(1)} />
          <StatCard label="Taxa de Aprovação" value={`${stats.passRate}%`} sub="≥ 5,0" />
        </div>
      )}

      {/* Grade distribution */}
      <Card className="border shadow-sm">
        <CardHeader className="pb-2">
          <CardTitle className="text-sm font-semibold flex items-center gap-2">
            <BarChart3 className="w-4 h-4 text-purple-500" />
            Distribuição de Notas
          </CardTitle>
        </CardHeader>
        <CardContent>
          <ResponsiveContainer width="100%" height={180}>
            <BarChart data={gradeDistribution} margin={{ top: 4, right: 4, left: -20, bottom: 0 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
              <XAxis dataKey="range" tick={{ fontSize: 10 }} />
              <YAxis tick={{ fontSize: 10 }} allowDecimals={false} />
              <Tooltip formatter={(v: any) => [`${v} aluno(s)`, "Respostas"]} />
              <Bar dataKey="count" radius={[3, 3, 0, 0]} name="Alunos">
                {gradeDistribution.map((entry, i) => (
                  <Cell
                    key={i}
                    fill={entry.from >= 0.5 ? "#10b981" : entry.from >= 0.3 ? "#f59e0b" : "#ef4444"}
                  />
                ))}
              </Bar>
            </BarChart>
          </ResponsiveContainer>
        </CardContent>
      </Card>

      {/* Question analysis table */}
      <Card className="border shadow-sm">
        <CardHeader className="pb-2">
          <CardTitle className="text-sm font-semibold flex items-center gap-2">
            <TrendingUp className="w-4 h-4 text-emerald-500" />
            Análise por Questão
            <UITooltip>
              <TooltipTrigger asChild>
                <Info className="w-3.5 h-3.5 text-muted-foreground cursor-help" />
              </TooltipTrigger>
              <TooltipContent className="max-w-xs text-xs">
                <p><strong>Dificuldade:</strong> % de acertos. Fácil ≥ 70%, Médio 30–70%, Difícil &lt; 30%.</p>
                <p className="mt-1"><strong>Discriminação:</strong> diferença de acerto entre os 27% melhores e 27% piores. Ótimo ≥ 0,4.</p>
              </TooltipContent>
            </UITooltip>
          </CardTitle>
        </CardHeader>
        <CardContent className="p-0">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b bg-muted/40">
                  <th className="px-4 py-2 text-left font-medium text-muted-foreground w-8">#</th>
                  <th className="px-4 py-2 text-left font-medium text-muted-foreground">Enunciado</th>
                  <th className="px-4 py-2 text-center font-medium text-muted-foreground">Correta</th>
                  <th className="px-4 py-2 text-center font-medium text-muted-foreground">Dificuldade</th>
                  <th className="px-4 py-2 text-center font-medium text-muted-foreground">Discriminação</th>
                  <th className="px-4 py-2 text-center font-medium text-muted-foreground">Distratores</th>
                </tr>
              </thead>
              <tbody>
                {questions.map((q: any, i: number) => {
                  const diff = difficultyLabel(q.difficulty);
                  const disc = discriminationLabel(q.discrimination);
                  return (
                    <tr key={q.questionId} className={i % 2 === 0 ? "" : "bg-muted/20"}>
                      <td className="px-4 py-3 text-muted-foreground text-xs">{q.order}</td>
                      <td className="px-4 py-3 max-w-xs">
                        <p className="truncate text-xs text-foreground">{q.statement || "–"}</p>
                        <p className="text-[10px] text-muted-foreground mt-0.5">
                          {q.totalCorrect}/{q.totalAnswered} acertos
                        </p>
                      </td>
                      <td className="px-4 py-3 text-center">
                        <span className="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold">
                          {q.correctAnswer}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-center">
                        <div className="flex flex-col items-center gap-1">
                          <Badge variant="outline" className={`text-[10px] px-1.5 py-0 ${diff.color}`}>
                            {diff.label}
                          </Badge>
                          <span className="text-[10px] text-muted-foreground">
                            {Math.round(q.difficulty * 100)}%
                          </span>
                        </div>
                      </td>
                      <td className="px-4 py-3 text-center">
                        <div className="flex flex-col items-center gap-0.5">
                          <span className={`text-xs font-semibold ${disc.color}`}>
                            {disc.label}
                          </span>
                          <span className="text-[10px] text-muted-foreground">
                            {q.discrimination >= 0 ? "+" : ""}{q.discrimination.toFixed(2)}
                          </span>
                        </div>
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex justify-center">
                          {q.distractors && q.distractors.length > 0 ? (
                            <DistractorBars distractors={q.distractors} />
                          ) : (
                            <span className="text-xs text-muted-foreground">—</span>
                          )}
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          {questions.some((q: any) => q.discrimination < 0.2) && (
            <div className="px-4 py-3 border-t bg-amber-50 flex items-start gap-2">
              <AlertTriangle className="w-4 h-4 text-amber-600 mt-0.5 shrink-0" />
              <p className="text-xs text-amber-700">
                <strong>{questions.filter((q: any) => q.discrimination < 0.2).length}</strong> questão(ões) com discriminação fraca ou negativa.
                Considere revisar o enunciado ou as alternativas.
              </p>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
