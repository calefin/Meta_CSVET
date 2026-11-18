import { useState } from "react";
import { useAuth } from "@/_core/hooks/useAuth";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Loader2, Plus, Trash2, FileText, Eye, Upload, Ban, RotateCcw, BarChart3, PenLine, Package } from "lucide-react";
import BackButton from "@/components/BackButton";
import { trpc } from "@/lib/trpc";
import { toast } from "sonner";
import { useLocation } from "wouter";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog";

function downloadXml(xml: string, filename: string) {
  const blob = new Blob([xml], { type: "application/xml" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a"); a.href = url; a.download = filename; a.click();
  URL.revokeObjectURL(url);
}

export default function ExamsList() {
  const { user, loading: authLoading } = useAuth();
  const [, navigate] = useLocation();
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [voidExamId, setVoidExamId] = useState<number | null>(null);
  const [qtiExamId, setQtiExamId] = useState<number | null>(null);

  const { data: exams = [], isLoading, refetch } = trpc.exams.list.useQuery();
  const deleteMutation = trpc.exams.delete.useMutation();

  const qtiQuery = trpc.qti.exportExam.useQuery(
    { examId: qtiExamId ?? 0 },
    {
      enabled: qtiExamId !== null,
      onSuccess: (data: any) => {
        if (data?.xml) { downloadXml(data.xml, data.filename); }
        setQtiExamId(null);
        toast.success("QTI exportado!");
      },
      onError: () => { toast.error("Erro ao exportar QTI"); setQtiExamId(null); },
    } as any
  );

  if (authLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <Loader2 className="animate-spin w-8 h-8 text-blue-600" />
      </div>
    );
  }

  if (!user) {
    navigate("/");
    return null;
  }

  const handleDelete = async () => {
    if (deleteId === null) return;
    try {
      await deleteMutation.mutateAsync({ id: deleteId });
      toast.success("Prova deletada com sucesso");
      refetch();
      setDeleteId(null);
    } catch (error) {
      toast.error("Erro ao deletar prova");
    }
  };

  return (
    <div className="space-y-4 max-w-5xl">
      <BackButton />
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">Minhas Avaliações</h1>
          <p className="text-muted-foreground text-sm">Gerencie suas avaliações acadêmicas</p>
        </div>
        <Button onClick={() => navigate("/create-exam")} size="sm">
          <Plus className="w-4 h-4 mr-1" /> Nova Avaliação
        </Button>
      </div>

      <Card>
          <CardHeader>
            <CardTitle>Provas ({exams.length})</CardTitle>
            <CardDescription>Lista de todas as suas provas criadas</CardDescription>
          </CardHeader>
          <CardContent>
            {isLoading ? (
              <div className="flex justify-center py-8">
                <Loader2 className="animate-spin w-8 h-8 text-blue-600" />
              </div>
            ) : exams.length === 0 ? (
              <div className="text-center py-12">
                <FileText className="w-16 h-16 text-slate-300 mx-auto mb-4" />
                <p className="text-slate-600 mb-4">Nenhuma prova criada ainda</p>
                <Button
                  onClick={() => navigate("/create-exam")}
                  className="bg-blue-600 hover:bg-blue-700 text-white"
                >
                  <Plus className="w-4 h-4 mr-2" />
                  Criar Primeira Prova
                </Button>
              </div>
            ) : (
              <div className="space-y-4">
                {exams.map((exam: any) => (
                  <div
                    key={exam.id}
                    className="p-4 border border-slate-200 rounded-lg hover:border-slate-300 transition"
                  >
                    <div className="flex items-start justify-between">
                      <div className="flex-1">
                        <h3 className="text-lg font-semibold text-slate-900 mb-1">
                          {exam.name}
                        </h3>
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm text-slate-600 mb-3">
                          <div>
                            <span className="font-medium">Curso:</span> {exam.course}
                          </div>
                          <div>
                            <span className="font-medium">Disciplina:</span> {exam.discipline}
                          </div>
                          <div>
                            <span className="font-medium">Turma:</span> {exam.class}
                          </div>
                          <div>
                            <span className="font-medium">Professor:</span> {exam.professor}
                          </div>
                        </div>
                        <div className="flex gap-2 flex-wrap">
                          <span className="text-xs px-2 py-1 bg-blue-100 text-blue-700 rounded">
                            {exam.numberOfVersions} versão(ões)
                          </span>
                          {exam.examDate && (
                            <span className="text-xs px-2 py-1 bg-slate-100 text-slate-700 rounded">
                              {new Date(exam.examDate).toLocaleDateString("pt-BR")}
                            </span>
                          )}
                        </div>
                      </div>
                      <div className="flex gap-2 ml-4">
                        <Button
                          onClick={() => navigate(`/exam/${exam.id}/pdf`)}
                          size="sm"
                          variant="outline"
                          className="text-blue-600 hover:text-blue-700"
                          title="Gerar PDF"
                        >
                          <Eye className="w-4 h-4" />
                        </Button>
                        <Button
                          onClick={() => navigate(`/exam/${exam.id}/answer-sheet`)}
                          size="sm"
                          variant="outline"
                          className="text-green-600 hover:text-green-700"
                          title="Upload de Gabarito"
                        >
                          <Upload className="w-4 h-4" />
                        </Button>
                        <Button
                          onClick={() => navigate(`/exam/${exam.id}/analysis`)}
                          size="sm"
                          variant="outline"
                          className="text-purple-600 hover:text-purple-700"
                          title="Análise da Avaliação"
                        >
                          <BarChart3 className="w-4 h-4" />
                        </Button>
                        <Button
                          onClick={() => navigate(`/exam/${exam.id}/essay-grading`)}
                          size="sm"
                          variant="outline"
                          className="text-teal-600 hover:text-teal-700"
                          title="Corrigir questões dissertativas"
                        >
                          <PenLine className="w-4 h-4" />
                        </Button>
                        <Button
                          onClick={() => setQtiExamId(exam.id)}
                          size="sm"
                          variant="outline"
                          className="text-slate-500 hover:text-slate-700"
                          title="Exportar QTI (Moodle/Canvas)"
                          disabled={qtiExamId === exam.id && qtiQuery.isFetching}
                        >
                          {qtiExamId === exam.id && qtiQuery.isFetching
                            ? <Loader2 className="w-4 h-4 animate-spin" />
                            : <Package className="w-4 h-4" />
                          }
                        </Button>
                        <Button
                          onClick={() => setVoidExamId(exam.id)}
                          size="sm"
                          variant="outline"
                          className="text-orange-600 hover:text-orange-700"
                          title="Anular / restaurar questões"
                        >
                          <Ban className="w-4 h-4" />
                        </Button>
                        <Button
                          onClick={() => setDeleteId(exam.id)}
                          size="sm"
                          variant="outline"
                          className="text-red-600 hover:text-red-700"
                        >
                          <Trash2 className="w-4 h-4" />
                        </Button>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </CardContent>
      </Card>

      {/* Delete Confirmation Dialog */}
      <AlertDialog open={deleteId !== null} onOpenChange={(open) => !open && setDeleteId(null)}>
        <AlertDialogContent>
          <AlertDialogTitle>Deletar Prova</AlertDialogTitle>
          <AlertDialogDescription>
            Tem certeza que deseja deletar esta prova? Esta ação não pode ser desfeita.
          </AlertDialogDescription>
          <div className="flex justify-end gap-2">
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleDelete}
              className="bg-red-600 hover:bg-red-700 text-white"
            >
              Deletar
            </AlertDialogAction>
          </div>
        </AlertDialogContent>
      </AlertDialog>

      {/* Void/Unvoid Questions Dialog */}
      {voidExamId !== null && (
        <VoidQuestionsDialog examId={voidExamId} onClose={() => setVoidExamId(null)} />
      )}
    </div>
  );
}

function VoidQuestionsDialog({ examId, onClose }: { examId: number; onClose: () => void }) {
  const utils = trpc.useUtils();
  const { data: examQuestions = [], isLoading, refetch } = trpc.exams.getQuestions.useQuery({ examId });
  const voidMutation = trpc.exams.voidQuestion.useMutation({
    onSuccess: () => { refetch(); toast.success("Questão anulada — notas serão recalculadas"); },
    onError: () => toast.error("Erro ao anular questão"),
  });
  const unvoidMutation = trpc.exams.unvoidQuestion.useMutation({
    onSuccess: () => { refetch(); toast.success("Questão restaurada"); },
    onError: () => toast.error("Erro ao restaurar questão"),
  });

  const voidedCount = examQuestions.filter((q: any) => q.isVoided === "true").length;

  return (
    <Dialog open onOpenChange={(o) => { if (!o) onClose(); }}>
      <DialogContent className="max-w-lg max-h-[80vh] flex flex-col">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Ban className="w-5 h-5 text-orange-500" />
            Gerenciar Questões Anuladas
          </DialogTitle>
        </DialogHeader>

        {isLoading ? (
          <div className="flex justify-center py-10"><Loader2 className="animate-spin w-6 h-6" /></div>
        ) : examQuestions.length === 0 ? (
          <p className="text-center text-muted-foreground py-8 text-sm">Nenhuma questão nesta prova.</p>
        ) : (
          <div className="overflow-y-auto flex-1 space-y-2 pr-1">
            {voidedCount > 0 && (
              <div className="text-xs text-orange-600 bg-orange-50 border border-orange-200 rounded px-3 py-2">
                {voidedCount} questão(ões) anulada(s). As notas dos alunos que tiverem respondido a prova serão ajustadas.
              </div>
            )}
            {examQuestions.map((eq: any, i: number) => {
              const isVoided = eq.isVoided === "true";
              return (
                <div
                  key={eq.id}
                  className={`flex items-start gap-3 p-3 border rounded-lg ${isVoided ? "bg-orange-50 border-orange-200" : "bg-white"}`}
                >
                  <span className="text-xs font-mono text-muted-foreground mt-0.5 shrink-0 w-5">{i + 1}.</span>
                  <p className="flex-1 text-sm line-clamp-2 min-w-0">{eq.question?.statement ?? "–"}</p>
                  <div className="flex items-center gap-1.5 shrink-0">
                    {isVoided && <Badge variant="outline" className="text-xs text-orange-600 border-orange-300 bg-orange-50">Anulada</Badge>}
                    <Button
                      size="sm"
                      variant={isVoided ? "outline" : "ghost"}
                      className={isVoided ? "text-green-600 hover:text-green-700 h-7 px-2" : "text-orange-600 hover:text-orange-700 h-7 px-2"}
                      onClick={() => {
                        if (isVoided) {
                          unvoidMutation.mutate({ examId, examQuestionId: eq.id });
                        } else {
                          voidMutation.mutate({ examId, examQuestionId: eq.id });
                        }
                      }}
                      disabled={voidMutation.isPending || unvoidMutation.isPending}
                    >
                      {isVoided ? <RotateCcw className="w-3.5 h-3.5 mr-1" /> : <Ban className="w-3.5 h-3.5 mr-1" />}
                      {isVoided ? "Restaurar" : "Anular"}
                    </Button>
                  </div>
                </div>
              );
            })}
          </div>
        )}

        <DialogFooter>
          <Button variant="outline" onClick={onClose}>Fechar</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
