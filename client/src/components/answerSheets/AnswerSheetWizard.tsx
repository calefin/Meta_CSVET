import { useState, useCallback } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Badge } from "@/components/ui/badge";
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from "@/components/ui/select";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  ChevronLeft, ChevronRight, Plus, Trash2, CheckCircle,
  ChevronUp, ChevronDown, Pencil, ListChecks, Calculator,
  AlignLeft, Hash, ToggleLeft,
} from "lucide-react";
import { nanoid } from "nanoid";
import type {
  AnswerSheetConfig, HeaderField, QuestionBlock, QuestionBlockType,
} from "@shared/_core/answerSheetTypes";
import { DEFAULT_CONFIG } from "@shared/_core/answerSheetTypes";
import AnswerSheetPreview from "./AnswerSheetPreview";
import QuestionBlockDialog from "./QuestionBlockDialog";

interface Props {
  initialName?: string;
  initialConfig?: AnswerSheetConfig;
  onPublish: (name: string, config: AnswerSheetConfig) => void;
  onCancel: () => void;
}

const STEPS = ["Nome", "Cabeçalho", "Seções", "Questões", "Publicar"];

const BLOCK_META: Record<QuestionBlockType, { label: string; icon: React.FC<any>; desc: string }> = {
  multiple_choice:  { label: "Múltipla Escolha",            icon: ListChecks,   desc: "Bolhas A-E por questão" },
  alternation:      { label: "MC com Alternância",           icon: ToggleLeft,   desc: "Rótulos alternados ímpar/par" },
  verbose:          { label: "Rótulo Detalhado",             icon: AlignLeft,    desc: "Respostas com texto longo" },
  numeric:          { label: "Entrada Numérica",             icon: Calculator,   desc: "Dígitos e símbolos (−  .  /)" },
  short_response:   { label: "Resposta Curta",               icon: Hash,         desc: "Caixa para correção manual" },
};

function blockSummary(b: QuestionBlock): string {
  switch (b.type) {
    case "multiple_choice":  return `${b.choices.join("")} · ${b.count}q`;
    case "alternation":      return `${(b.oddChoices ?? b.choices).join("")}/${(b.evenChoices ?? []).join("")} · ${b.count}q`;
    case "verbose":          return `${b.verboseAnswers?.length ?? 0} opções · ${b.count}q`;
    case "numeric":          return `${b.numDigits ?? 4} dígitos · ${b.count}q`;
    case "short_response":   return `${b.choices.join("")} · ${b.count}q`;
    default: return `${b.count}q`;
  }
}

export default function AnswerSheetWizard({ initialName = "", initialConfig, onPublish, onCancel }: Props) {
  const [step, setStep] = useState(0);
  const [name, setName] = useState(initialName);
  const [config, setConfig] = useState<AnswerSheetConfig>(
    initialConfig ? JSON.parse(JSON.stringify(initialConfig)) : JSON.parse(JSON.stringify(DEFAULT_CONFIG))
  );

  // Dialog state
  const [dialogType, setDialogType] = useState<QuestionBlockType | null>(null);
  const [editingBlock, setEditingBlock] = useState<QuestionBlock | null>(null);

  /* ── helpers ────────────────────────────────────────────────────────────── */
  const patchHeader = useCallback((patch: Partial<AnswerSheetConfig["header"]>) => {
    setConfig((c) => ({ ...c, header: { ...c.header, ...patch } }));
  }, []);

  const patchField = useCallback((id: string, patch: Partial<HeaderField>) => {
    setConfig((c) => ({
      ...c,
      header: { ...c.header, fields: c.header.fields.map((f) => (f.id === id ? { ...f, ...patch } : f)) },
    }));
  }, []);

  // Compute next question startNumber
  const nextStart = config.questions.length === 0
    ? 1
    : config.questions[config.questions.length - 1].startNumber + config.questions[config.questions.length - 1].count;

  function openAdd(type: QuestionBlockType) {
    setEditingBlock(null);
    setDialogType(type);
  }

  function openEdit(block: QuestionBlock) {
    setEditingBlock(block);
    setDialogType(block.type);
  }

  function handleBlockConfirm(block: QuestionBlock) {
    setConfig((c) => {
      if (editingBlock) {
        return { ...c, questions: c.questions.map((b) => b.id === editingBlock.id ? block : b) };
      }
      return { ...c, questions: [...c.questions, { ...block, startNumber: nextStart }] };
    });
    setDialogType(null);
    setEditingBlock(null);
  }

  function removeBlock(id: string) {
    setConfig((c) => ({ ...c, questions: c.questions.filter((b) => b.id !== id) }));
  }

  function moveBlock(id: string, dir: -1 | 1) {
    setConfig((c) => {
      const idx = c.questions.findIndex((b) => b.id === id);
      if (idx < 0) return c;
      const next = idx + dir;
      if (next < 0 || next >= c.questions.length) return c;
      const arr = [...c.questions];
      [arr[idx], arr[next]] = [arr[next], arr[idx]];
      return { ...c, questions: arr };
    });
  }

  const totalQuestions = config.questions.reduce((acc, b) => acc + b.count, 0);

  /* ── step 1 — name ──────────────────────────────────────────────────────── */
  const stepName = (
    <div className="space-y-4">
      <p className="text-sm text-muted-foreground">O nome deve ser único e descritivo.</p>
      <div className="space-y-1">
        <Label>Nome do Gabarito</Label>
        <Input value={name} onChange={(e) => setName(e.target.value)}
          placeholder="Ex: Turma 2024-1 — 50 Questões" autoFocus />
      </div>
    </div>
  );

  /* ── step 2 — header fields ─────────────────────────────────────────────── */
  const stepHeader = (
    <div className="space-y-3">
      <p className="text-sm text-muted-foreground">Configure os campos do cabeçalho que aparecerão no gabarito.</p>
      {config.header.fields.map((field) => (
        <div key={field.id} className="grid grid-cols-[auto_1fr_1fr_auto] gap-2 items-center p-2 border rounded-lg">
          <Switch checked={field.enabled} onCheckedChange={(v) => patchField(field.id, { enabled: v })} />
          <div className="space-y-0.5">
            <p className="text-[10px] font-medium text-muted-foreground uppercase">{field.defaultLabel}</p>
            <Input value={field.displayLabel} onChange={(e) => patchField(field.id, { displayLabel: e.target.value })}
              placeholder={field.defaultLabel} className="h-7 text-sm" disabled={!field.enabled} />
          </div>
          <Select value={field.width} onValueChange={(v: any) => patchField(field.id, { width: v })} disabled={!field.enabled}>
            <SelectTrigger className="h-7 text-xs"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="small">Pequeno</SelectItem>
              <SelectItem value="medium">Médio</SelectItem>
              <SelectItem value="large">Grande</SelectItem>
            </SelectContent>
          </Select>
        </div>
      ))}
    </div>
  );

  /* ── step 3 — sections (student ID + key version) ───────────────────────── */
  const stepSections = (
    <div className="space-y-5">
      <Card>
        <CardHeader className="pb-2">
          <div className="flex items-center justify-between">
            <CardTitle className="text-sm">Seção de Matrícula (Student ID)</CardTitle>
            <Switch checked={config.header.includeStudentId} onCheckedChange={(v) => patchHeader({ includeStudentId: v })} />
          </div>
        </CardHeader>
        {config.header.includeStudentId && (
          <CardContent className="space-y-3 pt-0">
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1">
                <Label className="text-xs">Nº de dígitos</Label>
                <Select value={String(config.header.studentIdDigits)} onValueChange={(v) => patchHeader({ studentIdDigits: Number(v) })}>
                  <SelectTrigger className="h-8"><SelectValue /></SelectTrigger>
                  <SelectContent>{[4,5,6,7,8,9,10].map((n) => <SelectItem key={n} value={String(n)}>{n} dígitos</SelectItem>)}</SelectContent>
                </Select>
              </div>
              <div className="space-y-1">
                <Label className="text-xs">Rótulo</Label>
                <Input value={config.header.studentIdLabel} onChange={(e) => patchHeader({ studentIdLabel: e.target.value })} className="h-8 text-sm" />
              </div>
            </div>
          </CardContent>
        )}
      </Card>

      <Card>
        <CardHeader className="pb-2">
          <div className="flex items-center justify-between">
            <CardTitle className="text-sm">Seção de Versão (Key)</CardTitle>
            <Switch checked={config.header.includeKeyVersion} onCheckedChange={(v) => patchHeader({ includeKeyVersion: v })} />
          </div>
        </CardHeader>
        {config.header.includeKeyVersion && (
          <CardContent className="space-y-3 pt-0">
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1">
                <Label className="text-xs">Letras/Números (ex: ABCD)</Label>
                <Input value={config.header.keyVersionLetters}
                  onChange={(e) => patchHeader({ keyVersionLetters: e.target.value.toUpperCase().slice(0, 8) })}
                  className="h-8 text-sm font-mono" placeholder="ABCD" />
              </div>
              <div className="space-y-1">
                <Label className="text-xs">Rótulo</Label>
                <Input value={config.header.keyVersionLabel} onChange={(e) => patchHeader({ keyVersionLabel: e.target.value })} className="h-8 text-sm" />
              </div>
            </div>
          </CardContent>
        )}
      </Card>
    </div>
  );

  /* ── step 4 — questions ─────────────────────────────────────────────────── */
  const stepQuestions = (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between">
        <p className="text-sm text-muted-foreground">
          {totalQuestions === 0 ? "Nenhuma questão definida ainda." : `${totalQuestions} questão(ões) definida(s).`}
        </p>
        {totalQuestions > 0 && <Badge variant="secondary">{totalQuestions} questões</Badge>}
      </div>

      {/* Existing blocks — reorderable list */}
      {config.questions.length > 0 && (
        <div className="space-y-2">
          <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wide">Blocos adicionados</p>
          {config.questions.map((block, idx) => {
            const meta = BLOCK_META[block.type];
            return (
              <div key={block.id} className="flex items-center gap-2 border rounded-lg px-3 py-2 bg-muted/20">
                {/* Reorder arrows */}
                <div className="flex flex-col shrink-0">
                  <button disabled={idx === 0} onClick={() => moveBlock(block.id, -1)}
                    className="text-muted-foreground hover:text-foreground disabled:opacity-30">
                    <ChevronUp className="w-3.5 h-3.5" />
                  </button>
                  <button disabled={idx === config.questions.length - 1} onClick={() => moveBlock(block.id, 1)}
                    className="text-muted-foreground hover:text-foreground disabled:opacity-30">
                    <ChevronDown className="w-3.5 h-3.5" />
                  </button>
                </div>
                {/* Icon */}
                <div className="p-1.5 bg-background border rounded shrink-0">
                  <meta.icon className="w-3.5 h-3.5 text-primary" />
                </div>
                {/* Info */}
                <div className="flex-1 min-w-0">
                  <p className="text-xs font-medium truncate">{meta.label}</p>
                  <p className="text-[10px] text-muted-foreground">
                    Q{block.startNumber}–{block.startNumber + block.count - 1} · {blockSummary(block)}
                  </p>
                </div>
                {/* Actions */}
                <Button variant="ghost" size="icon" className="h-7 w-7 shrink-0" onClick={() => openEdit(block)}>
                  <Pencil className="w-3.5 h-3.5" />
                </Button>
                <Button variant="ghost" size="icon" className="h-7 w-7 shrink-0 text-destructive hover:text-destructive"
                  onClick={() => removeBlock(block.id)}>
                  <Trash2 className="w-3.5 h-3.5" />
                </Button>
              </div>
            );
          })}
        </div>
      )}

      {/* Add block buttons */}
      <div className="space-y-1.5">
        <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wide">Adicionar bloco de questões</p>
        <div className="grid sm:grid-cols-2 gap-1.5">
          {(Object.entries(BLOCK_META) as [QuestionBlockType, typeof BLOCK_META[QuestionBlockType]][]).map(([type, meta]) => (
            <button key={type}
              onClick={() => openAdd(type)}
              className="flex items-start gap-2.5 p-2.5 border rounded-lg hover:border-primary/50 hover:bg-primary/5 transition-colors text-left group"
            >
              <div className="p-1.5 bg-muted rounded shrink-0 group-hover:bg-primary/10">
                <meta.icon className="w-4 h-4 text-muted-foreground group-hover:text-primary" />
              </div>
              <div>
                <p className="text-xs font-medium leading-tight">{meta.label}</p>
                <p className="text-[10px] text-muted-foreground mt-0.5">{meta.desc}</p>
              </div>
              <Plus className="w-3.5 h-3.5 text-muted-foreground ml-auto mt-0.5 shrink-0" />
            </button>
          ))}
        </div>
      </div>
    </div>
  );

  /* ── step 5 — publish ───────────────────────────────────────────────────── */
  const stepPublish = (
    <div className="space-y-4">
      <div className="p-4 bg-muted/40 rounded-lg space-y-2">
        <p className="font-semibold text-sm">Resumo</p>
        <div className="text-sm space-y-1 text-muted-foreground">
          <p>Nome: <span className="text-foreground font-medium">{name || "—"}</span></p>
          <p>Campos de cabeçalho: <span className="text-foreground">{config.header.fields.filter((f) => f.enabled).length}</span></p>
          <p>Matrícula: <span className="text-foreground">{config.header.includeStudentId ? `${config.header.studentIdDigits} dígitos` : "Não"}</span></p>
          <p>Versão/Key: <span className="text-foreground">{config.header.includeKeyVersion ? config.header.keyVersionLetters : "Não"}</span></p>
          <p>Blocos de questões: <span className="text-foreground">{config.questions.length}</span></p>
          <p>Total de questões: <span className="text-foreground font-medium">{totalQuestions}</span></p>
        </div>
      </div>
      <div className="flex items-start gap-2 text-sm text-amber-700 bg-amber-50 border border-amber-200 p-3 rounded-lg">
        <span className="shrink-0">⚠️</span>
        <p>Gabaritos personalizados são exclusivos desta conta. Não compartilhe cópias físicas sem também compartilhar a definição do formulário.</p>
      </div>
    </div>
  );

  const stepContent = [stepName, stepHeader, stepSections, stepQuestions, stepPublish];
  const canNext = step === 0 ? !!name.trim() : true;

  return (
    <div className="flex gap-6 h-full min-h-0">
      {/* ── Wizard form ─────────────────────────────────────────────────── */}
      <div className="flex-1 min-w-0 flex flex-col gap-0">
        {/* Progress */}
        <div className="flex items-center gap-1 mb-5 shrink-0">
          {STEPS.map((s, i) => (
            <div key={i} className="flex items-center gap-1">
              <div className={`flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold transition-colors ${
                i < step ? "bg-primary text-primary-foreground" :
                i === step ? "bg-primary text-primary-foreground ring-2 ring-primary/30" :
                "bg-muted text-muted-foreground"
              }`}>
                {i < step ? <CheckCircle className="w-3.5 h-3.5" /> : i + 1}
              </div>
              {i < STEPS.length - 1 && <div className={`h-0.5 w-5 ${i < step ? "bg-primary" : "bg-muted"}`} />}
            </div>
          ))}
          <span className="ml-2 text-xs text-muted-foreground">
            <strong>{STEPS[step]}</strong>
          </span>
        </div>

        {/* Content */}
        <div className="flex-1 overflow-y-auto min-h-0 pr-1">
          {stepContent[step]}
        </div>

        {/* Navigation */}
        <div className="flex justify-between pt-4 mt-2 border-t shrink-0">
          <Button variant="outline" size="sm" onClick={step === 0 ? onCancel : () => setStep((s) => s - 1)}>
            <ChevronLeft className="w-4 h-4 mr-1" />{step === 0 ? "Cancelar" : "Anterior"}
          </Button>
          {step < STEPS.length - 1 ? (
            <Button size="sm" disabled={!canNext} onClick={() => setStep((s) => s + 1)}>
              Próximo <ChevronRight className="w-4 h-4 ml-1" />
            </Button>
          ) : (
            <Button size="sm" disabled={!name.trim()} onClick={() => onPublish(name, config)} className="gap-2">
              <CheckCircle className="w-4 h-4" /> Publicar
            </Button>
          )}
        </div>
      </div>

      {/* ── Live preview ─────────────────────────────────────────────────── */}
      <div className="hidden lg:flex flex-col items-center gap-2 shrink-0">
        <p className="text-xs text-muted-foreground font-medium">Pré-visualização</p>
        {/* auto-fill qrPayload when possible: use template name as Template_ID and sample student id if studentId enabled */}
        {(() => {
          const templateId = name ? name.replace(/\s+/g, '_').toUpperCase() : null;
          const sampleUserId = config.header.includeStudentId ? String(0).repeat(config.header.studentIdDigits || 0) : null;
          // build consistent payload including optional fields
          // import dynamically to avoid top-level side-effects in the component module
          // (webpack/vite will still bundle it normally)
          // eslint-disable-next-line @typescript-eslint/no-var-requires
          const { buildQrPayload } = require("@/lib/buildQrPayload");
          const qrPayload = templateId ? buildQrPayload({ templateId, userId: sampleUserId }) : undefined;
          return <AnswerSheetPreview name={name} config={config} scale={0.42} qrPayload={qrPayload} />;
        })()}
        {totalQuestions > 0 && (
          <p className="text-[10px] text-muted-foreground">{totalQuestions} questão(ões)</p>
        )}
      </div>

      {/* ── Question block dialog ─────────────────────────────────────────── */}
      {dialogType && (
        <QuestionBlockDialog
          open={!!dialogType}
          type={dialogType}
          startNumber={editingBlock ? editingBlock.startNumber : nextStart}
          initialBlock={editingBlock}
          onConfirm={handleBlockConfirm}
          onClose={() => { setDialogType(null); setEditingBlock(null); }}
        />
      )}
    </div>
  );
}
