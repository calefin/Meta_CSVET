/**
 * Gerador de PDF para Concursos (Capa de Propaganda VetRank + Resumo + Edital Verticalizado
 * + mesclagem automática caso exista o PDF do edital oficial em data/editais/<slug>.pdf).
 *
 * Endpoint público:
 *   GET /api/concursos/:slug/guia.pdf
 */
import type { Express, Request, Response } from "express";
import { PDFDocument, rgb, StandardFonts, degrees } from "pdf-lib";
import fs from "fs";
import path from "path";
import { buscarConcursoPorSlug, fmtData, fmtSalario } from "./concursos";

/** Remove caracteres fora do WinAnsi (StandardFonts Helvetica do pdf-lib) */
function safePdfText(s: unknown): string {
  return String(s ?? "")
    .replace(/[\u2018\u2019]/g, "'")
    .replace(/[\u201C\u201D]/g, '"')
    .replace(/[\u2013\u2014]/g, "-")
    .replace(/\u2026/g, "...")
    .replace(/\u00A0/g, " ")
    .replace(/[^\x20-\x7E\xA0-\xFF]/g, "")
    .trim();
}

function wrapLines(text: string, font: any, fontSize: number, maxWidth: number): string[] {
  const words = safePdfText(text).split(/\s+/).filter(Boolean);
  const lines: string[] = [];
  let cur = "";
  for (const w of words) {
    const candidate = cur ? `${cur} ${w}` : w;
    if (font.widthOfTextAtSize(candidate, fontSize) > maxWidth && cur) {
      lines.push(cur);
      cur = w;
    } else {
      cur = candidate;
    }
  }
  if (cur) lines.push(cur);
  return lines;
}

const CHECKLIST_POR_PERFIL: Record<string, { bloco: string; topicos: string[] }[]> = {
  veterinaria: [
    {
      bloco: "1. Saude Publica, Zoonoses e Epidemiologia",
      topicos: [
        "Vigilancia epidemiologica, controle de zoonoses (Raiva, Leishmaniose, Leptospirose, Brucelose, Tuberculose)",
        "Sistema Unico de Saude (SUS), NASF, politicas publicas e Saude Unica (One Health)",
        "Controle de vetores, roedores, manejo populacional de caes e gatos e bem-estar animal",
      ],
    },
    {
      bloco: "2. Inspecao e Tecnologia de Produtos de Origem Animal (POA)",
      topicos: [
        "RIISPOA atualizado, inspecao ante e post-mortem de bovinos, suinos, aves e pescados",
        "Higiene, APPCC, BPF, analise microbiologica e fisico-quimica de leite, ovos, mel e derivados",
        "Doencas transmitidas por alimentos (DTAs) e legislacao sanitaria do MAPA",
      ],
    },
    {
      bloco: "3. Defesa Sanitaria Animal e Programas Oficiais",
      topicos: [
        "PNCEBT (Brucelose e Tuberculose), PNEFA (Febre Aftosa), PNSS, PNSA e PNCRH",
        "Notificacao obrigatoria de enfermidades (Lista de Doencas da OMSA/MAPA) e emergencia sanitaria",
        "Transito animal (GTA), rastreabilidade, quarentena e biosseguranca",
      ],
    },
    {
      bloco: "4. Clinica, Cirurgia, Patologia e Farmacologia",
      topicos: [
        "Clinica medica e cirurgica de pequenos e grandes animais, anestesiologia e emergencia",
        "Patologia geral e especial, necropsia, colheita e remessa de amostras laboratoriais",
        "Farmacologia, terapeutica, antimicrobianos, antiparasitarios e intoxicacoes",
      ],
    },
    {
      bloco: "5. Etica Profissional e Legislacao Veterinaria",
      topicos: [
        "Codigo de Etica do Medico Veterinario (CFMV), responsabilidade tecnica (RT) e biosseguranca",
      ],
    },
  ],
  zootecnia: [
    {
      bloco: "1. Nutricao e Alimentacao Animal",
      topicos: [
        "Nutricao de ruminantes e monogastricos, exigencias nutricionais e formulacao de racoes",
        "Forragicultura, manejo de pastagens, conservacao de forragens (silagem e feno) e aditivos",
      ],
    },
    {
      bloco: "2. Melhoramento Genetico e Reproducao Animal",
      topicos: [
        "Genetica quantitativa, selecao, cruzamentos, herdabilidade e avaliacao genetica",
        "Biotecnologias da reproducao, manejo reprodutivo e eficiencia produtiva dos rebanhos",
      ],
    },
    {
      bloco: "3. Sistemas de Producao, Ambiencia e Bem-Estar",
      topicos: [
        "Bovinocultura de corte e leite, suinocultura, avicultura, ovinocaprinocultura e aquicultura",
        "Bioclimatologia, instalacoes zootecnicas, ambiencia e bem-estar animal",
      ],
    },
    {
      bloco: "4. Qualidade de Produtos, Economia Rural e Legislacao",
      topicos: [
        "Avaliacao de carcacas, qualidade da carne, leite e ovos, gestao e extensao rural",
        "Legislacao profissional do Zootecnista, sustentabilidade e defesa agropecuaria",
      ],
    },
  ],
};

export async function gerarGuiaConcursoPdf(c: any, editalOficialBytes?: Uint8Array | null): Promise<Uint8Array> {
  const pdfDoc = await PDFDocument.create();
  const hb = await pdfDoc.embedFont(StandardFonts.HelveticaBold);
  const hr = await pdfDoc.embedFont(StandardFonts.Helvetica);

  const C = {
    darkBg: rgb(0.04, 0.09, 0.07),
    cardBg: rgb(0.07, 0.14, 0.11),
    darkGreen: rgb(0.05, 0.27, 0.18),
    midGreen: rgb(0.09, 0.45, 0.30),
    emerald: rgb(0.06, 0.73, 0.51),
    gold: rgb(0.96, 0.62, 0.04),
    cream: rgb(0.97, 0.98, 0.97),
    white: rgb(1, 1, 1),
    darkText: rgb(0.10, 0.15, 0.13),
    mutedText: rgb(0.35, 0.42, 0.39),
    lightMuted: rgb(0.70, 0.78, 0.74),
  };

  // ══════════════════════════════════════════════════════════════════════════
  // PÁGINA 1 — CAPA DE PROPAGANDA VETRANK + RESUMO DO CONCURSO
  // ══════════════════════════════════════════════════════════════════════════
  const p1 = pdfDoc.addPage([595.28, 841.89]); // A4 Vertical
  const { width: w, height: h } = p1.getSize();

  p1.drawRectangle({ x: 0, y: 0, width: w, height: h, color: C.cream });

  // Faixa superior escura (Header VetRank + Propaganda Topo)
  const topH = 135;
  p1.drawRectangle({ x: 0, y: h - topH, width: w, height: topH, color: C.darkBg });
  p1.drawRectangle({ x: 0, y: h - topH, width: w, height: 4, color: C.emerald });

  p1.drawText("VetRank", { x: 42, y: h - 48, size: 28, font: hb, color: C.emerald });
  p1.drawText("RADAR DE CONCURSOS  |  VETERINARIA & ZOOTECNIA", {
    x: 42, y: h - 68, size: 9.5, font: hb, color: C.white,
  });
  p1.drawText(
    "A maior plataforma gamificada de questoes comentadas e simulados do Brasil — www.vetrank.com.br",
    { x: 42, y: h - 86, size: 8.5, font: hr, color: C.lightMuted }
  );

  // Badge de destaque no topo direito
  p1.drawRectangle({ x: w - 210, y: h - 64, width: 168, height: 32, color: C.emerald });
  p1.drawText("7 DIAS GRATIS NO SITE", { x: w - 194, y: h - 52, size: 10, font: hb, color: C.darkBg });

  // Pill na base do header
  p1.drawRectangle({ x: 42, y: h - 120, width: w - 84, height: 22, color: C.cardBg });
  p1.drawText(
    "RESUMO OFICIAL DO EDITAL + GUIA VERTICALIZADO DE ESTUDOS",
    { x: 54, y: h - 113, size: 8.5, font: hb, color: C.emerald }
  );

  // Bloco Principal: Órgão + UF + Status
  let y = h - topH - 36;
  const statusLabel =
    c.status === "vai_abrir" ? "EDITAL PUBLICADO - INSCRICOES EM BREVE" :
    c.status === "aberto" ? "INSCRICOES ABERTAS" :
    c.status === "encerrado" ? "INSCRICOES ENCERRADAS - PROVA EM ANDAMENTO" :
    "EDITAL VIGENTE";

  p1.drawRectangle({ x: 42, y: y - 4, width: 240, height: 20, color: C.midGreen });
  p1.drawText(safePdfText(statusLabel), { x: 50, y: y + 2, size: 8.5, font: hb, color: C.white });
  y -= 28;

  const tituloOrgao = `${c.orgao}${c.uf ? ` (${c.uf})` : ""}`;
  const orgaoLines = wrapLines(tituloOrgao, hb, 20, w - 84);
  for (const line of orgaoLines.slice(0, 2)) {
    p1.drawText(line, { x: 42, y, size: 20, font: hb, color: C.darkText });
    y -= 25;
  }

  if (c.resumo) {
    y -= 4;
    const resumoLines = wrapLines(c.resumo, hr, 10.5, w - 84);
    for (const line of resumoLines.slice(0, 4)) {
      p1.drawText(line, { x: 42, y, size: 10.5, font: hr, color: C.mutedText });
      y -= 15;
    }
  }

  // Ficha Técnica (Grid de 6 blocos)
  y -= 14;
  const boxH = 118;
  p1.drawRectangle({ x: 42, y: y - boxH, width: w - 84, height: boxH, color: C.white });
  p1.drawRectangle({ x: 42, y: y - boxH, width: 4, height: boxH, color: C.emerald });

  const prazoStr =
    c.inscricoesInicio || c.inscricoesFim
      ? `${fmtData(c.inscricoesInicio) ?? "Aberto"} ate ${fmtData(c.inscricoesFim) ?? "ver edital"}`
      : "Consultar cronograma do edital";

  const ficha = [
    ["VAGAS CONTEMPLADAS", c.vagasVet || "Ver edital"],
    ["REMUNERACAO ATE", fmtSalario(c.salarioMax) || "Consultar edital"],
    ["PERIODO DE INSCRICAO", prazoStr],
    ["BANCA ORGANIZADORA", c.banca || "Comissao propria / Ver edital"],
    ["TAXA DE INSCRICAO", c.taxa || "Consultar edital"],
    ["ETAPAS DA SELECAO", c.etapas || "Prova objetiva"],
  ];

  const colW = (w - 104) / 2;
  ficha.forEach(([label, val], idx) => {
    const col = idx % 2;
    const row = Math.floor(idx / 2);
    const fx = 56 + col * colW;
    const fy = y - 22 - row * 35;
    p1.drawText(safePdfText(label), { x: fx, y: fy, size: 7.5, font: hb, color: C.mutedText });
    const valLine = wrapLines(val, hb, 10, colW - 14)[0] ?? "-";
    p1.drawText(valLine, { x: fx, y: fy - 13, size: 10, font: hb, color: idx === 1 ? C.midGreen : C.darkText });
  });

  y -= boxH + 22;

  // Tabela de Cargos Contemplados
  p1.drawText("CARGOS DE MEDICINA VETERINARIA E ZOOTECNIA", { x: 42, y, size: 11, font: hb, color: C.darkGreen });
  y -= 10;
  const cargos = Array.isArray(c.cargos) && c.cargos.length > 0
    ? c.cargos
    : [{ nome: "Medico Veterinario / Zootecnista", vagas: c.vagasVet ?? "Ver edital", salario: fmtSalario(c.salarioMax), cargaHoraria: null }];

  for (const cg of cargos.slice(0, 5)) {
    y -= 24;
    p1.drawRectangle({ x: 42, y: y - 5, width: w - 84, height: 22, color: C.white });
    const nomeCg = wrapLines(cg.nome || "Medico Veterinario", hb, 9.5, 290)[0] ?? "Medico Veterinario";
    const infoCg = [cg.vagas, cg.salario, cg.cargaHoraria].filter(Boolean).join("  |  ") || "Ver edital";
    p1.drawText(nomeCg, { x: 50, y: y + 2, size: 9.5, font: hb, color: C.darkText });
    const infoRight = safePdfText(infoCg).slice(0, 48);
    p1.drawText(infoRight, {
      x: w - 50 - hr.widthOfTextAtSize(infoRight, 8.5),
      y: y + 2,
      size: 8.5,
      font: hr,
      color: C.mutedText,
    });
  }

  // ── BANNER DE PROPAGANDA CENTRAL (Call-to-Action Forte VetRank) ─────────────
  const promoY = 74;
  const promoH = 178;
  p1.drawRectangle({ x: 42, y: promoY, width: w - 84, height: promoH, color: C.darkBg });
  p1.drawRectangle({ x: 42, y: promoY + promoH - 4, width: w - 84, height: 4, color: C.gold });

  p1.drawText("QUER SER APROVADO NESTE CONCURSO?", {
    x: 60, y: promoY + promoH - 30, size: 15, font: hb, color: C.emerald,
  });
  p1.drawText(
    "Saia na frente da concorrencia estudando na plataforma #1 da Medicina Veterinaria e Zootecnia:",
    { x: 60, y: promoY + promoH - 48, size: 9.5, font: hr, color: C.white }
  );

  const beneficios = [
    "+73.800 questoes comentadas alternativa por alternativa por especialistas",
    `Simulado cronometrado montado sob medida para ${c.orgao}`,
    "Flashcards com repeticao espacada (SM-2) e Caderno de Erros automatico",
    "Ranking nacional semanal, trilhas de estudo e questoes discursivas",
  ];
  beneficios.forEach((b, i) => {
    const by = promoY + promoH - 70 - i * 17;
    p1.drawCircle({ x: 66, y: by + 3, size: 3, color: C.emerald });
    p1.drawText(safePdfText(b), { x: 76, y: by, size: 9, font: hr, color: C.lightMuted });
  });

  // Botão CTA no PDF
  p1.drawRectangle({ x: 60, y: promoY + 14, width: w - 120, height: 30, color: C.emerald });
  const ctaTxt = `TREINAR AGORA GRATIS: www.vetrank.com.br/concursos/${c.slug}`;
  p1.drawText(safePdfText(ctaTxt).slice(0, 74), {
    x: 74, y: promoY + 25, size: 10, font: hb, color: C.darkBg,
  });

  // Rodapé Página 1
  p1.drawRectangle({ x: 0, y: 0, width: w, height: 48, color: C.darkGreen });
  p1.drawText(
    "Material gerado pelo Radar VetRank (www.vetrank.com.br) — Compartilhe este guia com seus colegas de turma!",
    { x: 42, y: 26, size: 8.5, font: hb, color: C.white }
  );
  p1.drawText(
    "Confira sempre o edital oficial no site da banca organizadora para eventuais retificacoes de cronograma.",
    { x: 42, y: 13, size: 7.5, font: hr, color: C.lightMuted }
  );

  // ══════════════════════════════════════════════════════════════════════════
  // PÁGINA 2 — EDITAL VERTICALIZADO & CHECKLIST DE ESTUDOS VETRANK
  // ══════════════════════════════════════════════════════════════════════════
  const p2 = pdfDoc.addPage([595.28, 841.89]);
  p2.drawRectangle({ x: 0, y: 0, width: w, height: h, color: C.cream });
  p2.drawRectangle({ x: 0, y: h - 70, width: w, height: 70, color: C.darkBg });
  p2.drawRectangle({ x: 0, y: h - 70, width: w, height: 3, color: C.emerald });

  p2.drawText("CHECKLIST VERTICALIZADO DE ESTUDOS — VETRANK", {
    x: 42, y: h - 34, size: 14, font: hb, color: C.emerald,
  });
  p2.drawText(safePdfText(`Plano tatico de revisao por topicos para: ${c.orgao}`).slice(0, 85), {
    x: 42, y: h - 52, size: 9.5, font: hr, color: C.white,
  });

  // Marca d'água sutil no fundo
  p2.drawText("VetRank", {
    x: w / 2 - 110, y: h / 2 - 60, size: 84, font: hb, color: C.midGreen, opacity: 0.04, rotate: degrees(35),
  });

  const Blocos = c.area === "zootecnia" ? CHECKLIST_POR_PERFIL.zootecnia : CHECKLIST_POR_PERFIL.veterinaria;
  let y2 = h - 98;

  for (const b of Blocos) {
    p2.drawRectangle({ x: 42, y: y2 - 6, width: w - 84, height: 22, color: C.darkGreen });
    p2.drawText(safePdfText(b.bloco), { x: 52, y: y2 + 1, size: 9.5, font: hb, color: C.white });
    p2.drawText("[ ] Teoria   [ ] Questoes   [ ] Revisao", {
      x: w - 215, y: y2 + 2, size: 8, font: hb, color: C.emerald,
    });
    y2 -= 24;

    for (const topico of b.topicos) {
      const lines = wrapLines(topico, hr, 9, w - 120);
      p2.drawRectangle({ x: 50, y: y2 - 2, width: 8, height: 8, borderColor: C.midGreen, borderWidth: 1 });
      for (const l of lines) {
        p2.drawText(l, { x: 66, y: y2 - 1, size: 9, font: hr, color: C.darkText });
        y2 -= 14;
      }
      y2 -= 6;
    }
    y2 -= 8;
  }

  // Box de dica na base da Página 2
  p2.drawRectangle({ x: 42, y: 65, width: w - 84, height: 64, color: C.white });
  p2.drawRectangle({ x: 42, y: 65, width: 4, height: 64, color: C.gold });
  p2.drawText("COMO ESTUDAR COM ALTA RETENCAO NO VETRANK:", {
    x: 56, y: 112, size: 9.5, font: hb, color: C.darkGreen,
  });
  p2.drawText(
    "1. Resolva 20 a 30 questoes comentadas por dia filtrando pelos topicos acima em www.vetrank.com.br/bank",
    { x: 56, y: 96, size: 8.5, font: hr, color: C.darkText }
  );
  p2.drawText(
    "2. Todas as questoes que voce errar vao automaticamente para o seu Caderno de Erros e viram Flashcards SM-2!",
    { x: 56, y: 81, size: 8.5, font: hr, color: C.darkText }
  );

  p2.drawRectangle({ x: 0, y: 0, width: w, height: 44, color: C.darkGreen });
  p2.drawText(
    "Acesse agora: www.vetrank.com.br  |  Simulados, Questoes Comentadas e Trilhas Gamificadas",
    { x: 42, y: 18, size: 9, font: hb, color: C.white }
  );

  // ══════════════════════════════════════════════════════════════════════════
  // ANEXA PÁGINAS DO EDITAL OFICIAL (SE EXISTIR EM data/editais/<slug>.pdf)
  // ══════════════════════════════════════════════════════════════════════════
  let extraBytes = editalOficialBytes;
  if (!extraBytes) {
    const localPdfPath = path.resolve(process.cwd(), `data/editais/${c.slug}.pdf`);
    if (fs.existsSync(localPdfPath)) {
      extraBytes = fs.readFileSync(localPdfPath);
    }
  }

  if (extraBytes) {
    try {
      const srcDoc = await PDFDocument.load(extraBytes, { ignoreEncryption: true });
      const copiedPages = await pdfDoc.copyPages(srcDoc, srcDoc.getPageIndices());
      for (const page of copiedPages) {
        const pw = page.getSize().width;
        page.drawRectangle({ x: 0, y: 0, width: pw, height: 18, color: C.darkBg });
        page.drawText(
          "Baixado em www.vetrank.com.br — Treine com Simulados e +73.800 Questoes Comentadas de Veterinaria e Zootecnia",
          { x: 16, y: 5, size: 7, font: hb, color: C.emerald }
        );
        pdfDoc.addPage(page);
      }
    } catch (e) {
      console.warn("[concursoPdf] Falha ao anexar PDF oficial:", e);
    }
  }

  return pdfDoc.save();
}

export function registerConcursoPdfRoutes(app: Express) {
  app.get("/api/concursos/:slug/guia.pdf", async (req: Request, res: Response) => {
    try {
      const slug = req.params.slug?.trim();
      if (!slug) return res.status(400).json({ error: "Slug inválido" });
      const c = await buscarConcursoPorSlug(slug);
      if (!c) return res.status(404).json({ error: "Concurso não encontrado" });

      const pdfBytes = await gerarGuiaConcursoPdf(c);
      res.set({
        "Content-Type": "application/pdf",
        "Content-Disposition": `attachment; filename="vetrank-edital-${c.slug}.pdf"`,
        "Content-Length": pdfBytes.byteLength,
        "Cache-Control": "public, max-age=3600",
      });
      res.end(Buffer.from(pdfBytes));
    } catch (err) {
      console.error("[concursoPdf] Error:", err);
      res.status(500).json({ error: "Erro ao gerar PDF do concurso." });
    }
  });
}
