import { useState } from "react";
import { Link } from "wouter";
import { trpc } from "@/lib/trpc";
import { useAuth } from "@/_core/hooks/useAuth";
import { useAuthModal } from "@/contexts/AuthModalContext";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Clock,
  GraduationCap,
  Search,
  Star,
  Users,
  BookOpen,
  ChevronRight,
} from "lucide-react";

type Level = "beginner" | "intermediate" | "advanced";

const LEVEL_LABELS: Record<Level, string> = {
  beginner: "Iniciante",
  intermediate: "Intermediário",
  advanced: "Avançado",
};

const LEVEL_COLORS: Record<Level, string> = {
  beginner: "bg-green-500/15 text-green-600 dark:text-green-400",
  intermediate: "bg-blue-500/15 text-blue-600 dark:text-blue-400",
  advanced: "bg-purple-500/15 text-purple-600 dark:text-purple-400",
};

function CourseCardSkeleton() {
  return (
    <div className="rounded-2xl border border-border/50 overflow-hidden bg-card">
      <Skeleton className="h-44 w-full rounded-none" />
      <div className="p-4 space-y-2">
        <Skeleton className="h-4 w-3/4" />
        <Skeleton className="h-3 w-1/2" />
        <Skeleton className="h-3 w-full" />
        <div className="flex justify-between pt-2">
          <Skeleton className="h-5 w-20" />
          <Skeleton className="h-8 w-24" />
        </div>
      </div>
    </div>
  );
}

function CourseCard({ course }: { course: any }) {
  const { isAuthenticated } = useAuth();
  const { openLogin: openAuthModal } = useAuthModal();

  const { data: access } = trpc.courses.checkAccess.useQuery(
    { courseId: course.id },
    { enabled: isAuthenticated },
  );

  return (
    <Link href={`/cursos/${course.slug}`} className="block group">
      <div className="rounded-2xl border border-border/50 overflow-hidden bg-card hover:border-primary/30 hover:shadow-lg hover:shadow-primary/5 transition-all duration-300 h-full flex flex-col">
        {/* Thumbnail */}
        <div className="relative h-44 bg-muted/30 overflow-hidden shrink-0">
          {course.thumbnailUrl ? (
            <img
              src={course.thumbnailUrl}
              alt={course.title}
              className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
            />
          ) : (
            <div className="w-full h-full flex items-center justify-center">
              <GraduationCap className="h-12 w-12 text-muted-foreground/20" />
            </div>
          )}
          {access?.hasAccess && (
            <div className="absolute top-2 right-2 bg-green-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full">
              Adquirido
            </div>
          )}
          <div className="absolute bottom-2 left-2">
            <span className={`text-[10px] font-semibold px-2 py-0.5 rounded-full ${LEVEL_COLORS[course.level as Level]}`}>
              {LEVEL_LABELS[course.level as Level]}
            </span>
          </div>
        </div>

        {/* Content */}
        <div className="p-4 flex flex-col flex-1">
          {course.category && (
            <p className="text-[11px] font-medium text-primary/70 uppercase tracking-wide mb-1">{course.category}</p>
          )}
          <h3 className="font-semibold text-sm leading-snug line-clamp-2 mb-1 group-hover:text-primary transition-colors">
            {course.title}
          </h3>
          {course.instructorName && (
            <p className="text-xs text-muted-foreground mb-3">por {course.instructorName}</p>
          )}

          <div className="flex items-center gap-3 text-xs text-muted-foreground mt-auto mb-3">
            {course.durationHours > 0 && (
              <span className="flex items-center gap-1">
                <Clock className="h-3 w-3" /> {course.durationHours}h
              </span>
            )}
          </div>

          <div className="flex items-center justify-between">
            <span className="font-bold text-base">
              R$ {Number(course.price).toFixed(2).replace(".", ",")}
            </span>
            <span className="flex items-center gap-1 text-xs font-medium text-primary group-hover:gap-2 transition-all">
              {access?.hasAccess ? "Acessar" : "Ver curso"}
              <ChevronRight className="h-3.5 w-3.5" />
            </span>
          </div>
        </div>
      </div>
    </Link>
  );
}

export default function Courses() {
  const [search, setSearch] = useState("");
  const [level, setLevel] = useState<string>("all");
  const [category, setCategory] = useState<string>("all");
  const [debouncedSearch, setDebouncedSearch] = useState("");

  const { data, isLoading } = trpc.courses.list.useQuery({
    search: debouncedSearch || undefined,
    level: level !== "all" ? (level as Level) : undefined,
    category: category !== "all" ? category : undefined,
    limit: 50,
    offset: 0,
  });

  // Debounce search
  const handleSearch = (v: string) => {
    setSearch(v);
    clearTimeout((window as any).__courseSearchTimer);
    (window as any).__courseSearchTimer = setTimeout(() => setDebouncedSearch(v), 400);
  };

  // Derive available categories from current results
  const categories = data
    ? Array.from(new Set(data.map((c: any) => c.category).filter(Boolean)))
    : [];

  return (
    <div className="min-h-screen bg-background">
      {/* Hero */}
      <div className="border-b border-border/40 bg-card/30">
        <div className="max-w-7xl mx-auto px-4 py-12 md:py-16">
          <div className="max-w-2xl">
            <div className="flex items-center gap-2 mb-3">
              <GraduationCap className="h-5 w-5 text-primary" />
              <span className="text-sm font-medium text-primary">Cursos</span>
            </div>
            <h1 className="font-serif text-3xl md:text-4xl font-bold mb-3">
              Aprenda com os melhores especialistas
            </h1>
            <p className="text-muted-foreground text-lg">
              Cursos completos de medicina veterinária, do básico ao avançado, com certificado de conclusão.
            </p>
          </div>
        </div>
      </div>

      <div className="max-w-7xl mx-auto px-4 py-8">
        {/* Filters */}
        <div className="flex flex-col sm:flex-row gap-3 mb-8">
          <div className="relative flex-1 max-w-md">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input
              value={search}
              onChange={e => handleSearch(e.target.value)}
              placeholder="Buscar cursos..."
              className="pl-9"
            />
          </div>

          <Select value={level} onValueChange={setLevel}>
            <SelectTrigger className="w-44">
              <SelectValue placeholder="Nível" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">Todos os níveis</SelectItem>
              <SelectItem value="beginner">Iniciante</SelectItem>
              <SelectItem value="intermediate">Intermediário</SelectItem>
              <SelectItem value="advanced">Avançado</SelectItem>
            </SelectContent>
          </Select>

          {categories.length > 0 && (
            <Select value={category} onValueChange={setCategory}>
              <SelectTrigger className="w-48">
                <SelectValue placeholder="Categoria" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todas as categorias</SelectItem>
                {categories.map((c: string) => (
                  <SelectItem key={c} value={c}>{c}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}
        </div>

        {/* Results count */}
        {!isLoading && data && (
          <p className="text-sm text-muted-foreground mb-6">
            {data.length} {data.length === 1 ? "curso encontrado" : "cursos encontrados"}
          </p>
        )}

        {/* Grid */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
          {isLoading && Array.from({ length: 8 }).map((_, i) => <CourseCardSkeleton key={i} />)}

          {!isLoading && data?.length === 0 && (
            <div className="col-span-full py-20 text-center">
              <BookOpen className="h-12 w-12 mx-auto mb-3 text-muted-foreground/20" />
              <p className="text-muted-foreground">Nenhum curso encontrado.</p>
              {(search || level !== "all") && (
                <Button variant="ghost" className="mt-3" onClick={() => { setSearch(""); setDebouncedSearch(""); setLevel("all"); setCategory("all"); }}>
                  Limpar filtros
                </Button>
              )}
            </div>
          )}

          {!isLoading && data?.map((course: any) => (
            <CourseCard key={course.id} course={course} />
          ))}
        </div>
      </div>
    </div>
  );
}
