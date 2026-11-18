import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Loader2 } from "lucide-react";
import { trpc } from "@/lib/trpc";
import { toast } from "sonner";

interface BatchEditDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  selectedIds: number[];
  disciplines: string[];
  subjects: string[];
  onSuccess: () => void;
}

const NONE = "__none__";

export function BatchEditDialog({
  open,
  onOpenChange,
  selectedIds,
  disciplines,
  subjects,
  onSuccess,
}: BatchEditDialogProps) {
  const [discipline, setDiscipline] = useState(NONE);
  const [customDiscipline, setCustomDiscipline] = useState("");
  const [subject, setSubject] = useState(NONE);
  const [customSubject, setCustomSubject] = useState("");
  const [difficulty, setDifficulty] = useState(NONE);
  const [tagsInput, setTagsInput] = useState("");

  const batchUpdate = trpc.questions.batchUpdate.useMutation();

  const hasChanges =
    discipline !== NONE ||
    subject !== NONE ||
    difficulty !== NONE ||
    tagsInput.trim() !== "";

  const handleSave = async () => {
    if (!hasChanges) {
      toast.info("Nenhuma alteração selecionada");
      return;
    }

    const finalDiscipline = discipline === "custom" ? customDiscipline.trim() : discipline !== NONE ? discipline : undefined;
    const finalSubject = subject === "custom" ? customSubject.trim() : subject !== NONE ? subject : undefined;

    try {
      const result = await batchUpdate.mutateAsync({
        ids: selectedIds,
        discipline: finalDiscipline,
        subject: finalSubject,
        difficulty: difficulty !== NONE ? (difficulty as any) : undefined,
        tags: tagsInput.trim() ? tagsInput.split(",").map((t) => t.trim()).filter(Boolean) : undefined,
      });

      toast.success(`${result.updatedCount} questão(ões) atualizada(s)`);
      onSuccess();
      onOpenChange(false);
      resetForm();
    } catch {
      toast.error("Erro ao atualizar questões");
    }
  };

  const resetForm = () => {
    setDiscipline(NONE);
    setCustomDiscipline("");
    setSubject(NONE);
    setCustomSubject("");
    setDifficulty(NONE);
    setTagsInput("");
  };

  return (
    <Dialog open={open} onOpenChange={(v) => { onOpenChange(v); if (!v) resetForm(); }}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Editar em Lote</DialogTitle>
          <DialogDescription>
            Edite os campos abaixo para aplicar a {selectedIds.length} questão(ões).
            Campos em branco não serão alterados.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4 py-2">
          <div className="space-y-1">
            <Label>Disciplina</Label>
            <Select value={discipline} onValueChange={setDiscipline}>
              <SelectTrigger>
                <SelectValue placeholder="Manter atual" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={NONE}>— Manter atual —</SelectItem>
                {disciplines.map((d) => (
                  <SelectItem key={d} value={d}>{d}</SelectItem>
                ))}
                <SelectItem value="custom">+ Nova disciplina</SelectItem>
              </SelectContent>
            </Select>
            {discipline === "custom" && (
              <Input
                placeholder="Digite a nova disciplina"
                value={customDiscipline}
                onChange={(e) => setCustomDiscipline(e.target.value)}
                className="mt-1"
              />
            )}
          </div>

          <div className="space-y-1">
            <Label>Assunto</Label>
            <Select value={subject} onValueChange={setSubject}>
              <SelectTrigger>
                <SelectValue placeholder="Manter atual" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={NONE}>— Manter atual —</SelectItem>
                {subjects.map((s) => (
                  <SelectItem key={s} value={s}>{s}</SelectItem>
                ))}
                <SelectItem value="custom">+ Novo assunto</SelectItem>
              </SelectContent>
            </Select>
            {subject === "custom" && (
              <Input
                placeholder="Digite o novo assunto"
                value={customSubject}
                onChange={(e) => setCustomSubject(e.target.value)}
                className="mt-1"
              />
            )}
          </div>

          <div className="space-y-1">
            <Label>Dificuldade</Label>
            <Select value={difficulty} onValueChange={setDifficulty}>
              <SelectTrigger>
                <SelectValue placeholder="Manter atual" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={NONE}>— Manter atual —</SelectItem>
                <SelectItem value="easy">Fácil</SelectItem>
                <SelectItem value="medium">Médio</SelectItem>
                <SelectItem value="hard">Difícil</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-1">
            <Label>Tags (substituir)</Label>
            <Input
              placeholder="Ex: vestibular, enem, 2024 (separadas por vírgula)"
              value={tagsInput}
              onChange={(e) => setTagsInput(e.target.value)}
            />
            <p className="text-xs text-gray-500">Deixe em branco para manter as tags atuais</p>
          </div>
        </div>

        <DialogFooter className="gap-2">
          <Button variant="outline" onClick={() => { onOpenChange(false); resetForm(); }}>
            Cancelar
          </Button>
          <Button onClick={handleSave} disabled={batchUpdate.isPending || !hasChanges}>
            {batchUpdate.isPending ? (
              <><Loader2 className="w-4 h-4 mr-2 animate-spin" /> Salvando...</>
            ) : (
              `Aplicar a ${selectedIds.length} questão(ões)`
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
