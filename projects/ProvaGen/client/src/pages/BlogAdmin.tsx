import { useState } from "react";
import { trpc } from "@/lib/trpc";
import { toast } from "sonner";
import ReactMarkdown from "react-markdown";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Badge } from "@/components/ui/badge";
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter,
} from "@/components/ui/dialog";
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger,
} from "@/components/ui/alert-dialog";
import { Plus, Pencil, Trash2, Eye, EyeOff, ExternalLink, Loader2 } from "lucide-react";

function toSlug(title: string) {
  return title.toLowerCase()
    .normalize("NFD").replace(/[̀-ͯ]/g, "")
    .replace(/[^a-z0-9\s-]/g, "")
    .trim().replace(/\s+/g, "-");
}

interface PostForm {
  title: string;
  slug: string;
  excerpt: string;
  markdown: string;
}

const EMPTY_FORM: PostForm = { title: "", slug: "", excerpt: "", markdown: "" };

export default function BlogAdmin() {
  const utils = trpc.useUtils();
  const { data: posts, isLoading } = trpc.blog.list.useQuery({ publishedOnly: false, limit: 100 });

  const createMut   = trpc.blog.create.useMutation();
  const updateMut   = trpc.blog.update.useMutation();
  const publishMut  = trpc.blog.publish.useMutation();
  const unpublishMut = trpc.blog.unpublish.useMutation();
  const deleteMut   = trpc.blog.delete.useMutation();

  const [dialogOpen,    setDialogOpen]    = useState(false);
  const [editingPost,   setEditingPost]   = useState<any | null>(null);
  const [form,          setForm]          = useState<PostForm>(EMPTY_FORM);
  const [previewOpen,   setPreviewOpen]   = useState(false);
  const [isSaving,      setIsSaving]      = useState(false);

  function invalidate() {
    utils.blog.list.invalidate();
  }

  function openCreate() {
    setEditingPost(null);
    setForm(EMPTY_FORM);
    setPreviewOpen(false);
    setDialogOpen(true);
  }

  function openEdit(post: any) {
    setEditingPost(post);
    setForm({
      title:    post.title ?? "",
      slug:     post.slug ?? "",
      excerpt:  post.excerpt ?? "",
      markdown: post.markdown ?? "",
    });
    setPreviewOpen(false);
    setDialogOpen(true);
  }

  function handleTitleChange(v: string) {
    setForm((f) => ({
      ...f,
      title: v,
      slug: editingPost ? f.slug : toSlug(v),
    }));
  }

  async function handleSave() {
    if (!form.title.trim() || !form.slug.trim()) {
      toast.error("Título e slug são obrigatórios");
      return;
    }
    setIsSaving(true);
    try {
      if (editingPost) {
        await updateMut.mutateAsync({ id: editingPost.id, ...form });
        toast.success("Artigo atualizado");
      } else {
        await createMut.mutateAsync(form);
        toast.success("Rascunho criado");
      }
      setDialogOpen(false);
      invalidate();
    } catch (e: any) {
      toast.error(e?.message || "Erro ao salvar");
    } finally {
      setIsSaving(false);
    }
  }

  async function handleTogglePublish(post: any) {
    try {
      if (post.published === "true") {
        await unpublishMut.mutateAsync({ id: post.id });
        toast.success("Artigo despublicado");
      } else {
        await publishMut.mutateAsync({ id: post.id });
        toast.success("Artigo publicado!");
      }
      invalidate();
    } catch (e: any) {
      toast.error(e?.message || "Erro");
    }
  }

  async function handleDelete(id: number) {
    try {
      await deleteMut.mutateAsync({ id });
      toast.success("Artigo excluído");
      invalidate();
    } catch (e: any) {
      toast.error(e?.message || "Erro ao excluir");
    }
  }

  if (isLoading) return (
    <div className="flex justify-center py-12">
      <Loader2 className="w-6 h-6 animate-spin text-muted-foreground" />
    </div>
  );

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <p className="text-sm text-muted-foreground">{(posts ?? []).length} artigo(s)</p>
        <Button size="sm" onClick={openCreate}>
          <Plus className="w-4 h-4 mr-1" /> Novo artigo
        </Button>
      </div>

      {(posts ?? []).length === 0 && (
        <p className="text-center text-muted-foreground py-10 text-sm">Nenhum artigo ainda.</p>
      )}

      <div className="space-y-2">
        {(posts as any[] ?? []).map((post: any) => (
          <div key={post.id} className="flex items-center gap-3 border rounded-lg px-4 py-3 bg-card">
            <div className="flex-1 min-w-0">
              <p className="font-medium text-sm truncate">{post.title}</p>
              <p className="text-xs text-muted-foreground font-mono">/blog/{post.slug}</p>
            </div>
            <Badge variant={post.published === "true" ? "default" : "secondary"} className="text-xs shrink-0">
              {post.published === "true" ? "Publicado" : "Rascunho"}
            </Badge>
            {post.publishedAt && (
              <span className="text-xs text-muted-foreground shrink-0 hidden md:block">
                {new Date(post.publishedAt).toLocaleDateString("pt-BR")}
              </span>
            )}
            <div className="flex items-center gap-1 shrink-0">
              <Button size="icon" variant="ghost" className="h-8 w-8" title="Editar" onClick={() => openEdit(post)}>
                <Pencil className="w-3.5 h-3.5" />
              </Button>
              <Button
                size="icon" variant="ghost" className="h-8 w-8"
                title={post.published === "true" ? "Despublicar" : "Publicar"}
                onClick={() => handleTogglePublish(post)}
              >
                {post.published === "true"
                  ? <EyeOff className="w-3.5 h-3.5" />
                  : <Eye className="w-3.5 h-3.5" />}
              </Button>
              {post.published === "true" && (
                <a href={`/blog/${post.slug}`} target="_blank" rel="noopener noreferrer">
                  <Button size="icon" variant="ghost" className="h-8 w-8" title="Ver no blog">
                    <ExternalLink className="w-3.5 h-3.5" />
                  </Button>
                </a>
              )}
              <AlertDialog>
                <AlertDialogTrigger asChild>
                  <Button size="icon" variant="ghost" className="h-8 w-8 text-destructive hover:text-destructive">
                    <Trash2 className="w-3.5 h-3.5" />
                  </Button>
                </AlertDialogTrigger>
                <AlertDialogContent>
                  <AlertDialogHeader>
                    <AlertDialogTitle>Excluir artigo?</AlertDialogTitle>
                    <AlertDialogDescription>
                      "{post.title}" será excluído permanentemente.
                    </AlertDialogDescription>
                  </AlertDialogHeader>
                  <AlertDialogFooter>
                    <AlertDialogCancel>Cancelar</AlertDialogCancel>
                    <AlertDialogAction
                      className="bg-destructive text-white hover:bg-destructive/90"
                      onClick={() => handleDelete(post.id)}
                    >
                      Excluir
                    </AlertDialogAction>
                  </AlertDialogFooter>
                </AlertDialogContent>
              </AlertDialog>
            </div>
          </div>
        ))}
      </div>

      {/* Editor dialog */}
      <Dialog open={dialogOpen} onOpenChange={(o) => !isSaving && setDialogOpen(o)}>
        <DialogContent className="max-w-4xl h-[90vh] flex flex-col p-0 gap-0">
          <DialogHeader className="px-6 pt-5 pb-3 border-b shrink-0">
            <DialogTitle>{editingPost ? "Editar artigo" : "Novo artigo"}</DialogTitle>
          </DialogHeader>

          <div className="flex-1 overflow-hidden flex flex-col gap-0">
            {/* Metadata row */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-3 px-6 py-3 border-b shrink-0">
              <div className="md:col-span-1 space-y-1">
                <Label className="text-xs">Título *</Label>
                <Input
                  value={form.title}
                  onChange={(e) => handleTitleChange(e.target.value)}
                  placeholder="Título do artigo"
                  className="h-8 text-sm"
                />
              </div>
              <div className="space-y-1">
                <Label className="text-xs">Slug *</Label>
                <Input
                  value={form.slug}
                  onChange={(e) => setForm((f) => ({ ...f, slug: e.target.value }))}
                  placeholder="url-do-artigo"
                  className="h-8 text-sm font-mono"
                />
              </div>
              <div className="space-y-1">
                <Label className="text-xs">Resumo (excerpt)</Label>
                <Input
                  value={form.excerpt}
                  onChange={(e) => setForm((f) => ({ ...f, excerpt: e.target.value }))}
                  placeholder="Descrição curta para listagem"
                  className="h-8 text-sm"
                />
              </div>
            </div>

            {/* Editor / Preview toggle */}
            <div className="flex items-center gap-2 px-6 py-2 border-b shrink-0">
              <button
                onClick={() => setPreviewOpen(false)}
                className={`text-xs px-3 py-1 rounded transition ${!previewOpen ? "bg-primary text-primary-foreground" : "text-muted-foreground hover:text-foreground"}`}
              >
                Escrever
              </button>
              <button
                onClick={() => setPreviewOpen(true)}
                className={`text-xs px-3 py-1 rounded transition ${previewOpen ? "bg-primary text-primary-foreground" : "text-muted-foreground hover:text-foreground"}`}
              >
                Pré-visualizar
              </button>
              <span className="text-xs text-muted-foreground ml-2">Markdown</span>
            </div>

            {/* Content area */}
            <div className="flex-1 overflow-auto px-6 py-4">
              {!previewOpen ? (
                <Textarea
                  value={form.markdown}
                  onChange={(e) => setForm((f) => ({ ...f, markdown: e.target.value }))}
                  placeholder={`# Título\n\nEscreva o conteúdo em **Markdown**...\n\n## Subtítulo\n\nTexto do artigo.`}
                  className="w-full h-full min-h-[300px] font-mono text-sm resize-none border-0 focus-visible:ring-0 p-0"
                />
              ) : (
                <div className="prose prose-slate max-w-none prose-headings:font-semibold prose-a:text-primary prose-code:bg-muted prose-code:px-1 prose-code:rounded">
                  {form.markdown
                    ? <ReactMarkdown>{form.markdown}</ReactMarkdown>
                    : <p className="text-muted-foreground italic">Nenhum conteúdo para pré-visualizar.</p>}
                </div>
              )}
            </div>
          </div>

          <DialogFooter className="px-6 py-3 border-t shrink-0">
            <Button variant="outline" onClick={() => setDialogOpen(false)} disabled={isSaving}>
              Cancelar
            </Button>
            <Button onClick={handleSave} disabled={isSaving}>
              {isSaving ? <Loader2 className="w-4 h-4 mr-2 animate-spin" /> : null}
              {editingPost ? "Salvar alterações" : "Criar rascunho"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
