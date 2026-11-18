import { useState } from "react";
import { trpc } from "@/lib/trpc";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { RefreshCw, Loader2, Activity } from "lucide-react";

const ACTION_LABELS: Record<string, string> = {
  create_question: "Criou questão",
  import_questions: "Importou questões",
  validate_question: "Validou questão",
  create_trail: "Criou trilha",
  update_user: "Atualizou usuário",
  accept_terms: "Aceitou termos",
  login: "Login",
  logout: "Logout",
  update_question: "Atualizou questão",
  delete_question: "Deletou questão",
  send_campaign: "Enviou campanha",
};

const ACTION_COLOR: Record<string, string> = {
  create_question: "bg-emerald-500/20 text-emerald-400",
  import_questions: "bg-emerald-500/20 text-emerald-400",
  validate_question: "bg-blue-500/20 text-blue-400",
  update_user: "bg-amber-500/20 text-amber-400",
  accept_terms: "bg-primary/20 text-primary",
  send_campaign: "bg-purple-500/20 text-purple-400",
};

const dt = (v: unknown) => v ? new Date(v as string).toLocaleString("pt-BR") : "—";

export default function AdminAtividade() {
  const [page, setPage] = useState(1);
  const { data: rows = [], isLoading, refetch } = trpc.admin.activityLog.useQuery({ page, limit: 50 });

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <p className="text-sm text-muted-foreground">Log de ações no sistema</p>
        <Button size="sm" variant="ghost" className="h-8" onClick={() => refetch()}>
          <RefreshCw className="h-3.5 w-3.5 mr-1.5" /> Atualizar
        </Button>
      </div>

      {isLoading ? (
        <div className="flex justify-center py-10"><Loader2 className="h-5 w-5 animate-spin text-primary" /></div>
      ) : (
        <div className="rounded-xl border border-border/50 overflow-hidden">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border/50 bg-muted/20">
                {["Ação", "Entidade", "Usuário", "Role", "IP", "Data"].map(h => (
                  <th key={h} className="px-3 py-2 text-left text-xs text-muted-foreground font-medium">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {(rows as any[]).map((a: any) => (
                <tr key={a.id} className="border-b border-border/30 hover:bg-muted/10">
                  <td className="px-3 py-2">
                    <Badge className={`text-[10px] border-0 ${ACTION_COLOR[a.action] ?? "bg-muted text-muted-foreground"}`}>
                      {ACTION_LABELS[a.action] ?? a.action}
                    </Badge>
                  </td>
                  <td className="px-3 py-2 text-xs text-muted-foreground">
                    {a.entityType ?? "—"}{a.entityId ? ` #${a.entityId}` : ""}
                  </td>
                  <td className="px-3 py-2 text-xs font-medium">{a.userName ?? "Sistema"}</td>
                  <td className="px-3 py-2 text-xs text-muted-foreground">{a.userRole ?? "—"}</td>
                  <td className="px-3 py-2 text-xs font-mono text-muted-foreground">{a.ipAddress ?? "—"}</td>
                  <td className="px-3 py-2 text-xs text-muted-foreground">{dt(a.createdAt)}</td>
                </tr>
              ))}
            </tbody>
          </table>
          {(rows as any[]).length === 0 && (
            <div className="text-center py-12">
              <Activity className="h-8 w-8 mx-auto mb-2 text-muted-foreground opacity-30" />
              <p className="text-sm text-muted-foreground">Nenhuma atividade registrada.</p>
            </div>
          )}
        </div>
      )}

      <div className="flex gap-2 justify-center">
        <Button size="sm" variant="outline" disabled={page === 1} onClick={() => setPage(p => p - 1)}>Anterior</Button>
        <span className="text-sm text-muted-foreground self-center">Página {page}</span>
        <Button size="sm" variant="outline" disabled={(rows as any[]).length < 50} onClick={() => setPage(p => p + 1)}>Próxima</Button>
      </div>
    </div>
  );
}
