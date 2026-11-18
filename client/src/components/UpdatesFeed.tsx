import { trpc } from "@/lib/trpc";
import { Link } from "wouter";
import { BookOpen, FileText, GraduationCap, Map, Megaphone, ArrowRight, Landmark } from "lucide-react";

type Item = {
  kind: "questions" | "blog" | "trail" | "course" | "announcement" | "concurso";
  date: string;
  title: string;
  detail?: string;
  href?: string;
};

const KIND_META: Record<Item["kind"], { icon: any; label: string; color: string }> = {
  questions:    { icon: BookOpen,      label: "Questões", color: "text-primary bg-primary/10 border-primary/30" },
  blog:         { icon: FileText,      label: "Blog",     color: "text-cyan-400 bg-cyan-500/10 border-cyan-500/30" },
  trail:        { icon: Map,           label: "Trilha",   color: "text-amber-400 bg-amber-500/10 border-amber-500/30" },
  course:       { icon: GraduationCap, label: "Curso",    color: "text-purple-400 bg-purple-500/10 border-purple-500/30" },
  announcement: { icon: Megaphone,     label: "Aviso",    color: "text-sky-400 bg-sky-500/10 border-sky-500/30" },
  concurso:     { icon: Landmark,      label: "Concurso", color: "text-emerald-400 bg-emerald-500/10 border-emerald-500/30" },
};

export function relativeTime(iso: string | null | undefined): string {
  if (!iso) return "hoje";
  const diffMs = Math.max(0, Date.now() - new Date(iso).getTime());
  const min = Math.floor(diffMs / 60000);
  if (min < 60) return min <= 5 ? "hoje" : `há ${min} min`;
  const h = Math.floor(min / 60);
  if (h < 24) return `há ${h}h`;
  const d = Math.floor(h / 24);
  if (d === 1) return "ontem";
  if (d < 30) return `há ${d} dias`;
  const m = Math.floor(d / 30);
  return m === 1 ? "há 1 mês" : `há ${m} meses`;
}

function useFeed() {
  return trpc.updates.feed.useQuery(undefined, { staleTime: 5 * 60 * 1000 });
}
export const useUpdatesFeed = useFeed;

/** Pill "ao vivo" para o hero: destaca a sincronização diária do Radar e do banco. */
export function LiveUpdatePill() {
  const { data } = useFeed();
  const s = data?.stats;
  if (!s) return null;
  return (
    <Link href="/concursos">
      <span className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border border-emerald-500/30 bg-emerald-500/10 text-emerald-300 text-sm font-sans hover:bg-emerald-500/15 transition-colors cursor-pointer">
        <span className="relative flex h-2 w-2">
          <span className="absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75 animate-ping" />
          <span className="relative inline-flex h-2 w-2 rounded-full bg-emerald-400" />
        </span>
        Radar atualizado {relativeTime(s.lastUpdateAt)}
        {(s.activeConcursos ?? 0) > 0 ? (
          <span className="text-emerald-200/80">· {s.activeConcursos} concursos abertos (Vet & Zoo)</span>
        ) : s.questionsLast30d > 0 ? (
          <span className="text-emerald-200/80">· +{s.questionsLast30d.toLocaleString("pt-BR")} questões em 30 dias</span>
        ) : null}
      </span>
    </Link>
  );
}

/** Linha do tempo das últimas novidades. */
export function UpdatesTimeline({ limit = 6, showStats = true }: { limit?: number; showStats?: boolean }) {
  const { data, isLoading } = useFeed();
  if (isLoading) {
    return <div className="h-48 rounded-2xl bg-card/40 border border-border/40 animate-pulse" />;
  }
  if (!data || data.items.length === 0) return null;
  const s = data.stats;
  const items = data.items.slice(0, limit) as Item[];

  return (
    <div className="grid gap-8 lg:grid-cols-[280px_1fr]">
      {showStats && (
        <div className="grid grid-cols-2 lg:grid-cols-1 gap-3 content-start">
          <Stat value={s.totalQuestions.toLocaleString("pt-BR")} label="questões comentadas no banco" />
          <Stat
            value={s.questionsLast30d > 0 ? `+${s.questionsLast30d.toLocaleString("pt-BR")}` : String(s.activeConcursos ?? 0)}
            label={s.questionsLast30d > 0 ? "adicionadas nos últimos 30 dias" : "concursos vigentes no Radar (Vet & Zoo)"}
            accent
          />
          <Stat value={String(s.disciplinesWithNew30d || 6)} label="grandes áreas contempladas" />
          <Stat value={relativeTime(s.lastUpdateAt) || "hoje"} label="última atualização do radar" accent />
        </div>
      )}

      <ol className="relative border-l border-border/50 ml-2 space-y-5">
        {items.map((it, i) => {
          const meta = KIND_META[it.kind];
          const Icon = meta.icon;
          const body = (
            <div className="group rounded-xl border border-border/40 bg-card/60 px-4 py-3 hover:border-primary/40 transition-colors">
              <div className="flex items-center gap-2 mb-1 text-xs font-sans">
                <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full border ${meta.color}`}>
                  <Icon className="h-3 w-3" /> {meta.label}
                </span>
                <span className="text-muted-foreground">{relativeTime(it.date)}</span>
              </div>
              <p className="font-medium text-sm text-foreground leading-snug flex items-center gap-1">
                {it.title}
                {it.href && <ArrowRight className="h-3.5 w-3.5 opacity-0 group-hover:opacity-100 transition-opacity text-primary" />}
              </p>
              {it.detail && <p className="text-xs text-muted-foreground mt-0.5 line-clamp-2">{it.detail}</p>}
            </div>
          );
          return (
            <li key={`${it.kind}-${it.date}-${i}`} className="pl-6 relative">
              <span className={`absolute -left-[7px] top-4 h-3.5 w-3.5 rounded-full border-2 border-background ${i === 0 ? "bg-emerald-400" : "bg-primary/60"}`} />
              {it.href ? <Link href={it.href}>{body}</Link> : body}
            </li>
          );
        })}
      </ol>
    </div>
  );
}

function Stat({ value, label, accent }: { value: string; label: string; accent?: boolean }) {
  return (
    <div className="rounded-xl border border-border/40 bg-card/60 p-4">
      <div className={`font-serif text-2xl font-bold ${accent ? "text-emerald-400" : "text-foreground"}`}>{value}</div>
      <div className="text-xs text-muted-foreground font-sans mt-0.5">{label}</div>
    </div>
  );
}
