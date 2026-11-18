import { adminProcedure, router } from "../_core/trpc";
import { z } from "zod";
import { TRPCError } from "@trpc/server";
import {
  getAllUsers,
  countAllUsers,
  updateUserRoleById,
  deleteUserById,
  getSystemStats,
  getAllUserEmails,
  getDb,
} from "../db";
import { sendEmail } from "../_core/email";
import { applyPlanToUser } from "./billing";

export const adminRouter = router({
  getStats: adminProcedure.query(async () => {
    return await getSystemStats();
  }),

  // Só booleanos — nunca expõe os valores dos secrets, mesmo pra admin.
  getSystemConfig: adminProcedure.query(async () => {
    return {
      recaptchaEnabled: !!process.env.RECAPTCHA_SECRET_KEY && !!process.env.VITE_RECAPTCHA_SITE_KEY,
      smtpConfigured: !!process.env.SMTP_USER && !!process.env.SMTP_PASS,
    };
  }),

  getUsers: adminProcedure
    .input(z.object({
      limit: z.number().min(1).max(200).default(50),
      offset: z.number().min(0).default(0),
    }))
    .query(async ({ input }) => {
      const [users, total] = await Promise.all([
        getAllUsers({ limit: input.limit, offset: input.offset }),
        countAllUsers(),
      ]);
      return { users, total };
    }),

  updateUserRole: adminProcedure
    .input(z.object({
      userId: z.number(),
      role: z.enum(["user", "admin"]),
    }))
    .mutation(async ({ input, ctx }) => {
      if (input.userId === ctx.user.id) {
        throw new TRPCError({ code: "BAD_REQUEST", message: "Você não pode alterar seu próprio perfil." });
      }
      await updateUserRoleById(input.userId, input.role);
      return { success: true };
    }),

  updateUserPlan: adminProcedure
    .input(z.object({
      userId: z.number(),
      plan: z.enum(["free", "basic", "pro", "enterprise"]),
    }))
    .mutation(async ({ input }) => {
      const db = await getDb();
      if (!db) throw new TRPCError({ code: "INTERNAL_SERVER_ERROR", message: "Database not available" });
      // Concessão manual (ex.: liberar o módulo de gestão pra um professor em teste),
      // fora do fluxo normal de pagamento via Mercado Pago em billing.ts.
      await applyPlanToUser(db, input.userId, input.plan);
      return { success: true };
    }),

  deleteUser: adminProcedure
    .input(z.object({ userId: z.number() }))
    .mutation(async ({ input, ctx }) => {
      if (input.userId === ctx.user.id) {
        throw new TRPCError({ code: "BAD_REQUEST", message: "Você não pode excluir sua própria conta." });
      }
      await deleteUserById(input.userId);
      return { success: true };
    }),

  sendMarketingEmail: adminProcedure
    .input(z.object({
      subject: z.string().min(1),
      html: z.string().min(1),
      text: z.string().optional(),
      targetEmails: z.array(z.string().email()).optional(),
    }))
    .mutation(async ({ input }) => {
      const recipients = input.targetEmails?.length
        ? input.targetEmails.map(e => ({ email: e, name: null }))
        : await getAllUserEmails();

      if (recipients.length === 0) {
        return { sent: 0, failed: 0 };
      }

      let sent = 0;
      let failed = 0;

      for (const recipient of recipients) {
        const personalizedHtml = input.html.replace(/\{\{nome\}\}/gi, recipient.name ?? "usuário");
        const ok = await sendEmail({
          to: recipient.email,
          subject: input.subject,
          html: personalizedHtml,
          text: input.text,
        });
        if (ok) sent++; else failed++;
      }

      return { sent, failed };
    }),
});
