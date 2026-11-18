import { useState } from "react";
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
import { Plus, Pencil, Archive, ArchiveRestore, Trash2, BookOpen, BarChart2, ClipboardList, Download } from "lucide-react";
import BackButton from "@/components/BackButton";

const CURRENT_YEAR = new Date().getFullYear();
const YEARS = Array.from({ length: 10 }, (_, i) => CURRENT_YEAR - 3 + i);

export default function Classes() {
  const { t } = useI18n();
  const utils = trpc.useUtils();

  const [includeArchived, setIncludeArchived] = useState(false);
  const [selected, setSelected] = useState<number[]>([]);
  const [showCreate, setShowCreate] = useState(false);
  const [showImport, setShowImport] = useState(false);
  const [editItem, setEditItem] = useState<any>(null);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [gradeBookClassId, setGradeBookClassId] = useState<number | null>(null);

  const { data: classes = [], isLoading } = trpc.classes.list.useQuery({ includeArchived });
  const { data: institutions = [] } = trpc.institutions.list.useQuery();

  const createMutation = trpc.classes.create.useMutation({
    onSuccess: () => { utils.classes.list.invalidate(); toast.success(t("classes.messages.created")); setShowCreate(false); resetForm(); },
    onError: (e) => toast.error(e.message),
  });
  const updateMutation = trpc.classes.update.useMutation({
    onSuccess: () => { utils.classes.list.invalidate(); toast.success(t("classes.messages.updated")); setEditItem(null); resetForm(); },
    onError: (e) => toast.error(e.message),
  });
  const archiveMutation = trpc.classes.archive.useMutation({
    onSuccess: () => { utils.classes.list.invalidate(); toast.success(t("classes.messages.archived")); },
  });
  const unarchiveMutation = trpc.classes.unarchive.useMutation({
    onSuccess: () => { utils.classes.list.invalidate(); toast.success(t("classes.messages.unarchived")); },
  });
  const deleteMutation = trpc.classes.delete.useMutation({
    onSuccess: () => { utils.classes.list.invalidate(); toast.success(t("classes.messages.deleted")); setDeleteId(null); setSelected([]); },
    onError: (e) => toast.error(e.message),
  });

  const [formName, setFormName] = useState("");
  const [formInstitutionId, setFormInstitutionId] = useState<string>("");
  const [formCourseId, setFormCourseId] = useState<string>("");
  const [formSemester, setFormSemester] = useState<"1" | "2">("1");
  const [formYear, setFormYear] = useState(CURRENT_YEAR);

  const { data: availableCourses = [] } = trpc.institutions.listCourses.useQuery(
    { institutionId: Number(formInstitutionId) },
    { enabled: !!formInstitutionId }
  );

  function resetForm() {
    setFormName(""); setFormInstitutionId(""); setFormCourseId(""); setFormSemester("1"); setFormYear(CURRENT_YEAR);
  }

  function openEdit(cls: any) {
    setFormName(cls.name);
    setFormInstitutionId(cls.institutionId?.toString() ?? "");
    setFormCourseId(cls.courseId?.toString() ?? "");
    setFormSemester(cls.semester as "1" | "2");
    setFormYear(cls.classYear);
    setEditItem(cls);
  }

  function handleSubmit() {
    const payload = {
      name: formName,
      institutionId: formInstitutionId ? Number(formInstitutionId) : undefined,
      courseId: formCourseId ? Number(formCourseId) : undefined,
      semester: formSemester,
      classYear: formYear,
    };
    if (editItem) {
      updateMutation.mutate({ id: editItem.id, ...payload });
    } else {
      createMutation.mutate(payload);
    }
  }

  function toggleSelect(id: number) {
    setSelected((prev) => prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]);
  }

  function toggleAll() {
    setSelected(selected.length === classes.length ? [] : classes.map((c: any) => c.id));
  }

  return (
    <div className="space-y-4">
      <BackButton />
      <div className="flex items-center justify-between flex-wrap gap-2">
        <div>
          <h1 className="text-2xl font-bold">{t("classes.title")}</h1>
          <p className="text-muted-foreground text-sm">{t("classes.subtitle")}</p>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" size="sm" onClick={() => setIncludeArchived(!includeArchived)}>
            {includeArchived ? <ArchiveRestore className="w-4 h-4 mr-1" /> : <Archive className="w-4 h-4 mr-1" />}
            {t("classes.showArchived")}
          </Button>
          {selected.length > 0 && (
            <Button variant="destructive" size="sm" onClick={() => selected.length === 1 && setDeleteId(selected[0])}>
              <Trash2 className="w-4 h-4 mr-1" />{t("classes.deleteSelected")}
            </Button>
          )}
          <Button variant="outline" size="sm" onClick={() => setShowImport(true)}>
            <Download className="w-4 h-4 mr-1" />Importar da Gestão
          </Button>
          <Button size="sm" onClick={() => { resetForm(); setShowCreate(true); }}>
            <Plus className="w-4 h-4 mr-1" />{t("classes.create")}
          </Button>
        </div>
      </div>

      <div className="border rounded-lg overflow-x-auto">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-10">
                <Checkbox checked={selected.length === classes.length && classes.length > 0} onCheckedChange={toggleAll} />
              </TableHead>
              <TableHead>{t("classes.className")}</TableHead>
              <TableHead className="hidden md:table-cell">{t("classes.semester")}/{t("classes.year")}</TableHead>
              <TableHead className="hidden lg:table-cell">{t("classes.institution")}</TableHead>
              <TableHead>{t("classes.studentCount")}</TableHead>
              <TableHead className="text-right">Ações</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading ? (
              <TableRow><TableCell colSpan={6} className="text-center text-muted-foreground py-8">{t("common.loading")}</TableCell></TableRow>
            ) : classes.length === 0 ? (
              <TableRow><TableCell colSpan={6} className="text-center text-muted-foreground py-8">{t("classes.noClasses")}</TableCell></TableRow>
            ) : classes.map((cls: any) => (
              <TableRow key={cls.id} className={cls.archived === "true" ? "opacity-60" : ""}>
                <TableCell>
                  <Checkbox checked={selected.includes(cls.id)} onCheckedChange={() => toggleSelect(cls.id)} />
                </TableCell>
                <TableCell className="font-medium">
                  {cls.name}
                  {cls.archived === "true" && <Badge variant="secondary" className="ml-2 text-xs">{t("classes.archived")}</Badge>}
                </TableCell>
                <TableCell className="hidden md:table-cell text-muted-foreground">
                  {cls.semester}º/{cls.classYear}
                </TableCell>
                <TableCell className="hidden lg:table-cell text-muted-foreground text-sm">
                  {institutions.find((i: any) => i.id === cls.institutionId)?.name ?? "–"}
                </TableCell>
                <TableCell>{(cls as any).studentCount ?? 0}</TableCell>
                <TableCell>
                  <div className="flex items-center justify-end gap-1">
                    <Button variant="ghost" size="icon" title={t("common.edit")} onClick={() => openEdit(cls)}>
                      <Pencil className="w-4 h-4" />
                    </Button>
                    <Button variant="ghost" size="icon" title={t("classes.gradeBook")} onClick={() => setGradeBookClassId(cls.id)}>
                      <BookOpen className="w-4 h-4" />
                    </Button>
                    <Button variant="ghost" size="icon" title={t("classes.report")}>
                      <BarChart2 className="w-4 h-4" />
                    </Button>
                    {cls.archived === "true" ? (
                      <Button variant="ghost" size="icon" title={t("classes.unarchive")} onClick={() => unarchiveMutation.mutate({ id: cls.id })}>
                        <ArchiveRestore className="w-4 h-4" />
                      </Button>
                    ) : (
                      <Button variant="ghost" size="icon" title={t("classes.archive")} onClick={() => archiveMutation.mutate({ id: cls.id })}>
                        <Archive className="w-4 h-4" />
                      </Button>
                    )}
                    <Button variant="ghost" size="icon" title={t("common.delete")} className="text-destructive hover:text-destructive" onClick={() => setDeleteId(cls.id)}>
                      <Trash2 className="w-4 h-4" />
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>

      {/* Create/Edit dialog */}
      <Dialog open={showCreate || editItem !== null} onOpenChange={(o) => { if (!o) { setShowCreate(false); setEditItem(null); } }}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editItem ? t("classes.edit") : t("classes.create")}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4 py-2">
            <div className="space-y-1">
              <Label>{t("classes.className")}</Label>
              <Input value={formName} onChange={(e) => setFormName(e.target.value)} />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1">
                <Label>{t("classes.semester")}</Label>
                <Select value={formSemester} onValueChange={(v) => setFormSemester(v as "1" | "2")}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="1">1º Semestre</SelectItem>
                    <SelectItem value="2">2º Semestre</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1">
                <Label>{t("classes.year")}</Label>
                <Select value={formYear.toString()} onValueChange={(v) => setFormYear(Number(v))}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    {YEARS.map((y) => <SelectItem key={y} value={y.toString()}>{y}</SelectItem>)}
                  </SelectContent>
                </Select>
              </div>
            </div>
            <div className="space-y-1">
              <Label>{t("classes.institution")} <span className="text-muted-foreground text-xs">({t("common.optional")})</span></Label>
              <Select value={formInstitutionId} onValueChange={setFormInstitutionId}>
                <SelectTrigger><SelectValue placeholder="Selecionar..." /></SelectTrigger>
                <SelectContent>
                  {institutions.map((i: any) => <SelectItem key={i.id} value={i.id.toString()}>{i.name}</SelectItem>)}
                </SelectContent>
              </Select>
            </div>
            {formInstitutionId && availableCourses.length > 0 && (
              <div className="space-y-1">
                <Label>{t("classes.course")} <span className="text-muted-foreground text-xs">({t("common.optional")})</span></Label>
                <Select value={formCourseId} onValueChange={setFormCourseId}>
                  <SelectTrigger><SelectValue placeholder="Selecionar..." /></SelectTrigger>
                  <SelectContent>
                    {availableCourses.map((c: any) => <SelectItem key={c.id} value={c.id.toString()}>{c.name}</SelectItem>)}
                  </SelectContent>
                </Select>
              </div>
            )}
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => { setShowCreate(false); setEditItem(null); }}>{t("common.cancel")}</Button>
            <Button onClick={handleSubmit} disabled={!formName.trim()}>{t("common.save")}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Delete confirm */}
      <AlertDialog open={deleteId !== null} onOpenChange={(o) => { if (!o) setDeleteId(null); }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("messages.confirmDelete")}</AlertDialogTitle>
            <AlertDialogDescription>{t("messages.cannotUndo")}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t("common.cancel")}</AlertDialogCancel>
            <AlertDialogAction
              onClick={() => deleteId && deleteMutation.mutate({ id: deleteId })}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              {t("common.delete")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* Grade Book sheet */}
      {gradeBookClassId && (
        <GradeBookSheet classId={gradeBookClassId} onClose={() => setGradeBookClassId(null)} t={t} />
      )}

      {/* Importar turma do módulo de gestão */}
      {showImport && (
        <ImportFromGestaoDialog onClose={() => setShowImport(false)} />
      )}
    </div>
  );
}

function ImportFromGestaoDialog({ onClose }: { onClose: () => void }) {
  const utils = trpc.useUtils();
  const { data: gestaoClasses = [], isLoading } = trpc.gestao.listClasses.useQuery();
  const importMutation = trpc.gestao.importClass.useMutation({
    onSuccess: (result) => {
      utils.classes.list.invalidate();
      toast.success(
        `Turma importada: ${result.imported} aluno(s) novo(s)${result.skipped ? `, ${result.skipped} já existiam` : ""}.`
      );
      onClose();
    },
    onError: (e) => toast.error(e.message),
  });

  return (
    <Dialog open onOpenChange={(o) => { if (!o) onClose(); }}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Importar turma do módulo de gestão</DialogTitle>
        </DialogHeader>
        <div className="space-y-2 py-2 max-h-80 overflow-y-auto">
          {isLoading ? (
            <p className="text-muted-foreground text-sm">{"Carregando..."}</p>
          ) : gestaoClasses.length === 0 ? (
            <p className="text-muted-foreground text-sm">
              Nenhuma turma disponível para importar no módulo de gestão.
            </p>
          ) : (
            gestaoClasses.map((c) => (
              <div key={c.id} className="flex items-center justify-between border rounded-md px-3 py-2">
                <div>
                  <p className="font-medium text-sm">{c.name}</p>
                  <p className="text-xs text-muted-foreground">
                    {c.semester}º semestre · {c.studentCount} aluno(s)
                  </p>
                </div>
                <Button
                  size="sm"
                  variant="outline"
                  disabled={importMutation.isPending}
                  onClick={() => importMutation.mutate({ classGroupId: c.id })}
                >
                  Importar
                </Button>
              </div>
            ))
          )}
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={onClose}>Fechar</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function GradeBookSheet({ classId, onClose, t }: { classId: number; onClose: () => void; t: (k: string) => string }) {
  const { data = [], isLoading } = trpc.classes.getGradeBook.useQuery({ classId });

  return (
    <Sheet open onOpenChange={(o) => { if (!o) onClose(); }}>
      <SheetContent className="w-full sm:max-w-2xl overflow-y-auto">
        <SheetHeader>
          <SheetTitle>{t("classes.gradeBook")}</SheetTitle>
        </SheetHeader>
        <div className="mt-4">
          {isLoading ? (
            <p className="text-muted-foreground">{t("common.loading")}</p>
          ) : data.length === 0 ? (
            <p className="text-muted-foreground">{t("students.noStudents")}</p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Aluno</TableHead>
                  <TableHead>Prova</TableHead>
                  <TableHead>Nota</TableHead>
                  <TableHead>%</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {data.map(({ student, grades }: any) =>
                  grades.length === 0 ? (
                    <TableRow key={student.id}>
                      <TableCell className="font-medium">{student.firstName} {student.lastName}</TableCell>
                      <TableCell colSpan={3} className="text-muted-foreground text-sm">Sem notas</TableCell>
                    </TableRow>
                  ) : grades.map((g: any, idx: number) => (
                    <TableRow key={`${student.id}-${idx}`}>
                      {idx === 0 && <TableCell rowSpan={grades.length} className="font-medium align-top">{student.firstName} {student.lastName}</TableCell>}
                      <TableCell className="text-sm">{g.examName}</TableCell>
                      <TableCell>{g.score ?? "–"}</TableCell>
                      <TableCell className="text-muted-foreground text-sm">
                        {g.score && g.maxScore ? `${((Number(g.score) / Number(g.maxScore)) * 100).toFixed(1)}%` : "–"}
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          )}
        </div>
      </SheetContent>
    </Sheet>
  );
}
