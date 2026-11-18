import { COOKIE_NAME, ONE_YEAR_MS } from "@shared/const";
import { getSessionCookieOptions } from "./_core/cookies";
import { systemRouter } from "./_core/systemRouter";
import { publicProcedure, protectedProcedure, router } from "./_core/trpc";
import { z } from "zod";
import { TRPCError } from "@trpc/server";
import { storagePut } from "./storage";
import { getUserByEmail, createLocalUser, getUserProfile, upsertUserProfile } from "./db";
import { hashPassword, verifyPassword } from "./_core/password";
import { sdk } from "./_core/sdk";
import { verifyRecaptcha } from "./_core/recaptcha";
import { questionsRouter } from "./routers/questions";
import { examsRouter } from "./routers/exams";
import { pdfRouter } from "./routers/pdf";
import { answerSheetsRouter } from "./routers/answer-sheets";
import { institutionsRouter } from "./routers/institutions";
import { classesRouter } from "./routers/classes";
import { studentsRouter } from "./routers/students";
import { gestaoRouter } from "./routers/gestao";
import { sharingRouter } from "./routers/sharing";
import { answerSheetTemplatesRouter } from "./routers/answer-sheet-templates";
import { examTemplatesRouter } from "./routers/exam-templates";
import { blogRouter } from "./routers/blog";
import { reportsRouter } from "./routers/reports";
import { adminRouter } from "./routers/admin";
import { qtiRouter } from "./routers/qti";
import { ticketsRouter } from "./routers/tickets";
import { quotesRouter } from "./routers/quotes";
import { billingRouter } from "./routers/billing";
import { mobileRouter } from "./routers/mobile";

export const appRouter = router({
  tickets: ticketsRouter,
  quotes: quotesRouter,
  billing: billingRouter,
  system: systemRouter,
  mobile: mobileRouter,
  auth: router({
    me: publicProcedure.query(opts => opts.ctx.user),
    signup: publicProcedure
      .input(z.object({
        email: z.string().email(),
        password: z.string().min(8),
        name: z.string().optional(),
        recaptchaToken: z.string().optional(),
      }))
      .mutation(async ({ ctx, input }) => {
        // Verify reCAPTCHA when a secret key is configured
        if (process.env.RECAPTCHA_SECRET_KEY) {
          const token = input.recaptchaToken ?? "";
          const valid = await verifyRecaptcha(token);
          if (!valid) {
            throw new TRPCError({
              code: "FORBIDDEN",
              message: "Verificação reCAPTCHA falhou. Por favor, tente novamente.",
            });
          }
        }

        const normalizedEmail = input.email.trim().toLowerCase();
        const existing = await getUserByEmail(normalizedEmail);
        if (existing) {
          throw new TRPCError({
            code: "CONFLICT",
            message: "Este email já está em uso.",
          });
        }

        const passwordHash = hashPassword(input.password);
        const openId = `local:${normalizedEmail}`;

        await createLocalUser({
          email: normalizedEmail,
          passwordHash,
          name: input.name?.trim() || null,
        });

        const sessionToken = await sdk.createSessionToken(openId, {
          name: input.name?.trim() || normalizedEmail,
          expiresInMs: ONE_YEAR_MS,
        });

        const cookieOptions = getSessionCookieOptions(ctx.req);
        ctx.res.cookie(COOKIE_NAME, sessionToken, {
          ...cookieOptions,
          maxAge: ONE_YEAR_MS,
        });

        return { success: true } as const;
      }),

    login: publicProcedure
      .input(z.object({
        email: z.string().email(),
        password: z.string().min(8),
        recaptchaToken: z.string().optional(),
      }))
      .mutation(async ({ ctx, input }) => {
        // Verify reCAPTCHA when a secret key is configured
        if (process.env.RECAPTCHA_SECRET_KEY) {
          const token = input.recaptchaToken ?? "";
          const valid = await verifyRecaptcha(token);
          if (!valid) {
            throw new TRPCError({
              code: "FORBIDDEN",
              message: "Verificação reCAPTCHA falhou. Por favor, tente novamente.",
            });
          }
        }

        const normalizedEmail = input.email.trim().toLowerCase();
        const user = await getUserByEmail(normalizedEmail);

        if (!user || !verifyPassword(input.password, user.passwordHash)) {
          throw new TRPCError({
            code: "UNAUTHORIZED",
            message: "Email ou senha incorretos.",
          });
        }

        const sessionToken = await sdk.createSessionToken(user.openId, {
          name: user.name || normalizedEmail,
          expiresInMs: ONE_YEAR_MS,
        });

        const cookieOptions = getSessionCookieOptions(ctx.req);
        ctx.res.cookie(COOKIE_NAME, sessionToken, {
          ...cookieOptions,
          maxAge: ONE_YEAR_MS,
        });

        return { success: true } as const;
      }),

    logout: publicProcedure.mutation(({ ctx }) => {
      const cookieOptions = getSessionCookieOptions(ctx.req);
      ctx.res.clearCookie(COOKIE_NAME, { ...cookieOptions, maxAge: -1 });
      return {
        success: true,
      } as const;
    }),

    // Get Google login URL
    getGoogleLoginUrl: publicProcedure.query(() => {
      return {
        url: "/api/auth/google",
      };
    }),

    // Verify Google session after OAuth callback
    verifyGoogleSession: publicProcedure.query(async ({ ctx }) => {
      try {
        const user = await sdk.authenticateRequest(ctx.req);
        if (user) {
          return { success: true, user };
        } else {
          return { success: false, user: null };
        }
      } catch {
        return { success: false, user: null };
      }
    }),
  }),

  profile: router({
    me: protectedProcedure.query(async ({ ctx }) => {
      const profile = await getUserProfile(ctx.user.id);
      return { user: ctx.user, profile };
    }),

    update: protectedProcedure
      .input(z.object({
        phone: z.string().optional(),
        university: z.string().optional(),
        disciplineColors: z.string().optional(), // JSON string
      }))
      .mutation(async ({ ctx, input }) => {
        await upsertUserProfile(ctx.user.id, {
          phone: input.phone,
          university: input.university,
          ...(input.disciplineColors !== undefined && { disciplineColors: input.disciplineColors }),
        });
        return { success: true };
      }),

    uploadLogo: protectedProcedure
      .input(z.object({
        fileName: z.string(),
        fileData: z.string(),
        mimeType: z.string(),
      }))
      .mutation(async ({ ctx, input }) => {
        try {
          const buffer = Buffer.from(input.fileData, "base64");
          const storageKey = `users/${ctx.user.id}/logo/${Date.now()}-${input.fileName}`;
          const { url } = await storagePut(storageKey, buffer, input.mimeType);

          await upsertUserProfile(ctx.user.id, {
            logoUrl: url,
            logoStorageKey: storageKey,
          });

          return { success: true, url };
        } catch (error) {
          console.error("Logo upload error:", error);
          throw new TRPCError({
            code: "INTERNAL_SERVER_ERROR",
            message: "Falha ao fazer upload do logotipo",
          });
        }
      }),
  }),

  questions: questionsRouter,
  exams: examsRouter,
  pdf: pdfRouter,
  answerSheets: answerSheetsRouter,
  institutions: institutionsRouter,
  classes: classesRouter,
  students: studentsRouter,
  gestao: gestaoRouter,
  sharing: sharingRouter,
  answerSheetTemplates: answerSheetTemplatesRouter,
  examTemplates: examTemplatesRouter,
  blog: blogRouter,
  reports: reportsRouter,
  admin: adminRouter,
  qti: qtiRouter,
});

export type AppRouter = typeof appRouter;
