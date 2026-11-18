import { Link, useParams } from "wouter";
import { trpc } from "@/lib/trpc";
import SEO from "@/components/SEO";
import ShareButtons from "@/components/ShareButtons";
import { AreaBadge, PrazoBadge } from "./Concursos";
import { Button } from "@/components/ui/button";
import { ArrowLeft, ExternalLink, FileDown, FlaskConical, Loader2, MapPin, Sparkles } from "lucide-react";
import { useAuth } from "@/_core/hooks/useAuth";
import { useAuthModal } from "@/contexts/AuthModalContext";

const brl = (v: unknown) => v ? Number(v).toLocaleString("pt-BR", { style: "currency", currency: "BRL", maximumFractionDigits: 0 }) : null;
const dt = (d: unknown) => d ? new Date(d as string).toLocaleDateString("pt-BR", { timeZone: "America/Sao_Paulo" }) : null;

export default function ConcursoDetail() {
  const { slug } = useParams<{ slug: string }>();
  const { data: c, isLoading, error } = trpc.concursos.bySlug.useQuery({ slug: slug ?? "" }, { enabled: !!slug, retry: false });
  const { isAuthenticated } = useAuth();
  const { openLogin } = useAuthModal();

  if (isLoading) return <div className="min-h-screen flex items-center justify-center"><Loader2 className="h-8 w-8 animate-spin text-primary" /></div>;
  if (error || !c) {
    return (
      <div className="min-h-screen flex flex-col items-center justify-center gap-4 px-4 text-center">
        <p className="text-muted-foreground">Concurso não encontrado ou removido.</p>
        <Link href="/concursos"><Button variant="outline">Ver concursos vigentes</Button></Link>
      </div>
    );
  }

  const cargos = (c.cargos as any[] | null) ?? [];
  const areaLabel = c.area === "zootecnia" ? "Zootecnista" : c.area === "ambas" ? "Veterinário e Zootecnista" : "Médico Veterinário";
  const shareText = `📢 Concurso para ${areaLabel}: ${c.orgao}${c.uf ? ` (${c.uf})` : ""}${c.vagasVet ? ` — ${c.vagasVet}` : ""}${c.salarioMax ? `, até ${brl(c.salarioMax)}` : ""}${c.inscricoesFim ? `. Inscrições até ${dt(c.inscricoesFim)}` : ""}`;
  const fatos: [string, string | null][] = [
    ["Inscrições", c.inscricoesInicio || c.inscricoesFim ? `${dt(c.inscricoesInicio) ?? "Aberto"} a ${dt(c.inscricoesFim) ?? "ver edital"}` : null],
    ["Taxa de Inscrição", c.taxa],
    ["Banca Organizadora", c.banca],
    ["Edital", c.editalNumero],
    ["Escolaridade Exigida", c.escolaridade],
    ["Etapas da Seleção", c.etapas],
  ];

  return (
    <div className="min-h-screen bg-background pt-20 pb-16">
      <SEO
        title={`Concurso ${c.orgao}${c.uf ? ` (${c.uf})` : ""} — ${areaLabel}`}
        description={shareText.replace(/^📢 /, "")}
        canonical={`/concursos/${c.slug}`}
      />
      <div className="container max-w-3xl mx-auto px-4">
        <Link href="/concursos">
          <span className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground cursor-pointer mb-6">
            <ArrowLeft className="h-4 w-4" /> Radar de Concursos
          </span>
        </Link>

        <div className="space-y-3 mb-6">
          <div className="flex flex-wrap items-center gap-2">
            <PrazoBadge
              status={c.status}
              dias={c.diasRestantes}
              diasParaAbrir={(c as any).diasParaAbrir}
              inicio={c.inscricoesInicio}
            />
            <AreaBadge area={(c as any).area} />
            {c.uf && (
              <span className="inline-flex items-center gap-1 text-sm text-muted-foreground">
                <MapPin className="h-3.5 w-3.5" />{c.cidade ? `${c.cidade} · ` : ""}{c.uf}
              </span>
            )}
          </div>
          <h1 className="font-serif text-3xl md:text-4xl font-bold">{c.orgao}</h1>
          {c.resumo && <p className="text-muted-foreground leading-relaxed">{c.resumo}</p>}
        </div>

        {/* Card de Download do Guia & Edital Verticalizado (PDF) */}
        <div className="rounded-2xl border border-emerald-500/40 bg-emerald-500/10 p-5 mb-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <h2 className="font-serif text-lg font-bold text-emerald-300 flex items-center gap-2">
              <FileDown className="h-5 w-5" /> Resumo do Edital + Checklist Verticalizado (PDF)
            </h2>
            <p className="text-xs text-muted-foreground mt-1">
              Baixe gratuitamente a ficha completa deste concurso com o cronograma e o checklist verticalizado dos tópicos mais cobrados para imprimir ou enviar para a turma.
            </p>
          </div>
          <a href={`/api/concursos/${c.slug}/guia.pdf`} target="_blank" rel="noopener noreferrer" className="shrink-0">
            <Button className="gap-2 bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-semibold w-full sm:w-auto">
              <FileDown className="h-4 w-4" /> Baixar Guia PDF
            </Button>
          </a>
        </div>

        <div className="rounded-2xl border border-border/40 bg-card/60 p-5 mb-5">
          <h2 className="font-serif text-lg font-bold mb-3">Cargos contemplados</h2>
          {cargos.length ? (
            <div className="divide-y divide-border/40">
              {cargos.map((x, i) => (
                <div key={i} className="py-2.5 flex flex-wrap justify-between gap-2 text-sm">
                  <span className="font-medium">{x.nome}</span>
                  <span className="text-muted-foreground">{[x.vagas, x.salario, x.cargaHoraria].filter(Boolean).join(" · ") || "ver edital"}</span>
                </div>
              ))}
            </div>
          ) : <p className="text-sm text-muted-foreground">Detalhes no edital oficial.</p>}
        </div>

        <div className="rounded-2xl border border-border/40 bg-card/60 p-5 mb-5 grid sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
          {fatos.filter(([, v]) => v).map(([k, v]) => (
            <div key={k}>
              <div className="text-xs text-muted-foreground">{k}</div>
              <div className="font-medium">{v}</div>
            </div>
          ))}
        </div>

        {(c.orgaoUrl || c.bancaUrl) && (
          <div className="flex flex-wrap gap-2 mb-8">
            {c.orgaoUrl && (
              <a href={c.orgaoUrl} target="_blank" rel="noopener noreferrer nofollow">
                <Button variant="outline" size="sm" className="gap-1.5">
                  <ExternalLink className="h-3.5 w-3.5" /> Página oficial do órgão
                </Button>
              </a>
            )}
            {c.bancaUrl && (
              <a href={c.bancaUrl} target="_blank" rel="noopener noreferrer nofollow">
                <Button variant="outline" size="sm" className="gap-1.5">
                  <ExternalLink className="h-3.5 w-3.5" /> Inscrição / Site da banca
                </Button>
              </a>
            )}
          </div>
        )}

        <div className="rounded-2xl border border-emerald-500/30 bg-emerald-500/5 p-5 mb-8">
          <p className="font-semibold mb-1">Divulgue no Instagram ou envie para a turma no WhatsApp:</p>
          <p className="text-xs text-muted-foreground mb-3">
            Gere automaticamente a arte 1080×1920 para Story ou 1080×1080 para Feed com todos os dados deste edital.
          </p>
          <ShareButtons
            url={`/concursos/${c.slug}`}
            text={shareText}
            storyImageUrl={`/api/og/concurso/${c.slug}/story.png`}
            feedImageUrl={`/api/og/concurso/${c.slug}/feed.png`}
          />
        </div>

        <div className="rounded-2xl border border-primary/30 bg-primary/5 p-6">
          <h3 className="font-serif text-xl font-bold mb-1 flex items-center gap-2">
            <FlaskConical className="h-5 w-5 text-primary" /> Simulado Exclusivo — {c.orgao}
          </h3>
          <p className="text-sm text-muted-foreground mb-4">
            Simulado cronometrado de 40 questões montado sob medida para o perfil deste concurso ({areaLabel}) — priorizando questões da banca {c.banca || "organizadora"} e os temas de maior incidência, com gabarito comentado alternativa por alternativa.
          </p>
          <div className="flex flex-wrap gap-3">
            {isAuthenticated ? (
              <Link href={`/exam?concurso=${c.slug}`}>
                <Button className="gap-1.5">
                  <Sparkles className="h-4 w-4" /> Fazer o simulado deste concurso
                </Button>
              </Link>
            ) : (
              <Button onClick={() => openLogin()} className="gap-1.5">
                <Sparkles className="h-4 w-4" /> Criar conta grátis e fazer o simulado
              </Button>
            )}
            <Link href="/bank">
              <Button variant="outline">Treinar no Banco (+73.800 questões)</Button>
            </Link>
          </div>
        </div>

        <p className="text-xs text-muted-foreground mt-8">
          Curadoria oficial Radar VetRank a partir de diários oficiais e editais públicos. Cronogramas e vagas podem sofrer retificações — confira sempre o edital oficial no portal da banca organizadora.
        </p>
      </div>
    </div>
  );
}
