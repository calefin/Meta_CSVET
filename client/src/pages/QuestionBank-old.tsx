// @ts-nocheck — arquivo legado, não utilizado na navegação atual
import { useState, useMemo } from "react";
import { useAuth } from "@/_core/hooks/useAuth";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Loader2, Trash2, Edit2, ChevronLeft, ChevronRight, Search } from "lucide-react";
import { trpc } from "@/lib/trpc";
import { toast } from "sonner";
import { useLocation } from "wouter";

const ITEMS_PER_PAGE = 10;

export default function QuestionBank() {
  const { user, loading: authLoading } = useAuth();
  const [, navigate] = useLocation();
  const [searchQuery, setSearchQuery] = useState("");
  const [filterType, setFilterType] = useState<string>("all");
  const [currentPage, setCurrentPage] = useState(1);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [editData, setEditData] = useState<any>(null);

  const { data: questions = [], isLoading, refetch } = trpc.questions.list.useQuery();
  const deleteMutation = trpc.questions.delete.useMutation();
  const updateMutation = trpc.questions.update.useMutation();

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

  const filteredQuestions = useMemo(() => {
    return questions.filter((q) => {
      const matchesSearch =
        q.statement.toLowerCase().includes(searchQuery.toLowerCase()) ||
        q.type.toLowerCase().includes(searchQuery.toLowerCase());
      const matchesType = filterType === "all" || q.type === filterType;
      return matchesSearch && matchesType;
    });
  }, [questions, searchQuery, filterType]);

  const totalPages = Math.ceil(filteredQuestions.length / ITEMS_PER_PAGE);
  const paginatedQuestions = filteredQuestions.slice(
    (currentPage - 1) * ITEMS_PER_PAGE,
    currentPage * ITEMS_PER_PAGE
  );

  const handleDelete = async () => {
    if (deleteId === null) return;
    try {
      await deleteMutation.mutateAsync({ id: deleteId });
      toast.success("Questão deletada com sucesso");
      refetch();
      setDeleteId(null);
    } catch (error) {
      toast.error("Erro ao deletar questão");
    }
  };

  const handleEdit = (question: any) => {
    setEditingId(question.id);
    setEditData({ ...question });
  };

  const handleSaveEdit = async () => {
    if (!editingId || !editData) return;
    try {
      await updateMutation.mutateAsync({
        id: editingId,
        type: editData.type,
        statement: editData.statement,
        alternatives: editData.alternatives,
        correctAnswer: editData.correctAnswer,
      });
      toast.success("Questão atualizada com sucesso");
      refetch();
      setEditingId(null);
      setEditData(null);
    } catch (error) {
      toast.error("Erro ao atualizar questão");
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100">
      {/* Navigation */}
      <nav className="border-b border-slate-200 bg-white/80 backdrop-blur-sm sticky top-0 z-50">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
          <h1 className="text-xl font-bold text-slate-900">Prof-Aid</h1>
          <Button variant="ghost" onClick={() => navigate("/")} className="text-slate-700">
            Voltar
          </Button>
        </div>
      </nav>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div className="mb-8">
          <h1 className="text-4xl font-bold text-slate-900 mb-2">Banco de Questões</h1>
          <p className="text-slate-600">Gerencie suas questões acadêmicas</p>
        </div>

        {/* Filters */}
        <Card className="border-0 shadow-lg mb-8">
          <CardHeader>
            <CardTitle>Filtros e Busca</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid md:grid-cols-3 gap-4">
              <div>
                <Label htmlFor="search" className="text-slate-700 mb-2 block">
                  Buscar
                </Label>
                <div className="relative">
                  <Search className="absolute left-3 top-3 w-4 h-4 text-slate-400" />
                  <Input
                    id="search"
                    placeholder="Buscar questão..."
                    value={searchQuery}
                    onChange={(e) => {
                      setSearchQuery(e.target.value);
                      setCurrentPage(1);
                    }}
                    className="pl-10"
                  />
                </div>
              </div>

              <div>
                <Label htmlFor="type" className="text-slate-700 mb-2 block">
                  Tipo
                </Label>
                <Select value={filterType} onValueChange={(value) => {
                  setFilterType(value);
                  setCurrentPage(1);
                }}>
                  <SelectTrigger id="type">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">Todos os tipos</SelectItem>
                    <SelectItem value="multiple_choice">Múltipla Escolha</SelectItem>
                    <SelectItem value="essay">Discursiva</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <div className="flex items-end">
                <Button
                  onClick={() => refetch()}
                  variant="outline"
                  className="w-full"
                >
                  Atualizar
                </Button>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Questions List */}
        <Card className="border-0 shadow-lg">
          <CardHeader>
            <CardTitle>
              Questões ({filteredQuestions.length})
            </CardTitle>
            <CardDescription>
              Página {currentPage} de {totalPages || 1}
            </CardDescription>
          </CardHeader>
          <CardContent>
            {isLoading ? (
              <div className="flex justify-center py-8">
                <Loader2 className="animate-spin w-8 h-8 text-blue-600" />
              </div>
            ) : paginatedQuestions.length === 0 ? (
              <div className="text-center py-8">
                <p className="text-slate-600">Nenhuma questão encontrada</p>
              </div>
            ) : (
              <>
                <div className="space-y-4">
                  {paginatedQuestions.map((q) => (
                    <div key={q.id} className="p-4 border border-slate-200 rounded-lg hover:border-slate-300 transition">
                      {editingId === q.id ? (
                        <div className="space-y-4">
                          <div>
                            <Label className="text-slate-700 mb-2 block">Enunciado</Label>
                            <textarea
                              value={editData.statement}
                              onChange={(e) =>
                                setEditData({ ...editData, statement: e.target.value })
                              }
                              className="w-full p-2 border border-slate-300 rounded-lg"
                              rows={3}
                            />
                          </div>

                          {editData.type === "multiple_choice" && (
                            <>
                              <div>
                                <Label className="text-slate-700 mb-2 block">Alternativas</Label>
                                {editData.alternatives?.map((alt: string, idx: number) => (
                                  <div key={idx} className="flex gap-2 mb-2">
                                    <span className="text-slate-600 font-medium w-8">
                                      {String.fromCharCode(65 + idx)})
                                    </span>
                                    <input
                                      type="text"
                                      value={alt}
                                      onChange={(e) => {
                                        const newAlts = [...editData.alternatives];
                                        newAlts[idx] = e.target.value;
                                        setEditData({ ...editData, alternatives: newAlts });
                                      }}
                                      className="flex-1 p-2 border border-slate-300 rounded-lg"
                                    />
                                  </div>
                                ))}
                              </div>

                              <div>
                                <Label className="text-slate-700 mb-2 block">Resposta Correta</Label>
                                <input
                                  type="text"
                                  value={editData.correctAnswer || ""}
                                  onChange={(e) =>
                                    setEditData({ ...editData, correctAnswer: e.target.value })
                                  }
                                  className="w-full p-2 border border-slate-300 rounded-lg"
                                />
                              </div>
                            </>
                          )}

                          <div className="flex gap-2 justify-end">
                            <Button
                              onClick={handleSaveEdit}
                              className="bg-green-600 hover:bg-green-700 text-white"
                            >
                              Salvar
                            </Button>
                            <Button
                              onClick={() => {
                                setEditingId(null);
                                setEditData(null);
                              }}
                              variant="outline"
                            >
                              Cancelar
                            </Button>
                          </div>
                        </div>
                      ) : (
                        <>
                          <div className="flex items-start justify-between mb-3">
                            <div className="flex-1">
                              <div className="flex gap-2 items-center mb-2">
                                <span className="text-xs px-2 py-1 bg-blue-100 text-blue-700 rounded">
                                  {q.type === "multiple_choice" ? "Múltipla Escolha" : "Discursiva"}
                                </span>
                              </div>
                              <p className="text-slate-900 font-medium">{q.statement}</p>
                            </div>
                            <div className="flex gap-2 ml-4">
                              <Button
                                onClick={() => handleEdit(q)}
                                size="sm"
                                variant="outline"
                                className="text-blue-600 hover:text-blue-700"
                              >
                                <Edit2 className="w-4 h-4" />
                              </Button>
                              <Button
                                onClick={() => setDeleteId(q.id)}
                                size="sm"
                                variant="outline"
                                className="text-red-600 hover:text-red-700"
                              >
                                <Trash2 className="w-4 h-4" />
                              </Button>
                            </div>
                          </div>

                          {q.alternatives && q.alternatives.length > 0 && (
                            <div className="space-y-1 text-sm text-slate-600 ml-0">
                              {q.alternatives.map((alt: string, i: number) => (
                                <p key={i}>
                                  {String.fromCharCode(65 + i)}) {alt}
                                </p>
                              ))}
                            </div>
                          )}

                          {q.correctAnswer && (
                            <p className="text-sm text-green-600 mt-2">
                              <strong>Resposta:</strong> {q.correctAnswer}
                            </p>
                          )}
                        </>
                      )}
                    </div>
                  ))}
                </div>

                {/* Pagination */}
                {totalPages > 1 && (
                  <div className="flex justify-between items-center mt-6 pt-6 border-t border-slate-200">
                    <Button
                      onClick={() => setCurrentPage(Math.max(1, currentPage - 1))}
                      disabled={currentPage === 1}
                      variant="outline"
                    >
                      <ChevronLeft className="w-4 h-4 mr-2" />
                      Anterior
                    </Button>

                    <span className="text-slate-600">
                      Página {currentPage} de {totalPages}
                    </span>

                    <Button
                      onClick={() => setCurrentPage(Math.min(totalPages, currentPage + 1))}
                      disabled={currentPage === totalPages}
                      variant="outline"
                    >
                      Próxima
                      <ChevronRight className="w-4 h-4 ml-2" />
                    </Button>
                  </div>
                )}
              </>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Delete Confirmation Dialog */}
      <AlertDialog open={deleteId !== null} onOpenChange={(open) => !open && setDeleteId(null)}>
        <AlertDialogContent>
          <AlertDialogTitle>Deletar Questão</AlertDialogTitle>
          <AlertDialogDescription>
            Tem certeza que deseja deletar esta questão? Esta ação não pode ser desfeita.
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
    </div>
  );
}
