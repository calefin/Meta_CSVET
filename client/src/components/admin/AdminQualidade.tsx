import { useState, useMemo } from "react";
import { trpc } from "@/lib/trpc";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { toast } from "sonner";
import { CheckSquare, Square, Loader2, RefreshCw, CheckCircle2, Search, ChevronDown, ChevronUp } from "lucide-react";

type IssueFilter = "no_subject" | "no_subject_tag" | "all_issues" | "all";

export default function AdminQualidade() {
  const [issue, setIssue] = useState<IssueFilter>("no_subject");
  const [disciplineId, setDisciplineId] = useState<string>("");
  const [currentSubjectId, setCurrentSubjectId] = useState<string>("");
  const [subjectTagSearch, setSubjectTagSearch] = useState("");
  const [page, setPage] = useState(1);

  const [selected, setSelected] = useState<Set<number>>(new Set());
  const [expanded, setExpanded] = useState<Set<number>>(new Set());
  const [patchDisciplineId, setPatchDisciplineId] = useState<string>("");
  const [patchSubjectId, setPatchSubjectId] = useState<string>("");
  const [patchTopicId, setPatchTopicId] = useState<string>("");
  const [patchSubjectTag, setPatchSubjectTag] = useState<string>("");

  const [showCreateSubject, setShowCreateSubject] = useState(false);
  const [newSubject, setNewSubject] = useState({ disciplineId: "", namePt: "", nameEn: "", slug: "" });
  const [showCreateTopic, setShowCreateTopic] = useState(false);
  const [newTopic, setNewTopic] = useState({ subjectId: "", disciplineId: "", namePt: "", nameEn: "", slug: "" });

  const { data: disciplines } = trpc.admin.disciplines.useQuery();
  const { data: subjects, refetch: refetchSubjects } = trpc.admin.subjects.useQuery();
  const { data: topicsData, refetch: refetchTopics } = trpc.admin.topics.useQuery(
    { subjectId: patchSubjectId && patchSubjectId !== "keep" ? Number(patchSubjectId) : undefined },
    { enabled: true }
  );
  const createSubjectMutation = trpc.admin.createSubject.useMutation();
  const createTopicMutation = trpc.admin.createTopic.useMutation();

  const filteredSubjectsByDiscipline = useMemo(() => {
    if (!disciplineId || disciplineId === "all") return subjects ?? [];
    return (subjects ?? []).filter((s: any) => String(s.disciplineId) === disciplineId);
  }, [subjects, disciplineId]);

  const queryInput = useMemo(() => ({
    issue,
    disciplineId: disciplineId && disciplineId !== "all" ? Number(disciplineId) : undefined,
    currentSubjectId: currentSubjectId === "none"
      ? null
      : currentSubjectId && currentSubjectId !== "all"
        ? Number(currentSubjectId)
        : undefined,
    subjectTagSearch: subjectTagSearch || undefined,
    page,
    limit: 50,
  }), [issue, disciplineId, currentSubjectId, subjectTagSearch, page]);

  const { data, isLoading, refetch } = trpc.admin.questionsWithIssues.useQuery(queryInput);
  const bulkPatch = trpc.admin.bulkPatchQuestions.useMutation();

  const filteredSubjectsForPatch = useMemo(() => {
    if (!patchDisciplineId || patchDisciplineId === "all") return subjects ?? [];
    return (subjects ?? []).filter((s: any) => String(s.disciplineId) === patchDisciplineId);
  }, [subjects, patchDisciplineId]);

  const rows = data?.rows ?? [];
  const total = data?.total ?? 0;
  const pageCount = Math.max(1, Math.ceil(total / 50));
  const allSelected = rows.length > 0 && rows.every((r: any) => selected.has(r.id));

  const toggleAll = () => {
    if (allSelected) {
      setSelected(prev => { const s = new Set(prev); rows.forEach((r: any) => s.delete(r.id)); return s; });
    } else {
      setSelected(prev => { const s = new Set(prev); rows.forEach((r: any) => s.add(r.id)); return s; });
    }
  };

  const toggleOne = (id: number) => {
    setSelected(prev => {
      const s = new Set(prev);
      if (s.has(id)) s.delete(id); else s.add(id);
      return s;
    });
  };

  const handleCreateSubject = async () => {
    if (!newSubject.disciplineId || !newSubject.namePt) { toast.error("Preencha Grande Área e nome PT"); return; }
    const slug = newSubject.slug || newSubject.namePt.toLowerCase().replace(/\s+/g, "-").replace(/[^a-z0-9-]/g, "");
    const nameEn = newSubject.nameEn || newSubject.namePt;
    try {
      await createSubjectMutation.mutateAsync({ disciplineId: Number(newSubject.disciplineId), namePt: newSubject.namePt, nameEn, slug });
      toast.success(`Assunto "${newSubject.namePt}" criado`);
      setNewSubject({ disciplineId: "", namePt: "", nameEn: "", slug: "" });
      setShowCreateSubject(false);
      refetchSubjects();
    } catch (err: any) {
      toast.error(err.message);
    }
  };

  const handleCreateTopic = async () => {
    if (!newTopic.subjectId || !newTopic.disciplineId || !newTopic.namePt) {
      toast.error("Preencha Grande Área, Assunto e nome PT"); return;
    }
    const slug = newTopic.slug || newTopic.namePt.toLowerCase().replace(/\s+/g, "-").replace(/[^a-z0-9-]/g, "");
    const nameEn = newTopic.nameEn || newTopic.namePt;
    try {
      await createTopicMutation.mutateAsync({
        subjectId: Number(newTopic.subjectId),
        disciplineId: Number(newTopic.disciplineId),
        namePt: newTopic.namePt, nameEn, slug,
      });
      toast.success(`Tópico "${newTopic.namePt}" criado`);
      setNewTopic({ subjectId: "", disciplineId: "", namePt: "", nameEn: "", slug: "" });
      setShowCreateTopic(false);
      refetchTopics();
    } catch (err: any) {
      toast.error(err.message);
    }
  };

  const handleApply = async () => {
    if (selected.size === 0) { toast.error("Selecione ao menos uma questão"); return; }
    const hasChange = patchDisciplineId || patchSubjectId || patchTopicId || patchSubjectTag;
    if (!hasChange) { toast.error("Defina ao menos um campo para atualizar"); return; }

    try {
      const result = await bulkPatch.mutateAsync({
        ids: Array.from(selected),
        disciplineId: patchDisciplineId && patchDisciplineId !== "keep" ? Number(patchDisciplineId) : undefined,
        subjectId: patchSubjectId === "clear" ? null : patchSubjectId && patchSubjectId !== "keep" ? Number(patchSubjectId) : undefined,
        topicId: patchTopicId === "clear" ? null : patchTopicId && patchTopicId !== "keep" ? Number(patchTopicId) : undefined,
        subjectTag: patchSubjectTag === "clear" ? null : patchSubjectTag || undefined,
      });
      toast.success(`${result.updated} questão(ões) atualizadas`);
      setSelected(new Set());
      setPatchDisciplineId("");
      setPatchSubjectId("");
      setPatchTopicId("");
      setPatchSubjectTag("");
      refetch();
    } catch (err: any) {
      toast.error(err.message);
    }
  };

  return (
    <div className="space-y-5">
      {/* Stats bar */}
      <div className="grid grid-cols-3 gap-3">
        <div className="bg-card border border-border/50 rounded-xl p-4">
          <div className="text-2xl font-bold text-amber-400">{total}</div>
          <div className="text-xs text-muted-foreground mt-1">questões com o filtro atual</div>
        </div>
        <div className="bg-card border border-border/50 rounded-xl p-4">
          <div className="text-2xl font-bold text-primary">{selected.size}</div>
          <div className="text-xs text-muted-foreground mt-1">selecionadas</div>
        </div>
        <div className="bg-card border border-border/50 rounded-xl p-4">
          <div className="text-2xl font-bold text-muted-foreground">{pageCount}</div>
          <div className="text-xs text-muted-foreground mt-1">páginas (50/pág)</div>
        </div>
      </div>

      {/* Filters */}
      <div className="bg-card border border-border/50 rounded-xl p-4 space-y-3">
        <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Filtros</p>
        <div className="flex flex-wrap gap-3">
          <Select value={issue} onValueChange={(v) => { setIssue(v as IssueFilter); setPage(1); setSelected(new Set()); }}>
            <SelectTrigger className="w-56 bg-background border-border/50 font-sans h-9">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">Todas as questões</SelectItem>
              <SelectItem value="no_subject">Sem assunto (subjectId nulo)</SelectItem>
              <SelectItem value="all_issues">Sem assunto ou sem tag</SelectItem>
            </SelectContent>
          </Select>

          <Select value={disciplineId || "all"} onValueChange={(v) => { setDisciplineId(v === "all" ? "" : v); setCurrentSubjectId(""); setPage(1); setSelected(new Set()); }}>
            <SelectTrigger className="w-52 bg-background border-border/50 font-sans h-9">
              <SelectValue placeholder="Grande Área" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">Todas as áreas</SelectItem>
              {disciplines?.map((d: any) => (
                <SelectItem key={d.id} value={String(d.id)}>{d.namePt}</SelectItem>
              ))}
            </SelectContent>
          </Select>

          <Select
            value={currentSubjectId || "all"}
            onValueChange={(v) => { setCurrentSubjectId(v === "all" ? "" : v); setPage(1); setSelected(new Set()); }}
          >
            <SelectTrigger className="w-52 bg-background border-border/50 font-sans h-9">
              <SelectValue placeholder="Assunto atual" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">Qualquer assunto</SelectItem>
              <SelectItem value="none">Sem assunto (nulo)</SelectItem>
              {filteredSubjectsByDiscipline.map((s: any) => (
                <SelectItem key={s.id} value={String(s.id)}>{s.namePt}</SelectItem>
              ))}
            </SelectContent>
          </Select>

          <div className="relative flex-1 min-w-[160px]">
            <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
            <Input
              value={subjectTagSearch}
              onChange={(e) => { setSubjectTagSearch(e.target.value); setPage(1); }}
              placeholder="Buscar por subjectTag..."
              className="pl-8 h-9 bg-background border-border/50 text-sm font-sans"
            />
          </div>

          <Button variant="outline" size="sm" className="h-9 gap-1.5 font-sans" onClick={() => refetch()}>
            <RefreshCw className="h-3.5 w-3.5" /> Atualizar
          </Button>
        </div>
      </div>

      {/* Bulk action bar — shown when items selected */}
      {selected.size > 0 && (
        <div className="bg-primary/10 border border-primary/30 rounded-xl p-4 flex flex-wrap gap-3 items-end">
          <div className="flex items-center gap-2">
            <CheckCircle2 className="h-4 w-4 text-primary" />
            <span className="text-sm font-medium text-primary">{selected.size} selecionada(s)</span>
          </div>

          <div className="flex flex-wrap gap-2 flex-1">
            <Select value={patchDisciplineId || "keep"} onValueChange={(v) => { setPatchDisciplineId(v === "keep" ? "" : v); setPatchSubjectId(""); }}>
              <SelectTrigger className="w-48 bg-background border-border/50 font-sans h-9 text-sm">
                <SelectValue placeholder="Grande Área" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="keep">— Manter área —</SelectItem>
                {disciplines?.map((d: any) => (
                  <SelectItem key={d.id} value={String(d.id)}>{d.namePt}</SelectItem>
                ))}
              </SelectContent>
            </Select>

            <Select value={patchSubjectId || "keep"} onValueChange={(v) => setPatchSubjectId(v === "keep" ? "" : v)}>
              <SelectTrigger className="w-52 bg-background border-border/50 font-sans h-9 text-sm">
                <SelectValue placeholder="Disciplina" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="keep">— Manter disciplina —</SelectItem>
                <SelectItem value="clear">Limpar (definir nulo)</SelectItem>
                {filteredSubjectsForPatch.map((s: any) => (
                  <SelectItem key={s.id} value={String(s.id)}>{s.namePt}</SelectItem>
                ))}
              </SelectContent>
            </Select>

            <Select value={patchTopicId || "keep"} onValueChange={(v) => setPatchTopicId(v === "keep" ? "" : v)}>
              <SelectTrigger className="w-52 bg-background border-border/50 font-sans h-9 text-sm">
                <SelectValue placeholder="Tópico (capítulo)" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="keep">— Manter tópico —</SelectItem>
                <SelectItem value="clear">Limpar (definir nulo)</SelectItem>
                {(topicsData ?? []).map((t: any) => (
                  <SelectItem key={t.id} value={String(t.id)}>{t.namePt}</SelectItem>
                ))}
              </SelectContent>
            </Select>

            <div className="relative">
              <Input
                value={patchSubjectTag}
                onChange={(e) => setPatchSubjectTag(e.target.value)}
                placeholder="subjectTag (livre)"
                className="w-44 h-9 bg-background border-border/50 text-sm font-sans"
              />
            </div>
          </div>

          <Button
            size="sm"
            className="bg-primary text-primary-foreground gap-2 font-sans h-9"
            onClick={handleApply}
            disabled={bulkPatch.isPending}
          >
            {bulkPatch.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <CheckCircle2 className="h-4 w-4" />}
            Aplicar
          </Button>

          <Button
            size="sm"
            variant="ghost"
            className="font-sans h-9 text-muted-foreground"
            onClick={() => setSelected(new Set())}
          >
            Limpar seleção
          </Button>
        </div>
      )}

      {/* Create subject inline */}
      <div className="flex justify-end gap-4">
        <button
          onClick={() => { setShowCreateTopic(v => !v); setShowCreateSubject(false); }}
          className="text-xs text-primary/70 hover:text-primary font-sans flex items-center gap-1 transition-colors"
        >
          + Criar tópico (capítulo)
        </button>
        <button
          onClick={() => { setShowCreateSubject(v => !v); setShowCreateTopic(false); }}
          className="text-xs text-primary/70 hover:text-primary font-sans flex items-center gap-1 transition-colors"
        >
          + Criar assunto (disciplina)
        </button>
      </div>

      {showCreateSubject && (
        <div className="bg-card border border-border/50 rounded-xl p-4 space-y-3">
          <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Novo assunto</p>
          <div className="flex flex-wrap gap-3 items-end">
            <Select value={newSubject.disciplineId || "none"} onValueChange={(v) => setNewSubject(s => ({ ...s, disciplineId: v === "none" ? "" : v }))}>
              <SelectTrigger className="w-48 bg-background border-border/50 font-sans h-9 text-sm">
                <SelectValue placeholder="Grande Área *" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="none">— Selecionar —</SelectItem>
                {disciplines?.map((d: any) => (
                  <SelectItem key={d.id} value={String(d.id)}>{d.namePt}</SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Input
              value={newSubject.namePt}
              onChange={(e) => setNewSubject(s => ({ ...s, namePt: e.target.value }))}
              placeholder="Nome PT *"
              className="w-48 h-9 bg-background border-border/50 text-sm font-sans"
            />
            <Input
              value={newSubject.nameEn}
              onChange={(e) => setNewSubject(s => ({ ...s, nameEn: e.target.value }))}
              placeholder="Nome EN (opcional)"
              className="w-44 h-9 bg-background border-border/50 text-sm font-sans"
            />
            <Input
              value={newSubject.slug}
              onChange={(e) => setNewSubject(s => ({ ...s, slug: e.target.value }))}
              placeholder="Slug (auto)"
              className="w-36 h-9 bg-background border-border/50 text-sm font-sans font-mono"
            />
            <Button size="sm" className="h-9 bg-primary text-primary-foreground font-sans gap-1.5" onClick={handleCreateSubject} disabled={createSubjectMutation.isPending}>
              {createSubjectMutation.isPending ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : null}
              Criar
            </Button>
          </div>
        </div>
      )}

      {showCreateTopic && (
        <div className="bg-card border border-border/50 rounded-xl p-4 space-y-3">
          <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Novo tópico (capítulo / sistema)</p>
          <div className="flex flex-wrap gap-3 items-end">
            <Select value={newTopic.disciplineId || "none"} onValueChange={(v) => setNewTopic(s => ({ ...s, disciplineId: v === "none" ? "" : v, subjectId: "" }))}>
              <SelectTrigger className="w-44 bg-background border-border/50 font-sans h-9 text-sm">
                <SelectValue placeholder="Grande Área *" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="none">— Selecionar —</SelectItem>
                {disciplines?.map((d: any) => (
                  <SelectItem key={d.id} value={String(d.id)}>{d.namePt}</SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Select value={newTopic.subjectId || "none"} onValueChange={(v) => setNewTopic(s => ({ ...s, subjectId: v === "none" ? "" : v }))}>
              <SelectTrigger className="w-48 bg-background border-border/50 font-sans h-9 text-sm">
                <SelectValue placeholder="Assunto *" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="none">— Selecionar —</SelectItem>
                {(subjects ?? [])
                  .filter((s: any) => !newTopic.disciplineId || String(s.disciplineId) === newTopic.disciplineId)
                  .map((s: any) => (
                    <SelectItem key={s.id} value={String(s.id)}>{s.namePt}</SelectItem>
                  ))}
              </SelectContent>
            </Select>
            <Input
              value={newTopic.namePt}
              onChange={(e) => setNewTopic(s => ({ ...s, namePt: e.target.value }))}
              placeholder="Nome PT * (ex: Sistema Cardiovascular)"
              className="w-64 h-9 bg-background border-border/50 text-sm font-sans"
            />
            <Input
              value={newTopic.nameEn}
              onChange={(e) => setNewTopic(s => ({ ...s, nameEn: e.target.value }))}
              placeholder="Nome EN (opcional)"
              className="w-44 h-9 bg-background border-border/50 text-sm font-sans"
            />
            <Button size="sm" className="h-9 bg-primary text-primary-foreground font-sans gap-1.5" onClick={handleCreateTopic} disabled={createTopicMutation.isPending}>
              {createTopicMutation.isPending ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : null}
              Criar
            </Button>
          </div>
        </div>
      )}

      {/* Table */}
      <div className="bg-card border border-border/50 rounded-xl overflow-hidden">
        {/* Table header */}
        <div className="grid grid-cols-[32px_48px_1fr_160px_180px_120px_100px] gap-3 px-4 py-2.5 bg-muted/30 border-b border-border/50 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
          <button onClick={toggleAll} className="flex items-center">
            {allSelected
              ? <CheckSquare className="h-4 w-4 text-primary" />
              : <Square className="h-4 w-4" />}
          </button>
          <span>ID</span>
          <span>Enunciado</span>
          <span>Grande Área</span>
          <span>Disciplina</span>
          <span>SubjectTag</span>
          <span>Dificuldade</span>
        </div>

        {isLoading && (
          <div className="flex items-center justify-center gap-2 py-12 text-muted-foreground text-sm font-sans">
            <Loader2 className="h-4 w-4 animate-spin" /> Carregando...
          </div>
        )}

        {!isLoading && rows.length === 0 && (
          <div className="flex flex-col items-center justify-center py-16 gap-2">
            <CheckCircle2 className="h-10 w-10 text-primary/30" />
            <p className="text-muted-foreground text-sm font-sans">Nenhuma questão encontrada com este filtro</p>
          </div>
        )}

        {!isLoading && rows.map((row) => (
          <div
            key={row.id}
            onClick={() => toggleOne(row.id)}
            className={`grid grid-cols-[32px_48px_1fr_160px_180px_120px_100px] gap-3 px-4 py-3 border-b border-border/30 cursor-pointer transition-colors text-sm font-sans ${
              selected.has(row.id) ? "bg-primary/8" : "hover:bg-muted/20"
            }`}
          >
            <span className="flex items-center">
              {selected.has(row.id)
                ? <CheckSquare className="h-4 w-4 text-primary" />
                : <Square className="h-4 w-4 text-muted-foreground/50" />}
            </span>
            <span className="text-muted-foreground text-xs self-center">{row.id}</span>
            <span className="self-start text-xs leading-relaxed">
              <span className={expanded.has(row.id) ? "" : "line-clamp-2"}>{row.textPt}</span>
              <button
                onClick={(e) => { e.stopPropagation(); setExpanded(prev => { const s = new Set(prev); s.has(row.id) ? s.delete(row.id) : s.add(row.id); return s; }); }}
                className="flex items-center gap-0.5 mt-1 text-[10px] text-primary/60 hover:text-primary transition-colors"
              >
                {expanded.has(row.id)
                  ? <><ChevronUp className="h-3 w-3" /> recolher</>
                  : <><ChevronDown className="h-3 w-3" /> expandir</>}
              </button>
            </span>
            <span className="self-center text-xs text-muted-foreground truncate">{row.disciplineName ?? "—"}</span>
            <span className="self-center">
              {row.subjectName
                ? <span className="text-xs text-emerald-400">{row.subjectName}</span>
                : <Badge className="text-[10px] bg-amber-500/15 text-amber-400 border-amber-500/30 border">sem assunto</Badge>}
            </span>
            <span className="self-center text-xs truncate text-muted-foreground">
              {row.subjectTag ?? <span className="text-muted-foreground/40">—</span>}
            </span>
            <span className="self-center">
              <Badge variant="outline" className="text-[10px] font-sans">{row.difficulty}</Badge>
            </span>
          </div>
        ))}
      </div>

      {/* Pagination */}
      {pageCount > 1 && (
        <div className="flex items-center justify-center gap-3">
          <Button variant="outline" size="sm" className="font-sans" disabled={page === 1} onClick={() => setPage(p => p - 1)}>
            ← Anterior
          </Button>
          <span className="text-sm text-muted-foreground font-sans">
            {page} / {pageCount} ({total} questões)
          </span>
          <Button variant="outline" size="sm" className="font-sans" disabled={page >= pageCount} onClick={() => setPage(p => p + 1)}>
            Próxima →
          </Button>
        </div>
      )}
    </div>
  );
}
