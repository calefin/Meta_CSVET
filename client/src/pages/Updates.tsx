import SEO from "@/components/SEO";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { LiveUpdatePill, UpdatesTimeline } from "@/components/UpdatesFeed";
import { useAuth } from "@/_core/hooks/useAuth";
import { Link } from "wouter";
import { Landmark, Megaphone, Sparkles } from "lucide-react";

export default function Updates() {
  const { user } = useAuth();
  const isStaff = !!user && ["admin", "superuser", "coordinator", "teacher"].includes(user.role);

  return (
    <div className="min-h-screen bg-background pt-20 pb-16">
      <SEO
        title="Novidades e Radar de Concursos — VetRank"
        description="Acompanhe em tempo real novos concursos de Medicina Veterinária e Zootecnia, questões comentadas, artigos científicos e trilhas no VetRank."
        canonical="/novidades"
      />
      <div className="container max-w-5xl mx-auto px-4">
        <div className="mb-10 space-y-3">
          <Badge className="bg-emerald-500/10 text-emerald-400 border-emerald-500/30 font-sans text-xs px-3 py-1">
            Atualização contínua · Veterinária & Zootecnia
          </Badge>
          <h1 className="font-serif text-4xl font-bold">Novidades do VetRank</h1>
          <p className="text-muted-foreground font-sans max-w-2xl">
            Acompanhe em tempo real os novos editais de concursos públicos para Medicina Veterinária e Zootecnia,
            pacotes de questões comentadas, artigos científicos e trilhas de aprendizado.
          </p>
          <div className="flex flex-wrap items-center gap-3 pt-1">
            <LiveUpdatePill />
            <Link href="/concursos">
              <Button size="sm" variant="outline" className="gap-1.5 border-emerald-500/40 text-emerald-400 hover:bg-emerald-500/10">
                <Landmark className="h-3.5 w-3.5" /> Ver Radar de Concursos
              </Button>
            </Link>
            <Link href="/exam">
              <Button size="sm" variant="ghost" className="gap-1.5 text-xs">
                <Sparkles className="h-3.5 w-3.5 text-primary" /> Fazer Simulado
              </Button>
            </Link>
          </div>
          {isStaff && (
            <div className="pt-2">
              <Link href="/announcements">
                <Button size="sm" variant="outline" className="gap-2 text-xs">
                  <Megaphone className="h-3.5 w-3.5" /> Publicar aviso manual (opcional)
                </Button>
              </Link>
            </div>
          )}
        </div>
        <UpdatesTimeline limit={24} />
      </div>
    </div>
  );
}
