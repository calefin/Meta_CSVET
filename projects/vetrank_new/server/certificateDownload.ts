/**
 * Certificate PDF generation endpoint.
 * GET /api/cursos/certificado/:validationCode
 * - Public endpoint: the validation code is the secret
 * - Generates a 2-page PDF:
 *   Page 1 — Certificate with dual signatures (CEO + instructor)
 *   Page 2 — Conteúdo programático (course structure)
 */
import type { Express, Request, Response } from "express";
import { PDFDocument, PDFImage, rgb, StandardFonts, degrees } from "pdf-lib";
import { getDb } from "./db";
import {
  courseCertificates, users, courses, courseModules, courseLessons,
} from "../drizzle/schema";
import { asc, eq } from "drizzle-orm";

async function embedImageFromUrl(pdfDoc: PDFDocument, url: string | null | undefined): Promise<PDFImage | null> {
  if (!url) return null;
  try {
    const resp = await fetch(url, { signal: AbortSignal.timeout(5000) });
    if (!resp.ok) return null;
    const bytes = await resp.arrayBuffer();
    const ct = resp.headers.get("content-type") ?? "";
    if (ct.includes("png") || url.toLowerCase().endsWith(".png")) return await pdfDoc.embedPng(bytes);
    return await pdfDoc.embedJpg(bytes);
  } catch {
    return null;
  }
}

function maskCpf(cpf: string | null | undefined): string {
  if (!cpf) return "";
  return cpf.replace(/^(\d{3})\.\d{3}\.\d{3}(-\d{2})$/, "$1.***.***$2");
}

export function registerCertificateDownload(app: Express) {
  app.get("/api/cursos/certificado/:code", async (req: Request, res: Response) => {
    try {
      const code = req.params.code?.trim();
      if (!code) return res.status(400).json({ error: "Código inválido." });

      const db = await getDb();
      if (!db) return res.status(500).json({ error: "DB unavailable." });

      // ── Fetch certificate + user + course ──────────────────────────────────────
      const [cert] = await db
        .select({
          id: courseCertificates.id,
          validationCode: courseCertificates.validationCode,
          hash: courseCertificates.hash,
          issuedAt: courseCertificates.issuedAt,
          courseId: courseCertificates.courseId,
          userName: users.name,
          userCpf: users.cpf,
          courseTitle: courses.title,
          courseDurationHours: courses.durationHours,
          instructorName: courses.instructorName,
          instructorSignatureUrl: courses.instructorSignatureUrl,
        })
        .from(courseCertificates)
        .innerJoin(users, eq(users.id, courseCertificates.userId))
        .innerJoin(courses, eq(courses.id, courseCertificates.courseId))
        .where(eq(courseCertificates.validationCode, code))
        .limit(1);

      if (!cert) return res.status(404).json({ error: "Certificado não encontrado." });

      // ── Fetch course structure for page 2 ─────────────────────────────────────
      const modules = await db
        .select({ id: courseModules.id, title: courseModules.title, order: courseModules.order })
        .from(courseModules)
        .where(eq(courseModules.courseId, cert.courseId))
        .orderBy(asc(courseModules.order));

      const lessons = await db
        .select({ moduleId: courseLessons.moduleId, title: courseLessons.title, order: courseLessons.order })
        .from(courseLessons)
        .where(eq(courseLessons.courseId, cert.courseId))
        .orderBy(asc(courseLessons.order));

      const structure = modules.map(mod => ({
        ...mod,
        lessons: lessons.filter(l => l.moduleId === mod.id),
      }));

      // ── Build PDF ──────────────────────────────────────────────────────────────
      const pdfDoc = await PDFDocument.create();
      const hb = await pdfDoc.embedFont(StandardFonts.HelveticaBold);
      const hr = await pdfDoc.embedFont(StandardFonts.Helvetica);

      // Color palette
      const C = {
        darkGreen:  rgb(0.05, 0.27, 0.18),
        midGreen:   rgb(0.09, 0.45, 0.30),
        lightGreen: rgb(0.13, 0.60, 0.40),
        neonGreen:  rgb(0.29, 0.87, 0.50),
        gold:       rgb(0.75, 0.60, 0.15),
        cream:      rgb(0.97, 0.98, 0.96),
        white:      rgb(1, 1, 1),
        darkText:   rgb(0.10, 0.14, 0.12),
        mutedText:  rgb(0.35, 0.42, 0.38),
        footerText: rgb(0.65, 0.85, 0.75),
        footerCode: rgb(0.80, 0.93, 0.87),
        footerUrl:  rgb(0.55, 0.75, 0.65),
      };

      // Fetch + embed signature rubric images (silent on failure)
      const [ceoSigImg, instrSigImg] = await Promise.all([
        embedImageFromUrl(pdfDoc, process.env.CERT_CEO_SIGNATURE_URL),
        embedImageFromUrl(pdfDoc, cert.instructorSignatureUrl ?? undefined),
      ]);

      const sideW = 28;

      // ════════════════════════════════════════════════════════════════════════════
      // PAGE 1 — Certificate
      // ════════════════════════════════════════════════════════════════════════════
      const p1 = pdfDoc.addPage([841.89, 595.28]);
      const w = p1.getSize().width;
      const h = p1.getSize().height;

      // Background
      p1.drawRectangle({ x:0, y:0, width:w, height:h, color:C.cream });

      // Top green band
      const bandH = 110;
      p1.drawRectangle({ x:0, y:h-bandH, width:w, height:bandH, color:C.darkGreen });
      p1.drawRectangle({ x:0, y:h-bandH, width:w, height:4, color:C.gold });

      // Side bars
      p1.drawRectangle({ x:0, y:0, width:sideW, height:h-bandH, color:C.midGreen });
      p1.drawRectangle({ x:w-sideW, y:0, width:sideW, height:h-bandH, color:C.midGreen });

      // Bottom bar
      p1.drawRectangle({ x:0, y:0, width:w, height:60, color:C.darkGreen });
      p1.drawRectangle({ x:0, y:60, width:w, height:3, color:C.gold });

      // Corner decorations
      p1.drawRectangle({ x:sideW+10, y:h-bandH+10, width:14, height:14, color:C.gold, opacity:0.5 });
      p1.drawRectangle({ x:w-sideW-24, y:h-bandH+10, width:14, height:14, color:C.gold, opacity:0.5 });

      // Header lockup: icon + brand name side by side, centered as a unit
      const iconSize = 36;
      const sc = iconSize / 64;
      const brand = "VetRank";
      const brandFontSize = 22;
      const brandW = hb.widthOfTextAtSize(brand, brandFontSize);
      const iconTextGap = 10;
      const lockupW = iconSize + iconTextGap + brandW;
      const lockupX = (w - lockupW) / 2;
      const rowCenterY = h - 40; // vertical center of the lockup row within the band

      const iX = lockupX;
      const iY = rowCenterY - iconSize / 2;
      p1.drawRectangle({ x:iX, y:iY, width:iconSize, height:iconSize, color:rgb(0.05,0.07,0.09) });
      p1.drawLine({ start:{x:iX+12*sc, y:iY+(64-20)*sc}, end:{x:iX+32*sc, y:iY+(64-48)*sc}, thickness:3, color:C.neonGreen });
      p1.drawLine({ start:{x:iX+32*sc, y:iY+(64-48)*sc}, end:{x:iX+52*sc, y:iY+(64-20)*sc}, thickness:3, color:C.neonGreen });
      p1.drawCircle({ x:iX+32*sc, y:iY+(64-16)*sc, size:6*sc, color:C.neonGreen });

      // Brand name vertically centered with icon (cap height ≈ 71.5% of font size)
      const capH = brandFontSize * 0.715;
      const brandTextY = iY + (iconSize - capH) / 2;
      p1.drawText(brand, { x:lockupX + iconSize + iconTextGap, y:brandTextY, size:brandFontSize, font:hb, color:C.white });

      const tagline = "Preparação para Residências Veterinárias";
      p1.drawText(tagline, { x:(w-hr.widthOfTextAtSize(tagline,9))/2, y:h-73, size:9, font:hr, color:rgb(0.75,0.90,0.82) });
      for (let i=0;i<7;i++) p1.drawCircle({ x:w/2-36+i*12, y:h-90, size:2, color:C.gold, opacity:0.7 });

      // Main title
      const mainTitle = "CERTIFICADO DE CONCLUSÃO";
      p1.drawText(mainTitle, { x:(w-hb.widthOfTextAtSize(mainTitle,24))/2, y:h-152, size:24, font:hb, color:C.darkGreen });

      // Ornament
      const lineY = h-166, lxc = w/2;
      p1.drawLine({ start:{x:lxc-100,y:lineY}, end:{x:lxc-12,y:lineY}, thickness:1, color:C.lightGreen, opacity:0.5 });
      p1.drawCircle({ x:lxc, y:lineY, size:4, color:C.gold });
      p1.drawLine({ start:{x:lxc+12,y:lineY}, end:{x:lxc+100,y:lineY}, thickness:1, color:C.lightGreen, opacity:0.5 });

      // Body text
      const certifyText = "Certificamos que o(a) profissional";
      p1.drawText(certifyText, { x:(w-hr.widthOfTextAtSize(certifyText,12))/2, y:h-200, size:12, font:hr, color:C.mutedText });

      const studentName = cert.userName ?? "Participante";
      const nameSize = studentName.length > 35 ? 24 : 30;
      const nameW = hb.widthOfTextAtSize(studentName, nameSize);
      p1.drawText(studentName, { x:(w-nameW)/2, y:h-240, size:nameSize, font:hb, color:C.darkGreen });
      p1.drawLine({ start:{x:(w-nameW)/2, y:h-246}, end:{x:(w+nameW)/2, y:h-246}, thickness:0.8, color:C.gold, opacity:0.7 });

      const cpfMasked = maskCpf(cert.userCpf);
      if (cpfMasked) {
        const cpfTxt = `CPF: ${cpfMasked}`;
        p1.drawText(cpfTxt, { x:(w-hr.widthOfTextAtSize(cpfTxt,10))/2, y:h-262, size:10, font:hr, color:C.mutedText });
      }

      const conclText = "concluiu com êxito o curso";
      p1.drawText(conclText, { x:(w-hr.widthOfTextAtSize(conclText,12))/2, y:h-288, size:12, font:hr, color:C.mutedText });

      const courseTitle = cert.courseTitle ?? "Curso";
      const ctSize = courseTitle.length > 55 ? 13 : courseTitle.length > 45 ? 15 : 17;
      const ctW = hb.widthOfTextAtSize(courseTitle, ctSize);
      p1.drawText(courseTitle, { x:(w-ctW)/2, y:h-315, size:ctSize, font:hb, color:C.midGreen });

      if (cert.courseDurationHours > 0) {
        const durTxt = `Carga horária: ${cert.courseDurationHours}h`;
        p1.drawText(durTxt, { x:(w-hr.widthOfTextAtSize(durTxt,10))/2, y:h-337, size:10, font:hr, color:C.mutedText });
      }

      const issuedDate = cert.issuedAt
        ? new Date(cert.issuedAt).toLocaleDateString("pt-BR", { day:"2-digit", month:"long", year:"numeric" })
        : new Date().toLocaleDateString("pt-BR", { day:"2-digit", month:"long", year:"numeric" });
      const dateTxt = `Emitido em ${issuedDate}`;
      p1.drawText(dateTxt, { x:(w-hr.widthOfTextAtSize(dateTxt,10))/2, y:h-356, size:10, font:hr, color:C.mutedText });

      // ── Dual signatures ────────────────────────────────────────────────────────
      const sigW2 = 205;
      const sigYLine = 128;
      const CEO_NAME = "Prof. Dr. Atilio Sersun Calefi";
      const CEO_CNPJ = `CNPJ: ${process.env.CERT_CNPJ ?? "32.645.724/0001-89"}`;
      const CEO_ORG  = "CSVET Editora, Cursos e Treinamentos";
      const CEO_ROLE = "CEO / Responsável pela emissão";

      const drawSig = (x1: number, x2: number, name: string, l1: string, l2: string, role: string, sigImg: PDFImage | null = null) => {
        if (sigImg) {
          const targetH = 32;
          const imgW = Math.min(sigImg.width * (targetH / sigImg.height), x2 - x1 - 20);
          const imgH = imgW * (sigImg.height / sigImg.width);
          p1.drawImage(sigImg, { x: x1 + (x2 - x1 - imgW) / 2, y: sigYLine + 6, width: imgW, height: imgH });
        }
        p1.drawLine({ start:{x:x1,y:sigYLine}, end:{x:x2,y:sigYLine}, thickness:0.8, color:C.midGreen });
        p1.drawText(name, { x:x1+(x2-x1-hb.widthOfTextAtSize(name,9))/2, y:sigYLine-14, size:9, font:hb, color:C.darkText });
        if (l1) p1.drawText(l1, { x:x1+(x2-x1-hr.widthOfTextAtSize(l1,8))/2, y:sigYLine-26, size:8, font:hr, color:C.mutedText });
        if (l2) p1.drawText(l2, { x:x1+(x2-x1-hr.widthOfTextAtSize(l2,7.5))/2, y:sigYLine-37, size:7.5, font:hr, color:C.mutedText });
        if (role) p1.drawText(role, { x:x1+(x2-x1-hr.widthOfTextAtSize(role,7))/2, y:sigYLine-48, size:7, font:hr, color:rgb(0.5,0.42,0.2) });
      };

      drawSig(sideW+50, sideW+50+sigW2, CEO_NAME, CEO_CNPJ, CEO_ORG, CEO_ROLE, ceoSigImg);

      // Right: instructor (if different from CEO)
      const instructor = cert.instructorName;
      if (instructor && instructor !== CEO_NAME) {
        drawSig(w-sideW-50-sigW2, w-sideW-50, instructor, "", "", "Instrutor do Curso", instrSigImg);
      }

      // ── Footer bar ─────────────────────────────────────────────────────────────
      if (cert.hash) {
        const hashLabel = `SHA-256: ${cert.hash.slice(0,32)}...`;
        p1.drawText(hashLabel, { x:(w-hr.widthOfTextAtSize(hashLabel,7))/2, y:43, size:7, font:hr, color:C.footerText });
      }
      const codeLabel = `Código de validação: ${cert.validationCode}`;
      p1.drawText(codeLabel, { x:(w-hr.widthOfTextAtSize(codeLabel,7.5))/2, y:29, size:7.5, font:hr, color:C.footerCode });
      const verifyUrl = `${process.env.VITE_CLIENT_URL ?? "https://vetrank.com.br"}/verificar-certificado/${cert.validationCode}`;
      p1.drawText(verifyUrl, { x:(w-hr.widthOfTextAtSize(verifyUrl,7))/2, y:14, size:7, font:hr, color:C.footerUrl });

      // Watermark
      p1.drawText("VetRank", { x:w/2-80, y:h/2-80, size:90, font:hb, color:C.midGreen, opacity:0.04, rotate:degrees(35) });

      // ════════════════════════════════════════════════════════════════════════════
      // PAGE 2 — Conteúdo Programático
      // ════════════════════════════════════════════════════════════════════════════
      const p2 = pdfDoc.addPage([841.89, 595.28]);
      const w2 = p2.getSize().width;
      const h2 = p2.getSize().height;

      p2.drawRectangle({ x:0, y:0, width:w2, height:h2, color:C.cream });
      p2.drawRectangle({ x:0, y:h2-55, width:w2, height:55, color:C.darkGreen });
      p2.drawRectangle({ x:0, y:h2-55, width:w2, height:3, color:C.gold });
      p2.drawRectangle({ x:0, y:0, width:w2, height:30, color:C.darkGreen });
      p2.drawRectangle({ x:0, y:30, width:w2, height:2, color:C.gold });

      const p2H = "CONTEÚDO PROGRAMÁTICO";
      p2.drawText(p2H, { x:(w2-hb.widthOfTextAtSize(p2H,18))/2, y:h2-38, size:18, font:hb, color:C.white });
      p2.drawText(cert.courseTitle ?? "", { x:(w2-hr.widthOfTextAtSize(cert.courseTitle??""  ,10))/2, y:h2-50, size:10, font:hr, color:rgb(0.75,0.90,0.82) });

      // Two-column structure
      const margin = 50;
      const colW = (w2 - margin * 2 - 20) / 2;
      let col = 0;
      let yPos = h2 - 75;

      for (const mod of structure) {
        const xBase = margin + col * (colW + 20);

        // Check if this module fits in remaining column space
        const needed = 24 + mod.lessons.length * 18 + 14;
        if (yPos - needed < 40 && col === 0) { col = 1; yPos = h2 - 75; }

        // Module header
        p2.drawRectangle({ x:xBase, y:yPos-18, width:colW, height:22, color:C.midGreen, opacity:0.12 });
        p2.drawRectangle({ x:xBase, y:yPos+2, width:3, height:18, color:C.midGreen });
        const modLabel = `${mod.order}. ${mod.title}`;
        p2.drawText(modLabel, { x:xBase+10, y:yPos-10, size:9.5, font:hb, color:C.darkGreen });
        yPos -= 28;

        for (const lesson of mod.lessons) {
          p2.drawCircle({ x:xBase+14, y:yPos-4, size:2, color:C.lightGreen });
          const lessonLabel = `${lesson.order}. ${lesson.title}`;
          p2.drawText(lessonLabel, { x:xBase+22, y:yPos-9, size:9, font:hr, color:C.mutedText });
          yPos -= 18;
        }
        yPos -= 10;

        if (col === 0 && yPos < 50) { col = 1; yPos = h2 - 75; }
        else if (col === 1 && yPos < 50) break;
      }

      // Footer page 2
      const footerInfo = `Aluno: ${cert.userName ?? "—"}  |  Carga Horária Total: ${cert.courseDurationHours}h  |  Emitido em ${issuedDate}`;
      p2.drawText(footerInfo, { x:(w2-hr.widthOfTextAtSize(footerInfo,8))/2, y:14, size:8, font:hr, color:C.footerCode });

      // ── Save and send ──────────────────────────────────────────────────────────
      const pdfBytes = await pdfDoc.save();
      const safeCode = cert.validationCode.replace(/[^a-zA-Z0-9-]/g, "").slice(0, 36);

      res.set({
        "Content-Type": "application/pdf",
        "Content-Disposition": `attachment; filename="certificado-${safeCode}.pdf"`,
        "Content-Length": pdfBytes.byteLength,
        "Cache-Control": "public, max-age=86400",
      });
      res.end(Buffer.from(pdfBytes));

    } catch (error) {
      console.error("[CertificateDownload] Error:", error);
      res.status(500).json({ error: "Erro ao gerar certificado." });
    }
  });
}
