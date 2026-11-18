/**
 * Storage Quota Management Types
 */

export interface StorageQuotaInfo {
  quotaLimitMB: number; // Total quota in MB
  usedStorageMB: number; // Used storage in MB
  remainingMB: number; // Available space
  usedPercentage: number; // 0-100
}

export interface StorageQuotaStatus {
  status: "ok" | "warning" | "exceeded";
  message: string;
  recommendedAction?: string;
}

export interface UploadQuotaCheckResult {
  canUpload: boolean;
  message: string;
  estimatedFinalUsage?: number;
}

/**
 * Quota tiers and pricing
 */
export const STORAGE_QUOTA_TIERS = [
  {
    id: "free",
    name: "Plano Gratuito",
    quotaMB: 100,
    monthlyPrice: 0,
    description: "Até 100MB de armazenamento",
  },
  {
    id: "basic",
    name: "Plano Básico",
    quotaMB: 500,
    monthlyPrice: 9.9,
    description: "Até 500MB de armazenamento",
  },
  {
    id: "pro",
    name: "Plano Pro",
    quotaMB: 2000,
    monthlyPrice: 29.9,
    description: "Até 2GB de armazenamento",
  },
  {
    id: "enterprise",
    name: "Plano Enterprise",
    quotaMB: 10000,
    monthlyPrice: 99.9,
    description: "Até 10GB de armazenamento",
  },
];

/**
 * Get quota status based on usage
 */
export function getQuotaStatus(quotaInfo: StorageQuotaInfo): StorageQuotaStatus {
  if (quotaInfo.usedPercentage >= 100) {
    return {
      status: "exceeded",
      message: "Você excedeu sua cota de armazenamento",
      recommendedAction: "Atualize seu plano para continuar armazenando imagens",
    };
  }

  if (quotaInfo.usedPercentage >= 80) {
    return {
      status: "warning",
      message: `Você está usando ${quotaInfo.usedPercentage.toFixed(1)}% da sua cota`,
      recommendedAction: "Considere atualizar seu plano",
    };
  }

  return {
    status: "ok",
    message: `${quotaInfo.remainingMB.toFixed(1)}MB disponível`,
  };
}

/**
 * Check if upload is allowed
 */
export function canUploadFile(
  fileSizeMB: number,
  quotaInfo: StorageQuotaInfo
): UploadQuotaCheckResult {
  const newTotal = quotaInfo.usedStorageMB + fileSizeMB;

  if (newTotal > quotaInfo.quotaLimitMB) {
    return {
      canUpload: false,
      message: `Arquivo muito grande. Você tem apenas ${quotaInfo.remainingMB.toFixed(1)}MB disponível.`,
    };
  }

  return {
    canUpload: true,
    message: "Você pode enviar este arquivo",
    estimatedFinalUsage: newTotal,
  };
}

/**
 * Format bytes to MB
 */
export function bytesToMB(bytes: number): number {
  return bytes / (1024 * 1024);
}

/**
 * Format storage size for display
 */
export function formatStorageSize(bytes: number): string {
  const mb = bytesToMB(bytes);
  if (mb >= 1000) {
    return `${(mb / 1024).toFixed(1)} GB`;
  }
  return `${mb.toFixed(1)} MB`;
}
