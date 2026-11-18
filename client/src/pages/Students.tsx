import { useState, useRef } from "react";
import { useI18n } from "@/contexts/I18nContext";
import { trpc } from "@/lib/trpc";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from "@/components/ui/table";
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter,
} from "@/components/ui/dialog";
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from "@/components/ui/select";
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import {
  Sheet, SheetContent, SheetHeader, SheetTitle,
} from "@/components/ui/sheet";
import { Badge } from "@/components/ui/badge";
import { Plus, Trash2, Pencil, Upload, Download, FileText, FileSpreadsheet, BarChart3, Link, ClipboardList, HelpCircle } from "lucide-react";
import BackButton from "@/components/BackButton";
import { LineChart, Line, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid } from "recharts";

const PER_PAGE_OPTIONS = [25, 50, 100];

export default function Students() {
  const { t } = useI18n();
  const utils = trpc.useUtils();
  const csvRef = useRef<HTMLInputElement>(null);
  const xlsxRef = useRef<HTMLInputElement>(null);

  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(50);
  const [selected, setSelected] = useState<number[]>([]);
  const [showCreate, setShowCreate] = useState(false);
  const [editItem, setEditItem] = useState<any>(null);
  const [deleteIds, setDeleteIds] = useState<number[]>([]);
  const [papersStudentId, setPapersStudentId] = useState<number | null>(null);
  const [importPreview, setImportPreview] = useState<{ rows: any[]; type: "csv" | "xlsx"; raw: string } | null>(null);
  const [performanceStudentId, setPerformanceStudentId] = useState<number | null>(null);

  const { data, isLoading } = trpc.students.list.useQuery({ page, perPage });
  const { data: classes = [] } = trpc.classes.list.useQuery({ includeArchived: false });

  const students = data?.students ?? [];
  const total = data?.total ?? 0;
  const totalPages = Math.ceil(total / perPage);

  const createMutation = trpc.students.create.useMutation({
    onSuccess: () => { utils.students.list.invalidate(); toast.success(t("students.messages.created")); setShowCreate(false); resetForm(); },
    onError: (e) => toast.error(e.message),
  });
  const updateMutation = trpc.students.update.useMutation({
    onSuccess: () => { utils.students.list.invalidate(); toast.success(t("students.messages.updated")); setEditItem(null); resetForm(); },
    onError: (e) => toast.error(e.message),
  });
  const deleteMutation = trpc.students.delete.useMutation({
    onSuccess: () => { utils.students.list.invalidate(); toast.success(t("students.messages.deleted")); setDeleteIds([]); setSelected([]); },
    onError: (e) => toast.error(e.message),
  });
  const bulkDeleteMutation = trpc.students.bulkDelete.useMutation({
    onSuccess: () => { utils.students.list.invalidate(); toast.success(t("students.messages.deleted")); setDeleteIds([]); setSelected([]); },
    onError: (e) => toast.error(e.message),
  });
  const importCSVMutation = trpc.students.importCSV.useMutation({
    onSuccess: (r) => { utils.students.list.invalidate(); toast.success(`${r.count} ${t("students.messages.imported")}`); setImportPreview(null); },
    onError: (e) => toast.error(e.message),
  });
  const importXLSXMutation = trpc.students.importXLSX.useMutation({
    onSuccess: (r) => { utils.students.list.invalidate(); toast.success(`${r.count} ${t("students.messages.imported")}`); setImportPreview(null); },
    onError: (e) => toast.error(e.message),
  });
  const generatePortalMutation = trpc.students.generatePortalToken.useMutation({
    onSuccess: (r) => {
      const url = `${window.location.origin}/student/${r.token}`;
      navigator.clipboard.writeText(url).then(() => toast.success("Link do portal copiado!")).catch(() => toast.info(`Link: ${url}`));
    },
    onError: () => toast.error("Erro ao gerar link"),
  });

  const exportCSV = trpc.students.exportCSV.useQuery({}, { enabled: false });
  const exportXLSX = trpc.students.exportXLSX.useQuery({}, { enabled: false });

  const [formFirst, setFormFirst] = useState("");
  const [formLast, setFormLast] = useState("");
  const [formStudentId, setFormStudentId] = useState("");
  const [formExtRef, setFormExtRef] = useState("");
  const [formEmail, setFormEmail] = useState("");
  const [formClassId, setFormClassId] = useState("");

  function resetForm() {
    setFormFirst(""); setFormLast(""); setFormStudentId(""); setFormExtRef(""); setFormEmail(""); setFormClassId("");
  }

  function openEdit(s: any) {
    setFormFirst(s.firstName); setFormLast(s.lastName); setFormStudentId(s.studentId ?? "");
    setFormExtRef(s.externalRef ?? ""); setFormEmail(s.email ?? ""); setEditItem(s);
  }

  function handleSubmit() {
    const payload = {
      firstName: formFirst, lastName: formLast,
      studentId: formStudentId || undefined, externalRef: formExtRef || undefined,
      email: formEmail || undefined,
      classId: formClassId ? Number(formClassId) : undefined,
    };
    if (editItem) { updateMutation.mutate({ id: editItem.id, ...payload }); }
    else { createMutation.mutate(payload); }
  }

  function toggleSelect(id: number) {
    setSelected((prev) => prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]);
  }

  function toggleAll() {
    setSelected(selected.length === students.length ? [] : students.map((s: any) => s.id));
  }

  async function handleCSVFile(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    if (!file) return;
    const text = await file.text();
    const lines = text.trim().split("\n");
    const headers = lines[0].split(",").map((h) => h.trim().replace(/^"|"$/g, ""));
    const rows = lines.slice(1, 6).map((line) => {
      const vals = line.split(",").map((v) => v.trim().replace(/^"|"$/g, ""));
      return Object.fromEntries(headers.map((h, i) => [h, vals[i] ?? ""]));
    });
    setImportPreview({ rows, type: "csv", raw: text });
    e.target.value = "";
  }

  async function handleXLSXFile(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    if (!file) return;
    const buffer = await file.arrayBuffer();
    const bytes = new Uint8Array(buffer);
    let binary = "";
    for (let i = 0; i < bytes.byteLength; i++) binary += String.fromCharCode(bytes[i]);
    const base64 = btoa(binary);
    setImportPreview({ rows: [{ info: `${file.name} — XLSX pronto para importação` }], type: "xlsx", raw: base64 });
    e.target.value = "";
  }

  function confirmImport() {
    if (!importPreview) return;
    if (importPreview.type === "csv") {
      importCSVMutation.mutate({ csvData: importPreview.raw });
    } else {
      importXLSXMutation.mutate({ base64: importPreview.raw });
    }
  }

  function downloadCSV(csv: string, filename: string) {
    const blob = new Blob([csv], { type: "text/csv" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a"); a.href = url; a.download = filename; a.click();
    URL.revokeObjectURL(url);
  }

  function downloadXLSX(base64: string, filename: string) {
    const binary = atob(base64);
    const bytes = Uint8Array.from({ length: binary.length }, (_, i) => binary.charCodeAt(i));
    const blob = new Blob([bytes], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a"); a.href = url; a.download = filename; a.click();
    URL.revokeObjectURL(url);
  }

  return (
    <div className="space-y-4">
      <BackButton />
      <div className="flex items-center justify-between flex-wrap gap-2">
        <div>
          <h1 className="text-2xl font-bold">{t("students.title")}</h1>
          <p className="text-muted-foreground text-sm">{t("students.subtitle")}</p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          {selected.length > 0 && (
            <Button variant="destructive" size="sm" onClick={() => setDeleteIds(selected)}>
              <Trash2 className="w-4 h-4 mr-1" />{t("students.deleteSelected")}
            </Button>
          )}
          <Button variant="outline" size="sm" onClick={() => csvRef.current?.click()}>
            <Upload className="w-4 h-4 mr-1" />{t("students.importCSV")}
          </Button>
          <Button variant="outline" size="sm" onClick={() => xlsxRef.current?.click()}>
            <FileSpreadsheet className="w-4 h-4 mr-1" />{t("students.importXLSX")}
          </Button>
          <Button variant="outline" size="sm" onClick={async () => {
            const r = await exportCSV.refetch();
            if (r.data?.csv) downloadCSV(r.data.csv, "students.csv");
          }}>
            <Download className="w-4 h-4 mr-1" />{t("students.exportCSV")}
          </Button>
          <Button variant="outline" size="sm" onClick={async () => {
            const r = await exportXLSX.refetch();
            if (r.data?.base64) downloadXLSX(r.data.base64, "students.xlsx");
          }}>
            <FileSpreadsheet className="w-4 h-4 mr-1" />{t("students.exportXLSX")}
          </Button>
          <Button size="sm" onClick={() => { resetForm(); setShowCreate(true); }}>
            <Plus className="w-4 h-4 mr-1" />{t("students.add")}
          </Button>
        </div>
      </div>

      {/* Helper Import Templates Banner */}
      <div className="bg-emerald-50 border border-emerald-100 rounded-lg p-3 flex flex-wrap items-center justify-between gap-3 text-sm text-emerald-900 shadow-sm">
        <div className="flex items-center gap-2">
          <HelpCircle className="w-4 h-4 text-emerald-600 shrink-0" />
          <span>Precisa de ajuda com a importação de alunos? Use nossos modelos vazios formatados.</span>
        </div>
        <div className="flex gap-4">
          <a
            href="/templates/alunos-modelo.xlsx"
            download
            className="font-semibold text-emerald-700 hover:text-emerald-950 flex items-center gap-1 hover:underline transition-colors"
          >
            <FileSpreadsheet className="w-3.5 h-3.5" /> Baixar Excel (.xlsx)
          </a>
          <a
            href="/templates/alunos-modelo.csv"
            download
            className="font-semibold text-emerald-700 hover:text-emerald-950 flex items-center gap-1 hover:underline transition-colors"
          >
            <FileText className="w-3.5 h-3.5" /> Baixar CSV (.csv)
          </a>
        </div>
      </div>

      {/* Per page + pagination */}
      <div className="flex items-center justify-between text-sm text-muted-foreground">
        <div className="flex items-center gap-2">
          <Select value={perPage.toString()} onValueChange={(v) => { setPerPage(Number(v)); setPage(1); }}>
            <SelectTrigger className="w-24 h-8"><SelectValue /></SelectTrigger>
            <SelectContent>
              {PER_PAGE_OPTIONS.map((n) => <SelectItem key={n} value={n.toString()}>{n} {t("students.perPage")}</SelectItem>)}
            </SelectContent>
          </Select>
          <span>{total} aluno(s)</span>
        </div>
        <div className="flex items-center gap-1">
          <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage(page - 1)}>{t("common.previous")}</Button>
          <span className="px-2">{page}/{totalPages || 1}</span>
          <Button variant="outline" size="sm" disabled={page >= totalPages} onClick={() => setPage(page + 1)}>{t("common.next")}</Button>
        </div>
      </div>

      <div className="border rounded-lg overflow-x-auto">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-10">
                <Checkbox checked={selected.length === students.length && students.length > 0} onCheckedChange={toggleAll} />
              </TableHead>
              <TableHead>{t("students.studentId")}</TableHead>
              <TableHead className="hidden md:table-cell">{t("students.externalRef")}</TableHead>
              <TableHead>{t("students.firstName")}</TableHead>
              <TableHead>{t("students.lastName")}</TableHead>
              <TableHead className="hidden lg:table-cell">{t("students.classes")}</TableHead>
              <TableHead className="text-right">Ações</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading ? (
              <TableRow><TableCell colSpan={7} className="text-center text-muted-foreground py-8">{t("common.loading")}</TableCell></TableRow>
            ) : students.length === 0 ? (
              <TableRow><TableCell colSpan={7} className="text-center text-muted-foreground py-8">{t("students.noStudents")}</TableCell></TableRow>
            ) : students.map((s: any) => (
              <TableRow key={s.id}>
                <TableCell><Checkbox checked={selected.includes(s.id)} onCheckedChange={() => toggleSelect(s.id)} /></TableCell>
                <TableCell className="font-mono text-sm">{s.studentId ?? "–"}</TableCell>
                <TableCell className="hidden md:table-cell text-sm text-muted-foreground">{s.externalRef ?? "–"}</TableCell>
                <TableCell>{s.firstName}</TableCell>
                <TableCell>{s.lastName}</TableCell>
                <TableCell className="hidden lg:table-cell">
                  <div className="flex flex-wrap gap-1">
                    {s.classes?.map((c: any) => (
                      <Badge key={c.id} variant="secondary" className="text-xs">{c.name}</Badge>
                    ))}
                  </div>
                </TableCell>
                <TableCell>
                  <div className="flex items-center justify-end gap-1">
                    <Button variant="ghost" size="icon" onClick={() => openEdit(s)}>
                      <Pencil className="w-4 h-4" />
                    </Button>
                    <Button variant="ghost" size="icon" title="Relatório de desempenho" onClick={() => setPerformanceStudentId(s.id)}>
                      <BarChart3 className="w-4 h-4 text-blue-500" />
                    </Button>
                    <Button variant="ghost" size="icon" title="Gerar link do portal do aluno" onClick={() => generatePortalMutation.mutate({ studentId: s.id })}>
                      <Link className="w-4 h-4 text-green-600" />
                    </Button>
                    <Button variant="ghost" size="icon" title="Boletim individual (PDF)" onClick={() => window.open(`/student-report/${s.id}`, "_blank")}>
                      <ClipboardList className="w-4 h-4 text-emerald-600" />
                    </Button>
                    <Button variant="ghost" size="icon" title={t("students.papersAndSessions")} onClick={() => setPapersStudentId(s.id)}>
                      <FileText className="w-4 h-4" />
                    </Button>
                    <Button variant="ghost" size="icon" className="text-destructive hover:text-destructive" onClick={() => setDeleteIds([s.id])}>
                      <Trash2 className="w-4 h-4" />
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>

      {/* Hidden file inputs */}
      <input ref={csvRef} type="file" accept=".csv" className="hidden" onChange={handleCSVFile} />
      <input ref={xlsxRef} type="file" accept=".xlsx,.xls" className="hidden" onChange={handleXLSXFile} />

      {/* Create/Edit dialog */}
      <Dialog open={showCreate || editItem !== null} onOpenChange={(o) => { if (!o) { setShowCreate(false); setEditItem(null); } }}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editItem ? t("common.edit") : t("students.add")}</DialogTitle>
          </DialogHeader>
          <div className="space-y-3 py-2">
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1">
                <Label>{t("students.firstName")}</Label>
                <Input value={formFirst} onChange={(e) => setFormFirst(e.target.value)} />
              </div>
              <div className="space-y-1">
                <Label>{t("students.lastName")}</Label>
                <Input value={formLast} onChange={(e) => setFormLast(e.target.value)} />
              </div>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1">
                <Label>{t("students.studentId")}</Label>
                <Input value={formStudentId} onChange={(e) => setFormStudentId(e.target.value)} placeholder="Matrícula" />
              </div>
              <div className="space-y-1">
                <Label>{t("students.externalRef")}</Label>
                <Input value={formExtRef} onChange={(e) => setFormExtRef(e.target.value)} />
              </div>
            </div>
            <div className="space-y-1">
              <Label>{t("students.email")}</Label>
              <Input type="email" value={formEmail} onChange={(e) => setFormEmail(e.target.value)} />
            </div>
            {!editItem && (
              <div className="space-y-1">
                <Label>{t("classes.title")} <span className="text-muted-foreground text-xs">({t("common.optional")})</span></Label>
                <Select value={formClassId} onValueChange={setFormClassId}>
                  <SelectTrigger><SelectValue placeholder="Selecionar turma..." /></SelectTrigger>
                  <SelectContent>
                    {classes.map((c: any) => <SelectItem key={c.id} value={c.id.toString()}>{c.name} — {c.semester}º/{c.classYear}</SelectItem>)}
                  </SelectContent>
                </Select>
              </div>
            )}
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => { setShowCreate(false); setEditItem(null); }}>{t("common.cancel")}</Button>
            <Button onClick={handleSubmit} disabled={!formFirst.trim() || !formLast.trim()}>{t("common.save")}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Import preview dialog */}
      <Dialog open={importPreview !== null} onOpenChange={(o) => { if (!o) setImportPreview(null); }}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle>{t("students.importPreview")}</DialogTitle>
          </DialogHeader>
          <div className="overflow-x-auto max-h-60">
            {importPreview && importPreview.rows.length > 0 && (
              <Table>
                <TableHeader>
                  <TableRow>
                    {Object.keys(importPreview.rows[0]).map((k) => <TableHead key={k}>{k}</TableHead>)}
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {importPreview.rows.map((row, i) => (
                    <TableRow key={i}>
                      {Object.values(row).map((v: any, j) => <TableCell key={j} className="text-sm">{v}</TableCell>)}
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )}
          </div>
          <p className="text-sm text-muted-foreground">Prévia das primeiras linhas. A importação completa será processada ao confirmar.</p>
          <DialogFooter>
            <Button variant="outline" onClick={() => setImportPreview(null)}>{t("common.cancel")}</Button>
            <Button onClick={confirmImport}>{t("students.confirmImport")}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Delete confirm */}
      <AlertDialog open={deleteIds.length > 0} onOpenChange={(o) => { if (!o) setDeleteIds([]); }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("messages.confirmDelete")}</AlertDialogTitle>
            <AlertDialogDescription>{t("messages.cannotUndo")}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t("common.cancel")}</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              onClick={() => {
                if (deleteIds.length === 1) deleteMutation.mutate({ id: deleteIds[0] });
                else bulkDeleteMutation.mutate({ ids: deleteIds });
              }}
            >
              {t("common.delete")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* Papers & Sessions */}
      {papersStudentId && (
        <Sheet open onOpenChange={(o) => { if (!o) setPapersStudentId(null); }}>
          <SheetContent>
            <SheetHeader><SheetTitle>{t("students.papersAndSessions")}</SheetTitle></SheetHeader>
            <p className="text-muted-foreground mt-4 text-sm">Funcionalidade de histórico de provas do aluno será exibida aqui após integração com correção por câmera.</p>
          </SheetContent>
        </Sheet>
      )}

      {/* Performance report */}
      {performanceStudentId !== null && (
        <PerformanceDialog
          studentId={performanceStudentId}
          onClose={() => setPerformanceStudentId(null)}
        />
      )}
    </div>
  );
}

function PerformanceDialog({ studentId, onClose }: { studentId: number; onClose: () => void }) {
  const { data, isLoading } = trpc.reports.studentPerformance.useQuery({ studentId });

  const chartData = (data?.grades ?? []).map((g: any) => ({
    name: g.examName.length > 12 ? g.examName.slice(0, 12) + "…" : g.examName,
    pct: g.pct,
    score: g.score,
    max: g.maxScore,
  }));

  return (
    <Dialog open onOpenChange={(o) => { if (!o) onClose(); }}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <BarChart3 className="w-5 h-5 text-blue-500" />
            Relatório de Desempenho
          </DialogTitle>
        </DialogHeader>
        {isLoading ? (
          <div className="flex justify-center py-10">
            <div className="animate-spin w-6 h-6 border-2 border-blue-600 border-t-transparent rounded-full" />
          </div>
        ) : !data ? (
          <p className="text-center text-muted-foreground py-8 text-sm">Dados não encontrados.</p>
        ) : (
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <p className="font-semibold">{data.student.firstName} {data.student.lastName}</p>
              {data.avgPct !== null && (
                <span className={`text-sm font-medium px-2 py-0.5 rounded-full ${data.avgPct >= 70 ? "bg-green-100 text-green-700" : data.avgPct >= 50 ? "bg-yellow-100 text-yellow-700" : "bg-red-100 text-red-700"}`}>
                  Média: {data.avgPct}%
                </span>
              )}
            </div>

            {chartData.length === 0 ? (
              <p className="text-center text-muted-foreground py-6 text-sm">Nenhuma nota registrada.</p>
            ) : (
              <ResponsiveContainer width="100%" height={180}>
                <LineChart data={chartData} margin={{ top: 4, right: 8, left: -20, bottom: 0 }}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                  <XAxis dataKey="name" tick={{ fontSize: 10 }} />
                  <YAxis domain={[0, 100]} tick={{ fontSize: 10 }} unit="%" />
                  <Tooltip formatter={(v: any) => `${v}%`} />
                  <Line type="monotone" dataKey="pct" stroke="#3b82f6" strokeWidth={2} dot={{ r: 4 }} name="Aproveitamento" />
                </LineChart>
              </ResponsiveContainer>
            )}

            <div className="space-y-1 max-h-40 overflow-y-auto">
              {data.grades.map((g: any, i: number) => (
                <div key={i} className="flex items-center justify-between py-1 text-sm border-b last:border-0">
                  <span className="truncate mr-2 text-slate-700">{g.examName}</span>
                  <span className={`shrink-0 font-medium ${g.pct >= 70 ? "text-green-600" : g.pct >= 50 ? "text-yellow-600" : "text-red-600"}`}>
                    {g.score}/{g.maxScore} ({g.pct}%)
                  </span>
                </div>
              ))}
            </div>
          </div>
        )}
        <DialogFooter>
          <Button variant="outline" onClick={onClose}>Fechar</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
