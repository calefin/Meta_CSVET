import { useState } from "react";
import { useAuth } from "@/_core/hooks/useAuth";
import { trpc } from "@/lib/trpc";
import { toast } from "sonner";
import { useLocation } from "wouter";
import BackButton from "@/components/BackButton";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Plus, Trash2, Edit, LayoutTemplate, Loader2, PlayCircle, X } from "lucide-react";

interface StructureItem {
  difficulty: "easy" | "medium" | "hard";
  count: number;
  subject?: string;
}

const DIFFICULTY_LABEL: Record<string, string> = {
  easy: "Fácil",
  medium: "Médio",
  hard: "Difícil",
};

const DIFFICULTY_COLOR: Record<string, string> = {
  easy: "text-green-600 bg-green-50 border-green-200",
  medium: "text-yellow-600 bg-yellow-50 border-yellow-200",
  hard: "text-red-600 bg-red-50 border-red-200",
};

const EMPTY_FORM = {
  name: "",
  description: "",
  discipline: "",
  shuffleMode: "all" as "all" | "questions" | "items" | "none",
  numberOfVersions: 1,
  totalPoints: "",
  structure: [] as StructureItem[],
};

export default function ExamTemplates() {
  const { user, loading: authLoading } = useAuth();
  const [, navigate] = useLocation();
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [form, setForm] = useState(EMPTY_FORM);
  const [newSlot, setNewSlot] = useState<StructureItem>({ difficulty: "easy", count: 1, subject: "" });

  const { data: templates = [], isLoading, refetch } = trpc.examTemplates.list.useQuery();
  const createMutation = trpc.examTemplates.create.useMutation();
  const updateMutation = trpc.examTemplates.update.useMutation();
  const deleteMutation = trpc.examTemplates.delete.useMutation();

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

  const openCreate = () => {
    setEditingId(null);
    setForm(EMPTY_FORM);
    setDialogOpen(true);
  };

  const openEdit = (tpl: any) => {
    setEditingId(tpl.id);
    setForm({
      name: tpl.name,
      description: tpl.description ?? "",
      discipline: tpl.discipline ?? "",
      shuffleMode: tpl.shuffleMode,
      numberOfVersions: tpl.numberOfVersions ?? 1,
      totalPoints: tpl.totalPoints ?? "",
      structure: tpl.structure ?? [],
    });
    setDialogOpen(true);
  };

  const addSlot = () => {
    if (newSlot.count < 1) return;
    setForm((f) => ({
      ...f,
      structure: [...f.structure, { ...newSlot, subject: newSlot.subject || undefined }],
    }));
    setNewSlot({ difficulty: "easy", count: 1, subject: "" });
  };

  const removeSlot = (i: number) => {
    setForm((f) => ({ ...f, structure: f.structure.filter((_, idx) => idx !== i) }));
  };

  const handleSave = async () => {
    if (!form.name.trim()) { toast.error("Nome é obrigatório"); return; }
    if (form.structure.length === 0) { toast.error("Adicione ao menos um bloco de questões"); return; }
    try {
      const payload = {
        name: form.name,
        description: form.description || undefined,
        discipline: form.discipline || undefined,
        shuffleMode: form.shuffleMode,
        numberOfVersions: form.numberOfVersions,
        totalPoints: form.totalPoints || undefined,
        structure: form.structure,
      };
      if (editingId !== null) {
        await updateMutation.mutateAsync({ id: editingId, ...payload });
        toast.success("Modelo atualizado");
      } else {
        await createMutation.mutateAsync(payload);
        toast.success("Modelo criado");
      }
      setDialogOpen(false);
      refetch();
    } catch {
      toast.error("Erro ao salvar modelo");
    }
  };

  const handleDelete = async () => {
    if (deleteId === null) return;
    try {
      await deleteMutation.mutateAsync({ id: deleteId });
      toast.success("Modelo deletado");
      refetch();
      setDeleteId(null);
    } catch {
      toast.error("Erro ao deletar");
    }
  };

  const handleApply = (tpl: any) => {
    const params = new URLSearchParams({
      templateId: String(tpl.id),
      templateName: tpl.name,
    });
    navigate(`/create-exam?${params.toString()}`);
  };

  const totalQuestions = (structure: StructureItem[]) =>
    structure.reduce((s, i) => s + i.count, 0);

  return (
    <div className="space-y-4 max-w-4xl">
      <BackButton />
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">Modelos de Prova</h1>
          <p className="text-muted-foreground text-sm">
            Salve estruturas reutilizáveis para criar provas rapidamente
          </p>
        </div>
        <Button onClick={openCreate} size="sm">
          <Plus className="w-4 h-4 mr-1" /> Novo Modelo
        </Button>
      </div>

      {isLoading ? (
        <div className="flex justify-center py-10">
          <Loader2 className="animate-spin w-6 h-6 text-blue-600" />
        </div>
      ) : templates.length === 0 ? (
        <Card className="border">
          <CardContent className="py-16 text-center">
            <LayoutTemplate className="w-10 h-10 text-slate-300 mx-auto mb-3" />
            <p className="font-medium text-slate-600">Nenhum modelo criado</p>
            <p className="text-sm text-muted-foreground mt-1">
              Crie um modelo com a distribuição de questões que você usa com frequência.
            </p>
            <Button onClick={openCreate} className="mt-4" size="sm">
              <Plus className="w-4 h-4 mr-1" /> Criar Primeiro Modelo
            </Button>
          </CardContent>
        </Card>
      ) : (
        <div className="grid sm:grid-cols-2 gap-4">
          {templates.map((tpl: any) => (
            <Card key={tpl.id} className="border shadow-sm hover:shadow-md transition-shadow">
              <CardHeader className="pb-2">
                <div className="flex items-start justify-between gap-2">
                  <CardTitle className="text-base leading-tight">{tpl.name}</CardTitle>
                  <div className="flex gap-1">
                    <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => openEdit(tpl)}>
                      <Edit className="w-3.5 h-3.5" />
                    </Button>
                    <Button variant="ghost" size="icon" className="h-7 w-7 text-destructive" onClick={() => setDeleteId(tpl.id)}>
                      <Trash2 className="w-3.5 h-3.5" />
                    </Button>
                  </div>
                </div>
                {tpl.description && <p className="text-xs text-muted-foreground">{tpl.description}</p>}
              </CardHeader>
              <CardContent className="space-y-3">
                <div className="flex flex-wrap gap-1.5">
                  {tpl.discipline && <Badge variant="outline" className="text-xs">{tpl.discipline}</Badge>}
                  <Badge variant="secondary" className="text-xs">{totalQuestions(tpl.structure)} questões</Badge>
                  <Badge variant="secondary" className="text-xs">{tpl.numberOfVersions}x versão</Badge>
                </div>

                <div className="flex flex-wrap gap-1">
                  {(tpl.structure as StructureItem[]).map((slot, i) => (
                    <span
                      key={i}
                      className={`text-xs px-2 py-0.5 rounded-full border font-medium ${DIFFICULTY_COLOR[slot.difficulty]}`}
                    >
                      {slot.count}× {DIFFICULTY_LABEL[slot.difficulty]}
                      {slot.subject ? ` (${slot.subject})` : ""}
                    </span>
                  ))}
                </div>

                <Button
                  onClick={() => handleApply(tpl)}
                  size="sm"
                  className="w-full mt-1"
                  variant="outline"
                >
                  <PlayCircle className="w-4 h-4 mr-1.5" /> Criar Prova com Este Modelo
                </Button>
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      {/* Create / Edit Dialog */}
      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="max-w-lg max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{editingId !== null ? "Editar Modelo" : "Novo Modelo"}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div>
              <Label>Nome *</Label>
              <Input
                value={form.name}
                onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                placeholder="Ex: Prova Mensal Exatas"
              />
            </div>
            <div>
              <Label>Descrição</Label>
              <Input
                value={form.description}
                onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
                placeholder="Opcional"
              />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <Label>Disciplina padrão</Label>
                <Input
                  value={form.discipline}
                  onChange={(e) => setForm((f) => ({ ...f, discipline: e.target.value }))}
                  placeholder="Ex: Matemática"
                />
              </div>
              <div>
                <Label>Pontuação total</Label>
                <Input
                  type="number"
                  value={form.totalPoints}
                  onChange={(e) => setForm((f) => ({ ...f, totalPoints: e.target.value }))}
                  placeholder="Ex: 10"
                />
              </div>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <Label>Embaralhamento</Label>
                <Select
                  value={form.shuffleMode}
                  onValueChange={(v) => setForm((f) => ({ ...f, shuffleMode: v as any }))}
                >
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">Questões e alternativas</SelectItem>
                    <SelectItem value="questions">Apenas questões</SelectItem>
                    <SelectItem value="items">Apenas alternativas</SelectItem>
                    <SelectItem value="none">Nenhum</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div>
                <Label>Versões</Label>
                <Input
                  type="number"
                  min={1}
                  max={50}
                  value={form.numberOfVersions}
                  onChange={(e) => setForm((f) => ({ ...f, numberOfVersions: parseInt(e.target.value) || 1 }))}
                />
              </div>
            </div>

            {/* Structure builder */}
            <div>
              <Label className="mb-2 block">Distribuição de questões *</Label>
              {form.structure.length > 0 && (
                <div className="flex flex-wrap gap-1.5 mb-3">
                  {form.structure.map((slot, i) => (
                    <span
                      key={i}
                      className={`flex items-center gap-1 text-xs px-2 py-1 rounded-full border font-medium ${DIFFICULTY_COLOR[slot.difficulty]}`}
                    >
                      {slot.count}× {DIFFICULTY_LABEL[slot.difficulty]}
                      {slot.subject ? ` (${slot.subject})` : ""}
                      <button onClick={() => removeSlot(i)} className="ml-0.5 hover:opacity-70">
                        <X className="w-3 h-3" />
                      </button>
                    </span>
                  ))}
                </div>
              )}
              <div className="flex gap-2 items-end">
                <div className="flex-1">
                  <Select
                    value={newSlot.difficulty}
                    onValueChange={(v) => setNewSlot((s) => ({ ...s, difficulty: v as any }))}
                  >
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="easy">Fácil</SelectItem>
                      <SelectItem value="medium">Médio</SelectItem>
                      <SelectItem value="hard">Difícil</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div className="w-20">
                  <Input
                    type="number"
                    min={1}
                    value={newSlot.count}
                    onChange={(e) => setNewSlot((s) => ({ ...s, count: parseInt(e.target.value) || 1 }))}
                  />
                </div>
                <div className="flex-1">
                  <Input
                    placeholder="Assunto (opcional)"
                    value={newSlot.subject}
                    onChange={(e) => setNewSlot((s) => ({ ...s, subject: e.target.value }))}
                  />
                </div>
                <Button type="button" size="icon" onClick={addSlot} variant="outline">
                  <Plus className="w-4 h-4" />
                </Button>
              </div>
              <p className="text-xs text-muted-foreground mt-1">
                Total: {totalQuestions(form.structure)} questão(ões)
              </p>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDialogOpen(false)}>Cancelar</Button>
            <Button onClick={handleSave} disabled={createMutation.isPending || updateMutation.isPending}>
              {(createMutation.isPending || updateMutation.isPending) && <Loader2 className="w-4 h-4 mr-1 animate-spin" />}
              Salvar
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Delete confirmation */}
      <AlertDialog open={deleteId !== null} onOpenChange={(o) => !o && setDeleteId(null)}>
        <AlertDialogContent>
          <AlertDialogTitle>Deletar modelo?</AlertDialogTitle>
          <AlertDialogDescription>Esta ação não pode ser desfeita.</AlertDialogDescription>
          <div className="flex gap-2 justify-end mt-4">
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction onClick={handleDelete} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
              Deletar
            </AlertDialogAction>
          </div>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
