import { useState } from "react";
import { trpc } from "@/lib/trpc";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter,
} from "@/components/ui/dialog";
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from "@/components/ui/select";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Plus, Download, FileText, Pencil, Trash2, CheckCircle2, Users, Loader2 } from "lucide-react";
import BackButton from "@/components/BackButton";
import AnswerSheetWizard from "@/components/answerSheets/AnswerSheetWizard";
import AnswerSheetPreview from "@/components/answerSheets/AnswerSheetPreview";
import { STANDARD_FORMS, STANDARD_FORM_CONFIGS } from "@shared/_core/answerSheetTypes";
import type { AnswerSheetConfig } from "@shared/_core/answerSheetTypes";
import { generateAnswerSheetSVG, AnswerSheetOverflowError } from "@/lib/answerSheetSvg";
import buildQrPayload from "@/lib/buildQrPayload";
import { printAnswerSheetPDF, downloadAnswerSheetPNG } from "@/lib/downloadAnswerSheet";

/* ── Download helpers ─────────────────────────────────────────────────────── */

function dlPDF(configKey: string, label: string) {
  const config = STANDARD_FORM_CONFIGS[configKey];
  if (!config) return;
  try {
    const svg = generateAnswerSheetSVG(label, config);
    printAnswerSheetPDF([svg], label);
  } catch (e) {
    toast.error(e instanceof AnswerSheetOverflowError ? e.message : "Erro ao gerar o gabarito.");
  }
}

function dlPNG(configKey: string, label: string) {
  const config = STANDARD_FORM_CONFIGS[configKey];
  if (!config) return;
  try {
    const svg = generateAnswerSheetSVG(label, config);
    downloadAnswerSheetPNG(svg, label.replace(/\s+/g, "_"));
  } catch (e) {
    toast.error(e instanceof AnswerSheetOverflowError ? e.message : "Erro ao gerar o gabarito.");
  }
}

/* ── Pack de Turma dialog ─────────────────────────────────────────────────── */

function ClassPackDialog({
  formId,
  formLabel,
  onClose,
}: {
  formId: string;
  formLabel: string;
  onClose: () => void;
}) {
  const [selectedClassId, setSelectedClassId] = useState<string>("");
  const [generating, setGenerating] = useState(false);

  const { data: classesData, isLoading: loadingClasses } = trpc.classes.list.useQuery({ includeArchived: false });
  const { data: studentsData, isLoading: loadingStudents } = trpc.students.list.useQuery(
    { classId: Number(selectedClassId), perPage: 500 },
    { enabled: !!selectedClassId }
  );

  const config = STANDARD_FORM_CONFIGS[formId];
  const tplQuery = trpc.answerSheetTemplates.get.useQuery({ id: Number(formId) }, { enabled: false });
  const classesList: any[] = classesData ?? [];
  const studentList: any[] = studentsData?.students ?? [];

  async function handleGenerate() {
    setGenerating(true);

    try {
      // Resolve config for custom templates when needed
      let usedConfig: AnswerSheetConfig | undefined = config;
      let templateVersion: string | undefined = undefined;
      if (!usedConfig) {
        const res = await tplQuery.refetch();
        if (res.data) {
          usedConfig = typeof res.data.config === 'string' ? JSON.parse(res.data.config) : res.data.config;
          const tvRaw = res.data.publishedAt ?? res.data.updatedAt ?? null;
          templateVersion = tvRaw ? new Date(tvRaw).toISOString() : undefined;
        }
      }

      if (!usedConfig || studentList.length === 0) {
        toast.error("Configuração inválida ou lista de alunos vazia.");
        setGenerating(false);
        return;
      }

      const svgs = studentList.map((s: any) => {
        const fullName = `${s.firstName} ${s.lastName}`.trim();
        const studentCode = s.studentId ?? String(s.id);
        const qrPayload = buildQrPayload({ templateId: formId, userId: studentCode, classId: selectedClassId, templateVersion });
        fetch('/api/telemetry', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ event: 'classpack_generate', template: formId, studentCode, qrPayload }) }).catch(() => {});
        return generateAnswerSheetSVG(formLabel, usedConfig as AnswerSheetConfig, { studentName: fullName, formId, studentCode, qrPayload });
      });
      const className = classesList.find((c: any) => String(c.id) === selectedClassId)?.name ?? "Turma";
      printAnswerSheetPDF(svgs, `${formLabel} — ${className}`);
      onClose();
    } catch (e) {
      toast.error(e instanceof AnswerSheetOverflowError ? e.message : "Erro ao gerar o pack de turma.");
    } finally {
      setGenerating(false);
    }
  }

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>Pack de Turma — {formLabel}</DialogTitle>
          <DialogDescription>
            Gera um PDF com um gabarito por aluno, com o nome pré-preenchido.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4 py-2">
          <div className="space-y-2">
            <label className="text-sm font-medium">Selecione a turma</label>
            {loadingClasses ? (
              <div className="flex items-center gap-2 text-sm text-muted-foreground">
                <Loader2 className="h-4 w-4 animate-spin" /> Carregando turmas...
              </div>
            ) : classesList.length === 0 ? (
              <p className="text-sm text-muted-foreground">Nenhuma turma cadastrada.</p>
            ) : (
              <Select value={selectedClassId} onValueChange={setSelectedClassId}>
                <SelectTrigger>
                  <SelectValue placeholder="Escolha uma turma..." />
                </SelectTrigger>
                <SelectContent>
                  {classesList.map((c: any) => (
                    <SelectItem key={c.id} value={String(c.id)}>
                      {c.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
          </div>

          {selectedClassId && (
            <div className="rounded-lg border bg-muted/30 p-3 text-sm">
              {loadingStudents ? (
                <div className="flex items-center gap-2 text-muted-foreground">
                  <Loader2 className="h-4 w-4 animate-spin" /> Buscando alunos...
                </div>
              ) : studentList.length === 0 ? (
                <p className="text-muted-foreground">Nenhum aluno nesta turma.</p>
              ) : (
                <div className="flex items-center gap-2">
                  <Users className="h-4 w-4 text-primary" />
                  <span><strong>{studentList.length}</strong> gabarito(s) serão gerados</span>
                </div>
              )}
            </div>
          )}
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={onClose}>Cancelar</Button>
          <Button
            onClick={handleGenerate}
            disabled={!selectedClassId || studentList.length === 0 || generating || loadingStudents}
            className="gap-2"
          >
            {generating ? <Loader2 className="h-4 w-4 animate-spin" /> : <Download className="h-4 w-4" />}
            {generating ? "Gerando..." : "Gerar PDF"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

/* ── Main page ───────────────────────────────────────────────────────────── */

export default function AnswerSheets() {
  const utils = trpc.useUtils();
  const [showWizard, setShowWizard] = useState(false);
  const [editItem, setEditItem] = useState<any>(null);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [previewItem, setPreviewItem] = useState<any>(null);
  const [classPack, setClassPack] = useState<{ formId: string; formLabel: string } | null>(null);

  const { data: templates = [], isLoading } = trpc.answerSheetTemplates.list.useQuery();

  const createMutation = trpc.answerSheetTemplates.create.useMutation({
    onSuccess: () => {
      utils.answerSheetTemplates.list.invalidate();
      toast.success("Gabarito publicado com sucesso!");
      setShowWizard(false);
      setEditItem(null);
    },
    onError: (e) => toast.error(e.message),
  });

  const updateMutation = trpc.answerSheetTemplates.update.useMutation({
    onSuccess: () => {
      utils.answerSheetTemplates.list.invalidate();
      toast.success("Gabarito atualizado!");
      setEditItem(null);
    },
    onError: (e) => toast.error(e.message),
  });

  const deleteMutation = trpc.answerSheetTemplates.delete.useMutation({
    onSuccess: () => {
      utils.answerSheetTemplates.list.invalidate();
      toast.success("Gabarito excluído.");
      setDeleteId(null);
    },
  });

  function handlePublish(name: string, config: AnswerSheetConfig) {
    const configStr = JSON.stringify(config);
    if (editItem) {
      updateMutation.mutate({ id: editItem.id, name, config: configStr });
    } else {
      createMutation.mutate({ name, config: configStr });
    }
  }

  function openEdit(tpl: any) {
    setEditItem({ ...tpl, config: typeof tpl.config === "string" ? JSON.parse(tpl.config) : tpl.config });
  }

  const published = templates.filter((t: any) => t.published === "true");
  const drafts = templates.filter((t: any) => t.published !== "true");

  return (
    <div className="space-y-5 max-w-5xl">
      <BackButton />

      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">Gabaritos</h1>
          <p className="text-muted-foreground text-sm">
            Modelos reutilizáveis para correção automática por câmera
          </p>
        </div>
        <Button onClick={() => setShowWizard(true)}>
          <Plus className="w-4 h-4 mr-1" /> Criar Gabarito
        </Button>
      </div>

      <Tabs defaultValue="standard">
        <TabsList>
          <TabsTrigger value="standard">Modelos Padrão</TabsTrigger>
          <TabsTrigger value="custom">
            Personalizados
            {templates.length > 0 && (
              <Badge variant="secondary" className="ml-1.5 text-[10px] px-1.5 py-0">{templates.length}</Badge>
            )}
          </TabsTrigger>
        </TabsList>

        {/* ── Standard Forms ──────────────────────────────────────────────── */}
        <TabsContent value="standard" className="space-y-4 mt-4">
          <p className="text-sm text-muted-foreground">
            Imprima em papel branco A4. O PDF abrirá o diálogo de impressão do navegador —
            escolha <strong>"Salvar como PDF"</strong> para baixar o arquivo.
          </p>

          {/* Feature comparison table */}
          <div className="overflow-x-auto rounded-lg border">
            <table className="w-full text-sm border-collapse">
              <thead>
                <tr className="bg-muted">
                  <th className="border-b px-3 py-2 text-left font-semibold">Recurso</th>
                  {STANDARD_FORMS.map((f) => (
                    <th key={f.id} className="border-b px-3 py-2 text-center font-semibold">{f.label}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {[
                  {
                    label: "Nº de questões",
                    render: (f: typeof STANDARD_FORMS[number]) => <span className="font-bold">{f.maxQuestions}</span>,
                  },
                  {
                    label: "Alternativas",
                    render: () => <span>A – E</span>,
                  },
                  {
                    label: "Seção de Matrícula",
                    render: (f: typeof STANDARD_FORMS[number]) =>
                      f.hasStudentId
                        ? <CheckCircle2 className="w-4 h-4 text-emerald-600 mx-auto" />
                        : <span className="text-muted-foreground">—</span>,
                  },
                  {
                    label: "Dígitos da matrícula",
                    render: (f: typeof STANDARD_FORMS[number]) =>
                      f.hasStudentId ? f.studentIdDigits : "—",
                  },
                  {
                    label: "Seção de Versão (A/B/C/D)",
                    render: () => <CheckCircle2 className="w-4 h-4 text-emerald-600 mx-auto" />,
                  },
                  {
                    label: "Pack de turma",
                    render: (f: typeof STANDARD_FORMS[number]) =>
                      f.classPacksAvailable
                        ? <CheckCircle2 className="w-4 h-4 text-emerald-600 mx-auto" />
                        : <span className="text-muted-foreground">—</span>,
                  },
                ].map((row, ri) => (
                  <tr key={ri} className={ri % 2 === 0 ? "" : "bg-muted/30"}>
                    <td className="border-b px-3 py-2 text-muted-foreground">{row.label}</td>
                    {STANDARD_FORMS.map((f) => (
                      <td key={f.id} className="border-b px-3 py-2 text-center">{row.render(f as any)}</td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Download cards */}
          <div className="grid md:grid-cols-3 gap-4">
            {STANDARD_FORMS.map((form) => (
              <Card key={form.id} className="border">
                <CardHeader className="pb-2">
                  <CardTitle className="text-base">{form.label}</CardTitle>
                  <p className="text-xs text-muted-foreground">{form.maxQuestions} questões · A-E</p>
                </CardHeader>
                <CardContent className="space-y-2">
                  <div className="flex flex-col gap-1.5">
                    {/* PDF sem versão */}
                    <Button
                      variant="outline" size="sm" className="w-full gap-2 justify-start"
                      onClick={() => dlPDF(`${form.id}-noversion`, `${form.label} — Sem Versão`)}
                    >
                      <FileText className="w-4 h-4 shrink-0" />
                      PDF — Sem seleção de versão
                    </Button>

                    {/* PDF com versão A/B/C/D */}
                    <Button
                      variant="outline" size="sm" className="w-full gap-2 justify-start"
                      onClick={() => dlPDF(form.id, `${form.label} — Com Versão`)}
                    >
                      <FileText className="w-4 h-4 shrink-0" />
                      PDF — Com seleção de versão
                    </Button>

                    {/* PNG */}
                    <Button
                      variant="outline" size="sm" className="w-full gap-2 justify-start"
                      onClick={() => dlPNG(form.id, `${form.label} — Com Versão`)}
                    >
                      <Download className="w-4 h-4 shrink-0" />
                      PNG (alta resolução)
                    </Button>

                    {/* Pack de turma */}
                    {form.classPacksAvailable && (
                      <Button
                        size="sm" className="w-full gap-2 justify-start"
                        onClick={() => setClassPack({ formId: form.id, formLabel: form.label })}
                      >
                        <Users className="w-4 h-4 shrink-0" />
                        Pack de Turma (PDF por aluno)
                      </Button>
                    )}
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        </TabsContent>

        {/* ── Custom Forms ────────────────────────────────────────────────── */}
        <TabsContent value="custom" className="mt-4">
          {isLoading ? (
            <div className="flex items-center gap-2 text-sm text-muted-foreground py-8">
              <Loader2 className="h-4 w-4 animate-spin" /> Carregando...
            </div>
          ) : templates.length === 0 ? (
            <Card>
              <CardContent className="flex flex-col items-center py-12 gap-4">
                <FileText className="w-10 h-10 text-muted-foreground" />
                <p className="text-muted-foreground text-sm">Nenhum gabarito personalizado criado ainda.</p>
                <Button onClick={() => setShowWizard(true)}>
                  <Plus className="w-4 h-4 mr-1" /> Criar Gabarito Personalizado
                </Button>
              </CardContent>
            </Card>
          ) : (
            <div className="space-y-4">
              <p className="text-sm text-muted-foreground">
                ⚠️ Gabaritos personalizados são exclusivos desta conta. Não compartilhe cópias físicas sem também compartilhar a definição do formulário.
              </p>

              {published.length > 0 && (
                <div>
                  <h3 className="text-sm font-semibold mb-2">Publicados</h3>
                  <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    {published.map((tpl: any) => (
                      <TemplateCard key={tpl.id} tpl={tpl}
                        onEdit={() => openEdit(tpl)}
                        onDelete={() => setDeleteId(tpl.id)}
                        onPreview={() => setPreviewItem(tpl)}
                        onDownload={() => {
                              const config = typeof tpl.config === "string" ? JSON.parse(tpl.config) : tpl.config;
                              const tvRaw = tpl.publishedAt ?? tpl.updatedAt ?? null;
                              const templateVersion = tvRaw ? new Date(tvRaw).toISOString() : undefined;
                              const qrPayload = buildQrPayload({ templateId: String(tpl.id), templateVersion });
                              try {
                                const svg = generateAnswerSheetSVG(tpl.name, config, { standalone: true, formId: String(tpl.id), qrPayload });
                                printAnswerSheetPDF([svg], tpl.name);
                              } catch (e) {
                                toast.error(e instanceof AnswerSheetOverflowError ? e.message : "Erro ao gerar o gabarito.");
                              }
                            }}
                      />
                    ))}
                  </div>
                </div>
              )}

              {drafts.length > 0 && (
                <div>
                  <h3 className="text-sm font-semibold mb-2 text-muted-foreground">Rascunhos</h3>
                  <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    {drafts.map((tpl: any) => (
                      <TemplateCard key={tpl.id} tpl={tpl}
                        onEdit={() => openEdit(tpl)}
                        onDelete={() => setDeleteId(tpl.id)}
                        onPreview={() => setPreviewItem(tpl)}
                        onDownload={() => {
                          const config = typeof tpl.config === "string" ? JSON.parse(tpl.config) : tpl.config;
                          const tvRaw = tpl.publishedAt ?? tpl.updatedAt ?? null;
                          const templateVersion = tvRaw ? new Date(tvRaw).toISOString() : undefined;
                          const qrPayload = buildQrPayload({ templateId: String(tpl.id), templateVersion });
                          try {
                            const svg = generateAnswerSheetSVG(tpl.name, config, { standalone: true, formId: String(tpl.id), qrPayload });
                            printAnswerSheetPDF([svg], tpl.name);
                          } catch (e) {
                            toast.error(e instanceof AnswerSheetOverflowError ? e.message : "Erro ao gerar o gabarito.");
                          }
                        }}
                      />
                    ))}
                  </div>
                </div>
              )}
            </div>
          )}
        </TabsContent>
      </Tabs>

      {/* ── Pack de Turma dialog ─────────────────────────────────────────── */}
      {classPack && (
        <ClassPackDialog
          formId={classPack.formId}
          formLabel={classPack.formLabel}
          onClose={() => setClassPack(null)}
        />
      )}

      {/* ── Wizard dialog ───────────────────────────────────────────────── */}
      <Dialog
        open={showWizard || editItem !== null}
        onOpenChange={(o) => { if (!o) { setShowWizard(false); setEditItem(null); } }}
      >
        <DialogContent className="max-w-4xl h-[92vh] flex flex-col gap-0 overflow-hidden p-0">
          <DialogHeader className="px-6 pt-5 pb-4 shrink-0 border-b">
            <DialogTitle>
              {editItem ? `Editar: ${editItem.name}` : "Novo Gabarito Personalizado"}
            </DialogTitle>
          </DialogHeader>
          <div className="flex-1 min-h-0 overflow-hidden px-6 py-4">
            <AnswerSheetWizard
              initialName={editItem?.name}
              initialConfig={editItem?.config}
              onPublish={handlePublish}
              onCancel={() => { setShowWizard(false); setEditItem(null); }}
            />
          </div>
        </DialogContent>
      </Dialog>

      {/* ── Preview dialog ──────────────────────────────────────────────── */}
      {previewItem && (
        <Dialog open onOpenChange={() => setPreviewItem(null)}>
          <DialogContent className="max-w-lg">
            <DialogHeader>
              <DialogTitle>Pré-visualização — {previewItem.name}</DialogTitle>
            </DialogHeader>
            <div className="flex justify-center py-2">
              {/* show a template-only QR (no user) */}
              {(() => {
                const cfg = typeof previewItem.config === "string" ? JSON.parse(previewItem.config) : previewItem.config;
                const tvRaw = previewItem.publishedAt ?? previewItem.updatedAt ?? null;
                const templateVersion = tvRaw ? new Date(tvRaw).toISOString() : undefined;
                return (
                  <AnswerSheetPreview
                    name={previewItem.name}
                    config={cfg}
                    scale={0.6}
                    formId={previewItem.id}
                    qrPayload={buildQrPayload({ templateId: String(previewItem.id), templateVersion })}
                  />
                );
              })()}
            </div>
            <div className="flex justify-end">
              <Button
                size="sm" className="gap-2"
                onClick={() => {
                  const config = typeof previewItem.config === "string" ? JSON.parse(previewItem.config) : previewItem.config;
                  try {
                    const svg = generateAnswerSheetSVG(previewItem.name, config);
                    printAnswerSheetPDF([svg], previewItem.name);
                  } catch (e) {
                    toast.error(e instanceof AnswerSheetOverflowError ? e.message : "Erro ao gerar o gabarito.");
                  }
                }}
              >
                <Download className="h-4 w-4" /> Imprimir / PDF
              </Button>
            </div>
          </DialogContent>
        </Dialog>
      )}

      {/* ── Delete confirm ──────────────────────────────────────────────── */}
      <AlertDialog open={deleteId !== null} onOpenChange={(o) => { if (!o) setDeleteId(null); }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Excluir gabarito?</AlertDialogTitle>
            <AlertDialogDescription>Esta ação não pode ser desfeita.</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              onClick={() => deleteId && deleteMutation.mutate({ id: deleteId })}
            >Excluir</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

function TemplateCard({ tpl, onEdit, onDelete, onPreview, onDownload }: {
  tpl: any;
  onEdit: () => void;
  onDelete: () => void;
  onPreview: () => void;
  onDownload: () => void;
}) {
  const config = typeof tpl.config === "string" ? JSON.parse(tpl.config) : tpl.config;
  const totalQ = config?.questions?.reduce((acc: number, b: any) => acc + b.count, 0) ?? 0;

  return (
    <Card className="border hover:shadow-md transition-shadow cursor-pointer" onClick={onPreview}>
      <CardContent className="py-3 px-3 flex items-start justify-between gap-2">
        <div className="min-w-0 flex-1">
          <div className="flex items-center gap-1.5 mb-0.5">
            <p className="font-medium text-sm truncate">{tpl.name}</p>
            {tpl.published === "true" && (
              <Badge className="text-[9px] px-1 py-0 bg-emerald-100 text-emerald-700 border-emerald-200">
                Publicado
              </Badge>
            )}
          </div>
          <p className="text-xs text-muted-foreground">{totalQ} questão(ões)</p>
          <p className="text-[10px] text-muted-foreground mt-0.5">
            {new Date(tpl.createdAt).toLocaleDateString("pt-BR")}
          </p>
        </div>
        <div className="flex gap-1 shrink-0" onClick={(e) => e.stopPropagation()}>
          <Button variant="ghost" size="icon" className="h-7 w-7" onClick={onDownload} title="Imprimir / PDF">
            <Download className="w-3.5 h-3.5" />
          </Button>
          <Button variant="ghost" size="icon" className="h-7 w-7" onClick={onEdit}>
            <Pencil className="w-3.5 h-3.5" />
          </Button>
          <Button variant="ghost" size="icon" className="h-7 w-7 text-destructive hover:text-destructive" onClick={onDelete}>
            <Trash2 className="w-3.5 h-3.5" />
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}
