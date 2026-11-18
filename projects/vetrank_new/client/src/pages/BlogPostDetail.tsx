import { trpc } from "@/lib/trpc";
import SEO from "@/components/SEO";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { useLanguage } from "@/contexts/LanguageContext";
import { ArrowLeft, BookOpen, Calendar, Clock, Share2, User } from "lucide-react";
import { Link, useRoute } from "wouter";
import { toast } from "sonner";

export default function BlogPostDetail() {
  const [match, params] = useRoute("/blog/:slug");
  const slug = match && params ? params.slug : "";

  const { language } = useLanguage();
  const isPt = language === "pt";

  // Query post detail by slug
  const { data: post, isLoading, error } = trpc.blog.getBySlug.useQuery({ slug }, {
    enabled: slug.length > 0
  });

  const getCategoryBadge = (category?: string) => {
    switch (category) {
      case "news":
        return (
          <Badge className="bg-blue-500/10 text-blue-500 border-blue-500/20 font-sans text-xs px-2.5 py-0.5">
            {isPt ? "Notícias" : "News"}
          </Badge>
        );
      case "contest":
        return (
          <Badge className="bg-purple-500/10 text-purple-500 border-purple-500/20 font-sans text-xs px-2.5 py-0.5">
            {isPt ? "Concursos" : "Contests"}
          </Badge>
        );
      case "residency":
        return (
          <Badge className="bg-amber-500/10 text-amber-500 border-amber-500/20 font-sans text-xs px-2.5 py-0.5">
            {isPt ? "Residências" : "Residencies"}
          </Badge>
        );
      default:
        return (
          <Badge className="bg-emerald-500/10 text-emerald-500 border-emerald-500/20 font-sans text-xs px-2.5 py-0.5">
            {isPt ? "Científico" : "Scientific"}
          </Badge>
        );
    }
  };

  const formatDate = (dateStr: string) => {
    try {
      const d = new Date(dateStr);
      return d.toLocaleDateString(isPt ? "pt-BR" : "en-US", {
        day: "2-digit",
        month: "long",
        year: "numeric",
      });
    } catch {
      return dateStr;
    }
  };

  const getReadingTime = (slugStr: string) => {
    const base = slugStr.length % 5;
    return `${5 + base} min`;
  };

  const handleShare = () => {
    try {
      navigator.clipboard.writeText(window.location.href);
      toast.success(isPt ? "Link copiado para a área de transferência!" : "Link copied to clipboard!");
    } catch {
      toast.error(isPt ? "Erro ao copiar link." : "Failed to copy link.");
    }
  };

  const jsonLd = post ? {
    "@context": "https://schema.org",
    "@type": "BlogPosting",
    "headline": post.title,
    "description": post.summary,
    "author": {
      "@type": "Person",
      "name": post.author || "Equipe VetRank"
    },
    "datePublished": post.createdAt,
    "dateModified": post.updatedAt,
    "mainEntityOfPage": `https://vetrank.com.br/blog/${post.slug}`,
    "publisher": {
      "@type": "Organization",
      "name": "VetRank",
      "logo": "https://vetrank.com.br/logo_vetrank.svg"
    }
  } : undefined;

  return (
    <div className="min-h-screen bg-background py-12">
      {post && (
        <SEO
          title={`${post.title} | Blog VetRank`}
          description={post.summary}
          canonical={`/blog/${post.slug}`}
          jsonLd={jsonLd}
        />
      )}

      <div className="container max-w-4xl mx-auto px-4">
        {/* Back Link */}
        <div className="mb-8">
          <Link href="/blog">
            <span className="inline-flex items-center gap-2 text-sm font-sans text-muted-foreground hover:text-primary transition-colors cursor-pointer group">
              <ArrowLeft className="h-4 w-4 group-hover:-translate-x-0.5 transition-transform" />
              {isPt ? "Voltar ao Blog" : "Back to Blog"}
            </span>
          </Link>
        </div>

        {isLoading ? (
          <div className="space-y-6">
            <Skeleton className="h-10 w-3/4 rounded" />
            <div className="flex gap-4">
              <Skeleton className="h-4 w-24" />
              <Skeleton className="h-4 w-24" />
            </div>
            <Skeleton className="h-64 w-full rounded-2xl" />
            <Skeleton className="h-4 w-full" />
            <Skeleton className="h-4 w-full" />
            <Skeleton className="h-4 w-5/6" />
          </div>
        ) : error || !post ? (
          <div className="text-center py-20 bg-card/20 rounded-3xl border border-border/30 max-w-lg mx-auto">
            <BookOpen className="h-12 w-12 text-destructive mx-auto mb-4" />
            <h3 className="font-serif text-xl font-bold text-foreground dark:text-white mb-2">
              {isPt ? "Artigo não encontrado" : "Article not found"}
            </h3>
            <p className="text-muted-foreground font-sans text-sm mb-6">
              {isPt
                ? "O artigo solicitado pode ter sido arquivado ou removido."
                : "The requested article might have been archived or removed."}
            </p>
            <Link href="/blog">
              <Button variant="outline" className="font-sans">
                {isPt ? "Explorar outros Artigos" : "Explore other Articles"}
              </Button>
            </Link>
          </div>
        ) : (
          <article className="space-y-8">
            {/* Header */}
            <div className="space-y-4">
              <div className="flex items-center gap-2">
                {getCategoryBadge(post.category)}
              </div>
              <h1 className="font-serif text-3xl md:text-5xl font-bold text-foreground dark:text-white leading-tight">
                {post.title}
              </h1>
              
              <div className="flex flex-wrap items-center justify-between gap-4 pt-4 border-t border-border/40 text-sm text-muted-foreground font-sans">
                <div className="flex flex-wrap items-center gap-4">
                  <span className="flex items-center gap-2 font-medium text-foreground dark:text-white">
                    <User className="h-4 w-4 text-primary" />
                    {post.author || "Equipe VetRank"}
                  </span>
                  <span className="flex items-center gap-1.5">
                    <Calendar className="h-4 w-4" />
                    {formatDate(post.createdAt)}
                  </span>
                  <span className="flex items-center gap-1.5">
                    <Clock className="h-4 w-4" />
                    {getReadingTime(post.slug)}
                  </span>
                </div>
                
                <Button
                  size="sm"
                  variant="outline"
                  className="border-border/60 hover:border-primary/40 font-sans gap-1.5 text-xs"
                  onClick={handleShare}
                >
                  <Share2 className="h-3.5 w-3.5" />
                  {isPt ? "Compartilhar" : "Share"}
                </Button>
              </div>
            </div>

            {/* Cover Image */}
            <div className="relative overflow-hidden rounded-3xl bg-accent min-h-[250px] md:min-h-[400px] flex items-center justify-center border border-border/50">
              {post.coverImageUrl ? (
                <img
                  src={post.coverImageUrl}
                  alt={post.title}
                  className="absolute inset-0 w-full h-full object-cover"
                />
              ) : (
                <div className="absolute inset-0 bg-gradient-to-br from-emerald-500/10 via-background to-purple-500/10 flex flex-col items-center justify-center p-8 text-center">
                  <BookOpen className="h-20 w-20 text-primary/30 mb-3" />
                  <h3 className="font-serif text-lg font-bold text-foreground/40 dark:text-white/40">VetRank Scientific Series</h3>
                  <span className="text-[10px] font-mono text-muted-foreground/50">CONTEÚDO CIENTÍFICO E PEDAGÓGICO DE EXCELÊNCIA</span>
                </div>
              )}
            </div>

            {/* Summary Block */}
            <div className="p-6 rounded-2xl bg-primary/5 border border-primary/20 italic text-muted-foreground font-sans text-sm md:text-base leading-relaxed">
              {post.summary}
            </div>

            {/* Body (Render HTML content generated by Gemini) */}
            <div 
              className="prose dark:prose-invert max-w-none font-sans text-muted-foreground leading-relaxed md:text-lg space-y-6 
                         prose-headings:font-serif prose-headings:text-foreground dark:prose-headings:text-white prose-h2:text-2xl prose-h2:font-bold prose-h2:mt-10 prose-h2:mb-4
                         prose-h3:text-xl prose-h3:font-semibold prose-h3:mt-6 prose-h3:mb-3
                         prose-p:mb-4 prose-p:leading-relaxed
                         prose-ul:list-disc prose-ul:pl-6 prose-ul:mb-4 prose-li:mb-2
                         prose-table:w-full prose-table:border-collapse prose-table:my-8
                         prose-th:bg-accent prose-th:text-foreground dark:prose-th:text-white prose-th:p-3 prose-th:border prose-th:border-border/60 prose-th:text-left
                         prose-td:p-3 prose-td:border prose-td:border-border/60"
              dangerouslySetInnerHTML={{ __html: post.body }}
            />

            {/* Bottom Section: CTA */}
            <div className="mt-16 p-8 rounded-3xl bg-card border border-border/80 text-center max-w-2xl mx-auto space-y-4">
              <Badge className="bg-primary/10 text-primary border-primary/20 font-sans text-xs px-2.5 py-0.5">
                {isPt ? "Estude de Forma Inteligente" : "Study Smart"}
              </Badge>
              <h3 className="font-serif text-2xl font-bold text-foreground dark:text-white">
                {isPt ? "Gostou deste artigo? Comece a praticar agora!" : "Liked this article? Start practicing now!"}
              </h3>
              <p className="text-muted-foreground text-sm font-sans max-w-md mx-auto leading-relaxed">
                {isPt
                  ? "O VetRank conta com milhares de questões reais e comentadas dos mesmos temas deste post para te ajudar a fixar."
                  : "VetRank features thousands of real, commented questions covering these exact topics to solidify your learning."}
              </p>
              <div className="pt-2">
                <Link href="/">
                  <Button className="bg-primary text-primary-foreground hover:bg-primary/90 px-8 font-sans gap-1.5 shadow-lg shadow-primary/10">
                    {isPt ? "Começar a Resolver Questões" : "Start Answering Questions"}
                    <ArrowLeft className="h-4 w-4 rotate-180" />
                  </Button>
                </Link>
              </div>
            </div>
          </article>
        )}
      </div>
    </div>
  );
}
