import { useState } from "react";
import { useI18n } from "@/contexts/I18nContext";
import { trpc } from "@/lib/trpc";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/components/ui/accordion";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Building2, GraduationCap, School, Plus, Pencil, Trash2, BookOpen } from "lucide-react";
import BackButton from "@/components/BackButton";

type InstitutionType = "university" | "faculty" | "school";

const TYPE_ICONS: Record<InstitutionType, React.FC<{ className?: string }>> = {
  university: GraduationCap,
  faculty: Building2,
  school: School,
};

export default function Institutions() {
  const { t } = useI18n();
  const utils = trpc.useUtils();

  const { data: institutions = [], isLoading } = trpc.institutions.list.useQuery();

  const createMutation = trpc.institutions.create.useMutation({
    onSuccess: () => { utils.institutions.list.invalidate(); toast.success(t("institutions.messages.created")); setShowCreate(false); resetForm(); },
    onError: (e) => toast.error(e.message),
  });
  const updateMutation = trpc.institutions.update.useMutation({
    onSuccess: () => { utils.institutions.list.invalidate(); toast.success(t("institutions.messages.updated")); setEditId(null); resetForm(); },
    onError: (e) => toast.error(e.message),
  });
  const deleteMutation = trpc.institutions.delete.useMutation({
    onSuccess: () => { utils.institutions.list.invalidate(); toast.success(t("institutions.messages.deleted")); setDeleteId(null); },
    onError: (e) => toast.error(e.message),
  });

  const createDeptMutation = trpc.institutions.createDepartment.useMutation({
    onSuccess: (_, v) => { utils.institutions.listDepartments.invalidate({ institutionId: v.institutionId }); setShowDeptForm(null); setDeptName(""); toast.success(t("common.success")); },
  });
  const createCourseMutation = trpc.institutions.createCourse.useMutation({
    onSuccess: (_, v) => { utils.institutions.listCourses.invalidate({ institutionId: v.institutionId }); setShowCourseForm(null); setCourseName(""); toast.success(t("common.success")); },
  });

  const [showCreate, setShowCreate] = useState(false);
  const [editId, setEditId] = useState<number | null>(null);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [showDeptForm, setShowDeptForm] = useState<number | null>(null);
  const [showCourseForm, setShowCourseForm] = useState<number | null>(null);
  const [deptName, setDeptName] = useState("");
  const [courseName, setCourseName] = useState("");

  const [formName, setFormName] = useState("");
  const [formType, setFormType] = useState<InstitutionType>("university");
  const [formAddress, setFormAddress] = useState("");

  function resetForm() { setFormName(""); setFormType("university"); setFormAddress(""); }

  function openEdit(inst: typeof institutions[0]) {
    setFormName(inst.name);
    setFormType(inst.type as InstitutionType);
    setFormAddress(inst.address ?? "");
    setEditId(inst.id);
  }

  function handleSubmit() {
    if (!formName.trim()) return;
    if (editId) {
      updateMutation.mutate({ id: editId, name: formName, type: formType, address: formAddress || undefined });
    } else {
      createMutation.mutate({ name: formName, type: formType, address: formAddress || undefined });
    }
  }

  return (
    <div className="max-w-4xl mx-auto space-y-6">
      <BackButton />
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">{t("institutions.title")}</h1>
          <p className="text-muted-foreground text-sm">{t("institutions.subtitle")}</p>
        </div>
        <Button onClick={() => { resetForm(); setShowCreate(true); }}>
          <Plus className="w-4 h-4 mr-2" />
          {t("institutions.create")}
        </Button>
      </div>

      {isLoading && <p className="text-muted-foreground">{t("common.loading")}</p>}

      {!isLoading && institutions.length === 0 && (
        <Card>
          <CardContent className="flex flex-col items-center py-12 gap-4">
            <Building2 className="w-12 h-12 text-muted-foreground" />
            <p className="text-muted-foreground">{t("institutions.noInstitutions")}</p>
            <Button onClick={() => { resetForm(); setShowCreate(true); }}>
              <Plus className="w-4 h-4 mr-2" />{t("institutions.create")}
            </Button>
          </CardContent>
        </Card>
      )}

      <div className="space-y-4">
        {institutions.map((inst: any) => {
          const Icon = TYPE_ICONS[inst.type as InstitutionType] ?? Building2;
          return (
            <InstitutionCard
              key={inst.id}
              institution={inst}
              Icon={Icon}
              t={t}
              onEdit={() => openEdit(inst)}
              onDelete={() => setDeleteId(inst.id)}
              onAddDept={() => { setShowDeptForm(inst.id); setDeptName(""); }}
              onAddCourse={() => { setShowCourseForm(inst.id); setCourseName(""); }}
              showDeptForm={showDeptForm === inst.id}
              showCourseForm={showCourseForm === inst.id}
              deptName={deptName}
              courseName={courseName}
              setDeptName={setDeptName}
              setCourseName={setCourseName}
              onSubmitDept={() => createDeptMutation.mutate({ institutionId: inst.id, name: deptName })}
              onSubmitCourse={() => createCourseMutation.mutate({ institutionId: inst.id, name: courseName })}
              onCancelDept={() => setShowDeptForm(null)}
              onCancelCourse={() => setShowCourseForm(null)}
            />
          );
        })}
      </div>

      {/* Create/Edit dialog */}
      <Dialog open={showCreate || editId !== null} onOpenChange={(o) => { if (!o) { setShowCreate(false); setEditId(null); } }}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editId ? t("institutions.edit") : t("institutions.create")}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4 py-2">
            <div className="space-y-1">
              <Label>{t("institutions.name")}</Label>
              <Input value={formName} onChange={(e) => setFormName(e.target.value)} placeholder={t("institutions.name")} />
            </div>
            <div className="space-y-1">
              <Label>{t("institutions.type")}</Label>
              <Select value={formType} onValueChange={(v) => setFormType(v as InstitutionType)}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="university">{t("institutions.university")}</SelectItem>
                  <SelectItem value="faculty">{t("institutions.faculty")}</SelectItem>
                  <SelectItem value="school">{t("institutions.school")}</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1">
              <Label>{t("institutions.address")} <span className="text-muted-foreground text-xs">({t("common.optional")})</span></Label>
              <Input value={formAddress} onChange={(e) => setFormAddress(e.target.value)} placeholder={t("institutions.address")} />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => { setShowCreate(false); setEditId(null); }}>{t("common.cancel")}</Button>
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
    </div>
  );
}

function InstitutionCard({ institution, Icon, t, onEdit, onDelete, onAddDept, onAddCourse, showDeptForm, showCourseForm, deptName, courseName, setDeptName, setCourseName, onSubmitDept, onSubmitCourse, onCancelDept, onCancelCourse }: any) {
  const { data: departments = [] } = trpc.institutions.listDepartments.useQuery({ institutionId: institution.id });
  const { data: courses = [] } = trpc.institutions.listCourses.useQuery({ institutionId: institution.id });

  return (
    <Card>
      <CardHeader className="pb-2">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <Icon className="w-5 h-5 text-primary" />
            <div>
              <CardTitle className="text-base">{institution.name}</CardTitle>
              <p className="text-xs text-muted-foreground capitalize">{institution.type}</p>
            </div>
          </div>
          <div className="flex gap-1">
            <Button variant="ghost" size="icon" onClick={onEdit}><Pencil className="w-4 h-4" /></Button>
            <Button variant="ghost" size="icon" onClick={onDelete} className="text-destructive hover:text-destructive"><Trash2 className="w-4 h-4" /></Button>
          </div>
        </div>
        {institution.address && <p className="text-xs text-muted-foreground mt-1">{institution.address}</p>}
      </CardHeader>

      <CardContent>
        <Accordion type="multiple" className="space-y-1">
          <AccordionItem value="departments" className="border rounded-lg px-3">
            <AccordionTrigger className="text-sm py-2">
              <span className="flex items-center gap-2"><Building2 className="w-3.5 h-3.5" />{t("institutions.departments")} ({departments.length})</span>
            </AccordionTrigger>
            <AccordionContent>
              <div className="space-y-1 py-1">
                {departments.map((d: any) => (
                  <div key={d.id} className="text-sm px-2 py-1 rounded bg-muted/50">{d.name}</div>
                ))}
                {showDeptForm ? (
                  <div className="flex gap-2 mt-2">
                    <Input value={deptName} onChange={(e) => setDeptName(e.target.value)} placeholder={t("institutions.departments")} className="h-8 text-sm" />
                    <Button size="sm" onClick={onSubmitDept} disabled={!deptName.trim()}>{t("common.save")}</Button>
                    <Button size="sm" variant="outline" onClick={onCancelDept}>{t("common.cancel")}</Button>
                  </div>
                ) : (
                  <Button variant="ghost" size="sm" className="w-full mt-1 h-7 text-xs" onClick={onAddDept}>
                    <Plus className="w-3 h-3 mr-1" />{t("institutions.addDepartment")}
                  </Button>
                )}
              </div>
            </AccordionContent>
          </AccordionItem>

          <AccordionItem value="courses" className="border rounded-lg px-3">
            <AccordionTrigger className="text-sm py-2">
              <span className="flex items-center gap-2"><BookOpen className="w-3.5 h-3.5" />{t("institutions.courses")} ({courses.length})</span>
            </AccordionTrigger>
            <AccordionContent>
              <div className="space-y-1 py-1">
                {courses.map((c: any) => (
                  <div key={c.id} className="text-sm px-2 py-1 rounded bg-muted/50">{c.name}</div>
                ))}
                {showCourseForm ? (
                  <div className="flex gap-2 mt-2">
                    <Input value={courseName} onChange={(e) => setCourseName(e.target.value)} placeholder={t("institutions.courses")} className="h-8 text-sm" />
                    <Button size="sm" onClick={onSubmitCourse} disabled={!courseName.trim()}>{t("common.save")}</Button>
                    <Button size="sm" variant="outline" onClick={onCancelCourse}>{t("common.cancel")}</Button>
                  </div>
                ) : (
                  <Button variant="ghost" size="sm" className="w-full mt-1 h-7 text-xs" onClick={onAddCourse}>
                    <Plus className="w-3 h-3 mr-1" />{t("institutions.addCourse")}
                  </Button>
                )}
              </div>
            </AccordionContent>
          </AccordionItem>
        </Accordion>
      </CardContent>
    </Card>
  );
}
