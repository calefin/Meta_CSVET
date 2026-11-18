/**
 * QuestionRenderer.tsx
 * Universal student-facing question display component.
 * Handles all 11 question formats: multiple_choice, assertion_reason,
 * complex_multiple_choice, matching, true_false, ordering, cloze,
 * clinical_case, image_analysis, interpretation, discursive.
 */
import { Badge } from "@/components/ui/badge";
import { type QuestionType } from "@/components/QuestionFormats";
import { cn } from "@/lib/utils";
import { MODEL_MAP } from "@shared/questionModels";
import { trpc } from "@/lib/trpc";
import { descricaoGabaritoM5 } from "@/components/QuestionModelForms";

// ─── Responsive image helper ──────────────────────────────────────────────────

/**
 * Renders a responsive <picture> element.
 * New uploads store the _md.webp variant as primary URL.
 * sm (480px) and lg (1440px) variants are inferred by suffix replacement.
 * Legacy single-image URLs (no _md.webp suffix) are served as-is.
 */
function QuestionImage({ src, alt, className }: { src: string; alt: string; className?: string }) {
  const isVariant = src.includes("_md.webp");
  if (!isVariant) {
    return <img src={src} alt={alt} className={className} loading="lazy" decoding="async" />;
  }
  const sm = src.replace("_md.webp", "_sm.webp");
  const lg = src.replace("_md.webp", "_lg.webp");
  return (
    <picture>
      <source media="(max-width: 480px)"  srcSet={sm} type="image/webp" />
      <source media="(max-width: 960px)"  srcSet={src} type="image/webp" />
      <source media="(min-width: 961px)"  srcSet={lg} type="image/webp" />
      <img src={src} alt={alt} className={className} loading="lazy" decoding="async" />
    </picture>
  );
}

// ─── Types ────────────────────────────────────────────────────────────────────

export interface QuestionOption {
  id: string;
  textPt: string;
  textEn?: string;
}

export interface QuestionData {
  id: number;
  textPt: string;
  textEn?: string;
  questionType?: QuestionType | string;
  modelId?: string;
  options: QuestionOption[];
  correctOption: string;
  explanationPt?: string;
  explanationEn?: string;
  assertion1?: string;
  assertion2?: string;
  // M5 booleanos
  a1Verdadeira?: boolean | null;
  a2Verdadeira?: boolean | null;
  relacaoCausal?: boolean | null;
  // M10
  grupoId?: string | null;
  posicaoBloco?: number | null;
  formatData?: any;
  imageUrl?: string;
  difficulty?: string;
  disciplineName?: string;
  subjectTag?: string;
  author?: string;
  banca?: string;
  year?: number;
  isAnulada?: boolean;
  isDesatualizada?: boolean;
}

interface QuestionRendererProps {
  question: QuestionData;
  selectedOption?: string | null;
  answered?: boolean;
  onAnswer?: (optionId: string) => void;
  language?: "pt" | "en";
  showExplanation?: boolean;
  /** If true, show the correct answer highlight even before user answers */
  revealAnswer?: boolean;
}

// ─── Main Component ───────────────────────────────────────────────────────────

// ─── M3: Assertivas sub-renderer ─────────────────────────────────────────────

function AssertiveRenderer({ questionId, fd, answered, lang, textPt }: {
  questionId: number; fd: any; answered: boolean; lang: string; textPt?: string;
}) {
  const { data: assertivas = [] } = trpc.questions.getAssertivas.useQuery(
    { questionId },
    { staleTime: 60_000 }
  );

  // Priority: 1) relational DB data, 2) formatData.items, 3) parse roman numerals from textPt
  const items: Array<{ id: string; label: string; textPt: string; correta?: boolean }> =
    assertivas.length > 0
      ? assertivas.map(a => ({ id: a.label, label: a.label, textPt: a.textPt, correta: a.correta }))
      : (fd.items ?? []).length > 0
        ? (fd.items as any[]).map((i: any) => ({ id: i.id, label: i.id, textPt: i.textPt, correta: undefined }))
        : (() => {
            if (!textPt) return [];
            const parsed = parseRomanNumeralList(textPt);
            if (!parsed?.items.length) return [];
            return parsed.items.map(it => ({ id: it.numeral, label: it.numeral, textPt: it.text, correta: undefined }));
          })();

  if (!items.length) return null;

  return (
    <div className="space-y-2 p-3 rounded-xl border border-border/40 bg-accent/10">
      <p className="text-[11px] font-semibold text-muted-foreground uppercase tracking-wide">
        {lang === "pt" ? "Analise as afirmativas:" : "Analyze the statements:"}
      </p>
      {items.map((item) => {
        const showVF = answered && item.correta !== undefined;
        return (
          <div key={item.id} className={cn(
            "flex gap-3 rounded-lg p-2.5 border transition-colors",
            showVF
              ? item.correta
                ? "border-emerald-500/40 bg-emerald-500/8"
                : "border-red-500/40 bg-red-500/8"
              : "border-border/30 bg-background/50"
          )}>
            <span className="text-xs font-black text-primary mt-0.5 w-6 shrink-0">{item.label}.</span>
            <span className="text-sm flex-1">{lang === "pt" ? item.textPt : item.textPt}</span>
            {showVF && (
              <span className={cn(
                "text-xs font-black shrink-0 mt-0.5 px-1.5 py-0.5 rounded",
                item.correta
                  ? "text-emerald-400 bg-emerald-500/20"
                  : "text-red-400 bg-red-500/20"
              )}>
                {item.correta ? "✓ V" : "✗ F"}
              </span>
            )}
          </div>
        );
      })}
    </div>
  );
}

// ─── PORQUE text parser (for assertion_reason with embedded text) ─────────────

function parsePorqueText(text: string): { before: string; a1: string; a2: string } | null {
  // Prefer uppercase PORQUE (formal M5 separator) over lowercase "porque" (conjunction).
  // This avoids splitting on "porque" used as a regular conjunction inside assertion text.
  let idx = text.search(/\bPORQUE\b/);       // case-sensitive: uppercase separator first
  if (idx === -1) idx = text.search(/\bPORQUE\b/i); // fallback: case-insensitive
  if (idx === -1) return null;
  let a1 = text.slice(0, idx).trim();
  let a2 = text.slice(idx + 6).trim(); // "PORQUE".length === 6
  if (!a1 || !a2) return null;

  // Strip "ASSERÇÃO I:" / "PROPOSIÇÃO I:" prefixes
  a1 = a1.replace(/^(?:ASSERÇÃO|PROPOSIÇÃO|AFIRMAÇÃO)\s+I\s*[:.]\s*/i, "");
  a2 = a2.replace(/^(?:ASSERÇÃO|PROPOSIÇÃO|AFIRMAÇÃO)\s+II\s*[:.]\s*/i, "");

  // Strip trailing command from a2 (e.g. "A respeito das asserções acima, assinale...")
  const trailingCmd = a2.match(/[\.\,]\s+((?:A respeito|Assinale|Sobre|Com relação|Acerca)[^.]{5,200}[:?])\s*$/i);
  if (trailingCmd) a2 = a2.slice(0, a2.length - trailingCmd[0].length).trim();

  return { before: "", a1, a2 };
}

// ─── M5: Asserção–Razão sub-renderer ─────────────────────────────────────────

function AssertionReasonRenderer({ question, answered, lang }: {
  question: QuestionData; answered: boolean; lang: string;
}) {
  // Parse from textPt if either assertion field is missing (handles partial data)
  const parsed = ((!question.assertion1 || !question.assertion2) && question.textPt)
    ? parsePorqueText(question.textPt)
    : null;

  const a1Text = question.assertion1 || parsed?.a1 || "";
  const a2Text = question.assertion2 || parsed?.a2 || "";

  // Extract intro context from textPt when assertions are stored separately.
  // The textPt may contain a leading sentence ("Analise as afirmativas:") before
  // the assertion content — show it so the question context isn't lost.
  const introText = (() => {
    if (!question.assertion1 || !question.textPt) return "";
    const txt = question.textPt;
    // Find where assertion1 content or "ASSERÇÃO I" marker begins
    const a1Idx = question.assertion1
      ? txt.indexOf(question.assertion1.substring(0, Math.min(40, question.assertion1.length)))
      : -1;
    const markerIdx = txt.search(/(?:ASSERÇÃO|PROPOSIÇÃO|AFIRMAÇÃO)\s+I\b/i);
    const cutIdx = a1Idx > 0 ? Math.min(a1Idx, markerIdx > 0 ? markerIdx : a1Idx) : (markerIdx > 0 ? markerIdx : -1);
    if (cutIdx <= 0) return "";
    const intro = txt.slice(0, cutIdx)
      .replace(/^(?:ASSERÇÃO|PROPOSIÇÃO|AFIRMAÇÃO)\s+I\s*[:.]\s*/i, "")
      .trim();
    return intro.length > 5 ? intro : "";
  })();

  const hasVF = question.a1Verdadeira != null && question.a2Verdadeira != null;
  const a1V = question.a1Verdadeira ?? true;
  const a2V = question.a2Verdadeira ?? true;

  const PropBox = ({ label, text, verdadeira }: { label: string; text?: string; verdadeira: boolean }) => (
    <div className={cn(
      "rounded-xl border p-4 space-y-2 transition-colors",
      answered && hasVF
        ? verdadeira ? "border-emerald-500/40 bg-emerald-500/8" : "border-red-500/40 bg-red-500/8"
        : "border-primary/20 bg-primary/5"
    )}>
      <div className="flex items-center justify-between">
        <span className="text-xs font-black text-primary uppercase tracking-wide">{label}</span>
        {answered && hasVF && (
          <span className={cn(
            "text-xs font-black px-2 py-0.5 rounded",
            verdadeira ? "text-emerald-400 bg-emerald-500/20" : "text-red-400 bg-red-500/20"
          )}>
            {verdadeira ? "✓ VERDADEIRA" : "✗ FALSA"}
          </span>
        )}
      </div>
      {text && <p className="text-sm">{text}</p>}
    </div>
  );

  // Fallback: if parsing completely failed, show raw text so question is never blank
  if (!a1Text && !a2Text) {
    return <p className="text-base font-sans text-foreground leading-relaxed">{question.textPt}</p>;
  }

  return (
    <div className="space-y-2">
      {introText && (
        <p className="text-base font-sans text-foreground leading-relaxed">{introText}</p>
      )}
      <PropBox
        label={lang === "pt" ? "Afirmativa I" : "Statement I"}
        text={a1Text}
        verdadeira={a1V}
      />
      <div className="flex items-center gap-3 py-1">
        <div className="flex-1 h-px bg-border/40" />
        <span className="text-xs font-black text-amber-400 tracking-widest px-3 py-1 rounded border border-amber-500/30 bg-amber-500/10">
          {lang === "pt" ? "PORQUE" : "BECAUSE"}
        </span>
        <div className="flex-1 h-px bg-border/40" />
      </div>
      <PropBox
        label={lang === "pt" ? "Afirmativa II (Razão)" : "Statement II (Reason)"}
        text={a2Text}
        verdadeira={a2V}
      />
      {answered && hasVF && question.relacaoCausal != null && a1V && a2V && (
        <div className={cn(
          "text-xs text-center py-2 px-3 rounded-lg border",
          question.relacaoCausal
            ? "border-emerald-500/30 text-emerald-400 bg-emerald-500/10"
            : "border-red-500/30 text-red-400 bg-red-500/10"
        )}>
          {question.relacaoCausal
            ? (lang === "pt" ? "✓ A Afirmativa II é justificativa correta da I" : "✓ Statement II correctly justifies Statement I")
            : (lang === "pt" ? "✗ A Afirmativa II NÃO é justificativa correta da I" : "✗ Statement II does NOT correctly justify Statement I")
          }
        </div>
      )}
      {answered && (
        <p className="text-xs text-muted-foreground text-center py-1">
          {descricaoGabaritoM5(question.correctOption)}
        </p>
      )}
    </div>
  );
}

// ─── Roman numeral list parser ───────────────────────────────────────────────

const ROMAN_VALUE: Record<string, number> = {
  I: 1, II: 2, III: 3, IV: 4, V: 5, VI: 6, VII: 7, VIII: 8, IX: 9, X: 10,
};

function parseRomanNumeralList(text: string): { intro: string; items: { numeral: string; text: string }[]; after: string } | null {
  // Match Roman numerals used as list markers (preceded by space, colon, period, or start)
  const markerRe = /(X{0,2}(?:IX|IV|VI{0,3}|I{1,3}))\.\s/g;
  const positions: { numeral: string; value: number; start: number; end: number }[] = [];
  let m: RegExpExecArray | null;
  while ((m = markerRe.exec(text)) !== null) {
    const charBefore = m.index > 0 ? text[m.index - 1] : "";
    if (m.index === 0 || [" ", "\n", ":", "."].includes(charBefore)) {
      const numeral = m[1]!.toUpperCase();
      const value = ROMAN_VALUE[numeral];
      if (value !== undefined) {
        positions.push({ numeral, value, start: m.index, end: m.index + m[0].length });
      }
    }
  }
  if (positions.length < 2) return null;
  // Require sequential Roman numerals (I→II→III or any consecutive run)
  let sequential = true;
  for (let i = 1; i < positions.length; i++) {
    if (positions[i]!.value !== positions[i - 1]!.value + 1) { sequential = false; break; }
  }
  if (!sequential) return null;

  const intro = text.slice(0, positions[0]!.start).trim();
  const items = positions.map(({ numeral, end }, i) => ({
    numeral,
    text: text.slice(end, positions[i + 1]?.start ?? text.length).trim(),
  }));

  // Extract trailing command/prompt from last item (e.g. "São CORRETAS apenas:")
  let after = "";
  if (items.length > 0) {
    const last = items[items.length - 1]!;
    const tm = last.text.match(/[\.\,]\s+([A-ZÁÉÍÓÚÂÊÎÔÛÃÕÇ][^.!?]{5,150}[?:])\s*$/);
    if (tm) {
      after = tm[1].trim();
      last.text = last.text.slice(0, last.text.length - tm[0].length).trim();
    }
  }

  return { intro, items, after };
}

function RomanNumeralList({ intro, items, after }: { intro: string; items: { numeral: string; text: string }[]; after: string }) {
  return (
    <div className="space-y-2">
      {intro && <p className="text-base font-sans text-foreground leading-relaxed">{intro}</p>}
      <div className="space-y-1.5 pl-1">
        {items.map(({ numeral, text }) => (
          <div key={numeral} className="flex gap-3 text-sm font-sans text-foreground leading-snug">
            <span className="font-bold text-primary shrink-0 w-6 pt-px">{numeral}.</span>
            <span>{text}</span>
          </div>
        ))}
      </div>
      {after && <p className="text-base font-sans text-foreground leading-relaxed mt-1">{after}</p>}
    </div>
  );
}

// ─── Column question parser ───────────────────────────────────────────────────

interface ColumnsData {
  before: string;
  col1Label: string;
  col1Items: { key: string; text: string }[];
  col2Label: string;
  col2Items: { key: string; text: string }[];
  after: string;
}

function parseKeyedItems(text: string, pattern: RegExp): { key: string; text: string }[] {
  const items: { key: string; text: string }[] = [];
  const re = new RegExp(pattern.source, "gi");
  const positions: { key: string; start: number; end: number }[] = [];
  let m: RegExpExecArray | null;
  while ((m = re.exec(text)) !== null) {
    positions.push({ key: m[1]!, start: m.index, end: m.index + m[0].length });
  }
  for (let i = 0; i < positions.length; i++) {
    const { key, end } = positions[i]!;
    const nextStart = positions[i + 1]?.start ?? text.length;
    items.push({ key, text: text.slice(end, nextStart).trim() });
  }
  return items;
}

function parseColumnsText(text: string): ColumnsData | null {
  const col1Re = /COLUNA\s+I(?!I)|COLUNA\s+A(?!\w)/gi;
  const col2Re = /COLUNA\s+II|COLUNA\s+B(?!\w)/gi;
  
  let col1Match;
  let lastCol1Match = null;
  while ((col1Match = col1Re.exec(text)) !== null) {
    lastCol1Match = col1Match;
  }
  
  let col2Match;
  let lastCol2Match = null;
  while ((col2Match = col2Re.exec(text)) !== null) {
    lastCol2Match = col2Match;
  }

  if (!lastCol1Match || !lastCol2Match || lastCol1Match.index >= lastCol2Match.index) return null;

  const col1End = lastCol1Match.index + lastCol1Match[0].length;
  const col2End = lastCol2Match.index + lastCol2Match[0].length;
  const before = text.slice(0, lastCol1Match.index).trim();
  const col1Raw = text.slice(col1End, lastCol2Match.index).trim();
  const col2Raw = text.slice(col2End).trim();

  // COLUNA I: try numeric keys first (1. 2.), then Roman numeral keys (I. II.)
  let col1Items = parseKeyedItems(col1Raw, /(?:^|\s)(\d+)[.)]/);
  if (!col1Items.length) col1Items = parseKeyedItems(col1Raw, /(?:^|\s)(X{0,2}(?:IX|IV|VI{0,3}|I{1,3}))[.)]/);
  const col2Items = parseKeyedItems(col2Raw, /(?:^|\s)([a-e])[.)]/);
  if (!col1Items.length || !col2Items.length) return null;

  // Extract trailing prompt from last col2 item (e.g. "A associação CORRETA é:")
  let after = "";
  if (col2Items.length > 0) {
    const last = col2Items[col2Items.length - 1]!;
    const tm = last.text.match(/\.\s+([A-Z][^.]{3,80}[?:])\s*$/);
    if (tm) {
      after = tm[1].trim();
      last.text = last.text.slice(0, last.text.length - tm[0].length).trim();
    }
  }

  return {
    before,
    col1Label: lastCol1Match[0].trim().toUpperCase(),
    col1Items,
    col2Label: lastCol2Match[0].trim().toUpperCase(),
    col2Items,
    after,
  };
}

function ColumnsDisplay({ data }: { data: ColumnsData }) {
  return (
    <div className="space-y-3">
      {data.before && (
        <p className="text-base font-sans text-foreground leading-relaxed">{data.before}</p>
      )}
      <div className="rounded-xl border border-border/40 bg-accent/10 overflow-hidden">
        <div className="grid grid-cols-2 divide-x divide-border/40">
          <div className="bg-primary/5 px-4 py-2 border-b border-border/40">
            <p className="text-xs font-bold text-primary uppercase tracking-wider">{data.col1Label}</p>
          </div>
          <div className="bg-primary/5 px-4 py-2 border-b border-border/40">
            <p className="text-xs font-bold text-blue-400 uppercase tracking-wider">{data.col2Label}</p>
          </div>
          <div className="p-3 space-y-2.5 align-top">
            {data.col1Items.map((item) => (
              <div key={item.key} className="flex gap-2 text-sm leading-snug">
                <span className="font-bold text-primary shrink-0 w-5">{item.key}.</span>
                <span>{item.text}</span>
              </div>
            ))}
          </div>
          <div className="p-3 space-y-2.5 align-top">
            {data.col2Items.map((item) => (
              <div key={item.key} className="flex gap-2 text-sm leading-snug">
                <span className="font-bold text-blue-400 shrink-0 w-5">{item.key})</span>
                <span>{item.text}</span>
              </div>
            ))}
          </div>
        </div>
      </div>
      {data.after && (
        <p className="text-sm font-sans text-muted-foreground italic">{data.after}</p>
      )}
    </div>
  );
}

// ─── Inline matching text parser ─────────────────────────────────────────────
// Handles: "(Coluna I) intro... (Coluna II): 1. Item 2. Item a) Alt b) Alt"
// where all items are listed sequentially without explicit column header blocks.

function parseInlineMatchingText(text: string): ColumnsData | null {
  if (!/\(\s*coluna\s+I\s*\)/i.test(text) || !/\(\s*coluna\s+II\s*\)/i.test(text)) return null;

  // Find all numeric item markers (1. 2. 3. ...)
  const numRe = /(\d+)[.)]\s+/g;
  const numTokens: { key: string; start: number; end: number }[] = [];
  let m: RegExpExecArray | null;
  while ((m = numRe.exec(text)) !== null) {
    numTokens.push({ key: m[1]!, start: m.index, end: m.index + m[0].length });
  }
  if (!numTokens.length) return null;

  const lastNumStart = numTokens[numTokens.length - 1]!.start;

  // Find letter item markers (a) b) c) ...) that appear AFTER the last numeric item
  const letRe = /([a-e])[.)]\s*/g;
  const letTokens: { key: string; start: number; end: number }[] = [];
  while ((m = letRe.exec(text)) !== null) {
    if (m.index > lastNumStart) {
      letTokens.push({ key: m[1]!, start: m.index, end: m.index + m[0].length });
    }
  }
  if (!letTokens.length) return null;

  const before = text.slice(0, numTokens[0]!.start).trim();
  const firstLetStart = letTokens[0]!.start;

  const col1Items: { key: string; text: string }[] = numTokens.map((t, i) => ({
    key: t.key,
    text: text.slice(t.end, i < numTokens.length - 1 ? numTokens[i + 1]!.start : firstLetStart).trim(),
  }));

  const col2Items: { key: string; text: string }[] = letTokens.map((t, i) => ({
    key: t.key,
    text: text.slice(t.end, i < letTokens.length - 1 ? letTokens[i + 1]!.start : text.length).trim(),
  }));

  if (!col1Items.length || !col2Items.length) return null;

  return { before, col1Label: "COLUNA I", col1Items, col2Label: "COLUNA II", col2Items, after: "" };
}

// ─── M8: Matching sub-renderer ────────────────────────────────────────────────

function MatchingRenderer({ questionId, fd, answered, correctOption, opts, lang, textPt }: {
  questionId: number; fd: any; answered: boolean;
  correctOption: string; opts: QuestionOption[]; lang: string; textPt?: string;
}) {
  const { data: matchingData } = trpc.questions.getMatchingData.useQuery(
    { questionId },
    { staleTime: 60_000 }
  );

  const hasRelational = matchingData && matchingData.esquerda.length > 0;

  if (hasRelational) {
    const { esquerda, direita, pairs } = matchingData;
    const correctPairMap = new Map(pairs.map(p => [p.esquerdaId, p.direitaId]));

    // Figure out which alt was selected/correct to color the pairs
    const correctAltText = opts.find(o => o.id === correctOption)?.textPt ?? "";

    return (
      <div className="rounded-xl border border-border/40 bg-accent/10 p-4 space-y-3">
        <div className="grid grid-cols-2 gap-3">
          <p className="text-[11px] font-semibold text-muted-foreground uppercase">
            {lang === "pt" ? "Coluna I" : "Column I"}
          </p>
          <p className="text-[11px] font-semibold text-muted-foreground uppercase">
            {lang === "pt" ? "Coluna II" : "Column II"}
          </p>
          {esquerda.map((e) => {
            const corrDirId = correctPairMap.get(e.id);
            const corrDir = direita.find(d => d.id === corrDirId);
            return (
              <div key={e.id} className={cn(
                "contents"
              )}>
                <div className={cn(
                  "p-2 rounded-lg border text-sm",
                  answered ? "border-emerald-500/30 bg-emerald-500/8" : "border-border/30 bg-background"
                )}>
                  <span className="text-xs font-bold text-primary mr-1.5">{e.label}.</span>
                  {e.textPt}
                </div>
                <div className={cn(
                  "p-2 rounded-lg border text-sm",
                  answered && corrDir ? "border-emerald-500/30 bg-emerald-500/8" : "border-border/30 bg-background"
                )}>
                  {answered && corrDir ? (
                    <>
                      <span className="text-xs font-bold text-blue-400 mr-1.5">{corrDir.label})</span>
                      {corrDir.textPt}
                      <span className="text-emerald-400 ml-1 text-xs">✓</span>
                    </>
                  ) : (
                    <span className="text-muted-foreground text-xs">
                      {lang === "pt" ? "→ selecione a alternativa" : "→ select the alternative"}
                    </span>
                  )}
                </div>
              </div>
            );
          })}
        </div>
        {answered && (
          <p className="text-xs text-center text-muted-foreground">
            {lang === "pt" ? "Correspondência correta:" : "Correct match:"} <strong>{correctAltText}</strong>
          </p>
        )}
      </div>
    );
  }

  // Fallback: formatData.pairs
  if (!fd.pairs?.length) {
    if (textPt) {
      const cols = parseColumnsText(textPt) ?? parseInlineMatchingText(textPt);
      if (cols) return <ColumnsDisplay data={cols} />;
      // Last resort: show the raw question text so it is never blank
      return <p className="text-base font-sans text-foreground leading-relaxed whitespace-pre-wrap">{textPt}</p>;
    }
    return null;
  }
  return (
    <div className="p-3 rounded-xl border border-border/40 bg-accent/10">
      <div className="grid grid-cols-2 gap-2">
        <p className="text-[11px] font-semibold text-muted-foreground uppercase mb-1">
          {lang === "pt" ? "Coluna A" : "Column A"}
        </p>
        <p className="text-[11px] font-semibold text-muted-foreground uppercase mb-1">
          {lang === "pt" ? "Coluna B" : "Column B"}
        </p>
        {fd.pairs.map((pair: any, i: number) => (
          <>
            <div key={`a-${i}`} className="text-sm p-2 bg-background rounded border border-border/30">
              <span className="text-xs font-bold text-primary mr-2">{i + 1}.</span>{pair.colA}
            </div>
            <div key={`b-${i}`} className="text-sm p-2 bg-background rounded border border-border/30">
              {pair.colB}
            </div>
          </>
        ))}
      </div>
    </div>
  );
}

// ─── Main component ───────────────────────────────────────────────────────────

export default function QuestionRenderer({
  question,
  selectedOption,
  answered = false,
  onAnswer,
  language = "pt",
  showExplanation = false,
  revealAnswer = false,
}: QuestionRendererProps) {
  const qType = (question.questionType || "multiple_choice") as QuestionType;
  const fd = question.formatData || {};
  const lang = language;

  const qText = lang === "pt" ? question.textPt : (question.textEn || question.textPt);

  // Resolve assertion_reason in priority order:
  // 1. questionType explicitly set
  // 2. Legacy rows stored as "multiple_choice" (the DB default) that actually have
  //    assertion1+assertion2 data, or match the "ASSERÇÃO I … PORQUE" text pattern.
  //    Scoped to qType === "multiple_choice" only — a question explicitly tagged as
  //    clinical_case/interpretation/etc. must never be overridden just because an
  //    importer accidentally populated assertion1/assertion2 for it.
  const effectiveQType: QuestionType = (() => {
    if (qType === "assertion_reason") return "assertion_reason";
    if (qType !== "multiple_choice") return qType;
    if (question.assertion1 && question.assertion2) return "assertion_reason";
    if (
      !question.assertion1 &&
      /(?:ASSERÇÃO|PROPOSIÇÃO|AFIRMAÇÃO)\s+I\b/i.test(qText) &&
      /\bPORQUE\b/i.test(qText)
    ) return "assertion_reason";
    return qType;
  })();

  // Defensive normalization: DB may store options as {label, textPt} instead of {id, textPt}
  const normalizedOptions: QuestionOption[] = (question.options || []).map((opt: any) => ({
    id: opt.id ?? opt.label ?? String(opt),
    textPt: opt.textPt ?? opt.text_pt ?? "",
    textEn: opt.textEn ?? opt.text_en,
  }));
  const opts = normalizedOptions;

  const isCorrect = (optId: string) => optId === question.correctOption;
  const isSelected = (optId: string) => optId === selectedOption;

  const optionClass = (optId: string) => {
    if (!answered && !revealAnswer) {
      return cn(
        "w-full flex items-start gap-2 px-4 py-3 rounded-lg border text-sm font-sans transition-all",
        isSelected(optId)
          ? "border-primary bg-primary/10 text-primary"
          : "border-border/50 bg-background hover:border-primary/50 hover:bg-accent/30"
      );
    }
    if (isCorrect(optId)) return "w-full flex items-start gap-2 px-4 py-3 rounded-lg border text-sm font-sans border-green-500 bg-green-500/10 text-green-400";
    if (isSelected(optId) && !isCorrect(optId)) return "w-full flex items-start gap-2 px-4 py-3 rounded-lg border text-sm font-sans border-red-500 bg-red-500/10 text-red-400";
    return "w-full flex items-start gap-2 px-4 py-3 rounded-lg border text-sm font-sans border-border/30 bg-background/50 text-muted-foreground";
  };

  // ── Render by type ──────────────────────────────────────────────────────────

  return (
    <div className="space-y-4">
      {/* Meta badges */}
      <div className="flex flex-wrap gap-2">
        {/* Model badge M1–M10 */}
        {question.modelId && MODEL_MAP[question.modelId as keyof typeof MODEL_MAP] && (
          <Badge className="text-xs font-sans bg-violet-500/20 text-violet-300 border-violet-500/30" title={MODEL_MAP[question.modelId as keyof typeof MODEL_MAP].nome_modelo}>
            {question.modelId}
          </Badge>
        )}
        {question.disciplineName && (
          <Badge variant="outline" className="text-xs font-sans">{question.disciplineName}</Badge>
        )}
        {question.subjectTag && (
          <Badge variant="secondary" className="text-xs font-sans">{question.subjectTag}</Badge>
        )}
        {question.banca && (
          <Badge variant="outline" className="text-xs font-sans border-blue-500/30 text-blue-400">{question.banca}</Badge>
        )}
        {question.year && (
          <Badge variant="outline" className="text-xs font-sans">{question.year}</Badge>
        )}
        {question.author && (
          <span className="text-xs text-muted-foreground font-sans">{question.author}</span>
        )}
        {question.isAnulada && (
          <Badge className="text-xs bg-orange-500/20 text-orange-400 border-orange-500/30">Anulada</Badge>
        )}
        {question.isDesatualizada && (
          <Badge className="text-xs bg-gray-500/20 text-gray-400 border-gray-500/30">Desatualizada</Badge>
        )}
        <Badge variant="outline" className={cn("text-xs font-sans ml-auto", {
          "border-sky-500/50 text-sky-400": question.difficulty === "very_easy",
          "border-green-500/50 text-green-400": question.difficulty === "easy",
          "border-yellow-500/50 text-yellow-400": question.difficulty === "medium",
          "border-red-500/50 text-red-400": question.difficulty === "hard",
          "border-purple-500/50 text-purple-400": question.difficulty === "very_hard",
        })}>
          {question.difficulty === "very_easy" ? "Muito Fácil" :
           question.difficulty === "easy" ? (lang === "pt" ? "Fácil" : "Easy") :
           question.difficulty === "hard" ? (lang === "pt" ? "Difícil" : "Hard") :
           question.difficulty === "very_hard" ? "Muito Difícil" :
           (lang === "pt" ? "Médio" : "Medium")}
        </Badge>
      </div>

      {/* ── Clinical Case: show case data before question text ── */}
      {qType === "clinical_case" && (() => {
        let caseBody: string;
        let closingQuestion: string | null = null;

        if (fd.caseText) {
          caseBody = fd.caseText;
          closingQuestion = qText; // textPt holds the actual question when caseText is stored separately
        } else if (qText.startsWith("[CASO CLÍNICO]")) {
          const body = qText.replace(/^\[CASO CLÍNICO\]\n?/, "");
          const lastBreak = body.lastIndexOf("\n\n");
          if (lastBreak > 0) {
            caseBody = body.slice(0, lastBreak);
            closingQuestion = body.slice(lastBreak + 2);
          } else {
            caseBody = body;
          }
        } else {
          caseBody = qText; // fallback: show full text in the case box
        }

        return (
          <>
            <div className="p-4 bg-amber-500/5 border border-amber-500/20 rounded-lg">
              <p className="text-xs font-sans font-semibold text-amber-400 uppercase tracking-wide mb-2">
                {lang === "pt" ? "Caso Clínico" : "Clinical Case"}
              </p>
              <p className="text-sm font-sans text-foreground/90 whitespace-pre-wrap">{caseBody}</p>
              {fd.imageUrl && (
                <QuestionImage src={fd.imageUrl} alt="Caso clínico" className="mt-3 rounded-lg max-h-64 object-contain" />
              )}
            </div>
            {closingQuestion && (
              <p className="text-base font-sans text-foreground leading-relaxed">{closingQuestion}</p>
            )}
          </>
        );
      })()}

      {/* ── Image Analysis: show image before question text ── */}
      {qType === "image_analysis" && fd.imageUrl && (
        <div className="p-3 bg-blue-500/5 border border-blue-500/20 rounded-lg">
          <p className="text-xs font-sans font-semibold text-blue-400 uppercase tracking-wide mb-2">
            {lang === "pt" ? "Analise a imagem abaixo:" : "Analyze the image below:"}
          </p>
          <QuestionImage src={fd.imageUrl} alt="Questão" className="rounded-lg max-h-72 object-contain mx-auto" />
          {fd.caseText && <p className="text-xs text-muted-foreground font-sans mt-2">{fd.caseText}</p>}
        </div>
      )}

      {/* ── Interpretation: show table/data before question text ── */}
      {qType === "interpretation" && fd.tableData && (
        <div className="p-3 bg-purple-500/5 border border-purple-500/20 rounded-lg">
          <p className="text-xs font-sans font-semibold text-purple-400 uppercase tracking-wide mb-2">
            {lang === "pt" ? "Dados para análise:" : "Data for analysis:"}
          </p>
          <pre className="text-xs font-mono text-foreground/90 whitespace-pre-wrap overflow-x-auto">{fd.tableData}</pre>
          {fd.imageUrl && <QuestionImage src={fd.imageUrl} alt="Dados" className="mt-2 rounded-lg max-h-48 object-contain" />}
        </div>
      )}

      {/* ── Question text ── */}
      {effectiveQType !== "matching" && effectiveQType !== "clinical_case" && effectiveQType !== "assertion_reason" && qType !== "cloze" && (() => {
        const cols = parseColumnsText(qText);
        if (cols) return <ColumnsDisplay data={cols} />;
        const roman = parseRomanNumeralList(qText);
        if (roman) {
          // M3 (complex_multiple_choice) and M9 (ordering) have dedicated sub-renderers for the
          // numbered items — only show the intro/command text here to avoid double rendering.
          if (qType === "complex_multiple_choice" || qType === "ordering") {
            const parts = [roman.intro, roman.after].filter(Boolean);
            if (!parts.length) return null;
            return (
              <div className="space-y-1">
                {parts.map((p, i) => (
                  <p key={i} className="text-base font-sans text-foreground leading-relaxed">{p}</p>
                ))}
              </div>
            );
          }
          return <RomanNumeralList intro={roman.intro} items={roman.items} after={roman.after} />;
        }
        // M4: highlight negative-focus keywords
        if (question.modelId === "M4") {
          const parts = qText.split(/(EXCETO|NÃO|INCORRETO)/g);
          return (
            <p className="text-base font-sans text-foreground leading-relaxed">
              {parts.map((p, i) =>
                /^(EXCETO|NÃO|INCORRETO)$/.test(p)
                  ? <mark key={i} className="bg-orange-500/20 text-orange-400 not-italic font-bold rounded px-0.5">{p}</mark>
                  : p
              )}
            </p>
          );
        }
        return <p className="text-base font-sans text-foreground leading-relaxed">{qText}</p>;
      })()}

      {/* ── Question-level image (M1/M2/M3/M4/M6/etc. com imagem no enunciado) ── */}
      {question.imageUrl && !fd.imageUrl && (
        <div className="rounded-lg overflow-hidden border border-border/30 bg-muted/10">
          <QuestionImage
            src={question.imageUrl}
            alt="Imagem da questão"
            className="w-full max-h-80 object-contain"
          />
        </div>
      )}

      {/* ── M5: Asserção–Razão com V/F revelado após resposta ── */}
      {effectiveQType === "assertion_reason" && (
        <AssertionReasonRenderer question={question} answered={answered || revealAnswer} lang={lang} />
      )}

      {/* ── M3: Complex Multiple Choice — assertivas com V/F revelado após resposta ── */}
      {qType === "complex_multiple_choice" && (
        <AssertiveRenderer questionId={question.id} fd={fd} answered={answered || revealAnswer} lang={lang} textPt={qText} />
      )}

      {/* ── M8: Matching — colunas com pares revelados após resposta ── */}
      {qType === "matching" && (
        <MatchingRenderer questionId={question.id} fd={fd} answered={answered || revealAnswer} correctOption={question.correctOption} opts={opts} lang={lang} textPt={qText} />
      )}

      {/* ── True/False: show statements ── */}
      {qType === "true_false" && fd.statements?.length > 0 && (
        <div className="space-y-2 p-3 bg-accent/20 border border-border/30 rounded-lg">
          <p className="text-xs font-sans font-semibold text-muted-foreground uppercase tracking-wide mb-2">
            {lang === "pt" ? "Julgue as afirmações abaixo:" : "Judge the statements below:"}
          </p>
          {fd.statements.map((s: any) => (
            <div key={s.id} className="flex gap-2">
              <span className="text-xs font-bold text-primary w-8 shrink-0">{s.id}.</span>
              <span className="text-sm font-sans">{lang === "pt" ? s.textPt : (s.textEn || s.textPt)}</span>
            </div>
          ))}
        </div>
      )}

      {/* ── Ordering: show steps to be ordered ── */}
      {qType === "ordering" && (() => {
        const steps: { label: string; text: string }[] = fd.steps?.length > 0
          ? fd.steps.map((s: any, i: number) => ({ label: String.fromCharCode(65 + i), text: lang === "pt" ? s.textPt : (s.textEn || s.textPt) }))
          : (() => {
              const parsed = parseRomanNumeralList(qText);
              return parsed ? [...parsed.items].map(it => ({ label: it.numeral, text: it.text })) : [];
            })();
        if (!steps.length) return null;
        return (
          <div className="space-y-2 p-3 bg-accent/20 border border-border/30 rounded-lg">
            <p className="text-xs font-sans font-semibold text-muted-foreground uppercase tracking-wide mb-2">
              {lang === "pt" ? "Etapas a ordenar:" : "Steps to order:"}
            </p>
            {steps.map((step, i) => (
              <div key={i} className="flex gap-2 items-center p-2 bg-background rounded border border-border/30">
                <span className="text-xs font-bold text-primary w-6 shrink-0">{step.label}.</span>
                <span className="text-sm font-sans">{step.text}</span>
              </div>
            ))}
          </div>
        );
      })()}

      {/* ── Cloze: highlight blanks in question text — suporta [BLANK], [___], [1], [2]… ── */}
      {qType === "cloze" && (
        <div className="p-3 bg-accent/20 border border-border/30 rounded-lg">
          <p className="text-xs font-sans text-muted-foreground mb-2">
            {lang === "pt" ? "Preencha as lacunas com a alternativa correta:" : "Fill in the blanks with the correct option:"}
          </p>
          <p className="text-sm font-sans">
            {qText.replace(/\[_{1,}\]|\[\d+\]/g, "[BLANK]").split("[BLANK]").map((part, i, arr) => (
              <span key={i}>
                {part}
                {i < arr.length - 1 && (
                  <span className={cn(
                    "inline-block min-w-16 mx-1 px-2 py-0.5 rounded border text-center font-mono",
                    answered || revealAnswer ? "border-green-500 bg-green-500/10 text-green-400" : "border-primary/50 bg-primary/5 text-primary"
                  )}>___</span>
                )}
              </span>
            ))}
          </p>
        </div>
      )}

      {/* ── Discursive: no options, just a text area hint ── */}
      {qType === "discursive" && (
        <div className="p-4 bg-accent/20 border border-border/30 rounded-lg">
          <p className="text-xs font-sans text-muted-foreground italic">
            {lang === "pt" ? "Questão discursiva — resposta aberta." : "Discursive question — open answer."}
          </p>
          {(answered || revealAnswer) && question.explanationPt && (
            <div className="mt-3 pt-3 border-t border-border/30">
              <p className="text-xs font-sans font-semibold text-primary mb-1">
                {lang === "pt" ? "Resposta esperada:" : "Expected answer:"}
              </p>
              <p className="text-sm font-sans text-foreground/90">{lang === "pt" ? question.explanationPt : (question.explanationEn || question.explanationPt)}</p>
            </div>
          )}
        </div>
      )}

      {/* ── Options (for all types with selectable alternatives) ── */}
      {qType !== "discursive" && opts.length > 0 && (
        <div className="space-y-2">
          {opts.map((opt) => (
            <button
              key={opt.id}
              className={optionClass(opt.id)}
              onClick={() => !answered && onAnswer?.(opt.id)}
              disabled={answered}
            >
              <span className="font-bold text-xs shrink-0 w-5">{opt.id})</span>
              <span className="flex-1 min-w-0 text-left break-words">
                {lang === "pt" ? opt.textPt : (opt.textEn || opt.textPt)}
                {(answered || revealAnswer) && isCorrect(opt.id) && (
                  <span className="ml-2 text-green-400 text-xs">✓</span>
                )}
                {answered && isSelected(opt.id) && !isCorrect(opt.id) && (
                  <span className="ml-2 text-red-400 text-xs">✗</span>
                )}
              </span>
            </button>
          ))}
        </div>
      )}

      {/* ── Explanation ── */}
      {(showExplanation || (answered && question.explanationPt)) && question.explanationPt && qType !== "discursive" && (
        <div className="p-4 bg-primary/5 border border-primary/20 rounded-lg">
          <p className="text-xs font-sans font-semibold text-primary mb-2">
            {lang === "pt" ? "Explicação:" : "Explanation:"}
          </p>
          <p className="text-sm font-sans text-foreground/90">
            {lang === "pt" ? question.explanationPt : (question.explanationEn || question.explanationPt)}
          </p>
        </div>
      )}
    </div>
  );
}
