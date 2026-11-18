import { type ReactNode } from "react";
import { trpc } from "@/lib/trpc";
import { useAuth } from "@/_core/hooks/useAuth";
import { Button } from "@/components/ui/button";
import { Crown, Lock, Zap } from "lucide-react";
import { Link } from "wouter";
import CheckoutModal from "./CheckoutModal";
import { useState } from "react";

interface Props {
  children: ReactNode;
}

/**
 * Bloqueia acesso a conteúdo premium quando:
 * - Trial expirado sem assinatura ativa
 * - Assinatura expirada sem renovação
 *
 * Staff (admin/teacher/coordinator/superuser) sempre passa.
 */
export default function SubscriptionGuard({ children }: Props) {
  const { user, isAuthenticated } = useAuth();
  const [checkoutPlan, setCheckoutPlan] = useState<"monthly" | "annual" | null>(null);

  const { data: planData, isLoading } = trpc.payment.myPlan.useQuery(undefined, {
    enabled: isAuthenticated && !!user,
  });

  // Não autenticado → AuthGuard cuida disso; aqui deixa passar
  if (!isAuthenticated || isLoading) return <>{children}</>;

  // Staff sempre tem acesso
  if (planData?.isStaff) return <>{children}</>;

  // Com acesso ativo → deixa passar
  if (planData?.hasAccess) return <>{children}</>;

  // Sem acesso: trial expirado ou assinatura expirada → bloqueia
  const trialExpired = planData?.trialExpired;
  const neverTrialed = !planData?.hasUsedTrial;

  return (
    <>
      <div className="min-h-screen bg-background flex items-center justify-center p-4">
        <div className="w-full max-w-md text-center space-y-6">
          {/* Icon */}
          <div className="w-20 h-20 rounded-2xl bg-primary/10 border border-primary/30 flex items-center justify-center mx-auto">
            <Lock className="h-9 w-9 text-primary" />
          </div>

          {/* Message */}
          {planData?.premiumExpired ? (
            <div>
              <h2 className="font-serif text-2xl font-bold mb-2">Sua assinatura expirou</h2>
              <p className="text-muted-foreground text-sm leading-relaxed">
                Seu histórico, XP e progresso continuam salvos. Escolha um plano abaixo para reativar o acesso completo imediatamente após a confirmação do pagamento.
              </p>
            </div>
          ) : trialExpired ? (
            <>
              <div>
                <h2 className="font-serif text-2xl font-bold mb-2">Seu trial expirou</h2>
                <p className="text-muted-foreground text-sm leading-relaxed">
                  O período de teste gratuito encerrou. Para continuar acessando todas as questões, simulados e trilhas, escolha um plano Premium.
                </p>
              </div>
            </>
          ) : (
            <>
              <div>
                <h2 className="font-serif text-2xl font-bold mb-2">Acesso Premium necessário</h2>
                <p className="text-muted-foreground text-sm leading-relaxed">
                  Este conteúdo é exclusivo para assinantes. Assine agora e acesse tudo ilimitado.
                </p>
              </div>
            </>
          )}

          {/* Plans */}
          <div className="space-y-3">
            <button
              onClick={() => setCheckoutPlan("annual")}
              className="w-full rounded-xl border-2 border-primary bg-primary/10 p-4 text-left hover:bg-primary/15 transition-colors relative"
            >
              <div className="absolute -top-2.5 right-3 bg-emerald-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full">
                MELHOR OPÇÃO
              </div>
              <div className="flex items-center justify-between">
                <div>
                  <p className="font-semibold">Premium Anual</p>
                  <p className="text-xs text-muted-foreground">≈ R$24,90/mês — 37% de desconto</p>
                </div>
                <div className="text-right">
                  <p className="font-serif font-bold text-2xl text-primary">R$299</p>
                  <p className="text-xs text-muted-foreground">/ano</p>
                </div>
              </div>
            </button>

            <button
              onClick={() => setCheckoutPlan("monthly")}
              className="w-full rounded-xl border border-border/50 bg-card p-4 text-left hover:bg-muted/30 transition-colors"
            >
              <div className="flex items-center justify-between">
                <div>
                  <p className="font-semibold">Premium Mensal</p>
                  <p className="text-xs text-muted-foreground">Cancele quando quiser</p>
                </div>
                <div className="text-right">
                  <p className="font-serif font-bold text-2xl">R$39,90</p>
                  <p className="text-xs text-muted-foreground">/mês</p>
                </div>
              </div>
            </button>
          </div>

          <p className="text-xs text-muted-foreground">
            Pagamento seguro via Mercado Pago.{" "}
            <Link href="/terms" className="text-primary hover:underline">Termos de Uso</Link>
            {" "}e{" "}
            <Link href="/privacy" className="text-primary hover:underline">política de cancelamento</Link>.
          </p>
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
