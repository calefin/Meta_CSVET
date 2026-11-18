import { useState } from "react";
import { trpc } from "@/lib/trpc";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription,
} from "@/components/ui/dialog";
import { Badge } from "@/components/ui/badge";
import { Loader2, ShieldCheck, CreditCard } from "lucide-react";
import { toast } from "sonner";
import { useAuth } from "@/_core/hooks/useAuth";
import { Link } from "wouter";

type Plan = "monthly" | "annual";

interface Props {
  open: boolean;
  plan: Plan | null;
  onOpenChange: (v: boolean) => void;
}

const PLAN_LABELS: Record<Plan, { name: string; price: string; badge?: string }> = {
  monthly: { name: "Premium Mensal", price: "R$ 39,90/mês" },
  annual:  { name: "Premium Anual",  price: "R$ 299,00/ano", badge: "37% OFF" },
};

function formatCpf(v: string) {
  const d = v.replace(/\D/g, "").slice(0, 11);
  return d
    .replace(/(\d{3})(\d)/, "$1.$2")
    .replace(/(\d{3})\.(\d{3})(\d)/, "$1.$2.$3")
    .replace(/(\d{3})\.(\d{3})\.(\d{3})(\d)/, "$1.$2.$3-$4");
}

function formatPhone(v: string) {
  const d = v.replace(/\D/g, "").slice(0, 11);
  if (d.length <= 10)
    return d.replace(/(\d{2})(\d{4})(\d{0,4})/, "($1) $2-$3");
  return d.replace(/(\d{2})(\d{5})(\d{0,4})/, "($1) $2-$3");
}

export default function CheckoutModal({ open, plan, onOpenChange }: Props) {
  const { user } = useAuth();
  const [name, setName] = useState(user?.name ?? "");
  const [cpf, setCpf] = useState("");
  const [phone, setPhone] = useState("");
  const [termsAccepted, setTermsAccepted] = useState(false);
  const [loading, setLoading] = useState(false);

  const saveInfo       = trpc.payment.savePayerInfo.useMutation();
  const createPref     = trpc.payment.createPreference.useMutation();
  const createSub      = trpc.payment.createSubscription.useMutation();

  const cpfDigits   = cpf.replace(/\D/g, "");
  const phoneDigits = phone.replace(/\D/g, "");
  const isValid     = name.trim().length >= 3 && cpfDigits.length === 11 && phoneDigits.length >= 10 && termsAccepted;

  const handleSubmit = async () => {
    if (!plan || !isValid) return;
    setLoading(true);
    try {
      await saveInfo.mutateAsync({ name: name.trim(), cpf: cpfDigits, phone: phoneDigits });
      const origin = window.location.origin;

      if (plan === "monthly") {
        // Monthly → Assinaturas (recorrente)
        const result = await createSub.mutateAsync({
          successUrl: `${origin}/payment/success`,
        });
        if (result.subscriptionUrl) window.location.href = result.subscriptionUrl;
      } else {
        // Annual → Checkout Pro (pagamento único)
        const result = await createPref.mutateAsync({
          plan,
          successUrl: `${origin}/payment/success`,
          failureUrl:  `${origin}/payment/failure`,
          pendingUrl:  `${origin}/payment/pending`,
        });
        if (result.checkoutUrl) window.location.href = result.checkoutUrl;
      }
    } catch (err: any) {
      toast.error(err.message || "Erro ao iniciar pagamento.");
      setLoading(false);
    }
  };

  const planInfo = plan ? PLAN_LABELS[plan] : null;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2 font-serif">
            <CreditCard className="h-5 w-5 text-primary" />
            Finalizar Assinatura
          </DialogTitle>
          <DialogDescription className="text-sm">
            Preencha seus dados para prosseguir para o pagamento seguro.
          </DialogDescription>
        </DialogHeader>

        {planInfo && (
          <div className="rounded-xl bg-primary/10 border border-primary/20 p-3 flex items-center justify-between">
            <div>
              <p className="font-semibold text-sm">{planInfo.name}</p>
              <p className="text-primary text-sm font-bold">{planInfo.price}</p>
            </div>
            {planInfo.badge && (
              <Badge className="bg-emerald-500/20 text-emerald-400 border-emerald-500/30">{planInfo.badge}</Badge>
            )}
          </div>
        )}

        <div className="space-y-4">
          <div className="space-y-1.5">
            <Label className="text-sm">Nome completo *</Label>
            <Input
              value={name}
              onChange={e => setName(e.target.value)}
              placeholder="Seu nome completo"
              className="h-10"
              disabled={loading}
            />
          </div>

          <div className="space-y-1.5">
            <Label className="text-sm">CPF *</Label>
            <Input
              value={cpf}
              onChange={e => setCpf(formatCpf(e.target.value))}
              placeholder="000.000.000-00"
              className="h-10"
              disabled={loading}
              inputMode="numeric"
            />
            <p className="text-xs text-muted-foreground">Necessário para emissão de nota fiscal e antifraude do Mercado Pago.</p>
          </div>

          <div className="space-y-1.5">
            <Label className="text-sm">Telefone / WhatsApp *</Label>
            <Input
              value={phone}
              onChange={e => setPhone(formatPhone(e.target.value))}
              placeholder="(11) 99999-9999"
              className="h-10"
              disabled={loading}
              inputMode="tel"
            />
          </div>
        </div>

        {/* Aceite de termos — obrigatório pelo CDC (Lei 8.078/90) */}
        <div className="flex items-start gap-3 p-3 rounded-lg border border-border/40 bg-muted/20">
          <Checkbox
            id="terms-checkout"
            checked={termsAccepted}
            onCheckedChange={v => setTermsAccepted(!!v)}
            className="mt-0.5 shrink-0"
            disabled={loading}
          />
          <label htmlFor="terms-checkout" className="text-xs text-muted-foreground leading-relaxed cursor-pointer">
            Li e concordo com os{" "}
            <Link href="/terms" className="text-primary hover:underline" onClick={() => {}}>
              Termos de Uso
            </Link>{" "}e a{" "}
            <Link href="/privacy" className="text-primary hover:underline" onClick={() => {}}>
              Política de Privacidade
            </Link>{" "}
            do VetRank, incluindo as condições de cancelamento e reembolso conforme o{" "}
            <span className="font-medium text-foreground">Código de Defesa do Consumidor (Lei 8.078/90)</span>.
          </label>
        </div>

        <div className="flex items-center gap-2 p-3 rounded-lg bg-muted/30 border border-border/40">
          <ShieldCheck className="h-4 w-4 text-emerald-400 shrink-0" />
          <p className="text-xs text-muted-foreground">
            Pagamento 100% seguro via Mercado Pago. Seus dados são protegidos por criptografia.
          </p>
        </div>

        <Button
          className="w-full bg-primary text-primary-foreground hover:bg-primary/90 h-11 font-semibold gap-2"
          onClick={handleSubmit}
          disabled={!isValid || loading}
        >
          {loading ? (
            <><Loader2 className="h-4 w-4 animate-spin" /> Redirecionando...</>
          ) : (
            <><CreditCard className="h-4 w-4" /> Ir para Pagamento</>
          )}
        </Button>
      </DialogContent>
    </Dialog>
  );
}
