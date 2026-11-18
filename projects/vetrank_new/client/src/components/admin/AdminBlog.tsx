import { useState } from "react";
import { trpc } from "@/lib/trpc";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { toast } from "sonner";
import {
  CheckCircle2, XCircle, Loader2, RefreshCw,
  ShieldCheck, Wrench, Eye, EyeOff, AlertTriangle,
} from "lucide-react";

type DOIResult = {
  postId: number;
  title: string;
  total: number;
  valid: { doi: string; title?: string }[];
  notFound: string[];
  errors: string[];
} | null;

export default function AdminBlog() {
  const { data: posts, isLoading, refetch } = trpc.blog.listAll.useQuery({ limit: 100, offset: 0 });

  const updatePost = trpc.blog.update.useMutation();
  const fixOne = trpc.blog.fixInvalidDOIs.useMutation();
  const verifyAll = trpc.blog.verifyAllPosts.useMutation();

  // Per-post DOI verification query is lazy — triggered manually
  const [verifying, setVerifying] = useState<number | null>(null);
  const [doiResults, setDoiResults] = useState<Record<number, DOIResult>>({});
  const [fixing, setFixing] = useState<number | null>(null);
  const [scanningAll, setScanningAll] = useState(false);
  const [allScanReport, setAllScanReport] = useState<{
    postsScanned: number; postsFixed: number;
    report: { postId: number; title: string; fixed: number; invalidDois: string[] }[];
  } | null>(null);

  const trpcUtils = trpc.useUtils();

  const handleToggleActive = async (id: number, current: boolean) => {
    try {
      await updatePost.mutateAsync({ id, active: !current });
      toast.success(current ? "Artigo desativado." : "Artigo publicado.");
      refetch();
    } catch (err: any) {
      toast.error(err.message);
    }
  };

  const handleVerifyDOIs = async (id: number) => {
    setVerifying(id);
    try {
      const result = await trpcUtils.blog.verifyReferences.fetch({ id });
      setDoiResults(prev => ({ ...prev, [id]: result }));
      if (result.notFound.length === 0) {
        toast.success(`${result.total} DOI(s) verificados — todos válidos.`);
      } else {
        toast.warning(`${result.notFound.length} DOI(s) inválido(s) encontrado(s).`);
      }
    } catch (err: any) {
      toast.error("Erro ao verificar DOIs: " + err.message);
    } finally {
      setVerifying(null);
    }
  };

  const handleFixDOIs = async (id: number) => {
    setFixing(id);
    try {
      const result = await fixOne.mutateAsync({ id });
      toast.success(result.message);
      // Clear cached verification result so user re-verifies after fix
      setDoiResults(prev => { const n = { ...prev }; delete n[id]; return n; });
      refetch();
    } catch (err: any) {
      toast.error("Erro ao corrigir DOIs: " + err.message);
    } finally {
      setFixing(null);
    }
  };

  const handleVerifyAll = async () => {
    setScanningAll(true);
    setAllScanReport(null);
    try {
      const result = await verifyAll.mutateAsync();
      setAllScanReport(result);
      if (result.postsFixed === 0) {
        toast.success(`${result.postsScanned} artigos escaneados — nenhum DOI inválido encontrado.`);
      } else {
        toast.warning(`${result.postsFixed} artigo(s) corrigido(s) de ${result.postsScanned} escaneados.`);
      }
      refetch();
    } catch (err: any) {
      toast.error("Erro no escaneamento: " + err.message);
    } finally {
      setScanningAll(false);
    }
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-16">
        <Loader2 className="w-6 h-6 animate-spin text-muted-foreground" />
      </div>
    );
  }

  const postList = (posts ?? []) as any[];

  return (
    <div className="space-y-6">
      {/* Header + global action */}
      <div className="flex items-center justify-between gap-4 flex-wrap">
        <div>
          <h2 className="font-serif text-2xl font-bold">Blog</h2>
          <p className="text-muted-foreground text-sm">{postList.length} artigos • Gerencie publicações e verifique referências</p>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" size="sm" onClick={() => refetch()} className="gap-1.5">
            <RefreshCw className="w-3.5 h-3.5" /> Atualizar
          </Button>
          <Button
            size="sm"
            onClick={handleVerifyAll}
            disabled={scanningAll}
            className="gap-1.5 bg-teal-700 hover:bg-teal-600 text-white"
          >
            {scanningAll ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <ShieldCheck className="w-3.5 h-3.5" />}
            {scanningAll ? "Escaneando…" : "Verificar DOIs em Todos os Artigos"}
          </Button>
        </div>
      </div>

      {/* Global scan report */}
      {allScanReport && (
        <div className={`rounded-lg border p-4 text-sm ${allScanReport.postsFixed > 0 ? "border-yellow-500/40 bg-yellow-500/5" : "border-green-500/40 bg-green-500/5"}`}>
          <p className="font-medium mb-2">
            {allScanReport.postsFixed === 0
              ? `✅ Todos os ${allScanReport.postsScanned} artigos verificados — nenhum DOI inválido encontrado.`
              : `⚠️ ${allScanReport.postsFixed} de ${allScanReport.postsScanned} artigos tinham DOIs inválidos e foram corrigidos.`}
          </p>
          {allScanReport.report.length > 0 && (
            <ul className="space-y-1 mt-2">
              {allScanReport.report.map(r => (
                <li key={r.postId} className="text-xs text-muted-foreground">
                  <span className="font-medium text-foreground">#{r.postId}</span> {r.title} —{" "}
                  <span className="text-red-400">{r.fixed} removido(s):</span> {r.invalidDois.join(", ")}
                </li>
              ))}
            </ul>
          )}
        </div>
      )}

      {/* Post list */}
      <div className="space-y-3">
        {postList.length === 0 && (
          <p className="text-muted-foreground text-sm text-center py-12">Nenhum artigo encontrado.</p>
        )}
        {postList.map((post: any) => {
          const doi = doiResults[post.id];
          const isVerifying = verifying === post.id;
          const isFixing = fixing === post.id;

          return (
            <div key={post.id} className="border border-border/50 rounded-xl p-4 bg-card/30 space-y-3">
              {/* Post header row */}
              <div className="flex items-start gap-3">
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 flex-wrap mb-1">
                    <Badge variant={post.active ? "default" : "secondary"} className="text-xs">
                      {post.active ? "Publicado" : "Rascunho"}
                    </Badge>
                    <Badge variant="outline" className="text-xs">{post.category}</Badge>
                    <span className="text-xs text-muted-foreground">#{post.id}</span>
                  </div>
                  <p className="font-medium text-sm leading-snug">{post.title}</p>
                  <p className="text-xs text-muted-foreground mt-0.5">{post.author} · {new Date(post.createdAt).toLocaleDateString("pt-BR")}</p>
                </div>

                {/* Action buttons */}
                <div className="flex items-center gap-1.5 shrink-0 flex-wrap justify-end">
                  <Button
                    variant="ghost" size="sm"
                    onClick={() => handleToggleActive(post.id, post.active)}
                    className="gap-1.5 h-8 text-xs"
                    title={post.active ? "Desativar" : "Publicar"}
                  >
                    {post.active
                      ? <><EyeOff className="w-3.5 h-3.5" /> Desativar</>
                      : <><Eye className="w-3.5 h-3.5" /> Publicar</>}
                  </Button>

                  <Button
                    variant="outline" size="sm"
                    onClick={() => handleVerifyDOIs(post.id)}
                    disabled={isVerifying}
                    className="gap-1.5 h-8 text-xs"
                  >
                    {isVerifying
                      ? <Loader2 className="w-3.5 h-3.5 animate-spin" />
                      : <ShieldCheck className="w-3.5 h-3.5" />}
                    {isVerifying ? "Verificando…" : "Verificar DOIs"}
                  </Button>

                  {doi && doi.notFound.length > 0 && (
                    <Button
                      variant="destructive" size="sm"
                      onClick={() => handleFixDOIs(post.id)}
                      disabled={isFixing}
                      className="gap-1.5 h-8 text-xs"
                    >
                      {isFixing
                        ? <Loader2 className="w-3.5 h-3.5 animate-spin" />
                        : <Wrench className="w-3.5 h-3.5" />}
                      {isFixing ? "Corrigindo…" : `Corrigir ${doi.notFound.length} DOI(s)`}
                    </Button>
                  )}
                </div>
              </div>

              {/* DOI verification result */}
              {doi && (
                <div className={`rounded-lg p-3 text-xs border ${
                  doi.total === 0
                    ? "border-border/40 bg-muted/20"
                    : doi.notFound.length === 0
                      ? "border-green-500/30 bg-green-500/5"
                      : "border-red-500/30 bg-red-500/5"
                }`}>
                  {doi.total === 0 ? (
                    <p className="text-muted-foreground">Nenhum DOI encontrado neste artigo.</p>
                  ) : (
                    <>
                      <div className="flex items-center gap-4 mb-2 flex-wrap">
                        <span className="flex items-center gap-1 text-green-400">
                          <CheckCircle2 className="w-3.5 h-3.5" />
                          {doi.valid.length} válido(s)
                        </span>
                        {doi.notFound.length > 0 && (
                          <span className="flex items-center gap-1 text-red-400">
                            <XCircle className="w-3.5 h-3.5" />
                            {doi.notFound.length} inválido(s)
                          </span>
                        )}
                        {doi.errors.length > 0 && (
                          <span className="flex items-center gap-1 text-yellow-400">
                            <AlertTriangle className="w-3.5 h-3.5" />
                            {doi.errors.length} erro(s) de rede
                          </span>
                        )}
                      </div>

                      {doi.notFound.length > 0 && (
                        <div className="space-y-0.5">
                          <p className="font-medium text-red-400 mb-1">DOIs não encontrados no CrossRef:</p>
                          {doi.notFound.map(d => (
                            <p key={d} className="font-mono text-red-300/80 break-all">{d}</p>
                          ))}
                        </div>
                      )}

                      {doi.valid.length > 0 && (
                        <details className="mt-2">
                          <summary className="cursor-pointer text-muted-foreground hover:text-foreground">
                            Ver DOIs válidos ({doi.valid.length})
                          </summary>
                          <div className="mt-1 space-y-0.5 pl-2 border-l border-green-500/20">
                            {doi.valid.map(v => (
                              <p key={v.doi} className="font-mono text-green-400/70 break-all">
                                {v.doi}{v.title ? <span className="font-sans text-muted-foreground not-italic"> — {v.title}</span> : null}
                              </p>
                            ))}
                          </div>
                        </details>
                      )}
                    </>
                  )}
                </div>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}
