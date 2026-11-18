import { trpc } from "@/lib/trpc";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { toast } from "sonner";
import { ExternalLink, FileDown, Landmark, Loader2, RefreshCw } from "lucide-react";

const dt = (d: unknown) => d ? new Date(d as string).toLocaleDateString("pt-BR", { timeZone: "America/Sao_Paulo" }) : "—";

/** Radar de Concursos — revisão rápida: ocultar item errado e forçar coleta. A coleta roda sozinha todo dia (cron). */
export default function AdminConcursos() {
  const utils = trpc.useUtils();
  const { data, isLoading } = trpc.concursos.adminList.useQuery();
  const setActive = trpc.concursos.adminSetActive.useMutation({ onSuccess: () => utils.concursos.adminList.invalidate() });
  const sync = trpc.concursos.adminSync.useMutation({
    onSuccess: (r) => {
      toast.success(`Coleta: ${r.inseridos} novo(s), ${r.atualizados} atualizado(s), ${r.ignorados} ignorado(s)${r.erros.length ? `, ${r.erros.length} erro(s)` : ""}.`);
      utils.concursos.adminList.invalidate();
    },
    onError: (e) => toast.error(e.message),
  });
  // Itens "ignorado-*"/"dup-*" são só marcadores para não reprocessar a mesma notícia
  const rows = (data ?? []).filter(c => !c.slug.startsWith("ignorado-") && !c.slug.startsWith("dup-"));

  return (
    <Card className="border-border/50">
      <CardHeader className="flex flex-row items-start justify-between space-y-0 gap-4">
        <div>
          <CardTitle className="font-serif flex items-center gap-2"><Landmark className="h-5 w-5 text-primary" /> Radar de Concursos (Vet & Zoo)</CardTitle>
          <p className="text-xs text-muted-foreground mt-1">Monitoramento diário de editais (custo zero de IA) + semente local (`data/concursos_seed.json`). Desligue o que estiver errado — some do site na hora.</p>
        </div>
        <Button size="sm" variant="outline" onClick={() => sync.mutate()} disabled={sync.isPending} className="gap-1.5">
          {sync.isPending ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <RefreshCw className="h-3.5 w-3.5" />} Coletar agora
        </Button>
      </CardHeader>
      <CardContent>
        {isLoading ? <div className="py-10 flex justify-center"><Loader2 className="h-6 w-6 animate-spin" /></div> : (
          <div className="overflow-x-auto">
            <Table className="text-sm">
              <TableHeader><TableRow>
                <TableHead>Visível</TableHead><TableHead>Órgão</TableHead><TableHead>UF</TableHead>
                <TableHead>Vagas</TableHead><TableHead>Inscrições até</TableHead><TableHead>Banca</TableHead><TableHead>Ações</TableHead>
              </TableRow></TableHeader>
              <TableBody>
                {rows.map(c => (
                  <TableRow key={c.id} className={c.active ? "" : "opacity-50"}>
                    <TableCell><Switch checked={c.active} onCheckedChange={(v) => setActive.mutate({ id: c.id, active: v })} /></TableCell>
                    <TableCell className="font-medium max-w-[280px]">{c.orgao}</TableCell>
                    <TableCell>{c.uf ?? "—"}</TableCell>
                    <TableCell>{c.vagasVet ?? "—"}</TableCell>
                    <TableCell>{dt(c.inscricoesFim)}</TableCell>
                    <TableCell>{c.banca ?? "—"}</TableCell>
                    <TableCell className="whitespace-nowrap">
                      <a href={`/concursos/${c.slug}`} target="_blank" rel="noreferrer" className="text-primary text-xs mr-3 inline-flex items-center gap-0.5">página <ExternalLink className="h-3 w-3" /></a>
                      <a href={`/api/concursos/${c.slug}/guia.pdf`} target="_blank" rel="noreferrer" className="text-muted-foreground hover:text-primary text-xs inline-flex items-center gap-0.5"><FileDown className="h-3 w-3" /> PDF</a>
                    </TableCell>
                  </TableRow>
                ))}
                {rows.length === 0 && <TableRow><TableCell colSpan={7} className="text-center text-muted-foreground py-8">Nenhum concurso coletado ainda — clique em "Coletar agora".</TableCell></TableRow>}
              </TableBody>
            </Table>
          </div>
        )}
      </CardContent>
    </Card>
  );
}
