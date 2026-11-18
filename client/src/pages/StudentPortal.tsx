import { trpc } from "@/lib/trpc";
import { GraduationCap, Loader2, PenLine, Trophy, TrendingUp } from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Progress } from "@/components/ui/progress";

interface Props {
  token: string;
}

export default function StudentPortal({ token }: Props) {
  const { data, isLoading, error } = trpc.students.getPortalData.useQuery({ token });

  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <Loader2 className="animate-spin w-8 h-8 text-emerald-600" />
      </div>
    );
  }

  if (error || !data) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-slate-50">
        <div className="text-center">
          <GraduationCap className="w-12 h-12 text-slate-400 mx-auto mb-4" />
          <h1 className="text-xl font-semibold text-slate-700">Portal não encontrado</h1>
          <p className="text-slate-500 mt-2 text-sm">O link de acesso é inválido ou expirou.</p>
        </div>
      </div>
    );
  }

  const { student, grades } = data;
  const totalExams = grades.length;
  const avgPct =
    totalExams > 0
      ? Math.round(grades.reduce((s, g) => s + (g.maxScore > 0 ? (g.score / g.maxScore) * 100 : 0), 0) / totalExams)
      : null;

  const approvalColor = (pct: number) =>
    pct >= 70 ? "text-green-600 bg-green-50" : pct >= 50 ? "text-yellow-600 bg-yellow-50" : "text-red-600 bg-red-50";

  return (
    <div className="min-h-screen bg-gradient-to-br from-indigo-50 via-slate-50 to-blue-50">
      {/* Header */}
      <header className="border-b border-slate-200 bg-white/80 backdrop-blur-sm sticky top-0 z-10">
        <div className="max-w-3xl mx-auto px-4 py-4 flex items-center gap-3">
          <PenLine className="w-5 h-5 text-primary" />
          <span className="font-bold text-slate-900 text-lg">Prof-Aid</span>
          <span className="text-slate-300">—</span>
          <span className="text-slate-600 text-sm">Portal do Aluno</span>
        </div>
      </header>

      <main className="max-w-3xl mx-auto px-4 py-8 space-y-6">
        {/* Student info */}
        <Card className="border-0 shadow-md">
          <CardContent className="py-5 px-5 flex items-center gap-4">
            <div className="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center">
              <GraduationCap className="w-6 h-6 text-blue-600" />
            </div>
            <div>
              <h1 className="text-xl font-bold">
                {student.firstName} {student.lastName}
              </h1>
              {student.studentId && (
                <p className="text-sm text-muted-foreground">Matrícula: {student.studentId}</p>
              )}
            </div>
            {avgPct !== null && (
              <div className="ml-auto text-right">
                <p className="text-xs text-muted-foreground">Média geral</p>
                <p className={`text-2xl font-bold ${avgPct >= 70 ? "text-green-600" : avgPct >= 50 ? "text-yellow-600" : "text-red-600"}`}>
                  {avgPct}%
                </p>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Summary stats */}
        {totalExams > 0 && (
          <div className="grid grid-cols-2 gap-4">
            <Card className="border shadow-sm">
              <CardContent className="py-4 px-4 flex items-center gap-3">
                <Trophy className="w-5 h-5 text-yellow-500" />
                <div>
                  <p className="text-xs text-muted-foreground">Avaliações realizadas</p>
                  <p className="text-xl font-bold">{totalExams}</p>
                </div>
              </CardContent>
            </Card>
            <Card className="border shadow-sm">
              <CardContent className="py-4 px-4 flex items-center gap-3">
                <TrendingUp className="w-5 h-5 text-blue-500" />
                <div>
                  <p className="text-xs text-muted-foreground">Aproveitamento médio</p>
                  <p className="text-xl font-bold">{avgPct ?? "–"}%</p>
                </div>
              </CardContent>
            </Card>
          </div>
        )}

        {/* Grades list */}
        <div>
          <h2 className="text-sm font-semibold text-muted-foreground uppercase tracking-wider mb-3 flex items-center gap-2">
            Histórico de Notas
          </h2>
          {grades.length === 0 ? (
            <Card className="border">
              <CardContent className="py-10 text-center text-muted-foreground text-sm">
                Nenhuma nota lançada ainda.
              </CardContent>
            </Card>
          ) : (
            <div className="space-y-3">
              {grades.map((g, i) => {
                const pct = g.maxScore > 0 ? Math.round((g.score / g.maxScore) * 100) : 0;
                return (
                  <Card key={i} className="border shadow-sm">
                    <CardContent className="py-4 px-4">
                      <div className="flex items-start justify-between gap-3 mb-3">
                        <div className="min-w-0">
                          <p className="font-medium text-sm leading-tight">{g.examName}</p>
                          <p className="text-xs text-muted-foreground mt-0.5">{g.discipline}</p>
                          {g.examDate && (
                            <p className="text-xs text-muted-foreground">
                              {new Date(g.examDate).toLocaleDateString("pt-BR")}
                            </p>
                          )}
                        </div>
                        <div className="flex items-center gap-2 shrink-0">
                          <span className="text-sm font-semibold">
                            {g.score}/{g.maxScore}
                          </span>
                          <Badge className={`text-xs ${approvalColor(pct)}`} variant="outline">
                            {pct}%
                          </Badge>
                        </div>
                      </div>
                      <Progress value={pct} className="h-1.5" />
                    </CardContent>
                  </Card>
                );
              })}
            </div>
          )}
        </div>
      </main>
    </div>
  );
}
