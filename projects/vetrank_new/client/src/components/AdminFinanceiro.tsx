import { useState } from "react";
import { trpc } from "@/lib/trpc";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { toast } from "sonner";
import {
  CreditCard, TrendingUp, AlertTriangle, Shield, Users,
  CheckCircle, XCircle, Clock, RefreshCw, Eye, Loader2, Download,
} from "lucide-react";

function exportCSV(rows: any[], filename: string, columns: { key: string; label: string }[]) {
  const header = columns.map(c => c.label).join(",");
  const body = rows.map(r =>
    columns.map(c => `"${String(r[c.key] ?? "").replace(/"/g, '""')}"`).join(",")
  ).join("\n");
  const blob = new Blob(["﻿" + header + "\n" + body], { type: "text/csv;charset=utf-8;" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a"); a.href = url; a.download = filename; a.click();
  URL.revokeObjectURL(url);
}

// ── Formatters ────────────────────────────────────────────────────────────────
const BRL = (v: number) => `R$ ${Number(v).toFixed(2).replace(".", ",")}`;
const dt  = (v: unknown) => v ? new Date(v as string).toLocaleDateString("pt-BR") : "—";

// ── Status badges ─────────────────────────────────────────────────────────────
const STATUS_STYLE: Record<string, string> = {
  approved:   "bg-emerald-500/20 text-emerald-400 border-emerald-500/30",
  rejected:   "bg-red-500/20 text-red-400 border-red-500/30",
  pending:    "bg-amber-500/20 text-amber-400 border-amber-500/30",
  cancelled:  "bg-red-500/20 text-red-400 border-red-500/30",
  refunded:   "bg-blue-500/20 text-blue-400 border-blue-500/30",
  authorized: "bg-emerald-500/20 text-emerald-400 border-emerald-500/30",
  paused:     "bg-amber-500/20 text-amber-400 border-amber-500/30",
  fraud:      "bg-red-500/20 text-red-400 border-red-500/30",
};
const STATUS_PT: Record<string, string> = {
  approved: "Aprovado", rejected: "Rejeitado", pending: "Pendente",
  cancelled: "Cancelado", refunded: "Estornado", authorized: "Autorizado",
  paused: "Pausado", fraud: "Fraude",
};
const EVENT_TYPE_STYLE: Record<string, string> = {
  payment: "bg-primary/20 text-primary border-primary/30",
  subscription_preapproval: "bg-purple-500/20 text-purple-400 border-purple-500/30",
  subscription_authorized_payment: "bg-purple-500/20 text-purple-300 border-purple-500/30",
  claim: "bg-amber-500/20 text-amber-400 border-amber-500/30",
  chargebacks: "bg-red-500/20 text-red-400 border-red-500/30",
  fraud_alert: "bg-red-500/20 text-red-300 border-red-500/30",
  merchant_order: "bg-muted text-muted-foreground border-border/40",
};
const EVENT_TYPE_PT: Record<string, string> = {
  payment: "Pagamento",
  subscription_preapproval: "Assinatura",
  subscription_authorized_payment: "Cobrança Recorrente",
  claim: "Reclamação",
  chargebacks: "Contestação",
  fraud_alert: "Alerta de Fraude",
  merchant_order: "Pedido",
};

// ── Summary cards ─────────────────────────────────────────────────────────────
function SummaryCard({ label, value, icon: Icon, color = "text-primary", alert = false }: {
  label: string; value: string | number; icon: React.ElementType; color?: string; alert?: boolean;
}) {
  return (
    <div className={`rounded-xl border p-4 ${alert ? "border-red-500/40 bg-red-500/5" : "border-border/50 bg-card"}`}>
      <div className="flex items-start justify-between mb-2">
        <span className="text-xs text-muted-foreground">{label}</span>
        <Icon className={`h-4 w-4 ${color}`} />
      </div>
      <p className={`text-xl font-bold font-serif ${alert ? "text-red-400" : color}`}>{value}</p>
    </div>
  );
}

// ── Payments tab ──────────────────────────────────────────────────────────────
function PaymentsTab() {
  const [status, setStatus] = useState<any>("all");
  const [planType, setPlanType] = useState<any>("all");
  const [page, setPage] = useState(1);
  const { data, isLoading, refetch } = trpc.admin.listPayments.useQuery({ page, limit: 30, status, planType });

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap gap-2 items-center">
        <Select value={status} onValueChange={v => { setStatus(v); setPage(1); }}>
          <SelectTrigger className="h-8 w-36 text-xs"><SelectValue /></SelectTrigger>
          <SelectContent>
            {["all","approved","rejected","pending","cancelled","refunded"].map(s => (
              <SelectItem key={s} value={s}>{s === "all" ? "Todos" : (STATUS_PT[s] ?? s)}</SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Select value={planType} onValueChange={v => { setPlanType(v); setPage(1); }}>
          <SelectTrigger className="h-8 w-36 text-xs"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem value="all">Todos os planos</SelectItem>
            <SelectItem value="monthly">Mensal</SelectItem>
            <SelectItem value="annual">Anual</SelectItem>
          </SelectContent>
        </Select>
        <Button size="sm" variant="ghost" className="h-8" onClick={() => refetch()}>
          <RefreshCw className="h-3.5 w-3.5 mr-1.5" /> Atualizar
        </Button>
        <Button size="sm" variant="outline" className="h-8 gap-1.5" onClick={() => exportCSV(
          data?.rows ?? [],
          `pagamentos_${new Date().toISOString().slice(0,10)}.csv`,
          [
            { key: "id", label: "ID" }, { key: "userName", label: "Nome" },
            { key: "userEmail", label: "Email" }, { key: "cpf", label: "CPF" },
            { key: "phone", label: "Telefone" }, { key: "planType", label: "Plano" },
            { key: "amount", label: "Valor" }, { key: "status", label: "Status" },
            { key: "paymentMethod", label: "Metodo" }, { key: "createdAt", label: "Data" },
          ]
        )}>
          <Download className="h-3.5 w-3.5" /> Exportar CSV
        </Button>
      </div>

      {isLoading ? (
        <div className="flex justify-center py-12"><Loader2 className="h-6 w-6 animate-spin text-primary" /></div>
      ) : (
        <div className="overflow-x-auto rounded-xl border border-border/50">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border/50 bg-muted/20">
                {["ID", "Usuário", "CPF", "Plano", "Valor", "Status", "Método", "Data"].map(h => (
                  <th key={h} className="px-3 py-2 text-left text-xs text-muted-foreground font-medium">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {(data?.rows ?? []).map((p: any) => (
                <tr key={p.id} className="border-b border-border/30 hover:bg-muted/10">
                  <td className="px-3 py-2 text-xs text-muted-foreground font-mono">#{p.id}</td>
                  <td className="px-3 py-2">
                    <p className="text-xs font-medium truncate max-w-32">{p.userName ?? "—"}</p>
                    <p className="text-[10px] text-muted-foreground truncate max-w-32">{p.userEmail ?? "—"}</p>
                  </td>
                  <td className="px-3 py-2 text-xs font-mono text-muted-foreground">{p.cpf ?? "—"}</td>
                  <td className="px-3 py-2">
                    <Badge variant="outline" className="text-[10px]">{p.planType === "monthly" ? "Mensal" : "Anual"}</Badge>
                  </td>
                  <td className="px-3 py-2 text-xs font-bold text-emerald-400">{BRL(p.amount)}</td>
                  <td className="px-3 py-2">
                    <Badge className={`text-[10px] border ${STATUS_STYLE[p.status] ?? ""}`}>
                      {STATUS_PT[p.status] ?? p.status}
                    </Badge>
                  </td>
                  <td className="px-3 py-2 text-xs text-muted-foreground">{p.paymentMethod ?? "—"}</td>
                  <td className="px-3 py-2 text-xs text-muted-foreground">{dt(p.createdAt)}</td>
                </tr>
              ))}
            </tbody>
          </table>
          {(data?.rows ?? []).length === 0 && (
            <div className="text-center py-12 text-muted-foreground text-sm">Nenhum pagamento encontrado.</div>
          )}
        </div>
      )}

      {(data?.total ?? 0) > 30 && (
        <div className="flex gap-2 justify-center">
          <Button size="sm" variant="outline" disabled={page === 1} onClick={() => setPage(p => p - 1)}>Anterior</Button>
          <Button size="sm" variant="outline" onClick={() => setPage(p => p + 1)}>Próxima</Button>
        </div>
      )}
    </div>
  );
}

// ── Subscribers tab ───────────────────────────────────────────────────────────
function SubscribersTab() {
  const { data: rows = [], isLoading } = trpc.admin.listSubscribers.useQuery();
  const now = new Date();

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <p className="text-sm text-muted-foreground">{(rows as any[]).length} assinante(s) premium ativos.</p>
        <Button size="sm" variant="outline" className="h-8 gap-1.5" onClick={() => exportCSV(
          rows as any[],
          `assinantes_${new Date().toISOString().slice(0,10)}.csv`,
          [
            { key: "name", label: "Nome" }, { key: "email", label: "Email" },
            { key: "cpf", label: "CPF" }, { key: "phone", label: "Telefone" },
            { key: "subscriptionPlan", label: "Plano" },
            { key: "premiumStartedAt", label: "Inicio" }, { key: "premiumEndsAt", label: "Expiracao" },
          ]
        )}>
          <Download className="h-3.5 w-3.5" /> Exportar CSV
        </Button>
      </div>
      <div className="overflow-x-auto rounded-xl border border-border/50">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-border/50 bg-muted/20">
              {["Nome", "Email", "CPF", "Telefone", "Plano", "Expira em", "Desde"].map(h => (
                <th key={h} className="px-3 py-2 text-left text-xs text-muted-foreground font-medium">{h}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.map((u: any) => {
              const expiresAt = u.premiumEndsAt ? new Date(u.premiumEndsAt) : null;
              const daysLeft = expiresAt ? Math.ceil((expiresAt.getTime() - now.getTime()) / 86400000) : null;
              const expiring = daysLeft !== null && daysLeft <= 7;
              return (
                <tr key={u.id} className={`border-b border-border/30 hover:bg-muted/10 ${expiring ? "bg-amber-500/5" : ""}`}>
                  <td className="px-3 py-2 text-xs font-medium">{u.name ?? "—"}</td>
                  <td className="px-3 py-2 text-xs text-muted-foreground">{u.email ?? "—"}</td>
                  <td className="px-3 py-2 text-xs font-mono text-muted-foreground">{u.cpf ?? "—"}</td>
                  <td className="px-3 py-2 text-xs text-muted-foreground">{u.phone ?? "—"}</td>
                  <td className="px-3 py-2">
                    <Badge variant="outline" className="text-[10px]">
                      {u.subscriptionPlan === "monthly" ? "Mensal" : u.subscriptionPlan === "annual" ? "Anual" : "—"}
                    </Badge>
                  </td>
                  <td className="px-3 py-2 text-xs">
                    {expiresAt ? (
                      <span className={expiring ? "text-amber-400 font-medium" : "text-muted-foreground"}>
                        {dt(expiresAt)} {daysLeft !== null && `(${daysLeft}d)`}
                      </span>
                    ) : "—"}
                  </td>
                  <td className="px-3 py-2 text-xs text-muted-foreground">{dt(u.premiumStartedAt)}</td>
                </tr>
              );
            })}
          </tbody>
        </table>
        {isLoading && <div className="flex justify-center py-8"><Loader2 className="h-5 w-5 animate-spin text-primary" /></div>}
        {!isLoading && rows.length === 0 && <div className="text-center py-12 text-sm text-muted-foreground">Nenhum assinante premium.</div>}
      </div>
    </div>
  );
}

// ── Events tab (claims, chargebacks, fraud) ───────────────────────────────────
function EventsTab() {
  const [typeFilter, setTypeFilter] = useState("");
  const [resolvedFilter, setResolvedFilter] = useState<boolean | undefined>(false);
  const [resolveId, setResolveId] = useState<number | null>(null);
  const [resolveNote, setResolveNote] = useState("");
  const [viewRaw, setViewRaw] = useState<any>(null);

  const { data, isLoading, refetch } = trpc.admin.listMpEvents.useQuery({
    type: typeFilter || undefined,
    resolved: resolvedFilter,
  });
  const resolveMutation = trpc.admin.resolveMpEvent.useMutation({
    onSuccess: () => { toast.success("Evento resolvido."); setResolveId(null); setResolveNote(""); refetch(); },
    onError: e => toast.error(e.message),
  });

  const alertTypes = ["claim", "chargebacks", "fraud_alert"];
  const rows = data?.rows ?? [];
  const alertRows = rows.filter((r: any) => alertTypes.includes(r.type));

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap gap-2 items-center">
        <Select value={typeFilter || "all"} onValueChange={v => setTypeFilter(v === "all" ? "" : v)}>
          <SelectTrigger className="h-8 w-48 text-xs"><SelectValue placeholder="Tipo de evento" /></SelectTrigger>
          <SelectContent>
            <SelectItem value="all">Todos os tipos</SelectItem>
            {Object.entries(EVENT_TYPE_PT).map(([k, v]) => (
              <SelectItem key={k} value={k}>{v}</SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Select value={resolvedFilter === undefined ? "all" : resolvedFilter ? "resolved" : "pending"} onValueChange={v => setResolvedFilter(v === "all" ? undefined : v === "resolved")}>
          <SelectTrigger className="h-8 w-36 text-xs"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem value="all">Todos</SelectItem>
            <SelectItem value="pending">Pendentes</SelectItem>
            <SelectItem value="resolved">Resolvidos</SelectItem>
          </SelectContent>
        </Select>
        <Button size="sm" variant="ghost" className="h-8" onClick={() => refetch()}>
          <RefreshCw className="h-3.5 w-3.5 mr-1.5" /> Atualizar
        </Button>
      </div>

      {alertRows.length > 0 && (
        <div className="rounded-xl border border-red-500/30 bg-red-500/5 p-4">
          <div className="flex items-center gap-2 mb-2">
            <AlertTriangle className="h-4 w-4 text-red-400" />
            <span className="text-sm font-medium text-red-400">{alertRows.length} alerta(s) pendente(s) de atenção</span>
          </div>
          <div className="flex flex-wrap gap-2">
            {alertRows.map((r: any) => (
              <Badge key={r.id} className={`border ${EVENT_TYPE_STYLE[r.type] ?? ""} text-xs`}>
                {EVENT_TYPE_PT[r.type] ?? r.type} #{r.id}
              </Badge>
            ))}
          </div>
        </div>
      )}

      {isLoading ? (
        <div className="flex justify-center py-12"><Loader2 className="h-6 w-6 animate-spin text-primary" /></div>
      ) : (
        <div className="overflow-x-auto rounded-xl border border-border/50">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border/50 bg-muted/20">
                {["#", "Tipo", "Ação", "Status", "Usuário", "Valor", "Data", ""].map(h => (
                  <th key={h} className="px-3 py-2 text-left text-xs text-muted-foreground font-medium">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.map((e: any) => (
                <tr key={e.id} className={`border-b border-border/30 hover:bg-muted/10 ${!e.resolved && alertTypes.includes(e.type) ? "bg-red-500/5" : ""}`}>
                  <td className="px-3 py-2 text-xs text-muted-foreground font-mono">{e.id}</td>
                  <td className="px-3 py-2">
                    <Badge className={`text-[10px] border ${EVENT_TYPE_STYLE[e.type] ?? ""}`}>
                      {EVENT_TYPE_PT[e.type] ?? e.type}
                    </Badge>
                  </td>
                  <td className="px-3 py-2 text-xs text-muted-foreground">{e.action ?? "—"}</td>
                  <td className="px-3 py-2">
                    {e.status ? (
                      <Badge className={`text-[10px] border ${STATUS_STYLE[e.status] ?? "bg-muted text-muted-foreground"}`}>
                        {STATUS_PT[e.status] ?? e.status}
                      </Badge>
                    ) : "—"}
                  </td>
                  <td className="px-3 py-2 text-xs">
                    <p className="font-medium truncate max-w-28">{e.userName ?? "—"}</p>
                    <p className="text-muted-foreground truncate max-w-28">{e.userEmail ?? ""}</p>
                  </td>
                  <td className="px-3 py-2 text-xs text-emerald-400">{e.amount ? BRL(e.amount) : "—"}</td>
                  <td className="px-3 py-2 text-xs text-muted-foreground">{dt(e.createdAt)}</td>
                  <td className="px-3 py-2">
                    <div className="flex gap-1">
                      <Button size="icon" variant="ghost" className="h-6 w-6" title="Ver payload" onClick={() => setViewRaw(e.raw)}>
                        <Eye className="h-3 w-3" />
                      </Button>
                      {!e.resolved && (
                        <Button size="icon" variant="ghost" className="h-6 w-6 text-emerald-400" title="Resolver" onClick={() => setResolveId(e.id)}>
                          <CheckCircle className="h-3 w-3" />
                        </Button>
                      )}
                      {e.resolved && <CheckCircle className="h-3.5 w-3.5 text-emerald-400 opacity-50 m-auto" />}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          {rows.length === 0 && (
            <div className="text-center py-12 text-sm text-muted-foreground">Nenhum evento encontrado.</div>
          )}
        </div>
      )}

      {/* Resolve dialog */}
      <Dialog open={!!resolveId} onOpenChange={v => { if (!v) setResolveId(null); }}>
        <DialogContent className="max-w-sm">
          <DialogHeader><DialogTitle>Resolver Evento #{resolveId}</DialogTitle></DialogHeader>
          <Textarea
            placeholder="Nota de resolução (opcional)..."
            value={resolveNote}
            onChange={e => setResolveNote(e.target.value)}
            rows={3}
            className="resize-none text-sm"
          />
          <Button
            className="w-full"
            disabled={resolveMutation.isPending}
            onClick={() => resolveId && resolveMutation.mutate({ id: resolveId, note: resolveNote || undefined })}
          >
            {resolveMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin mr-2" /> : <CheckCircle className="h-4 w-4 mr-2" />}
            Confirmar Resolução
          </Button>
        </DialogContent>
      </Dialog>

      {/* Raw payload dialog */}
      <Dialog open={!!viewRaw} onOpenChange={v => { if (!v) setViewRaw(null); }}>
        <DialogContent className="max-w-2xl max-h-[80vh] overflow-y-auto">
          <DialogHeader><DialogTitle>Payload do Webhook</DialogTitle></DialogHeader>
          <pre className="text-xs bg-muted/30 rounded-lg p-4 overflow-x-auto whitespace-pre-wrap break-all">
            {JSON.stringify(viewRaw, null, 2)}
          </pre>
        </DialogContent>
      </Dialog>
    </div>
  );
}

// ── Main component ─────────────────────────────────────────────────────────────
export default function AdminFinanceiro() {
  const [tab, setTab] = useState<"summary" | "payments" | "subscribers" | "events">("summary");
  const { data: summary } = trpc.admin.financialSummary.useQuery();

  const tabs = [
    { id: "summary",     label: "Resumo",      icon: TrendingUp },
    { id: "payments",    label: "Pagamentos",  icon: CreditCard },
    { id: "subscribers", label: "Assinantes",  icon: Users },
    { id: "events",      label: "Alertas MP",  icon: AlertTriangle },
  ] as const;

  const openAlerts = (summary?.openClaims ?? 0) + (summary?.openChargebacks ?? 0) + (summary?.openFraudAlerts ?? 0);

  return (
    <div className="space-y-6">
      {/* Tab switcher */}
      <div className="flex flex-wrap gap-1.5">
        {tabs.map(t => (
          <Button
            key={t.id}
            size="sm"
            variant={tab === t.id ? "default" : "outline"}
            className="h-8 text-xs gap-1.5"
            onClick={() => setTab(t.id)}
          >
            <t.icon className="h-3.5 w-3.5" />
            {t.label}
            {t.id === "events" && openAlerts > 0 && (
              <Badge className="ml-0.5 h-4 px-1 text-[10px] bg-red-500/30 text-red-300 border-red-500/30">{openAlerts}</Badge>
            )}
          </Button>
        ))}
      </div>

      {/* Summary */}
      {tab === "summary" && summary && (
        <div className="space-y-4">
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <SummaryCard label="Receita Total" value={BRL(summary.totalRevenue ?? 0)} icon={TrendingUp} color="text-emerald-400" />
            <SummaryCard label="Últimos 30 dias" value={BRL(summary.last30Days ?? 0)} icon={CreditCard} color="text-primary" />
            <SummaryCard label="Assinaturas Mensais" value={summary.monthlyCount ?? 0} icon={Users} color="text-purple-400" />
            <SummaryCard label="Planos Anuais" value={summary.annualCount ?? 0} icon={Shield} color="text-cyan-400" />
          </div>
          <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <SummaryCard label="Pagamentos Aprovados" value={summary.totalApproved ?? 0} icon={CheckCircle} color="text-emerald-400" />
            <SummaryCard label="Pagamentos Rejeitados" value={summary.totalRejected ?? 0} icon={XCircle} color="text-red-400" />
            <SummaryCard label="Reclamações abertas" value={summary.openClaims ?? 0} icon={AlertTriangle} color="text-amber-400" alert={(summary.openClaims ?? 0) > 0} />
          </div>
          {(summary.openChargebacks > 0 || summary.openFraudAlerts > 0) && (
            <div className="rounded-xl border border-red-500/40 bg-red-500/5 p-4 space-y-1">
              <p className="text-sm font-medium text-red-400 flex items-center gap-2">
                <AlertTriangle className="h-4 w-4" /> Atenção imediata necessária
              </p>
              {summary.openChargebacks > 0 && <p className="text-xs text-muted-foreground">• {summary.openChargebacks} contestação(ões) de cartão aberta(s)</p>}
              {summary.openFraudAlerts > 0 && <p className="text-xs text-muted-foreground">• {summary.openFraudAlerts} alerta(s) de fraude</p>}
              <Button size="sm" className="mt-2 h-7 text-xs" onClick={() => setTab("events")}>Ver alertas</Button>
            </div>
          )}
        </div>
      )}

      {tab === "payments" && <PaymentsTab />}
      {tab === "subscribers" && <SubscribersTab />}
      {tab === "events" && <EventsTab />}
    </div>
  );
}
