/**
 * Image Optimization Utilities (Client-side)
 * Optimizes images before upload to reduce file size
 */

/**
 * Compress image using Canvas API
 */
export async function compressImage(
  file: File,
  maxWidth: number = 1920,
  maxHeight: number = 1440,
  quality: number = 0.85
): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.readAsDataURL(file);
    reader.onload = (event) => {
      try {
        const img = new Image();
        img.src = event.target?.result as string;

        img.onload = () => {
          const canvas = document.createElement("canvas");

          // Calculate new dimensions
          let width = img.width;
          let height = img.height;

          if (width > maxWidth || height > maxHeight) {
            const scale = Math.min(maxWidth / width, maxHeight / height);
            width = Math.floor(width * scale);
            height = Math.floor(height * scale);
          }

          canvas.width = width;
          canvas.height = height;

          const ctx = canvas.getContext("2d");
          if (!ctx) {
            reject(new Error("Could not get canvas context"));
            return;
          }

          ctx.drawImage(img, 0, 0, width, height);

          // Convert to base64
          const compressedBase64 = canvas.toDataURL(
            file.type || "image/jpeg",
            quality
          );

          resolve(compressedBase64);
        };

        img.onerror = () => {
          reject(new Error("Could not load image"));
        };
      } catch (error) {
        reject(error);
      }
    };

    reader.onerror = () => {
      reject(new Error("Could not read file"));
    };
  });
}

/**
 * Estimate compressed file size
 */
export function estimateCompressedSize(base64String: string): number {
  // Remove data URL prefix if present
  const cleanBase64 = base64String.replace(/^data:[^;]+;base64,/, "");
  // Each character represents 6 bits, so 4 chars = 3 bytes
  return Math.ceil((cleanBase64.length * 3) / 4);
}

/**
 * Format bytes to readable size
 */
export function formatFileSize(bytes: number): string {
  if (bytes === 0) return "0 Bytes";
  const k = 1024;
  const sizes = ["Bytes", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + " " + sizes[i];
}

/**
 * Batch compress multiple images
 */
export async function compressImages(
  files: File[],
  maxWidth: number = 1920,
  maxHeight: number = 1440,
  quality: number = 0.85
): Promise<string[]> {
  return Promise.all(
    files.map((file) => compressImage(file, maxWidth, maxHeight, quality))
  );
}
