import { useAuth } from "@/_core/hooks/useAuth";
import { useAuthModal } from "@/contexts/AuthModalContext";
import { trpc } from "@/lib/trpc";
import { Button } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import { Link } from "wouter";
import {
  BookOpen,
  CheckCircle2,
  Circle,
  Dumbbell,
  FlaskConical,
  Flame,
  Zap,
  Trophy,
  Star,
  CalendarDays,
  Target,
} from "lucide-react";

function TaskRow({
  done,
  label,
  sub,
}: {
  done: boolean;
  label: string;
  sub?: string;
}) {
  return (
    <div className={`flex items-start gap-3 py-2.5 border-b border-border/20 last:border-0 transition-opacity ${done ? "opacity-60" : ""}`}>
      {done ? (
        <CheckCircle2 className="h-5 w-5 text-green-400 shrink-0 mt-0.5" />
      ) : (
        <Circle className="h-5 w-5 text-muted-foreground/40 shrink-0 mt-0.5" />
      )}
      <div className="flex-1 min-w-0">
        <p className={`text-sm font-sans font-medium leading-snug ${done ? "line-through text-muted-foreground" : ""}`}>
          {label}
        </p>
        {sub && <p className="text-xs text-muted-foreground font-sans mt-0.5">{sub}</p>}
      </div>
    </div>
  );
}

function ProgressTask({
  done,
  label,
  current,
  goal,
  unit,
}: {
  done: boolean;
  label: string;
  current: number;
  goal: number;
  unit: string;
}) {
  const pct = Math.min(100, Math.round((current / goal) * 100));
  return (
    <div className="py-2.5 border-b border-border/20 last:border-0">
      <div className="flex items-center gap-2 mb-1.5">
        {done ? (
          <CheckCircle2 className="h-4 w-4 text-green-400 shrink-0" />
        ) : (
          <Circle className="h-4 w-4 text-muted-foreground/40 shrink-0" />
        )}
        <span className={`text-sm font-sans font-medium flex-1 ${done ? "line-through text-muted-foreground" : ""}`}>
          {label}
        </span>
        <span className="text-xs font-sans font-bold text-primary shrink-0">
          {Math.min(current, goal)}/{goal} {unit}
        </span>
      </div>
      <div className="ml-6">
        <Progress value={pct} className="h-1.5" />
      </div>
    </div>
  );
}

function SectionCard({
  icon: Icon,
  title,
  subtitle,
  color,
  complete,
  children,
}: {
  icon: any;
  title: string;
  subtitle: string;
  color: string;
  complete: boolean;
  children: React.ReactNode;
}) {
  return (
    <div className={`rounded-2xl border bg-card overflow-hidden ${complete ? "border-green-500/30" : "border-border/50"}`}>
      <div className={`px-5 py-4 flex items-center gap-3 border-b ${complete ? "border-green-500/20 bg-green-500/5" : "border-border/30"}`}>
        <div className={`flex h-9 w-9 items-center justify-center rounded-xl ${color}`}>
          <Icon className="h-5 w-5" />
        </div>
        <div className="flex-1">
          <div className="flex items-center gap-2">
            <h3 className="font-sans font-semibold text-sm">{title}</h3>
            {complete && (
              <span className="text-xs font-sans font-bold text-green-400 bg-green-500/15 border border-green-500/30 rounded-full px-2 py-0.5">
                Completo!
              </span>
            )}
          </div>
          <p className="text-xs text-muted-foreground font-sans">{subtitle}</p>
        </div>
      </div>
      <div className="px-5 py-1">{children}</div>
    </div>
  );
}

export default function Missions() {
  const { isAuthenticated } = useAuth();
  const { openLogin } = useAuthModal();
  const { data, isLoading } = trpc.gamification.dailyMissions.useQuery(undefined, {
    enabled: isAuthenticated,
    refetchInterval: 60_000,
  });

  if (!isAuthenticated) {
    return (
      <div className="min-h-screen flex items-center justify-center px-4">
        <div className="text-center max-w-sm">
          <div className="w-16 h-16 rounded-2xl bg-primary/10 border border-primary/20 flex items-center justify-center mx-auto mb-5">
            <Target className="h-8 w-8 text-primary" />
          </div>
          <h2 className="font-serif text-2xl font-bold mb-2">Missões Diárias</h2>
          <p className="text-muted-foreground font-sans text-sm mb-5">
            Faça login para acompanhar suas missões e manter sua sequência de estudos.
          </p>
          <Button className="w-full" onClick={() => openLogin()}>
            Entrar
          </Button>
        </div>
      </div>
    );
  }

  if (isLoading || !data) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="animate-spin w-8 h-8 border-2 border-primary border-t-transparent rounded-full" />
      </div>
    );
  }

  const { profile, daily, weekly, monthly } = data;

  return (
    <div className="min-h-screen bg-background pt-20 pb-16 px-4">
      <div className="max-w-2xl mx-auto space-y-6">

        {/* ── Hero: XP + Streak ── */}
        <div className="rounded-2xl border border-border/50 bg-card p-6">
          <div className="flex items-center justify-between mb-4">
            <div>
              <h1 className="font-serif text-2xl font-bold">Missões & Rotina</h1>
              <p className="text-sm text-muted-foreground font-sans">
                {profile.levelIcon} {profile.levelTitle}
              </p>
            </div>
            <div className="flex items-center gap-2">
              {profile.streak > 0 && (
                <div className="flex flex-col items-center px-3 py-2 rounded-xl bg-orange-500/10 border border-orange-500/20">
                  <Flame className="h-5 w-5 text-orange-400" />
                  <span className="text-lg font-bold text-orange-400 leading-none mt-0.5">{profile.streak}</span>
                  <span className="text-[10px] text-orange-400/70 font-sans">dias</span>
                </div>
              )}
              <div className="flex flex-col items-center px-3 py-2 rounded-xl bg-primary/10 border border-primary/20">
                <Zap className="h-5 w-5 text-primary" />
                <span className="text-lg font-bold text-primary leading-none mt-0.5">
                  {profile.xp >= 1000 ? `${(profile.xp / 1000).toFixed(1)}k` : profile.xp}
                </span>
                <span className="text-[10px] text-primary/70 font-sans">XP</span>
              </div>
            </div>
          </div>

          {/* XP progress bar */}
          {profile.xpForNext > 0 && (
            <div>
              <div className="flex justify-between text-xs font-sans text-muted-foreground mb-1.5">
                <span>Nível {profile.level}</span>
                <span>{profile.xpInLevel} / {profile.xpForNext} XP → {profile.nextLevelTitle}</span>
              </div>
              <Progress value={profile.xpProgress} className="h-2" />
            </div>
          )}

          {/* Daily route status banner */}
          <div className={`mt-4 flex items-center gap-3 rounded-xl px-4 py-3 border text-sm font-sans ${
            daily.routeComplete
              ? "bg-green-500/10 border-green-500/25 text-green-300"
              : "bg-primary/5 border-primary/20 text-muted-foreground"
          }`}>
            <Star className={`h-4 w-4 shrink-0 ${daily.routeComplete ? "text-green-400" : "text-primary"}`} />
            {daily.routeComplete
              ? "Rota diária concluída! Continue amanhã para manter o streak."
              : "Complete a rota diária: login + 5 questões de prática."}
          </div>
        </div>

        {/* ── Daily Tasks ── */}
        <SectionCard
          icon={CalendarDays}
          title="Tarefas do Dia"
          subtitle="Reinicia à meia-noite"
          color="bg-blue-500/15 text-blue-400"
          complete={daily.routeComplete && daily.simulado}
        >
          <TaskRow done={daily.login} label="Fazer login hoje" sub="Você já está aqui!" />
          <ProgressTask
            done={daily.practice5}
            label="Responder 5 questões"
            current={daily.practice5Count}
            goal={5}
            unit="questões"
          />
          <ProgressTask
            done={daily.practice10}
            label="Responder 10 questões (bônus)"
            current={daily.practice10Count}
            goal={10}
            unit="questões"
          />
          <TaskRow
            done={daily.simulado}
            label="Completar 1 simulado"
            sub={daily.simulado ? "Feito!" : "Acesse Simulados para começar"}
          />
        </SectionCard>

        {/* ── Weekly Tasks ── */}
        <SectionCard
          icon={Trophy}
          title="Metas da Semana"
          subtitle="Reinicia toda segunda-feira"
          color="bg-purple-500/15 text-purple-400"
          complete={weekly.routeComplete}
        >
          <ProgressTask
            done={weekly.practice}
            label="Responder 30 questões"
            current={weekly.practiceCount}
            goal={weekly.practiceGoal}
            unit="questões"
          />
          <ProgressTask
            done={weekly.exam}
            label="Completar 2 simulados"
            current={weekly.examCount}
            goal={weekly.examGoal}
            unit="simulados"
          />
          <TaskRow
            done={weekly.streak}
            label={`Manter streak de ${weekly.streakGoal}+ dias`}
            sub={`Streak atual: ${profile.streak} dia${profile.streak !== 1 ? "s" : ""}`}
          />
        </SectionCard>

        {/* ── Monthly Goals ── */}
        <SectionCard
          icon={Star}
          title="Desafio do Mês"
          subtitle="Reinicia todo dia 1º"
          color="bg-yellow-500/15 text-yellow-400"
          complete={monthly.routeComplete}
        >
          <ProgressTask
            done={monthly.practice}
            label="Responder 150 questões"
            current={monthly.practiceCount}
            goal={monthly.practiceGoal}
            unit="questões"
          />
          <ProgressTask
            done={monthly.exam}
            label="Completar 5 simulados"
            current={monthly.examCount}
            goal={monthly.examGoal}
            unit="simulados"
          />
          <ProgressTask
            done={monthly.days}
            label="Cumprir a rota diária por 20 dias"
            current={monthly.daysCompleted}
            goal={monthly.daysGoal}
            unit="dias"
          />
        </SectionCard>

        {/* ── Quick links ── */}
        <div className="grid grid-cols-3 gap-3">
          <Link href="/practice">
            <button className="w-full flex flex-col items-center gap-2 rounded-xl border border-border/50 bg-card hover:border-primary/30 hover:bg-primary/5 transition-colors py-4 px-2">
              <Dumbbell className="h-5 w-5 text-primary" />
              <span className="text-xs font-sans font-medium">Praticar</span>
            </button>
          </Link>
          <Link href="/exam">
            <button className="w-full flex flex-col items-center gap-2 rounded-xl border border-border/50 bg-card hover:border-primary/30 hover:bg-primary/5 transition-colors py-4 px-2">
              <FlaskConical className="h-5 w-5 text-primary" />
              <span className="text-xs font-sans font-medium">Simulado</span>
            </button>
          </Link>
          <Link href="/bank">
            <button className="w-full flex flex-col items-center gap-2 rounded-xl border border-border/50 bg-card hover:border-primary/30 hover:bg-primary/5 transition-colors py-4 px-2">
              <BookOpen className="h-5 w-5 text-primary" />
              <span className="text-xs font-sans font-medium">Questões</span>
            </button>
          </Link>
        </div>
      </div>
    </div>
  );
}
