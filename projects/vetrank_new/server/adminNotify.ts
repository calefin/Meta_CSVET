/**
 * Para quem vão os avisos internos (tickets, alertas de cobrança, auditoria).
 * Ordem: ADMIN_NOTIFY_EMAILS (.env, separado por vírgula) → todos os usuários role=admin com e-mail
 * → endereço legado. Assim ninguém precisa editar código para mudar o destinatário.
 */
import { getDb } from "./db";
import { sendAdminAlertEmail } from "./email";

const LEGACY_ADMIN_EMAIL = "calefi@csvet.com.br";
let cache: { at: number; emails: string[] } | null = null;

export async function getAdminEmails(): Promise<string[]> {
  if (cache && Date.now() - cache.at < 5 * 60 * 1000) return cache.emails;
  const fromEnv = (process.env.ADMIN_NOTIFY_EMAILS ?? "")
    .split(",").map(s => s.trim().toLowerCase()).filter(Boolean);
  let emails = fromEnv;
  if (emails.length === 0) {
    try {
      const db = await getDb();
      if (db) {
        const [rows] = await db.execute(
          `SELECT DISTINCT LOWER(email) AS email FROM users
           WHERE role = 'admin' AND email IS NOT NULL AND email <> '' AND email NOT LIKE 'merged+%'`
        ) as any;
        emails = (rows as any[]).map(r => r.email).filter(Boolean);
      }
    } catch (e: any) {
      console.warn("[AdminNotify] could not load admin emails:", e?.message);
    }
  }
  if (emails.length === 0) emails = [LEGACY_ADMIN_EMAIL];
  cache = { at: Date.now(), emails };
  return emails;
}

export function escapeHtml(s: unknown): string {
  return String(s ?? "").replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]!));
}

/** Nunca lança — alerta não pode derrubar webhook nem request. */
export async function notifyAdmins(subject: string, bodyHtml: string): Promise<void> {
  try {
    const to = await getAdminEmails();
    await sendAdminAlertEmail(to, subject, bodyHtml);
  } catch (e: any) {
    console.error(`[AdminNotify] FAILED to send "${subject}":`, e?.message);
  }
}
