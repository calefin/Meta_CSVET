import { trpc } from "@/lib/trpc";
import {
  TrendingUp, Users, BookOpen, MessageSquare, CreditCard,
  AlertTriangle, CheckCircle, Clock, Zap, Activity,
} from "lucide-react";

const BRL = (v: number | string) =>
  `R$ ${Number(v).toLocaleString("pt-BR", { minimumFractionDigits: 2 })}`;
const n = (v: unknown) => Number(v ?? 0);
const dt = (v: unknown) => v ? new Date(v as string).toLocaleString("pt-BR") : "—";

function MetricCard({
  label, value, sub, icon: Icon, color = "text-primary", alert = false,
}: {
  label: string; value: string | number; sub?: string;
  icon: React.ElementType; color?: string; alert?: boolean;
}) {
  return (
    <div className={`rounded-2xl border p-5 ${alert ? "border-red-500/40 bg-red-500/5" : "border-border/50 bg-card"}`}>
      <div className="flex items-start justify-between mb-3">
        <span className="text-xs text-muted-foreground font-medium uppercase tracking-wide">{label}</span>
        <div className={`w-8 h-8 rounded-lg flex items-center justify-center ${alert ? "bg-red-500/20" : "bg-primary/10"}`}>
          <Icon className={`h-4 w-4 ${alert ? "text-red-400" : color}`} />
        </div>
      </div>
      <p className={`text-2xl font-bold font-serif ${alert ? "text-red-400" : color}`}>{value}</p>
      {sub && <p className="text-xs text-muted-foreground mt-1">{sub}</p>}
    </div>
  );
}

const ACTION_LABEL: Record<string, string> = {
  create_question: "Questão criada",
  import_questions: "Questões importadas",
  validate_question: "Questão validada",
  create_trail: "Trilha criada",
  update_user: "Usuário atualizado",
  accept_terms: "Termos aceitos",
  login: "Login",
};

export default function AdminDashboard() {
  const { data, isLoading } = trpc.admin.dashboardOverview.useQuery(undefined, {
    staleTime: 2 * 60 * 1000,   // 2 min — não refetch ao trocar de aba
    refetchInterval: 5 * 60 * 1000, // atualiza a cada 5 min em background
  });

  if (isLoading) {
    return (
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        {Array.from({ length: 8 }).map((_, i) => (
          <div key={i} className="rounded-2xl border border-border/50 bg-card p-5 animate-pulse h-24" />
        ))}
      </div>
    );
  }

  const u = data?.users;
  const c = data?.content;
  const f = data?.finance;
  const t = data?.tickets;
  const activity = data?.recentActivity ?? [];

  const hasAlerts = n(t?.urgentOpen) > 0 || n(f?.mrr) === 0;

  return (
    <div className="space-y-8">
      {/* Alert banner */}
      {hasAlerts && (
        <div className="rounded-xl border border-amber-500/30 bg-amber-500/5 p-4 flex items-start gap-3">
          <AlertTriangle className="h-5 w-5 text-amber-400 shrink-0 mt-0.5" />
          <div className="space-y-0.5">
            {n(t?.urgentOpen) > 0 && (
              <p className="text-sm text-amber-300 font-medium">
                {t?.urgentOpen} ticket(s) urgente(s) aguardando resposta
              </p>
            )}
            {n(f?.mrr) === 0 && (
              <p className="text-sm text-amber-300 font-medium">
                Nenhuma receita nos últimos 30 dias — verifique as configurações do Mercado Pago
              </p>
            )}
          </div>
        </div>
      )}

      {/* Usuarios */}
      <section>
        <h3 className="text-sm font-semibold text-muted-foreground uppercase tracking-wider mb-3">Usuários</h3>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <MetricCard label="Total de usuários" value={n(u?.total).toLocaleString()} icon={Users} color="text-primary" />
          <MetricCard label="Novos esta semana" value={n(u?.newThisWeek)} sub={`+${n(u?.newThisMonth)} este mês`} icon={TrendingUp} color="text-emerald-400" />
          <MetricCard label="Ativos esta semana" value={n(u?.activeThisWeek)} icon={Activity} color="text-cyan-400" />
          <MetricCard label="Assinantes premium" value={n(u?.premium)} sub={`${n(u?.trial)} em trial`} icon={CreditCard} color="text-purple-400" />
        </div>
      </section>

      {/* Financeiro */}
      <section>
        <h3 className="text-sm font-semibold text-muted-foreground uppercase tracking-wider mb-3">Financeiro</h3>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <MetricCard label="Receita (30 dias)" value={BRL(n(f?.mrr))} icon={TrendingUp} color="text-emerald-400" />
          <MetricCard label="Receita (7 dias)" value={BRL(n(f?.last7Days))} icon={Zap} color="text-primary" />
          <MetricCard label="Receita total" value={BRL(n(f?.totalRevenue))} icon={CreditCard} color="text-cyan-400" />
          <MetricCard label="Vendas este mês" value={n(f?.salesThisMonth)} icon={CheckCircle} color="text-emerald-400" />
        </div>
      </section>

      {/* Conteúdo + Suporte */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <section>
          <h3 className="text-sm font-semibold text-muted-foreground uppercase tracking-wider mb-3">Conteúdo</h3>
          <div className="grid grid-cols-2 gap-4">
            <MetricCard label="Questões ativas" value={n(c?.activeQuestions).toLocaleString()} icon={BookOpen} color="text-primary" />
            <MetricCard
              label="Aguardando validação" value={n(c?.pendingValidation)}
              icon={Clock} color="text-amber-400"
              alert={n(c?.pendingValidation) > 10}
            />
            <MetricCard label="Discursivas" value={n(c?.discursive)} icon={BookOpen} color="text-blue-400" />
            <MetricCard label="Total de questões" value={n(c?.totalQuestions).toLocaleString()} icon={BookOpen} color="text-muted-foreground" />
          </div>
        </section>

        <section>
          <h3 className="text-sm font-semibold text-muted-foreground uppercase tracking-wider mb-3">Suporte</h3>
          <div className="grid grid-cols-2 gap-4">
            <MetricCard label="Tickets abertos" value={n(t?.open)} icon={MessageSquare} color="text-red-400" alert={n(t?.open) > 0} />
            <MetricCard label="Em andamento" value={n(t?.inProgress)} icon={Clock} color="text-blue-400" />
            <MetricCard label="Urgentes" value={n(t?.urgentOpen)} icon={AlertTriangle} color="text-red-300" alert={n(t?.urgentOpen) > 0} />
            <MetricCard label="Total de tickets" value={n(t?.total)} icon={MessageSquare} color="text-muted-foreground" />
          </div>
        </section>
      </div>

      {/* Recent activity */}
      {activity.length > 0 && (
        <section>
          <h3 className="text-sm font-semibold text-muted-foreground uppercase tracking-wider mb-3">Atividade Recente</h3>
          <div className="rounded-2xl border border-border/50 bg-card overflow-hidden">
            {activity.map((a: any, i: number) => (
              <div key={i} className={`flex items-center gap-3 px-4 py-3 ${i < activity.length - 1 ? "border-b border-border/30" : ""}`}>
                <div className="w-7 h-7 rounded-lg bg-primary/10 flex items-center justify-center shrink-0">
                  <Activity className="h-3.5 w-3.5 text-primary" />
                </div>
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium truncate">
                    {ACTION_LABEL[a.action] ?? a.action}
                    {a.entityType && <span className="text-muted-foreground font-normal"> — {a.entityType}</span>}
                  </p>
                  <p className="text-xs text-muted-foreground">{a.userName ?? "Sistema"} {a.userRole ? `(${a.userRole})` : ""}</p>
                </div>
                <span className="text-xs text-muted-foreground shrink-0">{dt(a.createdAt)}</span>
              </div>
            ))}
          </div>
        </section>
      )}
    </div>
  );
}
