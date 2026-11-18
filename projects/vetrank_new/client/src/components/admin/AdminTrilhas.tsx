import { useState, useRef } from "react";
import { trpc } from "@/lib/trpc";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Badge } from "@/components/ui/badge";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { toast } from "sonner";
import {
  Route, Plus, Edit, Trash2, ChevronRight, Loader2,
  BookOpen, Users, Clock, RefreshCw, Upload, Download,
  AlertCircle, CheckCircle2, Crown, ShoppingBag, ChevronDown, ChevronUp, Copy,
} from "lucide-react";

const DIFFICULTY_LABELS: Record<string, string> = {
  easy: "Fácil", medium: "Médio", hard: "Difícil", mixed: "Misto",
};

// ── Exemplo canônico do JSON para geração via IA ───────────────────────────────
const JSON_EXAMPLE = JSON.stringify({
  title: "Fisiologia Veterinária — Parte I",
  description: "Cobertura completa dos sistemas cardiovascular, respiratório e renal. Indicada para alunos do 3º ao 5º semestre.",
  disciplineId: 2,
  accessType: "subscription",
  totalHours: 40,
  passingScore: 70,
  finalExamQuestions: 30,
  finalExamTimeSeconds: 3600,
  modules: [
    {
      title: "Sistema Cardiovascular",
      summary: "Débito cardíaco, pressão arterial, ciclo cardíaco, ECG veterinário.",
      difficulty: "medium",
      questionCount: 40,
      minPassRate: 70,
      questionFilter: { subjectIds: [30007], difficulty: "medium" },
    },
    {
      title: "Sistema Respiratório",
      summary: "Ventilação, troca gasosa, complacência pulmonar, regulação do pH.",
      difficulty: "medium",
      questionCount: 30,
      minPassRate: 70,
      questionFilter: { subjectIds: [30007] },
    },
    {
      title: "Sistema Renal e Homeostase",
      summary: "Filtração glomerular, reabsorção tubular, equilíbrio ácido-base.",
      difficulty: "hard",
      questionCount: 35,
      minPassRate: 70,
      questionFilter: { subjectIds: [30007] },
    },
  ],
}, null, 2);

const JSON_EXAMPLE_PURCHASE = JSON.stringify({
  title: "Clínica de Pequenos Animais — Intensivão",
  description: "Curso comprado individualmente. Aborda as principais emergências e casos clínicos de cães e gatos.",
  disciplineId: 5,
  accessType: "purchase",
  price: 49.90,
  totalHours: 20,
  passingScore: 70,
  finalExamQuestions: 20,
  finalExamTimeSeconds: 2400,
  modules: [
    {
      title: "Emergências Respiratórias",
      summary: "Dispneia, pneumotórax, efusão pleural.",
      difficulty: "hard",
      questionCount: 25,
      minPassRate: 70,
      questionFilter: { subjectIds: [30022], difficulty: "hard" },
    },
  ],
}, null, 2);

const AI_PROMPT = `Você é especialista em educação em Medicina Veterinária.
Gere uma trilha de aprendizado no formato JSON abaixo para a disciplina: [NOME DA DISCIPLINA].

REGRAS:
- "disciplineId": use o ID numérico da grande área do VetRank.
- "accessType": "subscription" (inclusa no plano) ou "purchase" (comprada à parte, inclua "price" em BRL).
- Cada módulo deve cobrir um tema coerente e sequencial (do básico ao avançado).
- "questionFilter.subjectIds": array com os IDs dos assuntos do banco VetRank relevantes ao módulo.
- "questionCount": 20–60 questões por módulo.
- "minPassRate": 60–80%.
- "difficulty": "easy" | "medium" | "hard" | "mixed".
- Mínimo 4 módulos, máximo 12.

Retorne APENAS o JSON válido, sem markdown, sem comentários.

Formato esperado:
${JSON_EXAMPLE}`;

// ── Import dialog ──────────────────────────────────────────────────────────────
function ImportTrailDialog({ onClose, onImported }: { onClose: () => void; onImported: () => void }) {
  const [json, setJson] = useState("");
  const [showDocs, setShowDocs] = useState(true);
  const [showPurchaseExample, setShowPurchaseExample] = useState(false);
  const [preview, setPreview] = useState<any>(null);
  const [parseError, setParseError] = useState("");
  const fileRef = useRef<HTMLInputElement>(null);

  const mutation = trpc.admin.importTrail.useMutation({
    onSuccess: (data) => {
      toast.success(`Trilha importada! ${data.moduleCount} módulo(s) criado(s).`);
      onImported();
      onClose();
    },
    onError: (e) => toast.error(e.message),
  });

  const validate = (raw: string) => {
    setParseError("");
    setPreview(null);
    if (!raw.trim()) return;
    try {
      const parsed = JSON.parse(raw);
      if (!parsed.title) throw new Error("Campo 'title' obrigatório.");
      if (!parsed.disciplineId) throw new Error("Campo 'disciplineId' obrigatório.");
      if (!Array.isArray(parsed.modules) || parsed.modules.length === 0)
        throw new Error("Campo 'modules' deve ser um array com ao menos 1 item.");
      setPreview(parsed);
    } catch (e: any) {
      setParseError(e.message || "JSON inválido.");
    }
  };

  const handleChange = (v: string) => {
    setJson(v);
    validate(v);
  };

  const handleFile = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (ev) => {
      const text = ev.target?.result as string;
      setJson(text);
      validate(text);
    };
    reader.readAsText(file);
  };

  const handleImport = () => {
    if (!preview) return;
    mutation.mutate(preview);
  };

  const copyPrompt = () => {
    navigator.clipboard.writeText(AI_PROMPT);
    toast.success("Prompt copiado!");
  };

  return (
    <div className="space-y-4 max-h-[80vh] overflow-y-auto pr-1">

      {/* Docs toggle */}
      <button
        type="button"
        onClick={() => setShowDocs(v => !v)}
        className="w-full flex items-center justify-between px-3 py-2 rounded-lg bg-muted/30 border border-border/40 text-sm font-medium hover:bg-muted/50 transition-colors"
      >
        <span className="flex items-center gap-2">
          <BookOpen className="h-4 w-4 text-primary" />
          Orientações e formato JSON
        </span>
        {showDocs ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
      </button>

      {showDocs && (
        <div className="rounded-xl border border-border/40 bg-muted/10 p-4 space-y-4 text-sm">

          {/* Tipos de trilha */}
          <div className="grid grid-cols-2 gap-3">
            <div className="rounded-lg border border-primary/20 bg-primary/5 p-3 space-y-1">
              <div className="flex items-center gap-1.5 text-xs font-semibold text-primary">
                <Crown className="h-3.5 w-3.5" /> subscription
              </div>
              <p className="text-xs text-muted-foreground">
                Inclusa no plano premium. Sem campo <code>price</code>.
                Todos os assinantes ativos têm acesso automático.
              </p>
            </div>
            <div className="rounded-lg border border-amber-500/20 bg-amber-500/5 p-3 space-y-1">
              <div className="flex items-center gap-1.5 text-xs font-semibold text-amber-400">
                <ShoppingBag className="h-3.5 w-3.5" /> purchase
              </div>
              <p className="text-xs text-muted-foreground">
                Comprada individualmente. Requer campo <code>price</code> em BRL.
                Aparecerá na loja para compra avulsa.
              </p>
            </div>
          </div>

          {/* Campos raiz */}
          <div>
            <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wide mb-2">Campos do objeto raiz</p>
            <div className="space-y-1 text-xs font-mono">
              {[
                ["title", "string *", "Nome da trilha"],
                ["description", "string", "Descrição e objetivos"],
                ["disciplineId", "number *", "ID da grande área (veja lista abaixo)"],
                ["accessType", '"subscription"|"purchase" *', "Tipo de acesso"],
                ["price", "number", "Preço em BRL — obrigatório se accessType='purchase'"],
                ["imageUrl", "string (URL)", "Thumbnail da trilha (opcional)"],
                ["totalHours", "number", "Carga horária estimada (padrão: 40)"],
                ["passingScore", "number 50–100", "% mínimo para aprovação final (padrão: 70)"],
                ["finalExamQuestions", "number 5–100", "Questões no exame final (padrão: 30)"],
                ["finalExamTimeSeconds", "number", "Tempo do exame em segundos (padrão: 3600)"],
                ["modules", "array *", "Lista de módulos (mín. 1)"],
              ].map(([field, type, desc]) => (
                <div key={field} className="grid grid-cols-[130px_1fr_1fr] gap-2 items-start py-0.5 border-b border-border/20 last:border-0">
                  <span className="text-primary">{field}</span>
                  <span className="text-amber-400">{type}</span>
                  <span className="text-muted-foreground font-sans">{desc}</span>
                </div>
              ))}
            </div>
          </div>

          {/* Campos de módulo */}
          <div>
            <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wide mb-2">Campos de cada módulo</p>
            <div className="space-y-1 text-xs font-mono">
              {[
                ["title", "string *", "Nome do módulo"],
                ["summary", "string", "Tópicos abordados"],
                ["difficulty", '"easy"|"medium"|"hard"|"mixed"', "Nível do módulo"],
                ["questionCount", "number 5–200", "Questões por tentativa (padrão: 40)"],
                ["minPassRate", "number 50–100", "% mínimo para passar (padrão: 70)"],
                ["ebookUrl", "string (URL)", "URL do PDF do ebook (antes das questões)"],
                ["ebookTitle", "string", "Título do ebook exibido ao aluno"],
                ["questionFilter.subjectIds", "number[]", "IDs dos assuntos (ex: [30007, 30008])"],
                ["questionFilter.difficulty", "string", "Filtrar por dificuldade das questões"],
                ["questionFilter.author", "string", "Filtrar por autor"],
                ["questionFilter.year", "number", "Filtrar por ano"],
              ].map(([field, type, desc]) => (
                <div key={field} className="grid grid-cols-[170px_1fr_1fr] gap-2 items-start py-0.5 border-b border-border/20 last:border-0">
                  <span className="text-primary">{field}</span>
                  <span className="text-amber-400">{type}</span>
                  <span className="text-muted-foreground font-sans">{desc}</span>
                </div>
              ))}
            </div>
          </div>

          {/* Exemplos */}
          <div>
            <div className="flex items-center gap-2 mb-2">
              <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wide">Exemplos</p>
              <button
                type="button"
                onClick={() => setShowPurchaseExample(v => !v)}
                className="text-[10px] text-primary hover:underline"
              >
                {showPurchaseExample ? "ver subscription" : "ver purchase"}
              </button>
            </div>
            <pre className="text-[10px] bg-background rounded-lg border border-border/30 p-3 overflow-x-auto leading-relaxed text-foreground/80">
              {showPurchaseExample ? JSON_EXAMPLE_PURCHASE : JSON_EXAMPLE}
            </pre>
            <button
              type="button"
              onClick={() => { setJson(showPurchaseExample ? JSON_EXAMPLE_PURCHASE : JSON_EXAMPLE); validate(showPurchaseExample ? JSON_EXAMPLE_PURCHASE : JSON_EXAMPLE); }}
              className="mt-1.5 text-[10px] text-primary hover:underline"
            >
              Usar este exemplo →
            </button>
          </div>

          {/* Prompt para IA */}
          <div className="rounded-lg border border-violet-500/20 bg-violet-500/5 p-3 space-y-2">
            <div className="flex items-center justify-between">
              <p className="text-xs font-semibold text-violet-300">Gerar com IA (Claude / ChatGPT / Gemini)</p>
              <Button size="sm" variant="ghost" className="h-6 text-[10px] gap-1 text-violet-300" onClick={copyPrompt}>
                <Copy className="h-3 w-3" /> Copiar prompt
              </Button>
            </div>
            <p className="text-[10px] text-muted-foreground">
              Copie o prompt acima, cole em qualquer IA, substitua <code>[NOME DA DISCIPLINA]</code> pelo assunto desejado
              e informe os IDs dos assuntos relevantes. A IA retornará o JSON pronto para importar aqui.
            </p>
          </div>
        </div>
      )}

      {/* JSON input */}
      <div className="space-y-2">
        <div className="flex items-center justify-between">
          <Label className="text-sm">JSON da Trilha *</Label>
          <div className="flex items-center gap-2">
            <input ref={fileRef} type="file" accept=".json" className="hidden" onChange={handleFile} />
            <Button size="sm" variant="ghost" className="h-7 text-xs gap-1" onClick={() => fileRef.current?.click()}>
              <Upload className="h-3 w-3" /> Carregar arquivo
            </Button>
          </div>
        </div>
        <Textarea
          value={json}
          onChange={e => handleChange(e.target.value)}
          rows={12}
          className="font-mono text-xs resize-y"
          placeholder={'{\n  "title": "...",\n  "disciplineId": 2,\n  "modules": [...]\n}'}
        />
      </div>

      {/* Error */}
      {parseError && (
        <div className="flex items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2">
          <AlertCircle className="h-4 w-4 text-destructive shrink-0 mt-0.5" />
          <p className="text-xs text-destructive">{parseError}</p>
        </div>
      )}

      {/* Preview */}
      {preview && !parseError && (
        <div className="rounded-xl border border-green-500/30 bg-green-500/5 p-4 space-y-2">
          <div className="flex items-center gap-2">
            <CheckCircle2 className="h-4 w-4 text-green-400" />
            <p className="text-sm font-semibold text-green-300">JSON válido — prévia da importação</p>
          </div>
          <div className="grid grid-cols-2 gap-2 text-xs">
            <div>
              <span className="text-muted-foreground">Título: </span>
              <span className="font-medium">{preview.title}</span>
            </div>
            <div>
              <span className="text-muted-foreground">Acesso: </span>
              <Badge className={`text-[10px] ${preview.accessType === "purchase" ? "bg-amber-500/20 text-amber-300 border-amber-500/30" : "bg-primary/20 text-primary border-primary/30"}`}>
                {preview.accessType === "purchase" ? `Compra — R$ ${Number(preview.price || 0).toFixed(2)}` : "Assinatura"}
              </Badge>
            </div>
            <div>
              <span className="text-muted-foreground">Módulos: </span>
              <span className="font-medium">{preview.modules?.length}</span>
            </div>
            <div>
              <span className="text-muted-foreground">Carga horária: </span>
              <span className="font-medium">{preview.totalHours ?? 40}h</span>
            </div>
            <div>
              <span className="text-muted-foreground">Aprovação: </span>
              <span className="font-medium">{preview.passingScore ?? 70}%</span>
            </div>
            <div>
              <span className="text-muted-foreground">Exame final: </span>
              <span className="font-medium">{preview.finalExamQuestions ?? 30} questões</span>
            </div>
          </div>
          <div className="space-y-1 mt-2">
            <p className="text-[10px] font-semibold text-muted-foreground uppercase tracking-wide">Módulos:</p>
            {preview.modules?.map((m: any, i: number) => (
              <div key={i} className="flex items-center gap-2 text-xs">
                <span className="w-5 h-5 rounded bg-primary/10 text-primary text-[10px] font-bold flex items-center justify-center shrink-0">{i + 1}</span>
                <span className="truncate">{m.title}</span>
                <Badge variant="outline" className="text-[10px] shrink-0">{m.questionCount ?? 40}Q</Badge>
                <Badge variant="outline" className="text-[10px] shrink-0">{DIFFICULTY_LABELS[m.difficulty] ?? m.difficulty}</Badge>
              </div>
            ))}
          </div>
        </div>
      )}

      <Button
        className="w-full gap-2"
        disabled={!preview || !!parseError || mutation.isPending}
        onClick={handleImport}
      >
        {mutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Download className="h-4 w-4" />}
        Importar Trilha
      </Button>
    </div>
  );
}

// ── Create/Edit Trail dialog ───────────────────────────────────────────────────
function TrailForm({ trail, onClose, onSaved }: {
  trail?: any; onClose: () => void; onSaved: () => void;
}) {
  const isEdit = !!trail;
  const { data: disciplines } = trpc.questions.disciplines.useQuery();
  const [form, setForm] = useState({
    disciplineId: trail?.disciplineId ? String(trail.disciplineId) : "",
    title: trail?.title ?? "",
    description: trail?.description ?? "",
    totalHours: trail?.totalHours ?? 40,
    passingScore: trail?.passingScore ?? 70,
    finalExamQuestions: trail?.finalExamQuestions ?? 30,
    finalExamTimeSeconds: trail?.finalExamTimeSeconds ?? 3600,
    accessType: trail?.accessType ?? "subscription",
    price: trail?.price ?? "",
    imageUrl: trail?.imageUrl ?? "",
  });

  const createMutation = trpc.trails.create.useMutation({
    onSuccess: () => { toast.success("Trilha criada!"); onSaved(); onClose(); },
    onError: e => toast.error(e.message),
  });
  const updateMutation = trpc.trails.update.useMutation({
    onSuccess: () => { toast.success("Trilha atualizada!"); onSaved(); onClose(); },
    onError: e => toast.error(e.message),
  });

  const set = (k: string, v: any) => setForm(p => ({ ...p, [k]: v }));
  const isPending = createMutation.isPending || updateMutation.isPending;

  const handleSave = () => {
    if (!form.disciplineId || !form.title) { toast.error("Preencha disciplina e título."); return; }
    if (form.accessType === "purchase" && !form.price) { toast.error("Informe o preço para trilha de compra."); return; }
    const data = {
      disciplineId: Number(form.disciplineId),
      title: form.title,
      description: form.description || undefined,
      totalHours: Number(form.totalHours),
      passingScore: Number(form.passingScore),
      finalExamQuestions: Number(form.finalExamQuestions),
      finalExamTimeSeconds: Number(form.finalExamTimeSeconds),
      accessType: form.accessType as "subscription" | "purchase",
      price: form.price ? Number(form.price) : undefined,
      imageUrl: form.imageUrl || undefined,
    };
    if (isEdit) updateMutation.mutate({ id: trail.id, ...data });
    else createMutation.mutate(data);
  };

  return (
    <div className="space-y-4">
      <div className="space-y-1.5">
        <Label className="text-sm">Grande Área *</Label>
        <Select value={form.disciplineId} onValueChange={v => set("disciplineId", v)}>
          <SelectTrigger className="h-10"><SelectValue placeholder="Selecione..." /></SelectTrigger>
          <SelectContent>
            {(disciplines as any[])?.map((d: any) => (
              <SelectItem key={d.id} value={String(d.id)}>{d.namePt}</SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>
      <div className="space-y-1.5">
        <Label className="text-sm">Título *</Label>
        <Input value={form.title} onChange={e => set("title", e.target.value)} placeholder="Ex: Fisiologia Veterinária I" className="h-10" />
      </div>
      <div className="space-y-1.5">
        <Label className="text-sm">Descrição</Label>
        <Textarea value={form.description} onChange={e => set("description", e.target.value)} rows={3} className="resize-none text-sm" placeholder="Descreva o conteúdo e objetivos da trilha..." />
      </div>

      {/* Tipo de acesso */}
      <div className="space-y-1.5">
        <Label className="text-sm">Tipo de Acesso *</Label>
        <div className="grid grid-cols-2 gap-2">
          {([["subscription", "Assinatura", "Inclusa no plano premium"], ["purchase", "Compra avulsa", "Adquirida individualmente"]] as const).map(([v, label, desc]) => (
            <button
              key={v}
              type="button"
              onClick={() => set("accessType", v)}
              className={`text-left p-3 rounded-lg border transition-all text-xs ${form.accessType === v ? "border-primary bg-primary/10" : "border-border/50 hover:border-primary/30"}`}
            >
              <div className="font-medium mb-0.5">{label}</div>
              <div className="text-muted-foreground">{desc}</div>
            </button>
          ))}
        </div>
      </div>

      {form.accessType === "purchase" && (
        <div className="space-y-1.5">
          <Label className="text-sm">Preço (R$) *</Label>
          <Input type="number" value={form.price} onChange={e => set("price", e.target.value)} className="h-9 text-sm" min={1} step={0.01} placeholder="49.90" />
        </div>
      )}

      <div className="space-y-1.5">
        <Label className="text-sm">URL da imagem (thumbnail)</Label>
        <Input value={form.imageUrl} onChange={e => set("imageUrl", e.target.value)} className="h-9 text-sm" placeholder="https://..." />
      </div>

      <div className="grid grid-cols-2 gap-3">
        <div className="space-y-1.5">
          <Label className="text-sm">Carga horária (h)</Label>
          <Input type="number" value={form.totalHours} onChange={e => set("totalHours", e.target.value)} className="h-9 text-sm" min={1} />
        </div>
        <div className="space-y-1.5">
          <Label className="text-sm">Aprovação mínima (%)</Label>
          <Input type="number" value={form.passingScore} onChange={e => set("passingScore", e.target.value)} className="h-9 text-sm" min={50} max={100} />
        </div>
        <div className="space-y-1.5">
          <Label className="text-sm">Questões no exame final</Label>
          <Input type="number" value={form.finalExamQuestions} onChange={e => set("finalExamQuestions", e.target.value)} className="h-9 text-sm" min={10} max={100} />
        </div>
        <div className="space-y-1.5">
          <Label className="text-sm">Tempo do exame final (seg)</Label>
          <Input type="number" value={form.finalExamTimeSeconds} onChange={e => set("finalExamTimeSeconds", e.target.value)} className="h-9 text-sm" min={600} />
        </div>
      </div>
      <Button className="w-full gap-2" onClick={handleSave} disabled={isPending}>
        {isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />}
        {isEdit ? "Salvar Alterações" : "Criar Trilha"}
      </Button>
    </div>
  );
}

// ── Module dialog ──────────────────────────────────────────────────────────────
function ModuleForm({ trailId, nextOrder, module, onClose, onSaved }: {
  trailId: number; nextOrder: number; module?: any; onClose: () => void; onSaved: () => void;
}) {
  const isEdit = !!module;
  const { data: disciplines } = trpc.questions.disciplines.useQuery();
  const filter = module?.questionFilter || {};
  const [form, setForm] = useState({
    title: module?.title || "",
    summary: module?.summary || "",
    difficulty: module?.difficulty || "mixed",
    questionCount: module?.questionCount || 40,
    cycleSize: module?.cycleSize ?? 10,
    minPassRate: module?.minPassRate || 70,
    disciplineId: filter.disciplineId ? String(filter.disciplineId) : "",
    subjectIds: filter.subjectIds || ([] as number[]),
    ebookUrl: module?.ebookUrl || "",
    ebookTitle: module?.ebookTitle || "",
  });

  const { data: subjects } = trpc.questions.subjects.useQuery(
    { disciplineId: Number(form.disciplineId) },
    { enabled: !!form.disciplineId }
  );

  const previewQuery = trpc.questions.list.useQuery({
    disciplineId: Number(form.disciplineId),
    subjectId: form.subjectIds.length ? form.subjectIds[0] : undefined,
    difficulty: form.difficulty !== "mixed" ? form.difficulty : undefined,
    limit: 1000,
  }, { enabled: !!form.disciplineId });

  const createMutation = trpc.trails.addModule.useMutation({
    onSuccess: () => { toast.success("Módulo adicionado!"); onSaved(); onClose(); },
    onError: e => toast.error(e.message),
  });
  const updateMutation = trpc.trails.updateModule.useMutation({
    onSuccess: () => { toast.success("Módulo atualizado!"); onSaved(); onClose(); },
    onError: e => toast.error(e.message),
  });

  const set = (k: string, v: any) => setForm(p => ({ ...p, [k]: v }));
  const isPending = createMutation.isPending || updateMutation.isPending;

  return (
    <div className="space-y-4">
      <div className="space-y-1.5">
        <Label className="text-sm">Título do Módulo *</Label>
        <Input value={form.title} onChange={e => set("title", e.target.value)} placeholder={`Módulo ${isEdit ? module.order : nextOrder} — Título`} className="h-10" />
      </div>
      <div className="space-y-1.5">
        <Label className="text-sm">Resumo do conteúdo</Label>
        <Textarea value={form.summary} onChange={e => set("summary", e.target.value)} rows={2} className="resize-none text-sm" placeholder="Tópicos abordados neste módulo..." />
      </div>
      <div className="grid grid-cols-2 gap-3">
        <div className="space-y-1.5">
          <Label className="text-sm">Dificuldade</Label>
          <Select value={form.difficulty} onValueChange={v => set("difficulty", v)}>
            <SelectTrigger className="h-9 text-sm"><SelectValue /></SelectTrigger>
            <SelectContent>
              {Object.entries(DIFFICULTY_LABELS).map(([v, l]) => (
                <SelectItem key={v} value={v}>{l}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-1.5">
          <Label className="text-sm">Questões (Alvo total)</Label>
          <Input type="number" value={form.questionCount} onChange={e => set("questionCount", Number(e.target.value))} className="h-9 text-sm" min={5} max={200} />
        </div>
        <div className="space-y-1.5">
          <Label className="text-sm">Aprovação mínima (%)</Label>
          <Input type="number" value={form.minPassRate} onChange={e => set("minPassRate", Number(e.target.value))} className="h-9 text-sm" min={50} max={100} />
        </div>
        <div className="space-y-1.5">
          <Label className="text-sm">Ciclo (Q. por vez)</Label>
          <Input type="number" value={form.cycleSize} onChange={e => set("cycleSize", Number(e.target.value))} className="h-9 text-sm" min={0} max={100} placeholder="10" />
        </div>
      </div>

      {/* Ebook (optional, for purchase trails) */}
      <div className="rounded-lg border border-amber-500/20 bg-amber-500/5 p-3 space-y-3">
        <p className="text-xs font-medium text-amber-300">Ebook do módulo (opcional — trilhas pagas)</p>
        <div className="space-y-2">
          <div className="space-y-1">
            <Label className="text-xs text-muted-foreground">URL do PDF</Label>
            <Input value={form.ebookUrl} onChange={e => set("ebookUrl", e.target.value)} className="h-8 text-xs" placeholder="https://storage.example.com/ebook.pdf" />
          </div>
          <div className="space-y-1">
            <Label className="text-xs text-muted-foreground">Título do Ebook</Label>
            <Input value={form.ebookTitle} onChange={e => set("ebookTitle", e.target.value)} className="h-8 text-xs" placeholder="Ex: Apostila de Fisiologia Cardiovascular" />
          </div>
        </div>
      </div>

      <div className="rounded-lg border border-border/40 p-3 space-y-3">
        <div className="flex items-center justify-between">
          <p className="text-xs font-medium text-muted-foreground">Filtro de questões para o módulo</p>
          {previewQuery.data && (
            <Badge variant="outline" className={previewQuery.data.questions.length < form.questionCount ? "text-red-400 border-red-500/30 bg-red-500/10" : "text-emerald-400 border-emerald-500/30 bg-emerald-500/10"}>
              {previewQuery.data.questions.length} disponíveis
            </Badge>
          )}
        </div>
        <div className="grid grid-cols-2 gap-3">
          <div className="space-y-1">
            <Label className="text-xs text-muted-foreground">Grande Área</Label>
            <Select value={form.disciplineId} onValueChange={v => set("disciplineId", v)}>
              <SelectTrigger className="h-8 text-xs"><SelectValue placeholder="Qualquer" /></SelectTrigger>
              <SelectContent>
                {(disciplines as any[])?.map((d: any) => (
                  <SelectItem key={d.id} value={String(d.id)}>{d.namePt}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1">
            <Label className="text-xs text-muted-foreground">Disciplina</Label>
            <Select
              disabled={!form.disciplineId}
              value={form.subjectIds.length === 0 ? "__all__" : String(form.subjectIds[0])}
              onValueChange={v => set("subjectIds", v === "__all__" ? [] : [Number(v)])}
            >
              <SelectTrigger className="h-8 text-xs"><SelectValue placeholder="Qualquer" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="__all__">Todas</SelectItem>
                {(subjects as any[])?.map((s: any) => (
                  <SelectItem key={s.id} value={String(s.id)}>{s.namePt}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        </div>
      </div>

      <Button
        className="w-full gap-2"
        disabled={!form.title || isPending}
        onClick={() => {
          const payload = {
            title: form.title,
            summary: form.summary || undefined,
            difficulty: form.difficulty,
            questionCount: form.questionCount,
            cycleSize: form.cycleSize,
            minPassRate: form.minPassRate,
            ebookUrl: form.ebookUrl || undefined,
            ebookTitle: form.ebookTitle || undefined,
            questionFilter: form.disciplineId ? {
              disciplineId: Number(form.disciplineId),
              subjectIds: form.subjectIds.length ? form.subjectIds : undefined,
              difficulty: form.difficulty !== "mixed" ? form.difficulty : undefined,
            } : undefined,
          };
          if (isEdit) {
            updateMutation.mutate({ id: module.id, ...payload });
          } else {
            createMutation.mutate({ trailId, ...payload });
          }
        }}
      >
        {isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : (isEdit ? <Edit className="h-4 w-4" /> : <Plus className="h-4 w-4" />)}
        {isEdit ? "Salvar Alterações" : "Adicionar Módulo"}
      </Button>
    </div>
  );
}

// ── Main component ─────────────────────────────────────────────────────────────
export default function AdminTrilhas() {
  const [showCreate, setShowCreate]   = useState(false);
  const [showImport, setShowImport]   = useState(false);
  const [editTrail, setEditTrail]     = useState<any>(null);
  const [editModule, setEditModule]   = useState<any>(null);
  const [expandedId, setExpandedId]   = useState<number | null>(null);
  const [showAddModule, setShowAddModule] = useState(false);

  const { data: trails = [], refetch, isLoading } = trpc.admin.listTrails.useQuery();
  const { data: modules = [], refetch: refetchModules } = trpc.admin.listTrailModules.useQuery(
    { trailId: expandedId! }, { enabled: !!expandedId }
  );

  const deleteMutation = trpc.trails.delete.useMutation({
    onSuccess: () => { toast.success("Trilha desativada."); refetch(); },
    onError: e => toast.error(e.message),
  });

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <p className="text-sm text-muted-foreground">{(trails as any[]).length} trilha(s) cadastrada(s)</p>
        <div className="flex gap-2">
          <Button size="sm" variant="ghost" className="h-8" onClick={() => refetch()}>
            <RefreshCw className="h-3.5 w-3.5 mr-1.5" /> Atualizar
          </Button>
          <Button size="sm" variant="outline" className="h-8 gap-1.5" onClick={() => setShowImport(true)}>
            <Upload className="h-3.5 w-3.5" /> Importar JSON
          </Button>
          <Button size="sm" className="h-8 gap-1.5" onClick={() => setShowCreate(true)}>
            <Plus className="h-3.5 w-3.5" /> Nova Trilha
          </Button>
        </div>
      </div>

      {isLoading ? (
        <div className="flex justify-center py-10"><Loader2 className="h-5 w-5 animate-spin text-primary" /></div>
      ) : (
        <div className="space-y-3">
          {(trails as any[]).map((trail: any) => (
            <div key={trail.id} className="rounded-xl border border-border/50 bg-card overflow-hidden">
              {/* Trail header */}
              <div
                className="flex items-center gap-3 p-4 cursor-pointer hover:bg-muted/10"
                onClick={() => setExpandedId(expandedId === trail.id ? null : trail.id)}
              >
                <div className="w-10 h-10 rounded-xl bg-primary/10 border border-primary/20 flex items-center justify-center shrink-0">
                  <Route className="h-5 w-5 text-primary" />
                </div>
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2">
                    <p className="font-semibold text-sm truncate">{trail.title}</p>
                    {trail.accessType === "purchase" ? (
                      <Badge className="text-[10px] shrink-0 bg-amber-500/15 text-amber-300 border-amber-500/30">
                        <ShoppingBag className="h-2.5 w-2.5 mr-1" />
                        R$ {Number(trail.price || 0).toFixed(2)}
                      </Badge>
                    ) : (
                      <Badge className="text-[10px] shrink-0 bg-primary/10 text-primary border-primary/20">
                        <Crown className="h-2.5 w-2.5 mr-1" />
                        Assinatura
                      </Badge>
                    )}
                  </div>
                  <p className="text-xs text-muted-foreground">{trail.disciplineName}</p>
                </div>
                <div className="flex items-center gap-3 shrink-0">
                  <div className="flex items-center gap-1 text-xs text-muted-foreground">
                    <BookOpen className="h-3.5 w-3.5" /> {trail.moduleCount} módulos
                  </div>
                  <div className="flex items-center gap-1 text-xs text-muted-foreground">
                    <Users className="h-3.5 w-3.5" /> {trail.enrollmentCount} alunos
                  </div>
                  <div className="flex items-center gap-1 text-xs text-muted-foreground">
                    <Clock className="h-3.5 w-3.5" /> {trail.totalHours}h
                  </div>
                  <Badge variant="outline" className="text-[10px]">
                    {trail.passingScore}% aprovação
                  </Badge>
                  <div className="flex gap-1">
                    <Button size="icon" variant="ghost" className="h-7 w-7"
                      onClick={e => { e.stopPropagation(); setEditTrail(trail); }}>
                      <Edit className="h-3.5 w-3.5" />
                    </Button>
                    <Button size="icon" variant="ghost" className="h-7 w-7 text-destructive hover:text-destructive"
                      onClick={e => { e.stopPropagation(); if (confirm("Desativar esta trilha?")) deleteMutation.mutate({ id: trail.id }); }}>
                      <Trash2 className="h-3.5 w-3.5" />
                    </Button>
                  </div>
                  <ChevronRight className={`h-4 w-4 text-muted-foreground transition-transform ${expandedId === trail.id ? "rotate-90" : ""}`} />
                </div>
              </div>

              {/* Modules */}
              {expandedId === trail.id && (
                <div className="border-t border-border/40 p-4 space-y-2">
                  <div className="flex items-center justify-between mb-3">
                    <p className="text-xs font-medium text-muted-foreground">Módulos da trilha</p>
                    <Button size="sm" variant="outline" className="h-7 text-xs gap-1"
                      onClick={() => setShowAddModule(true)}>
                      <Plus className="h-3 w-3" /> Adicionar Módulo
                    </Button>
                  </div>
                  {(modules as any[]).length === 0 ? (
                    <p className="text-sm text-muted-foreground text-center py-4">
                      Nenhum módulo ainda. Adicione ou importe via JSON.
                    </p>
                  ) : (
                    <div className="space-y-2">
                      {(modules as any[]).map((mod: any) => (
                        <div key={mod.id} className="flex items-center gap-3 rounded-lg bg-muted/20 border border-border/30 p-3">
                          <span className="w-7 h-7 rounded-lg bg-primary/10 text-primary text-xs font-bold flex items-center justify-center shrink-0">
                            {mod.order}
                          </span>
                          <div className="flex-1 min-w-0">
                            <p className="text-sm font-medium truncate">{mod.title}</p>
                            {mod.summary && <p className="text-xs text-muted-foreground truncate">{mod.summary}</p>}
                            {mod.ebookUrl && (
                              <p className="text-[10px] text-amber-400 flex items-center gap-1 mt-0.5">
                                <BookOpen className="h-2.5 w-2.5" />
                                {mod.ebookTitle || "Ebook"}
                              </p>
                            )}
                          </div>
                          <div className="flex items-center gap-2 shrink-0">
                            {mod.ebookUrl && (
                              <Badge className="text-[10px] bg-amber-500/15 text-amber-300 border-amber-500/30">PDF</Badge>
                            )}
                            <Badge variant="outline" className="text-[10px]">{DIFFICULTY_LABELS[mod.difficulty] ?? mod.difficulty}</Badge>
                            <span className="text-xs text-muted-foreground">{mod.questionCount}Q</span>
                            <span className="text-xs text-muted-foreground">{mod.minPassRate}% mín.</span>
                            <Button size="icon" variant="ghost" className="h-6 w-6 ml-2 text-muted-foreground hover:text-foreground" onClick={(e) => { e.stopPropagation(); setEditModule(mod); }}>
                              <Edit className="h-3 w-3" />
                            </Button>
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              )}
            </div>
          ))}
          {(trails as any[]).length === 0 && (
            <div className="text-center py-16 text-muted-foreground">
              <Route className="h-10 w-10 mx-auto mb-3 opacity-30" />
              <p className="text-sm">Nenhuma trilha cadastrada.</p>
              <div className="flex gap-2 justify-center mt-4">
                <Button size="sm" variant="outline" onClick={() => setShowImport(true)}>
                  <Upload className="h-3.5 w-3.5 mr-1.5" /> Importar JSON
                </Button>
                <Button size="sm" onClick={() => setShowCreate(true)}>Criar manualmente</Button>
              </div>
            </div>
          )}
        </div>
      )}

      {/* Import JSON dialog */}
      <Dialog open={showImport} onOpenChange={setShowImport}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle className="font-serif flex items-center gap-2">
              <Upload className="h-5 w-5 text-primary" /> Importar Trilha via JSON
            </DialogTitle>
          </DialogHeader>
          <ImportTrailDialog onClose={() => setShowImport(false)} onImported={() => refetch()} />
        </DialogContent>
      </Dialog>

      {/* Create trail dialog */}
      <Dialog open={showCreate} onOpenChange={setShowCreate}>
        <DialogContent className="max-w-lg">
          <DialogHeader><DialogTitle className="font-serif">Nova Trilha</DialogTitle></DialogHeader>
          <TrailForm onClose={() => setShowCreate(false)} onSaved={() => refetch()} />
        </DialogContent>
      </Dialog>

      {/* Edit trail dialog */}
      <Dialog open={!!editTrail} onOpenChange={v => { if (!v) setEditTrail(null); }}>
        <DialogContent className="max-w-lg">
          <DialogHeader><DialogTitle className="font-serif">Editar Trilha</DialogTitle></DialogHeader>
          {editTrail && <TrailForm trail={editTrail} onClose={() => setEditTrail(null)} onSaved={() => refetch()} />}
        </DialogContent>
      </Dialog>

      {/* Add/Edit module dialog */}
      <Dialog open={(showAddModule && !!expandedId) || !!editModule} onOpenChange={v => { if (!v) { setShowAddModule(false); setEditModule(null); } }}>
        <DialogContent className="max-w-lg">
          <DialogHeader><DialogTitle className="font-serif">{editModule ? "Editar Módulo" : "Adicionar Módulo"}</DialogTitle></DialogHeader>
          {expandedId && (
            <ModuleForm
              trailId={expandedId}
              nextOrder={(modules as any[]).length + 1}
              module={editModule}
              onClose={() => { setShowAddModule(false); setEditModule(null); }}
              onSaved={() => refetchModules()}
            />
          )}
        </DialogContent>
      </Dialog>
    </div>
  );
}
