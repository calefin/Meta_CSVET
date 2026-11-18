import { z } from "zod";
import { protectedProcedure, router } from "../_core/trpc";
import { TRPCError } from "@trpc/server";
import { getDb } from "../db";
import { users } from "../../drizzle/schema";
import { eq } from "drizzle-orm";
import { storagePut } from "../storage";
import { createRequire } from "module";
const require = createRequire(import.meta.url);
const sharp = require("sharp") as any;

export const userProfileRouter = router({
  // ── Atualizar dados do perfil ─────────────────────────────────────────────
  update: protectedProcedure
    .input(z.object({
      name:  z.string().min(2).max(100).optional(),
      phone: z.string().max(20).optional(),
      cpf:   z.string().max(14).optional(),
    }))
    .mutation(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });

      const updateData: Record<string, string | null> = {};
      if (input.name  !== undefined) updateData.name  = input.name.trim();
      if (input.phone !== undefined) updateData.phone = input.phone.replace(/\D/g, "").slice(0, 20) || null;
      if (input.cpf   !== undefined) updateData.cpf   = input.cpf.replace(/\D/g, "").slice(0, 11) || null;

      if (Object.keys(updateData).length === 0) return { success: true };

      await db.update(users).set(updateData as any).where(eq(users.id, ctx.user.id));
      return { success: true };
    }),

  // ── Upload de avatar ──────────────────────────────────────────────────────
  uploadAvatar: protectedProcedure
    .input(z.object({
      base64:   z.string().max(4 * 1024 * 1024), // max ~3 MB base64
      mimeType: z.string(),
    }))
    .mutation(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });

      const buffer = Buffer.from(input.base64, "base64");

      // Convert to 200×200 WebP circular-friendly square
      const webp = await sharp(buffer)
        .resize(200, 200, { fit: "cover", position: "centre" })
        .webp({ quality: 85 })
        .toBuffer();

      const key = `avatars/user_${ctx.user.id}_${Date.now()}.webp`;
      const url = await storagePut(key, webp, "image/webp");

      if (!url) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR", message: "Falha ao salvar avatar" });

      await db.update(users).set({ avatarUrl: url } as any).where(eq(users.id, ctx.user.id));
      return { url };
    }),

  // ── Remover avatar ────────────────────────────────────────────────────────
  removeAvatar: protectedProcedure.mutation(async ({ ctx }) => {
    const db = await getDb();
    if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR" });
    await db.update(users).set({ avatarUrl: null } as any).where(eq(users.id, ctx.user.id));
    return { success: true };
  }),
});
