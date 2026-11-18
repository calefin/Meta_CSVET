import { useMemo, useState } from "react";
import { trpc } from "@/lib/trpc";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { toast } from "sonner";
import { Download, Loader2, RefreshCw, Target } from "lucide-react";

/**
 * Plano de conteúdo — onde faltam questões x onde há procura.
 * Selecione os itens, baixe o JSON e rode localmente:
 *   python scripts/gerador_questoes.py plano plano_conteudo.json
 */
export default function AdminPlanoConteudo() {
  const { data, isLoading, refetch, isFetching } = trpc.contentRequests.plan.useQuery();
  const exportPlan = trpc.contentRequests.exportPlan.useMutation();
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [filter, setFilter] = useState("");

  const items = useMemo(() => {
    const f = filter.trim().toLowerCase();
    const all = data?.items ?? [];
    return f ? all.filter(i => `${i.disciplineName} ${i.subjectName} ${i.topicName ?? ""}`.toLowerCase().includes(f)) : all;
  }, [data, filter]);

  const toggle = (key: string) => setSelected(prev => {
    const next = new Set(prev);
    next.has(key) ? next.delete(key) : next.add(key);
    return next;
  });

  const totals = useMemo(() => {
    const chosen = (data?.items ?? []).filter(i => selected.has(i.key));
    return {
      n: chosen.length,
      obj: chosen.reduce((a, i) => a + i.recomendado.objetivas, 0),
      disc: chosen.reduce((a, i) => a + i.recomendado.discursivas, 0),
    };
  }, [data, selected]);

  const handleExport = async () => {
    if (selected.size === 0) { toast.error("Selecione ao menos um item."); return; }
    try {
      const plan = await exportPlan.mutateAsync({ keys: Array.from(selected) });
      const blob = new Blob([JSON.stringify(plan, null, 2)], { type: "application/json" });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = `plano_conteudo_${new Date().toISOString().slice(0, 10)}.json`;
      a.click();
      URL.revokeObjectURL(url);
      toast.success(`Plano com ${plan.itens.length} item(ns) baixado.`);
    } catch (e: any) {
      toast.error(e.message || "Falha ao exportar plano.");
    }
  };

  return (
    <Card className="border-border/50">
      <CardHeader className="flex flex-row items-start justify-between gap-4 space-y-0">
        <div>
          <CardTitle className="font-serif flex items-center gap-2"><Target className="h-5 w-5 text-primary" /> Plano de conteúdo</CardTitle>
          <p className="text-xs text-muted-foreground mt-1 font-sans max-w-2xl">
            Prioriza assuntos com <b>falta de questões</b> e <b>maior procura</b> (alunos ativos na disciplina, banco sendo esgotado
            e pedidos de alunos, últimos 90 dias). Meta: {data?.metas.assunto ?? 60} objetivas por assunto,{" "}
            {data?.metas.discursivas ?? 20} discursivas por disciplina. Selecione, baixe o plano e rode{" "}
            <code className="text-[11px]">python scripts/gerador_questoes.py plano &lt;arquivo&gt;</code>.
          </p>
        </div>
        <Button variant="outline" size="sm" onClick={() => refetch()} disabled={isFetching} className="gap-1.5 shrink-0">
          <RefreshCw className={`h-3.5 w-3.5 ${isFetching ? "animate-spin" : ""}`} /> Atualizar
        </Button>
      </CardHeader>
      <CardContent className="space-y-3">
        <div className="flex flex-wrap items-center gap-2">
          <Input placeholder="Filtrar por área, disciplina ou assunto…" value={filter} onChange={e => setFilter(e.target.value)} className="h-9 max-w-xs" />
          <Button size="sm" variant="ghost" onClick={() => setSelected(new Set(items.slice(0, 10).map(i => i.key)))}>Selecionar top 10</Button>
          <Button size="sm" variant="ghost" onClick={() => setSelected(new Set())}>Limpar</Button>
          <div className="flex-1" />
          <span className="text-xs text-muted-foreground font-sans">
            {totals.n} selecionado(s) · ~{totals.obj} objetivas · ~{totals.disc} discursivas
          </span>
          <Button size="sm" onClick={handleExport} disabled={exportPlan.isPending || selected.size === 0} className="gap-1.5">
            {exportPlan.isPending ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Download className="h-3.5 w-3.5" />}
            Baixar plano (JSON)
          </Button>
        </div>

        {isLoading ? (
          <div className="py-12 flex justify-center"><Loader2 className="h-6 w-6 animate-spin text-primary" /></div>
        ) : items.length === 0 ? (
          <p className="py-10 text-center text-sm text-muted-foreground">Nenhuma lacuna encontrada — todas as metas atingidas.</p>
        ) : (
          <div className="overflow-x-auto">
            <Table className="font-sans text-sm">
              <TableHeader>
                <TableRow>
                  <TableHead className="w-8" />
                  <TableHead className="w-10">#</TableHead>
                  <TableHead>Assunto</TableHead>
                  <TableHead className="text-right">Tem</TableHead>
                  <TableHead className="text-right">Alunos 90d</TableHead>
                  <TableHead className="text-right">Pedidos</TableHead>
                  <TableHead className="text-right">Gerar</TableHead>
                  <TableHead>Por quê</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {items.map(i => (
                  <TableRow key={i.key} className={selected.has(i.key) ? "bg-primary/5" : ""}>
                    <TableCell><Checkbox checked={selected.has(i.key)} onCheckedChange={() => toggle(i.key)} /></TableCell>
                    <TableCell className="text-muted-foreground">{i.rank}</TableCell>
                    <TableCell>
                      <div className="font-medium">{i.topicName ?? i.subjectName}</div>
                      <div className="text-xs text-muted-foreground">{i.disciplineName} › {i.subjectName}{i.topicId ? ` · topicId ${i.topicId}` : ""}</div>
                    </TableCell>
                    <TableCell className="text-right">
                      <span className={i.supply.objetivas === 0 ? "text-red-400 font-semibold" : ""}>{i.supply.objetivas}</span>
                    </TableCell>
                    <TableCell className="text-right">{i.demand.alunos90d}</TableCell>
                    <TableCell className="text-right">{i.requestIds.length ? <Badge className="bg-amber-500/15 text-amber-400 border-amber-500/30">{i.requestIds.length}</Badge> : "—"}</TableCell>
                    <TableCell className="text-right whitespace-nowrap">
                      {i.recomendado.objetivas > 0 && <span>{i.recomendado.objetivas} obj</span>}
                      {i.recomendado.discursivas > 0 && <span className="text-muted-foreground"> · {i.recomendado.discursivas} disc</span>}
                    </TableCell>
                    <TableCell className="text-xs text-muted-foreground max-w-[340px]">{i.motivos.join(" · ")}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        )}
      </CardContent>
    </Card>
  );
}
