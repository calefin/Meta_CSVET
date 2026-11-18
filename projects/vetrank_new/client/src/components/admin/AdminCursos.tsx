import { useState } from "react";
import { trpc } from "@/lib/trpc";
import { toast } from "sonner";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import {
  Dialog,
  DialogContent,
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
import {
  ChevronDown,
  ChevronRight,
  Edit,
  GraduationCap,
  Layers,
  Loader2,
  Plus,
  Trash2,
  UserCheck,
  UserMinus,
  Users,
  Video,
  FileText,
  HelpCircle,
  ArrowLeft,
  EyeOff,
} from "lucide-react";

// ─── Types ────────────────────────────────────────────────────────────────────

type CourseLevel = "beginner" | "intermediate" | "advanced";
type CourseStatus = "draft" | "published";
type LessonType = "video" | "ebook" | "questions";
type VideoProvider = "vimeo" | "panda" | "bunny" | "youtube" | "other";

interface CourseForm {
  slug: string;
  title: string;
  description: string;
  objectives: string;
  prerequisites: string;
  thumbnailUrl: string;
  trailerUrl: string;
  instructorName: string;
  instructorBio: string;
  instructorAvatarUrl: string;
  instructorSignatureUrl: string;
  level: CourseLevel;
  category: string;
  durationHours: number;
  price: number;
  status: CourseStatus;
}

const EMPTY_COURSE: CourseForm = {
  slug: "", title: "", description: "", objectives: "", prerequisites: "",
  thumbnailUrl: "", trailerUrl: "", instructorName: "", instructorBio: "",
  instructorAvatarUrl: "", instructorSignatureUrl: "", level: "beginner",
  category: "", durationHours: 0, price: 0, status: "draft",
};

const LEVEL_LABELS: Record<CourseLevel, string> = {
  beginner: "Iniciante", intermediate: "Intermediário", advanced: "Avançado",
};

const LESSON_TYPE_ICONS: Record<LessonType, typeof Video> = {
  video: Video, ebook: FileText, questions: HelpCircle,
};

const LESSON_TYPE_LABELS: Record<LessonType, string> = {
  video: "Vídeo", ebook: "Ebook", questions: "Questionário",
};

// ─── Course Form Dialog ────────────────────────────────────────────────────────

function CourseFormDialog({
  open, onClose, initial, editingId, onSaved,
}: {
  open: boolean;
  onClose: () => void;
  initial?: Partial<CourseForm>;
  editingId?: number;
  onSaved: () => void;
}) {
  const [form, setForm] = useState<CourseForm>({ ...EMPTY_COURSE, ...initial });
  const upsert = trpc.courses.admin.upsert.useMutation({
    onSuccess: () => { toast.success(editingId ? "Curso atualizado!" : "Curso criado!"); onSaved(); onClose(); },
    onError: (e) => toast.error(e.message),
  });

  const set = (k: keyof CourseForm, v: unknown) => setForm(f => ({ ...f, [k]: v }));

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{editingId ? "Editar Curso" : "Novo Curso"}</DialogTitle>
        </DialogHeader>
        <div className="space-y-4 mt-2">
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1">
              <label className="text-xs font-medium text-muted-foreground">Slug *</label>
              <Input placeholder="meu-curso" value={form.slug} onChange={e => set("slug", e.target.value)} />
            </div>
            <div className="space-y-1">
              <label className="text-xs font-medium text-muted-foreground">Título *</label>
              <Input placeholder="Nome do curso" value={form.title} onChange={e => set("title", e.target.value)} />
            </div>
          </div>

          <div className="space-y-1">
            <label className="text-xs font-medium text-muted-foreground">Descrição</label>
            <Textarea rows={3} placeholder="Descreva o curso..." value={form.description} onChange={e => set("description", e.target.value)} />
          </div>

          <div className="space-y-1">
            <label className="text-xs font-medium text-muted-foreground">Objetivos</label>
            <Textarea rows={2} placeholder="O que o aluno aprenderá..." value={form.objectives} onChange={e => set("objectives", e.target.value)} />
          </div>

          <div className="space-y-1">
            <label className="text-xs font-medium text-muted-foreground">Pré-requisitos</label>
            <Input placeholder="Nenhum" value={form.prerequisites} onChange={e => set("prerequisites", e.target.value)} />
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1">
              <label className="text-xs font-medium text-muted-foreground">URL da Thumbnail</label>
              <Input placeholder="https://..." value={form.thumbnailUrl} onChange={e => set("thumbnailUrl", e.target.value)} />
            </div>
            <div className="space-y-1">
              <label className="text-xs font-medium text-muted-foreground">URL do Trailer</label>
              <Input placeholder="https://..." value={form.trailerUrl} onChange={e => set("trailerUrl", e.target.value)} />
            </div>
          </div>

          <div className="grid grid-cols-3 gap-3">
            <div className="space-y-1">
              <label className="text-xs font-medium text-muted-foreground">Nome do Instrutor</label>
              <Input placeholder="Dr. João Silva" value={form.instructorName} onChange={e => set("instructorName", e.target.value)} />
            </div>
            <div className="space-y-1">
              <label className="text-xs font-medium text-muted-foreground">Avatar do Instrutor</label>
              <Input placeholder="https://..." value={form.instructorAvatarUrl} onChange={e => set("instructorAvatarUrl", e.target.value)} />
            </div>
            <div className="space-y-1">
              <label className="text-xs font-medium text-muted-foreground">Bio do Instrutor</label>
              <Input placeholder="Veterinário especialista..." value={form.instructorBio} onChange={e => set("instructorBio", e.target.value)} />
            </div>
          </div>

          <div className="space-y-1">
            <label className="text-xs font-medium text-muted-foreground">URL da Rubrica do Instrutor (assinatura no certificado)</label>
            <Input placeholder="https://storage.../rubrica-instrutor.png" value={form.instructorSignatureUrl} onChange={e => set("instructorSignatureUrl", e.target.value)} />
          </div>

          <div className="grid grid-cols-4 gap-3">
            <div className="space-y-1">
              <label className="text-xs font-medium text-muted-foreground">Nível *</label>
              <Select value={form.level} onValueChange={v => set("level", v)}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="beginner">Iniciante</SelectItem>
                  <SelectItem value="intermediate">Intermediário</SelectItem>
                  <SelectItem value="advanced">Avançado</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1">
              <label className="text-xs font-medium text-muted-foreground">Categoria</label>
              <Input placeholder="Clínica, Cirurgia..." value={form.category} onChange={e => set("category", e.target.value)} />
            </div>
            <div className="space-y-1">
              <label className="text-xs font-medium text-muted-foreground">Carga horária (h)</label>
              <Input type="number" min={0} value={form.durationHours} onChange={e => set("durationHours", Number(e.target.value))} />
            </div>
            <div className="space-y-1">
              <label className="text-xs font-medium text-muted-foreground">Preço (R$) *</label>
              <Input type="number" min={0} step={0.01} value={form.price} onChange={e => set("price", Number(e.target.value))} />
            </div>
          </div>

          <div className="space-y-1">
            <label className="text-xs font-medium text-muted-foreground">Status</label>
            <Select value={form.status} onValueChange={v => set("status", v)}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="draft">Rascunho</SelectItem>
                <SelectItem value="published">Publicado</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>
        <div className="flex justify-end gap-2 mt-4">
          <Button variant="outline" onClick={onClose}>Cancelar</Button>
          <Button
            onClick={() => upsert.mutate({ id: editingId, ...form })}
            disabled={upsert.isPending || !form.slug || !form.title}
          >
            {upsert.isPending && <Loader2 className="h-4 w-4 animate-spin mr-2" />}
            {editingId ? "Salvar alterações" : "Criar curso"}
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  );
}

// ─── Module + Lesson structure panel ──────────────────────────────────────────

function CourseStructurePanel({ courseId, onClose }: { courseId: number; onClose: () => void }) {
  const { data, refetch } = trpc.courses.admin.getOne.useQuery({ id: courseId });
  const [expandedModules, setExpandedModules] = useState<Set<number>>(new Set());
  const [addModDialog, setAddModDialog] = useState(false);
  const [editMod, setEditMod] = useState<{ id?: number; title: string; order: number } | null>(null);
  const [addLessonMod, setAddLessonMod] = useState<number | null>(null);
  const [editLesson, setEditLesson] = useState<{
    id?: number; moduleId: number; title: string; type: LessonType; order: number; isRequired: boolean;
  } | null>(null);
  const [lessonContent, setLessonContent] = useState<{
    lessonId: number; type: LessonType;
  } | null>(null);

  const upsertModule = trpc.courses.admin.upsertModule.useMutation({
    onSuccess: () => { toast.success("Módulo salvo!"); refetch(); setAddModDialog(false); setEditMod(null); },
    onError: (e) => toast.error(e.message),
  });
  const deleteModule = trpc.courses.admin.deleteModule.useMutation({
    onSuccess: () => { toast.success("Módulo removido."); refetch(); },
    onError: (e) => toast.error(e.message),
  });
  const upsertLesson = trpc.courses.admin.upsertLesson.useMutation({
    onSuccess: () => { toast.success("Aula salva!"); refetch(); setAddLessonMod(null); setEditLesson(null); },
    onError: (e) => toast.error(e.message),
  });
  const deleteLesson = trpc.courses.admin.deleteLesson.useMutation({
    onSuccess: () => { toast.success("Aula removida."); refetch(); },
    onError: (e) => toast.error(e.message),
  });

  const toggleModule = (id: number) =>
    setExpandedModules(prev => {
      const next = new Set(prev);
      next.has(id) ? next.delete(id) : next.add(id);
      return next;
    });

  if (!data) return <div className="flex items-center justify-center h-40"><Loader2 className="h-6 w-6 animate-spin text-muted-foreground" /></div>;

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <button onClick={onClose} className="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
          <ArrowLeft className="h-4 w-4" /> Voltar à lista
        </button>
        <Button size="sm" onClick={() => { setEditMod({ title: "", order: (data.modules?.length ?? 0) + 1 }); setAddModDialog(true); }}>
          <Plus className="h-4 w-4 mr-1" /> Módulo
        </Button>
      </div>

      <div className="rounded-xl border border-border/50 overflow-hidden">
        <div className="bg-card/50 px-4 py-3 border-b border-border/40">
          <h3 className="font-semibold text-sm">{data.title}</h3>
          <p className="text-xs text-muted-foreground">{data.modules?.length ?? 0} módulos · {data.modules?.reduce((a: number, m: any) => a + (m.lessons?.length ?? 0), 0)} aulas</p>
        </div>

        {(!data.modules || data.modules.length === 0) && (
          <div className="py-12 text-center text-sm text-muted-foreground">
            <Layers className="h-8 w-8 mx-auto mb-2 opacity-30" />
            Nenhum módulo criado. Clique em "Módulo" para adicionar.
          </div>
        )}

        {data.modules?.map((mod: any) => (
          <div key={mod.id} className="border-b border-border/30 last:border-0">
            <div
              className="flex items-center gap-2 px-4 py-3 cursor-pointer hover:bg-muted/10"
              onClick={() => toggleModule(mod.id)}
            >
              {expandedModules.has(mod.id) ? <ChevronDown className="h-4 w-4 text-muted-foreground" /> : <ChevronRight className="h-4 w-4 text-muted-foreground" />}
              <span className="text-xs text-muted-foreground w-5">{mod.order}.</span>
              <span className="flex-1 font-medium text-sm">{mod.title}</span>
              <span className="text-xs text-muted-foreground">{mod.lessons?.length ?? 0} aulas</span>
              <button
                onClick={e => { e.stopPropagation(); setEditMod({ id: mod.id, title: mod.title, order: mod.order }); setAddModDialog(true); }}
                className="p-1 rounded hover:bg-accent text-muted-foreground hover:text-foreground"
              >
                <Edit className="h-3.5 w-3.5" />
              </button>
              <button
                onClick={e => { e.stopPropagation(); if (confirm("Remover módulo e todas as suas aulas?")) deleteModule.mutate({ id: mod.id }); }}
                className="p-1 rounded hover:bg-red-500/10 text-muted-foreground hover:text-red-500"
              >
                <Trash2 className="h-3.5 w-3.5" />
              </button>
            </div>

            {expandedModules.has(mod.id) && (
              <div className="pl-8 pb-2 pr-4 space-y-1">
                {mod.lessons?.map((lesson: any) => {
                  const Icon = LESSON_TYPE_ICONS[lesson.type as LessonType] ?? Video;
                  return (
                    <div key={lesson.id} className="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-muted/10 group">
                      <Icon className="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                      <span className="text-xs text-muted-foreground w-4">{lesson.order}.</span>
                      <span className="flex-1 text-sm">{lesson.title}</span>
                      <Badge variant="outline" className="text-[10px] px-1.5 py-0">{LESSON_TYPE_LABELS[lesson.type as LessonType]}</Badge>
                      {!lesson.isRequired && <Badge variant="secondary" className="text-[10px] px-1.5 py-0">Opcional</Badge>}
                      <button
                        onClick={() => setLessonContent({ lessonId: lesson.id, type: lesson.type })}
                        className="p-1 rounded hover:bg-accent text-muted-foreground hover:text-foreground opacity-0 group-hover:opacity-100"
                        title="Configurar conteúdo"
                      >
                        <Layers className="h-3.5 w-3.5" />
                      </button>
                      <button
                        onClick={() => setEditLesson({ id: lesson.id, moduleId: mod.id, title: lesson.title, type: lesson.type, order: lesson.order, isRequired: lesson.isRequired })}
                        className="p-1 rounded hover:bg-accent text-muted-foreground hover:text-foreground opacity-0 group-hover:opacity-100"
                      >
                        <Edit className="h-3.5 w-3.5" />
                      </button>
                      <button
                        onClick={() => { if (confirm("Remover esta aula?")) deleteLesson.mutate({ id: lesson.id }); }}
                        className="p-1 rounded hover:bg-red-500/10 text-muted-foreground hover:text-red-500 opacity-0 group-hover:opacity-100"
                      >
                        <Trash2 className="h-3.5 w-3.5" />
                      </button>
                    </div>
                  );
                })}
                <button
                  onClick={() => { setAddLessonMod(mod.id); setEditLesson({ moduleId: mod.id, title: "", type: "video", order: (mod.lessons?.length ?? 0) + 1, isRequired: true }); }}
                  className="flex items-center gap-1.5 px-3 py-1.5 text-xs text-muted-foreground hover:text-foreground hover:bg-muted/10 rounded-lg w-full"
                >
                  <Plus className="h-3.5 w-3.5" /> Adicionar aula
                </button>
              </div>
            )}
          </div>
        ))}
      </div>

      {/* Module dialog */}
      <Dialog open={addModDialog} onOpenChange={() => { setAddModDialog(false); setEditMod(null); }}>
        <DialogContent className="max-w-sm">
          <DialogHeader>
            <DialogTitle>{editMod?.id ? "Editar Módulo" : "Novo Módulo"}</DialogTitle>
          </DialogHeader>
          <div className="space-y-3 mt-2">
            <div className="space-y-1">
              <label className="text-xs font-medium text-muted-foreground">Título *</label>
              <Input placeholder="Ex: Introdução ao Curso" value={editMod?.title ?? ""} onChange={e => setEditMod(m => m ? { ...m, title: e.target.value } : m)} />
            </div>
            <div className="space-y-1">
              <label className="text-xs font-medium text-muted-foreground">Ordem</label>
              <Input type="number" min={1} value={editMod?.order ?? 1} onChange={e => setEditMod(m => m ? { ...m, order: Number(e.target.value) } : m)} />
            </div>
          </div>
          <div className="flex justify-end gap-2 mt-4">
            <Button variant="outline" onClick={() => { setAddModDialog(false); setEditMod(null); }}>Cancelar</Button>
            <Button
              onClick={() => editMod && upsertModule.mutate({ id: editMod.id, courseId, title: editMod.title, order: editMod.order })}
              disabled={upsertModule.isPending || !editMod?.title}
            >
              {upsertModule.isPending && <Loader2 className="h-4 w-4 animate-spin mr-2" />}
              Salvar
            </Button>
          </div>
        </DialogContent>
      </Dialog>

      {/* Lesson dialog */}
      <Dialog open={!!(addLessonMod || editLesson?.id)} onOpenChange={() => { setAddLessonMod(null); setEditLesson(null); }}>
        <DialogContent className="max-w-sm">
          <DialogHeader>
            <DialogTitle>{editLesson?.id ? "Editar Aula" : "Nova Aula"}</DialogTitle>
          </DialogHeader>
          <div className="space-y-3 mt-2">
            <div className="space-y-1">
              <label className="text-xs font-medium text-muted-foreground">Título *</label>
              <Input placeholder="Ex: Introdução" value={editLesson?.title ?? ""} onChange={e => setEditLesson(l => l ? { ...l, title: e.target.value } : l)} />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1">
                <label className="text-xs font-medium text-muted-foreground">Tipo</label>
                <Select value={editLesson?.type ?? "video"} onValueChange={v => setEditLesson(l => l ? { ...l, type: v as LessonType } : l)}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="video">Vídeo</SelectItem>
                    <SelectItem value="ebook">Ebook</SelectItem>
                    <SelectItem value="questions">Questionário</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1">
                <label className="text-xs font-medium text-muted-foreground">Ordem</label>
                <Input type="number" min={1} value={editLesson?.order ?? 1} onChange={e => setEditLesson(l => l ? { ...l, order: Number(e.target.value) } : l)} />
              </div>
            </div>
            <label className="flex items-center gap-2 text-sm cursor-pointer">
              <input type="checkbox" checked={editLesson?.isRequired ?? true} onChange={e => setEditLesson(l => l ? { ...l, isRequired: e.target.checked } : l)} />
              Obrigatória para conclusão
            </label>
          </div>
          <div className="flex justify-end gap-2 mt-4">
            <Button variant="outline" onClick={() => { setAddLessonMod(null); setEditLesson(null); }}>Cancelar</Button>
            <Button
              onClick={() => editLesson && upsertLesson.mutate({
                id: editLesson.id, moduleId: editLesson.moduleId, courseId,
                title: editLesson.title, type: editLesson.type,
                order: editLesson.order, isRequired: editLesson.isRequired,
              })}
              disabled={upsertLesson.isPending || !editLesson?.title}
            >
              {upsertLesson.isPending && <Loader2 className="h-4 w-4 animate-spin mr-2" />}
              Salvar
            </Button>
          </div>
        </DialogContent>
      </Dialog>

      {/* Lesson content config dialog */}
      {lessonContent && (
        <LessonContentDialog
          lessonId={lessonContent.lessonId}
          type={lessonContent.type}
          onClose={() => setLessonContent(null)}
        />
      )}
    </div>
  );
}

// ─── Lesson content config ────────────────────────────────────────────────────

function LessonContentDialog({ lessonId, type, onClose }: { lessonId: number; type: LessonType; onClose: () => void }) {
  const [videoUrl, setVideoUrl] = useState("");
  const [videoProvider, setVideoProvider] = useState<VideoProvider>("other");
  const [durationSeconds, setDurationSeconds] = useState(0);
  const [ebookUrl, setEbookUrl] = useState("");
  const [ebookName, setEbookName] = useState("");
  const [allowDownload, setAllowDownload] = useState(false);
  const [minQuestions, setMinQuestions] = useState(10);
  const [minPassingScore, setMinPassingScore] = useState(70);

  const setVideo = trpc.courses.admin.setLessonVideo.useMutation({
    onSuccess: () => { toast.success("Vídeo configurado!"); onClose(); },
    onError: (e) => toast.error(e.message),
  });
  const addEbook = trpc.courses.admin.addLessonEbook.useMutation({
    onSuccess: () => { toast.success("Ebook adicionado!"); onClose(); },
    onError: (e) => toast.error(e.message),
  });
  const setQuiz = trpc.courses.admin.setLessonQuestionsConfig.useMutation({
    onSuccess: () => { toast.success("Quiz configurado!"); onClose(); },
    onError: (e) => toast.error(e.message),
  });

  return (
    <Dialog open onOpenChange={onClose}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>
            {type === "video" ? "Configurar Vídeo" : type === "ebook" ? "Adicionar Ebook" : "Configurar Questionário"}
          </DialogTitle>
        </DialogHeader>

        {type === "video" && (
          <div className="space-y-3 mt-2">
            <div className="space-y-1">
              <label className="text-xs font-medium text-muted-foreground">URL do Vídeo *</label>
              <Input placeholder="https://vimeo.com/..." value={videoUrl} onChange={e => setVideoUrl(e.target.value)} />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1">
                <label className="text-xs font-medium text-muted-foreground">Provider</label>
                <Select value={videoProvider} onValueChange={v => setVideoProvider(v as VideoProvider)}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="vimeo">Vimeo</SelectItem>
                    <SelectItem value="panda">Panda Video</SelectItem>
                    <SelectItem value="bunny">Bunny.net</SelectItem>
                    <SelectItem value="youtube">YouTube</SelectItem>
                    <SelectItem value="other">Outro</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1">
                <label className="text-xs font-medium text-muted-foreground">Duração (segundos)</label>
                <Input type="number" min={0} value={durationSeconds} onChange={e => setDurationSeconds(Number(e.target.value))} />
              </div>
            </div>
            <div className="flex justify-end gap-2 mt-2">
              <Button variant="outline" onClick={onClose}>Cancelar</Button>
              <Button onClick={() => setVideo.mutate({ lessonId, videoUrl, provider: videoProvider, durationSeconds })} disabled={!videoUrl || setVideo.isPending}>
                {setVideo.isPending && <Loader2 className="h-4 w-4 animate-spin mr-2" />} Salvar
              </Button>
            </div>
          </div>
        )}

        {type === "ebook" && (
          <div className="space-y-3 mt-2">
            <div className="space-y-1">
              <label className="text-xs font-medium text-muted-foreground">URL do arquivo *</label>
              <Input placeholder="https://storage.../arquivo.pdf" value={ebookUrl} onChange={e => setEbookUrl(e.target.value)} />
            </div>
            <div className="space-y-1">
              <label className="text-xs font-medium text-muted-foreground">Nome do arquivo *</label>
              <Input placeholder="apostila-modulo-1.pdf" value={ebookName} onChange={e => setEbookName(e.target.value)} />
            </div>
            <label className="flex items-center gap-2 text-sm cursor-pointer">
              <input type="checkbox" checked={allowDownload} onChange={e => setAllowDownload(e.target.checked)} />
              Permitir download
            </label>
            <div className="flex justify-end gap-2 mt-2">
              <Button variant="outline" onClick={onClose}>Cancelar</Button>
              <Button onClick={() => addEbook.mutate({ lessonId, fileUrl: ebookUrl, fileName: ebookName, allowDownload, order: 1 })} disabled={!ebookUrl || !ebookName || addEbook.isPending}>
                {addEbook.isPending && <Loader2 className="h-4 w-4 animate-spin mr-2" />} Adicionar
              </Button>
            </div>
          </div>
        )}

        {type === "questions" && (
          <div className="space-y-3 mt-2">
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1">
                <label className="text-xs font-medium text-muted-foreground">Mín. de questões</label>
                <Input type="number" min={1} value={minQuestions} onChange={e => setMinQuestions(Number(e.target.value))} />
              </div>
              <div className="space-y-1">
                <label className="text-xs font-medium text-muted-foreground">Nota mínima (%)</label>
                <Input type="number" min={0} max={100} value={minPassingScore} onChange={e => setMinPassingScore(Number(e.target.value))} />
              </div>
            </div>
            <p className="text-xs text-muted-foreground">Os filtros de questões (disciplina, assunto, etc.) podem ser configurados via API.</p>
            <div className="flex justify-end gap-2 mt-2">
              <Button variant="outline" onClick={onClose}>Cancelar</Button>
              <Button onClick={() => setQuiz.mutate({ lessonId, filtersJson: {}, minQuestions, minPassingScore })} disabled={setQuiz.isPending}>
                {setQuiz.isPending && <Loader2 className="h-4 w-4 animate-spin mr-2" />} Salvar
              </Button>
            </div>
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}

// ─── Students panel ────────────────────────────────────────────────────────────

function CourseStudentsPanel({ courseId, courseTitle, onClose }: { courseId: number; courseTitle: string; onClose: () => void }) {
  const { data, refetch } = trpc.courses.admin.getStudents.useQuery({ courseId });
  const [grantEmail, setGrantEmail] = useState("");
  const grantAccess = trpc.courses.admin.grantAccess.useMutation({
    onSuccess: () => { toast.success("Acesso concedido!"); refetch(); },
    onError: (e) => toast.error(e.message),
  });
  const revokeAccess = trpc.courses.admin.revokeAccess.useMutation({
    onSuccess: () => { toast.success("Acesso revogado."); refetch(); },
    onError: (e) => toast.error(e.message),
  });

  const statusColor: Record<string, string> = {
    active: "text-green-600 bg-green-50 dark:bg-green-900/20",
    pending: "text-amber-600 bg-amber-50 dark:bg-amber-900/20",
    refunded: "text-red-600 bg-red-50 dark:bg-red-900/20",
    cancelled: "text-muted-foreground bg-muted/30",
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <button onClick={onClose} className="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
          <ArrowLeft className="h-4 w-4" /> Voltar à lista
        </button>
        <span className="text-sm font-medium">{courseTitle}</span>
      </div>

      <div className="flex gap-2">
        <Input placeholder="ID do usuário" value={grantEmail} onChange={e => setGrantEmail(e.target.value)} className="max-w-xs" />
        <Button
          size="sm"
          onClick={() => grantAccess.mutate({ userId: Number(grantEmail), courseId })}
          disabled={!grantEmail || grantAccess.isPending}
        >
          {grantAccess.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <UserCheck className="h-4 w-4 mr-1" />}
          Conceder acesso
        </Button>
      </div>

      <div className="rounded-xl border border-border/50 overflow-hidden">
        <table className="w-full text-sm">
          <thead>
            <tr className="bg-card/50 border-b border-border/40">
              <th className="text-left px-4 py-3 text-xs font-semibold text-muted-foreground">Aluno</th>
              <th className="text-left px-4 py-3 text-xs font-semibold text-muted-foreground">Pago</th>
              <th className="text-left px-4 py-3 text-xs font-semibold text-muted-foreground">Status</th>
              <th className="text-left px-4 py-3 text-xs font-semibold text-muted-foreground">Data</th>
              <th className="px-4 py-3" />
            </tr>
          </thead>
          <tbody>
            {!data && (
              <tr><td colSpan={5} className="py-12 text-center text-muted-foreground">
                <Loader2 className="h-5 w-5 animate-spin mx-auto" />
              </td></tr>
            )}
            {data?.length === 0 && (
              <tr><td colSpan={5} className="py-12 text-center text-sm text-muted-foreground">
                <Users className="h-8 w-8 mx-auto mb-2 opacity-30" />
                Nenhum aluno matriculado.
              </td></tr>
            )}
            {data?.map((s: any) => (
              <tr key={s.purchaseId} className="border-b border-border/20 hover:bg-muted/5">
                <td className="px-4 py-3">
                  <p className="font-medium">{s.userName ?? "—"}</p>
                  <p className="text-xs text-muted-foreground">{s.userEmail} · ID {s.userId}</p>
                </td>
                <td className="px-4 py-3 text-sm">R$ {Number(s.pricePaid).toFixed(2)}</td>
                <td className="px-4 py-3">
                  <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${statusColor[s.status] ?? ""}`}>
                    {s.status}
                  </span>
                </td>
                <td className="px-4 py-3 text-xs text-muted-foreground">
                  {s.purchasedAt ? new Date(s.purchasedAt).toLocaleDateString("pt-BR") : "—"}
                </td>
                <td className="px-4 py-3">
                  {s.status === "active" ? (
                    <button
                      onClick={() => { if (confirm(`Revogar acesso de ${s.userName}?`)) revokeAccess.mutate({ userId: s.userId, courseId }); }}
                      className="text-xs text-red-500 hover:text-red-700 flex items-center gap-1"
                      disabled={revokeAccess.isPending}
                    >
                      <UserMinus className="h-3.5 w-3.5" /> Revogar
                    </button>
                  ) : (
                    <button
                      onClick={() => grantAccess.mutate({ userId: s.userId, courseId })}
                      className="text-xs text-green-600 hover:text-green-800 flex items-center gap-1"
                      disabled={grantAccess.isPending}
                    >
                      <UserCheck className="h-3.5 w-3.5" /> Reativar
                    </button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

// ─── Main AdminCursos component ───────────────────────────────────────────────

type PanelView = "list" | "structure" | "students";

export default function AdminCursos() {
  const [view, setView] = useState<PanelView>("list");
  const [selectedCourse, setSelectedCourse] = useState<{ id: number; title: string } | null>(null);
  const [courseDialogOpen, setCourseDialogOpen] = useState(false);
  const [editingCourse, setEditingCourse] = useState<{ id?: number; initial?: Partial<CourseForm> }>({});
  const [deactivateConfirm, setDeactivateConfirm] = useState<number | null>(null);

  const { data: courses, refetch, isLoading } = trpc.courses.admin.listAll.useQuery();
  const deactivate = trpc.courses.admin.deactivate.useMutation({
    onSuccess: () => { toast.success("Curso desativado."); refetch(); setDeactivateConfirm(null); },
    onError: (e) => toast.error(e.message),
  });

  const openNew = () => { setEditingCourse({}); setCourseDialogOpen(true); };
  const openEdit = (course: any) => {
    setEditingCourse({
      id: course.id,
      initial: {
        slug: course.slug, title: course.title, level: course.level,
        category: course.category ?? "", price: Number(course.price),
        durationHours: course.durationHours, status: course.status,
        instructorName: course.instructorName ?? "",
        instructorSignatureUrl: course.instructorSignatureUrl ?? "",
      },
    });
    setCourseDialogOpen(true);
  };

  const statusBadge = (status: string) =>
    status === "published"
      ? <Badge className="bg-green-500/15 text-green-700 dark:text-green-400 border-0 text-[10px]">Publicado</Badge>
      : <Badge variant="secondary" className="text-[10px]">Rascunho</Badge>;

  if (view === "structure" && selectedCourse) {
    return <CourseStructurePanel courseId={selectedCourse.id} onClose={() => setView("list")} />;
  }

  if (view === "students" && selectedCourse) {
    return <CourseStudentsPanel courseId={selectedCourse.id} courseTitle={selectedCourse.title} onClose={() => setView("list")} />;
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="font-serif text-2xl font-bold">Cursos</h2>
          <p className="text-muted-foreground text-sm">Gerencie cursos, módulos, aulas e alunos</p>
        </div>
        <Button onClick={openNew}>
          <Plus className="h-4 w-4 mr-2" /> Novo Curso
        </Button>
      </div>

      {isLoading && (
        <div className="flex items-center justify-center h-48">
          <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
        </div>
      )}

      {!isLoading && (!courses || courses.length === 0) && (
        <div className="rounded-2xl border border-border/50 py-20 text-center">
          <GraduationCap className="h-12 w-12 mx-auto mb-3 text-muted-foreground/30" />
          <p className="text-muted-foreground">Nenhum curso criado ainda.</p>
          <Button className="mt-4" onClick={openNew}>
            <Plus className="h-4 w-4 mr-2" /> Criar primeiro curso
          </Button>
        </div>
      )}

      {courses && courses.length > 0 && (
        <div className="rounded-xl border border-border/50 overflow-hidden">
          <table className="w-full text-sm">
            <thead>
              <tr className="bg-card/50 border-b border-border/40">
                <th className="text-left px-4 py-3 text-xs font-semibold text-muted-foreground">Curso</th>
                <th className="text-left px-4 py-3 text-xs font-semibold text-muted-foreground">Nível</th>
                <th className="text-left px-4 py-3 text-xs font-semibold text-muted-foreground">Categoria</th>
                <th className="text-left px-4 py-3 text-xs font-semibold text-muted-foreground">Preço</th>
                <th className="text-left px-4 py-3 text-xs font-semibold text-muted-foreground">CH</th>
                <th className="text-left px-4 py-3 text-xs font-semibold text-muted-foreground">Status</th>
                <th className="px-4 py-3" />
              </tr>
            </thead>
            <tbody>
              {courses.map((c: any) => (
                <tr key={c.id} className="border-b border-border/20 hover:bg-muted/5">
                  <td className="px-4 py-3">
                    <p className="font-medium">{c.title}</p>
                    <p className="text-xs text-muted-foreground">{c.slug} · {c.instructorName ?? "—"}</p>
                  </td>
                  <td className="px-4 py-3 text-xs text-muted-foreground">{LEVEL_LABELS[c.level as CourseLevel]}</td>
                  <td className="px-4 py-3 text-xs text-muted-foreground">{c.category ?? "—"}</td>
                  <td className="px-4 py-3 font-medium">R$ {Number(c.price).toFixed(2)}</td>
                  <td className="px-4 py-3 text-xs text-muted-foreground">{c.durationHours}h</td>
                  <td className="px-4 py-3">{statusBadge(c.status)}</td>
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-1 justify-end">
                      <button
                        onClick={() => { setSelectedCourse({ id: c.id, title: c.title }); setView("structure"); }}
                        className="p-1.5 rounded-lg hover:bg-accent text-muted-foreground hover:text-foreground"
                        title="Gerenciar módulos e aulas"
                      >
                        <Layers className="h-4 w-4" />
                      </button>
                      <button
                        onClick={() => { setSelectedCourse({ id: c.id, title: c.title }); setView("students"); }}
                        className="p-1.5 rounded-lg hover:bg-accent text-muted-foreground hover:text-foreground"
                        title="Ver alunos"
                      >
                        <Users className="h-4 w-4" />
                      </button>
                      <button
                        onClick={() => openEdit(c)}
                        className="p-1.5 rounded-lg hover:bg-accent text-muted-foreground hover:text-foreground"
                        title="Editar curso"
                      >
                        <Edit className="h-4 w-4" />
                      </button>
                      <button
                        onClick={() => setDeactivateConfirm(c.id)}
                        className="p-1.5 rounded-lg hover:bg-red-500/10 text-muted-foreground hover:text-red-500"
                        title="Desativar curso"
                      >
                        <EyeOff className="h-4 w-4" />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <CourseFormDialog
        open={courseDialogOpen}
        onClose={() => setCourseDialogOpen(false)}
        editingId={editingCourse.id}
        initial={editingCourse.initial}
        onSaved={refetch}
      />

      <AlertDialog open={deactivateConfirm !== null} onOpenChange={() => setDeactivateConfirm(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Desativar curso?</AlertDialogTitle>
            <AlertDialogDescription>
              O curso será ocultado do catálogo. Alunos com acesso mantêm o acesso. Esta ação pode ser desfeita editando o curso.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              onClick={() => deactivateConfirm !== null && deactivate.mutate({ id: deactivateConfirm })}
            >
              Desativar
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
