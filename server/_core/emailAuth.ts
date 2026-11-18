/**
 * Email/password authentication routes.
 * POST /api/auth/email/register  — create account
 * POST /api/auth/email/login     — sign in
 * POST /api/auth/email/forgot    — request password reset email
 * POST /api/auth/email/reset     — apply new password with token
 */
import type { Express, Request, Response } from "express";
import bcrypt from "bcryptjs";
import { nanoid } from "nanoid";
import * as db from "../db";
import { getDb } from "../db";
import { users } from "../../drizzle/schema";
import { eq, and, isNotNull, sql } from "drizzle-orm";
import { getSessionCookieOptions } from "./cookies";
import { sdk } from "./sdk";
import { COOKIE_NAME, ONE_YEAR_MS } from "@shared/const";
import { sendPasswordResetEmail, sendEmailVerificationEmail } from "../email";
import { ENV } from "./env";

const BCRYPT_ROUNDS = 12;
const RESET_TOKEN_TTL_MS = 60 * 60 * 1000; // 1 hour
const VERIFY_TOKEN_TTL_MS = 48 * 60 * 60 * 1000; // 48 hours
const RECAPTCHA_SCORE_THRESHOLD = 0.5;

async function verifyRecaptcha(token: string | undefined, expectedAction: string): Promise<boolean> {
  // Skip verification if secret key is not configured
  if (!ENV.recaptchaSecretKey) return true;

  // Reject missing tokens in production, but bypass in development for easy local testing
  if (!token) return !ENV.isProduction;

  const params = new URLSearchParams({
    secret: ENV.recaptchaSecretKey,
    response: token,
  });
  const res = await fetch("https://www.google.com/recaptcha/api/siteverify", {
    method: "POST",
    body: params,
  });
  const data = await res.json() as { success: boolean; score: number; action: string };
  return data.success && data.score >= RECAPTCHA_SCORE_THRESHOLD && data.action === expectedAction;
}

function getClientIp(req: Request): string {
  const forwarded = req.headers["x-forwarded-for"];
  if (typeof forwarded === "string") return forwarded.split(",")[0].trim();
  return req.ip ?? "unknown";
}

export function registerEmailAuthRoutes(app: Express) {
  // ── Register ──────────────────────────────────────────────────────────────
  app.post("/api/auth/email/register", async (req: Request, res: Response) => {
    const { name, email, password, recaptchaToken } = req.body ?? {};

    if (!email || !password || !name) {
      res.status(400).json({ error: "Nome, email e senha são obrigatórios." });
      return;
    }
    if (typeof password !== "string" || password.length < 8) {
      res.status(400).json({ error: "A senha deve ter pelo menos 8 caracteres." });
      return;
    }
    if (!await verifyRecaptcha(recaptchaToken, "register")) {
      res.status(400).json({ error: "Verificação de segurança falhou. Tente novamente." });
      return;
    }

    const dbConn = await getDb();
    if (!dbConn) { res.status(500).json({ error: "Erro interno." }); return; }

    // Check if email already registered
    const existing = await dbConn.select({ id: users.id, passwordHash: users.passwordHash, loginMethod: users.loginMethod })
      .from(users)
      .where(eq(users.email, email.toLowerCase()))
      .limit(1);

    if (existing.length > 0) {
      const viaGoogle = !existing[0].passwordHash && existing[0].loginMethod === "google";
      res.status(409).json({
        error: viaGoogle
          ? "Este e-mail já está cadastrado com Google. Use \"Entrar com Google\" ou \"Esqueceu a senha?\" para criar uma senha."
          : "Este e-mail já está cadastrado. Faça login ou use \"Esqueceu a senha?\".",
      });
      return;
    }

    const passwordHash = await bcrypt.hash(password, BCRYPT_ROUNDS);
    const openId = `email_${nanoid(16)}`;
    const verificationToken = nanoid(32);
    const verificationTokenExpiresAt = new Date(Date.now() + VERIFY_TOKEN_TTL_MS);

    await db.upsertUser({
      openId,
      name,
      email: email.toLowerCase(),
      loginMethod: "email",
      lastSignedIn: new Date(),
    });

    await dbConn.update(users)
      .set({ passwordHash, emailVerificationToken: verificationToken, emailVerificationTokenExpiresAt: verificationTokenExpiresAt })
      .where(eq(users.openId, openId));

    sendEmailVerificationEmail(email.toLowerCase(), name, verificationToken)
      .catch(e => console.error(`[EmailAuth] Verification email to ${email} failed:`, e?.message));

    res.json({ success: true, requiresVerification: true });
  });

  // ── Login ─────────────────────────────────────────────────────────────────
  app.post("/api/auth/email/login", async (req: Request, res: Response) => {
    const { email, password, recaptchaToken } = req.body ?? {};

    if (!email || !password) {
      res.status(400).json({ error: "E-mail e senha são obrigatórios." });
      return;
    }
    if (!await verifyRecaptcha(recaptchaToken, "login")) {
      res.status(400).json({ error: "Verificação de segurança falhou. Tente novamente." });
      return;
    }

    const dbConn = await getDb();
    if (!dbConn) { res.status(500).json({ error: "Erro interno." }); return; }

    // Same email can exist on more than one row (legacy Google + email accounts) — use the one
    // that actually has a password.
    const [user] = await dbConn.select({
      openId: users.openId,
      name: users.name,
      passwordHash: users.passwordHash,
      emailVerifiedAt: users.emailVerifiedAt,
    })
      .from(users)
      .where(and(eq(users.email, email.toLowerCase()), isNotNull(users.passwordHash)))
      .limit(1);

    if (!user?.passwordHash) {
      res.status(401).json({ error: "E-mail ou senha incorretos." });
      return;
    }

    const valid = await bcrypt.compare(password, user.passwordHash);
    if (!valid) {
      res.status(401).json({ error: "E-mail ou senha incorretos." });
      return;
    }

    if (!user.emailVerifiedAt) {
      res.status(403).json({ error: "Verifique seu e-mail antes de entrar. Cheque sua caixa de entrada.", requiresVerification: true });
      return;
    }

    await db.upsertUser({ openId: user.openId, lastSignedIn: new Date() });

    const sessionToken = await sdk.createSessionToken(user.openId, {
      name: user.name || "",
      expiresInMs: ONE_YEAR_MS,
    });

    const cookieOptions = getSessionCookieOptions(req);
    res.cookie(COOKIE_NAME, sessionToken, { ...cookieOptions, maxAge: ONE_YEAR_MS });
    res.json({ success: true });
  });

  // ── Forgot password ───────────────────────────────────────────────────────
  app.post("/api/auth/email/forgot", async (req: Request, res: Response) => {
    const { email } = req.body ?? {};
    if (!email) { res.status(400).json({ error: "E-mail obrigatório." }); return; }

    const dbConn = await getDb();
    if (!dbConn) { res.status(500).json({ error: "Erro interno." }); return; }

    // Prefer the row that already has a password; otherwise (Google-only account) the reset
    // creates one, enabling email+password login on that same account.
    const [user] = await dbConn.select({ openId: users.openId, name: users.name })
      .from(users)
      .where(eq(users.email, email.toLowerCase()))
      .orderBy(sql`${users.passwordHash} IS NULL`)
      .limit(1);

    // Always respond 200 to avoid email enumeration
    res.json({ success: true });
    if (!user) return;

    const token = nanoid(32);
    const expiresAt = new Date(Date.now() + RESET_TOKEN_TTL_MS);

    await dbConn.update(users)
      .set({ passwordResetToken: token, passwordResetTokenExpiresAt: expiresAt })
      .where(eq(users.openId, user.openId));

    await sendPasswordResetEmail(email, user.name || "Usuário", token)
      .catch(e => console.error(`[EmailAuth] Password reset email to ${email} failed:`, e?.message));
  });

  // ── Reset password ────────────────────────────────────────────────────────
  app.post("/api/auth/email/reset", async (req: Request, res: Response) => {
    const { token, password } = req.body ?? {};
    if (!token || !password) {
      res.status(400).json({ error: "Token e nova senha são obrigatórios." });
      return;
    }
    if (typeof password !== "string" || password.length < 8) {
      res.status(400).json({ error: "A senha deve ter pelo menos 8 caracteres." });
      return;
    }

    const dbConn = await getDb();
    if (!dbConn) { res.status(500).json({ error: "Erro interno." }); return; }

    const [user] = await dbConn.select({
      openId: users.openId,
      name: users.name,
      passwordResetTokenExpiresAt: users.passwordResetTokenExpiresAt,
    })
      .from(users)
      .where(eq(users.passwordResetToken, token))
      .limit(1);

    if (!user || !user.passwordResetTokenExpiresAt || user.passwordResetTokenExpiresAt < new Date()) {
      res.status(400).json({ error: "Token inválido ou expirado." });
      return;
    }

    const passwordHash = await bcrypt.hash(password, BCRYPT_ROUNDS);
    await dbConn.update(users)
      .set({
        passwordHash,
        passwordResetToken: null,
        passwordResetTokenExpiresAt: null,
        // Clicking the reset link proves ownership of the inbox — without this, a user who set a
        // password via "forgot password" stays locked out by the "verify your email" gate.
        emailVerifiedAt: sql`COALESCE(${users.emailVerifiedAt}, NOW())`,
        emailVerificationToken: null,
        emailVerificationTokenExpiresAt: null,
      })
      .where(eq(users.openId, user.openId));

    const sessionToken = await sdk.createSessionToken(user.openId, {
      name: user.name || "",
      expiresInMs: ONE_YEAR_MS,
    });

    const cookieOptions = getSessionCookieOptions(req);
    res.cookie(COOKIE_NAME, sessionToken, { ...cookieOptions, maxAge: ONE_YEAR_MS });
    res.json({ success: true });
  });

  // ── Verify email (link from email) ────────────────────────────────────────
  app.get("/api/auth/email/verify", async (req: Request, res: Response) => {
    const token = req.query.token as string | undefined;
    if (!token) { res.redirect("/?verified=error"); return; }

    const dbConn = await getDb();
    if (!dbConn) { res.redirect("/?verified=error"); return; }

    const [user] = await dbConn.select({
      openId: users.openId,
      name: users.name,
      emailVerificationTokenExpiresAt: users.emailVerificationTokenExpiresAt,
    })
      .from(users)
      .where(and(eq(users.emailVerificationToken, token)))
      .limit(1);

    if (!user || !user.emailVerificationTokenExpiresAt || user.emailVerificationTokenExpiresAt < new Date()) {
      res.redirect("/?verified=expired");
      return;
    }

    await dbConn.update(users)
      .set({ emailVerifiedAt: new Date(), emailVerificationToken: null, emailVerificationTokenExpiresAt: null })
      .where(eq(users.openId, user.openId));

    const sessionToken = await sdk.createSessionToken(user.openId, {
      name: user.name || "",
      expiresInMs: ONE_YEAR_MS,
    });
    const cookieOptions = getSessionCookieOptions(req);
    res.cookie(COOKIE_NAME, sessionToken, { ...cookieOptions, maxAge: ONE_YEAR_MS });
    res.redirect("/?verified=ok");
  });

  // ── Resend verification email ─────────────────────────────────────────────
  app.post("/api/auth/email/verify/resend", async (req: Request, res: Response) => {
    const { email } = req.body ?? {};
    if (!email) { res.status(400).json({ error: "E-mail obrigatório." }); return; }

    const dbConn = await getDb();
    if (!dbConn) { res.status(500).json({ error: "Erro interno." }); return; }

    // Don't filter on loginMethod: it's overwritten on every login, so a user who once signed in
    // with Google would never get the email again. Target the row that has a password.
    const [user] = await dbConn.select({
      openId: users.openId,
      name: users.name,
      emailVerifiedAt: users.emailVerifiedAt,
      emailVerificationTokenExpiresAt: users.emailVerificationTokenExpiresAt,
    })
      .from(users)
      .where(and(eq(users.email, email.toLowerCase()), isNotNull(users.passwordHash)))
      .limit(1);

    res.json({ success: true }); // always 200 to avoid enumeration
    if (!user || user.emailVerifiedAt) return;

    // Cooldown: 60s between sends (token issued-at = expiresAt - TTL)
    const issuedAt = user.emailVerificationTokenExpiresAt
      ? user.emailVerificationTokenExpiresAt.getTime() - VERIFY_TOKEN_TTL_MS
      : 0;
    if (Date.now() - issuedAt < 60_000) return;

    const newToken = nanoid(32);
    const expiresAt = new Date(Date.now() + VERIFY_TOKEN_TTL_MS);
    await dbConn.update(users)
      .set({ emailVerificationToken: newToken, emailVerificationTokenExpiresAt: expiresAt })
      .where(eq(users.openId, user.openId));

    sendEmailVerificationEmail(email.toLowerCase(), user.name || "Usuário", newToken)
      .catch(e => console.error(`[EmailAuth] Verification resend to ${email} failed:`, e?.message));
  });
}
