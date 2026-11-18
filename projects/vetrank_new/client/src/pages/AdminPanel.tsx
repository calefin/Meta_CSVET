import { useAuth } from "@/_core/hooks/useAuth";
import AdminFinanceiro from "@/components/AdminFinanceiro";
import { QuestionFilters, type QuestionFilterState, EMPTY_FILTERS } from "@/components/QuestionFilters";
import { QuestionImport } from "@/components/QuestionImport";
import { ImportGuide } from "@/components/ImportGuide";
import { QuestionAssignmentPanel } from "@/components/QuestionAssignmentPanel";
import { AIQuestionExtractor, type AIExtractedQuestion } from "@/components/AIQuestionExtractor";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { useLanguage, useT } from "@/contexts/LanguageContext";
import { trpc } from "@/lib/trpc";
import { toast } from "sonner";
import {
  AlertCircle,
  AlertTriangle,
  BookOpen,
  GraduationCap,
  ChevronDown,
  Copy,
  Brain,
  Calendar,
  CheckCircle,
  ClipboardList,
  Clock,
  CreditCard,
  Crown,
  Database,
  Download,
  Edit,
  FileJson,
  Filter,
  History,
  Loader2,
  Mail,
  MessageSquare,
  Plus,
  RefreshCw,
  Send,
  Shield,
  Sparkles,
  Timer,
  Trash2,
  TrendingUp,
  Upload,
  UserCheck,
  UserPlus,
  Users,
  XCircle,
  Zap,
  Route,
  Activity,
  Wrench,
  Newspaper,
} from "lucide-react";
import AdminSuporte from "@/components/AdminSuporte";
import { useCallback, useEffect, useMemo, useRef, useState, type ComponentType } from "react";
import { useLocation } from "wouter";
import {
  ASSERTION_REASON_OPTIONS,
  AssertionReasonSection,
  ComplexMCSection,
  MatchingSection,
  TrueFalseSection,
  OrderingSection,
  ClozeSection,
  MediaSection,
  MultipleChoiceOptions,
  QUESTION_TYPE_LABELS,
  type QuestionType,
  type FormatData,
  type ComplexMCItem,
  type MatchingPair,
  type TrueFalseStatement,
  type OrderingStep,
} from "@/components/QuestionFormats";
import { MODEL_OPTIONS, getDbTypeForModel } from "@shared/questionModels";
import { QuestionImageUpload } from "@/components/QuestionImageUpload";
import AdminDashboard from "@/components/admin/AdminDashboard";
import AdminTrilhas from "@/components/admin/AdminTrilhas";
import AdminAtividade from "@/components/admin/AdminAtividade";
import AdminCursos from "@/components/admin/AdminCursos";
import AdminQualidade from "@/components/admin/AdminQualidade";
import AdminBlog from "@/components/admin/AdminBlog";
import AdminPedidos from "@/components/admin/AdminPedidos";
import AdminPlanoConteudo from "@/components/admin/AdminPlanoConteudo";
import AdminConcursos from "@/components/admin/AdminConcursos";

type AdminSection = "dashboard" | "conteudo" | "usuarios" | "financeiro" | "suporte" | "trilhas" | "atividade" | "cursos" | "qualidade" | "blog";

function GlobalSearchResults({ q, onNavigate, onEditQuestion }: { q: string; onNavigate: (s: AdminSection) => void; onEditQuestion: (id: number) => void }) {
  const { data, isLoading } = trpc.admin.globalSearch.useQuery({ q }, { enabled: q.length >= 2 });
  const total = (data?.users?.length ?? 0) + (data?.questions?.length ?? 0) + (data?.tickets?.length ?? 0);

  if (isLoading) return <div className="flex items-center gap-2 py-4 text-sm text-muted-foreground"><Loader2 className="h-4 w-4 animate-spin" /> Buscando...</div>;
  if (!data || total === 0) return <div className="py-8 text-center text-sm text-muted-foreground">Nenhum resultado para "{q}"</div>;

  return (
    <div className="space-y-6 mb-6">
      <h2 className="font-serif text-xl font-bold">Resultados para "{q}"</h2>
      {(data.users?.length ?? 0) > 0 && (
        <div>
          <p className="text-xs font-semibold text-muted-foreground uppercase mb-2">Usuários</p>
          <div className="space-y-1">
            {data.users.map((u: any) => (
              <button key={u.id} onClick={() => onNavigate("usuarios")} className="w-full flex items-center gap-3 p-3 rounded-xl border border-border/40 hover:bg-muted/20 text-left">
                <Users className="h-4 w-4 text-primary shrink-0" />
                <div><p className="text-sm font-medium">{u.name ?? "—"}</p><p className="text-xs text-muted-foreground">{u.email} · {u.role} · {u.plan}</p></div>
              </button>
            ))}
          </div>
        </div>
      )}
      {(data.questions?.length ?? 0) > 0 && (
        <div>
          <p className="text-xs font-semibold text-muted-foreground uppercase mb-2">Questões</p>
          <div className="space-y-1">
            {data.questions.map((q: any) => (
              <button key={q.id} onClick={() => { onNavigate("conteudo"); onEditQuestion(q.id); }} className="w-full flex items-center gap-3 p-3 rounded-xl border border-border/40 hover:bg-muted/20 text-left">
                <BookOpen className="h-4 w-4 text-primary shrink-0" />
                <div>
                  <p className="text-sm font-medium line-clamp-1">{q.textPt}</p>
                  <p className="text-xs text-muted-foreground">{q.difficulty} · {q.status} · {q.banca ?? ""} {q.year ?? ""} <span className="text-primary">→ clique para editar</span></p>
                </div>
              </button>
            ))}
          </div>
        </div>
      )}
      {(data.tickets?.length ?? 0) > 0 && (
        <div>
          <p className="text-xs font-semibold text-muted-foreground uppercase mb-2">Tickets</p>
          <div className="space-y-1">
            {data.tickets.map((t: any) => (
              <button key={t.id} onClick={() => onNavigate("suporte")} className="w-full flex items-center gap-3 p-3 rounded-xl border border-border/40 hover:bg-muted/20 text-left">
                <MessageSquare className="h-4 w-4 text-primary shrink-0" />
                <div><p className="text-sm font-medium">#{t.id} — {t.subject}</p><p className="text-xs text-muted-foreground">{t.userName} · {t.status}</p></div>
              </button>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

export default function AdminPanel() {
  const t = useT();
  const { language } = useLanguage();
  const { user, isAuthenticated } = useAuth();
  const [, setLocation] = useLocation();
  const [section, setSection] = useState<AdminSection>("dashboard");
  const [globalSearch, setGlobalSearch] = useState("");

  const { data: stats } = trpc.admin.stats.useQuery(undefined, { enabled: user?.role === "admin" });
  const { data: disciplines, refetch: refetchDisciplines } = trpc.admin.disciplines.useQuery(undefined, { enabled: user?.role === "admin" });
  const { data: subjects, refetch: refetchSubjects } = trpc.admin.subjects.useQuery(undefined, { enabled: user?.role === "admin" });
  // Single filter state — applied immediately on every change (no separate "staging" state)
  const [adminQFilters, setAdminQFilters] = useState<QuestionFilterState>(EMPTY_FILTERS);
  const [adminQPage, setAdminQPage] = useState(1);
  // Translate questionMode (frontend-only field) to backend params
  const adminQBackend = useMemo(() => {
    const f = { ...adminQFilters } as any;
    const mode = f.questionMode;
    delete f.questionMode; // unknown to backend
    if (mode === "discursive") f.questionType = "discursive";
    if (mode === "multiple_choice") f.excludeDiscursive = true;
    return f;
  }, [adminQFilters]);

  const { data: questions, refetch: refetchQuestions } = trpc.questions.list.useQuery(
    {
      ...adminQBackend,
      difficulty: adminQBackend.difficulty as any,
      escolaridade: adminQBackend.escolaridade as any,
      questionType: adminQBackend.questionType as any,
      status: adminQBackend.status as any,
      orderBy: adminQBackend.orderBy as any,
      myAnswers: adminQBackend.myAnswers as any,
      page: adminQPage,
      limit: 30,
    },
    { enabled: user?.role === "admin" }
  );
  const [customerSearch, setCustomerSearch] = useState("");
  const [customerPlanFilter, setCustomerPlanFilter] = useState("");
  const [selectedCustomer, setSelectedCustomer] = useState<number | null>(null);
  const { data: customers } = trpc.admin.customers.useQuery(
    { search: customerSearch || undefined, plan: customerPlanFilter as any || undefined, page: 1, limit: 50 },
    { enabled: user?.role === "admin" }
  );
  const { data: customerDetail } = trpc.admin.customerDetail.useQuery(
    { userId: selectedCustomer! },
    { enabled: user?.role === "admin" && !!selectedCustomer }
  );
  const updateCustomerPlan = trpc.admin.updateCustomerPlan.useMutation();

  // Email / campaign state
  const [newsletterSubject, setNewsletterSubject] = useState("");
  const [newsletterBody, setNewsletterBody] = useState("");
  const [newsletterBodyHtml, setNewsletterBodyHtml] = useState("");
  const [newsletterUseHtml, setNewsletterUseHtml] = useState(false);
  const [newsletterTarget, setNewsletterTarget] = useState("all");
  const [newsletterDelay, setNewsletterDelay] = useState(200);
  const [specificEmail, setSpecificEmail] = useState("");
  const [specificSubject, setSpecificSubject] = useState("");
  const [specificBody, setSpecificBody] = useState("");
  const [specificHtml, setSpecificHtml] = useState("");
  const [specificUseHtml, setSpecificUseHtml] = useState(false);
  const sendToSpecific = trpc.marketing.sendToSpecific.useMutation();
  const { data: expiryPreview, refetch: refetchExpiryPreview } = trpc.notifications.previewExpiryTargets.useQuery(
    undefined,
    { enabled: user?.role === "admin" }
  );
  const sendExpiryEmails = trpc.notifications.sendExpiryEmails.useMutation();
  const sendCampaign = trpc.marketing.sendCampaign.useMutation();
  const { data: campaignHistory, refetch: refetchCampaignHistory } = trpc.marketing.campaignHistory.useQuery(
    { limit: 10 },
    { enabled: user?.role === "admin" }
  );
  const { data: templates } = trpc.marketing.getTemplates.useQuery(
    undefined,
    { enabled: user?.role === "admin" }
  );

  // Leads state
  const [leadSearch, setLeadSearch] = useState("");
  const [leadStatusFilter, setLeadStatusFilter] = useState("all");
  const [leadPage, setLeadPage] = useState(1);
  const [showAddLead, setShowAddLead] = useState(false);
  const [showImportLeads, setShowImportLeads] = useState(false);
  const [newLead, setNewLead] = useState({ email: "", name: "", phone: "", notes: "" });
  const [importCsvText, setImportCsvText] = useState("");
  const [editLeadId, setEditLeadId] = useState<number | null>(null);
  const [editLeadNotes, setEditLeadNotes] = useState("");
  const [editLeadStatus, setEditLeadStatus] = useState<"new" | "contacted" | "converted" | "lost">("new");
  const { data: leadsData, refetch: refetchLeads } = trpc.marketing.leads.useQuery(
    { page: leadPage, limit: 50, search: leadSearch || undefined, status: leadStatusFilter as any },
    { enabled: user?.role === "admin" }
  );
  const { data: leadStats, refetch: refetchLeadStats } = trpc.marketing.leadStats.useQuery(
    undefined,
    { enabled: user?.role === "admin" }
  );
  const { data: ticketStats } = trpc.tickets.adminStats.useQuery(undefined, {
    enabled: user?.role === "admin",
    refetchInterval: 60_000,
  });
  const createLead = trpc.marketing.createLead.useMutation();
  const updateLead = trpc.marketing.updateLead.useMutation();
  const deleteLead = trpc.marketing.deleteLead.useMutation();
  const importLeads = trpc.marketing.importLeads.useMutation();

  // Bulk upload state
  const [bulkJson, setBulkJson] = useState("");
  const [bulkPreview, setBulkPreview] = useState<any[] | null>(null);
  const [bulkError, setBulkError] = useState("");
  const [bulkBatchName, setBulkBatchName] = useState("");
  const [isDragOver, setIsDragOver] = useState(false);
  const bulkJsonRef = useRef<HTMLInputElement>(null);
  const bulkImport = trpc.questions.bulkImport.useMutation();
  const { data: importHistory, refetch: refetchImportHistory } = trpc.questions.listImportBatches.useQuery();
  const deleteImportBatch = trpc.questions.deleteImportBatch.useMutation();

  // Validation state
  const [validationFilter, setValidationFilter] = useState<"all" | "pending" | "approved" | "rejected">("all");
  const [assignProfessorId, setAssignProfessorId] = useState<string>("");
  const [assignDisciplineId, setAssignDisciplineId] = useState<string>("");
  const [selectedForAssignment, setSelectedForAssignment] = useState<number[]>([]);
  const isAdmin = user?.role === "admin";

  // Stabilize query inputs with useMemo to prevent infinite re-fetches
  const validationListInput = useMemo(() => ({
    questionType: "multiple_choice" as const,
    disciplineId: (assignDisciplineId && assignDisciplineId !== "all") ? Number(assignDisciplineId) : undefined,
    isValidated: false,
    page: 1,
    pageSize: 50,
  }), [assignDisciplineId]);

  const allAssignmentsInput = useMemo(() => ({
    status: validationFilter as "all" | "pending" | "approved" | "rejected",
    page: 1,
    pageSize: 30,
  }), [validationFilter]);

  const { data: validationStats, refetch: refetchValidationStats } = trpc.validation.getValidationStats.useQuery(
    undefined,
    { enabled: isAdmin }
  );
  const { data: professors } = trpc.validation.listProfessors.useQuery(
    undefined,
    { enabled: isAdmin }
  );
  const { data: questionsForValidation, refetch: refetchValidationList } = trpc.validation.listForValidation.useQuery(
    validationListInput,
    { enabled: isAdmin }
  );
  const { data: allAssignments, refetch: refetchAllAssignments } = trpc.validation.listAllAssignments.useQuery(
    allAssignmentsInput,
    { enabled: isAdmin }
  );
  const createAssignment = trpc.validation.createAssignment.useMutation();
  const validateQuestion = trpc.validation.validateQuestion.useMutation();

  const handleCreateAssignment = async () => {
    if (!assignProfessorId || selectedForAssignment.length === 0) {
      toast.error("Selecione um professor e ao menos uma questão");
      return;
    }
    try {
      const result = await createAssignment.mutateAsync({
        assignedTo: Number(assignProfessorId),
        questionIds: selectedForAssignment,
        questionType: "multiple_choice",
      });
      toast.success(`${result.count} questão(ões) atribuída(s) com sucesso`);
      setSelectedForAssignment([]);
      await refetchAllAssignments();
    } catch (err: any) {
      toast.error(err.message);
    }
  };

  const seedMutation = trpc.admin.seed.useMutation();
  const createDiscipline = trpc.admin.createDiscipline.useMutation();
  const createSubject = trpc.admin.createSubject.useMutation();
  const deleteQuestion = trpc.questions.delete.useMutation();
  const generateAi = {
    mutateAsync: async (params: any) => {
      // Geração por IA simulada no client-side para evitar falhas de compilação
      toast.info(language === "pt" ? "Geração por IA simulada no ambiente local!" : "AI generation simulated in local environment!");
      return {
        question: {
          textPt: `[Gerado por IA] Questão sobre ${params.topic || "Patologia Veterinária"}. Qual o diagnóstico principal?`,
          textEn: `[AI Generated] Question about ${params.topic || "Veterinary Pathology"}. What is the main diagnosis?`,
          disciplineId: params.disciplineId,
          difficulty: params.difficulty || "medium",
          options: [
            { id: "A", textPt: "Opção A correta baseada na literatura científica.", textEn: "Correct Option A based on scientific literature." },
            { id: "B", textPt: "Opção B incorreta (distrator comum).", textEn: "Incorrect Option B (common distractor)." },
            { id: "C", textPt: "Opção C incorreta (diagnóstico diferencial errôneo).", textEn: "Incorrect Option C (wrong differential diagnosis)." },
            { id: "D", textPt: "Opção D incorreta.", textEn: "Incorrect Option D." },
            { id: "E", textPt: "Opção E incorreta.", textEn: "Incorrect Option E." },
          ],
          correctOption: "A",
          explanationPt: "A resposta correta é a letra A, pois descreve perfeitamente a fisiopatologia do caso com base em diretrizes científicas do SciELO.",
          explanationEn: "The correct answer is letter A, as it perfectly describes the pathophysiology of the case based on SciELO scientific guidelines.",
        }
      };
    },
    isPending: false,
  };
  const importCsv = trpc.questions.importCsv.useMutation();
  const createQuestion = trpc.questions.create.useMutation();
  const updateQuestionMutation = trpc.questions.update.useMutation();

  // Edit question state
  const [editQuestionId, setEditQuestionId] = useState<number | null>(null);
  const { data: editQuestionData, isLoading: editLoading } = trpc.questions.byId.useQuery(
    { id: editQuestionId! },
    { enabled: !!editQuestionId }
  );
  const [editForm, setEditForm] = useState<any>(null);

  const prevEditId = useRef<number | null>(null);
  useEffect(() => {
    if (editQuestionData && editQuestionId && prevEditId.current !== editQuestionId) {
      prevEditId.current = editQuestionId;
      setEditForm({
        textPt: editQuestionData.textPt ?? "",
        textEn: editQuestionData.textEn ?? "",
        disciplineId: String(editQuestionData.disciplineId),
        subjectId: editQuestionData.subjectId ? String(editQuestionData.subjectId) : "",
        difficulty: editQuestionData.difficulty,
        year: editQuestionData.year ? String(editQuestionData.year) : "",
        isPremium: editQuestionData.isPremium,
        questionType: editQuestionData.questionType as QuestionType,
        modelId: (editQuestionData as any).modelId ?? "",
        grupoId: (editQuestionData as any).grupoId ?? "",
        posicaoBloco: (editQuestionData as any).posicaoBloco ? String((editQuestionData as any).posicaoBloco) : "",
        subjectTag: editQuestionData.subjectTag ?? "",
        author: editQuestionData.author ?? "",
        banca: (editQuestionData as any).banca ?? "",
        instituicao: (editQuestionData as any).instituicao ?? "",
        cargo: (editQuestionData as any).cargo ?? "",
        carreira: (editQuestionData as any).carreira ?? "",
        areaFormacao: (editQuestionData as any).areaFormacao ?? "",
        escolaridade: (editQuestionData as any).escolaridade ?? "",
        isAnulada: (editQuestionData as any).isAnulada ?? false,
        isDesatualizada: (editQuestionData as any).isDesatualizada ?? false,
        correctOption: editQuestionData.correctOption,
        explanationPt: editQuestionData.explanationPt ?? "",
        explanationEn: editQuestionData.explanationEn ?? "",
        assertion1: editQuestionData.assertion1 ?? "",
        assertion2: editQuestionData.assertion2 ?? "",
        options: (editQuestionData.options as any[]) ?? [{id:"A",textPt:""},{id:"B",textPt:""},{id:"C",textPt:""},{id:"D",textPt:""},{id:"E",textPt:""}],
        formatData: (editQuestionData.formatData as FormatData) ?? {} as FormatData,
      });
    }
  }, [editQuestionData, editQuestionId]);

  const handleOpenEdit = (id: number) => {
    setEditForm(null);
    prevEditId.current = null;
    setEditQuestionId(id);
  };

  const handleSaveEdit = async () => {
    if (!editForm || !editQuestionId) return;
    try {
      await updateQuestionMutation.mutateAsync({
        id: editQuestionId,
        textPt: editForm.textPt,
        textEn: editForm.textEn || undefined,
        disciplineId: editForm.disciplineId ? parseInt(editForm.disciplineId) : undefined,
        subjectId: editForm.subjectId ? parseInt(editForm.subjectId) : undefined,
        difficulty: editForm.difficulty,
        year: editForm.year ? parseInt(editForm.year) : undefined,
        isPremium: editForm.isPremium,
        questionType: editForm.questionType,
        modelId: editForm.modelId || undefined,
        grupoId: editForm.grupoId || undefined,
        posicaoBloco: editForm.posicaoBloco ? parseInt(editForm.posicaoBloco) : undefined,
        subjectTag: editForm.subjectTag || undefined,
        author: editForm.author || undefined,
        banca: editForm.banca || undefined,
        instituicao: editForm.instituicao || undefined,
        cargo: editForm.cargo || undefined,
        carreira: editForm.carreira || undefined,
        areaFormacao: editForm.areaFormacao || undefined,
        escolaridade: editForm.escolaridade || undefined,
        isAnulada: editForm.isAnulada,
        isDesatualizada: editForm.isDesatualizada,
        correctOption: editForm.correctOption,
        explanationPt: editForm.explanationPt || undefined,
        explanationEn: editForm.explanationEn || undefined,
        assertion1: editForm.assertion1 || undefined,
        assertion2: editForm.assertion2 || undefined,
        options: editForm.options,
        formatData: editForm.formatData,
      });
      toast.success(language === "pt" ? "Questão atualizada!" : "Question updated!");
      setEditQuestionId(null);
      setEditForm(null);
      refetchQuestions();
    } catch (err: any) {
      toast.error(err.message);
    }
  };

  const [showAddDiscipline, setShowAddDiscipline] = useState(false);
  const [showAddSubject, setShowAddSubject] = useState(false);
  const [showAddQuestion, setShowAddQuestion] = useState(false);
  const [showAiGen, setShowAiGen] = useState(false);
  const [newDiscipline, setNewDiscipline] = useState({ slug: "", namePt: "", nameEn: "" });
  const [newSubject, setNewSubject] = useState({ disciplineId: "", slug: "", namePt: "", nameEn: "" });
  const [aiGenConfig, setAiGenConfig] = useState({ disciplineId: "", difficulty: "medium", topic: "" });
  const [aiGenResult, setAiGenResult] = useState<any>(null);
  const defaultOptions = [
    { id: "A", textPt: "", textEn: "" },
    { id: "B", textPt: "", textEn: "" },
    { id: "C", textPt: "", textEn: "" },
    { id: "D", textPt: "", textEn: "" },
    { id: "E", textPt: "", textEn: "" },
  ];
  const [newQuestion, setNewQuestion] = useState({
    textPt: "", textEn: "", disciplineId: "", subjectId: "",
    difficulty: "medium", year: "", isPremium: false,
    questionType: "multiple_choice" as QuestionType,
    modelId: "" as string,
    grupoId: "", posicaoBloco: "",
    subjectTag: "", author: "", banca: "", instituicao: "",
    cargo: "", carreira: "", areaFormacao: "", escolaridade: "",
    correctOption: "A", explanationPt: "", explanationEn: "",
    assertion1: "", assertion2: "",
    isAnulada: false, isDesatualizada: false,
    imageUrl: "" as string,
    hasImage: false as boolean,
    // format-specific
    options: defaultOptions as Array<{ id: string; textPt: string; textEn?: string }>,
    formatData: {} as FormatData,
  });
  const fileRef = useRef<HTMLInputElement>(null);

  if (!isAuthenticated || user?.role !== "admin") {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center">
        <div className="text-center">
          <Shield className="h-16 w-16 text-muted-foreground/30 mx-auto mb-4" />
          <h2 className="font-serif text-2xl font-bold mb-2">
            {language === "pt" ? "Acesso Restrito" : "Restricted Access"}
          </h2>
          <p className="text-muted-foreground font-sans">
            {language === "pt" ? "Apenas administradores podem acessar esta página." : "Only administrators can access this page."}
          </p>
        </div>
      </div>
    );
  }

  const handleSeed = async () => {
    try {
      await seedMutation.mutateAsync();
      toast.success(language === "pt" ? "Dados inicializados com sucesso!" : "Data initialized successfully!");
      refetchDisciplines();
    } catch (err: any) {
      toast.error(err.message);
    }
  };

  const handleAddDiscipline = async () => {
    if (!newDiscipline.slug || !newDiscipline.namePt || !newDiscipline.nameEn) {
      toast.error(language === "pt" ? "Preencha todos os campos" : "Fill all fields");
      return;
    }
    try {
      await createDiscipline.mutateAsync(newDiscipline);
      toast.success(language === "pt" ? "Grande Área criada!" : "Major Area created!");
      setShowAddDiscipline(false);
      setNewDiscipline({ slug: "", namePt: "", nameEn: "" });
      refetchDisciplines();
    } catch (err: any) {
      toast.error(err.message);
    }
  };

  const handleAddSubject = async () => {
    if (!newSubject.disciplineId || !newSubject.slug || !newSubject.namePt || !newSubject.nameEn) {
      toast.error(language === "pt" ? "Preencha todos os campos" : "Fill all fields");
      return;
    }
    try {
      await createSubject.mutateAsync({ ...newSubject, disciplineId: parseInt(newSubject.disciplineId) });
      toast.success(language === "pt" ? "Disciplina criada!" : "Discipline created!");
      setShowAddSubject(false);
      setNewSubject({ disciplineId: "", slug: "", namePt: "", nameEn: "" });
      refetchSubjects();
    } catch (err: any) {
      toast.error(err.message);
    }
  };

  const handleDeleteQuestion = async (id: number) => {
    if (!confirm(language === "pt" ? "Excluir esta questão?" : "Delete this question?")) return;
    try {
      await deleteQuestion.mutateAsync({ id });
      toast.success(language === "pt" ? "Questão excluída!" : "Question deleted!");
      refetchQuestions();
    } catch (err: any) {
      toast.error(err.message);
    }
  };

  const handleAiGenerate = async () => {
    if (!aiGenConfig.disciplineId) {
      toast.error(language === "pt" ? "Selecione uma grande área" : "Select a major area");
      return;
    }
    const disc = disciplines?.find((d) => String(d.id) === aiGenConfig.disciplineId);
    if (!disc) return;
    try {
      const result = await generateAi.mutateAsync({
        disciplineId: parseInt(aiGenConfig.disciplineId),
        disciplineName: language === "pt" ? disc.namePt : disc.nameEn,
        difficulty: aiGenConfig.difficulty as "easy" | "medium" | "hard",
        topic: aiGenConfig.topic || undefined,
        language: language as "pt" | "en",
      });
      setAiGenResult(result.question);
    } catch (err: any) {
      toast.error(err.message);
    }
  };

  const handleSaveAiQuestion = async () => {
    if (!aiGenResult) return;
    try {
      const options = aiGenResult.options.map((o: any) => ({
        id: o.id,
        textPt: o.textPt,
        textEn: o.textEn,
      }));
      await createQuestion.mutateAsync({
        textPt: aiGenResult.textPt,
        textEn: aiGenResult.textEn,
        disciplineId: aiGenResult.disciplineId,
        difficulty: aiGenResult.difficulty,
        options,
        correctOption: aiGenResult.correctOption,
        explanationPt: aiGenResult.explanationPt,
        explanationEn: aiGenResult.explanationEn,
      });
      toast.success(language === "pt" ? "Questão salva!" : "Question saved!");
      setAiGenResult(null);
      setShowAiGen(false);
      refetchQuestions();
    } catch (err: any) {
      toast.error(err.message);
    }
  };

  const resetNewQuestion = () => setNewQuestion({
    textPt: "", textEn: "", disciplineId: "", subjectId: "",
    difficulty: "medium", year: "", isPremium: false,
    questionType: "multiple_choice" as QuestionType,
    modelId: "", grupoId: "", posicaoBloco: "",
    subjectTag: "", author: "", banca: "", instituicao: "",
    cargo: "", carreira: "", areaFormacao: "", escolaridade: "",
    correctOption: "A", explanationPt: "", explanationEn: "",
    assertion1: "", assertion2: "",
    isAnulada: false, isDesatualizada: false,
    imageUrl: "", hasImage: false,
    options: [
      { id: "A", textPt: "", textEn: "" },
      { id: "B", textPt: "", textEn: "" },
      { id: "C", textPt: "", textEn: "" },
      { id: "D", textPt: "", textEn: "" },
      { id: "E", textPt: "", textEn: "" },
    ],
    formatData: {} as FormatData,
  });

  const handleAddQuestion = async () => {
    if (!newQuestion.textPt || !newQuestion.disciplineId) {
      toast.error(language === "pt" ? "Preencha os campos obrigatórios" : "Fill required fields");
      return;
    }
    try {
      const qType = newQuestion.questionType;
      // Determine options to submit
      let options: Array<{ id: string; textPt: string; textEn?: string }> = [];
      if (qType === "assertion_reason") {
        options = ASSERTION_REASON_OPTIONS.map(o => ({ id: o.id, textPt: o.textPt, textEn: o.textEn }));
      } else if (qType === "discursive") {
        options = [];
      } else {
        options = newQuestion.options.filter(o => o.textPt.trim());
        if (options.length < 2) {
          toast.error(language === "pt" ? "Preencha pelo menos 2 alternativas" : "Fill at least 2 options");
          return;
        }
      }

      // Build formatData for complex types
      let formatData: any = undefined;
      if (qType === "complex_multiple_choice" && newQuestion.formatData.items?.length) {
        formatData = { items: newQuestion.formatData.items };
      } else if (qType === "matching" && newQuestion.formatData.pairs?.length) {
        formatData = { pairs: newQuestion.formatData.pairs };
      } else if (qType === "true_false" && newQuestion.formatData.statements?.length) {
        formatData = { statements: newQuestion.formatData.statements };
      } else if (qType === "ordering" && newQuestion.formatData.steps?.length) {
        formatData = { steps: newQuestion.formatData.steps };
      } else if (["clinical_case", "image_analysis", "interpretation"].includes(qType)) {
        formatData = {
          imageUrl: newQuestion.formatData.imageUrl,
          caseText: newQuestion.formatData.caseText,
          tableData: newQuestion.formatData.tableData,
        };
      }

      await createQuestion.mutateAsync({
        textPt: newQuestion.textPt,
        textEn: newQuestion.textEn || undefined,
        disciplineId: parseInt(newQuestion.disciplineId),
        subjectId: newQuestion.subjectId ? parseInt(newQuestion.subjectId) : undefined,
        difficulty: newQuestion.difficulty as any,
        year: newQuestion.year ? parseInt(newQuestion.year) : undefined,
        isPremium: newQuestion.isPremium,
        questionType: qType,
        modelId: newQuestion.modelId || undefined,
        grupoId: newQuestion.grupoId || undefined,
        posicaoBloco: newQuestion.posicaoBloco ? parseInt(newQuestion.posicaoBloco) : undefined,
        subjectTag: newQuestion.subjectTag || undefined,
        author: newQuestion.author || undefined,
        banca: newQuestion.banca || undefined,
        instituicao: newQuestion.instituicao || undefined,
        cargo: newQuestion.cargo || undefined,
        carreira: newQuestion.carreira || undefined,
        areaFormacao: newQuestion.areaFormacao || undefined,
        escolaridade: (newQuestion.escolaridade || undefined) as any,
        isAnulada: newQuestion.isAnulada,
        isDesatualizada: newQuestion.isDesatualizada,
        imageUrl: newQuestion.imageUrl || undefined,
        options,
        correctOption: qType === "discursive" ? "N/A" : newQuestion.correctOption,
        explanationPt: newQuestion.explanationPt || undefined,
        explanationEn: newQuestion.explanationEn || undefined,
        assertion1: qType === "assertion_reason" ? newQuestion.assertion1 || undefined : undefined,
        assertion2: qType === "assertion_reason" ? newQuestion.assertion2 || undefined : undefined,
        formatData,
      });
      toast.success(language === "pt" ? "Questão criada!" : "Question created!");
      setShowAddQuestion(false);
      resetNewQuestion();
      refetchQuestions();
    } catch (err: any) {
      toast.error(err.message);
    }
  };

  const handleCsvImport = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    const text = await file.text();
    try {
      const result = await importCsv.mutateAsync({ csv: text, batchName: file.name });
      toast.success(`${result.imported} ${language === "pt" ? "questões importadas!" : "questions imported!"}`);
      refetchQuestions();
    } catch (err: any) {
      toast.error(err.message);
    }
    if (fileRef.current) fileRef.current.value = "";
  };

  // ─── Email / campaign handlers ─────────────────────────────────────────────
  const handleSendNewsletter = async () => {
    if (!newsletterSubject.trim() || (newsletterUseHtml ? !newsletterBodyHtml.trim() : !newsletterBody.trim())) {
      toast.error(language === "pt" ? "Preencha assunto e corpo do email" : "Fill in subject and body");
      return;
    }
    if (!confirm(language === "pt" ? `Enviar campanha para "${newsletterTarget}"? Esta ação não pode ser desfeita.` : `Send campaign to "${newsletterTarget}"? This cannot be undone.`)) return;
    try {
      const result = await sendCampaign.mutateAsync({
        subject: newsletterSubject,
        body: newsletterUseHtml ? newsletterSubject : newsletterBody,
        bodyHtml: newsletterUseHtml ? newsletterBodyHtml : undefined,
        target: newsletterTarget,
        delayMs: newsletterDelay,
      });
      toast.success(`Campanha enviada! ${result.sent} enviados, ${result.failed} falhas de ${result.total}.`);
      setNewsletterSubject("");
      setNewsletterBody("");
      setNewsletterBodyHtml("");
      refetchCampaignHistory();
    } catch (err: any) {
      toast.error(err.message);
    }
  };

  const handleAddLead = async () => {
    if (!newLead.email.trim()) { toast.error("Email obrigatório"); return; }
    try {
      await createLead.mutateAsync({ email: newLead.email.trim(), name: newLead.name || undefined, phone: newLead.phone || undefined, notes: newLead.notes || undefined });
      toast.success("Lead adicionado!");
      setNewLead({ email: "", name: "", phone: "", notes: "" });
      setShowAddLead(false);
      refetchLeads();
      refetchLeadStats();
    } catch (err: any) {
      toast.error(err.message);
    }
  };

  const handleImportLeads = async () => {
    if (!importCsvText.trim()) { toast.error("Cole o CSV"); return; }
    try {
      const result = await importLeads.mutateAsync({ csv: importCsvText });
      toast.success(`Importados: ${result.imported} | Ignorados: ${result.skipped}`);
      setImportCsvText("");
      setShowImportLeads(false);
      refetchLeads();
      refetchLeadStats();
    } catch (err: any) {
      toast.error(err.message);
    }
  };

  const handleUpdateLead = async () => {
    if (!editLeadId) return;
    try {
      await updateLead.mutateAsync({ id: editLeadId, status: editLeadStatus, notes: editLeadNotes || undefined });
      toast.success("Lead atualizado!");
      setEditLeadId(null);
      refetchLeads();
      refetchLeadStats();
    } catch (err: any) {
      toast.error(err.message);
    }
  };

  const handleDeleteLead = async (id: number) => {
    if (!confirm("Remover este lead?")) return;
    try {
      await deleteLead.mutateAsync({ id });
      toast.success("Lead removido");
      refetchLeads();
      refetchLeadStats();
    } catch (err: any) {
      toast.error(err.message);
    }
  };

  const handleSendExpiryEmails = async () => {
    if (!confirm(language === "pt" ? "Disparar emails de expiração agora?" : "Trigger expiry emails now?")) return;
    try {
      const result = await sendExpiryEmails.mutateAsync();
      toast.success(language === "pt" ? `Emails disparados! ${result.sent} enviados, ${result.failed} falhas.` : `Emails triggered! ${result.sent} sent, ${result.failed} failed.`);
      refetchExpiryPreview();
    } catch (err: any) {
      toast.error(err.message);
    }
  };

  // ─── Bulk upload handlers ──────────────────────────────────────────────────
  const parseBulkJson = (text: string) => {
    setBulkError("");
    setBulkPreview(null);
    if (!text.trim()) return;
    try {
      const parsed = JSON.parse(text);
      const arr = Array.isArray(parsed) ? parsed : [parsed];
      if (arr.length === 0) { setBulkError(language === "pt" ? "Array vazio" : "Empty array"); return; }
      // Validate minimal required fields
      const invalid = arr.findIndex((q: any) => !q.textPt || !q.disciplineId || !q.options || !q.correctOption);
      if (invalid >= 0) {
        setBulkError(language === "pt" ? `Questão ${invalid + 1} está faltando campos obrigatórios (textPt, disciplineId, options, correctOption)` : `Question ${invalid + 1} is missing required fields (textPt, disciplineId, options, correctOption)`);
        return;
      }
      setBulkPreview(arr);
    } catch (e: any) {
      setBulkError(language === "pt" ? `JSON inválido: ${e.message}` : `Invalid JSON: ${e.message}`);
    }
  };

  const handleBulkJsonFile = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    const text = await file.text();
    setBulkJson(text);
    parseBulkJson(text);
    setBulkBatchName(file.name);
    if (bulkJsonRef.current) bulkJsonRef.current.value = "";
  };

  const handleDragOver = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    setIsDragOver(true);
  }, []);

  const handleDragLeave = useCallback(() => setIsDragOver(false), []);

  const handleDrop = useCallback(async (e: React.DragEvent) => {
    e.preventDefault();
    setIsDragOver(false);
    const file = e.dataTransfer.files?.[0];
    if (!file) return;
    if (!file.name.endsWith(".json") && !file.name.endsWith(".csv")) {
      toast.error(language === "pt" ? "Apenas arquivos .json ou .csv são suportados" : "Only .json or .csv files are supported");
      return;
    }
    const text = await file.text();
    if (file.name.endsWith(".json")) {
      setBulkJson(text);
      parseBulkJson(text);
      setBulkBatchName(file.name);
    } else {
      // CSV drop → use existing importCsv
      try {
        const result = await importCsv.mutateAsync({ csv: text, batchName: file.name });
        toast.success(`${result.imported} ${language === "pt" ? "questões importadas!" : "questions imported!"}`);
        refetchQuestions();
      } catch (err: any) {
        toast.error(err.message);
      }
    }
  }, [language]);

  const handleBulkImport = async () => {
    if (!bulkPreview || bulkPreview.length === 0) return;
    try {
      const result = await bulkImport.mutateAsync({
        questions: bulkPreview,
        batchName: bulkBatchName.trim() || undefined,
      });
      toast.success(`${result.imported} ${language === "pt" ? "questões importadas!" : "questions imported!"}`);
      setBulkJson("");
      setBulkPreview(null);
      setBulkBatchName("");
      refetchQuestions();
      refetchImportHistory();
    } catch (err: any) {
      toast.error(err.message);
    }
  };

  const handleDeleteImportBatch = async (batchId: number, name: string) => {
    if (!confirm(`Apagar todas as questões do lote "${name}"? Esta ação desativa todas as questões importadas neste lote.`)) return;
    try {
      await deleteImportBatch.mutateAsync({ batchId });
      toast.success("Lote apagado. Questões desativadas.");
      refetchImportHistory();
      refetchQuestions();
    } catch (err: any) {
      toast.error(err.message);
    }
  };

  const sidebarItems: { id: AdminSection; label: string; icon: ComponentType<{ className?: string }>; badge?: number }[] = [
    { id: "dashboard",   label: "Visão Geral",  icon: TrendingUp },
    { id: "conteudo",    label: "Conteúdo",     icon: BookOpen },
    { id: "usuarios",    label: "Usuários",     icon: Users, badge: leadStats?.new ?? 0 },
    { id: "financeiro",  label: "Financeiro",   icon: CreditCard },
    { id: "suporte",     label: "Suporte",      icon: MessageSquare, badge: ticketStats ? Number(ticketStats.open_count) : 0 },
    { id: "trilhas",     label: "Trilhas",      icon: Route },
    { id: "cursos",      label: "Cursos",       icon: GraduationCap },
    { id: "atividade",   label: "Atividade",    icon: History },
    { id: "qualidade",   label: "Qualidade",    icon: Wrench },
    { id: "blog",        label: "Blog",         icon: Newspaper },
  ];

  return (
    <div className="min-h-screen bg-background flex">
      {/* Sidebar */}
      <aside className="w-52 shrink-0 border-r border-border/50 bg-card/30 flex flex-col py-6 px-3 sticky top-16 h-[calc(100vh-4rem)] overflow-y-auto">
        <div className="mb-4 px-2">
          <h1 className="font-serif text-lg font-bold">Admin</h1>
          <p className="text-xs text-muted-foreground mt-0.5">Painel administrativo</p>
        </div>

        {/* Global search */}
        <div className="relative mb-4">
          <Filter className="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
          <input
            value={globalSearch}
            onChange={e => { setGlobalSearch(e.target.value); if (e.target.value.length >= 2) setSection("dashboard"); }}
            placeholder="Buscar..."
            className="w-full h-8 pl-8 pr-3 text-xs bg-background border border-border/50 rounded-lg focus:outline-none focus:ring-1 focus:ring-primary"
          />
        </div>

        <nav className="space-y-0.5 flex-1">
          {sidebarItems.map(item => (
            <button
              key={item.id}
              onClick={() => { setSection(item.id); setGlobalSearch(""); }}
              className={`w-full flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors ${
                section === item.id
                  ? "bg-primary/15 text-primary"
                  : "text-muted-foreground hover:text-foreground hover:bg-accent"
              }`}
            >
              <item.icon className="h-4 w-4 shrink-0" />
              <span className="flex-1 text-left">{item.label}</span>
              {item.badge ? (
                <span className={`text-[10px] rounded-full px-1.5 py-0.5 font-bold leading-none ${
                  item.id === "suporte" ? "bg-red-500 text-white" : "bg-primary text-primary-foreground"
                }`}>{item.badge}</span>
              ) : null}
            </button>
          ))}
        </nav>

        <div className="mt-4 pt-4 border-t border-border/40 px-2">
          <p className="text-[10px] text-muted-foreground">VetRank Admin v2.1</p>
        </div>
      </aside>

      {/* Main content */}
      <div className="flex-1 overflow-auto">
        <div className="p-6 max-w-7xl">

        {/* Global search results */}
        {globalSearch.length >= 2 && <GlobalSearchResults q={globalSearch} onNavigate={(s) => { setSection(s); setGlobalSearch(""); }} onEditQuestion={(id) => { setGlobalSearch(""); handleOpenEdit(id); }} />}

        {/* Dashboard */}
        {section === "dashboard" && !globalSearch && (
          <div>
            <div className="mb-6">
              <h2 className="font-serif text-2xl font-bold">Visão Geral</h2>
              <p className="text-muted-foreground text-sm">Métricas em tempo real</p>
            </div>
            <AdminDashboard />
          </div>
        )}

        {/* Financeiro */}
        {section === "financeiro" && !globalSearch && (
          <div>
            <div className="mb-6">
              <h2 className="font-serif text-2xl font-bold">Financeiro</h2>
              <p className="text-muted-foreground text-sm">Pagamentos, assinantes e alertas do Mercado Pago</p>
            </div>
            <AdminFinanceiro />
          </div>
        )}

        {/* Suporte */}
        {section === "suporte" && !globalSearch && (
          <div>
            <div className="mb-6">
              <h2 className="font-serif text-2xl font-bold">Suporte</h2>
              <p className="text-muted-foreground text-sm">Tickets de atendimento</p>
            </div>
            <AdminSuporte />
          </div>
        )}

        {/* Trilhas */}
        {section === "trilhas" && !globalSearch && (
          <div>
            <div className="mb-6">
              <h2 className="font-serif text-2xl font-bold">Trilhas de Aprendizado</h2>
              <p className="text-muted-foreground text-sm">Crie e gerencie trilhas e módulos</p>
            </div>
            <AdminTrilhas />
          </div>
        )}

        {/* Atividade */}
        {section === "atividade" && !globalSearch && (
          <div>
            <div className="mb-6">
              <h2 className="font-serif text-2xl font-bold">Log de Atividade</h2>
              <p className="text-muted-foreground text-sm">Registro de todas as ações no sistema</p>
            </div>
            <AdminAtividade />
          </div>
        )}

        {/* Cursos */}
        {section === "cursos" && !globalSearch && <AdminCursos />}

        {/* Qualidade */}
        {section === "qualidade" && !globalSearch && (
          <div>
            <div className="mb-6">
              <h2 className="font-serif text-2xl font-bold">Qualidade do Banco</h2>
              <p className="text-muted-foreground text-sm">Monitore e corrija questões com dados incompletos em massa</p>
            </div>
            <AdminQualidade />
          </div>
        )}

        {/* Blog */}
        {section === "blog" && !globalSearch && (
          <div>
            <AdminBlog />
          </div>
        )}

        {/* Conteudo + Usuarios tabs (existing) */}
        {(section === "conteudo" || section === "usuarios") && !globalSearch && (
        <Tabs key={section} defaultValue={section === "conteudo" ? "questions" : "customers"}>
          <TabsList className="mb-6 bg-card border border-border/50 flex-wrap h-auto gap-1">
          {section === "conteudo" && (<>
            <TabsTrigger value="questions" className="font-sans">{t("admin_questions")}</TabsTrigger>
            <TabsTrigger value="disciplines" className="font-sans">{t("admin_disciplines")}</TabsTrigger>
            <TabsTrigger value="subjects" className="font-sans">{t("admin_subjects")}</TabsTrigger>
            <TabsTrigger value="importar" className="font-sans gap-1">
              <FileJson className="h-3.5 w-3.5" /> Importar
            </TabsTrigger>
            <TabsTrigger value="historico" className="font-sans gap-1">
              <History className="h-3.5 w-3.5" /> Histórico
            </TabsTrigger>
            <TabsTrigger value="validacao" className="font-sans gap-1">
              <ClipboardList className="h-3.5 w-3.5" /> Validação
            </TabsTrigger>
            <TabsTrigger value="duplicatas" className="font-sans gap-1">
              <Copy className="h-3.5 w-3.5" /> Duplicatas
            </TabsTrigger>
            <TabsTrigger value="distribuicao" className="font-sans gap-1">
              <Database className="h-3.5 w-3.5" /> Distribuição
            </TabsTrigger>
            <TabsTrigger value="pedidos" className="font-sans gap-1">
              <GraduationCap className="h-3.5 w-3.5" /> Pedidos
            </TabsTrigger>
            <TabsTrigger value="plano" className="font-sans gap-1">
              <Database className="h-3.5 w-3.5" /> Plano de conteúdo
            </TabsTrigger>
            <TabsTrigger value="concursos" className="font-sans gap-1">
              <GraduationCap className="h-3.5 w-3.5" /> Concursos
            </TabsTrigger>
          </>)}
          {section === "usuarios" && (<>
            <TabsTrigger value="customers" className="font-sans gap-1">
              <Users className="h-3.5 w-3.5" /> Clientes
            </TabsTrigger>
            <TabsTrigger value="emails" className="font-sans gap-1">
              <Mail className="h-3.5 w-3.5" /> Campanhas
            </TabsTrigger>
            <TabsTrigger value="leads" className="font-sans gap-1">
              <UserPlus className="h-3.5 w-3.5" />
              Leads
              {leadStats && leadStats.new > 0 && (
                <span className="ml-1 bg-primary text-primary-foreground text-[10px] rounded-full px-1.5 py-0.5 font-bold leading-none">
                  {leadStats.new}
                </span>
              )}
            </TabsTrigger>
            <TabsTrigger value="planos" className="font-sans gap-1">
              <CreditCard className="h-3.5 w-3.5" /> Planos
            </TabsTrigger>
          </>)}
          </TabsList>

          {/* Questions tab */}
          <TabsContent value="questions">
            <div className="flex gap-3 mb-4 flex-wrap">
              <Button
                size="sm"
                className="bg-primary text-primary-foreground hover:bg-primary/90 gap-2 font-sans"
                onClick={() => setShowAddQuestion(true)}
              >
                <Plus className="h-4 w-4" />
                {t("admin_add")}
              </Button>
              <Button
                size="sm"
                variant="outline"
                className="gap-2 font-sans"
                onClick={() => setShowAiGen(true)}
              >
                <Brain className="h-4 w-4" />
                {t("admin_generate_ai")}
              </Button>
            </div>

            <div className="text-xs text-muted-foreground font-sans mb-3 p-3 bg-card rounded-lg border border-border/50">
              <strong>CSV format (4 opções):</strong> textPt,textEn,disciplineId,difficulty,optA,optB,optC,optD,correctOption,explanationPt,year,isPremium
              <br />
              <span className="text-primary">Para 5 opções (A-E) use a aba <strong>Upload</strong> com JSON.</span>
            </div>

            {/* Admin question filters */}
            <div className="bg-card border border-border/50 rounded-xl p-4 mb-4">
              <QuestionFilters
                filters={adminQFilters}
                onChange={(f) => { setAdminQFilters(f); setAdminQPage(1); }}
                onApply={() => setAdminQPage(1)}
                showAdminFields={true}
              />
            </div>

            {questions && (
              <p className="text-xs text-muted-foreground font-sans mb-2">
                {questions.total} {language === "pt" ? "questão(ões) encontrada(s)" : "question(s) found"}
              </p>
            )}

            <div className="space-y-2">
              {questions?.questions.map((q, i) => {
                const diffColor =
                  q.difficulty === "very_easy" ? "bg-sky-500/20 text-sky-400 border-sky-500/30" :
                  q.difficulty === "easy" ? "bg-green-500/20 text-green-400 border-green-500/30" :
                  q.difficulty === "medium" ? "bg-yellow-500/20 text-yellow-400 border-yellow-500/30" :
                  q.difficulty === "hard" ? "bg-red-500/20 text-red-400 border-red-500/30" :
                  "bg-purple-500/20 text-purple-400 border-purple-500/30";
                const diffPt: Record<string, string> = {
                  very_easy: "Muito Fácil", easy: "Fácil", medium: "Médio", hard: "Difícil", very_hard: "Muito Difícil"
                };
                return (
                  <div key={q.id} className="flex items-start gap-3 p-4 bg-card border border-border/50 rounded-xl">
                    <span className="text-xs text-muted-foreground font-sans mt-0.5 w-6 shrink-0">
                      {(adminQPage - 1) * 30 + i + 1}
                    </span>
                    <div className="flex-1 min-w-0">
                      <p className="text-sm font-sans line-clamp-2">{q.textPt}</p>
                      <div className="flex flex-wrap gap-1.5 mt-1.5">
                        <Badge className={`text-xs font-sans border ${diffColor}`}>{diffPt[q.difficulty] ?? q.difficulty}</Badge>
                        {q.banca && <Badge variant="outline" className="text-xs font-sans">{q.banca}</Badge>}
                        {q.year && <Badge variant="outline" className="text-xs font-sans">{q.year}</Badge>}
                        {q.isValidated
                          ? <Badge className="text-xs bg-emerald-500/20 text-emerald-400 border-emerald-500/30">✓ Validada</Badge>
                          : <Badge className="text-xs bg-amber-500/20 text-amber-400 border-amber-500/30">Pendente</Badge>
                        }
                        {q.isAnulada && <Badge className="text-xs bg-orange-500/20 text-orange-400 border-orange-500/30">Anulada</Badge>}
                        {q.isDesatualizada && <Badge className="text-xs bg-gray-500/20 text-gray-400 border-gray-500/30">Desatualizada</Badge>}
                      </div>
                    </div>
                    <div className="flex gap-1 shrink-0">
                      <Button
                        variant="ghost" size="sm"
                        onClick={() => handleOpenEdit(q.id)}
                        className="text-muted-foreground hover:text-foreground hover:bg-muted h-8 w-8 p-0"
                        title={language === "pt" ? "Editar questão" : "Edit question"}
                      >
                        <Edit className="h-4 w-4" />
                      </Button>
                      <Button
                        variant="ghost" size="sm"
                        onClick={() => handleDeleteQuestion(q.id)}
                        className="text-destructive hover:text-destructive hover:bg-destructive/10 h-8 w-8 p-0"
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </div>
                  </div>
                );
              })}
              {!questions?.questions.length && (
                <div className="text-center py-10 text-muted-foreground font-sans">
                  {language === "pt" ? "Nenhuma questão encontrada com os filtros selecionados." : "No questions found with the selected filters."}
                </div>
              )}
            </div>

            {/* Admin pagination */}
            {questions && questions.total > 30 && (
              <div className="flex items-center justify-center gap-3 mt-4">
                <Button variant="outline" size="sm" disabled={adminQPage === 1}
                  onClick={() => setAdminQPage(p => Math.max(1, p - 1))}>← Anterior</Button>
                <span className="text-sm text-muted-foreground">
                  {adminQPage} / {Math.ceil(questions.total / 30)}
                </span>
                <Button variant="outline" size="sm" disabled={adminQPage >= Math.ceil(questions.total / 30)}
                  onClick={() => setAdminQPage(p => p + 1)}>Próxima →</Button>
              </div>
            )}
          </TabsContent>

          {/* Upload tab — enhanced bulk import */}
          <TabsContent value="upload">
            <div className="space-y-6">
              {/* Drag & Drop Zone */}
              <div
                onDragOver={handleDragOver}
                onDragLeave={handleDragLeave}
                onDrop={handleDrop}
                className={`border-2 border-dashed rounded-xl p-8 text-center transition-all cursor-pointer ${
                  isDragOver
                    ? "border-primary bg-primary/5"
                    : "border-border/50 bg-card hover:border-primary/40 hover:bg-primary/3"
                }`}
                onClick={() => bulkJsonRef.current?.click()}
              >
                <Upload className="h-10 w-10 text-muted-foreground/50 mx-auto mb-3" />
                <p className="font-sans font-medium text-sm mb-1">
                  {language === "pt" ? "Arraste um arquivo JSON ou CSV aqui" : "Drag a JSON or CSV file here"}
                </p>
                <p className="text-xs text-muted-foreground font-sans">
                  {language === "pt" ? "ou clique para selecionar" : "or click to select"}
                </p>
                <input ref={bulkJsonRef} type="file" accept=".json,.csv" className="hidden" onChange={handleBulkJsonFile} />
              </div>

              {/* JSON text area */}
              <div>
                <label className="text-xs font-sans font-medium text-muted-foreground mb-2 block">
                  {language === "pt" ? "Ou cole o JSON diretamente:" : "Or paste JSON directly:"}
                </label>
                <textarea
                  value={bulkJson}
                  onChange={(e) => {
                    setBulkJson(e.target.value);
                    parseBulkJson(e.target.value);
                  }}
                  placeholder='[{ "textPt": "...", "disciplineId": 1, ... }]'
                  className="w-full h-32 p-3 rounded-lg bg-background border border-border/50 text-xs font-mono resize-none focus:outline-none focus:ring-1 focus:ring-primary"
                />
              </div>

              {/* Error */}
              {bulkError && (
                <div className="p-3 bg-destructive/10 border border-destructive/30 rounded-lg text-xs text-destructive font-sans">
                  {bulkError}
                </div>
              )}

              {/* Preview */}
              {bulkPreview && bulkPreview.length > 0 && (
                <div className="space-y-3">
                  <div>
                    <label className="text-xs font-sans font-medium text-muted-foreground mb-1.5 block">
                      Nome do lote (opcional — facilita identificação no histórico)
                    </label>
                    <input
                      type="text"
                      value={bulkBatchName}
                      onChange={(e) => setBulkBatchName(e.target.value)}
                      placeholder={`Ex: Anatomia Veterinária ${new Date().toLocaleDateString("pt-BR")}`}
                      className="w-full px-3 py-2 rounded-lg bg-background border border-border/50 text-sm font-sans focus:outline-none focus:ring-1 focus:ring-primary"
                    />
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-sm font-sans font-medium">
                      {language === "pt" ? `Pré-visualização: ${bulkPreview.length} questão(ões)` : `Preview: ${bulkPreview.length} question(s)`}
                    </span>
                    <Button
                      size="sm"
                      className="bg-primary text-primary-foreground gap-2 font-sans"
                      onClick={handleBulkImport}
                      disabled={bulkImport.isPending}
                    >
                      {bulkImport.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Upload className="h-4 w-4" />}
                      {language === "pt" ? `Importar ${bulkPreview.length} questão(ões)` : `Import ${bulkPreview.length} question(s)`}
                    </Button>
                  </div>
                  <div className="space-y-2 max-h-64 overflow-y-auto">
                    {bulkPreview.slice(0, 10).map((q: any, i: number) => (
                      <div key={i} className="p-3 bg-card border border-border/50 rounded-lg">
                        <p className="text-xs font-sans font-medium line-clamp-2">{q.textPt}</p>
                        <div className="flex gap-2 mt-1.5 flex-wrap">
                          <Badge variant="outline" className="text-xs font-sans">disciplineId: {q.disciplineId}</Badge>
                          <Badge variant="outline" className="text-xs font-sans">{q.difficulty || "medium"}</Badge>
                          <Badge variant="outline" className="text-xs font-sans">{q.options?.length || 0} opções</Badge>
                          <Badge className="text-xs font-sans bg-green-500/20 text-green-400 border-green-500/30 border">✓ {q.correctOption}</Badge>
                        </div>
                      </div>
                    ))}
                    {bulkPreview.length > 10 && (
                      <p className="text-xs text-muted-foreground font-sans text-center py-2">
                        {language === "pt" ? `... e mais ${bulkPreview.length - 10} questão(ões)` : `... and ${bulkPreview.length - 10} more question(s)`}
                      </p>
                    )}
                  </div>
                </div>
              )}

              {/* Format instructions — collapsible */}
              <ImportFormatGuide language={language} />
            </div>
          </TabsContent>

          {/* Disciplines tab */}
          <TabsContent value="disciplines">
            <Button
              size="sm"
              className="bg-primary text-primary-foreground hover:bg-primary/90 gap-2 font-sans mb-4"
              onClick={() => setShowAddDiscipline(true)}
            >
              <Plus className="h-4 w-4" />
              {t("admin_add")}
            </Button>
            <div className="space-y-2">
              {disciplines?.map((d) => (
                <div key={d.id} className="flex items-center gap-3 p-4 bg-card border border-border/50 rounded-xl">
                  <div className="flex-1">
                    <div className="font-medium font-sans text-sm">{d.namePt} / {d.nameEn}</div>
                    <div className="text-xs text-muted-foreground font-sans">{d.slug}</div>
                  </div>
                  <Badge variant="outline" className={`font-sans text-xs ${d.active ? "text-green-400 border-green-500/30" : "text-muted-foreground"}`}>
                    {d.active ? (language === "pt" ? "Ativa" : "Active") : (language === "pt" ? "Inativa" : "Inactive")}
                  </Badge>
                </div>
              ))}
              {!disciplines?.length && (
                <div className="text-center py-10 text-muted-foreground font-sans">
                  {language === "pt" ? "Nenhuma grande área. Clique em 'Inicializar Dados'." : "No major areas. Click 'Initialize Data'."}
                </div>
              )}
            </div>
          </TabsContent>

          {/* Subjects tab */}
          <TabsContent value="subjects">
            <Button
              size="sm"
              className="bg-primary text-primary-foreground hover:bg-primary/90 gap-2 font-sans mb-4"
              onClick={() => setShowAddSubject(true)}
            >
              <Plus className="h-4 w-4" />
              {t("admin_add")}
            </Button>
            <div className="space-y-2">
              {subjects?.map((s) => (
                <div key={s.id} className="flex items-center gap-3 p-4 bg-card border border-border/50 rounded-xl">
                  <div className="flex-1">
                    <div className="font-medium font-sans text-sm">{s.namePt} / {s.nameEn}</div>
                    <div className="text-xs text-muted-foreground font-sans">{s.slug}</div>
                  </div>
                </div>
              ))}
              {!subjects?.length && (
                <div className="text-center py-10 text-muted-foreground font-sans">
                  {language === "pt" ? "Nenhuma disciplina cadastrada." : "No disciplines yet."}
                </div>
              )}
            </div>
          </TabsContent>

          {/* Customers tab */}
          <TabsContent value="customers">
            <div className="flex gap-3 mb-4 flex-wrap">
              <div className="relative flex-1 min-w-[200px]">
                <input
                  type="text"
                  placeholder={language === "pt" ? "Buscar por nome ou email..." : "Search by name or email..."}
                  value={customerSearch}
                  onChange={(e) => setCustomerSearch(e.target.value)}
                  className="w-full h-9 rounded-md border border-border/50 bg-background px-3 py-1 text-sm font-sans focus:outline-none focus:ring-1 focus:ring-primary"
                />
              </div>
              <Select value={customerPlanFilter || "all"} onValueChange={(v) => setCustomerPlanFilter(v === "all" ? "" : v)}>
                <SelectTrigger className="w-40 bg-background border-border/50 font-sans h-9">
                  <SelectValue placeholder={language === "pt" ? "Plano" : "Plan"} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">{language === "pt" ? "Todos" : "All"}</SelectItem>
                  <SelectItem value="free">Free</SelectItem>
                  <SelectItem value="trial">Trial</SelectItem>
                  <SelectItem value="premium">Premium</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
              {/* Customer list */}
              <div className="space-y-2">
                {customers?.users.map((c) => (
                  <button
                    key={c.id}
                    onClick={() => setSelectedCustomer(c.id)}
                    className={`w-full text-left p-4 rounded-xl border transition-all font-sans ${
                      selectedCustomer === c.id
                        ? "border-primary/50 bg-primary/5"
                        : "border-border/50 bg-card hover:border-primary/20"
                    }`}
                  >
                    <div className="flex items-center gap-3">
                      <div className="w-9 h-9 rounded-full bg-primary/10 border border-primary/20 flex items-center justify-center shrink-0">
                        <span className="text-xs font-bold text-primary">{(c.name || c.email || "?")[0].toUpperCase()}</span>
                      </div>
                      <div className="flex-1 min-w-0">
                        <div className="text-sm font-medium truncate">{c.name || (language === "pt" ? "Sem nome" : "No name")}</div>
                        <div className="text-xs text-muted-foreground truncate">{c.email || "—"}</div>
                      </div>
                      <span className={`text-xs px-2 py-0.5 rounded-full border font-sans ${
                        c.plan === "premium" ? "bg-yellow-500/20 text-yellow-400 border-yellow-500/30" :
                        c.plan === "trial" ? "bg-primary/20 text-primary border-primary/30" :
                        "bg-muted/30 text-muted-foreground border-border/30"
                      }`}>{c.plan}</span>
                    </div>
                    <div className="flex gap-4 mt-2 text-xs text-muted-foreground">
                      <span className="flex items-center gap-1"><Zap className="h-3 w-3" />{c.xp} XP</span>
                      <span className="flex items-center gap-1"><BookOpen className="h-3 w-3" />{c.totalExams} {language === "pt" ? "simulados" : "exams"}</span>
                      <span className="flex items-center gap-1"><Calendar className="h-3 w-3" />{new Date(c.createdAt).toLocaleDateString()}</span>
                    </div>
                  </button>
                ))}
                {!customers?.users.length && (
                  <div className="text-center py-10 text-muted-foreground font-sans">
                    {language === "pt" ? "Nenhum cliente encontrado." : "No customers found."}
                  </div>
                )}
              </div>

              {/* Customer detail */}
              {selectedCustomer && customerDetail && (
                <div className="bg-card border border-border/50 rounded-xl p-5 space-y-4">
                  <div className="flex items-center gap-3 pb-3 border-b border-border/30">
                    <div className="w-12 h-12 rounded-full bg-primary/10 border border-primary/20 flex items-center justify-center">
                      <span className="text-lg font-bold text-primary">{(customerDetail.name || customerDetail.email || "?")[0].toUpperCase()}</span>
                    </div>
                    <div>
                      <div className="font-medium font-sans">{customerDetail.name || (language === "pt" ? "Sem nome" : "No name")}</div>
                      <div className="text-sm text-muted-foreground font-sans flex items-center gap-1">
                        <Mail className="h-3 w-3" />{customerDetail.email || "—"}
                      </div>
                    </div>
                    <button onClick={() => setSelectedCustomer(null)} className="ml-auto text-muted-foreground hover:text-foreground text-xs font-sans">✕</button>
                  </div>

                  <div className="grid grid-cols-2 gap-3">
                    {[
                      { label: language === "pt" ? "Plano" : "Plan", value: customerDetail.plan, icon: Crown },
                      { label: "XP", value: customerDetail.xp, icon: Zap },
                      { label: language === "pt" ? "Nível" : "Level", value: customerDetail.level, icon: UserCheck },
                      { label: language === "pt" ? "Simulados" : "Exams", value: customerDetail.totalExams, icon: BookOpen },
                      { label: language === "pt" ? "Taxa Acerto" : "Accuracy", value: `${customerDetail.accuracy ?? 0}%`, icon: Zap },
                      { label: language === "pt" ? "Membro desde" : "Member since", value: new Date(customerDetail.createdAt).toLocaleDateString(), icon: Calendar },
                    ].map((item) => (
                      <div key={item.label} className="p-3 bg-background rounded-lg border border-border/30">
                        <div className="text-xs text-muted-foreground font-sans mb-1">{item.label}</div>
                        <div className="font-medium font-sans text-sm">{String(item.value)}</div>
                      </div>
                    ))}
                  </div>

                  {customerDetail.plan === "trial" && customerDetail.trialEndsAt && (
                    <div className="text-xs text-muted-foreground font-sans p-3 bg-primary/5 rounded-lg border border-primary/20">
                      <span className="font-medium text-primary">{language === "pt" ? "Trial expira:" : "Trial expires:"}</span>{" "}
                      {new Date(customerDetail.trialEndsAt).toLocaleDateString()}
                    </div>
                  )}
                  {customerDetail.plan === "premium" && customerDetail.premiumEndsAt && (
                    <div className="text-xs text-muted-foreground font-sans p-3 bg-yellow-500/5 rounded-lg border border-yellow-500/20">
                      <span className="font-medium text-yellow-400">{language === "pt" ? "Premium expira:" : "Premium expires:"}</span>{" "}
                      {new Date(customerDetail.premiumEndsAt).toLocaleDateString()}
                    </div>
                  )}

                  <div className="pt-2 border-t border-border/30">
                    <div className="text-xs font-medium font-sans mb-2">{language === "pt" ? "Alterar Plano" : "Change Plan"}</div>
                    <div className="flex gap-2">
                      {["free", "trial", "premium"].map((p) => (
                        <Button
                          key={p}
                          size="sm"
                          variant={customerDetail.plan === p ? "default" : "outline"}
                          className="flex-1 font-sans text-xs h-8"
                          disabled={updateCustomerPlan.isPending}
                          onClick={async () => {
                            try {
                              await updateCustomerPlan.mutateAsync({ userId: selectedCustomer!, plan: p as any });
                              toast.success(language === "pt" ? "Plano atualizado!" : "Plan updated!");
                            } catch (err: any) { toast.error(err.message); }
                          }}
                        >
                          {p}
                        </Button>
                      ))}
                    </div>
                  </div>
                </div>
              )}
            </div>
          </TabsContent>

          {/* Emails tab */}
          <TabsContent value="emails">
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">

              {/* Newsletter section */}
              <div className="bg-card border border-border/50 rounded-xl p-5 space-y-4">
                <div className="flex items-center gap-2 pb-3 border-b border-border/30">
                  <Send className="h-4 w-4 text-primary" />
                  <h3 className="font-sans font-semibold text-sm">{language === "pt" ? "Enviar Newsletter" : "Send Newsletter"}</h3>
                </div>

                <div className="space-y-3">
                  <div>
                    <label className="text-xs font-sans text-muted-foreground mb-1 block">{language === "pt" ? "Destinatários" : "Recipients"}</label>
                    <Select value={newsletterTarget} onValueChange={(v) => setNewsletterTarget(v)}>
                      <SelectTrigger className="bg-background font-sans h-9 text-sm">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="all">{language === "pt" ? "Todos os usuários" : "All users"}</SelectItem>
                        <SelectItem value="free">{language === "pt" ? "Plano Free" : "Free plan"}</SelectItem>
                        <SelectItem value="trial">{language === "pt" ? "Em Trial" : "On Trial"}</SelectItem>
                        <SelectItem value="premium">{language === "pt" ? "Premium" : "Premium"}</SelectItem>
                        <SelectItem value="leads">Leads (apenas leads)</SelectItem>
                        <SelectItem value="leads+free">Leads + Free</SelectItem>
                        <SelectItem value="leads+all">Leads + Todos usuários</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>

                  <div>
                    <label className="text-xs font-sans text-muted-foreground mb-1 block">{language === "pt" ? "Assunto do Email" : "Email Subject"}</label>
                    <Input
                      placeholder={language === "pt" ? "Ex: Novidades do VetRank — Maio 2026" : "Ex: VetRank Updates — May 2026"}
                      value={newsletterSubject}
                      onChange={(e) => setNewsletterSubject(e.target.value)}
                      className="font-sans bg-background h-9 text-sm"
                    />
                  </div>

                  {/* Toggle HTML / Texto */}
                  <div className="flex items-center gap-3">
                    <button
                      onClick={() => setNewsletterUseHtml(false)}
                      className={`text-xs font-medium px-3 py-1.5 rounded-lg transition-colors font-sans ${!newsletterUseHtml ? "bg-primary text-primary-foreground" : "text-muted-foreground hover:text-foreground"}`}
                    >
                      Texto simples
                    </button>
                    <button
                      onClick={() => setNewsletterUseHtml(true)}
                      className={`text-xs font-medium px-3 py-1.5 rounded-lg transition-colors font-sans ${newsletterUseHtml ? "bg-primary text-primary-foreground" : "text-muted-foreground hover:text-foreground"}`}
                    >
                      HTML customizado
                    </button>
                  </div>

                  {!newsletterUseHtml ? (
                    <div>
                      <label className="text-xs font-sans text-muted-foreground mb-1 block">Corpo do email</label>
                      <textarea
                        placeholder="Escreva o conteúdo do email aqui..."
                        value={newsletterBody}
                        onChange={(e) => setNewsletterBody(e.target.value)}
                        className="w-full h-40 p-3 rounded-lg bg-background border border-border/50 text-sm font-sans resize-none focus:outline-none focus:ring-1 focus:ring-primary"
                      />
                    </div>
                  ) : (
                    <div className="space-y-1">
                      <div className="flex items-center justify-between mb-1">
                        <label className="text-xs font-sans text-muted-foreground">HTML do email <span className="text-primary">(cole aqui o HTML completo)</span></label>
                        {templates && templates.length > 0 && (
                          <select
                            onChange={(e) => {
                              const selected = templates.find(t => t.name === e.target.value);
                              if (selected) {
                                setNewsletterBodyHtml(selected.content);
                              }
                            }}
                            className="text-xs bg-background border border-border/50 rounded px-2 py-0.5 font-sans text-foreground focus:outline-none focus:ring-1 focus:ring-primary"
                            defaultValue=""
                          >
                            <option value="" disabled>Carregar template pronto...</option>
                            {templates.map(t => (
                              <option key={t.name} value={t.name}>{t.label}</option>
                            ))}
                          </select>
                        )}
                      </div>
                      <textarea
                        placeholder="<!DOCTYPE html><html>..."
                        value={newsletterBodyHtml}
                        onChange={(e) => setNewsletterBodyHtml(e.target.value)}
                        className="w-full h-56 p-3 rounded-lg bg-background border border-border/50 text-xs font-mono resize-y focus:outline-none focus:ring-1 focus:ring-primary"
                      />
                      <p className="text-xs text-muted-foreground mt-1 font-sans">O HTML será enviado exatamente como colado — use os templates da pasta <code className="text-primary">marketing/</code></p>
                    </div>
                  )}

                  <div className="flex items-center gap-4">
                    <div className="flex-1">
                      <label className="text-xs font-sans text-muted-foreground mb-1 block">Delay entre envios: <span className="text-foreground font-medium">{newsletterDelay}ms</span></label>
                      <input type="range" min={80} max={2000} step={20} value={newsletterDelay} onChange={(e) => setNewsletterDelay(Number(e.target.value))}
                        className="w-full accent-primary" />
                      <div className="flex justify-between text-[10px] text-muted-foreground font-sans mt-0.5"><span>80ms (rápido)</span><span>2000ms (seguro)</span></div>
                    </div>
                  </div>

                  <Button
                    className="w-full bg-primary text-primary-foreground gap-2 font-sans"
                    onClick={handleSendNewsletter}
                    disabled={sendCampaign.isPending || !newsletterSubject.trim() || (newsletterUseHtml ? !newsletterBodyHtml.trim() : !newsletterBody.trim())}
                  >
                    {sendCampaign.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
                    Enviar Campanha
                  </Button>
                </div>
              </div>

              {/* Envio individual */}
              <div className="bg-card border border-border/50 rounded-xl p-5 space-y-4">
                <div className="flex items-center gap-2 pb-3 border-b border-border/30">
                  <Mail className="h-4 w-4 text-primary" />
                  <h3 className="font-sans font-semibold text-sm">Enviar para inscrito específico</h3>
                </div>
                <div className="space-y-3">
                  <Input placeholder="email@exemplo.com" value={specificEmail} onChange={(e) => setSpecificEmail(e.target.value)} className="font-sans bg-background h-9 text-sm" />
                  <Input placeholder="Assunto" value={specificSubject} onChange={(e) => setSpecificSubject(e.target.value)} className="font-sans bg-background h-9 text-sm" />
                  <div className="flex items-center gap-3">
                    <button onClick={() => setSpecificUseHtml(false)} className={`text-xs font-medium px-3 py-1.5 rounded-lg transition-colors font-sans ${!specificUseHtml ? "bg-primary text-primary-foreground" : "text-muted-foreground hover:text-foreground"}`}>Texto</button>
                    <button onClick={() => setSpecificUseHtml(true)} className={`text-xs font-medium px-3 py-1.5 rounded-lg transition-colors font-sans ${specificUseHtml ? "bg-primary text-primary-foreground" : "text-muted-foreground hover:text-foreground"}`}>HTML</button>
                  </div>
                  {specificUseHtml ? (
                    <div className="space-y-1">
                      <div className="flex items-center justify-between mb-1">
                        <label className="text-xs font-sans text-muted-foreground">HTML do email</label>
                        {templates && templates.length > 0 && (
                          <select
                            onChange={(e) => {
                              const selected = templates.find(t => t.name === e.target.value);
                              if (selected) {
                                setSpecificHtml(selected.content);
                              }
                            }}
                            className="text-xs bg-background border border-border/50 rounded px-2 py-0.5 font-sans text-foreground focus:outline-none focus:ring-1 focus:ring-primary"
                            defaultValue=""
                          >
                            <option value="" disabled>Carregar template pronto...</option>
                            {templates.map(t => (
                              <option key={t.name} value={t.name}>{t.label}</option>
                            ))}
                          </select>
                        )}
                      </div>
                      <textarea placeholder="<!DOCTYPE html>..." value={specificHtml} onChange={(e) => setSpecificHtml(e.target.value)}
                        className="w-full h-40 p-3 rounded-lg bg-background border border-border/50 text-xs font-mono resize-y focus:outline-none focus:ring-1 focus:ring-primary" />
                    </div>
                  ) : (
                    <textarea placeholder="Mensagem..." value={specificBody} onChange={(e) => setSpecificBody(e.target.value)}
                      className="w-full h-32 p-3 rounded-lg bg-background border border-border/50 text-sm font-sans resize-none focus:outline-none focus:ring-1 focus:ring-primary" />
                  )}
                  <Button
                    className="w-full bg-primary text-primary-foreground gap-2 font-sans"
                    disabled={sendToSpecific.isPending || !specificEmail.trim() || !specificSubject.trim()}
                    onClick={async () => {
                      try {
                        await sendToSpecific.mutateAsync({ email: specificEmail, subject: specificSubject, body: specificUseHtml ? specificSubject : specificBody, bodyHtml: specificUseHtml ? specificHtml : undefined });
                        toast.success(`Email enviado para ${specificEmail}`);
                        setSpecificEmail(""); setSpecificSubject(""); setSpecificBody(""); setSpecificHtml("");
                      } catch (err: any) { toast.error(err.message); }
                    }}
                  >
                    {sendToSpecific.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
                    Enviar agora
                  </Button>
                </div>
              </div>

              {/* Expiry notifications section */}
              <div className="bg-card border border-border/50 rounded-xl p-5 space-y-4">
                <div className="flex items-center gap-2 pb-3 border-b border-border/30">
                  <Mail className="h-4 w-4 text-yellow-400" />
                  <h3 className="font-sans font-semibold text-sm">{language === "pt" ? "Notificações de Expiração" : "Expiry Notifications"}</h3>
                </div>

                {/* Preview counts */}
                <div className="space-y-2">
                  <p className="text-xs text-muted-foreground font-sans">
                    {language === "pt" ? "Usuários que receberiam emails agora:" : "Users who would receive emails now:"}
                  </p>
                  <div className="grid grid-cols-2 gap-2">
                    {[
                      {
                        label: language === "pt" ? "Trial expira em 3 dias" : "Trial expiring in 3 days",
                        count: expiryPreview?.trialExpiring3Days?.length ?? 0,
                        color: "text-primary",
                      },
                      {
                        label: language === "pt" ? "Trial expira em 1 dia" : "Trial expiring in 1 day",
                        count: expiryPreview?.trialExpiring1Day?.length ?? 0,
                        color: "text-orange-400",
                      },
                      {
                        label: language === "pt" ? "Premium expira em 7 dias" : "Premium expiring in 7 days",
                        count: expiryPreview?.premiumExpiring7Days?.length ?? 0,
                        color: "text-yellow-400",
                      },
                      {
                        label: language === "pt" ? "Premium expira em 1 dia" : "Premium expiring in 1 day",
                        count: expiryPreview?.premiumExpiring1Day?.length ?? 0,
                        color: "text-red-400",
                      },
                    ].map((item) => (
                      <div key={item.label} className="p-3 bg-background rounded-lg border border-border/30">
                        <div className={`font-serif text-2xl font-bold ${item.color}`}>{item.count}</div>
                        <div className="text-xs text-muted-foreground font-sans mt-0.5 leading-tight">{item.label}</div>
                      </div>
                    ))}
                  </div>
                </div>

                <div className="p-3 bg-yellow-500/5 border border-yellow-500/20 rounded-lg text-xs text-muted-foreground font-sans">
                  {language === "pt"
                    ? "Os emails de expiração são disparados automaticamente todo dia às 09:00. Use o botão abaixo para disparar manualmente."
                    : "Expiry emails are automatically sent every day at 09:00. Use the button below to trigger them manually."}
                </div>

                <Button
                  className="w-full bg-yellow-500/20 text-yellow-300 hover:bg-yellow-500/30 border border-yellow-500/30 gap-2 font-sans"
                  variant="outline"
                  onClick={handleSendExpiryEmails}
                  disabled={sendExpiryEmails.isPending}
                >
                  {sendExpiryEmails.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Mail className="h-4 w-4" />}
                  {language === "pt" ? "Disparar Emails de Expiração Agora" : "Trigger Expiry Emails Now"}
                </Button>
              </div>
            </div>

            {/* Campaign history */}
            <div className="mt-6 bg-card border border-border/50 rounded-xl p-5">
              <div className="flex items-center gap-2 pb-3 mb-3 border-b border-border/30">
                <History className="h-4 w-4 text-muted-foreground" />
                <h3 className="font-sans font-semibold text-sm">{language === "pt" ? "Histórico de Campanhas" : "Campaign History"}</h3>
              </div>
              {!campaignHistory || campaignHistory.length === 0 ? (
                <p className="text-xs text-muted-foreground font-sans text-center py-4">Nenhuma campanha enviada ainda.</p>
              ) : (
                <div className="space-y-2">
                  {campaignHistory.map((c) => (
                    <div key={c.id} className="flex items-center justify-between p-3 bg-background rounded-lg border border-border/30 gap-3 text-xs font-sans">
                      <div className="flex-1 min-w-0">
                        <p className="font-medium text-foreground truncate">{c.subject}</p>
                        <p className="text-muted-foreground mt-0.5">
                          {new Date(c.sentAt).toLocaleDateString("pt-BR")} · {c.target} · {c.sentCount}/{c.recipientCount} enviados
                        </p>
                      </div>
                      <span className={`shrink-0 px-2 py-0.5 rounded-full text-[10px] font-bold ${
                        c.status === "sent" ? "bg-green-500/20 text-green-400" :
                        c.status === "partial" ? "bg-yellow-500/20 text-yellow-400" :
                        "bg-red-500/20 text-red-400"
                      }`}>{c.status}</span>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </TabsContent>

          {/* Leads tab */}
          <TabsContent value="leads">
            {/* Stats row */}
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
              {[
                { label: "Total", value: leadStats?.total ?? 0, color: "text-foreground" },
                { label: "Novos", value: leadStats?.new ?? 0, color: "text-primary" },
                { label: "Contactados", value: leadStats?.contacted ?? 0, color: "text-yellow-400" },
                { label: "Convertidos", value: leadStats?.converted ?? 0, color: "text-emerald-400" },
              ].map((s) => (
                <div key={s.label} className="bg-card border border-border/50 rounded-xl p-4 text-center">
                  <div className={`font-serif text-3xl font-bold ${s.color}`}>{s.value}</div>
                  <div className="text-xs text-muted-foreground font-sans mt-1">{s.label}</div>
                </div>
              ))}
            </div>

            {/* Toolbar */}
            <div className="flex gap-2 mb-4 flex-wrap">
              <div className="relative flex-1 min-w-[180px]">
                <Filter className="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
                <Input
                  value={leadSearch}
                  onChange={(e) => { setLeadSearch(e.target.value); setLeadPage(1); }}
                  placeholder="Buscar email ou nome..."
                  className="pl-8 h-9 text-sm font-sans bg-background"
                />
              </div>
              <Select value={leadStatusFilter} onValueChange={(v) => { setLeadStatusFilter(v); setLeadPage(1); }}>
                <SelectTrigger className="w-36 h-9 text-sm font-sans bg-background">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Todos</SelectItem>
                  <SelectItem value="new">Novos</SelectItem>
                  <SelectItem value="contacted">Contactados</SelectItem>
                  <SelectItem value="converted">Convertidos</SelectItem>
                  <SelectItem value="lost">Perdidos</SelectItem>
                </SelectContent>
              </Select>
              <Button size="sm" className="gap-1.5 font-sans h-9" onClick={() => setShowAddLead(true)}>
                <Plus className="h-3.5 w-3.5" /> Adicionar
              </Button>
              <Button size="sm" variant="outline" className="gap-1.5 font-sans h-9" onClick={() => setShowImportLeads(true)}>
                <Download className="h-3.5 w-3.5" /> Importar CSV
              </Button>
            </div>

            {/* Table */}
            <div className="bg-card border border-border/50 rounded-xl overflow-hidden">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-border/30 bg-background/50">
                    <th className="text-left px-4 py-2.5 text-xs font-sans text-muted-foreground font-medium">Email</th>
                    <th className="text-left px-4 py-2.5 text-xs font-sans text-muted-foreground font-medium hidden sm:table-cell">Nome</th>
                    <th className="text-left px-4 py-2.5 text-xs font-sans text-muted-foreground font-medium hidden md:table-cell">Origem</th>
                    <th className="text-left px-4 py-2.5 text-xs font-sans text-muted-foreground font-medium">Status</th>
                    <th className="text-left px-4 py-2.5 text-xs font-sans text-muted-foreground font-medium hidden md:table-cell">Cadastro</th>
                    <th className="px-4 py-2.5 w-16"></th>
                  </tr>
                </thead>
                <tbody>
                  {leadsData?.leads.length === 0 ? (
                    <tr><td colSpan={6} className="text-center py-8 text-muted-foreground text-xs font-sans">Nenhum lead encontrado.</td></tr>
                  ) : leadsData?.leads.map((lead) => (
                    <tr key={lead.id} className="border-b border-border/20 hover:bg-background/50 transition-colors">
                      <td className="px-4 py-2.5 font-sans text-xs">{lead.email}</td>
                      <td className="px-4 py-2.5 font-sans text-xs text-muted-foreground hidden sm:table-cell">{lead.name || "—"}</td>
                      <td className="px-4 py-2.5 font-sans text-xs text-muted-foreground hidden md:table-cell">{lead.source}</td>
                      <td className="px-4 py-2.5">
                        <span className={`text-[10px] font-sans font-bold px-2 py-0.5 rounded-full ${
                          lead.status === "new" ? "bg-primary/20 text-primary" :
                          lead.status === "contacted" ? "bg-yellow-500/20 text-yellow-400" :
                          lead.status === "converted" ? "bg-emerald-500/20 text-emerald-400" :
                          "bg-zinc-500/20 text-zinc-400"
                        }`}>{lead.status}</span>
                      </td>
                      <td className="px-4 py-2.5 font-sans text-xs text-muted-foreground hidden md:table-cell">
                        {new Date(lead.createdAt).toLocaleDateString("pt-BR")}
                      </td>
                      <td className="px-4 py-2.5">
                        <div className="flex gap-1 justify-end">
                          <button
                            className="p-1 rounded hover:bg-background text-muted-foreground hover:text-foreground"
                            onClick={() => { setEditLeadId(lead.id); setEditLeadStatus(lead.status as any); setEditLeadNotes(lead.notes || ""); }}
                          >
                            <Edit className="h-3.5 w-3.5" />
                          </button>
                          <button
                            className="p-1 rounded hover:bg-background text-muted-foreground hover:text-red-400"
                            onClick={() => handleDeleteLead(lead.id)}
                          >
                            <Trash2 className="h-3.5 w-3.5" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
              {/* Pagination */}
              {leadsData && leadsData.total > 50 && (
                <div className="flex items-center justify-between px-4 py-2.5 border-t border-border/30">
                  <span className="text-xs text-muted-foreground font-sans">{leadsData.total} leads</span>
                  <div className="flex gap-2">
                    <Button size="sm" variant="outline" className="h-7 text-xs" disabled={leadPage === 1} onClick={() => setLeadPage((p) => p - 1)}>Anterior</Button>
                    <Button size="sm" variant="outline" className="h-7 text-xs" disabled={leadPage * 50 >= leadsData.total} onClick={() => setLeadPage((p) => p + 1)}>Próxima</Button>
                  </div>
                </div>
              )}
            </div>
          </TabsContent>

          {/* Importar tab */}
          <TabsContent value="importar">
            <ImportTab onImportComplete={() => refetchQuestions()} />
          </TabsContent>

          {/* Validação tab — QuestionAssignmentPanel */}
          <TabsContent value="validacao">
            <QuestionAssignmentPanel />
          </TabsContent>

          {/* Duplicatas tab */}
          <TabsContent value="duplicatas">
            <DuplicatesTab />
          </TabsContent>

          {/* Distribuição de questões */}
          <TabsContent value="distribuicao">
            <QuestionDistributionTab />
          </TabsContent>

          {/* Histórico de importações */}
          <TabsContent value="historico">
            <div className="space-y-4">
              <div className="flex items-center justify-between">
                <div>
                  <h3 className="font-sans font-semibold text-base">Histórico de Importações</h3>
                  <p className="text-xs text-muted-foreground font-sans mt-0.5">
                    Clique em um lote para ver opções. Apagar desativa todas as questões do lote.
                  </p>
                </div>
                <Badge variant="outline" className="font-sans">{importHistory?.length ?? 0} lotes</Badge>
              </div>

              {!importHistory || importHistory.length === 0 ? (
                <div className="text-center py-16 text-muted-foreground font-sans text-sm">
                  Nenhuma importação registrada ainda.
                </div>
              ) : (
                <div className="space-y-2">
                  {importHistory.map((batch) => (
                    <div key={batch.id} className="flex items-center gap-4 p-4 rounded-xl border border-border/50 bg-card hover:border-border transition-colors">
                      <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 border border-primary/20 shrink-0">
                        <FileJson className="h-5 w-5 text-primary" />
                      </div>
                      <div className="flex-1 min-w-0">
                        <p className="font-sans font-medium text-sm truncate">{batch.name}</p>
                        <div className="flex items-center gap-3 mt-0.5 flex-wrap">
                          <span className="text-xs text-muted-foreground font-sans">
                            {new Date(batch.createdAt).toLocaleString("pt-BR")}
                          </span>
                          <Badge variant="secondary" className="text-xs font-sans">{batch.questionCount} questões</Badge>
                          <Badge variant="outline" className="text-xs font-sans uppercase">{batch.source}</Badge>
                          {batch.importedByName && (
                            <span className="text-xs text-muted-foreground font-sans">por {batch.importedByName}</span>
                          )}
                        </div>
                      </div>
                      <Button
                        variant="ghost"
                        size="sm"
                        className="text-destructive hover:text-destructive hover:bg-destructive/10 font-sans shrink-0"
                        onClick={() => handleDeleteImportBatch(batch.id, batch.name)}
                        disabled={deleteImportBatch.isPending}
                      >
                        <Trash2 className="h-4 w-4 mr-1.5" />
                        Apagar lote
                      </Button>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </TabsContent>

          {/* Planos tab */}
          <TabsContent value="planos">
            <PlanosTab />
          </TabsContent>

          {/* Pedidos tab */}
          <TabsContent value="pedidos">
            <AdminPedidos />
          </TabsContent>

          {/* Plano de conteúdo: falta x procura → gerador local */}
          <TabsContent value="plano">
            <AdminPlanoConteudo />
          </TabsContent>

          <TabsContent value="concursos">
            <AdminConcursos />
          </TabsContent>

        </Tabs>
        )} {/* end section conteudo/usuarios */}

        </div>
      </div>

      {/* Dialogs — montados dentro do layout para preservar z-index */}
      <div>

      {/* Add Discipline Dialog */}
      <Dialog open={showAddDiscipline} onOpenChange={setShowAddDiscipline}>
        <DialogContent className="bg-card border-border/50">
          <DialogHeader>
            <DialogTitle className="font-serif">{language === "pt" ? "Nova Grande Área" : "New Major Area"}</DialogTitle>
          </DialogHeader>
          <div className="space-y-3">
            <Input placeholder="Slug (ex: pharmacology)" value={newDiscipline.slug} onChange={(e) => setNewDiscipline((p) => ({ ...p, slug: e.target.value }))} className="font-sans bg-background" />
            <Input placeholder="Nome PT" value={newDiscipline.namePt} onChange={(e) => setNewDiscipline((p) => ({ ...p, namePt: e.target.value }))} className="font-sans bg-background" />
            <Input placeholder="Name EN" value={newDiscipline.nameEn} onChange={(e) => setNewDiscipline((p) => ({ ...p, nameEn: e.target.value }))} className="font-sans bg-background" />
            <div className="flex gap-2">
              <Button variant="outline" onClick={() => setShowAddDiscipline(false)} className="flex-1 font-sans">{t("cancel")}</Button>
              <Button onClick={handleAddDiscipline} disabled={createDiscipline.isPending} className="flex-1 bg-primary text-primary-foreground font-sans">
                {createDiscipline.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : t("save")}
              </Button>
            </div>
          </div>
        </DialogContent>
      </Dialog>

      {/* Add Subject Dialog */}
      <Dialog open={showAddSubject} onOpenChange={setShowAddSubject}>
        <DialogContent className="bg-card border-border/50">
          <DialogHeader>
            <DialogTitle className="font-serif">{language === "pt" ? "Nova Disciplina" : "New Discipline"}</DialogTitle>
          </DialogHeader>
          <div className="space-y-3">
            <Select value={newSubject.disciplineId} onValueChange={(v) => setNewSubject((p) => ({ ...p, disciplineId: v }))}>
              <SelectTrigger className="bg-background font-sans"><SelectValue placeholder={t("exam_discipline")} /></SelectTrigger>
              <SelectContent>
                {disciplines?.map((d) => (
                  <SelectItem key={d.id} value={String(d.id)}>{language === "pt" ? d.namePt : d.nameEn}</SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Input placeholder="Slug" value={newSubject.slug} onChange={(e) => setNewSubject((p) => ({ ...p, slug: e.target.value }))} className="font-sans bg-background" />
            <Input placeholder="Nome PT" value={newSubject.namePt} onChange={(e) => setNewSubject((p) => ({ ...p, namePt: e.target.value }))} className="font-sans bg-background" />
            <Input placeholder="Name EN" value={newSubject.nameEn} onChange={(e) => setNewSubject((p) => ({ ...p, nameEn: e.target.value }))} className="font-sans bg-background" />
            <div className="flex gap-2">
              <Button variant="outline" onClick={() => setShowAddSubject(false)} className="flex-1 font-sans">{t("cancel")}</Button>
              <Button onClick={handleAddSubject} disabled={createSubject.isPending} className="flex-1 bg-primary text-primary-foreground font-sans">
                {createSubject.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : t("save")}
              </Button>
            </div>
          </div>
        </DialogContent>
      </Dialog>

      {/* AI Generate Dialog */}
      <Dialog open={showAiGen} onOpenChange={(o) => { setShowAiGen(o); if (!o) setAiGenResult(null); }}>
        <DialogContent className="bg-card border-border/50 max-w-2xl">
          <DialogHeader>
            <DialogTitle className="font-serif">{t("admin_generate_ai")}</DialogTitle>
          </DialogHeader>
          {!aiGenResult ? (
            <div className="space-y-3">
              <Select value={aiGenConfig.disciplineId} onValueChange={(v) => setAiGenConfig((p) => ({ ...p, disciplineId: v }))}>
                <SelectTrigger className="bg-background font-sans"><SelectValue placeholder={t("exam_discipline")} /></SelectTrigger>
                <SelectContent>
                  {disciplines?.map((d) => (
                    <SelectItem key={d.id} value={String(d.id)}>{language === "pt" ? d.namePt : d.nameEn}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <Select value={aiGenConfig.difficulty} onValueChange={(v) => setAiGenConfig((p) => ({ ...p, difficulty: v }))}>
                <SelectTrigger className="bg-background font-sans"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="easy">{t("exam_easy")}</SelectItem>
                  <SelectItem value="medium">{t("exam_medium")}</SelectItem>
                  <SelectItem value="hard">{t("exam_hard")}</SelectItem>
                </SelectContent>
              </Select>
              <Input
                placeholder={language === "pt" ? "Tópico específico (opcional)" : "Specific topic (optional)"}
                value={aiGenConfig.topic}
                onChange={(e) => setAiGenConfig((p) => ({ ...p, topic: e.target.value }))}
                className="font-sans bg-background"
              />
              <Button
                onClick={handleAiGenerate}
                disabled={generateAi.isPending}
                className="w-full bg-primary text-primary-foreground font-sans gap-2"
              >
                {generateAi.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Brain className="h-4 w-4" />}
                {language === "pt" ? "Gerar Questão" : "Generate Question"}
              </Button>
            </div>
          ) : (
            <div className="space-y-3">
              <div className="p-3 bg-accent/30 rounded-lg">
                <p className="text-sm font-sans font-medium mb-2">{aiGenResult.textPt}</p>
                {aiGenResult.options.map((o: any) => (
                  <div key={o.id} className={`text-xs font-sans p-2 rounded mb-1 ${o.id === aiGenResult.correctOption ? "bg-green-500/20 text-green-300" : "text-muted-foreground"}`}>
                    {o.id}) {o.textPt} {o.id === aiGenResult.correctOption && "✓"}
                  </div>
                ))}
              </div>
              {aiGenResult.explanationPt && (
                <div className="p-3 bg-primary/5 border border-primary/20 rounded-lg">
                  <p className="text-xs text-muted-foreground font-sans">{aiGenResult.explanationPt}</p>
                </div>
              )}
              <div className="flex gap-2">
                <Button variant="outline" onClick={() => setAiGenResult(null)} className="flex-1 font-sans">
                  {language === "pt" ? "Gerar Outra" : "Generate Another"}
                </Button>
                <Button onClick={handleSaveAiQuestion} disabled={createQuestion.isPending} className="flex-1 bg-primary text-primary-foreground font-sans">
                  {createQuestion.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : t("save")}
                </Button>
              </div>
            </div>
          )}
        </DialogContent>
      </Dialog>

      {/* Add Question Dialog */}
      <Dialog open={showAddQuestion} onOpenChange={(open) => { setShowAddQuestion(open); if (!open) resetNewQuestion(); }}>
        <DialogContent className="bg-card border-border/50 max-w-2xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle className="font-serif">{language === "pt" ? "Nova Questão" : "New Question"}</DialogTitle>
          </DialogHeader>
          <div className="space-y-3">
            {/* Model selector M1–M10 */}
            <div className="space-y-1">
              <label className="text-xs font-medium text-muted-foreground font-sans">
                Modelo de Item (M1–M10)
              </label>
              <Select
                value={newQuestion.modelId || "__none__"}
                onValueChange={(v) => {
                  const mid = v === "__none__" ? "" : v;
                  const dbType = mid ? getDbTypeForModel(mid) : newQuestion.questionType;
                  setNewQuestion((p) => ({
                    ...p,
                    modelId: mid,
                    questionType: (dbType || p.questionType) as QuestionType,
                    correctOption: "A",
                    options: defaultOptions,
                    formatData: {},
                  }));
                }}
              >
                <SelectTrigger className="bg-background font-sans">
                  <SelectValue placeholder="Selecione o modelo de item..." />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="__none__">— Sem modelo definido —</SelectItem>
                  {MODEL_OPTIONS.map((m) => (
                    <SelectItem key={m.value} value={m.value}>
                      <span className="font-sans">{m.label}</span>
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {newQuestion.modelId && (
                <p className="text-xs text-emerald-400 font-sans px-1">
                  Tipo DB automático: <strong>{getDbTypeForModel(newQuestion.modelId)}</strong>
                </p>
              )}
            </div>

            {/* M10 block fields */}
            {newQuestion.modelId === "M10" && (
              <div className="grid grid-cols-2 gap-2">
                <Input
                  placeholder="grupoId do bloco (ex: BLOCO_2024_Q1)"
                  value={newQuestion.grupoId}
                  onChange={(e) => setNewQuestion((p) => ({ ...p, grupoId: e.target.value }))}
                  className="bg-background font-sans text-sm"
                />
                <Input
                  placeholder="Posição no bloco (1, 2, 3...)"
                  type="number"
                  value={newQuestion.posicaoBloco}
                  onChange={(e) => setNewQuestion((p) => ({ ...p, posicaoBloco: e.target.value }))}
                  className="bg-background font-sans text-sm"
                />
              </div>
            )}

            {/* Question Type Selector */}
            <Select value={newQuestion.questionType} onValueChange={(v) => setNewQuestion((p) => ({ ...p, questionType: v as QuestionType, correctOption: "A", options: defaultOptions, formatData: {} }))}>
              <SelectTrigger className="bg-background font-sans">
                <SelectValue placeholder={language === "pt" ? "Tipo de Questão" : "Question Type"} />
              </SelectTrigger>
              <SelectContent>
                {(Object.entries(QUESTION_TYPE_LABELS) as [QuestionType, { pt: string; en: string; description: string }][]).map(([val, label]) => (
                  <SelectItem key={val} value={val}>
                    <span className="font-sans">{language === "pt" ? label.pt : label.en}</span>
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>

            {/* Type description hint */}
            <p className="text-xs text-muted-foreground font-sans px-1">
              {language === "pt" ? QUESTION_TYPE_LABELS[newQuestion.questionType].description : QUESTION_TYPE_LABELS[newQuestion.questionType].en}
            </p>

            {/* Question text */}
            <textarea
              placeholder="Enunciado PT *"
              value={newQuestion.textPt}
              onChange={(e) => setNewQuestion((p) => ({ ...p, textPt: e.target.value }))}
              rows={3}
              className="w-full p-3 rounded-lg bg-background border border-border/50 text-sm font-sans resize-none focus:outline-none focus:ring-1 focus:ring-primary"
            />
            <textarea
              placeholder="Statement EN"
              value={newQuestion.textEn}
              onChange={(e) => setNewQuestion((p) => ({ ...p, textEn: e.target.value }))}
              rows={2}
              className="w-full p-3 rounded-lg bg-background border border-border/50 text-sm font-sans resize-none focus:outline-none focus:ring-1 focus:ring-primary"
            />

            {/* Discipline + Difficulty */}
            <div className="grid grid-cols-2 gap-3">
              <Select value={newQuestion.disciplineId} onValueChange={(v) => setNewQuestion((p) => ({ ...p, disciplineId: v }))}>
                <SelectTrigger className="bg-background font-sans"><SelectValue placeholder={t("exam_discipline")} /></SelectTrigger>
                <SelectContent>
                  {disciplines?.map((d) => <SelectItem key={d.id} value={String(d.id)}>{language === "pt" ? d.namePt : d.nameEn}</SelectItem>)}
                </SelectContent>
              </Select>
              <Select value={newQuestion.difficulty} onValueChange={(v) => setNewQuestion((p) => ({ ...p, difficulty: v }))}>
                <SelectTrigger className="bg-background font-sans"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="easy">{t("exam_easy")}</SelectItem>
                  <SelectItem value="medium">{t("exam_medium")}</SelectItem>
                  <SelectItem value="hard">{t("exam_hard")}</SelectItem>
                </SelectContent>
              </Select>
            </div>

            {/* Subject tag + Author */}
            <div className="grid grid-cols-2 gap-3">
              <Input placeholder={language === "pt" ? "Tag de disciplina" : "Discipline tag"} value={newQuestion.subjectTag} onChange={(e) => setNewQuestion((p) => ({ ...p, subjectTag: e.target.value }))} className="font-sans bg-background" />
              <Input placeholder={language === "pt" ? "Autor / Banca" : "Author / Board"} value={newQuestion.author} onChange={(e) => setNewQuestion((p) => ({ ...p, author: e.target.value }))} className="font-sans bg-background" />
            </div>

            {/* ── Format-specific sections ── */}

            {/* Assertion-Reason */}
            {newQuestion.questionType === "assertion_reason" && (
              <AssertionReasonSection
                assertion1={newQuestion.assertion1}
                assertion2={newQuestion.assertion2}
                correctOption={newQuestion.correctOption}
                lang={language as "pt" | "en"}
                onChange={(field, value) => setNewQuestion((p) => ({ ...p, [field]: value }))}
              />
            )}

            {/* Complex Multiple Choice */}
            {newQuestion.questionType === "complex_multiple_choice" && (
              <ComplexMCSection
                items={newQuestion.formatData.items || []}
                options={newQuestion.options}
                correctOption={newQuestion.correctOption}
                lang={language as "pt" | "en"}
                onItemsChange={(items) => setNewQuestion((p) => ({ ...p, formatData: { ...p.formatData, items } }))}
                onOptionsChange={(opts) => setNewQuestion((p) => ({ ...p, options: opts }))}
                onCorrectChange={(v) => setNewQuestion((p) => ({ ...p, correctOption: v }))}
              />
            )}

            {/* Matching */}
            {newQuestion.questionType === "matching" && (
              <MatchingSection
                pairs={newQuestion.formatData.pairs || []}
                options={newQuestion.options}
                correctOption={newQuestion.correctOption}
                lang={language as "pt" | "en"}
                onPairsChange={(pairs) => setNewQuestion((p) => ({ ...p, formatData: { ...p.formatData, pairs } }))}
                onOptionsChange={(opts) => setNewQuestion((p) => ({ ...p, options: opts }))}
                onCorrectChange={(v) => setNewQuestion((p) => ({ ...p, correctOption: v }))}
              />
            )}

            {/* True/False Sequential */}
            {newQuestion.questionType === "true_false" && (
              <TrueFalseSection
                statements={newQuestion.formatData.statements || []}
                options={newQuestion.options}
                correctOption={newQuestion.correctOption}
                lang={language as "pt" | "en"}
                onStatementsChange={(statements) => setNewQuestion((p) => ({ ...p, formatData: { ...p.formatData, statements } }))}
                onOptionsChange={(opts) => setNewQuestion((p) => ({ ...p, options: opts }))}
                onCorrectChange={(v) => setNewQuestion((p) => ({ ...p, correctOption: v }))}
              />
            )}

            {/* Ordering */}
            {newQuestion.questionType === "ordering" && (
              <OrderingSection
                steps={newQuestion.formatData.steps || []}
                options={newQuestion.options}
                correctOption={newQuestion.correctOption}
                lang={language as "pt" | "en"}
                onStepsChange={(steps) => setNewQuestion((p) => ({ ...p, formatData: { ...p.formatData, steps } }))}
                onOptionsChange={(opts) => setNewQuestion((p) => ({ ...p, options: opts }))}
                onCorrectChange={(v) => setNewQuestion((p) => ({ ...p, correctOption: v }))}
              />
            )}

            {/* Cloze */}
            {newQuestion.questionType === "cloze" && (
              <ClozeSection
                options={newQuestion.options}
                correctOption={newQuestion.correctOption}
                lang={language as "pt" | "en"}
                onOptionsChange={(opts) => setNewQuestion((p) => ({ ...p, options: opts }))}
                onCorrectChange={(v) => setNewQuestion((p) => ({ ...p, correctOption: v }))}
              />
            )}

            {/* Clinical Case / Image Analysis / Interpretation */}
            {["clinical_case", "image_analysis", "interpretation"].includes(newQuestion.questionType) && (
              <>
                <MediaSection
                  questionType={newQuestion.questionType}
                  imageUrl={newQuestion.formatData.imageUrl}
                  caseText={newQuestion.formatData.caseText}
                  tableData={newQuestion.formatData.tableData}
                  lang={language as "pt" | "en"}
                  onChange={(field, val) => setNewQuestion((p) => ({ ...p, formatData: { ...p.formatData, [field]: val } }))}
                />
                <MultipleChoiceOptions
                  options={newQuestion.options}
                  correctOption={newQuestion.correctOption}
                  lang={language as "pt" | "en"}
                  onOptionsChange={(opts) => setNewQuestion((p) => ({ ...p, options: opts }))}
                  onCorrectChange={(v) => setNewQuestion((p) => ({ ...p, correctOption: v }))}
                />
              </>
            )}

            {/* Standard Multiple Choice options */}
            {newQuestion.questionType === "multiple_choice" && (
              <MultipleChoiceOptions
                options={newQuestion.options}
                correctOption={newQuestion.correctOption}
                lang={language as "pt" | "en"}
                onOptionsChange={(opts) => setNewQuestion((p) => ({ ...p, options: opts }))}
                onCorrectChange={(v) => setNewQuestion((p) => ({ ...p, correctOption: v }))}
              />
            )}

            {/* Explanation */}
            <textarea
              placeholder={language === "pt" ? "Explicação PT" : "Explanation PT"}
              value={newQuestion.explanationPt}
              onChange={(e) => setNewQuestion((p) => ({ ...p, explanationPt: e.target.value }))}
              rows={2}
              className="w-full p-3 rounded-lg bg-background border border-border/50 text-sm font-sans resize-none focus:outline-none focus:ring-1 focus:ring-primary"
            />
            <Input placeholder={language === "pt" ? "Ano (opcional)" : "Year (optional)"} value={newQuestion.year} onChange={(e) => setNewQuestion((p) => ({ ...p, year: e.target.value }))} className="font-sans bg-background" />

            {/* Image toggle */}
            <label className="flex items-center gap-2 cursor-pointer select-none text-sm font-sans">
              <input
                type="checkbox"
                checked={newQuestion.hasImage}
                onChange={(e) => setNewQuestion((p) => ({ ...p, hasImage: e.target.checked, imageUrl: e.target.checked ? p.imageUrl : "" }))}
                className="h-4 w-4 accent-primary"
              />
              {language === "pt" ? "Esta questão possui imagem" : "This question has an image"}
            </label>
            {newQuestion.hasImage && (
              <QuestionImageUpload
                value={newQuestion.imageUrl || null}
                onChange={(url) => setNewQuestion((p) => ({ ...p, imageUrl: url ?? "" }))}
                questionType="mc"
              />
            )}

            <div className="flex gap-2">
              <Button variant="outline" onClick={() => { setShowAddQuestion(false); resetNewQuestion(); }} className="flex-1 font-sans">{t("cancel")}</Button>
              <Button onClick={handleAddQuestion} disabled={createQuestion.isPending} className="flex-1 bg-primary text-primary-foreground font-sans">
                {createQuestion.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : t("save")}
              </Button>
            </div>
          </div>
        </DialogContent>
      </Dialog>
       {/* Edit Question Dialog */}
      <Dialog open={!!editQuestionId} onOpenChange={(o) => { if (!o) { setEditQuestionId(null); setEditForm(null); } }}>
        <DialogContent className="bg-card border-border/50 max-w-3xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle className="font-serif">{language === "pt" ? "Editar Questão" : "Edit Question"} #{editQuestionId}</DialogTitle>
          </DialogHeader>
          {editLoading || !editForm ? (
            <div className="flex items-center justify-center py-12">
              <Loader2 className="h-8 w-8 animate-spin text-primary" />
            </div>
          ) : (
            <div className="space-y-4 py-2">
              {/* Model — read-only display */}
              <div className="flex items-center gap-2 p-3 rounded-lg bg-background border border-border/50">
                <span className="text-xs font-sans text-muted-foreground shrink-0">Modelo de Item:</span>
                {editForm.modelId ? (
                  <span className="text-xs font-bold font-sans bg-primary/15 text-primary border border-primary/30 px-2 py-0.5 rounded">
                    {MODEL_OPTIONS.find(m => m.value === editForm.modelId)?.label ?? editForm.modelId}
                  </span>
                ) : (
                  <span className="text-xs text-muted-foreground font-sans italic">Sem modelo definido</span>
                )}
                <span className="text-xs text-muted-foreground font-sans ml-auto">Tipo DB: <strong>{editForm.questionType}</strong></span>
              </div>

              {/* Enunciado */}
              <div>
                <label className="text-xs font-sans text-muted-foreground mb-1 block">{language === "pt" ? "Enunciado (PT)" : "Statement (PT)"} *</label>
                <textarea
                  value={editForm.textPt}
                  onChange={(e) => setEditForm((p: any) => ({ ...p, textPt: e.target.value }))}
                  rows={4}
                  className="w-full p-3 rounded-lg bg-background border border-border/50 text-sm font-sans resize-none focus:outline-none focus:ring-1 focus:ring-primary"
                />
              </div>
              {/* Metadata: área + dificuldade */}
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="text-xs font-sans text-muted-foreground mb-1 block">{language === "pt" ? "Grande Área" : "Major Area"}</label>
                  <Select value={editForm.disciplineId} onValueChange={(v) => setEditForm((p: any) => ({ ...p, disciplineId: v }))}>
                    <SelectTrigger className="bg-background font-sans text-sm"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      {disciplines?.map((d) => (
                        <SelectItem key={d.id} value={String(d.id)}>{language === "pt" ? d.namePt : d.nameEn}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div>
                  <label className="text-xs font-sans text-muted-foreground mb-1 block">{language === "pt" ? "Dificuldade" : "Difficulty"}</label>
                  <Select value={editForm.difficulty} onValueChange={(v) => setEditForm((p: any) => ({ ...p, difficulty: v }))}>
                    <SelectTrigger className="bg-background font-sans text-sm"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="very_easy">{language === "pt" ? "Muito Fácil" : "Very Easy"}</SelectItem>
                      <SelectItem value="easy">{language === "pt" ? "Fácil" : "Easy"}</SelectItem>
                      <SelectItem value="medium">{language === "pt" ? "Médio" : "Medium"}</SelectItem>
                      <SelectItem value="hard">{language === "pt" ? "Difícil" : "Hard"}</SelectItem>
                      <SelectItem value="very_hard">{language === "pt" ? "Muito Difícil" : "Very Hard"}</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>
              {/* Ano + Banca */}
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="text-xs font-sans text-muted-foreground mb-1 block">{language === "pt" ? "Ano" : "Year"}</label>
                  <Input value={editForm.year} onChange={(e) => setEditForm((p: any) => ({ ...p, year: e.target.value }))} className="bg-background font-sans text-sm" placeholder="ex: 2023" />
                </div>
                <div>
                  <label className="text-xs font-sans text-muted-foreground mb-1 block">{language === "pt" ? "Banca" : "Exam Board"}</label>
                  <Input value={editForm.banca} onChange={(e) => setEditForm((p: any) => ({ ...p, banca: e.target.value }))} className="bg-background font-sans text-sm" placeholder="ex: VUNESP, FGV" />
                </div>
              </div>
              {/* Autor + Instituição */}
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="text-xs font-sans text-muted-foreground mb-1 block">{language === "pt" ? "Autor" : "Author"}</label>
                  <Input value={editForm.author} onChange={(e) => setEditForm((p: any) => ({ ...p, author: e.target.value }))} className="bg-background font-sans text-sm" />
                </div>
                <div>
                  <label className="text-xs font-sans text-muted-foreground mb-1 block">{language === "pt" ? "Instituição" : "Institution"}</label>
                  <Input value={editForm.instituicao} onChange={(e) => setEditForm((p: any) => ({ ...p, instituicao: e.target.value }))} className="bg-background font-sans text-sm" />
                </div>
              </div>
              {/* Alternativas */}
              {editForm.questionType === "multiple_choice" && (
                <div>
                  <label className="text-xs font-sans text-muted-foreground mb-2 block">{language === "pt" ? "Alternativas" : "Options"}</label>
                  <div className="space-y-2">
                    {(editForm.options as Array<{id:string;textPt:string}>).map((opt, idx) => (
                      <div key={opt.id} className="flex items-center gap-2">
                        <button
                          type="button"
                          onClick={() => setEditForm((p: any) => ({ ...p, correctOption: opt.id }))}
                          className={`w-7 h-7 rounded-full text-xs font-bold shrink-0 border-2 transition-colors ${
                            editForm.correctOption === opt.id
                              ? "bg-primary text-primary-foreground border-primary"
                              : "bg-background text-muted-foreground border-border hover:border-primary"
                          }`}
                        >{opt.id}</button>
                        <Input
                          value={opt.textPt}
                          onChange={(e) => {
                            const opts = [...editForm.options];
                            opts[idx] = { ...opts[idx], textPt: e.target.value };
                            setEditForm((p: any) => ({ ...p, options: opts }));
                          }}
                          className="bg-background font-sans text-sm flex-1"
                          placeholder={`Alternativa ${opt.id}`}
                        />
                      </div>
                    ))}
                  </div>
                  <p className="text-xs text-muted-foreground mt-1">{language === "pt" ? "Clique na letra para marcar como correta" : "Click the letter to mark as correct"}</p>
                </div>
              )}
              {/* Explicação */}
              <div>
                <label className="text-xs font-sans text-muted-foreground mb-1 block">{language === "pt" ? "Explicação (opcional)" : "Explanation (optional)"}</label>
                <textarea
                  value={editForm.explanationPt}
                  onChange={(e) => setEditForm((p: any) => ({ ...p, explanationPt: e.target.value }))}
                  rows={3}
                  className="w-full p-3 rounded-lg bg-background border border-border/50 text-sm font-sans resize-none focus:outline-none focus:ring-1 focus:ring-primary"
                />
              </div>
              {/* Premium toggle */}
              <div className="flex items-center gap-3">
                <input
                  type="checkbox"
                  id="edit-premium"
                  checked={editForm.isPremium}
                  onChange={(e) => setEditForm((p: any) => ({ ...p, isPremium: e.target.checked }))}
                  className="w-4 h-4 accent-primary"
                />
                <label htmlFor="edit-premium" className="text-sm font-sans">{language === "pt" ? "Questão Premium" : "Premium Question"}</label>
              </div>

              {/* Image toggle + upload */}
              <div className="space-y-2">
                <label className="flex items-center gap-2 cursor-pointer select-none text-sm font-sans">
                  <input
                    type="checkbox"
                    checked={!!(editForm.imageUrl)}
                    onChange={(e) => {
                      if (!e.target.checked) setEditForm((p: any) => ({ ...p, imageUrl: "" }));
                    }}
                    className="h-4 w-4 accent-primary"
                  />
                  {language === "pt" ? "Esta questão possui imagem" : "This question has an image"}
                </label>
                {editForm.imageUrl !== undefined && (
                  <QuestionImageUpload
                    value={editForm.imageUrl || null}
                    onChange={(url) => setEditForm((p: any) => ({ ...p, imageUrl: url ?? "" }))}
                    questionId={editQuestionId ?? undefined}
                    questionType="mc"
                  />
                )}
              </div>

              {/* Actions */}
              <div className="flex gap-2 pt-2">
                <Button variant="outline" onClick={() => { setEditQuestionId(null); setEditForm(null); }} className="flex-1 font-sans">{t("cancel")}</Button>
                <Button onClick={handleSaveEdit} disabled={updateQuestionMutation.isPending} className="flex-1 bg-primary text-primary-foreground font-sans">
                  {updateQuestionMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : (language === "pt" ? "Salvar Alterações" : "Save Changes")}
                </Button>
              </div>
            </div>
          )}
        </DialogContent>
      </Dialog>

      {/* Add Lead Dialog */}
      <Dialog open={showAddLead} onOpenChange={setShowAddLead}>
        <DialogContent className="bg-card border-border/50 max-w-sm">
          <DialogHeader><DialogTitle className="font-sans text-sm">Adicionar Lead</DialogTitle></DialogHeader>
          <div className="space-y-3 pt-2">
            <div>
              <label className="text-xs text-muted-foreground font-sans block mb-1">Email *</label>
              <Input value={newLead.email} onChange={(e) => setNewLead((p) => ({ ...p, email: e.target.value }))} placeholder="email@exemplo.com" className="font-sans bg-background h-9 text-sm" />
            </div>
            <div>
              <label className="text-xs text-muted-foreground font-sans block mb-1">Nome</label>
              <Input value={newLead.name} onChange={(e) => setNewLead((p) => ({ ...p, name: e.target.value }))} placeholder="Nome completo" className="font-sans bg-background h-9 text-sm" />
            </div>
            <div>
              <label className="text-xs text-muted-foreground font-sans block mb-1">Telefone / WhatsApp</label>
              <Input value={newLead.phone} onChange={(e) => setNewLead((p) => ({ ...p, phone: e.target.value }))} placeholder="(11) 99999-9999" className="font-sans bg-background h-9 text-sm" />
            </div>
            <div>
              <label className="text-xs text-muted-foreground font-sans block mb-1">Notas</label>
              <textarea value={newLead.notes} onChange={(e) => setNewLead((p) => ({ ...p, notes: e.target.value }))} rows={2} className="w-full p-2 rounded-lg bg-background border border-border/50 text-sm font-sans resize-none focus:outline-none focus:ring-1 focus:ring-primary" />
            </div>
            <Button className="w-full gap-1.5 font-sans" onClick={handleAddLead} disabled={createLead.isPending}>
              {createLead.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />}
              Adicionar Lead
            </Button>
          </div>
        </DialogContent>
      </Dialog>

      {/* Import Leads Dialog */}
      <Dialog open={showImportLeads} onOpenChange={setShowImportLeads}>
        <DialogContent className="bg-card border-border/50 max-w-md">
          <DialogHeader><DialogTitle className="font-sans text-sm">Importar Leads via CSV</DialogTitle></DialogHeader>
          <div className="space-y-3 pt-2">
            <p className="text-xs text-muted-foreground font-sans">Cole uma lista de emails, um por linha. Formato aceito: <code className="bg-background px-1 rounded">email,nome</code> ou apenas <code className="bg-background px-1 rounded">email</code>.</p>
            <textarea
              value={importCsvText}
              onChange={(e) => setImportCsvText(e.target.value)}
              rows={8}
              placeholder={"joao@email.com,João Silva\nmaria@email.com\npedro@email.com,Pedro"}
              className="w-full p-3 rounded-lg bg-background border border-border/50 text-sm font-sans resize-none focus:outline-none focus:ring-1 focus:ring-primary font-mono"
            />
            <Button className="w-full gap-1.5 font-sans" onClick={handleImportLeads} disabled={importLeads.isPending}>
              {importLeads.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Download className="h-4 w-4" />}
              Importar
            </Button>
          </div>
        </DialogContent>
      </Dialog>

      {/* Edit Lead Dialog */}
      <Dialog open={!!editLeadId} onOpenChange={(open) => !open && setEditLeadId(null)}>
        <DialogContent className="bg-card border-border/50 max-w-sm">
          <DialogHeader><DialogTitle className="font-sans text-sm">Editar Lead</DialogTitle></DialogHeader>
          <div className="space-y-3 pt-2">
            <div>
              <label className="text-xs text-muted-foreground font-sans block mb-1">Status</label>
              <Select value={editLeadStatus} onValueChange={(v) => setEditLeadStatus(v as any)}>
                <SelectTrigger className="bg-background font-sans h-9 text-sm"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="new">Novo</SelectItem>
                  <SelectItem value="contacted">Contactado</SelectItem>
                  <SelectItem value="converted">Convertido</SelectItem>
                  <SelectItem value="lost">Perdido</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div>
              <label className="text-xs text-muted-foreground font-sans block mb-1">Notas</label>
              <textarea value={editLeadNotes} onChange={(e) => setEditLeadNotes(e.target.value)} rows={3} className="w-full p-2 rounded-lg bg-background border border-border/50 text-sm font-sans resize-none focus:outline-none focus:ring-1 focus:ring-primary" />
            </div>
            <Button className="w-full gap-1.5 font-sans" onClick={handleUpdateLead} disabled={updateLead.isPending}>
              {updateLead.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <CheckCircle className="h-4 w-4" />}
              Salvar
            </Button>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  </div>
  );
}
// ─── Import Tab (sub-component used by AdminPanel) ────────────────────────────
function ImportTab({ onImportComplete }: { onImportComplete: () => void }) {
  const [preloadedRows, setPreloadedRows] = useState<import("@/components/QuestionImport").ParsedMCQuestion[]>([]);
  const [preloadedFileName, setPreloadedFileName] = useState("");
  const [activeSection, setActiveSection] = useState<"file" | "ai">("file");

  const handleAIExtracted = (questions: AIExtractedQuestion[], fileName: string) => {
    // Map AIExtractedQuestion → ParsedMCQuestion (disciplineId is required; default 0 = unset)
    const mapped = questions.map((q) => ({
      textPt: q.textPt,
      disciplineId: q.disciplineId ?? 0,
      subjectTag: q.disciplineSuggestion,
      author: q.author ?? "",
      year: q.year,
      difficulty: q.difficulty,
      questionType: q.questionType as import("@/components/QuestionImport").ParsedMCQuestion["questionType"],
      optA: q.optA,
      optB: q.optB,
      optC: q.optC,
      optD: q.optD,
      optE: q.optE,
      correctOption: q.correctOption,
      explanationPt: q.explanationPt,
      assertion1: q.assertion1,
      assertion2: q.assertion2,
      _rowIndex: q._rowIndex,
      _errors: q._errors,
      _valid: q._valid,
    })) as import("@/components/QuestionImport").ParsedMCQuestion[];
    setPreloadedRows(mapped);
    setPreloadedFileName(fileName);
    setActiveSection("file");
    toast.success(`${mapped.length} questões carregadas na pré-visualização de importação`);
  };

  return (
    <div className="space-y-6">
      <div>
        <h2 className="font-serif text-xl font-bold mb-1">Importar Questões</h2>
        <p className="text-sm text-muted-foreground font-sans">
          Importe questões em lote via CSV, XLSX ou JSON — ou use a IA para extrair automaticamente de PDFs e arquivos Word.
        </p>
      </div>

      {/* Section toggle */}
      <div className="flex gap-2">
        <Button
          size="sm"
          variant={activeSection === "file" ? "default" : "outline"}
          className="font-sans gap-1"
          onClick={() => setActiveSection("file")}
        >
          <Upload className="h-3.5 w-3.5" />
          CSV / XLSX / JSON
        </Button>
        <Button
          size="sm"
          variant={activeSection === "ai" ? "default" : "outline"}
          className="font-sans gap-1"
          onClick={() => setActiveSection("ai")}
        >
          <Sparkles className="h-3.5 w-3.5" />
          Extrair com IA (PDF / Word)
        </Button>
      </div>

      {activeSection === "ai" && (
        <AIQuestionExtractor onQuestionsExtracted={handleAIExtracted} />
      )}

      {activeSection === "file" && (
        <QuestionImport
          onImportComplete={(count) => { toast.success(`${count} questões importadas`); onImportComplete(); }}
          preloadedRows={preloadedRows.length > 0 ? preloadedRows : undefined}
          initialFileName={preloadedFileName}
        />
      )}
    </div>
  );
}

// ─── ImportFormatGuide — collapsible instructions card ────────────────────────
function ImportFormatGuide({ language }: { language: string }) {
  const [open, setOpen] = useState(false);
  return (
    <div className="border border-border/50 rounded-xl overflow-hidden">
      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        className="w-full flex items-center justify-between px-4 py-3 bg-card hover:bg-accent/30 transition-colors text-left"
      >
        <div className="flex items-center gap-2">
          <FileJson className="h-4 w-4 text-primary" />
          <span className="font-sans font-medium text-sm">
            {language === "pt" ? "Instruções de formato" : "Format instructions"}
          </span>
        </div>
        <ChevronDown className={`h-4 w-4 text-muted-foreground transition-transform ${open ? "rotate-180" : ""}`} />
      </button>
      {open && (
        <div className="border-t border-border/50">
          <ImportGuide />
        </div>
      )}
    </div>
  );
}

// ─── PlanosTab ────────────────────────────────────────────────────────────────
function DuplicatesTab() {
  const { data, isLoading, isFetched, refetch } = trpc.admin.findDuplicates.useQuery(undefined, {
    staleTime: 0,
    enabled: false,
  });
  const deleteMutation = trpc.admin.deleteDuplicates.useMutation({
    onSuccess: (r) => {
      toast.success(`${r.deleted} questão(ões) desativada(s).`);
      setSelected({});
      refetch();
    },
    onError: (e) => toast.error(e.message),
  });

  // selected: Map<groupIndex, Set<id>>
  const [selected, setSelected] = useState<Record<number, Set<number>>>({});

  const toggleId = (groupIdx: number, id: number) => {
    setSelected(prev => {
      const set = new Set(prev[groupIdx] ?? []);
      set.has(id) ? set.delete(id) : set.add(id);
      return { ...prev, [groupIdx]: set };
    });
  };

  const selectAllDuplicates = () => {
    if (!data) return;
    const next: Record<number, Set<number>> = {};
    data.forEach((g, i) => { next[i] = new Set(g.duplicateIds); });
    setSelected(next);
  };

  const clearAll = () => setSelected({});

  const selectedIds = Object.values(selected).flatMap(s => [...s]);

  if (!isFetched && !isLoading) return (
    <div className="flex flex-col items-center justify-center py-24 text-center gap-4">
      <Copy className="w-12 h-12 text-muted-foreground/40" />
      <div>
        <p className="text-base font-semibold mb-1">Verificar duplicatas</p>
        <p className="text-sm text-muted-foreground">Compara todos os enunciados e agrupa questões com texto idêntico.</p>
      </div>
      <Button className="gap-2" onClick={() => refetch()}>
        <RefreshCw className="w-4 h-4" /> Verificar duplicatas
      </Button>
    </div>
  );

  if (isLoading) return <div className="flex justify-center py-20"><Loader2 className="w-6 h-6 animate-spin text-primary" /></div>;

  if (!data?.length) return (
    <div className="flex flex-col items-center justify-center py-20 text-center gap-3">
      <CheckCircle className="w-12 h-12 text-emerald-400" />
      <p className="text-lg font-semibold">Nenhuma duplicata encontrada</p>
      <p className="text-sm text-muted-foreground">Todas as questões têm texto único.</p>
      <Button variant="outline" size="sm" className="gap-2 mt-2" onClick={() => refetch()}>
        <RefreshCw className="w-3.5 h-3.5" /> Verificar novamente
      </Button>
    </div>
  );

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <p className="text-sm font-semibold">{data.length} grupo(s) de duplicatas — {data.reduce((s, g) => s + g.total, 0)} questões envolvidas</p>
          <p className="text-xs text-muted-foreground">A questão mais antiga (menor ID) é sugerida para manter. As demais podem ser removidas.</p>
        </div>
        <div className="flex gap-2 flex-wrap">
          <Button size="sm" variant="outline" className="font-sans text-xs h-8 gap-1.5" onClick={() => refetch()} disabled={isLoading}>
            <RefreshCw className="w-3 h-3" /> Verificar novamente
          </Button>
          <Button size="sm" variant="outline" className="font-sans text-xs h-8" onClick={selectAllDuplicates}>
            Selecionar todas as cópias
          </Button>
          <Button size="sm" variant="outline" className="font-sans text-xs h-8" onClick={clearAll}>
            Limpar seleção
          </Button>
          <Button
            size="sm"
            variant="destructive"
            className="font-sans text-xs h-8 gap-1.5"
            disabled={selectedIds.length === 0 || deleteMutation.isPending}
            onClick={() => deleteMutation.mutate({ ids: selectedIds })}
          >
            {deleteMutation.isPending ? <Loader2 className="w-3 h-3 animate-spin" /> : <Trash2 className="w-3 h-3" />}
            Deletar {selectedIds.length > 0 ? `(${selectedIds.length})` : "selecionadas"}
          </Button>
        </div>
      </div>

      {/* Groups */}
      <div className="space-y-3">
        {data.map((group, gi) => (
          <div key={gi} className="border border-border/50 rounded-xl overflow-hidden">
            {/* Group header */}
            <div className="bg-accent/20 px-4 py-2.5 flex items-center justify-between gap-3">
              <div className="flex items-center gap-2 min-w-0">
                <Badge variant="outline" className="shrink-0 text-xs border-red-500/40 text-red-400">{group.total}x</Badge>
                {group.disciplineName && (
                  <Badge variant="outline" className="shrink-0 text-xs">{group.disciplineName}</Badge>
                )}
                <p className="text-xs text-muted-foreground truncate max-w-md">{group.textPt.slice(0, 120)}{group.textPt.length > 120 ? "…" : ""}</p>
              </div>
              <Button
                size="sm"
                variant="ghost"
                className="text-xs h-7 shrink-0 font-sans"
                onClick={() => {
                  const set = new Set(group.duplicateIds);
                  setSelected(prev => ({ ...prev, [gi]: set }));
                }}
              >
                Selecionar cópias
              </Button>
            </div>

            {/* Rows */}
            <div className="divide-y divide-border/30">
              {group.ids.map((id) => {
                const isKeep = id === group.keepId;
                const isChecked = selected[gi]?.has(id) ?? false;
                return (
                  <div key={id} className={`flex items-center gap-3 px-4 py-2.5 text-sm ${isKeep ? "bg-emerald-500/5" : ""}`}>
                    <input
                      type="checkbox"
                      className="w-4 h-4 accent-red-500"
                      checked={isChecked}
                      disabled={isKeep}
                      onChange={() => toggleId(gi, id)}
                    />
                    <span className="font-mono text-xs text-muted-foreground w-12">#{id}</span>
                    {isKeep ? (
                      <Badge variant="outline" className="text-xs border-emerald-500/40 text-emerald-400 shrink-0">Manter</Badge>
                    ) : (
                      <Badge variant="outline" className="text-xs border-red-500/30 text-red-400 shrink-0">Cópia</Badge>
                    )}
                  </div>
                );
              })}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

function QuestionDistributionTab() {
  const { data, isLoading, refetch } = trpc.admin.questionDistribution.useQuery();
  const [openDisciplineId, setOpenDisciplineId] = useState<number | null>(null);

  if (isLoading) return <div className="py-16 flex justify-center"><Loader2 className="h-6 w-6 animate-spin text-primary" /></div>;
  if (!data) return null;

  const totalQuestions = data.byDiscipline.reduce((s, d) => s + d.total, 0);
  const totalApproved = data.byDiscipline.reduce((s, d) => s + d.approved, 0);

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h3 className="font-sans font-semibold text-base">Distribuição de Questões</h3>
          <p className="text-xs text-muted-foreground font-sans mt-0.5">
            Total: <strong>{totalQuestions}</strong> questões ({totalApproved} aprovadas). Clique em uma área para ver as disciplinas.
          </p>
        </div>
        <Button size="sm" variant="outline" className="font-sans text-xs h-8 gap-1.5" onClick={() => refetch()}>
          <RefreshCw className="w-3 h-3" /> Atualizar
        </Button>
      </div>

      <div className="space-y-2">
        {data.byDiscipline.map((disc) => {
          const subjects = data.bySubject.filter((s) => s.disciplineId === disc.id);
          const isOpen = openDisciplineId === disc.id;
          const pct = totalQuestions > 0 ? Math.round((disc.total / totalQuestions) * 100) : 0;

          return (
            <div key={disc.id} className="border border-border/50 rounded-lg overflow-hidden">
              <button
                className="w-full flex items-center gap-3 px-4 py-3 bg-card hover:bg-accent/50 transition-colors text-left"
                onClick={() => setOpenDisciplineId(isOpen ? null : disc.id)}
              >
                <ChevronDown className={`h-4 w-4 text-muted-foreground transition-transform shrink-0 ${isOpen ? "rotate-180" : ""}`} />
                <span className="flex-1 font-medium text-sm font-sans truncate">{disc.namePt}</span>
                <span className="text-xs text-muted-foreground font-sans shrink-0">{pct}%</span>
                <span className="text-xs font-sans font-semibold text-primary shrink-0 w-16 text-right">{disc.total} questões</span>
                <span className="text-xs text-green-400 font-sans shrink-0 w-20 text-right">{disc.approved} aprovadas</span>
              </button>

              {isOpen && (
                <div className="border-t border-border/30">
                  {subjects.length === 0 ? (
                    <p className="px-6 py-3 text-xs text-muted-foreground font-sans italic">Nenhuma disciplina com questões nesta área.</p>
                  ) : (
                    <table className="w-full text-sm font-sans">
                      <thead className="bg-muted/30">
                        <tr>
                          <th className="text-left px-6 py-2 text-xs font-medium text-muted-foreground">Disciplina</th>
                          <th className="text-right px-4 py-2 text-xs font-medium text-muted-foreground">Total</th>
                          <th className="text-right px-4 py-2 text-xs font-medium text-muted-foreground">Aprovadas</th>
                          <th className="text-right px-4 py-2 text-xs font-medium text-muted-foreground">Pendentes</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-border/30">
                        {subjects.sort((a, b) => b.total - a.total).map((subj) => (
                          <tr key={subj.id} className="hover:bg-accent/30 transition-colors">
                            <td className="px-6 py-2 text-sm">{subj.namePt}</td>
                            <td className="px-4 py-2 text-right font-semibold text-primary">{subj.total}</td>
                            <td className="px-4 py-2 text-right text-green-400">{subj.approved}</td>
                            <td className="px-4 py-2 text-right text-yellow-400">{subj.total - subj.approved}</td>
                          </tr>
                        ))}
                        {disc.total > subjects.reduce((s, sub) => s + sub.total, 0) && (
                          <tr className="hover:bg-accent/30 transition-colors">
                            <td className="px-6 py-2 text-sm italic text-muted-foreground">Sem disciplina atribuída</td>
                            <td className="px-4 py-2 text-right font-semibold text-primary">
                              {disc.total - subjects.reduce((s, sub) => s + sub.total, 0)}
                            </td>
                            <td className="px-4 py-2 text-right text-green-400">
                              {disc.approved - subjects.reduce((s, sub) => s + sub.approved, 0)}
                            </td>
                            <td className="px-4 py-2 text-right text-yellow-400">
                              {(disc.total - subjects.reduce((s, sub) => s + sub.total, 0)) - (disc.approved - subjects.reduce((s, sub) => s + sub.approved, 0))}
                            </td>
                          </tr>
                        )}
                      </tbody>
                    </table>
                  )}
                </div>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}

function PlanosTab() {
  const [activeSection, setActiveSection] = useState<"users" | "payments">("users");
  const [search, setSearch] = useState("");
  const [planFilter, setPlanFilter] = useState<"all" | "free" | "trial" | "premium" | "expired">("all");
  const [paymentStatusFilter, setPaymentStatusFilter] = useState<"all" | "pending" | "approved" | "rejected" | "cancelled" | "refunded">("all");
  const [paymentSearch, setPaymentSearch] = useState("");
  const [extendDays, setExtendDays] = useState<number>(7);
  const [extendUserId, setExtendUserId] = useState<number | null>(null);
  const [showExtendDialog, setShowExtendDialog] = useState(false);
  const [selectedUserId, setSelectedUserId] = useState<number | null>(null);
  const [showPaymentDialog, setShowPaymentDialog] = useState(false);
  const [newPayment, setNewPayment] = useState({ amount: "", planType: "monthly" as "monthly" | "annual", paymentMethod: "manual", notes: "" });
  const [mpSyncOpen, setMpSyncOpen] = useState(false);
  const [mpSyncId, setMpSyncId] = useState("");
  const [mpSyncResult, setMpSyncResult] = useState<any>(null);
  const [showPremiumDialog, setShowPremiumDialog] = useState(false);
  const [premiumTargetId, setPremiumTargetId] = useState<number | null>(null);
  const [premiumDays, setPremiumDays] = useState<number>(30);

  const usersInput = useMemo(() => ({ page: 1, limit: 100, search: search || undefined, planFilter }), [search, planFilter]);
  const paymentsInput = useMemo(() => ({ page: 1, limit: 100, status: paymentStatusFilter, search: paymentSearch || undefined }), [paymentStatusFilter, paymentSearch]);

  const { data: usersData, refetch: refetchUsers, isLoading: usersLoading, isFetching: usersFetching } = trpc.plans.listUsersWithPlans.useQuery(usersInput);
  const { data: paymentsData, refetch: refetchPayments, isLoading: paymentsLoading, isFetching: paymentsFetching } = trpc.plans.listPayments.useQuery(paymentsInput);
  const { data: recentMPData, refetch: refetchRecentMP, isFetching: mpFetching } = trpc.admin.listRecentMPPayments.useQuery(
    { days: 7 }, { enabled: mpSyncOpen }
  );

  const updatePlan = trpc.plans.updateUserPlan.useMutation({ onSuccess: () => { toast.success("Plano atualizado"); refetchUsers(); }, onError: (e) => toast.error(e.message) });
  const extendTrial = trpc.plans.extendTrial.useMutation({ onSuccess: () => { toast.success("Trial estendido"); setShowExtendDialog(false); refetchUsers(); }, onError: (e) => toast.error(e.message) });
  const updatePayment = trpc.plans.updatePaymentStatus.useMutation({ onSuccess: () => { toast.success("Pagamento atualizado"); refetchPayments(); refetchUsers(); }, onError: (e) => toast.error(e.message) });
  const createPayment = trpc.plans.createPaymentRecord.useMutation({ onSuccess: () => { toast.success("Registro criado"); setShowPaymentDialog(false); refetchPayments(); refetchUsers(); }, onError: (e) => toast.error(e.message) });
  const syncMPPayment = trpc.admin.syncMPPayment.useMutation({
    onSuccess: (r) => { setMpSyncResult(r); toast.success(`Sincronizado: ${r.action}`); refetchPayments(); refetchUsers(); },
    onError: (e) => toast.error(e.message),
  });
  const deleteUser = trpc.plans.deleteUser.useMutation({
    onSuccess: () => { toast.success("Usuário deletado."); refetchUsers(); },
    onError: (e) => toast.error(e.message),
  });

  const statusBadge = (status: string) => {
    const map: Record<string, string> = {
      active: "bg-emerald-500/20 text-emerald-400 border-emerald-500/30",
      trial: "bg-blue-500/20 text-blue-400 border-blue-500/30",
      free: "bg-zinc-500/20 text-zinc-400 border-zinc-500/30",
      expired: "bg-red-500/20 text-red-400 border-red-500/30",
      premium: "bg-amber-500/20 text-amber-400 border-amber-500/30",
    };
    const labels: Record<string, string> = { active: "Premium Ativo", trial: "Trial", free: "Free", expired: "Expirado", premium: "Premium" };
    return <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs border font-medium ${map[status] ?? map.free}`}>{labels[status] ?? status}</span>;
  };

  const paymentBadge = (status: string) => {
    const map: Record<string, string> = {
      approved: "bg-emerald-500/20 text-emerald-400 border-emerald-500/30",
      pending: "bg-amber-500/20 text-amber-400 border-amber-500/30",
      rejected: "bg-red-500/20 text-red-400 border-red-500/30",
      cancelled: "bg-zinc-500/20 text-zinc-400 border-zinc-500/30",
      refunded: "bg-purple-500/20 text-purple-400 border-purple-500/30",
    };
    const labels: Record<string, string> = { approved: "Aprovado", pending: "Pendente", rejected: "Recusado", cancelled: "Cancelado", refunded: "Reembolsado" };
    return <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs border font-medium ${map[status] ?? map.pending}`}>{labels[status] ?? status}</span>;
  };

  return (
    <div className="space-y-4">
      {/* Section toggle */}
      <div className="flex gap-2">
        <Button size="sm" variant={activeSection === "users" ? "default" : "outline"} onClick={() => setActiveSection("users")} className="gap-1.5">
          <Users className="h-3.5 w-3.5" /> Usuários e Planos
        </Button>
        <Button size="sm" variant={activeSection === "payments" ? "default" : "outline"} onClick={() => setActiveSection("payments")} className="gap-1.5">
          <CreditCard className="h-3.5 w-3.5" /> Pagamentos
        </Button>
      </div>

      {/* ── Users section ── */}
      {activeSection === "users" && (
        <div className="space-y-3">
          <div className="flex gap-2 flex-wrap">
            <Input placeholder="Buscar por nome ou email..." value={search} onChange={(e) => setSearch(e.target.value)} className="max-w-xs font-sans bg-background text-sm" />
            <Select value={planFilter} onValueChange={(v) => setPlanFilter(v as any)}>
              <SelectTrigger className="w-40 font-sans bg-background text-sm"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todos os planos</SelectItem>
                <SelectItem value="free">Free</SelectItem>
                <SelectItem value="trial">Trial</SelectItem>
                <SelectItem value="premium">Premium</SelectItem>
                <SelectItem value="expired">Expirados</SelectItem>
              </SelectContent>
            </Select>
            <Button size="sm" variant="outline" onClick={() => refetchUsers()} disabled={usersFetching} className="gap-1">{usersFetching ? <Loader2 className="h-3 w-3 animate-spin" /> : <RefreshCw className="h-3 w-3" />} Atualizar</Button>
          </div>

          {usersLoading ? (
            <div className="flex justify-center py-8"><Loader2 className="h-6 w-6 animate-spin text-muted-foreground" /></div>
          ) : (
            <div className="rounded-lg border border-border/50 overflow-hidden">
              <table className="w-full text-sm">
                <thead className="bg-muted/30">
                  <tr>
                    <th className="text-left px-3 py-2 font-medium text-muted-foreground">Usuário</th>
                    <th className="text-left px-3 py-2 font-medium text-muted-foreground">Status</th>
                    <th className="text-left px-3 py-2 font-medium text-muted-foreground">Trial</th>
                    <th className="text-left px-3 py-2 font-medium text-muted-foreground">Vencimento Premium</th>
                    <th className="text-left px-3 py-2 font-medium text-muted-foreground">Ações</th>
                  </tr>
                </thead>
                <tbody>
                  {(usersData?.users ?? []).map((u) => (
                    <tr key={u.id} className="border-t border-border/30 hover:bg-muted/10">
                      <td className="px-3 py-2">
                        <div className="font-medium">{u.name ?? "—"}</div>
                        <div className="text-xs text-muted-foreground">{u.email ?? "—"}</div>
                      </td>
                      <td className="px-3 py-2">{statusBadge((u as any).status)}</td>
                      <td className="px-3 py-2">
                        {u.trialEndsAt ? (
                          <div className="text-xs">
                            <div>{(u as any).trialDays != null ? `${(u as any).trialDays} dias restantes` : "—"}</div>
                            <div className="text-muted-foreground">{new Date(u.trialEndsAt).toLocaleDateString("pt-BR")}</div>
                          </div>
                        ) : <span className="text-muted-foreground text-xs">Não iniciado</span>}
                      </td>
                      <td className="px-3 py-2">
                        {u.premiumEndsAt ? (
                          <div className="text-xs">
                            <div className={(u as any).premiumDays === 0 ? "text-red-400" : ""}>{(u as any).premiumDays != null ? `${(u as any).premiumDays} dias` : "—"}</div>
                            <div className="text-muted-foreground">{new Date(u.premiumEndsAt).toLocaleDateString("pt-BR")}</div>
                          </div>
                        ) : <span className="text-muted-foreground text-xs">—</span>}
                      </td>
                      <td className="px-3 py-2">
                        <div className="flex gap-1 flex-wrap">
                          {u.plan !== "premium" ? (
                            <Button size="sm" variant="outline" className="h-6 text-xs gap-1 border-amber-500/40 text-amber-400 hover:bg-amber-500/10"
                              onClick={() => { setPremiumTargetId(u.id); setPremiumDays(30); setShowPremiumDialog(true); }}>
                              <Crown className="h-3 w-3" /> Premium
                            </Button>
                          ) : (
                            <Button size="sm" variant="outline" className="h-6 text-xs gap-1 border-zinc-500/40 text-zinc-400 hover:bg-zinc-500/10"
                              onClick={() => updatePlan.mutate({ userId: u.id, plan: "free" })}>
                              <XCircle className="h-3 w-3" /> Revogar
                            </Button>
                          )}
                          <Button size="sm" variant="outline" className="h-6 text-xs gap-1"
                            onClick={() => { setExtendUserId(u.id); setShowExtendDialog(true); }}>
                            <Timer className="h-3 w-3" /> Trial
                          </Button>
                          <Button size="sm" variant="outline" className="h-6 text-xs gap-1 border-blue-500/40 text-blue-400 hover:bg-blue-500/10"
                            onClick={() => { setSelectedUserId(u.id); setShowPaymentDialog(true); }}>
                            <CreditCard className="h-3 w-3" /> Pagamento
                          </Button>
                          <Button size="sm" variant="outline" className="h-6 text-xs gap-1 border-red-500/40 text-red-400 hover:bg-red-500/10"
                            onClick={() => { if (window.confirm(`Deletar ${u.name ?? u.email}? Esta ação não pode ser desfeita.`)) deleteUser.mutate({ userId: u.id }); }}>
                            <Trash2 className="h-3 w-3" /> Deletar
                          </Button>
                        </div>
                      </td>
                    </tr>
                  ))}
                  {(usersData?.users ?? []).length === 0 && (
                    <tr><td colSpan={5} className="px-3 py-6 text-center text-muted-foreground text-sm">Nenhum usuário encontrado</td></tr>
                  )}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {/* ── Payments section ── */}
      {activeSection === "payments" && (
        <div className="space-y-3">
          <div className="flex gap-2 flex-wrap">
            <Input placeholder="Buscar por nome ou email..." value={paymentSearch} onChange={(e) => setPaymentSearch(e.target.value)} className="max-w-xs font-sans bg-background text-sm" />
            <Select value={paymentStatusFilter} onValueChange={(v) => setPaymentStatusFilter(v as any)}>
              <SelectTrigger className="w-44 font-sans bg-background text-sm"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todos os status</SelectItem>
                <SelectItem value="pending">Pendente</SelectItem>
                <SelectItem value="approved">Aprovado</SelectItem>
                <SelectItem value="rejected">Recusado</SelectItem>
                <SelectItem value="cancelled">Cancelado</SelectItem>
                <SelectItem value="refunded">Reembolsado</SelectItem>
              </SelectContent>
            </Select>
            <Button size="sm" variant="outline" onClick={() => refetchPayments()} disabled={paymentsFetching} className="gap-1">{paymentsFetching ? <Loader2 className="h-3 w-3 animate-spin" /> : <RefreshCw className="h-3 w-3" />} Atualizar</Button>
            <Button size="sm" variant="outline" onClick={() => { setMpSyncOpen(true); setMpSyncResult(null); setMpSyncId(""); }} className="gap-1 border-green-500/40 text-green-600 hover:bg-green-500/10">
              <RefreshCw className="h-3 w-3" /> Sincronizar MP
            </Button>
          </div>

          {/* MP Sync dialog */}
          {mpSyncOpen && (
            <div className="rounded-xl border border-green-500/30 bg-green-500/5 p-4 space-y-4">
              <div className="flex items-center justify-between">
                <h3 className="font-semibold text-sm text-green-400">Sincronização Mercado Pago</h3>
                <button onClick={() => setMpSyncOpen(false)} className="text-muted-foreground hover:text-foreground text-lg leading-none">×</button>
              </div>

              {/* Manual sync by ID */}
              <div>
                <p className="text-xs text-muted-foreground mb-2">Sincronizar pagamento por ID do MP (para ativar manualmente uma compra não processada):</p>
                <div className="flex gap-2">
                  <Input
                    placeholder="ID do pagamento no MP (ex: 1234567890)"
                    value={mpSyncId}
                    onChange={e => setMpSyncId(e.target.value)}
                    className="max-w-xs text-sm"
                  />
                  <Button size="sm" onClick={() => syncMPPayment.mutate({ paymentId: mpSyncId })} disabled={!mpSyncId || syncMPPayment.isPending}>
                    {syncMPPayment.isPending ? <Loader2 className="h-3 w-3 animate-spin" /> : "Processar"}
                  </Button>
                </div>
                {mpSyncResult && (
                  <div className="mt-2 text-xs rounded-lg bg-card border border-border/50 p-3 space-y-1">
                    <p><span className="text-muted-foreground">Ação:</span> <strong className={mpSyncResult.action === "activated" ? "text-green-400" : "text-amber-400"}>{mpSyncResult.action}</strong></p>
                    {mpSyncResult.payerEmail && <p><span className="text-muted-foreground">Payer email:</span> {mpSyncResult.payerEmail}</p>}
                    {mpSyncResult.amount && <p><span className="text-muted-foreground">Valor:</span> R$ {Number(mpSyncResult.amount).toFixed(2)}</p>}
                    {mpSyncResult.userId && <p><span className="text-muted-foreground">User ID ativado:</span> {mpSyncResult.userId}</p>}
                    {mpSyncResult.premiumEndsAt && <p><span className="text-muted-foreground">Premium até:</span> {new Date(mpSyncResult.premiumEndsAt).toLocaleDateString("pt-BR")}</p>}
                  </div>
                )}
              </div>

              {/* Recent MP payments list */}
              <div>
                <div className="flex items-center justify-between mb-2">
                  <p className="text-xs text-muted-foreground">Últimos 7 dias no MP vs. banco de dados:</p>
                  <Button size="sm" variant="ghost" onClick={() => refetchRecentMP()} className="h-6 text-xs gap-1">
                    {mpFetching ? <Loader2 className="h-3 w-3 animate-spin" /> : <RefreshCw className="h-3 w-3" />} Atualizar
                  </Button>
                </div>
                {mpFetching ? (
                  <div className="flex justify-center py-4"><Loader2 className="h-4 w-4 animate-spin text-muted-foreground" /></div>
                ) : (recentMPData?.payments ?? []).length === 0 ? (
                  <p className="text-xs text-muted-foreground py-2">Nenhum pagamento aprovado encontrado.</p>
                ) : (
                  <div className="rounded-lg border border-border/40 overflow-hidden">
                    <table className="w-full text-xs">
                      <thead className="bg-muted/20">
                        <tr>
                          <th className="text-left px-3 py-2 text-muted-foreground">MP ID</th>
                          <th className="text-left px-3 py-2 text-muted-foreground">Email</th>
                          <th className="text-left px-3 py-2 text-muted-foreground">Valor</th>
                          <th className="text-left px-3 py-2 text-muted-foreground">Data</th>
                          <th className="text-left px-3 py-2 text-muted-foreground">No DB</th>
                          <th className="px-3 py-2" />
                        </tr>
                      </thead>
                      <tbody>
                        {(recentMPData?.payments ?? []).map((p: any) => (
                          <tr key={p.mpId} className="border-t border-border/20">
                            <td className="px-3 py-1.5 font-mono">{p.mpId}</td>
                            <td className="px-3 py-1.5 text-muted-foreground">{p.payerEmail ?? "—"}</td>
                            <td className="px-3 py-1.5">R$ {Number(p.amount).toFixed(2)}</td>
                            <td className="px-3 py-1.5 text-muted-foreground">{p.date ? new Date(p.date).toLocaleDateString("pt-BR") : "—"}</td>
                            <td className="px-3 py-1.5">
                              {p.inDb
                                ? <span className="text-green-400">✓ {p.dbStatus}</span>
                                : <span className="text-red-400">✗ ausente</span>
                              }
                            </td>
                            <td className="px-3 py-1.5">
                              {!p.inDb && (
                                <button
                                  onClick={() => { setMpSyncId(p.mpId); syncMPPayment.mutate({ paymentId: p.mpId }); }}
                                  className="text-green-500 hover:text-green-400 font-medium"
                                  disabled={syncMPPayment.isPending}
                                >
                                  Ativar
                                </button>
                              )}
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </div>
            </div>
          )}

          {paymentsLoading ? (
            <div className="flex justify-center py-8"><Loader2 className="h-6 w-6 animate-spin text-muted-foreground" /></div>
          ) : (
            <div className="rounded-lg border border-border/50 overflow-hidden">
              <table className="w-full text-sm">
                <thead className="bg-muted/30">
                  <tr>
                    <th className="text-left px-3 py-2 font-medium text-muted-foreground">Usuário</th>
                    <th className="text-left px-3 py-2 font-medium text-muted-foreground">Valor</th>
                    <th className="text-left px-3 py-2 font-medium text-muted-foreground">Plano</th>
                    <th className="text-left px-3 py-2 font-medium text-muted-foreground">Método</th>
                    <th className="text-left px-3 py-2 font-medium text-muted-foreground">Status</th>
                    <th className="text-left px-3 py-2 font-medium text-muted-foreground">Data</th>
                    <th className="text-left px-3 py-2 font-medium text-muted-foreground">Ações</th>
                  </tr>
                </thead>
                <tbody>
                  {(paymentsData?.payments ?? []).map((p: any) => (
                    <tr key={p.id} className="border-t border-border/30 hover:bg-muted/10">
                      <td className="px-3 py-2">
                        <div className="font-medium">{p.userName ?? "—"}</div>
                        <div className="text-xs text-muted-foreground">{p.userEmail ?? "—"}</div>
                      </td>
                      <td className="px-3 py-2 font-medium">R$ {Number(p.amount).toFixed(2)}</td>
                      <td className="px-3 py-2 text-xs capitalize">{p.planType ?? "—"}</td>
                      <td className="px-3 py-2 text-xs capitalize">{p.paymentMethod ?? "—"}</td>
                      <td className="px-3 py-2">
                        {paymentBadge(p.status)}
                        {p.failureReason && <div className="text-xs text-red-400 mt-0.5 max-w-[160px] truncate" title={p.failureReason}>{p.failureReason}</div>}
                      </td>
                      <td className="px-3 py-2 text-xs text-muted-foreground">{new Date(p.createdAt).toLocaleDateString("pt-BR")}</td>
                      <td className="px-3 py-2">
                        <div className="flex gap-1 flex-wrap">
                          {p.status === "pending" && (
                            <>
                              <Button size="sm" variant="outline" className="h-6 text-xs gap-1 border-emerald-500/40 text-emerald-400 hover:bg-emerald-500/10"
                                onClick={() => updatePayment.mutate({ paymentId: p.id, status: "approved", activatePlan: true, planType: p.planType ?? "monthly" })}>
                                <CheckCircle className="h-3 w-3" /> Aprovar
                              </Button>
                              <Button size="sm" variant="outline" className="h-6 text-xs gap-1 border-red-500/40 text-red-400 hover:bg-red-500/10"
                                onClick={() => { const reason = prompt("Motivo da recusa:"); if (reason !== null) updatePayment.mutate({ paymentId: p.id, status: "rejected", failureReason: reason }); }}>
                                <XCircle className="h-3 w-3" /> Recusar
                              </Button>
                            </>
                          )}
                          {p.status === "approved" && (
                            <Button size="sm" variant="outline" className="h-6 text-xs gap-1 border-purple-500/40 text-purple-400 hover:bg-purple-500/10"
                              onClick={() => updatePayment.mutate({ paymentId: p.id, status: "refunded" })}>
                              <RefreshCw className="h-3 w-3" /> Reembolsar
                            </Button>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}
                  {(paymentsData?.payments ?? []).length === 0 && (
                    <tr><td colSpan={7} className="px-3 py-6 text-center text-muted-foreground text-sm">Nenhum pagamento encontrado</td></tr>
                  )}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {/* Extend Trial Dialog */}
      <Dialog open={showExtendDialog} onOpenChange={setShowExtendDialog}>
        <DialogContent className="bg-card border-border/50 max-w-sm">
          <DialogHeader><DialogTitle className="font-serif">Estender Trial</DialogTitle></DialogHeader>
          <div className="space-y-3">
            <p className="text-sm text-muted-foreground">Quantos dias deseja adicionar ao trial do usuário?</p>
            <div className="flex gap-2">
              {[7, 14, 30].map((d) => (
                <Button key={d} size="sm" variant={extendDays === d ? "default" : "outline"} onClick={() => setExtendDays(d)}>{d} dias</Button>
              ))}
              <Input type="number" min={1} max={365} value={extendDays} onChange={(e) => setExtendDays(Number(e.target.value))} className="w-20 font-sans bg-background text-sm" />
            </div>
            <div className="flex gap-2 justify-end">
              <Button variant="outline" onClick={() => setShowExtendDialog(false)}>Cancelar</Button>
              <Button onClick={() => extendUserId && extendTrial.mutate({ userId: extendUserId, days: extendDays })} disabled={extendTrial.isPending}>
                {extendTrial.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : "Confirmar"}
              </Button>
            </div>
          </div>
        </DialogContent>
      </Dialog>

      {/* Premium Duration Dialog */}
      <Dialog open={showPremiumDialog} onOpenChange={setShowPremiumDialog}>
        <DialogContent className="bg-card border-border/50 max-w-sm">
          <DialogHeader><DialogTitle className="font-serif">Ativar Premium</DialogTitle></DialogHeader>
          <div className="space-y-3">
            <p className="text-sm text-muted-foreground">Duração do acesso premium:</p>
            <div className="flex gap-2 flex-wrap">
              {[30, 90, 180, 365].map((d) => (
                <Button key={d} size="sm" variant={premiumDays === d ? "default" : "outline"} onClick={() => setPremiumDays(d)}>{d} dias</Button>
              ))}
              <Input type="number" min={1} max={3650} value={premiumDays} onChange={(e) => setPremiumDays(Number(e.target.value))} className="w-24 font-sans bg-background text-sm" />
            </div>
            <div className="flex gap-2 justify-end">
              <Button variant="outline" onClick={() => setShowPremiumDialog(false)}>Cancelar</Button>
              <Button onClick={() => { if (premiumTargetId) { updatePlan.mutate({ userId: premiumTargetId, plan: "premium", planType: premiumDays >= 365 ? "annual" : "monthly", durationDays: premiumDays }); setShowPremiumDialog(false); } }} disabled={updatePlan.isPending}>
                {updatePlan.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : "Confirmar"}
              </Button>
            </div>
          </div>
        </DialogContent>
      </Dialog>

      {/* Create Payment Record Dialog */}
      <Dialog open={showPaymentDialog} onOpenChange={setShowPaymentDialog}>
        <DialogContent className="bg-card border-border/50 max-w-sm">
          <DialogHeader><DialogTitle className="font-serif">Registrar Pagamento</DialogTitle></DialogHeader>
          <div className="space-y-3">
            <div>
              <label className="text-xs text-muted-foreground mb-1 block">Valor (R$)</label>
              <Input type="number" min={0} step={0.01} value={newPayment.amount} onChange={(e) => setNewPayment((p) => ({ ...p, amount: e.target.value }))} className="font-sans bg-background text-sm" placeholder="Ex: 39.90" />
            </div>
            <div>
              <label className="text-xs text-muted-foreground mb-1 block">Plano</label>
              <Select value={newPayment.planType} onValueChange={(v) => setNewPayment((p) => ({ ...p, planType: v as any }))}>
                <SelectTrigger className="font-sans bg-background text-sm"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="monthly">Mensal (R$ 39,90)</SelectItem>
                  <SelectItem value="annual">Anual (R$ 299,00)</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div>
              <label className="text-xs text-muted-foreground mb-1 block">Método</label>
              <Select value={newPayment.paymentMethod} onValueChange={(v) => setNewPayment((p) => ({ ...p, paymentMethod: v }))}>
                <SelectTrigger className="font-sans bg-background text-sm"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="manual">Manual</SelectItem>
                  <SelectItem value="pix">PIX</SelectItem>
                  <SelectItem value="credit_card">Cartão de Crédito</SelectItem>
                  <SelectItem value="boleto">Boleto</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div>
              <label className="text-xs text-muted-foreground mb-1 block">Observações</label>
              <Input value={newPayment.notes} onChange={(e) => setNewPayment((p) => ({ ...p, notes: e.target.value }))} className="font-sans bg-background text-sm" placeholder="Opcional" />
            </div>
            <div className="flex gap-2 justify-end">
              <Button variant="outline" onClick={() => setShowPaymentDialog(false)}>Cancelar</Button>
              <Button onClick={() => selectedUserId && createPayment.mutate({ userId: selectedUserId, amount: Number(newPayment.amount), planType: newPayment.planType, paymentMethod: newPayment.paymentMethod, notes: newPayment.notes || undefined, status: "approved", activatePlan: true })} disabled={createPayment.isPending || !newPayment.amount}>
                {createPayment.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : "Registrar e Ativar"}
              </Button>
            </div>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  );
}

// ─── Assinantes Tab ──────────────────────────────────────────────────────────
function AssinantesTab() {
  const { language } = useLanguage();
  const isPt = language === "pt";

  const [planFilter, setPlanFilter] = useState<"all" | "trial" | "premium" | "free" | "expired">("all");
  const [expiryFilter, setExpiryFilter] = useState<"all" | "7" | "15" | "30" | "expired">("all");
  const [search, setSearch] = useState("");
  
  const [showPremiumDialog, setShowPremiumDialog] = useState(false);
  const [premiumTargetId, setPremiumTargetId] = useState<number | null>(null);
  const [premiumDays, setPremiumDays] = useState<number>(30);

  const { data, isLoading, refetch } = trpc.plans.listUsersWithPlans.useQuery({
    page: 1,
    limit: 100,
    planFilter: planFilter === "all" ? undefined : planFilter,
  });

  const updatePlan = trpc.plans.updateUserPlan.useMutation({
    onSuccess: () => { toast.success(isPt ? "Plano atualizado" : "Plan updated"); refetch(); },
    onError: (e) => toast.error(e.message),
  });

  const users = (data?.users ?? []).filter((u: any) => {
    const matchSearch = !search || u.name?.toLowerCase().includes(search.toLowerCase()) || u.email?.toLowerCase().includes(search.toLowerCase());
    if (!matchSearch) return false;
    if (expiryFilter === "all") return true;
    const now = Date.now();
    if (expiryFilter === "expired") {
      return u.plan === "premium" && u.premiumEndsAt && new Date(u.premiumEndsAt).getTime() < now;
    }
    const days = parseInt(expiryFilter);
    const deadline = now + days * 86400000;
    if (u.plan === "premium" && u.premiumEndsAt) {
      const end = new Date(u.premiumEndsAt).getTime();
      return end > now && end <= deadline;
    }
    if (u.plan === "trial" && u.trialEndsAt) {
      const end = new Date(u.trialEndsAt).getTime();
      return end > now && end <= deadline;
    }
    return false;
  });

  function daysLeft(dateStr: string | null | undefined): number | null {
    if (!dateStr) return null;
    const diff = new Date(dateStr).getTime() - Date.now();
    return Math.ceil(diff / 86400000);
  }

  function planBadge(u: any) {
    const days = u.plan === "premium" ? daysLeft(u.premiumEndsAt) : daysLeft(u.trialEndsAt);
    if (u.plan === "premium") {
      if (days !== null && days <= 0) return <Badge className="bg-red-500/20 text-red-400 border-red-500/30 font-sans text-xs">{isPt ? "Expirado" : "Expired"}</Badge>;
      if (days !== null && days <= 7) return <Badge className="bg-orange-500/20 text-orange-400 border-orange-500/30 font-sans text-xs">{isPt ? `Expira em ${days}d` : `Expires in ${days}d`}</Badge>;
      return <Badge className="bg-yellow-500/20 text-yellow-400 border-yellow-500/30 font-sans text-xs"><Crown className="h-3 w-3 mr-1" />Premium</Badge>;
    }
    if (u.plan === "trial") {
      if (days !== null && days <= 0) return <Badge className="bg-red-500/20 text-red-400 border-red-500/30 font-sans text-xs">{isPt ? "Trial expirado" : "Trial expired"}</Badge>;
      if (days !== null && days <= 3) return <Badge className="bg-orange-500/20 text-orange-400 border-orange-500/30 font-sans text-xs">{isPt ? `Trial: ${days}d` : `Trial: ${days}d`}</Badge>;
      return <Badge className="bg-blue-500/20 text-blue-400 border-blue-500/30 font-sans text-xs"><Timer className="h-3 w-3 mr-1" />Trial</Badge>;
    }
    return <Badge className="bg-muted/50 text-muted-foreground border-border font-sans text-xs">Free</Badge>;
  }

  // Stats
  const premiumCount = (data?.users ?? []).filter((u: any) => u.plan === "premium").length;
  const trialCount = (data?.users ?? []).filter((u: any) => u.plan === "trial").length;
  const freeCount = (data?.users ?? []).filter((u: any) => u.plan === "free").length;
  const expiringCount = (data?.users ?? []).filter((u: any) => {
    const days = u.plan === "premium" ? daysLeft(u.premiumEndsAt) : u.plan === "trial" ? daysLeft(u.trialEndsAt) : null;
    return days !== null && days > 0 && days <= 7;
  }).length;

  return (
    <div className="space-y-6">
      {/* Summary cards */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div className="p-4 rounded-xl bg-yellow-500/10 border border-yellow-500/20">
          <div className="flex items-center gap-2 mb-1">
            <Crown className="h-4 w-4 text-yellow-400" />
            <span className="text-xs text-muted-foreground font-sans">Premium</span>
          </div>
          <div className="text-2xl font-bold font-serif text-yellow-400">{premiumCount}</div>
        </div>
        <div className="p-4 rounded-xl bg-blue-500/10 border border-blue-500/20">
          <div className="flex items-center gap-2 mb-1">
            <Timer className="h-4 w-4 text-blue-400" />
            <span className="text-xs text-muted-foreground font-sans">Trial</span>
          </div>
          <div className="text-2xl font-bold font-serif text-blue-400">{trialCount}</div>
        </div>
        <div className="p-4 rounded-xl bg-muted/30 border border-border/50">
          <div className="flex items-center gap-2 mb-1">
            <Users className="h-4 w-4 text-muted-foreground" />
            <span className="text-xs text-muted-foreground font-sans">Free</span>
          </div>
          <div className="text-2xl font-bold font-serif text-foreground">{freeCount}</div>
        </div>
        <div className="p-4 rounded-xl bg-orange-500/10 border border-orange-500/20">
          <div className="flex items-center gap-2 mb-1">
            <AlertTriangle className="h-4 w-4 text-orange-400" />
            <span className="text-xs text-muted-foreground font-sans">{isPt ? "Expirando em 7d" : "Expiring in 7d"}</span>
          </div>
          <div className="text-2xl font-bold font-serif text-orange-400">{expiringCount}</div>
        </div>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap gap-3 items-center">
        <div className="relative flex-1 min-w-[200px]">
          <input
            className="w-full h-9 rounded-md border border-border bg-background px-3 text-sm font-sans placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-primary"
            placeholder={isPt ? "Buscar por nome ou email..." : "Search by name or email..."}
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </div>
        <Select value={planFilter} onValueChange={(v) => setPlanFilter(v as any)}>
          <SelectTrigger className="w-40 font-sans text-sm bg-background">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">{isPt ? "Todos os planos" : "All plans"}</SelectItem>
            <SelectItem value="premium">Premium</SelectItem>
            <SelectItem value="trial">Trial</SelectItem>
            <SelectItem value="free">Free</SelectItem>
            <SelectItem value="expired">{isPt ? "Expirados" : "Expired"}</SelectItem>
          </SelectContent>
        </Select>
        <Select value={expiryFilter} onValueChange={(v) => setExpiryFilter(v as any)}>
          <SelectTrigger className="w-48 font-sans text-sm bg-background">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">{isPt ? "Qualquer vencimento" : "Any expiry"}</SelectItem>
            <SelectItem value="7">{isPt ? "Expira em 7 dias" : "Expires in 7 days"}</SelectItem>
            <SelectItem value="15">{isPt ? "Expira em 15 dias" : "Expires in 15 days"}</SelectItem>
            <SelectItem value="30">{isPt ? "Expira em 30 dias" : "Expires in 30 days"}</SelectItem>
            <SelectItem value="expired">{isPt ? "Já expirados" : "Already expired"}</SelectItem>
          </SelectContent>
        </Select>
        <Button size="sm" variant="outline" onClick={() => refetch()} className="gap-1 font-sans">
          <RefreshCw className="h-3.5 w-3.5" />
          {isPt ? "Atualizar" : "Refresh"}
        </Button>
      </div>

      {/* Table */}
      {isLoading ? (
        <div className="flex items-center justify-center py-12">
          <Loader2 className="h-6 w-6 animate-spin text-primary" />
        </div>
      ) : users.length === 0 ? (
        <div className="text-center py-12 text-muted-foreground font-sans">
          {isPt ? "Nenhum assinante encontrado" : "No subscribers found"}
        </div>
      ) : (
        <div className="rounded-xl border border-border/50 overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-card/80 border-b border-border/50">
              <tr>
                <th className="text-left p-3 font-sans text-muted-foreground font-medium">{isPt ? "Usuário" : "User"}</th>
                <th className="text-left p-3 font-sans text-muted-foreground font-medium">{isPt ? "Plano" : "Plan"}</th>
                <th className="text-left p-3 font-sans text-muted-foreground font-medium">{isPt ? "Início" : "Start"}</th>
                <th className="text-left p-3 font-sans text-muted-foreground font-medium">{isPt ? "Vencimento" : "Expires"}</th>
                <th className="text-left p-3 font-sans text-muted-foreground font-medium">{isPt ? "Dias restantes" : "Days left"}</th>
                <th className="text-left p-3 font-sans text-muted-foreground font-medium">{isPt ? "Ações" : "Actions"}</th>
              </tr>
            </thead>
            <tbody>
              {users.map((u: any, i: number) => {
                const isPremium = u.plan === "premium";
                const isTrial = u.plan === "trial";
                const endDate = isPremium ? u.premiumEndsAt : isTrial ? u.trialEndsAt : null;
                const startDate = isPremium ? u.premiumStartedAt : isTrial ? u.trialStartedAt : null;
                const days = daysLeft(endDate);
                const isExpired = days !== null && days <= 0;
                const isUrgent = days !== null && days > 0 && days <= 7;

                return (
                  <tr key={u.id} className={`border-b border-border/30 hover:bg-card/40 transition-colors ${isExpired ? "bg-red-500/5" : isUrgent ? "bg-orange-500/5" : ""}`}>
                    <td className="p-3">
                      <div className="font-sans font-medium text-foreground">{u.name || "—"}</div>
                      <div className="text-xs text-muted-foreground">{u.email}</div>
                    </td>
                    <td className="p-3">{planBadge(u)}</td>
                    <td className="p-3 text-muted-foreground font-sans text-xs">
                      {startDate ? new Date(startDate).toLocaleDateString(isPt ? "pt-BR" : "en-US") : "—"}
                    </td>
                    <td className="p-3 font-sans text-xs">
                      {endDate ? (
                        <span className={isExpired ? "text-red-400" : isUrgent ? "text-orange-400" : "text-foreground"}>
                          {new Date(endDate).toLocaleDateString(isPt ? "pt-BR" : "en-US")}
                        </span>
                      ) : "—"}
                    </td>
                    <td className="p-3">
                      {days !== null ? (
                        <div className="flex items-center gap-1.5">
                          {isExpired ? (
                            <span className="flex items-center gap-1 text-red-400 text-xs font-sans">
                              <AlertTriangle className="h-3.5 w-3.5" />
                              {isPt ? "Expirado" : "Expired"}
                            </span>
                          ) : isUrgent ? (
                            <span className="flex items-center gap-1 text-orange-400 text-xs font-sans">
                              <Clock className="h-3.5 w-3.5" />
                              {days} {isPt ? "dias" : "days"}
                            </span>
                          ) : (
                            <span className="flex items-center gap-1 text-emerald-400 text-xs font-sans">
                              <CheckCircle className="h-3.5 w-3.5" />
                              {days} {isPt ? "dias" : "days"}
                            </span>
                          )}
                        </div>
                      ) : "—"}
                    </td>
                    <td className="p-3">
                      <div className="flex gap-2">
                        {!isPremium && (
                          <Button
                            size="sm"
                            variant="outline"
                            className="h-7 text-xs font-sans border-yellow-500/40 text-yellow-400 hover:bg-yellow-500/10"
                            onClick={() => updatePlan.mutate({ userId: u.id, plan: "premium", planType: "monthly" })}
                          >
                            <Crown className="h-3 w-3 mr-1" />
                            {isPt ? "Ativar Premium" : "Activate Premium"}
                          </Button>
                        )}
                        {isPremium && (
                          <Button
                            size="sm"
                            variant="outline"
                            className="h-7 text-xs font-sans border-blue-500/40 text-blue-400 hover:bg-blue-500/10"
                            onClick={() => { setPremiumTargetId(u.id); setPremiumDays(30); setShowPremiumDialog(true); }}
                          >
                            <TrendingUp className="h-3 w-3 mr-1" />
                            +dias
                          </Button>
                        )}
                        {(isPremium || isExpired) && (
                          <Button
                            size="sm"
                            variant="outline"
                            className="h-7 text-xs font-sans border-red-500/40 text-red-400 hover:bg-red-500/10"
                            onClick={() => updatePlan.mutate({ userId: u.id, plan: "free" })}
                          >
                            <XCircle className="h-3 w-3 mr-1" />
                            {isPt ? "Cancelar" : "Cancel"}
                          </Button>
                        )}
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
      <p className="text-xs text-muted-foreground font-sans">
        {users.length} {isPt ? "assinantes exibidos" : "subscribers shown"}
        {expiringCount > 0 && (
          <span className="ml-2 text-orange-400">
            · {expiringCount} {isPt ? "expirando em 7 dias" : "expiring within 7 days"}
          </span>
        )}
      </p>

      {/* Premium Duration Dialog */}
      <Dialog open={showPremiumDialog} onOpenChange={setShowPremiumDialog}>
        <DialogContent className="bg-card border-border/50 max-w-sm">
          <DialogHeader><DialogTitle className="font-serif">{isPt ? "Ativar Premium" : "Activate Premium"}</DialogTitle></DialogHeader>
          <div className="space-y-3">
            <p className="text-sm text-muted-foreground">{isPt ? "Duração do acesso premium:" : "Premium access duration:"}</p>
            <div className="flex gap-2 flex-wrap">
              {[30, 90, 180, 365].map((d) => (
                <Button key={d} size="sm" variant={premiumDays === d ? "default" : "outline"} onClick={() => setPremiumDays(d)}>{d} {isPt ? "dias" : "days"}</Button>
              ))}
              <Input type="number" min={1} max={3650} value={premiumDays} onChange={(e) => setPremiumDays(Number(e.target.value))} className="w-24 font-sans bg-background text-sm" />
            </div>
            <div className="flex gap-2 justify-end">
              <Button variant="outline" onClick={() => setShowPremiumDialog(false)}>{isPt ? "Cancelar" : "Cancel"}</Button>
              <Button onClick={() => { if (premiumTargetId) { updatePlan.mutate({ userId: premiumTargetId, plan: "premium", planType: premiumDays >= 365 ? "annual" : "monthly", durationDays: premiumDays }); setShowPremiumDialog(false); } }} disabled={updatePlan.isPending}>
                {updatePlan.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : isPt ? "Confirmar" : "Confirm"}
              </Button>
            </div>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  );
}
