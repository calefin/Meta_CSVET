import { useAuth } from "@/_core/hooks/useAuth";
import { getLoginUrl } from "@/const";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import { useLanguage, useT } from "@/contexts/LanguageContext";
import { trpc } from "@/lib/trpc";
import { toast } from "sonner";
import {
  BookOpen,
  Calendar,
  CheckCircle,
  Clock,
  Crown,
  Flame,
  Loader2,
  Lock,
  ShoppingBag,
  Snowflake,
  Star,
  Trophy,
  User,
  Zap,
} from "lucide-react";
import { useEffect } from "react";
import { Link } from "wouter";

const LEVEL_ICONS = ["🎓", "🔬", "🩺", "⭐", "💡", "🔭", "📚", "🏆", "👑"];

function formatDate(ts: number | Date | null | undefined) {
  if (!ts) return "—";
  return new Date(ts).toLocaleDateString();
}

function formatTime(seconds: number) {
  const m = Math.floor(seconds / 60);
  const s = seconds % 60;
  return `${m}m ${s}s`;
}

export default function Profile() {
  const t = useT();
  const { language } = useLanguage();
  const { isAuthenticated } = useAuth();

  const { data: profile, isLoading } = trpc.gamification.myProfile.useQuery(undefined, {
    enabled: isAuthenticated,
  });
  const { data: examHistory } = trpc.exams.history.useQuery(
    { limit: 20 },
    { enabled: isAuthenticated }
  );
  const { data: planStatus } = trpc.subscription.myPlan.useQuery(undefined, {
    enabled: isAuthenticated,
  });
  // Fonte de verdade de "trial já usado / assinatura expirada" (mesma do SubscriptionGuard e Pricing)
  const { data: accessInfo } = trpc.payment.myPlan.useQuery(undefined, {
    enabled: isAuthenticated,
  });
  const startTrial = trpc.subscription.startTrial.useMutation();
  const dailyLogin = trpc.gamification.dailyLogin.useMutation();
  const utils = trpc.useUtils();

  const { data: chestStatus } = trpc.gamification.dailyChestStatus.useQuery(undefined, { enabled: isAuthenticated });
  const { data: rouletteStatus } = trpc.gamification.rouletteStatus.useQuery(undefined, { enabled: isAuthenticated });
  const claimChest = trpc.gamification.claimDailyChest.useMutation({
    onSuccess: (d) => {
      toast.success(`🎁 Baú aberto! +${d.rewardAmount} ${d.rewardType === "coins" ? "VetCoins" : d.rewardType === "freeze" ? "Congelamento" : "XP"}`);
      utils.gamification.myProfile.invalidate();
      utils.gamification.dailyChestStatus.invalidate();
    },
    onError: (e) => toast.error(e.message),
  });
  const spinRoulette = trpc.gamification.spinRoulette.useMutation({
    onSuccess: (d) => {
      toast.success(`🎰 Roleta! +${d.rewardAmount} ${d.rewardType === "coins" ? "VetCoins" : "Congelamento"}`);
      utils.gamification.myProfile.invalidate();
      utils.gamification.rouletteStatus.invalidate();
    },
    onError: (e) => toast.error(e.message),
  });

  useEffect(() => {
    if (isAuthenticated) {
      dailyLogin.mutate(undefined, {
        onSuccess: (data) => {
          if (data.xpBonus > 0) {
            toast.success(`🔥 ${language === "pt" ? `Sequência de ${data.streak} dias! +${data.xpBonus} XP` : `${data.streak}-day streak! +${data.xpBonus} XP`}`);
            utils.gamification.myProfile.invalidate();
          }
        },
      });
    }
  }, [isAuthenticated]);

  if (!isAuthenticated) {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center">
        <div className="text-center">
          <User className="h-16 w-16 text-muted-foreground/30 mx-auto mb-4" />
          <h2 className="font-serif text-2xl font-bold mb-2">{t("login_required")}</h2>
          <Button
            className="mt-4 bg-primary text-primary-foreground hover:bg-primary/90"
            onClick={() => (window.location.href = getLoginUrl())}
          >
            {t("login_btn")}
          </Button>
        </div>
      </div>
    );
  }

  if (isLoading || !profile) {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center">
        <Loader2 className="h-8 w-8 animate-spin text-primary" />
      </div>
    );
  }

  const { user, currentLevel, nextLevel, progressPercent, xpInCurrentLevel, xpForNextLevel, badges } = profile;

  const handleStartTrial = async () => {
    try {
      await startTrial.mutateAsync();
      toast.success(language === "pt" ? "Trial premium ativado por 7 dias! 🎉" : "Premium trial activated for 7 days! 🎉");
      utils.subscription.myPlan.invalidate();
      utils.payment.myPlan.invalidate();
      utils.auth.me.invalidate();
    } catch (err: any) {
      toast.error(err.message || t("error"));
    }
  };

  return (
    <div className="min-h-screen bg-background">
      <div className="container py-10 max-w-4xl mx-auto">
        <h1 className="font-serif text-3xl font-bold mb-8">{t("profile_title")}</h1>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Left column */}
          <div className="lg:col-span-1 space-y-4">
            {/* User card */}
            <div className="bg-card border border-border/50 rounded-2xl p-6 text-center">
              <div className="flex h-20 w-20 items-center justify-center rounded-full bg-primary/15 border-2 border-primary/30 mx-auto mb-4">
                <span className="font-serif text-3xl font-bold text-primary">
                  {user.name?.charAt(0)?.toUpperCase() ?? "?"}
                </span>
              </div>
              <h2 className="font-serif text-xl font-bold mb-1">{user.name}</h2>
              <p className="text-sm text-muted-foreground font-sans mb-3">{user.email}</p>

              <div className="flex items-center justify-center gap-2 mb-4">
                <span className="text-2xl">{LEVEL_ICONS[currentLevel.level - 1]}</span>
                <div>
                  <div className="font-serif font-bold text-primary">
                    {language === "pt" ? currentLevel.name : currentLevel.nameEn}
                  </div>
                  <div className="text-xs text-muted-foreground font-sans">Nível {currentLevel.level}</div>
                </div>
              </div>

              {/* XP Progress */}
              <div className="mb-2">
                <div className="flex justify-between text-xs text-muted-foreground font-sans mb-1">
                  <span>{xpInCurrentLevel} XP</span>
                  {nextLevel && <span>{xpForNextLevel} XP</span>}
                </div>
                <Progress value={progressPercent} className="h-2" />
                <div className="text-xs text-muted-foreground font-sans mt-1 text-center">
                  {nextLevel
                    ? `${progressPercent}% ${language === "pt" ? "para" : "to"} ${language === "pt" ? nextLevel.name : nextLevel.nameEn}`
                    : language === "pt" ? "Nível máximo!" : "Max level!"}
                </div>
              </div>
            </div>

            {/* Stats */}
            <div className="bg-card border border-border/50 rounded-2xl p-5">
              <h3 className="font-serif font-semibold mb-4">{language === "pt" ? "Estatísticas" : "Statistics"}</h3>
              <div className="space-y-3">
                {[
                  { icon: Zap,          label: t("profile_xp"),        value: `${user.xp.toLocaleString()} XP`,          color: "text-primary" },
                  { icon: Flame,        label: t("profile_streak"),     value: `${user.streak} ${t("profile_days")}`,     color: "text-orange-400" },
                  { icon: CheckCircle,  label: t("profile_accuracy"),   value: `${user.accuracy}%`,                       color: "text-green-400" },
                  { icon: BookOpen,     label: t("profile_exams"),      value: String(user.totalExams),                   color: "text-cyan-400" },
                  { icon: Star,         label: t("profile_questions"),  value: String(user.totalQuestions),               color: "text-yellow-400" },
                ].map((stat) => (
                  <div key={stat.label} className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                      <stat.icon className={`h-4 w-4 ${stat.color}`} />
                      <span className="text-sm text-muted-foreground font-sans">{stat.label}</span>
                    </div>
                    <span className={`font-serif font-bold text-sm ${stat.color}`}>{stat.value}</span>
                  </div>
                ))}
                {/* VetCoins + Freezes */}
                <div className="pt-2 border-t border-border/30 space-y-2">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                      <span className="text-base">🪙</span>
                      <span className="text-sm text-muted-foreground font-sans">VetCoins</span>
                    </div>
                    <span className="font-serif font-bold text-sm text-amber-400">{(user.coins ?? 0).toLocaleString()}</span>
                  </div>
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                      <Snowflake className="h-4 w-4 text-cyan-400" />
                      <span className="text-sm text-muted-foreground font-sans">Congelamentos</span>
                    </div>
                    <div className="flex gap-1">
                      {[0, 1].map(i => (
                        <div key={i} className={`w-5 h-5 rounded border flex items-center justify-center text-xs ${i < (user.streakFreezes ?? 0) ? "bg-cyan-500/20 border-cyan-500/40 text-cyan-400" : "border-border/30 text-muted-foreground/30"}`}>
                          {i < (user.streakFreezes ?? 0) ? "❄️" : "○"}
                        </div>
                      ))}
                    </div>
                  </div>
                </div>
              </div>
            </div>

            {/* Plan */}
            <div className="bg-card border border-border/50 rounded-2xl p-5">
              <h3 className="font-serif font-semibold mb-3">{t("profile_plan")}</h3>
              {planStatus && (
                <div>
                  <div className="flex items-center gap-2 mb-3">
                    <Crown className={`h-4 w-4 ${planStatus.canAccessPremium ? "text-yellow-400" : "text-muted-foreground"}`} />
                    <Badge className={`font-sans text-xs ${
                      planStatus.canAccessPremium
                        ? "bg-yellow-500/20 text-yellow-400 border-yellow-500/30"
                        : "bg-accent text-muted-foreground border-border/50"
                    }`}>
                      {planStatus.plan === "premium" ? "Premium" : planStatus.plan === "trial" ? (language === "pt" ? "Trial Premium" : "Premium Trial") : (language === "pt" ? "Gratuito" : "Free")}
                    </Badge>
                  </div>
                  {planStatus.plan === "trial" && planStatus.trialDaysLeft !== undefined && (
                    <p className="text-xs text-muted-foreground font-sans mb-3">
                      {t("profile_trial_ends")}: {planStatus.trialDaysLeft} {language === "pt" ? "dias" : "days"}
                    </p>
                  )}
                  {planStatus.plan === "free" && accessInfo && !accessInfo.isStaff && (
                    accessInfo.hasUsedTrial ? (
                      // Trial já usado (ou ex-assinante): o único caminho é assinar — nunca oferecer trial aqui.
                      <>
                        <p className="text-xs text-muted-foreground font-sans mb-3">
                          {accessInfo.premiumExpired
                            ? (language === "pt" ? "Sua assinatura expirou. Reative para voltar a ter acesso completo." : "Your subscription expired. Reactivate to regain full access.")
                            : (language === "pt" ? "Seu período de teste terminou." : "Your trial has ended.")}
                        </p>
                        <Link href="/pricing">
                          <Button size="sm" className="w-full bg-primary text-primary-foreground hover:bg-primary/90 gap-2 font-sans text-xs">
                            <Crown className="h-3 w-3" />
                            {accessInfo.premiumExpired
                              ? (language === "pt" ? "Reativar Premium" : "Reactivate Premium")
                              : (language === "pt" ? "Assinar Premium" : "Subscribe Premium")}
                          </Button>
                        </Link>
                      </>
                    ) : (
                      <Button
                        size="sm"
                        className="w-full bg-primary text-primary-foreground hover:bg-primary/90 gap-2 font-sans text-xs"
                        onClick={handleStartTrial}
                        disabled={startTrial.isPending}
                      >
                        {startTrial.isPending ? <Loader2 className="h-3 w-3 animate-spin" /> : <Crown className="h-3 w-3" />}
                        {language === "pt" ? "Ativar Trial Grátis (7 dias)" : "Activate Free Trial (7 days)"}
                      </Button>
                    )
                  )}
                </div>
              )}
            </div>
          </div>

          {/* Right column */}
          <div className="lg:col-span-2 space-y-6">
            {/* Recompensas Diárias */}
            <div className="bg-card border border-border/50 rounded-2xl p-5">
              <h3 className="font-serif text-lg font-semibold mb-4">🎮 Recompensas Diárias</h3>
              <div className="grid grid-cols-2 gap-3">
                {/* Roleta */}
                <div className="p-4 rounded-xl border border-border/40 bg-accent/20 flex flex-col items-center gap-2 text-center">
                  <span className="text-3xl">🎰</span>
                  <p className="text-sm font-medium">Roleta Grátis</p>
                  <p className="text-xs text-muted-foreground">1 giro por dia</p>
                  {rouletteStatus?.spun ? (
                    <div className="text-xs text-green-400 font-medium">
                      ✓ Girado hoje (+{rouletteStatus.reward?.amount} {rouletteStatus.reward?.type === "coins" ? "🪙" : "❄️"})
                    </div>
                  ) : (
                    <Button size="sm" className="w-full text-xs" onClick={() => spinRoulette.mutate(undefined)} disabled={spinRoulette.isPending}>
                      {spinRoulette.isPending ? <Loader2 className="h-3 w-3 animate-spin" /> : "Girar Agora"}
                    </Button>
                  )}
                </div>
                {/* Baú Diário */}
                <div className="p-4 rounded-xl border border-border/40 bg-accent/20 flex flex-col items-center gap-2 text-center">
                  <span className="text-3xl">🎁</span>
                  <p className="text-sm font-medium">Baú Diário</p>
                  <p className="text-xs text-muted-foreground">
                    {chestStatus ? `${chestStatus.practiceCount}/5 questões` : "Responda 5 questões"}
                  </p>
                  {chestStatus?.claimed ? (
                    <div className="text-xs text-green-400 font-medium">
                      ✓ Coletado hoje (+{chestStatus.reward?.amount} {chestStatus.reward?.type === "coins" ? "🪙" : "❄️"})
                    </div>
                  ) : (
                    <Button size="sm" className="w-full text-xs" onClick={() => claimChest.mutate(undefined)}
                      disabled={!chestStatus?.canClaim || claimChest.isPending}>
                      {claimChest.isPending ? <Loader2 className="h-3 w-3 animate-spin" /> : chestStatus?.canClaim ? "Abrir Baú! 🎉" : "Indisponível"}
                    </Button>
                  )}
                </div>
              </div>
              <p className="text-xs text-muted-foreground mt-3 text-center font-sans">
                Acesse a <a href="/shop" className="text-primary hover:underline">Loja</a> para gastar seus VetCoins 🪙
              </p>
            </div>

            {/* Badges */}
            <div className="bg-card border border-border/50 rounded-2xl p-6">
              <div className="flex items-center justify-between mb-4">
                <h3 className="font-serif text-lg font-semibold">{t("profile_badges")}</h3>
                <div className="flex gap-1.5 text-xs">
                  {[
                    { label: "Comum", color: "border-border/50 text-muted-foreground" },
                    { label: "Raro", color: "border-blue-500/50 text-blue-400" },
                    { label: "Épico", color: "border-purple-500/50 text-purple-400" },
                    { label: "Lend.", color: "border-amber-500/50 text-amber-400" },
                  ].map(r => (
                    <span key={r.label} className={`px-1.5 py-0.5 rounded border font-sans ${r.color}`}>{r.label}</span>
                  ))}
                </div>
              </div>
              <div className="grid grid-cols-5 gap-3">
                {badges
                  .filter(b => b.rarity !== "hidden" || b.earned)
                  .map((badge) => {
                    const rarityStyle = badge.earned ? {
                      common:    "bg-primary/10 border-border/50",
                      rare:      "bg-blue-500/10 border-blue-500/40",
                      epic:      "bg-purple-500/10 border-purple-500/40",
                      legendary: "bg-amber-500/10 border-amber-500/40 shadow-sm shadow-amber-500/20",
                      hidden:    "bg-teal-500/10 border-teal-500/40",
                    }[badge.rarity] ?? "bg-primary/10 border-primary/30" : "bg-accent/30 border-border/30 opacity-50";
                    return (
                      <div
                        key={badge.id}
                        className={`flex flex-col items-center gap-1.5 p-3 rounded-xl border transition-all ${rarityStyle}`}
                        title={`${badge.rarity.toUpperCase()} — ${(language === "pt" ? badge.descriptionPt : badge.descriptionEn) ?? ""}`}
                      >
                        <span className="text-2xl">{badge.earned ? badge.icon : "🔒"}</span>
                        <span className={`text-xs text-center leading-tight font-sans ${badge.earned ? "text-foreground" : "text-muted-foreground"}`}>
                          {language === "pt" ? badge.namePt : badge.nameEn}
                        </span>
                        {!badge.earned && <Lock className="h-3 w-3 text-muted-foreground/50" />}
                      </div>
                    );
                })}
              </div>
            </div>

            {/* Exam history */}
            <div className="bg-card border border-border/50 rounded-2xl p-6">
              <div className="flex items-center justify-between mb-4">
                <h3 className="font-serif text-lg font-semibold">{t("profile_history")}</h3>
                <Link href="/exam">
                  <Button size="sm" variant="outline" className="font-sans text-xs gap-1.5">
                    <Trophy className="h-3 w-3" />
                    {language === "pt" ? "Novo Simulado" : "New Exam"}
                  </Button>
                </Link>
              </div>

              {!examHistory?.length ? (
                <div className="text-center py-10">
                  <BookOpen className="h-10 w-10 text-muted-foreground/30 mx-auto mb-3" />
                  <p className="text-muted-foreground font-sans text-sm">{t("profile_no_exams")}</p>
                  <Link href="/exam">
                    <Button size="sm" className="mt-3 bg-primary text-primary-foreground hover:bg-primary/90 font-sans">
                      {language === "pt" ? "Fazer primeiro simulado" : "Take first exam"}
                    </Button>
                  </Link>
                </div>
              ) : (
                <div className="space-y-3">
                  {examHistory.map((exam) => {
                    const accuracy = exam.totalQuestions > 0
                      ? Math.round(((exam.correctAnswers ?? 0) / exam.totalQuestions) * 100)
                      : 0;
                    return (
                      <div key={exam.id} className="flex items-center gap-4 p-4 rounded-xl bg-accent/30 border border-border/30">
                        <div className={`flex h-10 w-10 items-center justify-center rounded-xl shrink-0 ${
                          accuracy >= 70 ? "bg-green-500/20 border border-green-500/30" : "bg-red-500/20 border border-red-500/30"
                        }`}>
                          <span className={`font-serif font-bold text-sm ${accuracy >= 70 ? "text-green-400" : "text-red-400"}`}>
                            {accuracy}%
                          </span>
                        </div>
                        <div className="flex-1 min-w-0">
                          <div className="flex items-center gap-2 mb-0.5">
                            <span className="text-sm font-medium font-sans">
                              {exam.correctAnswers ?? 0}/{exam.totalQuestions} {language === "pt" ? "corretas" : "correct"}
                            </span>
                            <Badge className="text-xs bg-primary/15 text-primary border-primary/20 font-sans">
                              +{exam.xpEarned} XP
                            </Badge>
                          </div>
                          <div className="flex items-center gap-3 text-xs text-muted-foreground font-sans">
                            <span className="flex items-center gap-1">
                              <Calendar className="h-3 w-3" />
                              {formatDate(exam.completedAt)}
                            </span>
                            {exam.timeSpentSeconds && (
                              <span className="flex items-center gap-1">
                                <Clock className="h-3 w-3" />
                                {formatTime(exam.timeSpentSeconds)}
                              </span>
                            )}
                          </div>
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
