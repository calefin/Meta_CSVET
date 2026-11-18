import type { Express } from "express";
import { S3Client, GetObjectCommand } from "@aws-sdk/client-s3";
import { getSignedUrl } from "@aws-sdk/s3-request-presigner";
import { existsSync } from "node:fs";
import path from "node:path";

const UPLOADS_DIR = path.resolve(process.cwd(), "uploads");

let _s3: S3Client | null = null;
function getS3(): S3Client | null {
  if (!process.env.AWS_ACCESS_KEY_ID || !process.env.AWS_REGION || !process.env.AWS_BUCKET_NAME) return null;
  if (!_s3) {
    _s3 = new S3Client({
      region: process.env.AWS_REGION,
      credentials: {
        accessKeyId:     process.env.AWS_ACCESS_KEY_ID!,
        secretAccessKey: process.env.AWS_SECRET_ACCESS_KEY!,
      },
    });
  }
  return _s3;
}

export function registerStorageProxy(app: Express) {
  app.get("/manus-storage/*", async (req, res) => {
    const key = (req.params as Record<string, string>)["0"];
    if (!key) { res.status(400).send("Missing storage key"); return; }

    const isImage = /\.(webp|jpg|jpeg|png|gif|svg)$/i.test(key);
    const cacheHeader = isImage ? "public, max-age=31536000, immutable" : "no-store";

    // ── Backend 1: AWS S3 direto ─────────────────────────────────────────────
    const s3 = getS3();
    if (s3) {
      try {
        const url = await getSignedUrl(
          s3,
          new GetObjectCommand({ Bucket: process.env.AWS_BUCKET_NAME!, Key: key }),
          { expiresIn: isImage ? 31536000 : 3600 },
        );
        res.set("Cache-Control", cacheHeader);
        res.redirect(307, url);
        return;
      } catch (err) {
        console.error("[StorageProxy/S3] failed:", err);
        res.status(502).send("Storage S3 error");
        return;
      }
    }

    // ── Backend 2: Disco local ───────────────────────────────────────────────
    const localPath = path.join(UPLOADS_DIR, key);
    if (existsSync(localPath)) {
      res.set("Cache-Control", cacheHeader);
      res.sendFile(localPath);
      return;
    }

    res.status(404).send("File not found");
  });
}
