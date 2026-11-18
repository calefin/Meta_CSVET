import { useState } from "react";
import { useI18n } from "@/contexts/I18nContext";
import { useAuth } from "@/_core/hooks/useAuth";
import { trpc } from "@/lib/trpc";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Check, CreditCard } from "lucide-react";
import BackButton from "@/components/BackButton";
import CheckoutDialog from "@/components/CheckoutDialog";
import type { PlanKey } from "@shared/plans";

const PLANS = [
  {
    key: "free" as const,
    features: [
      "Até 50 questões no banco",
      "3 avaliações por mês",
      "1 versão por avaliação",
      "100MB de armazenamento",
      "Correção manual",
    ],
    featuresEN: [
      "Up to 50 questions in bank",
      "3 assessments per month",
      "1 version per assessment",
      "100MB storage",
      "Manual grading",
    ],
    popular: false,
  },
  {
    key: "basic" as const,
    features: [
      "Até 500 questões no banco",
      "Avaliações ilimitadas",
      "Até 5 versões por avaliação",
      "1GB de armazenamento",
      "Correção por câmera",
      "Importação CSV/XLSX de alunos",
    ],
    featuresEN: [
      "Up to 500 questions in bank",
      "Unlimited assessments",
      "Up to 5 versions per assessment",
      "1GB storage",
      "Camera grading",
      "CSV/XLSX student import",
    ],
    popular: false,
  },
  {
    key: "pro" as const,
    features: [
      "Questões ilimitadas",
      "Avaliações ilimitadas",
      "Até 50 versões por avaliação",
      "10GB de armazenamento",
      "Correção por câmera em lote",
      "Compartilhamento público",
      "Relatórios avançados",
      "Suporte prioritário",
    ],
    featuresEN: [
      "Unlimited questions",
      "Unlimited assessments",
      "Up to 50 versions",
      "10GB storage",
      "Batch camera grading",
      "Public sharing",
      "Advanced reports",
      "Priority support",
    ],
    popular: true,
  },
  {
    key: "enterprise" as const,
    features: [
      "Tudo do Pro",
      "Armazenamento ilimitado",
      "SSO / LDAP",
      "API de integração",
      "SLA garantido",
      "Treinamento da equipe",
    ],
    featuresEN: [
      "Everything in Pro",
      "Unlimited storage",
      "SSO / LDAP",
      "Integration API",
      "Guaranteed SLA",
      "Team training",
    ],
    popular: false,
  },
];

export default function Pricing() {
  const { t, locale } = useI18n();
  const { user } = useAuth();
  const [checkoutPlan, setCheckoutPlan] = useState<Exclude<PlanKey, "free"> | null>(null);
  const { data: subData } = trpc.billing.getSubscription.useQuery(undefined, { enabled: !!user });

  const currentPlan = (subData?.plan ?? "free") as PlanKey;

  function handleChoosePlan(planKey: string) {
    if (!user) { window.location.href = "/login"; return; }
    if (planKey === "free" || planKey === currentPlan) return;
    setCheckoutPlan(planKey as Exclude<PlanKey, "free">);
  }

  return (
    <div className="max-w-5xl mx-auto space-y-6">
      <BackButton />
      <div className="text-center">
        <div className="flex items-center justify-center gap-2 mb-2">
          <CreditCard className="w-7 h-7 text-primary" />
          <h1 className="text-2xl font-bold">{t("pricing.title")}</h1>
        </div>
        <p className="text-muted-foreground">{t("pricing.subtitle")}</p>
      </div>

      <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
        {PLANS.map((plan) => {
          const priceObj = (t(`pricing.${plan.key}.price`));
          const periodObj = (t(`pricing.${plan.key}.period`));
          const nameObj = (t(`pricing.${plan.key}.name`));
          const features = locale === "en" ? plan.featuresEN : plan.features;

          return (
            <Card key={plan.key} className={`flex flex-col ${plan.popular ? "border-primary border-2 shadow-lg" : ""}`}>
              <CardHeader>
                <div className="flex items-center justify-between">
                  <CardTitle className="text-lg">{nameObj}</CardTitle>
                  {plan.popular && <Badge className="text-xs">Popular</Badge>}
                </div>
                <div className="flex items-end gap-1 mt-2">
                  <span className="text-3xl font-bold">{priceObj}</span>
                  <span className="text-muted-foreground text-sm pb-1">{periodObj}</span>
                </div>
              </CardHeader>

              <CardContent className="flex-1">
                <ul className="space-y-2">
                  {features.map((f, i) => (
                    <li key={i} className="flex items-start gap-2 text-sm">
                      <Check className="w-4 h-4 text-primary shrink-0 mt-0.5" />
                      <span>{f}</span>
                    </li>
                  ))}
                </ul>
              </CardContent>

              <CardFooter>
                <Button
                  variant={plan.popular ? "default" : "outline"}
                  className="w-full"
                  disabled={plan.key === "free" || plan.key === currentPlan}
                  onClick={() => handleChoosePlan(plan.key)}
                >
                  {plan.key === currentPlan || plan.key === "free"
                    ? t("pricing.currentPlan")
                    : t("pricing.choosePlan")}
                </Button>
              </CardFooter>
            </Card>
          );
        })}
      </div>

      <p className="text-center text-sm text-muted-foreground">
        Todos os planos pagos incluem 14 dias de teste gratuito. Sem cartão de crédito necessário para começar.
      </p>

      <CheckoutDialog
        open={!!checkoutPlan}
        planKey={checkoutPlan}
        onClose={() => setCheckoutPlan(null)}
      />
    </div>
  );
}
