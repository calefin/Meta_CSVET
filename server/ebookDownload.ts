/**
 * Secure ebook download endpoint.
 * - Validates session and course purchase
 * - Embeds user watermark (name + email) in every PDF page via pdf-lib
 * - Logs every download in ebook_download_logs
 */
import type { Express, Request, Response } from "express";
import { PDFDocument, rgb, degrees } from "pdf-lib";
import crypto from "crypto";
import { sdk } from "./_core/sdk";
import { getDb } from "./db";
import {
  lessonEbooks, courseLessons, coursePurchases, ebookDownloadLogs, users,
} from "../drizzle/schema";
import { and, eq } from "drizzle-orm";

export function registerEbookDownload(app: Express) {
  app.get("/api/cursos/download-ebook/:ebookId", async (req: Request, res: Response) => {
    try {
      // ── Auth ───────────────────────────────────────────────────────────────────
      let user: Awaited<ReturnType<typeof sdk.authenticateRequest>>;
      try {
        user = await sdk.authenticateRequest(req);
      } catch {
        return res.status(401).json({ error: "Não autenticado." });
      }

      const ebookId = Number(req.params.ebookId);
      if (!ebookId || isNaN(ebookId)) return res.status(400).json({ error: "ID inválido." });

      const db = await getDb();
      if (!db) return res.status(500).json({ error: "DB unavailable." });

      // ── Fetch ebook record ─────────────────────────────────────────────────────
      const [ebook] = await db
        .select()
        .from(lessonEbooks)
        .where(eq(lessonEbooks.id, ebookId))
        .limit(1);

      if (!ebook) return res.status(404).json({ error: "Ebook não encontrado." });
      if (!ebook.allowDownload) return res.status(403).json({ error: "Download não permitido para este arquivo." });

      // ── Resolve courseId from lessonId ─────────────────────────────────────────
      const [lesson] = await db
        .select({ courseId: courseLessons.courseId })
        .from(courseLessons)
        .where(eq(courseLessons.id, ebook.lessonId))
        .limit(1);

      if (!lesson) return res.status(404).json({ error: "Aula não encontrada." });

      // ── Verify active purchase ─────────────────────────────────────────────────
      const [purchase] = await db
        .select({ id: coursePurchases.id })
        .from(coursePurchases)
        .where(and(
          eq(coursePurchases.userId, user.id),
          eq(coursePurchases.courseId, lesson.courseId),
          eq(coursePurchases.status, "active"),
        ))
        .limit(1);

      if (!purchase) return res.status(403).json({ error: "Acesso ao curso não encontrado." });

      // ── Fetch the PDF from storage ─────────────────────────────────────────────
      const fileResp = await fetch(ebook.fileUrl);
      if (!fileResp.ok) return res.status(502).json({ error: "Erro ao buscar o arquivo PDF." });
      const pdfBytes = await fileResp.arrayBuffer();

      // ── Fetch user info for watermark ──────────────────────────────────────────
      const [userData] = await db
        .select({ name: users.name, email: users.email })
        .from(users)
        .where(eq(users.id, user.id))
        .limit(1);

      const watermarkText = [
        userData?.name ?? "Usuário",
        userData?.email ?? "",
        new Date().toLocaleDateString("pt-BR"),
        `VetRank — uso exclusivo`,
      ].filter(Boolean).join("  ·  ");

      // ── Apply watermark with pdf-lib ───────────────────────────────────────────
      const pdfDoc = await PDFDocument.load(pdfBytes);
      const pages = pdfDoc.getPages();

      for (const page of pages) {
        const { width, height } = page.getSize();

        // Draw watermark diagonally across page, repeated in a grid
        const fontSize = 11;
        const color = rgb(0.5, 0.5, 0.5);
        const opacity = 0.18;
        const angle = 45;

        // Repeat watermark in a grid pattern
        const stepX = width / 2;
        const stepY = height / 3;

        for (let xi = 0; xi < 3; xi++) {
          for (let yi = 0; yi < 4; yi++) {
            page.drawText(watermarkText, {
              x: xi * stepX - 60,
              y: yi * stepY,
              size: fontSize,
              color,
              opacity,
              rotate: degrees(angle),
            });
          }
        }
      }

      const watermarkedBytes = await pdfDoc.save();

      // ── Log download ───────────────────────────────────────────────────────────
      const downloadHash = crypto.randomUUID();
      const ipAddress = (req.ip ?? req.socket.remoteAddress ?? "unknown").slice(0, 64);
      await db.insert(ebookDownloadLogs).values({
        userId: user.id,
        lessonEbookId: ebookId,
        courseId: lesson.courseId,
        ipAddress,
        userAgent: (req.headers["user-agent"] ?? "").slice(0, 500) || undefined,
        downloadHash,
        downloadedAt: new Date(),
      }).catch(() => {});

      // ── Send response ──────────────────────────────────────────────────────────
      const safeFileName = ebook.fileName
        .replace(/[^a-zA-Z0-9._-]/g, "_")
        .replace(/\.pdf$/i, "") + "_secure.pdf";

      res.set({
        "Content-Type": "application/pdf",
        "Content-Disposition": `attachment; filename="${safeFileName}"`,
        "Content-Length": watermarkedBytes.byteLength,
        "Cache-Control": "no-store",
      });

      res.end(Buffer.from(watermarkedBytes));

    } catch (error) {
      console.error("[EbookDownload] Error:", error);
      res.status(500).json({ error: "Erro interno ao processar o download." });
    }
  });
}
