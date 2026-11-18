import { SignJWT } from "jose";
import { ENV } from "./env";

const SSO_TOKEN_TTL_MS = 60_000; // curto de propósito: só serve pra um redirect imediato

/**
 * Token de propósito único pro SSO do módulo de gestão (Laravel, D:\ProvaGen\gestao).
 * Não reaproveita SDKServer.createSessionToken/signSession (aquele é o cookie de
 * sessão do próprio ProvaGen) — segredo e formato de payload são só desse fluxo.
 */
export async function mintGestaoSsoToken(userId: number, email: string): Promise<string> {
  if (!ENV.gestaoSsoSecret) {
    throw new Error("GESTAO_SSO_SECRET não configurado");
  }

  const secretKey = new TextEncoder().encode(ENV.gestaoSsoSecret);
  const expirationSeconds = Math.floor((Date.now() + SSO_TOKEN_TTL_MS) / 1000);

  return new SignJWT({ sub: String(userId), email })
    .setProtectedHeader({ alg: "HS256", typ: "JWT" })
    .setExpirationTime(expirationSeconds)
    .sign(secretKey);
}
