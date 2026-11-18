import { useState } from "react";
import { useAuth } from "@/_core/hooks/useAuth";
import { useLocation } from "wouter";
import { trpc } from "@/lib/trpc";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Loader2, CreditCard, CheckCircle2, XCircle, AlertCircle, ArrowLeft } from "lucide-react";
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger,
} from "@/components/ui/alert-dialog";
import { PLAN_NAMES, PLAN_PRICES, PLAN_LIMITS } from "@shared/plans";
import type { PlanKey } from "@shared/plans";

const STATUS_LABELS: Record<string, string> = {
  authorized: "Ativa",
  pending:    "Aguardando pagamento",
  paused:     "Pausada",
  cancelled:  "Cancelada",
};

const PLAN_BADGE: Record<PlanKey, string> = {
  free:       "bg-slate-100 text-slate-600",
  basic:      "bg-blue-100 text-blue-700",
  pro:        "bg-emerald-100 text-emerald-700",
  enterprise: "bg-purple-100 text-purple-700",
};

export default function BillingPortal() {
  const { user, loading: authLoading } = useAuth();
  const [, navigate] = useLocation();
  const [isCancelling, setIsCancelling] = useState(false);

  const { data, isLoading, refetch } = trpc.billing.getSubscription.useQuery(undefined, { enabled: !!user });
  const cancelMutation = trpc.billing.cancelSubscription.useMutation();

  async function handleCancel() {
    setIsCancelling(true);
    try {
      await cancelMutation.mutateAsync();
      toast.success("Assinatura cancelada. Seu acesso continua até o fim do período pago.");
      refetch();
    } catch (e: any) {
      toast.error(e?.message || "Erro ao cancelar assinatura");
    } finally {
      setIsCancelling(false);
    }
  }

  if (authLoading || isLoading) {
    return <div className="flex items-center justify-center min-h-[40vh]"><Loader2 className="animate-spin w-8 h-8" /></div>;
  }
  if (!user) { navigate("/"); return null; }

  const plan = (data?.plan ?? "free") as PlanKey;
  const sub  = data?.subscription as any;
  const limits = PLAN_LIMITS[plan];

  return (
    <div className="max-w-2xl mx-auto space-y-6 pb-16">
      <Button variant="ghost" size="sm" onClick={() => navigate("/profile")} className="px-0">
        <ArrowLeft className="w-4 h-4 mr-1" /> Minha Conta
      </Button>

      <div>
        <h1 className="text-2xl font-bold">Assinatura</h1>
        <p className="text-muted-foreground text-sm">Gerencie seu plano e faturamento</p>
      </div>

      {/* Current plan */}
      <div className="border rounded-xl p-5 bg-card space-y-4">
        <div className="flex items-center justify-between">
          <h2 className="font-semibold text-sm uppercase tracking-wide text-muted-foreground">Plano atual</h2>
          <span className={`text-xs font-semibold px-2.5 py-1 rounded-full ${PLAN_BADGE[plan]}`}>
            {PLAN_NAMES[plan]}
          </span>
        </div>

        <div className="grid grid-cols-2 gap-3 text-sm">
          <div>
            <span className="text-muted-foreground text-xs block mb-0.5">Questões</span>
            {limits.maxQuestions === -1 ? "Ilimitado" : `até ${limits.maxQuestions}`}
          </div>
          <div>
            <span className="text-muted-foreground text-xs block mb-0.5">Versões/prova</span>
            {`até ${limits.maxVersions}`}
          </div>
          <div>
            <span className="text-muted-foreground text-xs block mb-0.5">Armazenamento</span>
            {limits.storageMB === -1 ? "Ilimitado" : `${limits.storageMB >= 1024 ? `${limits.storageMB / 1024} GB` : `${limits.storageMB} MB`}`}
          </div>
          <div>
            <span className="text-muted-foreground text-xs block mb-0.5">Compartilhamento</span>
            {limits.sharingEnabled ? <CheckCircle2 className="w-4 h-4 text-emerald-500" /> : <XCircle className="w-4 h-4 text-slate-400" />}
          </div>
        </div>

        {plan === "free" && (
          <Button className="w-full mt-2" onClick={() => navigate("/pricing")}>
            <CreditCard className="w-4 h-4 mr-2" />
            Fazer upgrade
          </Button>
        )}
      </div>

      {/* Active subscription details */}
      {sub && (
        <div className="border rounded-xl p-5 bg-card space-y-4">
          <h2 className="font-semibold text-sm uppercase tracking-wide text-muted-foreground">Detalhes da assinatura</h2>
          <div className="grid grid-cols-2 gap-3 text-sm">
            <div>
              <span className="text-muted-foreground text-xs block mb-0.5">Status</span>
              <span className={`inline-flex items-center gap-1 ${sub.status === "authorized" ? "text-emerald-600" : "text-amber-600"}`}>
                {sub.status === "authorized" ? <CheckCircle2 className="w-3.5 h-3.5" /> : <AlertCircle className="w-3.5 h-3.5" />}
                {STATUS_LABELS[sub.status] ?? sub.status}
              </span>
            </div>
            <div>
              <span className="text-muted-foreground text-xs block mb-0.5">Valor mensal</span>
              R$ {PLAN_PRICES[sub.planKey as keyof typeof PLAN_PRICES]?.toFixed(2) ?? "—"}
            </div>
            {sub.currentPeriodEnd && (
              <div>
                <span className="text-muted-foreground text-xs block mb-0.5">Próxima cobrança</span>
                {new Date(sub.currentPeriodEnd).toLocaleDateString("pt-BR")}
              </div>
            )}
            <div>
              <span className="text-muted-foreground text-xs block mb-0.5">Criada em</span>
              {new Date(sub.createdAt).toLocaleDateString("pt-BR")}
            </div>
          </div>

          {sub.status === "authorized" && (
            <AlertDialog>
              <AlertDialogTrigger asChild>
                <Button variant="outline" className="text-destructive border-destructive/30 hover:bg-destructive/5">
                  Cancelar assinatura
                </Button>
              </AlertDialogTrigger>
              <AlertDialogContent>
                <AlertDialogHeader>
                  <AlertDialogTitle>Cancelar assinatura?</AlertDialogTitle>
                  <AlertDialogDescription>
                    Seu acesso ao plano {PLAN_NAMES[sub.planKey as PlanKey]} continuará até o fim do período atual.
                    Depois, sua conta será rebaixada para o plano Gratuito.
                  </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                  <AlertDialogCancel>Voltar</AlertDialogCancel>
                  <AlertDialogAction
                    onClick={handleCancel}
                    disabled={isCancelling}
                    className="bg-destructive text-white hover:bg-destructive/90"
                  >
                    {isCancelling ? <Loader2 className="w-4 h-4 animate-spin mr-2" /> : null}
                    Confirmar cancelamento
                  </AlertDialogAction>
                </AlertDialogFooter>
              </AlertDialogContent>
            </AlertDialog>
          )}
        </div>
      )}

      {!sub && plan === "free" && (
        <p className="text-sm text-muted-foreground text-center">
          Você está no plano gratuito.{" "}
          <button onClick={() => navigate("/pricing")} className="text-primary underline">
            Veja os planos pagos
          </button>
          {" "}para desbloquear mais recursos.
        </p>
      )}
    </div>
  );
}
