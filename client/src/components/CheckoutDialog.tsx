import { useState, useEffect } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { trpc } from "@/lib/trpc";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Loader2, CreditCard } from "lucide-react";
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription,
} from "@/components/ui/dialog";
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from "@/components/ui/select";
import { PLAN_NAMES, PLAN_PRICES } from "@shared/plans";
import type { PlanKey } from "@shared/plans";

const COUNTRIES = [
  { code: "BR", label: "Brasil" },
  { code: "AR", label: "Argentina" },
  { code: "CO", label: "Colômbia" },
  { code: "MX", label: "México" },
  { code: "PT", label: "Portugal" },
  { code: "US", label: "Estados Unidos" },
  { code: "OTHER", label: "Outro" },
];

const schema = z.object({
  fullName: z.string().min(3, "Nome completo obrigatório"),
  cpf: z.string().regex(/^\d{3}\.\d{3}\.\d{3}-\d{2}$/, "CPF inválido (use o formato 000.000.000-00)"),
  phone: z.string().min(10, "Telefone obrigatório"),
  country: z.string().length(2, "Selecione um país").or(z.literal("OTHER")),
});

type FormData = z.infer<typeof schema>;

function formatCPF(v: string) {
  return v.replace(/\D/g, "").slice(0, 11)
    .replace(/(\d{3})(\d)/, "$1.$2")
    .replace(/(\d{3})\.(\d{3})(\d)/, "$1.$2.$3")
    .replace(/(\d{3})\.(\d{3})\.(\d{3})(\d)/, "$1.$2.$3-$4");
}

function formatPhone(v: string) {
  const d = v.replace(/\D/g, "").slice(0, 11);
  if (d.length <= 2)  return d;
  if (d.length <= 7)  return `(${d.slice(0,2)}) ${d.slice(2)}`;
  if (d.length <= 10) return `(${d.slice(0,2)}) ${d.slice(2,6)}-${d.slice(6)}`;
  return `(${d.slice(0,2)}) ${d.slice(2,7)}-${d.slice(7)}`;
}

interface Props {
  open: boolean;
  planKey: Exclude<PlanKey, "free"> | null;
  onClose: () => void;
}

export default function CheckoutDialog({ open, planKey, onClose }: Props) {
  const [isSubmitting, setIsSubmitting] = useState(false);

  const { data: profile } = trpc.billing.getBillingProfile.useQuery(undefined, { enabled: open });
  const createCheckout = trpc.billing.createCheckoutUrl.useMutation();

  const { register, handleSubmit, setValue, watch, reset, formState: { errors } } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: { country: "BR" },
  });

  // Pre-fill from saved profile
  useEffect(() => {
    if (profile) {
      const cpfRaw = profile.cpf ?? "";
      const cpfFormatted = cpfRaw.length === 11
        ? cpfRaw.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4")
        : cpfRaw;
      reset({
        fullName: profile.fullName ?? "",
        cpf:     cpfFormatted,
        phone:   profile.phone ?? "",
        country: profile.country ?? "BR",
      });
    }
  }, [profile, reset]);

  async function onSubmit(data: FormData) {
    if (!planKey) return;
    setIsSubmitting(true);
    try {
      const { checkoutUrl } = await createCheckout.mutateAsync({
        planKey,
        payer: data,
      });
      window.location.href = checkoutUrl;
    } catch (e: any) {
      toast.error(e?.message || "Erro ao iniciar checkout");
      setIsSubmitting(false);
    }
  }

  if (!planKey) return null;

  return (
    <Dialog open={open} onOpenChange={(o) => { if (!o) onClose(); }}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <CreditCard className="w-5 h-5 text-primary" />
            Assinar plano {PLAN_NAMES[planKey]}
          </DialogTitle>
          <DialogDescription>
            R$ {PLAN_PRICES[planKey]?.toFixed(2)}/mês · Recorrência mensal · Cancele quando quiser
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4 mt-2">
          <div className="space-y-1">
            <Label className="text-xs">Nome completo *</Label>
            <Input
              placeholder="João da Silva"
              {...register("fullName")}
            />
            {errors.fullName && <p className="text-xs text-destructive">{errors.fullName.message}</p>}
          </div>

          <div className="space-y-1">
            <Label className="text-xs">CPF *</Label>
            <Input
              placeholder="000.000.000-00"
              value={watch("cpf") ?? ""}
              onChange={(e) => setValue("cpf", formatCPF(e.target.value), { shouldValidate: true })}
            />
            {errors.cpf && <p className="text-xs text-destructive">{errors.cpf.message}</p>}
          </div>

          <div className="space-y-1">
            <Label className="text-xs">Telefone / WhatsApp *</Label>
            <Input
              placeholder="(11) 99999-9999"
              value={watch("phone") ?? ""}
              onChange={(e) => setValue("phone", formatPhone(e.target.value), { shouldValidate: true })}
            />
            {errors.phone && <p className="text-xs text-destructive">{errors.phone.message}</p>}
          </div>

          <div className="space-y-1">
            <Label className="text-xs">País *</Label>
            <Select
              value={watch("country")}
              onValueChange={(v) => setValue("country", v, { shouldValidate: true })}
            >
              <SelectTrigger>
                <SelectValue placeholder="Selecione seu país" />
              </SelectTrigger>
              <SelectContent>
                {COUNTRIES.map((c) => (
                  <SelectItem key={c.code} value={c.code}>{c.label}</SelectItem>
                ))}
              </SelectContent>
            </Select>
            {errors.country && <p className="text-xs text-destructive">{errors.country.message}</p>}
          </div>

          <p className="text-xs text-muted-foreground">
            Seus dados são usados para identificação no pagamento e emissão de recibos. Você será redirecionado ao MercadoPago para finalizar.
          </p>

          <div className="flex gap-2 pt-1">
            <Button type="button" variant="outline" className="flex-1" onClick={onClose} disabled={isSubmitting}>
              Cancelar
            </Button>
            <Button type="submit" className="flex-1" disabled={isSubmitting}>
              {isSubmitting ? <Loader2 className="w-4 h-4 mr-2 animate-spin" /> : null}
              {isSubmitting ? "Redirecionando..." : "Ir para pagamento"}
            </Button>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  );
}
