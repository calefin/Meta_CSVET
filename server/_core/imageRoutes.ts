/**
 * Image Serving Routes
 * Handles serving optimized question images from local storage
 */

import { Express, Request, Response } from "express";
import { getImageFromStorage } from "./imageStorage";

export function registerImageRoutes(app: Express): void {
  /**
   * Serve image by storage key
   * GET /api/images/:storageKey
   */
  app.get("/api/images/:storageKey", async (req: Request, res: Response) => {
    try {
      const { storageKey } = req.params;

      if (!storageKey || storageKey.length !== 32) {
        return res.status(400).json({ error: "Invalid storage key" });
      }

      const imageData = await getImageFromStorage(storageKey);

      if (!imageData) {
        return res.status(404).json({ error: "Image not found" });
      }

      // Set appropriate headers
      res.setHeader("Content-Type", imageData.mimeType);
      res.setHeader("Cache-Control", "public, max-age=31536000"); // Cache for 1 year
      res.setHeader("Content-Length", imageData.buffer.length);

      res.send(imageData.buffer);
    } catch (error) {
      console.error("Error serving image:", error);
      res.status(500).json({ error: "Failed to serve image" });
    }
  });

  /**
   * Health check endpoint for images
   * GET /api/images/health
   */
  app.get("/api/images/health", (req: Request, res: Response) => {
    res.json({ status: "ok", service: "image-server" });
  });
}
