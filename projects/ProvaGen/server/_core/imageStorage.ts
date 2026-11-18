/**
 * Image Storage Manager
 * Handles image upload and storage on local server
 * Note: Image optimization is done on client-side before upload
 */

import fs from "fs";
import path from "path";
import crypto from "crypto";
import { getDb } from "../db";
import { questionImages } from "../db";
import { eq } from "drizzle-orm";

const STORAGE_DIR = path.join(process.cwd(), "storage", "question-images");

/**
 * Ensure storage directory exists
 */
export async function ensureStorageDirectory(): Promise<void> {
  if (!fs.existsSync(STORAGE_DIR)) {
    fs.mkdirSync(STORAGE_DIR, { recursive: true });
  }
}

/**
 * Generate unique storage key for image
 */
function generateStorageKey(): string {
  return crypto.randomBytes(16).toString("hex");
}

/**
 * Get image dimensions from buffer
 */
async function getImageDimensions(
  imageBuffer: Buffer,
  mimeType: string
): Promise<{ width: number; height: number }> {
  // Parse basic dimensions from image data
  // This is a simplified approach - in production, use a proper image library
  
  if (mimeType === "image/jpeg" || mimeType === "image/jpg") {
    // JPEG SOF marker is at offset 0xFFC0 or 0xFFC2
    try {
      // Look for SOF marker
      let offset = 2;
      while (offset < imageBuffer.length - 9) {
        if (imageBuffer[offset] === 0xFF && imageBuffer[offset + 1] === 0xC0) {
          const height = (imageBuffer[offset + 5] << 8) | imageBuffer[offset + 6];
          const width = (imageBuffer[offset + 7] << 8) | imageBuffer[offset + 8];
          return { width, height };
        }
        offset++;
      }
    } catch (error) {
      console.warn("Could not parse JPEG dimensions", error);
    }
  } else if (mimeType === "image/png") {
    // PNG dimensions at offset 16-24
    try {
      if (imageBuffer.length >= 24) {
        const width = imageBuffer.readUInt32BE(16);
        const height = imageBuffer.readUInt32BE(20);
        return { width, height };
      }
    } catch (error) {
      console.warn("Could not parse PNG dimensions", error);
    }
  }

  // Default fallback
  return { width: 1920, height: 1440 };
}

/**
 * Save image to local storage
 */
export async function saveImageLocally(
  imageBuffer: Buffer,
  fileName: string,
  mimeType: string,
  userId: number,
  questionId: number
): Promise<{
  storageKey: string;
  storagePath: string;
  fileSize: number;
  width: number;
  height: number;
}> {
  await ensureStorageDirectory();

  // Generate storage key and path
  const storageKey = generateStorageKey();
  const ext = getFileExtension(mimeType);
  const fileName_sanitized = `${storageKey}${ext}`;
  const storagePath = path.join(STORAGE_DIR, fileName_sanitized);

  // Save to disk
  await fs.promises.writeFile(storagePath, imageBuffer);

  // Get image dimensions
  const dimensions = await getImageDimensions(imageBuffer, mimeType);

  // Save metadata to database
  const db = await getDb();
  if (db) {
    await db.insert(questionImages).values({
      questionId,
      userId,
      originalFileName: fileName,
      storageKey,
      storagePath,
      fileSize: imageBuffer.length,
      mimeType,
      width: dimensions.width,
      height: dimensions.height,
    });
  }

  return {
    storageKey,
    storagePath,
    fileSize: imageBuffer.length,
    width: dimensions.width,
    height: dimensions.height,
  };
}

/**
 * Get image URL for serving
 */
export function getImageUrl(storageKey: string): string {
  return `/api/images/${storageKey}`;
}

/**
 * Delete image from storage
 */
export async function deleteImage(storageKey: string): Promise<void> {
  const db = await getDb();
  if (!db) throw new Error("Database not available");

  // Get image metadata
  const images = await db
    .select()
    .from(questionImages)
    .where(eq(questionImages.storageKey, storageKey));

  if (!images.length) {
    throw new Error("Image not found");
  }

  const image = images[0];

  // Delete from disk
  if (fs.existsSync(image.storagePath)) {
    await fs.promises.unlink(image.storagePath);
  }

  // Delete from database
  await db
    .delete(questionImages)
    .where(eq(questionImages.storageKey, storageKey));
}

/**
 * Get image from storage
 */
export async function getImageFromStorage(
  storageKey: string
): Promise<{ buffer: Buffer; mimeType: string } | null> {
  const db = await getDb();
  if (!db) return null;

  const images = await db
    .select()
    .from(questionImages)
    .where(eq(questionImages.storageKey, storageKey));

  if (!images.length) {
    return null;
  }

  const image = images[0];

  if (!fs.existsSync(image.storagePath)) {
    return null;
  }

  const buffer = await fs.promises.readFile(image.storagePath);
  return {
    buffer,
    mimeType: image.mimeType,
  };
}

/**
 * Get file extension from MIME type
 */
function getFileExtension(mimeType: string): string {
  const extensionMap: { [key: string]: string } = {
    "image/jpeg": ".jpg",
    "image/jpg": ".jpg",
    "image/png": ".png",
    "image/webp": ".webp",
    "image/gif": ".gif",
  };

  return extensionMap[mimeType] || ".bin";
}

/**
 * Calculate total storage used by user
 */
export async function calculateUserImageStorageUsage(
  userId: number
): Promise<number> {
  const db = await getDb();
  if (!db) return 0;

  const images = await db
    .select()
    .from(questionImages)
    .where(eq(questionImages.userId, userId));

  return images.reduce((total: number, img: any) => total + img.fileSize, 0);
}

/**
 * Cleanup orphaned images (images of deleted questions)
 */
export async function cleanupOrphanedImages(): Promise<void> {
  const db = await getDb();
  if (!db) return;

  // This would need a proper implementation to find orphaned images
  // For now, just log
  console.log("Cleanup orphaned images - to be implemented");
}
