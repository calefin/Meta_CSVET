import { trpc } from "@/lib/trpc";
import { useAuth } from "@/_core/hooks/useAuth";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Loader2, ShoppingBag, Snowflake, Package } from "lucide-react";
import { toast } from "sonner";
import { useAuthModal } from "@/contexts/AuthModalContext";

const CATEGORY_LABELS: Record<string, { pt: string; icon: string }> = {
  utility:   { pt: "Utilidade",   icon: "🛠️" },
  cosmetic:  { pt: "Cosmético",   icon: "✨" },
  luck:      { pt: "Sorte",       icon: "🎲" },
  education: { pt: "Educação",    icon: "📚" },
};

export default function Shop() {
  const { isAuthenticated, user } = useAuth();
  const { openLogin } = useAuthModal();
  const utils = trpc.useUtils();

  const { data: items, isLoading } = trpc.gamification.shopList.useQuery({ productType: "gamification" });
  const { data: profile } = trpc.gamification.myProfile.useQuery(undefined, { enabled: isAuthenticated });

  const buyMutation = trpc.gamification.shopBuy.useMutation({
    onSuccess: () => {
      toast.success("Compra realizada! ✅");
      utils.gamification.myProfile.invalidate();
      utils.gamification.myCoins.invalidate();
    },
    onError: (e) => toast.error(e.message),
  });

  const coins = profile?.user.coins ?? 0;
  const streakFreezes = profile?.user.streakFreezes ?? 0;

  if (!isAuthenticated) {
    return (
      <div className="min-h-screen flex items-center justify-center px-4">
        <Card className="p-8 text-center max-w-sm">
          <ShoppingBag className="w-12 h-12 text-primary mx-auto mb-4" />
          <h2 className="text-xl font-bold mb-2">Faça login para acessar a loja</h2>
          <Button className="w-full mt-2" onClick={() => openLogin()}>Entrar</Button>
        </Card>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-background pt-20 pb-12 px-4">
      <div className="max-w-3xl mx-auto">
        {/* Header */}
        <div className="flex items-center justify-between mb-6">
          <div>
            <h1 className="text-2xl font-bold" style={{ fontFamily: "Playfair Display, serif" }}>
              🛒 Loja VetRank
            </h1>
            <p className="text-sm text-muted-foreground">Gaste seus VetCoins em itens úteis</p>
          </div>
          <div className="text-right bg-card border border-border/50 rounded-xl px-4 py-2">
            <p className="text-xs text-muted-foreground">Saldo</p>
            <p className="text-xl font-bold text-amber-400">🪙 {coins.toLocaleString()}</p>
          </div>
        </div>

        {/* Freeze status */}
        <Card className="mb-6 border-cyan-500/20 bg-cyan-500/5">
          <CardContent className="p-4 flex items-center gap-4">
            <Snowflake className="w-8 h-8 text-cyan-400 shrink-0" />
            <div className="flex-1">
              <p className="text-sm font-medium">Congelamentos de Ofensiva</p>
              <p className="text-xs text-muted-foreground">Protegem seu streak quando você perde um dia. Máximo de 2.</p>
            </div>
            <div className="flex gap-1.5">
              {[0, 1].map(i => (
                <div key={i} className={`w-8 h-8 rounded-lg border flex items-center justify-center text-sm ${
                  i < streakFreezes ? "bg-cyan-500/20 border-cyan-500/40" : "border-border/30 opacity-40"
                }`}>
                  {i < streakFreezes ? "❄️" : "○"}
                </div>
              ))}
            </div>
          </CardContent>
        </Card>

        {/* Items */}
        {isLoading ? (
          <div className="flex justify-center py-20">
            <Loader2 className="w-8 h-8 animate-spin text-primary" />
          </div>
        ) : (
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {(items ?? []).map((item) => {
              const cat = CATEGORY_LABELS[item.category] ?? { pt: item.category, icon: "📦" };
              const canAfford = coins >= item.cost;
              const isFreeze = item.itemType === "freeze";
              const freezeFull = isFreeze && streakFreezes >= 2;

              return (
                <Card key={item.id} className={`border-border/50 transition-all ${canAfford && !freezeFull ? "hover:border-primary/40" : "opacity-70"}`}>
                  <CardContent className="p-5">
                    <div className="flex items-start gap-4">
                      <div className="text-4xl shrink-0">{item.icon ?? "📦"}</div>
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2 mb-1">
                          <h3 className="font-semibold text-sm">{item.namePt}</h3>
                          <Badge variant="outline" className="text-xs font-sans shrink-0">
                            {cat.icon} {cat.pt}
                          </Badge>
                        </div>
                        <p className="text-xs text-muted-foreground mb-3">{item.descriptionPt}</p>
                        <div className="flex items-center justify-between">
                          <span className={`font-bold text-sm ${canAfford ? "text-amber-400" : "text-red-400"}`}>
                            🪙 {item.cost.toLocaleString()} VetCoins
                          </span>
                          <Button
                            size="sm"
                            className="text-xs h-7"
                            disabled={!canAfford || freezeFull || buyMutation.isPending}
                            onClick={() => buyMutation.mutate({ itemId: item.id })}
                          >
                            {buyMutation.isPending ? <Loader2 className="w-3 h-3 animate-spin" /> :
                             freezeFull ? "Máx. atingido" :
                             !canAfford ? "Coins insuf." : "Comprar"}
                          </Button>
                        </div>
                      </div>
                    </div>
                  </CardContent>
                </Card>
              );
            })}
          </div>
        )}

        {/* Footer info */}
        <div className="mt-8 p-4 rounded-xl bg-accent/20 border border-border/30">
          <p className="text-xs text-muted-foreground text-center font-sans">
            💡 <strong>Como ganhar VetCoins?</strong> Você recebe <strong>10 VetCoins</strong> a cada <strong>100 XP</strong> ganhos.
            Acerte questões, complete simulados e mantenha sua ofensiva para acumular.
          </p>
        </div>
      </div>
    </div>
  );
}
