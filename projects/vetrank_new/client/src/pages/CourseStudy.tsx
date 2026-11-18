import { useEffect, useMemo, useState } from "react";
import { useRoute, useLocation } from "wouter";
import { trpc } from "@/lib/trpc";
import { useAuth } from "@/_core/hooks/useAuth";
import { toast } from "sonner";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import {
  Award,
  Check,
  CheckCircle2,
  ChevronDown,
  ChevronRight,
  FileText,
  HelpCircle,
  Loader2,
  Lock,
  Play,
  RotateCcw,
  Video,
  X,
} from "lucide-react";

// ─── Lesson type icons ─────────────────────────────────────────────────────────

const LESSON_ICONS: Record<string, typeof Video> = {
  video: Video,
  ebook: FileText,
  questions: HelpCircle,
};

// ─── Video embed helper ────────────────────────────────────────────────────────

function getEmbedUrl(videoUrl: string, provider: string): string {
  if (provider === "youtube") {
    const m = videoUrl.match(/(?:v=|youtu\.be\/)([A-Za-z0-9_-]{11})/);
    return m ? `https://www.youtube.com/embed/${m[1]}?rel=0` : videoUrl;
  }
  if (provider === "vimeo") {
    const m = videoUrl.match(/vimeo\.com\/(\d+)/);
    return m ? `https://player.vimeo.com/video/${m[1]}` : videoUrl;
  }
  return videoUrl;
}

// ─── VideoLesson ───────────────────────────────────────────────────────────────

function VideoLesson({
  lesson,
  courseId,
  onComplete,
}: {
  lesson: any;
  courseId: number;
  onComplete: () => void;
}) {
  const embedUrl = getEmbedUrl(lesson.videoUrl ?? "", lesson.provider ?? "other");
  const markProgress = trpc.courses.markVideoProgress.useMutation({
    onSuccess: (data) => {
      if (data.completed) { toast.success("Aula concluída!"); onComplete(); }
    },
    onError: (e) => toast.error(e.message),
  });

  const isCompleted = lesson.progress?.status === "completed";

  return (
    <div className="space-y-4">
      {/* Player */}
      <div className="aspect-video bg-black rounded-2xl overflow-hidden">
        {embedUrl ? (
          <iframe
            src={embedUrl}
            className="w-full h-full"
            allow="autoplay; fullscreen; picture-in-picture"
            allowFullScreen
            title={lesson.title}
          />
        ) : (
          <div className="w-full h-full flex items-center justify-center text-muted-foreground">
            <Video className="h-10 w-10 opacity-30" />
          </div>
        )}
      </div>

      {/* Action */}
      {isCompleted ? (
        <div className="flex items-center gap-2 text-green-500 text-sm font-medium">
          <CheckCircle2 className="h-4 w-4" /> Aula concluída
        </div>
      ) : (
        <Button
          onClick={() => markProgress.mutate({ lessonId: lesson.id, courseId, watchedPercent: 100 })}
          disabled={markProgress.isPending}
          className="gap-2"
        >
          {markProgress.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Check className="h-4 w-4" />}
          Marcar como concluída
        </Button>
      )}
    </div>
  );
}

// ─── EbookLesson ───────────────────────────────────────────────────────────────

function EbookLesson({ lesson, courseId, onComplete }: { lesson: any; courseId: number; onComplete: () => void }) {
  const markProgress = trpc.courses.markVideoProgress.useMutation({
    onSuccess: () => { toast.success("Aula concluída!"); onComplete(); },
    onError: (e) => toast.error(e.message),
  });
  const isCompleted = lesson.progress?.status === "completed";

  return (
    <div className="space-y-4">
      {lesson.ebooks?.length > 0 ? (
        <div className="space-y-3">
          {lesson.ebooks.map((ebook: any) => (
            <div key={ebook.id} className="flex items-center gap-3 p-4 rounded-xl border border-border/50 bg-card/40 hover:bg-card/60 transition-colors">
              <div className="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center shrink-0">
                <FileText className="h-5 w-5 text-primary" />
              </div>
              <div className="flex-1 min-w-0">
                <p className="font-medium text-sm truncate">{ebook.fileName}</p>
                {ebook.allowDownload ? (
                  <p className="text-xs text-muted-foreground">Download permitido</p>
                ) : (
                  <p className="text-xs text-muted-foreground">Somente leitura online</p>
                )}
              </div>
              {ebook.allowDownload ? (
                <a
                  href={`/api/cursos/download-ebook/${ebook.id}`}
                  className="px-3 py-1.5 text-xs font-medium rounded-lg bg-primary/10 text-primary hover:bg-primary/20 transition-colors"
                >
                  Download
                </a>
              ) : (
                <a
                  href={ebook.fileUrl}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="px-3 py-1.5 text-xs font-medium rounded-lg bg-primary/10 text-primary hover:bg-primary/20 transition-colors"
                >
                  Abrir
                </a>
              )}
            </div>
          ))}
        </div>
      ) : (
        <div className="py-12 text-center text-muted-foreground">
          <FileText className="h-10 w-10 mx-auto mb-2 opacity-20" />
          <p className="text-sm">Material ainda não disponível.</p>
        </div>
      )}

      {isCompleted ? (
        <div className="flex items-center gap-2 text-green-500 text-sm font-medium">
          <CheckCircle2 className="h-4 w-4" /> Aula concluída
        </div>
      ) : (
        <Button
          onClick={() => markProgress.mutate({ lessonId: lesson.id, courseId, watchedPercent: 100 })}
          disabled={markProgress.isPending}
          className="gap-2"
        >
          {markProgress.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Check className="h-4 w-4" />}
          Confirmar leitura
        </Button>
      )}
    </div>
  );
}

// ─── QuizLesson ────────────────────────────────────────────────────────────────

function QuizLesson({ lesson, courseId, onComplete }: { lesson: any; courseId: number; onComplete: () => void }) {
  const [answers, setAnswers] = useState<Record<string, string>>({});
  const [result, setResult] = useState<{ score: number; correct: number; total: number; passed: boolean; minPassingScore: number } | null>(null);
  const [started, setStarted] = useState(false);

  const { data: questionsData, isLoading: loadingQ, refetch: refetchQ } = trpc.courses.getLessonQuestions.useQuery(
    { lessonId: lesson.id, courseId },
    { enabled: started, staleTime: 0 },
  );

  const submit = trpc.courses.submitQuizAttempt.useMutation({
    onSuccess: (data) => {
      setResult(data);
      if (data.passed) { toast.success(`Aprovado! Nota: ${data.score}%`); onComplete(); }
      else toast.error(`Reprovado. Nota: ${data.score}%. Mínimo: ${data.minPassingScore}%.`);
    },
    onError: (e) => toast.error(e.message),
  });

  const isCompleted = lesson.progress?.status === "completed";
  const questions = questionsData ?? [];

  const handleRetry = () => {
    setResult(null);
    setAnswers({});
    refetchQ();
  };

  if (isCompleted && !started) {
    return (
      <div className="text-center py-12 space-y-3">
        <CheckCircle2 className="h-12 w-12 text-green-500 mx-auto" />
        <p className="font-medium">Questionário concluído!</p>
        <Button variant="outline" size="sm" onClick={() => { setStarted(true); setResult(null); setAnswers({}); }}>
          Refazer questionário
        </Button>
      </div>
    );
  }

  if (!started) {
    return (
      <div className="text-center py-12 space-y-4">
        <HelpCircle className="h-12 w-12 text-primary mx-auto opacity-70" />
        <div>
          <p className="font-semibold text-lg">Questionário</p>
          <p className="text-sm text-muted-foreground mt-1">
            {lesson.minQuestions ?? "?"} questões · Nota mínima: {lesson.minPassingScore ?? 70}%
          </p>
        </div>
        <Button onClick={() => setStarted(true)}>
          <Play className="h-4 w-4 mr-2" /> Iniciar questionário
        </Button>
      </div>
    );
  }

  if (loadingQ) {
    return (
      <div className="flex items-center justify-center h-48">
        <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
      </div>
    );
  }

  if (result) {
    return (
      <div className="space-y-6">
        <div className={`rounded-2xl p-6 text-center ${result.passed ? "bg-green-500/10 border border-green-500/20" : "bg-red-500/10 border border-red-500/20"}`}>
          {result.passed ? (
            <CheckCircle2 className="h-12 w-12 text-green-500 mx-auto mb-3" />
          ) : (
            <X className="h-12 w-12 text-red-500 mx-auto mb-3" />
          )}
          <p className="text-2xl font-bold mb-1">{result.score}%</p>
          <p className="text-sm text-muted-foreground">
            {result.correct} de {result.total} questões corretas · Mínimo: {result.minPassingScore}%
          </p>
          <p className={`mt-2 font-semibold ${result.passed ? "text-green-600 dark:text-green-400" : "text-red-600 dark:text-red-400"}`}>
            {result.passed ? "Aprovado!" : "Reprovado"}
          </p>
        </div>
        {!result.passed && (
          <Button variant="outline" onClick={handleRetry} className="gap-2">
            <RotateCcw className="h-4 w-4" /> Tentar novamente
          </Button>
        )}
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {questions.length === 0 && (
        <div className="text-center py-12 text-muted-foreground">
          <p className="text-sm">Nenhuma questão disponível para esta aula.</p>
        </div>
      )}

      {questions.map((q: any, i: number) => (
        <div key={q.id} className="space-y-3">
          <p className="font-medium text-sm">
            <span className="text-muted-foreground mr-2">{i + 1}.</span>
            {q.textPt}
          </p>
          {Array.isArray(q.options) && (
            <div className="space-y-2">
              {(q.options as Array<{ id: string; textPt: string }>).map(opt => (
                <button
                  key={opt.id}
                  onClick={() => setAnswers(a => ({ ...a, [q.id]: opt.id }))}
                  className={`w-full flex items-start gap-3 px-4 py-3 rounded-xl border text-left text-sm transition-colors ${
                    answers[q.id] === opt.id
                      ? "border-primary bg-primary/10 text-foreground"
                      : "border-border/50 hover:border-border hover:bg-muted/10"
                  }`}
                >
                  <span className={`w-5 h-5 rounded-full border-2 shrink-0 mt-0.5 flex items-center justify-center text-[10px] font-bold transition-colors ${
                    answers[q.id] === opt.id ? "border-primary bg-primary text-primary-foreground" : "border-muted-foreground/40"
                  }`}>
                    {opt.id}
                  </span>
                  <span>{opt.textPt}</span>
                </button>
              ))}
            </div>
          )}
        </div>
      ))}

      {questions.length > 0 && (
        <Button
          onClick={() => submit.mutate({ lessonId: lesson.id, courseId, answers })}
          disabled={submit.isPending || Object.keys(answers).length < questions.length}
          className="gap-2"
        >
          {submit.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Check className="h-4 w-4" />}
          Finalizar ({Object.keys(answers).length}/{questions.length} respondidas)
        </Button>
      )}
    </div>
  );
}

// ─── Main CourseStudy page ─────────────────────────────────────────────────────

export default function CourseStudy() {
  const [, params] = useRoute("/cursos/:slug/aula");
  const [, setLocation] = useLocation();
  const slug = params?.slug ?? "";

  const { isAuthenticated } = useAuth();

  // First resolve course id from slug
  const { data: courseInfo } = trpc.courses.getBySlug.useQuery(
    { slug },
    { enabled: !!slug },
  );
  const courseId = courseInfo?.id;

  const { data: studyData, refetch: refetchStudy } = trpc.courses.getStudyData.useQuery(
    { courseId: courseId! },
    { enabled: !!courseId },
  );

  const [selectedLessonId, setSelectedLessonId] = useState<number | null>(null);
  const [expandedModules, setExpandedModules] = useState<Set<number>>(new Set());

  // Auto-select first incomplete lesson on load
  useEffect(() => {
    if (!studyData || selectedLessonId !== null) return;
    const allLessons = studyData.modules?.flatMap((m: any) => m.lessons) ?? [];
    const first = allLessons.find((l: any) => l.progress?.status !== "completed") ?? allLessons[0];
    if (first) {
      setSelectedLessonId(first.id);
      const mod = studyData.modules?.find((m: any) => m.lessons?.some((l: any) => l.id === first.id));
      if (mod) setExpandedModules(prev => { const next = new Set(prev); next.add(mod.id); return next; });
    }
  }, [studyData, selectedLessonId]);

  const allLessons = useMemo(
    () => studyData?.modules?.flatMap((m: any) => m.lessons) ?? [],
    [studyData],
  );

  const selectedLesson = useMemo(
    () => allLessons.find((l: any) => l.id === selectedLessonId) ?? null,
    [allLessons, selectedLessonId],
  );

  const selectedModule = useMemo(
    () => studyData?.modules?.find((m: any) => m.lessons?.some((l: any) => l.id === selectedLessonId)) ?? null,
    [studyData, selectedLessonId],
  );

  const handleLessonSelect = (lessonId: number, moduleId: number) => {
    setSelectedLessonId(lessonId);
    setExpandedModules(prev => { const next = new Set(prev); next.add(moduleId); return next; });
  };

  const toggleModule = (id: number) =>
    setExpandedModules(prev => {
      const next = new Set(prev);
      next.has(id) ? next.delete(id) : next.add(id);
      return next;
    });

  const goNext = () => {
    const idx = allLessons.findIndex((l: any) => l.id === selectedLessonId);
    if (idx < allLessons.length - 1) {
      const next = allLessons[idx + 1];
      const mod = studyData?.modules?.find((m: any) => m.lessons?.some((l: any) => l.id === next.id));
      handleLessonSelect(next.id, mod?.id ?? 0);
    }
  };

  // Redirect if not authenticated
  if (!isAuthenticated) {
    setLocation(`/cursos/${slug}`);
    return null;
  }

  if (!studyData) {
    return (
      <div className="flex items-center justify-center h-[60vh]">
        <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
      </div>
    );
  }

  const { course, modules, completionPercent } = studyData;

  return (
    <div className="h-[calc(100vh-4rem)] flex flex-col bg-background">
      {/* Top bar */}
      <div className="border-b border-border/40 bg-card/30 px-4 py-2.5 flex items-center gap-4 shrink-0">
        <button
          onClick={() => setLocation(`/cursos/${slug}`)}
          className="text-sm text-muted-foreground hover:text-foreground transition-colors"
        >
          ← {course.title}
        </button>
        <div className="flex-1 max-w-xs">
          <div className="flex items-center gap-2 text-xs text-muted-foreground">
            <Progress value={completionPercent} className="flex-1 h-1.5" />
            <span className="shrink-0">{completionPercent}%</span>
          </div>
        </div>
        {completionPercent === 100 && (
          <Button size="sm" variant="outline" className="gap-1.5 text-xs" onClick={() => setLocation(`/cursos/${slug}`)}>
            <Award className="h-3.5 w-3.5 text-amber-500" /> Emitir certificado
          </Button>
        )}
      </div>

      <div className="flex flex-1 overflow-hidden">
        {/* Sidebar */}
        <aside className="w-72 shrink-0 border-r border-border/40 bg-card/20 overflow-y-auto flex flex-col">
          <div className="px-3 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider border-b border-border/30">
            Conteúdo do curso
          </div>
          <nav className="flex-1 py-2">
            {modules?.map((mod: any) => {
              const completed = mod.lessons?.filter((l: any) => l.progress?.status === "completed").length ?? 0;
              const total = mod.lessons?.length ?? 0;
              const isExpanded = expandedModules.has(mod.id);
              return (
                <div key={mod.id}>
                  <button
                    onClick={() => toggleModule(mod.id)}
                    className="w-full flex items-center gap-2 px-3 py-2.5 text-left hover:bg-muted/10 transition-colors"
                  >
                    {isExpanded ? <ChevronDown className="h-3.5 w-3.5 text-muted-foreground shrink-0" /> : <ChevronRight className="h-3.5 w-3.5 text-muted-foreground shrink-0" />}
                    <div className="flex-1 min-w-0">
                      <p className="text-xs font-semibold line-clamp-1">{mod.title}</p>
                      <p className="text-[10px] text-muted-foreground">{completed}/{total} concluídas</p>
                    </div>
                  </button>
                  {isExpanded && (
                    <div className="pl-2 pb-1">
                      {mod.lessons?.map((lesson: any) => {
                        const Icon = LESSON_ICONS[lesson.type] ?? Video;
                        const isDone = lesson.progress?.status === "completed";
                        const isActive = lesson.id === selectedLessonId;
                        return (
                          <button
                            key={lesson.id}
                            onClick={() => handleLessonSelect(lesson.id, mod.id)}
                            className={`w-full flex items-start gap-2.5 px-3 py-2.5 rounded-lg text-left transition-colors group ${
                              isActive ? "bg-primary/15 text-primary" : "hover:bg-muted/10"
                            }`}
                          >
                            <div className={`w-5 h-5 rounded-full shrink-0 flex items-center justify-center mt-0.5 ${
                              isDone ? "bg-green-500 text-white" : isActive ? "bg-primary text-primary-foreground" : "bg-muted/40"
                            }`}>
                              {isDone ? <Check className="h-2.5 w-2.5" /> : <Icon className="h-2.5 w-2.5" />}
                            </div>
                            <div className="flex-1 min-w-0">
                              <p className={`text-xs font-medium line-clamp-2 leading-snug ${isActive ? "text-primary" : isDone ? "text-muted-foreground" : ""}`}>
                                {lesson.title}
                              </p>
                              {!lesson.isRequired && (
                                <span className="text-[10px] text-muted-foreground/60">Opcional</span>
                              )}
                            </div>
                          </button>
                        );
                      })}
                    </div>
                  )}
                </div>
              );
            })}
          </nav>
        </aside>

        {/* Main content */}
        <main className="flex-1 overflow-y-auto">
          <div className="max-w-3xl mx-auto px-6 py-6">
            {!selectedLesson ? (
              <div className="flex items-center justify-center h-48 text-muted-foreground">
                <p className="text-sm">Selecione uma aula na barra lateral.</p>
              </div>
            ) : (
              <div className="space-y-6">
                {/* Lesson header */}
                <div>
                  {selectedModule && (
                    <p className="text-xs text-muted-foreground mb-1 font-medium uppercase tracking-wide">{selectedModule.title}</p>
                  )}
                  <h1 className="font-serif text-xl font-bold">{selectedLesson.title}</h1>
                  <div className="flex items-center gap-2 mt-2">
                    <Badge variant="outline" className="text-xs gap-1">
                      {(() => {
                        const Icon = LESSON_ICONS[selectedLesson.type] ?? Video;
                        return <Icon className="h-3 w-3" />;
                      })()}
                      {selectedLesson.type === "video" ? "Vídeo" : selectedLesson.type === "ebook" ? "Ebook" : "Questionário"}
                    </Badge>
                    {selectedLesson.progress?.status === "completed" && (
                      <Badge className="text-xs gap-1 bg-green-500/15 text-green-600 dark:text-green-400 border-0">
                        <CheckCircle2 className="h-3 w-3" /> Concluída
                      </Badge>
                    )}
                  </div>
                </div>

                {/* Lesson content */}
                {selectedLesson.type === "video" && (
                  <VideoLesson lesson={selectedLesson} courseId={courseId!} onComplete={() => { refetchStudy(); goNext(); }} />
                )}
                {selectedLesson.type === "ebook" && (
                  <EbookLesson lesson={selectedLesson} courseId={courseId!} onComplete={() => { refetchStudy(); goNext(); }} />
                )}
                {selectedLesson.type === "questions" && (
                  <QuizLesson lesson={selectedLesson} courseId={courseId!} onComplete={() => { refetchStudy(); goNext(); }} />
                )}

                {/* Navigation */}
                <div className="flex justify-between pt-4 border-t border-border/30">
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => {
                      const idx = allLessons.findIndex((l: any) => l.id === selectedLessonId);
                      if (idx > 0) {
                        const prev = allLessons[idx - 1];
                        const mod = studyData?.modules?.find((m: any) => m.lessons?.some((l: any) => l.id === prev.id));
                        handleLessonSelect(prev.id, mod?.id ?? 0);
                      }
                    }}
                    disabled={allLessons.findIndex((l: any) => l.id === selectedLessonId) === 0}
                  >
                    ← Anterior
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={goNext}
                    disabled={allLessons.findIndex((l: any) => l.id === selectedLessonId) === allLessons.length - 1}
                  >
                    Próxima →
                  </Button>
                </div>
              </div>
            )}
          </div>
        </main>
      </div>
    </div>
  );
}
