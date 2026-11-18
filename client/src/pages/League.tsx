import { useState, useEffect } from "react";
import { trpc } from "@/lib/trpc";
import { useAuth } from "@/_core/hooks/useAuth";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Loader2, Trophy, TrendingUp, TrendingDown, Minus } from "lucide-react";
import { useAuthModal } from "@/contexts/AuthModalContext";

const DIVISION_COLORS: Record<string, string> = {
  iron:     "text-gray-400 border-gray-400/30 bg-gray-400/5",
  bronze:   "text-amber-700 border-amber-700/30 bg-amber-700/5",
  silver:   "text-slate-400 border-slate-400/30 bg-slate-400/5",
  gold:     "text-amber-400 border-amber-400/30 bg-amber-400/5",
  platinum: "text-sky-400 border-sky-400/30 bg-sky-400/5",
  diamond:  "text-cyan-300 border-cyan-300/30 bg-cyan-300/5",
  master:   "text-purple-400 border-purple-400/30 bg-purple-400/5",
};

const PROMO_BG = "bg-emerald-500/10 border-l-2 border-l-emerald-500";
const RELG_BG  = "bg-red-500/10 border-l-2 border-l-red-500";

function useCountdown(endDate: string | undefined) {
  const [timeLeft, setTimeLeft] = useState("");

  useEffect(() => {
    if (!endDate) return;
    const tick = () => {
      const diff = new Date(endDate).getTime() - Date.now();
      if (diff <= 0) { setTimeLeft("Encerrada"); return; }
      const d = Math.floor(diff / 86400000);
      const h = Math.floor((diff % 86400000) / 3600000);
      const m = Math.floor((diff % 3600000) / 60000);
      const s = Math.floor((diff % 60000) / 1000);
      if (d > 0) setTimeLeft(`${d}d ${h}h ${m}m`);
      else setTimeLeft(`${h}h ${m}m ${s}s`);
    };
    tick();
    const id = setInterval(tick, 1000);
    return () => clearInterval(id);
  }, [endDate]);

  return timeLeft;
}

function HistorySection() {
  const { data: history, isLoading } = trpc.leagues.history.useQuery({ limit: 5 });

  if (isLoading) return <Loader2 className="w-4 h-4 animate-spin mx-auto" />;
  if (!history?.length) return <p className="text-xs text-muted-foreground text-center py-4">Nenhum histórico ainda.</p>;

  return (
    <div className="space-y-2">
      {history.map((row, i) => {
        const resultIcon = row.result === "promoted" ? <TrendingUp className="w-4 h-4 text-emerald-400" /> :
                           row.result === "relegated" ? <TrendingDown className="w-4 h-4 text-red-400" /> :
                           <Minus className="w-4 h-4 text-muted-foreground" />;
        const resultLabel = row.result === "promoted" ? "Promovido" :
                            row.result === "relegated" ? "Rebaixado" : "Mantido";
        return (
          <div key={i} className="flex items-center justify-between text-sm border border-border/30 rounded-lg px-3 py-2">
            <div className="flex items-center gap-2">
              <span className="text-base">{row.divisionLabel.icon}</span>
              <span className="font-medium">{row.divisionLabel.pt}</span>
              <span className="text-xs text-muted-foreground">— Semana {row.weekKey}</span>
            </div>
            <div className="flex items-center gap-3 text-xs">
              <span className="text-muted-foreground">#{row.rank} · {row.xpGained} XP</span>
              <div className="flex items-center gap-1">
                {resultIcon}
                <span>{resultLabel}</span>
              </div>
            </div>
          </div>
        );
      })}
    </div>
  );
}

export default function League() {
  const { isAuthenticated, user } = useAuth();
  const { openLogin } = useAuthModal();
  const { data, isLoading } = trpc.leagues.myLeague.useQuery(undefined, { enabled: isAuthenticated });
  const timeLeft = useCountdown(data?.endOfWeek);

  if (!isAuthenticated) {
    return (
      <div className="min-h-screen flex items-center justify-center px-4">
        <Card className="p-8 text-center max-w-sm">
          <Trophy className="w-12 h-12 text-primary mx-auto mb-4" />
          <h2 className="text-xl font-bold mb-2">Faça login para ver sua liga</h2>
          <Button className="w-full mt-2" onClick={() => openLogin()}>Entrar</Button>
        </Card>
      </div>
    );
  }

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <Loader2 className="w-8 h-8 animate-spin text-primary" />
      </div>
    );
  }

  if (!data) return null;

  const { division, divisionLabel, members, myRank, promotionZone, relegationZone, totalMembers } = data;
  const divColor = DIVISION_COLORS[division] ?? "";

  return (
    <div className="min-h-screen bg-background pt-20 pb-12 px-4">
      <div className="max-w-2xl mx-auto space-y-6">

        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold" style={{ fontFamily: "Playfair Display, serif" }}>
              🏆 Liga Semanal
            </h1>
            <p className="text-sm text-muted-foreground">Compita com outros alunos e suba de divisão</p>
          </div>
          <div className={`border rounded-xl px-4 py-2 text-center ${divColor}`}>
            <span className="text-2xl block">{divisionLabel.icon}</span>
            <span className="text-xs font-semibold">{divisionLabel.pt}</span>
          </div>
        </div>

        {/* Info strip */}
        <div className="grid grid-cols-3 gap-3 text-center">
          <div className="bg-card border border-border/50 rounded-xl py-3">
            <p className="text-xl font-bold">{myRank ?? "—"}</p>
            <p className="text-xs text-muted-foreground">Sua posição</p>
          </div>
          <div className="bg-card border border-border/50 rounded-xl py-3">
            <p className="text-xl font-bold">{totalMembers}</p>
            <p className="text-xs text-muted-foreground">Participantes</p>
          </div>
          <div className="bg-card border border-border/50 rounded-xl py-3">
            <p className="text-base font-bold tabular-nums">{timeLeft}</p>
            <p className="text-xs text-muted-foreground">Encerra em</p>
          </div>
        </div>

        {/* Legend */}
        <div className="flex gap-4 text-xs text-muted-foreground px-1">
          <div className="flex items-center gap-1.5">
            <div className="w-2.5 h-2.5 rounded-sm bg-emerald-500/30 border border-emerald-500" />
            <span>Promoção (top {promotionZone})</span>
          </div>
          <div className="flex items-center gap-1.5">
            <div className="w-2.5 h-2.5 rounded-sm bg-red-500/30 border border-red-500" />
            <span>Rebaixamento (últimos {totalMembers - relegationZone})</span>
          </div>
        </div>

        {/* Ranking */}
        <Card className="border-border/50">
          <CardHeader className="pb-2">
            <CardTitle className="text-base">Classificação</CardTitle>
          </CardHeader>
          <CardContent className="p-0">
            <div className="divide-y divide-border/30">
              {members.map((m) => {
                const isPromo = m.rank <= promotionZone;
                const isRelg  = m.rank > relegationZone;
                const rowBg   = isPromo ? PROMO_BG : isRelg ? RELG_BG : "";

                return (
                  <div
                    key={m.userId}
                    className={`flex items-center gap-3 px-4 py-3 ${rowBg} ${m.isMe ? "bg-primary/5" : ""}`}
                  >
                    {/* Rank */}
                    <div className="w-7 text-center shrink-0">
                      {m.rank === 1 ? <span className="text-lg">🥇</span> :
                       m.rank === 2 ? <span className="text-lg">🥈</span> :
                       m.rank === 3 ? <span className="text-lg">🥉</span> :
                       <span className="text-sm font-mono text-muted-foreground">{m.rank}</span>}
                    </div>

                    {/* Avatar */}
                    <Avatar className="w-8 h-8 shrink-0">
                      <AvatarImage src={m.avatarUrl ?? undefined} />
                      <AvatarFallback className="text-xs">{(m.name ?? "?")[0]?.toUpperCase()}</AvatarFallback>
                    </Avatar>

                    {/* Name */}
                    <div className="flex-1 min-w-0">
                      <p className={`text-sm font-medium truncate ${m.isMe ? "text-primary" : ""}`}>
                        {m.name ?? "Usuário"} {m.isMe && <span className="text-xs text-muted-foreground">(você)</span>}
                      </p>
                      <p className="text-xs text-muted-foreground">Nível {m.level}</p>
                    </div>

                    {/* XP */}
                    <div className="text-right shrink-0">
                      <p className="text-sm font-bold">{m.xpGained.toLocaleString()}</p>
                      <p className="text-xs text-muted-foreground">XP esta semana</p>
                    </div>

                    {/* Zone badge */}
                    {isPromo && (
                      <Badge variant="outline" className="text-xs border-emerald-500/50 text-emerald-400 shrink-0">
                        ↑ Sobe
                      </Badge>
                    )}
                    {isRelg && (
                      <Badge variant="outline" className="text-xs border-red-500/50 text-red-400 shrink-0">
                        ↓ Desce
                      </Badge>
                    )}
                  </div>
                );
              })}

              {members.length === 0 && (
                <p className="text-sm text-muted-foreground text-center py-8">
                  Nenhum membro ainda. Pratique para aparecer aqui!
                </p>
              )}
            </div>
          </CardContent>
        </Card>

        {/* Rewards */}
        <Card className="border-border/50 bg-accent/10">
          <CardContent className="p-4">
            <p className="text-sm font-semibold mb-3">🎁 Recompensas no fim da semana</p>
            <div className="space-y-2 text-xs text-muted-foreground">
              <div className="flex justify-between">
                <span>🥇 1º lugar</span>
                <span className="font-medium text-amber-400">+200 VetCoins + Promoção</span>
              </div>
              <div className="flex justify-between">
                <span>↑ Zona de promoção (top 25%)</span>
                <span className="font-medium text-emerald-400">+100 VetCoins + Promoção</span>
              </div>
              <div className="flex justify-between">
                <span>— Zona estável</span>
                <span className="font-medium">+30 VetCoins</span>
              </div>
              <div className="flex justify-between">
                <span>↓ Zona de rebaixamento (últimos 25%)</span>
                <span className="font-medium text-red-400">Rebaixamento</span>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* History */}
        <Card className="border-border/50">
          <CardHeader className="pb-2">
            <CardTitle className="text-base">Histórico</CardTitle>
          </CardHeader>
          <CardContent>
            <HistorySection />
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
