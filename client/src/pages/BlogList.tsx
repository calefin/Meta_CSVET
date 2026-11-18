import { trpc } from "@/lib/trpc";
import { useLocation } from "wouter";
import { Loader2, BookOpen, CalendarDays, ArrowRight } from "lucide-react";

export default function BlogList() {
  const [, navigate] = useLocation();
  const { data: posts, isLoading } = trpc.blog.list.useQuery({ publishedOnly: true, limit: 50 });

  return (
    <div className="max-w-3xl mx-auto space-y-10">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">Blog</h1>
          <p className="text-muted-foreground mt-1">Dicas, novidades e boas práticas para professores</p>
        </div>

        {isLoading && (
          <div className="flex justify-center py-16">
            <Loader2 className="w-7 h-7 animate-spin text-muted-foreground" />
          </div>
        )}

        {!isLoading && (!posts || posts.length === 0) && (
          <div className="text-center py-20 text-muted-foreground">
            <BookOpen className="w-10 h-10 mx-auto mb-3 opacity-30" />
            <p>Nenhum artigo publicado ainda.</p>
          </div>
        )}

        <div className="space-y-8">
          {(posts ?? []).map((post: any) => (
            <article
              key={post.id}
              className="group cursor-pointer border-b pb-8 last:border-0"
              onClick={() => navigate(`/blog/${post.slug}`)}
            >
              <div className="flex items-center gap-2 text-xs text-muted-foreground mb-2">
                <CalendarDays className="w-3.5 h-3.5" />
                {post.publishedAt
                  ? new Date(post.publishedAt).toLocaleDateString("pt-BR", { day: "2-digit", month: "long", year: "numeric" })
                  : "—"}
              </div>
              <h2 className="text-xl font-semibold group-hover:text-primary transition-colors">
                {post.title}
              </h2>
              {post.excerpt && (
                <p className="text-muted-foreground mt-2 text-sm leading-relaxed line-clamp-3">
                  {post.excerpt}
                </p>
              )}
              <div className="mt-3 flex items-center gap-1 text-sm text-primary font-medium">
                Ler artigo <ArrowRight className="w-4 h-4" />
              </div>
            </article>
          ))}
        </div>
    </div>
  );
}
