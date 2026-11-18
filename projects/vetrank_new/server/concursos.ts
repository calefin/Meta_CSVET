/**
 * Radar de Concursos — Medicina Veterinária e Zootecnia.
 *
 * Coleta automatizada e curadoria de editais públicos (municipais, estaduais, federais,
 * residências e Forças Armadas) com vagas para Médico Veterinário e Zootecnista.
 *
 * Regras de negócio:
 *  - Extrai apenas FATOS oficiais (órgão, UF, cidade, cargos, vagas, salários, prazos, banca, links oficiais);
 *  - Nunca expõe sites agregadores ou concorrentes; links externos apontam exclusivamente
 *    para páginas oficiais dos órgãos públicos (.gov.br, .edu.br, .mil.br) ou bancas organizadoras;
 *  - Possui extrator determinístico completo (independente de chave de LLM) + enriquecimento opcional via LLM;
 *  - Mantém seed local (data/concursos_seed.json) sincronizado no boot para garantir disponibilidade imediata.
 */
import { getDb } from "./db";
import { concursos } from "../drizzle/schema";
import { eq, and, sql } from "drizzle-orm";
import { invokeLLM, hasLLMConfigured } from "./_core/llm";
import crypto from "crypto";
import fs from "fs";
import path from "path";

const FONTES_CARGOS = [
  { url: "https://www.pciconcursos.com.br/cargos/medico-veterinario", areaPadrao: "veterinaria" as const },
  { url: "https://www.pciconcursos.com.br/cargos/veterinario", areaPadrao: "veterinaria" as const },
  { url: "https://www.pciconcursos.com.br/cargos/zootecnista", areaPadrao: "zootecnia" as const },
];

const FONTES_VAGAS = [
  { url: "https://www.pciconcursos.com.br/concursos/vagas/medico-veterinario", cargoPadrao: "Médico Veterinário" },
  { url: "https://www.pciconcursos.com.br/concursos/vagas/veterinario", cargoPadrao: "Médico Veterinário" },
  { url: "https://www.pciconcursos.com.br/concursos/vagas/zootecnista", cargoPadrao: "Zootecnista" },
  { url: "https://www.pciconcursos.com.br/concursos/vagas/fiscal-agropecuario", cargoPadrao: "Fiscal Agropecuário (Veterinária/Zootecnia)" },
];

const DOU_URLS = [
  "https://www.in.gov.br/consulta/-/buscar/dou?q=%22medicina+veterinaria%22&s=do3&exactDate=mes&sortType=0",
  "https://www.in.gov.br/consulta/-/buscar/dou?q=%22medico+veterinario%22&s=do3&exactDate=mes&sortType=0",
  "https://www.in.gov.br/consulta/-/buscar/dou?q=%22zootecnia%22&s=do3&exactDate=mes&sortType=0",
  "https://www.in.gov.br/consulta/-/buscar/dou?q=%22zootecnista%22&s=do3&exactDate=mes&sortType=0",
];

const UA = "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36";
const MAX_POR_EXECUCAO = 45;
const PAUSA_MS = 800;

/** Domínios de agregadores, redes sociais ou concorrentes que NUNCA devem aparecer em links públicos */
const DOMINIOS_BLOQUEADOS = /pciconcursos|pci\.app\.br|estrategiaconcursos|grancursos|qconcursos|direcaoconcursos|folhadirigida|jcconcursos|concursosnobrasil|editalconcursosbrasil|acheconcursos|noticiaconcursos|concursossc|sanar|vetsmart|aprovaconcursos|novaconcursos|alfaCon|focusconcursos|cursoenfase|facebook|twitter|x\.com|linkedin|t\.me|whatsapp|unpkg|google|instagram|youtube|tiktok|bit\.ly|tinyurl/i;

export function isUrlOficialPermitida(u: string | null | undefined): boolean {
  if (!u || !/^https?:\/\//i.test(u)) return false;
  return !DOMINIOS_BLOQUEADOS.test(u);
}

export type ItemLista = {
  url: string;
  orgao: string;
  titulo: string;
  cargos: string[];
  ufHint?: string | null;
  salarioHint?: number | null;
  inscricoesInicioHint?: string | null;
  inscricoesFimHint?: string | null;
  isDou?: boolean;
  douContent?: string;
  douPubDate?: string;
};

const decode = (s: string) => s
  .replace(/&amp;/g, "&").replace(/&quot;/g, '"').replace(/&#39;/g, "'").replace(/&lt;/g, "<").replace(/&gt;/g, ">")
  .replace(/&nbsp;/g, " ").replace(/&#(\d+);/g, (_, n) => String.fromCharCode(Number(n)));

async function baixar(url: string): Promise<string> {
  const r = await fetch(url, { headers: { "User-Agent": UA, "Accept-Language": "pt-BR" }, signal: AbortSignal.timeout(20000) });
  if (!r.ok) throw new Error(`HTTP ${r.status} em ${url}`);
  return r.text();
}

/** Lista de cargos: pares <ul class="link-d"> (notícia/órgão) seguidos de <ul class="link-i"> (cargo). */
export function parseLista(html: string, cargoPadrao?: string): ItemLista[] {
  const out = new Map<string, ItemLista>();
  let atual: ItemLista | null = null;
  const re = /<ul class="link-(d|i)">([\s\S]*?)<\/ul>/g;
  let m: RegExpExecArray | null;
  while ((m = re.exec(html))) {
    const [, tipo, corpo] = m;
    if (tipo === "d") {
      const a = /href="\s*(https:\/\/www\.pciconcursos\.com\.br\/noticias\/[^"\s]+)\s*"[\s\S]*?title="([^"]*)"[\s\S]*?>([\s\S]*?)<small/.exec(corpo);
      if (a) {
        const url = a[1].trim();
        atual = out.get(url) ?? { url, titulo: decode(a[2]).trim(), orgao: decode(a[3].replace(/<[^>]+>/g, "")).trim(), cargos: [] };
        out.set(url, atual);
      }
    } else if (atual) {
      const c = /<i[^>]*><\/i>([^<]+)<\/a>/.exec(corpo);
      if (c) {
        const cg = decode(c[1]).trim();
        if (!atual.cargos.includes(cg)) atual.cargos.push(cg);
      }
    }
  }
  if (cargoPadrao) {
    for (const item of out.values()) {
      if (item.cargos.length === 0) item.cargos.push(cargoPadrao);
    }
  }
  return Array.from(out.values());
}

const dmyToIso = (dmy: string): string | null => {
  const m = /(\d{2})\/(\d{2})\/(\d{4})/.exec(dmy);
  if (!m) return null;
  return `${m[3]}-${m[2]}-${m[1]}`;
};

/** Tabela /concursos/vagas/* que já traz UF, salário máximo e datas de inscrição estruturadas */
export function parseTabelaVagas(html: string, cargoPadrao: string): ItemLista[] {
  const out: ItemLista[] = [];
  const re = /<div class="na"[^>]*data-url="([^"]+)"[\s\S]*?<div class="ca"><a[^>]*title="([^"]*)"[^>]*>([^<]+)<\/a>[\s\S]*?<div class="cc">([^<]*)<\/div>\s*<div class="cd">([\s\S]*?)<\/div><div class="ce">([\s\S]*?)<\/div>/g;
  let m: RegExpExecArray | null;
  while ((m = re.exec(html))) {
    const url = m[1].trim();
    const titulo = decode(m[2]).trim();
    const orgao = decode(m[3]).trim();
    const ufRaw = m[4].trim().toUpperCase();
    const ufHint = /^[A-Z]{2}$/.test(ufRaw) ? ufRaw : null;
    const cdText = decode(m[5].replace(/<[^>]+>/g, " ")).replace(/\s+/g, " ").trim();
    const ceText = decode(m[6].replace(/<[^>]+>/g, " ")).replace(/\s+/g, " ").trim();

    let salarioHint: number | null = null;
    const salMatch = /R\$\s*([\d.]+,\d{2})/.exec(cdText);
    if (salMatch) {
      const val = Number(salMatch[1].replace(/\./g, "").replace(",", "."));
      if (val > 500 && val < 100000) salarioHint = val;
    }

    const datas = [...ceText.matchAll(/\d{2}\/\d{2}\/\d{4}/g)].map(x => dmyToIso(x[0])).filter(Boolean) as string[];
    const inscricoesInicioHint = datas.length >= 2 ? datas[0] : null;
    const inscricoesFimHint = datas.length >= 2 ? datas[1] : datas[0] ?? null;

    out.push({
      url,
      titulo,
      orgao,
      cargos: [cargoPadrao],
      ufHint,
      salarioHint,
      inscricoesInicioHint,
      inscricoesFimHint,
    });
  }
  return out;
}

/** Busca editais federais no Diário Oficial da União (Seção 3) */
export function parseDouHtml(html: string): ItemLista[] {
  const out: ItemLista[] = [];
  const m = html.match(/id="_br_com_seatecnologia_in_buscadou_BuscaDouPortlet_params"[^>]*>([\s\S]*?)<\/script>/);
  if (!m) return out;
  try {
    const data = JSON.parse(m[1]);
    const arr = Array.isArray(data?.jsonArray) ? data.jsonArray : [];
    for (const item of arr) {
      const rawTitle = String(item.title ?? "").replace(/<[^>]+>/g, "").trim();
      const rawContent = String(item.content ?? "").replace(/<[^>]+>/g, " ").replace(/\s+/g, " ").trim();
      const hierarchyList: string[] = Array.isArray(item.hierarchyList) ? item.hierarchyList : [];
      const orgaoCand = hierarchyList.find(h => /universidade|instituto|minist[eé]rio da defesa|comando|prefeitura|conselho|empresa|funda[cç][aã]o/i.test(h) && !/^minist[eé]rio da educa/i.test(h))
        || hierarchyList[1]
        || hierarchyList[0]
        || "Órgão Federal";
      const orgao = orgaoCand.trim();
      const combined = `${orgao} ${rawTitle} ${rawContent}`;
      // Filtra apenas editais de concurso/seleção/residência que citem veterinária ou zootecnia
      if (!/veterin[aá]ri|zootecn/i.test(combined)) continue;
      if (!/concurso|processo seletivo|sele[cç][aã]o|resid[eê]ncia|edital/i.test(combined)) continue;
      if (/licita[cç][aã]o|preg[aã]o|extrato|dispensa|notifica[cç][aã]o|convoca[cç][aã]o|homologa[cç][aã]o|resultado|elei[cç][aã]o|citar/i.test(`${rawTitle} ${rawContent.slice(0, 180)}`)) continue;

      const cargos: string[] = [];
      if (/veterin[aá]ri/i.test(combined)) cargos.push("Médico Veterinário / Docente");
      if (/zootecn/i.test(combined)) cargos.push("Zootecnista / Docente");

      const url = `https://www.in.gov.br/web/dou/-/${item.urlTitle}`;
      out.push({
        url,
        orgao,
        titulo: `${orgao} — ${rawTitle}`,
        cargos,
        isDou: true,
        douContent: rawContent,
        douPubDate: dmyToIso(String(item.pubDate ?? "")) ?? undefined,
      });
    }
  } catch {
    // ignore parse errors
  }
  return out;
}

/** Página da notícia → texto corrido + itens de lista + parágrafos + links externos oficiais + data. */
export function parseNoticia(html: string) {
  const meta = (k: string) => (new RegExp(`(?:property|name|itemprop)="${k}"[^>]*content="([^"]+)"`).exec(html)?.[1]) ?? null;
  const publicado =
    /"datePublished"\s*:\s*"([^"]+)"/.exec(html)?.[1] ??
    /<abbr class="published" title="([^"]+)"/.exec(html)?.[1] ??
    meta("article:published_time");
  const ini = Math.max(0, html.indexOf("<article") >= 0 ? html.indexOf("<article") : html.indexOf("<h1"));
  const fimArt = html.indexOf("</article>", ini);
  const trecho = html.slice(ini, fimArt > ini ? fimArt : ini + 60000);
  const limpo = trecho.replace(/<script[\s\S]*?<\/script>|<style[\s\S]*?<\/style>/g, " ");

  const liItems = [...limpo.matchAll(/<li[^>]*>([\s\S]*?)<\/li>/gi)]
    .map(m => decode(m[1].replace(/<[^>]+>/g, " ")).replace(/\s+/g, " ").trim())
    .filter(Boolean);

  const paragraphs = [...limpo.matchAll(/<p[^>]*>([\s\S]*?)<\/p>/gi)]
    .map(m => m[1].trim())
    .filter(Boolean);

  const texto = decode(limpo.replace(/<[^>]+>/g, "\n"))
    .replace(/\n\s*\n+/g, "\n").trim().slice(0, 14000);

  const links: { url: string; anchor: string; paragraphIdx: number }[] = [];
  const seen = new Set<string>();
  paragraphs.forEach((p, idx) => {
    for (const l of p.matchAll(/<a[^>]+href="(https?:\/\/[^"]+)"[^>]*>([\s\S]*?)<\/a>/gi)) {
      const u = l[1].split("#")[0].trim();
      const anchor = decode(l[2].replace(/<[^>]+>/g, "")).trim();
      if (!isUrlOficialPermitida(u)) continue;
      if (!seen.has(u)) {
        seen.add(u);
        links.push({ url: u, anchor, paragraphIdx: idx });
      }
    }
  });

  return {
    titulo: meta("og:title") ?? decode(/<h1[^>]*>([\s\S]*?)<\/h1>/i.exec(limpo)?.[1]?.replace(/<[^>]+>/g, "") ?? "").trim(),
    publicado,
    texto,
    liItems,
    paragraphs,
    links: links.map(l => l.url).slice(0, 15),
    richLinks: links.slice(0, 15),
  };
}

export type Extraido = {
  ehVeterinaria: boolean;
  orgao: string;
  uf: string | null;
  cidade: string | null;
  cargos: { nome: string; vagas: string | null; salario: string | null; cargaHoraria: string | null }[];
  vagasVet: string | null;
  salarioMax: number | null;
  inscricoesInicio: string | null;
  inscricoesFim: string | null;
  taxa: string | null;
  banca: string | null;
  bancaUrl: string | null;
  orgaoUrl: string | null;
  editalNumero: string | null;
  escolaridade: string | null;
  etapas: string | null;
  resumo: string;
};

const MESES: Record<string, string> = {
  janeiro: "01", fevereiro: "02", marco: "03", "março": "03", abril: "04",
  maio: "05", junho: "06", julho: "07", agosto: "08", setembro: "09",
  outubro: "10", novembro: "11", dezembro: "12",
};

const ESTADOS_NOME_UF: Record<string, string> = {
  "acre": "AC", "alagoas": "AL", "amapa": "AP", "amapá": "AP", "amazonas": "AM",
  "bahia": "BA", "ceara": "CE", "ceará": "CE", "distrito federal": "DF",
  "espirito santo": "ES", "espírito santo": "ES", "goias": "GO", "goiás": "GO",
  "maranhao": "MA", "maranhão": "MA", "mato grosso do sul": "MS", "mato grosso": "MT",
  "minas gerais": "MG", "paraiba": "PB", "paraíba": "PB", "parana": "PR", "paraná": "PR",
  "pernambuco": "PE", "piaui": "PI", "piauí": "PI", "rio de janeiro": "RJ",
  "rio grande do norte": "RN", "rio grande do sul": "RS", "rondonia": "RO", "rondônia": "RO",
  "roraima": "RR", "santa catarina": "SC", "sao paulo": "SP", "são paulo": "SP",
  "sergipe": "SE", "tocantins": "TO", "para": "PA", "pará": "PA",
};

const BANCAS_CONHECIDAS = [
  "FGV", "Fundação Getulio Vargas", "Cebraspe", "Cespe", "Vunesp", "Fuvest", "FCC",
  "Fundação Carlos Chagas", "IBFC", "Instituto AOCP", "AOCP", "Instituto ACCESS",
  "Fundatec", "Quadrix", "Consulplan", "Instituto Consulplan", "Fepese", "Selecon",
  "Instituto Selecon", "Igeduc", "Avança SP", "Nosso Rumo", "Idecan", "Objetiva Concursos",
  "Objetiva", "Fauel", "Unifil", "Ibam", " FGV ", "Faurgs", "La Salle", "Legalle",
  "webaula", "RBO", "Indepac", "SHDias", "Omni", " Apta ", " IGEDUC ", " Ameosc ",
  " Unoesc ", " Fundep ", " Fumarc ", " Copeve ", " Comperve ", " NC/UFPR ", " Fafipa ",
  " Instituto Mais ", " Zambini ", " Integri ", " Inaz do Pará ", " Cetrede ", " Imparh ",
  " Adm&Tec ", " Upnet ", " Ivin ", " Crescer ", " Machado de Assis ", " Reis & Reis ",
];

/** Converte datas em português (ex.: "22 de outubro de 2026" ou "22/10/2026") encontradas em um trecho */
function extrairDatasPt(texto: string, anoPadrao = new Date().getFullYear()): string[] {
  const achadas: { idx: number; iso: string }[] = [];

  for (const m of texto.matchAll(/(\d{1,2})\/(\d{2})\/(20\d{2})/g)) {
    achadas.push({ idx: m.index ?? 0, iso: `${m[3]}-${m[2]}-${m[1].padStart(2, "0")}` });
  }

  const reExtenso = /(\d{1,2})[º°]?\s+de\s+(janeiro|fevereiro|mar[cç]o|abril|maio|junho|julho|agosto|setembro|outubro|novembro|dezembro)(?:\s+de\s+(20\d{2}))?/gi;
  const matchesExtenso = [...texto.matchAll(reExtenso)];
  // Descobre o ano explícito mais próximo à direita quando omitido (ex.: "22 de outubro a 10 de dezembro de 2026")
  for (let i = 0; i < matchesExtenso.length; i++) {
    const m = matchesExtenso[i];
    const dia = m[1].padStart(2, "0");
    const mes = MESES[m[2].toLowerCase()] ?? "01";
    let ano = m[3];
    if (!ano) {
      const proxComAno = matchesExtenso.slice(i + 1).find(x => x[3]);
      ano = proxComAno?.[3] ?? String(anoPadrao);
    }
    achadas.push({ idx: m.index ?? 0, iso: `${ano}-${mes}-${dia}` });
  }

  // Datas do tipo "10 e 11 de novembro" ou "22 a 30 de novembro de 2026"
  for (const m of texto.matchAll(/(\d{1,2})[º°]?\s+(?:a|até|e)\s+(\d{1,2})[º°]?\s+de\s+(janeiro|fevereiro|mar[cç]o|abril|maio|junho|julho|agosto|setembro|outubro|novembro|dezembro)(?:\s+de\s+(20\d{2}))?/gi)) {
    const mes = MESES[m[3].toLowerCase()] ?? "01";
    const ano = m[4] ?? String(anoPadrao);
    achadas.push({ idx: (m.index ?? 0) - 1, iso: `${ano}-${mes}-${m[1].padStart(2, "0")}` });
    achadas.push({ idx: m.index ?? 0, iso: `${ano}-${mes}-${m[2].padStart(2, "0")}` });
  }

  achadas.sort((a, b) => a.idx - b.idx);
  const unicas: string[] = [];
  for (const a of achadas) {
    if (!unicas.includes(a.iso)) unicas.push(a.iso);
  }
  return unicas;
}

/**
 * Extrator determinístico completo: funciona 100% offline/sem LLM,
 * extraindo fatos reais da página da notícia e dos metadados da listagem.
 */
export function extrairDeterministico(item: ItemLista, pagina: ReturnType<typeof parseNoticia>): Extraido {
  const textoCompleto = `${item.orgao}\n${item.titulo}\n${pagina.titulo ?? ""}\n${pagina.texto}`;
  const RE_VET_ZOO = /veterin[aá]ri|zootecn|defesa agropecu[aá]ria|inspe[cç][aã]o de produtos de origem animal|sanidade animal/i;

  // 1. Órgão limpo, UF e Cidade
  let uf: string | null = item.ufHint ?? null;
  if (!uf) {
    const mUf = /\b(?:-\s*|\/|\bde\s+)(AC|AL|AP|AM|BA|CE|DF|ES|GO|MA|MT|MS|MG|PA|PB|PR|PE|PI|RJ|RN|RS|RO|RR|SC|SP|SE|TO)\b/.exec(`${item.orgao} ${item.titulo}`);
    if (mUf) uf = mUf[1].toUpperCase();
  }
  if (!uf) {
    const buscaUf = `${item.orgao} ${item.titulo} ${pagina.texto.slice(0, 900)}`.toLowerCase();
    for (const [nome, sigla] of Object.entries(ESTADOS_NOME_UF)) {
      if (
        buscaUf.includes(`estado de ${nome}`) ||
        buscaUf.includes(`estado do ${nome}`) ||
        buscaUf.includes(`estado da ${nome}`) ||
        buscaUf.includes(`de ${nome}`) ||
        buscaUf.includes(`do ${nome}`) ||
        buscaUf.includes(`em ${nome}`)
      ) {
        uf = sigla;
        break;
      }
    }
  }

  let orgao = item.orgao
    .replace(/\s*-\s*(AC|AL|AP|AM|BA|CE|DF|ES|GO|MA|MT|MS|MG|PA|PB|PR|PE|PI|RJ|RN|RS|RO|RR|SC|SP|SE|TO)$/i, "")
    .trim();

  let cidade: string | null = null;
  const mCidade = /(?:Prefeitura|C[âa]mara)(?:\s+Municipal)?\s+de\s+([A-ZÀ-Ú][a-zà-ú]+(?:\s+(?:de|da|do|dos|das|e|[A-ZÀ-Ú][a-zà-ú]+))*)/.exec(orgao);
  if (mCidade) {
    cidade = mCidade[1].trim();
  }

  // 2. Jornada e Salários gerais mencionados no texto
  let cargaGeral: string | null = null;
  const mCarga = /(\d{2}(?:\s*a\s*\d{2})?\s*horas\s*semanais|\d{2}h\s*semanais|\d{2}h)/i.exec(pagina.texto);
  if (mCarga) cargaGeral = mCarga[1].replace(/\s+semanais/i, "/sem");

  const salariosNoTexto = [...pagina.texto.matchAll(/R\$\s*([\d.]+,\d{2})/g)]
    .map(m => ({ str: `R$ ${m[1]}`, val: Number(m[1].replace(/\./g, "").replace(",", ".")) }))
    .filter(x => x.val >= 1200 && x.val <= 45000);

  let salarioMax: number | null = item.salarioHint ?? null;
  if (!salarioMax && salariosNoTexto.length > 0) {
    salarioMax = Math.max(...salariosNoTexto.map(s => s.val));
  }

  // 3. Cargos de Veterinária e Zootecnia nas <li> da notícia
  const cargosExtraidos: { nome: string; vagas: string | null; salario: string | null; cargaHoraria: string | null }[] = [];
  for (const li of pagina.liItems) {
    if (!RE_VET_ZOO.test(li)) continue;
    const mVagas = /\(([^)]*(?:vaga|CR|reserva)[^)]*)\)/i.exec(li);
    const vagas = mVagas ? mVagas[1].trim() : null;
    const mSal = /R\$\s*[\d.]+,\d{2}/.exec(li);
    const mCh = /(\d{2}\s*h(?:oras)?)/i.exec(li);
    const nomeLimpo = li
      .replace(/\s*\([^)]*(?:vaga|CR|reserva)[^)]*\)/gi, "")
      .replace(/\s*-\s*R\$\s*[\d.]+,\d{2}.*$/i, "")
      .trim()
      .slice(0, 140);
    if (nomeLimpo && !cargosExtraidos.some(c => c.nome.toLowerCase() === nomeLimpo.toLowerCase())) {
      cargosExtraidos.push({
        nome: nomeLimpo,
        vagas: vagas ?? "Ver edital",
        salario: mSal ? mSal[0] : salarioMax ? `até ${ salarioMax.toLocaleString("pt-BR", { style: "currency", currency: "BRL" }) }` : null,
        cargaHoraria: mCh ? mCh[1] : cargaGeral,
      });
    }
  }

  if (cargosExtraidos.length === 0 && item.cargos.length > 0) {
    for (const cg of item.cargos) {
      if (!RE_VET_ZOO.test(cg)) continue;
      const nomeFormatado = cg
        .toLowerCase()
        .replace(/(?:^|\s|-|\/)\S/g, l => l.toUpperCase())
        .replace(/\bDe\b/g, "de")
        .replace(/\bDa\b/g, "da")
        .replace(/\bDo\b/g, "do")
        .replace(/\bEm\b/g, "em")
        .replace(/\bPara\b/g, "para");
      cargosExtraidos.push({
        nome: nomeFormatado,
        vagas: "Ver edital",
        salario: salarioMax ? `até ${salarioMax.toLocaleString("pt-BR", { style: "currency", currency: "BRL" })}` : null,
        cargaHoraria: cargaGeral,
      });
    }
  }

  const ehVeterinaria = cargosExtraidos.length > 0 || RE_VET_ZOO.test(textoCompleto);

  // Calcula total de vagasVet
  let totalVagasNum = 0;
  let temCR = false;
  for (const c of cargosExtraidos) {
    const v = c.vagas ?? "";
    const mNum = /(\d+)\s*vaga/i.exec(v);
    if (mNum) totalVagasNum += Number(mNum[1]);
    if (/\bCR\b|reserva/i.test(v)) temCR = true;
  }
  const vagasVet =
    totalVagasNum > 0 && temCR ? `${totalVagasNum} vaga${totalVagasNum > 1 ? "s" : ""} + CR` :
    totalVagasNum > 0 ? `${totalVagasNum} vaga${totalVagasNum > 1 ? "s" : ""}` :
    temCR ? "Cadastro Reserva (CR)" :
    cargosExtraidos.length > 0 ? "Vagas no edital" : null;

  // 4. Datas de Inscrição, Taxa e Banca
  let inscricoesInicio: string | null = item.inscricoesInicioHint ?? null;
  let inscricoesFim: string | null = item.inscricoesFimHint ?? null;
  let taxa: string | null = null;
  let banca: string | null = null;
  let bancaUrl: string | null = null;
  let orgaoUrl: string | null = null;

  // Primeiro link do 1º parágrafo geralmente é o site oficial do órgão
  const firstLink = pagina.richLinks.find(l => l.paragraphIdx === 0);
  if (firstLink && isUrlOficialPermitida(firstLink.url)) {
    orgaoUrl = firstLink.url;
  }

  // Parágrafo de inscrição
  const pInscIdx = pagina.paragraphs.findIndex(p => /inscri[cç][oõ]es/i.test(p));
  const pInscText = pInscIdx >= 0
    ? decode(pagina.paragraphs[pInscIdx].replace(/<[^>]+>/g, " ")).replace(/\s+/g, " ")
    : pagina.texto;

  if (!inscricoesFim || !inscricoesInicio) {
    const anoPub = pagina.publicado ? new Date(pagina.publicado).getFullYear() : new Date().getFullYear();
    // Corta antes de falar de "isenção" para não pegar a data de isenção como fim da inscrição
    const trechoInsc = pInscText.split(/isen[cç][aã]o/i)[0] || pInscText;
    const datasInsc = extrairDatasPt(trechoInsc, isNaN(anoPub) ? new Date().getFullYear() : anoPub);
    if (datasInsc.length >= 2) {
      inscricoesInicio = inscricoesInicio ?? datasInsc[0];
      inscricoesFim = inscricoesFim ?? datasInsc[1];
    } else if (datasInsc.length === 1) {
      inscricoesFim = inscricoesFim ?? datasInsc[0];
    }
  }

  // Taxa de inscrição
  const mTaxa = /taxa(?:s)?\s+(?:de\s+inscri[cç][aã]o\s+)?(?:[eé]\s+de|varia(?:m)?\s+de|s[aã]o\s+de|no\s+valor\s+de)?\s*(R\$\s*[\d.,]+(?:\s*(?:a|ou)\s*R\$\s*[\d.,]+)?)/i.exec(pInscText);
  if (mTaxa) {
    taxa = mTaxa[1].trim();
  } else {
    const taxas = [...pInscText.matchAll(/R\$\s*([\d.]+,\d{2})/g)]
      .map(m => ({ str: `R$ ${m[1]}`, val: Number(m[1].replace(/\./g, "").replace(",", ".")) }))
      .filter(x => x.val >= 15 && x.val <= 450);
    if (taxas.length > 0) taxa = taxas[taxas.length - 1].str;
  }

  // Link e nome da banca no parágrafo de inscrição
  if (pInscIdx >= 0) {
    const linkBanca = pagina.richLinks.find(l => l.paragraphIdx === pInscIdx);
    if (linkBanca && isUrlOficialPermitida(linkBanca.url)) {
      bancaUrl = linkBanca.url;
      const nomeAncora = linkBanca.anchor
        .replace(/^(?:site|p[aá]gina|portal|endere[cç]o)\s+(?:da|do|de)\s+/i, "")
        .trim();
      if (nomeAncora.length >= 2 && nomeAncora.length <= 80 && !/inscri|edital|aqui|link/i.test(nomeAncora)) {
        banca = nomeAncora;
      }
    }
  }

  if (!banca) {
    for (const b of BANCAS_CONHECIDAS) {
      const re = new RegExp(`\\b${b.trim().replace(/[.*+?^${}()|[\]\\]/g, "\\$&")}\\b`, "i");
      if (re.test(pagina.texto)) {
        banca = b.trim();
        break;
      }
    }
  }

  if (!orgaoUrl && pagina.richLinks.length > 0) {
    const cand = pagina.richLinks.find(l => l.url !== bancaUrl && isUrlOficialPermitida(l.url));
    if (cand) orgaoUrl = cand.url;
  }

  // 5. Etapas e Escolaridade
  const etapasAchadas: string[] = [];
  if (/prova\s+(?:escrita\s+)?objetiva/i.test(pagina.texto)) etapasAchadas.push("Prova objetiva");
  if (/prova\s+discursiva|prova\s+dissertativa|reda[cç][aã]o/i.test(pagina.texto)) etapasAchadas.push("Prova discursiva");
  if (/prova\s+pr[aá]tica/i.test(pagina.texto)) etapasAchadas.push("Prova prática");
  if (/prova\s+de\s+t[ií]tulos|an[aá]lise\s+de\s+t[ií]tulos|avalia[cç][aã]o\s+de\s+t[ií]tulos/i.test(pagina.texto)) etapasAchadas.push("Prova de títulos");
  if (/teste\s+de\s+aptid[aã]o\s+f[ií]sica|taf\b/i.test(pagina.texto)) etapasAchadas.push("Aptidão física");
  const etapas = etapasAchadas.length > 0 ? etapasAchadas.join(", ") : "Ver edital";

  const temVet = cargosExtraidos.some(c => /veterin/i.test(c.nome)) || /veterin/i.test(item.cargos.join(" "));
  const temZoo = cargosExtraidos.some(c => /zootecn/i.test(c.nome)) || /zootecn/i.test(item.cargos.join(" "));
  const escolaridade =
    temVet && temZoo ? "Superior em Medicina Veterinária / Zootecnia" :
    temZoo ? "Superior em Zootecnia" :
    "Superior em Medicina Veterinária";

  const mEdital = /edital\s*(?:de\s+abertura\s*)?n[º°.]?\s*([\d./-]{2,20})/i.exec(pagina.texto);
  const editalNumero = mEdital ? `Edital nº ${mEdital[1]}` : null;

  // 6. Resumo autoral VetRank
  const nomesCargos = cargosExtraidos.map(c => c.nome).slice(0, 2).join(" e ") || (temZoo ? "Zootecnista" : "Médico Veterinário");
  const partesResumo: string[] = [
    `${orgao}${uf ? ` (${uf})` : ""} realiza seleção pública com oportunidades para ${nomesCargos}.`,
  ];
  const detalhesVaga: string[] = [];
  if (vagasVet && vagasVet !== "Vagas no edital") detalhesVaga.push(`oferta de ${vagasVet}`);
  if (salarioMax) detalhesVaga.push(`remuneração de até ${salarioMax.toLocaleString("pt-BR", { style: "currency", currency: "BRL" })}`);
  if (cargaGeral) detalhesVaga.push(`jornada de ${cargaGeral}`);
  if (detalhesVaga.length > 0) {
    partesResumo.push(`O edital prevê ${detalhesVaga.join(", ")}.`);
  }
  if (inscricoesFim) {
    const fimBR = new Date(`${inscricoesFim}T12:00:00-03:00`).toLocaleDateString("pt-BR", { timeZone: "America/Sao_Paulo" });
    const iniBR = inscricoesInicio ? new Date(`${inscricoesInicio}T12:00:00-03:00`).toLocaleDateString("pt-BR", { timeZone: "America/Sao_Paulo" }) : null;
    partesResumo.push(
      `As inscrições ${iniBR ? `ocorrem de ${iniBR} a ${fimBR}` : `seguem abertas até ${fimBR}`}${banca ? `, sob organização da banca ${banca}` : ""}.`
    );
  } else if (banca) {
    partesResumo.push(`A seleção é organizada pela banca ${banca}.`);
  }

  const RE_CLICKBAIT = /\b(concurso previsto|comiss[aã]o formada|comiss[aã]o organizadora formada|autorizado|aguarda edital|pode abrir|solicita abertura|prev[eê] abertura)\b/i;
  const ehClickbait = RE_CLICKBAIT.test(`${item.titulo} ${pagina.titulo ?? ""}`) && !inscricoesFim && !inscricoesInicio;
  const semDatasNemEdital = !inscricoesFim && !inscricoesInicio && !editalNumero;
  const ehValido = ehVeterinaria && !ehClickbait && !semDatasNemEdital;

  return {
    ehVeterinaria: ehValido,
    orgao,
    uf,
    cidade,
    cargos: cargosExtraidos,
    vagasVet,
    salarioMax,
    inscricoesInicio,
    inscricoesFim,
    taxa,
    banca,
    bancaUrl,
    orgaoUrl,
    editalNumero,
    escolaridade,
    etapas,
    resumo: partesResumo.join(" "),
  };
}

export function promptExtracao(item: ItemLista, pagina: ReturnType<typeof parseNoticia>) {
  return [
    {
      role: "system",
      content:
        "Você extrai FATOS de anúncios de concursos públicos brasileiros para Medicina Veterinária e Zootecnia. Responda só com JSON. " +
        "Não invente: campo ausente no texto = null. Datas no formato YYYY-MM-DD. " +
        "URLs: use SOMENTE as da lista fornecida (ou null), nunca sites de terceiros/concorrentes. " +
        "Em 'cargos' liste APENAS cargos de Medicina Veterinária e Zootecnia (Médico Veterinário, Veterinário, Zootecnista, Fiscal/Auditor Agropecuário, " +
        "Auxiliar/Técnico de veterinária) com vagas ('3', 'CR', '1 + CR'), salário ('R$ 8.500,00') e carga horária. " +
        "'resumo': 2 a 3 frases ORIGINAIS suas (não copie frases do texto), objetivas, para estudantes e profissionais de veterinária e zootecnia: " +
        "quem abriu, vagas/salário, prazo de inscrição e banca. " +
        "'salarioMax' = maior salário DOS CARGOS DE VETERINÁRIA/ZOOTECNIA (número, ex.: 8500.5). " +
        "'ehVeterinaria' = true se houver ao menos um cargo da área de Medicina Veterinária ou Zootecnia.",
    },
    {
      role: "user",
      content:
        `Órgão (listagem): ${item.orgao}\nTítulo: ${pagina.titulo ?? item.titulo}\nCargos listados: ${item.cargos.join("; ")}\n` +
        `Publicado em: ${pagina.publicado ?? "?"}\nLinks oficiais permitidos:\n${pagina.links.map(l => "- " + l).join("\n") || "(nenhum)"}\n\n` +
        `TEXTO:\n${pagina.texto}\n\n` +
        'JSON: {"ehVeterinaria":true,"orgao":"","uf":"SP","cidade":"","cargos":[{"nome":"","vagas":"","salario":"","cargaHoraria":""}],' +
        '"vagasVet":"","salarioMax":0,"inscricoesInicio":"","inscricoesFim":"","taxa":"","banca":"","bancaUrl":"","orgaoUrl":"",' +
        '"editalNumero":"","escolaridade":"","etapas":"","resumo":""}',
    },
  ];
}

async function extrairComFallback(item: ItemLista, pagina: ReturnType<typeof parseNoticia>): Promise<Extraido> {
  const det = extrairDeterministico(item, pagina);
  if (!hasLLMConfigured()) return det;
  try {
    const res = await invokeLLM({ messages: promptExtracao(item, pagina) as any, responseFormat: { type: "json_object" } });
    const txt = res.choices[0].message.content;
    if (typeof txt !== "string") return det;
    const llm = JSON.parse(txt) as Partial<Extraido>;
    return {
      ehVeterinaria: llm.ehVeterinaria ?? det.ehVeterinaria,
      orgao: llm.orgao || det.orgao,
      uf: (llm.uf && /^[A-Z]{2}$/.test(llm.uf) ? llm.uf : null) ?? det.uf,
      cidade: llm.cidade || det.cidade,
      cargos: Array.isArray(llm.cargos) && llm.cargos.length > 0 ? llm.cargos : det.cargos,
      vagasVet: llm.vagasVet || det.vagasVet,
      salarioMax: (llm.salarioMax && llm.salarioMax > 500 ? llm.salarioMax : null) ?? det.salarioMax,
      inscricoesInicio: llm.inscricoesInicio || det.inscricoesInicio,
      inscricoesFim: llm.inscricoesFim || det.inscricoesFim,
      taxa: llm.taxa || det.taxa,
      banca: llm.banca || det.banca,
      bancaUrl: (llm.bancaUrl && isUrlOficialPermitida(llm.bancaUrl) ? llm.bancaUrl : null) ?? det.bancaUrl,
      orgaoUrl: (llm.orgaoUrl && isUrlOficialPermitida(llm.orgaoUrl) ? llm.orgaoUrl : null) ?? det.orgaoUrl,
      editalNumero: llm.editalNumero || det.editalNumero,
      escolaridade: llm.escolaridade || det.escolaridade,
      etapas: llm.etapas || det.etapas,
      resumo: llm.resumo || det.resumo,
    };
  } catch {
    return det;
  }
}

export const slugify = (s: string) => s.normalize("NFKD").replace(/[̀-ͯ]/g, "").toLowerCase()
  .replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "").slice(0, 110);

const data = (s: string | null | undefined) => {
  if (!s || !/^\d{4}-\d{2}-\d{2}/.test(s)) return null;
  const d = new Date(`${s.slice(0, 10)}T23:59:00-03:00`);
  return isNaN(d.getTime()) ? null : d;
};
const urlPermitida = (u: string | null, links: string[]) =>
  u && isUrlOficialPermitida(u) && links.some(l => l.startsWith(u) || u.startsWith(l)) ? u.slice(0, 500) : null;
const normOrgao = (s: string) => slugify(s).replace(/-(sp|rj|mg|rs|pr|sc|ba|go|pe|ce|pa|ma|mt|ms|es|pb|rn|al|pi|se|am|ro|to|ac|ap|rr|df)$/, "");

/** Coleta e mescla todas as fontes (Veterinária + Zootecnia + Tabelas de Vagas + DOU) */
export async function coletarItensFontes(): Promise<ItemLista[]> {
  const mapa = new Map<string, ItemLista>();

  // 1. Tabelas de vagas (trazem UF, salário e datas já estruturados)
  for (const f of FONTES_VAGAS) {
    try {
      const html = await baixar(f.url);
      for (const it of parseTabelaVagas(html, f.cargoPadrao)) {
        const atual = mapa.get(it.url);
        if (atual) {
          for (const c of it.cargos) if (!atual.cargos.includes(c)) atual.cargos.push(c);
          atual.ufHint = atual.ufHint ?? it.ufHint;
          atual.salarioHint = atual.salarioHint ?? it.salarioHint;
          atual.inscricoesInicioHint = atual.inscricoesInicioHint ?? it.inscricoesInicioHint;
          atual.inscricoesFimHint = atual.inscricoesFimHint ?? it.inscricoesFimHint;
        } else {
          mapa.set(it.url, it);
        }
      }
    } catch (e: any) {
      console.warn("[Concursos] falha em fonte vagas:", f.url, e?.message);
    }
  }

  // 2. Listas completas por cargo (Veterinário e Zootecnista)
  for (const f of FONTES_CARGOS) {
    try {
      const html = await baixar(f.url);
      const padrao = f.areaPadrao === "zootecnia" ? "Zootecnista" : "Médico Veterinário";
      for (const it of parseLista(html, padrao)) {
        const atual = mapa.get(it.url);
        if (atual) {
          for (const c of it.cargos) if (!atual.cargos.includes(c)) atual.cargos.push(c);
        } else {
          mapa.set(it.url, it);
        }
      }
    } catch (e: any) {
      console.warn("[Concursos] falha em fonte cargos:", f.url, e?.message);
    }
  }

  // 3. Diário Oficial da União (DOU - Seção 3)
  for (const u of DOU_URLS) {
    try {
      const html = await baixar(u);
      for (const it of parseDouHtml(html)) {
        if (!mapa.has(it.url)) mapa.set(it.url, it);
      }
    } catch (e: any) {
      console.warn("[Concursos] falha em DOU:", u, e?.message);
    }
  }

  return Array.from(mapa.values());
}

/** Sincroniza o arquivo local data/concursos_seed.json com o banco no boot do servidor */
export async function syncConcursosSeed(): Promise<number> {
  const db = await getDb();
  if (!db) return 0;
  try {
    const seedPath = path.resolve(process.cwd(), "data/concursos_seed.json");
    if (!fs.existsSync(seedPath)) return 0;
    const items = JSON.parse(fs.readFileSync(seedPath, "utf8"));
    if (!Array.isArray(items) || items.length === 0) return 0;

    const conhecidos = new Set(((await db.execute(sql`SELECT slug, sourceUrl FROM concursos`)) as any)[0].map((r: any) => r.sourceUrl));
    let inseridos = 0;
    for (const c of items) {
      if (!c.slug || !c.sourceUrl || conhecidos.has(c.sourceUrl)) continue;
      try {
        await db.insert(concursos).values({
          slug: c.slug,
          sourceUrl: c.sourceUrl,
          sourceName: "Radar VetRank",
          orgao: c.orgao,
          uf: c.uf || null,
          cidade: c.cidade || null,
          cargos: c.cargos ?? [],
          vagasVet: c.vagasVet || null,
          salarioMax: c.salarioMax ? String(c.salarioMax) as any : null,
          inscricoesInicio: c.inscricoesInicio ? new Date(c.inscricoesInicio) : null,
          inscricoesFim: c.inscricoesFim ? new Date(c.inscricoesFim) : null,
          taxa: c.taxa || null,
          banca: c.banca || null,
          bancaUrl: isUrlOficialPermitida(c.bancaUrl) ? c.bancaUrl : null,
          orgaoUrl: isUrlOficialPermitida(c.orgaoUrl) ? c.orgaoUrl : null,
          editalNumero: c.editalNumero || null,
          escolaridade: c.escolaridade || null,
          etapas: c.etapas || null,
          resumo: c.resumo || null,
          publishedAt: c.publishedAt ? new Date(c.publishedAt) : new Date(),
          active: c.active !== false,
        });
        conhecidos.add(c.sourceUrl);
        inseridos++;
      } catch {
        // ignore duplicate slug
      }
    }
    if (inseridos > 0) console.log(`[Concursos Seed] Inserted ${inseridos} contests from seed.`);
    return inseridos;
  } catch (err) {
    console.warn("[Concursos Seed] Failed to sync seed:", err);
    return 0;
  }
}

export async function syncConcursos(maxItens = MAX_POR_EXECUCAO): Promise<{ lista: number; novos: number; inseridos: number; atualizados: number; ignorados: number; erros: string[] }> {
  const db = await getDb();
  if (!db) throw new Error("DB indisponível");
  const itens = await coletarItensFontes();

  const conhecidos = new Set(((await db.execute(sql`SELECT sourceUrl FROM concursos`)) as any)[0].map((r: any) => r.sourceUrl));
  const novos = itens.filter(i => !conhecidos.has(i.url)).slice(0, maxItens);
  let inseridos = 0, atualizados = 0, ignorados = 0;
  const erros: string[] = [];

  for (const [idx, item] of novos.entries()) {
    if (idx > 0) await new Promise(r => setTimeout(r, PAUSA_MS));
    try {
      let x: Extraido;
      let linksPermitidos: string[] = [];
      let publicadoIso: string | null = null;

      if (item.isDou) {
        publicadoIso = item.douPubDate ?? new Date().toISOString().slice(0, 10);
        linksPermitidos = [item.url];
        x = extrairDeterministico(item, {
          titulo: item.titulo,
          publicado: publicadoIso,
          texto: item.douContent ?? item.titulo,
          liItems: [],
          paragraphs: [item.douContent ?? ""],
          links: [item.url],
          richLinks: [{ url: item.url, anchor: "Diário Oficial da União", paragraphIdx: 0 }],
        });
        x.orgaoUrl = item.url;
        x.banca = x.banca ?? "Diário Oficial da União (DOU)";
      } else {
        const pagina = parseNoticia(await baixar(item.url));
        linksPermitidos = pagina.links;
        publicadoIso = pagina.publicado;
        // 100% determinístico e gratuito (zero custo de API de IA)
        x = extrairDeterministico(item, pagina);
      }

      const orgao = (x.orgao || item.orgao).slice(0, 250);
      const fim = data(x.inscricoesFim);
      const sourceId = crypto.createHash("md5").update(item.url).digest("hex").slice(0, 8);
      const base = {
        sourceUrl: item.url,
        sourceName: "Radar VetRank",
        orgao,
        uf: x.uf && /^[A-Z]{2}$/.test(x.uf) ? x.uf : null,
        cidade: x.cidade?.slice(0, 120) || null,
        cargos: (x.cargos ?? []).slice(0, 12) as any,
        vagasVet: x.vagasVet?.slice(0, 60) || null,
        salarioMax: x.salarioMax && x.salarioMax > 500 && x.salarioMax < 100000 ? String(x.salarioMax) as any : null,
        inscricoesInicio: data(x.inscricoesInicio),
        inscricoesFim: fim,
        taxa: x.taxa?.slice(0, 120) || null,
        banca: x.banca?.slice(0, 120) || null,
        bancaUrl: urlPermitida(x.bancaUrl, linksPermitidos),
        orgaoUrl: urlPermitida(x.orgaoUrl, linksPermitidos),
        editalNumero: x.editalNumero?.slice(0, 60) || null,
        escolaridade: x.escolaridade?.slice(0, 60) || null,
        etapas: x.etapas?.slice(0, 250) || null,
        resumo: x.resumo?.slice(0, 1200) || null,
        publishedAt: publicadoIso ? new Date(publicadoIso) : new Date(),
      };

      if (!x.ehVeterinaria) {
        await db.insert(concursos).values({ ...base, slug: `ignorado-${sourceId}`, active: false });
        ignorados++; continue;
      }

      const [existente] = ((await db.execute(sql`
        SELECT id, orgao, inscricoesFim FROM concursos WHERE active = 1 AND createdAt >= NOW() - INTERVAL 120 DAY`)) as any)[0]
        .filter((r: any) => normOrgao(r.orgao) === normOrgao(orgao));
      if (existente) {
        const { sourceUrl, ...upd } = base;
        await db.update(concursos).set(Object.fromEntries(Object.entries(upd).filter(([, v]) => v != null && !(Array.isArray(v) && !v.length))) as any)
          .where(eq(concursos.id, existente.id));
        await db.insert(concursos).values({ ...base, slug: `dup-${sourceId}`, active: false });
        atualizados++; continue;
      }

      const ano = (fim ?? new Date()).getFullYear();
      await db.insert(concursos).values({ ...base, slug: `${slugify(`${orgao} ${base.uf ?? ""} ${ano}`)}-${sourceId}`, active: true });
      inseridos++;
    } catch (e: any) {
      erros.push(`${item.url}: ${e?.message ?? e}`);
      console.error("[Concursos] falha:", item.url, e?.message);
    }
  }
  return { lista: itens.length, novos: novos.length, inseridos, atualizados, ignorados, erros };
}

export type StatusConcurso = "aberto" | "vai_abrir" | "verificar" | "encerrado";
export type AreaConcurso = "veterinaria" | "zootecnia" | "ambas";

export function classificarConcurso(r: any, agora = Date.now()) {
  const ini = r.inscricoesInicio ? new Date(r.inscricoesInicio).getTime() : null;
  const fim = r.inscricoesFim ? new Date(r.inscricoesFim).getTime() : null;

  let status: StatusConcurso;
  if (ini && ini > agora && (!fim || fim >= agora)) {
    status = "vai_abrir";
  } else if (fim != null) {
    status = fim >= agora ? "aberto" : "encerrado";
  } else {
    status = "verificar";
  }

  const diasRestantes = fim ? Math.ceil((fim - agora) / 86400000) : null;
  const diasParaAbrir = ini && ini > agora ? Math.ceil((ini - agora) / 86400000) : null;

  const textoCargos = `${r.escolaridade ?? ""} ${JSON.stringify(r.cargos ?? [])}`.toLowerCase();
  const temVet = /veterin/i.test(textoCargos) || !/zootecn/i.test(textoCargos);
  const temZoo = /zootecn/i.test(textoCargos);
  const area: AreaConcurso = temVet && temZoo ? "ambas" : temZoo ? "zootecnia" : "veterinaria";

  // Sanitiza e nunca expõe sourceUrl ou links de concorrentes
  const { sourceUrl: _su, sourceName: _sn, ...limpo } = r;
  return {
    ...limpo,
    bancaUrl: isUrlOficialPermitida(r.bancaUrl) ? r.bancaUrl : null,
    orgaoUrl: isUrlOficialPermitida(r.orgaoUrl) ? r.orgaoUrl : null,
    status,
    diasRestantes,
    diasParaAbrir,
    area,
  };
}

function carregarSeedFallback(): any[] {
  try {
    const seedPath = path.resolve(process.cwd(), "data/concursos_seed.json");
    if (fs.existsSync(seedPath)) {
      const parsed = JSON.parse(fs.readFileSync(seedPath, "utf8"));
      if (Array.isArray(parsed)) return parsed.filter(c => c.active !== false);
    }
  } catch {
    // ignore
  }
  return [];
}

/** Lista pública: inscrições abertas primeiro (prazo mais próximo), depois vai_abrir, depois verificar, depois encerrados recentes. */
export async function listarConcursos(opts: { uf?: string; area?: string; incluirEncerrados?: boolean; limite?: number } = {}) {
  const db = await getDb();
  let rows: any[] = [];
  if (db) {
    try {
      const conds = [eq(concursos.active, true)];
      if (opts.uf) conds.push(eq(concursos.uf, opts.uf));
      rows = await db.select().from(concursos).where(and(...conds)).limit(500);
    } catch (e) {
      console.warn("[listarConcursos] DB error, using seed fallback:", e);
    }
  }
  if (rows.length === 0) {
    rows = carregarSeedFallback();
    if (opts.uf) rows = rows.filter(r => r.uf === opts.uf);
  }

  const agora = Date.now();
  const comStatus = rows
    .map((r, idx) => classificarConcurso({ id: r.id ?? idx + 1, ...r }, agora))
    .filter(r => {
      if (opts.area && opts.area !== "todas") {
        if (opts.area === "veterinaria" && r.area === "zootecnia") return false;
        if (opts.area === "zootecnia" && r.area === "veterinaria") return false;
      }
      return opts.incluirEncerrados || r.status !== "encerrado" || (r.diasRestantes ?? 0) > -60;
    });

  const ordem: Record<StatusConcurso, number> = { aberto: 0, vai_abrir: 1, verificar: 2, encerrado: 3 };
  comStatus.sort((a, b) =>
    ordem[a.status as StatusConcurso] - ordem[b.status as StatusConcurso] ||
    (a.status === "aberto"
      ? (a.diasRestantes ?? 999) - (b.diasRestantes ?? 999)
      : a.status === "vai_abrir"
      ? (a.diasParaAbrir ?? 999) - (b.diasParaAbrir ?? 999)
      : +new Date(b.publishedAt ?? b.createdAt ?? 0) - +new Date(a.publishedAt ?? a.createdAt ?? 0))
  );
  return comStatus.slice(0, opts.limite ?? 200);
}

export async function buscarConcursoPorSlug(slug: string) {
  const db = await getDb();
  if (db) {
    try {
      const [c] = await db.select().from(concursos).where(and(eq(concursos.slug, slug), eq(concursos.active, true))).limit(1);
      if (c) return classificarConcurso(c);
    } catch {
      // fallback to seed
    }
  }
  const seed = carregarSeedFallback();
  const found = seed.find(c => c.slug === slug);
  return found ? classificarConcurso({ id: 1, ...found }) : null;
}

export type ConcursoListado = Awaited<ReturnType<typeof listarConcursos>>[number];

export const fmtData = (d: Date | string | null | undefined) =>
  d ? new Date(d).toLocaleDateString("pt-BR", { timeZone: "America/Sao_Paulo" }) : null;
export const fmtSalario = (v: unknown) =>
  v ? Number(v).toLocaleString("pt-BR", { style: "currency", currency: "BRL", maximumFractionDigits: 0 }) : null;


/** Post semanal do blog (rascunho, active=false → aprovação no Admin → Blog). Montado só com dados próprios do VetRank. */
export async function criarPostSemanalConcursos(): Promise<{ criado: boolean; slug?: string; total: number }> {
  const db = await getDb();
  if (!db) throw new Error("DB indisponível");
  const abertos = (await listarConcursos({ limite: 100 })).filter(c => c.status === "aberto" || c.status === "vai_abrir" || c.status === "verificar");
  if (abertos.length === 0) return { criado: false, total: 0 };

  const hoje = new Date();
  const dataBR = hoje.toLocaleDateString("pt-BR", { timeZone: "America/Sao_Paulo" });
  const slug = `concursos-veterinario-abertos-${hoje.toISOString().slice(0, 10)}`;
  const { blogPosts } = await import("../drizzle/schema");
  const [ja] = await db.select({ id: blogPosts.id }).from(blogPosts).where(eq(blogPosts.slug, slug)).limit(1);
  if (ja) return { criado: false, slug, total: abertos.length };

  const h = (x: unknown) => String(x ?? "").replace(/[&<>"]/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c]!));
  const urgentes = abertos.filter(c => c.status === "aberto" && (c.diasRestantes ?? 99) <= 7);
  const salarios = abertos.map(c => Number(c.salarioMax) || 0).filter(Boolean);
  const porUf = new Map<string, typeof abertos>();
  for (const c of abertos) { const k = c.uf ?? "Nacional / Outros"; porUf.set(k, [...(porUf.get(k) ?? []), c]); }

  const card = (c: (typeof abertos)[number]) => `
    <li><a href="/concursos/${h(c.slug)}"><strong>${h(c.orgao)}</strong></a>${c.cidade ? ` — ${h(c.cidade)}` : ""}<br/>
    ${[c.vagasVet, c.salarioMax ? `salário até ${fmtSalario(c.salarioMax)}` : null, c.inscricoesFim ? `inscrições até ${fmtData(c.inscricoesFim)}` : "prazo: ver edital", c.banca ? `banca ${c.banca}` : null]
      .filter(Boolean).map(h).join(" · ")}</li>`;

  const body = `
<p>Esta é a curadoria da semana com os <strong>${abertos.length} concursos e processos seletivos com vagas para Médico Veterinário e Zootecnista</strong> vigentes no Brasil${salarios.length ? `, com remunerações que chegam a <strong>${fmtSalario(Math.max(...salarios))}</strong>` : ""}. Acompanhe todos os editais em tempo real no <a href="/concursos">Radar de Concursos do VetRank</a>.</p>
${urgentes.length ? `<h2>Últimos dias de inscrição</h2><ul>${urgentes.map(card).join("")}</ul>` : ""}
<h2>Concursos vigentes por estado</h2>
${Array.from(porUf.entries()).sort((a, b) => a[0].localeCompare(b[0])).map(([uf, lista]) => `<h3>${h(uf)}</h3><ul>${lista.map(card).join("")}</ul>`).join("")}
<h2>Como direcionar sua preparação</h2>
<p>Provas de concursos para Medicina Veterinária e Zootecnia cobram com alta frequência Saúde Pública e Zoonoses, Inspeção de Produtos de Origem Animal (POA), Defesa Sanitária Animal, Clínica, Nutrição e Produção Animal. Pratique agora com <a href="/exam">simulados cronometrados por banca e órgão</a> e questões comentadas no VetRank.</p>
<p><em>Curadoria oficial Radar VetRank a partir de diários oficiais e editais públicos das bancas organizadoras. Confira sempre o edital oficial do certame.</em></p>`;

  await db.insert(blogPosts).values({
    slug,
    title: `Concursos para Veterinária e Zootecnia abertos — semana de ${dataBR}`,
    summary: `${abertos.length} concursos com vagas para Médico Veterinário e Zootecnista vigentes${urgentes.length ? `, ${urgentes.length} encerrando nos próximos 7 dias` : ""}. Veja prazos, vagas e salários.`.slice(0, 300),
    body,
    author: "Radar VetRank",
    category: "contest",
    coverImageUrl: `https://www.vetrank.com.br/api/og/concursos-semana.png?d=${hoje.toISOString().slice(0, 10)}`,
    active: false,
  });
  return { criado: true, slug, total: abertos.length };
}
