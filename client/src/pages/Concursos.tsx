import { useMemo, useState } from "react";
import { Link } from "wouter";
import { trpc } from "@/lib/trpc";
import SEO from "@/components/SEO";
import ShareButtons from "@/components/ShareButtons";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import {
  Bell, Building2, CalendarClock, ChevronRight, Clock, FileDown, Landmark, MapPin, Search, Wallet,
} from "lucide-react";
import { useAuth } from "@/_core/hooks/useAuth";
import { useAuthModal } from "@/contexts/AuthModalContext";

const brl = (v: unknown) => v ? Number(v).toLocaleString("pt-BR", { style: "currency", currency: "BRL", maximumFractionDigits: 0 }) : null;
const dt = (d: unknown) => d ? new Date(d as string).toLocaleDateString("pt-BR", { timeZone: "America/Sao_Paulo" }) : null;

export function PrazoBadge({
  status,
  dias,
  diasParaAbrir,
  inicio,
}: {
  status: string;
  dias: number | null;
  diasParaAbrir?: number | null;
  inicio?: unknown;
}) {
  if (status === "vai_abrir") {
    const iniStr = dt(inicio);
    return (
      <Badge className="bg-sky-500/15 text-sky-400 border-sky-500/30">
        Vai abrir{diasParaAbrir ? ` em ${diasParaAbrir} dia${diasParaAbrir !== 1 ? "s" : ""}` : ""}{iniStr ? ` · ${iniStr}` : ""}
      </Badge>
    );
  }
  if (status === "encerrado") {
    return <Badge variant="outline" className="text-muted-foreground">Inscrições encerradas · Prova em andamento</Badge>;
  }
  if (status === "verificar" || dias == null) {
    return <Badge variant="outline" className="text-amber-400 border-amber-500/30">Edital vigente · Ver cronograma</Badge>;
  }
  if (dias <= 0) return <Badge className="bg-red-500/15 text-red-400 border-red-500/30">Último dia de inscrição!</Badge>;
  if (dias <= 7) return <Badge className="bg-red-500/15 text-red-400 border-red-500/30">Últimos {dias} dia{dias !== 1 ? "s" : ""}!</Badge>;
  return <Badge className="bg-emerald-500/15 text-emerald-400 border-emerald-500/30">Inscrições abertas · {dias} dias</Badge>;
}

export function AreaBadge({ area }: { area?: string }) {
  if (area === "zootecnia") {
    return <Badge variant="outline" className="text-amber-300 border-amber-500/30 bg-amber-500/5 text-xs">Zootecnia</Badge>;
  }
  if (area === "ambas") {
    return <Badge variant="outline" className="text-purple-300 border-purple-500/30 bg-purple-500/5 text-xs">Veterinária & Zootecnia</Badge>;
  }
  return <Badge variant="outline" className="text-emerald-300 border-emerald-500/30 bg-emerald-500/5 text-xs">Medicina Veterinária</Badge>;
}

type AbaStatus = "vigentes" | "aberto" | "vai_abrir" | "encerrado";

export default function Concursos() {
  const [uf, setUf] = useState<string>("todas");
  const [area, setArea] = useState<"todas" | "veterinaria" | "zootecnia">("todas");
  const [aba, setAba] = useState<AbaStatus>("vigentes");
  const [busca, setBusca] = useState("");
  const { data, isLoading } = trpc.concursos.list.useQuery({
    uf: uf === "todas" ? undefined : uf,
    area,
    incluirEncerrados: true,
  });
  const { isAuthenticated } = useAuth();
  const { openLogin } = useAuthModal();

  const todos = data?.itens ?? [];
  const r = data?.resumo;

  const itens = useMemo(() => {
    const q = busca.trim().toLowerCase();
    return todos.filter(c => {
      if (aba === "vigentes" && c.status === "encerrado") return false;
      if (aba === "aberto" && c.status !== "aberto") return false;
      if (aba === "vai_abrir" && c.status !== "vai_abrir") return false;
      if (aba === "encerrado" && c.status !== "encerrado") return false;
      if (!q) return true;
      return `${c.orgao} ${c.cidade ?? ""} ${c.banca ?? ""} ${JSON.stringify(c.cargos ?? [])}`.toLowerCase().includes(q);
    });
  }, [todos, aba, busca]);

  return (
    <div className="min-h-screen bg-background pt-20 pb-16">
      <SEO
        title="Concursos para Veterinário e Zootecnista Abertos — Radar VetRank"
        description="Concursos públicos e residências com vagas para Médico Veterinário e Zootecnista: inscrições abertas, editais previstos, salários, guias em PDF e simulados."
        canonical="/concursos"
      />
      <div className="container max-w-5xl mx-auto px-4">
        {/* Hero */}
        <div className="mb-8 flex flex-col md:flex-row md:items-end justify-between gap-4">
          <div className="space-y-3">
            <Badge className="bg-emerald-500/10 text-emerald-400 border-emerald-500/30 font-sans text-xs px-3 py-1">
              <span className="relative flex h-2 w-2 mr-2">
                <span className="absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75 animate-ping" />
                <span className="relative inline-flex h-2 w-2 rounded-full bg-emerald-400" />
              </span>
              Monitoramento diário · Veterinária & Zootecnia
            </Badge>
            <h1 className="font-serif text-4xl md:text-5xl font-bold">Radar de Concursos</h1>
            <p className="text-muted-foreground font-sans max-w-2xl">
              Editais municipais, estaduais, federais e Forças Armadas com vagas para <strong>Médico Veterinário</strong> e{" "}
              <strong>Zootecnista</strong>. Baixe o resumo verticalizado em PDF, compartilhe no Instagram/WhatsApp e treine com simulados específicos.
            </p>
          </div>
          <div className="shrink-0">
            <ShareButtons
              compact
              url="/concursos"
              text={`📢 Radar VetRank: ${r?.vigentes ?? "Dezenas de"} concursos abertos para Medicina Veterinária e Zootecnia no Brasil!`}
              storyImageUrl="/api/og/concursos-semana/story.png"
              feedImageUrl="/api/og/concursos-semana.png"
            />
          </div>
        </div>

        {/* Números */}
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
          <Stat icon={Landmark} value={r ? String(r.abertos) : "—"} label="inscrições abertas agora" accent="text-emerald-400" />
          <Stat icon={Clock} value={r ? String(r.vaiAbrir ?? 0) : "—"} label="editais que vão abrir" accent="text-sky-400" />
          <Stat icon={CalendarClock} value={r ? String(r.encerrandoEm7Dias) : "—"} label="encerram nos próximos 7 dias" accent="text-red-400" />
          <Stat icon={Wallet} value={brl(r?.maiorSalario) ?? "—"} label="maior salário anunciado" accent="text-emerald-400" />
        </div>

        {/* Abas de Status (Ciclo de vida claro: Vigentes, Abertos, Vai Abrir, Encerrados) */}
        <div className="flex flex-wrap gap-2 mb-4 border-b border-border/40 pb-3">
          <Button
            size="sm"
            variant={aba === "vigentes" ? "default" : "outline"}
            onClick={() => setAba("vigentes")}
            className="gap-1.5"
          >
            Todos vigentes ({r?.vigentes ?? 0})
          </Button>
          <Button
            size="sm"
            variant={aba === "aberto" ? "default" : "outline"}
            onClick={() => setAba("aberto")}
            className="gap-1.5"
          >
            Inscrições abertas ({r?.abertos ?? 0})
          </Button>
          <Button
            size="sm"
            variant={aba === "vai_abrir" ? "default" : "outline"}
            onClick={() => setAba("vai_abrir")}
            className="gap-1.5"
          >
            Vai abrir ({r?.vaiAbrir ?? 0})
          </Button>
          <Button
            size="sm"
            variant={aba === "encerrado" ? "default" : "outline"}
            onClick={() => setAba("encerrado")}
            className="gap-1.5"
          >
            Inscrições encerradas ({r?.encerrados ?? 0})
          </Button>
        </div>

        {/* Filtros de Busca, Área e Estado */}
        <div className="flex flex-col sm:flex-row gap-3 mb-6">
          <div className="relative flex-1">
            <Search className="h-4 w-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
            <input
              value={busca}
              onChange={e => setBusca(e.target.value)}
              placeholder="Buscar órgão, cidade, cargo ou banca…"
              className="w-full h-10 rounded-md border border-border/60 bg-card pl-9 pr-3 text-sm font-sans"
            />
          </div>
          <Select value={area} onValueChange={(v: any) => setArea(v)}>
            <SelectTrigger className="sm:w-52 h-10"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="todas">Veterinária + Zootecnia</SelectItem>
              <SelectItem value="veterinaria">Medicina Veterinária</SelectItem>
              <SelectItem value="zootecnia">Zootecnia</SelectItem>
            </SelectContent>
          </Select>
          <Select value={uf} onValueChange={setUf}>
            <SelectTrigger className="sm:w-44 h-10"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="todas">Todos os estados</SelectItem>
              {(r?.ufs ?? []).map(u => <SelectItem key={u} value={u}>{u}</SelectItem>)}
            </SelectContent>
          </Select>
        </div>

        {/* Lista */}
        {isLoading ? (
          <div className="space-y-3">{[0, 1, 2].map(i => <div key={i} className="h-36 rounded-2xl bg-card/40 border border-border/40 animate-pulse" />)}</div>
        ) : itens.length === 0 ? (
          <div className="rounded-2xl border border-border/40 bg-card/60 p-10 text-center text-muted-foreground">
            Nenhum concurso encontrado com esse filtro.
          </div>
        ) : (
          <div className="space-y-3">
            {itens.map((c: any) => {
              const cargos = (c.cargos as any[] | null) ?? [];
              return (
                <div key={c.slug} className="rounded-2xl border border-border/40 bg-card/60 p-5 hover:border-primary/40 transition-colors">
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                      <div className="flex flex-wrap items-center gap-2 mb-1.5">
                        <PrazoBadge
                          status={c.status}
                          dias={c.diasRestantes}
                          diasParaAbrir={c.diasParaAbrir}
                          inicio={c.inscricoesInicio}
                        />
                        <AreaBadge area={c.area} />
                        {c.uf && (
                          <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
                            <MapPin className="h-3 w-3" />{c.cidade ? `${c.cidade} · ` : ""}{c.uf}
                          </span>
                        )}
                      </div>
                      <Link href={`/concursos/${c.slug}`}>
                        <h2 className="font-serif text-xl font-bold hover:text-primary cursor-pointer">{c.orgao}</h2>
                      </Link>
                      <p className="text-sm text-muted-foreground mt-1">
                        {cargos.slice(0, 3).map(x => x.nome).join(" · ") || "Médico Veterinário / Zootecnista"}
                      </p>
                    </div>
                    <div className="text-right text-sm shrink-0">
                      {c.salarioMax && <div className="font-semibold text-emerald-400">até {brl(c.salarioMax)}</div>}
                      {c.vagasVet && <div className="text-muted-foreground">{c.vagasVet}</div>}
                      {c.inscricoesFim && (
                        <div className="text-xs text-muted-foreground">
                          {c.status === "vai_abrir" && c.inscricoesInicio
                            ? `inscrições: ${dt(c.inscricoesInicio)} a ${dt(c.inscricoesFim)}`
                            : `inscrições até ${dt(c.inscricoesFim)}`}
                        </div>
                      )}
                    </div>
                  </div>
                  <div className="flex flex-wrap items-center justify-between gap-3 mt-4">
                    <span className="text-xs text-muted-foreground inline-flex items-center gap-1">
                      <Building2 className="h-3 w-3" /> {c.banca ? `Banca: ${c.banca}` : "Banca: ver edital"}
                    </span>
                    <div className="flex flex-wrap items-center gap-2">
                      <ShareButtons
                        compact
                        url={`/concursos/${c.slug}`}
                        text={`📢 Concurso ${c.orgao}${c.uf ? ` (${c.uf})` : ""}${c.salarioMax ? ` — salário até ${brl(c.salarioMax)}` : ""}${c.inscricoesFim ? ` · inscrições até ${dt(c.inscricoesFim)}` : ""}`}
                        storyImageUrl={`/api/og/concurso/${c.slug}/story.png`}
                        feedImageUrl={`/api/og/concurso/${c.slug}/feed.png`}
                      />
                      <a href={`/api/concursos/${c.slug}/guia.pdf`} target="_blank" rel="noopener noreferrer">
                        <Button size="sm" variant="outline" className="gap-1 border-emerald-500/40 text-emerald-400 hover:bg-emerald-500/10">
                          <FileDown className="h-3.5 w-3.5" /> Guia & Edital PDF
                        </Button>
                      </a>
                      <Link href={`/exam?concurso=${c.slug}`}>
                        <Button size="sm" variant="outline" className="gap-1 border-primary/40 text-primary">Simular</Button>
                      </Link>
                      <Link href={`/concursos/${c.slug}`}>
                        <Button size="sm" variant="outline" className="gap-1">Detalhes <ChevronRight className="h-3.5 w-3.5" /></Button>
                      </Link>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        )}

        {/* CTA */}
        <div className="mt-10 rounded-2xl border border-primary/30 bg-primary/5 p-6 flex flex-col md:flex-row md:items-center gap-4 justify-between">
          <div>
            <h3 className="font-serif text-xl font-bold flex items-center gap-2">
              <Bell className="h-5 w-5 text-primary" /> Receba os novos editais e simulados toda semana
            </h3>
            <p className="text-sm text-muted-foreground mt-1">
              Quem tem conta no VetRank recebe gratuitamente o alerta semanal de concursos para Medicina Veterinária e Zootecnia e tem 7 dias de acesso Premium liberado.
            </p>
          </div>
          {isAuthenticated
            ? <Link href="/exam"><Button className="shrink-0">Treinar com simulados</Button></Link>
            : <Button className="shrink-0" onClick={() => openLogin()}>Criar conta grátis (7 dias Premium)</Button>}
        </div>

        <p className="text-xs text-muted-foreground mt-8 leading-relaxed">
          Curadoria oficial <b>Radar VetRank</b> a partir de diários oficiais e bancas organizadoras. Datas, vagas e retificações podem sofrer alterações — <b>confira sempre o edital oficial</b> da banca responsável antes de efetuar sua inscrição.
        </p>
      </div>
    </div>
  );
}

function Stat({ icon: Icon, value, label, accent }: { icon: any; value: string; label: string; accent?: string }) {
  return (
    <div className="rounded-xl border border-border/40 bg-card/60 p-4">
      <Icon className="h-4 w-4 text-muted-foreground mb-2" />
      <div className={`font-serif text-2xl font-bold ${accent ?? "text-foreground"}`}>{value}</div>
      <div className="text-xs text-muted-foreground font-sans mt-0.5">{label}</div>
    </div>
  );
}
