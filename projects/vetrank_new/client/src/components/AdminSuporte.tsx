import { useState } from "react";
import { trpc } from "@/lib/trpc";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { toast } from "sonner";
import {
  MessageSquare, Send, RefreshCw, Search, Loader2,
  AlertTriangle, Clock, CheckCircle, User, Shield,
} from "lucide-react";

const STATUS_STYLE: Record<string, string> = {
  open:         "bg-red-500/20 text-red-400 border-red-500/30",
  in_progress:  "bg-blue-500/20 text-blue-400 border-blue-500/30",
  waiting_user: "bg-amber-500/20 text-amber-400 border-amber-500/30",
  resolved:     "bg-emerald-500/20 text-emerald-400 border-emerald-500/30",
  closed:       "bg-muted text-muted-foreground border-border/40",
};
const STATUS_PT: Record<string, string> = {
  open: "Aberto", in_progress: "Em andamento",
  waiting_user: "Aguardando usuário", resolved: "Resolvido", closed: "Fechado",
};
const PRIORITY_STYLE: Record<string, string> = {
  urgent: "bg-red-500/30 text-red-300 border-red-500/30",
  high:   "bg-orange-500/20 text-orange-400 border-orange-500/30",
  medium: "bg-amber-500/15 text-amber-400 border-amber-500/20",
  low:    "bg-muted text-muted-foreground border-border/40",
};
const PRIORITY_PT: Record<string, string> = {
  urgent: "Urgente", high: "Alta", medium: "Média", low: "Baixa",
};
const dt = (v: unknown) => v ? new Date(v as string).toLocaleString("pt-BR") : "—";

export default function AdminSuporte() {
  const [statusFilter, setStatusFilter] = useState<any>("open");
  const [search, setSearch] = useState("");
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [replyMsg, setReplyMsg]       = useState("");
  const [newStatus, setNewStatus]     = useState<any>("");
  const [adminNote, setAdminNote]     = useState("");
  const [saving, setSaving]           = useState(false);

  const { data: stats }   = trpc.tickets.adminStats.useQuery();
  const { data: list, refetch, isLoading } = trpc.tickets.adminList.useQuery({
    status: statusFilter, search: search || undefined,
  });
  const { data: ticketData, refetch: refetchTicket } = trpc.tickets.getTicket.useQuery(
    { ticketId: selectedId! }, { enabled: !!selectedId }
  );
  const updateMutation = trpc.tickets.adminUpdate.useMutation({
    onSuccess: () => {
      toast.success("Ticket atualizado.");
      setReplyMsg(""); setNewStatus(""); setAdminNote(""); setSaving(false);
      refetch(); refetchTicket();
    },
    onError: e => { toast.error(e.message); setSaving(false); },
  });

  const ticket   = ticketData?.ticket;
  const messages = ticketData?.messages ?? [];

  const handleSave = () => {
    if (!selectedId) return;
    setSaving(true);
    updateMutation.mutate({
      ticketId: selectedId,
      status:    newStatus || undefined,
      message:   replyMsg.trim() || undefined,
      adminNote: adminNote || undefined,
    });
  };

  const openCount    = Number(stats?.open_count ?? 0);
  const urgentCount  = Number(stats?.urgent_open ?? 0);

  return (
    <div className="space-y-4">
      {/* Stats strip */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
        {[
          { label: "Abertos", value: openCount, icon: MessageSquare, color: "text-red-400", alert: openCount > 0 },
          { label: "Em andamento", value: stats?.in_progress ?? 0, icon: Clock, color: "text-blue-400" },
          { label: "Aguardando usuário", value: stats?.waiting_user ?? 0, icon: Clock, color: "text-amber-400" },
          { label: "Urgentes abertos", value: urgentCount, icon: AlertTriangle, color: "text-red-300", alert: urgentCount > 0 },
        ].map(s => (
          <div key={s.label} className={`rounded-xl border p-3 ${s.alert ? "border-red-500/30 bg-red-500/5" : "border-border/50 bg-card"}`}>
            <div className="flex items-center justify-between mb-1">
              <span className="text-xs text-muted-foreground">{s.label}</span>
              <s.icon className={`h-3.5 w-3.5 ${s.color}`} />
            </div>
            <p className={`text-xl font-bold font-serif ${s.color}`}>{s.value}</p>
          </div>
        ))}
      </div>

      <div className="flex flex-wrap gap-2 items-center">
        <Select value={statusFilter} onValueChange={v => setStatusFilter(v)}>
          <SelectTrigger className="h-8 w-44 text-xs"><SelectValue /></SelectTrigger>
          <SelectContent>
            {[["all","Todos"],["open","Abertos"],["in_progress","Em andamento"],
              ["waiting_user","Aguardando"],["resolved","Resolvidos"],["closed","Fechados"]].map(([v,l]) => (
              <SelectItem key={v} value={v}>{l}</SelectItem>
            ))}
          </SelectContent>
        </Select>
        <div className="relative flex-1 min-w-40">
          <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
          <Input value={search} onChange={e => setSearch(e.target.value)}
            placeholder="Buscar usuário, assunto..." className="h-8 text-xs pl-8" />
        </div>
        <Button size="sm" variant="ghost" className="h-8" onClick={() => refetch()}>
          <RefreshCw className="h-3.5 w-3.5 mr-1.5" /> Atualizar
        </Button>
      </div>

      {isLoading ? (
        <div className="flex justify-center py-10"><Loader2 className="h-5 w-5 animate-spin text-primary" /></div>
      ) : (
        <div className="overflow-x-auto rounded-xl border border-border/50">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border/50 bg-muted/20">
                {["#", "Usuário", "Assunto", "Categoria", "Prioridade", "Status", "Msgs", "Criado"].map(h => (
                  <th key={h} className="px-3 py-2 text-left text-xs text-muted-foreground font-medium">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {(list?.rows ?? []).map((t: any) => (
                <tr
                  key={t.id}
                  className="border-b border-border/30 hover:bg-muted/10 cursor-pointer"
                  onClick={() => { setSelectedId(t.id); setNewStatus(t.status); setAdminNote(t.adminNote ?? ""); }}
                >
                  <td className="px-3 py-2 text-xs font-mono text-muted-foreground">#{t.id}</td>
                  <td className="px-3 py-2">
                    <p className="text-xs font-medium truncate max-w-28">{t.userName ?? "—"}</p>
                    <p className="text-[10px] text-muted-foreground truncate max-w-28">{t.userEmail ?? ""}</p>
                  </td>
                  <td className="px-3 py-2 text-xs max-w-48 truncate">{t.subject}</td>
                  <td className="px-3 py-2 text-xs text-muted-foreground">{t.category}</td>
                  <td className="px-3 py-2">
                    <Badge className={`text-[10px] border ${PRIORITY_STYLE[t.priority] ?? ""}`}>
                      {PRIORITY_PT[t.priority] ?? t.priority}
                    </Badge>
                  </td>
                  <td className="px-3 py-2">
                    <Badge className={`text-[10px] border ${STATUS_STYLE[t.status] ?? ""}`}>
                      {STATUS_PT[t.status] ?? t.status}
                    </Badge>
                  </td>
                  <td className="px-3 py-2 text-xs text-muted-foreground">{t.messageCount}</td>
                  <td className="px-3 py-2 text-xs text-muted-foreground">{dt(t.createdAt)}</td>
                </tr>
              ))}
            </tbody>
          </table>
          {(list?.rows ?? []).length === 0 && (
            <div className="text-center py-12 text-sm text-muted-foreground">Nenhum ticket encontrado.</div>
          )}
        </div>
      )}

      {/* Ticket detail dialog */}
      <Dialog open={!!selectedId} onOpenChange={v => { if (!v) setSelectedId(null); }}>
        <DialogContent className="max-w-2xl max-h-[85vh] flex flex-col overflow-hidden">
          <DialogHeader>
            <DialogTitle className="font-serif text-base">
              Ticket #{selectedId} — {ticket?.subject}
            </DialogTitle>
          </DialogHeader>

          {ticket && (
            <div className="flex flex-col gap-4 overflow-hidden flex-1">
              {/* Meta */}
              <div className="flex flex-wrap gap-2 text-xs">
                <Badge className={`border ${STATUS_STYLE[ticket.status] ?? ""}`}>{STATUS_PT[ticket.status]}</Badge>
                <Badge className={`border ${PRIORITY_STYLE[ticket.priority] ?? ""}`}>{PRIORITY_PT[ticket.priority]}</Badge>
                <span className="text-muted-foreground">Categoria: {ticket.category}</span>
                <span className="text-muted-foreground">Criado: {dt(ticket.createdAt)}</span>
              </div>

              {/* Messages */}
              <div className="flex-1 overflow-y-auto space-y-3 max-h-64 pr-1">
                {messages.map(m => (
                  <div key={m.id} className={`rounded-xl p-3 ${m.authorType === "admin"
                    ? "bg-primary/10 border border-primary/20 ml-6"
                    : "bg-muted/30 border border-border/40 mr-6"
                  }`}>
                    <div className="flex items-center gap-1.5 mb-1.5">
                      {m.authorType === "admin"
                        ? <Shield className="h-3 w-3 text-primary" />
                        : <User className="h-3 w-3 text-muted-foreground" />}
                      <span className={`text-xs font-medium ${m.authorType === "admin" ? "text-primary" : "text-foreground"}`}>
                        {m.authorType === "admin" ? "Equipe VetRank" : m.authorName}
                      </span>
                      <span className="text-[10px] text-muted-foreground ml-auto">{dt(m.createdAt)}</span>
                    </div>
                    <p className="text-sm text-muted-foreground leading-relaxed whitespace-pre-wrap">{m.message}</p>
                  </div>
                ))}
              </div>

              {/* Admin actions */}
              <div className="space-y-3 border-t border-border/40 pt-3">
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <p className="text-xs text-muted-foreground mb-1">Atualizar status</p>
                    <Select value={newStatus} onValueChange={setNewStatus}>
                      <SelectTrigger className="h-8 text-xs"><SelectValue placeholder="Manter atual" /></SelectTrigger>
                      <SelectContent>
                        {[["open","Aberto"],["in_progress","Em andamento"],["waiting_user","Aguardando usuário"],
                          ["resolved","Resolvido"],["closed","Fechado"]].map(([v,l]) => (
                          <SelectItem key={v} value={v}>{l}</SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                  <div>
                    <p className="text-xs text-muted-foreground mb-1">Nota interna (não enviada ao usuário)</p>
                    <Input value={adminNote} onChange={e => setAdminNote(e.target.value)}
                      placeholder="Anotação interna..." className="h-8 text-xs" />
                  </div>
                </div>
                <div>
                  <p className="text-xs text-muted-foreground mb-1">Resposta ao usuário (enviada por e-mail)</p>
                  <Textarea
                    value={replyMsg}
                    onChange={e => setReplyMsg(e.target.value)}
                    placeholder="Digite a resposta que será enviada ao usuário por e-mail e registrada no ticket..."
                    rows={3}
                    className="resize-none text-sm"
                  />
                </div>
                <Button
                  className="w-full gap-2"
                  disabled={saving}
                  onClick={handleSave}
                >
                  {saving
                    ? <><Loader2 className="h-4 w-4 animate-spin" /> Salvando...</>
                    : <><Send className="h-4 w-4" /> Salvar e Notificar</>}
                </Button>
              </div>
            </div>
          )}
        </DialogContent>
      </Dialog>
    </div>
  );
}
