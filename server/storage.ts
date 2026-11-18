/**
 * storage.ts — camada de armazenamento de arquivos.
 *
 * Suporta dois backends (verificados nesta ordem):
 *
 *  1. AWS S3 direto:
 *     Usa AWS_ACCESS_KEY_ID + AWS_SECRET_ACCESS_KEY + AWS_REGION + AWS_BUCKET_NAME
 *
 *  2. Disco local (VPS sem S3):
 *     Salva em ./uploads/ e serve via rota /manus-storage/*
 *
 * A URL de retorno (/manus-storage/{key}) é a mesma em ambos os modos.
 */

import { S3Client, PutObjectCommand } from "@aws-sdk/client-s3";
import { mkdir, writeFile } from "node:fs/promises";
import path from "node:path";

const UPLOADS_DIR = path.resolve(process.cwd(), "uploads");

// ─── Helpers ──────────────────────────────────────────────────────────────────

function normalizeKey(relKey: string): string {
  return relKey.replace(/^\/+/, "");
}

function appendHashSuffix(relKey: string): string {
  const hash = crypto.randomUUID().replace(/-/g, "").slice(0, 8);
  const lastDot = relKey.lastIndexOf(".");
  if (lastDot === -1) return `${relKey}_${hash}`;
  return `${relKey.slice(0, lastDot)}_${hash}${relKey.slice(lastDot)}`;
}

function hasS3(): boolean {
  return !!(
    process.env.AWS_ACCESS_KEY_ID &&
    process.env.AWS_SECRET_ACCESS_KEY &&
    process.env.AWS_REGION &&
    process.env.AWS_BUCKET_NAME
  );
}

let _s3Client: S3Client | null = null;
function getS3Client(): S3Client {
  if (!_s3Client) {
    _s3Client = new S3Client({
      region: process.env.AWS_REGION!,
      credentials: {
        accessKeyId:     process.env.AWS_ACCESS_KEY_ID!,
        secretAccessKey: process.env.AWS_SECRET_ACCESS_KEY!,
      },
    });
  }
  return _s3Client;
}

// ─── storagePut ───────────────────────────────────────────────────────────────

export async function storagePut(
  relKey: string,
  data: Buffer | Uint8Array | string,
  contentType = "application/octet-stream",
): Promise<{ key: string; url: string }> {
  const key = appendHashSuffix(normalizeKey(relKey));

  // ── Backend 1: AWS S3 direto ──────────────────────────────────────────────
  if (hasS3()) {
    const bucket = process.env.AWS_BUCKET_NAME!;
    const body = typeof data === "string" ? Buffer.from(data) : data;

    await getS3Client().send(new PutObjectCommand({
      Bucket:      bucket,
      Key:         key,
      Body:        body,
      ContentType: contentType,
    }));

    return { key, url: `/manus-storage/${key}` };
  }

  // ── Backend 2: Disco local (VPS sem S3) ───────────────────────────────────
  const filePath = path.join(UPLOADS_DIR, key);
  await mkdir(path.dirname(filePath), { recursive: true });
  const fileData = typeof data === "string" ? Buffer.from(data) : Buffer.from(data);
  await writeFile(filePath, fileData);
  return { key, url: `/manus-storage/${key}` };
}

// ─── storageGet (apenas retorna a URL do proxy) ───────────────────────────────

export async function storageGet(relKey: string): Promise<{ key: string; url: string }> {
  const key = normalizeKey(relKey);
  return { key, url: `/manus-storage/${key}` };
}

// ─── storageGetSignedUrl (para downloads diretos) ─────────────────────────────

export async function storageGetSignedUrl(relKey: string): Promise<string> {
  const key = normalizeKey(relKey);

  if (hasS3()) {
    const { getSignedUrl } = await import("@aws-sdk/s3-request-presigner");
    const { GetObjectCommand } = await import("@aws-sdk/client-s3");
    return getSignedUrl(getS3Client(), new GetObjectCommand({
      Bucket: process.env.AWS_BUCKET_NAME!,
      Key:    key,
    }), { expiresIn: 3600 });
  }

  // Disco local: retorna URL relativa (sem signed URL)
  return `/manus-storage/${key}`;
}
