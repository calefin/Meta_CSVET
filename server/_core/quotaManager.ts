/**
 * Storage Quota Management
 * Manages user storage quotas and usage
 */

import { getDb } from "../db";
import { userStorageQuotas } from "../db";
import { eq } from "drizzle-orm";
import {
  StorageQuotaInfo,
  bytesToMB,
  UploadQuotaCheckResult,
  canUploadFile as validateQuotaCheck,
} from "../../shared/_core/storageTypes";

/**
 * Initialize storage quota for new user
 */
export async function initializeUserStorageQuota(
  userId: number,
  quotaLimitMB: number = 100
): Promise<void> {
  const db = await getDb();
  if (!db) throw new Error("Database not available");

  try {
    await db.insert(userStorageQuotas).values({
      userId,
      quotaLimitMB,
      usedStorageMB: 0,
    });
  } catch (error) {
    // Quota already exists, ignore
    console.log(`Quota already exists for user ${userId}`);
  }
}

/**
 * Get user's storage quota info
 */
export async function getUserStorageQuota(
  userId: number
): Promise<StorageQuotaInfo> {
  const db = await getDb();
  if (!db) throw new Error("Database not available");

  const quota = await db
    .select()
    .from(userStorageQuotas)
    .where(eq(userStorageQuotas.userId, userId))
    .limit(1);

  if (!quota.length) {
    // Initialize if doesn't exist
    await initializeUserStorageQuota(userId);
    return {
      quotaLimitMB: 100,
      usedStorageMB: 0,
      remainingMB: 100,
      usedPercentage: 0,
    };
  }

  const q = quota[0];
  return {
    quotaLimitMB: q.quotaLimitMB,
    usedStorageMB: q.usedStorageMB,
    remainingMB: Math.max(0, q.quotaLimitMB - q.usedStorageMB),
    usedPercentage: (q.usedStorageMB / q.quotaLimitMB) * 100,
  };
}

/**
 * Check if user can upload a file of given size
 */
export async function canUserUploadFile(
  userId: number,
  fileSizeBytes: number
): Promise<UploadQuotaCheckResult> {
  const quotaInfo = await getUserStorageQuota(userId);
  const fileSizeMB = bytesToMB(fileSizeBytes);
  return validateQuotaCheck(fileSizeMB, quotaInfo);
}

/**
 * Update user's used storage
 */
export async function updateUserStorageUsage(
  userId: number,
  deltaBytes: number
): Promise<void> {
  const db = await getDb();
  if (!db) throw new Error("Database not available");

  const deltaMB = bytesToMB(deltaBytes);
  const quota = await getUserStorageQuota(userId);
  const newUsed = Math.max(0, quota.usedStorageMB + deltaMB);

  await db
    .update(userStorageQuotas)
    .set({
      usedStorageMB: newUsed,
      updatedAt: new Date(),
    })
    .where(eq(userStorageQuotas.userId, userId));
}

/**
 * Upgrade user's quota
 */
export async function upgradeUserQuota(
  userId: number,
  newQuotaMB: number
): Promise<void> {
  const db = await getDb();
  if (!db) throw new Error("Database not available");

  const quota = await getUserStorageQuota(userId);
  if (newQuotaMB <= quota.quotaLimitMB) {
    throw new Error("New quota must be larger than current quota");
  }

  await db
    .update(userStorageQuotas)
    .set({
      quotaLimitMB: newQuotaMB,
      updatedAt: new Date(),
    })
    .where(eq(userStorageQuotas.userId, userId));
}

/**
 * Get available quota tiers for user
 */
export function getAvailableUpgrades(
  currentQuotaMB: number
): Array<{ quotaMB: number; price: number; label: string }> {
  const tiers = [
    { quotaMB: 500, price: 9.9, label: "500MB - R$ 9,90/mês" },
    { quotaMB: 2000, price: 29.9, label: "2GB - R$ 29,90/mês" },
    { quotaMB: 10000, price: 99.9, label: "10GB - R$ 99,90/mês" },
  ];

  return tiers.filter((t) => t.quotaMB > currentQuotaMB);
}
