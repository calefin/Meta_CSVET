import { useState, useEffect } from "react";
import { trpc } from "@/lib/trpc";
import { useAuth } from "@/_core/hooks/useAuth";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Crown, X, Check } from "lucide-react";
import CheckoutModal from "./CheckoutModal";

const STORAGE_KEY = "vetrank-trial-offer-date";

function todayKey() {
  return new Date().toISOString().slice(0, 10);
}

export default function TrialOfferModal() {
  const { user, isAuthenticated } = useAuth();
  const [visible, setVisible] = useState(false);
  const [checkoutPlan, setCheckoutPlan] = useState<"monthly" | "annual" | null>(null);

  const { data: planData } = trpc.payment.myPlan.useQuery(undefined, {
    enabled: isAuthenticated && !!user,
  });

  useEffect(() => {
    if (!planData?.isTrialActive) return;
    if (localStorage.getItem(STORAGE_KEY) === todayKey()) return;
    const timer = setTimeout(() => setVisible(true), 2000);
    return () => clearTimeout(timer);
  }, [planData]);

  const dismiss = () => {
    localStorage.setItem(STORAGE_KEY, todayKey());
    setVisible(false);
  };

  if (!visible) return null;

  const daysLeft = planData?.trialEndsAt
    ? Math.max(0, Math.ceil((new Date(planData.trialEndsAt).getTime() - Date.now()) / 86400000))
    : 0;

  const benefits = [
    "Questões ilimitadas",
    "Simulados sem limite",
    "Ranking completo",
    "Todos os badges e conquistas",
    "Suporte prioritário",
  ];

  return (
    <>
      <div className="fixed inset-0 z-[150] flex items-end sm:items-center justify-center p-4 bg-background/60 backdrop-blur-sm">
        <div className="w-full max-w-sm rounded-2xl border border-primary/40 bg-card shadow-2xl shadow-primary/10 relative overflow-hidden">
          <div className="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-primary/60 via-primary to-primary/60" />

          <button
            onClick={dismiss}
            className="absolute top-3 right-3 text-muted-foreground hover:text-foreground transition-colors z-10"
          >
            <X className="h-4 w-4" />
          </button>

          <div className="p-6 space-y-4">
            {/* Header */}
            <div className="flex items-start gap-3">
              <div className="w-11 h-11 rounded-xl bg-primary/20 border border-primary/30 flex items-center justify-center shrink-0">
                <Crown className="h-5 w-5 text-primary" />
              </div>
              <div>
                <h3 className="font-serif font-bold text-base mb-0.5">Assine o Premium</h3>
                <p className="text-xs text-muted-foreground">
                  {daysLeft > 0
                    ? `Trial expira em ${daysLeft} dia${daysLeft !== 1 ? "s" : ""}. Não perca o acesso!`
                    : "Trial ativo. Aproveite a oferta especial!"}
                </p>
              </div>
            </div>

            {/* Benefits */}
            <ul className="space-y-1.5">
              {benefits.map(b => (
                <li key={b} className="flex items-center gap-2 text-sm">
                  <Check className="h-3.5 w-3.5 text-primary shrink-0" />
                  <span className="text-muted-foreground">{b}</span>
                </li>
              ))}
            </ul>

            {/* Plan options */}
            <div className="space-y-2">
              {/* Annual — destaque */}
              <button
                onClick={() => { dismiss(); setCheckoutPlan("annual"); }}
                className="w-full rounded-xl border-2 border-primary bg-primary/10 p-3.5 text-left hover:bg-primary/15 transition-colors relative"
              >
                <Badge className="absolute -top-2.5 right-3 bg-emerald-500 text-white border-0 text-[10px] px-2">MELHOR OPÇÃO</Badge>
                <div className="flex items-baseline justify-between">
                  <div>
                    <p className="font-semibold text-sm">Premium Anual</p>
                    <p className="text-xs text-muted-foreground">≈ R$24,90/mês • economize R$179,80</p>
                  </div>
                  <div className="text-right">
                    <p className="font-serif font-bold text-xl text-primary">R$299</p>
                    <p className="text-xs text-muted-foreground">/ano</p>
                  </div>
                </div>
                <Badge className="mt-1.5 bg-emerald-500/20 text-emerald-400 border-emerald-500/30 text-[10px]">37% OFF vs mensal</Badge>
              </button>

              {/* Monthly */}
              <button
                onClick={() => { dismiss(); setCheckoutPlan("monthly"); }}
                className="w-full rounded-xl border border-border/50 bg-muted/20 p-3.5 text-left hover:bg-muted/40 transition-colors"
              >
                <div className="flex items-baseline justify-between">
                  <div>
                    <p className="font-semibold text-sm">Premium Mensal</p>
                    <p className="text-xs text-muted-foreground">Cancele quando quiser</p>
                  </div>
                  <div className="text-right">
                    <p className="font-serif font-bold text-xl">R$39,90</p>
                    <p className="text-xs text-muted-foreground">/mês</p>
                  </div>
                </div>
              </button>
            </div>

            <button
              onClick={dismiss}
              className="text-xs text-muted-foreground hover:text-foreground transition-colors text-center py-1 w-full"
            >
              Continuar com o trial por enquanto
            </button>
          </div>
        </div>
      </div>

      <CheckoutModal
        open={!!checkoutPlan}
        plan={checkoutPlan}
        onOpenChange={v => { if (!v) setCheckoutPlan(null); }}
      />
    </>
  );
}
