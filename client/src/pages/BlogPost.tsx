import { trpc } from "@/lib/trpc";
import { useParams, useLocation } from "wouter";
import ReactMarkdown from "react-markdown";
import { Loader2, CalendarDays, ArrowLeft, BookOpen } from "lucide-react";
import { Button } from "@/components/ui/button";

export default function BlogPost() {
  const params = useParams<{ slug: string }>();
  const [, navigate] = useLocation();
  const { data: post, isLoading } = trpc.blog.getBySlug.useQuery(
    { slug: params.slug ?? "" },
    { enabled: !!params.slug },
  );

  return (
    <div className="max-w-2xl mx-auto">
        {isLoading && (
          <div className="flex justify-center py-24">
            <Loader2 className="w-7 h-7 animate-spin text-muted-foreground" />
          </div>
        )}

        {!isLoading && !post && (
          <div className="text-center py-24 space-y-4">
            <BookOpen className="w-10 h-10 mx-auto text-muted-foreground opacity-30" />
            <p className="text-muted-foreground">Artigo não encontrado.</p>
            <Button variant="outline" onClick={() => navigate("/blog")}>
              <ArrowLeft className="w-4 h-4 mr-2" /> Voltar ao blog
            </Button>
          </div>
        )}

        {post && (
          <article className="space-y-6">
            <Button
              variant="ghost" size="sm"
              className="px-0 text-muted-foreground"
              onClick={() => navigate("/blog")}
            >
              <ArrowLeft className="w-4 h-4 mr-1" /> Blog
            </Button>

            <div className="space-y-3">
              <div className="flex items-center gap-2 text-xs text-muted-foreground">
                <CalendarDays className="w-3.5 h-3.5" />
                {post.publishedAt
                  ? new Date(post.publishedAt).toLocaleDateString("pt-BR", { day: "2-digit", month: "long", year: "numeric" })
                  : "—"}
              </div>
              <h1 className="text-3xl font-bold tracking-tight">{post.title}</h1>
              {post.excerpt && (
                <p className="text-lg text-muted-foreground leading-relaxed">{post.excerpt}</p>
              )}
            </div>

            <hr />

            <div className="prose prose-slate max-w-none prose-headings:font-semibold prose-a:text-primary prose-code:bg-muted prose-code:px-1 prose-code:rounded">
              <ReactMarkdown>{post.markdown ?? ""}</ReactMarkdown>
            </div>
          </article>
        )}
    </div>
  );
}
