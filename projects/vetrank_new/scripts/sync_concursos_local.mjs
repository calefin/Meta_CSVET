#!/usr/bin/env node
/**
 * Script local e 100% gratuito (ZERO uso de API de IA) para coletar e atualizar
 * a base de concursos de Medicina Veterinária e Zootecnia (data/concursos_seed.json).
 *
 * Uso:
 *   node scripts/sync_concursos_local.mjs
 *   pnpm concursos:sync
 */
import crypto from "crypto";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const SEED_PATH = path.resolve(__dirname, "../data/concursos_seed.json");

const UA = "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36";

const FONTES_VAGAS = [
  { url: "https://www.pciconcursos.com.br/concursos/vagas/medico-veterinario", cargoPadrao: "Médico Veterinário" },
  { url: "https://www.pciconcursos.com.br/concursos/vagas/veterinario", cargoPadrao: "Médico Veterinário" },
  { url: "https://www.pciconcursos.com.br/concursos/vagas/zootecnista", cargoPadrao: "Zootecnista" },
  { url: "https://www.pciconcursos.com.br/concursos/vagas/fiscal-agropecuario", cargoPadrao: "Fiscal Agropecuário (Veterinária/Zootecnia)" },
];

const FONTES_CARGOS = [
  { url: "https://www.pciconcursos.com.br/cargos/medico-veterinario", cargoPadrao: "Médico Veterinário" },
  { url: "https://www.pciconcursos.com.br/cargos/veterinario", cargoPadrao: "Médico Veterinário" },
  { url: "https://www.pciconcursos.com.br/cargos/zootecnista", cargoPadrao: "Zootecnista" },
];

const DOU_URLS = [
  "https://www.in.gov.br/consulta/-/buscar/dou?q=%22medicina+veterinaria%22&s=do3&exactDate=mes&sortType=0",
  "https://www.in.gov.br/consulta/-/buscar/dou?q=%22medico+veterinario%22&s=do3&exactDate=mes&sortType=0",
  "https://www.in.gov.br/consulta/-/buscar/dou?q=%22zootecnia%22&s=do3&exactDate=mes&sortType=0",
  "https://www.in.gov.br/consulta/-/buscar/dou?q=%22zootecnista%22&s=do3&exactDate=mes&sortType=0",
];

const DOMINIOS_BLOQUEADOS = /pciconcursos|pci\.app\.br|estrategiaconcursos|grancursos|qconcursos|direcaoconcursos|folhadirigida|jcconcursos|concursosnobrasil|editalconcursosbrasil|acheconcursos|noticiaconcursos|concursossc|sanar|vetsmart|aprovaconcursos|novaconcursos|alfaCon|focusconcursos|cursoenfase|facebook|twitter|x\.com|linkedin|t\.me|whatsapp|unpkg|google|instagram|youtube|tiktok|bit\.ly|tinyurl/i;

function isUrlOficialPermitida(u) {
  if (!u || !/^https?:\/\//i.test(u)) return false;
  return !DOMINIOS_BLOQUEADOS.test(u);
}

const decode = (s) => s
  .replace(/&amp;/g, "&").replace(/&quot;/g, '"').replace(/&#39;/g, "'").replace(/&lt;/g, "<").replace(/&gt;/g, ">")
  .replace(/&nbsp;/g, " ").replace(/&#(\d+);/g, (_, n) => String.fromCharCode(Number(n)));

const slugify = (s) => s.normalize("NFKD").replace(/[̀-ͯ]/g, "").toLowerCase()
  .replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "").slice(0, 110);

const dmyToIso = (dmy) => {
  const m = /(\d{2})\/(\d{2})\/(\d{4})/.exec(dmy);
  return m ? `${m[3]}-${m[2]}-${m[1]}` : null;
};

async function baixar(url) {
  const r = await fetch(url, { headers: { "User-Agent": UA, "Accept-Language": "pt-BR" }, signal: AbortSignal.timeout(20000) });
  if (!r.ok) throw new Error(`HTTP ${r.status}`);
  return r.text();
}

function parseTabelaVagas(html, cargoPadrao) {
  const out = [];
  const re = /<div class="na"[^>]*data-url="([^"]+)"[\s\S]*?<div class="ca"><a[^>]*title="([^"]*)"[^>]*>([^<]+)<\/a>[\s\S]*?<div class="cc">([^<]*)<\/div>\s*<div class="cd">([\s\S]*?)<\/div><div class="ce">([\s\S]*?)<\/div>/g;
  let m;
  while ((m = re.exec(html))) {
    const url = m[1].trim();
    const titulo = decode(m[2]).trim();
    const orgao = decode(m[3]).trim();
    const ufRaw = m[4].trim().toUpperCase();
    const ufHint = /^[A-Z]{2}$/.test(ufRaw) ? ufRaw : null;
    const cdText = decode(m[5].replace(/<[^>]+>/g, " ")).replace(/\s+/g, " ").trim();
    const ceText = decode(m[6].replace(/<[^>]+>/g, " ")).replace(/\s+/g, " ").trim();

    let salarioHint = null;
    const salMatch = /R\$\s*([\d.]+,\d{2})/.exec(cdText);
    if (salMatch) {
      const val = Number(salMatch[1].replace(/\./g, "").replace(",", "."));
      if (val > 500 && val < 100000) salarioHint = val;
    }

    const datas = [...ceText.matchAll(/\d{2}\/\d{2}\/\d{4}/g)].map(x => dmyToIso(x[0])).filter(Boolean);
    const inscricoesInicioHint = datas.length >= 2 ? datas[0] : null;
    const inscricoesFimHint = datas.length >= 2 ? datas[1] : datas[0] ?? null;

    out.push({ url, titulo, orgao, cargos: [cargoPadrao], ufHint, salarioHint, inscricoesInicioHint, inscricoesFimHint });
  }
  return out;
}

function parseLista(html, cargoPadrao) {
  const out = new Map();
  let atual = null;
  const re = /<ul class="link-(d|i)">([\s\S]*?)<\/ul>/g;
  let m;
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
    for (const it of out.values()) if (it.cargos.length === 0) it.cargos.push(cargoPadrao);
  }
  return Array.from(out.values());
}

function parseDouHtml(html) {
  const out = [];
  const m = html.match(/id="_br_com_seatecnologia_in_buscadou_BuscaDouPortlet_params"[^>]*>([\s\S]*?)<\/script>/);
  if (!m) return out;
  try {
    const data = JSON.parse(m[1]);
    const arr = Array.isArray(data?.jsonArray) ? data.jsonArray : [];
    for (const item of arr) {
      const rawTitle = String(item.title ?? "").replace(/<[^>]+>/g, "").trim();
      const rawContent = String(item.content ?? "").replace(/<[^>]+>/g, " ").replace(/\s+/g, " ").trim();
      const hierarchyList = Array.isArray(item.hierarchyList) ? item.hierarchyList : [];
      const orgaoCand = hierarchyList.find(h => /universidade|instituto|minist[eé]rio da defesa|comando|prefeitura|conselho|empresa|funda[cç][aã]o/i.test(h) && !/^minist[eé]rio da educa/i.test(h))
        || hierarchyList[1]
        || hierarchyList[0]
        || "Órgão Federal";
      const orgao = orgaoCand.trim();
      const combined = `${orgao} ${rawTitle} ${rawContent}`;
      if (!/veterin[aá]ri|zootecn/i.test(combined)) continue;
      if (!/concurso|processo seletivo|sele[cç][aã]o|resid[eê]ncia|edital/i.test(combined)) continue;
      if (/licita[cç][aã]o|preg[aã]o|extrato|dispensa|notifica[cç][aã]o|convoca[cç][aã]o|homologa[cç][aã]o|resultado|elei[cç][aã]o|citar/i.test(`${rawTitle} ${rawContent.slice(0, 180)}`)) continue;
      const cargos = [];
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
  } catch {}
  return out;
}

function parseNoticia(html) {
  const meta = (k) => (new RegExp(`(?:property|name|itemprop)="${k}"[^>]*content="([^"]+)"`).exec(html)?.[1]) ?? null;
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

  const texto = decode(limpo.replace(/<[^>]+>/g, "\n")).replace(/\n\s*\n+/g, "\n").trim().slice(0, 14000);

  const links = [];
  const seen = new Set();
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
    richLinks: links.slice(0, 15),
  };
}

const MESES = {
  janeiro: "01", fevereiro: "02", marco: "03", "março": "03", abril: "04",
  maio: "05", junho: "06", julho: "07", agosto: "08", setembro: "09",
  outubro: "10", novembro: "11", dezembro: "12",
};

const ESTADOS_NOME_UF = {
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
  "Objetiva", "Fauel", "Unifil", "Ibam", "Faurgs", "La Salle", "Legalle", "RBO",
  "Indepac", "SHDias", "Omni", "Ameosc", "Unoesc", "Fundep", "Fumarc", "Copeve",
  "Comperve", "NC/UFPR", "Fafipa", "Instituto Mais", "Zambini", "Cetrede", "Imparh",
  "Adm&Tec", "Upnet", "Ivin",
];

function extrairDatasPt(texto, anoPadrao = new Date().getFullYear()) {
  const achadas = [];
  for (const m of texto.matchAll(/(\d{1,2})\/(\d{2})\/(20\d{2})/g)) {
    achadas.push({ idx: m.index ?? 0, iso: `${m[3]}-${m[2]}-${m[1].padStart(2, "0")}` });
  }
  const reExtenso = /(\d{1,2})[º°]?\s+de\s+(janeiro|fevereiro|mar[cç]o|abril|maio|junho|julho|agosto|setembro|outubro|novembro|dezembro)(?:\s+de\s+(20\d{2}))?/gi;
  const matchesExtenso = [...texto.matchAll(reExtenso)];
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
  for (const m of texto.matchAll(/(\d{1,2})[º°]?\s+(?:a|até|e)\s+(\d{1,2})[º°]?\s+de\s+(janeiro|fevereiro|mar[cç]o|abril|maio|junho|julho|agosto|setembro|outubro|novembro|dezembro)(?:\s+de\s+(20\d{2}))?/gi)) {
    const mes = MESES[m[3].toLowerCase()] ?? "01";
    const ano = m[4] ?? String(anoPadrao);
    achadas.push({ idx: (m.index ?? 0) - 1, iso: `${ano}-${mes}-${m[1].padStart(2, "0")}` });
    achadas.push({ idx: m.index ?? 0, iso: `${ano}-${mes}-${m[2].padStart(2, "0")}` });
  }
  achadas.sort((a, b) => a.idx - b.idx);
  const unicas = [];
  for (const a of achadas) if (!unicas.includes(a.iso)) unicas.push(a.iso);
  return unicas;
}

function extrairDeterministico(item, pagina) {
  const textoCompleto = `${item.orgao}\n${item.titulo}\n${pagina.titulo ?? ""}\n${pagina.texto}`;
  const RE_VET_ZOO = /veterin[aá]ri|zootecn|defesa agropecu[aá]ria|inspe[cç][aã]o de produtos de origem animal|sanidade animal/i;

  let uf = item.ufHint ?? null;
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

  const orgao = item.orgao
    .replace(/\s*-\s*(AC|AL|AP|AM|BA|CE|DF|ES|GO|MA|MT|MS|MG|PA|PB|PR|PE|PI|RJ|RN|RS|RO|RR|SC|SP|SE|TO)$/i, "")
    .trim();

  let cidade = null;
  const mCidade = /(?:Prefeitura|C[âa]mara)(?:\s+Municipal)?\s+de\s+([A-ZÀ-Ú][a-zà-ú]+(?:\s+(?:de|da|do|dos|das|e|[A-ZÀ-Ú][a-zà-ú]+))*)/.exec(orgao);
  if (mCidade) cidade = mCidade[1].trim();

  let cargaGeral = null;
  const mCarga = /(\d{2}(?:\s*a\s*\d{2})?\s*horas\s*semanais|\d{2}h\s*semanais|\d{2}h)/i.exec(pagina.texto);
  if (mCarga) cargaGeral = mCarga[1].replace(/\s+semanais/i, "/sem");

  const salariosNoTexto = [...pagina.texto.matchAll(/R\$\s*([\d.]+,\d{2})/g)]
    .map(m => ({ str: `R$ ${m[1]}`, val: Number(m[1].replace(/\./g, "").replace(",", ".")) }))
    .filter(x => x.val >= 1200 && x.val <= 45000);

  let salarioMax = item.salarioHint ?? null;
  if (!salarioMax && salariosNoTexto.length > 0) {
    salarioMax = Math.max(...salariosNoTexto.map(s => s.val));
  }

  const cargosExtraidos = [];
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
        salario: mSal ? mSal[0] : salarioMax ? `até ${salarioMax.toLocaleString("pt-BR", { style: "currency", currency: "BRL" })}` : null,
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
        .replace(/\bDe\b/g, "de").replace(/\bDa\b/g, "da").replace(/\bDo\b/g, "do").replace(/\bEm\b/g, "em").replace(/\bPara\b/g, "para");
      cargosExtraidos.push({
        nome: nomeFormatado,
        vagas: "Ver edital",
        salario: salarioMax ? `até ${salarioMax.toLocaleString("pt-BR", { style: "currency", currency: "BRL" })}` : null,
        cargaHoraria: cargaGeral,
      });
    }
  }

  const ehVeterinaria = cargosExtraidos.length > 0 || RE_VET_ZOO.test(textoCompleto);

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

  let inscricoesInicio = item.inscricoesInicioHint ?? null;
  let inscricoesFim = item.inscricoesFimHint ?? null;
  let taxa = null;
  let banca = null;
  let bancaUrl = null;
  let orgaoUrl = null;

  const firstLink = pagina.richLinks.find(l => l.paragraphIdx === 0);
  if (firstLink && isUrlOficialPermitida(firstLink.url)) orgaoUrl = firstLink.url;

  const pInscIdx = pagina.paragraphs.findIndex(p => /inscri[cç][oõ]es/i.test(p));
  const pInscText = pInscIdx >= 0
    ? decode(pagina.paragraphs[pInscIdx].replace(/<[^>]+>/g, " ")).replace(/\s+/g, " ")
    : pagina.texto;

  if (!inscricoesFim || !inscricoesInicio) {
    const anoPub = pagina.publicado ? new Date(pagina.publicado).getFullYear() : new Date().getFullYear();
    const trechoInsc = pInscText.split(/isen[cç][aã]o/i)[0] || pInscText;
    const datasInsc = extrairDatasPt(trechoInsc, isNaN(anoPub) ? new Date().getFullYear() : anoPub);
    if (datasInsc.length >= 2) {
      inscricoesInicio = inscricoesInicio ?? datasInsc[0];
      inscricoesFim = inscricoesFim ?? datasInsc[1];
    } else if (datasInsc.length === 1) {
      inscricoesFim = inscricoesFim ?? datasInsc[0];
    }
  }

  const mTaxa = /taxa(?:s)?\s+(?:de\s+inscri[cç][aã]o\s+)?(?:[eé]\s+de|varia(?:m)?\s+de|s[aã]o\s+de|no\s+valor\s+de)?\s*(R\$\s*[\d.,]+(?:\s*(?:a|ou)\s*R\$\s*[\d.,]+)?)/i.exec(pInscText);
  if (mTaxa) {
    taxa = mTaxa[1].trim();
  } else {
    const taxas = [...pInscText.matchAll(/R\$\s*([\d.]+,\d{2})/g)]
      .map(m => ({ str: `R$ ${m[1]}`, val: Number(m[1].replace(/\./g, "").replace(",", ".")) }))
      .filter(x => x.val >= 15 && x.val <= 450);
    if (taxas.length > 0) taxa = taxas[taxas.length - 1].str;
  }

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

  const etapasAchadas = [];
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

  const nomesCargos = cargosExtraidos.map(c => c.nome).slice(0, 2).join(" e ") || (temZoo ? "Zootecnista" : "Médico Veterinário");
  const partesResumo = [
    `${orgao}${uf ? ` (${uf})` : ""} realiza seleção pública com oportunidades para ${nomesCargos}.`,
  ];
  const detalhesVaga = [];
  if (vagasVet && vagasVet !== "Vagas no edital") detalhesVaga.push(`oferta de ${vagasVet}`);
  if (salarioMax) detalhesVaga.push(`remuneração de até ${salarioMax.toLocaleString("pt-BR", { style: "currency", currency: "BRL" })}`);
  if (cargaGeral) detalhesVaga.push(`jornada de ${cargaGeral}`);
  if (detalhesVaga.length > 0) partesResumo.push(`O edital prevê ${detalhesVaga.join(", ")}.`);

  if (inscricoesFim) {
    const fimBR = new Date(`${inscricoesFim}T12:00:00-03:00`).toLocaleDateString("pt-BR", { timeZone: "America/Sao_Paulo" });
    const iniBR = inscricoesInicio ? new Date(`${inscricoesInicio}T12:00:00-03:00`).toLocaleDateString("pt-BR", { timeZone: "America/Sao_Paulo" }) : null;
    partesResumo.push(`As inscrições ${iniBR ? `ocorrem de ${iniBR} a ${fimBR}` : `seguem abertas até ${fimBR}`}${banca ? `, sob organização da banca ${banca}` : ""}.`);
  } else if (banca) {
    partesResumo.push(`A seleção é organizada pela banca ${banca}.`);
  }

  // Anti-clickbait: ignora notícias de "concurso previsto", "comissão formada", "autorizado" sem datas de inscrição
  const RE_CLICKBAIT = /\b(concurso previsto|comiss[aã]o formada|comiss[aã]o organizadora formada|autorizado|aguarda edital|pode abrir|solicita abertura|prev[eê] abertura)\b/i;
  const ehClickbait = RE_CLICKBAIT.test(`${item.titulo} ${pagina.titulo ?? ""}`) && !inscricoesFim && !inscricoesInicio;
  const semDatasNemEdital = !inscricoesFim && !inscricoesInicio && !editalNumero;
  const ehValido = ehVeterinaria && !ehClickbait && !semDatasNemEdital;

  return {
    ehVeterinaria: ehValido, orgao, uf, cidade, cargos: cargosExtraidos, vagasVet, salarioMax,
    inscricoesInicio, inscricoesFim, taxa, banca, bancaUrl, orgaoUrl, editalNumero, escolaridade, etapas,
    resumo: partesResumo.join(" "),
  };
}

/**
 * Tenta visitar o site oficial da banca ou do órgão para achar um link mais específico
 * para a página do concurso ou diretamente para o PDF do edital.
 */
async function refinarLinksNoSiteDaBanca(c) {
  const termo = (c.cidade || c.orgao.replace(/^Prefeitura\s+(?:Municipal\s+)?de\s+/i, "").split(/[-–(]/)[0]).trim();
  const termoSlug = slugify(termo).replace(/-/g, "");
  if (termoSlug.length < 4) return { pdfUrl: null };

  for (const base of [c.bancaUrl, c.orgaoUrl].filter(Boolean)) {
    try {
      const r = await fetch(base, {
        headers: { "User-Agent": UA, "Accept-Language": "pt-BR" },
        signal: AbortSignal.timeout(5000),
      });
      if (!r.ok) continue;
      const ct = r.headers.get("content-type") || "";
      if (ct.includes("pdf")) return { pdfUrl: base };
      const html = await r.text();

      const links = [...html.matchAll(/href=["']([^"'#]+)["']/gi)].map(m => {
        try { return new URL(m[1].trim(), base).toString(); } catch { return null; }
      }).filter(u => u && isUrlOficialPermitida(u));

      // Procura PDF de edital cujo próprio nome do arquivo indique Edital de Concurso
      const pdfMatch = links.find(u => {
        if (!/\.pdf(\?|$)/i.test(u)) return false;
        const pathname = new URL(u).pathname.toLowerCase();
        if (/organograma|etica|lai|vestibular|legislacao|lei_complementar|calendario|lista|autuacao|codigo/i.test(pathname)) return false;
        return /edital.*(?:abertura|concurso|seletivo|001|01)|concurso.*edital/i.test(pathname);
      });

      // Procura página específica do concurso dentro do portal da banca
      const paginaMatch = links.find(u => {
        const s = slugify(u).replace(/-/g, "");
        return s.includes(termoSlug) && /concurso|processo|selecao|edital|detalhe|informac/i.test(u);
      });

      if (paginaMatch && base === c.bancaUrl && c.bancaUrl.replace(/\/$/, "").split("/").length <= 4) {
        c.bancaUrl = paginaMatch;
      }
      if (pdfMatch) return { pdfUrl: pdfMatch };
    } catch {
      // timeout ou bloqueio SSL em site de prefeitura/banca: mantém URL original
    }
  }
  return { pdfUrl: null };
}

async function main() {
  console.log("🔍 Coletando editais para Medicina Veterinária e Zootecnia (100% gratuito, zero IA)...");
  const mapa = new Map();

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
    } catch (e) {
      console.warn("Aviso em fonte vagas:", f.url, e.message);
    }
  }

  for (const f of FONTES_CARGOS) {
    try {
      const html = await baixar(f.url);
      for (const it of parseLista(html, f.cargoPadrao)) {
        const atual = mapa.get(it.url);
        if (atual) {
          for (const c of it.cargos) if (!atual.cargos.includes(c)) atual.cargos.push(c);
        } else {
          mapa.set(it.url, it);
        }
      }
    } catch (e) {
      console.warn("Aviso em fonte cargos:", f.url, e.message);
    }
  }

  for (const u of DOU_URLS) {
    try {
      const html = await baixar(u);
      for (const it of parseDouHtml(html)) {
        if (!mapa.has(it.url)) mapa.set(it.url, it);
      }
    } catch (e) {
      console.warn("Aviso em DOU:", u, e.message);
    }
  }

  const itens = Array.from(mapa.values());
  console.log(`📋 ${itens.length} editais encontrados nas listagens. Extraindo detalhes...`);

  const seed = [];
  const seenOrgao = new Set();

  // Processa em pequenos lotes paralelos de 5 para ser rápido e gentil com a rede
  for (let i = 0; i < itens.length; i += 5) {
    const batch = itens.slice(i, i + 5);
    const results = await Promise.all(batch.map(async (item) => {
      try {
        let x;
        let publicadoIso = new Date().toISOString();
        if (item.isDou) {
          publicadoIso = item.douPubDate ? `${item.douPubDate}T12:00:00-03:00` : new Date().toISOString();
          let textoDou = item.douContent ?? item.titulo;
          try {
            const htmlDou = await baixar(item.url);
            const mDou = /<div class="texto-dou">([\s\S]*?)<\/div>/.exec(htmlDou);
            if (mDou) textoDou = decode(mDou[1].replace(/<[^>]+>/g, " ")).replace(/\s+/g, " ").trim();
          } catch {}
          x = extrairDeterministico(item, {
            titulo: item.titulo,
            publicado: publicadoIso,
            texto: textoDou,
            liItems: [],
            paragraphs: [textoDou],
            richLinks: [{ url: item.url, anchor: "Diário Oficial da União", paragraphIdx: 0 }],
          });
          x.orgaoUrl = item.url;
          x.banca = x.banca ?? "Diário Oficial da União (DOU)";
        } else {
          const html = await baixar(item.url);
          const pagina = parseNoticia(html);
          if (pagina.publicado) publicadoIso = pagina.publicado;
          x = extrairDeterministico(item, pagina);
        }
        return { item, x, publicadoIso };
      } catch {
        return null;
      }
    }));

    for (const r of results) {
      if (!r || !r.x.ehVeterinaria) continue;
      const { item, x, publicadoIso } = r;
      const orgao = (x.orgao || item.orgao).slice(0, 250);
      const norm = slugify(orgao).replace(/-(sp|rj|mg|rs|pr|sc|ba|go|pe|ce|pa|ma|mt|ms|es|pb|rn|al|pi|se|am|ro|to|ac|ap|rr|df)$/, "");
      if (seenOrgao.has(norm)) continue;
      seenOrgao.add(norm);

      const sourceId = crypto.createHash("md5").update(item.url).digest("hex").slice(0, 8);
      const fim = x.inscricoesFim ? `${x.inscricoesFim.slice(0, 10)}T23:59:00-03:00` : null;
      const ini = x.inscricoesInicio ? `${x.inscricoesInicio.slice(0, 10)}T08:00:00-03:00` : null;
      const ano = fim ? new Date(fim).getFullYear() : new Date().getFullYear();
      const slug = `${slugify(`${orgao} ${x.uf ?? ""} ${ano}`)}-${sourceId}`;

      seed.push({
        slug,
        sourceUrl: item.url,
        orgao,
        uf: x.uf && /^[A-Z]{2}$/.test(x.uf) ? x.uf : null,
        cidade: x.cidade?.slice(0, 120) || null,
        cargos: (x.cargos ?? []).slice(0, 12),
        vagasVet: x.vagasVet?.slice(0, 60) || null,
        salarioMax: x.salarioMax && x.salarioMax > 500 && x.salarioMax < 100000 ? x.salarioMax : null,
        inscricoesInicio: ini,
        inscricoesFim: fim,
        taxa: x.taxa?.slice(0, 120) || null,
        banca: x.banca?.slice(0, 120) || null,
        bancaUrl: isUrlOficialPermitida(x.bancaUrl) ? x.bancaUrl : null,
        orgaoUrl: isUrlOficialPermitida(x.orgaoUrl) ? x.orgaoUrl : null,
        editalNumero: x.editalNumero?.slice(0, 60) || null,
        escolaridade: x.escolaridade?.slice(0, 60) || null,
        etapas: x.etapas?.slice(0, 250) || null,
        resumo: x.resumo?.slice(0, 1200) || null,
        publishedAt: publicadoIso,
        createdAt: new Date().toISOString(),
        active: true,
      });
    }
  }

  console.log(`🔎 Verificando portais das bancas/órgãos para refinar links de ${seed.length} editais...`);
  const relatorioDuvidas = [];
  for (let i = 0; i < seed.length; i += 8) {
    const batch = seed.slice(i, i + 8);
    await Promise.all(batch.map(async (c) => {
      const { pdfUrl } = await refinarLinksNoSiteDaBanca(c);
      if (pdfUrl) c.editalPdfUrl = pdfUrl;
      const duvidas = [];
      if (!c.vagasVet || c.vagasVet === "Vagas no edital") duvidas.push("Nº exato de vagas no quadro do edital");
      if (!c.salarioMax) duvidas.push("Remuneração inicial");
      if (!c.inscricoesFim) duvidas.push("Data final de inscrição");
      if (!c.bancaUrl && !c.orgaoUrl) duvidas.push("Link oficial da banca/órgão");
      if (duvidas.length > 0 || pdfUrl) {
        relatorioDuvidas.push({
          slug: c.slug,
          orgao: c.orgao,
          uf: c.uf,
          banca: c.banca,
          bancaUrl: c.bancaUrl,
          orgaoUrl: c.orgaoUrl,
          pdfUrl,
          duvidas,
        });
      }
    }));
  }

  fs.writeFileSync(SEED_PATH, JSON.stringify(seed, null, 2));
  console.log(`✅ Concluído! ${seed.length} concursos reais (sem clickbait) salvos em data/concursos_seed.json.`);
  console.log(`📊 Editais com link direto de PDF ou campos para conferência manual: ${relatorioDuvidas.length}`);
}

main().catch(err => {
  console.error("Erro:", err);
  process.exit(1);
});
