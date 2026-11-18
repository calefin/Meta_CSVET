import { useAuth } from "@/_core/hooks/useAuth";
import BlogAdmin from "./BlogAdmin";
import { trpc } from "@/lib/trpc";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import {
  Users, FileText, BookOpen, Share2, TrendingUp, Mail,
  ShieldCheck, Trash2, ChevronLeft, ChevronRight, Send,
  BarChart3, UserCheck, AlertCircle, CheckSquare, MessageSquare, Loader2, Building2
} from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";
import { useLocation } from "wouter";

function StatCard({ icon: Icon, label, value, sub }: {
  icon: React.ElementType;
  label: string;
  value: number | string;
  sub?: string;
}) {
  return (
    <Card>
      <CardContent className="p-5">
        <div className="flex items-start justify-between">
          <div>
            <p className="text-sm text-muted-foreground">{label}</p>
            <p className="text-3xl font-bold mt-1">{value}</p>
            {sub && <p className="text-xs text-muted-foreground mt-1">{sub}</p>}
          </div>
          <div className="h-10 w-10 rounded-lg bg-primary/10 flex items-center justify-center shrink-0">
            <Icon className="h-5 w-5 text-primary" />
          </div>
        </div>
      </CardContent>
    </Card>
  );
}

function OverviewTab() {
  const statsQuery = trpc.admin.getStats.useQuery();
  const stats = statsQuery.data;
  const configQuery = trpc.admin.getSystemConfig.useQuery();
  const config = configQuery.data;

  if (statsQuery.isLoading) {
    return (
      <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
        {Array.from({ length: 7 }).map((_, i) => (
          <Card key={i}><CardContent className="p-5 h-24 animate-pulse bg-muted/40 rounded-lg" /></Card>
        ))}
      </div>
    );
  }

  if (!stats) return null;

  return (
    <div className="space-y-6">
      <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
        <StatCard icon={Users}     label="Total de usuários"        value={stats.totalUsers}     sub={`+${stats.newUsersThisMonth} este mês`} />
        <StatCard icon={UserCheck} label="Novos este mês"           value={stats.newUsersThisMonth} />
        <StatCard icon={BookOpen}  label="Questões criadas"         value={stats.totalQuestions} />
        <StatCard icon={FileText}  label="Avaliações criadas"       value={stats.totalExams} />
        <StatCard icon={Users}     label="Alunos cadastrados"       value={stats.totalStudents} />
        <StatCard icon={Share2}    label="Links de compartilhamento" value={stats.totalShares} />
        <StatCard icon={BarChart3} label="Respostas de quiz"        value={stats.totalResponses} />
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Informações do sistema</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div className="flex justify-between py-2 border-b">
              <span className="text-muted-foreground">reCAPTCHA</span>
              <Badge variant={config?.recaptchaEnabled ? "default" : "secondary"}>
                {!config ? "…" : config.recaptchaEnabled ? "Ativo" : "Inativo (faltam variáveis no .env)"}
              </Badge>
            </div>
            <div className="flex justify-between py-2 border-b">
              <span className="text-muted-foreground">E-mail SMTP</span>
              <Badge variant={config?.smtpConfigured ? "default" : "secondary"}>
                {!config ? "…" : config.smtpConfigured ? "Configurado" : "Não configurado"}
              </Badge>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

function UsersTab({ currentUserId }: { currentUserId: number }) {
  const [page, setPage] = useState(0);
  const [deleteTarget, setDeleteTarget] = useState<{ id: number; name: string | null } | null>(null);
  const PAGE_SIZE = 25;

  const usersQuery = trpc.admin.getUsers.useQuery({ limit: PAGE_SIZE, offset: page * PAGE_SIZE });
  const updateRole = trpc.admin.updateUserRole.useMutation({
    onSuccess: () => { toast.success("Perfil atualizado."); usersQuery.refetch(); },
    onError: (e) => toast.error(e.message),
  });
  const updatePlan = trpc.admin.updateUserPlan.useMutation({
    onSuccess: () => { toast.success("Plano atualizado."); usersQuery.refetch(); },
    onError: (e) => toast.error(e.message),
  });
  const deleteUser = trpc.admin.deleteUser.useMutation({
    onSuccess: () => { toast.success("Usuário excluído."); setDeleteTarget(null); usersQuery.refetch(); },
    onError: (e) => toast.error(e.message),
  });

  const { users = [], total = 0 } = usersQuery.data ?? {};
  const totalPages = Math.ceil(total / PAGE_SIZE);

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <p className="text-sm text-muted-foreground">{total} usuário(s) cadastrado(s)</p>
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          <span>Página {page + 1} / {Math.max(1, totalPages)}</span>
          <Button variant="outline" size="icon" disabled={page === 0} onClick={() => setPage(p => p - 1)}>
            <ChevronLeft className="h-4 w-4" />
          </Button>
          <Button variant="outline" size="icon" disabled={page + 1 >= totalPages} onClick={() => setPage(p => p + 1)}>
            <ChevronRight className="h-4 w-4" />
          </Button>
        </div>
      </div>

      <div className="rounded-md border overflow-hidden">
        <Table>
          <TableHeader>
            <TableRow className="bg-muted/40">
              <TableHead>Nome / E-mail</TableHead>
              <TableHead>Login</TableHead>
              <TableHead>Perfil</TableHead>
              <TableHead>Plano</TableHead>
              <TableHead>Cadastro</TableHead>
              <TableHead>Últ. acesso</TableHead>
              <TableHead className="text-right">Ações</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {usersQuery.isLoading && (
              <TableRow><TableCell colSpan={7} className="text-center py-8 text-muted-foreground">Carregando...</TableCell></TableRow>
            )}
            {!usersQuery.isLoading && users.length === 0 && (
              <TableRow><TableCell colSpan={7} className="text-center py-8 text-muted-foreground">Nenhum usuário encontrado.</TableCell></TableRow>
            )}
            {users.map((u: any) => (
              <TableRow key={u.id}>
                <TableCell>
                  <div>
                    <p className="font-medium text-sm">{u.name ?? "—"}</p>
                    <p className="text-xs text-muted-foreground">{u.email ?? "—"}</p>
                  </div>
                </TableCell>
                <TableCell>
                  <Badge variant="outline" className="text-xs">{u.loginMethod}</Badge>
                </TableCell>
                <TableCell>
                  <Select
                    value={u.role}
                    disabled={u.id === currentUserId || updateRole.isPending}
                    onValueChange={(role: "user" | "admin") => updateRole.mutate({ userId: u.id, role })}
                  >
                    <SelectTrigger className="h-7 w-24 text-xs">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="user">Usuário</SelectItem>
                      <SelectItem value="admin">Admin</SelectItem>
                    </SelectContent>
                  </Select>
                </TableCell>
                <TableCell>
                  <Select
                    value={u.plan ?? "free"}
                    disabled={updatePlan.isPending}
                    onValueChange={(plan: "free" | "basic" | "pro" | "enterprise") => updatePlan.mutate({ userId: u.id, plan })}
                  >
                    <SelectTrigger className="h-7 w-28 text-xs">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="free">Gratuito</SelectItem>
                      <SelectItem value="basic">Básico</SelectItem>
                      <SelectItem value="pro">Pro</SelectItem>
                      <SelectItem value="enterprise">Enterprise</SelectItem>
                    </SelectContent>
                  </Select>
                </TableCell>
                <TableCell className="text-xs text-muted-foreground">
                  {u.createdAt ? new Date(u.createdAt).toLocaleDateString("pt-BR") : "—"}
                </TableCell>
                <TableCell className="text-xs text-muted-foreground">
                  {u.lastSignedIn ? new Date(u.lastSignedIn).toLocaleDateString("pt-BR") : "—"}
                </TableCell>
                <TableCell className="text-right">
                  <Button
                    variant="ghost"
                    size="icon"
                    className="h-7 w-7 text-destructive hover:text-destructive"
                    disabled={u.id === currentUserId}
                    onClick={() => setDeleteTarget({ id: u.id, name: u.name })}
                  >
                    <Trash2 className="h-3.5 w-3.5" />
                  </Button>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>

      <Dialog open={!!deleteTarget} onOpenChange={open => !open && setDeleteTarget(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Excluir usuário</DialogTitle>
            <DialogDescription>
              Tem certeza que deseja excluir <strong>{deleteTarget?.name ?? "este usuário"}</strong>? Esta ação não pode ser desfeita.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDeleteTarget(null)}>Cancelar</Button>
            <Button
              variant="destructive"
              onClick={() => deleteTarget && deleteUser.mutate({ userId: deleteTarget.id })}
              disabled={deleteUser.isPending}
            >
              {deleteUser.isPending ? "Excluindo..." : "Excluir"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}

function EmailMarketingTab() {
  const [subject, setSubject] = useState("");
  const [html, setHtml] = useState("");
  const [text, setText] = useState("");
  const [targetMode, setTargetMode] = useState<"all" | "custom">("all");
  const [customEmails, setCustomEmails] = useState("");
  const [result, setResult] = useState<{ sent: number; failed: number } | null>(null);

  const sendEmail = trpc.admin.sendMarketingEmail.useMutation({
    onSuccess: (data) => {
      setResult(data);
      toast.success(`E-mail enviado para ${data.sent} destinatário(s).`);
    },
    onError: (e) => toast.error(e.message),
  });

  function handleSend() {
    if (!subject.trim() || !html.trim()) {
      toast.error("Preencha o assunto e o corpo do e-mail.");
      return;
    }

    const targetEmails = targetMode === "custom"
      ? customEmails.split(/[\n,;]/).map(e => e.trim()).filter(Boolean)
      : undefined;

    setResult(null);
    sendEmail.mutate({ subject, html, text: text || undefined, targetEmails });
  }

  return (
    <div className="space-y-6 max-w-3xl">
      <Card>
        <CardHeader>
          <CardTitle className="text-base">Compor e-mail</CardTitle>
          <CardDescription>
            Use <code className="bg-muted px-1 rounded text-xs">{"{{nome}}"}</code> no corpo para personalizar com o nome do destinatário.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            <Label>Destinatários</Label>
            <Select value={targetMode} onValueChange={(v: "all" | "custom") => setTargetMode(v)}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todos os usuários cadastrados</SelectItem>
                <SelectItem value="custom">E-mails específicos</SelectItem>
              </SelectContent>
            </Select>
            {targetMode === "custom" && (
              <Textarea
                placeholder="Insira e-mails separados por vírgula, ponto e vírgula ou nova linha"
                value={customEmails}
                onChange={e => setCustomEmails(e.target.value)}
                rows={3}
              />
            )}
          </div>

          <div className="space-y-2">
            <Label>Assunto</Label>
            <Input
              placeholder="Assunto do e-mail"
              value={subject}
              onChange={e => setSubject(e.target.value)}
            />
          </div>

          <div className="space-y-2">
            <Label>Corpo (HTML)</Label>
            <Textarea
              placeholder="<p>Olá {{nome}}, ...</p>"
              value={html}
              onChange={e => setHtml(e.target.value)}
              rows={10}
              className="font-mono text-xs"
            />
          </div>

          <div className="space-y-2">
            <Label>Texto simples (opcional)</Label>
            <Textarea
              placeholder="Versão texto puro do e-mail (fallback)"
              value={text}
              onChange={e => setText(e.target.value)}
              rows={3}
            />
          </div>
        </CardContent>
      </Card>

      {result && (
        <Card className="border-green-200 bg-green-50 dark:bg-green-950/20">
          <CardContent className="p-4 flex items-center gap-3">
            <Send className="h-5 w-5 text-green-600" />
            <div className="text-sm">
              <span className="font-medium text-green-700">{result.sent} enviado(s)</span>
              {result.failed > 0 && (
                <span className="text-destructive ml-3">{result.failed} falhou</span>
              )}
            </div>
          </CardContent>
        </Card>
      )}

      <Button onClick={handleSend} disabled={sendEmail.isPending} className="gap-2">
        <Send className="h-4 w-4" />
        {sendEmail.isPending ? "Enviando..." : "Enviar e-mail"}
      </Button>
    </div>
  );
}

function FinancialTab() {
  const { data, isLoading, refetch } = trpc.billing.adminStats.useQuery();
  const cancelSub = trpc.billing.adminCancelSubscription.useMutation({
    onSuccess: () => { toast.success("Assinatura cancelada."); refetch(); },
    onError:   (e) => toast.error(e.message),
  });

  if (isLoading) return <div className="flex items-center justify-center h-32"><Loader2 className="animate-spin w-6 h-6 text-muted-foreground" /></div>;

  const d = data as any;
  const totalUsers = Object.values(d?.usersByPlan ?? {}).reduce((a: number, b) => a + Number(b), 0);

  return (
    <div className="space-y-6 max-w-4xl">
      {/* MRR / ARR / totals */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        {[
          { label: "MRR",               value: `R$ ${(d?.mrr ?? 0).toFixed(2)}`,  sub: "receita mensal recorrente" },
          { label: "ARR",               value: `R$ ${(d?.arr ?? 0).toFixed(2)}`,  sub: "projeção anual" },
          { label: "Assinaturas ativas",value: d?.activeSubscriptions ?? 0,         sub: "pagantes agora" },
          { label: "Usuários totais",   value: totalUsers,                          sub: "incluindo free" },
        ].map(({ label, value, sub }) => (
          <Card key={label}>
            <CardContent className="pt-4">
              <p className="text-xs text-muted-foreground uppercase tracking-wide">{label}</p>
              <p className="text-2xl font-bold mt-1">{value}</p>
              <p className="text-xs text-muted-foreground mt-0.5">{sub}</p>
            </CardContent>
          </Card>
        ))}
      </div>

      {/* Subscribers by plan */}
      <Card>
        <CardHeader><CardTitle className="text-sm">Assinantes por plano (ativos)</CardTitle></CardHeader>
        <CardContent>
          <div className="space-y-2">
            {(["enterprise", "pro", "basic"] as const).map((k) => {
              const cnt = d?.byPlan?.[k] ?? 0;
              const max = Math.max(...Object.values(d?.byPlan ?? { basic: 0, pro: 0, enterprise: 0 }).map(Number), 1);
              return (
                <div key={k} className="flex items-center gap-3 text-sm">
                  <span className="w-20 capitalize text-muted-foreground">{k}</span>
                  <div className="flex-1 bg-muted rounded-full h-2 overflow-hidden">
                    <div className="h-2 bg-primary rounded-full" style={{ width: `${(cnt / max) * 100}%` }} />
                  </div>
                  <span className="w-8 text-right font-medium">{cnt}</span>
                </div>
              );
            })}
          </div>
        </CardContent>
      </Card>

      {/* Users distribution */}
      <Card>
        <CardHeader><CardTitle className="text-sm">Distribuição de planos — todos os usuários</CardTitle></CardHeader>
        <CardContent>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
            {(["free", "basic", "pro", "enterprise"] as const).map((k) => (
              <div key={k} className="text-center p-3 border rounded-lg">
                <p className="text-lg font-bold">{d?.usersByPlan?.[k] ?? 0}</p>
                <p className="text-xs text-muted-foreground capitalize mt-0.5">{k === "free" ? "Gratuito" : k}</p>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>

      {/* Recent subscriptions with user info + cancel */}
      {d?.recentSubs?.length > 0 && (
        <Card>
          <CardHeader><CardTitle className="text-sm">Assinaturas recentes</CardTitle></CardHeader>
          <CardContent>
            <div className="space-y-0 text-sm">
              {d.recentSubs.map((s: any) => (
                <div key={s.id} className="grid grid-cols-[1fr_auto_auto_auto_auto] items-center gap-3 py-2 border-b last:border-0">
                  <div className="min-w-0">
                    <p className="font-medium truncate">{s.userName}</p>
                    <p className="text-xs text-muted-foreground truncate">{s.userEmail}</p>
                  </div>
                  <span className="capitalize text-xs">{s.planKey}</span>
                  <Badge variant={s.status === "authorized" ? "default" : "secondary"} className="text-xs">
                    {s.status}
                  </Badge>
                  <span className="text-muted-foreground text-xs whitespace-nowrap">
                    {new Date(s.createdAt).toLocaleDateString("pt-BR")}
                  </span>
                  {s.status === "authorized" && (
                    <Button
                      size="sm" variant="ghost"
                      className="text-destructive hover:text-destructive text-xs h-7 px-2"
                      disabled={cancelSub.isPending}
                      onClick={() => cancelSub.mutate({ subscriptionId: s.id })}
                    >
                      Cancelar
                    </Button>
                  )}
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  );
}

export default function Admin() {
  const { user, loading } = useAuth();
  const [, navigate] = useLocation();

  if (loading) {
    return <div className="flex items-center justify-center h-64 text-muted-foreground text-sm">Carregando...</div>;
  }

  if (!user || user.role !== "admin") {
    return (
      <div className="flex flex-col items-center justify-center h-64 gap-3 text-center">
        <ShieldCheck className="h-10 w-10 text-muted-foreground opacity-40" />
        <p className="font-medium">Acesso restrito</p>
        <p className="text-sm text-muted-foreground">Esta área é exclusiva para administradores.</p>
        <Button variant="outline" onClick={() => navigate("/")}>Voltar ao início</Button>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <div className="h-9 w-9 rounded-lg bg-primary/10 flex items-center justify-center">
          <ShieldCheck className="h-5 w-5 text-primary" />
        </div>
        <div>
          <h1 className="text-xl font-semibold">Painel Administrativo</h1>
          <p className="text-sm text-muted-foreground">Gerenciamento do sistema Prof-Aid</p>
        </div>
      </div>

      <Tabs defaultValue="overview">
        <TabsList className="grid w-full grid-cols-4 md:grid-cols-7 h-auto max-w-3xl gap-1 p-1">
          <TabsTrigger value="overview" className="gap-1.5 text-xs">
            <BarChart3 className="h-3.5 w-3.5" />
            Visão Geral
          </TabsTrigger>
          <TabsTrigger value="users" className="gap-1.5 text-xs">
            <Users className="h-3.5 w-3.5" />
            Usuários
          </TabsTrigger>
          <TabsTrigger value="email" className="gap-1.5 text-xs">
            <Mail className="h-3.5 w-3.5" />
            E-mail
          </TabsTrigger>
          <TabsTrigger value="financial" className="gap-1.5 text-xs">
            <TrendingUp className="h-3.5 w-3.5" />
            Financeiro
          </TabsTrigger>
          <TabsTrigger value="tickets" className="gap-1.5 text-xs">
            <CheckSquare className="h-3.5 w-3.5" />
            Tickets
          </TabsTrigger>
          <TabsTrigger value="quotes" className="gap-1.5 text-xs">
            <Building2 className="h-3.5 w-3.5" />
            Orçamentos
          </TabsTrigger>
          <TabsTrigger value="blog" className="gap-1.5 text-xs">
            <BookOpen className="h-3.5 w-3.5" />
            Blog
          </TabsTrigger>
        </TabsList>

        <TabsContent value="overview" className="mt-6">
          <OverviewTab />
        </TabsContent>

        <TabsContent value="users" className="mt-6">
          <UsersTab currentUserId={user.id} />
        </TabsContent>

        <TabsContent value="email" className="mt-6">
          <EmailMarketingTab />
        </TabsContent>

        <TabsContent value="financial" className="mt-6">
          <FinancialTab />
        </TabsContent>

        <TabsContent value="tickets" className="mt-6">
          <TicketsTab />
        </TabsContent>

        <TabsContent value="quotes" className="mt-6">
          <QuotesTab />
        </TabsContent>

        <TabsContent value="blog" className="mt-6">
          <BlogAdmin />
        </TabsContent>
      </Tabs>
    </div>
  );
}

function TicketsTab() {
  const { data: tickets = [], isLoading, refetch } = trpc.tickets.listAllTickets.useQuery();
  const respondMutation = trpc.tickets.respond.useMutation();
  const updateStatusMutation = trpc.tickets.updateStatus.useMutation();

  const [selectedTicket, setSelectedTicket] = useState<any | null>(null);
  const [response, setResponse] = useState("");
  const [status, setStatus] = useState<"open" | "in_progress" | "resolved" | "closed">("resolved");
  const [submitting, setSubmitting] = useState(false);

  const STATUS_LABELS: Record<string, string> = {
    open: "Aberto",
    in_progress: "Em Andamento",
    resolved: "Resolvido",
    closed: "Fechado",
  };

  const STATUS_COLORS: Record<string, string> = {
    open: "bg-blue-50 text-blue-700 border-blue-200",
    in_progress: "bg-amber-50 text-amber-700 border-amber-200",
    resolved: "bg-green-50 text-green-700 border-green-200",
    closed: "bg-slate-50 text-slate-700 border-slate-200",
  };

  const PRIORITY_LABELS: Record<string, string> = {
    low: "Baixa",
    medium: "Média",
    high: "Alta",
  };

  const PRIORITY_COLORS: Record<string, string> = {
    low: "bg-slate-100 text-slate-800",
    medium: "bg-blue-100 text-blue-800",
    high: "bg-red-100 text-red-800",
  };

  const handleOpenRespond = (ticket: any) => {
    setSelectedTicket(ticket);
    setResponse(ticket.adminResponse || "");
    setStatus(ticket.status === "open" ? "resolved" : ticket.status);
  };

  const handleRespond = async () => {
    if (!selectedTicket) return;
    if (!response.trim()) {
      toast.error("Digite uma resposta.");
      return;
    }
    setSubmitting(true);
    try {
      await respondMutation.mutateAsync({
        ticketId: selectedTicket.id,
        response,
        status,
      });
      toast.success("Resposta enviada com sucesso!");
      setSelectedTicket(null);
      refetch();
    } catch (e: any) {
      toast.error(e?.message || "Erro ao responder chamado.");
    } finally {
      setSubmitting(false);
    }
  };

  const handleStatusChange = async (ticketId: number, newStatus: any) => {
    try {
      await updateStatusMutation.mutateAsync({
        ticketId,
        status: newStatus,
      });
      toast.success("Status atualizado!");
      refetch();
    } catch (e: any) {
      toast.error(e?.message || "Erro ao atualizar status.");
    }
  };

  if (isLoading) {
    return (
      <div className="flex justify-center py-10">
        <Loader2 className="w-8 h-8 animate-spin text-blue-600" />
      </div>
    );
  }

  return (
    <Card className="border shadow-sm">
      <CardHeader>
        <CardTitle className="text-base flex items-center gap-2">
          <CheckSquare className="h-5 w-5 text-primary" />
          Gerenciamento de Tickets de Suporte
        </CardTitle>
        <CardDescription>
          Visualize e responda a dúvidas, erros ou sugestões registradas pelos usuários do sistema.
        </CardDescription>
      </CardHeader>
      <CardContent>
        {tickets.length === 0 ? (
          <p className="text-center text-sm text-muted-foreground py-8">Nenhum chamado de suporte registrado.</p>
        ) : (
          <div className="border rounded-lg overflow-x-auto">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="w-[80px]">ID</TableHead>
                  <TableHead className="w-[180px]">Usuário</TableHead>
                  <TableHead>Título / Assunto</TableHead>
                  <TableHead className="w-[100px]">Prioridade</TableHead>
                  <TableHead className="w-[120px]">Status</TableHead>
                  <TableHead className="w-[120px]">Criado em</TableHead>
                  <TableHead className="text-right w-[150px]">Ações</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {tickets.map((t: any) => (
                  <TableRow key={t.id}>
                    <TableCell className="font-mono text-xs">#{t.id}</TableCell>
                    <TableCell>
                      <div className="text-xs">
                        <p className="font-semibold text-slate-800">{t.user?.name || "Sem nome"}</p>
                        <p className="text-muted-foreground">{t.user?.email || "Sem e-mail"}</p>
                      </div>
                    </TableCell>
                    <TableCell>
                      <p className="font-medium text-xs text-slate-900">{t.title}</p>
                      <p className="text-[10px] text-muted-foreground line-clamp-1 mt-0.5" title={t.description}>
                        {t.description}
                      </p>
                    </TableCell>
                    <TableCell>
                      <Badge variant="secondary" className={`text-[10px] ${PRIORITY_COLORS[t.priority]}`}>
                        {PRIORITY_LABELS[t.priority]}
                      </Badge>
                    </TableCell>
                    <TableCell>
                      <Select
                        value={t.status}
                        onValueChange={(val: any) => handleStatusChange(t.id, val)}
                      >
                        <SelectTrigger className="h-7 text-[10px] px-2">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent className="text-xs">
                          <SelectItem value="open">Aberto</SelectItem>
                          <SelectItem value="in_progress">Andamento</SelectItem>
                          <SelectItem value="resolved">Resolvido</SelectItem>
                          <SelectItem value="closed">Fechado</SelectItem>
                        </SelectContent>
                      </Select>
                    </TableCell>
                    <TableCell className="text-xs text-muted-foreground">
                      {new Date(t.createdAt).toLocaleDateString("pt-BR")}
                    </TableCell>
                    <TableCell className="text-right">
                      <Button size="sm" onClick={() => handleOpenRespond(t)} className="h-7 text-xs border" variant="ghost">
                        <MessageSquare className="w-3.5 h-3.5 mr-1 text-slate-600" />
                        {t.adminResponse ? "Ver / Editar" : "Responder"}
                      </Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        )}
      </CardContent>

      {/* Admin Respond Dialog */}
      <Dialog open={selectedTicket !== null} onOpenChange={(open) => !open && setSelectedTicket(null)}>
        <DialogContent className="max-w-lg">
          {selectedTicket && (
            <>
              <DialogHeader>
                <DialogTitle>Responder Chamado #{selectedTicket.id}</DialogTitle>
              </DialogHeader>
              <div className="space-y-4 py-2 text-sm">
                <div>
                  <h4 className="font-semibold text-slate-800 text-xs uppercase tracking-wider mb-1">Assunto</h4>
                  <p className="font-medium text-slate-900">{selectedTicket.title}</p>
                </div>
                <div>
                  <h4 className="font-semibold text-slate-800 text-xs uppercase tracking-wider mb-1">Mensagem do Usuário</h4>
                  <p className="bg-slate-50 border p-3 rounded text-slate-700 whitespace-pre-wrap leading-relaxed text-xs">
                    {selectedTicket.description}
                  </p>
                </div>
                <div className="space-y-1.5">
                  <Label htmlFor="admin-resp">Resposta do Suporte *</Label>
                  <Textarea
                    id="admin-resp"
                    placeholder="Digite a solução do problema ou orientações para o usuário..."
                    rows={5}
                    value={response}
                    onChange={(e) => setResponse(e.target.value)}
                  />
                </div>
                <div className="space-y-1.5">
                  <Label>Atualizar Status</Label>
                  <Select value={status} onValueChange={(v: any) => setStatus(v)}>
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="in_progress">Manter Em Andamento</SelectItem>
                      <SelectItem value="resolved">Marcar como Resolvido</SelectItem>
                      <SelectItem value="closed">Marcar como Fechado</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>
              <DialogFooter>
                <Button variant="outline" onClick={() => setSelectedTicket(null)} disabled={submitting}>
                  Cancelar
                </Button>
                <Button onClick={handleRespond} disabled={submitting} className="gap-1.5">
                  {submitting && <Loader2 className="w-4 h-4 animate-spin" />}
                  Salvar Resposta
                </Button>
              </DialogFooter>
            </>
          )}
        </DialogContent>
      </Dialog>
    </Card>
  );
}

function QuotesTab() {
  const { data: quotes = [], isLoading, refetch } = trpc.quotes.listAll.useQuery();
  const updateMutation = trpc.quotes.update.useMutation();

  const [selectedQuote, setSelectedQuote] = useState<any | null>(null);
  const [adminNotes, setAdminNotes] = useState("");
  const [status, setStatus] = useState<"pending" | "contacted" | "closed" | "declined">("pending");
  const [submitting, setSubmitting] = useState(false);

  const STATUS_LABELS: Record<string, string> = {
    pending: "Pendente",
    contacted: "Contatado",
    closed: "Fechado",
    declined: "Recusado",
  };

  const STATUS_COLORS: Record<string, string> = {
    pending: "bg-amber-50 text-amber-700 border-amber-200",
    contacted: "bg-blue-50 text-blue-700 border-blue-200",
    closed: "bg-green-50 text-green-700 border-green-200",
    declined: "bg-rose-50 text-rose-700 border-rose-200",
  };

  const handleOpenDetails = (quote: any) => {
    setSelectedQuote(quote);
    setAdminNotes(quote.adminNotes || "");
    setStatus(quote.status);
  };

  const handleSaveNotes = async () => {
    if (!selectedQuote) return;
    setSubmitting(true);
    try {
      await updateMutation.mutateAsync({
        quoteId: selectedQuote.id,
        status,
        adminNotes,
      });
      toast.success("Orçamento atualizado com sucesso!");
      setSelectedQuote(null);
      refetch();
    } catch (e: any) {
      toast.error(e?.message || "Erro ao atualizar orçamento.");
    } finally {
      setSubmitting(false);
    }
  };

  const handleQuickStatusChange = async (quoteId: number, newStatus: any, notes: string) => {
    try {
      await updateMutation.mutateAsync({
        quoteId,
        status: newStatus,
        adminNotes: notes,
      });
      toast.success("Status atualizado!");
      refetch();
    } catch (e: any) {
      toast.error("Erro ao atualizar status.");
    }
  };

  return (
    <div className="space-y-6">
      <Card>
        <CardHeader className="pb-3">
          <CardTitle className="text-lg">Solicitações de Orçamento Corporativo</CardTitle>
          <CardDescription className="text-xs">
            Gerencie contatos comerciais de escolas e faculdades para bancos de questões e sistemas de provas.
          </CardDescription>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="flex justify-center py-8">
              <Loader2 className="w-6 h-6 animate-spin text-primary" />
            </div>
          ) : quotes.length === 0 ? (
            <div className="text-center py-12 text-sm text-muted-foreground border border-dashed rounded-lg bg-slate-50/50">
              Nenhuma solicitação de orçamento registrada.
            </div>
          ) : (
            <div className="border rounded-lg overflow-hidden bg-white">
              <Table>
                <TableHeader className="bg-slate-50">
                  <TableRow>
                    <TableHead className="text-xs">Solicitante</TableHead>
                    <TableHead className="text-xs">Empresa/Cargo</TableHead>
                    <TableHead className="text-xs">Tipo de Projeto</TableHead>
                    <TableHead className="text-xs">Volume/Prazo</TableHead>
                    <TableHead className="text-xs">Status</TableHead>
                    <TableHead className="text-xs text-right">Ações</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {quotes.map((q: any) => (
                    <TableRow key={q.id}>
                      <TableCell className="font-medium text-xs">
                        <div>{q.name}</div>
                        <div className="text-[10px] text-muted-foreground">{q.email} · {q.phone}</div>
                      </TableCell>
                      <TableCell className="text-xs">
                        <div>{q.company}</div>
                        <div className="text-[10px] text-muted-foreground">{q.role}</div>
                      </TableCell>
                      <TableCell className="text-xs font-semibold text-slate-700">
                        {q.projectType === "questions" ? "Banco de Questões" :
                         q.projectType === "exams" ? "Sistema de Provas" :
                         q.projectType === "grading" ? "Correção Óptica" : "Outro"}
                      </TableCell>
                      <TableCell className="text-xs">
                        <div className="text-[11px]">Volume: {q.estimatedVolume === "v1" ? "Pequeno" : q.estimatedVolume === "v2" ? "Médio" : "Grande"}</div>
                        <div className="text-[10px] text-muted-foreground">Prazo: {q.timeframe === "now" ? "Imediato" : q.timeframe === "month" ? "30 dias" : q.timeframe === "quarter" ? "90 dias" : "Futuro"}</div>
                      </TableCell>
                      <TableCell>
                        <Select
                          value={q.status}
                          onValueChange={(val: any) => handleQuickStatusChange(q.id, val, q.adminNotes || "")}
                        >
                          <SelectTrigger className={`h-7 w-28 text-[11px] font-semibold border ${STATUS_COLORS[q.status]}`}>
                            <SelectValue />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="pending">Pendente</SelectItem>
                            <SelectItem value="contacted">Contatado</SelectItem>
                            <SelectItem value="closed">Fechado</SelectItem>
                            <SelectItem value="declined">Recusado</SelectItem>
                          </SelectContent>
                        </Select>
                      </TableCell>
                      <TableCell className="text-right">
                        <Button size="sm" onClick={() => handleOpenDetails(q)} className="h-7 text-xs border" variant="ghost">
                          <MessageSquare className="w-3.5 h-3.5 mr-1 text-slate-600" />
                          Detalhes
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          )}
        </CardContent>
      </Card>

      <Dialog open={selectedQuote !== null} onOpenChange={(open) => !open && setSelectedQuote(null)}>
        <DialogContent className="max-w-lg">
          {selectedQuote && (
            <>
              <DialogHeader>
                <DialogTitle>Solicitação de Orçamento #{selectedQuote.id}</DialogTitle>
                <DialogDescription className="text-xs">
                  Criado em: {new Date(selectedQuote.createdAt).toLocaleDateString("pt-BR")} às {new Date(selectedQuote.createdAt).toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" })}
                </DialogDescription>
              </DialogHeader>

              <div className="space-y-4 py-2 text-sm">
                <div className="grid grid-cols-2 gap-4 bg-slate-50 p-3 rounded-lg border">
                  <div>
                    <span className="text-[10px] text-muted-foreground block font-bold uppercase">Solicitante</span>
                    <span className="font-semibold text-slate-800">{selectedQuote.name}</span>
                    <span className="text-xs text-slate-500 block">{selectedQuote.role}</span>
                  </div>
                  <div>
                    <span className="text-[10px] text-muted-foreground block font-bold uppercase">Empresa / Instituição</span>
                    <span className="font-semibold text-slate-800">{selectedQuote.company}</span>
                  </div>
                  <div className="pt-2">
                    <span className="text-[10px] text-muted-foreground block font-bold uppercase">E-mail</span>
                    <a href={`mailto:${selectedQuote.email}`} className="text-blue-600 hover:underline">{selectedQuote.email}</a>
                  </div>
                  <div className="pt-2">
                    <span className="text-[10px] text-muted-foreground block font-bold uppercase">Telefone</span>
                    <span className="text-slate-800 font-medium">{selectedQuote.phone}</span>
                  </div>
                </div>

                <div className="grid grid-cols-3 gap-2 text-center text-xs">
                  <div className="border p-2 rounded bg-white">
                    <span className="text-[9px] text-muted-foreground block font-bold">PROJETO</span>
                    <span className="font-bold text-slate-700">
                      {selectedQuote.projectType === "questions" ? "Banco Questões" :
                       selectedQuote.projectType === "exams" ? "Sist. Provas" :
                       selectedQuote.projectType === "grading" ? "Corr. Óptica" : "Outro"}
                    </span>
                  </div>
                  <div className="border p-2 rounded bg-white">
                    <span className="text-[9px] text-muted-foreground block font-bold">ESCALA</span>
                    <span className="font-bold text-slate-700">
                      {selectedQuote.estimatedVolume === "v1" ? "Pequena" : selectedQuote.estimatedVolume === "v2" ? "Mediana" : "Grande"}
                    </span>
                  </div>
                  <div className="border p-2 rounded bg-white">
                    <span className="text-[9px] text-muted-foreground block font-bold">PRAZO</span>
                    <span className="font-bold text-slate-700">
                      {selectedQuote.timeframe === "now" ? "Imediato" : selectedQuote.timeframe === "month" ? "30 dias" : selectedQuote.timeframe === "quarter" ? "90 dias" : "Futuro"}
                    </span>
                  </div>
                </div>

                {selectedQuote.features && (() => {
                  try {
                    const parsed = JSON.parse(selectedQuote.features);
                    if (Array.isArray(parsed) && parsed.length > 0) {
                      const featureLabels: Record<string, string> = {
                        lms: "Integração LMS",
                        ai: "Geração por IA",
                        latex: "Suporte LaTeX",
                        analytics: "Métricas/TRI",
                        mobile: "App Mobile",
                        lockdown: "Lockdown Browser",
                      };
                      return (
                        <div className="space-y-1 bg-slate-50/50 p-2.5 rounded border">
                          <span className="text-[10px] text-muted-foreground font-bold uppercase block">Recursos Adicionais Desejados:</span>
                          <div className="flex flex-wrap gap-1.5 mt-1">
                            {parsed.map((f: string) => (
                              <Badge key={f} variant="secondary" className="text-[10px] bg-slate-200/60 text-slate-700 hover:bg-slate-200">
                                {featureLabels[f] || f}
                              </Badge>
                            ))}
                          </div>
                        </div>
                      );
                    }
                  } catch {}
                  return null;
                })()}

                <div className="space-y-1">
                  <span className="text-[10px] text-muted-foreground font-bold uppercase block">Descrição das Necessidades:</span>
                  <p className="bg-slate-50/50 p-3 rounded border text-xs text-slate-700 whitespace-pre-wrap leading-relaxed max-h-40 overflow-y-auto">
                    {selectedQuote.description}
                  </p>
                </div>

                <div className="space-y-3 pt-2 border-t">
                  <div className="grid grid-cols-2 gap-4">
                    <div className="space-y-1.5">
                      <Label>Status Comercial</Label>
                      <Select value={status} onValueChange={(val: any) => setStatus(val)}>
                        <SelectTrigger className="h-9">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="pending">Pendente (Novo)</SelectItem>
                          <SelectItem value="contacted">Contatado (Em Negociação)</SelectItem>
                          <SelectItem value="closed">Fechado (Ganho)</SelectItem>
                          <SelectItem value="declined">Recusado (Perdido)</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                  </div>

                  <div className="space-y-1.5">
                    <Label htmlFor="q-notes">Notas Administrativas / Histórico de Contato</Label>
                    <Textarea
                      id="q-notes"
                      placeholder="Registrar anotações sobre ligações, e-mails enviados, propostas enviadas..."
                      rows={3}
                      value={adminNotes}
                      onChange={(e) => setAdminNotes(e.target.value)}
                    />
                  </div>
                </div>
              </div>

              <DialogFooter>
                <Button variant="outline" onClick={() => setSelectedQuote(null)} disabled={submitting}>
                  Fechar
                </Button>
                <Button onClick={handleSaveNotes} disabled={submitting} className="gap-1.5">
                  {submitting && <Loader2 className="w-4 h-4 animate-spin" />}
                  Salvar Alterações
                </Button>
              </DialogFooter>
            </>
          )}
        </DialogContent>
      </Dialog>
    </div>
  );
}
