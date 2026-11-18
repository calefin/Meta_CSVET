import { useState, useEffect } from "react";
import { nanoid } from "nanoid";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from "@/components/ui/select";
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter,
} from "@/components/ui/dialog";
import { Plus, X } from "lucide-react";
import type { QuestionBlock, QuestionBlockType, VerboseAnswer } from "@shared/_core/answerSheetTypes";

/* ─── helpers ─────────────────────────────────────────────────────────────── */

function LabelsInput({
  label, value, onChange, hint,
}: { label: string; value: string; onChange: (v: string) => void; hint?: string }) {
  const letters = value.toUpperCase().replace(/[^A-Z0-9]/g, "").slice(0, 10);
  return (
    <div className="space-y-1">
      <Label className="text-xs">{label}</Label>
      <Input
        value={letters}
        onChange={(e) => onChange(e.target.value)}
        className="h-8 text-sm font-mono uppercase"
        placeholder="ABCDE"
        maxLength={10}
      />
      <p className="text-[10px] text-muted-foreground">
        {hint ?? "Letras para rótulos, sem espaços (ex: ABCDE ou TF). Até 10 letras."}
      </p>
    </div>
  );
}

function CountInput({ value, onChange }: { value: number; onChange: (v: number) => void }) {
  return (
    <div className="space-y-1">
      <Label className="text-xs">Número de questões</Label>
      <Input
        type="number" min={1} max={100} value={value}
        onChange={(e) => onChange(Math.min(100, Math.max(1, Number(e.target.value))))}
        className="h-8 text-sm w-28"
      />
    </div>
  );
}

function LabelRow({ label, value, onChange, placeholder }: { label: string; value: string; onChange: (v: string) => void; placeholder?: string }) {
  return (
    <div className="space-y-1">
      <Label className="text-xs">{label}</Label>
      <Input value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} className="h-8 text-sm" />
    </div>
  );
}

/* ─── per-type forms ──────────────────────────────────────────────────────── */

function MultipleChoiceForm({ draft, set }: { draft: Partial<QuestionBlock>; set: (p: Partial<QuestionBlock>) => void }) {
  return (
    <div className="space-y-4">
      <div className="grid grid-cols-2 gap-3">
        <LabelRow label="Rótulo interno (app)" value={draft.internalLabel ?? ""} onChange={(v) => set({ internalLabel: v })} placeholder="ex: Múltipla Escolha" />
        <LabelRow label="Rótulo externo (gabarito)" value={draft.externalLabel ?? ""} onChange={(v) => set({ externalLabel: v })} placeholder="ex: MC" />
      </div>
      <CountInput value={draft.count ?? 10} onChange={(v) => set({ count: v })} />
      <LabelsInput
        label="Rótulos de resposta"
        value={(draft.choices ?? ["A","B","C","D","E"]).join("")}
        onChange={(v) => set({ choices: v.split("") })}
      />
    </div>
  );
}

function AlternationForm({ draft, set }: { draft: Partial<QuestionBlock>; set: (p: Partial<QuestionBlock>) => void }) {
  return (
    <div className="space-y-4">
      <div className="grid grid-cols-2 gap-3">
        <LabelRow label="Rótulo interno" value={draft.internalLabel ?? ""} onChange={(v) => set({ internalLabel: v })} />
        <LabelRow label="Rótulo externo" value={draft.externalLabel ?? ""} onChange={(v) => set({ externalLabel: v })} />
      </div>
      <CountInput value={draft.count ?? 10} onChange={(v) => set({ count: v })} />
      <div className="grid grid-cols-2 gap-3">
        <LabelsInput
          label="Rótulos — questões ímpares"
          value={(draft.oddChoices ?? ["A","B","C","D","E"]).join("")}
          onChange={(v) => set({ oddChoices: v.split(""), choices: v.split("") })}
          hint="Para questões 1, 3, 5..."
        />
        <LabelsInput
          label="Rótulos — questões pares"
          value={(draft.evenChoices ?? ["F","G","H","J","K"]).join("")}
          onChange={(v) => set({ evenChoices: v.split("") })}
          hint="Para questões 2, 4, 6..."
        />
      </div>
    </div>
  );
}

function VerboseForm({ draft, set }: { draft: Partial<QuestionBlock>; set: (p: Partial<QuestionBlock>) => void }) {
  const answers: VerboseAnswer[] = draft.verboseAnswers ?? [{ shortLabel: "", verboseLabel: "" }];

  function updateAnswer(i: number, patch: Partial<VerboseAnswer>) {
    const updated = answers.map((a, idx) => idx === i ? { ...a, ...patch } : a);
    set({ verboseAnswers: updated, choices: updated.map((a) => a.shortLabel).filter(Boolean) });
  }

  function addAnswer() {
    if (answers.length >= 10) return;
    set({ verboseAnswers: [...answers, { shortLabel: "", verboseLabel: "" }] });
  }

  function removeAnswer(i: number) {
    const updated = answers.filter((_, idx) => idx !== i);
    set({ verboseAnswers: updated, choices: updated.map((a) => a.shortLabel).filter(Boolean) });
  }

  return (
    <div className="space-y-4">
      <CountInput value={draft.count ?? 5} onChange={(v) => set({ count: v })} />
      <div className="space-y-1">
        <Label className="text-xs">Exibir rótulos curtos no formulário</Label>
        <RadioGroup
          value={draft.showShortLabels ? "show" : "hide"}
          onValueChange={(v) => set({ showShortLabels: v === "show" })}
          className="flex gap-4"
        >
          <div className="flex items-center gap-1.5"><RadioGroupItem value="show" id="show" /><Label htmlFor="show" className="text-sm cursor-pointer">Mostrar rótulos curtos</Label></div>
          <div className="flex items-center gap-1.5"><RadioGroupItem value="hide" id="hide" /><Label htmlFor="hide" className="text-sm cursor-pointer">Ocultar rótulos</Label></div>
        </RadioGroup>
      </div>

      <div className="space-y-2">
        <div className="flex items-center justify-between">
          <Label className="text-xs">Respostas detalhadas (até 10)</Label>
          <Button variant="outline" size="sm" className="h-6 text-xs gap-1" onClick={addAnswer} disabled={answers.length >= 10}>
            <Plus className="w-3 h-3" /> Adicionar
          </Button>
        </div>
        <div className="grid grid-cols-[80px_1fr_24px] gap-1.5 text-[10px] text-muted-foreground font-medium px-1">
          <span>Rótulo curto</span><span>Rótulo detalhado</span><span />
        </div>
        {answers.map((ans, i) => (
          <div key={i} className="grid grid-cols-[80px_1fr_24px] gap-1.5 items-center">
            <Input
              value={ans.shortLabel}
              onChange={(e) => updateAnswer(i, { shortLabel: e.target.value.toUpperCase().slice(0, 3) })}
              className="h-7 text-xs font-mono uppercase" placeholder="A"
            />
            <Input
              value={ans.verboseLabel}
              onChange={(e) => updateAnswer(i, { verboseLabel: e.target.value })}
              className="h-7 text-xs" placeholder="Verdadeiro"
            />
            <button onClick={() => removeAnswer(i)} className="text-muted-foreground hover:text-destructive">
              <X className="w-3.5 h-3.5" />
            </button>
          </div>
        ))}
        <p className="text-[10px] text-muted-foreground">
          Cada resposta deve ter um rótulo curto único para exibição no app e um rótulo detalhado.
        </p>
      </div>
    </div>
  );
}

function NumericForm({ draft, set }: { draft: Partial<QuestionBlock>; set: (p: Partial<QuestionBlock>) => void }) {
  const toggle = (key: keyof QuestionBlock) =>
    set({ [key]: !draft[key as keyof typeof draft] });

  return (
    <div className="space-y-4">
      <CountInput value={draft.count ?? 5} onChange={(v) => set({ count: v })} />
      <div className="space-y-1">
        <Label className="text-xs">Número de dígitos</Label>
        <Input
          type="number" min={1} max={12}
          value={draft.numDigits ?? 4}
          onChange={(e) => set({ numDigits: Number(e.target.value) })}
          className="h-8 text-sm w-24"
        />
      </div>
      <div className="space-y-2">
        <Label className="text-xs">Opções adicionais</Label>
        {([
          ["hasNegativeSign", "Tem sinal negativo (−)"],
          ["hasDecimalPoint", "Tem ponto decimal (.)"],
          ["hasFractionBar", "Tem barra de fração (/)"],
          ["hasScientificNotation", "Tem notação científica (e)"],
        ] as [keyof QuestionBlock, string][]).map(([key, label]) => (
          <div key={String(key)} className="flex items-center gap-2">
            <Switch
              checked={!!draft[key as keyof typeof draft]}
              onCheckedChange={() => toggle(key)}
            />
            <span className="text-sm">{label}</span>
          </div>
        ))}
      </div>
    </div>
  );
}

function ShortResponseForm({ draft, set }: { draft: Partial<QuestionBlock>; set: (p: Partial<QuestionBlock>) => void }) {
  return (
    <div className="space-y-4">
      <div className="p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800 space-y-1">
        <p className="font-semibold">⚠️ Apenas para provas em papel</p>
        <p>Caixas de resposta curta NÃO aparecem em testes online. Permite correção manual marcando os círculos correspondentes à nota antes de digitalizar. Certifique-se de inserir as letras na tela "Editar Gabarito" com o valor de pontos — por exemplo, use "C" para Correto.</p>
      </div>
      <CountInput value={draft.count ?? 5} onChange={(v) => set({ count: v })} />
      <LabelsInput
        label="Rótulos de resposta"
        value={(draft.choices ?? ["C","X"]).join("")}
        onChange={(v) => set({ choices: v.split("") })}
        hint="ex: CX para 'Correto' e 'Errado'. Mínimo 2 círculos por questão."
      />
      <div className="grid grid-cols-2 gap-3">
        <div className="space-y-1">
          <Label className="text-xs">Largura da caixa</Label>
          <RadioGroup
            value={draft.entryBoxWidth ?? "medium"}
            onValueChange={(v: any) => set({ entryBoxWidth: v })}
            className="space-y-1"
          >
            {[["small","Pequena"],["medium","Média"],["large","Grande"]].map(([v,l]) => (
              <div key={v} className="flex items-center gap-1.5">
                <RadioGroupItem value={v} id={`w-${v}`} />
                <Label htmlFor={`w-${v}`} className="text-sm cursor-pointer">{l}</Label>
              </div>
            ))}
          </RadioGroup>
        </div>
        <div className="space-y-1">
          <Label className="text-xs">Altura da caixa</Label>
          <Select
            value={String(draft.entryBoxHeight ?? 1)}
            onValueChange={(v) => set({ entryBoxHeight: Number(v) })}
          >
            <SelectTrigger className="h-8 w-28"><SelectValue /></SelectTrigger>
            <SelectContent>
              {[1,2,3,4,5,6,7].map((n) => (
                <SelectItem key={n} value={String(n)}>{n}x</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>
    </div>
  );
}

/* ─── Dialog titles & defaults ────────────────────────────────────────────── */

const DIALOG_TITLES: Record<QuestionBlockType, string> = {
  multiple_choice: "Múltipla Escolha",
  alternation: "Múltipla Escolha c/ Alternância",
  verbose: "Questão com Rótulo Detalhado",
  numeric: "Entrada Numérica",
  short_response: "Resposta Curta",
};

function defaultDraft(type: QuestionBlockType, startNumber: number): Partial<QuestionBlock> {
  const base = { id: nanoid(6), type, startNumber, count: 10 };
  switch (type) {
    case "multiple_choice":
      return { ...base, choices: ["A","B","C","D","E"], internalLabel: "", externalLabel: "" };
    case "alternation":
      return { ...base, choices: ["A","B","C","D","E"], oddChoices: ["A","B","C","D","E"], evenChoices: ["F","G","H","J","K"], internalLabel: "", externalLabel: "" };
    case "verbose":
      return { ...base, count: 5, choices: [], showShortLabels: true, verboseAnswers: [{ shortLabel: "V", verboseLabel: "Verdadeiro" }, { shortLabel: "F", verboseLabel: "Falso" }] };
    case "numeric":
      return { ...base, count: 5, choices: [], numDigits: 4, hasNegativeSign: false, hasDecimalPoint: false, hasFractionBar: false, hasScientificNotation: false };
    case "short_response":
      return { ...base, count: 5, choices: ["C","X"], entryBoxWidth: "medium", entryBoxHeight: 1 };
    default:
      return { ...base, choices: [] };
  }
}

/* ─── Main dialog component ───────────────────────────────────────────────── */

interface Props {
  open: boolean;
  type: QuestionBlockType;
  startNumber: number;
  initialBlock?: QuestionBlock | null; // for editing
  onConfirm: (block: QuestionBlock) => void;
  onClose: () => void;
}

export default function QuestionBlockDialog({ open, type, startNumber, initialBlock, onConfirm, onClose }: Props) {
  const [draft, setDraft] = useState<Partial<QuestionBlock>>(() =>
    initialBlock ? { ...initialBlock } : defaultDraft(type, startNumber)
  );

  useEffect(() => {
    setDraft(initialBlock ? { ...initialBlock } : defaultDraft(type, startNumber));
  }, [open, type, startNumber, initialBlock]);

  function patch(p: Partial<QuestionBlock>) {
    setDraft((d) => ({ ...d, ...p }));
  }

  function handleConfirm() {
    // verbose: `choices` only gets synced when the user actively edits the
    // verboseAnswers list — normalize it here so accepting the defaults
    // untouched doesn't save a block with no bubble labels.
    const choices =
      type === "verbose" && (!draft.choices || draft.choices.length === 0)
        ? (draft.verboseAnswers ?? []).map(a => a.shortLabel).filter(Boolean)
        : (draft.choices ?? []);

    const block = {
      ...draft,
      id: draft.id ?? nanoid(6),
      type: draft.type ?? type,
      count: draft.count ?? 10,
      startNumber: draft.startNumber ?? startNumber,
      choices,
    } as QuestionBlock;
    onConfirm(block);
  }

  const isValid = () => {
    if (!draft.count || draft.count < 1) return false;
    if (type === "multiple_choice" && (!draft.choices || draft.choices.length < 2)) return false;
    if (type === "alternation" && (!draft.oddChoices?.length || !draft.evenChoices?.length)) return false;
    if (type === "verbose" && (!draft.verboseAnswers || draft.verboseAnswers.length < 2)) return false;
    if (type === "short_response" && (!draft.choices || draft.choices.length < 2)) return false;
    return true;
  };

  return (
    <Dialog open={open} onOpenChange={(o) => { if (!o) onClose(); }}>
      <DialogContent className="max-w-lg max-h-[85vh] flex flex-col">
        <DialogHeader>
          <DialogTitle className="text-base">{DIALOG_TITLES[type]}</DialogTitle>
        </DialogHeader>

        <div className="flex-1 overflow-y-auto min-h-0 py-2">
          {type === "multiple_choice" && <MultipleChoiceForm draft={draft} set={patch} />}
          {type === "alternation" && <AlternationForm draft={draft} set={patch} />}
          {type === "verbose" && <VerboseForm draft={draft} set={patch} />}
          {type === "numeric" && <NumericForm draft={draft} set={patch} />}
          {type === "short_response" && <ShortResponseForm draft={draft} set={patch} />}
        </div>

        <DialogFooter className="gap-2 border-t pt-3">
          <Button variant="outline" size="sm" onClick={onClose}>Fechar</Button>
          <Button size="sm" disabled={!isValid()} onClick={handleConfirm}>
            {initialBlock ? "Salvar alterações" : "Adicionar questões"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
