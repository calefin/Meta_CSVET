import { useEffect } from "react";
import { useRoute, useLocation } from "wouter";
import { trpc } from "@/lib/trpc";
import { useAuth } from "@/_core/hooks/useAuth";
import { useAuthModal } from "@/contexts/AuthModalContext";
import { toast } from "sonner";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/components/ui/accordion";
import {
  BookOpen,
  CheckCircle2,
  Clock,
  FileText,
  GraduationCap,
  HelpCircle,
  Loader2,
  Lock,
  Play,
  ShoppingCart,
  User,
  Video,
  Award,
  ChevronLeft,
} from "lucide-react";

const LEVEL_LABELS: Record<string, string> = {
  beginner: "Iniciante",
  intermediate: "Intermediário",
  advanced: "Avançado",
};

const LESSON_ICONS: Record<string, typeof Video> = {
  video: Video,
  ebook: FileText,
  questions: HelpCircle,
};

const LESSON_LABELS: Record<string, string> = {
  video: "Vídeo",
  ebook: "Ebook",
  questions: "Questionário",
};

function DetailSkeleton() {
  return (
    <div className="max-w-7xl mx-auto px-4 py-8 grid grid-cols-1 lg:grid-cols-3 gap-8">
      <div className="lg:col-span-2 space-y-6">
        <Skeleton className="h-8 w-3/4" />
        <Skeleton className="h-4 w-1/2" />
        <Skeleton className="h-52 w-full rounded-2xl" />
        <Skeleton className="h-4 w-full" />
        <Skeleton className="h-4 w-4/5" />
      </div>
      <div>
        <Skeleton className="h-80 w-full rounded-2xl" />
      </div>
    </div>
  );
}

export default function CourseDetail() {
  const [, params] = useRoute("/cursos/:slug");
  const [, setLocation] = useLocation();
  const slug = params?.slug ?? "";

  const { isAuthenticated } = useAuth();
  const { openLogin: openAuthModal } = useAuthModal();

  const { data: course, isLoading } = trpc.courses.getBySlug.useQuery(
    { slug },
    { enabled: !!slug },
  );

  const { data: access, refetch: refetchAccess } = trpc.courses.checkAccess.useQuery(
    { courseId: course?.id ?? 0 },
    { enabled: isAuthenticated && !!course?.id },
  );

  const createPreference = trpc.courses.createPreference.useMutation({
    onSuccess: (data) => {
      if (data.checkoutUrl) window.location.href = data.checkoutUrl;
    },
    onError: (e) => toast.error(e.message),
  });

  // Handle return from MP (payment success/failure query param)
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const payment = params.get("payment");
    if (payment === "success") {
      toast.success("Pagamento aprovado! Seu acesso ao curso foi liberado.");
      refetchAccess();
    } else if (payment === "failure") {
      toast.error("Pagamento não aprovado. Tente novamente.");
    } else if (payment === "pending") {
      toast.info("Pagamento pendente. Você receberá uma confirmação em breve.");
    }
  }, []);

  const handleBuy = () => {
    if (!isAuthenticated) { openAuthModal(); return; }
    if (!course) return;
    const origin = window.location.origin;
    createPreference.mutate({
      courseId: course.id,
      successUrl: `${origin}/cursos/${slug}?payment=success`,
      failureUrl: `${origin}/cursos/${slug}?payment=failure`,
      pendingUrl: `${origin}/cursos/${slug}?payment=pending`,
    });
  };

  const totalLessons = course?.modules?.reduce(
    (acc: number, m: any) => acc + (m.lessons?.length ?? 0), 0,
  ) ?? 0;

  const requiredLessons = course?.modules?.reduce(
    (acc: number, m: any) => acc + (m.lessons?.filter((l: any) => l.isRequired)?.length ?? 0), 0,
  ) ?? 0;

  if (isLoading) return <DetailSkeleton />;
  if (!course) return (
    <div className="max-w-7xl mx-auto px-4 py-20 text-center">
      <GraduationCap className="h-12 w-12 mx-auto mb-3 text-muted-foreground/20" />
      <p className="text-muted-foreground">Curso não encontrado.</p>
      <Button variant="ghost" className="mt-4" onClick={() => setLocation("/cursos")}>
        <ChevronLeft className="h-4 w-4 mr-1" /> Ver todos os cursos
      </Button>
    </div>
  );

  return (
    <div className="min-h-screen bg-background">
      {/* Breadcrumb */}
      <div className="border-b border-border/40 bg-card/20">
        <div className="max-w-7xl mx-auto px-4 py-3 flex items-center gap-2 text-sm text-muted-foreground">
          <button onClick={() => setLocation("/cursos")} className="hover:text-foreground transition-colors">Cursos</button>
          <span>/</span>
          <span className="text-foreground line-clamp-1">{course.title}</span>
        </div>
      </div>

      <div className="max-w-7xl mx-auto px-4 py-8 grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Left column */}
        <div className="lg:col-span-2 space-y-8">
          {/* Header */}
          <div>
            <div className="flex flex-wrap items-center gap-2 mb-3">
              {course.category && <Badge variant="outline" className="text-xs">{course.category}</Badge>}
              <Badge className={`text-xs border-0 ${
                course.level === "beginner" ? "bg-green-500/15 text-green-700 dark:text-green-400" :
                course.level === "intermediate" ? "bg-blue-500/15 text-blue-700 dark:text-blue-400" :
                "bg-purple-500/15 text-purple-700 dark:text-purple-400"
              }`}>
                {LEVEL_LABELS[course.level] ?? course.level}
              </Badge>
            </div>
            <h1 className="font-serif text-2xl md:text-3xl font-bold mb-3">{course.title}</h1>
            {course.description && (
              <p className="text-muted-foreground leading-relaxed">{course.description}</p>
            )}
            <div className="flex flex-wrap items-center gap-4 mt-4 text-sm text-muted-foreground">
              {course.durationHours > 0 && (
                <span className="flex items-center gap-1.5"><Clock className="h-4 w-4" />{course.durationHours}h de conteúdo</span>
              )}
              <span className="flex items-center gap-1.5"><BookOpen className="h-4 w-4" />{totalLessons} aulas</span>
              <span className="flex items-center gap-1.5"><Award className="h-4 w-4" />Certificado de conclusão</span>
            </div>
          </div>

          {/* Thumbnail / Trailer */}
          {(course.thumbnailUrl || course.trailerUrl) && (
            <div className="rounded-2xl overflow-hidden border border-border/40 aspect-video bg-muted/20 relative">
              {course.thumbnailUrl ? (
                <img src={course.thumbnailUrl} alt={course.title} className="w-full h-full object-cover" />
              ) : null}
              {course.trailerUrl && (
                <div className="absolute inset-0 flex items-center justify-center bg-black/40">
                  <a href={course.trailerUrl} target="_blank" rel="noopener noreferrer"
                    className="w-14 h-14 rounded-full bg-white/90 flex items-center justify-center hover:bg-white transition-colors">
                    <Play className="h-6 w-6 text-black fill-black" />
                  </a>
                </div>
              )}
            </div>
          )}

          {/* Objectives */}
          {course.objectives && (
            <div className="rounded-2xl border border-border/40 bg-card/40 p-5">
              <h2 className="font-semibold text-base mb-3 flex items-center gap-2">
                <CheckCircle2 className="h-4 w-4 text-green-500" /> O que você vai aprender
              </h2>
              <p className="text-sm text-muted-foreground leading-relaxed whitespace-pre-line">{course.objectives}</p>
            </div>
          )}

          {/* Prerequisites */}
          {course.prerequisites && (
            <div className="rounded-2xl border border-border/40 bg-card/40 p-5">
              <h2 className="font-semibold text-base mb-2">Pré-requisitos</h2>
              <p className="text-sm text-muted-foreground leading-relaxed">{course.prerequisites}</p>
            </div>
          )}

          {/* Course structure */}
          <div>
            <h2 className="font-semibold text-lg mb-4">Conteúdo do curso</h2>
            <p className="text-sm text-muted-foreground mb-4">
              {course.modules?.length ?? 0} módulos · {totalLessons} aulas · {requiredLessons} obrigatórias
            </p>
            {course.modules && course.modules.length > 0 ? (
              <Accordion type="multiple" className="space-y-2">
                {course.modules.map((mod: any, mi: number) => (
                  <AccordionItem key={mod.id} value={String(mod.id)} className="rounded-xl border border-border/40 overflow-hidden">
                    <AccordionTrigger className="px-4 py-3 hover:bg-muted/10 hover:no-underline">
                      <div className="flex items-center gap-3 text-left">
                        <span className="text-sm font-medium text-muted-foreground w-5">{mi + 1}.</span>
                        <span className="font-medium text-sm">{mod.title}</span>
                        <span className="text-xs text-muted-foreground ml-auto pr-2">{mod.lessons?.length ?? 0} aulas</span>
                      </div>
                    </AccordionTrigger>
                    <AccordionContent className="px-4 pb-3 pt-0">
                      <div className="space-y-1 pt-1">
                        {mod.lessons?.map((lesson: any, li: number) => {
                          const Icon = LESSON_ICONS[lesson.type] ?? Video;
                          const isLocked = !access?.hasAccess;
                          return (
                            <div key={lesson.id} className="flex items-center gap-3 py-2 px-2 rounded-lg text-sm">
                              <Icon className="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                              <span className="flex-1 text-muted-foreground">{li + 1}. {lesson.title}</span>
                              <span className="text-[10px] text-muted-foreground">{LESSON_LABELS[lesson.type]}</span>
                              {!lesson.isRequired && <span className="text-[10px] text-muted-foreground/60">Opcional</span>}
                              {isLocked && <Lock className="h-3 w-3 text-muted-foreground/40 shrink-0" />}
                            </div>
                          );
                        })}
                      </div>
                    </AccordionContent>
                  </AccordionItem>
                ))}
              </Accordion>
            ) : (
              <p className="text-sm text-muted-foreground">Estrutura do curso em breve.</p>
            )}
          </div>

          {/* Instructor */}
          {course.instructorName && (
            <div className="rounded-2xl border border-border/40 bg-card/40 p-5">
              <h2 className="font-semibold text-base mb-4">Sobre o instrutor</h2>
              <div className="flex items-start gap-4">
                {course.instructorAvatarUrl ? (
                  <img src={course.instructorAvatarUrl} alt={course.instructorName}
                    className="w-14 h-14 rounded-full object-cover border border-border/40" />
                ) : (
                  <div className="w-14 h-14 rounded-full bg-muted flex items-center justify-center shrink-0">
                    <User className="h-6 w-6 text-muted-foreground" />
                  </div>
                )}
                <div>
                  <p className="font-medium">{course.instructorName}</p>
                  {course.instructorBio && (
                    <p className="text-sm text-muted-foreground mt-1 leading-relaxed">{course.instructorBio}</p>
                  )}
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Right column — sticky purchase card */}
        <div className="lg:col-span-1">
          <div className="sticky top-20">
            <div className="rounded-2xl border border-border/50 bg-card p-6 shadow-xl shadow-black/5 space-y-5">
              {/* Price */}
              <div>
                <p className="text-3xl font-bold">
                  R$ {Number(course.price).toFixed(2).replace(".", ",")}
                </p>
                <p className="text-xs text-muted-foreground mt-0.5">Acesso vitalício</p>
              </div>

              {/* CTA */}
              {access?.hasAccess ? (
                <Button
                  className="w-full"
                  size="lg"
                  onClick={() => setLocation(`/cursos/${slug}/aula`)}
                >
                  <Play className="h-4 w-4 mr-2" /> Acessar curso
                </Button>
              ) : (
                <Button
                  className="w-full"
                  size="lg"
                  onClick={handleBuy}
                  disabled={createPreference.isPending}
                >
                  {createPreference.isPending ? (
                    <Loader2 className="h-4 w-4 animate-spin mr-2" />
                  ) : (
                    <ShoppingCart className="h-4 w-4 mr-2" />
                  )}
                  Comprar curso
                </Button>
              )}

              {/* Highlights */}
              <ul className="space-y-2 text-sm text-muted-foreground">
                {course.durationHours > 0 && (
                  <li className="flex items-center gap-2">
                    <Clock className="h-4 w-4 shrink-0" />
                    {course.durationHours}h de conteúdo
                  </li>
                )}
                <li className="flex items-center gap-2">
                  <BookOpen className="h-4 w-4 shrink-0" />
                  {totalLessons} aulas
                </li>
                <li className="flex items-center gap-2">
                  <Award className="h-4 w-4 shrink-0" />
                  Certificado de conclusão
                </li>
                <li className="flex items-center gap-2">
                  <CheckCircle2 className="h-4 w-4 shrink-0 text-green-500" />
                  Acesso vitalício
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
