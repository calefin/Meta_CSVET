import { useAuth } from "@/_core/hooks/useAuth";
import SEO from "@/components/SEO";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { useLanguage } from "@/contexts/LanguageContext";
import { useAuthModal } from "@/contexts/AuthModalContext";
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";
import {
  ArrowRight,
  Award,
  BookOpen,
  Check,
  ChevronRight,
  ClipboardList,
  GraduationCap,
  HeartPulse,
  Shield,
  Stethoscope,
  Target,
  Trophy,
  Zap,
} from "lucide-react";
import { Link } from "wouter";

interface LandingSeoProps {
  type: "enade" | "enare" | "residencia";
}

export default function LandingSEO({ type }: LandingSeoProps) {
  const { isAuthenticated } = useAuth();
  const { language } = useLanguage();
  const { openLogin } = useAuthModal();
  const isPt = language === "pt";

  // Data tailored to the landing type
  const config = {
    enade: {
      badge: isPt ? "Especial ENADE" : "ENADE Special",
      badgeColor: "bg-purple-500/10 border-purple-500/30 text-purple-400",
      glowColor: "bg-purple-500/5",
      title: isPt ? "Gabarite a prova do ENADE de Veterinária" : "Ace the Veterinary ENADE Exam",
      subtitle: isPt
        ? "Prepare-se com provas reais anteriores gabaritadas, simulados cronometrados e relatórios inteligentes baseados no edital oficial do ENADE."
        : "Prepare with parsed past exams, timed simulations, and intelligent reports based on the official ENADE syllabus.",
      ctaPrimary: isPt ? "Criar Simulado ENADE Grátis" : "Create Free ENADE Exam",
      audience: isPt ? "Estudantes cursando Medicina Veterinária" : "Veterinary undergraduate students",
      faqs: [
        {
          q: isPt ? "Como funciona o ENADE de Medicina Veterinária?" : "How does the Veterinary ENADE work?",
          a: isPt
            ? "O ENADE é uma prova obrigatória para a colação de grau de estudantes no último ano de Medicina Veterinária. Ela avalia os conhecimentos do ciclo básico, clínico e zootecnia."
            : "ENADE is a mandatory exam for graduating veterinary students in Brazil, evaluating core, clinical, and zootechnics knowledge.",
        },
        {
          q: isPt ? "Quais temas do ENADE estão disponíveis no banco?" : "Which ENADE topics are available?",
          a: isPt
            ? "Você encontrará questões de Anatomia, Fisiologia, Patologia, Farmacologia, Clínica Veterinária, Cirurgia, Inspeção de Alimentos, Zootecnia e Deontologia/Ética."
            : "You'll find questions on Anatomy, Physiology, Pathology, Pharmacology, Veterinary Clinics, Surgery, Food Inspection, Zootechnics, and Ethics.",
        },
        {
          q: isPt ? "Como o VetRank me ajuda no ENADE?" : "How does VetRank help with ENADE?",
          a: isPt
            ? "Permitimos que você crie simulados cronometrados idênticos à prova real, treine sua velocidade e identifique através de gráficos quais grandes áreas você precisa revisar."
            : "We let you create timed exams identical to the real test, track your speed, and identify which areas need revision via clear dashboard charts.",
        },
        {
          q: isPt ? "Posso estudar pelo celular como um aplicativo?" : "Can I study on my phone like an app?",
          a: isPt
            ? "Sim! O VetRank suporta a tecnologia PWA (Progressive Web App). Você pode adicioná-lo à tela inicial do seu celular diretamente do navegador sem precisar ir à App Store. Ele permite que você faça simulados, ganhe XP e estude em qualquer lugar!"
            : "Yes! VetRank supports PWA (Progressive Web App) technology. You can install it on your mobile home screen directly from your browser without using the App Store, enabling you to study and earn XP on the go!",
        },
      ],
    },
    enare: {
      badge: isPt ? "Especial ENARE" : "ENARE Special",
      badgeColor: "bg-cyan-500/10 border-cyan-500/30 text-cyan-400",
      glowColor: "bg-cyan-500/5",
      title: isPt ? "Domine o ENARE de Medicina Veterinária" : "Conquer the Veterinary ENARE",
      subtitle: isPt
        ? "Conquiste sua vaga na residência dos sonhos. Resolva questões comentadas, crie simulados com peso oficial e estude com estatísticas precisas."
        : "Secure your dream residency spot. Solve commented questions, create exams with official weightings, and study with precise statistics.",
      ctaPrimary: isPt ? "Criar Simulado ENARE Grátis" : "Create Free ENARE Exam",
      audience: isPt ? "Médicos Veterinários focados em residência" : "Veterinarians aiming for residency",
      faqs: [
        {
          q: isPt ? "O que é o ENARE de Medicina Veterinária?" : "What is the Veterinary ENARE?",
          a: isPt
            ? "O ENARE (Exame Nacional de Residência) é uma prova unificada nacional que oferece vagas de residência em diversos hospitais universitários de excelência pelo Brasil."
            : "ENARE is a national unified exam offering residency positions at major university hospitals across Brazil.",
        },
        {
          q: isPt ? "Como estudar de forma eficiente para o ENARE?" : "How to study efficiently for ENARE?",
          a: isPt
            ? "Focar na resolução de questões das provas anteriores é a metodologia cientificamente comprovada mais eficiente. O VetRank organiza o conteúdo por disciplina para maximizar sua retenção."
            : "Focusing on past questions is the most scientifically proven method. VetRank organizes content by discipline to maximize retention.",
        },
        {
          q: isPt ? "O plano gratuito cobre questões do ENARE?" : "Does the free plan cover ENARE?",
          a: isPt
            ? "Sim! No plano gratuito você tem um limite diário de resoluções de questões, incluindo a base do ENARE. Para resoluções ilimitadas e simulados com peso oficial, recomendamos a assinatura Premium."
            : "Yes! The free plan lets you solve a limited number of questions daily, including ENARE. For unlimited access and weighted mock exams, we recommend Premium.",
        },
        {
          q: isPt ? "Posso estudar pelo celular como um aplicativo?" : "Can I study on my phone like an app?",
          a: isPt
            ? "Sim! O VetRank suporta a tecnologia PWA (Progressive Web App). Você pode adicioná-lo à tela inicial do seu celular diretamente do navegador sem precisar ir à App Store. Ele permite que você faça simulados, ganhe XP e estude em qualquer lugar!"
            : "Yes! VetRank supports PWA (Progressive Web App) technology. You can install it on your mobile home screen directly from your browser without using the App Store, enabling you to study and earn XP on the go!",
        },
      ],
    },
    residencia: {
      badge: isPt ? "Preparatório Residência" : "Residency Prep",
      badgeColor: "bg-emerald-500/10 border-emerald-500/30 text-emerald-400",
      glowColor: "bg-emerald-500/5",
      title: isPt ? "Aprovado na Residência Veterinária" : "Pass Your Veterinary Residency Exam",
      subtitle: isPt
        ? "Banco completo de questões teóricas avançadas e discursivas comentadas com resposta modelo das bancas mais concorridas do Brasil (USP, UNESP, ENARE, etc.)."
        : "Complete bank of advanced multiple-choice and commented discursive questions with model answers from highly competitive Brazilian exams.",
      ctaPrimary: isPt ? "Começar Teste de 7 Dias Grátis" : "Start 7-Day Free Trial",
      audience: isPt ? "Profissionais graduados e pós-graduandos" : "Graduated professionals and postgraduates",
      faqs: [
        {
          q: isPt ? "O VetRank possui questões discursivas?" : "Does VetRank have discursive questions?",
          a: isPt
            ? "Sim! O VetRank conta com um banco exclusivo de questões discursivas reais de provas de residência com os critérios exatos de correção e as respostas padrão das bancas."
            : "Yes! VetRank features a premium bank of real discursive residency questions with exact grading criteria and official model answers.",
        },
        {
          q: isPt ? "Quais especialidades veterinárias estão cobertas?" : "Which veterinary specialties are covered?",
          a: isPt
            ? "Cobrimos Clínica Médica, Cirurgia, Anestesiologia, Patologia Clínica, Diagnóstico por Imagem, Animais Silvestres, Medicina Preventiva e Saúde Pública."
            : "We cover Internal Medicine, Surgery, Anesthesiology, Clinical Pathology, Imaging, Wild Animals, Preventive Medicine, and Public Health.",
        },
        {
          q: isPt ? "A assinatura possui fidelidade?" : "Is there a long-term commitment?",
          a: isPt
            ? "Não. Nossas assinaturas são mensais ou anuais e você pode cancelar a qualquer momento sem nenhuma taxa ou multa de rescisão diretamente no seu painel de perfil."
            : "No. Our plans are monthly or yearly and you can cancel anytime with zero fees directly from your profile dashboard.",
        },
        {
          q: isPt ? "Posso estudar pelo celular como um aplicativo?" : "Can I study on my phone like an app?",
          a: isPt
            ? "Sim! O VetRank suporta a tecnologia PWA (Progressive Web App). Você pode adicioná-lo à tela inicial do seu celular diretamente do navegador sem precisar ir à App Store. Ele permite que você faça simulados, ganhe XP e estude em qualquer lugar!"
            : "Yes! VetRank supports PWA (Progressive Web App) technology. You can install it on your mobile home screen directly from your browser without using the App Store, enabling you to study and earn XP on the go!",
        },
      ],
    },
  }[type];

  // Specific canonical path
  const canonicalPath = `/${type}`;

  return (
    <div className="min-h-screen bg-background">
      <SEO
        title={config.title}
        description={config.subtitle}
        canonical={canonicalPath}
      />

      {/* Hero Section */}
      <section className="relative overflow-hidden gradient-hero py-24 md:py-32 border-b border-border/40">
        <div className="absolute inset-0 pointer-events-none">
          <div className={`absolute top-1/4 left-1/4 w-96 h-96 rounded-full blur-3xl ${config.glowColor}`} />
          <div className={`absolute bottom-1/4 right-1/4 w-64 h-64 rounded-full blur-3xl ${config.glowColor}`} />
        </div>
        <div className="container relative">
          <div className="max-w-4xl mx-auto text-center">
            <Badge className={`mb-6 border px-4 py-1.5 text-sm font-sans hover:bg-transparent ${config.badgeColor}`}>
              <Zap className="h-3.5 w-3.5 mr-1.5" />
              {config.badge}
            </Badge>
            <h1 className="font-serif text-4xl md:text-6xl font-bold mb-6 leading-tight text-white">
              {config.title}
            </h1>
            <p className="text-lg md:text-xl text-muted-foreground mb-8 max-w-2xl mx-auto leading-relaxed font-sans">
              {config.subtitle}
            </p>

            <div className="flex flex-wrap gap-2 justify-center mb-10">
              <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm font-sans">
                <GraduationCap className="h-3.5 w-3.5" />
                {config.audience}
              </span>
              <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-cyan-500/10 border border-cyan-500/30 text-cyan-400 text-sm font-sans">
                <Stethoscope className="h-3.5 w-3.5" />
                {isPt ? "Questões Comentadas por Especialistas" : "Commented Questions"}
              </span>
              <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-purple-500/10 border border-purple-500/30 text-purple-400 text-sm font-sans">
                <Award className="h-3.5 w-3.5" />
                {isPt ? "Simulados Cronometrados com Nota" : "Timed Practice Mock Exams"}
              </span>
            </div>

            <div className="flex flex-col sm:flex-row gap-4 justify-center">
              {isAuthenticated ? (
                <Link href="/exam">
                  <Button size="lg" className="bg-primary text-primary-foreground hover:bg-primary/90 glow-primary px-8 gap-2 text-base font-sans">
                    {isPt ? "Criar meu Simulado Agora" : "Create My Exam Now"}
                    <ArrowRight className="h-4 w-4" />
                  </Button>
                </Link>
              ) : (
                <Button
                  size="lg"
                  className="bg-primary text-primary-foreground hover:bg-primary/90 glow-primary px-8 gap-2 text-base font-sans"
                  onClick={() => openLogin()}
                >
                  {config.ctaPrimary}
                  <ArrowRight className="h-4 w-4" />
                </Button>
              )}
              <Link href="/pricing">
                <Button size="lg" variant="outline" className="border-border/60 hover:border-primary/50 px-8 text-base font-sans">
                  {isPt ? "Ver Planos Premium" : "View Premium Plans"}
                </Button>
              </Link>
            </div>
            <p className="mt-5 text-sm text-muted-foreground flex items-center justify-center gap-2 font-sans">
              <Check className="h-4 w-4 text-primary" />
              {isPt ? "7 dias grátis • Cancele quando quiser • Sem cartão" : "7 days free • Cancel anytime • No credit card"}
            </p>
          </div>
        </div>
      </section>

      {/* Social Proof Above the Fold */}
      <section className="py-12 border-b border-border/40 bg-card/10">
        <div className="container">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-4xl mx-auto text-center">
            <div className="p-4">
              <div className="font-serif text-4xl md:text-5xl font-bold text-primary mb-1">50.000+</div>
              <p className="text-muted-foreground text-sm font-sans">{isPt ? "Questões Veterinárias Catalogadas" : "Veterinary Questions Cataloged"}</p>
            </div>
            <div className="p-4 border-y md:border-y-0 md:border-x border-border/40">
              <div className="font-serif text-4xl md:text-5xl font-bold text-primary mb-1">3.000+</div>
              <p className="text-muted-foreground text-sm font-sans">{isPt ? "Estudantes e Veterinários Ativos" : "Active Students & Vets"}</p>
            </div>
            <div className="p-4">
              <div className="font-serif text-4xl md:text-5xl font-bold text-primary mb-1">94.6%</div>
              <p className="text-muted-foreground text-sm font-sans">{isPt ? "Taxa de Recomendação dos Alunos" : "Student Recommendation Rate"}</p>
            </div>
          </div>
        </div>
      </section>

      {/* Features Section */}
      <section className="py-24 bg-background">
        <div className="container">
          <div className="text-center mb-16">
            <Badge className="mb-4 bg-primary/10 text-primary border-primary/20 font-sans text-xs px-3 py-1">
              {isPt ? "Estratégia de Aprovação" : "Strategy for Success"}
            </Badge>
            <h2 className="font-serif text-3xl md:text-4xl font-bold mb-4">
              {isPt ? "Como o VetRank acelera seus resultados" : "How VetRank accelerates your results"}
            </h2>
            <p className="text-muted-foreground text-lg font-sans max-w-2xl mx-auto">
              {isPt
                ? "Unimos a base de dados de maior qualidade do mercado com inteligência de dados e gamificação para impulsionar sua rotina."
                : "We combine the highest quality database on the market with data insights and gamification to fuel your routine."}
            </p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-5xl mx-auto">
            {/* Feat 1 */}
            <div className="p-8 rounded-3xl bg-card border border-border/50 hover:border-primary/30 transition-all">
              <div className="w-12 h-12 rounded-2xl bg-primary/10 flex items-center justify-center mb-6">
                <BookOpen className="h-6 w-6 text-primary" />
              </div>
              <h3 className="font-serif text-xl font-bold mb-3 text-white">{isPt ? "Questões Comentadas" : "Commented Questions"}</h3>
              <p className="text-muted-foreground font-sans text-sm leading-relaxed">
                {isPt
                  ? "Entenda o porquê de cada gabarito. Nossas explicações desmembram os conceitos teóricos do ciclo básico e clínico, facilitando a fixação sem decoreba."
                  : "Understand the reason behind each answer. Our explanations dissect theoretical concepts, making learning smooth and intuitive."}
              </p>
            </div>
            {/* Feat 2 */}
            <div className="p-8 rounded-3xl bg-card border border-border/50 hover:border-primary/30 transition-all">
              <div className="w-12 h-12 rounded-2xl bg-cyan-500/10 flex items-center justify-center mb-6">
                <ClipboardList className="h-6 w-6 text-cyan-400" />
              </div>
              <h3 className="font-serif text-xl font-bold mb-3 text-cyan-400">{isPt ? "Simulados Dinâmicos" : "Dynamic Mock Exams"}</h3>
              <p className="text-muted-foreground font-sans text-sm leading-relaxed">
                {isPt
                  ? "Treine no modelo exato das maiores bancas. Controle o tempo de execução, escolha o nível de dificuldade (fácil, médio, difícil) e filtre por assunto específico."
                  : "Train with the exact model of large boards. Control execution time, select difficulty, and filter by specific topics."}
              </p>
            </div>
            {/* Feat 3 */}
            <div className="p-8 rounded-3xl bg-card border border-border/50 hover:border-primary/30 transition-all">
              <div className="w-12 h-12 rounded-2xl bg-purple-500/10 flex items-center justify-center mb-6">
                <Target className="h-6 w-6 text-purple-400" />
              </div>
              <h3 className="font-serif text-xl font-bold mb-3 text-purple-400">{isPt ? "Relatórios Detalhados" : "Detailed Dashboards"}</h3>
              <p className="text-muted-foreground font-sans text-sm leading-relaxed">
                {isPt
                  ? "Monitore seus pontos fracos e fortes. Nosso painel mapeia suas taxas de acerto por disciplinas como Anatomia, Fisiologia e Patologia Geral."
                  : "Monitor your strengths and weaknesses. Our dashboard maps your accuracy rates across subjects like Anatomy, Physiology, and Pathology."}
              </p>
            </div>
          </div>
        </div>
      </section>

      {/* Gamification & Streaks Show */}
      <section className="py-24 bg-card/20 border-y border-border/40">
        <div className="container">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 max-w-5xl mx-auto items-center">
            <div>
              <Badge className="mb-4 bg-primary/10 text-primary border-primary/20 font-sans text-xs px-3 py-1">
                {isPt ? "Gamificação e Hábito" : "Gamification & Habit"}
              </Badge>
              <h2 className="font-serif text-3xl md:text-4xl font-bold mb-6 text-white leading-tight">
                {isPt ? "Transforme esforço em hábito diário" : "Turn studying into a rewarding daily habit"}
              </h2>
              <p className="text-muted-foreground font-sans mb-6 leading-relaxed">
                {isPt
                  ? "Estudar para provas complexas exige constância. No VetRank, você acumula XP, sobe de liga e conquista badges exclusivos conforme bate suas metas de estudo diárias."
                  : "Studying for complex exams requires consistency. On VetRank, you gather XP, level up leagues, and win exclusive badges as you hit your goals."}
              </p>
              <div className="space-y-4">
                <div className="flex items-center gap-3">
                  <div className="w-8 h-8 rounded-lg bg-emerald-500/15 flex items-center justify-center shrink-0">
                    <Trophy className="h-4 w-4 text-emerald-400" />
                  </div>
                  <span className="text-sm font-sans text-muted-foreground">
                    {isPt ? "Ranking nacional semanal com disputas acirradas" : "Weekly national ranking with healthy competition"}
                  </span>
                </div>
                <div className="flex items-center gap-3">
                  <div className="w-8 h-8 rounded-lg bg-amber-500/15 flex items-center justify-center shrink-0">
                    <Zap className="h-4 w-4 text-amber-400" />
                  </div>
                  <span className="text-sm font-sans text-muted-foreground">
                    {isPt ? "Manutenção de ofensivas (streaks) para criar disciplina" : "Streak protection to build consistent daily discipline"}
                  </span>
                </div>
                <div className="flex items-center gap-3">
                  <div className="w-8 h-8 rounded-lg bg-cyan-500/15 flex items-center justify-center shrink-0">
                    <Award className="h-4 w-4 text-cyan-400" />
                  </div>
                  <span className="text-sm font-sans text-muted-foreground">
                    {isPt ? "Conquista de insígnias que validam seu progresso" : "Unlockable badges validating your step-by-step progress"}
                  </span>
                </div>
              </div>
            </div>
            {/* Visual Simulated Mock Screen */}
            <div className="p-6 rounded-3xl bg-card border border-border/80 shadow-2xl relative overflow-hidden max-w-md mx-auto">
              <div className="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-primary via-cyan-500 to-purple-500" />
              <div className="flex items-center justify-between mb-6">
                <div className="flex items-center gap-2">
                  <span className="text-xl">🎓</span>
                  <div>
                    <div className="text-xs font-mono text-muted-foreground">Level 4</div>
                    <div className="text-sm font-bold font-sans text-white">Anestesiologista Jr.</div>
                  </div>
                </div>
                <div className="flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-500/15 border border-amber-500/30 text-amber-400 text-xs font-bold font-sans">
                  <Zap className="h-3.5 w-3.5" />
                  5 Dias de Ofensiva!
                </div>
              </div>
              <div className="space-y-4 font-sans text-sm">
                <div className="p-3.5 rounded-xl bg-background border border-border/60">
                  <div className="flex justify-between mb-1.5">
                    <span className="font-bold text-white">{isPt ? "Meta de Hoje" : "Today's Goal"}</span>
                    <span className="text-xs text-primary font-bold">12/15 {isPt ? "Questões" : "Questions"}</span>
                  </div>
                  <div className="w-full h-2 rounded-full bg-muted overflow-hidden">
                    <div className="h-full bg-primary rounded-full transition-all" style={{ width: "80%" }} />
                  </div>
                </div>
                <div className="p-3.5 rounded-xl bg-background border border-border/60">
                  <div className="text-xs text-muted-foreground mb-1">{isPt ? "Último Simulado Realizado" : "Last Mock Exam"}</div>
                  <div className="flex justify-between items-center">
                    <span className="font-bold text-white">Simulado {type.toUpperCase()}</span>
                    <span className="text-emerald-400 font-bold font-mono">86.6% {isPt ? "Acertos" : "Correct"}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* FAQ Section */}
      <section className="py-24 bg-background">
        <div className="container max-w-4xl mx-auto">
          <div className="text-center mb-16">
            <h2 className="font-serif text-3xl md:text-4xl font-bold mb-4 text-white">
              {isPt ? "Perguntas Frequentes" : "Frequently Asked Questions"}
            </h2>
            <p className="text-muted-foreground font-sans">
              {isPt ? "Tudo o que você precisa saber sobre o VetRank e a preparação." : "Everything you need to know about VetRank and prep."}
            </p>
          </div>

          <Accordion type="single" collapsible className="w-full">
            {config.faqs.map((faq, i) => (
              <AccordionItem key={i} value={`item-${i}`} className="border-border/50">
                <AccordionTrigger className="font-serif text-base text-left hover:text-primary transition-colors py-4">
                  {faq.q}
                </AccordionTrigger>
                <AccordionContent className="font-sans text-sm text-muted-foreground leading-relaxed pb-4">
                  {faq.a}
                </AccordionContent>
              </AccordionItem>
            ))}
          </Accordion>
        </div>
      </section>

      {/* Footer CTA */}
      <section className="py-24 bg-background">
        <div className="container">
          <div className="max-w-3xl mx-auto text-center p-12 rounded-3xl bg-primary/10 border border-primary/30 relative overflow-hidden">
            <div className="absolute inset-0 pointer-events-none">
              <div className="absolute top-0 right-0 w-48 h-48 rounded-full bg-primary/10 blur-3xl" />
            </div>
            <Award className="h-12 w-12 text-primary mx-auto mb-6" />
            <h2 className="font-serif text-3xl font-bold mb-4 text-white">
              {isPt ? "Dê o próximo passo na sua carreira hoje" : "Take the next step in your career today"}
            </h2>
            <p className="text-muted-foreground mb-8 font-sans max-w-md mx-auto text-sm leading-relaxed">
              {isPt
                ? "Junte-se a milhares de estudantes e profissionais. Comece a responder questões no modelo das maiores provas do país."
                : "Join thousands of students and professionals. Begin solving questions modeled after the country's major exams."}
            </p>
            {isAuthenticated ? (
              <Link href="/exam">
                <Button size="lg" className="bg-primary text-primary-foreground hover:bg-primary/90 glow-primary px-10 gap-2 font-sans">
                  {isPt ? "Criar meu Primeiro Simulado" : "Create My First Mock Exam"}
                  <ArrowRight className="h-4 w-4" />
                </Button>
              </Link>
            ) : (
              <Button
                size="lg"
                className="bg-primary text-primary-foreground hover:bg-primary/90 glow-primary px-10 gap-2 font-sans"
                onClick={() => openLogin()}
              >
                {config.ctaPrimary}
                <ArrowRight className="h-4 w-4" />
              </Button>
            )}
          </div>
        </div>
      </section>
    </div>
  );
}
