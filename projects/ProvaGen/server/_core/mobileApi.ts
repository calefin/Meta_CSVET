import type { Express, Request, Response } from "express";
import { getUserByEmail } from "../db";
import { verifyPassword } from "./password";
import { sdk } from "./sdk";

export function registerMobileApiRoutes(app: Express) {
  // POST /api/auth/login
  app.post("/api/auth/login", async (req: Request, res: Response) => {
    try {
      const { email, password } = req.body;

      if (!email || !password) {
        res.status(400).json({ message: "E-mail e senha são obrigatórios." });
        return;
      }

      const normalizedEmail = email.trim().toLowerCase();
      const user = await getUserByEmail(normalizedEmail);

      if (!user || !verifyPassword(password, user.passwordHash)) {
        res.status(401).json({ message: "Credenciais inválidas ou e-mail não cadastrado." });
        return;
      }

      // Generate session token matching COOKIE_NAME/SDK structure
      const sessionToken = await sdk.createSessionToken(user.openId, {
        name: user.name || normalizedEmail,
      });

      const hasPremium = user.plan && user.plan !== "free";
      const planName = hasPremium ? `Premium Ativo (${user.plan.toUpperCase()})` : "Assinatura Gratuita";

      res.status(200).json({
        token: sessionToken,
        email: normalizedEmail,
        subscription: {
          status: hasPremium ? "Premium" : "Gratuito",
          plan: planName,
          expires_at: "2036-12-31T23:59:59Z", // Far future placeholder
        },
      });
    } catch (error) {
      console.error("[Mobile API] Login error:", error);
      res.status(500).json({ message: "Erro interno do servidor ao autenticar." });
    }
  });

  // GET /api/subscription/validate
  app.get("/api/subscription/validate", async (req: Request, res: Response) => {
    try {
      const user = await sdk.authenticateRequest(req);
      if (!user) {
        res.status(401).json({ message: "Sessão expirada. Faça login novamente." });
        return;
      }

      const hasPremium = user.plan && user.plan !== "free";
      const planName = hasPremium ? `Premium Ativo (${user.plan.toUpperCase()})` : "Assinatura Gratuita";

      res.status(200).json({
        status: hasPremium ? "active" : "expired",
        plan: planName,
        expires_at: "2036-12-31T23:59:59Z",
      });
    } catch (error) {
      console.warn("[Mobile API] Subscription validation failed", String(error));
      res.status(401).json({ message: "Sessão expirada. Faça login novamente." });
    }
  });
}
