/**
 * Admin alerts by e-mail (D-70) to ADMIN_EMAILS: new site errors (browser or API), payment problems and good
 * news (new subscription, Pix paid). Throttled so a burst never floods the inbox: the same alert at most once
 * every 15 minutes and at most 6 alerts of a kind per hour (Redis counters).
 */
import { createHash } from 'node:crypto';
import type { Mailer } from './mailer.js';
import { adminAlertEmail } from './emails.js';

export type AlertKind = 'error' | 'billing' | 'good';

/** The Redis calls we use (ioredis-compatible; a fake in tests) */
export interface AlertStore {
  set(key: string, value: string, ex: 'EX', seconds: number, nx: 'NX'): Promise<'OK' | null>;
  incr(key: string): Promise<number>;
  expire(key: string, seconds: number): Promise<number>;
}

export interface Alerts {
  notify(kind: AlertKind, title: string, lines: string[]): Promise<boolean>;
}

export const ALERT_REPEAT_S = 15 * 60;
export const ALERTS_PER_HOUR = 6;

export function createAlerts(o: { mailer?: Mailer | null; store: AlertStore; emails: string[]; siteUrl: string; log?: { warn: (o: object, m: string) => void } }): Alerts {
  return {
    async notify(kind, title, lines) {
      if (!o.mailer?.enabled || !o.emails.length) return false;
      try {
        const id = createHash('sha1').update(`${kind}|${title}`).digest('hex').slice(0, 16);
        if ((await o.store.set(`alert:once:${id}`, '1', 'EX', ALERT_REPEAT_S, 'NX')) !== 'OK') return false;
        const hourKey = `alert:hour:${kind}:${new Date().toISOString().slice(0, 13)}`;
        const n = await o.store.incr(hourKey);
        if (n === 1) await o.store.expire(hourKey, 3700);
        if (n > ALERTS_PER_HOUR) return false;
        await o.mailer.send(o.emails.join(', '), adminAlertEmail({ kind, title, lines, url: `${o.siteUrl}/admin`, support: o.mailer.support }));
        return true;
      } catch (err) {
        o.log?.warn({ err: (err as Error).message }, 'admin alert failed');
        return false;
      }
    },
  };
}

/** Billing log kinds worth an e-mail, with the subject line */
export const BILLING_ALERTS: Record<string, { kind: AlertKind; title: string }> = {
  payment_failed: { kind: 'billing', title: '💳 Pagamento de assinatura falhou ou atrasou' },
  duplicate_refund_failed: { kind: 'billing', title: '⚠️ Falha ao reembolsar assinatura duplicada' },
  unknown_customer: { kind: 'billing', title: '❓ Pagamento sem conta correspondente' },
  customer_mismatch: { kind: 'billing', title: '⚠️ Pagamento não confere com a conta' },
  sync_error: { kind: 'billing', title: '⚠️ Erro ao sincronizar pagamento' },
  pix_refunded: { kind: 'billing', title: '↩️ Pix devolvido no Mercado Pago' },
  mp_claim: { kind: 'billing', title: '📣 Reclamação no Mercado Pago' },
  mp_chargeback: { kind: 'billing', title: '🚨 Contestação (chargeback) no Mercado Pago' },
  subscription_ended: { kind: 'billing', title: '🔚 Assinatura encerrada' },
  subscribed: { kind: 'good', title: '🎉 Nova assinatura no cartão' },
  pix_paid: { kind: 'good', title: '⚡ Pix pago' },
};
