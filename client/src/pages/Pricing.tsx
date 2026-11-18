import { useAuth } from "@/_core/hooks/useAuth";
import { useAuthModal } from "@/contexts/AuthModalContext";
import SEO from "@/components/SEO";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { useLanguage } from "@/contexts/LanguageContext";
import { trpc } from "@/lib/trpc";
import { toast } from "sonner";
import { Check, Crown, Loader2, Sparkles, Zap, CreditCard, Shield } from "lucide-react";
import { useState } from "react";
import { Checkbox } from "@/components/ui/checkbox";
import { Link } from "wouter";
import CheckoutModal from "@/components/CheckoutModal";

export default function Pricing() {
  const { language } = useLanguage();
  const { isAuthenticated } = useAuth();
  const { openLogin } = useAuthModal();
  const [checkoutPlan, setCheckoutPlan] = useState<"monthly" | "annual" | null>(null);
  const [termsAccepted, setTermsAccepted] = useState(false);

  const { data: planStatus } = trpc.payment.myPlan.useQuery(undefined, {
    enabled: isAuthenticated,
  });
  const startTrial = trpc.payment.startTrial.useMutation();
  const utils = trpc.useUtils();

  const handleStartTrial = async () => {
    if (!isAuthenticated) { openLogin(); return; }
    if (!termsAccepted) {
      toast.error(language === "pt" ? "Você precisa aceitar os Termos de Uso para ativar o trial." : "You must accept the Terms of Use to activate the trial.");
      return;
    }
    try {
      await startTrial.mutateAsync();
      toast.success(language === "pt" ? "Trial premium ativado por 7 dias! 🎉" : "Premium trial activated for 7 days! 🎉");
      utils.payment.myPlan.invalidate();
    } catch (err: any) {
      toast.error(err.message || (language === "pt" ? "Erro ao ativar trial." : "Error activating trial."));
    }
  };

  const handleCheckout = (plan: "monthly" | "annual") => {
    if (!isAuthenticated) { openLogin(); return; }
    setCheckoutPlan(plan);
  };

  const isPremium = planStatus?.isPremiumActive;
  const isTrial = planStatus?.isTrialActive;
  const hasUsedTrial = !!planStatus?.hasUsedTrial;

  const freePlanFeatures = language === "pt"
    ? ["30 questões gratuitas por mês", "Banco de questões com filtros", "Perfil gamificado básico", "Ranking geral"]
    : ["30 free questions per month", "Question bank with filters", "Basic gamified profile", "General ranking"];

  const monthlyFeatures = language === "pt"
    ? ["Questões ilimitadas de todas as disciplinas", "Simulados ilimitados com timer", "Ranking semanal e mensal", "10 badges desbloqueáveis", "Histórico completo de simulados", "Suporte prioritário"]
    : ["Unlimited questions from all disciplines", "Unlimited exams with timer", "Weekly and monthly ranking", "10 unlockable badges", "Full exam history", "Priority support"];

  const annualFeatures = language === "pt"
    ? [...monthlyFeatures, "37% de desconto vs. mensalidade", "Acesso antecipado a novos recursos"]
    : [...monthlyFeatures, "37% discount vs. monthly plan", "Early access to new features"];

  return (
    <div className="min-h-screen bg-background pt-20 pb-16">
      <SEO
        title="Planos e Preços"
        description="Escolha o plano VetRank ideal para você. Trial grátis de 7 dias. Acesso ilimitado ao banco de questões, simulados e trilhas de Medicina Veterinária."
        canonical="/pricing"
      />
      <div className="container py-8 max-w-6xl mx-auto px-4">
        {/* Header */}
        <div className="text-center mb-14">
          <Badge className="mb-4 bg-primary/15 text-primary border-primary/30">
            {language === "pt" ? "Planos & Preços" : "Plans & Pricing"}
          </Badge>
          <h1 className="text-4xl md:text-5xl font-bold mb-4" style={{ fontFamily: "Playfair Display, serif" }}>
            {language === "pt" ? "Invista no seu futuro" : "Invest in your future"}
          </h1>
          <p className="text-muted-foreground text-lg max-w-2xl mx-auto">
            {language === "pt"
              ? "Comece gratuitamente e faça upgrade quando estiver pronto. Trial premium de 7 dias sem cartão de crédito."
              : "Start for free and upgrade when you're ready. 7-day premium trial with no credit card required."}
          </p>
        </div>

        {/* Plans Grid */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-5xl mx-auto">

          {/* Free Plan */}
          <div className="bg-card border border-border/50 rounded-2xl p-8 flex flex-col">
            <div className="mb-6">
              <div className="flex items-center gap-2 mb-3">
                <Zap className="h-5 w-5 text-muted-foreground" />
                <h2 className="text-xl font-bold" style={{ fontFamily: "Playfair Display, serif" }}>
                  {language === "pt" ? "Gratuito" : "Free"}
                </h2>
              </div>
              <div className="flex items-baseline gap-1 mb-1">
                <span className="text-4xl font-bold" style={{ fontFamily: "Playfair Display, serif" }}>R$0</span>
                <span className="text-muted-foreground text-sm">/{language === "pt" ? "sempre" : "forever"}</span>
              </div>
              <p className="text-muted-foreground text-sm">{language === "pt" ? "Para começar sua jornada" : "To start your journey"}</p>
            </div>
            <ul className="space-y-3 flex-1 mb-8">
              {freePlanFeatures.map((f) => (
                <li key={f} className="flex items-start gap-3">
                  <Check className="h-4 w-4 text-muted-foreground mt-0.5 shrink-0" />
                  <span className="text-sm text-muted-foreground">{f}</span>
                </li>
              ))}
            </ul>
            <Button variant="outline" className="w-full bg-transparent"
              onClick={() => !isAuthenticated && openLogin()}
              disabled={isAuthenticated && !isPremium}>
              {isAuthenticated
                ? isPremium ? (language === "pt" ? "Fazer Downgrade" : "Downgrade") : (language === "pt" ? "Plano Atual" : "Current Plan")
                : (language === "pt" ? "Começar Grátis" : "Start Free")}
            </Button>
          </div>

          {/* Premium Monthly */}
          <div className="bg-card border border-border/50 rounded-2xl p-8 flex flex-col">
            <div className="mb-6">
              <div className="flex items-center gap-2 mb-3">
                <Crown className="h-5 w-5 text-yellow-400" />
                <h2 className="text-xl font-bold" style={{ fontFamily: "Playfair Display, serif" }}>Premium Mensal</h2>
              </div>
              <div className="flex items-baseline gap-1 mb-2">
                <span className="text-4xl font-bold" style={{ fontFamily: "Playfair Display, serif" }}>R$39</span>
                <span className="text-2xl font-bold" style={{ fontFamily: "Playfair Display, serif" }}>,90</span>
                <span className="text-muted-foreground text-sm">/mês</span>
              </div>
              <p className="text-muted-foreground text-sm">
                Acesso completo renovado mensalmente. Ideal para quem quer flexibilidade.
              </p>
            </div>
            <ul className="space-y-3 flex-1 mb-8">
              {monthlyFeatures.map((f) => (
                <li key={f} className="flex items-start gap-3">
                  <Check className="h-4 w-4 text-primary mt-0.5 shrink-0" />
                  <span className="text-sm">{f}</span>
                </li>
              ))}
            </ul>
            {isPremium && !isTrial ? (
              <Button disabled className="w-full bg-primary/20 text-primary gap-2">
                <Crown className="h-4 w-4" /> Plano Ativo
              </Button>
            ) : (
              <div className="space-y-3">
                {!hasUsedTrial && (
                  <>
                    <div className="flex items-start gap-2 p-3 rounded-lg bg-muted/30 border border-border/50">
                      <Checkbox
                        id="terms-trial"
                        checked={termsAccepted}
                        onCheckedChange={(v) => setTermsAccepted(!!v)}
                        className="mt-0.5 shrink-0"
                      />
                      <label htmlFor="terms-trial" className="text-xs text-muted-foreground leading-relaxed cursor-pointer">
                        Li e aceito os{" "}
                        <Link href="/terms" className="text-primary hover:underline">Termos de Uso</Link>.
                        Estou ciente do período de 7 dias e da política de cancelamento.
                      </label>
                    </div>
                    <Button variant="outline"
                      className="w-full gap-2 border-primary/40 text-primary hover:bg-primary/10 bg-transparent"
                      onClick={handleStartTrial}
                      disabled={startTrial.isPending || isTrial || !termsAccepted}>
                      {startTrial.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Zap className="h-4 w-4" />}
                      {isTrial ? "Trial Ativo" : "Experimente grátis por 7 dias"}
                    </Button>
                    <p className="text-center text-xs text-muted-foreground">
                      Sem cobrança durante o período de teste
                    </p>
                    <div className="flex items-center gap-2 text-muted-foreground/40 text-xs justify-center">
                      <span className="flex-1 h-px bg-border/50" />
                      <span>ou assine diretamente</span>
                      <span className="flex-1 h-px bg-border/50" />
                    </div>
                  </>
                )}
                <Button className="w-full gap-2 bg-card border border-primary/40 text-primary hover:bg-primary/10"
                  onClick={() => handleCheckout("monthly")}>
                  <CreditCard className="h-4 w-4" />
                  Assinar Mensal — R$39,90/mês
                </Button>
                <p className="text-center text-xs text-muted-foreground">
                  Cobrança recorrente via Mercado Pago
                </p>
              </div>
            )}
          </div>

          {/* Premium Annual — HIGHLIGHT */}
          <div className="relative bg-card border-2 border-primary/50 rounded-2xl p-8 flex flex-col shadow-xl shadow-primary/10">
            <div className="absolute -top-3.5 left-1/2 -translate-x-1/2">
              <Badge className="bg-primary text-primary-foreground px-4 py-1 text-xs font-semibold shadow-lg">
                <Sparkles className="h-3 w-3 mr-1" />
                Melhor Custo-Benefício
              </Badge>
            </div>
            <div className="mb-6">
              <div className="flex items-center gap-2 mb-3">
                <Crown className="h-5 w-5 text-yellow-400" />
                <h2 className="text-xl font-bold" style={{ fontFamily: "Playfair Display, serif" }}>Premium Anual</h2>
              </div>
              <div className="flex items-baseline gap-1 mb-1">
                <span className="text-4xl font-bold text-primary" style={{ fontFamily: "Playfair Display, serif" }}>R$299</span>
                <span className="text-muted-foreground text-sm">/ano</span>
              </div>
              <div className="flex items-center gap-2 mb-2">
                <span className="text-sm text-primary font-semibold">≈ R$24,90/mês</span>
                <Badge className="bg-emerald-500/15 text-emerald-400 border-emerald-500/30 text-xs px-2">37% OFF</Badge>
              </div>
              <p className="text-sm text-emerald-400 font-medium">
                Economize R$179,80 vs. plano mensal
              </p>
            </div>
            <ul className="space-y-3 flex-1 mb-8">
              {annualFeatures.map((f) => (
                <li key={f} className="flex items-start gap-3">
                  <Check className="h-4 w-4 text-primary mt-0.5 shrink-0" />
                  <span className="text-sm">{f}</span>
                </li>
              ))}
            </ul>
            {isPremium && !isTrial ? (
              <Button disabled className="w-full bg-primary text-primary-foreground gap-2">
                <Crown className="h-4 w-4" /> Plano Ativo
              </Button>
            ) : (
              <div className="space-y-3">
                <Button className="w-full gap-2 bg-primary text-primary-foreground hover:bg-primary/90 shadow-lg shadow-primary/25 h-12 text-base font-semibold"
                  onClick={() => handleCheckout("annual")}>
                  <Crown className="h-5 w-5" />
                  Assinar Premium Anual
                </Button>
                <div className="grid grid-cols-2 gap-2 text-xs text-muted-foreground">
                  <div className="flex items-center gap-1.5">
                    <Check className="h-3 w-3 text-emerald-400 shrink-0" />
                    Pagamento único
                  </div>
                  <div className="flex items-center gap-1.5">
                    <Check className="h-3 w-3 text-emerald-400 shrink-0" />
                    12 meses de acesso
                  </div>
                  <div className="flex items-center gap-1.5">
                    <Check className="h-3 w-3 text-emerald-400 shrink-0" />
                    Pagamento seguro
                  </div>
                  <div className="flex items-center gap-1.5">
                    <Check className="h-3 w-3 text-emerald-400 shrink-0" />
                    Via Mercado Pago
                  </div>
                </div>
              </div>
            )}
          </div>
        </div>

        {/* Payment security note */}
        <div className="flex items-center justify-center gap-2 mt-10 text-muted-foreground text-sm">
          <Shield className="w-4 h-4 text-primary" />
          <span>
            {language === "pt"
              ? "Pagamento seguro processado pelo Mercado Pago. Seus dados estão protegidos."
              : "Secure payment processed by Mercado Pago. Your data is protected."}
          </span>
        </div>
      </div>
      <CheckoutModal
        open={!!checkoutPlan}
        plan={checkoutPlan}
        onOpenChange={v => { if (!v) setCheckoutPlan(null); }}
      />
    </div>
  );
}
