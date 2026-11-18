import { useAuth } from "@/_core/hooks/useAuth";
import { useI18n } from "@/contexts/I18nContext";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Loader2, BookOpen, FileText, BarChart3, GraduationCap, Users, Building2, Share2, CheckSquare, HelpCircle, CreditCard, User, Upload, TrendingUp, PenLine, Globe, Zap } from "lucide-react";
import { getLoginUrl } from "@/const";
import { useLocation } from "wouter";
import DashboardLayout from "@/components/DashboardLayout";
import { trpc } from "@/lib/trpc";
import { useActivityLog } from "@/hooks/useActivityLog";
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid } from "recharts";
import type { Locale } from "@shared/locales/index";

const LOCALE_FLAGS: Record<Locale, string> = { pt: "🇧🇷", en: "🇺🇸", es: "🇪🇸" };
const LOCALE_LABELS: Record<Locale, string> = { pt: "Português", en: "English", es: "Español" };

// ─── Landing page (unauthenticated) ───────────────────────────────────────────

function LandingPage() {
  const [, navigate] = useLocation();
  const { locale, setLocale } = useI18n();

  return (
    <div className="min-h-screen bg-gradient-to-br from-indigo-50 via-slate-50 to-blue-50">
      <nav className="border-b border-slate-200 bg-white/80 backdrop-blur-sm sticky top-0 z-50">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
          <div className="flex items-center gap-2">
            <PenLine className="w-5 h-5 text-primary" />
            <span className="text-xl font-bold text-slate-900">Prof-Aid</span>
          </div>
          <div className="flex items-center gap-2">
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="sm" className="h-9 px-2 gap-1.5">
                  <Globe className="w-4 h-4 text-slate-500" />
                  <span className="text-sm">{LOCALE_FLAGS[locale]}</span>
                  <span className="hidden sm:inline text-xs text-slate-500">{LOCALE_LABELS[locale]}</span>
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-36">
                {(["pt", "en", "es"] as Locale[]).map((l) => (
                  <DropdownMenuItem
                     key={l}
                     onClick={() => setLocale(l)}
                     className={`text-sm cursor-pointer ${locale === l ? "bg-accent font-medium" : ""}`}
                  >
                    {LOCALE_FLAGS[l]} {LOCALE_LABELS[l]}
                  </DropdownMenuItem>
                ))}
              </DropdownMenuContent>
            </DropdownMenu>
            <Button onClick={() => navigate(getLoginUrl())} className="bg-primary hover:bg-indigo-950 text-white">
              Entrar
            </Button>
          </div>
        </div>
      </nav>

      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
        <div className="text-center mb-16">
          <h1 className="text-5xl md:text-6xl font-bold text-slate-900 mb-6 leading-tight">
            Crie e Gerencie Avaliações
            <span className="block text-transparent bg-clip-text bg-gradient-to-r from-indigo-900 to-blue-700">
              com Simplicidade e Eficiência
            </span>
          </h1>
          <p className="text-xl text-slate-600 mb-8 max-w-2xl mx-auto">
            Plataforma completa para professores: banco de questões, geração de PDF,
            múltiplas versões embaralhadas e correção automática por câmera.
          </p>
          <Button
            onClick={() => navigate(getLoginUrl())}
            size="lg"
            className="bg-gradient-to-r from-indigo-900 to-blue-800 hover:from-indigo-950 hover:to-blue-900 text-white text-lg px-8 py-6"
          >
            Comece Gratuitamente
          </Button>
        </div>

        <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-6 mt-16">
          {[
            { icon: Upload, color: "text-indigo-900", title: "Importar Questões", desc: "XLSX, DOCX, PDF e JSON com processamento inteligente" },
            { icon: BookOpen, color: "text-blue-800", title: "Banco de Questões", desc: "4 tipos de questão com filtros e imagens" },
            { icon: FileText, color: "text-indigo-800", title: "Gerar PDFs", desc: "Múltiplas versões com embaralhamento automático" },
            { icon: CheckSquare, color: "text-blue-900", title: "Correção Automática", desc: "Gabaritos via câmera com nota em tempo real" },
          ].map((f) => (
            <Card key={f.title} className="border-0 shadow-lg hover:shadow-xl transition-shadow">
              <CardHeader>
                <f.icon className={`w-8 h-8 ${f.color} mb-2`} />
                <CardTitle className="text-base">{f.title}</CardTitle>
              </CardHeader>
              <CardContent>
                <p className="text-sm text-slate-600">{f.desc}</p>
              </CardContent>
            </Card>
          ))}
        </div>
      </div>
    </div>
  );
}

// ─── Dashboard (authenticated) ────────────────────────────────────────────────

function Dashboard() {
  const { user } = useAuth();
  const { t } = useI18n();
  const [, navigate] = useLocation();
  const { activities, clearLog } = useActivityLog();

  const { data: metrics, isLoading: metricsLoading } = trpc.reports.dashboardMetrics.useQuery();

  const stats = [
    { label: t("nav.questions"), value: metrics?.totalQuestions ?? 0, color: "text-blue-600 bg-blue-50", icon: BookOpen },
    { label: t("nav.assessments"), value: metrics?.totalExams ?? 0, color: "text-purple-600 bg-purple-50", icon: FileText },
    { label: t("nav.classes"), value: metrics?.totalClasses ?? 0, color: "text-green-600 bg-green-50", icon: GraduationCap },
    { label: t("nav.students"), value: metrics?.totalStudents ?? 0, color: "text-orange-600 bg-orange-50", icon: Users },
  ];

  const modules = [
    { icon: BookOpen,    color: "text-blue-600",   bg: "bg-blue-50",   title: t("nav.questions"),    desc: "Crie, importe e organize questões",         path: "/questions" },
    { icon: FileText,    color: "text-purple-600",  bg: "bg-purple-50", title: t("nav.assessments"),  desc: "Monte provas com múltiplas versões",         path: "/exams" },
    { icon: BarChart3,   color: "text-violet-600",  bg: "bg-violet-50", title: "Modelos de Prova",    desc: "Reutilize estruturas de distribuição de questões", path: "/exam-templates" },
    { icon: CheckSquare, color: "text-teal-600",    bg: "bg-teal-50",   title: t("nav.answerSheets"), desc: "Corrija gabaritos por câmera",               path: "/answer-sheets" },
    { icon: GraduationCap,color:"text-green-600",  bg: "bg-green-50",  title: t("nav.classes"),      desc: "Turmas por semestre com gradebook",          path: "/classes" },
    { icon: Users,       color: "text-orange-600",  bg: "bg-orange-50", title: t("nav.students"),     desc: "Cadastre e importe alunos por CSV/XLSX",     path: "/students" },
    { icon: Building2,   color: "text-indigo-600",  bg: "bg-indigo-50", title: t("nav.institutions"), desc: "Universidades, faculdades e escolas",        path: "/institutions" },
    { icon: Share2,      color: "text-pink-600",    bg: "bg-pink-50",   title: t("nav.sharing"),      desc: "Links públicos para alunos responderem",     path: "/sharing" },
    { icon: Upload,      color: "text-yellow-600",  bg: "bg-yellow-50", title: "Importar Questões",   desc: "Importe de XLSX, DOCX, PDF ou JSON",        path: "/import-questions" },
    { icon: User,        color: "text-slate-600",   bg: "bg-slate-50",  title: t("nav.myAccount"),    desc: "Perfil, logo da instituição e senha",        path: "/profile" },
    { icon: CreditCard,  color: "text-emerald-600", bg: "bg-emerald-50",title: t("nav.pricing"),      desc: "Planos disponíveis e recursos",              path: "/pricing" },
    { icon: HelpCircle,  color: "text-cyan-600",    bg: "bg-cyan-50",   title: t("nav.faq"),          desc: "Perguntas frequentes e suporte",             path: "/faq" },
  ];

  return (
    <div className="space-y-6">
      {/* Welcome */}
      <div className="flex items-start justify-between flex-wrap gap-4">
        <div>
          <h1 className="text-2xl font-bold">Bem-vindo, {user?.name?.split(" ")[0]}!</h1>
          <p className="text-muted-foreground text-sm mt-1">Aqui está um resumo do seu sistema</p>
        </div>
        <button
          onClick={() => navigate("/gabarito-rapido")}
          className="flex items-center gap-2 bg-primary text-primary-foreground px-5 py-2.5 rounded-xl font-semibold shadow hover:opacity-90 transition text-sm"
        >
          <Zap className="w-4 h-4" />
          Fazer Avaliação Rápida
        </button>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        {stats.map((s) => (
          <Card key={s.label} className="border shadow-sm">
            <CardContent className="py-4 px-4">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-xs text-muted-foreground">{s.label}</p>
                  <p className="text-2xl font-bold mt-0.5">{metricsLoading ? "–" : s.value}</p>
                </div>
                <div className={`p-2 rounded-lg ${s.color.split(" ")[1]}`}>
                  <s.icon className={`w-5 h-5 ${s.color.split(" ")[0]}`} />
                </div>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      {/* Charts row */}
      <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
        {/* Exams by month */}
        <Card className="border shadow-sm">
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-semibold flex items-center gap-2">
              <BarChart3 className="w-4 h-4 text-purple-500" />
              Avaliações por mês
            </CardTitle>
          </CardHeader>
          <CardContent>
            {metricsLoading ? (
              <div className="flex justify-center py-6"><Loader2 className="animate-spin w-5 h-5" /></div>
            ) : (
              <ResponsiveContainer width="100%" height={140}>
                <BarChart data={metrics?.examsByMonth ?? []} margin={{ top: 0, right: 0, left: -20, bottom: 0 }}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                  <XAxis dataKey="month" tick={{ fontSize: 10 }} />
                  <YAxis tick={{ fontSize: 10 }} allowDecimals={false} />
                  <Tooltip />
                  <Bar dataKey="exams" fill="#8b5cf6" radius={[3, 3, 0, 0]} name="Avaliações" />
                </BarChart>
              </ResponsiveContainer>
            )}
          </CardContent>
        </Card>

        {/* Avg grade + recent exams */}
        <Card className="border shadow-sm">
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-semibold flex items-center gap-2">
              <TrendingUp className="w-4 h-4 text-green-500" />
              Avaliações recentes
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            {metricsLoading ? (
              <div className="flex justify-center py-6"><Loader2 className="animate-spin w-5 h-5" /></div>
            ) : (metrics?.recentExams?.length ?? 0) === 0 ? (
              <p className="text-sm text-muted-foreground text-center py-4">Nenhuma avaliação criada.</p>
            ) : (
              <>
                {metrics?.avgGrade !== null && metrics?.avgGrade !== undefined && (
                  <div className="flex items-center gap-2 mb-3 p-2 bg-green-50 rounded-md">
                    <TrendingUp className="w-4 h-4 text-green-600" />
                    <span className="text-sm text-green-700 font-medium">
                      Média geral da turma: <strong>{metrics.avgGrade}/10</strong>
                    </span>
                  </div>
                )}
                {metrics?.recentExams?.map((e: any) => (
                  <div
                    key={e.id}
                    className="flex items-center justify-between py-1.5 border-b last:border-0 cursor-pointer hover:bg-slate-50 px-1 rounded"
                    onClick={() => navigate(`/exam/${e.id}/pdf`)}
                  >
                    <div className="min-w-0">
                      <p className="text-sm font-medium truncate">{e.name}</p>
                      <p className="text-xs text-muted-foreground">{e.discipline}</p>
                    </div>
                    <p className="text-xs text-muted-foreground shrink-0 ml-2">
                      {new Date(e.createdAt).toLocaleDateString("pt-BR")}
                    </p>
                  </div>
                ))}
              </>
            )}
          </CardContent>
        </Card>

        {/* Registro de Atividades Recentes */}
        <Card className="border shadow-sm col-span-1 md:col-span-2 lg:col-span-1">
          <CardHeader className="pb-2 flex flex-row items-center justify-between">
            <CardTitle className="text-sm font-semibold flex items-center gap-2">
              <TrendingUp className="w-4 h-4 text-emerald-500" />
              Atividades Recentes
            </CardTitle>
            {activities.length > 0 && (
              <Button
                variant="ghost"
                size="sm"
                className="h-6 text-[10px] text-muted-foreground px-1.5 hover:text-destructive"
                onClick={clearLog}
              >
                Limpar
              </Button>
            )}
          </CardHeader>
          <CardContent className="py-2">
            {activities.length === 0 ? (
              <p className="text-xs text-muted-foreground text-center py-8">Nenhuma atividade recente.</p>
            ) : (
              <div className="space-y-1.5 max-h-[160px] overflow-y-auto pr-1">
                {activities.map((a) => (
                  <div
                    key={a.id}
                    onClick={() => navigate(a.path)}
                    className="flex items-center justify-between p-2 rounded border border-slate-100 bg-slate-50/50 hover:bg-slate-50 cursor-pointer hover:border-primary/20 transition-all text-xs"
                    title={`Clique para ir para: ${a.label}`}
                  >
                    <div className="min-w-0 flex-1 flex items-center gap-1.5">
                      <div className="w-1.5 h-1.5 rounded-full shrink-0 bg-emerald-500" />
                      <span className="font-medium text-slate-800 truncate">{a.label}</span>
                    </div>
                    <span className="text-[10px] text-muted-foreground ml-2 shrink-0">
                      {new Date(a.timestamp).toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" })}
                    </span>
                  </div>
                ))}
              </div>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Modules grid */}
      <div>
        <h2 className="text-sm font-semibold text-muted-foreground uppercase tracking-wider mb-3">Módulos</h2>
        <div className="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
          {modules.map((m) => (
            <Card
              key={m.path}
              className="border cursor-pointer hover:shadow-md hover:border-primary/30 transition-all group"
              onClick={() => navigate(m.path)}
            >
              <CardContent className="py-4 px-4 flex items-start gap-3">
                <div className={`p-2 rounded-lg ${m.bg} shrink-0 mt-0.5 group-hover:scale-110 transition-transform`}>
                  <m.icon className={`w-4 h-4 ${m.color}`} />
                </div>
                <div className="min-w-0">
                  <p className="font-medium text-sm leading-tight">{m.title}</p>
                  <p className="text-xs text-muted-foreground mt-0.5 line-clamp-2">{m.desc}</p>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      </div>

    </div>
  );
}

// ─── Root component ────────────────────────────────────────────────────────────

export default function Home() {
  const { user, loading } = useAuth();

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <Loader2 className="animate-spin w-8 h-8 text-blue-600" />
      </div>
    );
  }

  if (!user) {
    return <LandingPage />;
  }

  return (
    <DashboardLayout>
      <Dashboard />
    </DashboardLayout>
  );
}
