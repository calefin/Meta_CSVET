import { useState } from "react";
import { trpc } from "@/lib/trpc";
import { useAuth } from "@/_core/hooks/useAuth";
import { Button } from "@/components/ui/button";
import { Shield, FileText } from "lucide-react";
import { Link } from "wouter";
import { toast } from "sonner";

export default function TosAcceptModal() {
  const { user, isAuthenticated, refresh } = useAuth();
  const [checked, setChecked] = useState(false);

  const acceptMutation = trpc.lgpd.acceptTerms.useMutation({
    onSuccess: () => {
      toast.success("Termos aceitos. Bem-vindo ao VetRank!");
      refresh();
    },
    onError: (e) => toast.error(e.message),
  });

  // Show only for authenticated users who haven't accepted TOS
  if (!isAuthenticated || !user || user.tosAcceptedAt) return null;

  return (
    <div className="fixed inset-0 z-[200] flex items-center justify-center bg-background/90 backdrop-blur-sm p-4">
      <div className="w-full max-w-md rounded-2xl border border-border/60 bg-card shadow-2xl">
        <div className="p-6 space-y-5">
          {/* Header */}
          <div className="flex items-start gap-3">
            <div className="w-10 h-10 rounded-xl bg-primary/20 border border-primary/30 flex items-center justify-center shrink-0">
              <Shield className="w-5 h-5 text-primary" />
            </div>
            <div>
              <h2 className="font-serif text-lg font-bold">Termos de Uso e Privacidade</h2>
              <p className="text-xs text-muted-foreground mt-0.5">Leitura obrigatória antes de continuar</p>
            </div>
          </div>

          {/* Summary */}
          <div className="rounded-xl bg-muted/30 border border-border/40 p-4 space-y-2.5 text-sm text-muted-foreground">
            <p>Ao usar o <strong className="text-foreground">VetRank</strong>, você concorda que:</p>
            <ul className="space-y-1.5 pl-4 list-disc text-xs leading-relaxed">
              <li>Seus dados pessoais são tratados conforme a <strong className="text-foreground">LGPD (Lei 13.709/2018)</strong></li>
              <li>O uso da plataforma é para fins educacionais e pessoais</li>
              <li>Assinaturas são cobradas conforme o plano escolhido, sem reembolso após 7 dias</li>
              <li>Você é responsável por manter sua senha segura e confidencial</li>
              <li>Conteúdos e questões são protegidos por direitos autorais</li>
            </ul>
            <div className="flex gap-3 pt-1">
              <Link
                href="/terms"
                className="inline-flex items-center gap-1 text-xs text-primary hover:underline"
              >
                <FileText className="h-3 w-3" />
                Ler Termos completos
              </Link>
              <Link
                href="/privacy"
                className="inline-flex items-center gap-1 text-xs text-primary hover:underline"
              >
                <Shield className="h-3 w-3" />
                Política de Privacidade
              </Link>
            </div>
          </div>

          {/* Checkbox */}
          <label className="flex items-start gap-3 cursor-pointer group">
            <input
              type="checkbox"
              checked={checked}
              onChange={e => setChecked(e.target.checked)}
              className="mt-0.5 h-4 w-4 accent-primary shrink-0"
            />
            <span className="text-sm leading-relaxed group-hover:text-foreground transition-colors">
              Li e concordo com os{" "}
              <Link href="/terms" className="text-primary hover:underline">Termos de Uso</Link>
              {" "}e a{" "}
              <Link href="/privacy" className="text-primary hover:underline">Política de Privacidade</Link>
              {" "}do VetRank.
            </span>
          </label>

          {/* Action */}
          <Button
            className="w-full bg-primary text-primary-foreground hover:bg-primary/90"
            onClick={() => acceptMutation.mutate()}
            disabled={!checked || acceptMutation.isPending}
          >
            {acceptMutation.isPending ? "Salvando..." : "Aceitar e Continuar"}
          </Button>

          <p className="text-[11px] text-center text-muted-foreground/60">
            Seu IP e data de aceite são registrados para fins legais (LGPD art. 7).
          </p>
        </div>
      </div>
    </div>
  );
}
