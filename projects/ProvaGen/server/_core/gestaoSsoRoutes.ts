import type { Express, Request, Response } from "express";
import { sdk } from "./sdk";
import { mintGestaoSsoToken } from "./gestaoSso";
import { ENV } from "./env";

/**
 * Ponte de entrada pro módulo de gestão de alunos (Laravel) — o professor
 * clica em algo no prof-aid, cai aqui autenticado (cookie de sessão do
 * ProvaGen já validado), a gente veste um token de curta duração e manda
 * ele pro /sso do Laravel, que faz Auth::guard('admin')->login() direto.
 */
export function registerGestaoSsoRoutes(app: Express) {
  app.get("/api/gestao/sso", async (req: Request, res: Response) => {
    try {
      const user = await sdk.authenticateRequest(req);

      if (!user.email) {
        res.status(400).send("Conta sem e-mail cadastrado — não é possível entrar no módulo de gestão.");
        return;
      }

      const token = await mintGestaoSsoToken(user.id, user.email);
      const redirectUrl = `${ENV.gestaoBaseUrl}/sso?token=${encodeURIComponent(token)}`;

      res.redirect(302, redirectUrl);
    } catch (error) {
      console.error("[Gestao SSO] Falha ao iniciar SSO:", error);
      res.status(401).send("Sessão inválida. Faça login novamente no prof-aid.");
    }
  });
}
