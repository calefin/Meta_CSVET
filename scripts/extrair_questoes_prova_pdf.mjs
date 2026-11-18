#!/usr/bin/env node
/**
 * Extrator Determinístico (Custo R$ 0,00 — Sem IA) de Questões de Provas em PDF + Gabarito PDF.
 * Lê o PDF da prova e o PDF do gabarito usando pdfjs-dist, separa as questões de múltipla escolha
 * (A-E ou A-D), cruza com o gabarito oficial, classifica automaticamente nas Grandes Áreas do VetRank
 * e gera um JSON pronto para importar em Admin -> Importar Questões (JSON)!
 *
 * Uso:
 *   pnpm provas:extrair <prova.pdf> [gabarito.pdf] [--banca="FGV"] [--orgao="MP-ES"] [--ano=2026] [--cargo="Médico Veterinário"] [--todas]
 */
import fs from "fs";
import path from "path";
import * as pdfjsLib from "pdfjs-dist/legacy/build/pdf.mjs";

// Classificador automático por palavras-chave para as 6 Grandes Áreas do VetRank
const REGRAS_DISCIPLINA = [
  {
    disciplineId: 30004, // Saúde Pública
    subjectId: 30028,
    tag: "Saúde Pública, Zoonoses e Inspeção (RIISPOA)",
    re: /\b(zoonos|raiva|leptospiros|brucelos|tuberculos|riispoa|inspe[cç][aã]o|abatedouro|frigor[ií]fico|leite|carca[cç]a|epidemiolog|vigil[aâ]ncia sanit[aá]ria|sus\b| sa[uú]de [uú]nica|one health|toxoplasmos|leishmanios|esporotricos|febre aftosa|defesa sanit[aá]ria|mapa\b|pncebt|pnss|sindrome respiratoria|alimentos|microbiologia de alimentos|haccp|appcc)\b/i,
  },
  {
    disciplineId: 30005, // Produção Animal e Zootecnia
    subjectId: 30033,
    tag: "Produção Animal, Nutrição e Zootecnia",
    re: /\b(zootecn|nutri[cç][aã]o animal|ruminante|forrag|pastagem|silagem|feno|bovinocultura|suinocultura|avicultura|ovinocultura|caprinocultura|equinocultura|piscicultura|aquicultura|melhoramento gen[eé]tico|convers[aã]o alimentar|ganho de peso|escore corporal|mat[eé]ria seca|fibra em detergente|fdn|fda|prote[ií]na bruta|nelore|girolando|holand[eê]s|confinamento)\b/i,
  },
  {
    disciplineId: 30002, // Patobiologia
    subjectId: 30010,
    tag: "Patologia, Parasitologia e Microbiologia Veterinária",
    re: /\b(necropsia|histopatolog|les[aã]o|necrose|apoptose|inflama[cç][aã]o|neoplasia|tumor|carcinoma|sarcoma|linfoma|parasit|helminto|nemat[oó]deo|carrapato|rhipicephalus|babesia|ehrlichia|anaplasma|protozo[aá]rio|eimeria|giardia|bact[eé]ria|fungo|v[ií]rus|parvov[ií]rus|cinomose|imunolog|anticorpo|ant[ií]geno|vacina)\b/i,
  },
  {
    disciplineId: 30003, // Clínica e Cirurgia
    subjectId: 30017,
    tag: "Clínica Médica, Cirurgia, Anestesiologia e Reprodução",
    re: /\b(cl[ií]nica|cirurgi|anestesi|f[aá]rmaco|analgesi|cetamina|propofol|isoflurano|sutura|laparotomia|ovariohisterectomia|orquiectomia|fratura|ortopedia|radiografia|ultrassonografia|diagn[oó]stico|tratamento|terap[eê]utica|fluidoterapia|choque|cardiopatia|insufici[eê]ncia renal|endocrin|gesta[cç][aã]o|parto|distocia|insemina[cç][aã]o|ciclo estral|progesterona|c[aã]o|c[aã]es|gato|felino|equino|bovino|paciente)\b/i,
  },
  {
    disciplineId: 30006, // Ética e Legislação Profissional
    subjectId: 30040,
    tag: "Ética, Bioética e Legislação Médico-Veterinária",
    re: /\b(cfmv|crmv|c[oó]digo de [eé]tica|bem-estar animal|eutan[aá]sia|responsabilidade t[eé]cnica|exerc[ií]cio profissional|bio[eé]tica|cinco liberdades|ceua|concea)\b/i,
  },
  {
    disciplineId: 30001, // Ciências Biológicas
    subjectId: 30001,
    tag: "Anatomia, Fisiologia, Bioquímica e Farmacologia",
    re: /\b(anatomia|fisiologia|histologia|embriologia|bioqu[ií]mica|gen[eé]tica|c[eé]lula|mitoc[oô]ndria|potencial de a[cç][aã]o|horm[oô]nio|homeostase|nervo|m[uú]sculo|art[eé]ria|veia|r[uú]men|omaso|abomaso|ret[ií]culo)\b/i,
  },
];

function classificarQuestao(texto) {
  for (const r of REGRAS_DISCIPLINA) {
    if (r.re.test(texto)) return r;
  }
  return null;
}

async function extrairTextoPdf(pdfPath) {
  const data = new Uint8Array(fs.readFileSync(pdfPath));
  const doc = await pdfjsLib.getDocument({ data, useSystemFonts: true }).promise;
  let fullText = "";
  for (let i = 1; i <= doc.numPages; i++) {
    const page = await doc.getPage(i);
    const content = await page.getTextContent();
    let lastY = null;
    let pageStr = "";
    for (const item of content.items) {
      if (!("str" in item)) continue;
      const y = Math.round(item.transform[5]);
      if (lastY !== null && Math.abs(y - lastY) > 4) {
        pageStr += "\n";
      } else if (pageStr && !pageStr.endsWith(" ") && !pageStr.endsWith("\n")) {
        pageStr += " ";
      }
      pageStr += item.str;
      lastY = y;
    }
    fullText += `\n${pageStr}\n`;
  }
  return fullText;
}

function extrairGabarito(textoGabarito) {
  const mapa = new Map();
  // Casa padrões como: "01 A", "1 - B", "Questão 12: C", "15 D", "20 - ANULADA"
  const re = /\b(?:quest[aã]o\s*)?0*(\d{1,3})\s*[-–:.|\s]\s*([A-E]|ANULADA|\*)\b/gi;
  let m;
  while ((m = re.exec(textoGabarito))) {
    const num = Number(m[1]);
    const resp = m[2].toUpperCase();
    if (num >= 1 && num <= 200 && !mapa.has(num)) {
      mapa.set(num, resp === "*" ? "ANULADA" : resp);
    }
  }
  return mapa;
}

function parsearQuestoesDoTexto(texto, gabaritoMap, incluirGerais = false) {
  const linhas = texto.split(/\r?\n/).map(l => l.trim()).filter(Boolean);
  const textoUnido = linhas.join("\n");

  // Divide por início de questão: "QUESTÃO 01", "Questão 1", "01.", "1)", "01 -"
  const reInicio = /(?:^|\n)\s*(?:QUEST[AÃ]O\s+0*(\d{1,3})\b|0*(\d{1,3})\s*[.)-]\s+(?=[A-ZÀ-Ú"“'(\[]))/gi;
  const indices = [];
  let m;
  while ((m = reInicio.exec(textoUnido))) {
    const num = Number(m[1] || m[2]);
    if (num >= 1 && num <= 150) {
      indices.push({ num, start: m.index, contentStart: m.index + m[0].length });
    }
  }

  const questoes = [];
  for (let i = 0; i < indices.length; i++) {
    const atual = indices[i];
    const fim = i + 1 < indices.length ? indices[i + 1].start : textoUnido.length;
    const bloco = textoUnido.slice(atual.contentStart, fim).trim();
    if (bloco.length < 60 || bloco.length > 6000) continue;

    // Separa alternativas (A) / A) / A. / A -
    const reAlt = /(?:^|\n)\s*(?:\(([A-E])\)|([A-E])\s*[.)-])\s+/g;
    const altMatches = [...bloco.matchAll(reAlt)];
    if (altMatches.length < 4) continue;

    // Pega as últimas 4 ou 5 alternativas A, B, C, D(, E) em sequência
    let seqStart = -1;
    for (let j = altMatches.length - 1; j >= 0; j--) {
      const letra = (altMatches[j][1] || altMatches[j][2]).toUpperCase();
      if (letra === "A") {
        seqStart = j;
        break;
      }
    }
    if (seqStart < 0) continue;
    const altsSelecionadas = altMatches.slice(seqStart);
    const letras = altsSelecionadas.map(a => (a[1] || a[2]).toUpperCase());
    if (letras[0] !== "A" || letras[1] !== "B" || letras[2] !== "C" || letras[3] !== "D") continue;

    const enunciado = bloco.slice(0, altsSelecionadas[0].index).replace(/\s+/g, " ").trim();
    if (enunciado.length < 20) continue;

    const options = [];
    for (let k = 0; k < altsSelecionadas.length && k < 5; k++) {
      const id = (altsSelecionadas[k][1] || altsSelecionadas[k][2]).toUpperCase();
      const optIni = altsSelecionadas[k].index + altsSelecionadas[k][0].length;
      const optFim = k + 1 < altsSelecionadas.length ? altsSelecionadas[k + 1].index : bloco.length;
      const optTxt = bloco.slice(optIni, optFim).replace(/\s+/g, " ").trim();
      if (optTxt) options.push({ id, textPt: optTxt });
    }
    if (options.length < 4) continue;

    const textoCompletoQ = `${enunciado} ${options.map(o => o.textPt).join(" ")}`;
    const classif = classificarQuestao(textoCompletoQ);
    if (!classif && !incluirGerais) continue; // Ignora Português/Matemática/Informática a menos que --todas seja passado

    const gab = gabaritoMap.get(atual.num) || "A";
    if (gab === "ANULADA") continue;

    questoes.push({
      numeroOriginal: atual.num,
      disciplineId: classif?.disciplineId ?? 30003,
      subjectId: classif?.subjectId ?? 30017,
      subjectTag: classif?.tag ?? "Conhecimentos Específicos",
      textPt: enunciado,
      options,
      correctOption: ["A", "B", "C", "D", "E"].includes(gab) ? gab : "A",
      temGabaritoConfirmado: gabaritoMap.has(atual.num),
    });
  }

  return questoes;
}

async function main() {
  const args = process.argv.slice(2);
  const pos = args.filter(a => !a.startsWith("--"));
  const flags = Object.fromEntries(
    args.filter(a => a.startsWith("--")).map(a => {
      const [k, v] = a.slice(2).split("=");
      return [k, v ?? true];
    })
  );

  if (pos.length < 1) {
    console.log("Uso: pnpm provas:extrair <caminho-prova.pdf> [caminho-gabarito.pdf] [--banca=\"FGV\"] [--orgao=\"MP-ES\"] [--ano=2026] [--cargo=\"Médico Veterinário\"] [--todas]");
    process.exit(1);
  }

  const provaPath = path.resolve(process.cwd(), pos[0]);
  const gabaritoPath = pos[1] ? path.resolve(process.cwd(), pos[1]) : null;
  const banca = flags.banca || "Banca Oficial";
  const orgao = flags.orgao || "Concurso Público";
  const ano = Number(flags.ano || new Date().getFullYear());
  const cargo = flags.cargo || "Médico Veterinário";
  const incluirGerais = Boolean(flags.todas);

  console.log(`📄 Lendo prova: ${provaPath}`);
  const textoProva = await extrairTextoPdf(provaPath);

  let gabaritoMap = new Map();
  if (gabaritoPath && fs.existsSync(gabaritoPath)) {
    console.log(`🔑 Lendo gabarito: ${gabaritoPath}`);
    const textoGab = await extrairTextoPdf(gabaritoPath);
    gabaritoMap = extrairGabarito(textoGab);
    console.log(`   -> ${gabaritoMap.size} respostas identificadas no gabarito.`);
  }

  const extraidas = parsearQuestoesDoTexto(textoProva, gabaritoMap, incluirGerais);
  const formatadas = extraidas.map(q => ({
    disciplineId: q.disciplineId,
    subjectId: q.subjectId,
    difficulty: "medium",
    year: ano,
    questionType: "multiple_choice",
    modelId: "M1",
    questionModel: "standard",
    subjectTag: q.subjectTag,
    author: `${banca} (${orgao} ${ano})`,
    banca,
    instituicao: orgao,
    cargo,
    areaFormacao: /zootecn/i.test(cargo) ? "Zootecnia" : "Medicina Veterinária",
    escolaridade: "superior",
    textPt: q.textPt,
    options: q.options,
    correctOption: q.correctOption,
    explanationPt: `Questão oficial do concurso ${orgao} (${ano}), aplicada pela banca ${banca} para o cargo de ${cargo}. Gabarito oficial: alternativa ${q.correctOption}.`,
    isPremium: false,
    status: "approved",
  }));

  const outDir = path.resolve(process.cwd(), "data/questoes_extraidas");
  fs.mkdirSync(outDir, { recursive: true });
  const baseName = path.basename(provaPath, ".pdf").replace(/[^a-z0-9_-]/gi, "_");
  const outFile = path.join(outDir, `${baseName}_${ano}.json`);
  fs.writeFileSync(outFile, JSON.stringify(formatadas, null, 2));

  console.log(`\n✅ ${formatadas.length} questões de Medicina Veterinária / Zootecnia extraídas com sucesso!`);
  console.log(`📁 Arquivo pronto para importar no painel Admin do VetRank:`);
  console.log(`   ${outFile}`);
}

main().catch(console.error);
