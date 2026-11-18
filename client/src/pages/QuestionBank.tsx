import { useState, useMemo } from "react";
import BackButton from "@/components/BackButton";
import { useAuth } from "@/_core/hooks/useAuth";
import { useLocation } from "wouter";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Badge } from "@/components/ui/badge";
import {
  Loader2,
  Trash2,
  Edit2,
  ChevronLeft,
  ChevronRight,
  Search,
  Plus,
  Copy,
  PenLine,
  X,
  CheckSquare,
} from "lucide-react";
import { trpc } from "@/lib/trpc";
import { toast } from "sonner";
import { Progress } from "@/components/ui/progress";
import { formatStorageSize, getQuotaStatus } from "@/../../shared/_core/storageTypes";
import { BatchEditDialog } from "@/components/questions/BatchEditDialog";

const ITEMS_PER_PAGE = 10;

export default function QuestionBank() {
  const { user, loading: authLoading } = useAuth();
  const [, navigate] = useLocation();

  // Filters
  const [searchQuery, setSearchQuery] = useState("");
  const [filterType, setFilterType] = useState<string>("all");
  const [filterDiscipline, setFilterDiscipline] = useState<string>("all");
  const [filterDifficulty, setFilterDifficulty] = useState<string>("all");
  const [currentPage, setCurrentPage] = useState(1);

  // Dialogs
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [showBatchEdit, setShowBatchEdit] = useState(false);
  const [showBulkDeleteConfirm, setShowBulkDeleteConfirm] = useState(false);

  // Selection
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());

  const { data: questions = [], isLoading, refetch } = trpc.questions.list.useQuery({
    discipline: filterDiscipline !== "all" ? filterDiscipline : undefined,
    type: filterType !== "all" ? filterType : undefined,
    difficulty: filterDifficulty !== "all" ? filterDifficulty : undefined,
  });

  const { data: disciplines } = trpc.questions.getDisciplinesAndSubjects.useQuery();
  const { data: quota } = trpc.questions.getStorageQuota.useQuery();
  const { data: profileData } = trpc.profile.me.useQuery();

  const disciplineColors: Record<string, string> = (() => {
    try {
      return JSON.parse(profileData?.profile?.disciplineColors ?? "{}") ?? {};
    } catch {
      return {};
    }
  })();

  const deleteMutation = trpc.questions.delete.useMutation();
  const bulkDeleteMutation = trpc.questions.bulkDelete.useMutation();
  const duplicateMutation = trpc.questions.duplicate.useMutation();

  if (authLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <Loader2 className="animate-spin w-8 h-8" />
      </div>
    );
  }

  if (!user) {
    navigate("/");
    return null;
  }

  const filteredQuestions = useMemo(() => {
    return questions.filter((q: any) =>
      q.statement.toLowerCase().includes(searchQuery.toLowerCase())
    );
  }, [questions, searchQuery]);

  const totalPages = Math.ceil(filteredQuestions.length / ITEMS_PER_PAGE);
  const paginatedQuestions = filteredQuestions.slice(
    (currentPage - 1) * ITEMS_PER_PAGE,
    currentPage * ITEMS_PER_PAGE
  );

  // Selection helpers
  const allPageSelected =
    paginatedQuestions.length > 0 &&
    paginatedQuestions.every((q: any) => selectedIds.has(q.id));

  const toggleSelect = (id: number) => {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      next.has(id) ? next.delete(id) : next.add(id);
      return next;
    });
  };

  const toggleSelectAll = () => {
    if (allPageSelected) {
      setSelectedIds((prev) => {
        const next = new Set(prev);
        paginatedQuestions.forEach((q: any) => next.delete(q.id));
        return next;
      });
    } else {
      setSelectedIds((prev) => {
        const next = new Set(prev);
        paginatedQuestions.forEach((q: any) => next.add(q.id));
        return next;
      });
    }
  };

  const clearSelection = () => setSelectedIds(new Set());

  const handleDelete = async () => {
    if (deleteId === null) return;
    try {
      await deleteMutation.mutateAsync({ id: deleteId });
      toast.success("Questão deletada com sucesso");
      setSelectedIds((prev) => { const n = new Set(prev); n.delete(deleteId); return n; });
      refetch();
      setDeleteId(null);
    } catch {
      toast.error("Erro ao deletar questão");
    }
  };

  const handleBulkDelete = async () => {
    try {
      await bulkDeleteMutation.mutateAsync({ ids: Array.from(selectedIds) });
      toast.success(`${selectedIds.size} questão(ões) deletada(s)`);
      clearSelection();
      refetch();
    } catch {
      toast.error("Erro ao deletar questões");
    } finally {
      setShowBulkDeleteConfirm(false);
    }
  };

  const handleDuplicate = async (id: number) => {
    try {
      await duplicateMutation.mutateAsync({ id });
      toast.success("Questão duplicada com sucesso");
      refetch();
    } catch {
      toast.error("Erro ao duplicar questão");
    }
  };

  const getTypeLabel = (type: string) => {
    const labels: Record<string, string> = {
      multiple_choice: "Múltipla Escolha",
      multiple_choice_complex: "Múltipla Escolha Complexa",
      essay: "Dissertativa",
      assertion_reason: "Asserção e Razão",
    };
    return labels[type] || type;
  };

  const getDifficultyColor = (difficulty?: string) => {
    if (difficulty === "easy") return "secondary";
    if (difficulty === "hard") return "destructive";
    return "default";
  };

  const getDifficultyLabel = (difficulty?: string) => {
    const labels: Record<string, string> = { easy: "Fácil", medium: "Médio", hard: "Difícil" };
    return labels[difficulty || ""] || difficulty || "-";
  };

  const quotaStatus = quota ? getQuotaStatus(quota) : null;
  const disciplineList = (disciplines?.disciplines as string[] | undefined) ?? [];
  const subjectList = (disciplines?.subjects as string[] | undefined) ?? [];

  return (
    <div className="bg-gray-50 p-6 min-h-full">
      <div className="max-w-7xl mx-auto">
        <BackButton />

        <div className="flex justify-between items-center mb-6">
          <div>
            <h1 className="text-3xl font-bold">Banco de Questões</h1>
            <p className="text-gray-600 mt-1">Gerencie suas questões personalizadas</p>
          </div>
          <Button onClick={() => navigate("/create-question")} className="gap-2">
            <Plus className="w-4 h-4" /> Nova Questão
          </Button>
        </div>

        {/* Storage Quota */}
        {quota && (
          <Card className="mb-6 border-blue-200 bg-blue-50">
            <CardHeader className="pb-3">
              <CardTitle className="text-base">Armazenamento</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2">
              <Progress value={quota.usedPercentage} className="h-2" />
              <div className="flex justify-between text-sm">
                <span>
                  {formatStorageSize(quota.usedStorageMB * 1024 * 1024)} /{" "}
                  {formatStorageSize(quota.quotaLimitMB * 1024 * 1024)}
                </span>
                <span className={quotaStatus?.status === "ok" ? "text-green-600" : "text-orange-600"}>
                  {quotaStatus?.message}
                </span>
              </div>
            </CardContent>
          </Card>
        )}

        {/* Batch Action Toolbar */}
        {selectedIds.size > 0 && (
          <div className="mb-4 flex items-center gap-3 p-3 bg-blue-600 text-white rounded-lg shadow">
            <span className="font-medium text-sm">{selectedIds.size} selecionada(s)</span>
            <div className="flex-1" />
            <Button
              size="sm"
              variant="secondary"
              className="gap-1"
              onClick={() => setShowBatchEdit(true)}
            >
              <PenLine className="w-4 h-4" /> Editar em Lote
            </Button>
            <Button
              size="sm"
              variant="destructive"
              className="gap-1"
              onClick={() => setShowBulkDeleteConfirm(true)}
            >
              <Trash2 className="w-4 h-4" /> Deletar Selecionadas
            </Button>
            <Button size="sm" variant="ghost" className="text-white hover:bg-blue-700" onClick={clearSelection}>
              <X className="w-4 h-4" />
            </Button>
          </div>
        )}

        {/* Filters */}
        <Card className="mb-6">
          <CardHeader>
            <CardTitle className="text-base">Filtros</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex gap-2 mb-4">
              <div className="flex-1 relative">
                <Search className="absolute left-3 top-2.5 w-4 h-4 text-gray-400" />
                <Input
                  placeholder="Buscar questões..."
                  value={searchQuery}
                  onChange={(e) => { setSearchQuery(e.target.value); setCurrentPage(1); }}
                  className="pl-10"
                />
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <Label htmlFor="filter-type">Tipo</Label>
                <Select value={filterType} onValueChange={setFilterType}>
                  <SelectTrigger id="filter-type"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">Todos os tipos</SelectItem>
                    <SelectItem value="multiple_choice">Múltipla Escolha</SelectItem>
                    <SelectItem value="multiple_choice_complex">Múltipla Escolha Complexa</SelectItem>
                    <SelectItem value="essay">Dissertativa</SelectItem>
                    <SelectItem value="assertion_reason">Asserção e Razão</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <div>
                <Label htmlFor="filter-discipline">Disciplina</Label>
                <Select value={filterDiscipline} onValueChange={setFilterDiscipline}>
                  <SelectTrigger id="filter-discipline"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">Todas as disciplinas</SelectItem>
                    {disciplineList.map((d) => (
                      <SelectItem key={d} value={d}>{d}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div>
                <Label htmlFor="filter-difficulty">Dificuldade</Label>
                <Select value={filterDifficulty} onValueChange={setFilterDifficulty}>
                  <SelectTrigger id="filter-difficulty"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">Todas as dificuldades</SelectItem>
                    <SelectItem value="easy">Fácil</SelectItem>
                    <SelectItem value="medium">Médio</SelectItem>
                    <SelectItem value="hard">Difícil</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Questions List */}
        {/* Select-all row */}
        {paginatedQuestions.length > 0 && (
          <div className="flex items-center gap-2 mb-2 px-1">
            <Checkbox
              id="select-all"
              checked={allPageSelected}
              onCheckedChange={toggleSelectAll}
            />
            <label htmlFor="select-all" className="text-sm text-gray-500 cursor-pointer select-none">
              {allPageSelected ? "Desmarcar página" : "Selecionar página"}
            </label>
            {selectedIds.size > 0 && (
              <button onClick={clearSelection} className="ml-auto text-xs text-blue-600 hover:underline flex items-center gap-1">
                <CheckSquare className="w-3 h-3" /> Limpar seleção
              </button>
            )}
          </div>
        )}

        <div className="space-y-3">
          {isLoading ? (
            <div className="flex justify-center py-8">
              <Loader2 className="animate-spin w-8 h-8" />
            </div>
          ) : paginatedQuestions.length === 0 ? (
            <Card className="text-center py-8">
              <CardContent>
                <p className="text-gray-600 mb-4">Nenhuma questão encontrada</p>
                <Button onClick={() => navigate("/create-question")}>Criar Primeira Questão</Button>
              </CardContent>
            </Card>
          ) : (
            paginatedQuestions.map((question: any) => {
              const isSelected = selectedIds.has(question.id);
              return (
                <Card
                  key={question.id}
                  className={`transition-shadow ${isSelected ? "ring-2 ring-blue-500 bg-blue-50" : "hover:shadow-md"}`}
                >
                  <CardContent className="pt-4 pb-4">
                    <div className="flex gap-3 items-start">
                      <Checkbox
                        checked={isSelected}
                        onCheckedChange={() => toggleSelect(question.id)}
                        className="mt-1 shrink-0"
                      />
                      <div className="flex-1 min-w-0">
                        <h3 className="font-semibold line-clamp-2 text-sm">{question.statement}</h3>
                        <div className="flex gap-2 mt-2 flex-wrap">
                          <Badge variant="outline">{getTypeLabel(question.type)}</Badge>
                          {question.discipline && (() => {
                            const bg = disciplineColors[question.discipline];
                            return bg ? (
                              <Badge
                                variant="outline"
                                style={{ backgroundColor: bg + "22", borderColor: bg, color: bg }}
                              >
                                {question.discipline}
                              </Badge>
                            ) : (
                              <Badge variant="secondary">{question.discipline}</Badge>
                            );
                          })()}
                          {question.subject && (
                            <Badge variant="secondary" className="opacity-70">
                              {question.subject}
                            </Badge>
                          )}
                          {question.difficulty && (
                            <Badge variant={getDifficultyColor(question.difficulty)}>
                              {getDifficultyLabel(question.difficulty)}
                            </Badge>
                          )}
                        </div>
                      </div>

                      <div className="flex gap-1 shrink-0">
                        <Button
                          variant="ghost"
                          size="sm"
                          title="Duplicar questão"
                          onClick={() => handleDuplicate(question.id)}
                          disabled={duplicateMutation.isPending}
                        >
                          <Copy className="w-4 h-4 text-gray-500" />
                        </Button>
                        <Button
                          variant="ghost"
                          size="sm"
                          title="Editar questão"
                          onClick={() => navigate(`/questions/${question.id}/edit`)}
                        >
                          <Edit2 className="w-4 h-4" />
                        </Button>
                        <Button
                          variant="ghost"
                          size="sm"
                          title="Deletar questão"
                          onClick={() => setDeleteId(question.id)}
                        >
                          <Trash2 className="w-4 h-4 text-red-500" />
                        </Button>
                      </div>
                    </div>
                  </CardContent>
                </Card>
              );
            })
          )}
        </div>

        {/* Pagination */}
        {totalPages > 1 && (
          <div className="flex justify-between items-center mt-6">
            <div className="text-sm text-gray-600">
              Página {currentPage} de {totalPages} ({filteredQuestions.length} questões)
            </div>
            <div className="flex gap-2">
              <Button
                variant="outline"
                size="sm"
                disabled={currentPage === 1}
                onClick={() => setCurrentPage(Math.max(1, currentPage - 1))}
              >
                <ChevronLeft className="w-4 h-4" />
              </Button>
              {Array.from({ length: totalPages }, (_, i) => i + 1)
                .slice(Math.max(0, currentPage - 2), Math.min(totalPages, currentPage + 1))
                .map((page) => (
                  <Button
                    key={page}
                    variant={page === currentPage ? "default" : "outline"}
                    size="sm"
                    onClick={() => setCurrentPage(page)}
                  >
                    {page}
                  </Button>
                ))}
              <Button
                variant="outline"
                size="sm"
                disabled={currentPage === totalPages}
                onClick={() => setCurrentPage(Math.min(totalPages, currentPage + 1))}
              >
                <ChevronRight className="w-4 h-4" />
              </Button>
            </div>
          </div>
        )}
      </div>

      {/* Delete single */}
      <AlertDialog open={deleteId !== null} onOpenChange={(open) => !open && setDeleteId(null)}>
        <AlertDialogContent>
          <AlertDialogTitle>Confirmar Exclusão</AlertDialogTitle>
          <AlertDialogDescription>
            Tem certeza que deseja deletar esta questão? Esta ação não pode ser desfeita.
          </AlertDialogDescription>
          <div className="flex gap-3 justify-end">
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction onClick={handleDelete} className="bg-red-600 hover:bg-red-700">
              Deletar
            </AlertDialogAction>
          </div>
        </AlertDialogContent>
      </AlertDialog>

      {/* Bulk delete */}
      <AlertDialog open={showBulkDeleteConfirm} onOpenChange={setShowBulkDeleteConfirm}>
        <AlertDialogContent>
          <AlertDialogTitle>Confirmar Exclusão em Lote</AlertDialogTitle>
          <AlertDialogDescription>
            Tem certeza que deseja deletar {selectedIds.size} questão(ões)? Esta ação não pode ser desfeita.
          </AlertDialogDescription>
          <div className="flex gap-3 justify-end">
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleBulkDelete}
              className="bg-red-600 hover:bg-red-700"
              disabled={bulkDeleteMutation.isPending}
            >
              {bulkDeleteMutation.isPending ? (
                <Loader2 className="w-4 h-4 animate-spin" />
              ) : (
                `Deletar ${selectedIds.size} questão(ões)`
              )}
            </AlertDialogAction>
          </div>
        </AlertDialogContent>
      </AlertDialog>

      {/* Batch Edit */}
      <BatchEditDialog
        open={showBatchEdit}
        onOpenChange={setShowBatchEdit}
        selectedIds={Array.from(selectedIds)}
        disciplines={disciplineList}
        subjects={subjectList}
        onSuccess={() => { refetch(); clearSelection(); }}
      />

    </div>
  );
}
