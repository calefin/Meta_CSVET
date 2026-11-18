import { useEffect } from "react";
import { trpc } from "@/lib/trpc";
import { Loader2 } from "lucide-react";

export default function StudentReport() {
  const studentId = parseInt(window.location.pathname.split("/")[2] ?? "0");

  const { data, isLoading } = trpc.reports.studentPerformance.useQuery(
    { studentId },
    { enabled: !!studentId }
  );

  useEffect(() => {
    if (data) {
      // small delay to let the DOM render before print dialog
      const t = setTimeout(() => window.print(), 400);
      return () => clearTimeout(t);
    }
  }, [data]);

  if (isLoading || !data) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <Loader2 className="animate-spin w-8 h-8 text-emerald-600" />
      </div>
    );
  }

  const { student, grades, avgPct } = data;
  const fullName = `${student.firstName} ${student.lastName}`.trim();

  return (
    <>
      {/* Print styles injected via style tag */}
      <style>{`
        @media print {
          body { margin: 0; }
          .no-print { display: none !important; }
          @page { margin: 2cm; }
        }
        body { font-family: Arial, sans-serif; color: #111; }
      `}</style>

      {/* Close button — hidden on print */}
      <div className="no-print fixed top-4 right-4 flex gap-2 z-50">
        <button
          onClick={() => window.print()}
          className="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700"
        >
          Imprimir / Salvar PDF
        </button>
        <button
          onClick={() => window.close()}
          className="px-4 py-2 bg-slate-200 text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-300"
        >
          Fechar
        </button>
      </div>

      <div className="max-w-3xl mx-auto p-8">
        {/* Header */}
        <div className="border-b-2 border-slate-800 pb-4 mb-6">
          <h1 className="text-2xl font-bold text-slate-900">Boletim Individual</h1>
          <h2 className="text-xl font-semibold text-slate-700 mt-1">{fullName}</h2>
          {student.studentId && <p className="text-sm text-slate-500 mt-0.5">Matrícula: {student.studentId}</p>}
          {student.email && <p className="text-sm text-slate-500">E-mail: {student.email}</p>}
        </div>

        {/* Summary */}
        <div className="grid grid-cols-3 gap-4 mb-6">
          <div className="border rounded-lg p-4 text-center">
            <p className="text-3xl font-bold text-emerald-600">{grades.length}</p>
            <p className="text-xs text-slate-500 mt-1">Avaliações</p>
          </div>
          <div className="border rounded-lg p-4 text-center">
            <p className="text-3xl font-bold text-slate-800">{avgPct ?? "–"}%</p>
            <p className="text-xs text-slate-500 mt-1">Média geral</p>
          </div>
          <div className="border rounded-lg p-4 text-center">
            <p className="text-3xl font-bold" style={{
              color: avgPct !== null
                ? avgPct >= 70 ? "#16a34a" : avgPct >= 50 ? "#d97706" : "#dc2626"
                : "#6b7280"
            }}>
              {avgPct === null ? "–" : avgPct >= 70 ? "Aprovado" : avgPct >= 50 ? "Em recuperação" : "Reprovado"}
            </p>
            <p className="text-xs text-slate-500 mt-1">Situação geral</p>
          </div>
        </div>

        {/* Grade table */}
        <h3 className="text-base font-semibold text-slate-700 mb-3">Histórico de Avaliações</h3>
        {grades.length === 0 ? (
          <p className="text-sm text-slate-500 italic">Nenhuma nota registrada.</p>
        ) : (
          <table className="w-full border-collapse text-sm">
            <thead>
              <tr className="bg-slate-100">
                <th className="border px-3 py-2 text-left font-semibold">Avaliação</th>
                <th className="border px-3 py-2 text-left font-semibold">Disciplina</th>
                <th className="border px-3 py-2 text-center font-semibold">Data</th>
                <th className="border px-3 py-2 text-center font-semibold">Nota</th>
                <th className="border px-3 py-2 text-center font-semibold">%</th>
                <th className="border px-3 py-2 text-center font-semibold">Situação</th>
              </tr>
            </thead>
            <tbody>
              {grades.map((g: any, i: number) => {
                const pct = g.pct;
                const situation = pct >= 70 ? "Aprovado" : pct >= 50 ? "Recuperação" : "Reprovado";
                const color = pct >= 70 ? "#16a34a" : pct >= 50 ? "#d97706" : "#dc2626";
                return (
                  <tr key={i} style={{ backgroundColor: i % 2 === 0 ? "#fff" : "#f8fafc" }}>
                    <td className="border px-3 py-2">{g.examName}</td>
                    <td className="border px-3 py-2 text-slate-500">{g.discipline}</td>
                    <td className="border px-3 py-2 text-center">
                      {g.examDate ? new Date(g.examDate).toLocaleDateString("pt-BR") : "–"}
                    </td>
                    <td className="border px-3 py-2 text-center font-semibold">
                      {g.score.toFixed(1)}/{g.maxScore.toFixed(1)}
                    </td>
                    <td className="border px-3 py-2 text-center">{pct}%</td>
                    <td className="border px-3 py-2 text-center font-medium" style={{ color }}>{situation}</td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        )}

        {/* Footer */}
        <div className="mt-8 pt-4 border-t text-xs text-slate-400 flex justify-between">
          <span>Prof-Aid · Relatório gerado em {new Date().toLocaleDateString("pt-BR")}</span>
          <span>prof-aid.com</span>
        </div>
      </div>
    </>
  );
}
