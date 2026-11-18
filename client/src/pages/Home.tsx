import { useAuth } from "@/_core/hooks/useAuth";
import SEO from "@/components/SEO";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { useLanguage, useT } from "@/contexts/LanguageContext";
import { useAuthModal } from "@/contexts/AuthModalContext";
import { trpc } from "@/lib/trpc";
import {
  ArrowRight,
  Award,
  BookOpen,
  Check,
  ChevronRight,
  ClipboardList,
  Crown,
  FlaskConical,
  Globe,
  GraduationCap,
  Stethoscope,
  Target,
  TrendingUp,
  Trophy,
  Zap,
} from "lucide-react";
import { useEffect } from "react";
import { Link, useLocation } from "wouter";
import { LiveUpdatePill, UpdatesTimeline, useUpdatesFeed } from "@/components/UpdatesFeed";


const DISCIPLINE_ICONS: Record<string, string> = {
  "ciencias-biologicas": "🔬",
  "patobiologia": "🦠",
  "clinica-cirurgia": "🏥",
  "medicina-preventiva": "🛡️",
  "zootecnia": "🐄",
  "etica-humanidades": "⚖️",
  // legacy fallbacks
  pharmacology: "💊",
  clinics: "🩺",
  herpetology: "🦎",
  ornithology: "🦜",
  anesthesiology: "💉",
  "small-mammals": "🐹",
};

const LEVEL_ICONS = ["🎓", "🔬", "🩺", "⭐", "💡", "🔭", "📚", "🏆", "👑"];

export default function Home() {
  const { isAuthenticated } = useAuth();
  const [, setLocation] = useLocation();

  useEffect(() => {
    if (isAuthenticated) {
      setLocation("/dashboard");
    }
  }, [isAuthenticated, setLocation]);

  const t = useT();
  const { language } = useLanguage();
  const { openLogin } = useAuthModal();
  const { data: disciplines } = trpc.questions.disciplines.useQuery();
  const { data: updatesFeed } = useUpdatesFeed();

  const isPt = language === "pt";

  const features = [
    { icon: BookOpen, title: t("feat_bank_title"), desc: t("feat_bank_desc"), color: "text-primary" },
    { icon: FlaskConical, title: t("feat_exam_title"), desc: t("feat_exam_desc"), color: "text-cyan-400" },
    { icon: Zap, title: t("feat_gamification_title"), desc: t("feat_gamification_desc"), color: "text-yellow-400" },
    { icon: Trophy, title: t("feat_ranking_title"), desc: t("feat_ranking_desc"), color: "text-orange-400" },
    { icon: Globe, title: t("feat_bilingual_title"), desc: t("feat_bilingual_desc"), color: "text-blue-400" },
  ];

  const levels = [
    t("level_1"), t("level_2"), t("level_3"), t("level_4"), t("level_5"),
    t("level_6"), t("level_7"), t("level_8"), t("level_9"),
  ];

  const plans = [
    {
      name: t("plan_free"),
      price: t("plan_free_price"),
      period: "",
      desc: t("plan_free_desc"),
      features: t("plan_free_features") as unknown as string[],
      cta: t("plan_cta_free"),
      highlight: false,
      badge: null,
      noCard: false,
      subtext: null,
      discount: null,
    },
    {
      name: t("plan_monthly"),
      price: t("plan_monthly_price"),
      period: t("plan_per_month"),
      desc: t("plan_monthly_desc"),
      features: t("plan_trial_features") as unknown as string[],
      cta: t("plan_cta_monthly"),
      highlight: false,
      badge: null,
      noCard: true,
      subtext: null,
      discount: null,
    },
    {
      name: t("plan_premium"),
      price: t("plan_premium_price"),
      period: t("plan_per_year"),
      desc: t("plan_premium_desc"),
      features: t("plan_premium_features") as unknown as string[],
      cta: t("plan_cta_premium"),
      highlight: true,
      badge: t("plan_popular"),
      noCard: true,
      subtext: t("plan_monthly_equiv"),
      discount: t("plan_discount"),
    },
  ];

  const fallbackDisciplines = [
    { slug: "pharmacology", namePt: "Farmacologia", nameEn: "Pharmacology" },
    { slug: "clinics", namePt: "Clínica", nameEn: "Clinics" },
    { slug: "herpetology", namePt: "Herpetologia", nameEn: "Herpetology" },
    { slug: "ornithology", namePt: "Ornitologia", nameEn: "Ornithology" },
    { slug: "anesthesiology", namePt: "Anestesiologia", nameEn: "Anesthesiology" },
    { slug: "small-mammals", namePt: "Pequenos Mamíferos", nameEn: "Small Mammals" },
  ];

  const displayDisciplines = (disciplines && disciplines.length > 0) ? disciplines : fallbackDisciplines;

  const jsonLd = [
    {
      "@context": "https://schema.org",
      "@type": "Organization",
      name: "VetRank",
      url: "https://vetrank.com.br",
      logo: "https://vetrank.com.br/og-image.png",
      description: "Plataforma de preparação para Medicina Veterinária com banco de questões, simulados e gamificação.",
      sameAs: [],
    },
    {
      "@context": "https://schema.org",
      "@type": "WebSite",
      name: "VetRank",
      url: "https://vetrank.com.br",
      potentialAction: {
        "@type": "SearchAction",
        target: "https://vetrank.com.br/bank?search={search_term_string}",
        "query-input": "required name=search_term_string",
      },
    },
    {
      "@context": "https://schema.org",
      "@type": "EducationalOrganization",
      name: "VetRank",
      url: "https://vetrank.com.br",
      description: "Preparação para ENADE, Residência Veterinária, ENARE e provas de graduação em Medicina Veterinária.",
      educationalCredentialAwarded: "Preparação para concursos e provas veterinárias",
    },
  ];

  return (
    <div className="min-h-screen bg-background">
      <SEO
        canonical="/"
        jsonLd={jsonLd}
        description="Prepare-se para ENADE, Residência, ENARE e provas de graduação em Medicina Veterinária. Banco de questões, simulados cronometrados e gamificação."
      />
      {/* Hero */}
      <section className="relative overflow-hidden gradient-hero py-24 md:py-36">
        <div className="absolute inset-0 pointer-events-none">
          <div className="absolute top-1/4 left-1/4 w-96 h-96 rounded-full bg-primary/5 blur-3xl" />
          <div className="absolute bottom-1/4 right-1/4 w-64 h-64 rounded-full bg-cyan-500/5 blur-3xl" />
        </div>
        <div className="container relative">
          <div className="max-w-4xl mx-auto text-center">
            <div className="flex flex-wrap items-center justify-center gap-2 mb-6">
              <Badge className="bg-primary/15 text-primary border-primary/30 hover:bg-primary/20 px-4 py-1.5 text-sm font-sans">
                <Zap className="h-3.5 w-3.5 mr-1.5" />
                {t("hero_badge")}
              </Badge>
              <LiveUpdatePill />
            </div>
            <h1 className="font-serif text-5xl md:text-7xl font-bold mb-6 leading-tight">
              <span className="text-gradient">{t("hero_title")}</span>
            </h1>
            <p className="text-lg md:text-xl text-muted-foreground mb-4 max-w-2xl mx-auto leading-relaxed font-sans">
              {t("hero_subtitle")}
            </p>
            {/* Audience tags */}
            <div className="flex flex-wrap gap-2 justify-center mb-10">
              <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm font-sans">
                <GraduationCap className="h-3.5 w-3.5" />
                {isPt ? "Estudantes de Veterinária" : "Veterinary Students"}
              </span>
              <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-cyan-500/10 border border-cyan-500/30 text-cyan-400 text-sm font-sans">
                <Stethoscope className="h-3.5 w-3.5" />
                {isPt ? "Médicos Veterinários Formados" : "Licensed Veterinarians"}
              </span>
              <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-purple-500/10 border border-purple-500/30 text-purple-400 text-sm font-sans">
                <Award className="h-3.5 w-3.5" />
                {isPt ? "Residência e Pós-Graduação" : "Residency & Postgraduate"}
              </span>
            </div>
            <div className="flex flex-col sm:flex-row gap-4 justify-center">
              {isAuthenticated ? (
                <Link href="/exam">
                  <Button size="lg" className="bg-primary text-primary-foreground hover:bg-primary/90 glow-primary px-8 gap-2 text-base">
                    {t("hero_cta_primary")}
                    <ArrowRight className="h-4 w-4" />
                  </Button>
                </Link>
              ) : (
                <Button
                  size="lg"
                  className="bg-primary text-primary-foreground hover:bg-primary/90 glow-primary px-8 gap-2 text-base"
                  onClick={() => (openLogin())}
                >
                  {t("hero_cta_primary")}
                  <ArrowRight className="h-4 w-4" />
                </Button>
              )}
              <Link href="/pricing">
                <Button size="lg" variant="outline" className="border-border/60 hover:border-primary/50 px-8 text-base">
                  {t("hero_cta_secondary")}
                </Button>
              </Link>
            </div>
            <p className="mt-5 text-sm text-muted-foreground flex items-center justify-center gap-2 font-sans">
              <Check className="h-4 w-4 text-primary" />
              {t("hero_trial")}
            </p>
          </div>


        </div>
      </section>

      {/* Constant evolution — feed automático gerado da atividade real (sem posts manuais) */}
      {(updatesFeed?.items?.length ?? 0) > 0 && <section className="py-20 bg-background border-y border-border/30">
        <div className="container max-w-5xl">
          <div className="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-10">
            <div>
              <Badge className="mb-3 bg-emerald-500/10 text-emerald-400 border-emerald-500/30 font-sans text-xs px-3 py-1">
                {isPt ? "Em constante evolução" : "Always evolving"}
              </Badge>
              <h2 className="font-serif text-3xl md:text-4xl font-bold">
                {isPt ? "Conteúdo novo toda semana" : "New content every week"}
              </h2>
              <p className="text-muted-foreground font-sans mt-2 max-w-xl">
                {isPt
                  ? "Questões, artigos e trilhas entram na plataforma continuamente. Veja o que chegou por último."
                  : "Questions, articles and trails are added continuously. Here's what arrived most recently."}
              </p>
            </div>
            <Link href="/novidades">
              <Button variant="outline" className="gap-2 border-border/60">
                {isPt ? "Ver todas as novidades" : "See all updates"} <ChevronRight className="h-4 w-4" />
              </Button>
            </Link>
          </div>
          <UpdatesTimeline limit={5} />
        </div>
      </section>}

      {/* For Professionals & Students */}
      <section className="py-24 bg-card/20">
        <div className="container">
          <div className="text-center mb-16">
            <Badge className="mb-4 bg-primary/10 text-primary border-primary/20 font-sans text-xs px-3 py-1">
              {isPt ? "Para quem é o VetRank?" : "Who is VetRank for?"}
            </Badge>
            <h2 className="font-serif text-4xl font-bold mb-4">
              {isPt ? "Desenvolvido para toda a carreira veterinária" : "Built for every stage of your veterinary career"}
            </h2>
            <p className="text-muted-foreground text-lg font-sans max-w-2xl mx-auto">
              {isPt
                ? "Seja você um estudante buscando aprovação em residências e concursos ou um profissional se atualizando, o VetRank tem o conteúdo certo para você."
                : "Whether you're a student preparing for residency or competitive exams, or a professional staying current, VetRank has the right content for you."}
            </p>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-5xl mx-auto">
            {/* Students */}
            <div className="relative p-8 rounded-3xl bg-gradient-to-br from-emerald-500/10 to-emerald-500/5 border border-emerald-500/30 hover:border-emerald-500/50 transition-all">
              <div className="absolute top-6 right-6 w-12 h-12 rounded-2xl bg-emerald-500/15 flex items-center justify-center">
                <GraduationCap className="h-6 w-6 text-emerald-400" />
              </div>
              <h3 className="font-serif text-2xl font-bold mb-2 text-emerald-400">{isPt ? "Estudantes" : "Students"}</h3>
              <p className="text-muted-foreground font-sans text-sm mb-6 leading-relaxed">
                {isPt
                  ? "Prepare-se para ENADE, ENARE, residências e provas de graduação com questões organizadas por disciplina, nível e tipo. Acompanhe seu progresso com gamificação e ranking."
                  : "Prepare for ENADE, ENARE, residency and graduation exams with questions organized by discipline, level, and type."}
              </p>
              <ul className="space-y-2.5 mb-8">
                {(isPt ? [
                  "Banco completo de questões de provas reais",
                  "Simulados no estilo ENADE, Residência e ENARE",
                  "Trilhas de estudo por grande área",
                  "Ranking e sistema de XP para motivação",
                  "Questões discursivas com gabarito comentado",
                ] : [
                  "Full bank of questions from real exams",
                  "ENADE, Residency and ENARE-style mock exams",
                  "Study trails by subject area",
                  "Ranking and XP system for motivation",
                  "Discursive questions with detailed answers",
                ]).map((item) => (
                  <li key={item} className="flex items-start gap-2 text-sm font-sans">
                    <Check className="h-4 w-4 text-emerald-400 shrink-0 mt-0.5" />
                    <span className="text-muted-foreground">{item}</span>
                  </li>
                ))}
              </ul>
              {isAuthenticated ? (
                <Link href="/exam"><Button className="w-full bg-emerald-500/20 text-emerald-400 border border-emerald-500/40 hover:bg-emerald-500/30 font-sans gap-2">{isPt ? "Começar a Estudar" : "Start Studying"}<ArrowRight className="h-4 w-4" /></Button></Link>
              ) : (
                <Button className="w-full bg-emerald-500/20 text-emerald-400 border border-emerald-500/40 hover:bg-emerald-500/30 font-sans gap-2" onClick={() => (openLogin())}>{isPt ? "Criar Conta Grátis" : "Create Free Account"}<ArrowRight className="h-4 w-4" /></Button>
              )}
            </div>
            {/* Professionals */}
            <div className="relative p-8 rounded-3xl bg-gradient-to-br from-cyan-500/10 to-cyan-500/5 border border-cyan-500/30 hover:border-cyan-500/50 transition-all">
              <div className="absolute top-6 right-6 w-12 h-12 rounded-2xl bg-cyan-500/15 flex items-center justify-center">
                <Stethoscope className="h-6 w-6 text-cyan-400" />
              </div>
              <h3 className="font-serif text-2xl font-bold mb-2 text-cyan-400">{isPt ? "Profissionais Formados" : "Licensed Professionals"}</h3>
              <p className="text-muted-foreground font-sans text-sm mb-6 leading-relaxed">
                {isPt
                  ? "Mantenha-se atualizado, prepare-se para concursos públicos e especializações. Acesse conteúdo avançado em clínica, cirurgia, saúde pública e muito mais."
                  : "Stay current, prepare for public service exams and specializations. Access advanced content in clinics, surgery, public health, and more."}
              </p>
              <ul className="space-y-2.5 mb-8">
                {(isPt ? [
                  "Questões de concursos públicos e residências",
                  "Conteúdo bilíngue (PT/EN) para publicações",
                  "Filtros avançados por área e especialidade",
                  "Modo prática sem pressão de tempo",
                  "Histórico detalhado de desempenho",
                ] : [
                  "Questions from public service exams and residencies",
                  "Bilingual content (PT/EN) for publications",
                  "Advanced filters by area and specialty",
                  "Practice mode without time pressure",
                  "Detailed performance history",
                ]).map((item) => (
                  <li key={item} className="flex items-start gap-2 text-sm font-sans">
                    <Check className="h-4 w-4 text-cyan-400 shrink-0 mt-0.5" />
                    <span className="text-muted-foreground">{item}</span>
                  </li>
                ))}
              </ul>
              {isAuthenticated ? (
                <Link href="/bank"><Button className="w-full bg-cyan-500/20 text-cyan-400 border border-cyan-500/40 hover:bg-cyan-500/30 font-sans gap-2">{isPt ? "Explorar Banco de Questões" : "Explore Question Bank"}<ArrowRight className="h-4 w-4" /></Button></Link>
              ) : (
                <Button className="w-full bg-cyan-500/20 text-cyan-400 border border-cyan-500/40 hover:bg-cyan-500/30 font-sans gap-2" onClick={() => (openLogin())}>{isPt ? "Acessar a Plataforma" : "Access the Platform"}<ArrowRight className="h-4 w-4" /></Button>
              )}
            </div>
          </div>
          {/* Residency strip */}
          <div className="mt-8 max-w-5xl mx-auto p-6 rounded-2xl bg-purple-500/5 border border-purple-500/20 flex flex-col md:flex-row items-center gap-4">
            <div className="w-12 h-12 rounded-xl bg-purple-500/15 flex items-center justify-center shrink-0">
              <Award className="h-6 w-6 text-purple-400" />
            </div>
            <div className="flex-1 text-center md:text-left">
              <h4 className="font-serif text-lg font-bold text-purple-400 mb-1">{isPt ? "Residência e Pós-Graduação" : "Residency & Postgraduate"}</h4>
              <p className="text-sm text-muted-foreground font-sans">
                {isPt
                  ? "Questões de alta complexidade para provas de residência, mestrado e doutorado em Medicina Veterinária. Conteúdo validado por especialistas."
                  : "High-complexity questions for residency, master's, and doctoral exams in Veterinary Medicine. Content validated by specialists."}
              </p>
            </div>
            <Link href="/bank"><Button size="sm" variant="outline" className="border-purple-500/40 text-purple-400 hover:bg-purple-500/10 font-sans shrink-0">{isPt ? "Ver Questões" : "View Questions"}</Button></Link>
          </div>
        </div>
      </section>

      {/* How it works */}
      <section className="py-24 bg-background">
        <div className="container">
          <div className="text-center mb-16">
            <h2 className="font-serif text-4xl font-bold mb-4">{isPt ? "Como funciona?" : "How does it work?"}</h2>
            <p className="text-muted-foreground text-lg font-sans">{isPt ? "Três passos para dominar a Medicina Veterinária" : "Three steps to master Veterinary Medicine"}</p>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-4xl mx-auto">
            {[
              { step: "01", icon: ClipboardList, color: "text-primary", bg: "bg-primary/10", title: isPt ? "Escolha seu foco" : "Choose your focus", desc: isPt ? "Selecione a grande área, disciplina, nível de dificuldade e tipo de questão que deseja praticar." : "Select the subject area, discipline, difficulty level, and question type you want to practice." },
              { step: "02", icon: Target, color: "text-cyan-400", bg: "bg-cyan-500/10", title: isPt ? "Pratique e simule" : "Practice and simulate", desc: isPt ? "Responda questões no modo prática ou crie um simulado cronometrado no estilo ENADE, Residência e ENARE." : "Answer questions in practice mode or create a timed exam in ENADE, Residency or ENARE style." },
              { step: "03", icon: TrendingUp, color: "text-emerald-400", bg: "bg-emerald-500/10", title: isPt ? "Evolua e suba de nível" : "Evolve and level up", desc: isPt ? "Acompanhe seu desempenho por área, ganhe XP, suba no ranking e identifique seus pontos fracos." : "Track your performance by area, earn XP, climb the ranking, and identify your weak points." },
            ].map((item, i) => (
              <div key={i} className="text-center">
                <div className={`w-16 h-16 rounded-2xl ${item.bg} flex items-center justify-center mx-auto mb-4`}>
                  <item.icon className={`h-7 w-7 ${item.color}`} />
                </div>
                <div className="text-xs font-mono text-muted-foreground/50 mb-2">{item.step}</div>
                <h3 className="font-serif text-lg font-semibold mb-2">{item.title}</h3>
                <p className="text-sm text-muted-foreground font-sans leading-relaxed">{item.desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Features */}
      <section className="py-24 bg-background">
        <div className="container">
          <div className="text-center mb-16">
            <h2 className="font-serif text-4xl font-bold mb-4">{t("features_title")}</h2>
            <p className="text-muted-foreground text-lg max-w-2xl mx-auto font-sans">{t("features_subtitle")}</p>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {features.map((feat) => (
              <div
                key={feat.title}
                className="group p-6 rounded-2xl bg-card border border-border/50 hover:border-primary/30 transition-all duration-300 hover:shadow-lg hover:shadow-primary/5"
              >
                <div className="flex items-center gap-3 mb-4">
                  <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-accent">
                    <feat.icon className={`h-5 w-5 ${feat.color}`} />
                  </div>
                  <h3 className="font-serif text-lg font-semibold">{feat.title}</h3>
                </div>
                <p className="text-muted-foreground text-sm leading-relaxed font-sans">{feat.desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Disciplines */}
      <section className="py-24 bg-card/30">
        <div className="container">
          <div className="text-center mb-16">
            <h2 className="font-serif text-4xl font-bold mb-4">{t("disciplines_title")}</h2>
            <p className="text-muted-foreground text-lg font-sans">{t("disciplines_subtitle")}</p>
          </div>
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            {displayDisciplines.map((d: any) => (
              <Link key={d.slug} href={`/bank?discipline=${d.id || d.slug}`}>
                <div className="group p-4 rounded-2xl bg-card border border-border/50 hover:border-primary/30 transition-all duration-300 text-center cursor-pointer hover:shadow-md hover:shadow-primary/5">
                  <div className="text-3xl mb-3">{DISCIPLINE_ICONS[d.slug] || "📚"}</div>
                  <div className="text-sm font-medium text-foreground group-hover:text-primary transition-colors font-sans">
                    {language === "pt" ? d.namePt : d.nameEn}
                  </div>
                </div>
              </Link>
            ))}
          </div>
        </div>
      </section>

      {/* Levels */}
      <section className="py-24 bg-background">
        <div className="container">
          <div className="text-center mb-16">
            <h2 className="font-serif text-4xl font-bold mb-4">{t("levels_title")}</h2>
            <p className="text-muted-foreground text-lg font-sans">{t("levels_subtitle")}</p>
          </div>
          <div className="grid grid-cols-3 md:grid-cols-9 gap-3 max-w-4xl mx-auto">
            {levels.map((level, i) => (
              <div
                key={i}
                className={`flex flex-col items-center gap-2 p-3 rounded-xl border transition-all ${
                  i === 8
                    ? "bg-yellow-500/10 border-yellow-500/40 shadow-lg shadow-yellow-500/10"
                    : "bg-card border-border/50"
                }`}
              >
                <span className="text-2xl">{LEVEL_ICONS[i]}</span>
                <span className={`text-xs font-medium text-center leading-tight font-sans ${i === 8 ? "text-yellow-400" : "text-muted-foreground"}`}>
                  {level}
                </span>
                <span className="text-xs text-muted-foreground/60 font-sans">Lv.{i + 1}</span>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Pricing */}
      <section id="pricing" className="py-24 bg-card/30">
        <div className="container">
          <div className="text-center mb-16">
            <h2 className="font-serif text-4xl font-bold mb-4">{t("pricing_title")}</h2>
            <p className="text-muted-foreground text-lg font-sans">{t("pricing_subtitle")}</p>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-5xl mx-auto">
            {plans.map((plan) => (
              <div
                key={plan.name}
                className={`relative p-8 rounded-2xl border transition-all duration-300 ${
                  plan.highlight
                    ? "bg-primary/10 border-primary/40 shadow-xl shadow-primary/10 scale-105"
                    : "bg-card border-border/50"
                }`}
              >
                {plan.badge && (
                  <div className="absolute -top-3 left-1/2 -translate-x-1/2">
                    <Badge className="bg-primary text-primary-foreground px-4 py-1 font-sans">{plan.badge}</Badge>
                  </div>
                )}
                <div className="mb-6">
                  <h3 className="font-serif text-xl font-bold mb-1">{plan.name}</h3>
                  <p className="text-sm text-muted-foreground font-sans">{plan.desc}</p>
                </div>
                <div className="mb-6">
                  <div className="flex items-baseline gap-1 flex-wrap">
                    <span className={`font-serif text-4xl font-bold ${plan.highlight ? "text-primary" : ""}`}>{plan.price}</span>
                    {plan.period && <span className="text-muted-foreground ml-1 font-sans text-sm">{plan.period}</span>}
                    {plan.discount && (
                      <Badge className="bg-emerald-500/15 text-emerald-400 border-emerald-500/30 font-sans text-xs ml-1">{plan.discount}</Badge>
                    )}
                  </div>
                  {plan.subtext && (
                    <p className="text-sm text-primary font-semibold font-sans mt-1">{plan.subtext}</p>
                  )}
                  {plan.noCard && (
                    <p className="text-xs text-muted-foreground mt-1 flex items-center gap-1 font-sans">
                      <Check className="h-3 w-3 text-primary" />
                      {t("plan_no_card")}
                    </p>
                  )}
                </div>
                <ul className="space-y-3 mb-8">
                  {plan.features.map((feat: string) => (
                    <li key={feat} className="flex items-center gap-2 text-sm font-sans">
                      <Check className="h-4 w-4 text-primary shrink-0" />
                      <span className="text-muted-foreground">{feat}</span>
                    </li>
                  ))}
                </ul>
                {isAuthenticated ? (
                  <Link href="/profile">
                    <Button
                      className={`w-full font-sans ${plan.highlight ? "bg-primary text-primary-foreground hover:bg-primary/90 glow-primary" : ""}`}
                      variant={plan.highlight ? "default" : "outline"}
                    >
                      {plan.cta}
                      <ChevronRight className="h-4 w-4 ml-1" />
                    </Button>
                  </Link>
                ) : (
                  <Button
                    className={`w-full font-sans ${plan.highlight ? "bg-primary text-primary-foreground hover:bg-primary/90 glow-primary" : ""}`}
                    variant={plan.highlight ? "default" : "outline"}
                    onClick={() => (openLogin())}
                  >
                    {plan.cta}
                    <ChevronRight className="h-4 w-4 ml-1" />
                  </Button>
                )}
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* CTA */}
      <section className="py-24 bg-background">
        <div className="container">
          <div className="max-w-2xl mx-auto text-center p-12 rounded-3xl bg-primary/10 border border-primary/30 relative overflow-hidden">
            <div className="absolute inset-0 pointer-events-none">
              <div className="absolute top-0 right-0 w-48 h-48 rounded-full bg-primary/10 blur-3xl" />
            </div>
            <Crown className="h-12 w-12 text-primary mx-auto mb-6" />
            <h2 className="font-serif text-3xl font-bold mb-4">
              {language === "pt" ? "Pronto para começar sua jornada?" : "Ready to start your journey?"}
            </h2>
            <p className="text-muted-foreground mb-8 font-sans">
              {language === "pt"
                ? "Junte-se a milhares de estudantes que já estão se preparando com o VetRank."
                : "Join thousands of students already preparing with VetRank."}
            </p>
            {isAuthenticated ? (
              <Link href="/exam">
                <Button size="lg" className="bg-primary text-primary-foreground hover:bg-primary/90 glow-primary px-10 gap-2">
                  {t("hero_cta_primary")}
                  <ArrowRight className="h-4 w-4" />
                </Button>
              </Link>
            ) : (
              <Button
                size="lg"
                className="bg-primary text-primary-foreground hover:bg-primary/90 glow-primary px-10 gap-2"
                onClick={() => (openLogin())}
              >
                {t("hero_cta_primary")}
                <ArrowRight className="h-4 w-4" />
              </Button>
            )}
          </div>
        </div>
      </section>

    </div>
  );
}
