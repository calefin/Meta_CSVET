import { useState } from "react";
import { trpc } from "@/lib/trpc";
import SEO from "@/components/SEO";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { useLanguage } from "@/contexts/LanguageContext";
import { ArrowRight, BookOpen, Calendar, Clock, User, Zap, ChevronRight } from "lucide-react";
import { Link } from "wouter";

export default function Blog() {
  const { language } = useLanguage();
  const isPt = language === "pt";
  const [selectedCategory, setSelectedCategory] = useState<string | undefined>(undefined);

  // Query blog posts from tRPC
  const { data: posts, isLoading } = trpc.blog.list.useQuery({
    limit: 24,
    category: selectedCategory,
  });

  const categories = [
    { value: undefined, labelPt: "Todos", labelEn: "All" },
    { value: "scientific", labelPt: "Artigos Científicos", labelEn: "Scientific Articles" },
    { value: "news", labelPt: "Notícias", labelEn: "News" },
    { value: "contest", labelPt: "Concursos", labelEn: "Contests" },
    { value: "residency", labelPt: "Residências", labelEn: "Residencies" },
  ];

  const getCategoryBadge = (category?: string) => {
    switch (category) {
      case "news":
        return (
          <Badge className="bg-blue-500/10 text-blue-500 border-blue-500/20 font-sans text-[10px] px-2 py-0.5">
            {isPt ? "Notícias" : "News"}
          </Badge>
        );
      case "contest":
        return (
          <Badge className="bg-purple-500/10 text-purple-500 border-purple-500/20 font-sans text-[10px] px-2 py-0.5">
            {isPt ? "Concursos" : "Contests"}
          </Badge>
        );
      case "residency":
        return (
          <Badge className="bg-amber-500/10 text-amber-500 border-amber-500/20 font-sans text-[10px] px-2 py-0.5">
            {isPt ? "Residências" : "Residencies"}
          </Badge>
        );
      default:
        return (
          <Badge className="bg-emerald-500/10 text-emerald-500 border-emerald-500/20 font-sans text-[10px] px-2 py-0.5">
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
        month: "short",
        year: "numeric",
      });
    } catch {
      return dateStr;
    }
  };

  const getReadingTime = (slug: string) => {
    // Estimativa fictícia baseada no slug para preencher visualmente
    const base = slug.length % 5;
    return `${5 + base} min`;
  };

  const jsonLd = {
    "@context": "https://schema.org",
    "@type": "Blog",
    "name": "Blog Científico VetRank",
    "url": "https://vetrank.com.br/blog",
    "description": "Artigos científicos, guias de estudo e atualizações da Medicina Veterinária voltados a provas de Residência, ENADE e ENARE.",
    "publisher": {
      "@type": "Organization",
      "name": "VetRank",
      "logo": "https://vetrank.com.br/logo_vetrank.svg"
    }
  };

  // Separate latest/featured post from the rest
  const featuredPost = posts && posts.length > 0 ? posts[0] : null;
  const regularPosts = posts && posts.length > 1 ? posts.slice(1) : [];

  return (
    <div className="min-h-screen bg-background">
      <SEO
        title={isPt ? "Blog Científico e Guias de Estudo | VetRank" : "Scientific Blog & Study Guides | VetRank"}
        description={isPt
          ? "Acesse artigos científicos aprofundados, resumos de editais, guias e dicas práticas para provas de Residência Veterinária, ENARE e ENADE."
          : "Access deep scientific articles, exam updates, guides, and practical tips for Veterinary Residency, ENARE, and ENADE."}
        canonical="/blog"
        jsonLd={jsonLd}
      />

      {/* Hero Section */}
      <section className="relative overflow-hidden py-16 md:py-24 border-b border-border/40 bg-card/10">
        <div className="absolute inset-0 pointer-events-none">
          <div className="absolute top-1/4 left-1/4 w-96 h-96 rounded-full bg-emerald-500/5 blur-3xl" />
          <div className="absolute bottom-1/4 right-1/4 w-64 h-64 rounded-full bg-purple-500/5 blur-3xl" />
        </div>
        <div className="container relative">
          <div className="max-w-3xl mx-auto text-center">
            <Badge className="mb-4 bg-primary/10 text-primary border-primary/20 font-sans text-xs px-3 py-1">
              {isPt ? "Blog Científico VetRank" : "VetRank Scientific Blog"}
            </Badge>
            <h1 className="font-serif text-4xl md:text-5xl font-bold mb-4 text-foreground dark:text-white leading-tight">
              {isPt ? "Conhecimento que impulsiona sua aprovação" : "Knowledge that drives your success"}
            </h1>
            <p className="text-muted-foreground text-base md:text-lg font-sans leading-relaxed max-w-xl mx-auto">
              {isPt
                ? "Artigos científicos, análises de editais, dicas e novidades da Medicina Veterinária atualizados semanalmente."
                : "Weekly updated scientific articles, exam reviews, guides, and news in Veterinary Medicine."}
            </p>
          </div>
        </div>
      </section>

      <div className="container py-16">
        {/* Category Tabs */}
        <div className="flex flex-wrap items-center justify-center gap-2 mb-12">
          {categories.map((cat) => (
            <button
              key={cat.value || "all"}
              onClick={() => setSelectedCategory(cat.value)}
              className={`px-5 py-2.5 rounded-full font-sans text-xs md:text-sm font-semibold transition-all cursor-pointer border ${
                selectedCategory === cat.value
                  ? "bg-primary text-primary-foreground border-primary shadow-md shadow-primary/20"
                  : "bg-card text-muted-foreground border-border/50 hover:text-foreground hover:bg-accent/40"
              }`}
            >
              {isPt ? cat.labelPt : cat.labelEn}
            </button>
          ))}
        </div>

        {isLoading ? (
          <div className="space-y-12">
            {/* Featured Post Skeleton */}
            <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 bg-card/30 p-8 rounded-3xl border border-border/50">
              <div className="lg:col-span-7">
                <Skeleton className="h-64 w-full rounded-2xl" />
              </div>
              <div className="lg:col-span-5 flex flex-col justify-center space-y-4">
                <Skeleton className="h-4 w-24" />
                <Skeleton className="h-8 w-full" />
                <Skeleton className="h-20 w-full" />
                <div className="flex gap-4">
                  <Skeleton className="h-4 w-20" />
                  <Skeleton className="h-4 w-20" />
                </div>
              </div>
            </div>
            {/* Grid Skeletons */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
              {[1, 2, 3].map((i) => (
                <div key={i} className="p-6 bg-card border border-border/50 rounded-2xl space-y-4">
                  <Skeleton className="h-40 w-full rounded-xl" />
                  <Skeleton className="h-4 w-24" />
                  <Skeleton className="h-6 w-full" />
                  <Skeleton className="h-12 w-full" />
                </div>
              ))}
            </div>
          </div>
        ) : !posts || posts.length === 0 ? (
          <div className="text-center py-20 bg-card/20 rounded-3xl border border-border/30 max-w-xl mx-auto">
            <BookOpen className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
            <h3 className="font-serif text-xl font-bold text-foreground dark:text-white mb-2">
              {isPt ? "Nenhum artigo encontrado" : "No articles found"}
            </h3>
            <p className="text-muted-foreground font-sans text-sm mb-6">
              {isPt
                ? "Estamos preparando conteúdos excepcionais nesta categoria para você. Volte em breve!"
                : "We are preparing high-quality contents in this category for you. Please check back later!"}
            </p>
          </div>
        ) : (
          <div className="space-y-16">
            {/* Featured Post */}
            {featuredPost && (
              <Link href={`/blog/${featuredPost.slug}`}>
                <div className="group grid grid-cols-1 lg:grid-cols-12 gap-8 bg-card/30 hover:bg-card/50 transition-all p-6 md:p-8 rounded-3xl border border-border/50 hover:border-primary/30 cursor-pointer shadow-lg shadow-black/10">
                  <div className="lg:col-span-7 relative overflow-hidden rounded-2xl bg-accent flex items-center justify-center min-h-[250px] md:min-h-[350px]">
                    {featuredPost.coverImageUrl ? (
                      <img
                        src={featuredPost.coverImageUrl}
                        alt={featuredPost.title}
                        className="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                      />
                    ) : (
                      <div className="absolute inset-0 bg-gradient-to-br from-emerald-500/10 via-background to-purple-500/10 flex flex-col items-center justify-center p-8">
                        <BookOpen className="h-16 w-16 text-primary/40 mb-3" />
                        <span className="text-xs font-mono text-muted-foreground">VetRank Scientific Series</span>
                      </div>
                    )}
                    <Badge className="absolute top-4 left-4 bg-emerald-500 text-emerald-950 font-bold font-sans">
                      {isPt ? "Mais Recente" : "Featured"}
                    </Badge>
                  </div>
                  <div className="lg:col-span-5 flex flex-col justify-center">
                    <div className="flex flex-wrap items-center gap-3 text-xs text-muted-foreground font-sans mb-4">
                      {getCategoryBadge(featuredPost.category)}
                      <span className="flex items-center gap-1">
                        <Calendar className="h-3.5 w-3.5" />
                        {formatDate(featuredPost.createdAt)}
                      </span>
                      <span className="flex items-center gap-1">
                        <Clock className="h-3.5 w-3.5" />
                        {getReadingTime(featuredPost.slug)}
                      </span>
                    </div>
                    <h2 className="font-serif text-2xl md:text-3xl font-bold mb-4 text-foreground dark:text-white group-hover:text-primary transition-colors leading-tight">
                      {featuredPost.title}
                    </h2>
                    <p className="text-muted-foreground font-sans text-sm md:text-base mb-6 leading-relaxed line-clamp-3">
                      {featuredPost.summary}
                    </p>
                    <div className="flex items-center justify-between mt-auto pt-4 border-t border-border/40">
                      <span className="flex items-center gap-2 text-sm font-sans text-muted-foreground font-medium">
                        <User className="h-4 w-4 text-primary" />
                        {featuredPost.author || "Equipe VetRank"}
                      </span>
                      <span className="flex items-center gap-1.5 text-primary text-sm font-bold font-sans group-hover:translate-x-1 transition-transform">
                        {isPt ? "Ler Artigo" : "Read Article"}
                        <ArrowRight className="h-4 w-4" />
                      </span>
                    </div>
                  </div>
                </div>
              </Link>
            )}

            {/* Grid of Regular Posts */}
            {regularPosts.length > 0 && (
              <div className="space-y-8">
                <h3 className="font-serif text-2xl font-bold text-foreground dark:text-white border-b border-border/30 pb-3">
                  {isPt ? "Artigos Publicados" : "Published Articles"}
                </h3>
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                  {regularPosts.map((post) => (
                    <Link key={post.id} href={`/blog/${post.slug}`}>
                      <div className="group p-6 bg-card border border-border/50 hover:border-primary/20 rounded-2xl cursor-pointer hover:shadow-lg hover:shadow-primary/5 transition-all flex flex-col h-full">
                        <div className="relative overflow-hidden rounded-xl bg-accent h-44 mb-4 flex items-center justify-center shrink-0">
                          {post.coverImageUrl ? (
                            <img
                              src={post.coverImageUrl}
                              alt={post.title}
                              className="absolute inset-0 w-full h-full object-cover group-hover:scale-102 transition-transform duration-300"
                            />
                          ) : (
                            <div className="absolute inset-0 bg-gradient-to-tr from-emerald-500/5 via-background to-cyan-500/5 flex flex-col items-center justify-center">
                              <BookOpen className="h-10 w-10 text-primary/30 mb-2" />
                              <span className="text-[10px] font-mono text-muted-foreground/60">VetRank</span>
                            </div>
                          )}
                        </div>

                        <div className="flex flex-wrap items-center gap-3 text-xs text-muted-foreground font-sans mb-3">
                          {getCategoryBadge(post.category)}
                          <span className="flex items-center gap-1">
                            <Calendar className="h-3 w-3" />
                            {formatDate(post.createdAt)}
                          </span>
                          <span className="flex items-center gap-1">
                            <Clock className="h-3 w-3" />
                            {getReadingTime(post.slug)}
                          </span>
                        </div>

                        <h4 className="font-serif text-lg font-bold mb-2 text-foreground dark:text-white group-hover:text-primary transition-colors line-clamp-2 leading-snug">
                          {post.title}
                        </h4>

                        <p className="text-muted-foreground font-sans text-xs mb-4 line-clamp-3 leading-relaxed">
                          {post.summary}
                        </p>

                        <div className="flex items-center justify-between mt-auto pt-3 border-t border-border/30">
                          <span className="text-[11px] font-sans text-muted-foreground flex items-center gap-1.5">
                            <User className="h-3.5 w-3.5 text-primary" />
                            {post.author || "Equipe VetRank"}
                          </span>
                          <span className="flex items-center gap-1 text-primary text-xs font-bold font-sans group-hover:translate-x-0.5 transition-transform">
                            {isPt ? "Ler" : "Read"}
                            <ChevronRight className="h-3.5 w-3.5" />
                          </span>
                        </div>
                      </div>
                    </Link>
                  ))}
                </div>
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
